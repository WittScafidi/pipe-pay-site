<?php
/**
 * Standalone test for mu-plugins/pipepay-lib/cadence-logic.php.
 * Run: php tests/cadence-logic-test.php   (exit 0 = pass)
 * No WordPress required; PIPEPAY_CADENCE_TEST bypasses the ABSPATH guard.
 */
define( 'PIPEPAY_CADENCE_TEST', true );
require __DIR__ . '/../mu-plugins/pipepay-lib/cadence-logic.php';

$failures = 0;
function check( bool $cond, string $label ): void {
    global $failures;
    if ( ! $cond ) { $failures++; echo "FAIL: {$label}\n"; }
}

// Which trial stages match each days_to_expiry value. Windows must be
// disjoint (one stage per day) and never fire EARLY (a stage may fire on
// its scheduled day or up to 1 day after, to survive a missed cron run).
$expected_trial = [
    7  => [],
    6  => [ 'trial_t-6' ],
    5  => [ 'trial_t-6' ],   // catch-up only: t-4 must NOT match at 5
    4  => [ 'trial_t-4' ],
    3  => [ 'trial_t-4' ],
    2  => [ 'trial_t-2' ],
    1  => [ 'trial_t-2' ],
    0  => [ 'trial_t+0' ],
    -1 => [ 'trial_t+0' ],
    -2 => [],
    -3 => [ 'trial_t+3' ],
    -4 => [ 'trial_t+3' ],
    -5 => [],
    -6 => [],
    -7 => [ 'trial_t+7' ],
    -8 => [ 'trial_t+7' ],
    -9 => [],
];
foreach ( $expected_trial as $days => $expected_keys ) {
    $matched = [];
    foreach ( PIPEPAY_CADENCE_STAGES as $key => $stage ) {
        if ( pipepay_cadence_stage_matches( $stage, $days, 'trial' ) ) {
            $matched[] = $key;
        }
    }
    check( $matched === $expected_keys, "trial days={$days}: got [" . implode( ',', $matched ) . '] expected [' . implode( ',', $expected_keys ) . ']' );
}

// Paid spot checks: same late-only window semantics.
foreach ( [ 31 => false, 30 => true, 29 => true, 28 => false ] as $days => $should ) {
    check( pipepay_cadence_stage_matches( PIPEPAY_CADENCE_STAGES['paid_t-30'], $days, 'paid' ) === $should, "paid_t-30 days={$days}" );
}
check( ! pipepay_cadence_stage_matches( PIPEPAY_CADENCE_STAGES['paid_t-30'], 30, 'trial' ), 'kind mismatch never matches' );

// New stages carry the right flags + email class names.
check( ! empty( PIPEPAY_CADENCE_STAGES['trial_t-6']['segmented'] ), 'trial_t-6 segmented' );
check( ! empty( PIPEPAY_CADENCE_STAGES['trial_t-4']['segmented'] ), 'trial_t-4 segmented' );
check( ! empty( PIPEPAY_CADENCE_STAGES['trial_t+3']['skip_if_converted'] ), 'trial_t+3 skip_if_converted' );
check( ! empty( PIPEPAY_CADENCE_STAGES['trial_t+7']['skip_if_converted'] ), 'trial_t+7 skip_if_converted' );
check( 'WC_Email_PipePay_Trial_Day1' === PIPEPAY_CADENCE_STAGES['trial_t-6']['email'], 'trial_t-6 email class' );
check( 'WC_Email_PipePay_Trial_Day3' === PIPEPAY_CADENCE_STAGES['trial_t-4']['email'], 'trial_t-4 email class' );
check( 'WC_Email_PipePay_Trial_Winback' === PIPEPAY_CADENCE_STAGES['trial_t+3']['email'], 'trial_t+3 email class' );
check( 'WC_Email_PipePay_Trial_Winback_Final' === PIPEPAY_CADENCE_STAGES['trial_t+7']['email'], 'trial_t+7 email class' );

// Stage table order must be chronological (send-priority: earliest unsent
// stage wins when the cron catches up after downtime).
$trial_days_in_order = [];
foreach ( PIPEPAY_CADENCE_STAGES as $stage ) {
    if ( 'trial' === $stage['kind'] ) { $trial_days_in_order[] = $stage['days']; }
}
$sorted = $trial_days_in_order; rsort( $sorted );
check( $trial_days_in_order === $sorted, 'trial stages listed chronologically (descending days)' );

// Tier display map: live prices, both lanes.
check( [ 'name' => 'Single Site', 'annual' => '$297', 'monthly' => '$35' ] === pipepay_cadence_tier_display( 34 ), 'tier 34' );
check( [ 'name' => '5 Sites', 'annual' => '$497', 'monthly' => '$65' ] === pipepay_cadence_tier_display( 35 ), 'tier 35' );
check( [ 'name' => 'Unlimited Sites', 'annual' => '$997', 'monthly' => '$129' ] === pipepay_cadence_tier_display( 36 ), 'tier 36' );
check( null === pipepay_cadence_tier_display( 38 ), 'trial product has no tier display' );
check( null === pipepay_cadence_tier_display( 0 ), 'zero pid' );

if ( $failures ) { echo "{$failures} failure(s)\n"; exit( 1 ); }
echo "OK\n";
exit( 0 );
