<?php
/**
 * Global settings page.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Settings_Page
 */
class Mantis_SEO_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'maybe_append_tab_to_settings_redirect' ), 1 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_migrate' ) );
		add_action( 'admin_init', array( $this, 'handle_export' ) );
		add_action( 'admin_init', array( $this, 'handle_import' ) );
	}

	/**
	 * Valid settings UI panel element ids (sidebar tabs).
	 *
	 * @return string[]
	 */
	private function get_settings_panel_ids() {
		return array(
			'mantis-titles',
			'mantis-social',
			'mantis-schema',
			'mantis-sitemap',
			'mantis-advanced',
			'mantis-tools',
		);
	}

	/**
	 * After options.php saves, append mantis_tab to the redirect so the correct panel is shown.
	 */
	public function maybe_append_tab_to_settings_redirect() {
		if ( ! isset( $_POST['option_page'], $_POST['action'] ) || 'update' !== $_POST['action'] ) {
			return;
		}
		if ( 'mantis_seo_settings' !== $_POST['option_page'] ) {
			return;
		}
		if ( empty( $_POST['mantis_active_tab'] ) ) {
			return;
		}
		$tab     = sanitize_key( wp_unslash( $_POST['mantis_active_tab'] ) );
		$allowed = $this->get_settings_panel_ids();
		if ( ! in_array( $tab, $allowed, true ) || 'mantis-titles' === $tab ) {
			return;
		}
		add_filter(
			'wp_redirect',
			static function ( $location ) use ( $tab ) {
				if ( false === strpos( $location, 'page=mantis-seo' ) ) {
					return $location;
				}
				return add_query_arg( 'mantis_tab', $tab, $location );
			},
			10,
			1
		);
	}

	/**
	 * Handle settings export.
	 */
	public function handle_export() {
		if ( ! isset( $_GET['mantis_export'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'mantis_export' ) ) {
			return;
		}

		$settings = Mantis_SEO_Main::get_settings();
		$data    = array(
			'mantis_seo_export' => true,
			'version'          => MANTIS_SEO_VERSION,
			'exported'         => current_time( 'c' ),
			'settings'         => $settings,
		);

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="mantis-seo-settings-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Handle settings import.
	 */
	public function handle_import() {
		if ( ! isset( $_POST['mantis_import'] ) || empty( $_FILES['mantis_import_file']['tmp_name'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'mantis_import' );

		$redirect_url = add_query_arg( 'mantis_tab', 'mantis-tools', admin_url( 'admin.php?page=mantis-seo' ) );
		$file_path    = isset( $_FILES['mantis_import_file']['tmp_name'] ) ? $_FILES['mantis_import_file']['tmp_name'] : '';

		try {
			if ( ! is_uploaded_file( $file_path ) ) {
				throw new Exception( __( 'No valid file was uploaded.', 'mantis-seo' ) );
			}

			$content = file_get_contents( $file_path );
			if ( false === $content || '' === trim( $content ) ) {
				throw new Exception( __( 'Could not read the uploaded file. It may be empty or inaccessible.', 'mantis-seo' ) );
			}

			$imported = $this->parse_import_file( $content );
			if ( empty( $imported ) ) {
				throw new Exception( __( 'No valid settings found in file. Supported formats: Mantis JSON, Yoast INI/text, Yoast Keywords CSV, CSV (key,value).', 'mantis-seo' ) );
			}

			if ( isset( $imported['_import_type'] ) && 'yoast_keywords' === $imported['_import_type'] && ! empty( $imported['_post_updates'] ) ) {
				$count = 0;
				foreach ( $imported['_post_updates'] as $post_id => $meta ) {
					if ( ! empty( $meta['focus_keyword'] ) ) {
						update_post_meta( (int) $post_id, '_mantis_focus_keyword', $meta['focus_keyword'] );
					}
					if ( ! empty( $meta['title'] ) ) {
						update_post_meta( (int) $post_id, '_mantis_title', $meta['title'] );
					}
					if ( isset( $meta['description'] ) && '' !== $meta['description'] ) {
						update_post_meta( (int) $post_id, '_mantis_description', $meta['description'] );
					}
					$count++;
				}
				wp_safe_redirect( add_query_arg( array( 'mantis_imported' => 1, 'mantis_imported_posts' => $count ), $redirect_url ) );
				exit;
			}

			$imported = wp_parse_args( $imported, Mantis_SEO_Main::get_default_settings() );
			$saved    = Mantis_SEO_Main::save_settings( $imported );
			if ( ! $saved ) {
				throw new Exception( __( 'Failed to save settings.', 'mantis-seo' ) );
			}

			wp_safe_redirect( add_query_arg( array( 'mantis_imported' => 1, 'settings-updated' => 1 ), $redirect_url ) );
			exit;
		} catch ( Exception $e ) {
			set_transient( 'mantis_import_error', $e->getMessage(), 60 );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Mantis SEO import error: ' . $e->getMessage() );
			}
			wp_safe_redirect( add_query_arg( 'mantis_import_error', 1, $redirect_url ) );
			exit;
		}
	}

	/**
	 * Parse import file content and return Mantis settings array.
	 *
	 * @param string $content File content.
	 * @return array Mantis settings or empty array if unsupported format.
	 */
	private function parse_import_file( $content ) {
		// Try Mantis JSON first.
		$data = json_decode( $content, true );
		if ( is_array( $data ) && ! empty( $data['mantis_seo_export'] ) && ! empty( $data['settings'] ) ) {
			return $data['settings'];
		}

		// Try Yoast INI format ([wpseo_titles], [wpseo], etc).
		if ( preg_match( '/\[' . preg_quote( 'wpseo', '/' ) . '[\w\-]*\]/', $content ) ) {
			$parsed = $this->parse_yoast_ini( $content );
			if ( ! empty( $parsed ) ) {
				return $parsed;
			}
		}

		// Try Yoast Keywords CSV (ID, seo title, meta description per row).
		$keywords = $this->parse_yoast_keywords_csv( $content );
		if ( ! empty( $keywords ) ) {
			return array(
				'_import_type'   => 'yoast_keywords',
				'_post_updates'  => $keywords,
			);
		}

		// Try CSV (key,value or key;value) for global settings.
		$parsed = $this->parse_csv_settings( $content );
		if ( ! empty( $parsed ) ) {
			return $parsed;
		}

		return array();
	}

	/**
	 * Parse Yoast INI/plain-text export into Mantis settings.
	 *
	 * @param string $content Raw file content.
	 * @return array Mantis settings.
	 */
	private function parse_yoast_ini( $content ) {
		$options = @parse_ini_string( $content, true, INI_SCANNER_RAW );
		if ( ! is_array( $options ) || empty( $options ) ) {
			return array();
		}

		$yoast_to_mantis = array(
			'title-home-wpseo'      => 'title_home',
			'title-post'            => 'title_post',
			'title-page'            => 'title_page',
			'separator'             => 'separator',
			'metadesc-home-wpseo'   => 'metadesc_home',
			'metadesc-post'         => 'metadesc_post',
			'metadesc-page'         => 'metadesc_page',
			'noindex-author-wpseo'  => 'noindex_author',
			'noindex-archive-wpseo' => 'noindex_date',
			'company_name'          => 'schema_organization_name',
			'company_logo'          => 'schema_organization_logo',
		);

		$mantis = array();
		$sections = array( 'wpseo_titles', 'wpseo', 'wpseo_social' );

		foreach ( $sections as $section ) {
			if ( empty( $options[ $section ] ) || ! is_array( $options[ $section ] ) ) {
				continue;
			}
			foreach ( $options[ $section ] as $key => $value ) {
				if ( isset( $yoast_to_mantis[ $key ] ) ) {
					$mantis_key = $yoast_to_mantis[ $key ];
					$val       = is_string( $value ) ? $value : (string) $value;
					if ( in_array( $mantis_key, array( 'title_home', 'title_post', 'title_page', 'metadesc_home', 'metadesc_post', 'metadesc_page', 'separator' ), true ) ) {
						$val = Mantis_SEO_Yoast_Importer::convert_yoast_vars( $val );
					}
					if ( in_array( $mantis_key, array( 'noindex_author', 'noindex_date' ), true ) ) {
						$mantis[ $mantis_key ] = (bool) $value;
					} else {
						$mantis[ $mantis_key ] = sanitize_text_field( $val );
					}
				}
			}
		}

		if ( ! empty( $options['wpseo_social']['og_default_image'] ) ) {
			$mantis['og_default_image'] = esc_url_raw( $options['wpseo_social']['og_default_image'] );
		}
		if ( ! empty( $options['wpseo_social']['twitter_card_type'] ) ) {
			$mantis['twitter_card_type'] = sanitize_text_field( $options['wpseo_social']['twitter_card_type'] );
		}
		if ( ! empty( $options['wpseo']['enable_xml_sitemap'] ) ) {
			$mantis['sitemap_enabled'] = true;
		}

		return $mantis;
	}

	/**
	 * Parse Yoast SEO Keywords CSV export (per-post seo title, meta description).
	 *
	 * @param string $content Raw file content.
	 * @return array List of [post_id => ['title' => '', 'description' => '']] or empty.
	 */
	private function parse_yoast_keywords_csv( $content ) {
		$lines = preg_split( '/[\r\n]+/', trim( $content ), -1, PREG_SPLIT_NO_EMPTY );
		if ( count( $lines ) < 2 ) {
			return array();
		}

		$header = array_map( 'trim', str_getcsv( $lines[0] ) );
		$idx_id       = array_search( 'ID', $header, true );
		$idx_keyphrase = array_search( 'keyphrase', $header, true );
		$idx_seo      = array_search( 'seo title', $header, true );
		$idx_meta     = array_search( 'meta description', $header, true );
		if ( false === $idx_id || ( false === $idx_seo && false === $idx_meta && false === $idx_keyphrase ) ) {
			return array();
		}
		if ( false === $idx_keyphrase ) {
			$idx_keyphrase = -1;
		}
		if ( false === $idx_seo ) {
			$idx_seo = -1;
		}
		if ( false === $idx_meta ) {
			$idx_meta = -1;
		}

		$updates = array();
		for ( $i = 1; $i < count( $lines ); $i++ ) {
			$row = str_getcsv( $lines[ $i ] );
			$post_id = isset( $row[ $idx_id ] ) ? absint( $row[ $idx_id ] ) : 0;
			if ( ! $post_id || ! get_post( $post_id ) ) {
				continue;
			}
			$keyphrase = ( $idx_keyphrase >= 0 && isset( $row[ $idx_keyphrase ] ) ) ? trim( (string) $row[ $idx_keyphrase ] ) : '';
			$title     = ( $idx_seo >= 0 && isset( $row[ $idx_seo ] ) ) ? trim( (string) $row[ $idx_seo ] ) : '';
			$desc      = ( $idx_meta >= 0 && isset( $row[ $idx_meta ] ) ) ? trim( (string) $row[ $idx_meta ] ) : '';
			if ( '' === $keyphrase && '' === $title && '' === $desc ) {
				continue;
			}
			$title = Mantis_SEO_Yoast_Importer::convert_yoast_vars( $title );
			$desc  = Mantis_SEO_Yoast_Importer::convert_yoast_vars( $desc );
			$updates[ $post_id ] = array(
				'focus_keyword' => $keyphrase,
				'title'         => $title,
				'description'   => $desc,
			);
		}
		return $updates;
	}

	/**
	 * Parse CSV (key,value) into Mantis settings.
	 *
	 * @param string $content Raw file content.
	 * @return array Mantis settings.
	 */
	private function parse_csv_settings( $content ) {
		$lines = preg_split( '/[\r\n]+/', trim( $content ), -1, PREG_SPLIT_NO_EMPTY );
		if ( empty( $lines ) ) {
			return array();
		}

		$valid_keys = array(
			'title_home', 'title_post', 'title_page', 'separator',
			'metadesc_home', 'metadesc_post', 'metadesc_page',
			'og_default_image', 'twitter_card_type',
			'noindex_author', 'noindex_date', 'sitemap_enabled',
			'schema_organization_name', 'schema_organization_url', 'schema_organization_logo',
			'schema_same_as_facebook', 'schema_same_as_instagram', 'schema_same_as_x', 'schema_same_as_linkedin',
		);

		$mantis = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$row = str_getcsv( $line );
			if ( count( $row ) < 2 ) {
				continue;
			}
			$key   = sanitize_key( trim( $row[0] ) );
			$value = trim( $row[1] );
			if ( in_array( $key, $valid_keys, true ) ) {
				$mantis[ $key ] = sanitize_text_field( $value );
			}
		}

		return $mantis;
	}

	/**
	 * Handle Yoast migration request.
	 */
	public function handle_migrate() {
		if ( ! isset( $_POST['mantis_migrate_yoast'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && isset( $_GET['mantis_debug'] ) ) {
				error_log( 'Mantis SEO: handle_migrate skipped - no POST mantis_migrate_yoast' );
			}
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Mantis SEO: handle_migrate skipped - user cannot manage_options' );
			}
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'mantis_migrate_yoast' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Mantis SEO: handle_migrate failed - nonce verification failed' );
			}
			return;
		}

		$result = Mantis_SEO_Yoast_Importer::migrate();
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Mantis SEO: migration complete ' . wp_json_encode( $result ) );
		}
		set_transient( 'mantis_migrate_result', $result, 60 );
		wp_safe_redirect( add_query_arg( 'mantis_migrated', 1, admin_url( 'admin.php?page=mantis-seo' ) ) );
		exit;
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting(
			'mantis_seo_settings',
			'mantis_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$sanitized = array();

		$text_fields = array(
			'title_home', 'title_post', 'title_page', 'separator',
			'metadesc_home', 'metadesc_post', 'metadesc_page',
			'og_default_image', 'twitter_card_type',
			'google_verify', 'bing_verify',
			'schema_organization_name', 'schema_organization_url', 'schema_organization_logo',
		);

		foreach ( $text_fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				if ( in_array( $field, array( 'metadesc_home', 'metadesc_post', 'metadesc_page' ), true ) ) {
					$sanitized[ $field ] = sanitize_textarea_field( $input[ $field ] );
				} else {
					$sanitized[ $field ] = sanitize_text_field( $input[ $field ] );
				}
			}
		}

		$bool_fields = array( 'noindex_author', 'noindex_date', 'noindex_category', 'noindex_tag', 'sitemap_enabled' );
		foreach ( $bool_fields as $field ) {
			$sanitized[ $field ] = ! empty( $input[ $field ] );
		}

		$social_fields = array( 'schema_same_as_facebook', 'schema_same_as_instagram', 'schema_same_as_x', 'schema_same_as_linkedin' );
		foreach ( $social_fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$sanitized[ $field ] = esc_url_raw( $input[ $field ] );
			}
		}

		if ( isset( $input['sitemap_post_types'] ) && is_array( $input['sitemap_post_types'] ) ) {
			$valid_types = get_post_types( array( 'public' => true ), 'names' );
			$sanitized['sitemap_post_types'] = array_values( array_intersect( array_map( 'sanitize_key', $input['sitemap_post_types'] ), $valid_types ) );
			if ( empty( $sanitized['sitemap_post_types'] ) ) {
				$sanitized['sitemap_post_types'] = array( 'post', 'page' );
			}
		}

		return wp_parse_args( $sanitized, Mantis_SEO_Main::get_default_settings() );
	}

	/**
	 * SEO template field: pills in the UI, hidden input stores %variable% syntax.
	 *
	 * @param string $field_id   HTML id for the editor (label `for`).
	 * @param string $input_name Name attribute, e.g. mantis_settings[title_post].
	 * @param string $value      Stored template string.
	 * @param bool   $multiline  Taller editor for descriptions (optional line breaks).
	 */
	private function render_title_template_control( $field_id, $input_name, $value, $multiline = false ) {
		$var_labels = Mantis_SEO_Meta::get_variable_dropdown_options();
		$classes    = 'mantis-template-editor mantis-template-editor--settings';
		if ( $multiline ) {
			$classes .= ' mantis-template-editor--multiline';
		}
		echo '<div class="mantis-template-editor-wrap">';
		echo '<input type="hidden" name="' . esc_attr( $input_name ) . '" class="mantis-template-hidden" value="' . esc_attr( $value ) . '" autocomplete="off" />';
		echo '<div id="' . esc_attr( $field_id ) . '" class="' . esc_attr( $classes ) . '" data-mantis-template-editor data-pill-mode="label" tabindex="0" role="textbox"></div>';
		echo '<div class="mantis-template-editor-toolbar">';
		echo '<button type="button" class="button button-small mantis-settings-var-btn" aria-haspopup="true" aria-expanded="false">' . esc_html__( 'Insert variable', 'mantis-seo' ) . '</button>';
		echo '<div class="mantis-var-dropdown mantis-settings-var-dropdown" role="menu" aria-hidden="true">';
		foreach ( $var_labels as $var => $lbl ) {
			echo '<button type="button" role="menuitem" class="mantis-var-option mantis-settings-var-option" data-var="' . esc_attr( $var ) . '">' . esc_html( $lbl ) . '</button>';
		}
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render settings page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings    = Mantis_SEO_Main::get_settings();
		$panel_ids   = $this->get_settings_panel_ids();
		$initial_tab = 'mantis-titles';
		if ( isset( $_GET['mantis_tab'] ) ) {
			$req = sanitize_key( wp_unslash( $_GET['mantis_tab'] ) );
			if ( in_array( $req, $panel_ids, true ) ) {
				$initial_tab = $req;
			}
		}

		// Custom markup only (no div.notice / div.updated): WP common.js relocates those after .wrap h1/h2,
		// which drops feedback inside the hidden Titles tab. See wp-admin/js/common.js (insertAfter header).
		$mantis_settings_alert = null;
		if ( isset( $_GET['settings-updated'] ) || isset( $_GET['mantis_imported'] ) ) {
			$posts_count = isset( $_GET['mantis_imported_posts'] ) ? (int) $_GET['mantis_imported_posts'] : 0;
			if ( $posts_count > 0 ) {
				$msg = sprintf( /* translators: %d: number of posts */ _n( '%d post updated with SEO data.', '%d posts updated with SEO data.', $posts_count, 'mantis-seo' ), $posts_count );
			} else {
				$msg = isset( $_GET['mantis_imported'] ) ? __( 'Settings imported successfully.', 'mantis-seo' ) : __( 'Settings saved.', 'mantis-seo' );
			}
			$mantis_settings_alert = array(
				'type'    => 'success',
				'message' => $msg,
			);
		}

		if ( isset( $_GET['mantis_import_error'] ) ) {
			$err = get_transient( 'mantis_import_error' );
			if ( $err ) {
				delete_transient( 'mantis_import_error' );
				$mantis_settings_alert = array(
					'type'    => 'error',
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Import failed: %s', 'mantis-seo' ),
						$err
					),
				);
			}
		}
		?>
		<div class="mantis-seo-app" id="mantis-seo-app">
			<script>!function(){var e=document.getElementById("mantis-seo-app");e&&"dark"===localStorage.getItem("mantis_seo_theme")&&e.classList.add("mantis-seo-dark")}();</script>
			<?php require_once MANTIS_SEO_PATH . 'admin/partials/app-header.php'; ?>
			<main class="mantis-seo-app-main">
				<div class="mantis-seo-app-content wrap mantis-seo-settings">
			<?php if ( Mantis_SEO_Yoast_Importer::has_yoast_data() && ! get_option( 'mantis_migrated_from_yoast' ) ) : ?>
			<div class="notice notice-info inline" style="margin:20px 0;">
				<p><strong><?php esc_html_e( 'Yoast SEO detected.', 'mantis-seo' ); ?></strong> <?php esc_html_e( 'You can migrate your SEO settings, post meta, and redirects to Mantis SEO.', 'mantis-seo' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=mantis-seo' ) ); ?>" style="margin-top:10px;">
					<?php wp_nonce_field( 'mantis_migrate_yoast' ); ?>
					<button type="submit" name="mantis_migrate_yoast" value="1" class="button button-primary"><?php esc_html_e( 'Migrate from Yoast', 'mantis-seo' ); ?></button>
				</form>
			</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['mantis_migrated'] ) && $migrate_result = get_transient( 'mantis_migrate_result' ) ) : ?>
					<?php delete_transient( 'mantis_migrate_result' ); ?>
					<div class="notice notice-success inline"><p>
						<?php
						printf(
							/* translators: 1: posts count, 2: terms count, 3: redirects count */
							esc_html__( 'Migration complete: %1$d post meta items, %2$d term meta items, %3$d redirects imported.', 'mantis-seo' ),
							(int) ( $migrate_result['posts'] ?? 0 ),
							(int) ( $migrate_result['terms'] ?? 0 ),
							(int) ( $migrate_result['redirects'] ?? 0 )
						);
						?>
						<?php esc_html_e( 'Consider deactivating Yoast SEO to avoid duplicate meta output.', 'mantis-seo' ); ?>
					</p></div>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php settings_fields( 'mantis_seo_settings' ); ?>
				<input type="hidden" name="mantis_active_tab" id="mantis-settings-active-tab" value="<?php echo esc_attr( $initial_tab ); ?>" />

				<div class="mantis-settings-layout">
					<nav class="mantis-settings-sidebar" aria-label="<?php esc_attr_e( 'Settings sections', 'mantis-seo' ); ?>">
						<ul class="mantis-settings-nav">
							<li><a href="#mantis-titles" class="mantis-settings-nav-item mantis-settings-nav-active" data-panel="mantis-titles"><?php esc_html_e( 'Titles & Meta', 'mantis-seo' ); ?></a></li>
							<li><a href="#mantis-social" class="mantis-settings-nav-item" data-panel="mantis-social"><?php esc_html_e( 'Social', 'mantis-seo' ); ?></a></li>
							<li><a href="#mantis-schema" class="mantis-settings-nav-item" data-panel="mantis-schema"><?php esc_html_e( 'Schema', 'mantis-seo' ); ?></a></li>
							<li><a href="#mantis-sitemap" class="mantis-settings-nav-item" data-panel="mantis-sitemap"><?php esc_html_e( 'Sitemap', 'mantis-seo' ); ?></a></li>
							<li><a href="#mantis-advanced" class="mantis-settings-nav-item" data-panel="mantis-advanced"><?php esc_html_e( 'Advanced', 'mantis-seo' ); ?></a></li>
							<li><a href="#mantis-tools" class="mantis-settings-nav-item" data-panel="mantis-tools"><?php esc_html_e( 'Tools', 'mantis-seo' ); ?></a></li>
						</ul>
					</nav>
					<div class="mantis-settings-body">
					<div class="mantis-settings-body__notices" role="status" aria-live="polite"><?php
					if ( ! empty( $mantis_settings_alert ) ) {
						$alert_role = 'error' === $mantis_settings_alert['type'] ? 'alert' : 'status';
						echo '<div class="mantis-settings-feedback mantis-settings-feedback--' . esc_attr( $mantis_settings_alert['type'] ) . '" role="' . esc_attr( $alert_role ) . '"><p>' . esc_html( $mantis_settings_alert['message'] ) . '</p></div>';
					}
					?></div>
					<div id="mantis-titles" class="mantis-tab-panel">
						<h2><?php esc_html_e( 'Title Templates', 'mantis-seo' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Compose titles with text and variable pills. Set the separator character below; it appears where you place the Separator pill.', 'mantis-seo' ); ?></p>
						<table class="form-table">
							<tr>
								<th><label for="mantis_title_home"><?php esc_html_e( 'Homepage Title', 'mantis-seo' ); ?></label></th>
								<td><?php $this->render_title_template_control( 'mantis_title_home', 'mantis_settings[title_home]', $settings['title_home'] ); ?></td>
							</tr>
							<tr>
								<th><label for="mantis_title_post"><?php esc_html_e( 'Post Title', 'mantis-seo' ); ?></label></th>
								<td><?php $this->render_title_template_control( 'mantis_title_post', 'mantis_settings[title_post]', $settings['title_post'] ); ?></td>
							</tr>
							<tr>
								<th><label for="mantis_title_page"><?php esc_html_e( 'Page Title', 'mantis-seo' ); ?></label></th>
								<td><?php $this->render_title_template_control( 'mantis_title_page', 'mantis_settings[title_page]', $settings['title_page'] ); ?></td>
							</tr>
							<tr>
								<th><label for="mantis_separator"><?php esc_html_e( 'Separator', 'mantis-seo' ); ?></label></th>
								<td><input type="text" id="mantis_separator" name="mantis_settings[separator]" value="<?php echo esc_attr( $settings['separator'] ); ?>" class="small-text" /></td>
							</tr>
						</table>

						<h2><?php esc_html_e( 'Meta Descriptions', 'mantis-seo' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Default meta description templates for each context. Use variable pills like title templates; per-post overrides live in the editor SEO panel.', 'mantis-seo' ); ?></p>
						<table class="form-table">
							<tr>
								<th><label for="mantis_metadesc_home"><?php esc_html_e( 'Homepage Description', 'mantis-seo' ); ?></label></th>
								<td><?php $this->render_title_template_control( 'mantis_metadesc_home', 'mantis_settings[metadesc_home]', $settings['metadesc_home'], true ); ?></td>
							</tr>
							<tr>
								<th><label for="mantis_metadesc_post"><?php esc_html_e( 'Post Description', 'mantis-seo' ); ?></label></th>
								<td><?php $this->render_title_template_control( 'mantis_metadesc_post', 'mantis_settings[metadesc_post]', $settings['metadesc_post'], true ); ?></td>
							</tr>
							<tr>
								<th><label for="mantis_metadesc_page"><?php esc_html_e( 'Page Description', 'mantis-seo' ); ?></label></th>
								<td><?php $this->render_title_template_control( 'mantis_metadesc_page', 'mantis_settings[metadesc_page]', $settings['metadesc_page'], true ); ?></td>
							</tr>
						</table>
					</div>

					<div id="mantis-social" class="mantis-tab-panel" style="display:none;">
						<h2><?php esc_html_e( 'Open Graph & X', 'mantis-seo' ); ?></h2>
						<table class="form-table">
							<tr>
								<th><label for="mantis_og_default_image"><?php esc_html_e( 'Default OG Image', 'mantis-seo' ); ?></label></th>
								<td class="mantis-image-field-wrap">
									<input type="url" id="mantis_og_default_image" name="mantis_settings[og_default_image]" value="<?php echo esc_url( $settings['og_default_image'] ); ?>" class="large-text mantis-image-input" />
									<button type="button" class="button mantis-media-upload" data-target="#mantis_og_default_image" aria-label="<?php esc_attr_e( 'Select image from media library', 'mantis-seo' ); ?>"><?php esc_html_e( 'Upload / Select', 'mantis-seo' ); ?></button>
									<span class="mantis-image-preview" data-for="#mantis_og_default_image"></span>
								</td>
							</tr>
							<tr>
								<th><label for="mantis_twitter_card_type"><?php esc_html_e( 'X Card Type', 'mantis-seo' ); ?></label></th>
								<td>
									<select id="mantis_twitter_card_type" name="mantis_settings[twitter_card_type]">
										<option value="summary" <?php selected( $settings['twitter_card_type'], 'summary' ); ?>><?php esc_html_e( 'Summary', 'mantis-seo' ); ?></option>
										<option value="summary_large_image" <?php selected( $settings['twitter_card_type'], 'summary_large_image' ); ?>><?php esc_html_e( 'Summary Large Image', 'mantis-seo' ); ?></option>
									</select>
								</td>
							</tr>
						</table>
					</div>

					<div id="mantis-schema" class="mantis-tab-panel" style="display:none;">
						<h2><?php esc_html_e( 'Organization Schema', 'mantis-seo' ); ?></h2>
						<table class="form-table">
							<tr>
								<th><label for="mantis_schema_org_name"><?php esc_html_e( 'Organization Name', 'mantis-seo' ); ?></label></th>
								<td><input type="text" id="mantis_schema_org_name" name="mantis_settings[schema_organization_name]" value="<?php echo esc_attr( $settings['schema_organization_name'] ); ?>" class="large-text" /></td>
							</tr>
							<tr>
								<th><label for="mantis_schema_org_url"><?php esc_html_e( 'Organization URL', 'mantis-seo' ); ?></label></th>
								<td><input type="url" id="mantis_schema_org_url" name="mantis_settings[schema_organization_url]" value="<?php echo esc_url( $settings['schema_organization_url'] ); ?>" class="large-text" /></td>
							</tr>
							<tr>
								<th><label for="mantis_schema_org_logo"><?php esc_html_e( 'Logo', 'mantis-seo' ); ?></label></th>
								<td class="mantis-image-field-wrap">
									<input type="url" id="mantis_schema_org_logo" name="mantis_settings[schema_organization_logo]" value="<?php echo esc_url( $settings['schema_organization_logo'] ); ?>" class="large-text mantis-image-input" />
									<button type="button" class="button mantis-media-upload" data-target="#mantis_schema_org_logo" aria-label="<?php esc_attr_e( 'Select image from media library', 'mantis-seo' ); ?>"><?php esc_html_e( 'Upload / Select', 'mantis-seo' ); ?></button>
									<span class="mantis-image-preview" data-for="#mantis_schema_org_logo"></span>
								</td>
							</tr>
							<tr>
								<th><label><?php esc_html_e( 'Social Profiles', 'mantis-seo' ); ?></label></th>
								<td>
									<p class="description" style="margin-bottom:12px;"><?php esc_html_e( 'Same As (schema.org) – URLs where your organization appears on social platforms.', 'mantis-seo' ); ?></p>
									<?php
									$social_platforms = array(
										'facebook'  => array( 'label' => __( 'Facebook', 'mantis-seo' ), 'icon' => 'mantis-icon-facebook', 'url' => 'schema_same_as_facebook' ),
										'instagram' => array( 'label' => __( 'Instagram', 'mantis-seo' ), 'icon' => 'mantis-icon-instagram', 'url' => 'schema_same_as_instagram' ),
										'x'         => array( 'label' => __( 'X (Twitter)', 'mantis-seo' ), 'icon' => 'mantis-icon-x', 'url' => 'schema_same_as_x' ),
										'linkedin'  => array( 'label' => __( 'LinkedIn', 'mantis-seo' ), 'icon' => 'mantis-icon-linkedin', 'url' => 'schema_same_as_linkedin' ),
									);
									foreach ( $social_platforms as $key => $platform ) :
										$value = $settings[ $platform['url'] ] ?? '';
										?>
										<div class="mantis-social-row">
											<span class="mantis-social-icon <?php echo esc_attr( $platform['icon'] ); ?>" title="<?php echo esc_attr( $platform['label'] ); ?>" aria-hidden="true"></span>
											<input type="url" name="mantis_settings[<?php echo esc_attr( $platform['url'] ); ?>]" value="<?php echo esc_url( $value ); ?>" class="large-text mantis-social-input" placeholder="https://<?php echo esc_attr( $key ); ?>.com/..." />
										</div>
									<?php endforeach; ?>
								</td>
							</tr>
						</table>
					</div>

					<div id="mantis-sitemap" class="mantis-tab-panel mantis-sitemap-panel" style="display:none;">
						<h2 class="mantis-sitemap-panel__page-title"><?php esc_html_e( 'XML Sitemap', 'mantis-seo' ); ?></h2>
						<div class="mantis-sitemap-stack">
							<section class="mantis-settings-card" aria-labelledby="mantis-sitemap-enable-heading">
								<h3 id="mantis-sitemap-enable-heading" class="mantis-settings-card__title"><?php esc_html_e( 'Enable Sitemap', 'mantis-seo' ); ?></h3>
								<p class="mantis-settings-card__lead"><?php esc_html_e( 'When enabled, search engines can discover your public URLs from the XML sitemap.', 'mantis-seo' ); ?></p>
								<div class="mantis-settings-card__body">
									<label class="mantis-toggle-row" for="mantis_sitemap_enabled">
										<span class="mantis-toggle-row__text">
											<span class="mantis-toggle-row__label"><?php esc_html_e( 'Enable XML sitemap', 'mantis-seo' ); ?></span>
										</span>
										<span class="mantis-toggle">
											<input type="checkbox" id="mantis_sitemap_enabled" class="mantis-toggle__input" name="mantis_settings[sitemap_enabled]" value="1" <?php checked( $settings['sitemap_enabled'] ); ?> />
											<span class="mantis-toggle__track" aria-hidden="true"></span>
										</span>
									</label>
									<?php if ( $settings['sitemap_enabled'] ) : ?>
										<p class="mantis-sitemap-view-link">
											<a href="<?php echo esc_url( home_url( '/sitemap_index.xml' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View sitemap', 'mantis-seo' ); ?></a>
										</p>
									<?php endif; ?>
								</div>
							</section>
							<section class="mantis-settings-card" aria-labelledby="mantis-sitemap-types-heading">
								<h3 id="mantis-sitemap-types-heading" class="mantis-settings-card__title"><?php esc_html_e( 'Post Types', 'mantis-seo' ); ?></h3>
								<p class="mantis-settings-card__lead"><?php esc_html_e( 'Turn each type on or off to control which content appears in the sitemap.', 'mantis-seo' ); ?></p>
								<?php
								$sitemap_post_types = isset( $settings['sitemap_post_types'] ) && is_array( $settings['sitemap_post_types'] ) ? $settings['sitemap_post_types'] : array( 'post', 'page' );
								$public_post_types  = get_post_types( array( 'public' => true ), 'objects' );
								uasort(
									$public_post_types,
									function ( $a, $b ) {
										$la = $a->labels->singular_name ?? $a->name;
										$lb = $b->labels->singular_name ?? $b->name;
										return strcasecmp( $la, $lb );
									}
								);
								?>
								<ul class="mantis-sitemap-type-list" role="list">
									<?php foreach ( $public_post_types as $pt ) : ?>
										<?php
										$pt_id    = 'mantis_sitemap_pt_' . $pt->name;
										$pt_label = $pt->labels->singular_name ?? $pt->name;
										$on       = in_array( $pt->name, $sitemap_post_types, true );
										?>
										<li class="mantis-sitemap-type-list__item">
											<label class="mantis-toggle-row" for="<?php echo esc_attr( $pt_id ); ?>">
												<span class="mantis-toggle-row__text">
													<span class="mantis-toggle-row__label"><?php echo esc_html( $pt_label ); ?></span>
												</span>
												<span class="mantis-toggle">
													<input type="checkbox" id="<?php echo esc_attr( $pt_id ); ?>" class="mantis-toggle__input" name="mantis_settings[sitemap_post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( $on ); ?> />
													<span class="mantis-toggle__track" aria-hidden="true"></span>
												</span>
											</label>
										</li>
									<?php endforeach; ?>
								</ul>
							</section>
						</div>
					</div>

					<div id="mantis-advanced" class="mantis-tab-panel mantis-advanced-panel" style="display:none;">
						<h2 class="mantis-advanced-panel__section-title"><?php esc_html_e( 'Indexing', 'mantis-seo' ); ?></h2>
						<section class="mantis-settings-card mantis-advanced-noindex-card" aria-labelledby="mantis-advanced-noindex-heading">
							<h3 id="mantis-advanced-noindex-heading" class="mantis-settings-card__title"><?php esc_html_e( 'Noindex Archives', 'mantis-seo' ); ?></h3>
							<p class="mantis-settings-card__lead"><?php esc_html_e( 'When enabled, the noindex directive is sent for the matching archive templates so they are less likely to appear in search results.', 'mantis-seo' ); ?></p>
							<?php
							$noindex_archive_options = array(
								'noindex_author'   => __( 'Author archives', 'mantis-seo' ),
								'noindex_date'     => __( 'Date archives', 'mantis-seo' ),
								'noindex_category' => __( 'Category archives', 'mantis-seo' ),
								'noindex_tag'      => __( 'Tag archives', 'mantis-seo' ),
							);
							?>
							<ul class="mantis-sitemap-type-list" role="list">
								<?php foreach ( $noindex_archive_options as $field => $option_label ) : ?>
									<?php
									$input_id = 'mantis_' . $field;
									$on       = ! empty( $settings[ $field ] );
									?>
									<li class="mantis-sitemap-type-list__item">
										<label class="mantis-toggle-row" for="<?php echo esc_attr( $input_id ); ?>">
											<span class="mantis-toggle-row__text">
												<span class="mantis-toggle-row__label"><?php echo esc_html( $option_label ); ?></span>
											</span>
											<span class="mantis-toggle">
												<input type="checkbox" id="<?php echo esc_attr( $input_id ); ?>" class="mantis-toggle__input" name="mantis_settings[<?php echo esc_attr( $field ); ?>]" value="1" <?php checked( $on ); ?> />
												<span class="mantis-toggle__track" aria-hidden="true"></span>
											</span>
										</label>
									</li>
								<?php endforeach; ?>
							</ul>
						</section>
						<h2><?php esc_html_e( 'Webmaster Verification', 'mantis-seo' ); ?></h2>
						<table class="form-table">
							<tr>
								<th><label for="mantis_google_verify"><?php esc_html_e( 'Google', 'mantis-seo' ); ?></label></th>
								<td><input type="text" id="mantis_google_verify" name="mantis_settings[google_verify]" value="<?php echo esc_attr( $settings['google_verify'] ); ?>" class="large-text" placeholder="content="..." /></td>
							</tr>
							<tr>
								<th><label for="mantis_bing_verify"><?php esc_html_e( 'Bing', 'mantis-seo' ); ?></label></th>
								<td><input type="text" id="mantis_bing_verify" name="mantis_settings[bing_verify]" value="<?php echo esc_attr( $settings['bing_verify'] ); ?>" class="large-text" /></td>
							</tr>
						</table>
					</div>

					<div id="mantis-tools" class="mantis-tab-panel mantis-tools-panel" style="display:none;">
						<h2 class="mantis-tools-panel__page-title"><?php esc_html_e( 'Export / Import', 'mantis-seo' ); ?></h2>
						<p class="mantis-tools-panel__intro"><?php esc_html_e( 'Export your settings or import from a backup. You can also import directly from Yoast SEO (INI/text or Keywords CSV).', 'mantis-seo' ); ?></p>
						<div class="mantis-tools-stack">
							<section class="mantis-settings-card mantis-tools-card" aria-labelledby="mantis-tools-export-heading">
								<h3 id="mantis-tools-export-heading" class="mantis-settings-card__title"><?php esc_html_e( 'Export Settings', 'mantis-seo' ); ?></h3>
								<p class="mantis-settings-card__lead"><?php esc_html_e( 'Download a JSON file with your current Mantis SEO settings. Use it as a backup or to copy configuration to another site.', 'mantis-seo' ); ?></p>
								<div class="mantis-tools-card__actions">
									<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'mantis_export', 1 ), 'mantis_export', '_wpnonce' ) ); ?>" class="button button-primary mantis-tools-action-btn"><?php esc_html_e( 'Download export file', 'mantis-seo' ); ?></a>
								</div>
							</section>
							<section class="mantis-settings-card mantis-tools-card" aria-labelledby="mantis-tools-import-heading">
								<h3 id="mantis-tools-import-heading" class="mantis-settings-card__title"><?php esc_html_e( 'Import Settings', 'mantis-seo' ); ?></h3>
								<p class="mantis-settings-card__lead"><?php esc_html_e( 'Choose a file and run import. Supported formats: Mantis JSON, Yoast INI/text, Yoast Keywords CSV, or a simple CSV of key,value pairs.', 'mantis-seo' ); ?></p>
								<div id="mantis-import-cell" class="mantis-tools-import-slot"></div>
							</section>
						</div>
					</div>

				<?php submit_button(); ?>
					</div>
				</div>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=mantis-seo' ) ); ?>" enctype="multipart/form-data" id="mantis-import-form" class="mantis-tools-import-form" style="display:none;">
				<?php wp_nonce_field( 'mantis_import' ); ?>
				<div class="mantis-tools-import-form__fields">
					<label for="mantis_import_file" class="mantis-tools-file-field">
						<span class="mantis-tools-file-field__label"><?php esc_html_e( 'Backup file', 'mantis-seo' ); ?></span>
						<input type="file" id="mantis_import_file" name="mantis_import_file" accept=".json,.ini,.txt,.csv" class="mantis-tools-file-input" />
					</label>
					<button type="submit" name="mantis_import" value="1" class="button button-primary mantis-tools-action-btn"><?php esc_html_e( 'Import', 'mantis-seo' ); ?></button>
				</div>
			</form>
				</div>
			</main>
		</div>

		<script>
		(function() {
			var navItems = document.querySelectorAll('.mantis-settings-nav-item');
			var panels = document.querySelectorAll('.mantis-settings-body .mantis-tab-panel');
			var importForm = document.getElementById('mantis-import-form');
			var importCell = document.getElementById('mantis-import-cell');
			var activeTabInput = document.getElementById('mantis-settings-active-tab');
			var allowedTabs = <?php echo wp_json_encode( $panel_ids ); ?>;
			function showPanel(targetId) {
				navItems.forEach(function(n) { n.classList.remove('mantis-settings-nav-active'); });
				panels.forEach(function(p) {
					p.style.display = p.id === targetId ? 'block' : 'none';
				});
				var active = document.querySelector('.mantis-settings-nav-item[data-panel="' + targetId + '"]');
				if (active) active.classList.add('mantis-settings-nav-active');
				if (activeTabInput) activeTabInput.value = targetId;
				if (targetId === 'mantis-tools' && importForm && importCell) {
					importCell.appendChild(importForm);
					importForm.style.display = 'block';
				} else if (importForm) {
					importForm.style.display = 'none';
				}
			}
			navItems.forEach(function(item) {
				item.addEventListener('click', function(e) {
					e.preventDefault();
					var targetId = this.getAttribute('data-panel') || this.getAttribute('href').slice(1);
					showPanel(targetId);
				});
			});
			if (window.location.search.indexOf('mantis_import_error') !== -1) {
				showPanel('mantis-tools');
			} else {
				var params = new URLSearchParams(window.location.search);
				var tabParam = params.get('mantis_tab');
				if (tabParam && allowedTabs.indexOf(tabParam) !== -1 && document.getElementById(tabParam)) {
					showPanel(tabParam);
				} else if (activeTabInput && activeTabInput.value && activeTabInput.value !== 'mantis-titles') {
					showPanel(activeTabInput.value);
				}
			}
		})();
		</script>
		<?php
	}
}
