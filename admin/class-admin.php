<?php
/**
 * Admin functionality.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Admin
 */
class Mantis_SEO_Admin {

	/**
	 * Settings page instance.
	 *
	 * @var Mantis_SEO_Settings_Page
	 */
	private $settings_page;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		$this->settings_page = new Mantis_SEO_Settings_Page();
		new Mantis_SEO_Redirects_Page();
		new Mantis_SEO_Metabox();
	}

	/**
	 * Add admin menu.
	 */
	public function add_menu() {
		add_menu_page(
			__( 'Mantis SEO', 'mantis-seo' ),
			__( 'Mantis SEO', 'mantis-seo' ),
			'manage_options',
			'mantis-seo',
			array( $this, 'render_settings_page' ),
			'dashicons-search',
			30
		);
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		$this->settings_page->render();
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'mantis-seo' ) === false && 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'mantis-seo-admin',
			MANTIS_SEO_URL . 'assets/css/admin.css',
			array(),
			MANTIS_SEO_VERSION
		);

		if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
			wp_enqueue_script(
				'mantis-seo-metabox',
				MANTIS_SEO_URL . 'assets/js/admin-metabox.js',
				array(),
				MANTIS_SEO_VERSION,
				true
			);
		}
	}
}
