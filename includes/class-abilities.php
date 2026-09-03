<?php
/**
 * WordPress Abilities API + MCP exposure for per-post SEO.
 *
 * Registers when wp_register_ability() exists (WordPress 6.9+ or Abilities API package).
 * No-ops cleanly on older sites. MCP clients see tools when the MCP adapter is present
 * and meta.mcp.public is true.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Abilities
 */
class Mantis_SEO_Abilities {

	/**
	 * Ability category slug.
	 *
	 * @var string
	 */
	const CATEGORY = 'mantis-seo';

	/**
	 * Constructor — hooks registration when the Abilities API is available.
	 */
	public function __construct() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Whether the Abilities API primitives exist on this site.
	 *
	 * @return bool
	 */
	public static function api_available() {
		return function_exists( 'wp_register_ability' ) && function_exists( 'wp_register_ability_category' );
	}

	/**
	 * Tool catalog for settings UI and registration (slug => config).
	 *
	 * Slugs match the ability name after `mantis-seo/`.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_tool_definitions() {
		return array(
			'posts-list-seo'  => array(
				'label'       => __( 'List posts SEO', 'mantis-seo' ),
				'description' => __( 'List posts/pages with SEO summary; find gaps with missing_only.', 'mantis-seo' ),
				'readonly'    => true,
			),
			'post-get-seo'    => array(
				'label'       => __( 'Get post SEO', 'mantis-seo' ),
				'description' => __( 'Read one post’s SEO fields and content excerpt.', 'mantis-seo' ),
				'readonly'    => true,
			),
			'settings-get'    => array(
				'label'       => __( 'Get SEO settings', 'mantis-seo' ),
				'description' => __( 'Read global title and meta description templates.', 'mantis-seo' ),
				'readonly'    => true,
			),
			'post-update-seo' => array(
				'label'       => __( 'Update post SEO', 'mantis-seo' ),
				'description' => __( 'Write SEO title, description, and related fields for a post.', 'mantis-seo' ),
				'readonly'    => false,
			),
		);
	}

	/**
	 * Default per-tool enable flags (all off).
	 *
	 * @return array<string, bool>
	 */
	public static function get_default_tool_flags() {
		$flags = array();
		foreach ( array_keys( self::get_tool_definitions() ) as $slug ) {
			$flags[ $slug ] = false;
		}
		return $flags;
	}

	/**
	 * Whether the master MCP / abilities switch is on.
	 *
	 * @return bool
	 */
	public static function is_mcp_enabled() {
		$settings = Mantis_SEO_Main::get_settings();
		return ! empty( $settings['mcp_enabled'] );
	}

	/**
	 * Whether a specific ability slug is enabled for clients/MCP.
	 *
	 * Requires master switch + per-tool opt-in.
	 *
	 * @param string $slug Tool slug (e.g. posts-list-seo).
	 * @return bool
	 */
	public static function is_ability_enabled( $slug ) {
		if ( ! self::is_mcp_enabled() ) {
			return false;
		}
		$settings = Mantis_SEO_Main::get_settings();
		$tools    = isset( $settings['mcp_abilities'] ) && is_array( $settings['mcp_abilities'] )
			? $settings['mcp_abilities']
			: array();
		return ! empty( $tools[ $slug ] );
	}

	/**
	 * Sanitize mcp_abilities from settings input.
	 *
	 * @param mixed $input Raw mcp_abilities value.
	 * @return array<string, bool>
	 */
	public static function sanitize_tool_flags( $input ) {
		$defaults = self::get_default_tool_flags();
		if ( ! is_array( $input ) ) {
			return $defaults;
		}
		$out = array();
		foreach ( $defaults as $slug => $default ) {
			$out[ $slug ] = ! empty( $input[ $slug ] );
		}
		return $out;
	}

	/**
	 * Register the Mantis SEO ability category when MCP is enabled.
	 */
	public function register_category() {
		if ( ! self::api_available() || ! self::is_mcp_enabled() ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Mantis SEO', 'mantis-seo' ),
				'description' => __( 'Per-post SEO metadata tools for titles, descriptions, social tags, and related settings.', 'mantis-seo' ),
			)
		);
	}

	/**
	 * Register only abilities that are opted in under Advanced → MCP.
	 */
	public function register_abilities() {
		if ( ! self::api_available() || ! self::is_mcp_enabled() ) {
			return;
		}

		$map = array(
			'posts-list-seo'  => 'register_list_seo',
			'post-get-seo'    => 'register_get_seo',
			'post-update-seo' => 'register_update_seo',
			'settings-get'    => 'register_settings_get',
		);

		foreach ( $map as $slug => $method ) {
			if ( self::is_ability_enabled( $slug ) && method_exists( $this, $method ) ) {
				$this->{$method}();
			}
		}
	}

	/**
	 * Shared client/MCP meta for an ability.
	 *
	 * @param bool   $readonly     Whether the ability only reads data.
	 * @param string $instructions Agent-facing usage hints.
	 * @return array<string, mixed>
	 */
	private function client_meta( $readonly, $instructions ) {
		return array(
			'show_in_rest' => true,
			'public'       => true,
			'mcp'          => array(
				'public' => true,
				'type'   => 'tool',
			),
			'annotations'  => array(
				'readonly'     => (bool) $readonly,
				'destructive'  => false,
				'idempotent'   => true,
				'instructions' => $instructions,
			),
		);
	}

	/**
	 * JSON Schema properties for writable SEO fields.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function seo_field_schema_properties() {
		$props = array();
		foreach ( Mantis_SEO_Rest_Meta::get_field_definitions() as $key => $config ) {
			$props[ $key ] = array(
				'type'        => 'string',
				'description' => isset( $config['description'] ) ? $config['description'] : $key,
			);
		}
		return $props;
	}

	/**
	 * List posts/pages with SEO summary.
	 */
	private function register_list_seo() {
		wp_register_ability(
			'mantis-seo/posts-list-seo',
			array(
				'label'               => __( 'List posts SEO', 'mantis-seo' ),
				'description'         => __( 'List public posts or pages with current Mantis SEO meta and resolved title/description. Use missing_only to find content without custom SEO title or description.', 'mantis-seo' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type'    => array(
							'type'        => 'string',
							'description' => __( 'Post type slug (default: any public type). Examples: post, page.', 'mantis-seo' ),
							'default'     => '',
						),
						'status'       => array(
							'type'        => 'string',
							'description' => __( 'Post status (default: publish).', 'mantis-seo' ),
							'default'     => 'publish',
						),
						'missing_only' => array(
							'type'        => 'boolean',
							'description' => __( 'When true, only return items missing custom _mantis_title or _mantis_description.', 'mantis-seo' ),
							'default'     => false,
						),
						'per_page'     => array(
							'type'        => 'integer',
							'description' => __( 'Number of items (1–100, default 20).', 'mantis-seo' ),
							'default'     => 20,
							'minimum'     => 1,
							'maximum'     => 100,
						),
						'page'         => array(
							'type'        => 'integer',
							'description' => __( 'Page number (default 1).', 'mantis-seo' ),
							'default'     => 1,
							'minimum'     => 1,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'total'    => array( 'type' => 'integer' ),
						'page'     => array( 'type' => 'integer' ),
						'per_page' => array( 'type' => 'integer' ),
						'items'    => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_list_seo' ),
				'permission_callback' => array( $this, 'permission_list_seo' ),
				'meta'                => $this->client_meta(
					true,
					'Start here to discover posts needing SEO. Prefer missing_only=true for bulk generation. Then call mantis-seo/post-get-seo for full content context before writing.'
				),
			)
		);
	}

	/**
	 * Get SEO + content context for one post.
	 */
	private function register_get_seo() {
		wp_register_ability(
			'mantis-seo/post-get-seo',
			array(
				'label'               => __( 'Get post SEO', 'mantis-seo' ),
				'description'         => __( 'Get Mantis SEO fields, resolved title/description, and a plain-text content excerpt for one post or page.', 'mantis-seo' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'post_id' ),
					'properties'           => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => __( 'Post ID.', 'mantis-seo' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'             => array( 'type' => 'integer' ),
						'post_type'           => array( 'type' => 'string' ),
						'title'               => array( 'type' => 'string' ),
						'url'                 => array( 'type' => 'string' ),
						'excerpt'             => array( 'type' => 'string' ),
						'content_excerpt'     => array( 'type' => 'string' ),
						'seo'                 => array( 'type' => 'object' ),
						'resolved_title'      => array( 'type' => 'string' ),
						'resolved_description'=> array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_get_seo' ),
				'permission_callback' => array( $this, 'permission_get_seo' ),
				'meta'                => $this->client_meta(
					true,
					'Use before generating SEO copy. Base title/description on post title and content_excerpt. Do not invent facts not present in the content.'
				),
			)
		);
	}

	/**
	 * Update SEO meta for one post.
	 */
	private function register_update_seo() {
		$field_props = $this->seo_field_schema_properties();

		wp_register_ability(
			'mantis-seo/post-update-seo',
			array(
				'label'               => __( 'Update post SEO', 'mantis-seo' ),
				'description'         => __( 'Update Mantis SEO meta for a post or page. Only provided fields are written. Empty title/description clears the custom override so global templates apply.', 'mantis-seo' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'post_id' ),
					'properties'           => array_merge(
						array(
							'post_id' => array(
								'type'        => 'integer',
								'description' => __( 'Post ID.', 'mantis-seo' ),
							),
							'force'   => array(
								'type'        => 'boolean',
								'description' => __( 'When false (default), refuse to overwrite existing custom title or description.', 'mantis-seo' ),
								'default'     => false,
							),
							'seo'     => array(
								'type'                 => 'object',
								'description'          => __( 'SEO fields to set (keys without _mantis_ prefix).', 'mantis-seo' ),
								'properties'           => $field_props,
								'additionalProperties' => false,
							),
						),
						$field_props
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'              => array( 'type' => 'integer' ),
						'updated'              => array( 'type' => 'array' ),
						'seo'                  => array( 'type' => 'object' ),
						'resolved_title'       => array( 'type' => 'string' ),
						'resolved_description' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'execute_update_seo' ),
				'permission_callback' => array( $this, 'permission_update_seo' ),
				'meta'                => $this->client_meta(
					false,
					'Pass fields either at the top level (title, description, …) or nested under seo. Only set title/description when providing a real override. Skip posts that already have custom meta unless force=true. Robots flags are "1" or "0".'
				),
			)
		);
	}

	/**
	 * Read global SEO template settings (readonly).
	 */
	private function register_settings_get() {
		wp_register_ability(
			'mantis-seo/settings-get',
			array(
				'label'               => __( 'Get SEO settings', 'mantis-seo' ),
				'description'         => __( 'Get global Mantis SEO title/description templates and related site-wide SEO settings (read-only).', 'mantis-seo' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type' => 'object',
				),
				'execute_callback'    => array( $this, 'execute_settings_get' ),
				'permission_callback' => array( $this, 'permission_settings_get' ),
				'meta'                => $this->client_meta(
					true,
					'Use to understand global title/metadesc templates before deciding whether a per-post override is needed.'
				),
			)
		);
	}

	/**
	 * Permission: list SEO ability enabled + edit_posts.
	 *
	 * @param array $input Input (unused).
	 * @return bool|WP_Error
	 */
	public function permission_list_seo( $input = array() ) {
		unset( $input );
		if ( ! self::is_ability_enabled( 'posts-list-seo' ) ) {
			return new WP_Error( 'mantis_seo_mcp_disabled', __( 'This Mantis SEO ability is disabled in settings.', 'mantis-seo' ) );
		}
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Permission: get-seo ability enabled + edit the post.
	 *
	 * @param array $input Input with post_id.
	 * @return bool|WP_Error
	 */
	public function permission_get_seo( $input = array() ) {
		if ( ! self::is_ability_enabled( 'post-get-seo' ) ) {
			return new WP_Error( 'mantis_seo_mcp_disabled', __( 'This Mantis SEO ability is disabled in settings.', 'mantis-seo' ) );
		}
		return $this->permission_edit_post_from_input( $input );
	}

	/**
	 * Permission: update-seo ability enabled + edit the post.
	 *
	 * @param array $input Input with post_id.
	 * @return bool|WP_Error
	 */
	public function permission_update_seo( $input = array() ) {
		if ( ! self::is_ability_enabled( 'post-update-seo' ) ) {
			return new WP_Error( 'mantis_seo_mcp_disabled', __( 'This Mantis SEO ability is disabled in settings.', 'mantis-seo' ) );
		}
		return $this->permission_edit_post_from_input( $input );
	}

	/**
	 * Permission: user can edit the given post_id.
	 *
	 * @param array $input Input with post_id.
	 * @return bool|WP_Error
	 */
	public function permission_edit_post_from_input( $input = array() ) {
		$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		if ( ! $post_id ) {
			return new WP_Error( 'mantis_seo_missing_post_id', __( 'post_id is required.', 'mantis-seo' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'mantis_seo_cannot_edit', __( 'You cannot edit this post.', 'mantis-seo' ) );
		}
		return true;
	}

	/**
	 * Permission: settings-get enabled + edit_posts.
	 *
	 * @param array $input Input (unused).
	 * @return bool|WP_Error
	 */
	public function permission_settings_get( $input = array() ) {
		unset( $input );
		if ( ! self::is_ability_enabled( 'settings-get' ) ) {
			return new WP_Error( 'mantis_seo_mcp_disabled', __( 'This Mantis SEO ability is disabled in settings.', 'mantis-seo' ) );
		}
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Execute list ability.
	 *
	 * @param array $input Input.
	 * @return array|WP_Error
	 */
	public function execute_list_seo( $input = array() ) {
		$input         = is_array( $input ) ? $input : array();
		$per_page      = isset( $input['per_page'] ) ? min( 100, max( 1, (int) $input['per_page'] ) ) : 20;
		$page          = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$status        = ! empty( $input['status'] ) ? sanitize_key( $input['status'] ) : 'publish';
		$missing_only  = ! empty( $input['missing_only'] );
		$post_type_in  = ! empty( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : '';

		$public_types = get_post_types( array( 'public' => true ), 'names' );
		if ( $post_type_in ) {
			if ( ! in_array( $post_type_in, $public_types, true ) ) {
				return new WP_Error( 'mantis_seo_invalid_post_type', __( 'Invalid or non-public post type.', 'mantis-seo' ) );
			}
			$post_types = array( $post_type_in );
		} else {
			$post_types = array_values( $public_types );
		}

		$query = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => $status,
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => false,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}

			$seo = $this->get_seo_fields_for_post( $post->ID );
			if ( $missing_only ) {
				$has_title = '' !== trim( (string) $seo['title'] );
				$has_desc  = '' !== trim( (string) $seo['description'] );
				if ( $has_title && $has_desc ) {
					continue;
				}
			}

			$items[] = array(
				'post_id'              => (int) $post->ID,
				'post_type'            => $post->post_type,
				'title'                => get_the_title( $post ),
				'url'                  => get_permalink( $post ),
				'status'               => $post->post_status,
				'has_custom_title'     => '' !== trim( (string) $seo['title'] ),
				'has_custom_description' => '' !== trim( (string) $seo['description'] ),
				'seo'                  => $seo,
				'resolved_title'       => Mantis_SEO_Meta::resolve_document_title_for_post( $post ),
				'resolved_description' => Mantis_SEO_Meta::resolve_post_meta_description( $post ),
			);
		}

		return array(
			'total'    => (int) $query->found_posts,
			'page'     => $page,
			'per_page' => $per_page,
			'items'    => $items,
		);
	}

	/**
	 * Execute get ability.
	 *
	 * @param array $input Input.
	 * @return array|WP_Error
	 */
	public function execute_get_seo( $input = array() ) {
		$post = $this->get_editable_post_from_input( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$content = wp_strip_all_tags( (string) $post->post_content );
		$content = preg_replace( '/\s+/', ' ', $content );
		$content = is_string( $content ) ? trim( $content ) : '';
		if ( strlen( $content ) > 4000 ) {
			$content = substr( $content, 0, 4000 ) . '…';
		}

		return array(
			'post_id'              => (int) $post->ID,
			'post_type'            => $post->post_type,
			'title'                => get_the_title( $post ),
			'url'                  => get_permalink( $post ),
			'status'               => $post->post_status,
			'excerpt'              => wp_strip_all_tags( (string) $post->post_excerpt ),
			'content_excerpt'      => $content,
			'seo'                  => $this->get_seo_fields_for_post( $post->ID ),
			'resolved_title'       => Mantis_SEO_Meta::resolve_document_title_for_post( $post ),
			'resolved_description' => Mantis_SEO_Meta::resolve_post_meta_description( $post ),
		);
	}

	/**
	 * Execute update ability.
	 *
	 * @param array $input Input.
	 * @return array|WP_Error
	 */
	public function execute_update_seo( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		$post  = $this->get_editable_post_from_input( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$force = ! empty( $input['force'] );
		$seo   = array();
		if ( isset( $input['seo'] ) && is_array( $input['seo'] ) ) {
			$seo = $input['seo'];
		}

		$definitions = Mantis_SEO_Rest_Meta::get_field_definitions();
		foreach ( array_keys( $definitions ) as $key ) {
			if ( array_key_exists( $key, $input ) && ! array_key_exists( $key, $seo ) ) {
				$seo[ $key ] = $input[ $key ];
			}
		}

		if ( empty( $seo ) ) {
			return new WP_Error( 'mantis_seo_no_fields', __( 'No SEO fields provided to update.', 'mantis-seo' ) );
		}

		$existing = $this->get_seo_fields_for_post( $post->ID );
		if ( ! $force ) {
			$touching_title = array_key_exists( 'title', $seo );
			$touching_desc  = array_key_exists( 'description', $seo );
			if ( $touching_title && '' !== trim( (string) $existing['title'] ) ) {
				return new WP_Error(
					'mantis_seo_exists',
					__( 'Custom SEO title already set. Pass force=true to overwrite.', 'mantis-seo' )
				);
			}
			if ( $touching_desc && '' !== trim( (string) $existing['description'] ) ) {
				return new WP_Error(
					'mantis_seo_exists',
					__( 'Custom meta description already set. Pass force=true to overwrite.', 'mantis-seo' )
				);
			}
		}

		$updated = array();
		foreach ( $seo as $key => $raw ) {
			if ( ! isset( $definitions[ $key ] ) ) {
				continue;
			}
			$value = $this->sanitize_seo_field( $key, $raw, $definitions[ $key ] );
			$meta_key = Mantis_SEO_Meta::META_PREFIX . $key;

			if ( in_array( $key, array( 'title', 'description' ), true ) && '' === trim( (string) $value ) ) {
				delete_post_meta( $post->ID, $meta_key );
				$updated[] = $key;
				continue;
			}

			if ( in_array( $key, array( 'schema_page_type', 'schema_article_type' ), true ) && '' === $value ) {
				delete_post_meta( $post->ID, $meta_key );
				$updated[] = $key;
				continue;
			}

			update_post_meta( $post->ID, $meta_key, $value );
			$updated[] = $key;
		}

		// Refresh post object for resolved preview after writes.
		$post = get_post( $post->ID );

		return array(
			'post_id'              => (int) $post->ID,
			'updated'              => $updated,
			'seo'                  => $this->get_seo_fields_for_post( $post->ID ),
			'resolved_title'       => Mantis_SEO_Meta::resolve_document_title_for_post( $post ),
			'resolved_description' => Mantis_SEO_Meta::resolve_post_meta_description( $post ),
		);
	}

	/**
	 * Execute settings get.
	 *
	 * @param array $input Input (unused).
	 * @return array
	 */
	public function execute_settings_get( $input = array() ) {
		unset( $input );
		$settings = Mantis_SEO_Main::get_settings();
		$keys     = array(
			'title_home',
			'title_post',
			'title_page',
			'separator',
			'metadesc_home',
			'metadesc_post',
			'metadesc_page',
			'noindex_author',
			'noindex_date',
			'noindex_category',
			'noindex_tag',
			'og_default_image',
			'twitter_card_type',
			'sitemap_enabled',
			'sitemap_post_types',
		);

		$out = array();
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$out[ $key ] = $settings[ $key ];
			}
		}
		return $out;
	}

	/**
	 * Resolve an editable post from input.
	 *
	 * @param array $input Input.
	 * @return WP_Post|WP_Error
	 */
	private function get_editable_post_from_input( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		if ( ! $post_id ) {
			return new WP_Error( 'mantis_seo_missing_post_id', __( 'post_id is required.', 'mantis-seo' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'mantis_seo_not_found', __( 'Post not found.', 'mantis-seo' ) );
		}

		$public_types = get_post_types( array( 'public' => true ), 'names' );
		if ( ! in_array( $post->post_type, $public_types, true ) ) {
			return new WP_Error( 'mantis_seo_invalid_post_type', __( 'Post type is not public.', 'mantis-seo' ) );
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'mantis_seo_cannot_edit', __( 'You cannot edit this post.', 'mantis-seo' ) );
		}

		return $post;
	}

	/**
	 * All SEO field values for a post (keys without prefix).
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, string>
	 */
	private function get_seo_fields_for_post( $post_id ) {
		$out = array();
		foreach ( array_keys( Mantis_SEO_Rest_Meta::get_field_definitions() ) as $key ) {
			$out[ $key ] = Mantis_SEO_Meta::get_post_meta( $post_id, $key );
		}
		return $out;
	}

	/**
	 * Sanitize one SEO field using REST meta definitions.
	 *
	 * @param string               $key    Field key.
	 * @param mixed                $value  Raw value.
	 * @param array<string, mixed> $config Field config.
	 * @return string
	 */
	private function sanitize_seo_field( $key, $value, $config ) {
		unset( $key );
		$callback = isset( $config['sanitize_callback'] ) ? $config['sanitize_callback'] : 'sanitize_text_field';
		if ( is_callable( $callback ) ) {
			$sanitized = call_user_func( $callback, $value );
			return is_string( $sanitized ) ? $sanitized : (string) $sanitized;
		}
		return sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
	}
}
