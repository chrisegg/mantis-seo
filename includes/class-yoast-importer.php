<?php
/**
 * Yoast SEO migration importer.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Yoast_Importer
 */
class Mantis_SEO_Yoast_Importer {

	/**
	 * Meta key mapping (Yoast => Mantis).
	 *
	 * @var array
	 */
	private static $meta_map = array(
		'_yoast_wpseo_title'                => '_mantis_title',
		'_yoast_wpseo_metadesc'             => '_mantis_description',
		'_yoast_wpseo_canonical'            => '_mantis_canonical',
		'_yoast_wpseo_meta-robots-noindex'  => '_mantis_robots_noindex',
		'_yoast_wpseo_meta-robots-nofollow' => '_mantis_robots_nofollow',
		'_yoast_wpseo_opengraph-title'      => '_mantis_og_title',
		'_yoast_wpseo_opengraph-description' => '_mantis_og_description',
		'_yoast_wpseo_opengraph-image'      => '_mantis_og_image',
		'_yoast_wpseo_twitter-title'        => '_mantis_twitter_title',
		'_yoast_wpseo_twitter-description'  => '_mantis_twitter_description',
		'_yoast_wpseo_twitter-image'        => '_mantis_twitter_image',
	);

	/**
	 * Meta keys to skip.
	 *
	 * @var array
	 */
	private static $skip_keys = array(
		'_yoast_wpseo_focuskw',
		'_yoast_wpseo_linkdex',
		'_yoast_wpseo_content_score',
		'_yoast_wpseo_inclusive_language_score',
		'_yoast_wpseo_is_cornerstone',
		'_yoast_wpseo_meta-robots-adv',
		'_yoast_wpseo_bctitle',
	);

	/**
	 * Check if Yoast data exists to migrate.
	 *
	 * @return bool
	 */
	public static function has_yoast_data() {
		global $wpdb;

		if ( class_exists( 'WPSEO_Options' ) ) {
			return true;
		}

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = %s",
				'_yoast_wpseo_title'
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Run migration.
	 *
	 * @return array{posts: int, terms: int, settings: bool, redirects: int, errors: array}
	 */
	public static function migrate() {
		$result = array(
			'posts'    => 0,
			'terms'    => 0,
			'settings' => false,
			'redirects' => 0,
			'errors'   => array(),
		);

		$result['posts'] = self::migrate_posts();
		$result['terms'] = self::migrate_terms();
		$result['settings'] = self::migrate_settings();
		$result['redirects'] = self::migrate_redirects();

		if ( $result['posts'] > 0 || $result['terms'] > 0 || $result['settings'] || $result['redirects'] > 0 ) {
			update_option( 'mantis_migrated_from_yoast', time() );
		}

		return $result;
	}

	/**
	 * Migrate post meta.
	 *
	 * @return int
	 */
	private static function migrate_posts() {
		global $wpdb;
		$count = 0;

		$rows = $wpdb->get_results(
			"SELECT post_id, meta_key, meta_value FROM $wpdb->postmeta WHERE meta_key LIKE '_yoast_wpseo_%'",
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$post_meta = array();
		foreach ( $rows as $row ) {
			if ( in_array( $row['meta_key'], self::$skip_keys, true ) ) {
				continue;
			}
			if ( ! isset( self::$meta_map[ $row['meta_key'] ] ) ) {
				continue;
			}
			$new_key = self::$meta_map[ $row['meta_key'] ];
			$value   = $row['meta_value'];

			// Convert Yoast robots: 1 = noindex/nofollow.
			if ( '_mantis_robots_noindex' === $new_key || '_mantis_robots_nofollow' === $new_key ) {
				$value = ( '1' === $value || 1 === (int) $value ) ? '1' : '0';
			}

			// Convert Yoast template variables in titles and descriptions.
			if ( in_array( $new_key, array( '_mantis_title', '_mantis_description', '_mantis_og_title', '_mantis_og_description', '_mantis_twitter_title', '_mantis_twitter_description' ), true ) ) {
				$value = self::convert_yoast_vars( $value );
			}

			if ( ! isset( $post_meta[ $row['post_id'] ] ) ) {
				$post_meta[ $row['post_id'] ] = array();
			}
			$post_meta[ $row['post_id'] ][ $new_key ] = $value;
		}

		foreach ( $post_meta as $post_id => $meta ) {
			foreach ( $meta as $key => $value ) {
				update_post_meta( (int) $post_id, $key, $value );
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Migrate term meta.
	 *
	 * @return int
	 */
	private static function migrate_terms() {
		global $wpdb;
		$count = 0;

		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$wpdb->termmeta'" ) ) {
			return 0;
		}

		$rows = $wpdb->get_results(
			"SELECT term_id, meta_key, meta_value FROM $wpdb->termmeta WHERE meta_key LIKE '_yoast_wpseo_%'",
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$term_meta = array();
		foreach ( $rows as $row ) {
			if ( in_array( $row['meta_key'], self::$skip_keys, true ) ) {
				continue;
			}
			if ( ! isset( self::$meta_map[ $row['meta_key'] ] ) ) {
				continue;
			}
			$new_key = self::$meta_map[ $row['meta_key'] ];
			$value   = $row['meta_value'];
			if ( '_mantis_robots_noindex' === $new_key || '_mantis_robots_nofollow' === $new_key ) {
				$value = ( '1' === $value || 1 === (int) $value ) ? '1' : '0';
			}
			if ( in_array( $new_key, array( '_mantis_title', '_mantis_description', '_mantis_og_title', '_mantis_og_description', '_mantis_twitter_title', '_mantis_twitter_description' ), true ) ) {
				$value = self::convert_yoast_vars( $value );
			}
			if ( ! isset( $term_meta[ $row['term_id'] ] ) ) {
				$term_meta[ $row['term_id'] ] = array();
			}
			$term_meta[ $row['term_id'] ][ $new_key ] = $value;
		}

		foreach ( $term_meta as $term_id => $meta ) {
			foreach ( $meta as $key => $value ) {
				update_term_meta( (int) $term_id, $key, $value );
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Normalize a redirect target URL from Yoast format.
	 *
	 * Yoast may store targets as relative paths (e.g. "category/page-builders") or
	 * malformed URLs like "http://category/page-builders" where the host is
	 * actually a path segment. This converts them to proper paths.
	 *
	 * @param string $target Raw target from Yoast.
	 * @return string Normalized target (path with leading slash, or valid absolute URL).
	 */
	private static function normalize_redirect_target( $target ) {
		$target = trim( $target );
		if ( empty( $target ) ) {
			return $target;
		}

		$parsed = wp_parse_url( $target );

		// Malformed: http(s)://path-segment/more-path (e.g. http://category/page-builders).
		// Real domains contain a dot; single-word hosts like "category" are path segments.
		if ( ! empty( $parsed['scheme'] ) && in_array( $parsed['scheme'], array( 'http', 'https' ), true ) ) {
			$host = isset( $parsed['host'] ) ? $parsed['host'] : '';
			$path = isset( $parsed['path'] ) ? $parsed['path'] : '';
			if ( $host && false === strpos( $host, '.' ) && ( '' !== $path || isset( $parsed['path'] ) ) ) {
				return '/' . $host . $path;
			}
		}

		// Protocol-relative with path-like host: //category/page-builders.
		if ( 0 === strpos( $target, '//' ) ) {
			$rest  = substr( $target, 2 );
			$slash = strpos( $rest, '/' );
			if ( false !== $slash ) {
				$host = substr( $rest, 0, $slash );
				$path = substr( $rest, $slash );
				if ( false === strpos( $host, '.' ) ) {
					return '/' . $host . $path;
				}
			}
		}

		// Relative path without leading slash: category/page-builders.
		if ( '/' !== substr( $target, 0, 1 ) && 0 !== strpos( $target, 'http' ) && 0 !== strpos( $target, '//' ) ) {
			return '/' . $target;
		}

		return $target;
	}

	/**
	 * Migrate redirects from Yoast Premium.
	 */
	private static function migrate_redirects() {
		global $wpdb;
		$count = 0;

		// Legacy per-post redirect (very old Yoast free).
		$legacy_redirects = $wpdb->get_results(
			"SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_yoast_wpseo_redirect' AND meta_value != ''",
			ARRAY_A
		);
		foreach ( $legacy_redirects as $r ) {
			$post = get_post( $r['post_id'] );
			if ( $post ) {
				$origin = '/' . $post->post_name . '/';
				$target = self::normalize_redirect_target( $r['meta_value'] );
				if ( Mantis_SEO_Redirects::add( $origin, $target, 301, 'plain' ) ) {
					$count++;
				}
			}
		}

		$count += self::migrate_redirects_from_options();

		return $count;
	}

	/**
	 * Migrate redirects from Yoast option format.
	 *
	 * @return int
	 */
	private static function migrate_redirects_from_options() {
		$count = 0;

		$sources = array(
			'wpseo-premium-redirects-base',
			'wpseo_redirect',
		);

		foreach ( $sources as $option_name ) {
			$redirects = get_option( $option_name, array() );
			if ( ! is_array( $redirects ) ) {
				continue;
			}

			foreach ( $redirects as $entry ) {
				if ( isset( $entry['origin'], $entry['url'] ) ) {
					$origin = $entry['origin'];
					$target = self::normalize_redirect_target( $entry['url'] );
				} elseif ( isset( $entry['origin'], $entry['target'] ) ) {
					$origin = $entry['origin'];
					$target = self::normalize_redirect_target( $entry['target'] );
				} elseif ( is_string( $entry ) ) {
					continue;
				} else {
					continue;
				}
				$type   = isset( $entry['type'] ) ? (int) $entry['type'] : 301;
				$format = isset( $entry['format'] ) && 'regex' === $entry['format'] ? 'regex' : 'plain';
				if ( Mantis_SEO_Redirects::add( $origin, $target, $type, $format ) ) {
					$count++;
				}
			}
		}

		// Legacy format: key => value in option.
		$legacy_plain = get_option( 'wpseo-premium-redirects', array() );
		$legacy_regex = get_option( 'wpseo-premium-redirects-regex', array() );
		if ( is_array( $legacy_plain ) ) {
			foreach ( $legacy_plain as $origin => $data ) {
				if ( is_array( $data ) && isset( $data['url'] ) ) {
					$target = self::normalize_redirect_target( $data['url'] );
					$type   = isset( $data['type'] ) ? (int) $data['type'] : 301;
					if ( Mantis_SEO_Redirects::add( $origin, $target, $type, 'plain' ) ) {
						$count++;
					}
				}
			}
		}
		if ( is_array( $legacy_regex ) ) {
			foreach ( $legacy_regex as $origin => $data ) {
				if ( is_array( $data ) && isset( $data['url'] ) ) {
					$target = self::normalize_redirect_target( $data['url'] );
					$type   = isset( $data['type'] ) ? (int) $data['type'] : 301;
					if ( Mantis_SEO_Redirects::add( $origin, $target, $type, 'regex' ) ) {
						$count++;
					}
				}
			}
		}

		return $count;
	}

	/**
	 * Migrate global settings.
	 *
	 * @return bool
	 */
	private static function migrate_settings() {
		$settings = Mantis_SEO_Main::get_settings();

		// Read options directly - they exist in DB even if Yoast is deactivated.
		$wpseo_titles = get_option( 'wpseo_titles', array() );
		$wpseo_social = get_option( 'wpseo_social', array() );
		$wpseo        = get_option( 'wpseo', array() );

		if ( is_array( $wpseo_titles ) ) {
			$get = function( $keys, $fallback = '' ) use ( $wpseo_titles ) {
				foreach ( (array) $keys as $k ) {
					if ( isset( $wpseo_titles[ $k ] ) && '' !== (string) $wpseo_titles[ $k ] ) {
						return $wpseo_titles[ $k ];
					}
				}
				return $fallback;
			};
			// Yoast uses title-{post_type} (e.g. title-post, title-page). Default in code is %%title%% %%page%% %%sep%% %%sitename%%.
			$yoast_default_single = '%%title%% %%page%% %%sep%% %%sitename%%';
			$v = $get( array( 'title-home-wpseo', 'title_home' ) );
			if ( $v ) {
				$settings['title_home'] = self::convert_yoast_vars( $v );
			}
			$v = $get( array( 'title-post-wpseo', 'title-post', 'title_post', 'title-pt-post' ), $yoast_default_single );
			$settings['title_post'] = self::convert_yoast_vars( $v );
			$v = $get( array( 'title-page-wpseo', 'title-page', 'title_page', 'title-pt-page' ), $yoast_default_single );
			$settings['title_page'] = self::convert_yoast_vars( $v );
			$v = $get( array( 'metadesc-home-wpseo', 'metadesc_home' ) );
			if ( $v ) {
				$settings['metadesc_home'] = self::convert_yoast_vars( $v );
			}
			$v = $get( array( 'metadesc-post-wpseo', 'metadesc-post', 'metadesc_post' ) );
			if ( $v ) {
				$settings['metadesc_post'] = self::convert_yoast_vars( $v );
			}
			$v = $get( array( 'metadesc-page-wpseo', 'metadesc-page', 'metadesc_page' ) );
			if ( $v ) {
				$settings['metadesc_page'] = self::convert_yoast_vars( $v );
			}
			if ( ! empty( $wpseo_titles['separator'] ) ) {
				$settings['separator'] = $wpseo_titles['separator'];
			}
			$settings['noindex_author'] = ! empty( $wpseo_titles['noindex-author-wpseo'] ?? $wpseo_titles['noindex_author'] ?? false );
			$settings['noindex_date']  = ! empty( $wpseo_titles['noindex-archive-wpseo'] ?? $wpseo_titles['noindex_date'] ?? false );
			if ( ! empty( $wpseo_titles['company_name'] ) ) {
				$settings['schema_organization_name'] = $wpseo_titles['company_name'];
			}
			if ( ! empty( $wpseo_titles['company_logo'] ?? '' ) ) {
				$settings['schema_organization_logo'] = $wpseo_titles['company_logo'];
			}
		}

		if ( is_array( $wpseo_social ) ) {
			if ( ! empty( $wpseo_social['og_default_image'] ) ) {
				$settings['og_default_image'] = $wpseo_social['og_default_image'];
			}
			if ( ! empty( $wpseo_social['twitter_card_type'] ?? '' ) ) {
				$settings['twitter_card_type'] = $wpseo_social['twitter_card_type'];
			}
		}

		if ( is_array( $wpseo ) ) {
			$settings['sitemap_enabled'] = ! empty( $wpseo['enable_xml_sitemap'] );
		}

		return Mantis_SEO_Main::save_settings( $settings );
	}

	/**
	 * Convert Yoast variable syntax to Mantis.
	 *
	 * @param string $str String with Yoast vars.
	 * @return string
	 */
	private static function convert_yoast_vars( $str ) {
		$map = array(
			'%%sitename%%'     => '%site_name%',
			'%%sitedesc%%'     => '%site_description%',
			'%%sep%%'          => '%separator%',
			'%%title%%'        => '%post_title%',
			'%%postname%%'     => '%post_title%',
			'%%page%%'         => '%page%',
			'%%date%%'         => '%date%',
			'%%name%%'         => '%name%',
			'%%searchphrase%%' => '%search_phrase%',
			'%%excerpt%%'      => '%excerpt%',
		);
		return str_replace( array_keys( $map ), array_values( $map ), $str );
	}
}
