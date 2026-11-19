<?php
/**
 * Standalone test for the seat rule in mu-plugins/pipepay-update-paid-conversion.php.
 * Run: php tests/paid-conversion-test.php   (exit 0 = pass)
 * No WordPress required: the two WP functions the file touches at load are stubbed.
 */
define( 'ABSPATH', __DIR__ . '/' );
function add_filter( ...$args ) {}
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
require __DIR__ . '/../mu-plugins/pipepay-update-paid-conversion.php';

$failures = 0;
function check( bool $cond, string $label ): void {
	global $failures;
	if ( ! $cond ) { $failures++; echo "FAIL: {$label}\n"; }
}
$allowed = 'pipepay_paid_conversion_allowed_instances';

// Single-site license, no real activation, one store revalidating: it gets the seat.
check( $allowed( [ 'aaa' ], [], 1 ) === [ 'aaa' ], 'single seat goes to the only instance' );
// A second site on the same single-site key does NOT get a seat; the oldest keeps it.
check( $allowed( [ 'aaa', 'bbb' ], [], 1 ) === [ 'aaa' ], 'oldest instance keeps the single seat' );
check( ! in_array( 'bbb', $allowed( [ 'aaa', 'bbb' ], [], 1 ), true ), 'second instance is refused on a 1-seat license' );
// A real activation already uses the only seat: nothing is served here.
check( $allowed( [ 'aaa' ], [ 'zzz' ], 1 ) === [], 'no free seat when a real activation holds it' );
// An instance that already has a real activation is not double counted.
check( $allowed( [ 'zzz', 'aaa' ], [ 'zzz' ], 2 ) === [ 'aaa' ], 'activated instance does not consume a second seat' );
// 5-site license: up to five distinct instances, in first-seen order.
check( $allowed( [ 'a', 'b', 'c', 'd', 'e', 'f' ], [], 5 ) === [ 'a', 'b', 'c', 'd', 'e' ], 'five seats, sixth refused' );
// Duplicates and blanks in the log are ignored.
check( $allowed( [ 'a', '', 'a', 'b' ], [], 5 ) === [ 'a', 'b' ], 'duplicates and blanks ignored' );
// Zero or negative cap never serves.
check( $allowed( [ 'a' ], [], 0 ) === [], 'zero cap serves nobody' );

// Host normalisation: bare host, full URL, case, trailing slash and path all compare equal.
$host = 'pipepay_paid_conversion_host';
check( $host( 'https://Example.com/' ) === 'example.com', 'url to host' );
check( $host( 'example.com' ) === 'example.com', 'bare host' );
check( $host( 'https://example.com/shop' ) === $host( 'EXAMPLE.com' ), 'path and case ignored' );
check( $host( 'https://staging.example.com' ) !== $host( 'example.com' ), 'subdomain is a different host' );
check( $host( '' ) === '', 'empty stays empty' );

echo $failures ? "{$failures} failure(s)\n" : "paid-conversion: all checks passed\n";
exit( $failures ? 1 : 0 );
