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
	 * Bootstrap hooks (admin only).
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
		add_action(
			'in_plugin_update_message-' . MANTIS_SEO_BASENAME,
			array( __CLASS__, 'update_message' )
		);
		add_action( 'load-update-core.php', array( __CLASS__, 'clear_release_cache' ) );
		add_filter( 'upgrader_pre_install', array( __CLASS__, 'prevent_deactivation_during_update' ), 5, 2 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'remove_cron_filter_after_update' ), 10, 2 );
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
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = self::get_release_data();
		if ( ! is_array( $release ) || empty( $release['version'] ) ) {
			return $transient;
		}

		$current = MANTIS_SEO_VERSION;
		$latest  = $release['version'];
		if ( version_compare( $latest, $current, '<=' ) ) {
			return $transient;
		}

		$headers = self::plugin_headers();
		$req_wp  = ! empty( $headers['requires'] ) ? $headers['requires'] : '6.0';
		$req_php = ! empty( $headers['requires_php'] ) ? $headers['requires_php'] : '7.4';
		if ( version_compare( get_bloginfo( 'version' ), $req_wp, '<' ) || version_compare( PHP_VERSION, $req_php, '<' ) ) {
			return $transient;
		}

		$plugin_file = MANTIS_SEO_BASENAME;
		$tag_name    = isset( $release['tag_name'] ) ? $release['tag_name'] : 'v' . $latest;
		$package     = self::package_url( $tag_name );

		$changelog_raw = isset( $release['changelog'] ) ? $release['changelog'] : '';
		if ( '' === trim( wp_strip_all_tags( $changelog_raw ) ) ) {
			$changelog_raw = self::get_changelog_from_readme();
		}

		$transient->response[ $plugin_file ] = (object) array(
			'slug'         => 'mantis-seo',
			'plugin'       => $plugin_file,
			'new_version'  => $latest,
			'url'          => 'https://github.com/' . MANTIS_SEO_GITHUB_REPO,
			'package'      => $package,
			'requires'     => $req_wp,
			'requires_php' => $req_php,
			'tested'       => ! empty( $headers['tested'] ) ? $headers['tested'] : get_bloginfo( 'version' ),
			'last_updated' => isset( $release['date'] ) ? $release['date'] : '',
			'sections'     => array(
				'description' => self::readme_excerpt_description(),
				'changelog'   => self::format_changelog_html( $changelog_raw, $latest ),
			),
		);

		return $transient;
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
			return $cached;
		}

		$url  = 'https://api.github.com/repos/' . MANTIS_SEO_GITHUB_REPO . '/releases/latest';
		$resp = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
				),
			)
		);

		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return false;
		}

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
