<?php
/**
 * Standalone test for the seat rule in mu-plugins/pipepay-update-paid-conversion.php.
 * Run: php tests/paid-conversion-test.php   (exit 0 = pass)
 * No WordPress required: the two WP functions the file touches at load are stubbed.
 */
define( 'ABSPATH', __DIR__ . '/' );
function add_filter( ...$args ) {}
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function apply_filters( $tag, $value ) { return $value; }
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

// ── v1.1.0 ──────────────────────────────────────────────────────────────────

// Revoke list: the database matches keys case-insensitively, so the list must too.
$revoked = 'pipepay_paid_conversion_key_is_revoked';
$list    = [ 'ck_abc123def456abc123def456' => [ 'reason' => 'chargeback' ] ];
check( $revoked( 'ck_abc123def456abc123def456', $list ) === true, 'exact key is revoked' );
check( $revoked( 'CK_ABC123DEF456ABC123DEF456', $list ) === true, 'upper-cased key is still revoked' );
check( $revoked( 'ck_Abc123def456abc123def456', $list ) === true, 'one flipped letter is still revoked' );
check( $revoked( 'ck_other0000000000000000000', $list ) === false, 'a different key is not revoked' );
check( $revoked( 'ck_abc123def456abc123def456', 'not-an-array' ) === false, 'corrupt list revokes nobody' );
check( $revoked( 'ck_abc', [ 'CK_ABC' => [] ] ) === true, 'list entry stored in another case still matches' );

// Which requests are "converted" stores: bound to a product the key no longer
// has, while the key owns a different, paid product.
$conv = 'pipepay_paid_conversion_is_converted';
$paid = [ 34, 35, 36, 526, 527, 528 ];
check( $conv( 38, 34, [ 34 ], $paid ) === true, 'trial-bound store, key now owns Single Site' );
check( $conv( 527, 35, [ 35 ], $paid ) === true, 'monthly-bound store, key now owns annual 5 Sites' );
check( $conv( 34, 34, [ 34 ], $paid ) === false, 'same product: a deleted activation is not a conversion' );
check( $conv( 38, 34, [ 34, 38 ], $paid ) === false, 'trial still active: the standard path owns it' );
check( $conv( 38, 38, [ 38 ], $paid ) === false, 'a trial resource is never served here' );
check( $conv( 38, 999, [ 999 ], $paid ) === false, 'a non Pipe Pay product is never served' );
check( $conv( 0, 34, [ 34 ], $paid ) === false, 'missing product id is refused' );

// Rate limit: a fixed window that does not slide forward on every hit.
$step = 'pipepay_paid_conversion_rate_step';
$r = $step( false, 1000, 3, 3600 );
check( $r['allowed'] === true && $r['state'] === [ 'start' => 1000, 'count' => 1 ], 'first hit opens a window' );
$r = $step( $r['state'], 1010, 3, 3600 ); $r = $step( $r['state'], 1020, 3, 3600 );
check( $r['allowed'] === true && $r['state']['count'] === 3, 'third hit still allowed' );
$r = $step( $r['state'], 1030, 3, 3600 );
check( $r['allowed'] === false && $r['state']['start'] === 1000, 'fourth hit refused, window start unchanged' );
$r2 = $step( $r['state'], 4599, 3, 3600 );
check( $r2['allowed'] === false, 'still refused one second before the window ends, despite steady hits' );
$r3 = $step( $r2['state'], 4600, 3, 3600 );
check( $r3['allowed'] === true && $r3['state'] === [ 'start' => 4600, 'count' => 1 ], 'window resets exactly one window after it opened' );
check( $step( 'garbage', 50, 3, 3600 )['allowed'] === true, 'corrupt state starts a fresh window' );
check( $step( 7, 50, 3, 3600 )['state'] === [ 'start' => 50, 'count' => 1 ], 'legacy integer counter starts a fresh window' );

echo $failures ? "{$failures} failure(s)\n" : "paid-conversion: all checks passed\n";
exit( $failures ? 1 : 0 );
