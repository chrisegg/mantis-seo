<?php
/**
 * AI crawler definitions and robots.txt policy output.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_AI_Bots
 */
class Mantis_SEO_AI_Bots {

	/**
	 * Known AI user-agents and recommended defaults for GEO visibility.
	 *
	 * Policies: allow | block | inherit (omit from robots.txt).
	 *
	 * @return array<string, array{label:string, description:string, user_agent:string, default:string, group:string}>
	 */
	public static function get_bot_definitions() {
		return array(
			'oai_searchbot'       => array(
				'label'       => 'OAI-SearchBot',
				'description' => __( 'OpenAI search indexing (ChatGPT citations). Allow for AI answer visibility.', 'mantis-seo' ),
				'user_agent'  => 'OAI-SearchBot',
				'default'     => 'allow',
				'group'       => 'search',
			),
			'chatgpt_user'        => array(
				'label'       => 'ChatGPT-User',
				'description' => __( 'Live browsing when a ChatGPT user asks about your site.', 'mantis-seo' ),
				'user_agent'  => 'ChatGPT-User',
				'default'     => 'allow',
				'group'       => 'search',
			),
			'gptbot'              => array(
				'label'       => 'GPTBot',
				'description' => __( 'OpenAI training crawler. Separate from search; block if you do not want training use.', 'mantis-seo' ),
				'user_agent'  => 'GPTBot',
				'default'     => 'inherit',
				'group'       => 'training',
			),
			'claude_searchbot'    => array(
				'label'       => 'Claude-SearchBot',
				'description' => __( 'Anthropic search indexing. Allow for Claude citations.', 'mantis-seo' ),
				'user_agent'  => 'Claude-SearchBot',
				'default'     => 'allow',
				'group'       => 'search',
			),
			'claude_user'         => array(
				'label'       => 'Claude-User',
				'description' => __( 'Live fetch when a Claude user asks about your site.', 'mantis-seo' ),
				'user_agent'  => 'Claude-User',
				'default'     => 'allow',
				'group'       => 'search',
			),
			'claudebot'           => array(
				'label'       => 'ClaudeBot',
				'description' => __( 'Anthropic crawl/training. Separate from search.', 'mantis-seo' ),
				'user_agent'  => 'ClaudeBot',
				'default'     => 'inherit',
				'group'       => 'training',
			),
			'perplexitybot'       => array(
				'label'       => 'PerplexityBot',
				'description' => __( 'Perplexity indexing. Allow if you want Perplexity citations.', 'mantis-seo' ),
				'user_agent'  => 'PerplexityBot',
				'default'     => 'allow',
				'group'       => 'search',
			),
			'perplexity_user'     => array(
				'label'       => 'Perplexity-User',
				'description' => __( 'Live fetch for Perplexity answers.', 'mantis-seo' ),
				'user_agent'  => 'Perplexity-User',
				'default'     => 'allow',
				'group'       => 'search',
			),
			'google_extended'     => array(
				'label'       => 'Google-Extended',
				'description' => __( 'Controls Gemini grounding/training use of your content (not classic Google Search or AI Overviews).', 'mantis-seo' ),
				'user_agent'  => 'Google-Extended',
				'default'     => 'inherit',
				'group'       => 'training',
			),
			'applebot_extended'   => array(
				'label'       => 'Applebot-Extended',
				'description' => __( 'Apple Intelligence training token.', 'mantis-seo' ),
				'user_agent'  => 'Applebot-Extended',
				'default'     => 'inherit',
				'group'       => 'training',
			),
			'ccbot'               => array(
				'label'       => 'CCBot',
				'description' => __( 'Common Crawl. Often blocked when you want tighter control over scrapers.', 'mantis-seo' ),
				'user_agent'  => 'CCBot',
				'default'     => 'block',
				'group'       => 'training',
			),
			'bytespider'          => array(
				'label'       => 'Bytespider',
				'description' => __( 'ByteDance crawler. Often blocked.', 'mantis-seo' ),
				'user_agent'  => 'Bytespider',
				'default'     => 'block',
				'group'       => 'training',
			),
		);
	}

	/**
	 * Default policy map keyed by bot id.
	 *
	 * @return array<string, string>
	 */
	public static function get_default_policies() {
		$out = array();
		foreach ( self::get_bot_definitions() as $id => $bot ) {
			$out[ $id ] = $bot['default'];
		}
		return $out;
	}

	/**
	 * Merge saved policies with defaults.
	 *
	 * @param mixed $saved Saved policies.
	 * @return array<string, string>
	 */
	public static function merge_policies( $saved ) {
		$defaults = self::get_default_policies();
		if ( ! is_array( $saved ) ) {
			return $defaults;
		}
		$allowed = array( 'allow', 'block', 'inherit' );
		$merged  = $defaults;
		foreach ( $saved as $id => $policy ) {
			$id     = sanitize_key( (string) $id );
			$policy = sanitize_key( (string) $policy );
			if ( isset( $defaults[ $id ] ) && in_array( $policy, $allowed, true ) ) {
				$merged[ $id ] = $policy;
			}
		}
		return $merged;
	}

	/**
	 * Sanitize policies from settings input.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, string>
	 */
	public static function sanitize_policies( $input ) {
		return self::merge_policies( $input );
	}

	/**
	 * Constructor: append AI rules to robots.txt.
	 */
	public function __construct() {
		add_filter( 'robots_txt', array( $this, 'append_ai_rules' ), 20, 2 );
	}

	/**
	 * Append AI crawler Allow/Disallow groups.
	 *
	 * @param string $output robots.txt body.
	 * @param bool   $public Whether the site is public.
	 * @return string
	 */
	public function append_ai_rules( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}

		$settings = Mantis_SEO_Main::get_settings();
		if ( empty( $settings['ai_robots_enabled'] ) ) {
			return $output;
		}

		$policies = self::merge_policies( isset( $settings['ai_bot_policies'] ) ? $settings['ai_bot_policies'] : array() );
		$defs     = self::get_bot_definitions();
		$blocks   = array();

		foreach ( $policies as $id => $policy ) {
			if ( 'inherit' === $policy || ! isset( $defs[ $id ] ) ) {
				continue;
			}
			$ua = $defs[ $id ]['user_agent'];
			if ( 'allow' === $policy ) {
				$blocks[] = "User-agent: {$ua}\nAllow: /\n";
			} elseif ( 'block' === $policy ) {
				$blocks[] = "User-agent: {$ua}\nDisallow: /\n";
			}
		}

		if ( empty( $blocks ) ) {
			return $output;
		}

		$section = "\n\n# Mantis SEO — AI crawler policies\n" . implode( "\n", $blocks );
		return rtrim( $output ) . $section;
	}
}
