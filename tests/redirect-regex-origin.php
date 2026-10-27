<?php
/**
 * Regex redirect origins keep lookbehind characters and still rewrite.
 *
 * Run: php tests/redirect-regex-origin.php
 *
 * @package Mantis_SEO
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

/**
 * Stand-in for the WordPress UTF-8 check. The real function is not loaded here.
 *
 * @param string $text Text.
 * @return string
 */
function wp_check_invalid_utf8( $text ) {
	return (string) $text;
}

/**
 * Stand-in so the plain-origin branch is observable without WordPress.
 *
 * @param string $text Text.
 * @return string
 */
function sanitize_text_field( $text ) {
	return 'PLAIN:' . $text;
}

require dirname( __DIR__ ) . '/includes/class-redirects.php';

/**
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $message  Label.
 */
function mantis_assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL {$message}\n expected: " . var_export( $expected, true ) . "\n actual:   " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "ok {$message}\n" );
}

$raw    = "/(?<=^/)old/(\d+)/?$\n";
$origin = Mantis_SEO_Redirects::sanitize_origin( $raw, 'regex' );
mantis_assert_same( '/(?<=^/)old/(\d+)/?$', $origin, 'regex origin keeps a lookbehind and drops the line break' );
mantis_assert_same( true, Mantis_SEO_Redirects::is_valid_regex_pattern( $origin ), 'lookbehind pattern compiles' );

$mangled = str_replace( '<', '&lt;', $origin );
mantis_assert_same( false, Mantis_SEO_Redirects::is_valid_regex_pattern( $mangled ), 'entity-encoded lookbehind is rejected' );

$plain = Mantis_SEO_Redirects::sanitize_origin( '  /old-page  ', 'plain' );
mantis_assert_same( 'PLAIN:  /old-page  ', $plain, 'plain origin still uses sanitize_text_field' );

$method = new ReflectionMethod( 'Mantis_SEO_Redirects', 'match_regex_redirect' );
$method->setAccessible( true );
$hit = $method->invoke(
	null,
	array(
		'origin' => $origin,
		'target' => '/new/$1/',
		'type'   => 301,
		'format' => 'regex',
	),
	'/old/42/'
);

mantis_assert_same( '/new/42/', is_array( $hit ) ? $hit['target'] : null, 'request /old/42/ rewrites to /new/42/' );
mantis_assert_same( 301, is_array( $hit ) ? (int) $hit['type'] : 0, 'matching rule stays a 301' );

fwrite( STDOUT, "all passed\n" );
