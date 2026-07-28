<?php
/**
 * Allowed schema.org @type values for WebPage and Article JSON-LD.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Schema_Types
 */
class Mantis_SEO_Schema_Types {

	/**
	 * Web page–like @type values (schema.org).
	 *
	 * @var array<string, string> value => admin label
	 */
	private static $page_types = array(
		'WebPage'              => 'Web Page',
		'AboutPage'            => 'About Page',
		'ContactPage'          => 'Contact Page',
		'FAQPage'              => 'FAQ Page',
		'QAPage'               => 'Q&A Page',
		'ProfilePage'          => 'Profile Page',
		'CollectionPage'       => 'Collection Page',
		'CheckoutPage'         => 'Checkout Page',
		'ItemPage'             => 'Item Page',
		'MedicalWebPage'       => 'Medical Web Page',
		'SearchResultsPage'    => 'Search Results Page',
		'RealEstateListing'    => 'Real Estate Listing',
	);

	/**
	 * Article–like @type values (schema.org).
	 *
	 * @var array<string, string> value => admin label
	 */
	private static $article_types = array(
		'Article'                   => 'Article',
		'BlogPosting'               => 'Blog Posting',
		'NewsArticle'               => 'News Article',
		'SocialMediaPosting'        => 'Social Media Posting',
		'AdvertiserContentArticle'  => 'Advertiser Content Article',
		'SatiricalArticle'          => 'Satirical Article',
		'ScholarlyArticle'          => 'Scholarly Article',
		'TechArticle'               => 'Tech Article',
		'Report'                    => 'Report',
	);

	/**
	 * Page types for select elements.
	 *
	 * @return array<string, string>
	 */
	public static function get_page_type_choices() {
		return self::$page_types;
	}

	/**
	 * Article types for select elements.
	 *
	 * @return array<string, string>
	 */
	public static function get_article_type_choices() {
		return self::$article_types;
	}

	/**
	 * Allowed page type strings.
	 *
	 * @return string[]
	 */
	public static function get_allowed_page_types() {
		return array_keys( self::$page_types );
	}

	/**
	 * Allowed article type strings.
	 *
	 * @return string[]
	 */
	public static function get_allowed_article_types() {
		return array_keys( self::$article_types );
	}

	/**
	 * Sanitize a page @type against the allowlist.
	 *
	 * @param string $value Raw value.
	 * @return string Empty if invalid.
	 */
	public static function sanitize_page_type( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return '';
		}
		return isset( self::$page_types[ $value ] ) ? $value : '';
	}

	/**
	 * Sanitize an article @type against the allowlist.
	 *
	 * @param string $value Raw value.
	 * @return string Empty if invalid.
	 */
	public static function sanitize_article_type( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return '';
		}
		return isset( self::$article_types[ $value ] ) ? $value : '';
	}

	/**
	 * Built-in defaults for a post type (before saved settings).
	 *
	 * @param string $post_type Post type slug.
	 * @return array{page_type: string, article_type: string, article_enabled: bool}
	 */
	public static function get_builtin_row( $post_type ) {
		$post_type = sanitize_key( $post_type );
		if ( 'post' === $post_type ) {
			return array(
				'page_type'       => 'WebPage',
				'article_type'    => 'Article',
				'article_enabled' => true,
			);
		}
		return array(
			'page_type'       => 'WebPage',
			'article_type'    => 'Article',
			'article_enabled' => false,
		);
	}

	/**
	 * Merge saved schema_type_defaults with builtins for all public post types.
	 *
	 * @param array $saved Partial or full saved map post_type => row.
	 * @return array<string, array{page_type: string, article_type: string, article_enabled: bool}>
	 */
	public static function merge_settings_defaults( $saved ) {
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$out      = array();
		$public   = get_post_types( array( 'public' => true ), 'names' );
		$builtins = array();
		foreach ( $public as $pt ) {
			$builtins[ $pt ] = self::get_builtin_row( $pt );
		}
		foreach ( $public as $pt ) {
			$row = isset( $saved[ $pt ] ) && is_array( $saved[ $pt ] ) ? $saved[ $pt ] : array();
			$base = $builtins[ $pt ];
			$page = self::sanitize_page_type( isset( $row['page_type'] ) ? (string) $row['page_type'] : '' );
			$art  = self::sanitize_article_type( isset( $row['article_type'] ) ? (string) $row['article_type'] : '' );
			$out[ $pt ] = array(
				'page_type'       => $page !== '' ? $page : $base['page_type'],
				'article_type'    => $art !== '' ? $art : $base['article_type'],
				'article_enabled' => ( 'post' === $pt ) ? true : ! empty( $row['article_enabled'] ),
			);
		}
		return $out;
	}

	/**
	 * Sanitize schema_type_defaults from settings form input.
	 *
	 * @param mixed $input Raw POST fragment.
	 * @return array<string, array{page_type: string, article_type: string, article_enabled: bool}>
	 */
	public static function sanitize_settings_input( $input ) {
		if ( ! is_array( $input ) ) {
			return self::merge_settings_defaults( array() );
		}
		$public = get_post_types( array( 'public' => true ), 'names' );
		$saved  = array();
		foreach ( $public as $pt ) {
			if ( ! isset( $input[ $pt ] ) || ! is_array( $input[ $pt ] ) ) {
				continue;
			}
			$row = $input[ $pt ];
			$page = self::sanitize_page_type( isset( $row['page_type'] ) ? (string) $row['page_type'] : '' );
			$art  = self::sanitize_article_type( isset( $row['article_type'] ) ? (string) $row['article_type'] : '' );
			$saved[ $pt ] = array(
				'page_type'       => $page,
				'article_type'    => $art,
				'article_enabled' => ! empty( $row['article_enabled'] ),
			);
		}
		return self::merge_settings_defaults( $saved );
	}

	/**
	 * Effective page type, article type, and whether to output an Article node.
	 *
	 * @param WP_Post $post     Post.
	 * @param array   $settings Mantis settings (merged).
	 * @return array{page_type: string, article_type: string, emit_article: bool}
	 */
	public static function resolve_for_post( WP_Post $post, array $settings ) {
		$map = self::merge_settings_defaults( isset( $settings['schema_type_defaults'] ) && is_array( $settings['schema_type_defaults'] ) ? $settings['schema_type_defaults'] : array() );
		$pt  = $post->post_type;
		$row = isset( $map[ $pt ] ) ? $map[ $pt ] : self::get_builtin_row( $pt );

		$page_meta = Mantis_SEO_Meta::get_post_meta( $post->ID, 'schema_page_type' );
		$art_meta  = Mantis_SEO_Meta::get_post_meta( $post->ID, 'schema_article_type' );

		$page_type = self::sanitize_page_type( (string) $page_meta );
		if ( '' === $page_type ) {
			$page_type = $row['page_type'];
		}

		$article_type = self::sanitize_article_type( (string) $art_meta );
		if ( '' === $article_type ) {
			$article_type = $row['article_type'];
		}

		$emit_article = ! empty( $row['article_enabled'] );

		return array(
			'page_type'    => $page_type,
			'article_type' => $article_type,
			'emit_article' => $emit_article,
		);
	}

	/**
	 * Map Yoast-stored schema type string to our allowlist (Yoast uses schema.org names).
	 *
	 * @param string $value Yoast meta value.
	 * @param string $kind  'page' or 'article'.
	 * @return string Sanitized or empty.
	 */
	public static function map_yoast_type( $value, $kind ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return '';
		}
		// Yoast sometimes prefixes or uses variants; normalize common cases.
		$map_article = array(
			'Article'                 => 'Article',
			'BlogPosting'             => 'BlogPosting',
			'BlogPostingArticle'      => 'BlogPosting',
			'NewsArticle'             => 'NewsArticle',
			'SocialMediaPosting'      => 'SocialMediaPosting',
			'AdvertiserContentArticle' => 'AdvertiserContentArticle',
			'SatiricalArticle'        => 'SatiricalArticle',
			'ScholarlyArticle'        => 'ScholarlyArticle',
			'TechArticle'             => 'TechArticle',
			'Report'                  => 'Report',
		);
		$map_page    = array(
			'WebPage'           => 'WebPage',
			'AboutPage'         => 'AboutPage',
			'ContactPage'       => 'ContactPage',
			'FAQPage'           => 'FAQPage',
			'QAPage'            => 'QAPage',
			'ProfilePage'       => 'ProfilePage',
			'CollectionPage'    => 'CollectionPage',
			'CheckoutPage'      => 'CheckoutPage',
			'ItemPage'          => 'ItemPage',
			'MedicalWebPage'    => 'MedicalWebPage',
			'SearchResultsPage' => 'SearchResultsPage',
			'RealEstateListing' => 'RealEstateListing',
		);
		if ( 'article' === $kind ) {
			if ( isset( $map_article[ $value ] ) ) {
				return self::sanitize_article_type( $map_article[ $value ] );
			}
			return self::sanitize_article_type( $value );
		}
		if ( isset( $map_page[ $value ] ) ) {
			return self::sanitize_page_type( $map_page[ $value ] );
		}
		return self::sanitize_page_type( $value );
	}
}
