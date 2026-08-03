# Trial-Conversion Email Flow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the pipepay.app trial email cadence from 3 blind touches to 7 activation-aware touches (spec: `~/Desktop/Pipe Pay/marketing/trial-conversion-email-flow.md`, owner-approved 2026-08-02), plus a one-time 7-day trial-extension valve.

**Architecture:** Everything rides the existing daily cadence cron in `mu-plugins/pipepay-license-renewals.php` (Action Scheduler hook `pipepay_license_check_renewals`, per-order idempotency stamps, WC-registered email classes rendering templates from `Email Templates/`). New work: a pure-logic lib file (testable without WordPress), 2 segmented + 2 plain email classes, 6 templates, upgrades to 3 existing templates, an `pp_action=extend_trial` branch on the `/renew/` route, and a stage-matching fix that stops emails firing a day early.

**Tech Stack:** PHP 8.3, WordPress mu-plugins, WooCommerce `WC_Email` subclasses, MariaDB via `$wpdb`. No test harness exists in the site repo; pure logic gets a standalone `php` test script, DB-bound logic gets the server-side wp-cli verification runbook (Task 7).

**Working directory:** `/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site` (git repo; the folder `Email Templates` has a space, always quote it).

## Global Constraints

- Prices: annual **$297 / $497 / $997** (products 34/35/36), monthly **$35 / $65 / $129** (products 526/527/528, Stripe card lane, cancel anytime). Trial = product 38, 7 days.
- Copy rules (binding, from site CLAUDE.md + owner decisions): never the phrase "open source"; no em-dashes anywhere in email copy (use commas/periods; sweep existing `&mdash;`/`—` when touching a template); never "reply to this email" (point at `support@pipepay.app`); soft high-risk voice; honest "paying ends, service ends" framing; **neutral Pipe Pay voice, no founder name in signoffs** (owner decision 2026-08-02); **no discounts anywhere** (monthly is the only "cheaper way in").
- Trial post-expiry behavior (already live, copy must match): updates/support pause at expiry; gateway keeps accepting orders for a 30-day grace period; then Pipe Pay stops appearing at NEW checkouts until a paid tier is bought.
- Plain-text template branches must never be wrapped in `esc_html()` (WC mangles entities); sanitize with `sanitize_text_field()` / `esc_url_raw()` at the top of the plain branch, matching existing templates.
- `pipepay-lib/` is a subdirectory deliberately: WP auto-includes only depth-0 mu-plugin files, and files defining `class X extends WC_Email` must not load before WC. Never move lib files to depth 0.
- Deploy is file-sync via the CLAUDE.md cheatsheet commands (Task 7); the repo is source-of-truth, not a deploy mechanism.
- Every commit message ends with `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`.

---

### Task 1: Pure cadence logic lib + standalone test

Move the stage table into a WP-free lib file, fix the early-fire window bug, add the tier display map. The bug: current matching is `abs(days_to_expiry - stage_days) <= 1`, so adjacent windows abut and each stage fires on its first *tolerance* day (T-2 fires at 3 days out; with the new day-1/day-3 stages everything would cascade a day early). Fix: fire on the scheduled day or up to 1 day LATE (missed-cron catch-up), never early.

**Files:**
- Create: `mu-plugins/pipepay-lib/cadence-logic.php`
- Create: `tests/cadence-logic-test.php`
- Modify: `mu-plugins/pipepay-license-renewals.php` (remove `PIPEPAY_CADENCE_STAGES` + `PIPEPAY_CADENCE_TOLERANCE_DAYS` consts, add `require_once`)

**Interfaces:**
- Produces: const `PIPEPAY_CADENCE_STAGES` (array; stage keys `trial_t-6`, `trial_t-4`, `trial_t-2`, `trial_t+0`, `trial_t+3`, `trial_t+7`, `paid_t-30`, `paid_t-7`, `paid_t-0`, `paid_t+7`, `paid_t+30`; each entry has `days`, `email`, `kind`, `stamp`, optional `segmented`, optional `skip_if_converted`); const `PIPEPAY_CADENCE_TOLERANCE_DAYS = 1`; `pipepay_cadence_stage_matches( array $stage, int $days_to_expiry, string $kind ): bool`; `pipepay_cadence_tier_display( int $product_id ): ?array` returning `['name' => string, 'annual' => string, 'monthly' => string]` for 34/35/36, null otherwise.
- Consumed by: Task 2 (engine), Task 6 (extension stamp cleanup uses `PIPEPAY_CADENCE_STAGES`).

- [ ] **Step 1: Write the failing test**

Create `tests/cadence-logic-test.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd "/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site" && php tests/cadence-logic-test.php`
Expected: FAIL with "Failed opening required .../cadence-logic.php"

- [ ] **Step 3: Create the lib**

Create `mu-plugins/pipepay-lib/cadence-logic.php`:

```php
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
```

- [ ] **Step 4: Wire the lib into the mu-plugin and delete the old consts**

In `mu-plugins/pipepay-license-renewals.php`:

(a) Directly under the existing top consts (after the `PIPEPAY_RENEWAL_PAGE_SLUG` line, currently line 48), add:

```php
require_once __DIR__ . '/pipepay-lib/cadence-logic.php';
```

(b) Delete the entire old `PIPEPAY_CADENCE_STAGES` const (currently lines 437–446) and the `const PIPEPAY_CADENCE_TOLERANCE_DAYS = 1;` line (currently line 448). Keep `PIPEPAY_CADENCE_HOOK` and `PIPEPAY_CADENCE_FAIL_CAP` where they are.

- [ ] **Step 5: Run test + lint to verify green**

Run: `cd "/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site" && php tests/cadence-logic-test.php && php -l mu-plugins/pipepay-lib/cadence-logic.php && php -l mu-plugins/pipepay-license-renewals.php`
Expected: `OK` then two `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
cd "/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site" && git add mu-plugins/pipepay-lib/cadence-logic.php mu-plugins/pipepay-license-renewals.php tests/cadence-logic-test.php && git commit -m "feat: extract cadence schedule to pure lib, add day1/day3/winback stages, fix early-fire window

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: Cadence engine — activation segmentation, conversion skip, richer license_data

**Files:**
- Modify: `mu-plugins/pipepay-license-renewals.php` (cadence section, currently lines ~466–610, plus the `Version:` header)

**Interfaces:**
- Consumes: `pipepay_cadence_stage_matches()`, `pipepay_cadence_tier_display()`, `PIPEPAY_CADENCE_STAGES` (Task 1); existing `pipepay_renewal_get_intended_tier( int $order_id ): ?int`, `pipepay_renewal_hmac_sign( string $key, int $expires ): string`.
- Produces (for Tasks 3–6):
  - `pipepay_cadence_trial_is_activated( int $api_resource_id ): bool`
  - `pipepay_cadence_user_has_paid_license( int $user_id ): bool`
  - `pipepay_cadence_maybe_send( array $lic, string $stage_key, array $stage, ?bool $is_activated = null ): bool` (was void; true only on an actual successful send)
  - `pipepay_cadence_build_license_data( array $lic, WC_Order $order, ?bool $is_activated = null ): array` — return array gains keys `activated` (bool), `intent_tier_name`, `intent_tier_price`, `intent_monthly_price`, `trial_extension_url` (all strings, `''` when n/a).

- [ ] **Step 1: Bump the mu-plugin version header**

In the file docblock, change `* Version:     1.0.0` to `* Version:     1.1.0`.

- [ ] **Step 2: Add the two DB predicates**

Insert directly above `function pipepay_license_cadence_run()`:

```php
/**
 * Has this trial license ever been activated on a store?
 * Primary signal: a seat row in Kestrel's activation table. Fallback: any
 * daily revalidation ping (proves a live install even if the seat row was
 * later cleaned up).
 */
function pipepay_cadence_trial_is_activated( int $api_resource_id ): bool {
    global $wpdb;
    if ( $api_resource_id <= 0 ) {
        return false;
    }
    $seats = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wc_am_api_activation WHERE api_resource_id = %d",
        $api_resource_id
    ) );
    if ( $seats > 0 ) {
        return true;
    }
    $log_table = $wpdb->prefix . 'pipepay_revalidate_log';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $log_table ) ) !== $log_table ) {
        return false;
    }
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$log_table} WHERE api_resource_id = %d",
        $api_resource_id
    ) ) > 0;
}

/**
 * Does this user already hold a live paid entitlement (annual 34/35/36 or
 * monthly 526/527/528)? Used to silence win-back emails after conversion.
 */
function pipepay_cadence_user_has_paid_license( int $user_id ): bool {
    global $wpdb;
    if ( $user_id <= 0 ) {
        return false;
    }
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wc_am_api_resource
         WHERE user_id = %d AND product_id IN (34, 35, 36, 526, 527, 528)
           AND active = 1 AND access_expires > %d",
        $user_id,
        time()
    ) ) > 0;
}
```

- [ ] **Step 3: Rewrite the per-license stage loop in `pipepay_license_cadence_run()`**

Replace the current block

```php
        $expires       = (int) $lic['access_expires'];
        $days_to_expiry = (int) floor( ( $expires - $now ) / DAY_IN_SECONDS );
        $is_trial      = (int) $lic['product_id'] === PIPEPAY_RENEWAL_TRIAL_PRODUCT_ID;
        $kind          = $is_trial ? 'trial' : 'paid';

        foreach ( PIPEPAY_CADENCE_STAGES as $stage_key => $stage ) {
            if ( $stage['kind'] !== $kind ) {
                continue;
            }
            // Match with +/- 1 day tolerance so a missed cron run doesn't drop the email.
            if ( abs( $days_to_expiry - $stage['days'] ) > PIPEPAY_CADENCE_TOLERANCE_DAYS ) {
                continue;
            }
            pipepay_cadence_maybe_send( $lic, $stage_key, $stage );
        }
```

with

```php
        $expires        = (int) $lic['access_expires'];
        $days_to_expiry = (int) floor( ( $expires - $now ) / DAY_IN_SECONDS );
        $is_trial       = (int) $lic['product_id'] === PIPEPAY_RENEWAL_TRIAL_PRODUCT_ID;
        $kind           = $is_trial ? 'trial' : 'paid';

        // Segment lookup once per trial license, at send time (fresh: a user
        // who activated an hour ago gets the activated branch today).
        $is_activated = $is_trial
            ? pipepay_cadence_trial_is_activated( (int) $lic['api_resource_id'] )
            : null;

        foreach ( PIPEPAY_CADENCE_STAGES as $stage_key => $stage ) {
            if ( ! pipepay_cadence_stage_matches( $stage, $days_to_expiry, $kind ) ) {
                continue;
            }
            if ( ! empty( $stage['skip_if_converted'] ) && pipepay_cadence_user_has_paid_license( (int) $lic['user_id'] ) ) {
                continue;
            }
            // At most ONE email per license per run: after cron downtime the
            // earliest overdue stage fires today, later ones on later days
            // (stage table is chronological). Already-stamped stages return
            // false and don't consume the slot.
            if ( pipepay_cadence_maybe_send( $lic, $stage_key, $stage, $is_activated ) ) {
                break;
            }
        }
```

- [ ] **Step 4: Thread the activation flag through `pipepay_cadence_maybe_send()`**

Change its signature and the two lines that use it:

```php
function pipepay_cadence_maybe_send( array $lic, string $stage_key, array $stage, ?bool $is_activated = null ): bool {
```

Every existing bare `return;` inside becomes `return false;`. The `$license_data` build line becomes:

```php
    $license_data = pipepay_cadence_build_license_data( $lic, $order, $is_activated );
    if ( ! $license_data ) {
        return false;
    }
```

At the end, the success branch returns `true` after stamping; the failure branch returns `false` after bumping the fail counter:

```php
    if ( $sent ) {
        update_post_meta( $order_id, $stamp_key, time() );
        delete_post_meta( $order_id, $fail_key );
        if ( function_exists( 'pipepay_log' ) ) {
            pipepay_log( 'info', 'Cadence email sent', [ 'stage' => $stage_key, 'order_id' => $order_id, 'recipient_domain' => substr( strrchr( $license_data['recipient'], '@' ), 1 ) ] );
        }
        return true;
    }

    $fails = (int) get_post_meta( $order_id, $fail_key, true ) + 1;
    update_post_meta( $order_id, $fail_key, $fails );
    if ( function_exists( 'pipepay_log' ) ) {
        pipepay_log( 'warning', 'Cadence email send failed', [ 'stage' => $stage_key, 'order_id' => $order_id, 'fail_count' => $fails ] );
    }
    return false;
```

- [ ] **Step 5: Extend `pipepay_cadence_build_license_data()`**

Change the signature to `function pipepay_cadence_build_license_data( array $lic, WC_Order $order, ?bool $is_activated = null ): array {` and, before the `return`, add:

```php
    $is_trial = PIPEPAY_RENEWAL_TRIAL_PRODUCT_ID === (int) $lic['product_id'];

    // Intended-tier display (trial signups that clicked a specific pricing
    // card carry ?intent= through to the order item meta).
    $intent_tier_name = $intent_tier_price = $intent_monthly_price = '';
    $intent_pid       = pipepay_renewal_get_intended_tier( $order->get_id() );
    if ( $intent_pid ) {
        $tier = pipepay_cadence_tier_display( $intent_pid );
        if ( $tier ) {
            $intent_tier_name     = $tier['name'];
            $intent_tier_price    = $tier['annual'];
            $intent_monthly_price = $tier['monthly'];
        }
    }

    // One-time trial extension link: only offered to trials that were never
    // activated and never extended. Signed with the CURRENT access_expires,
    // so the link self-invalidates the moment the extension changes it.
    $trial_extension_url = '';
    if ( $is_trial
        && false === $is_activated
        && ! get_post_meta( $order->get_id(), '_pipepay_trial_extended', true ) ) {
        $trial_extension_url = add_query_arg(
            [
                'key'       => rawurlencode( $license_key ),
                'token'     => pipepay_renewal_hmac_sign( $license_key, $expires ),
                'pp_action' => 'extend_trial',
            ],
            home_url( '/' . PIPEPAY_RENEWAL_PAGE_SLUG . '/' )
        );
    }
```

and extend the returned array:

```php
    return [
        'license_key'          => $license_key,
        'order_id'             => $order->get_id(),
        'recipient'            => $order->get_billing_email(),
        'first_name'           => $order->get_billing_first_name(),
        'tier_name'            => $lic['product_title'] ?: 'Pipe Pay',
        'expires_at'           => $expires,
        'expires_label'        => $expires_label,
        'renewal_url'          => $renewal_url,
        'activated'            => (bool) $is_activated,
        'intent_tier_name'     => $intent_tier_name,
        'intent_tier_price'    => $intent_tier_price,
        'intent_monthly_price' => $intent_monthly_price,
        'trial_extension_url'  => $trial_extension_url,
    ];
```

- [ ] **Step 6: Lint + rerun the pure test**

Run: `cd "/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site" && php -l mu-plugins/pipepay-license-renewals.php && php tests/cadence-logic-test.php`
Expected: `No syntax errors detected` then `OK`

- [ ] **Step 7: Commit**

```bash
cd "/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site" && git add mu-plugins/pipepay-license-renewals.php && git commit -m "feat: activation-segmented cadence engine with conversion skip and one-send-per-run

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 3: Email classes — segmented base support + 4 new classes

**Files:**
- Modify: `mu-plugins/pipepay-lib/email-classes.php`

**Interfaces:**
- Consumes: `license_data` shape from Task 2 (`activated`, `intent_*`, `trial_extension_url`, `license_key` keys).
- Produces: classes `WC_Email_PipePay_Trial_Day1` (id `pipepay_trial_day1`), `WC_Email_PipePay_Trial_Day3` (id `pipepay_trial_day3`), `WC_Email_PipePay_Trial_Winback` (id `pipepay_trial_winback`), `WC_Email_PipePay_Trial_Winback_Final` (id `pipepay_trial_winback_final`); base-class template scope now includes `$intent_tier_name`, `$intent_tier_price`, `$intent_monthly_price`, `$trial_extension_url`, `$license_key` (Task 4 templates rely on these). NOTE: the registration list in `mu-plugins/pipepay-email-classes.php` must also gain the 4 new classes — check how the existing 7 cadence classes are registered there and mirror it exactly.

- [ ] **Step 1: Add segmented-template support to `PipePay_Cadence_Email_Base`**

Above `render_template()`, add:

```php
    /**
     * Segmented subclasses (day-1 / day-3) override this to pick a template
     * by activation state at render time.
     */
    protected function resolve_template_basename(): string {
        return $this->template_basename;
    }
```

In `render_template()`, change the path line to:

```php
        $path = PIPEPAY_EMAIL_TEMPLATES_DIR . $this->resolve_template_basename();
```

and extend the scope-variable block:

```php
        $first_name    = $this->license_data['first_name']    ?? '';
        $tier_name     = $this->license_data['tier_name']     ?? '';
        $expires_label = $this->license_data['expires_label'] ?? '';
        $renewal_url   = $this->license_data['renewal_url']   ?? '';

        $license_key          = $this->license_data['license_key']          ?? '';
        $intent_tier_name     = $this->license_data['intent_tier_name']     ?? '';
        $intent_tier_price    = $this->license_data['intent_tier_price']    ?? '';
        $intent_monthly_price = $this->license_data['intent_monthly_price'] ?? '';
        $trial_extension_url  = $this->license_data['trial_extension_url']  ?? '';
```

- [ ] **Step 2: Append the 4 new concrete classes**

At the end of the file (after `WC_Email_PipePay_Renewal_Final`), add:

```php
class WC_Email_PipePay_Trial_Day1 extends PipePay_Cadence_Email_Base {
    public function __construct() {
        $this->id          = 'pipepay_trial_day1';
        $this->title       = 'Pipe Pay - Trial day 1 (setup nudge)';
        $this->description = 'Sent the day after a trial starts. Not-activated users get an install walkthrough; activated users get a setup checklist.';
        $this->subject     = 'Get Pipe Pay running on your store';
        $this->heading     = 'Get set up';
        parent::__construct();
    }
    protected function resolve_template_basename(): string {
        return ! empty( $this->license_data['activated'] )
            ? 'trial-day1-activated.php'
            : 'trial-day1-not-activated.php';
    }
}

class WC_Email_PipePay_Trial_Day3 extends PipePay_Cadence_Email_Base {
    public function __construct() {
        $this->id          = 'pipepay_trial_day3';
        $this->title       = 'Pipe Pay - Trial day 3 (value / unblock)';
        $this->description = 'Sent 3 days into a trial. Not-activated users get a short unblock note; activated users get the value rundown and first pricing mention.';
        $this->subject     = 'Halfway through your Pipe Pay trial';
        $this->heading     = 'Halfway there';
        parent::__construct();
    }
    protected function resolve_template_basename(): string {
        return ! empty( $this->license_data['activated'] )
            ? 'trial-day3-activated.php'
            : 'trial-day3-not-activated.php';
    }
}

class WC_Email_PipePay_Trial_Winback extends PipePay_Cadence_Email_Base {
    protected $template_basename = 'trial-winback.php';
    public function __construct() {
        $this->id          = 'pipepay_trial_winback';
        $this->title       = 'Pipe Pay - Trial win-back (T+3)';
        $this->description = 'Sent 3 days after a trial ended, only if the user has not converted. Leads with the monthly lane. No discounts.';
        $this->subject     = 'A smaller way to keep Pipe Pay';
        $this->heading     = 'Monthly starts at $35';
        parent::__construct();
    }
}

class WC_Email_PipePay_Trial_Winback_Final extends PipePay_Cadence_Email_Base {
    protected $template_basename = 'trial-winback-final.php';
    public function __construct() {
        $this->id          = 'pipepay_trial_winback_final';
        $this->title       = 'Pipe Pay - Trial win-back final (T+7)';
        $this->description = 'Sent 7 days after a trial ended, only if the user has not converted. Last automated email for the trial.';
        $this->subject     = 'Your Pipe Pay license is saved in your account';
        $this->heading     = 'Whenever you are ready';
        parent::__construct();
    }
}
```

- [ ] **Step 3: Register the classes**

In `mu-plugins/pipepay-email-classes.php`, inside `pipepay_register_custom_emails()`, add these four lines directly after the `$emails['WC_Email_PipePay_Trial_Ended']` line (before the renewal classes):

```php
    $emails['WC_Email_PipePay_Trial_Day1']          = new WC_Email_PipePay_Trial_Day1();
    $emails['WC_Email_PipePay_Trial_Day3']          = new WC_Email_PipePay_Trial_Day3();
    $emails['WC_Email_PipePay_Trial_Winback']       = new WC_Email_PipePay_Trial_Winback();
    $emails['WC_Email_PipePay_Trial_Winback_Final'] = new WC_Email_PipePay_Trial_Winback_Final();
```

Also extend the "Registered email IDs" list in the file's docblock with the four new ids:

```
 *   pipepay_trial_day1           - day 1 of trial (segmented: activated vs not)
 *   pipepay_trial_day3           - day 3 of trial (segmented: activated vs not)
 *   pipepay_trial_winback        - T+3 after trial ended (skipped if converted)
 *   pipepay_trial_winback_final  - T+7 after trial ended (skipped if converted)
```

- [ ] **Step 4: Lint**

Run: `cd "/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site" && php -l mu-plugins/pipepay-lib/email-classes.php && php -l mu-plugins/pipepay-email-classes.php`
Expected: `No syntax errors detected` twice

- [ ] **Step 5: Commit**

```bash
cd "/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site" && git add mu-plugins/pipepay-lib/email-classes.php mu-plugins/pipepay-email-classes.php && git commit -m "feat: day1/day3 segmented + winback trial email classes

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: Six new templates

All in `Email Templates/`. Every template follows the existing contract: ABSPATH guard, `require_once __DIR__ . '/partials/helpers.php';`, plain-text branch first (sanitize inputs, echo raw text, `return`), then HTML branch wrapped in `woocommerce_email_header` / `woocommerce_email_footer` actions, `$additional_content` block before the footer. Copy: no em-dashes, no "reply to this email", no "open source", no discounts.

**Files:**
- Create: `Email Templates/trial-day1-not-activated.php`
- Create: `Email Templates/trial-day1-activated.php`
- Create: `Email Templates/trial-day3-not-activated.php`
- Create: `Email Templates/trial-day3-activated.php`
- Create: `Email Templates/trial-winback.php`
- Create: `Email Templates/trial-winback-final.php`

**Interfaces:**
- Consumes scope vars from Task 3 base class: `$first_name`, `$expires_label`, `$renewal_url`, `$license_key`, `$intent_tier_name`, `$intent_tier_price`, `$intent_monthly_price`, `$plain_text`, `$email`, `$email_heading`, `$additional_content`. Helpers: `pp_email_greeting()`, `pp_email_paragraph()`, `pp_email_button()`, `pp_email_license_card()`, `pp_email_signoff()`, `pp_email_brand_color()`, `pp_email_text_color()`.

- [ ] **Step 1: `trial-day1-not-activated.php`**

```php
<?php
/**
 * Trial day 1 - license not yet activated. Install walkthrough.
 * Scope: standard cadence contract (see PipePay_Cadence_Email_Base).
 *
 * @package pipe-pay-site
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/partials/helpers.php';

$docs_url = home_url( '/docs/getting-started/' );

if ( $plain_text ) :
    $first_name  = sanitize_text_field( $first_name );
    $license_key = sanitize_text_field( (string) $license_key );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    echo "Your Pipe Pay trial started yesterday. The one step that turns the\n";
    echo "gateway on is activating your license. Most stores are done in about\n";
    echo "10 minutes:\n\n";
    echo "  1. Download the plugin zip from your account: " . esc_url_raw( home_url( '/my-account/downloads/' ) ) . "\n";
    echo "  2. Upload it in WordPress -> Plugins -> Add New -> Upload Plugin\n";
    echo "  3. Paste your license key into Pipe Pay -> License and click Activate\n\n";
    if ( $license_key ) {
        echo "Your license key:\n{$license_key}\n\n";
    }
    echo "Step-by-step guide with screenshots: " . esc_url_raw( $docs_url ) . "\n\n";
    echo "Stuck on anything? Email support@pipepay.app and we will walk you through it.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph( 'Your Pipe Pay trial started yesterday. The one step that turns the gateway on is activating your license. Most stores are done in about 10 minutes:' );
?>
<ol style="margin:0 0 24px 22px;padding:0;font-size:16px;line-height:1.6;color:<?php echo esc_attr( pp_email_text_color() ); ?>;">
    <li style="margin:0 0 6px 0;">Download the plugin zip from your <a href="<?php echo esc_url( home_url( '/my-account/downloads/' ) ); ?>" style="color:<?php echo esc_attr( pp_email_brand_color() ); ?>;text-decoration:underline;">account downloads</a></li>
    <li style="margin:0 0 6px 0;">Upload it in WordPress &rarr; <strong>Plugins &rarr; Add New &rarr; Upload Plugin</strong></li>
    <li style="margin:0 0 6px 0;">Paste your license key into <strong>Pipe Pay &rarr; License</strong> and click Activate</li>
</ol>
<?php
pp_email_license_card( $license_key );
pp_email_button( $docs_url, 'Open the setup guide' );
pp_email_paragraph( 'Stuck on anything? Email <a href="mailto:support@pipepay.app" style="color:' . esc_attr( pp_email_brand_color() ) . ';text-decoration:underline;">support@pipepay.app</a> and we will walk you through it.' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
```

- [ ] **Step 2: `trial-day1-activated.php`**

```php
<?php
/**
 * Trial day 1 - license activated. Setup checklist toward a first verified order.
 *
 * @package pipe-pay-site
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/partials/helpers.php';

$config_url = home_url( '/docs/configuration/' );

if ( $plain_text ) :
    $first_name = sanitize_text_field( $first_name );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    echo "Your license is activated. Three things get you to your first\n";
    echo "verified payment:\n\n";
    echo "  1. Add your payment handles (Venmo, Cash App, PayPal, Zelle) under\n";
    echo "     WooCommerce -> Settings -> Payments -> Pipe Pay\n";
    echo "  2. Optional: add an AI provider key so screenshots verify\n";
    echo "     automatically (a few cents per order). Without one, every\n";
    echo "     upload lands in your Proofs queue for one-click manual review.\n";
    echo "  3. Place a \$1 test order on your own store and pay it from your\n";
    echo "     phone. Watching your own screenshot verify is the fastest way\n";
    echo "     to see what your customers will see.\n\n";
    echo "Configuration guide: " . esc_url_raw( $config_url ) . "\n\n";
    echo "Questions? Email support@pipepay.app.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph( 'Your license is activated. Three things get you to your first verified payment:' );
?>
<ol style="margin:0 0 24px 22px;padding:0;font-size:16px;line-height:1.6;color:<?php echo esc_attr( pp_email_text_color() ); ?>;">
    <li style="margin:0 0 6px 0;">Add your payment handles (Venmo, Cash App, PayPal, Zelle) under <strong>WooCommerce &rarr; Settings &rarr; Payments &rarr; Pipe Pay</strong></li>
    <li style="margin:0 0 6px 0;">Optional: add an AI provider key so screenshots verify automatically (a few cents per order). Without one, every upload lands in your Proofs queue for one-click manual review.</li>
    <li style="margin:0 0 6px 0;">Place a $1 test order on your own store and pay it from your phone. Watching your own screenshot verify is the fastest way to see what your customers will see.</li>
</ol>
<?php
pp_email_button( $config_url, 'Open the configuration guide' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
```

- [ ] **Step 3: `trial-day3-not-activated.php`**

```php
<?php
/**
 * Trial day 3 - still not activated. Short unblock note, neutral voice.
 *
 * @package pipe-pay-site
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/partials/helpers.php';

$docs_url = home_url( '/docs/getting-started/' );

if ( $plain_text ) :
    $first_name = sanitize_text_field( $first_name );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    echo "You are halfway through your Pipe Pay trial and the license has not\n";
    echo "been activated yet. Most stores finish setup in about 10 minutes,\n";
    echo "so if something got in the way, it is probably a small thing.\n\n";
    echo "The setup guide covers every step with screenshots:\n";
    echo esc_url_raw( $docs_url ) . "\n\n";
    echo "If anything is unclear or broken, email support@pipepay.app with\n";
    echo "what you are seeing and we will sort it out.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph( 'You are halfway through your Pipe Pay trial and the license has not been activated yet. Most stores finish setup in about 10 minutes, so if something got in the way, it is probably a small thing.' );
pp_email_button( $docs_url, 'Open the setup guide' );
pp_email_paragraph( 'If anything is unclear or broken, email <a href="mailto:support@pipepay.app" style="color:' . esc_attr( pp_email_brand_color() ) . ';text-decoration:underline;">support@pipepay.app</a> with what you are seeing and we will sort it out.' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
```

- [ ] **Step 4: `trial-day3-activated.php`**

```php
<?php
/**
 * Trial day 3 - activated. Value rundown + first pricing mention (annual +
 * monthly, intent-aware). No discounts.
 *
 * @package pipe-pay-site
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/partials/helpers.php';

if ( $plain_text ) :
    $first_name        = sanitize_text_field( $first_name );
    $renewal_url       = esc_url_raw( $renewal_url );
    $intent_tier_name  = sanitize_text_field( $intent_tier_name );
    $intent_tier_price = sanitize_text_field( $intent_tier_price );
    $intent_monthly    = sanitize_text_field( $intent_monthly_price );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    echo "You are halfway through your trial. Here is what Pipe Pay is doing\n";
    echo "on every order while it runs:\n\n";
    echo "  - Checks each screenshot: right amount, right recipient handle,\n";
    echo "    payment completed (not pending or cancelled), no signs of editing\n";
    echo "  - Compares a privacy-safe fingerprint against the whole network of\n";
    echo "    Pipe Pay stores, so a fake screenshot used at another shop gets\n";
    echo "    flagged at yours\n";
    echo "  - Sends payment reminders and auto-cancels unpaid orders so your\n";
    echo "    order list stays clean\n\n";
    if ( $intent_tier_name ) {
        echo "When you started, you picked the {$intent_tier_name} tier. Keeping it\n";
        echo "is {$intent_tier_price} per year, or {$intent_monthly} per month, cancel anytime.\n\n";
    } else {
        echo "Keeping Pipe Pay after the trial: \$297/\$497/\$997 per year for\n";
        echo "Single Site / 5 Sites / Unlimited, or \$35/\$65/\$129 per month,\n";
        echo "cancel anytime.\n\n";
    }
    echo "Convert in one click (details pre-filled):\n{$renewal_url}\n\n";
    echo "Questions? Email support@pipepay.app.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph( 'You are halfway through your trial. Here is what Pipe Pay is doing on every order while it runs:' );
?>
<ul style="margin:0 0 24px 22px;padding:0;font-size:16px;line-height:1.6;color:<?php echo esc_attr( pp_email_text_color() ); ?>;">
    <li style="margin:0 0 6px 0;">Checks each screenshot: right amount, right recipient handle, payment <strong>completed</strong> (not pending or cancelled), no signs of editing</li>
    <li style="margin:0 0 6px 0;">Compares a privacy-safe fingerprint against the whole network of Pipe Pay stores, so a fake screenshot used at another shop gets flagged at yours</li>
    <li style="margin:0 0 6px 0;">Sends payment reminders and auto-cancels unpaid orders so your order list stays clean</li>
</ul>
<?php
if ( $intent_tier_name ) {
    pp_email_paragraph( 'When you started, you picked the <strong>' . esc_html( $intent_tier_name ) . '</strong> tier. Keeping it is <strong>' . esc_html( $intent_tier_price ) . ' per year</strong>, or ' . esc_html( $intent_monthly_price ) . ' per month, cancel anytime.' );
} else {
    pp_email_paragraph( 'Keeping Pipe Pay after the trial: $297 / $497 / $997 per year for Single Site / 5 Sites / Unlimited, or $35 / $65 / $129 per month, cancel anytime.' );
}
pp_email_button( $renewal_url, 'Keep it running' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
```

- [ ] **Step 5: `trial-winback.php` (T+3)**

```php
<?php
/**
 * Trial win-back (T+3). Only sent when the user has not converted (engine
 * skip_if_converted). Leads with the monthly lane. No discounts.
 *
 * @package pipe-pay-site
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/partials/helpers.php';

$pricing_url = home_url( '/pricing/' );

if ( $plain_text ) :
    $first_name     = sanitize_text_field( $first_name );
    $renewal_url    = esc_url_raw( $renewal_url );
    $intent_monthly = sanitize_text_field( $intent_monthly_price );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    echo "Your Pipe Pay trial ended a few days ago. Your gateway is still\n";
    echo "accepting orders during the 30-day grace window, but updates and\n";
    echo "support are paused, and at the end of the window Pipe Pay stops\n";
    echo "appearing at checkout.\n\n";
    echo "If the annual license was the sticking point, monthly billing might\n";
    echo "fit better: " . ( $intent_monthly ?: '$35' ) . " per month, cancel anytime, same plugin,\n";
    echo "same updates. Pick monthly on the pricing page:\n";
    echo esc_url_raw( $pricing_url ) . "\n\n";
    echo "Prefer annual? Your one-click link still works:\n{$renewal_url}\n\n";
    echo "And if Pipe Pay was not the right fit, one question helps us more\n";
    echo "than anything: what stopped you? Email support@pipepay.app, even one\n";
    echo "sentence is useful.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph( 'Your Pipe Pay trial ended a few days ago. Your gateway is still accepting orders during the 30-day grace window, but updates and support are paused, and at the end of the window Pipe Pay stops appearing at checkout.' );
pp_email_paragraph( 'If the annual license was the sticking point, monthly billing might fit better: <strong>' . esc_html( $intent_monthly_price ?: '$35' ) . ' per month</strong>, cancel anytime, same plugin, same updates.' );
pp_email_button( $pricing_url, 'See monthly pricing' );
pp_email_paragraph( 'Prefer annual? <a href="' . esc_url( $renewal_url ) . '" style="color:' . esc_attr( pp_email_brand_color() ) . ';text-decoration:underline;">Your one-click conversion link</a> still works.' );
pp_email_paragraph( 'And if Pipe Pay was not the right fit, one question helps us more than anything: what stopped you? Email <a href="mailto:support@pipepay.app" style="color:' . esc_attr( pp_email_brand_color() ) . ';text-decoration:underline;">support@pipepay.app</a>, even one sentence is useful.' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
```

- [ ] **Step 6: `trial-winback-final.php` (T+7)**

```php
<?php
/**
 * Trial win-back final (T+7). Last automated email for the trial. No pressure
 * copy, then silence.
 *
 * @package pipe-pay-site
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/partials/helpers.php';

if ( $plain_text ) :
    $first_name  = sanitize_text_field( $first_name );
    $renewal_url = esc_url_raw( $renewal_url );

    echo "Hi " . ( $first_name ?: 'there' ) . ",\n\n";
    echo "This is the last email about your Pipe Pay trial.\n\n";
    echo "Your license stays saved in your account, and converting later uses\n";
    echo "the same one-click link, whenever the timing is right:\n";
    echo "{$renewal_url}\n\n";
    echo "Heads up on timing: 30 days after the trial ended, the gateway\n";
    echo "stops offering Pipe Pay at new checkouts until a paid tier is\n";
    echo "active. Converting reopens it right away.\n\n";
    echo "Questions? Email support@pipepay.app.\n\n";
    echo "- Pipe Pay\n";
    return;
endif;

do_action( 'woocommerce_email_header', $email_heading, $email );

pp_email_greeting( $first_name );
pp_email_paragraph( 'This is the last email about your Pipe Pay trial.' );
pp_email_paragraph( 'Your license stays saved in your account, and converting later uses the same one-click link, whenever the timing is right.' );
pp_email_button( $renewal_url, 'Pick a tier' );
pp_email_paragraph( 'Heads up on timing: 30 days after the trial ended, the gateway stops offering Pipe Pay at new checkouts until a paid tier is active. Converting reopens it right away.' );
pp_email_signoff();

if ( ! empty( $additional_content ) ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
```

- [ ] **Step 7: Lint all six + copy-rule grep**

Run: `cd "/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site" && for f in "Email Templates"/trial-day1-not-activated.php "Email Templates"/trial-day1-activated.php "Email Templates"/trial-day3-not-activated.php "Email Templates"/trial-day3-activated.php "Email Templates"/trial-winback.php "Email Templates"/trial-winback-final.php; do php -l "$f" || exit 1; done && ! grep -l -e '—' -e '&mdash;' -e 'reply to this email' -e 'open source' "Email Templates"/trial-day1-*.php "Email Templates"/trial-day3-*.php "Email Templates"/trial-winback*.php`
Expected: six `No syntax errors detected`, and the grep finds nothing (exit 0 overall)

- [ ] **Step 8: Commit**

```bash
cd "/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site" && git add "Email Templates"/trial-day1-not-activated.php "Email Templates"/trial-day1-activated.php "Email Templates"/trial-day3-not-activated.php "Email Templates"/trial-day3-activated.php "Email Templates"/trial-winback.php "Email Templates"/trial-winback-final.php && git commit -m "feat: six trial-flow email templates (day1/day3 segmented, t+3/t+7 winback)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 5: Upgrade the three existing trial templates

Intent-aware pricing on T-2, extension block + intent pricing on T+0, activation emphasis on Day-0, em-dash sweep on all three.

**Files:**
- Modify: `Email Templates/trial-ending-soon.php`
- Modify: `Email Templates/trial-ended.php`
- Modify: `Email Templates/free-trial.php`

**Interfaces:**
- Consumes: `$intent_tier_name`, `$intent_tier_price`, `$intent_monthly_price`, `$trial_extension_url` (in scope for cadence templates since Task 3; `free-trial.php` is rendered by the theme wrapper, NOT the cadence base class, so it does NOT get these vars — its edit is copy-only).

- [ ] **Step 1: `trial-ending-soon.php` pricing paragraph**

In the plain branch, after the `$renewal_url` sanitize line add:

```php
    $intent_tier_name  = sanitize_text_field( $intent_tier_name );
    $intent_tier_price = sanitize_text_field( $intent_tier_price );
    $intent_monthly    = sanitize_text_field( $intent_monthly_price );
```

and replace the line `echo "No card required to start - you'll only be charged after you confirm.\n";` and the following `echo "Your details are pre-filled so renewal takes about 30 seconds.\n\n";` with:

```php
    if ( $intent_tier_name ) {
        echo "You picked the {$intent_tier_name} tier when you started: {$intent_tier_price}\n";
        echo "per year, or {$intent_monthly} per month, cancel anytime. Your details\n";
        echo "are pre-filled so converting takes about 30 seconds.\n\n";
    } else {
        echo "Tiers: \$297/\$497/\$997 per year, or \$35/\$65/\$129 per month, cancel\n";
        echo "anytime. Your details are pre-filled so converting takes about 30\n";
        echo "seconds.\n\n";
    }
```

In the HTML branch, replace the `pp_email_paragraph( 'No card required to start — ...' );` call with:

```php
if ( $intent_tier_name ) {
    pp_email_paragraph( 'You picked the <strong>' . esc_html( $intent_tier_name ) . '</strong> tier when you started: <strong>' . esc_html( $intent_tier_price ) . ' per year</strong>, or ' . esc_html( $intent_monthly_price ) . ' per month, cancel anytime. Your details are pre-filled so converting takes about 30 seconds.' );
} else {
    pp_email_paragraph( 'Tiers: $297 / $497 / $997 per year, or $35 / $65 / $129 per month, cancel anytime. Your details are pre-filled so converting takes about 30 seconds.' );
}
```

Then sweep the remaining copy: in the "Prefer a card?" paragraph replace ` &mdash; ` with `. ` (sentence break) and confirm no `—` characters remain anywhere in the file.

- [ ] **Step 2: `trial-ended.php` extension block + intent pricing**

Plain branch: after the `$renewal_url` sanitize line add:

```php
    $intent_tier_name    = sanitize_text_field( $intent_tier_name );
    $intent_tier_price   = sanitize_text_field( $intent_tier_price );
    $intent_monthly      = sanitize_text_field( $intent_monthly_price );
    $trial_extension_url = esc_url_raw( $trial_extension_url );
```

Replace the hard-coded three-tier list block (`echo "Three paid tiers:\n"; ...` through the Unlimited line) with:

```php
    if ( $intent_tier_name ) {
        echo "You picked the {$intent_tier_name} tier when you started: {$intent_tier_price}\n";
        echo "per year, or {$intent_monthly} per month, cancel anytime.\n\n";
    } else {
        echo "Three paid tiers:\n";
        echo "  - Single Site     - \$297/yr or \$35/mo  - 1 site\n";
        echo "  - 5 Sites         - \$497/yr or \$65/mo  - up to 5 sites\n";
        echo "  - Unlimited Sites - \$997/yr or \$129/mo - any number\n\n";
    }
    if ( $trial_extension_url ) {
        echo "Still setting up? You can extend your trial once, by 7 days:\n";
        echo "{$trial_extension_url}\n\n";
    }
```

HTML branch: replace the `pp_email_paragraph( 'Three tiers: ...' );` call with:

```php
if ( $intent_tier_name ) {
    pp_email_paragraph( 'You picked the <strong>' . esc_html( $intent_tier_name ) . '</strong> tier when you started: <strong>' . esc_html( $intent_tier_price ) . ' per year</strong>, or ' . esc_html( $intent_monthly_price ) . ' per month, cancel anytime. Converting takes about 30 seconds, your details are pre-filled.' );
} else {
    pp_email_paragraph( 'Three tiers: Single Site $297/yr, 5 Sites $497/yr, Unlimited $997/yr. Monthly billing is also available at $35 / $65 / $129, cancel anytime. Converting takes about 30 seconds, your details are pre-filled.' );
}
if ( $trial_extension_url ) {
    pp_email_paragraph( 'Still setting up? You can extend your trial once, by 7 days:' );
    pp_email_button( $trial_extension_url, 'Extend my trial 7 days' );
}
```

Sweep `&mdash;` / `—` from the remaining copy (the old "Three tiers" paragraph contained one; the "Prefer a card?" paragraph contains `&mdash;`, replace with a sentence break).

- [ ] **Step 3: `free-trial.php` activation emphasis (copy-only)**

After the `<ol>` steps block (after the closing `</ol>` and before the "Your trial expires" paragraph), add:

```php
pp_email_paragraph( 'Step 3 is the one that turns the gateway on. Most stores are live in about 10 minutes.' );
```

In the plain branch, after the three numbered steps, add the matching line:

```php
    echo "Step 3 is the one that turns the gateway on. Most stores are live\n";
    echo "in about 10 minutes.\n\n";
```

Replace the `&mdash;` in `'Your free 7-day Pipe Pay trial is live &mdash; no card required.'` with `, ` (reads: "...is live, no card required.").

- [ ] **Step 4: Lint + sweep check**

Run: `cd "/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site" && php -l "Email Templates/trial-ending-soon.php" && php -l "Email Templates/trial-ended.php" && php -l "Email Templates/free-trial.php" && ! grep -l -e '—' -e '&mdash;' "Email Templates/trial-ending-soon.php" "Email Templates/trial-ended.php" "Email Templates/free-trial.php"`
Expected: three `No syntax errors detected`, grep finds nothing

- [ ] **Step 5: Commit**

```bash
cd "/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site" && git add "Email Templates/trial-ending-soon.php" "Email Templates/trial-ended.php" "Email Templates/free-trial.php" && git commit -m "feat: intent-aware pricing + extension offer + activation emphasis in existing trial emails

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 6: Trial-extension valve + tier-picker price fix

**Files:**
- Modify: `mu-plugins/pipepay-license-renewals.php` (route handler, lookup SELECT, new handler + confirmation renderer, tier picker prices)

**Interfaces:**
- Consumes: `pipepay_renewal_render_error()`, `PIPEPAY_CADENCE_STAGES` (stamp cleanup), `pipepay_cadence_tier_display()` (picker prices).
- Produces: route behavior `GET /renew/?key=...&token=...&pp_action=extend_trial`; order meta `_pipepay_trial_extended` (ISO timestamp, once ever); `pipepay_renewal_handle_trial_extension( array $license, string $key ): void`; `pipepay_renewal_render_extension_confirmation( string $new_expires_label ): void`.

- [ ] **Step 1: Add `api_resource_id` to the license lookup**

In `pipepay_renewal_lookup_license()`, change the SELECT column list from
`master_api_key, product_id, product_title, order_id, access_expires, active` to
`api_resource_id, master_api_key, product_id, product_title, order_id, access_expires, active`.
(master_api_key is per-USER in Kestrel, so any UPDATE must target `api_resource_id`, never the key.)

- [ ] **Step 2: Route the extension action**

In `pipepay_renewal_route_handler()`, read the action param alongside the existing GET reads:

```php
    $pp_action = isset( $_GET['pp_action'] ) ? sanitize_key( wp_unslash( $_GET['pp_action'] ) ) : '';
```

Then, AFTER the `empty( $license['active'] )` check and BEFORE the tier-override block, add:

```php
    if ( 'extend_trial' === $pp_action ) {
        pipepay_renewal_handle_trial_extension( $license, $key );
        return;
    }
```

- [ ] **Step 3: The extension handler + confirmation page**

Add after `pipepay_renewal_redirect_to_checkout()`:

```php
/**
 * One-time 7-day trial extension. The email link is only built for trials
 * that were never activated, and the once-ever guard lives here: after the
 * first extension the order carries _pipepay_trial_extended forever. The
 * HMAC token also self-invalidates because access_expires (a signed input)
 * changes.
 */
function pipepay_renewal_handle_trial_extension( array $license, string $key ): void {
    global $wpdb;

    if ( PIPEPAY_RENEWAL_TRIAL_PRODUCT_ID !== (int) $license['product_id'] ) {
        pipepay_renewal_render_error(
            'Not available',
            'Extensions apply to trial licenses only. Paid licenses renew from the link in your renewal email.',
            400
        );
        return;
    }

    $order_id = (int) $license['order_id'];
    if ( $order_id && get_post_meta( $order_id, '_pipepay_trial_extended', true ) ) {
        pipepay_renewal_render_error(
            'Extension already used',
            'This trial has already been extended once. To keep using Pipe Pay, pick a tier on the <a href="' . esc_url( home_url( '/pricing/' ) ) . '">pricing page</a>.',
            410
        );
        return;
    }

    $new_expires = max( time(), (int) $license['access_expires'] ) + 7 * DAY_IN_SECONDS;

    $updated = $wpdb->update(
        $wpdb->prefix . 'wc_am_api_resource',
        [ 'access_expires' => $new_expires ],
        [ 'api_resource_id' => (int) $license['api_resource_id'] ],
        [ '%d' ],
        [ '%d' ]
    );
    if ( false === $updated ) {
        pipepay_renewal_render_error(
            'Something went wrong',
            'We could not extend the trial. Please <a href="' . esc_url( home_url( '/contact/' ) ) . '">contact support</a>.',
            500
        );
        return;
    }

    // Re-open the plugin zip download: product 38's download permission
    // expired with the original 7-day window, and a not-yet-activated
    // customer still needs the zip.
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->prefix}woocommerce_downloadable_product_permissions
         SET access_expires = %s
         WHERE order_id = %d AND product_id = %d",
        gmdate( 'Y-m-d H:i:s', $new_expires ),
        $order_id,
        PIPEPAY_RENEWAL_TRIAL_PRODUCT_ID
    ) );

    if ( $order_id ) {
        update_post_meta( $order_id, '_pipepay_trial_extended', gmdate( 'c' ) );
        // Fresh window: T-2 / T+0 fire again against the new expiry.
        foreach ( [ 'trial_t-2', 'trial_t+0' ] as $stage_key ) {
            $stamp = PIPEPAY_CADENCE_STAGES[ $stage_key ]['stamp'];
            delete_post_meta( $order_id, $stamp );
            delete_post_meta( $order_id, $stamp . '_fails' );
        }
        $wc_order = wc_get_order( $order_id );
        if ( $wc_order ) {
            $wc_order->add_order_note( sprintf(
                'Pipe Pay trial extension: expiry moved to %s (one-time, customer-initiated).',
                gmdate( 'Y-m-d', $new_expires )
            ) );
            $wc_order->save();
        }
    }

    pipepay_renewal_render_extension_confirmation(
        wc_format_datetime( ( new DateTime( '@' . $new_expires ) )->setTimezone( wp_timezone() ), 'F j, Y' )
    );
}

/**
 * Confirmation page for a successful trial extension. Site chrome, next
 * steps, and exit.
 */
function pipepay_renewal_render_extension_confirmation( string $new_expires_label ): void {
    get_header();
    ?>
    <section class="pp-page-hero">
        <div class="pp-container">
            <span class="pp-page-hero__kicker">Trial extended</span>
            <h1 class="pp-page-title">Your trial now runs through <?php echo esc_html( $new_expires_label ); ?>.</h1>
            <p class="pp-page-hero__sub">Your license key is unchanged, and your plugin download has been re-opened in <a href="<?php echo esc_url( home_url( '/my-account/downloads/' ) ); ?>">your account</a>. This extension can be used once per trial.</p>
        </div>
    </section>
    <section class="pp-section pp-section--tight">
        <div class="pp-container">
            <p>Pick up where you left off: <a href="<?php echo esc_url( home_url( '/docs/getting-started/' ) ); ?>">the setup guide</a> walks through install and activation step by step. If anything is in the way, email <a href="mailto:support@pipepay.app">support@pipepay.app</a>.</p>
        </div>
    </section>
    <?php
    get_footer();
    exit;
}
```

- [ ] **Step 4: Fix the stale tier-picker prices**

In `pipepay_renewal_render_tier_picker()`, the `$tiers` array shows `$249` / `$599` / `$1,199` while the live store charges $297 / $497 / $997. Replace the three hard-coded `'price'` values by deriving from the single source of truth:

```php
                $tiers = [];
                foreach ( [ 34, 35, 36 ] as $tid ) {
                    $display = pipepay_cadence_tier_display( $tid );
                    $tiers[ $tid ] = [
                        'title'    => $display['name'],
                        'price'    => $display['annual'],
                        'subtitle' => [
                            34 => 'For one WooCommerce store.',
                            35 => 'For agencies or multi-store owners.',
                            36 => 'No activation cap. Run it everywhere.',
                        ][ $tid ],
                        'features' => [
                            34 => [ '1 site activation', '1 year of plugin updates', '1 year of email support' ],
                            35 => [ 'Up to 5 site activations', '1 year of plugin updates', '1 year of email support' ],
                            36 => [ 'Unlimited site activations', '1 year of plugin updates', '1 year of email support' ],
                        ][ $tid ],
                    ];
                }
                $tiers[35]['featured'] = true;
```

(Rendering loop below it is unchanged.)

- [ ] **Step 5: Lint + rerun pure test**

Run: `cd "/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site" && php -l mu-plugins/pipepay-license-renewals.php && php tests/cadence-logic-test.php`
Expected: `No syntax errors detected`, `OK`

- [ ] **Step 6: Commit**

```bash
cd "/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site" && git add mu-plugins/pipepay-license-renewals.php && git commit -m "feat: one-time trial extension valve on /renew/ + tier picker price fix (297/497/997)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 7: Docs, deploy, and server-side E2E verification

Deployment + live verification require SSH to the OptiPlex. If this task runs in an environment without SSH access, stop after Step 2 and report the remaining steps as the operator runbook.

**Files:**
- Modify: `Email Templates/README.md` (template roster)
- Modify: `CLAUDE.md` (ship-log entry)

- [ ] **Step 1: Update the Email Templates README roster**

In the "Wired (firing today)" / cadence table area of `Email Templates/README.md`, move `trial-ending-soon.php` and `trial-ended.php` guidance to reflect they are LIVE via the cadence cron (the "Ready, but trigger TBD" table is stale), and add the six new templates with their triggers:

```markdown
| `trial-day1-not-activated.php` | Cadence stage `trial_t-6`, not-activated segment | `WC_Email_PipePay_Trial_Day1` |
| `trial-day1-activated.php` | Cadence stage `trial_t-6`, activated segment | same class |
| `trial-day3-not-activated.php` | Cadence stage `trial_t-4`, not-activated segment | `WC_Email_PipePay_Trial_Day3` |
| `trial-day3-activated.php` | Cadence stage `trial_t-4`, activated segment | same class |
| `trial-winback.php` | Cadence stage `trial_t+3` (skipped if converted) | `WC_Email_PipePay_Trial_Winback` |
| `trial-winback-final.php` | Cadence stage `trial_t+7` (skipped if converted) | `WC_Email_PipePay_Trial_Winback_Final` |
```

- [ ] **Step 2: Add the CLAUDE.md ship-log entry + commit**

Add a `### Resolved`-style entry to the site `CLAUDE.md` summarizing: 7-touch trial flow, activation segmentation (activation-table + revalidate-log predicate), late-only stage matching (behavior change: paid stages no longer fire a day early), one-send-per-license-per-run, trial extension valve (`pp_action=extend_trial`, once ever, re-opens download permission, clears T-2/T+0 stamps), tier-picker price fix, mu-plugin 1.0.0 → 1.1.0. Then:

```bash
cd "/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site" && git add "Email Templates/README.md" CLAUDE.md && git commit -m "docs: trial-conversion email flow roster + ship-log

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

- [ ] **Step 3: Deploy (three syncs)**

Run the CLAUDE.md cheatsheet commands, in this order:
1. The **renewal cadence + email classes** sync (3-file scp block) — EXTENDED for this ship: also copy `mu-plugins/pipepay-lib/cadence-logic.php` to `/var/www/pipepay/wp-content/mu-plugins/pipepay-lib/cadence-logic.php` (add it to the scp list and an extra `sudo install` line, same pattern as `email-classes.php`).
2. The **Email Templates** sync (tar/scp block, folder name quoted).
3. Verify no fatals: `ssh witt-scafidi@100.102.251.125 'sudo -u www-data wp --path=/var/www/pipepay eval "echo defined(\"PIPEPAY_CADENCE_TOLERANCE_DAYS\") ? \"lib loaded\" : \"LIB MISSING\";"'` → expect `lib loaded`.

- [ ] **Step 4: E2E — stage-by-stage send test**

On the server, pick (or create) a trial license row and walk the schedule. For each stage: set `access_expires`, run the cron, confirm exactly one email + stamp; re-run, confirm no duplicate.

```bash
ssh witt-scafidi@100.102.251.125
# Identify a TEST trial license (product 38) + its order:
sudo -u www-data wp --path=/var/www/pipepay db query "SELECT api_resource_id, order_id, access_expires FROM wp_wc_am_api_resource WHERE product_id = 38 AND active = 1 ORDER BY api_resource_id DESC LIMIT 3"
# Day 1 (not-activated branch expected — the test license has no activation seats):
sudo -u www-data wp --path=/var/www/pipepay db query "UPDATE wp_wc_am_api_resource SET access_expires = UNIX_TIMESTAMP() + 6*86400 WHERE api_resource_id = <ID>"
sudo -u www-data wp --path=/var/www/pipepay action-scheduler run --hooks=pipepay_license_check_renewals
# → expect: 'Cadence email sent' stage=trial_t-6 in WC → Status → Logs (source=pipepay); email at billing address uses the NOT-ACTIVATED body.
sudo -u www-data wp --path=/var/www/pipepay action-scheduler run --hooks=pipepay_license_check_renewals
# → expect: no second send (idempotency stamp).
```

Repeat for `+4*86400` (day-3 email), `+2*86400` (T-2, confirm intent pricing renders when the trial order has `_pipepay_intended_tier_pid`), `-1` seconds (T+0, confirm the extension button appears since the license has no activations), `-3*86400` (t+3), `-7*86400` (t+7). Also verify:
- **One-per-run**: clear all stamps (`wp post meta delete <order_id> _pipepay_renewal_stamp_trial_t-6` etc.), set expiry to `+2*86400`, run once → ONLY `trial_t-2` sends (late-only windows mean earlier stages no longer match), not a stack of emails.
- **Activated segment**: insert a synthetic seat row for the license (`INSERT INTO wp_wc_am_api_activation (activation_time, api_key, api_resource_id, assigned_product_id, instance, master_api_key, object, order_id, order_item_id, product_id, product_order_api_key, user_id) VALUES (UNIX_TIMESTAMP(), '<key>', <ID>, 38, 'e2e-test-instance', '<key>', 'https://example.test', <order_id>, 0, '38', '<key>', <user_id>)`), clear the day-1 stamp, set expiry `+6*86400`, run → ACTIVATED body arrives, and T+0 (when re-tested) shows NO extension button. Delete the synthetic row afterwards.
- **Converted skip**: on a user with an active paid row, set trial expiry to `-3*86400`, run → nothing sends, no stamp written.
- **Post-expiry rows still enumerate**: confirm the `-7*86400` row still comes back from the cadence query (`active=1` survives expiry; expiry is timestamp-enforced). If WCAM flips `active=0` on expired trials in 3.7.6, STOP and report — the t+3/t+7 stages need the query relaxed for trial rows, which is a design change to confirm with the owner.

- [ ] **Step 5: E2E — extension round-trip**

With the un-activated test trial at T+0: open the extension URL from the T+0 email in a private window → confirmation page renders with the new date; DB shows `access_expires` +7 days and the download-permission row re-opened; order note present; T-2/T+0 stamps cleared. Open the SAME URL again → "Renewal link invalid" (the token died with the expiry change). Rebuild a fresh URL (wp eval snippet at the bottom of the mu-plugin) with `&pp_action=extend_trial` → "Extension already used" (410). Confirm the next cadence run re-fires T-2 two days before the NEW expiry.

- [ ] **Step 6: Cleanup + report**

Remove synthetic rows/stamps created for testing, restore the test license's real `access_expires`. Report: stages verified, any deviations, and the two operational notes for the owner: (a) paid-cadence timing now fires on the scheduled day instead of one day early; (b) weekly metrics pull should now also report the day-3 activation rate (count of trial licenses with a seat row by T-4).

---

## Out of scope (explicitly)

- Cloudflare cache-rule changes (none needed; `/renew/` bypass was an existing to-do, unchanged by this work).
- Anti-abuse on trial signup (Turnstile / one-trial-per-email) — separate CLAUDE.md backlog item; the extension valve's once-ever guard does not depend on it.
- Any plugin-repo (`pipe-pay-extracted`) changes — this ship is entirely site-side.
- Open/click tracking beyond Resend's dashboard.
