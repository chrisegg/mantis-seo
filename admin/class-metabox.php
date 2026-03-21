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
	 * Meta prefix (single source: Mantis_SEO_Meta).
	 *
	 * @var string
	 */
	const META_PREFIX = Mantis_SEO_Meta::META_PREFIX;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'save_post', array( $this, 'save_metabox' ), 10, 2 );
		add_action( 'wp_ajax_mantis_seo_save_meta', array( $this, 'ajax_save_meta' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'localize_metabox_script' ), 20 );
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
				'high'
			);
		}
	}

	/**
	 * Google-style snippet preview block (search result mockup).
	 *
	 * @param string $preview_title Resolved title text.
	 * @param string $preview_desc  Resolved description text.
	 * @param string $preview_url   Permalink.
	 */
	private function render_metabox_snippet_preview( $preview_title, $preview_desc, $preview_url ) {
		echo '<div class="mantis-seo-preview">';
		echo '<h3 class="mantis-seo-preview-heading">' . esc_html__( 'Preview', 'mantis-seo' ) . '</h3>';
		echo '<div class="mantis-seo-snippet">';
		echo '<a><span id="mantis-snippet-title">' . esc_html( $preview_title ) . '</span></a>';
		echo '<cite id="mantis-snippet-url">' . esc_url( $preview_url ) . '</cite>';
		echo '<span id="mantis-snippet-description">' . esc_html( $preview_desc ) . '</span>';
		echo '</div>';
		echo '<p class="mantis-seo-preview-counts"><span id="mantis-title-count">0</span> ' . esc_html__( 'characters', 'mantis-seo' ) . ' · <span id="mantis-desc-count">0</span> ' . esc_html__( 'characters', 'mantis-seo' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render metabox.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_metabox( $post ) {
		wp_nonce_field( 'mantis_seo_metabox', 'mantis_seo_metabox_nonce' );

		$fields         = $this->get_field_definitions();
		$title_template = $this->get_effective_default_title_template( $post->post_type );

		echo '<div class="mantis-seo-metabox-trigger">';
		echo '<button type="button" class="button button-primary button-large mantis-seo-open-panel">';
		echo '<span class="dashicons dashicons-admin-generic" style="margin-right:4px;margin-top:3px;"></span>';
		echo esc_html__( 'SEO Settings', 'mantis-seo' );
		echo '</button>';
		echo '</div>';

		// Slide-out panel (stays in form for submission).
		echo '<div id="mantis-seo-slideout" class="mantis-seo-slideout" aria-hidden="true" data-post-id="' . (int) $post->ID . '">';
		echo '<div class="mantis-seo-slideout-backdrop"></div>';
		echo '<div class="mantis-seo-slideout-panel-wrap">';
		echo '<button type="button" class="mantis-seo-resize-tab" title="' . esc_attr__( 'Expand panel', 'mantis-seo' ) . '" aria-label="' . esc_attr__( 'Expand panel', 'mantis-seo' ) . '"><span class="mantis-seo-resize-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="m3.86 8.753 5.482 4.796c.646.566 1.658.106 1.658-.753V3.204a1 1 0 0 0-1.659-.753l-5.48 4.796a1 1 0 0 0 0 1.506z"/></svg></span></button>';
		echo '<div class="mantis-seo-slideout-panel">';
		echo '<div class="mantis-seo-slideout-header">';
		echo '<button type="button" class="mantis-seo-pin-toggle" title="' . esc_attr__( 'Pin panel open', 'mantis-seo' ) . '" aria-pressed="false">';
		echo '<span class="mantis-seo-icon-tack" aria-hidden="true">';
		echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M9.828.722a.5.5 0 0 1 .354.146l4.95 4.95a.5.5 0 0 1 0 .707c-.48.48-1.072.588-1.503.588-.177 0-.335-.018-.46-.039l-3.134 3.134a6 6 0 0 1 .16 1.013c.046.702-.032 1.687-.72 2.375a.5.5 0 0 1-.707 0l-2.829-2.828-3.182 3.182c-.195.195-1.219.902-1.414.707s.512-1.22.707-1.414l3.182-3.182-2.828-2.829a.5.5 0 0 1 0-.707c.688-.688 1.673-.767 2.375-.72a6 6 0 0 1 1.013.16l3.134-3.133a3 3 0 0 1-.04-.461c0-.43.108-1.022.589-1.503a.5.5 0 0 1 .353-.146"/></svg>';
		echo '</span>';
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
			'og'      => __( 'Facebook', 'mantis-seo' ),
			'twitter' => __( 'X', 'mantis-seo' ),
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
		$seo_title           = Mantis_SEO_Meta::get_post_meta( $post->ID, 'title' );
		$seo_desc            = Mantis_SEO_Meta::get_post_meta( $post->ID, 'description' );
		$metadesc_template  = $this->get_effective_default_metadesc_template( $post->post_type );
		$preview_title_raw = ! empty( trim( (string) $seo_title ) ) ? $seo_title : $title_template;
		$preview_desc_raw  = ! empty( trim( (string) $seo_desc ) ) ? $seo_desc : $metadesc_template;
		$preview_title = Mantis_SEO_Meta::replace_vars_for_preview( $preview_title_raw, $post );
		$preview_desc  = Mantis_SEO_Meta::replace_vars_for_preview( $preview_desc_raw, $post );
		$preview_url   = get_permalink( $post );
		foreach ( $fields as $tab_id => $tab_fields ) {
			$active = $first ? ' mantis-seo-tab-panel-active' : '';
			echo '<div class="mantis-seo-tab-panel' . esc_attr( $active ) . '" role="tabpanel" id="mantis-seo-tab-' . esc_attr( $tab_id ) . '" aria-hidden="' . ( $first ? 'false' : 'true' ) . '">';
			$first  = false;
			echo '<div class="mantis-seo-metabox">';
			if ( 'meta' === $tab_id ) {
				$this->render_metabox_snippet_preview( $preview_title, $preview_desc, $preview_url );
			}
			foreach ( $tab_fields as $key => $label ) {
				$stored   = Mantis_SEO_Meta::get_post_meta( $post->ID, $key );
				$meta_key = self::META_PREFIX . $key;
				$placeholder = '';

				if ( in_array( $key, array( 'robots_noindex', 'robots_nofollow' ), true ) ) {
					if ( 'robots_noindex' === $key ) {
						echo '<div class="mantis-metabox-robots-group">';
					}
					$toggle_id = 'mantis-slideout-' . str_replace( '_', '-', $key );
					echo '<div class="mantis-metabox-robot-row">';
					echo '<label class="mantis-toggle-row" for="' . esc_attr( $toggle_id ) . '">';
					echo '<span class="mantis-toggle-row__text"><span class="mantis-toggle-row__label">' . esc_html( $label ) . '</span></span>';
					echo '<span class="mantis-toggle">';
					echo '<input type="checkbox" id="' . esc_attr( $toggle_id ) . '" class="mantis-toggle__input" name="' . esc_attr( $meta_key ) . '" value="1" ' . checked( $stored, '1', false ) . ' />';
					echo '<span class="mantis-toggle__track" aria-hidden="true"></span>';
					echo '</span></label></div>';
					if ( 'robots_nofollow' === $key ) {
						echo '</div>';
					}
				} else {
					$type       = ( 'canonical' === $key || 'og_image' === $key || 'twitter_image' === $key ) ? 'url' : 'text';
					$input_type = 'url' === $type ? 'url' : 'text';
					$is_image   = ( 'og_image' === $key || 'twitter_image' === $key );
					$is_desc    = ( 'description' === $key || false !== strpos( $key, '_description' ) );
					$tag        = $is_desc ? 'textarea' : 'input';
					$rows       = $is_desc ? 4 : 2;
					$has_vars   = in_array( $key, array( 'title', 'description', 'og_title', 'og_description', 'twitter_title', 'twitter_description' ), true );
					echo '<p>';
					if ( $has_vars ) {
						$hidden_template = $stored;
						if ( 'title' === $key && '' === trim( (string) $stored ) ) {
							$hidden_template = $title_template;
						} elseif ( 'description' === $key && '' === trim( (string) $stored ) ) {
							$hidden_template = $metadesc_template;
						}
						$editor_classes = 'mantis-template-editor';
						if ( in_array( $key, array( 'description', 'og_description', 'twitter_description' ), true ) ) {
							$editor_classes .= ' mantis-template-editor--multiline';
						}
						echo '<div class="mantis-var-field-wrap" data-field="' . esc_attr( $meta_key ) . '">';
						echo '<div class="mantis-var-label-row">';
						echo '<label for="' . esc_attr( $meta_key ) . '"><strong>' . esc_html( $label ) . '</strong></label>';
						echo '<div class="mantis-var-insert-wrap">';
						echo '<button type="button" class="button button-small mantis-settings-var-btn" aria-haspopup="true" aria-expanded="false" aria-label="' . esc_attr__( 'Insert variable', 'mantis-seo' ) . '">';
						echo esc_html__( 'Insert variable', 'mantis-seo' ) . '</button>';
						echo '<div class="mantis-var-dropdown mantis-settings-var-dropdown" role="menu" aria-hidden="true">';
						foreach ( $this->get_variable_options() as $var => $var_label ) {
							echo '<button type="button" role="menuitem" class="mantis-var-option mantis-settings-var-option" data-var="' . esc_attr( $var ) . '">' . esc_html( $var_label ) . '</button>';
						}
						echo '</div></div>';
						echo '</div>';
						echo '<div class="mantis-var-input-wrap">';
						echo '<div class="mantis-template-editor-wrap">';
						echo '<input type="hidden" name="' . esc_attr( $meta_key ) . '" class="mantis-template-hidden" value="' . esc_attr( $hidden_template ) . '" autocomplete="off" />';
						echo '<div id="' . esc_attr( $meta_key ) . '" class="' . esc_attr( $editor_classes ) . '" data-mantis-template-editor data-pill-mode="label" tabindex="0" role="textbox" aria-label="' . esc_attr( $label ) . '"></div>';
						echo '</div>';
					} else {
						echo '<label for="' . esc_attr( $meta_key ) . '"><strong>' . esc_html( $label ) . '</strong></label><br>';
					}
					if ( ! $has_vars && 'textarea' === $tag ) {
						echo '<textarea id="' . esc_attr( $meta_key ) . '" name="' . esc_attr( $meta_key ) . '" rows="' . esc_attr( $rows ) . '" class="large-text" style="width:100%">' . esc_textarea( $stored ) . '</textarea>';
					} elseif ( ! $has_vars ) {
						$place_attr = ( '' !== $placeholder ) ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
						if ( $is_image ) {
							$input_id = 'mantis_meta_' . $key;
							echo '<div class="mantis-image-field-wrap">';
							echo '<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $meta_key ) . '" value="' . esc_attr( $stored ) . '" class="large-text mantis-image-input" style="width:100%"' . $place_attr . ' />';
							echo ' <button type="button" class="button mantis-media-upload" data-target="#' . esc_attr( $input_id ) . '" aria-label="' . esc_attr__( 'Select image from media library', 'mantis-seo' ) . '">' . esc_html__( 'Upload / Select', 'mantis-seo' ) . '</button>';
							echo '<span class="mantis-image-preview" data-for="#' . esc_attr( $input_id ) . '"></span>';
							echo '</div>';
						} else {
							echo '<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $meta_key ) . '" name="' . esc_attr( $meta_key ) . '" value="' . esc_attr( $stored ) . '" class="large-text mantis-var-field" style="width:100%"' . $place_attr . ' />';
						}
					}
					if ( $has_vars ) {
						echo '</div></div>';
					}
					echo '</p>';
				}
			}
			echo '</div></div>';
		}
		echo '</div>';
		echo '<div class="mantis-seo-slideout-footer">';
		echo '<button type="button" class="button button-primary mantis-seo-save-btn">' . esc_html__( 'Save SEO', 'mantis-seo' ) . '</button>';
		echo ' <span class="mantis-seo-save-status" aria-live="polite"></span>';
		echo '</div>';
		echo '</div></div></div>';
	}

	/**
	 * SEO title template from global settings for a post type (with fallback).
	 *
	 * @param string $post_type Post type slug.
	 * @return string
	 */
	private function get_effective_default_title_template( $post_type ) {
		$settings = Mantis_SEO_Main::get_settings();
		$template = 'post' === $post_type ? $settings['title_post'] : $settings['title_page'];
		if ( '' === trim( (string) $template ) ) {
			$template = '%post_title% %page% %separator% %site_name%';
		}
		return $template;
	}

	/**
	 * Default meta description template from global settings for a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return string
	 */
	private function get_effective_default_metadesc_template( $post_type ) {
		$settings = Mantis_SEO_Main::get_settings();
		$template = 'post' === $post_type ? ( $settings['metadesc_post'] ?? '' ) : ( $settings['metadesc_page'] ?? '' );
		return is_string( $template ) ? $template : '';
	}

	/**
	 * Get variable options for the attribute selector.
	 *
	 * @return array Map of %var% => human-readable label.
	 */
	private function get_variable_options() {
		return Mantis_SEO_Meta::get_variable_dropdown_options();
	}

	/**
	 * Localize metabox script with replacement vars for live preview.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function localize_metabox_script( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$post = null;
		if ( 'post.php' === $hook && ! empty( $_GET['post'] ) ) {
			$post = get_post( (int) $_GET['post'] );
		}
		$replacements           = Mantis_SEO_Meta::get_replacements_for_preview( $post );
		$default_title_template = '';
		if ( $post ) {
			$default_title_template = $this->get_effective_default_title_template( $post->post_type );
		} elseif ( ! empty( $_GET['post_type'] ) && is_string( $_GET['post_type'] ) ) {
			$default_title_template = $this->get_effective_default_title_template( sanitize_key( wp_unslash( $_GET['post_type'] ) ) );
		} else {
			$default_title_template = $this->get_effective_default_title_template( 'post' );
		}
		$default_metadesc_template = '';
		if ( $post ) {
			$default_metadesc_template = $this->get_effective_default_metadesc_template( $post->post_type );
		} elseif ( ! empty( $_GET['post_type'] ) && is_string( $_GET['post_type'] ) ) {
			$default_metadesc_template = $this->get_effective_default_metadesc_template( sanitize_key( wp_unslash( $_GET['post_type'] ) ) );
		} else {
			$default_metadesc_template = $this->get_effective_default_metadesc_template( 'post' );
		}
		wp_localize_script( 'mantis-seo-metabox', 'mantisSeoMetabox', array(
			'replaceVars'                    => $replacements,
			'defaultTitleTemplate'           => $default_title_template,
			'defaultMetaDescriptionTemplate' => $default_metadesc_template,
			'noTitlePlaceholder'             => __( 'No title', 'mantis-seo' ),
			'ajaxUrl'                        => admin_url( 'admin-ajax.php' ),
			'ajaxNonce'                      => wp_create_nonce( 'mantis_seo_save_meta' ),
		) );
	}

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
				'og_title'       => __( 'Facebook Title', 'mantis-seo' ),
				'og_description' => __( 'Facebook Description', 'mantis-seo' ),
				'og_image'       => __( 'Facebook Image URL', 'mantis-seo' ),
			),
			'twitter' => array(
				'twitter_title'       => __( 'X Title', 'mantis-seo' ),
				'twitter_description' => __( 'X Description', 'mantis-seo' ),
				'twitter_image'       => __( 'X Image URL', 'mantis-seo' ),
			),
		);
	}

	/**
	 * Persist SEO fields from POST (classic save or AJAX).
	 *
	 * @param int     $post_id       Post ID.
	 * @param WP_Post $post          Post object.
	 * @param string  $checkbox_mode 'isset' for form submit (unchecked = absent), 'ajax' for explicit values.
	 */
	private function save_post_seo_fields( $post_id, $post, $checkbox_mode = 'isset' ) {
		$fields = array(
			'focus_keyword', 'title', 'description', 'canonical', 'og_title', 'og_description', 'og_image',
			'twitter_title', 'twitter_description', 'twitter_image',
		);
		foreach ( $fields as $field ) {
			$meta_key = self::META_PREFIX . $field;
			if ( isset( $_POST[ $meta_key ] ) ) {
				if ( in_array( $field, array( 'description', 'og_description', 'twitter_description' ), true ) ) {
					$value = sanitize_textarea_field( wp_unslash( $_POST[ $meta_key ] ) );
				} elseif ( 'canonical' === $field || strpos( $field, 'image' ) !== false ) {
					$value = esc_url_raw( wp_unslash( $_POST[ $meta_key ] ) );
				} else {
					$value = sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) );
				}
				if ( 'title' === $field ) {
					$default_t = $this->get_effective_default_title_template( $post->post_type );
					if ( '' === trim( $value ) || trim( $value ) === trim( $default_t ) ) {
						delete_post_meta( $post_id, $meta_key );
						continue;
					}
				}
				if ( 'description' === $field ) {
					$default_d = $this->get_effective_default_metadesc_template( $post->post_type );
					if ( '' === trim( $value ) || trim( $value ) === trim( $default_d ) ) {
						delete_post_meta( $post_id, $meta_key );
						continue;
					}
				}
				update_post_meta( $post_id, $meta_key, $value );
			}
		}

		$checkboxes = array( 'robots_noindex', 'robots_nofollow' );
		foreach ( $checkboxes as $field ) {
			$meta_key = self::META_PREFIX . $field;
			if ( 'ajax' === $checkbox_mode ) {
				$value = isset( $_POST[ $meta_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) ) : '';
				update_post_meta( $post_id, $meta_key, ( '1' === $value ) ? '1' : '0' );
			} else {
				update_post_meta( $post_id, $meta_key, isset( $_POST[ $meta_key ] ) ? '1' : '0' );
			}
		}
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

		$this->save_post_seo_fields( $post_id, $post, 'isset' );
	}

	/**
	 * AJAX handler for Save SEO button.
	 */
	public function ajax_save_meta() {
		if ( ! isset( $_POST['mantis_seo_ajax_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mantis_seo_ajax_nonce'] ) ), 'mantis_seo_save_meta' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mantis-seo' ) ) );
		}
		$post_id  = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$post_obj = $post_id ? get_post( $post_id ) : null;
		if ( ! $post_id || ! $post_obj ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post.', 'mantis-seo' ) ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'mantis-seo' ) ) );
		}

		$this->save_post_seo_fields( $post_id, $post_obj, 'ajax' );

		wp_send_json_success( array( 'message' => __( 'SEO settings saved.', 'mantis-seo' ) ) );
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
		<div class="form-field">
			<label><?php esc_html_e( 'Robots', 'mantis-seo' ); ?></label>
			<label style="display: inline-block; margin-right: 1em;"><input type="checkbox" name="mantis_term_robots_noindex" value="1" /> <?php esc_html_e( 'Noindex', 'mantis-seo' ); ?></label>
			<label style="display: inline-block;"><input type="checkbox" name="mantis_term_robots_nofollow" value="1" /> <?php esc_html_e( 'Nofollow', 'mantis-seo' ); ?></label>
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
		$robots_noindex  = get_term_meta( $term->term_id, self::META_PREFIX . 'robots_noindex', true );
		$robots_nofollow = get_term_meta( $term->term_id, self::META_PREFIX . 'robots_nofollow', true );
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
		<tr class="form-field">
			<th><label><?php esc_html_e( 'Robots', 'mantis-seo' ); ?></label></th>
			<td>
				<label style="display: inline-block; margin-right: 1em;"><input type="checkbox" name="mantis_term_robots_noindex" value="1" <?php checked( $robots_noindex, '1' ); ?> /> <?php esc_html_e( 'Noindex', 'mantis-seo' ); ?></label>
				<label style="display: inline-block;"><input type="checkbox" name="mantis_term_robots_nofollow" value="1" <?php checked( $robots_nofollow, '1' ); ?> /> <?php esc_html_e( 'Nofollow', 'mantis-seo' ); ?></label>
			</td>
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
		if ( ! current_user_can( 'edit_term', (int) $term_id ) ) {
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
		update_term_meta( $term_id, self::META_PREFIX . 'robots_noindex', isset( $_POST['mantis_term_robots_noindex'] ) ? '1' : '0' );
		update_term_meta( $term_id, self::META_PREFIX . 'robots_nofollow', isset( $_POST['mantis_term_robots_nofollow'] ) ? '1' : '0' );
	}
}
