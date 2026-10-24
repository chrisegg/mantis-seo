<?php
/**
 * GitHub Releases update checker (installable zip asset).
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Updater
 */
class Mantis_SEO_Updater {

	const CACHE_KEY = 'mantis_seo_github_release';

	const CACHE_DURATION = 43200;

	/**
	 * Bootstrap hooks.
	 *
	 * Runs in wp-cron as well as wp-admin. WordPress refreshes plugin updates from cron,
	 * and cron is not an admin request.
	 */
	public static function init() {
		// Update URI is https://github.com/... so core calls this inside wp_update_plugins().
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'filter_github_update' ), 10, 4 );
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_update' ) );
		add_filter( 'http_request_args', array( __CLASS__, 'authorize_github_request' ), 10, 2 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
		add_action(
			'in_plugin_update_message-' . MANTIS_SEO_BASENAME,
			array( __CLASS__, 'update_message' )
		);
		add_action( 'admin_notices', array( __CLASS__, 'notice_update_check_failed' ) );
		add_action( 'load-update-core.php', array( __CLASS__, 'clear_release_cache' ) );
		add_filter( 'upgrader_pre_install', array( __CLASS__, 'prevent_deactivation_during_update' ), 5, 2 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'remove_cron_filter_after_update' ), 10, 2 );
	}

	/**
	 * Supply update data for the Update URI header (host github.com).
	 *
	 * WordPress requires a `version` field. It compares that with the installed plugin
	 * and only then shows the update notice.
	 *
	 * @param array|false $update      Existing update data.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @param string[]    $locales     Installed locales.
	 * @return array|false
	 */
	public static function filter_github_update( $update, $plugin_data, $plugin_file, $locales ) {
		unset( $plugin_data, $locales );
		if ( MANTIS_SEO_BASENAME !== $plugin_file ) {
			return $update;
		}
		$payload = self::release_update_payload( $plugin_file );
		return null === $payload ? $update : $payload;
	}

	/**
	 * Add a read token when this private repository is requested.
	 *
	 * @param array  $args Request args.
	 * @param string $url  Request URL.
	 * @return array
	 */
	public static function authorize_github_request( $args, $url ) {
		$token = self::github_token();
		if ( '' === $token || ! is_string( $url ) || ! self::is_our_github_url( $url ) ) {
			return $args;
		}
		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = array();
		}
		$args['headers']['Authorization'] = 'Bearer ' . $token;
		return $args;
	}

	/**
	 * Warn on the Plugins and Updates screens when GitHub refused the release check.
	 */
	public static function notice_update_check_failed() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'update-core' ), true ) ) {
			return;
		}
		$code = get_transient( 'mantis_seo_github_release_error' );
		if ( false === $code || '' === (string) $code ) {
			return;
		}
		if ( '404' === (string) $code || '401' === (string) $code ) {
			$message = __( 'Mantis SEO could not see a GitHub release, so no update notice is shown. The repository is private. Add a read-only token to wp-config.php: define( \'MANTIS_SEO_GITHUB_TOKEN\', \'github_pat_...\' );', 'mantis-seo' );
		} else {
			$message = sprintf(
				/* translators: %s: HTTP status code, or 0 when the request failed. */
				__( 'Mantis SEO could not reach GitHub to check for updates (HTTP %s).', 'mantis-seo' ),
				(string) $code
			);
		}
		echo '<div class="notice notice-warning"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Clear cached release when the Updates screen loads.
	 */
	public static function clear_release_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Skip core deactivation for this plugin during upgrade (same idea as background updates).
	 *
	 * @param bool|WP_Error $response   Passed through.
	 * @param array         $hook_extra Hook extra from Plugin_Upgrader.
	 * @return bool|WP_Error
	 */
	public static function prevent_deactivation_during_update( $response, $hook_extra ) {
		if ( isset( $hook_extra['type'], $hook_extra['plugin'] ) && 'plugin' === $hook_extra['type'] && MANTIS_SEO_BASENAME === $hook_extra['plugin'] ) {
			add_filter( 'wp_doing_cron', '__return_true' );
		}
		return $response;
	}

	/**
	 * Remove temporary wp_doing_cron override after upgrade.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $options  Process options.
	 */
	public static function remove_cron_filter_after_update( $upgrader, $options ) {
		if ( empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
			return;
		}
		$plugins = array();
		if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			$plugins = $options['plugins'];
		} elseif ( ! empty( $options['plugin'] ) ) {
			$plugins = array( $options['plugin'] );
		}
		if ( in_array( MANTIS_SEO_BASENAME, $plugins, true ) ) {
			remove_filter( 'wp_doing_cron', '__return_true' );
		}
	}

	/**
	 * Inject update into the plugins update transient.
	 *
	 * @param object $transient Update plugins transient.
	 * @return object
	 */
	public static function check_for_update( $transient ) {
		if ( ! is_object( $transient ) || ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			return $transient;
		}

		$payload = self::release_update_payload( MANTIS_SEO_BASENAME );
		if ( null === $payload ) {
			return $transient;
		}
		if ( version_compare( $payload['new_version'], MANTIS_SEO_VERSION, '<=' ) ) {
			return $transient;
		}

		$transient->response[ MANTIS_SEO_BASENAME ] = (object) $payload;
		return $transient;
	}

	/**
	 * Update payload for one installed copy, or null when GitHub has no release.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return array<string, string>|null
	 */
	private static function release_update_payload( $plugin_file ) {
		$release = self::get_release_data();
		if ( ! is_array( $release ) || empty( $release['version'] ) ) {
			return null;
		}

		$headers  = self::plugin_headers();
		$latest   = $release['version'];
		$tag_name = isset( $release['tag_name'] ) ? $release['tag_name'] : 'v' . $latest;
		$req_wp   = ! empty( $headers['requires'] ) ? $headers['requires'] : '6.0';
		$req_php  = ! empty( $headers['requires_php'] ) ? $headers['requires_php'] : '7.4';

		return array(
			'slug'         => 'mantis-seo',
			'plugin'       => $plugin_file,
			'version'      => $latest,
			'new_version'  => $latest,
			'url'          => 'https://github.com/' . MANTIS_SEO_GITHUB_REPO,
			'package'      => self::package_url( $tag_name ),
			'requires'     => $req_wp,
			'requires_php' => $req_php,
			'tested'       => ! empty( $headers['tested'] ) ? $headers['tested'] : '',
		);
	}

	/**
	 * plugins_api callback for the plugin details modal.
	 *
	 * @param false|object|array $result Default result.
	 * @param string               $action API action.
	 * @param object               $args   Request args.
	 * @return false|object
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		$slug = is_object( $args ) ? ( isset( $args->slug ) ? $args->slug : '' ) : ( isset( $args['slug'] ) ? $args['slug'] : '' );
		if ( ! self::is_our_slug( $slug ) ) {
			return $result;
		}

		$release = self::get_release_data();
		if ( ! is_array( $release ) ) {
			$release = array();
		}

		$headers = self::plugin_headers();
		$version = ! empty( $release['version'] ) ? $release['version'] : MANTIS_SEO_VERSION;
		$date    = isset( $release['date'] ) ? $release['date'] : '';
		$tag     = isset( $release['tag_name'] ) ? $release['tag_name'] : 'v' . $version;

		$changelog_raw = isset( $release['changelog'] ) ? $release['changelog'] : '';
		if ( '' === trim( wp_strip_all_tags( $changelog_raw ) ) ) {
			$changelog_raw = self::get_changelog_from_readme();
		}
		$changelog = self::format_changelog_html( $changelog_raw, $version );

		$req_wp  = ! empty( $headers['requires'] ) ? $headers['requires'] : '6.0';
		$req_php = ! empty( $headers['requires_php'] ) ? $headers['requires_php'] : '7.4';
		$tested  = ! empty( $headers['tested'] ) ? $headers['tested'] : get_bloginfo( 'version' );

		$description = self::readme_excerpt_description();
		$installation = '<p>' . esc_html__( 'Install from the Plugins screen or upload the plugin folder to wp-content/plugins/, then activate Mantis SEO.', 'mantis-seo' ) . '</p>';

		$github_url = 'https://github.com/' . MANTIS_SEO_GITHUB_REPO;
		$package    = self::package_url( $tag );

		return (object) array(
			'name'           => ! empty( $headers['name'] ) ? $headers['name'] : 'Mantis SEO',
			'slug'           => 'mantis-seo',
			'version'        => $version,
			'author'         => ! empty( $headers['author'] ) ? $headers['author'] : '',
			'author_profile' => ! empty( $headers['author_uri'] ) ? $headers['author_uri'] : '',
			'requires'       => $req_wp,
			'requires_php'   => $req_php,
			'tested'         => $tested,
			'last_updated'   => $date,
			'homepage'       => ! empty( $headers['plugin_uri'] ) ? $headers['plugin_uri'] : $github_url,
			'download_link'  => $package,
			'trunk'          => $package,
			'sections'       => array(
				'description'  => $description ? $description : '<p>' . esc_html__( 'Lightweight SEO for WordPress.', 'mantis-seo' ) . '</p>',
				'installation' => $installation,
				'changelog'    => $changelog,
			),
		);
	}

	/**
	 * Note under the plugin row when an update exists.
	 */
	public static function update_message() {
		echo '<br /><strong>' . esc_html__( 'Note:', 'mantis-seo' ) . '</strong> ';
		echo esc_html__( 'This update is delivered from GitHub. Back up your site before updating.', 'mantis-seo' );
	}

	/**
	 * Fetch latest release JSON (cached).
	 *
	 * @return array<string, string>|false
	 */
	private static function get_release_data() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached && is_array( $cached ) ) {
			delete_transient( 'mantis_seo_github_release_error' );
			return $cached;
		}

		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
		);
		$token = self::github_token();
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$url  = 'https://api.github.com/repos/' . MANTIS_SEO_GITHUB_REPO . '/releases/latest';
		$resp = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => $headers,
			)
		);

		$code = is_wp_error( $resp ) ? 0 : (int) wp_remote_retrieve_response_code( $resp );
		if ( is_wp_error( $resp ) || 200 !== $code ) {
			set_transient( 'mantis_seo_github_release_error', (string) $code, HOUR_IN_SECONDS );
			return false;
		}
		delete_transient( 'mantis_seo_github_release_error' );

		$body = wp_remote_retrieve_body( $resp );
		$data = json_decode( $body, true );
		if ( empty( $data['tag_name'] ) ) {
			return false;
		}

		$version = ltrim( (string) $data['tag_name'], 'vV' );
		$date    = '';
		if ( ! empty( $data['published_at'] ) ) {
			$date = gmdate( 'Y-m-d', strtotime( $data['published_at'] ) );
		}

		$release = array(
			'version'   => $version,
			'tag_name'  => (string) $data['tag_name'],
			'date'      => $date,
			'changelog' => isset( $data['body'] ) ? (string) $data['body'] : '',
		);

		set_transient( self::CACHE_KEY, $release, self::CACHE_DURATION );

		return $release;
	}

	/**
	 * Read-only GitHub token for a private repository.
	 *
	 * Set define( 'MANTIS_SEO_GITHUB_TOKEN', 'github_pat_...' ); in wp-config.php.
	 *
	 * @return string
	 */
	private static function github_token() {
		$token = '';
		if ( defined( 'MANTIS_SEO_GITHUB_TOKEN' ) && is_string( MANTIS_SEO_GITHUB_TOKEN ) ) {
			$token = trim( MANTIS_SEO_GITHUB_TOKEN );
		}
		$filtered = apply_filters( 'mantis_seo_github_token', $token );
		return is_string( $filtered ) ? trim( $filtered ) : '';
	}

	/**
	 * Whether a URL is this plugin's GitHub API or release download.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function is_our_github_url( $url ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( 'api.github.com' === $host && false !== strpos( $path, '/repos/' . MANTIS_SEO_GITHUB_REPO . '/' ) ) {
			return true;
		}
		return 'github.com' === $host && false !== strpos( $path, '/' . MANTIS_SEO_GITHUB_REPO . '/releases/download/' );
	}

	/**
	 * Installable zip URL (release asset built by GitHub Actions).
	 *
	 * @param string $tag_name Exact tag from GitHub (e.g. v1.0.1).
	 * @return string
	 */
	private static function package_url( $tag_name ) {
		$encoded_tag = implode(
			'/',
			array_map( 'rawurlencode', explode( '/', $tag_name ) )
		);
		return 'https://github.com/' . MANTIS_SEO_GITHUB_REPO . '/releases/download/' . $encoded_tag . '/' . MANTIS_SEO_RELEASE_ZIP;
	}

	/**
	 * @param string $slug plugins_api slug.
	 */
	private static function is_our_slug( $slug ) {
		if ( '' === $slug ) {
			return false;
		}
		if ( 'mantis-seo' === $slug ) {
			return true;
		}
		if ( MANTIS_SEO_BASENAME === $slug ) {
			return true;
		}
		return 0 === strpos( (string) $slug, 'mantis-seo' );
	}

	/**
	 * @return array<string, string>
	 */
	private static function plugin_headers() {
		static $headers = null;
		if ( null === $headers ) {
			$headers = get_file_data(
				MANTIS_SEO_FILE,
				array(
					'name'         => 'Plugin Name',
					'plugin_uri'   => 'Plugin URI',
					'author'       => 'Author',
					'author_uri'   => 'Author URI',
					'requires'     => 'Requires at least',
					'requires_php' => 'Requires PHP',
					'tested'       => 'Tested up to',
				),
				'plugin'
			);
		}
		return $headers;
	}

	/**
	 * First section of README.md after the title (markdown → simple HTML for modal).
	 *
	 * @return string
	 */
	private static function readme_excerpt_description() {
		$path = MANTIS_SEO_PATH . 'README.md';
		if ( ! is_readable( $path ) ) {
			return '';
		}
		$content = file_get_contents( $path );
		if ( false === $content ) {
			return '';
		}
		if ( preg_match( '/^#\s+[^\n]+\n+(.+?)\n+---/s', $content, $m ) ) {
			$text = trim( $m[1] );
			return self::simple_markdown_to_html( $text );
		}
		return '';
	}

	/**
	 * ## Changelog block from README.md (fallback).
	 *
	 * @return string
	 */
	private static function get_changelog_from_readme() {
		$path = MANTIS_SEO_PATH . 'README.md';
		if ( ! is_readable( $path ) ) {
			return '';
		}
		$content = file_get_contents( $path );
		if ( false === $content ) {
			return '';
		}
		$pos = stripos( $content, '## Changelog' );
		if ( false === $pos ) {
			return '';
		}
		$rest = substr( $content, $pos + strlen( '## Changelog' ) );
		$rest = ltrim( $rest, " \t\r\n" );
		if ( preg_match( '/^##\s+/m', $rest, $m, PREG_OFFSET_CAPTURE ) ) {
			return trim( substr( $rest, 0, $m[0][1] ) );
		}
		return trim( $rest );
	}

	/**
	 * Turn GitHub / readme changelog text into list HTML (safe for core kses).
	 *
	 * @param string $raw     Raw markdown or plain text.
	 * @param string $version Version label for heading.
	 * @return string
	 */
	private static function format_changelog_html( $raw, $version ) {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '<p>' . esc_html__( 'See release notes on GitHub for this version.', 'mantis-seo' ) . '</p>';
		}
		$lines = explode( "\n", $raw );
		$items = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$line    = preg_replace( '/^[-*•]\s+/', '', $line );
			$line    = preg_replace( '/^#{1,6}\s+/', '', $line );
			$items[] = '<li>' . esc_html( $line ) . '</li>';
		}
		if ( empty( $items ) ) {
			return '<p>' . esc_html( $raw ) . '</p>';
		}
		return '<h2>' . sprintf(
			/* translators: %s: plugin version */
			esc_html__( 'Mantis SEO %s', 'mantis-seo' ),
			esc_html( $version )
		) . '</h2><ul>' . implode( '', $items ) . '</ul>';
	}

	/**
	 * Minimal markdown for description (bold, code, lists).
	 *
	 * @param string $text Markdown-ish body.
	 * @return string
	 */
	private static function simple_markdown_to_html( $text ) {
		$blocks = preg_split( '/\n\n+/', trim( $text ) );
		$html   = '';
		foreach ( $blocks as $block ) {
			$block = esc_html( $block );
			$block = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $block );
			$block = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $block );
			$html .= '<p>' . nl2br( $block ) . '</p>';
		}
		return $html;
	}
}
