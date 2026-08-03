<?php
/**
 * Pure cadence logic for the license renewal / trial email schedule.
 *
 * WP-free on purpose so `tests/cadence-logic-test.php` can exercise it with
 * plain `php`. Lives in pipepay-lib/ (NOT depth 0 of mu-plugins/) and is
 * required by pipepay-license-renewals.php.
 *
 * @package pipe-pay-site
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'PIPEPAY_CADENCE_TEST' ) ) {
    exit;
}

const PIPEPAY_CADENCE_TOLERANCE_DAYS = 1;

/**
 * The full cadence schedule, chronological within each kind. Order matters:
 * the engine sends at most one email per license per run, and the FIRST
 * matching unsent stage wins, so after cron downtime the earliest overdue
 * email goes out first and the rest follow on subsequent days.
 *
 * Keys per stage:
 *   days               int    days_to_expiry the stage targets (negative = after expiry)
 *   email              string WC email class id registered in pipepay-lib/email-classes.php
 *   kind               'trial' | 'paid'
 *   stamp              string idempotency postmeta key on the originating order
 *   segmented          bool   (optional) engine must pass the activation flag;
 *                             the email class picks its template by segment
 *   skip_if_converted  bool   (optional) skip when the user already holds an
 *                             active paid license (win-back stages)
 */
const PIPEPAY_CADENCE_STAGES = [
    'trial_t-6' => [ 'days' =>  6, 'email' => 'WC_Email_PipePay_Trial_Day1',          'kind' => 'trial', 'stamp' => '_pipepay_renewal_stamp_trial_t-6', 'segmented' => true ],
    'trial_t-4' => [ 'days' =>  4, 'email' => 'WC_Email_PipePay_Trial_Day3',          'kind' => 'trial', 'stamp' => '_pipepay_renewal_stamp_trial_t-4', 'segmented' => true ],
    'trial_t-2' => [ 'days' =>  2, 'email' => 'WC_Email_PipePay_Trial_Ending_Soon',   'kind' => 'trial', 'stamp' => '_pipepay_renewal_stamp_trial_t-2' ],
    'trial_t+0' => [ 'days' =>  0, 'email' => 'WC_Email_PipePay_Trial_Ended',         'kind' => 'trial', 'stamp' => '_pipepay_renewal_stamp_trial_t+0' ],
    'trial_t+3' => [ 'days' => -3, 'email' => 'WC_Email_PipePay_Trial_Winback',       'kind' => 'trial', 'stamp' => '_pipepay_renewal_stamp_trial_t+3', 'skip_if_converted' => true ],
    'trial_t+7' => [ 'days' => -7, 'email' => 'WC_Email_PipePay_Trial_Winback_Final', 'kind' => 'trial', 'stamp' => '_pipepay_renewal_stamp_trial_t+7', 'skip_if_converted' => true ],
    'paid_t-30' => [ 'days' => 30, 'email' => 'WC_Email_PipePay_Renewal_30',          'kind' => 'paid',  'stamp' => '_pipepay_renewal_stamp_paid_t-30' ],
    'paid_t-7'  => [ 'days' =>  7, 'email' => 'WC_Email_PipePay_Renewal_7',           'kind' => 'paid',  'stamp' => '_pipepay_renewal_stamp_paid_t-7' ],
    'paid_t-0'  => [ 'days' =>  0, 'email' => 'WC_Email_PipePay_Renewal_Expiry',      'kind' => 'paid',  'stamp' => '_pipepay_renewal_stamp_paid_t-0' ],
    'paid_t+7'  => [ 'days' => -7, 'email' => 'WC_Email_PipePay_Renewal_Grace',       'kind' => 'paid',  'stamp' => '_pipepay_renewal_stamp_paid_t+7' ],
    'paid_t+30' => [ 'days' =>-30, 'email' => 'WC_Email_PipePay_Renewal_Final',       'kind' => 'paid',  'stamp' => '_pipepay_renewal_stamp_paid_t+30' ],
];

/**
 * Does this stage fire today? Late-direction window ONLY: the scheduled day,
 * or up to PIPEPAY_CADENCE_TOLERANCE_DAYS after it (so a missed cron run
 * doesn't drop the email). Never early - the previous abs() matching made
 * every stage fire on its first tolerance day, one day ahead of schedule,
 * and made adjacent stages overlap.
 */
function pipepay_cadence_stage_matches( array $stage, int $days_to_expiry, string $kind ): bool {
    if ( $stage['kind'] !== $kind ) {
        return false;
    }
    return $days_to_expiry <= $stage['days']
        && $days_to_expiry >= $stage['days'] - PIPEPAY_CADENCE_TOLERANCE_DAYS;
}

/**
 * Customer-facing name + live prices for a paid tier product.
 * Single source of truth for prices quoted inside cadence emails.
 */
function pipepay_cadence_tier_display( int $product_id ): ?array {
    $tiers = [
        34 => [ 'name' => 'Single Site',     'annual' => '$297', 'monthly' => '$35' ],
        35 => [ 'name' => '5 Sites',         'annual' => '$497', 'monthly' => '$65' ],
        36 => [ 'name' => 'Unlimited Sites', 'annual' => '$997', 'monthly' => '$129' ],
    ];
    return $tiers[ $product_id ] ?? null;
}
