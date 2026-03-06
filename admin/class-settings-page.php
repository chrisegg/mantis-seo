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
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_migrate' ) );
		add_action( 'admin_init', array( $this, 'handle_export' ) );
		add_action( 'admin_init', array( $this, 'handle_import' ) );
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

		$redirect_url = admin_url( 'admin.php?page=mantis-seo' );
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
				$sanitized[ $field ] = sanitize_text_field( $input[ $field ] );
			}
		}

		$bool_fields = array( 'noindex_author', 'noindex_date', 'sitemap_enabled' );
		foreach ( $bool_fields as $field ) {
			$sanitized[ $field ] = ! empty( $input[ $field ] );
		}

		$social_fields = array( 'schema_same_as_facebook', 'schema_same_as_instagram', 'schema_same_as_x', 'schema_same_as_linkedin' );
		foreach ( $social_fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$sanitized[ $field ] = esc_url_raw( $input[ $field ] );
			}
		}

		return wp_parse_args( $sanitized, Mantis_SEO_Main::get_default_settings() );
	}

	/**
	 * Render settings page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Mantis_SEO_Main::get_settings();

		if ( isset( $_GET['settings-updated'] ) || isset( $_GET['mantis_imported'] ) ) {
			$posts_count = isset( $_GET['mantis_imported_posts'] ) ? (int) $_GET['mantis_imported_posts'] : 0;
			if ( $posts_count > 0 ) {
				$msg = sprintf( /* translators: %d: number of posts */ _n( '%d post updated with SEO data.', '%d posts updated with SEO data.', $posts_count, 'mantis-seo' ), $posts_count );
			} else {
				$msg = isset( $_GET['mantis_imported'] ) ? __( 'Settings imported successfully.', 'mantis-seo' ) : __( 'Settings saved.', 'mantis-seo' );
			}
			add_settings_error(
				'mantis_seo_messages',
				'mantis_seo_message',
				$msg,
				'success'
			);
		}

		if ( isset( $_GET['mantis_import_error'] ) ) {
			$err = get_transient( 'mantis_import_error' );
			if ( $err ) {
				delete_transient( 'mantis_import_error' );
				add_settings_error(
					'mantis_seo_messages',
					'mantis_import_error',
					sprintf( /* translators: %s: error message */ __( 'Import failed: %s', 'mantis-seo' ), esc_html( $err ) ),
					'error'
				);
			}
		}

		settings_errors( 'mantis_seo_messages' );
		?>
		<div class="mantis-seo-app" id="mantis-seo-app">
			<script>!function(){var e=document.getElementById("mantis-seo-app");e&&"dark"===localStorage.getItem("mantis_seo_theme")&&e.classList.add("mantis-seo-dark")}();</script>
			<?php require_once MANTIS_SEO_PATH . 'admin/partials/app-header.php'; ?>
			<main class="mantis-seo-app-main">
				<div class="mantis-seo-app-content wrap mantis-seo-settings">
			<?php if ( Mantis_SEO_Yoast_Importer::has_yoast_data() && ! get_option( 'mantis_migrated_from_yoast' ) ) : ?>
			<div class="notice notice-info" style="margin:20px 0;">
				<p><strong><?php esc_html_e( 'Yoast SEO detected.', 'mantis-seo' ); ?></strong> <?php esc_html_e( 'You can migrate your SEO settings, post meta, and redirects to Mantis SEO.', 'mantis-seo' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=mantis-seo' ) ); ?>" style="margin-top:10px;">
					<?php wp_nonce_field( 'mantis_migrate_yoast' ); ?>
					<button type="submit" name="mantis_migrate_yoast" value="1" class="button button-primary"><?php esc_html_e( 'Migrate from Yoast', 'mantis-seo' ); ?></button>
				</form>
			</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['mantis_migrated'] ) && $migrate_result = get_transient( 'mantis_migrate_result' ) ) : ?>
					<?php delete_transient( 'mantis_migrate_result' ); ?>
					<div class="notice notice-success"><p>
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
					<div id="mantis-titles" class="mantis-tab-panel">
						<h2><?php esc_html_e( 'Title Templates', 'mantis-seo' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Use %post_title%, %site_name%, %site_description%, %excerpt%, %date%, %separator%', 'mantis-seo' ); ?></p>
						<table class="form-table">
							<tr>
								<th><label for="mantis_title_home"><?php esc_html_e( 'Homepage Title', 'mantis-seo' ); ?></label></th>
								<td><input type="text" id="mantis_title_home" name="mantis_settings[title_home]" value="<?php echo esc_attr( $settings['title_home'] ); ?>" class="large-text" /></td>
							</tr>
							<tr>
								<th><label for="mantis_title_post"><?php esc_html_e( 'Post Title', 'mantis-seo' ); ?></label></th>
								<td><input type="text" id="mantis_title_post" name="mantis_settings[title_post]" value="<?php echo esc_attr( $settings['title_post'] ); ?>" class="large-text" /></td>
							</tr>
							<tr>
								<th><label for="mantis_title_page"><?php esc_html_e( 'Page Title', 'mantis-seo' ); ?></label></th>
								<td><input type="text" id="mantis_title_page" name="mantis_settings[title_page]" value="<?php echo esc_attr( $settings['title_page'] ); ?>" class="large-text" /></td>
							</tr>
							<tr>
								<th><label for="mantis_separator"><?php esc_html_e( 'Separator', 'mantis-seo' ); ?></label></th>
								<td><input type="text" id="mantis_separator" name="mantis_settings[separator]" value="<?php echo esc_attr( $settings['separator'] ); ?>" class="small-text" /></td>
							</tr>
						</table>

						<h2><?php esc_html_e( 'Meta Descriptions', 'mantis-seo' ); ?></h2>
						<table class="form-table">
							<tr>
								<th><label for="mantis_metadesc_home"><?php esc_html_e( 'Homepage Description', 'mantis-seo' ); ?></label></th>
								<td><textarea id="mantis_metadesc_home" name="mantis_settings[metadesc_home]" rows="2" class="large-text"><?php echo esc_textarea( $settings['metadesc_home'] ); ?></textarea></td>
							</tr>
							<tr>
								<th><label for="mantis_metadesc_post"><?php esc_html_e( 'Post Description', 'mantis-seo' ); ?></label></th>
								<td><input type="text" id="mantis_metadesc_post" name="mantis_settings[metadesc_post]" value="<?php echo esc_attr( $settings['metadesc_post'] ); ?>" class="large-text" /></td>
							</tr>
							<tr>
								<th><label for="mantis_metadesc_page"><?php esc_html_e( 'Page Description', 'mantis-seo' ); ?></label></th>
								<td><input type="text" id="mantis_metadesc_page" name="mantis_settings[metadesc_page]" value="<?php echo esc_attr( $settings['metadesc_page'] ); ?>" class="large-text" /></td>
							</tr>
						</table>
					</div>

					<div id="mantis-social" class="mantis-tab-panel" style="display:none;">
						<h2><?php esc_html_e( 'Open Graph & Twitter', 'mantis-seo' ); ?></h2>
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
								<th><label for="mantis_twitter_card_type"><?php esc_html_e( 'Twitter Card Type', 'mantis-seo' ); ?></label></th>
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

					<div id="mantis-sitemap" class="mantis-tab-panel" style="display:none;">
						<h2><?php esc_html_e( 'XML Sitemap', 'mantis-seo' ); ?></h2>
						<table class="form-table">
							<tr>
								<th><label for="mantis_sitemap_enabled"><?php esc_html_e( 'Enable Sitemap', 'mantis-seo' ); ?></label></th>
								<td>
									<label><input type="checkbox" id="mantis_sitemap_enabled" name="mantis_settings[sitemap_enabled]" value="1" <?php checked( $settings['sitemap_enabled'] ); ?> /> <?php esc_html_e( 'Enable XML sitemap', 'mantis-seo' ); ?></label>
									<?php if ( $settings['sitemap_enabled'] ) : ?>
										<p class="description"><a href="<?php echo esc_url( home_url( '/sitemap_index.xml' ) ); ?>" target="_blank"><?php esc_html_e( 'View sitemap', 'mantis-seo' ); ?></a></p>
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</div>

					<div id="mantis-advanced" class="mantis-tab-panel" style="display:none;">
						<h2><?php esc_html_e( 'Indexing', 'mantis-seo' ); ?></h2>
						<table class="form-table">
							<tr>
								<th><?php esc_html_e( 'Noindex Archives', 'mantis-seo' ); ?></th>
								<td>
									<label><input type="checkbox" name="mantis_settings[noindex_author]" value="1" <?php checked( $settings['noindex_author'] ); ?> /> <?php esc_html_e( 'Author archives', 'mantis-seo' ); ?></label><br>
									<label><input type="checkbox" name="mantis_settings[noindex_date]" value="1" <?php checked( $settings['noindex_date'] ); ?> /> <?php esc_html_e( 'Date archives', 'mantis-seo' ); ?></label>
								</td>
							</tr>
						</table>
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
						<h2><?php esc_html_e( 'Export / Import', 'mantis-seo' ); ?></h2>
						<p class="description mantis-tools-intro"><?php esc_html_e( 'Export your settings or import from a backup. You can also import directly from Yoast SEO (INI/text or Keywords CSV).', 'mantis-seo' ); ?></p>
						<table class="form-table">
							<tr>
								<th><?php esc_html_e( 'Export Settings', 'mantis-seo' ); ?></th>
								<td>
									<div class="mantis-tools-export">
										<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'mantis_export', 1 ), 'mantis_export', '_wpnonce' ) ); ?>" class="button mantis-tools-button"><?php esc_html_e( 'Download export file', 'mantis-seo' ); ?></a>
										<p class="description"><?php esc_html_e( 'Exports settings as JSON. Use Import below to restore on this or another site.', 'mantis-seo' ); ?></p>
									</div>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Import Settings', 'mantis-seo' ); ?></th>
								<td>
									<div class="mantis-tools-import">
										<div id="mantis-import-cell"></div>
										<p class="description"><?php esc_html_e( 'Supports: Mantis JSON, Yoast INI/text, Yoast Keywords CSV, or CSV (key,value).', 'mantis-seo' ); ?></p>
									</div>
								</td>
							</tr>
						</table>
					</div>

				<?php submit_button(); ?>
					</div>
				</div>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=mantis-seo' ) ); ?>" enctype="multipart/form-data" id="mantis-import-form" style="display:none;">
				<?php wp_nonce_field( 'mantis_import' ); ?>
				<input type="file" id="mantis_import_file" name="mantis_import_file" accept=".json,.ini,.txt,.csv" />
				<button type="submit" name="mantis_import" value="1" class="button mantis-tools-button"><?php esc_html_e( 'Import', 'mantis-seo' ); ?></button>
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
			function showPanel(targetId) {
				navItems.forEach(function(n) { n.classList.remove('mantis-settings-nav-active'); });
				panels.forEach(function(p) {
					p.style.display = p.id === targetId ? 'block' : 'none';
				});
				var active = document.querySelector('.mantis-settings-nav-item[data-panel="' + targetId + '"]');
				if (active) active.classList.add('mantis-settings-nav-active');
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
			if (window.location.search.indexOf('mantis_import_error') !== -1 && navItems[5]) {
				showPanel('mantis-tools');
			}
		})();
		</script>
		<?php
	}
}
