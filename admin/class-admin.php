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
		add_action( 'admin_notices', array( $this, 'notice_search_engines_discouraged' ) );

		$this->settings_page = new Mantis_SEO_Settings_Page();
		new Mantis_SEO_Redirects_Page();
		new Mantis_SEO_Metabox();
	}

	/**
	 * Warn when Settings → Reading has "Discourage search engines" enabled (blog_public = 0).
	 */
	public function notice_search_engines_discouraged() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( 0 !== (int) get_option( 'blog_public' ) ) {
			return;
		}

		$reading_url = admin_url( 'options-reading.php' );
		$link        = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $reading_url ),
			esc_html__( 'Settings → Reading', 'mantis-seo' )
		);

		echo '<div class="notice notice-error"><p>';
		echo '<strong>' . esc_html__( 'Mantis SEO — Serious indexing issue', 'mantis-seo' ) . '</strong> — ';
		printf(
			wp_kses(
				/* translators: %s: HTML link to Settings → Reading */
				__( 'WordPress is set to <strong>discourage search engines</strong> from indexing this site. Public SEO and sitemaps cannot work as intended until this is turned off. Go to %s and uncheck “Discourage search engines from indexing this site” when you are ready to be visible in search results.', 'mantis-seo' ),
				array( 'strong' => array() )
			),
			wp_kses(
				$link,
				array(
					'a' => array(
						'href' => array(),
					),
				)
			)
		);
		echo '</p></div>';
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
		$is_mantis = strpos( $hook, 'mantis-seo' ) !== false;
		$is_post_edit = in_array( $hook, array( 'post.php', 'post-new.php' ), true );
		$is_post_list = ( 'edit.php' === $hook );

		if ( ! $is_mantis && ! $is_post_edit && ! $is_post_list ) {
			return;
		}

		// List screens only use core .notice styles for redirect notices; skip heavy app CSS there.
		if ( $is_mantis || $is_post_edit ) {
			wp_enqueue_style(
				'mantis-seo-admin',
				MANTIS_SEO_URL . 'assets/css/admin.css',
				array(),
				MANTIS_SEO_VERSION
			);
		}

		// Enqueue media library for image upload fields.
		if ( $is_mantis || $is_post_edit ) {
			wp_enqueue_media();
			wp_enqueue_script(
				'mantis-seo-media-upload',
				MANTIS_SEO_URL . 'assets/js/media-upload.js',
				array( 'jquery' ),
				MANTIS_SEO_VERSION,
				true
			);
		}

		wp_register_script(
			'mantis-template-editor',
			MANTIS_SEO_URL . 'assets/js/mantis-template-editor.js',
			array(),
			MANTIS_SEO_VERSION,
			true
		);
		wp_localize_script(
			'mantis-template-editor',
			'mantisSeoTemplateVars',
			array(
				'varLabels' => Mantis_SEO_Meta::get_variable_labels(),
			)
		);

		if ( $is_mantis || $is_post_edit ) {
			wp_enqueue_script( 'mantis-template-editor' );
		}

		if ( $is_post_edit ) {
			wp_enqueue_script(
				'mantis-seo-metabox',
				MANTIS_SEO_URL . 'assets/js/admin-metabox.js',
				array( 'mantis-template-editor' ),
				MANTIS_SEO_VERSION,
				true
			);

			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if (
				$screen
				&& Mantis_SEO_Redirect_Manager::is_redirect_notice_screen()
				&& method_exists( $screen, 'is_block_editor' )
				&& $screen->is_block_editor()
			) {
				wp_enqueue_script(
					'mantis-seo-editor-redirect-notice',
					MANTIS_SEO_URL . 'assets/js/editor-redirect-notice.js',
					array( 'wp-data', 'wp-dom-ready', 'wp-edit-post' ),
					MANTIS_SEO_VERSION,
					true
				);
				wp_localize_script(
					'mantis-seo-editor-redirect-notice',
					'mantisSeoRedirectNotice',
					$this->redirect_notice_script_l10n()
				);
			}
		}

		if ( $is_post_list && Mantis_SEO_Redirect_Manager::is_redirect_notice_screen() ) {
			wp_enqueue_script(
				'mantis-seo-list-redirect-notice',
				MANTIS_SEO_URL . 'assets/js/post-list-redirect-notice.js',
				array( 'jquery', 'wp-a11y' ),
				MANTIS_SEO_VERSION,
				true
			);
			wp_localize_script(
				'mantis-seo-list-redirect-notice',
				'mantisSeoRedirectNotice',
				$this->redirect_notice_script_l10n()
			);
		}

		if ( $is_mantis ) {
			wp_enqueue_script(
				'mantis-seo-admin-app',
				MANTIS_SEO_URL . 'assets/js/admin-app.js',
				array( 'mantis-template-editor' ),
				MANTIS_SEO_VERSION,
				true
			);
		}
	}

	/**
	 * Shared strings / AJAX config for block-editor and post-list redirect notices.
	 *
	 * @return array
	 */
	private function redirect_notice_script_l10n() {
		return array(
			'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'mantis_seo_editor_redirect_notice' ),
			'viewRedirectLabel' => __( 'View redirect', 'mantis-seo' ),
			'dismissNoticeSr'  => __( 'Dismiss this notice.', 'mantis-seo' ),
		);
	}
}
