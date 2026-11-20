<?php
/**
 * Custom robots.txt directives.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Robots
 */
class Mantis_SEO_Robots {

	/**
	 * Constructor: add saved rules to the catch-all group before sitemap and AI groups.
	 */
	public function __construct() {
		add_filter( 'robots_txt', array( $this, 'add_custom_rules' ), 5, 2 );
	}

	/**
	 * Insert saved Allow, Disallow, and Crawl-delay lines into User-agent: *.
	 *
	 * Runs before the sitemap line (priority 10), the llms.txt note (15), and
	 * AI crawler groups (20). Named AI groups are updated separately so those
	 * crawlers, which ignore the * group, still see the same paths.
	 *
	 * @param string $output robots.txt body.
	 * @param bool   $public Whether the site is public.
	 * @return string
	 */
	public function add_custom_rules( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}

		$settings = Mantis_SEO_Main::get_settings();
		$rules    = isset( $settings['robots_custom_rules'] ) ? (string) $settings['robots_custom_rules'] : '';
		return self::insert_into_star_group( $output, $rules );
	}

	/**
	 * Whether a physical robots.txt will be served instead of WordPress.
	 *
	 * @return bool
	 */
	public static function has_physical_file() {
		return is_file( ABSPATH . 'robots.txt' );
	}

	/**
	 * Keep Allow, Disallow, and Crawl-delay lines. Everything else is dropped.
	 *
	 * @param mixed $raw Raw textarea value.
	 * @return string
	 */
	public static function sanitize_rules( $raw ) {
		if ( ! is_string( $raw ) ) {
			return '';
		}
		if ( function_exists( 'wp_unslash' ) ) {
			$raw = wp_unslash( $raw );
		}

		$raw  = str_replace( array( "\r\n", "\r" ), "\n", $raw );
		$kept = array();
		foreach ( explode( "\n", $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			if ( ! preg_match( '/^(Allow|Disallow|Crawl-delay)\s*:\s*(.*)$/i', $line, $matches ) ) {
				continue;
			}

			$directive = strtolower( $matches[1] );
			if ( 'allow' === $directive ) {
				$directive = 'Allow';
			} elseif ( 'disallow' === $directive ) {
				$directive = 'Disallow';
			} else {
				$directive = 'Crawl-delay';
			}

			$value = trim( $matches[2] );
			$value = preg_replace( '/[\x00-\x1F\x7F]/', '', $value );
			if ( ! is_string( $value ) || strlen( $value ) > 500 ) {
				continue;
			}

			if ( 'Crawl-delay' === $directive ) {
				if ( ! preg_match( '/^\d+(\.\d+)?$/', $value ) ) {
					continue;
				}
			} elseif ( '' === $value ) {
				if ( 'Disallow' !== $directive ) {
					continue;
				}
			} elseif ( ! preg_match( '/^[^\s#]+$/', $value ) ) {
				continue;
			}

			$normalized = ( '' === $value ) ? $directive . ':' : $directive . ': ' . $value;
			if ( in_array( $normalized, $kept, true ) ) {
				continue;
			}
			$kept[] = $normalized;
		}

		return implode( "\n", $kept );
	}

	/**
	 * Sanitized directives as separate lines.
	 *
	 * @param string $rules Raw or stored rules.
	 * @return string[]
	 */
	public static function lines( $rules ) {
		$clean = self::sanitize_rules( $rules );
		if ( '' === $clean ) {
			return array();
		}
		return explode( "\n", $clean );
	}

	/**
	 * Place directives in the opening User-agent: * group.
	 *
	 * An empty rule list returns $output unchanged. When a Sitemap line or a
	 * named user-agent is already present, the directives go immediately before it.
	 *
	 * @param string $output robots.txt body.
	 * @param string $rules  Raw or stored rules.
	 * @return string
	 */
	public static function insert_into_star_group( $output, $rules ) {
		$lines = self::lines( $rules );
		if ( empty( $lines ) ) {
			return $output;
		}

		$block  = implode( "\n", $lines );
		$output = (string) $output;
		if ( preg_match( '/^(Sitemap:|User-agent:[ \t]+(?!\*))/mi', $output, $matches, PREG_OFFSET_CAPTURE ) ) {
			$pos  = $matches[0][1];
			$head = rtrim( substr( $output, 0, $pos ), "\r\n" );
			$tail = ltrim( substr( $output, $pos ), "\r\n" );
			return $head . "\n" . $block . "\n\n" . $tail;
		}

		return rtrim( $output, "\r\n" ) . "\n" . $block . "\n";
	}

	/**
	 * Append custom directives to one crawler group without duplicating a line.
	 *
	 * @param string[] $group_lines Lines already in the group, without the User-agent line.
	 * @param string[] $custom      Sanitized directive lines.
	 * @return string[]
	 */
	public static function merge_group_lines( $group_lines, $custom ) {
		foreach ( $custom as $line ) {
			if ( ! in_array( $line, $group_lines, true ) ) {
				$group_lines[] = $line;
			}
		}
		return $group_lines;
	}
}
