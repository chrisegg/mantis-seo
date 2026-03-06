<?php
/**
 * Post and term metabox for SEO fields.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Metabox
 */
class Mantis_SEO_Metabox {

	/**
	 * Meta prefix.
	 *
	 * @var string
	 */
	const META_PREFIX = '_mantis_';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'save_post', array( $this, 'save_metabox' ), 10, 2 );
		add_action( 'category_add_form_fields', array( $this, 'term_add_fields' ), 10, 2 );
		add_action( 'category_edit_form_fields', array( $this, 'term_edit_fields' ), 10, 2 );
		add_action( 'edit_category', array( $this, 'save_term_meta' ), 10, 2 );
		add_action( 'created_category', array( $this, 'save_term_meta' ), 10, 2 );
		add_action( 'post_tag_add_form_fields', array( $this, 'term_add_fields' ), 10, 2 );
		add_action( 'post_tag_edit_form_fields', array( $this, 'term_edit_fields' ), 10, 2 );
		add_action( 'edit_post_tag', array( $this, 'save_term_meta' ), 10, 2 );
		add_action( 'created_post_tag', array( $this, 'save_term_meta' ), 10, 2 );
	}

	/**
	 * Add metabox.
	 */
	public function add_metabox() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'mantis_seo',
				__( 'Mantis SEO', 'mantis-seo' ),
				array( $this, 'render_metabox' ),
				$post_type,
				'side',
				1
			);
		}
	}

	/**
	 * Render metabox.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_metabox( $post ) {
		wp_nonce_field( 'mantis_seo_metabox', 'mantis_seo_metabox_nonce' );

		$fields = $this->get_field_definitions();
		$settings = Mantis_SEO_Main::get_settings();
		$title_template = 'post' === $post->post_type ? $settings['title_post'] : $settings['title_page'];
		if ( '' === trim( (string) $title_template ) ) {
			$title_template = '%post_title% %page% %separator% %site_name%';
		}

		// Compact trigger button in sidebar.
		echo '<div class="mantis-seo-metabox-trigger">';
		echo '<button type="button" class="button button-primary button-large mantis-seo-open-panel">';
		echo '<span class="dashicons dashicons-admin-generic" style="margin-right:4px;margin-top:3px;"></span>';
		echo esc_html__( 'SEO Settings', 'mantis-seo' );
		echo '</button>';
		echo '</div>';

		// Slide-out panel (stays in form for submission).
		echo '<div id="mantis-seo-slideout" class="mantis-seo-slideout" aria-hidden="true">';
		echo '<div class="mantis-seo-slideout-backdrop"></div>';
		echo '<div class="mantis-seo-slideout-panel">';
		echo '<div class="mantis-seo-slideout-header">';
		echo '<button type="button" class="mantis-seo-pin-toggle" title="' . esc_attr__( 'Pin panel open', 'mantis-seo' ) . '" aria-pressed="false">';
		echo '<span class="dashicons dashicons-location"></span>';
		echo '</button>';
		echo '<div class="mantis-seo-slideout-header-text">';
		echo '<h2>' . esc_html__( 'SEO Settings', 'mantis-seo' ) . '</h2>';
		echo '<p class="mantis-seo-slideout-subtitle">' . esc_html__( 'Manage meta title, description, and social sharing.', 'mantis-seo' ) . '</p>';
		echo '</div>';
		echo '<button type="button" class="mantis-seo-slideout-close" aria-label="' . esc_attr__( 'Close', 'mantis-seo' ) . '">&times;</button>';
		echo '</div>';
		echo '<nav class="mantis-seo-slideout-tabs" role="tablist">';
		$tab_labels = array(
			'meta'    => __( 'Meta', 'mantis-seo' ),
			'og'      => __( 'OG', 'mantis-seo' ),
			'twitter' => __( 'Twitter', 'mantis-seo' ),
		);
		$first = true;
		foreach ( array_keys( $fields ) as $tab_id ) {
			$active = $first ? ' mantis-seo-tab-active' : '';
			echo '<button type="button" role="tab" class="mantis-seo-tab' . esc_attr( $active ) . '" data-tab="' . esc_attr( $tab_id ) . '" aria-selected="' . ( $first ? 'true' : 'false' ) . '">' . esc_html( $tab_labels[ $tab_id ] ?? $tab_id ) . '</button>';
			$first  = false;
		}
		echo '</nav>';
		echo '<div class="mantis-seo-slideout-body">';
		$first = true;
		foreach ( $fields as $tab_id => $tab_fields ) {
			$active = $first ? ' mantis-seo-tab-panel-active' : '';
			echo '<div class="mantis-seo-tab-panel' . esc_attr( $active ) . '" role="tabpanel" id="mantis-seo-tab-' . esc_attr( $tab_id ) . '" aria-hidden="' . ( $first ? 'false' : 'true' ) . '">';
			$first  = false;
			echo '<div class="mantis-seo-metabox">';
			foreach ( $tab_fields as $key => $label ) {
				$value     = Mantis_SEO_Meta::get_post_meta( $post->ID, $key );
				$meta_key  = self::META_PREFIX . $key;
				$placeholder = ( 'title' === $key && '' === trim( (string) $value ) ) ? $title_template : '';

				if ( in_array( $key, array( 'robots_noindex', 'robots_nofollow' ), true ) ) {
					echo '<p>';
					echo '<label><input type="checkbox" name="' . esc_attr( $meta_key ) . '" value="1" ' . checked( $value, '1', false ) . ' /> ' . esc_html( $label ) . '</label>';
					echo '</p>';
				} else {
					$type       = ( 'canonical' === $key || 'og_image' === $key || 'twitter_image' === $key ) ? 'url' : 'text';
					$input_type = 'url' === $type ? 'url' : 'text';
					$tag        = 'description' === $key ? 'textarea' : 'input';
					echo '<p>';
					echo '<label for="' . esc_attr( $meta_key ) . '"><strong>' . esc_html( $label ) . '</strong></label><br>';
					if ( 'textarea' === $tag ) {
						echo '<textarea id="' . esc_attr( $meta_key ) . '" name="' . esc_attr( $meta_key ) . '" rows="2" class="large-text" style="width:100%">' . esc_textarea( $value ) . '</textarea>';
					} else {
						$place_attr = ( '' !== $placeholder ) ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
						echo '<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $meta_key ) . '" name="' . esc_attr( $meta_key ) . '" value="' . esc_attr( $value ) . '" class="large-text" style="width:100%"' . $place_attr . ' />';
					}
					echo '</p>';
				}
			}
			echo '</div></div>';
		}
		echo '</div></div></div>';
	}

	/**
	 * Get field definitions for the SEO metabox.
	 *
	 * @return array
	 */
	/**
	 * Get field definitions grouped by tab.
	 *
	 * @return array Tab slug => array of key => label.
	 */
	private function get_field_definitions() {
		return array(
			'meta'    => array(
				'focus_keyword'   => __( 'Focus Keyword', 'mantis-seo' ),
				'title'           => __( 'SEO Title', 'mantis-seo' ),
				'description'     => __( 'Meta Description', 'mantis-seo' ),
				'canonical'       => __( 'Canonical URL', 'mantis-seo' ),
				'robots_noindex'  => __( 'Noindex', 'mantis-seo' ),
				'robots_nofollow' => __( 'Nofollow', 'mantis-seo' ),
			),
			'og'      => array(
				'og_title'       => __( 'OG Title', 'mantis-seo' ),
				'og_description' => __( 'OG Description', 'mantis-seo' ),
				'og_image'       => __( 'OG Image URL', 'mantis-seo' ),
			),
			'twitter' => array(
				'twitter_title'       => __( 'Twitter Title', 'mantis-seo' ),
				'twitter_description' => __( 'Twitter Description', 'mantis-seo' ),
				'twitter_image'       => __( 'Twitter Image URL', 'mantis-seo' ),
			),
		);
	}

	/**
	 * Save metabox.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_metabox( $post_id, $post ) {
		if ( ! isset( $_POST['mantis_seo_metabox_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mantis_seo_metabox_nonce'] ) ), 'mantis_seo_metabox' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'focus_keyword', 'title', 'description', 'canonical', 'og_title', 'og_description', 'og_image',
			'twitter_title', 'twitter_description', 'twitter_image',
		);
		foreach ( $fields as $field ) {
			$meta_key = self::META_PREFIX . $field;
			if ( isset( $_POST[ $meta_key ] ) ) {
				$value = 'canonical' === $field || strpos( $field, 'image' ) !== false
					? esc_url_raw( wp_unslash( $_POST[ $meta_key ] ) )
					: sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) );
				if ( 'description' === $field ) {
					$value = sanitize_textarea_field( wp_unslash( $_POST[ $meta_key ] ) );
				}
				update_post_meta( $post_id, $meta_key, $value );
			}
		}

		$checkboxes = array( 'robots_noindex', 'robots_nofollow' );
		foreach ( $checkboxes as $field ) {
			$meta_key = self::META_PREFIX . $field;
			update_post_meta( $post_id, $meta_key, isset( $_POST[ $meta_key ] ) ? '1' : '0' );
		}
	}

	/**
	 * Term add form fields.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function term_add_fields( $taxonomy ) {
		wp_nonce_field( 'mantis_seo_term', 'mantis_seo_term_nonce' );
		?>
		<div class="form-field">
			<label for="mantis_term_focus_keyword"><?php esc_html_e( 'Focus Keyword', 'mantis-seo' ); ?></label>
			<input type="text" id="mantis_term_focus_keyword" name="mantis_term_focus_keyword" value="" />
		</div>
		<div class="form-field">
			<label for="mantis_term_title"><?php esc_html_e( 'SEO Title', 'mantis-seo' ); ?></label>
			<input type="text" id="mantis_term_title" name="mantis_term_title" value="" />
		</div>
		<div class="form-field">
			<label for="mantis_term_description"><?php esc_html_e( 'Meta Description', 'mantis-seo' ); ?></label>
			<textarea id="mantis_term_description" name="mantis_term_description" rows="2"></textarea>
		</div>
		<?php
	}

	/**
	 * Term edit form fields.
	 *
	 * @param WP_Term $term     Term object.
	 * @param string  $taxonomy Taxonomy slug.
	 */
	public function term_edit_fields( $term, $taxonomy ) {
		wp_nonce_field( 'mantis_seo_term', 'mantis_seo_term_nonce' );
		$focus_keyword = get_term_meta( $term->term_id, self::META_PREFIX . 'focus_keyword', true );
		$title         = get_term_meta( $term->term_id, self::META_PREFIX . 'title', true );
		$description   = get_term_meta( $term->term_id, self::META_PREFIX . 'description', true );
		?>
		<tr class="form-field">
			<th><label for="mantis_term_focus_keyword"><?php esc_html_e( 'Focus Keyword', 'mantis-seo' ); ?></label></th>
			<td><input type="text" id="mantis_term_focus_keyword" name="mantis_term_focus_keyword" value="<?php echo esc_attr( $focus_keyword ); ?>" class="large-text" /></td>
		</tr>
		<tr class="form-field">
			<th><label for="mantis_term_title"><?php esc_html_e( 'SEO Title', 'mantis-seo' ); ?></label></th>
			<td><input type="text" id="mantis_term_title" name="mantis_term_title" value="<?php echo esc_attr( $title ); ?>" class="large-text" /></td>
		</tr>
		<tr class="form-field">
			<th><label for="mantis_term_description"><?php esc_html_e( 'Meta Description', 'mantis-seo' ); ?></label></th>
			<td><textarea id="mantis_term_description" name="mantis_term_description" rows="2" class="large-text"><?php echo esc_textarea( $description ); ?></textarea></td>
		</tr>
		<?php
	}

	/**
	 * Save term meta.
	 *
	 * @param int $term_id Term ID.
	 */
	public function save_term_meta( $term_id ) {
		if ( ! isset( $_POST['mantis_seo_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mantis_seo_term_nonce'] ) ), 'mantis_seo_term' ) ) {
			return;
		}
		if ( isset( $_POST['mantis_term_focus_keyword'] ) ) {
			update_term_meta( $term_id, self::META_PREFIX . 'focus_keyword', sanitize_text_field( wp_unslash( $_POST['mantis_term_focus_keyword'] ) ) );
		}
		if ( isset( $_POST['mantis_term_title'] ) ) {
			update_term_meta( $term_id, self::META_PREFIX . 'title', sanitize_text_field( wp_unslash( $_POST['mantis_term_title'] ) ) );
		}
		$desc = isset( $_POST['mantis_term_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['mantis_term_description'] ) ) : '';
		update_term_meta( $term_id, self::META_PREFIX . 'description', $desc );
	}
}
