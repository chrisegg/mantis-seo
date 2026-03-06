<?php
/**
 * Redirect manager: post watcher for slug changes, trash, delete.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Redirect_Manager
 */
class Mantis_SEO_Redirect_Manager {

	/**
	 * Admin notice option key.
	 *
	 * @var string
	 */
	const NOTICE_OPTION = 'mantis_redirect_notice';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'post_updated', array( $this, 'detect_slug_change' ), 12, 3 );
		add_action( 'wp_trash_post', array( $this, 'detect_trash' ) );
		add_action( 'before_delete_post', array( $this, 'detect_delete' ) );
		add_action( 'admin_notices', array( $this, 'show_redirect_notices' ) );
		add_action( 'admin_init', array( $this, 'dismiss_notice' ) );
	}

	/**
	 * Detect slug change and auto-create redirect.
	 *
	 * @param int     $post_id   Post ID.
	 * @param WP_Post $post     Post after update.
	 * @param WP_Post $post_before Post before update.
	 */
	public function detect_slug_change( $post_id, $post, $post_before ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post_type = get_post_type_object( $post->post_type );
		if ( ! $post_type || ! $post_type->public ) {
			return;
		}

		if ( $post->post_name === $post_before->post_name ) {
			return;
		}

		if ( ! in_array( $post_before->post_status, array( 'publish', 'private' ), true ) ) {
			return;
		}
		if ( ! in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
			return;
		}

		$old_url = $this->get_post_path( $post_before );
		$new_url = $this->get_post_path( $post_id );

		if ( empty( $old_url ) || $old_url === $new_url || '/' === $old_url ) {
			return;
		}

		// Create 301 redirect from old to new.
		$target = get_permalink( $post_id );
		Mantis_SEO_Redirects::add( $old_url, $target, 301, 'plain' );
	}

	/**
	 * Detect post trashed - offer to create redirect.
	 *
	 * @param int $post_id Post ID.
	 */
	public function detect_trash( $post_id ) {
		$this->maybe_store_redirect_notice( $post_id, 'trash' );
	}

	/**
	 * Detect post deleted (permanent) - offer to create redirect.
	 *
	 * @param int $post_id Post ID.
	 */
	public function detect_delete( $post_id ) {
		if ( 'trash' === get_post_status( $post_id ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		$this->maybe_store_redirect_notice( $post_id, 'delete' );
	}

	/**
	 * Store redirect notice if post had a public URL.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $action  'trash' or 'delete'.
	 */
	private function maybe_store_redirect_notice( $post_id, $action ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$post_type = get_post_type_object( $post->post_type );
		if ( ! $post_type || ! $post_type->public ) {
			return;
		}

		if ( ! in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
			return;
		}

		$path = $this->get_post_path( $post_id );
		if ( empty( $path ) || '/' === $path ) {
			return;
		}

		$notices = get_option( self::NOTICE_OPTION, array() );
		if ( ! is_array( $notices ) ) {
			$notices = array();
		}
		$notices[] = array(
			'origin' => $path,
			'action'  => $action,
		);
		update_option( self::NOTICE_OPTION, $notices );
	}

	/**
	 * Show admin notices for redirect creation.
	 */
	public function show_redirect_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notices = get_option( self::NOTICE_OPTION, array() );
		if ( empty( $notices ) || ! is_array( $notices ) ) {
			return;
		}

		foreach ( $notices as $i => $notice ) {
			$origin = isset( $notice['origin'] ) ? $notice['origin'] : '';
			$action = isset( $notice['action'] ) ? $notice['action'] : 'delete';
			if ( empty( $origin ) ) {
				continue;
			}

			$redirect_url = add_query_arg( 'origin', rawurlencode( $origin ), admin_url( 'admin.php?page=mantis-redirects' ) );
			$dismiss_url = wp_nonce_url(
				add_query_arg(
					array(
						'mantis_dismiss_redirect' => 1,
						'mantis_notice_idx'      => $i,
					),
					admin_url( 'admin.php?page=mantis-redirects' )
				),
				'mantis_dismiss_redirect'
			);

			$action_text = 'trash' === $action
				? __( 'moved to trash', 'mantis-seo' )
				: __( 'permanently deleted', 'mantis-seo' );

			?>
			<div class="notice notice-info is-dismissible">
				<p>
					<?php
					printf(
						/* translators: 1: action (trashed/deleted), 2: URL path, 3: link to add redirect */
						esc_html__( 'A post was %1$s. The URL %2$s may need a redirect. %3$s', 'mantis-seo' ),
						esc_html( $action_text ),
						'<code>' . esc_html( $origin ) . '</code>',
						'<a href="' . esc_url( $redirect_url ) . '">' . esc_html__( 'Add redirect', 'mantis-seo' ) . '</a>'
					);
					?>
					| <a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Dismiss', 'mantis-seo' ); ?></a>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Dismiss redirect notice.
	 */
	public function dismiss_notice() {
		if ( ! isset( $_GET['mantis_dismiss_redirect'] ) || ! isset( $_GET['mantis_notice_idx'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'mantis_dismiss_redirect' ) ) {
			return;
		}
		if ( ! is_admin() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$idx = (int) $_GET['mantis_notice_idx'];
		$notices = get_option( self::NOTICE_OPTION, array() );
		if ( isset( $notices[ $idx ] ) ) {
			unset( $notices[ $idx ] );
			$notices = array_values( $notices );
			update_option( self::NOTICE_OPTION, $notices );
		}

		wp_safe_redirect( remove_query_arg( array( 'mantis_dismiss_redirect', 'mantis_notice_idx', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Get post path (URL path) for a post.
	 *
	 * @param int|WP_Post $post Post ID or object.
	 * @return string
	 */
	private function get_post_path( $post ) {
		$url = get_permalink( $post );
		if ( ! $url ) {
			return '';
		}
		$parsed = wp_parse_url( $url );
		return isset( $parsed['path'] ) ? $parsed['path'] : '';
	}
}
