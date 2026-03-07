<?php
/**
 * Main Mantis SEO plugin class.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Main
 */
class Mantis_SEO_Main {

	/**
	 * Singleton instance.
	 *
	 * @var Mantis_SEO_Main|null
	 */
	private static $instance = null;

	/**
	 * Components.
	 *
	 * @var array
	 */
	private $components = array();

	/**
	 * Get instance.
	 *
	 * @return Mantis_SEO_Main
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_includes();
		$this->init_components();
	}

	/**
	 * Load include files.
	 */
	private function load_includes() {
		require_once MANTIS_SEO_PATH . 'includes/class-redirects.php';
		require_once MANTIS_SEO_PATH . 'includes/class-redirect-manager.php';
		require_once MANTIS_SEO_PATH . 'includes/class-meta.php';
		require_once MANTIS_SEO_PATH . 'includes/class-schema.php';
		require_once MANTIS_SEO_PATH . 'includes/class-sitemap.php';
		require_once MANTIS_SEO_PATH . 'includes/class-sitemap-provider.php';
		require_once MANTIS_SEO_PATH . 'includes/class-sitemap-posts.php';
		require_once MANTIS_SEO_PATH . 'includes/class-sitemap-terms.php';

		if ( is_admin() ) {
			require_once MANTIS_SEO_PATH . 'includes/class-yoast-importer.php';
			require_once MANTIS_SEO_PATH . 'admin/class-admin.php';
			require_once MANTIS_SEO_PATH . 'admin/class-settings-page.php';
			require_once MANTIS_SEO_PATH . 'admin/class-metabox.php';
			require_once MANTIS_SEO_PATH . 'admin/class-redirects-page.php';
		}
	}

	/**
	 * Initialize components.
	 */
	private function init_components() {
		$this->components['meta']              = new Mantis_SEO_Meta();
		$this->components['redirects']          = new Mantis_SEO_Redirects();
		$this->components['redirect_manager']   = new Mantis_SEO_Redirect_Manager();
		$this->components['schema']             = new Mantis_SEO_Schema();
		$this->components['sitemap']  = new Mantis_SEO_Sitemap();

		if ( is_admin() ) {
			$this->components['admin'] = new Mantis_SEO_Admin();
		}
	}

	/**
	 * Get a component.
	 *
	 * @param string $name Component name.
	 * @return object|null
	 */
	public function get_component( $name ) {
		return isset( $this->components[ $name ] ) ? $this->components[ $name ] : null;
	}

	/**
	 * Get settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = self::get_default_settings();
		$saved    = get_option( 'mantis_settings', array() );
		$merged   = wp_parse_args( $saved, $defaults );
		// Migrate legacy schema_same_as array to platform-specific fields.
		if ( ! empty( $saved['schema_same_as'] ) && is_array( $saved['schema_same_as'] ) ) {
			$merged = self::migrate_schema_same_as( $saved['schema_same_as'], $merged );
		}
		// Empty template fields should use defaults - migration/save may have stored ''.
		$template_keys = array( 'title_home', 'title_post', 'title_page', 'metadesc_home', 'metadesc_post', 'metadesc_page' );
		foreach ( $template_keys as $key ) {
			if ( isset( $merged[ $key ] ) && '' === trim( (string) $merged[ $key ] ) && isset( $defaults[ $key ] ) ) {
				$merged[ $key ] = $defaults[ $key ];
			}
		}
		return $merged;
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public static function get_default_settings() {
		return array(
			'title_home'             => '%site_name% - %site_description%',
			'title_post'             => '%post_title% %page% %separator% %site_name%',
			'title_page'             => '%post_title% %page% %separator% %site_name%',
			'separator'              => '|',
			'metadesc_home'          => '',
			'metadesc_post'          => '%excerpt%',
			'metadesc_page'          => '%excerpt%',
			'noindex_author'         => true,
			'noindex_date'           => true,
			'noindex_category'       => false,
			'noindex_tag'             => false,
			'og_default_image'       => '',
			'twitter_card_type'      => 'summary_large_image',
			'google_verify'           => '',
			'bing_verify'            => '',
			'sitemap_enabled'        => true,
			'schema_organization_name' => '',
			'schema_organization_url'  => '',
			'schema_organization_logo'  => '',
			'schema_same_as_facebook'   => '',
			'schema_same_as_instagram'  => '',
			'schema_same_as_x'          => '',
			'schema_same_as_linkedin'   => '',
			'sitemap_post_types'        => array( 'post', 'page' ),
		);
	}

	/**
	 * Migrate legacy schema_same_as array to platform-specific fields.
	 *
	 * @param array $urls     Legacy URL array.
	 * @param array $merged  Current merged settings.
	 * @return array
	 */
	private static function migrate_schema_same_as( $urls, $merged ) {
		foreach ( $urls as $url ) {
			$url = esc_url_raw( $url );
			if ( empty( $url ) ) {
				continue;
			}
			$host = strtolower( (string) ( parse_url( $url, PHP_URL_HOST ) ?: '' ) );
			if ( str_contains( $host, 'facebook.com' ) || str_contains( $host, 'fb.com' ) ) {
				$merged['schema_same_as_facebook'] = $url;
			} elseif ( str_contains( $host, 'instagram.com' ) ) {
				$merged['schema_same_as_instagram'] = $url;
			} elseif ( str_contains( $host, 'twitter.com' ) || str_contains( $host, 'x.com' ) ) {
				$merged['schema_same_as_x'] = $url;
			} elseif ( str_contains( $host, 'linkedin.com' ) ) {
				$merged['schema_same_as_linkedin'] = $url;
			}
		}
		return $merged;
	}

	/**
	 * Save settings.
	 *
	 * @param array $settings Settings to save.
	 * @return bool
	 */
	public static function save_settings( $settings ) {
		return update_option( 'mantis_settings', $settings );
	}

	/**
	 * Run activation tasks.
	 */
	public static function activate() {
		// Create redirect table (will be implemented in step 2).
		require_once MANTIS_SEO_PATH . 'includes/class-redirects.php';
		Mantis_SEO_Redirects::create_table();

		// Set default settings if not exists.
		if ( false === get_option( 'mantis_settings', false ) ) {
			update_option( 'mantis_settings', self::get_default_settings() );
		}

		flush_rewrite_rules();
	}
}
