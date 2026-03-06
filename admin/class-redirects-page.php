<?php
/**
 * Redirects admin page.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Redirects_Page
 */
class Mantis_SEO_Redirects_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 20 );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Add redirects submenu.
	 */
	public function add_submenu() {
		add_submenu_page(
			'mantis-seo',
			__( 'Redirects', 'mantis-seo' ),
			__( 'Redirects', 'mantis-seo' ),
			'manage_options',
			'mantis-redirects',
			array( $this, 'render' )
		);
	}

	/**
	 * Handle form actions.
	 */
	public function handle_actions() {
		if ( ! isset( $_GET['page'] ) || 'mantis-redirects' !== $_GET['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Delete.
		if ( isset( $_POST['mantis_redirect_delete'] ) && isset( $_POST['mantis_redirect_id'] ) ) {
			check_admin_referer( 'mantis_redirect_delete' );
			Mantis_SEO_Redirects::delete( (int) $_POST['mantis_redirect_id'] );
			wp_safe_redirect( add_query_arg( 'deleted', 1, admin_url( 'admin.php?page=mantis-redirects' ) ) );
			exit;
		}

		// Add or update.
		if ( isset( $_POST['mantis_redirect_save'] ) ) {
			check_admin_referer( 'mantis_redirect_save' );
			$origin = isset( $_POST['mantis_redirect_origin'] ) ? sanitize_text_field( wp_unslash( $_POST['mantis_redirect_origin'] ) ) : '';
			$target = isset( $_POST['mantis_redirect_target'] ) ? esc_url_raw( wp_unslash( $_POST['mantis_redirect_target'] ) ) : '';
			$type   = isset( $_POST['mantis_redirect_type'] ) ? (int) $_POST['mantis_redirect_type'] : 301;
			$format = isset( $_POST['mantis_redirect_format'] ) && 'regex' === $_POST['mantis_redirect_format'] ? 'regex' : 'plain';

			if ( ! empty( $origin ) && ! empty( $target ) ) {
				$edit_id = isset( $_POST['mantis_redirect_edit_id'] ) ? (int) $_POST['mantis_redirect_edit_id'] : 0;
				if ( $edit_id ) {
					Mantis_SEO_Redirects::update( $edit_id, $origin, $target, $type, $format );
					wp_safe_redirect( add_query_arg( 'updated', 1, admin_url( 'admin.php?page=mantis-redirects' ) ) );
				} else {
					Mantis_SEO_Redirects::add( $origin, $target, $type, $format );
					wp_safe_redirect( add_query_arg( 'added', 1, admin_url( 'admin.php?page=mantis-redirects' ) ) );
				}
				exit;
			}
		}
	}

	/**
	 * Render redirects page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$redirects = Mantis_SEO_Redirects::get_all();
		$edit_id   = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
		$editing   = $edit_id ? Mantis_SEO_Redirects::get_by_id( $edit_id ) : null;
		$prefill   = isset( $_GET['origin'] ) ? sanitize_text_field( wp_unslash( $_GET['origin'] ) ) : '';

		if ( $editing ) {
			$form_origin = $editing['origin'];
			$form_target = $editing['target'];
			$form_type   = (int) $editing['type'];
			$form_format = $editing['format'];
		} elseif ( $prefill ) {
			$form_origin = $prefill;
			$form_target = '';
			$form_type   = 301;
			$form_format = 'plain';
		} else {
			$form_origin = '';
			$form_target = '';
			$form_type   = 301;
			$form_format = 'plain';
		}

		$types = array(
			301 => __( '301 Permanent', 'mantis-seo' ),
			302 => __( '302 Temporary', 'mantis-seo' ),
			307 => __( '307 Temporary', 'mantis-seo' ),
			308 => __( '308 Permanent', 'mantis-seo' ),
			410 => __( '410 Gone', 'mantis-seo' ),
		);
		?>
		<div class="wrap mantis-redirects">
			<h1><?php esc_html_e( 'Redirects', 'mantis-seo' ); ?></h1>

			<?php if ( isset( $_GET['added'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Redirect added.', 'mantis-seo' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Redirect updated.', 'mantis-seo' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['deleted'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Redirect deleted.', 'mantis-seo' ); ?></p></div>
			<?php endif; ?>

			<div class="mantis-redirect-form" style="margin-bottom:30px;">
				<h2><?php echo $editing ? esc_html__( 'Edit Redirect', 'mantis-seo' ) : esc_html__( 'Add Redirect', 'mantis-seo' ); ?></h2>
				<form method="post" action="">
					<?php wp_nonce_field( 'mantis_redirect_save' ); ?>
					<input type="hidden" name="mantis_redirect_save" value="1" />
					<?php if ( $editing ) : ?>
						<input type="hidden" name="mantis_redirect_edit_id" value="<?php echo esc_attr( $edit_id ); ?>" />
					<?php endif; ?>
					<table class="form-table">
						<tr>
							<th><label for="mantis_redirect_origin"><?php esc_html_e( 'Origin (from URL path)', 'mantis-seo' ); ?></label></th>
							<td>
								<input type="text" id="mantis_redirect_origin" name="mantis_redirect_origin" value="<?php echo esc_attr( $form_origin ); ?>" class="regular-text" required />
								<select name="mantis_redirect_format">
									<option value="plain" <?php selected( $form_format, 'plain' ); ?>><?php esc_html_e( 'Plain', 'mantis-seo' ); ?></option>
									<option value="regex" <?php selected( $form_format, 'regex' ); ?>><?php esc_html_e( 'Regex', 'mantis-seo' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="mantis_redirect_target"><?php esc_html_e( 'Target (to URL)', 'mantis-seo' ); ?></label></th>
							<td><input type="url" id="mantis_redirect_target" name="mantis_redirect_target" value="<?php echo esc_url( $form_target ); ?>" class="large-text" required /></td>
						</tr>
						<tr>
							<th><label for="mantis_redirect_type"><?php esc_html_e( 'Type', 'mantis-seo' ); ?></label></th>
							<td>
								<select id="mantis_redirect_type" name="mantis_redirect_type">
									<?php foreach ( $types as $code => $label ) : ?>
										<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $form_type, $code ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					</table>
					<p class="submit">
						<button type="submit" class="button button-primary"><?php echo $editing ? esc_html__( 'Update Redirect', 'mantis-seo' ) : esc_html__( 'Add Redirect', 'mantis-seo' ); ?></button>
						<?php if ( $editing ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=mantis-redirects' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'mantis-seo' ); ?></a>
						<?php endif; ?>
					</p>
				</form>
			</div>

			<h2><?php esc_html_e( 'Existing Redirects', 'mantis-seo' ); ?></h2>
			<?php if ( empty( $redirects ) ) : ?>
				<p><?php esc_html_e( 'No redirects yet.', 'mantis-seo' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Origin', 'mantis-seo' ); ?></th>
							<th><?php esc_html_e( 'Target', 'mantis-seo' ); ?></th>
							<th><?php esc_html_e( 'Type', 'mantis-seo' ); ?></th>
							<th><?php esc_html_e( 'Format', 'mantis-seo' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'mantis-seo' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $redirects as $r ) : ?>
							<tr>
								<td><code><?php echo esc_html( $r['origin'] ); ?></code></td>
								<td><a href="<?php echo esc_url( $r['target'] ); ?>" target="_blank"><?php echo esc_html( $r['target'] ); ?></a></td>
								<td><?php echo esc_html( $r['type'] ); ?></td>
								<td><?php echo esc_html( $r['format'] ); ?></td>
								<td>
									<a href="<?php echo esc_url( add_query_arg( 'edit', $r['id'], admin_url( 'admin.php?page=mantis-redirects' ) ) ); ?>"><?php esc_html_e( 'Edit', 'mantis-seo' ); ?></a>
									|
									<form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Delete this redirect?', 'mantis-seo' ); ?>');">
										<?php wp_nonce_field( 'mantis_redirect_delete' ); ?>
										<input type="hidden" name="mantis_redirect_delete" value="1" />
										<input type="hidden" name="mantis_redirect_id" value="<?php echo esc_attr( $r['id'] ); ?>" />
										<button type="submit" class="button-link-delete"><?php esc_html_e( 'Delete', 'mantis-seo' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
