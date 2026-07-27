<?php
/**
 * Sitemap router and renderer.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Sitemap
 */
class Mantis_SEO_Sitemap {

	/**
	 * Query var for sitemap.
	 *
	 * @var string
	 */
	const QUERY_VAR = 'mantis_sitemap';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ), 0 );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_request' ), 1 );
		add_filter( 'wp_sitemaps_enabled', array( $this, 'maybe_disable_core_sitemaps' ) );
		add_filter( 'robots_txt', array( $this, 'add_sitemap_to_robots' ), 10, 2 );
	}

	/**
	 * Disable WordPress core sitemaps when Mantis sitemap is enabled (avoid duplicates).
	 *
	 * @param bool $enabled Whether core sitemaps are enabled.
	 * @return bool
	 */
	public function maybe_disable_core_sitemaps( $enabled ) {
		$settings = Mantis_SEO_Main::get_settings();
		if ( ! empty( $settings['sitemap_enabled'] ) ) {
			return false;
		}
		return $enabled;
	}

	/**
	 * Advertise the Mantis sitemap index in robots.txt.
	 *
	 * @param string $output    robots.txt body.
	 * @param bool   $public    Whether the site is public (blog_public).
	 * @return string
	 */
	public function add_sitemap_to_robots( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}
		$settings = Mantis_SEO_Main::get_settings();
		if ( empty( $settings['sitemap_enabled'] ) ) {
			return $output;
		}
		$sitemap = home_url( '/sitemap_index.xml' );
		if ( false !== stripos( $output, 'Sitemap:' ) ) {
			return $output;
		}
		return rtrim( $output ) . "\n\nSitemap: " . $sitemap . "\n";
	}

	/**
	 * Register sitemap rewrite rules (must run before flush_rewrite_rules()).
	 *
	 * Called on init and from activation so rules exist when the rewrite option is rebuilt.
	 */
	public static function register_rewrite_rules() {
		global $wp_rewrite;
		if ( ! $wp_rewrite instanceof WP_Rewrite ) {
			return;
		}
		add_rewrite_rule( 'sitemap_index\.xml$', 'index.php?' . self::QUERY_VAR . '=index', 'top' );
		add_rewrite_rule( 'sitemap_([^/]+)\.xml$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
		add_rewrite_rule( 'sitemap_([^/]+)_([0-9]+)\.xml$', 'index.php?' . self::QUERY_VAR . '=$matches[1]&mantis_sitemap_page=$matches[2]', 'top' );
	}

	/**
	 * Add query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		$vars[] = 'mantis_sitemap_page';
		return $vars;
	}

	/**
	 * Sitemap URL/query slug for a post type (avoids double "s" for CPTs like "news").
	 *
	 * @param string $post_type Post type name.
	 * @return string
	 */
	public static function post_type_sitemap_slug( $post_type ) {
		if ( 'post' === $post_type ) {
			return 'posts';
		}
		if ( 'page' === $post_type ) {
			return 'pages';
		}
		$post_type = (string) $post_type;
		if ( '' !== $post_type && 's' === substr( $post_type, -1 ) ) {
			return $post_type;
		}
		return $post_type . 's';
	}

	/**
	 * Handle sitemap request.
	 */
	public function handle_request() {
		$type = get_query_var( self::QUERY_VAR );
		if ( empty( $type ) ) {
			return;
		}

		$settings = Mantis_SEO_Main::get_settings();
		if ( empty( $settings['sitemap_enabled'] ) ) {
			return;
		}

		$page = (int) get_query_var( 'mantis_sitemap_page', 1 );
		$this->serve_sitemap( $type, $page );
		exit;
	}

	/**
	 * Serve sitemap XML.
	 *
	 * @param string $type Sitemap type (index, posts, pages, etc).
	 * @param int    $page Page number.
	 */
	private function serve_sitemap( $type, $page = 1 ) {
		$providers = $this->get_providers();

		if ( 'index' === $type ) {
			$xml = $this->render_index( $providers );
		} else {
			$xml = $this->render_sitemap( $providers, $type, $page );
		}

		if ( $xml ) {
			header( 'Content-Type: application/xml; charset=UTF-8' );
			header( 'X-Robots-Tag: noindex, follow', true );
			echo $xml;
		} else {
			status_header( 404 );
		}
	}

	/**
	 * Get sitemap providers.
	 *
	 * @return Mantis_SEO_Sitemap_Provider[]
	 */
	private function get_providers() {
		$settings  = Mantis_SEO_Main::get_settings();
		$post_types = isset( $settings['sitemap_post_types'] ) && is_array( $settings['sitemap_post_types'] )
			? $settings['sitemap_post_types']
			: array( 'post', 'page' );

		$valid_types = array_keys( get_post_types( array( 'public' => true ), 'names' ) );
		$post_types  = array_intersect( $post_types, $valid_types );

		$providers = array();
		foreach ( $post_types as $post_type ) {
			$key                 = self::post_type_sitemap_slug( $post_type );
			$providers[ $key ] = new Mantis_SEO_Sitemap_Posts( $post_type );
		}

		// Omit archives that are globally noindexed so the sitemap matches robots.
		if ( empty( $settings['noindex_category'] ) ) {
			$providers['categories'] = new Mantis_SEO_Sitemap_Terms( 'category' );
		}
		if ( empty( $settings['noindex_tag'] ) ) {
			$providers['tags'] = new Mantis_SEO_Sitemap_Terms( 'post_tag' );
		}

		return apply_filters( 'mantis_sitemap_providers', $providers );
	}

	/**
	 * Render sitemap index.
	 *
	 * @param Mantis_SEO_Sitemap_Provider[] $providers Providers.
	 * @return string
	 */
	private function render_index( $providers ) {
		$max_entries = 1000;
		$links       = array();

		foreach ( $providers as $name => $provider ) {
			if ( ! $provider instanceof Mantis_SEO_Sitemap_Provider ) {
				continue;
			}
			$index_links = $provider->get_index_links( $max_entries );
			$links      = array_merge( $links, $index_links );
		}

		ob_start();
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( $links as $link ) {
			echo "\t<sitemap>\n";
			echo "\t\t<loc>" . esc_url( $link['loc'] ) . "</loc>\n";
			if ( ! empty( $link['lastmod'] ) ) {
				echo "\t\t<lastmod>" . esc_xml( $link['lastmod'] ) . "</lastmod>\n";
			}
			echo "\t</sitemap>\n";
		}
		echo '</sitemapindex>';
		return ob_get_clean();
	}

	/**
	 * Render single sitemap.
	 *
	 * @param Mantis_SEO_Sitemap_Provider[] $providers Providers.
	 * @param string                         $type     Type.
	 * @param int                            $page     Page.
	 * @return string
	 */
	private function render_sitemap( $providers, $type, $page ) {
		$max_entries = 1000;

		// Parse type like "posts" or "posts_2".
		$parts = explode( '_', $type, 2 );
		$base_type = $parts[0];

		foreach ( $providers as $name => $provider ) {
			if ( ! $provider instanceof Mantis_SEO_Sitemap_Provider ) {
				continue;
			}
			if ( $provider->handles_type( $base_type ) ) {
				$links = $provider->get_sitemap_links( $base_type, $max_entries, $page );
				return $this->render_urlset( $links );
			}
		}

		return '';
	}

	/**
	 * Render URL set.
	 *
	 * @param array $links URL links.
	 * @return string
	 */
	private function render_urlset( $links ) {
		ob_start();
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( $links as $link ) {
			echo "\t<url>\n";
			echo "\t\t<loc>" . esc_url( $link['loc'] ) . "</loc>\n";
			if ( ! empty( $link['lastmod'] ) ) {
				echo "\t\t<lastmod>" . esc_xml( $link['lastmod'] ) . "</lastmod>\n";
			}
			if ( ! empty( $link['changefreq'] ) ) {
				echo "\t\t<changefreq>" . esc_xml( $link['changefreq'] ) . "</changefreq>\n";
			}
			if ( ! empty( $link['priority'] ) ) {
				echo "\t\t<priority>" . esc_xml( $link['priority'] ) . "</priority>\n";
			}
			echo "\t</url>\n";
		}
		echo '</urlset>';
		return ob_get_clean();
	}
}
