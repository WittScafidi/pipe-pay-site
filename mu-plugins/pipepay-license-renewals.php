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
 *         cart-item meta (used by a future order-completion hook to extend
 *         the existing license's access_expires by 365 days instead of
 *         minting a new license - see CLAUDE.md renewal-cadence to-do)
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
    if ( $tier_override_raw && in_array( $tier_override_raw, PIPEPAY_RENEWAL_TIER_PRODUCT_IDS, true ) ) {
        // Pass renewal-for-license only when this is a paid-tier renewal -
        // trial->paid is a NEW license, not an extension of the trial.
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
    // pointer attached. The order-completion hook that extends access_expires
    // is a follow-up (renewal-cadence to-do in CLAUDE.md). This path works
    // today as a normal "buy the same tier" checkout; the future hook just
    // upgrades it to "extend the existing license."
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
 *                                    renewals so a future order-completion
 *                                    hook can extend the existing license.
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
