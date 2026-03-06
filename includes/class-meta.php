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
		add_filter( 'pre_get_document_title', array( $this, 'filter_title' ), 15 );
		add_filter( 'document_title_parts', array( $this, 'filter_title_parts' ), 15 );
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
				echo '<meta property="og:' . esc_attr( $key ) . '" content="' . esc_attr( $value ) . '">' . "\n";
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

		echo "\n<!-- Mantis SEO Twitter -->\n";
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

		$title = $this->get_post_meta( $object_id, 'title' );
		if ( empty( $title ) ) {
			$template = 'post' === $post->post_type ? $settings['title_post'] : $settings['title_page'];
			$title    = $this->replace_vars( $template, $post );
		}
		// Title is filtered separately via document_title_parts.

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
		$title = self::get_term_meta( $term_id, 'title' );
		$description = self::get_term_meta( $term_id, 'description' );

		if ( ! empty( $title ) ) {
			// Title for taxonomies is output via filter - WP doesn't have a standard way, so we'd need to hook wp_title or similar.
		}
		if ( ! empty( $description ) ) {
			$meta['description'] = $description;
		}

		$meta['robots'] = array( 'index', 'follow' );
		$meta['canonical'] = get_term_link( $term_id );

		return $meta;
	}

	/**
	 * Build OG array.
	 *
	 * @param array $meta Meta data.
	 * @return array
	 */
	private function build_og( $meta ) {
		$settings = Mantis_SEO_Main::get_settings();

		$og = array(
			'locale'      => get_locale(),
			'type'        => is_singular() ? 'article' : 'website',
			'site_name'   => get_bloginfo( 'name' ),
			'title'       => ! empty( $meta['og_title'] ) ? $meta['og_title'] : '',
			'description' => ! empty( $meta['og_description'] ) ? $meta['og_description'] : ( ! empty( $meta['description'] ) ? $meta['description'] : get_bloginfo( 'description' ) ),
			'url'         => ! empty( $meta['canonical'] ) ? $meta['canonical'] : $this->get_canonical_url(),
			'image'       => ! empty( $meta['og_image'] ) ? $meta['og_image'] : $settings['og_default_image'],
		);

		if ( is_singular() ) {
			if ( empty( $og['image'] ) ) {
				$thumb = get_the_post_thumbnail_url( get_queried_object_id(), 'full' );
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
		$twitter = array(
			'card'        => $settings['twitter_card_type'],
			'title'       => ! empty( $meta['twitter_title'] ) ? $meta['twitter_title'] : '',
			'description' => ! empty( $meta['twitter_description'] ) ? $meta['twitter_description'] : ( ! empty( $meta['description'] ) ? $meta['description'] : get_bloginfo( 'description' ) ),
			'image'       => ! empty( $meta['twitter_image'] ) ? $meta['twitter_image'] : ( ! empty( $meta['og_image'] ) ? $meta['og_image'] : $settings['og_default_image'] ),
		);

		if ( is_singular() && empty( $twitter['image'] ) ) {
			$thumb = get_the_post_thumbnail_url( get_queried_object_id(), 'full' );
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
			$custom = $this->get_post_meta( get_queried_object_id(), 'title' );
			if ( ! empty( $custom ) ) {
				return $custom;
			}
		}
		if ( is_home() && ! is_front_page() ) {
			$settings = Mantis_SEO_Main::get_settings();
			return $this->replace_vars( $settings['title_home'] );
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
			$custom = $this->get_post_meta( get_queried_object_id(), 'title' );
			if ( ! empty( $custom ) ) {
				$parts['title'] = $custom;
				unset( $parts['tagline'], $parts['site'] );
			}
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

		$replacements = array(
			'%site_name%'        => get_bloginfo( 'name' ),
			'%site_description%' => get_bloginfo( 'description' ),
			'%sitename%'         => get_bloginfo( 'name' ),
			'%sitedesc%'         => get_bloginfo( 'description' ),
			'%separator%'        => '|',
			'%date%'             => date_i18n( get_option( 'date_format' ) ),
		);

		if ( $post ) {
			$replacements['%post_title%'] = $post->post_title;
			$replacements['%title%']      = $post->post_title;
			$replacements['%excerpt%']    = has_excerpt( $post->ID ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
		}

		$settings = Mantis_SEO_Main::get_settings();
		if ( isset( $settings['separator'] ) ) {
			$replacements['%separator%'] = $settings['separator'];
		}

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $str );
	}

	/**
	 * Get canonical URL for current request.
	 *
	 * @return string
	 */
	private function get_canonical_url() {
		global $wp;
		return home_url( $wp->request );
	}
}
