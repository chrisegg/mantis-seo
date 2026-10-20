<?php
/**
 * Serve /llms.txt and optional /llms-full.txt for AI agents.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_LLMs_Txt
 */
class Mantis_SEO_LLMs_Txt {

	/**
	 * Query var.
	 *
	 * @var string
	 */
	const QUERY_VAR = 'mantis_llms';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ), 0 );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_request' ), 1 );
		add_filter( 'robots_txt', array( $this, 'mention_llms_in_robots' ), 15, 2 );
	}

	/**
	 * Register rewrite rules.
	 */
	public static function register_rewrite_rules() {
		global $wp_rewrite;
		if ( ! $wp_rewrite instanceof WP_Rewrite ) {
			return;
		}
		add_rewrite_rule( 'llms\.txt$', 'index.php?' . self::QUERY_VAR . '=index', 'top' );
		add_rewrite_rule( 'llms-full\.txt$', 'index.php?' . self::QUERY_VAR . '=full', 'top' );
	}

	/**
	 * Add query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Optionally note llms.txt in robots.txt comments.
	 *
	 * @param string $output robots.txt body.
	 * @param bool   $public Whether the site is public.
	 * @return string
	 */
	public function mention_llms_in_robots( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}
		$settings = Mantis_SEO_Main::get_settings();
		if ( empty( $settings['ai_llms_enabled'] ) ) {
			return $output;
		}
		if ( false !== stripos( $output, 'llms.txt' ) ) {
			return $output;
		}
		$note = "\n\n# AI discovery: " . home_url( '/llms.txt' ) . "\n";
		return rtrim( $output ) . $note;
	}

	/**
	 * Handle llms.txt requests.
	 */
	public function handle_request() {
		$type = get_query_var( self::QUERY_VAR );
		if ( empty( $type ) ) {
			return;
		}

		$settings = Mantis_SEO_Main::get_settings();
		if ( empty( $settings['ai_llms_enabled'] ) ) {
			status_header( 404 );
			exit;
		}

		if ( 'full' === $type ) {
			if ( empty( $settings['ai_llms_full_enabled'] ) ) {
				status_header( 404 );
				exit;
			}
			$body = $this->build_llms_full( $settings );
		} else {
			$body = $this->build_llms_txt( $settings );
		}

		nocache_headers();
		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow', true );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text document.
		exit;
	}

	/**
	 * Build curated llms.txt.
	 *
	 * @param array $settings Plugin settings.
	 * @return string
	 */
	public function build_llms_txt( $settings ) {
		$site_name = get_bloginfo( 'name' );
		$summary   = isset( $settings['ai_llms_summary'] ) ? trim( (string) $settings['ai_llms_summary'] ) : '';
		if ( '' === $summary ) {
			$summary = get_bloginfo( 'description' );
		}
		if ( '' === $summary ) {
			$summary = sprintf(
				/* translators: %s: site name */
				__( '%s — curated facts and key pages for AI assistants.', 'mantis-seo' ),
				$site_name
			);
		}

		$lines   = array();
		$lines[] = '# ' . $this->plain_line( $site_name );
		$lines[] = '';
		$lines[] = '> ' . $this->plain_line( $summary );
		$lines[] = '';

		$ai_page_id = isset( $settings['ai_page_id'] ) ? absint( $settings['ai_page_id'] ) : 0;
		if ( $ai_page_id && 'publish' === get_post_status( $ai_page_id ) ) {
			$lines[] = '## Brand facts for AI';
			$lines[] = '';
			$lines[] = sprintf(
				'- [%s](%s): %s',
				$this->plain_line( get_the_title( $ai_page_id ) ),
				esc_url_raw( get_permalink( $ai_page_id ) ),
				$this->plain_line( __( 'Canonical brand facts page for AI assistants and crawlers.', 'mantis-seo' ) )
			);
			$lines[] = '';
		}

		$priority = $this->get_priority_links( $settings );
		if ( ! empty( $priority ) ) {
			$lines[] = '## Important pages';
			$lines[] = '';
			foreach ( $priority as $link ) {
				$lines[] = sprintf(
					'- [%s](%s): %s',
					$this->plain_line( $link['title'] ),
					esc_url_raw( $link['url'] ),
					$this->plain_line( $link['note'] )
				);
			}
			$lines[] = '';
		}

		$optional = $this->parse_extra_links( isset( $settings['ai_llms_extra_links'] ) ? $settings['ai_llms_extra_links'] : '' );
		if ( ! empty( $optional ) ) {
			$lines[] = '## Optional';
			$lines[] = '';
			foreach ( $optional as $link ) {
				$note = $link['note'] !== '' ? $link['note'] : __( 'Additional resource.', 'mantis-seo' );
				$lines[] = sprintf(
					'- [%s](%s): %s',
					$this->plain_line( $link['title'] ),
					esc_url_raw( $link['url'] ),
					$this->plain_line( $note )
				);
			}
			$lines[] = '';
		}

		if ( ! empty( $settings['ai_llms_full_enabled'] ) ) {
			$lines[] = '## Full content';
			$lines[] = '';
			$lines[] = sprintf(
				'- [%s](%s): %s',
				'llms-full.txt',
				esc_url_raw( home_url( '/llms-full.txt' ) ),
				$this->plain_line( __( 'Expanded Markdown dump of the AI facts page and priority URLs.', 'mantis-seo' ) )
			);
			$lines[] = '';
		}

		/**
		 * Filter the generated llms.txt body.
		 *
		 * @param string $body     Document body.
		 * @param array  $settings Plugin settings.
		 */
		$body = implode( "\n", $lines );
		return apply_filters( 'mantis_llms_txt_content', $body, $settings );
	}

	/**
	 * Build optional llms-full.txt (Markdown dump).
	 *
	 * @param array $settings Plugin settings.
	 * @return string
	 */
	public function build_llms_full( $settings ) {
		$parts   = array();
		$parts[] = $this->build_llms_txt( $settings );
		$parts[] = '';
		$parts[] = '---';
		$parts[] = '';

		$ai_page_id = isset( $settings['ai_page_id'] ) ? absint( $settings['ai_page_id'] ) : 0;
		if ( $ai_page_id && 'publish' === get_post_status( $ai_page_id ) ) {
			$parts[] = $this->post_to_markdown( $ai_page_id );
			$parts[] = '';
			$parts[] = '---';
			$parts[] = '';
		}

		foreach ( $this->get_priority_links( $settings ) as $link ) {
			if ( empty( $link['post_id'] ) ) {
				continue;
			}
			if ( (int) $link['post_id'] === $ai_page_id ) {
				continue;
			}
			$parts[] = $this->post_to_markdown( (int) $link['post_id'] );
			$parts[] = '';
			$parts[] = '---';
			$parts[] = '';
		}

		$body = rtrim( implode( "\n", $parts ) ) . "\n";
		/**
		 * Filter the generated llms-full.txt body.
		 *
		 * @param string $body     Document body.
		 * @param array  $settings Plugin settings.
		 */
		return apply_filters( 'mantis_llms_full_content', $body, $settings );
	}

	/**
	 * Priority links: home + AI page + selected page IDs.
	 *
	 * @param array $settings Settings.
	 * @return array<int, array{title:string, url:string, note:string, post_id?:int}>
	 */
	private function get_priority_links( $settings ) {
		$links = array();

		$links[] = array(
			'title' => get_bloginfo( 'name' ) . ' — ' . __( 'Home', 'mantis-seo' ),
			'url'   => home_url( '/' ),
			'note'  => __( 'Primary website homepage.', 'mantis-seo' ),
		);

		$page_ids = isset( $settings['ai_llms_page_ids'] ) && is_array( $settings['ai_llms_page_ids'] )
			? array_map( 'absint', $settings['ai_llms_page_ids'] )
			: array();
		$page_ids = array_filter( array_unique( $page_ids ) );

		foreach ( $page_ids as $page_id ) {
			if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
				continue;
			}
			$excerpt = get_the_excerpt( $page_id );
			if ( '' === trim( (string) $excerpt ) ) {
				$excerpt = __( 'Key page on this site.', 'mantis-seo' );
			}
			$links[] = array(
				'title'   => get_the_title( $page_id ),
				'url'     => get_permalink( $page_id ),
				'note'    => wp_strip_all_tags( $excerpt ),
				'post_id' => $page_id,
			);
		}

		return $links;
	}

	/**
	 * Parse extra links textarea: Title | https://url | optional note (one per line).
	 *
	 * @param string $raw Raw textarea.
	 * @return array<int, array{title:string, url:string, note:string}>
	 */
	private function parse_extra_links( $raw ) {
		$raw   = (string) $raw;
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$out   = array();
		if ( ! is_array( $lines ) ) {
			return $out;
		}
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( count( $parts ) < 2 ) {
				continue;
			}
			$title = $parts[0];
			$url   = esc_url_raw( $parts[1] );
			$note  = isset( $parts[2] ) ? $parts[2] : '';
			if ( '' === $title || '' === $url ) {
				continue;
			}
			$out[] = array(
				'title' => $title,
				'url'   => $url,
				'note'  => $note,
			);
		}
		return $out;
	}

	/**
	 * Convert a published post/page to simple Markdown.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function post_to_markdown( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return '';
		}
		$title   = get_the_title( $post );
		$url     = get_permalink( $post );
		$content = strip_shortcodes( $post->post_content );
		$content = preg_replace( '/<!--.*?-->/s', '', $content );
		$content = wp_strip_all_tags( (string) $content, false );
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$content = preg_replace( "/[ \t]+/", ' ', $content );
		$content = preg_replace( "/\n{3,}/", "\n\n", trim( (string) $content ) );

		$md   = array();
		$md[] = '# ' . $this->plain_line( $title );
		$md[] = '';
		$md[] = 'Source: ' . esc_url_raw( $url );
		$md[] = '';
		$md[] = $content;
		return implode( "\n", $md );
	}

	/**
	 * Collapse whitespace for a single plain-text line.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function plain_line( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		return trim( (string) $text );
	}
}
