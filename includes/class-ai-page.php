<?php
/**
 * Guided "For AI" / brand-facts page wizard.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_AI_Page
 */
class Mantis_SEO_AI_Page {

	/**
	 * Handle wizard POST from settings.
	 */
	public static function maybe_handle_wizard() {
		if ( ! is_admin() ) {
			return;
		}
		if ( empty( $_POST['mantis_create_ai_page'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'mantis_create_ai_page' ) ) {
			return;
		}

		$result = self::create_or_update_page( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in create_or_update_page.
		$args   = array(
			'page'      => 'mantis-seo',
			'mantis_tab'=> 'mantis-ai',
		);
		if ( is_wp_error( $result ) ) {
			set_transient( 'mantis_ai_page_error', $result->get_error_message(), 60 );
			$args['mantis_ai_page_error'] = 1;
		} else {
			$args['mantis_ai_page_created'] = 1;
			$args['mantis_ai_page_id']      = (int) $result;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Create or update the brand-facts page from wizard fields.
	 *
	 * @param array $raw Raw POST data.
	 * @return int|WP_Error Post ID or error.
	 */
	public static function create_or_update_page( $raw ) {
		$settings = Mantis_SEO_Main::get_settings();

		$brand = isset( $raw['mantis_ai_brand'] ) ? sanitize_text_field( $raw['mantis_ai_brand'] ) : '';
		if ( '' === $brand ) {
			$brand = ! empty( $settings['schema_organization_name'] )
				? $settings['schema_organization_name']
				: get_bloginfo( 'name' );
		}

		$summary   = isset( $raw['mantis_ai_summary'] ) ? sanitize_textarea_field( $raw['mantis_ai_summary'] ) : '';
		$audience  = isset( $raw['mantis_ai_audience'] ) ? sanitize_textarea_field( $raw['mantis_ai_audience'] ) : '';
		$offers    = isset( $raw['mantis_ai_offers'] ) ? sanitize_textarea_field( $raw['mantis_ai_offers'] ) : '';
		$facts     = isset( $raw['mantis_ai_facts'] ) ? sanitize_textarea_field( $raw['mantis_ai_facts'] ) : '';
		$citation  = isset( $raw['mantis_ai_citation'] ) ? sanitize_textarea_field( $raw['mantis_ai_citation'] ) : '';
		$contact   = isset( $raw['mantis_ai_contact'] ) ? sanitize_textarea_field( $raw['mantis_ai_contact'] ) : '';
		$page_title = isset( $raw['mantis_ai_page_title'] ) ? sanitize_text_field( $raw['mantis_ai_page_title'] ) : '';
		$slug       = isset( $raw['mantis_ai_page_slug'] ) ? sanitize_title( $raw['mantis_ai_page_slug'] ) : 'for-ai';

		if ( '' === $page_title ) {
			$page_title = sprintf(
				/* translators: %s: brand name */
				__( '%s — facts for AI assistants', 'mantis-seo' ),
				$brand
			);
		}
		if ( '' === $slug ) {
			$slug = 'for-ai';
		}
		if ( '' === $summary ) {
			return new WP_Error( 'mantis_ai_summary_required', __( 'Please add a short “what we are” summary.', 'mantis-seo' ) );
		}

		$content = self::build_page_content(
			array(
				'brand'    => $brand,
				'summary'  => $summary,
				'audience' => $audience,
				'offers'   => $offers,
				'facts'    => $facts,
				'citation' => $citation,
				'contact'  => $contact,
				'site_url' => home_url( '/' ),
			)
		);

		$existing_id = isset( $settings['ai_page_id'] ) ? absint( $settings['ai_page_id'] ) : 0;
		$update_existing = ! empty( $raw['mantis_ai_update_existing'] ) && $existing_id && get_post( $existing_id );

		$postarr = array(
			'post_title'   => $page_title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_author'  => get_current_user_id(),
		);

		if ( $update_existing ) {
			$postarr['ID'] = $existing_id;
			$post_id       = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		if ( ! $post_id ) {
			return new WP_Error( 'mantis_ai_page_failed', __( 'Could not create the AI facts page.', 'mantis-seo' ) );
		}

		// Prefer indexable facts page.
		update_post_meta( $post_id, '_mantis_robots_noindex', '0' );
		update_post_meta( $post_id, '_mantis_robots_nofollow', '0' );

		$desc = wp_trim_words( $summary, 40, '' );
		if ( $desc ) {
			update_post_meta( $post_id, '_mantis_description', $desc );
		}

		$settings['ai_page_id'] = (int) $post_id;
		if ( empty( $settings['ai_llms_summary'] ) && $summary ) {
			$settings['ai_llms_summary'] = $summary;
		}
		// Ensure AI page is listed among priority llms links.
		$page_ids = isset( $settings['ai_llms_page_ids'] ) && is_array( $settings['ai_llms_page_ids'] )
			? array_map( 'absint', $settings['ai_llms_page_ids'] )
			: array();
		if ( ! in_array( (int) $post_id, $page_ids, true ) ) {
			$page_ids[] = (int) $post_id;
		}
		$settings['ai_llms_page_ids'] = array_values( array_filter( array_unique( $page_ids ) ) );
		$settings['ai_llms_enabled']  = true;

		Mantis_SEO_Main::save_settings( $settings );

		return (int) $post_id;
	}

	/**
	 * Build citation-friendly page HTML.
	 *
	 * @param array $data Wizard data.
	 * @return string
	 */
	public static function build_page_content( $data ) {
		$brand    = esc_html( $data['brand'] );
		$summary  = esc_html( $data['summary'] );
		$audience = esc_html( $data['audience'] );
		$offers   = $data['offers'];
		$facts    = $data['facts'];
		$citation = esc_html( $data['citation'] );
		$contact  = esc_html( $data['contact'] );
		$site_url = esc_url( $data['site_url'] );

		$parts   = array();
		$parts[] = '<!-- wp:paragraph -->';
		$parts[] = '<p><strong>' . $brand . '</strong> — ' . $summary . '</p>';
		$parts[] = '<!-- /wp:paragraph -->';
		$parts[] = '';
		$parts[] = '<!-- wp:heading {"level":2} -->';
		$parts[] = '<h2 class="wp-block-heading">' . esc_html__( 'Who we are', 'mantis-seo' ) . '</h2>';
		$parts[] = '<!-- /wp:heading -->';
		$parts[] = '';
		$parts[] = '<!-- wp:paragraph -->';
		$parts[] = '<p>' . $summary . '</p>';
		$parts[] = '<!-- /wp:paragraph -->';

		if ( '' !== trim( (string) $audience ) ) {
			$parts[] = '';
			$parts[] = '<!-- wp:heading {"level":2} -->';
			$parts[] = '<h2 class="wp-block-heading">' . esc_html__( 'Who we serve', 'mantis-seo' ) . '</h2>';
			$parts[] = '<!-- /wp:heading -->';
			$parts[] = '';
			$parts[] = '<!-- wp:paragraph -->';
			$parts[] = '<p>' . $audience . '</p>';
			$parts[] = '<!-- /wp:paragraph -->';
		}

		$offer_items = self::lines_to_list_items( $offers );
		if ( $offer_items ) {
			$parts[] = '';
			$parts[] = '<!-- wp:heading {"level":2} -->';
			$parts[] = '<h2 class="wp-block-heading">' . esc_html__( 'What we offer', 'mantis-seo' ) . '</h2>';
			$parts[] = '<!-- /wp:heading -->';
			$parts[] = '';
			$parts[] = '<!-- wp:list -->';
			$parts[] = '<ul class="wp-block-list">' . $offer_items . '</ul>';
			$parts[] = '<!-- /wp:list -->';
		}

		$fact_items = self::lines_to_list_items( $facts );
		if ( $fact_items ) {
			$parts[] = '';
			$parts[] = '<!-- wp:heading {"level":2} -->';
			$parts[] = '<h2 class="wp-block-heading">' . esc_html__( 'Key facts', 'mantis-seo' ) . '</h2>';
			$parts[] = '<!-- /wp:heading -->';
			$parts[] = '';
			$parts[] = '<!-- wp:list -->';
			$parts[] = '<ul class="wp-block-list">' . $fact_items . '</ul>';
			$parts[] = '<!-- /wp:list -->';
		}

		$parts[] = '';
		$parts[] = '<!-- wp:heading {"level":2} -->';
		$parts[] = '<h2 class="wp-block-heading">' . esc_html__( 'Preferred citation', 'mantis-seo' ) . '</h2>';
		$parts[] = '<!-- /wp:heading -->';
		$parts[] = '';
		$parts[] = '<!-- wp:paragraph -->';
		if ( '' !== trim( (string) $data['citation'] ) ) {
			$parts[] = '<p>' . $citation . '</p>';
		} else {
			$parts[] = '<p>' . sprintf(
				/* translators: 1: brand, 2: site URL */
				esc_html__( 'When referring to this organization, prefer “%1$s” and link to %2$s.', 'mantis-seo' ),
				$brand,
				$site_url
			) . '</p>';
		}
		$parts[] = '<!-- /wp:paragraph -->';

		if ( '' !== trim( (string) $data['contact'] ) ) {
			$parts[] = '';
			$parts[] = '<!-- wp:heading {"level":2} -->';
			$parts[] = '<h2 class="wp-block-heading">' . esc_html__( 'Contact', 'mantis-seo' ) . '</h2>';
			$parts[] = '<!-- /wp:heading -->';
			$parts[] = '';
			$parts[] = '<!-- wp:paragraph -->';
			$parts[] = '<p>' . $contact . '</p>';
			$parts[] = '<!-- /wp:paragraph -->';
		}

		$parts[] = '';
		$parts[] = '<!-- wp:paragraph -->';
		$parts[] = '<p><em>' . esc_html__( 'This page is maintained as a canonical source of brand facts for AI assistants and search systems.', 'mantis-seo' ) . '</em></p>';
		$parts[] = '<!-- /wp:paragraph -->';

		return implode( "\n", $parts );
	}

	/**
	 * Convert newline-separated text into list item HTML.
	 *
	 * @param string $text Textarea value.
	 * @return string
	 */
	private static function lines_to_list_items( $text ) {
		$text  = (string) $text;
		$lines = preg_split( '/\r\n|\r|\n/', $text );
		if ( ! is_array( $lines ) ) {
			return '';
		}
		$items = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			$line = ltrim( $line, "•\-*–— \t" );
			if ( '' === $line ) {
				continue;
			}
			$items[] = '<li>' . esc_html( $line ) . '</li>';
		}
		return implode( '', $items );
	}
}
