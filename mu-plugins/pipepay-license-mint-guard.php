<?php
/**
 * Plugin Name: Pipe Pay - License Mint Guard
 * Description: Catches completed orders that contain a licensing product but have no license resource, re-mints them, and alerts when it can't. Closes the silent-failure gap where WCAM omits the "License information" block and the customer receives a keyless order-complete email.
 * Author:      Pipe Pay
 * Version:     1.0.0
 *
 * WHY THIS EXISTS
 * ---------------
 * There is no separate license email. The key rides inside WooCommerce's
 * standard "Completed order" email: WCAM hooks `woocommerce_email_before_order_table`
 * -> WC_AM_ORDER()->email_api_keys(), which renders the "License information"
 * block ONLY when:
 *
 *     ! empty( $resources ) && WC_AM_ORDER_DATA_STORE()->has_api_product( $order )
 *
 * If minting has not happened by the time that email renders, the block is
 * simply omitted - the customer still receives a normal, cheerful "your order
 * is complete" email with no key in it. Nothing errors, nothing is logged, no
 * admin is told. The only signal is a confused customer (or, as seen with
 * order 1440 on 2026-08-22, one who silently re-orders 102 seconds later).
 *
 * HOW IT WORKS
 * ------------
 * Layer 1 - inline (same request, BEFORE the email):
 *   WC_Order::status_transition() fires the generic
 *   `woocommerce_order_status_completed` (where WCAM mints at priority 10)
 *   BEFORE the transition-specific `woocommerce_order_status_{from}_to_completed`
 *   (where WC sends the customer email at priority 10). Deferred transactional
 *   emails are OFF on this install, so that email really is sent in-request.
 *   Hooking the generic action at priority 20 therefore lands us after the mint
 *   attempt but still before the email: a successful re-mint is picked up by
 *   the ORIGINAL email and the customer never knows anything happened.
 *
 * Layer 2 - sweep (safety net):
 *   If the whole request died before our hook ran, an hourly Action Scheduler
 *   job re-checks recently completed orders. A recovery here is necessarily
 *   after the fact, so it re-sends the completed-order email (exactly once)
 *   which now carries the key.
 *
 * Anything unrecoverable (the classic case: a guest order, which WCAM cannot
 * mint against) raises a one-shot admin alert plus an order note.
 *
 * SAFETY: every entry point is wrapped - WCAM's update_order() is documented
 * `@throws \Exception`, and this code runs inside checkout. It must never be
 * the reason an order fails to complete.
 */

defined( 'ABSPATH' ) || exit;

const PIPEPAY_MINT_GUARD_SWEEP_HOOK   = 'pipepay_license_mint_guard_sweep';
const PIPEPAY_MINT_GUARD_SWEEP_DAYS   = 7;   // how far back the safety net looks
const PIPEPAY_MINT_GUARD_SWEEP_LIMIT  = 50;  // max orders inspected per sweep
const PIPEPAY_MINT_GUARD_META_ALERTED = '_pipepay_mint_guard_alerted';
const PIPEPAY_MINT_GUARD_META_RESENT  = '_pipepay_mint_guard_key_email_resent';

/**
 * Is WCAM loaded far enough to answer questions about licensing?
 */
function pipepay_mint_guard_wcam_ready(): bool {
	return function_exists( 'WC_AM_ORDER_DATA_STORE' ) && function_exists( 'WC_AM_ORDER' );
}

/**
 * Does this order promise a license it cannot currently deliver?
 *
 * Deliberately mirrors WC_AM_ORDER::email_api_keys()'s own predicate, so a
 * `true` here means precisely "the License information block will be missing
 * from the customer's email".
 *
 * @return bool|null true = licensing product with no resource; false = fine;
 *                   null = cannot tell (WCAM not ready / not an order).
 */
function pipepay_mint_guard_is_missing_license( $order ): ?bool {
	if ( ! $order instanceof WC_Order || ! pipepay_mint_guard_wcam_ready() ) {
		return null;
	}
	try {
		$store = WC_AM_ORDER_DATA_STORE();
		if ( ! is_object( $store ) || ! method_exists( $store, 'has_api_product' ) || ! method_exists( $store, 'get_api_resource_items_for_order' ) ) {
			return null;
		}
		if ( ! $store->has_api_product( $order ) ) {
			return false; // nothing licensed on this order - not our business.
		}
		return empty( $store->get_api_resource_items_for_order( $order ) );
	} catch ( \Throwable $e ) {
		pipepay_mint_guard_log( 'warning', 'Mint guard could not inspect order', [ 'order_id' => $order->get_id(), 'error' => $e->getMessage() ] );
		return null;
	}
}

/**
 * Ask WCAM to (re)build this order's license resources. Returns true when the
 * order has resources afterwards.
 */
function pipepay_mint_guard_attempt_remint( WC_Order $order ): bool {
	if ( ! pipepay_mint_guard_wcam_ready() ) {
		return false;
	}
	try {
		WC_AM_ORDER()->update_order( $order->get_id() );
	} catch ( \Throwable $e ) {
		pipepay_mint_guard_log( 'error', 'Mint guard re-mint threw', [ 'order_id' => $order->get_id(), 'error' => $e->getMessage() ] );
		return false;
	}
	// Re-read from a fresh order object: update_order() writes through the data
	// store, and the in-memory object we were handed may be stale.
	$fresh = wc_get_order( $order->get_id() );
	return false === pipepay_mint_guard_is_missing_license( $fresh );
}

/**
 * Log through the plugin's scrubbing logger when it is loaded, else error_log.
 * mu-plugins load before plugins, so pipepay_log() only exists at hook time.
 */
function pipepay_mint_guard_log( string $level, string $message, array $context = [] ): void {
	if ( function_exists( 'pipepay_log' ) ) {
		pipepay_log( $level, $message, $context );
		return;
	}
	error_log( sprintf( '[pipepay-mint-guard][%s] %s %s', strtoupper( $level ), $message, wp_json_encode( $context ) ) );
}

/**
 * One-shot admin alert for an order we could not fix. The meta stamp keeps a
 * recurring sweep from mailing the same order every hour forever.
 */
function pipepay_mint_guard_alert( WC_Order $order, string $reason ): void {
	if ( $order->get_meta( PIPEPAY_MINT_GUARD_META_ALERTED, true ) ) {
		return;
	}
	$order_id = $order->get_id();
	pipepay_mint_guard_log( 'error', 'Licensing order has no license key - manual action needed', [
		'order_id' => $order_id,
		'reason'   => $reason,
	] );
	$order->add_order_note( sprintf(
		'Pipe Pay license guard: this order contains a licensing product but has NO license key, so the customer\'s order-complete email did not include one. Reason: %s',
		$reason
	) );

	$to = get_option( 'admin_email' );
	if ( $to ) {
		$edit_url = method_exists( $order, 'get_edit_order_url' ) ? $order->get_edit_order_url() : admin_url( 'post.php?post=' . $order_id . '&action=edit' );
		wp_mail(
			$to,
			sprintf( '[%s] Order #%s completed with NO license key', get_bloginfo( 'name' ), $order->get_order_number() ),
			sprintf(
				"Order #%s completed, but no license resource exists for it.\n\n"
				. "The customer's \"Completed order\" email was sent WITHOUT the License information block, so they have no key.\n\n"
				. "Reason: %s\n"
				. "Customer: %s\n"
				. "Order: %s\n\n"
				. "Re-minting was attempted automatically and did not resolve it. This one needs a look.",
				$order->get_order_number(),
				$reason,
				$order->get_billing_email(),
				$edit_url
			)
		);
	}

	$order->update_meta_data( PIPEPAY_MINT_GUARD_META_ALERTED, time() );
	$order->save();
}

/**
 * Why can't this order mint? Used to make the alert actionable rather than
 * "something went wrong". The guest case is the known trap: WCAM's
 * update_order() bails on an empty customer id, so no key is ever created.
 */
function pipepay_mint_guard_diagnose( WC_Order $order ): string {
	return 0 === (int) $order->get_customer_id()
		? 'order has no customer account (guest order) - WCAM cannot mint a license without a WP user'
		: 'unknown - re-mint ran but produced no resource';
}

// ── Layer 1: inline guard, before the customer email renders ─────────────────

/**
 * Priority 20: after WCAM's own mint (priority 10 on this same action) and
 * before the transition-specific action that sends the customer email.
 */
function pipepay_mint_guard_check_order( $order_id ): void {
	try {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		if ( true !== pipepay_mint_guard_is_missing_license( $order ) ) {
			return;
		}

		pipepay_mint_guard_log( 'warning', 'Licensing order completed with no license - re-minting before email', [ 'order_id' => $order->get_id() ] );

		if ( pipepay_mint_guard_attempt_remint( $order ) ) {
			pipepay_mint_guard_log( 'info', 'Mint guard recovered a missing license in-request', [ 'order_id' => $order->get_id() ] );
			// add_order_note() writes its comment immediately - no save() needed,
			// and we avoid an extra order write mid status-transition.
			$order->add_order_note( 'Pipe Pay license guard: license was missing after checkout and was re-minted automatically. The order-complete email includes the key.' );
			return;
		}

		pipepay_mint_guard_alert( $order, pipepay_mint_guard_diagnose( $order ) );
	} catch ( \Throwable $e ) {
		// Never let this break order completion.
		pipepay_mint_guard_log( 'error', 'Mint guard inline check failed', [ 'order_id' => $order_id, 'error' => $e->getMessage() ] );
	}
}
add_action( 'woocommerce_order_status_completed', 'pipepay_mint_guard_check_order', 20, 1 );
// Mirrors WCAM's own trigger set: it also mints on `processing` when the
// "grant access after payment" option is on.
add_action( 'woocommerce_order_status_processing', function ( $order_id ) {
	if ( function_exists( 'WCAM' ) && WCAM()->get_grant_access_after_payment() ) {
		pipepay_mint_guard_check_order( $order_id );
	}
}, 20, 1 );

// ── Layer 2: sweep, for requests that died before the inline guard ──────────

add_action( 'init', function () {
	if ( ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
		return;
	}
	if ( false === as_next_scheduled_action( PIPEPAY_MINT_GUARD_SWEEP_HOOK ) ) {
		as_schedule_recurring_action( time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS, PIPEPAY_MINT_GUARD_SWEEP_HOOK, [], 'pipepay' );
	}
} );

function pipepay_mint_guard_sweep(): void {
	if ( ! pipepay_mint_guard_wcam_ready() || ! function_exists( 'wc_get_orders' ) ) {
		return;
	}
	try {
		$orders = wc_get_orders( [
			'status'       => [ 'completed' ],
			'date_created' => '>' . ( time() - ( PIPEPAY_MINT_GUARD_SWEEP_DAYS * DAY_IN_SECONDS ) ),
			'limit'        => PIPEPAY_MINT_GUARD_SWEEP_LIMIT,
			'orderby'      => 'date',
			'order'        => 'DESC',
		] );
	} catch ( \Throwable $e ) {
		pipepay_mint_guard_log( 'error', 'Mint guard sweep query failed', [ 'error' => $e->getMessage() ] );
		return;
	}

	foreach ( (array) $orders as $order ) {
		if ( ! $order instanceof WC_Order ) {
			continue;
		}
		try {
			if ( true !== pipepay_mint_guard_is_missing_license( $order ) ) {
				continue;
			}
			pipepay_mint_guard_log( 'warning', 'Mint guard sweep found a licensing order with no license', [ 'order_id' => $order->get_id() ] );

			if ( ! pipepay_mint_guard_attempt_remint( $order ) ) {
				pipepay_mint_guard_alert( $order, pipepay_mint_guard_diagnose( $order ) );
				continue;
			}

			// Recovered, but too late for the original email - that one went out
			// without a key. Re-send it once so the customer actually receives one.
			$fresh = wc_get_order( $order->get_id() );
			if ( $fresh && ! $fresh->get_meta( PIPEPAY_MINT_GUARD_META_RESENT, true ) ) {
				pipepay_mint_guard_resend_key_email( $fresh );
			}
		} catch ( \Throwable $e ) {
			pipepay_mint_guard_log( 'error', 'Mint guard sweep failed on order', [ 'order_id' => $order->get_id(), 'error' => $e->getMessage() ] );
		}
	}
}
add_action( PIPEPAY_MINT_GUARD_SWEEP_HOOK, 'pipepay_mint_guard_sweep' );

/**
 * Re-send the completed-order email so a late-minted key reaches the customer.
 * Stamped so it can only ever happen once per order.
 */
function pipepay_mint_guard_resend_key_email( WC_Order $order ): void {
	$mailer = function_exists( 'WC' ) ? WC()->mailer() : null;
	if ( ! $mailer ) {
		return;
	}
	$emails = $mailer->get_emails();
	$email  = $emails['WC_Email_Customer_Completed_Order'] ?? null;
	if ( ! $email || ! method_exists( $email, 'trigger' ) ) {
		return;
	}
	$order->update_meta_data( PIPEPAY_MINT_GUARD_META_RESENT, time() );
	$order->save();

	$email->trigger( $order->get_id(), $order );

	pipepay_mint_guard_log( 'info', 'Mint guard re-sent the completed-order email with the recovered license key', [ 'order_id' => $order->get_id() ] );
	$order->add_order_note( 'Pipe Pay license guard: license was minted late, so the completed-order email (which now carries the license key) was re-sent to the customer.' );
}
