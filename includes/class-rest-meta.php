<?php
/**
 * Register per-post SEO meta for the WordPress REST API.
 *
 * Exposes `_mantis_*` fields under `meta` on public post type routes so agents
 * and integrations can read/write SEO settings via Application Passwords.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Rest_Meta
 */
class Mantis_SEO_Rest_Meta {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_meta' ), 20 );
	}

	/**
	 * Meta field definitions for REST (key without prefix => config).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_field_definitions() {
		return array(
			'focus_keyword'       => array(
				'description'       => __( 'Focus keyword', 'mantis-seo' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'title'               => array(
				'description'       => __( 'SEO title', 'mantis-seo' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description'         => array(
				'description'       => __( 'Meta description', 'mantis-seo' ),
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'canonical'           => array(
				'description'       => __( 'Canonical URL', 'mantis-seo' ),
				'sanitize_callback' => 'esc_url_raw',
			),
			'robots_noindex'      => array(
				'description'       => __( 'Noindex (1 or 0)', 'mantis-seo' ),
				'sanitize_callback' => array( __CLASS__, 'sanitize_robots_flag' ),
			),
			'robots_nofollow'     => array(
				'description'       => __( 'Nofollow (1 or 0)', 'mantis-seo' ),
				'sanitize_callback' => array( __CLASS__, 'sanitize_robots_flag' ),
			),
			'og_title'            => array(
				'description'       => __( 'Open Graph title', 'mantis-seo' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'og_description'      => array(
				'description'       => __( 'Open Graph description', 'mantis-seo' ),
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'og_image'            => array(
				'description'       => __( 'Open Graph image URL', 'mantis-seo' ),
				'sanitize_callback' => 'esc_url_raw',
			),
			'twitter_title'       => array(
				'description'       => __( 'X (Twitter) title', 'mantis-seo' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'twitter_description' => array(
				'description'       => __( 'X (Twitter) description', 'mantis-seo' ),
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'twitter_image'       => array(
				'description'       => __( 'X (Twitter) image URL', 'mantis-seo' ),
				'sanitize_callback' => 'esc_url_raw',
			),
			'schema_page_type'    => array(
				'description'       => __( 'Schema.org page @type (empty = default)', 'mantis-seo' ),
				'sanitize_callback' => array( 'Mantis_SEO_Schema_Types', 'sanitize_page_type' ),
			),
			'schema_article_type' => array(
				'description'       => __( 'Schema.org article @type (empty = default)', 'mantis-seo' ),
				'sanitize_callback' => array( 'Mantis_SEO_Schema_Types', 'sanitize_article_type' ),
			),
		);
	}

	/**
	 * Register meta for all public post types.
	 */
	public function register_meta() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		if ( empty( $post_types ) || ! is_array( $post_types ) ) {
			return;
		}

		foreach ( $post_types as $post_type ) {
			foreach ( self::get_field_definitions() as $key => $config ) {
				$this->register_field( $post_type, $key, $config );
			}
		}
	}

	/**
	 * Register a single meta key for a post type.
	 *
	 * @param string               $post_type Post type slug.
	 * @param string               $key       Key without prefix.
	 * @param array<string, mixed> $config    Field config.
	 */
	private function register_field( $post_type, $key, $config ) {
		$meta_key = Mantis_SEO_Meta::META_PREFIX . $key;

		register_post_meta(
			$post_type,
			$meta_key,
			array(
				'type'              => 'string',
				'description'       => isset( $config['description'] ) ? $config['description'] : '',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'auth_callback'     => array( __CLASS__, 'auth_callback' ),
				'sanitize_callback' => isset( $config['sanitize_callback'] ) ? $config['sanitize_callback'] : 'sanitize_text_field',
			)
		);
	}

	/**
	 * Whether the current user may update SEO meta for a post.
	 *
	 * @param bool   $allowed  Whether the user can add this meta (unused).
	 * @param string $meta_key Meta key.
	 * @param int    $object_id Post ID.
	 * @return bool
	 */
	public static function auth_callback( $allowed, $meta_key, $object_id ) {
		unset( $allowed, $meta_key );
		return current_user_can( 'edit_post', (int) $object_id );
	}

	/**
	 * Normalize robots flags to "1" or "0".
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_robots_flag( $value ) {
		if ( true === $value || 1 === $value || '1' === $value ) {
			return '1';
		}
		return '0';
	}
}
