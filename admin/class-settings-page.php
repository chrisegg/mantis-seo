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

			$json = file_get_contents( $file_path );
			if ( false === $json ) {
				throw new Exception( __( 'Could not read the uploaded file. It may be empty or inaccessible.', 'mantis-seo' ) );
			}

			$data = json_decode( $json, true );
			if ( null === $data && json_last_error() !== JSON_ERROR_NONE ) {
				$msg = function_exists( 'json_last_error_msg' ) ? json_last_error_msg() : __( 'Invalid JSON', 'mantis-seo' );
				throw new Exception( sprintf( /* translators: %s: JSON error message */ __( 'Invalid JSON in file: %s', 'mantis-seo' ), $msg ) );
			}

			if ( ! $data || empty( $data['mantis_seo_export'] ) || empty( $data['settings'] ) ) {
				throw new Exception( __( 'Invalid import file. The file is not a valid Mantis SEO export.', 'mantis-seo' ) );
			}

			$imported = wp_parse_args( $data['settings'], Mantis_SEO_Main::get_default_settings() );
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

		if ( isset( $input['schema_same_as'] ) && is_array( $input['schema_same_as'] ) ) {
			$sanitized['schema_same_as'] = array_values( array_filter( array_map( 'esc_url_raw', $input['schema_same_as'] ) ) );
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

		if ( isset( $_GET['settings-updated'] ) ) {
			$msg = isset( $_GET['mantis_imported'] ) ? __( 'Settings imported successfully.', 'mantis-seo' ) : __( 'Settings saved.', 'mantis-seo' );
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
		<div class="wrap mantis-seo-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

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

				<div class="mantis-seo-tabs">
					<nav class="nav-tab-wrapper">
						<a href="#mantis-titles" class="nav-tab nav-tab-active"><?php esc_html_e( 'Titles & Meta', 'mantis-seo' ); ?></a>
						<a href="#mantis-social" class="nav-tab"><?php esc_html_e( 'Social', 'mantis-seo' ); ?></a>
						<a href="#mantis-schema" class="nav-tab"><?php esc_html_e( 'Schema', 'mantis-seo' ); ?></a>
						<a href="#mantis-sitemap" class="nav-tab"><?php esc_html_e( 'Sitemap', 'mantis-seo' ); ?></a>
						<a href="#mantis-advanced" class="nav-tab"><?php esc_html_e( 'Advanced', 'mantis-seo' ); ?></a>
						<a href="#mantis-tools" class="nav-tab"><?php esc_html_e( 'Tools', 'mantis-seo' ); ?></a>
					</nav>

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
								<th><label for="mantis_og_default_image"><?php esc_html_e( 'Default OG Image URL', 'mantis-seo' ); ?></label></th>
								<td><input type="url" id="mantis_og_default_image" name="mantis_settings[og_default_image]" value="<?php echo esc_url( $settings['og_default_image'] ); ?>" class="large-text" /></td>
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
								<th><label for="mantis_schema_org_logo"><?php esc_html_e( 'Logo URL', 'mantis-seo' ); ?></label></th>
								<td><input type="url" id="mantis_schema_org_logo" name="mantis_settings[schema_organization_logo]" value="<?php echo esc_url( $settings['schema_organization_logo'] ); ?>" class="large-text" /></td>
							</tr>
							<tr>
								<th><label><?php esc_html_e( 'Social Profiles (sameAs)', 'mantis-seo' ); ?></label></th>
								<td>
									<?php
									$same_as = isset( $settings['schema_same_as'] ) ? $settings['schema_same_as'] : array();
									if ( empty( $same_as ) ) {
										$same_as = array( '' );
									}
									foreach ( $same_as as $i => $url ) :
										?>
										<input type="url" name="mantis_settings[schema_same_as][]" value="<?php echo esc_url( $url ); ?>" class="large-text" placeholder="https://" style="margin-bottom:5px; display:block;" />
									<?php endforeach; ?>
									<p class="description"><?php esc_html_e( 'One URL per line for social profiles.', 'mantis-seo' ); ?></p>
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

					<div id="mantis-tools" class="mantis-tab-panel" style="display:none;">
						<h2><?php esc_html_e( 'Export / Import', 'mantis-seo' ); ?></h2>
						<p class="description" style="margin-bottom:20px;"><?php esc_html_e( 'These tools are for Mantis SEO settings only. To migrate from Yoast SEO, use the "Migrate from Yoast" button at the top of this page.', 'mantis-seo' ); ?></p>
						<table class="form-table">
							<tr>
								<th><?php esc_html_e( 'Export Settings', 'mantis-seo' ); ?></th>
								<td>
									<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'mantis_export', 1 ), 'mantis_export', '_wpnonce' ) ); ?>" class="button"><?php esc_html_e( 'Download export file', 'mantis-seo' ); ?></a>
									<p class="description"><?php esc_html_e( 'Exports settings as JSON. Use Import below to restore on this or another site.', 'mantis-seo' ); ?></p>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Import Settings', 'mantis-seo' ); ?></th>
								<td id="mantis-import-cell"></td>
							</tr>
						</table>
						<p class="description" style="margin-top:-10px;"><?php esc_html_e( 'Import accepts JSON files from Mantis SEO Export only. Yoast CSV/text exports are not supported—use Migrate from Yoast instead.', 'mantis-seo' ); ?></p>
					</div>
				</div>

				<?php submit_button(); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=mantis-seo' ) ); ?>" enctype="multipart/form-data" id="mantis-import-form" style="display:none;">
				<?php wp_nonce_field( 'mantis_import' ); ?>
				<input type="file" id="mantis_import_file" name="mantis_import_file" accept=".json" style="margin-right:8px;" />
				<button type="submit" name="mantis_import" value="1" class="button"><?php esc_html_e( 'Import', 'mantis-seo' ); ?></button>
			</form>
		</div>

		<script>
		(function() {
			var tabs = document.querySelectorAll('.mantis-seo-tabs .nav-tab');
			var panels = document.querySelectorAll('.mantis-tab-panel');
			var importForm = document.getElementById('mantis-import-form');
			var importCell = document.getElementById('mantis-import-cell');
			function showTab(targetId) {
				tabs.forEach(function(t) { t.classList.remove('nav-tab-active'); });
				panels.forEach(function(p) {
					p.style.display = p.id === targetId ? 'block' : 'none';
				});
				if (targetId === 'mantis-tools' && importForm && importCell) {
					importCell.appendChild(importForm);
					importForm.style.display = 'block';
				} else if (importForm) {
					importForm.style.display = 'none';
				}
			}
			tabs.forEach(function(tab) {
				tab.addEventListener('click', function(e) {
					e.preventDefault();
					var targetId = this.getAttribute('href').slice(1);
					var prev = document.querySelector('.mantis-seo-tabs .nav-tab-active');
					if (prev) prev.classList.remove('nav-tab-active');
					this.classList.add('nav-tab-active');
					showTab(targetId);
				});
			});
			// Switch to Tools tab if import error in URL.
			if (window.location.search.indexOf('mantis_import_error') !== -1 && tabs[5]) {
				tabs.forEach(function(t) { t.classList.remove('nav-tab-active'); });
				tabs[5].classList.add('nav-tab-active');
				showTab('mantis-tools');
			}
		})();
		</script>
		<?php
	}
}
