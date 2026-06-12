<?php
/**
 * Plugin Name: Pipe Pay - License Renewals
 * Description: HMAC-signed /renew/ landing page for trial conversions and paid renewals. Stages 3 + 4 of the trial-tier-intent flow.
 * Author:      Pipe Pay
 * Version:     1.0.0
 *
 * Routes (single page, all flows):
 *   /renew/?key=<license>&token=<hmac>             Conversion / renewal landing
 *   /renew/?key=<license>&token=<hmac>&tier=<id>   Tier override (Stage 4 picker)
 *
 * The HMAC signs (license_key, access_expires, secret). Replay-resistant: once
 * a license is renewed, access_expires changes, and the old token stops
 * verifying. The signed tuple is the auth - no logged-in customer required.
 *
 * Behavior matrix:
 *
 *   trial license (product_id = 38) + intent meta on trial order
 *      -> empty cart, add the intended tier, redirect to /checkout/
 *
 *   trial license + no intent meta (header / hero / final-CTA path)
 *      -> render the tier picker (Stage 4), then second click flows through
 *         the &tier=N override branch
 *
 *   paid license (product_id in {34, 35, 36})
 *      -> empty cart, add the same tier, attach _pipepay_renewal_for_license
 *         cart-item meta (consumed by the renewal-completion hooks at the
 *         bottom of this file: extends the existing license's access_expires
 *         by 365 days and removes the duplicate key WCAM mints)
 *
 *   any tier override (?tier=NN, allow-listed) wins over the default routing.
 *   The HMAC still secures the (key, expires) tuple; tier choice is open
 *   because a customer paying for a different tier is paying real money.
 *
 * Required: wp-config.php must define PIPEPAY_RENEWAL_HMAC_SECRET (a long
 * random string). Without it, the endpoint surfaces a setup error instead
 * of pretending tokens are valid.
 *
 * Cloudflare: /renew/ should be in the cache-bypass rule alongside /checkout/,
 * /cart/, /my-account/, /wp-json/. The handler also sends no-store headers,
 * but a CF cache rule update is the belt-and-suspenders move.
 */

defined( 'ABSPATH' ) || exit;

const PIPEPAY_RENEWAL_TIER_PRODUCT_IDS = [ 34, 35, 36 ];
const PIPEPAY_RENEWAL_TRIAL_PRODUCT_ID = 38;
const PIPEPAY_RENEWAL_PAGE_SLUG        = 'renew';

// ── HMAC helpers ─────────────────────────────────────────────────────────────

/**
 * Sign a (license_key, access_expires) tuple.
 *
 * Returns empty string if the secret isn't configured - caller must check
 * defined( 'PIPEPAY_RENEWAL_HMAC_SECRET' ) before relying on a token. This
 * is intentional: an empty token never verifies, so misconfiguration
 * can't accidentally produce valid links.
 */
function pipepay_renewal_hmac_sign( string $license_key, int $access_expires ): string {
    if ( ! defined( 'PIPEPAY_RENEWAL_HMAC_SECRET' ) || '' === PIPEPAY_RENEWAL_HMAC_SECRET ) {
        return '';
    }
    return hash_hmac( 'sha256', $license_key . '|' . $access_expires, PIPEPAY_RENEWAL_HMAC_SECRET );
}

/**
 * Constant-time compare an incoming token against the expected one.
 * Replay-resistant by binding to access_expires - a renewed license has a new
 * expires, and the old token no longer matches.
 */
function pipepay_renewal_hmac_verify( string $license_key, int $access_expires, string $token ): bool {
    if ( ! defined( 'PIPEPAY_RENEWAL_HMAC_SECRET' ) || '' === PIPEPAY_RENEWAL_HMAC_SECRET ) {
        return false;
    }
    $expected = pipepay_renewal_hmac_sign( $license_key, $access_expires );
    return '' !== $expected && hash_equals( $expected, $token );
}

// ── License + order lookups ──────────────────────────────────────────────────

/**
 * Find a license row in API Manager's resource table.
 *
 * @return array|null Row with master_api_key, product_id, order_id, access_expires, etc., or null on miss.
 */
function pipepay_renewal_lookup_license( string $license_key ): ?array {
    $license_key = trim( $license_key );
    if ( '' === $license_key ) {
        return null;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'wc_am_api_resource';
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT master_api_key, product_id, product_title, order_id, access_expires, active
             FROM {$table}
             WHERE master_api_key = %s
             ORDER BY api_resource_id ASC
             LIMIT 1",
            $license_key
        ),
        ARRAY_A
    );
    // phpcs:enable
    return $row ?: null;
}

/**
 * Pull the intended-tier hint off a trial order's product-38 line item.
 * Returns the tier product ID (34/35/36) or null if not set / out of allow-list.
 */
function pipepay_renewal_get_intended_tier( int $order_id ): ?int {
    if ( $order_id <= 0 ) {
        return null;
    }
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return null;
    }
    foreach ( $order->get_items() as $item ) {
        if ( PIPEPAY_RENEWAL_TRIAL_PRODUCT_ID !== (int) $item->get_product_id() ) {
            continue;
        }
        $tier = (int) $item->get_meta( '_pipepay_intended_tier_pid' );
        if ( in_array( $tier, PIPEPAY_RENEWAL_TIER_PRODUCT_IDS, true ) ) {
            return $tier;
        }
    }
    return null;
}

// ── Route handler ────────────────────────────────────────────────────────────
// Hooks at template_redirect on the /renew/ WP page (slug: renew).
// Page must exist - see post-deploy step in the README block at the bottom.

add_action( 'template_redirect', 'pipepay_renewal_route_handler' );

function pipepay_renewal_route_handler(): void {
    if ( ! is_page( PIPEPAY_RENEWAL_PAGE_SLUG ) ) {
        return;
    }

    // Never cache. The token+key combo is single-use semantics; caching could
    // serve a stale redirect after the trial converts. Headers + nocache hint.
    nocache_headers();
    header( 'X-Robots-Tag: noindex, nofollow' );
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );

    // Setup check: refuse to operate if the HMAC secret isn't set, instead
    // of silently generating tokens that no one can ever verify.
    if ( ! defined( 'PIPEPAY_RENEWAL_HMAC_SECRET' ) || '' === PIPEPAY_RENEWAL_HMAC_SECRET ) {
        pipepay_renewal_render_error(
            'Renewal is not configured',
            'The site administrator needs to set <code>PIPEPAY_RENEWAL_HMAC_SECRET</code> in <code>wp-config.php</code> before the renewal flow works.',
            503
        );
        return;
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $key   = isset( $_GET['key'] )   ? sanitize_text_field( wp_unslash( $_GET['key'] ) )   : '';
    $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
    $tier_override_raw = isset( $_GET['tier'] ) ? (int) $_GET['tier'] : 0;
    // phpcs:enable

    if ( '' === $key || '' === $token ) {
        pipepay_renewal_render_error(
            'Invalid renewal link',
            'This renewal link is missing required parameters. Please use the link from your renewal email, or visit your <a href="' . esc_url( home_url( '/my-account' ) ) . '">account page</a>.',
            400
        );
        return;
    }

    $license = pipepay_renewal_lookup_license( $key );
    if ( ! $license ) {
        // Same opaque error as a bad HMAC - no enumeration oracle.
        pipepay_renewal_render_error(
            'Renewal link invalid',
            'This renewal link is no longer valid. It may have been used already, or the license details have changed since the link was issued. Please request a fresh link from your <a href="' . esc_url( home_url( '/my-account' ) ) . '">account page</a> or <a href="' . esc_url( home_url( '/contact' ) ) . '">contact support</a>.',
            404
        );
        return;
    }

    if ( ! pipepay_renewal_hmac_verify( $key, (int) $license['access_expires'], $token ) ) {
        pipepay_renewal_render_error(
            'Renewal link invalid',
            'This renewal link is no longer valid. It may have been used already, or the license details have changed since the link was issued. Please request a fresh link from your <a href="' . esc_url( home_url( '/my-account' ) ) . '">account page</a> or <a href="' . esc_url( home_url( '/contact' ) ) . '">contact support</a>.',
            403
        );
        return;
    }

    if ( empty( $license['active'] ) ) {
        pipepay_renewal_render_error(
            'License is deactivated',
            'This license has been deactivated. Please <a href="' . esc_url( home_url( '/contact' ) ) . '">contact support</a> if this is unexpected.',
            403
        );
        return;
    }

    $license_product_id = (int) $license['product_id'];
    $is_trial           = ( PIPEPAY_RENEWAL_TRIAL_PRODUCT_ID === $license_product_id );

    // Tier override (?tier=NN). Used by the Stage 4 picker for trial signups
    // without intent. Allow-list ensures only valid paid tiers can be selected.
    // PAID renewals ignore overrides that differ from the license's own tier:
    // the completion hook always extends the ORIGINAL license, so accepting a
    // cheaper tier here would extend a 5-Sites license at the Single-Site
    // price. Trials may pick any tier (that purchase is a NEW license).
    if ( $tier_override_raw && in_array( $tier_override_raw, PIPEPAY_RENEWAL_TIER_PRODUCT_IDS, true ) ) {
        if ( ! $is_trial && $tier_override_raw !== $license_product_id ) {
            $tier_override_raw = $license_product_id;
        }
        pipepay_renewal_redirect_to_checkout( $tier_override_raw, $is_trial ? '' : $key );
        return;
    }

    if ( $is_trial ) {
        $intended = pipepay_renewal_get_intended_tier( (int) $license['order_id'] );
        if ( $intended ) {
            // Stage 3 happy path: customer originally clicked a tier card.
            pipepay_renewal_redirect_to_checkout( $intended );
            return;
        }
        // Stage 4 fallback: trial without intent (header / hero / final-CTA path).
        pipepay_renewal_render_tier_picker( $key, $token );
        return;
    }

    // Paid renewal path: same tier as the existing license, with the renewal
    // pointer attached. The renewal-completion hooks at the bottom of this
    // file consume the pointer on payment completion and extend the existing
    // license instead of keeping the new key WCAM mints.
    if ( in_array( $license_product_id, PIPEPAY_RENEWAL_TIER_PRODUCT_IDS, true ) ) {
        pipepay_renewal_redirect_to_checkout( $license_product_id, $key );
        return;
    }

    // Unknown product on the resource record - shouldn't happen with our
    // 4 products, but log and bail safely.
    if ( function_exists( 'error_log' ) ) {
        error_log( 'Pipe Pay renewal: unrecognized product_id ' . $license_product_id . ' on license ' . substr( $key, 0, 6 ) . '...' );
    }
    pipepay_renewal_render_error(
        'License tier not recognized',
        'Your license is associated with an unknown product. Please <a href="' . esc_url( home_url( '/contact' ) ) . '">contact support</a>.',
        500
    );
}

// ── Cart manipulation + redirect ─────────────────────────────────────────────

/**
 * Empty the cart, add the target tier, and redirect to checkout.
 *
 * @param int    $tier_product_id     Paid tier product ID (34, 35, 36).
 * @param string $renewal_for_license Optional license key - present on paid
 *                                    renewals so the renewal-completion hook
 *                                    can extend the existing license.
 *                                    Empty for trial -> paid conversions.
 */
function pipepay_renewal_redirect_to_checkout( int $tier_product_id, string $renewal_for_license = '' ): void {
    if ( ! function_exists( 'WC' ) ) {
        // WC not loaded - shouldn't happen on the renew page (it's a WP page,
        // WC is fully booted by template_redirect), but defensive.
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }
    if ( ! WC()->cart ) {
        wc_load_cart();
    }
    WC()->cart->empty_cart();

    $cart_item_data = [];
    if ( '' !== $renewal_for_license ) {
        $cart_item_data['_pipepay_renewal_for_license'] = $renewal_for_license;
    }
    WC()->cart->add_to_cart( $tier_product_id, 1, 0, [], $cart_item_data );

    wp_safe_redirect( wc_get_checkout_url() );
    exit;
}

// ── Stage 4: tier picker ─────────────────────────────────────────────────────

/**
 * Render the tier selection page for trial customers without intent meta.
 * Each tier card POSTs back to /renew/ with the same key+token plus tier=NN.
 */
function pipepay_renewal_render_tier_picker( string $key, string $token ): void {
    get_header();
    ?>
    <section class="pp-page-hero">
        <div class="pp-container">
            <span class="pp-page-hero__kicker">Choose your tier</span>
            <h1 class="pp-page-title">Pick the license size that fits your store.</h1>
            <p class="pp-page-hero__sub">Your trial is converting. Pick the tier that matches the number of WooCommerce stores you run. You can always upgrade later.</p>
        </div>
    </section>

    <section class="pp-section pp-section--tight">
        <div class="pp-container">
            <div class="pp-pricing-grid">
                <?php
                $tiers = [
                    34 => [
                        'title'    => 'Single Site',
                        'price'    => '$249',
                        'subtitle' => 'For one WooCommerce store.',
                        'features' => [ '1 site activation', '1 year of plugin updates', '1 year of email support' ],
                    ],
                    35 => [
                        'title'    => '5 Sites',
                        'price'    => '$599',
                        'subtitle' => 'For agencies or multi-store owners.',
                        'features' => [ 'Up to 5 site activations', '1 year of plugin updates', '1 year of email support' ],
                        'featured' => true,
                    ],
                    36 => [
                        'title'    => 'Unlimited Sites',
                        'price'    => '$1,199',
                        'subtitle' => 'No activation cap. Run it everywhere.',
                        'features' => [ 'Unlimited site activations', '1 year of plugin updates', '1 year of email support' ],
                    ],
                ];
                foreach ( $tiers as $tid => $t ) {
                    $continue_url = add_query_arg(
                        [
                            'key'   => rawurlencode( $key ),
                            'token' => rawurlencode( $token ),
                            'tier'  => $tid,
                        ],
                        home_url( '/' . PIPEPAY_RENEWAL_PAGE_SLUG . '/' )
                    );
                    $card_class = 'pp-pricing-card' . ( ! empty( $t['featured'] ) ? ' pp-pricing-card--featured' : '' );
                    $btn_class  = ! empty( $t['featured'] ) ? 'pp-btn pp-btn--primary' : 'pp-btn pp-btn--secondary';
                    ?>
                    <div class="<?php echo esc_attr( $card_class ); ?>">
                        <?php if ( ! empty( $t['featured'] ) ) : ?>
                            <span class="pp-pricing-ribbon">Most Popular</span>
                        <?php endif; ?>
                        <h3><?php echo esc_html( $t['title'] ); ?></h3>
                        <p class="pp-price-detail"><?php echo esc_html( $t['subtitle'] ); ?></p>
                        <div class="pp-price"><?php echo esc_html( $t['price'] ); ?><small></small></div>
                        <div class="pp-price-period">per year</div>
                        <ul class="pp-pricing-features">
                            <?php foreach ( $t['features'] as $f ) : ?>
                                <li><?php echo esc_html( $f ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <a class="<?php echo esc_attr( $btn_class ); ?>" href="<?php echo esc_url( $continue_url ); ?>">Continue with <?php echo esc_html( $t['title'] ); ?></a>
                        <p class="pp-cta-skip"><a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">or pay by card with auto-renewal &rarr;</a></p>
                    </div>
                    <?php
                }
                ?>
            </div>
            <p class="pp-pricing-fineprint" style="margin-top:32px;">Cancel any time before your trial ends and you won't be charged. Once your trial converts, all sales are final.</p>
        </div>
    </section>
    <?php
    get_footer();
    exit;
}

// ── Error rendering ──────────────────────────────────────────────────────────

/**
 * Render a friendly error page within site chrome and exit.
 * Sends the matching HTTP status code so monitoring/log-search works.
 */
function pipepay_renewal_render_error( string $title, string $body_html, int $status = 400 ): void {
    status_header( $status );
    get_header();
    ?>
    <section class="pp-page-hero">
        <div class="pp-container">
            <span class="pp-page-hero__kicker">Renewal</span>
            <h1 class="pp-page-title"><?php echo esc_html( $title ); ?></h1>
            <p class="pp-page-hero__sub"><?php echo wp_kses_post( $body_html ); ?></p>
        </div>
    </section>
    <section class="pp-section pp-section--tight">
        <div class="pp-container">
            <p>If you reached this page in error, please <a href="<?php echo esc_url( home_url( '/contact' ) ); ?>">contact support</a> with the URL you used and we'll sort it out.</p>
        </div>
    </section>
    <?php
    get_footer();
    exit;
}

/*
 * ─── Post-deploy steps (one-time) ─────────────────────────────────────────────
 *
 * 1. Create the WordPress page:
 *    sudo -u www-data wp --path=/var/www/pipepay post create \
 *        --post_type=page --post_title="Renew" --post_name="renew" \
 *        --post_status="publish" --post_content="(handled by mu-plugin)"
 *
 * 2. Add the HMAC secret to wp-config.php (above the "stop editing" line):
 *    define( 'PIPEPAY_RENEWAL_HMAC_SECRET', '<64-char random string>' );
 *
 *    Generate one with:
 *    sudo -u www-data wp --path=/var/www/pipepay eval 'echo wp_generate_password(64, false);'
 *
 * 3. Add /renew/ to the Cloudflare Cache Rule "Bypass dynamic + logged-in"
 *    expression alongside the existing /checkout/, /cart/, /my-account/, etc.
 *    The mu-plugin already sends no-store headers; the CF rule is belt-and-
 *    suspenders so token-bearing URLs never get cached at the edge.
 *
 * 4. Test: build a renewal URL for an existing trial license and load it.
 *    sudo -u www-data wp --path=/var/www/pipepay eval 'require_once WPMU_PLUGIN_DIR . "/pipepay-license-renewals.php"; $row = pipepay_renewal_lookup_license("<test_key>"); echo "https://pipepay.app/renew/?key=" . rawurlencode($row["master_api_key"]) . "&token=" . pipepay_renewal_hmac_sign($row["master_api_key"], (int) $row["access_expires"]);'
 *
 * 5. Once the renewal-cadence cron + email work lands (separate to-do in
 *    CLAUDE.md), the email templates will use these same helpers to mint
 *    URLs for outbound reminders.
 */

// ── Cadence cron handler ─────────────────────────────────────────────────────
//
// Daily scan of `wp_wc_am_api_resource` to fire renewal/trial-cadence emails
// at the configured intervals. Idempotent via order postmeta stamps so a
// double-firing cron doesn't double-send.
//
// Schedule: daily at 02:00 UTC via Action Scheduler (already used by WC).
// Hook tag: `pipepay_license_check_renewals` - safe to trigger manually:
//   wp action-scheduler run --hooks=pipepay_license_check_renewals

const PIPEPAY_CADENCE_HOOK = 'pipepay_license_check_renewals';

const PIPEPAY_CADENCE_STAGES = [
    // [ days_from_now, email_id, product_id_filter ('trial' | 'paid'), stamp_key ]
    'trial_t-2'     => [ 'days' =>  2, 'email' => 'WC_Email_PipePay_Trial_Ending_Soon', 'kind' => 'trial', 'stamp' => '_pipepay_renewal_stamp_trial_t-2' ],
    'trial_t+0'     => [ 'days' =>  0, 'email' => 'WC_Email_PipePay_Trial_Ended',       'kind' => 'trial', 'stamp' => '_pipepay_renewal_stamp_trial_t+0' ],
    'paid_t-30'     => [ 'days' => 30, 'email' => 'WC_Email_PipePay_Renewal_30',        'kind' => 'paid',  'stamp' => '_pipepay_renewal_stamp_paid_t-30' ],
    'paid_t-7'      => [ 'days' =>  7, 'email' => 'WC_Email_PipePay_Renewal_7',         'kind' => 'paid',  'stamp' => '_pipepay_renewal_stamp_paid_t-7' ],
    'paid_t-0'      => [ 'days' =>  0, 'email' => 'WC_Email_PipePay_Renewal_Expiry',    'kind' => 'paid',  'stamp' => '_pipepay_renewal_stamp_paid_t-0' ],
    'paid_t+7'      => [ 'days' => -7, 'email' => 'WC_Email_PipePay_Renewal_Grace',     'kind' => 'paid',  'stamp' => '_pipepay_renewal_stamp_paid_t+7' ],
    'paid_t+30'     => [ 'days' =>-30, 'email' => 'WC_Email_PipePay_Renewal_Final',     'kind' => 'paid',  'stamp' => '_pipepay_renewal_stamp_paid_t+30' ],
];

const PIPEPAY_CADENCE_TOLERANCE_DAYS = 1;
const PIPEPAY_CADENCE_FAIL_CAP       = 3;  // skip license after this many consecutive send failures per stage

/**
 * Schedule the daily cron on plugin load. Action Scheduler handles persistence;
 * no extra cron table needed.
 */
add_action( 'init', function () {
    if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
        return;
    }
    if ( false === as_next_scheduled_action( PIPEPAY_CADENCE_HOOK ) ) {
        // Next 02:00 UTC, then every 24h.
        $next = strtotime( 'tomorrow 02:00 UTC' );
        as_schedule_recurring_action( $next, DAY_IN_SECONDS, PIPEPAY_CADENCE_HOOK, [], 'pipepay' );
    }
} );

add_action( PIPEPAY_CADENCE_HOOK, 'pipepay_license_cadence_run' );

/**
 * Main cadence handler. Walks active licenses, matches each against a stage,
 * sends the appropriate email if not already sent.
 */
function pipepay_license_cadence_run(): void {
    global $wpdb;

    // Monthly Stripe-billed products (526/527/528) are excluded: Stripe auto-renews
    // them and emails its own receipts, so "renew your license" emails would be
    // wrong — and their HMAC links would 403 anyway because the pipepay-stripe-subs
    // bridge rewrites access_expires (an HMAC input) on every billing cycle.
    $licenses = $wpdb->get_results(
        "SELECT api_resource_id, master_api_key, product_id, product_title, order_id, user_id, access_expires, active
         FROM {$wpdb->prefix}wc_am_api_resource
         WHERE active = 1 AND access_expires > 0
           AND product_id NOT IN (526, 527, 528)",
        ARRAY_A
    );
    if ( ! $licenses ) {
        return;
    }

    $now = time();
    foreach ( $licenses as $lic ) {
        // Yearly Stripe subscriptions (card lane) renew automatically — "renew your
        // license" emails would be wrong, and their HMAC links break the moment the
        // bridge rewrites access_expires anyway. The bridge stamps per-product user
        // meta pointing at the rows it manages; skip those while the sub is active.
        $stripe_backed_row = (int) get_user_meta( (int) $lic['user_id'], '_pipepay_stripe_api_resource_id_' . (int) $lic['product_id'], true );
        if ( $stripe_backed_row === (int) $lic['api_resource_id']
            && 'active' === get_user_meta( (int) $lic['user_id'], '_pipepay_stripe_subscription_status', true )
            // Belt-and-braces vs a lost cancellation webhook: only trust the
            // 'active' status while the last-known paid period is current
            // (one day of slack for renewal-webhook timing).
            && (int) get_user_meta( (int) $lic['user_id'], '_pipepay_stripe_period_end', true ) > ( $now - DAY_IN_SECONDS ) ) {
            continue;
        }

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
    }
}

/**
 * Send one cadence email if idempotency permits. Stamps on success; bumps a
 * failure counter on failure and gives up after PIPEPAY_CADENCE_FAIL_CAP misses.
 */
function pipepay_cadence_maybe_send( array $lic, string $stage_key, array $stage ): void {
    $order_id = (int) $lic['order_id'];
    if ( ! $order_id ) {
        return;
    }

    $stamp_key = $stage['stamp'];
    $fail_key  = $stamp_key . '_fails';

    // Already sent?
    if ( get_post_meta( $order_id, $stamp_key, true ) ) {
        return;
    }
    // Hit the fail cap?
    if ( (int) get_post_meta( $order_id, $fail_key, true ) >= PIPEPAY_CADENCE_FAIL_CAP ) {
        return;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    $license_data = pipepay_cadence_build_license_data( $lic, $order );
    if ( ! $license_data ) {
        return;
    }

    $emails = WC()->mailer()->get_emails();
    $email  = $emails[ $stage['email'] ] ?? null;
    if ( ! $email ) {
        // Email class not registered (mu-plugin failed to load?) - log + skip.
        if ( function_exists( 'pipepay_log' ) ) {
            pipepay_log( 'error', 'Cadence email class not registered', [ 'email_id' => $stage['email'], 'stage' => $stage_key ] );
        }
        return;
    }

    $sent = (bool) $email->trigger( $license_data );

    if ( $sent ) {
        update_post_meta( $order_id, $stamp_key, time() );
        delete_post_meta( $order_id, $fail_key );
        if ( function_exists( 'pipepay_log' ) ) {
            pipepay_log( 'info', 'Cadence email sent', [ 'stage' => $stage_key, 'order_id' => $order_id, 'recipient_domain' => substr( strrchr( $license_data['recipient'], '@' ), 1 ) ] );
        }
    } else {
        $fails = (int) get_post_meta( $order_id, $fail_key, true ) + 1;
        update_post_meta( $order_id, $fail_key, $fails );
        if ( function_exists( 'pipepay_log' ) ) {
            pipepay_log( 'warning', 'Cadence email send failed', [ 'stage' => $stage_key, 'order_id' => $order_id, 'fail_count' => $fails ] );
        }
    }
}

/**
 * Assemble the context expected by the cadence email classes / templates.
 */
function pipepay_cadence_build_license_data( array $lic, WC_Order $order ): array {
    $license_key   = (string) $lic['master_api_key'];
    $expires       = (int) $lic['access_expires'];
    $expires_dt    = ( new DateTime( '@' . $expires ) )->setTimezone( wp_timezone() );
    $expires_label = wc_format_datetime( $expires_dt, 'F j, Y' );

    $renewal_url = add_query_arg(
        [
            'key'   => rawurlencode( $license_key ),
            'token' => pipepay_renewal_hmac_sign( $license_key, $expires ),
        ],
        home_url( '/' . PIPEPAY_RENEWAL_PAGE_SLUG . '/' )
    );

    return [
        'license_key'   => $license_key,
        'order_id'      => $order->get_id(),
        'recipient'     => $order->get_billing_email(),
        'first_name'    => $order->get_billing_first_name(),
        'tier_name'     => $lic['product_title'] ?: 'Pipe Pay',
        'expires_at'    => $expires,
        'expires_label' => $expires_label,
        'renewal_url'   => $renewal_url,
    ];
}

/**
 * On uninstall, clean up the cron + meta stamps. (mu-plugin doesn't get a
 * traditional uninstall hook - this runs if the file is deleted and the
 * action-scheduler unschedule is best-effort via init guard.)
 */
register_deactivation_hook( __FILE__, function () {
    if ( function_exists( 'as_unschedule_all_actions' ) ) {
        as_unschedule_all_actions( PIPEPAY_CADENCE_HOOK );
    }
} );

/* =========================================================================
 * Renewal completion (2026-06-12) — the missing half of the /renew/ flow.
 *
 * The /renew/ landing above attaches `_pipepay_renewal_for_license` cart-item
 * data; until now nothing persisted it to the order or consumed it, so every
 * renewal minted a brand-new key instead of extending the customer's existing
 * one. These hooks close that: persist the marker onto the order line item,
 * extend the ORIGINAL license +365 days on payment completion, and remove the
 * duplicate key WCAM mints for the renewal order. Same key, fresh expiry.
 *
 * Hook ordering on woocommerce_order_status_completed / _processing:
 *   9   pipepay_renewal_extend             — extend original license, stamp order
 *   10  WCAM WC_AM_Order::update_order     — mints a duplicate key for this order
 *   10  WC transactional emails            — paid-completed.php renewal branch
 *                                            reads the original key (already extended)
 *   999 pipepay_renewal_cleanup_duplicate  — delete the WCAM duplicate
 * ========================================================================= */

add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $cart_item_key, $values ) {
    if ( ! empty( $values['_pipepay_renewal_for_license'] ) ) {
        $item->add_meta_data( '_pipepay_renewal_for_license', sanitize_text_field( (string) $values['_pipepay_renewal_for_license'] ), true );
    }
}, 10, 3 );

function pipepay_renewal_order_license_key( $order ): string {
    foreach ( $order->get_items() as $item ) {
        $key = (string) $item->get_meta( '_pipepay_renewal_for_license', true );
        if ( '' !== $key ) {
            return $key;
        }
    }
    return '';
}

add_action( 'woocommerce_order_status_completed', 'pipepay_renewal_extend', 9 );
add_action( 'woocommerce_order_status_processing', 'pipepay_renewal_extend', 9 );

function pipepay_renewal_extend( $order_id ): void {
    global $wpdb;

    $order = wc_get_order( $order_id );
    if ( ! $order || $order->get_meta( '_pipepay_renewal_extended' ) ) {
        return;
    }

    $license_key = pipepay_renewal_order_license_key( $order );
    if ( '' === $license_key ) {
        return; // not a renewal order
    }

    $table = $wpdb->prefix . 'wc_am_api_resource';
    $row   = $wpdb->get_row( $wpdb->prepare(
        "SELECT api_resource_id, access_expires, order_id FROM {$table} WHERE master_api_key = %s ORDER BY api_resource_id ASC LIMIT 1",
        $license_key
    ) );

    if ( ! $row ) {
        // Original license gone (RTBF, manual delete). The key WCAM mints for this
        // order stands as a fresh license instead — cleanup below skips 'orphan'.
        $order->update_meta_data( '_pipepay_renewal_extended', 'orphan' );
        $order->add_order_note( 'Pipe Pay renewal: original license not found — the new key issued with this order stands.' );
        $order->save();
        error_log( "Pipe Pay renewal: order $order_id references unknown license ..." . substr( $license_key, -4 ) );
        return;
    }

    // +365 days from the later of now / current expiry: early renewals keep their
    // remaining time; lapsed renewals restart from today.
    $new_expires = max( time(), (int) $row->access_expires ) + 365 * DAY_IN_SECONDS;

    $wpdb->update(
        $table,
        [ 'access_expires' => $new_expires, 'active' => 1 ],
        [ 'api_resource_id' => (int) $row->api_resource_id ],
        [ '%d', '%d' ],
        [ '%d' ]
    );

    $order->update_meta_data( '_pipepay_renewal_extended', gmdate( 'c' ) );
    $order->update_meta_data( '_pipepay_renewal_resource_id', (int) $row->api_resource_id );
    // Pre-extension expiry, so a refund of this renewal order can reverse the
    // extension (see pipepay_renewal_reverse_on_refund below).
    $order->update_meta_data( '_pipepay_renewal_original_expires', (int) $row->access_expires );

    // Year-1 cadence stamps live on the ORIGINAL license order and would
    // otherwise suppress every year-2 reminder forever. Fresh year, fresh
    // cadence.
    foreach ( PIPEPAY_CADENCE_STAGES as $stage ) {
        delete_post_meta( (int) $row->order_id, $stage['stamp'] );
        delete_post_meta( (int) $row->order_id, $stage['stamp'] . '_fails' );
    }
    $order->add_order_note( sprintf(
        'Pipe Pay renewal: extended license ...%s to %s (+365 days, same key).',
        substr( $license_key, -4 ),
        gmdate( 'Y-m-d', $new_expires )
    ) );
    $order->save();
}

add_action( 'woocommerce_order_status_completed', 'pipepay_renewal_cleanup_duplicate', 999 );
add_action( 'woocommerce_order_status_processing', 'pipepay_renewal_cleanup_duplicate', 999 );

function pipepay_renewal_cleanup_duplicate( $order_id ): void {
    global $wpdb;

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }
    $stamp = (string) $order->get_meta( '_pipepay_renewal_extended' );
    if ( '' === $stamp || 'orphan' === $stamp ) {
        return; // not a renewal, or original license missing (the new key stands)
    }

    $original_resource_id = (int) $order->get_meta( '_pipepay_renewal_resource_id' );
    if ( ! $original_resource_id ) {
        return;
    }

    // Kestrel's master_api_key is per-USER: the duplicate row WCAM mints for this
    // renewal order shares the original license's key, so the only safe filter is
    // the row id. The original row is keyed to its own (old) order and can never
    // match order_id = this renewal order; the exclusion is belt-and-braces.
    $renewed_product_id = 0;
    foreach ( $order->get_items() as $item ) {
        $renewed_product_id = (int) $item->get_product_id();
        break;
    }

    $table   = $wpdb->prefix . 'wc_am_api_resource';
    $deleted = $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$table} WHERE order_id = %d AND api_resource_id != %d AND product_id = %d",
        $order_id,
        $original_resource_id,
        $renewed_product_id
    ) );
    if ( $deleted ) {
        $order->add_order_note( "Pipe Pay renewal: removed $deleted duplicate license key(s) minted for this renewal order." );
    }
}

// ── Refund reversal (2026-06-12 review finding) ──────────────────────────────
// A full refund of a renewal order must take back the +365-day extension.
// Without this, the cleanup hook above has already removed the row WCAM's own
// refund handler would have acted on, so the customer keeps the extended year
// after getting their money back.

add_action( 'woocommerce_order_status_refunded', 'pipepay_renewal_reverse_on_refund', 20 );

function pipepay_renewal_reverse_on_refund( $order_id ): void {
    global $wpdb;

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }
    $stamp = (string) $order->get_meta( '_pipepay_renewal_extended' );
    if ( '' === $stamp || 'orphan' === $stamp || $order->get_meta( '_pipepay_renewal_reversed' ) ) {
        return;
    }
    $resource_id      = (int) $order->get_meta( '_pipepay_renewal_resource_id' );
    $original_expires = (int) $order->get_meta( '_pipepay_renewal_original_expires' );
    if ( ! $resource_id || ! $original_expires ) {
        return;
    }

    $wpdb->update(
        $wpdb->prefix . 'wc_am_api_resource',
        [ 'access_expires' => $original_expires ],
        [ 'api_resource_id' => $resource_id ],
        [ '%d' ],
        [ '%d' ]
    );

    $order->update_meta_data( '_pipepay_renewal_reversed', gmdate( 'c' ) );
    $order->add_order_note( sprintf(
        'Pipe Pay renewal refund: extension reversed - license expiry restored to %s.',
        gmdate( 'Y-m-d', $original_expires )
    ) );
    $order->save();
}
