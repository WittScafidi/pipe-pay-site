<?php
/**
 * Plugin Name: Pipe Pay - Stripe Subscriptions Bridge
 * Description: Bridges Stripe subscription events to WCAM license issuance/renewal/revocation. Provides /pricing Checkout + /my-account Customer Portal endpoints.
 * Version:     0.7.1
 * Author:      Pipe Pay
 * License:     GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PIPEPAY_STRIPE_SUBS_VERSION', '0.7.1' );
define( 'PIPEPAY_STRIPE_API_BASE', 'https://api.stripe.com' );
define( 'PIPEPAY_STRIPE_WEBHOOK_TOLERANCE', 300 ); // 5 minutes replay protection

/**
 * Billing mode, derived from the secret key prefix. 'live' iff key starts sk_live_.
 * The webhook handler rejects events whose livemode flag disagrees with this.
 */
function pipepay_stripe_subs_get_mode() {
	return strpos( pipepay_stripe_subs_get_secret_key(), 'sk_live_' ) === 0 ? 'live' : 'test';
}

/**
 * Stripe Price ID → tier config, selected by billing mode.
 *
 * Price IDs come from wp-config.php constants (PIPEPAY_STRIPE_PRICE_SINGLE / _FIVE /
 * _UNLIM) so the live flip is a single wp-config edit shared by this plugin and the
 * pricing templates. Constants default to the test-mode IDs when undefined.
 *
 * Live since 2026-06-12 — all five constants set in wp-config.php.
 */
function pipepay_stripe_subs_get_config() {
	$single = defined( 'PIPEPAY_STRIPE_PRICE_SINGLE' ) ? PIPEPAY_STRIPE_PRICE_SINGLE : 'price_1TgPw3GFSkcp1uiX7linrCwn';
	$five   = defined( 'PIPEPAY_STRIPE_PRICE_FIVE' ) ? PIPEPAY_STRIPE_PRICE_FIVE : 'price_1TgPw5GFSkcp1uiXU49mFdS3';
	$unlim  = defined( 'PIPEPAY_STRIPE_PRICE_UNLIM' ) ? PIPEPAY_STRIPE_PRICE_UNLIM : 'price_1TgPwAGFSkcp1uiXF9Tasn8m';

	$config = array(
		$single => array(
			'tier'          => 'single',
			'wc_product_id' => 526,
			'amount_cents'  => 3500,
			'label'         => 'Single Site Monthly',
		),
		$five => array(
			'tier'          => 'five',
			'wc_product_id' => 527,
			'amount_cents'  => 6500,
			'label'         => '5 Sites Monthly',
		),
		$unlim => array(
			'tier'          => 'unlimited',
			'wc_product_id' => 528,
			'amount_cents'  => 12900,
			'label'         => 'Unlimited Monthly',
		),
	);

	// Yearly auto-renew subscriptions for the ANNUAL tiers (card lane). Same
	// lifecycle as monthly — when the subscription ends, the license expires and
	// the gateway stops. The payment-app lane (one-time purchase via WC checkout,
	// manual renewal, lapse = banner only) uses the same WC products but never
	// touches this bridge.
	if ( defined( 'PIPEPAY_STRIPE_PRICE_SINGLE_YR' ) ) {
		$config[ PIPEPAY_STRIPE_PRICE_SINGLE_YR ] = array(
			'tier'          => 'single',
			'wc_product_id' => 34,
			'amount_cents'  => 29700,
			'label'         => 'Single Site Annual',
		);
	}
	if ( defined( 'PIPEPAY_STRIPE_PRICE_FIVE_YR' ) ) {
		$config[ PIPEPAY_STRIPE_PRICE_FIVE_YR ] = array(
			'tier'          => 'five',
			'wc_product_id' => 35,
			'amount_cents'  => 49700,
			'label'         => '5 Sites Annual',
		);
	}
	if ( defined( 'PIPEPAY_STRIPE_PRICE_UNLIM_YR' ) ) {
		$config[ PIPEPAY_STRIPE_PRICE_UNLIM_YR ] = array(
			'tier'          => 'unlimited',
			'wc_product_id' => 36,
			'amount_cents'  => 99700,
			'label'         => 'Unlimited Annual',
		);
	}

	// Guard against a live key paired with test Price IDs (or vice versa): a price
	// minted in one mode does not exist in the other, so checkouts would 400. Refuse
	// loudly instead of silently breaking the monthly CTAs.
	if ( pipepay_stripe_subs_get_mode() === 'live' ) {
		foreach ( array_keys( $config ) as $price_id ) {
			if ( $price_id === 'price_1TgPw3GFSkcp1uiX7linrCwn' || $price_id === 'price_1TgPw5GFSkcp1uiXU49mFdS3' || $price_id === 'price_1TgPwAGFSkcp1uiXF9Tasn8m' ) {
				return array(); // live key + test prices = misconfigured; admin notice below.
			}
		}
	}

	return $config;
}

// Surface key/price misconfiguration to the admin instead of failing silently.
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! pipepay_stripe_subs_get_secret_key() || ! pipepay_stripe_subs_get_webhook_secret() ) {
		echo '<div class="notice notice-error"><p><strong>Pipe Pay Stripe Subscriptions:</strong> PIPEPAY_STRIPE_SECRET_KEY and/or PIPEPAY_STRIPE_WEBHOOK_SECRET are not set in wp-config.php. Monthly subscriptions will not work.</p></div>';
	} elseif ( ! pipepay_stripe_subs_get_config() ) {
		echo '<div class="notice notice-error"><p><strong>Pipe Pay Stripe Subscriptions:</strong> the secret key is live-mode but the configured Price IDs are test-mode. Set PIPEPAY_STRIPE_PRICE_SINGLE / _FIVE / _UNLIM in wp-config.php to the live Price IDs.</p></div>';
	}
} );

function pipepay_stripe_subs_get_secret_key() {
	return defined( 'PIPEPAY_STRIPE_SECRET_KEY' ) ? PIPEPAY_STRIPE_SECRET_KEY : '';
}

function pipepay_stripe_subs_get_webhook_secret() {
	return defined( 'PIPEPAY_STRIPE_WEBHOOK_SECRET' ) ? PIPEPAY_STRIPE_WEBHOOK_SECRET : '';
}

/**
 * Reverse lookup: WC product id → (price_id, config entry). Used by the WC
 * payment gateway to map an order line to the matching Stripe subscription.
 *
 * @return array|null [ price_id, config ] or null.
 */
function pipepay_stripe_subs_price_for_product( $product_id ) {
	foreach ( pipepay_stripe_subs_get_config() as $price_id => $cfg ) {
		if ( (int) $cfg['wc_product_id'] === (int) $product_id ) {
			return array( $price_id, $cfg );
		}
	}
	return null;
}

/* -------------------------------------------------------------------------
 * WC payment gateway: "Credit/Debit Card" (auto-renewing Stripe subscription)
 *
 * Appears in the normal WooCommerce payment-options list next to Pipe Pay.
 * process_payment() creates a Stripe Checkout session carrying the WC order
 * id in metadata and redirects the customer to Stripe's hosted payment page.
 * The checkout.session.completed webhook then completes THIS order (no
 * duplicate bridge order) and WCAM issues the license from it.
 * ------------------------------------------------------------------------- */

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	class PipePay_Stripe_Sub_Gateway extends WC_Payment_Gateway {
		public function __construct() {
			$this->id                 = 'pipepay_stripe_sub';
			$this->method_title       = 'Credit/Debit Card (Stripe subscription)';
			$this->method_description = 'Auto-renewing card billing for Pipe Pay license tiers via Stripe Checkout.';
			$this->has_fields         = false;
			$this->title              = 'Credit/Debit Card';
			$this->description        = 'Auto-renews through Stripe. Cancel anytime from your billing portal. You will be sent to Stripe\'s secure payment page.';
			$this->supports           = array( 'products' );
			// No admin settings: availability is driven entirely by the cart
			// contents + the configured Stripe prices.
			$this->enabled = 'yes';
		}

		/** Available only when the cart/order holds a tier mapped to a Stripe price. */
		public function is_available() {
			if ( ! pipepay_stripe_subs_get_secret_key() ) {
				return false;
			}
			// Pay-for-order page: inspect the order.
			if ( function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page() ) {
				$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
				if ( $order ) {
					foreach ( $order->get_items() as $item ) {
						if ( pipepay_stripe_subs_price_for_product( $item->get_product_id() ) ) {
							return true;
						}
					}
				}
				return false;
			}
			if ( function_exists( 'WC' ) && WC()->cart ) {
				foreach ( WC()->cart->get_cart() as $cart_item ) {
					if ( pipepay_stripe_subs_price_for_product( $cart_item['product_id'] ) ) {
						return true;
					}
				}
			}
			return false;
		}

		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );

			$mapped = null;
			foreach ( $order->get_items() as $item ) {
				$mapped = pipepay_stripe_subs_price_for_product( $item->get_product_id() );
				if ( $mapped ) {
					break;
				}
			}
			if ( ! $mapped ) {
				wc_add_notice( 'This payment method is not available for the items in your cart.', 'error' );
				return array( 'result' => 'failure' );
			}
			list( $price_id ) = $mapped;

			try {
				$session = pipepay_stripe_subs_api_post( '/v1/checkout/sessions', array(
					'mode'                       => 'subscription',
					'currency'                   => 'usd',
					'line_items[0][price]'       => $price_id,
					'line_items[0][quantity]'    => 1,
					'customer_email'             => $order->get_billing_email(),
					'client_reference_id'        => (string) $order_id,
					'metadata[wc_order_id]'      => (string) $order_id,
					'subscription_data[metadata][wc_order_id]' => (string) $order_id,
					'allow_promotion_codes'      => 'true',
					'billing_address_collection' => 'auto',
					'success_url'                => home_url( '/my-account/?stripe_success=1&session_id={CHECKOUT_SESSION_ID}' ),
					'cancel_url'                 => wc_get_checkout_url(),
				) );
			} catch ( Throwable $e ) {
				pipepay_stripe_subs_log( 'gateway: session create failed for order ' . $order_id . ': ' . $e->getMessage(), 'error' );
				wc_add_notice( 'Could not start the card payment. Please try again or choose another payment method.', 'error' );
				return array( 'result' => 'failure' );
			}

			$order->update_meta_data( '_pipepay_stripe_session_id', $session['id'] ?? '' );
			$order->update_status( 'pending', 'Awaiting Stripe Checkout payment.' );
			$order->save();

			return array(
				'result'   => 'success',
				'redirect' => $session['url'],
			);
		}
	}

	add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {
		$gateways[] = 'PipePay_Stripe_Sub_Gateway';
		return $gateways;
	} );
} );

// Block Checkout integration: register the gateway with the blocks payment
// registry via an inline script (the bridge is a single-file plugin).
add_action( 'woocommerce_blocks_payment_method_type_registration', function ( $registry ) {
	if ( ! class_exists( 'Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType' ) ) {
		return;
	}

	$integration = new class extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
		protected $name = 'pipepay_stripe_sub';

		public function initialize() {}

		public function is_active() {
			$gateways = WC()->payment_gateways()->payment_gateways();
			return isset( $gateways['pipepay_stripe_sub'] ) && $gateways['pipepay_stripe_sub']->is_available();
		}

		public function get_payment_method_script_handles() {
			if ( ! wp_script_is( 'pipepay-stripe-sub-blocks', 'registered' ) ) {
				wp_register_script( 'pipepay-stripe-sub-blocks', '', array( 'wc-blocks-registry', 'wp-element', 'wp-html-entities' ), PIPEPAY_STRIPE_SUBS_VERSION, true );
				wp_add_inline_script( 'pipepay-stripe-sub-blocks', '
( function () {
	var settings = window.wc.wcSettings.getSetting( "pipepay_stripe_sub_data", {} );
	var label    = window.wp.htmlEntities.decodeEntities( settings.title || "Credit/Debit Card" );
	var content  = window.wp.htmlEntities.decodeEntities( settings.description || "" );
	window.wc.wcBlocksRegistry.registerPaymentMethod( {
		name: "pipepay_stripe_sub",
		label: label,
		ariaLabel: label,
		content: window.wp.element.createElement( "div", null, content ),
		edit: window.wp.element.createElement( "div", null, content ),
		canMakePayment: function () { return true; },
		supports: { features: ( settings.supports || [ "products" ] ) }
	} );
} )();
' );
			}
			return array( 'pipepay-stripe-sub-blocks' );
		}

		public function get_payment_method_data() {
			$gateways = WC()->payment_gateways()->payment_gateways();
			$gw       = $gateways['pipepay_stripe_sub'] ?? null;
			return array(
				'title'       => $gw ? $gw->title : 'Credit/Debit Card',
				'description' => $gw ? $gw->description : '',
				'supports'    => array( 'products' ),
			);
		}
	};

	$registry->register( $integration );
} );

/* -------------------------------------------------------------------------
 * REST endpoints
 * ------------------------------------------------------------------------- */

add_action( 'rest_api_init', function () {
	register_rest_route( 'pipepay-stripe-subs/v1', '/webhook', array(
		'methods'             => 'POST',
		'callback'            => 'pipepay_stripe_subs_handle_webhook',
		'permission_callback' => '__return_true',
	) );

	register_rest_route( 'pipepay-stripe-subs/v1', '/checkout', array(
		'methods'             => 'POST',
		'callback'            => 'pipepay_stripe_subs_create_checkout_session',
		'permission_callback' => '__return_true',
	) );

	register_rest_route( 'pipepay-stripe-subs/v1', '/portal', array(
		'methods'             => 'POST',
		'callback'            => 'pipepay_stripe_subs_create_portal_session',
		'permission_callback' => function ( WP_REST_Request $request ) {
			// Logged-in + a valid wp_rest nonce (closes the formal CSRF gap;
			// any future portal button must send X-WP-Nonce).
			return is_user_logged_in()
				&& false !== wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' );
		},
	) );

	register_rest_route( 'pipepay-stripe-subs/v1', '/healthcheck', array(
		'methods'             => 'GET',
		'callback'            => function () {
			$out = array(
				'ok'              => true,
				'version'         => PIPEPAY_STRIPE_SUBS_VERSION,
				'mode'            => pipepay_stripe_subs_get_mode(),
				'has_secret_key'  => pipepay_stripe_subs_get_secret_key() ? true : false,
				'has_webhook_key' => pipepay_stripe_subs_get_webhook_secret() ? true : false,
			);
			// Price IDs are admin-only: public disclosure aids /checkout abuse.
			if ( current_user_can( 'manage_options' ) ) {
				$out['configured_prices'] = array_keys( pipepay_stripe_subs_get_config() );
			}
			return $out;
		},
		'permission_callback' => '__return_true',
	) );
} );

/* -------------------------------------------------------------------------
 * Webhook handler
 * ------------------------------------------------------------------------- */

function pipepay_stripe_subs_handle_webhook( WP_REST_Request $request ) {
	$payload    = $request->get_body();
	$sig_header = $request->get_header( 'stripe_signature' );

	if ( ! $payload || ! $sig_header ) {
		return new WP_REST_Response( array( 'error' => 'missing payload or signature' ), 400 );
	}

	if ( ! pipepay_stripe_subs_verify_signature( $payload, $sig_header ) ) {
		pipepay_stripe_subs_log( 'rejected webhook: invalid signature' );
		return new WP_REST_Response( array( 'error' => 'invalid signature' ), 400 );
	}

	$event = json_decode( $payload, true );
	if ( ! $event || empty( $event['id'] ) || empty( $event['type'] ) ) {
		return new WP_REST_Response( array( 'error' => 'malformed event' ), 400 );
	}

	// Livemode guard: a signed test-mode event must never mutate live data (and vice
	// versa). Critical during the test→live flip window.
	$expect_live = ( pipepay_stripe_subs_get_mode() === 'live' );
	if ( (bool) ( $event['livemode'] ?? false ) !== $expect_live ) {
		pipepay_stripe_subs_log( 'rejected webhook: livemode mismatch (event=' . $event['id'] . ')' );
		return new WP_REST_Response( array( 'error' => 'livemode mismatch' ), 400 );
	}

	$event_id     = sanitize_key( $event['id'] );
	$durable_key  = 'pipepay_stripe_evt_' . $event_id;
	$inflight_key = 'pipepay_stripe_evt_inflight_' . $event_id;

	// Durable idempotency: this event was fully processed already, no-op.
	if ( get_transient( $durable_key ) ) {
		return new WP_REST_Response( array( 'ok' => true, 'note' => 'already processed' ), 200 );
	}

	// Atomic in-flight claim. wp_cache_add() is atomic on persistent object caches
	// (Redis/Memcached); falls back to per-request memory on stock WP but the durable
	// transient below + the per-handler order/license existence checks protect us.
	if ( ! wp_cache_add( $inflight_key, 1, 'pipepay_stripe_subs', 60 ) ) {
		// Another worker already claimed this event id. Return 200 so Stripe stops
		// retrying immediately; the worker holding the claim will mark durable on success.
		return new WP_REST_Response( array( 'ok' => true, 'note' => 'in flight' ), 200 );
	}

	try {
		switch ( $event['type'] ) {
			case 'checkout.session.completed':
				pipepay_stripe_subs_handle_checkout_completed( $event['data']['object'] );
				break;
			case 'invoice.payment_succeeded':
				pipepay_stripe_subs_handle_invoice_paid( $event['data']['object'] );
				break;
			case 'customer.subscription.updated':
				pipepay_stripe_subs_handle_subscription_updated( $event['data']['object'] );
				break;
			case 'customer.subscription.deleted':
				pipepay_stripe_subs_handle_subscription_deleted( $event['data']['object'] );
				break;
			case 'invoice.payment_failed':
				pipepay_stripe_subs_handle_invoice_failed( $event['data']['object'] );
				break;
			case 'charge.refunded':
				pipepay_stripe_subs_handle_charge_refunded( $event['data']['object'] );
				break;
			default:
				// Unhandled events still 200 OK so Stripe stops retrying.
				break;
		}
	} catch ( Throwable $e ) {
		pipepay_stripe_subs_log( 'event ' . $event['id'] . ' (' . $event['type'] . ') failed: ' . $e->getMessage(), 'error' );
		// Do NOT mark the durable key — Stripe will retry. The in-flight cache key
		// expires in 60s on its own, freeing the claim for the next retry attempt.
		// Generic error response: do NOT leak $e->getMessage() (logged by Stripe dashboard).
		return new WP_REST_Response( array( 'error' => 'internal error' ), 500 );
	}

	// Success: mark durable so future Stripe retries early-return.
	set_transient( $durable_key, 1, WEEK_IN_SECONDS );
	wp_cache_delete( $inflight_key, 'pipepay_stripe_subs' );
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * Verify Stripe signature header.
 * Stripe signs the payload with HMAC-SHA256(timestamp + '.' + payload).
 */
function pipepay_stripe_subs_verify_signature( $payload, $sig_header ) {
	$secret = pipepay_stripe_subs_get_webhook_secret();
	if ( ! $secret ) {
		return false;
	}

	$parts = array();
	foreach ( explode( ',', $sig_header ) as $kv ) {
		$piece = explode( '=', $kv, 2 );
		if ( count( $piece ) === 2 ) {
			$parts[ trim( $piece[0] ) ][] = trim( $piece[1] );
		}
	}

	if ( empty( $parts['t'] ) || empty( $parts['v1'] ) ) {
		return false;
	}

	$timestamp      = $parts['t'][0];
	$signed_payload = $timestamp . '.' . $payload;
	$expected       = hash_hmac( 'sha256', $signed_payload, $secret );

	$matched = false;
	foreach ( $parts['v1'] as $candidate ) {
		if ( hash_equals( $expected, $candidate ) ) {
			$matched = true;
			break;
		}
	}
	if ( ! $matched ) {
		return false;
	}

	// Replay protection.
	if ( abs( time() - intval( $timestamp ) ) > PIPEPAY_STRIPE_WEBHOOK_TOLERANCE ) {
		return false;
	}
	return true;
}

/* -------------------------------------------------------------------------
 * Event handlers
 * ------------------------------------------------------------------------- */

/**
 * checkout.session.completed → initial subscription created.
 * Create-or-get WC user, store Stripe IDs in user meta, create initial WC order
 * (status=completed → WCAM auto-issues license via woocommerce_order_status_completed),
 * fire pipepay_stripe_subs_subscription_started for WCAM expiry sync.
 */
function pipepay_stripe_subs_handle_checkout_completed( $session ) {
	if ( ( $session['mode'] ?? '' ) !== 'subscription' ) {
		return;
	}

	$email             = $session['customer_details']['email'] ?? ( $session['customer_email'] ?? '' );
	$stripe_customer_id = $session['customer'] ?? '';
	$stripe_sub_id     = $session['subscription'] ?? '';

	if ( ! $email || ! $stripe_customer_id || ! $stripe_sub_id ) {
		throw new Exception( 'Missing email/customer/subscription in checkout session' );
	}

	// Sessions created by the WC payment gateway carry the order id: complete
	// THAT order instead of creating a bridge order.
	$wc_order_id = (int) ( $session['metadata']['wc_order_id'] ?? 0 );
	if ( $wc_order_id ) {
		pipepay_stripe_subs_complete_gateway_order( $wc_order_id, $email, $stripe_customer_id, $stripe_sub_id );
		return;
	}

	// Fetch subscription to read price + current_period_end.
	$sub = pipepay_stripe_subs_api_get( '/v1/subscriptions/' . rawurlencode( $stripe_sub_id ) );
	if ( empty( $sub['items']['data'][0]['price']['id'] ) ) {
		throw new Exception( 'No price on subscription ' . $stripe_sub_id );
	}

	$price_id   = $sub['items']['data'][0]['price']['id'];
	$period_end = pipepay_stripe_subs_extract_period_end( $sub );
	$config     = pipepay_stripe_subs_get_config();
	if ( ! isset( $config[ $price_id ] ) ) {
		throw new Exception( "Unknown Stripe price: $price_id" );
	}
	$tier_config = $config[ $price_id ];

	if ( ! $tier_config['wc_product_id'] ) {
		throw new Exception( 'wc_product_id not configured for price ' . $price_id . ' — update plugin config' );
	}

	// Get-or-create WC user.
	$user_id = email_exists( $email );
	if ( ! $user_id ) {
		$user_id = wc_create_new_customer( $email, '', wp_generate_password( 16 ), array(
			'first_name' => $session['customer_details']['name'] ?? '',
		) );
		if ( is_wp_error( $user_id ) ) {
			// Race-safe without a lock: if a concurrent webhook created the user between
			// our email_exists check and the insert, the insert fails — re-check and use it.
			$user_id = email_exists( $email );
			if ( ! $user_id ) {
				throw new Exception( 'User creation failed' );
			}
		}
	}

	// Two-tab guard: if the user already has a DIFFERENT active subscription (double
	// checkout from two tabs, or re-subscribing while the old sub still runs), cancel
	// the old one in Stripe so it doesn't bill forever as an orphan. Overwriting the
	// sub-id meta below makes the old sub unfindable, so its deletion webhook
	// harmlessly no-ops — the license from THIS checkout is unaffected.
	$prior_sub_id = get_user_meta( $user_id, '_pipepay_stripe_subscription_id', true );
	if ( $prior_sub_id && $prior_sub_id !== $stripe_sub_id
		&& get_user_meta( $user_id, '_pipepay_stripe_subscription_status', true ) === 'active' ) {
		try {
			pipepay_stripe_subs_api_delete( '/v1/subscriptions/' . rawurlencode( $prior_sub_id ) );
			pipepay_stripe_subs_log( "canceled orphaned prior sub $prior_sub_id for user $user_id (superseded by $stripe_sub_id)" );
		} catch ( Throwable $e ) {
			// Already-canceled subs 404 here; anything else is logged for manual follow-up.
			pipepay_stripe_subs_log( "could not cancel prior sub $prior_sub_id: " . $e->getMessage() );
		}
	}

	// Persist Stripe IDs on user.
	update_user_meta( $user_id, '_pipepay_stripe_customer_id', $stripe_customer_id );
	update_user_meta( $user_id, '_pipepay_stripe_subscription_id', $stripe_sub_id );
	update_user_meta( $user_id, '_pipepay_stripe_price_id', $price_id );
	update_user_meta( $user_id, '_pipepay_stripe_subscription_status', 'active' );
	update_user_meta( $user_id, '_pipepay_stripe_period_end', $period_end );

	// Idempotency pre-check: if a prior webhook attempt created the order but failed
	// downstream (sync, meta, etc.), Stripe will retry. Find the existing order so
	// retry doesn't create a duplicate; re-attempt sync to recover from the failure.
	$existing_order_id = pipepay_stripe_subs_find_order_by_subscription( $stripe_sub_id );
	if ( $existing_order_id ) {
		pipepay_stripe_subs_log( "checkout retry: order $existing_order_id already exists for sub $stripe_sub_id; re-syncing only" );
		$api_resource_id = pipepay_stripe_subs_sync_wcam_for_order( $existing_order_id, $period_end );
		if ( $api_resource_id ) {
			pipepay_stripe_subs_set_api_resource_meta( $user_id, $tier_config['wc_product_id'], $api_resource_id );
		}
		return;
	}

	// Create initial WC order (completed → triggers WCAM license issuance via woocommerce_order_status_completed).
	$order = pipepay_stripe_subs_create_order( $user_id, $email, $session['customer_details']['name'] ?? '', $tier_config, $stripe_sub_id, $session['invoice'] ?? '', 'initial' );

	// WCAM has now created an api_resource row keyed to this order. Sync its access_expires to Stripe's period_end
	// and remember the row ID so future renewal/cancel events can update it directly.
	$api_resource_id = pipepay_stripe_subs_sync_wcam_for_order( $order->get_id(), $period_end );
	if ( $api_resource_id ) {
		pipepay_stripe_subs_set_api_resource_meta( $user_id, $tier_config['wc_product_id'], $api_resource_id );

		// Re-subscribe cleanup: superseded inactive rows for the same (user, product)
		// would show as confusing dead keys on /my-account/api-keys/. The WC orders
		// remain as the financial audit trail.
		global $wpdb;
		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}wc_am_api_resource WHERE user_id = %d AND product_id = %d AND active = 0 AND api_resource_id != %d",
			$user_id, $tier_config['wc_product_id'], $api_resource_id
		) );
		if ( $deleted ) {
			pipepay_stripe_subs_log( "removed $deleted superseded inactive license row(s) for user $user_id product {$tier_config['wc_product_id']}" );
		}
	}

	pipepay_stripe_subs_log( "initial sub for $email (user $user_id, order " . $order->get_id() . ", price $price_id, api_resource $api_resource_id)" );
}

/**
 * Complete a WC order placed through the PipePay_Stripe_Sub_Gateway once its
 * Stripe Checkout session has been paid. Mirrors the bridge-order path:
 * user wiring, payment_complete (→ WCAM mints the license at priority 10),
 * access_expires sync, per-product meta, superseded-row cleanup.
 */
function pipepay_stripe_subs_complete_gateway_order( $order_id, $email, $stripe_customer_id, $stripe_sub_id ) {
	global $wpdb;

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		pipepay_stripe_subs_log( "gateway order $order_id not found for sub $stripe_sub_id", 'error' );
		return;
	}

	// Idempotency: Stripe retries re-run the sync only.
	$already = (string) $order->get_meta( '_pipepay_stripe_subscription_id' );

	$sub = pipepay_stripe_subs_api_get( '/v1/subscriptions/' . rawurlencode( $stripe_sub_id ) );
	if ( empty( $sub['items']['data'][0]['price']['id'] ) ) {
		throw new Exception( 'No price on subscription ' . $stripe_sub_id );
	}
	$price_id   = $sub['items']['data'][0]['price']['id'];
	$period_end = pipepay_stripe_subs_extract_period_end( $sub );
	$config     = pipepay_stripe_subs_get_config();
	if ( ! isset( $config[ $price_id ] ) ) {
		throw new Exception( "Unknown Stripe price: $price_id" );
	}
	$tier_config = $config[ $price_id ];

	// Guest checkout: attach (or create) the WP user so WCAM has an owner.
	$user_id = (int) $order->get_customer_id();
	if ( ! $user_id ) {
		$user_id = (int) email_exists( $email );
		if ( ! $user_id ) {
			$user_id = wc_create_new_customer( $email, '', wp_generate_password( 16 ), array(
				'first_name' => $order->get_billing_first_name(),
			) );
			if ( is_wp_error( $user_id ) ) {
				$user_id = (int) email_exists( $email );
				if ( ! $user_id ) {
					throw new Exception( 'User creation failed' );
				}
			}
		}
		$order->set_customer_id( $user_id );
	}

	// Two-tab / re-subscribe guard: cancel a different still-active prior sub.
	$prior_sub_id = get_user_meta( $user_id, '_pipepay_stripe_subscription_id', true );
	if ( $prior_sub_id && $prior_sub_id !== $stripe_sub_id
		&& get_user_meta( $user_id, '_pipepay_stripe_subscription_status', true ) === 'active' ) {
		try {
			pipepay_stripe_subs_api_delete( '/v1/subscriptions/' . rawurlencode( $prior_sub_id ) );
			pipepay_stripe_subs_log( "canceled orphaned prior sub $prior_sub_id for user $user_id (superseded by $stripe_sub_id)" );
		} catch ( Throwable $e ) {
			pipepay_stripe_subs_log( "could not cancel prior sub $prior_sub_id: " . $e->getMessage() );
		}
	}

	update_user_meta( $user_id, '_pipepay_stripe_customer_id', $stripe_customer_id );
	update_user_meta( $user_id, '_pipepay_stripe_subscription_id', $stripe_sub_id );
	update_user_meta( $user_id, '_pipepay_stripe_price_id', $price_id );
	update_user_meta( $user_id, '_pipepay_stripe_subscription_status', 'active' );
	update_user_meta( $user_id, '_pipepay_stripe_period_end', $period_end );

	$order->update_meta_data( '_pipepay_stripe_subscription_id', $stripe_sub_id );
	$order->update_meta_data( '_pipepay_stripe_event_type', 'initial' );
	$order->save();

	if ( ! $already && ! $order->is_paid() ) {
		// payment_complete → completed (virtual+downloadable) → WCAM mints.
		$order->payment_complete( $stripe_sub_id );
	}

	$api_resource_id = pipepay_stripe_subs_sync_wcam_for_order( $order_id, $period_end );
	if ( $api_resource_id ) {
		pipepay_stripe_subs_set_api_resource_meta( $user_id, $tier_config['wc_product_id'], $api_resource_id );
		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}wc_am_api_resource WHERE user_id = %d AND product_id = %d AND active = 0 AND api_resource_id != %d",
			$user_id, $tier_config['wc_product_id'], $api_resource_id
		) );
		if ( $deleted ) {
			pipepay_stripe_subs_log( "removed $deleted superseded inactive license row(s) for user $user_id product {$tier_config['wc_product_id']}" );
		}
	}

	pipepay_stripe_subs_log( "gateway sub for $email (user $user_id, order $order_id, price $price_id, api_resource $api_resource_id)" );
}

/**
 * invoice.payment_succeeded → either a same-tier renewal OR a tier change.
 *
 * Same tier (billing_reason=subscription_cycle, or subscription_update with unchanged price):
 *   direct UPDATE on wp_wc_am_api_resource to extend access_expires. No new order.
 *
 * Tier change (subscription_update with a new price_id):
 *   deactivate the old tier's api_resource row, create a new WC order for the new tier
 *   (which triggers WCAM to issue a license against the new product), sync access_expires
 *   to the new period_end, repoint user meta to the new row.
 *
 * Skips subscription_create — that path is handled by checkout.session.completed.
 */
function pipepay_stripe_subs_handle_invoice_paid( $invoice ) {
	$billing_reason = $invoice['billing_reason'] ?? '';

	if ( $billing_reason === 'subscription_create' ) {
		return; // handled by checkout.session.completed
	}
	if ( $billing_reason !== 'subscription_cycle' && $billing_reason !== 'subscription_update' ) {
		return;
	}

	// Stripe API 2025+ moved invoice.subscription to invoice.parent.subscription_details.subscription.
	// Fall back to the legacy root field for older API versions.
	$stripe_sub_id = $invoice['parent']['subscription_details']['subscription']
		?? $invoice['subscription']
		?? '';
	if ( ! $stripe_sub_id ) {
		return;
	}

	$user = pipepay_stripe_subs_find_user_by_subscription( $stripe_sub_id );
	if ( ! $user ) {
		// Unknown sub: log + return 200 so Stripe does not enter a 72h retry loop on
		// a sub we have no record of (e.g. created on a sibling environment, manually
		// imported, or the DB was rolled back). Caller marks durable on return.
		pipepay_stripe_subs_log( "renewal for unknown sub $stripe_sub_id (billing_reason=$billing_reason); skipping" );
		return;
	}

	// Read price + period_end from the live subscription object, NOT from invoice.lines.
	// On a tier-change invoice, lines[0] can be the proration credit for the OLD price.
	$sub        = pipepay_stripe_subs_api_get( '/v1/subscriptions/' . rawurlencode( $stripe_sub_id ) );
	$price_id   = $sub['items']['data'][0]['price']['id'] ?? '';
	$period_end = pipepay_stripe_subs_extract_period_end( $sub );

	if ( ! $price_id ) {
		throw new Exception( "No price on subscription $stripe_sub_id" );
	}

	$config = pipepay_stripe_subs_get_config();
	if ( ! isset( $config[ $price_id ] ) ) {
		throw new Exception( "Renewal: unknown price $price_id" );
	}
	$tier_config = $config[ $price_id ];

	update_user_meta( $user->ID, '_pipepay_stripe_period_end', $period_end );
	update_user_meta( $user->ID, '_pipepay_stripe_subscription_status', 'active' );

	$previous_price_id = get_user_meta( $user->ID, '_pipepay_stripe_price_id', true );

	if ( $previous_price_id && $previous_price_id !== $price_id ) {
		pipepay_stripe_subs_apply_tier_change( $user, $stripe_sub_id, $previous_price_id, $price_id, $period_end, $invoice['id'] ?? '' );
		return;
	}

	// Same-tier renewal: extend the existing license in place.
	$api_resource_id = pipepay_stripe_subs_extend_license( $user->ID, $tier_config['wc_product_id'], $period_end );
	pipepay_stripe_subs_log( "renewal for {$user->user_email} (sub $stripe_sub_id, api_resource $api_resource_id, period_end $period_end)" );
}

/**
 * customer.subscription.updated → catches tier changes the moment they happen.
 *
 * The Customer Portal's default proration mode (create_prorations) defers the
 * proration invoice to the next billing cycle, so waiting for
 * invoice.payment_succeeded would delay the customer's tier change by up to a
 * month. This handler applies the change immediately; when the deferred invoice
 * later arrives, the price meta already matches and it takes the extend path.
 */
function pipepay_stripe_subs_handle_subscription_updated( $sub ) {
	$stripe_sub_id = $sub['id'] ?? '';
	if ( ! $stripe_sub_id || ( $sub['status'] ?? '' ) !== 'active' ) {
		return;
	}

	$user = pipepay_stripe_subs_find_user_by_subscription( $stripe_sub_id );
	if ( ! $user ) {
		pipepay_stripe_subs_log( "subscription.updated for unknown sub $stripe_sub_id; skipping" );
		return;
	}

	$price_id          = $sub['items']['data'][0]['price']['id'] ?? '';
	$previous_price_id = get_user_meta( $user->ID, '_pipepay_stripe_price_id', true );
	if ( ! $price_id || ! $previous_price_id || $price_id === $previous_price_id ) {
		return; // not a tier change (status flip, metadata edit, cancel_at_period_end, ...)
	}

	$period_end = pipepay_stripe_subs_extract_period_end( $sub );
	update_user_meta( $user->ID, '_pipepay_stripe_period_end', $period_end );

	pipepay_stripe_subs_apply_tier_change( $user, $stripe_sub_id, $previous_price_id, $price_id, $period_end, $sub['latest_invoice'] ?? '' );
}

/**
 * Shared tier-change path: expire the old tier's license, create a WC order for the
 * new tier (triggers WCAM issuance), sync the new row, repoint user meta.
 *
 * Both customer.subscription.updated and invoice.payment_succeeded can race here for
 * the same change (Stripe fires them within the same second). The add_option lock is
 * atomic at the DB layer (unique key on option_name), unlike wp_cache_add on stock WP.
 * Whichever event loses the lock re-checks price meta and finds no diff.
 */
function pipepay_stripe_subs_apply_tier_change( $user, $stripe_sub_id, $previous_price_id, $price_id, $period_end, $stripe_invoice_id ) {
	$config      = pipepay_stripe_subs_get_config();
	$tier_config = $config[ $price_id ] ?? null;
	if ( ! $tier_config ) {
		throw new Exception( "Tier change: unknown price $price_id" );
	}

	$lock_key = 'pipepay_stripe_tier_lock_' . $user->ID;
	if ( ! add_option( $lock_key, time(), '', false ) ) {
		// Lock held: if stale (>2 min, a crashed worker), steal it; otherwise the
		// other event's worker is mid-change — skip, it will finish the job.
		$held_since = (int) get_option( $lock_key );
		if ( time() - $held_since < 120 ) {
			pipepay_stripe_subs_log( "tier change for user {$user->ID} already in flight; skipping duplicate event" );
			return;
		}
		update_option( $lock_key, time(), false );
	}

	try {
		// Re-check under the lock: the other racing event may have completed the change.
		$current_price = get_user_meta( $user->ID, '_pipepay_stripe_price_id', true );
		if ( $current_price === $price_id ) {
			pipepay_stripe_subs_log( "tier change for user {$user->ID} already applied; no-op" );
			return;
		}

		$previous_config = $config[ $previous_price_id ] ?? null;
		if ( $previous_config && ! empty( $previous_config['wc_product_id'] ) ) {
			pipepay_stripe_subs_expire_license( $user->ID, $previous_config['wc_product_id'] );
		}

		$new_order       = pipepay_stripe_subs_create_order( $user->ID, $user->user_email, $user->display_name, $tier_config, $stripe_sub_id, $stripe_invoice_id, 'tier_change' );
		$api_resource_id = pipepay_stripe_subs_sync_wcam_for_order( $new_order->get_id(), $period_end );

		if ( $api_resource_id ) {
			pipepay_stripe_subs_set_api_resource_meta( $user->ID, $tier_config['wc_product_id'], $api_resource_id );
		}
		update_user_meta( $user->ID, '_pipepay_stripe_price_id', $price_id );

		pipepay_stripe_subs_log( "tier change for {$user->user_email}: $previous_price_id -> $price_id (sub $stripe_sub_id, new order " . $new_order->get_id() . ", api_resource $api_resource_id)" );
	} finally {
		delete_option( $lock_key );
	}
}

/**
 * customer.subscription.deleted → cancel/end.
 * Sets status meta, fires hook for WCAM license expiry.
 */
function pipepay_stripe_subs_handle_subscription_deleted( $sub ) {
	$stripe_sub_id = $sub['id'] ?? '';
	if ( ! $stripe_sub_id ) {
		return;
	}

	$user = pipepay_stripe_subs_find_user_by_subscription( $stripe_sub_id );
	if ( ! $user ) {
		return; // unknown — nothing to do
	}

	update_user_meta( $user->ID, '_pipepay_stripe_subscription_status', 'canceled' );

	$price_id = $sub['items']['data'][0]['price']['id'] ?? '';
	$config   = pipepay_stripe_subs_get_config();
	$wc_pid   = isset( $config[ $price_id ] ) ? $config[ $price_id ]['wc_product_id'] : 0;

	// Expire the license immediately.
	$api_resource_id = pipepay_stripe_subs_expire_license( $user->ID, $wc_pid );

	pipepay_stripe_subs_log( "canceled sub for {$user->user_email} (sub $stripe_sub_id, api_resource $api_resource_id)" );
}

/**
 * invoice.payment_failed → log + email.
 * Stripe handles dunning retries automatically; after final retry, fires subscription.deleted.
 */
function pipepay_stripe_subs_handle_invoice_failed( $invoice ) {
	// Modern Stripe API moved invoice.subscription to invoice.parent.subscription_details.subscription.
	$stripe_sub_id = $invoice['parent']['subscription_details']['subscription']
		?? $invoice['subscription']
		?? '';
	$user          = $stripe_sub_id ? pipepay_stripe_subs_find_user_by_subscription( $stripe_sub_id ) : null;

	pipepay_stripe_subs_log( 'payment failed for sub ' . $stripe_sub_id . ( $user ? " ({$user->user_email})" : '' ) );

	if ( $user ) {
		update_user_meta( $user->ID, '_pipepay_stripe_subscription_status', 'past_due' );
	}
}

/**
 * charge.refunded → on a FULL refund of a subscription charge, cancel the Stripe
 * subscription. The resulting customer.subscription.deleted webhook then expires the
 * license through the normal cancel path — one code path for "service ends".
 * Partial refunds (charge.refunded fires for those too) deliberately do nothing.
 */
function pipepay_stripe_subs_handle_charge_refunded( $charge ) {
	if ( empty( $charge['refunded'] ) ) {
		return; // partial refund — charge not fully refunded
	}

	// Resolve charge → invoice. Stripe 2025+ removed charge.invoice; the linkage is
	// charge.payment_intent → /v1/invoice_payments?payment_intent → invoice. Keep the
	// legacy direct field as the cheap first try for older API versions.
	$invoice_id = $charge['invoice'] ?? '';
	if ( ! $invoice_id && ! empty( $charge['payment_intent'] ) ) {
		$payments   = pipepay_stripe_subs_api_get( '/v1/invoice_payments?payment[payment_intent]=' . rawurlencode( $charge['payment_intent'] ) . '&payment[type]=payment_intent' );
		$invoice_id = $payments['data'][0]['invoice'] ?? '';
	}
	if ( ! $invoice_id ) {
		return; // not an invoice-backed charge (not a subscription payment)
	}

	$invoice       = pipepay_stripe_subs_api_get( '/v1/invoices/' . rawurlencode( $invoice_id ) );
	$stripe_sub_id = $invoice['parent']['subscription_details']['subscription']
		?? $invoice['subscription']
		?? '';
	if ( ! $stripe_sub_id ) {
		return;
	}

	$user = pipepay_stripe_subs_find_user_by_subscription( $stripe_sub_id );
	pipepay_stripe_subs_log( 'full refund on charge ' . ( $charge['id'] ?? '?' ) . " for sub $stripe_sub_id" . ( $user ? " ({$user->user_email})" : '' ) . '; canceling subscription' );

	try {
		pipepay_stripe_subs_api_delete( '/v1/subscriptions/' . rawurlencode( $stripe_sub_id ) );
	} catch ( Throwable $e ) {
		// Already-canceled subs error here — the deleted webhook has fired or will; fine.
		pipepay_stripe_subs_log( "could not cancel refunded sub $stripe_sub_id: " . $e->getMessage() );
	}
}

/* -------------------------------------------------------------------------
 * REST: Checkout session creation (called by /pricing CTA)
 * ------------------------------------------------------------------------- */

function pipepay_stripe_subs_create_checkout_session( WP_REST_Request $request ) {
	// Same-origin check: this endpoint is only ever called by our own pricing pages.
	// Pricing pages are Cloudflare-cached for anonymous visitors, so a WP nonce can't
	// work here (it would be stale in the cached HTML); Origin/Referer is the check
	// that survives caching. Browsers always send Origin on fetch() POSTs.
	// Exact-host match: a prefix check would accept https://pipepay.app.evil.com.
	$home   = untrailingslashit( home_url() );
	$origin = $request->get_header( 'origin' );
	$refer  = $request->get_header( 'referer' );
	$origin_ok = ( $origin && untrailingslashit( $origin ) === $home )
		|| ( $refer && ( $refer === $home || 0 === strpos( $refer, $home . '/' ) ) );
	if ( ! $origin_ok ) {
		return new WP_REST_Response( array( 'error' => 'forbidden' ), 403 );
	}

	// Per-IP rate limit: Stripe Checkout session creation is a write against Stripe's
	// API quota. 10 per 10 minutes is generous for a human comparing tiers.
	$ip        = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$rate_key  = 'pipepay_stripe_co_' . md5( $ip );
	$attempts  = (int) get_transient( $rate_key );
	if ( $attempts >= 10 ) {
		return new WP_REST_Response( array( 'error' => 'too many requests, try again shortly' ), 429 );
	}
	set_transient( $rate_key, $attempts + 1, 10 * MINUTE_IN_SECONDS );

	$price_id = sanitize_text_field( $request->get_param( 'price_id' ) );
	if ( ! $price_id ) {
		return new WP_REST_Response( array( 'error' => 'price_id required' ), 400 );
	}

	$config = pipepay_stripe_subs_get_config();
	if ( ! isset( $config[ $price_id ] ) ) {
		return new WP_REST_Response( array( 'error' => 'unknown price' ), 400 );
	}

	// Embedded mode mounts the same Stripe Checkout inside a modal on our own
	// pages (no redirect to stripe.com); the front end exchanges the returned
	// client_secret with Stripe.js. Redirect mode remains the fallback for
	// no-JS / Stripe.js-load-failure paths.
	$embedded = ( 'embedded' === $request->get_param( 'ui' ) );

	// Note: subscription mode auto-creates a customer — do NOT pass customer_creation (payment-mode only).
	$body = array(
		'mode'                       => 'subscription',
		// Pin USD so Stripe Adaptive Pricing can never charge a converted amount that
		// mismatches the USD totals recorded on the WC order.
		'currency'                   => 'usd',
		'line_items[0][price]'       => $price_id,
		'line_items[0][quantity]'    => 1,
		'allow_promotion_codes'      => 'true',
		'billing_address_collection' => 'auto',
	);
	if ( $embedded ) {
		// Embedded sessions take return_url only; on completion Stripe performs a
		// top-level redirect there, so the existing auto-login flow is unchanged.
		$body['ui_mode']    = 'embedded_page'; // Stripe 2025+ name (was 'embedded')
		$body['return_url'] = home_url( '/my-account/?stripe_success=1&session_id={CHECKOUT_SESSION_ID}' );
	} else {
		$body['success_url'] = home_url( '/my-account/?stripe_success=1&session_id={CHECKOUT_SESSION_ID}' );
		$body['cancel_url']  = home_url( '/pricing/' );
	}

	// If user is logged in, prefill email + link to their existing Stripe customer if known.
	if ( is_user_logged_in() ) {
		$user              = wp_get_current_user();
		$existing_customer = get_user_meta( $user->ID, '_pipepay_stripe_customer_id', true );
		if ( $existing_customer ) {
			$body['customer'] = $existing_customer;
		} else {
			$body['customer_email'] = $user->user_email;
		}
	}

	try {
		$session = pipepay_stripe_subs_api_post( '/v1/checkout/sessions', $body );
	} catch ( Throwable $e ) {
		pipepay_stripe_subs_log( 'checkout session create failed: ' . $e->getMessage() );
		return new WP_REST_Response( array( 'error' => 'could not start checkout' ), 500 );
	}

	if ( $embedded ) {
		if ( empty( $session['client_secret'] ) ) {
			pipepay_stripe_subs_log( 'embedded checkout: session created without client_secret' );
			return new WP_REST_Response( array( 'error' => 'could not start checkout' ), 500 );
		}
		return new WP_REST_Response( array( 'client_secret' => $session['client_secret'] ), 200 );
	}
	return new WP_REST_Response( array( 'url' => $session['url'] ), 200 );
}

/* -------------------------------------------------------------------------
 * REST: Customer Portal session (called from /my-account "Manage Subscription")
 * ------------------------------------------------------------------------- */

function pipepay_stripe_subs_create_portal_session( WP_REST_Request $request ) {
	$user_id            = get_current_user_id();
	$stripe_customer_id = get_user_meta( $user_id, '_pipepay_stripe_customer_id', true );
	if ( ! $stripe_customer_id ) {
		return new WP_REST_Response( array( 'error' => 'no subscription' ), 404 );
	}

	try {
		$session = pipepay_stripe_subs_api_post( '/v1/billing_portal/sessions', array(
			'customer'   => $stripe_customer_id,
			'return_url' => home_url( '/my-account/' ),
		) );
	} catch ( Throwable $e ) {
		pipepay_stripe_subs_log( 'portal session create failed: ' . $e->getMessage() );
		return new WP_REST_Response( array( 'error' => 'could not open billing portal' ), 500 );
	}

	return new WP_REST_Response( array( 'url' => $session['url'] ), 200 );
}

/* -------------------------------------------------------------------------
 * Auto-login after Stripe Checkout success
 *
 * success_url lands on /my-account/?stripe_success=1&session_id=cs_... but the
 * customer (often a brand-new user created by the webhook seconds earlier) has no
 * WP session and would hit the login form. The session_id is an unguessable
 * bearer token that only the paying customer's browser receives from Stripe's
 * redirect; we validate it against Stripe (paid + recent + livemode match), make
 * it single-use, and log the matching user in.
 * ------------------------------------------------------------------------- */

define( 'PIPEPAY_STRIPE_AUTOLOGIN_MAX_AGE', 30 * MINUTE_IN_SECONDS );

add_action( 'template_redirect', 'pipepay_stripe_subs_checkout_autologin' );

function pipepay_stripe_subs_checkout_autologin() {
	if ( ! isset( $_GET['stripe_success'], $_GET['session_id'] ) ) {
		return;
	}

	$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );

	// Already signed in (existing customer who was logged in when they subscribed):
	// just clean the URL.
	if ( is_user_logged_in() ) {
		wp_safe_redirect( add_query_arg( 'subscribed', '1', $account_url ) );
		exit;
	}

	$session_id = sanitize_text_field( wp_unslash( $_GET['session_id'] ) );
	if ( ! preg_match( '/^cs_(live|test)_[A-Za-z0-9]+$/', $session_id ) ) {
		wp_safe_redirect( $account_url );
		exit;
	}

	// Single-use: a session id that already performed a login can never do it again
	// (defends against the URL being replayed from browser history on a shared device).
	if ( get_transient( 'pipepay_stripe_autologin_used_' . md5( $session_id ) ) ) {
		wp_safe_redirect( $account_url );
		exit;
	}

	// Per-IP throttle: session ids are unguessable, but don't let anyone brute the
	// Stripe API through us either. Only FAILED validations count against the
	// budget — the webhook-lag interstitial below legitimately re-validates the
	// same (valid) session every 2 seconds and must not exhaust its own limit.
	$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$rate_key = 'pipepay_stripe_al_' . md5( $ip );
	$attempts = (int) get_transient( $rate_key );
	if ( $attempts >= 10 ) {
		// Recovery page instead of a silent bounce to the login form.
		status_header( 429 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!DOCTYPE html><html><head><title>One moment</title>'
			. '<style>body{font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:90vh;color:#1c2333}div{text-align:center;max-width:32em}p{color:#5a6173}</style>'
			. '</head><body><div><h1>Almost there</h1>'
			. '<p>Your payment went through, but account setup is taking longer than usual. Check your email for your receipt and license key, or <a href="' . esc_url( wp_lostpassword_url() ) . '">sign in with your email address</a>.</p>'
			. '<p>Need help? <a href="mailto:support@pipepay.app">support@pipepay.app</a></p></div></body></html>';
		exit;
	}

	$count_failure = function () use ( $rate_key, $attempts ) {
		set_transient( $rate_key, $attempts + 1, 10 * MINUTE_IN_SECONDS );
	};

	try {
		$session = pipepay_stripe_subs_api_get( '/v1/checkout/sessions/' . rawurlencode( $session_id ) );
	} catch ( Throwable $e ) {
		pipepay_stripe_subs_log( 'autologin: session lookup failed: ' . $e->getMessage() );
		$count_failure();
		wp_safe_redirect( $account_url );
		exit;
	}

	$expect_live = ( pipepay_stripe_subs_get_mode() === 'live' );
	$paid        = ( ( $session['payment_status'] ?? '' ) === 'paid' );
	$recent      = ( time() - intval( $session['created'] ?? 0 ) ) <= PIPEPAY_STRIPE_AUTOLOGIN_MAX_AGE;
	$mode_ok     = ( (bool) ( $session['livemode'] ?? false ) === $expect_live );
	if ( ! $paid || ! $recent || ! $mode_ok || ( $session['mode'] ?? '' ) !== 'subscription' ) {
		$count_failure();
		wp_safe_redirect( $account_url );
		exit;
	}

	// Find the user the webhook created: by Stripe customer id first, email fallback.
	$user_id            = 0;
	$stripe_customer_id = $session['customer'] ?? '';
	if ( $stripe_customer_id ) {
		$users = get_users( array(
			'meta_key'   => '_pipepay_stripe_customer_id',
			'meta_value' => $stripe_customer_id,
			'number'     => 1,
			'fields'     => 'ID',
		) );
		$user_id = $users ? (int) $users[0] : 0;
	}
	if ( ! $user_id ) {
		$email   = $session['customer_details']['email'] ?? '';
		$user_id = $email ? (int) email_exists( $email ) : 0;
	}

	if ( ! $user_id ) {
		// Webhook hasn't landed yet (Stripe usually delivers within seconds). Render a
		// tiny self-refreshing interstitial; each refresh re-runs this handler, and the
		// 30-minute session age cap bounds the loop.
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="2"><title>Finalizing your account&hellip;</title>'
			. '<style>body{font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:90vh;color:#1c2333}div{text-align:center}p{color:#5a6173}</style>'
			. '</head><body><div><h1>Payment received &#10003;</h1><p>Setting up your account &mdash; this page will refresh in a moment.</p></div></body></html>';
		exit;
	}

	set_transient( 'pipepay_stripe_autologin_used_' . md5( $session_id ), 1, DAY_IN_SECONDS );

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true );
	pipepay_stripe_subs_log( "autologin: user $user_id signed in via checkout session" );

	wp_safe_redirect( add_query_arg( 'subscribed', '1', $account_url ) );
	exit;
}

// Success notice on /my-account after the post-checkout redirect.
add_action( 'woocommerce_before_account_navigation', function () {
	if ( isset( $_GET['subscribed'] ) && is_user_logged_in() ) {
		echo '<div class="woocommerce-message" role="alert">Your subscription is active. Your license key is under <strong>License keys</strong> in the menu below &mdash; paste it into WP&nbsp;Admin &rarr; Pipe&nbsp;Pay &rarr; License on your store.</div>';
	}
} );

/* -------------------------------------------------------------------------
 * Stripe API helpers (wp_remote_*)
 * ------------------------------------------------------------------------- */

function pipepay_stripe_subs_api_request( $method, $path, $body = null ) {
	$secret = pipepay_stripe_subs_get_secret_key();
	if ( ! $secret ) {
		throw new Exception( 'Stripe secret key not configured (PIPEPAY_STRIPE_SECRET_KEY)' );
	}

	$args = array(
		'method'      => $method,
		'headers'     => array( 'Authorization' => 'Bearer ' . $secret ),
		// 8s, not WP's 5s default-ish or a long 15s: webhook handlers block a PHP-FPM
		// worker for the full duration when Stripe is slow.
		'timeout'     => 8,
		// Explicit TLS posture: a host-level https_ssl_verify filter must not be able
		// to downgrade calls carrying the secret key, and a redirect must never replay
		// the Authorization header to another host.
		'sslverify'   => true,
		'redirection' => 0,
	);
	if ( null !== $body ) {
		$args['body'] = $body;
	}

	$response = wp_remote_request( PIPEPAY_STRIPE_API_BASE . $path, $args );

	if ( is_wp_error( $response ) ) {
		throw new Exception( 'Stripe API error: ' . $response->get_error_message() );
	}

	$parsed = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! empty( $parsed['error'] ) ) {
		throw new Exception( 'Stripe API error: ' . ( $parsed['error']['message'] ?? wp_json_encode( $parsed['error'] ) ) );
	}
	return $parsed;
}

function pipepay_stripe_subs_api_get( $path ) {
	return pipepay_stripe_subs_api_request( 'GET', $path );
}

function pipepay_stripe_subs_api_post( $path, $body ) {
	return pipepay_stripe_subs_api_request( 'POST', $path, $body );
}

function pipepay_stripe_subs_api_delete( $path ) {
	return pipepay_stripe_subs_api_request( 'DELETE', $path );
}

/* -------------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------------- */

/**
 * Extract current_period_end from a subscription object. Stripe's 2025+ API versions
 * put it on the subscription item (verified against live test-mode payloads); older
 * versions had it on the root. Throws rather than silently writing expires=0.
 *
 * @return int Unix timestamp.
 */
function pipepay_stripe_subs_extract_period_end( $sub ) {
	$period_end = intval( $sub['items']['data'][0]['current_period_end'] ?? $sub['current_period_end'] ?? 0 );
	if ( ! $period_end ) {
		throw new Exception( 'Subscription ' . ( $sub['id'] ?? '?' ) . ' missing current_period_end' );
	}
	return $period_end;
}

function pipepay_stripe_subs_find_user_by_subscription( $stripe_sub_id ) {
	$users = get_users( array(
		'meta_key'   => '_pipepay_stripe_subscription_id',
		'meta_value' => $stripe_sub_id,
		'number'     => 1,
		'fields'     => 'all',
	) );
	return $users ? $users[0] : null;
}

/**
 * Find a WC order id keyed to a Stripe subscription id. HPOS-aware.
 * Used by checkout.session.completed to detect retried webhook deliveries.
 *
 * @return int|null
 */
function pipepay_stripe_subs_find_order_by_subscription( $stripe_sub_id ) {
	global $wpdb;

	// HPOS path (WC 8+ default on new installs, active on pipepay.app).
	$hpos_table = $wpdb->prefix . 'wc_orders_meta';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos_table ) ) ) {
		$id = $wpdb->get_var( $wpdb->prepare(
			"SELECT order_id FROM {$hpos_table} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
			'_pipepay_stripe_subscription_id',
			$stripe_sub_id
		) );
		if ( $id ) {
			return (int) $id;
		}
	}

	// Classic postmeta fallback (also covers HPOS sync-on if enabled).
	$id = $wpdb->get_var( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
		'_pipepay_stripe_subscription_id',
		$stripe_sub_id
	) );
	return $id ? (int) $id : null;
}

function pipepay_stripe_subs_create_order( $user_id, $email, $name, $tier_config, $stripe_sub_id, $stripe_invoice_id, $event_type ) {
	if ( ! function_exists( 'wc_create_order' ) ) {
		throw new Exception( 'WooCommerce not active' );
	}

	$order = wc_create_order( array( 'customer_id' => $user_id ) );
	if ( is_wp_error( $order ) ) {
		throw new Exception( 'wc_create_order failed: ' . $order->get_error_message() );
	}

	$product = wc_get_product( $tier_config['wc_product_id'] );
	if ( ! $product ) {
		throw new Exception( "WC product {$tier_config['wc_product_id']} not found" );
	}

	$amount = $tier_config['amount_cents'] / 100;

	$item_id = $order->add_product( $product, 1, array(
		'subtotal' => $amount,
		'total'    => $amount,
	) );
	if ( ! $item_id ) {
		throw new Exception( 'add_product failed' );
	}

	$first = trim( $name );
	$last  = '';
	if ( strpos( $first, ' ' ) !== false ) {
		list( $first, $last ) = array_pad( explode( ' ', $first, 2 ), 2, '' );
	}

	$order->set_address( array(
		'first_name' => $first,
		'last_name'  => $last,
		'email'      => $email,
	), 'billing' );

	$order->update_meta_data( '_pipepay_stripe_subscription_id', $stripe_sub_id );
	$order->update_meta_data( '_pipepay_stripe_invoice_id', $stripe_invoice_id );
	$order->update_meta_data( '_pipepay_stripe_event_type', $event_type );
	$order->set_payment_method( 'stripe' );
	$order->set_payment_method_title( 'Stripe (' . ( $event_type === 'renewal' ? 'Renewal' : 'Subscription' ) . ')' );

	$order->calculate_totals();
	$order->update_status( 'completed', sprintf( 'Stripe %s: invoice %s', $event_type, $stripe_invoice_id ) );

	return $order;
}

/**
 * Log to WC Admin → Status → Logs (source=pipepay-stripe-subs) where an operator
 * actually looks during an incident, matching the main plugin's wc_get_logger()
 * pattern. error_log fallback covers webhook calls before WC loads (shouldn't
 * happen on a REST request, but cheap insurance).
 */
function pipepay_stripe_subs_log( $msg, $level = 'info' ) {
	if ( function_exists( 'wc_get_logger' ) ) {
		wc_get_logger()->log( $level, $msg, array( 'source' => 'pipepay-stripe-subs' ) );
	} else {
		error_log( '[pipepay-stripe-subs] ' . $msg );
	}
}

/* -------------------------------------------------------------------------
 * WCAM license bridge (direct DB on wp_wc_am_api_resource)
 * ------------------------------------------------------------------------- */

/**
 * Find the api_resource row WCAM created for a given WC order, and sync access_expires
 * to the Stripe period_end (overrides WCAM's product-level _access_expires days calc).
 *
 * @return int|null api_resource_id, or null if WCAM didn't create one.
 */
function pipepay_stripe_subs_sync_wcam_for_order( $order_id, $period_end ) {
	global $wpdb;
	$table = $wpdb->prefix . 'wc_am_api_resource';

	// WCAM creates the row synchronously on woocommerce_order_status_completed, but
	// retry briefly in case a future WCAM version defers insertion.
	$row = null;
	for ( $attempt = 0; $attempt < 3; $attempt++ ) {
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT api_resource_id FROM {$table} WHERE order_id = %d ORDER BY api_resource_id DESC LIMIT 1",
			$order_id
		) );
		if ( $row ) {
			break;
		}
		usleep( 500000 );
	}
	if ( ! $row ) {
		pipepay_stripe_subs_log( "sync_wcam_for_order: no api_resource row for order $order_id after 3 attempts — license NOT synced", 'error' );
		return null;
	}

	$wpdb->update(
		$table,
		array( 'access_expires' => $period_end ),
		array( 'api_resource_id' => $row->api_resource_id ),
		array( '%d' ),
		array( '%d' )
	);
	return (int) $row->api_resource_id;
}

/**
 * Store the api_resource pointer keyed BY PRODUCT, so operations on one tier can
 * never modify another tier's row (the review's multi-product collision finding).
 * The legacy scalar key is kept in sync for anything still reading it.
 */
function pipepay_stripe_subs_set_api_resource_meta( $user_id, $product_id, $api_resource_id ) {
	update_user_meta( $user_id, '_pipepay_stripe_api_resource_id_' . intval( $product_id ), $api_resource_id );
	update_user_meta( $user_id, '_pipepay_stripe_api_resource_id', $api_resource_id );
}

/**
 * Resolve the api_resource row for (user, product): per-product meta first, then a
 * direct (user_id, product_id) lookup. The legacy scalar meta is deliberately NOT
 * consulted — it may point at a different product's row after a tier change.
 *
 * @return int api_resource_id, or 0 if none found.
 */
function pipepay_stripe_subs_resolve_api_resource( $user_id, $product_id, $require_active ) {
	global $wpdb;
	$table = $wpdb->prefix . 'wc_am_api_resource';

	$api_resource_id = (int) get_user_meta( $user_id, '_pipepay_stripe_api_resource_id_' . intval( $product_id ), true );
	if ( $api_resource_id ) {
		return $api_resource_id;
	}

	$sql = "SELECT api_resource_id FROM {$table} WHERE user_id = %d AND product_id = %d";
	if ( $require_active ) {
		$sql .= ' AND active = 1';
	}
	$sql .= ' ORDER BY api_resource_id DESC LIMIT 1';

	$row = $wpdb->get_row( $wpdb->prepare( $sql, $user_id, $product_id ) );
	return $row ? (int) $row->api_resource_id : 0;
}

/**
 * Extend a user's license to a new access_expires timestamp.
 *
 * @return int|null api_resource_id updated, or null if not found.
 */
function pipepay_stripe_subs_extend_license( $user_id, $product_id, $period_end ) {
	global $wpdb;
	$table = $wpdb->prefix . 'wc_am_api_resource';

	$api_resource_id = pipepay_stripe_subs_resolve_api_resource( $user_id, $product_id, true );
	if ( ! $api_resource_id ) {
		pipepay_stripe_subs_log( "extend_license: no active row for user $user_id product $product_id" );
		return null;
	}
	pipepay_stripe_subs_set_api_resource_meta( $user_id, $product_id, $api_resource_id );

	// Monotonicity guard: never move access_expires backwards (out-of-order webhooks).
	$wpdb->query( $wpdb->prepare(
		"UPDATE {$table} SET access_expires = %d, active = 1 WHERE api_resource_id = %d AND access_expires < %d",
		$period_end, $api_resource_id, $period_end
	) );
	return $api_resource_id;
}

/**
 * Expire a user's license immediately (set access_expires to now, active to 0).
 *
 * Sets active=0 by design. The existing pipepay-license-revalidate.php mu-plugin
 * reads active=0 as state=revoked, which fires the Phase C gateway kill switch on
 * the customer's install. For Stripe SUBSCRIPTIONS (monthly and yearly) this is
 * the intended product model: paying ends → service ends → gateway stops. The
 * CLAUDE.md "License Model" lapse-is-banner-only rule applies to the payment-app
 * lane (one-time annual purchases that renew manually), which never reaches this
 * code.
 *
 * @return int|null api_resource_id updated.
 */
function pipepay_stripe_subs_expire_license( $user_id, $product_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'wc_am_api_resource';

	$api_resource_id = pipepay_stripe_subs_resolve_api_resource( $user_id, $product_id, false );
	if ( ! $api_resource_id ) {
		pipepay_stripe_subs_log( "expire_license: no row for user $user_id product $product_id" );
		return null;
	}

	$wpdb->update(
		$table,
		array( 'access_expires' => time(), 'active' => 0 ),
		array( 'api_resource_id' => $api_resource_id ),
		array( '%d', '%d' ),
		array( '%d' )
	);

	// Stale sibling rows for the same (user, product) - e.g. an old payment-app
	// annual purchase that already ran out - share the per-user master key and
	// could otherwise shadow this cancellation at the revalidate endpoint.
	// Rows still inside their paid period are deliberately untouched: the
	// customer paid for that time regardless of this subscription ending.
	$wpdb->query( $wpdb->prepare(
		"UPDATE {$table} SET active = 0 WHERE user_id = %d AND product_id = %d AND api_resource_id != %d AND active = 1 AND access_expires <= %d",
		$user_id, $product_id, $api_resource_id, time()
	) );
	return $api_resource_id;
}
