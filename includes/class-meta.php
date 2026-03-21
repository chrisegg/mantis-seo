<?php
/**
 * Meta output (title, description, OG, Twitter, etc.).
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Meta
 */
class Mantis_SEO_Meta {

	/**
	 * Meta prefix for post/term meta.
	 *
	 * @var string
	 */
	const META_PREFIX = '_mantis_';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_meta' ), 1 );
		add_filter( 'pre_get_document_title', array( $this, 'filter_title' ), 9999 );
		add_filter( 'document_title_parts', array( $this, 'filter_title_parts' ), 9999 );
	}

	/**
	 * Output meta tags in head.
	 */
	public function output_meta() {
		$meta = $this->get_meta_output();

		if ( ! empty( $meta['description'] ) ) {
			echo '<meta name="description" content="' . esc_attr( $meta['description'] ) . '">' . "\n";
		}

		if ( ! empty( $meta['robots'] ) ) {
			echo '<meta name="robots" content="' . esc_attr( implode( ', ', $meta['robots'] ) ) . '">' . "\n";
		}

		if ( ! empty( $meta['canonical'] ) ) {
			echo '<link rel="canonical" href="' . esc_url( $meta['canonical'] ) . '">' . "\n";
		}

		$this->output_og( $meta );
		$this->output_twitter( $meta );
		$this->output_verification( $meta );
	}

	/**
	 * Output Open Graph tags.
	 *
	 * @param array $meta Meta data.
	 */
	private function output_og( $meta ) {
		if ( empty( $meta['og'] ) ) {
			return;
		}

		echo "\n<!-- Mantis SEO Open Graph -->\n";
		foreach ( $meta['og'] as $key => $value ) {
			if ( ! empty( $value ) ) {
				// Keys with ":" are full OG namespaces (e.g. article:modified_time).
				$property = ( false !== strpos( $key, ':' ) ) ? $key : 'og:' . $key;
				echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $value ) . '">' . "\n";
			}
		}
	}

	/**
	 * Output Twitter Card tags.
	 *
	 * @param array $meta Meta data.
	 */
	private function output_twitter( $meta ) {
		if ( empty( $meta['twitter'] ) ) {
			return;
		}

		echo "\n<!-- Mantis SEO X (Twitter Cards) -->\n";
		foreach ( $meta['twitter'] as $key => $value ) {
			if ( ! empty( $value ) ) {
				echo '<meta name="twitter:' . esc_attr( $key ) . '" content="' . esc_attr( $value ) . '">' . "\n";
			}
		}
	}

	/**
	 * Output webmaster verification tags.
	 *
	 * @param array $meta Meta data.
	 */
	private function output_verification( $meta ) {
		$settings = Mantis_SEO_Main::get_settings();

		if ( ! empty( $settings['google_verify'] ) ) {
			echo '<meta name="google-site-verification" content="' . esc_attr( $settings['google_verify'] ) . '">' . "\n";
		}
		if ( ! empty( $settings['bing_verify'] ) ) {
			echo '<meta name="msvalidate.01" content="' . esc_attr( $settings['bing_verify'] ) . '">' . "\n";
		}
	}

	/**
	 * Get meta output for current context.
	 *
	 * @return array
	 */
	private function get_meta_output() {
		$meta = array(
			'description' => '',
			'robots'      => array(),
			'canonical'   => '',
			'og'          => array(),
			'twitter'     => array(),
		);

		$settings = Mantis_SEO_Main::get_settings();

		if ( is_singular() ) {
			$object_id = get_queried_object_id();
			$meta     = $this->get_singular_meta( $object_id, $meta );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$object_id = get_queried_object_id();
			$meta     = $this->get_taxonomy_meta( $object_id, $meta );
		} elseif ( is_home() && ! is_front_page() ) {
			$meta['description'] = $this->replace_vars( $settings['metadesc_home'] );
			$meta['canonical']   = get_permalink( get_option( 'page_for_posts' ) );
		} elseif ( is_author() && $settings['noindex_author'] ) {
			$meta['robots'][] = 'noindex';
		} elseif ( is_date() && $settings['noindex_date'] ) {
			$meta['robots'][] = 'noindex';
		} elseif ( is_search() || is_404() ) {
			$meta['robots'][] = 'noindex';
		}

		if ( empty( $meta['robots'] ) ) {
			$meta['robots'] = array( 'index', 'follow' );
		}

		if ( empty( $meta['canonical'] ) && ! is_404() ) {
			$meta['canonical'] = $this->get_canonical_url();
		}

		$meta['og'] = $this->build_og( $meta );
		$meta['twitter'] = $this->build_twitter( $meta, $settings );

		return $meta;
	}

	/**
	 * Get singular post/page meta.
	 *
	 * @param int   $object_id Post ID.
	 * @param array $meta     Meta array to fill.
	 * @return array
	 */
	private function get_singular_meta( $object_id, $meta ) {
		$post   = get_post( $object_id );
		$settings = Mantis_SEO_Main::get_settings();

		if ( ! $post ) {
			return $meta;
		}

		$meta['document_title'] = $this->replace_vars( self::get_raw_title_template_for_post( $post ), $post );

		$description = $this->get_post_meta( $object_id, 'description' );
		if ( empty( $description ) ) {
			$template   = 'post' === $post->post_type ? $settings['metadesc_post'] : $settings['metadesc_page'];
			$meta['description'] = $this->replace_vars( $template, $post );
		} else {
			$meta['description'] = $description;
		}

		$noindex  = $this->get_post_meta( $object_id, 'robots_noindex' );
		$nofollow  = $this->get_post_meta( $object_id, 'robots_nofollow' );
		if ( $noindex ) {
			$meta['robots'][] = 'noindex';
		} else {
			$meta['robots'][] = 'index';
		}
		if ( $nofollow ) {
			$meta['robots'][] = 'nofollow';
		} else {
			$meta['robots'][] = 'follow';
		}

		$canonical = $this->get_post_meta( $object_id, 'canonical' );
		$meta['canonical'] = ! empty( $canonical ) ? $canonical : get_permalink( $object_id );

		$meta['og_title']       = $this->get_post_meta( $object_id, 'og_title' );
		$meta['og_description'] = $this->get_post_meta( $object_id, 'og_description' );
		$meta['og_image']       = $this->get_post_meta( $object_id, 'og_image' );
		$meta['twitter_title']  = $this->get_post_meta( $object_id, 'twitter_title' );
		$meta['twitter_description'] = $this->get_post_meta( $object_id, 'twitter_description' );
		$meta['twitter_image']  = $this->get_post_meta( $object_id, 'twitter_image' );

		return $meta;
	}

	/**
	 * Get taxonomy term meta for output.
	 *
	 * @param int   $term_id Term ID.
	 * @param array $meta    Meta array to fill.
	 * @return array
	 */
	private function get_taxonomy_meta( $term_id, $meta ) {
		$settings = Mantis_SEO_Main::get_settings();
		$title = self::get_term_meta( $term_id, 'title' );
		$description = self::get_term_meta( $term_id, 'description' );

		if ( ! empty( $title ) ) {
			// Title for taxonomies is output via filter - WP doesn't have a standard way, so we'd need to hook wp_title or similar.
		}
		if ( ! empty( $description ) ) {
			$meta['description'] = $description;
		}

		$meta['robots'] = array( 'index', 'follow' );

		// Global noindex for category/tag archives.
		if ( is_category() && ! empty( $settings['noindex_category'] ) ) {
			$meta['robots'] = array( 'noindex', 'follow' );
		} elseif ( is_tag() && ! empty( $settings['noindex_tag'] ) ) {
			$meta['robots'] = array( 'noindex', 'follow' );
		}

		// Per-term noindex/nofollow (overrides global when set).
		$term_noindex  = self::get_term_meta( $term_id, 'robots_noindex' );
		$term_nofollow = self::get_term_meta( $term_id, 'robots_nofollow' );
		if ( $term_noindex || $term_nofollow ) {
			$meta['robots'] = array(
				$term_noindex ? 'noindex' : 'index',
				$term_nofollow ? 'nofollow' : 'follow',
			);
		}

		$meta['canonical'] = get_term_link( $term_id );

		return $meta;
	}

	/**
	 * Featured image URL for the main queried singular post (memoized per request).
	 *
	 * @return string URL or empty.
	 */
	private function get_queried_post_thumbnail_url() {
		if ( ! is_singular() ) {
			return '';
		}
		static $memo = null;
		if ( null !== $memo ) {
			return $memo;
		}
		$id = get_queried_object_id();
		$url = $id ? get_the_post_thumbnail_url( $id, 'full' ) : '';
		$memo = $url ? (string) $url : '';
		return $memo;
	}

	/**
	 * Build OG array.
	 *
	 * @param array $meta Meta data.
	 * @return array
	 */
	private function build_og( $meta ) {
		$settings = Mantis_SEO_Main::get_settings();

		$og_title = ! empty( $meta['og_title'] ) ? $meta['og_title'] : ( ! empty( $meta['document_title'] ) ? $meta['document_title'] : '' );
		$og = array(
			'locale'      => get_locale(),
			// Match schema: Article only for post type "post"; pages/CPTs use WebPage → og:website.
			'type'        => is_singular( 'post' ) ? 'article' : 'website',
			'site_name'   => get_bloginfo( 'name' ),
			'title'       => $og_title,
			'description' => ! empty( $meta['og_description'] ) ? $meta['og_description'] : ( ! empty( $meta['description'] ) ? $meta['description'] : get_bloginfo( 'description' ) ),
			'url'         => ! empty( $meta['canonical'] ) ? $meta['canonical'] : $this->get_canonical_url(),
			'image'       => ! empty( $meta['og_image'] ) ? $meta['og_image'] : $settings['og_default_image'],
		);

		if ( is_singular() ) {
			$post = get_post( get_queried_object_id() );
			if ( $post ) {
				$published = get_the_date( 'c', $post );
				$modified  = get_the_modified_date( 'c', $post );
				if ( $published ) {
					$og['article:published_time'] = $published;
				}
				if ( $modified ) {
					$og['article:modified_time'] = $modified;
				}
			}
			if ( empty( $og['image'] ) ) {
				$thumb = $this->get_queried_post_thumbnail_url();
				if ( $thumb ) {
					$og['image'] = $thumb;
				}
			}
		}

		return $og;
	}

	/**
	 * Build Twitter array.
	 *
	 * @param array $meta     Meta data.
	 * @param array $settings Settings.
	 * @return array
	 */
	private function build_twitter( $meta, $settings ) {
		$twitter_title = ! empty( $meta['twitter_title'] ) ? $meta['twitter_title'] : ( ! empty( $meta['document_title'] ) ? $meta['document_title'] : '' );
		$twitter = array(
			'card'        => $settings['twitter_card_type'],
			'title'       => $twitter_title,
			'description' => ! empty( $meta['twitter_description'] ) ? $meta['twitter_description'] : ( ! empty( $meta['description'] ) ? $meta['description'] : get_bloginfo( 'description' ) ),
			'image'       => ! empty( $meta['twitter_image'] ) ? $meta['twitter_image'] : ( ! empty( $meta['og_image'] ) ? $meta['og_image'] : $settings['og_default_image'] ),
		);

		if ( is_singular() && empty( $twitter['image'] ) ) {
			$thumb = $this->get_queried_post_thumbnail_url();
			if ( $thumb ) {
				$twitter['image'] = $thumb;
			}
		}

		return $twitter;
	}

	/**
	 * Filter document title.
	 *
	 * @param string $title Document title.
	 * @return string
	 */
	public function filter_title( $title ) {
		if ( is_singular() ) {
			$post = get_post( get_queried_object_id() );
			if ( ! $post ) {
				return $title;
			}
			return $this->replace_vars( self::get_raw_title_template_for_post( $post ), $post );
		}
		if ( is_home() && ! is_front_page() ) {
			$settings = Mantis_SEO_Main::get_settings();
			$template = ! empty( trim( (string) ( $settings['title_home'] ?? '' ) ) ) ? $settings['title_home'] : '%site_name% - %site_description%';
			return $this->replace_vars( $template );
		}
		return $title;
	}

	/**
	 * Filter document title parts.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public function filter_title_parts( $parts ) {
		if ( is_singular() ) {
			$post = get_post( get_queried_object_id() );
			if ( ! $post ) {
				return $parts;
			}
			$parts['title'] = $this->replace_vars( self::get_raw_title_template_for_post( $post ), $post );
			unset( $parts['tagline'], $parts['site'] );
		}
		return $parts;
	}

	/**
	 * Get post meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key (without prefix).
	 * @return string
	 */
	public static function get_post_meta( $post_id, $key ) {
		$value = get_post_meta( $post_id, self::META_PREFIX . $key, true );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Resolved SEO document title for a post (matches front-end title output).
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	public static function resolve_document_title_for_post( WP_Post $post ) {
		return self::replace_vars_for_preview( self::get_raw_title_template_for_post( $post ), $post );
	}

	/**
	 * Raw SEO title template for a post (custom meta or global template).
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function get_raw_title_template_for_post( WP_Post $post ) {
		$custom = self::get_post_meta( $post->ID, 'title' );
		if ( ! empty( trim( (string) $custom ) ) ) {
			return $custom;
		}
		$settings = Mantis_SEO_Main::get_settings();
		$template = 'post' === $post->post_type ? $settings['title_post'] : $settings['title_page'];
		return ! empty( trim( (string) $template ) ) ? $template : '%post_title% %page% %separator% %site_name%';
	}

	/**
	 * Resolved meta description for a post (matches meta description output).
	 *
	 * @param WP_Post $post Post object.
	 * @return string Plain text.
	 */
	public static function resolve_post_meta_description( WP_Post $post ) {
		$custom = self::get_post_meta( $post->ID, 'description' );
		if ( ! empty( trim( (string) $custom ) ) ) {
			return wp_strip_all_tags( $custom );
		}
		$settings = Mantis_SEO_Main::get_settings();
		$template = 'post' === $post->post_type ? $settings['metadesc_post'] : $settings['metadesc_page'];
		return wp_strip_all_tags( self::replace_vars_for_preview( $template, $post ) );
	}

	/**
	 * Get term meta value.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $key     Meta key (without prefix).
	 * @return string
	 */
	public static function get_term_meta( $term_id, $key ) {
		$value = get_term_meta( $term_id, self::META_PREFIX . $key, true );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Replace variables in string.
	 *
	 * @param string   $str  String with placeholders.
	 * @param WP_Post  $post Optional post object.
	 * @return string
	 */
	private function replace_vars( $str, $post = null ) {
		if ( empty( $str ) ) {
			return '';
		}
		$r = self::build_replacements_map( $post, true );
		return str_replace( array_keys( $r ), array_values( $r ), $str );
	}

	/**
	 * Build %var% replacement map.
	 *
	 * @param WP_Post|null $post                 Optional post.
	 * @param bool         $include_pagination  Whether to resolve %page% from the main query (front-end only).
	 * @return array
	 */
	private static function build_replacements_map( $post = null, $include_pagination = false ) {
		$replacements = array(
			'%site_name%'        => get_bloginfo( 'name' ),
			'%site_description%' => get_bloginfo( 'description' ),
			'%sitename%'         => get_bloginfo( 'name' ),
			'%sitedesc%'         => get_bloginfo( 'description' ),
			'%separator%'        => '|',
			'%date%'             => date_i18n( get_option( 'date_format' ) ),
		);

		if ( $include_pagination ) {
			$paged = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : ( get_query_var( 'page' ) ? (int) get_query_var( 'page' ) : 0 );
			$replacements['%page%'] = '';
			if ( $paged > 0 ) {
				global $wp_query;
				$max = isset( $wp_query->max_num_pages ) ? (int) $wp_query->max_num_pages : 1;
				$replacements['%page%'] = sprintf( /* translators: 1: current page, 2: total pages */ __( 'Page %1$d of %2$d', 'mantis-seo' ), $paged, $max );
			}
		} else {
			$replacements['%page%'] = '';
		}

		if ( $post instanceof WP_Post ) {
			$post_title = $post->post_title;
			if ( '' === trim( (string) $post_title ) ) {
				$post_title = __( 'No title', 'mantis-seo' );
			}
			$replacements['%post_title%'] = $post_title;
			$replacements['%title%']      = $post_title;
			$replacements['%excerpt%']    = has_excerpt( $post->ID ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
		}

		$settings = Mantis_SEO_Main::get_settings();
		if ( isset( $settings['separator'] ) ) {
			$replacements['%separator%'] = $settings['separator'];
		}

		return $replacements;
	}

	/**
	 * Replace variables for preview output (public, for metabox/settings).
	 *
	 * @param string   $str  String with placeholders.
	 * @param WP_Post  $post Optional post object.
	 * @return string
	 */
	public static function replace_vars_for_preview( $str, $post = null ) {
		if ( empty( $str ) ) {
			return '';
		}
		$replacements = self::get_replacements_for_preview( $post );
		return str_replace( array_keys( $replacements ), array_values( $replacements ), $str );
	}

	/**
	 * Get replacement map for use in JavaScript (e.g. live preview).
	 *
	 * @param WP_Post|null $post Optional post object.
	 * @return array Map of %var% => replacement value.
	 */
	public static function get_replacements_for_preview( $post = null ) {
		return self::build_replacements_map( $post, false );
	}

	/**
	 * Human-readable labels for SEO template variables (for UI pills).
	 *
	 * @return array Map of %var% => label.
	 */
	public static function get_variable_labels() {
		return array(
			'%post_title%'       => __( 'Post Title', 'mantis-seo' ),
			'%title%'            => __( 'Post Title', 'mantis-seo' ),
			'%site_name%'        => __( 'Site Title', 'mantis-seo' ),
			'%sitename%'         => __( 'Site Title', 'mantis-seo' ),
			'%site_description%' => __( 'Site Description', 'mantis-seo' ),
			'%sitedesc%'         => __( 'Site Description', 'mantis-seo' ),
			'%separator%'        => __( 'Separator', 'mantis-seo' ),
			'%page%'             => __( 'Page', 'mantis-seo' ),
			'%date%'             => __( 'Date', 'mantis-seo' ),
			'%excerpt%'          => __( 'Excerpt', 'mantis-seo' ),
		);
	}

	/**
	 * Variables shown in Insert dropdowns (one entry per concept; aliases omitted).
	 *
	 * @return array Map of %var% => label.
	 */
	public static function get_variable_dropdown_options() {
		return array(
			'%post_title%'       => __( 'Post Title', 'mantis-seo' ),
			'%site_name%'        => __( 'Site Title', 'mantis-seo' ),
			'%site_description%' => __( 'Site Description', 'mantis-seo' ),
			'%separator%'        => __( 'Separator', 'mantis-seo' ),
			'%page%'             => __( 'Page', 'mantis-seo' ),
			'%date%'             => __( 'Date', 'mantis-seo' ),
			'%excerpt%'          => __( 'Excerpt', 'mantis-seo' ),
		);
	}

	/**
	 * Get canonical URL for current request.
	 *
	 * @return string
	 */
	private function get_canonical_url() {
		if ( function_exists( 'wp_get_canonical_url' ) && is_singular() ) {
			$id = get_queried_object_id();
			if ( $id ) {
				$url = wp_get_canonical_url( $id );
				if ( ! empty( $url ) ) {
					return $url;
				}
			}
		}

		$paged = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : 0;
		$page  = get_query_var( 'page' ) ? (int) get_query_var( 'page' ) : 0;
		$p     = max( $paged, $page );
		if ( $p > 1 ) {
			$paginated = get_pagenum_link( $p, false );
			if ( ! empty( $paginated ) ) {
				return $paginated;
			}
		}

		global $wp;
		$request = isset( $wp->request ) && is_string( $wp->request ) ? $wp->request : '';
		if ( '' === $request ) {
			return home_url( '/' );
		}
		return home_url( user_trailingslashit( $request ) );
	}
}
