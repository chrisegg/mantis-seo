<?php
/**
 * Redirect storage, matching, and execution.
 *
 * @package Mantis_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mantis_SEO_Redirects
 */
class Mantis_SEO_Redirects {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	const TABLE_NAME = 'mantis_redirects';

	/**
	 * Transient key for cached regex redirect rows.
	 *
	 * @var string
	 */
	const TRANSIENT_REGEX_ROWS = 'mantis_seo_regex_redirects';

	/**
	 * PCRE delimiter for regex redirects (literal `~` in a pattern must be written as \~).
	 *
	 * @var string
	 */
	private static $regex_delimiter = '~';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
	}

	/**
	 * Bust cached regex redirect list.
	 */
	public static function bust_regex_cache() {
		delete_transient( self::TRANSIENT_REGEX_ROWS );
	}

	/**
	 * Wrap stored regex origin for preg_* (delimiter-safe).
	 *
	 * @param string $pattern Pattern from DB.
	 * @return string
	 */
	private static function regex_pcre( $pattern ) {
		$d = self::$regex_delimiter;
		return $d . str_replace( $d, '\\' . $d, $pattern ) . $d;
	}

	/**
	 * Whether a regex pattern compiles without PCRE errors.
	 *
	 * @param string $pattern Normalized origin (regex body).
	 * @return bool
	 */
	public static function is_valid_regex_pattern( $pattern ) {
		$pattern = (string) $pattern;
		if ( '' === $pattern ) {
			return false;
		}
		$wrapped = self::regex_pcre( $pattern );
		$result  = @preg_match( $wrapped, '/' );
		if ( false === $result ) {
			return false;
		}
		return PREG_NO_ERROR === preg_last_error();
	}

	/**
	 * Sanitize redirect / gone HTTP status.
	 *
	 * @param int $type Raw type.
	 * @return int
	 */
	public static function sanitize_redirect_type( $type ) {
		$type = (int) $type;
		$ok   = array( 301, 302, 303, 307, 308, 410, 451 );
		return in_array( $type, $ok, true ) ? $type : 301;
	}

	/**
	 * Sanitize stored redirect target (absolute URL, protocol-relative, or site path).
	 *
	 * @param string $raw Raw target from user or import.
	 * @return string Empty if invalid.
	 */
	public static function sanitize_target( $raw ) {
		$raw = is_string( $raw ) ? trim( $raw ) : '';
		if ( '' === $raw ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $raw ) ) {
			return esc_url_raw( $raw );
		}

		if ( 0 === strpos( $raw, '//' ) ) {
			$check = esc_url_raw( 'https:' . $raw );
			return $check ? $check : '';
		}

		$path = self::normalize_origin( $raw );
		return sanitize_text_field( $path );
	}

	/**
	 * Resolve stored target to an absolute URL for redirects and admin links.
	 *
	 * @param string $stored Value from DB.
	 * @return string Empty if invalid.
	 */
	public static function resolve_target_url( $stored ) {
		$stored = (string) $stored;
		if ( '' === $stored ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $stored ) ) {
			return esc_url_raw( $stored );
		}

		if ( 0 === strpos( $stored, '//' ) ) {
			$abs = esc_url_raw( 'https:' . $stored );
			return $abs ? $abs : '';
		}

		return esc_url_raw( home_url( $stored ) );
	}

	/**
	 * Regex redirect rows (transient-cached).
	 *
	 * @return array
	 */
	private static function get_regex_redirect_rows() {
		$cached = get_transient( self::TRANSIENT_REGEX_ROWS );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name from $wpdb->prefix + constant only.
		$rows  = $wpdb->get_results( "SELECT * FROM $table WHERE format = 'regex' ORDER BY id ASC", ARRAY_A );
		$rows  = is_array( $rows ) ? $rows : array();
		set_transient( self::TRANSIENT_REGEX_ROWS, $rows, HOUR_IN_SECONDS );
		return $rows;
	}

	/**
	 * Create the redirects table.
	 */
	public static function create_table() {
		global $wpdb;
		$table_name     = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dbDelta: table name from $wpdb->prefix + constant only.
		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			origin varchar(500) NOT NULL,
			target varchar(500) NOT NULL,
			type smallint(3) NOT NULL DEFAULT 301,
			format varchar(10) NOT NULL DEFAULT 'plain',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY origin (origin(191))
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Prepared WHERE clause for admin redirect search, or empty string.
	 *
	 * Matches origin, target, format, and type.
	 *
	 * @param string $search Raw search term.
	 * @return string
	 */
	private static function prepared_search_where( $search ) {
		global $wpdb;
		$search = is_string( $search ) ? trim( $search ) : '';
		if ( '' === $search ) {
			return '';
		}
		if ( strlen( $search ) > 200 ) {
			$search = substr( $search, 0, 200 );
		}
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		return $wpdb->prepare(
			' WHERE (origin LIKE %s OR target LIKE %s OR format LIKE %s OR CAST(type AS CHAR) LIKE %s)',
			$like,
			$like,
			$like,
			$like
		);
	}

	/**
	 * Get redirects (optionally paginated and filtered).
	 *
	 * @param int|null $limit  Max rows, or null for all.
	 * @param int      $offset Offset for LIMIT.
	 * @param string   $search Optional origin/target/type/format search.
	 * @return array
	 */
	public static function get_all( $limit = null, $offset = 0, $search = '' ) {
		global $wpdb;
		$table = self::get_table_name();
		$sql   = "SELECT * FROM $table" . self::prepared_search_where( $search ) . ' ORDER BY format ASC, origin ASC';
		if ( null !== $limit ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', max( 1, (int) $limit ), max( 0, (int) $offset ) );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name from $wpdb->prefix + constant only; search clause is prepared.
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Total redirect count (optionally matching a search).
	 *
	 * @param string $search Optional origin/target/type/format search.
	 * @return int
	 */
	public static function count_all( $search = '' ) {
		global $wpdb;
		$table = self::get_table_name();
		$sql   = "SELECT COUNT(*) FROM $table" . self::prepared_search_where( $search );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name from $wpdb->prefix + constant only; search clause is prepared.
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get redirect by ID.
	 *
	 * @param int $id Redirect ID.
	 * @return array|null
	 */
	public static function get_by_id( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		$id = (int) $id;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix + constant only.
		$sql = $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id );
		return $wpdb->get_row( $sql, ARRAY_A );
	}

	/**
	 * Add redirect.
	 *
	 * @param string $origin  Origin path.
	 * @param string $target  Target URL.
	 * @param int    $type    Redirect type (301, 302, etc).
	 * @param string $format  Format: plain or regex.
	 * @return int|false Insert ID or false.
	 */
	public static function add( $origin, $target, $type = 301, $format = 'plain' ) {
		global $wpdb;
		$table = self::get_table_name();

		$format = 'regex' === $format ? 'regex' : 'plain';
		$origin = self::normalize_origin( self::sanitize_origin( $origin, $format ) );
		$target = self::sanitize_target( $target );
		$type   = self::sanitize_redirect_type( $type );

		if ( empty( $origin ) || empty( $target ) ) {
			return false;
		}

		if ( 'regex' === $format && ! self::is_valid_regex_pattern( $origin ) ) {
			return false;
		}

		$result = $wpdb->insert(
			$table,
			array(
				'origin' => $origin,
				'target' => $target,
				'type'   => $type,
				'format' => $format,
			),
			array( '%s', '%s', '%d', '%s' )
		);

		if ( $result ) {
			self::bust_regex_cache();
			return (int) $wpdb->insert_id;
		}
		return false;
	}

	/**
	 * Update redirect.
	 *
	 * @param int    $id     Redirect ID.
	 * @param string $origin Origin path.
	 * @param string $target Target URL.
	 * @param int    $type   Redirect type.
	 * @param string $format Format.
	 * @return bool
	 */
	public static function update( $id, $origin, $target, $type = 301, $format = 'plain' ) {
		global $wpdb;
		$table = self::get_table_name();
		$id    = (int) $id;

		$format = 'regex' === $format ? 'regex' : 'plain';
		$origin = self::normalize_origin( self::sanitize_origin( $origin, $format ) );
		$target = self::sanitize_target( $target );
		$type   = self::sanitize_redirect_type( $type );

		if ( empty( $origin ) || empty( $target ) ) {
			return false;
		}

		if ( 'regex' === $format && ! self::is_valid_regex_pattern( $origin ) ) {
			return false;
		}

		$ok = (bool) $wpdb->update(
			$table,
			array(
				'origin' => $origin,
				'target' => $target,
				'type'   => $type,
				'format' => $format,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);
		if ( $ok ) {
			self::bust_regex_cache();
		}
		return $ok;
	}

	/**
	 * Delete redirect.
	 *
	 * @param int $id Redirect ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		$id    = (int) $id;
		$ok    = (bool) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		if ( $ok ) {
			self::bust_regex_cache();
		}
		return $ok;
	}

	/**
	 * Find matching redirect for request path.
	 *
	 * Plain origins match with or without a trailing slash (e.g. /old-page and /old-page/).
	 * Regex patterns are tested against both slash variants of the request path.
	 * The exact requested path is tried first so it wins when both slash forms exist.
	 *
	 * @param string $path Request path (e.g. /old-page/).
	 * @return array|null Redirect row or null.
	 */
	public static function get_match( $path ) {
		$candidates = self::origin_match_candidates( $path );

		$plain = self::get_plain_match( $candidates );
		if ( $plain ) {
			return $plain;
		}

		return self::get_regex_match( $candidates );
	}

	/**
	 * Paths to try when matching a request (with and without trailing slash).
	 *
	 * The original path is first so an exact stored origin still wins when both
	 * slash variants exist as separate rules.
	 *
	 * @param string $path Request path.
	 * @return string[] Unique candidates.
	 */
	public static function origin_match_candidates( $path ) {
		$path = self::normalize_origin( $path );
		if ( '/' === $path ) {
			return array( '/' );
		}

		$unslashed = untrailingslashit( $path );
		if ( '' === $unslashed ) {
			return array( '/' );
		}
		$slashed = trailingslashit( $unslashed );

		$candidates = array( $path );
		foreach ( array( $unslashed, $slashed ) as $variant ) {
			if ( ! in_array( $variant, $candidates, true ) ) {
				$candidates[] = $variant;
			}
		}

		return $candidates;
	}

	/**
	 * First plain redirect whose origin is one of the path candidates.
	 *
	 * @param string[] $candidates Paths to match, preferred order.
	 * @return array|null
	 */
	private static function get_plain_match( $candidates ) {
		global $wpdb;

		if ( empty( $candidates ) ) {
			return null;
		}

		$table        = self::get_table_name();
		$placeholders = implode( ', ', array_fill( 0, count( $candidates ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix + constant; IN list size matches $candidates.
		$sql  = $wpdb->prepare( "SELECT * FROM $table WHERE format = 'plain' AND origin IN ($placeholders)", ...$candidates );
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( empty( $rows ) ) {
			return null;
		}

		$by_origin = array();
		foreach ( $rows as $row ) {
			$by_origin[ $row['origin'] ] = $row;
		}
		foreach ( $candidates as $candidate ) {
			if ( isset( $by_origin[ $candidate ] ) ) {
				return $by_origin[ $candidate ];
			}
		}

		return null;
	}

	/**
	 * First regex redirect that matches a path candidate.
	 *
	 * Candidates are tried in order (exact request path first) so a later rule
	 * that matches the exact path wins over an earlier rule that only matches
	 * the trailing-slash variant.
	 *
	 * @param string[] $candidates Paths to match, preferred order.
	 * @return array|null
	 */
	private static function get_regex_match( $candidates ) {
		$regex_redirects = self::get_regex_redirect_rows();
		if ( empty( $regex_redirects ) ) {
			return null;
		}

		foreach ( $candidates as $candidate ) {
			foreach ( $regex_redirects as $redirect ) {
				$hit = self::match_regex_redirect( $redirect, $candidate );
				if ( $hit ) {
					return $hit;
				}
			}
		}

		return null;
	}

	/**
	 * Apply one regex redirect to a candidate path.
	 *
	 * @param array  $redirect Redirect row.
	 * @param string $path     Candidate request path.
	 * @return array|null Row (target may be rewritten) or null.
	 */
	private static function match_regex_redirect( $redirect, $path ) {
		$wrapped = self::regex_pcre( $redirect['origin'] );
		$matched = preg_match( $wrapped, $path );
		if ( false === $matched || PREG_NO_ERROR !== preg_last_error() ) {
			return null;
		}
		if ( 1 !== (int) $matched ) {
			return null;
		}
		$target = preg_replace( $wrapped, $redirect['target'], $path );
		if ( null === $target || false === $target || PREG_NO_ERROR !== preg_last_error() ) {
			return null;
		}
		if ( $target !== $path ) {
			$redirect['target'] = $target;
		}
		return $redirect;
	}

	/**
	 * Sanitize a redirect origin. Plain origins are text; regex origins keep
	 * characters like < > that sanitize_text_field() would mangle.
	 *
	 * @param string $raw    Unslashed input.
	 * @param string $format 'plain' or 'regex'.
	 * @return string
	 */
	public static function sanitize_origin( $raw, $format = 'plain' ) {
		$raw = is_string( $raw ) ? $raw : '';
		if ( 'regex' !== $format ) {
			return sanitize_text_field( $raw );
		}
		$raw   = wp_check_invalid_utf8( $raw );
		$clean = preg_replace( "/[\x00-\x1F\x7F]/", '', $raw );
		return trim( is_string( $clean ) ? $clean : '' );
	}

	/**
	 * Normalize origin path.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	public static function normalize_origin( $path ) {
		static $home = null;
		if ( null === $home ) {
			$home = home_url( '/' );
		}
		$path = trim( $path );
		$path = str_replace( $home, '/', $path );
		if ( 0 !== strpos( $path, '/' ) ) {
			$path = '/' . $path;
		}
		return $path;
	}

	/**
	 * Maybe perform redirect.
	 */
	public function maybe_redirect() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( empty( $path ) ) {
			$path = '/';
		}

		$redirect = self::get_match( $path );
		if ( ! $redirect ) {
			return;
		}

		$type = self::sanitize_redirect_type( (int) $redirect['type'] );

		if ( in_array( $type, array( 410, 451 ), true ) ) {
			status_header( $type );
			nocache_headers();
			exit;
		}

		$target = self::resolve_target_url( $redirect['target'] );
		if ( '' === $target ) {
			return;
		}

		wp_redirect( $target, $type, 'Mantis SEO' );
		exit;
	}
}
