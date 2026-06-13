# Cross-Store Screenshot Hash Network Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A fraudster who reuses the same payment screenshot across *different* Pipe Pay stores gets caught: each store submits a 64-bit perceptual hash (never the image) to pipepay.app, and an upload whose hash was already seen on another store is forced to manual review.

**Architecture:** Server side: a new mu-plugin on pipepay.app (`pipepay-phash-network.php`) with a custom table and a REST check-and-submit endpoint mirroring the revalidate endpoint's security posture (license-key auth, opaque 404s, per-IP + per-store rate limits, Ed25519-signed responses with a new `phash-v1|` canonical prefix). Plugin side (v1.10.0): a thin client called from the existing dHash block in the upload handler (3s timeout, fail-open — a network problem NEVER blocks a customer upload or causes a flag), a one-time backfill cron that bulk-submits historical hashes (they persist as order meta forever, so the network launches with full history), a red "Seen on another Pipe Pay store" badge in the Proofs queue, and an opt-out toggle in gateway settings.

**Tech Stack:** WordPress mu-plugin + `$wpdb` (server), WP REST API, libsodium Ed25519 (existing keypair + verify-helper pattern in `pipepay-license-verify.php`), PHPUnit (plugin repo suite, currently green at 442+ tests).

**Key locked decisions (do not relitigate during implementation):**
1. **The network transports the existing 64-bit dHash** (order meta `_pipepay_proof_dhash`), NOT a new pHash-DCT implementation. "phash" in function/file names is the historical roadmap name kept for continuity. Backfill works precisely because this hash already exists on every historical order.
2. **Exact-match only on the server** (indexed `CHAR(16)` lookup). The dominant cross-store fraud case is the same screenshot file re-uploaded elsewhere → identical hash. Hamming-near matching server-side needs band indexes; documented as the upgrade path, not built now (YAGNI).
3. **Privacy invariant:** the server stores `(hash, store_bucket, first_seen)` and NOTHING else. `store_bucket` is a one-way HMAC of `api_key|instance` with a server-side pepper — it cannot be reversed to a merchant, exists only so same-store resubmissions don't self-flag and so rate limits work. No IPs, no site URLs, no PII in the table. RTBF: nothing in this table is personal data; document that in the mu-plugin docblock.
4. **Degenerate hashes** (`0000000000000000`, `ffffffffffffffff` — featureless images collapse to these, discovered in v1.8.9 test work) are never stored and never matched, on both sides.
5. **Fail-open everywhere on the plugin side:** no license key → skip; HTTP error/timeout → skip; bad/missing signature → treat as unreachable and skip. A network hit forces manual review (fail-safe direction); network absence changes nothing vs today.
6. **Plugin version v1.10.0** (current: 1.9.36). Server mu-plugin is new, v1.0.0.

**Repos and deploy paths:**
- Plugin repo: `~/Desktop/Pipe Pay/pipe-pay-extracted/` (run `composer exec phpunit` here; CI runs on push)
- Site repo: `~/Desktop/Pipe Pay/pipe-pay-site/` (mu-plugins/ + theme; deployed by scp per the CLAUDE.md cheatsheet)
- Server: `ssh witt-scafidi@100.102.251.125`, WP at `/var/www/pipepay`, wp-cli as `sudo -u www-data wp --path=/var/www/pipepay`

---

### Task 1: Server mu-plugin — table, bucket, validation helpers

**Files:**
- Create: `pipe-pay-site/mu-plugins/pipepay-phash-network.php`

- [ ] **Step 1: Write the file with table install + pure helpers (REST routes come in Task 2)**

```php
<?php
/**
 * Plugin Name: Pipe Pay Screenshot Hash Network
 * Description: Cross-store duplicate-screenshot detection endpoint. Stores 64-bit
 * perceptual hashes of customer payment screenshots submitted by licensed Pipe Pay
 * installs and answers "has this exact hash been seen on a DIFFERENT store?".
 * Version: 1.0.0
 *
 * PRIVACY / RTBF POSTURE (load-bearing - do not relax):
 * - The image NEVER reaches this server. A 64-bit dHash is a one-way fingerprint;
 *   no amount, handle, face, or text can be reconstructed from it.
 * - The table stores (hash, store_bucket, first_seen) and NOTHING else. No IPs,
 *   no site URLs, no license keys, no PII. store_bucket is a one-way HMAC of
 *   api_key|instance with a server-side pepper - it exists only so a store's own
 *   resubmissions don't self-flag and so per-store rate limits work. It cannot be
 *   reversed to a merchant. Nothing here is personal data under GDPR/CCPA, so the
 *   RTBF runbook does not need to touch this table.
 * - Security posture mirrors pipepay-license-revalidate.php: HTTPS required,
 *   shape validation BEFORE rate-limit accounting, opaque 404 for invalid keys,
 *   per-IP and per-bucket rate limits, Ed25519-signed responses (prefix phash-v1|,
 *   distinct from v1| / revalidate-v1| / download-v1| so captured signatures
 *   cannot be replayed across contexts).
 */

defined( 'ABSPATH' ) || exit;

define( 'PIPEPAY_PHASH_DB_VERSION', 1 );
define( 'PIPEPAY_PHASH_IP_LIMIT', 120 );        // submits per hour per IP
define( 'PIPEPAY_PHASH_BUCKET_LIMIT', 2000 );   // submits per day per store bucket
define( 'PIPEPAY_PHASH_BULK_MAX', 200 );        // hashes per bulk (backfill) request

/**
 * Idempotent table install, gated by a db-version option (same pattern as
 * pipepay-license-analytics.php).
 */
function pipepay_phash_install_table() {
	if ( (int) get_option( 'pipepay_phash_db_version' ) >= PIPEPAY_PHASH_DB_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = $wpdb->prefix . 'pipepay_screenshot_hashes';
	dbDelta( "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		hash CHAR(16) NOT NULL,
		store_bucket CHAR(32) NOT NULL,
		first_seen DATETIME NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY hash_bucket (hash, store_bucket),
		KEY hash (hash)
	) {$wpdb->get_charset_collate()};" );
	update_option( 'pipepay_phash_db_version', PIPEPAY_PHASH_DB_VERSION );
}
add_action( 'plugins_loaded', 'pipepay_phash_install_table' );

/** Valid lowercase 16-hex dHash, excluding the two degenerate values. */
function pipepay_phash_is_storable_hash( $hash ) {
	return is_string( $hash )
		&& 1 === preg_match( '/^[0-9a-f]{16}$/', $hash )
		&& '0000000000000000' !== $hash
		&& 'ffffffffffffffff' !== $hash;
}

/** One-way per-store bucket. Pepper survives in wp-config if defined; falls back to auth salt. */
function pipepay_phash_bucket( $api_key, $instance ) {
	$pepper = defined( 'PIPEPAY_PHASH_BUCKET_PEPPER' ) ? PIPEPAY_PHASH_BUCKET_PEPPER : wp_salt( 'auth' );
	return substr( hash_hmac( 'sha256', $api_key . '|' . $instance, $pepper ), 0, 32 );
}

/** True when the key belongs to a currently-entitled license (any product). */
function pipepay_phash_license_ok( $api_key ) {
	global $wpdb;
	$row = $wpdb->get_var( $wpdb->prepare(
		"SELECT api_resource_id FROM {$wpdb->prefix}wc_am_api_resource
		 WHERE master_api_key = %s AND active = 1
		   AND ( access_expires = 0 OR access_expires > UNIX_TIMESTAMP() )
		 ORDER BY api_resource_id ASC LIMIT 1",
		$api_key
	) );
	return ! empty( $row );
}

/**
 * Transient rate limiter shared by both routes.
 * Returns true when the caller is OVER the limit.
 */
function pipepay_phash_over_limit( $bucket_key, $limit, $window_seconds ) {
	$key      = 'pipepay_phash_rl_' . md5( $bucket_key );
	$attempts = (int) get_transient( $key );
	if ( $attempts >= $limit ) {
		return true;
	}
	if ( 0 === $attempts ) {
		set_transient( $key, 1, $window_seconds );
	} else {
		set_transient( $key, $attempts + 1, $window_seconds );
	}
	return false;
}

/**
 * Ed25519-sign a submit verdict. Canonical: phash-v1|<issued>|<hash>|<0|1>
 * Returns array{sig,issued_at} or null when signing infra is unavailable
 * (plugin treats unsigned as network-unavailable and fails open).
 */
function pipepay_phash_sign_verdict( $hash, $seen_elsewhere ) {
	if ( ! defined( 'PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY' ) || ! function_exists( 'sodium_crypto_sign_detached' ) ) {
		return null;
	}
	$issued = time();
	$msg    = sprintf( 'phash-v1|%d|%s|%s', $issued, $hash, $seen_elsewhere ? '1' : '0' );
	$sk     = base64_decode( PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY );
	if ( false === $sk ) {
		return null;
	}
	$sig = base64_encode( sodium_crypto_sign_detached( $msg, $sk ) );
	sodium_memzero( $sk );
	return array( 'sig' => $sig, 'issued_at' => $issued );
}
```

- [ ] **Step 2: Lint**

Run: `php -l "pipe-pay-site/mu-plugins/pipepay-phash-network.php"`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit (site repo)**

```bash
cd ~/Desktop/Pipe\ Pay/pipe-pay-site && git add mu-plugins/pipepay-phash-network.php && git commit -m "phash network mu-plugin: table install + helpers"
```

---

### Task 2: Server mu-plugin — REST routes (check-and-submit + bulk)

**Files:**
- Modify: `pipe-pay-site/mu-plugins/pipepay-phash-network.php` (append)

- [ ] **Step 1: Append the REST routes**

```php
add_action( 'rest_api_init', function () {
	register_rest_route( 'pipepay-phash/v1', '/submit', array(
		'methods'             => 'POST',
		'callback'            => 'pipepay_phash_handle_submit',
		'permission_callback' => '__return_true', // auth = license key in body, like the resolver
	) );
	register_rest_route( 'pipepay-phash/v1', '/submit-bulk', array(
		'methods'             => 'POST',
		'callback'            => 'pipepay_phash_handle_submit_bulk',
		'permission_callback' => '__return_true',
	) );
} );

/**
 * Shared front-half: HTTPS, shape, rate limits, license, bucket.
 * Returns array{bucket} on success or WP_REST_Response error to return as-is.
 */
function pipepay_phash_gate( WP_REST_Request $request ) {
	if ( ! is_ssl() ) {
		return new WP_REST_Response( array( 'success' => false, 'code' => 'https_required' ), 400 );
	}
	$api_key  = isset( $request['api_key'] ) ? (string) $request['api_key'] : '';
	$instance = isset( $request['instance'] ) ? (string) $request['instance'] : '';
	// Shape validation BEFORE rate-limit accounting (resolver lesson: malformed
	// junk must not burn a legitimate store's bucket on shared NAT).
	if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{8,190}$/', $api_key ) || 1 !== preg_match( '/^[A-Za-z0-9]{8,64}$/', $instance ) ) {
		return new WP_REST_Response( array( 'success' => false, 'code' => 'invalid_key' ), 404 );
	}
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( pipepay_phash_over_limit( 'ip|' . $ip, PIPEPAY_PHASH_IP_LIMIT, HOUR_IN_SECONDS ) ) {
		return new WP_REST_Response( array( 'success' => false, 'code' => 'rate_limited' ), 429 );
	}
	if ( ! pipepay_phash_license_ok( $api_key ) ) {
		// Opaque: same body as shape failure (no enumeration oracle).
		return new WP_REST_Response( array( 'success' => false, 'code' => 'invalid_key' ), 404 );
	}
	$bucket = pipepay_phash_bucket( $api_key, $instance );
	if ( pipepay_phash_over_limit( 'bucket|' . $bucket, PIPEPAY_PHASH_BUCKET_LIMIT, DAY_IN_SECONDS ) ) {
		return new WP_REST_Response( array( 'success' => false, 'code' => 'rate_limited' ), 429 );
	}
	return array( 'bucket' => $bucket );
}

/** POST /submit {api_key, instance, hash} -> {success, seen_elsewhere, signature, issued_at} */
function pipepay_phash_handle_submit( WP_REST_Request $request ) {
	$gate = pipepay_phash_gate( $request );
	if ( $gate instanceof WP_REST_Response ) {
		return $gate;
	}
	$hash = isset( $request['hash'] ) ? (string) $request['hash'] : '';
	if ( ! pipepay_phash_is_storable_hash( $hash ) ) {
		return new WP_REST_Response( array( 'success' => false, 'code' => 'invalid_hash' ), 400 );
	}
	global $wpdb;
	$table = $wpdb->prefix . 'pipepay_screenshot_hashes';
	// Seen on a DIFFERENT store? (check before our own insert is equivalent to
	// after, because we exclude our own bucket either way)
	$seen = (bool) $wpdb->get_var( $wpdb->prepare(
		"SELECT 1 FROM {$table} WHERE hash = %s AND store_bucket != %s LIMIT 1",
		$hash, $gate['bucket']
	) );
	$wpdb->query( $wpdb->prepare(
		"INSERT IGNORE INTO {$table} (hash, store_bucket, first_seen) VALUES (%s, %s, UTC_TIMESTAMP())",
		$hash, $gate['bucket']
	) );
	if ( '' !== (string) $wpdb->last_error ) {
		return new WP_REST_Response( array( 'success' => false, 'code' => 'service_unavailable' ), 503 );
	}
	$body = array( 'success' => true, 'seen_elsewhere' => $seen );
	$sig  = pipepay_phash_sign_verdict( $hash, $seen );
	if ( null !== $sig ) {
		$body['signature'] = $sig['sig'];
		$body['issued_at'] = $sig['issued_at'];
	}
	return new WP_REST_Response( $body, 200 );
}

/**
 * POST /submit-bulk {api_key, instance, hashes: string[]} -> {success, stored}
 * Write-only seeding path for the plugin's backfill cron. No verdicts, no
 * signature (nothing here drives a runtime decision).
 */
function pipepay_phash_handle_submit_bulk( WP_REST_Request $request ) {
	$gate = pipepay_phash_gate( $request );
	if ( $gate instanceof WP_REST_Response ) {
		return $gate;
	}
	$hashes = $request['hashes'];
	if ( ! is_array( $hashes ) || empty( $hashes ) || count( $hashes ) > PIPEPAY_PHASH_BULK_MAX ) {
		return new WP_REST_Response( array( 'success' => false, 'code' => 'invalid_hashes' ), 400 );
	}
	global $wpdb;
	$table  = $wpdb->prefix . 'pipepay_screenshot_hashes';
	$stored = 0;
	foreach ( array_unique( array_map( 'strval', $hashes ) ) as $hash ) {
		if ( ! pipepay_phash_is_storable_hash( $hash ) ) {
			continue; // skip silently: historical meta may contain degenerates
		}
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$table} (hash, store_bucket, first_seen) VALUES (%s, %s, UTC_TIMESTAMP())",
			$hash, $gate['bucket']
		) );
		if ( $wpdb->rows_affected > 0 ) {
			$stored++;
		}
	}
	return new WP_REST_Response( array( 'success' => true, 'stored' => $stored ), 200 );
}
```

- [ ] **Step 2: Lint**

Run: `php -l "pipe-pay-site/mu-plugins/pipepay-phash-network.php"`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit (site repo)**

```bash
cd ~/Desktop/Pipe\ Pay/pipe-pay-site && git add mu-plugins/pipepay-phash-network.php && git commit -m "phash network: check-and-submit + bulk REST routes"
```

---

### Task 3: Deploy mu-plugin + live smoke test

- [ ] **Step 1: Deploy**

```bash
scp "~/Desktop/Pipe Pay/pipe-pay-site/mu-plugins/pipepay-phash-network.php" witt-scafidi@100.102.251.125:/tmp/ && ssh witt-scafidi@100.102.251.125 'sudo install -o www-data -g www-data -m 644 /tmp/pipepay-phash-network.php /var/www/pipepay/wp-content/mu-plugins/pipepay-phash-network.php && rm /tmp/pipepay-phash-network.php && sudo systemctl reload php8.3-fpm'
```

- [ ] **Step 2: Verify table installed**

```bash
ssh witt-scafidi@100.102.251.125 'sudo -u www-data wp --path=/var/www/pipepay eval "do_action(\"plugins_loaded\");" >/dev/null 2>&1; sudo -u www-data wp --path=/var/www/pipepay db query "SHOW CREATE TABLE wp_pipepay_screenshot_hashes\G" | head -12'
```
Expected: table DDL with `UNIQUE KEY hash_bucket` and `KEY hash`.

- [ ] **Step 3: Smoke-test the gate (invalid key → opaque 404; bad hash with real key → 400; real submit → signed verdict; cross-bucket hit → seen_elsewhere=true)**

```bash
# 1. invalid key -> 404 invalid_key
curl -s -w "|%{http_code}\n" -X POST https://pipepay.app/wp-json/pipepay-phash/v1/submit -d "api_key=bogus_key_123456&instance=abcdef1234567890&hash=a1b2c3d4e5f60789"
# 2-4. server-side with a real key (never paste a key into chat/shell history):
ssh witt-scafidi@100.102.251.125 'sudo -u www-data wp --path=/var/www/pipepay eval "
\$key = \$GLOBALS[\"wpdb\"]->get_var(\"SELECT master_api_key FROM wp_wc_am_api_resource WHERE active=1 AND access_expires>UNIX_TIMESTAMP() ORDER BY api_resource_id ASC LIMIT 1\");
\$post = function( \$body ) { return json_decode( wp_remote_retrieve_body( wp_remote_post( \"https://pipepay.app/wp-json/pipepay-phash/v1/submit\", array( \"body\" => \$body, \"timeout\" => 10 ) ) ), true ); };
echo \"bad hash: \" . wp_json_encode( \$post( array( \"api_key\" => \$key, \"instance\" => \"aaaaaaaaaaaaaaaa\", \"hash\" => \"ZZZ\" ) ) ) . \"\n\";
echo \"degenerate: \" . wp_json_encode( \$post( array( \"api_key\" => \$key, \"instance\" => \"aaaaaaaaaaaaaaaa\", \"hash\" => \"0000000000000000\" ) ) ) . \"\n\";
echo \"store A submit: \" . wp_json_encode( \$post( array( \"api_key\" => \$key, \"instance\" => \"aaaaaaaaaaaaaaaa\", \"hash\" => \"deadbeefcafe0123\" ) ) ) . \"\n\";
echo \"store A resubmit (no self-flag): \" . wp_json_encode( \$post( array( \"api_key\" => \$key, \"instance\" => \"aaaaaaaaaaaaaaaa\", \"hash\" => \"deadbeefcafe0123\" ) ) ) . \"\n\";
echo \"store B same hash (cross-store hit): \" . wp_json_encode( \$post( array( \"api_key\" => \$key, \"instance\" => \"bbbbbbbbbbbbbbbb\", \"hash\" => \"deadbeefcafe0123\" ) ) ) . \"\n\";
// cleanup test rows
\$GLOBALS[\"wpdb\"]->query( \"DELETE FROM wp_pipepay_screenshot_hashes WHERE hash = \x27deadbeefcafe0123\x27\" );
echo \"cleaned\n\";
"'
```
Expected: `|404` for bogus key; `invalid_hash` ×2; store A submit + resubmit both `seen_elsewhere:false` with `signature` present; store B `seen_elsewhere:true`; `cleaned`.

- [ ] **Step 4: Commit any fixes; push site repo**

```bash
cd ~/Desktop/Pipe\ Pay/pipe-pay-site && git push
```

---

### Task 4: Plugin — Ed25519 verifier for the `phash-v1|` prefix (TDD)

**Files:**
- Modify: `pipe-pay-extracted/pipe-pay/includes/pipepay-license-verify.php` (append, next to the other three verifiers)
- Test: `pipe-pay-extracted/tests/PhashNetworkTest.php` (create)

- [ ] **Step 1: Write the failing tests**

Create `tests/PhashNetworkTest.php`. Follow the keypair pattern used in `tests/LicenseSignatureTest.php` (generate a fresh sodium keypair in the test, pass the public key via the `$override_public_key_b64` parameter):

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PhashNetworkTest extends TestCase {

	private string $public_b64;
	private string $secret;

	protected function setUp(): void {
		pipepay_test_reset_options();
		$kp               = sodium_crypto_sign_keypair();
		$this->secret     = sodium_crypto_sign_secretkey( $kp );
		$this->public_b64 = base64_encode( sodium_crypto_sign_publickey( $kp ) );
	}

	private function sign( string $msg ): string {
		return base64_encode( sodium_crypto_sign_detached( $msg, $this->secret ) );
	}

	public function test_signed_seen_verdict_verifies(): void {
		$issued = time();
		$sig    = $this->sign( "phash-v1|{$issued}|a1b2c3d4e5f60789|1" );
		$this->assertTrue( pipepay_license_verify_phash_signature( 'a1b2c3d4e5f60789', true, $issued, $sig, $this->public_b64 ) );
	}

	public function test_signed_not_seen_verdict_verifies(): void {
		$issued = time();
		$sig    = $this->sign( "phash-v1|{$issued}|a1b2c3d4e5f60789|0" );
		$this->assertTrue( pipepay_license_verify_phash_signature( 'a1b2c3d4e5f60789', false, $issued, $sig, $this->public_b64 ) );
	}

	public function test_flipped_seen_bit_fails(): void {
		$issued = time();
		$sig    = $this->sign( "phash-v1|{$issued}|a1b2c3d4e5f60789|0" );
		$this->assertNotTrue( pipepay_license_verify_phash_signature( 'a1b2c3d4e5f60789', true, $issued, $sig, $this->public_b64 ) );
	}

	public function test_different_hash_fails(): void {
		$issued = time();
		$sig    = $this->sign( "phash-v1|{$issued}|a1b2c3d4e5f60789|1" );
		$this->assertNotTrue( pipepay_license_verify_phash_signature( 'ffff000011112222', true, $issued, $sig, $this->public_b64 ) );
	}

	public function test_stale_signature_fails_at_301s(): void {
		$issued = time() - 301;
		$sig    = $this->sign( "phash-v1|{$issued}|a1b2c3d4e5f60789|1" );
		$this->assertNotTrue( pipepay_license_verify_phash_signature( 'a1b2c3d4e5f60789', true, $issued, $sig, $this->public_b64 ) );
	}

	public function test_boundary_300s_still_verifies(): void {
		$issued = time() - 300;
		$sig    = $this->sign( "phash-v1|{$issued}|a1b2c3d4e5f60789|1" );
		$this->assertTrue( pipepay_license_verify_phash_signature( 'a1b2c3d4e5f60789', true, $issued, $sig, $this->public_b64 ) );
	}

	public function test_revalidate_prefix_cannot_replay_as_phash(): void {
		$issued = time();
		$sig    = $this->sign( "revalidate-v1|{$issued}|a1b2c3d4e5f60789|1" );
		$this->assertNotTrue( pipepay_license_verify_phash_signature( 'a1b2c3d4e5f60789', true, $issued, $sig, $this->public_b64 ) );
	}

	public function test_canonical_format_is_pinned(): void {
		// Hand-built literal, NOT the production sprintf - catches a coordinated
		// format drift (the golden-vector lesson from v1.7.3).
		$issued = 1750000000;
		$msg    = 'phash-v1|' . '1750000000' . '|' . 'a1b2c3d4e5f60789' . '|' . '1';
		$sig    = $this->sign( $msg );
		$this->assertTrue( pipepay_license_verify_phash_signature( 'a1b2c3d4e5f60789', true, $issued, $sig, $this->public_b64, /* skip_age_check */ true ) );
	}
}
```

Note: the pinned-canonical test needs an age-check bypass for a fixed historic timestamp. Mirror however `LicenseSignatureTest.php` handles its golden vector (it either uses `time()` or an override flag); if the existing verifiers take no skip flag, build the message with `time()` instead and drop the sixth argument — keep consistency with the existing test file over this listing.

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd ~/Desktop/Pipe\ Pay/pipe-pay-extracted && composer exec phpunit -- --filter PhashNetworkTest`
Expected: errors — `pipepay_license_verify_phash_signature` undefined.

- [ ] **Step 3: Implement the verifier**

Append to `pipe-pay/includes/pipepay-license-verify.php`, copying the structure of `pipepay_license_verify_revalidate_signature()` exactly (same sodium guard, same error-string returns, same max-age constant). Only the canonical differs:

```php
/**
 * Verifies a phash-network verdict signature.
 * Canonical: phash-v1|<issued_at>|<hash>|<0|1>
 * Same 5-minute replay window as activation/revalidate (round-trip response).
 * Side-effect-free. Returns true or an error-code string.
 */
function pipepay_license_verify_phash_signature( $hash, $seen_elsewhere, $issued_at, $signature_b64, $override_public_key_b64 = null ) {
	if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
		return 'sodium_extension_missing';
	}
	$issued_at = (int) $issued_at;
	if ( $issued_at <= 0 ) {
		return 'missing_issued_at';
	}
	$age = time() - $issued_at;
	if ( $age > PIPEPAY_LICENSE_SIGNATURE_MAX_AGE ) {
		return 'signature_stale';
	}
	if ( $issued_at > time() + PIPEPAY_LICENSE_SIGNATURE_MAX_AGE ) {
		return 'signature_from_future';
	}
	$public_b64 = null !== $override_public_key_b64 ? $override_public_key_b64 : PIPEPAY_LICENSE_SIGNING_PUBLIC_KEY;
	$public     = base64_decode( (string) $public_b64, true );
	$signature  = base64_decode( (string) $signature_b64, true );
	if ( false === $public || false === $signature || SODIUM_CRYPTO_SIGN_BYTES !== strlen( $signature ) ) {
		return 'signature_malformed';
	}
	$msg = sprintf( 'phash-v1|%d|%s|%s', $issued_at, (string) $hash, $seen_elsewhere ? '1' : '0' );
	try {
		$ok = sodium_crypto_sign_verify_detached( $signature, $msg, $public );
	} catch ( \SodiumException $e ) {
		return 'signature_malformed';
	}
	return $ok ? true : 'signature_invalid';
}
```

(Adjust error-code strings and the exact guard order to match the existing revalidate verifier in the file — consistency with siblings wins over this listing.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `composer exec phpunit -- --filter PhashNetworkTest`
Expected: all PhashNetworkTest tests PASS. Then run the FULL suite: `composer exec phpunit` — expected: green, no regressions.

- [ ] **Step 5: Commit (plugin repo)**

```bash
cd ~/Desktop/Pipe\ Pay/pipe-pay-extracted && git add tests/PhashNetworkTest.php pipe-pay/includes/pipepay-license-verify.php && git commit -m "feat: phash-v1 verdict signature verifier (TDD)"
```

---

### Task 5: Plugin — degenerate-hash helper (TDD)

**Files:**
- Modify: `pipe-pay-extracted/pipe-pay/includes/pipepay-dhash.php` (append)
- Test: `pipe-pay-extracted/tests/PhashNetworkTest.php` (append)

- [ ] **Step 1: Write failing tests** (append to PhashNetworkTest.php)

```php
	public function test_all_zeros_hash_is_degenerate(): void {
		$this->assertTrue( pipepay_dhash_is_degenerate( '0000000000000000' ) );
	}

	public function test_all_ones_hash_is_degenerate(): void {
		$this->assertTrue( pipepay_dhash_is_degenerate( 'ffffffffffffffff' ) );
	}

	public function test_normal_hash_is_not_degenerate(): void {
		$this->assertFalse( pipepay_dhash_is_degenerate( 'a1b2c3d4e5f60789' ) );
	}

	public function test_non_string_is_degenerate(): void {
		$this->assertTrue( pipepay_dhash_is_degenerate( null ) );
	}
```

- [ ] **Step 2: Run to verify failure** — `composer exec phpunit -- --filter degenerate` → undefined function.

- [ ] **Step 3: Implement** (append to `pipepay-dhash.php`):

```php
/**
 * Degenerate hashes come from featureless images (uniform gradients collapse
 * every adjacent-pixel comparison the same way - the v1.8.9 test-fixture
 * lesson). They match EVERYTHING of the same kind, so the cross-store network
 * must neither store nor flag on them.
 */
function pipepay_dhash_is_degenerate( $hash ) {
	return ! is_string( $hash )
		|| '0000000000000000' === $hash
		|| 'ffffffffffffffff' === $hash;
}
```

- [ ] **Step 4: Run** — filter passes, then full suite green.

- [ ] **Step 5: Commit** — `git add -A && git commit -m "feat: degenerate dhash helper"`

---

### Task 6: Plugin — network client (TDD on the pure parser; thin HTTP wrapper)

**Files:**
- Create: `pipe-pay-extracted/pipe-pay/includes/pipepay-phash-network.php`
- Test: `pipe-pay-extracted/tests/PhashNetworkTest.php` (append)

- [ ] **Step 1: Write failing tests for the pure response parser** (append):

```php
	private function signed_body( string $hash, bool $seen ): array {
		$issued = time();
		return array(
			'success'        => true,
			'seen_elsewhere' => $seen,
			'issued_at'      => $issued,
			'signature'      => $this->sign( sprintf( 'phash-v1|%d|%s|%s', $issued, $hash, $seen ? '1' : '0' ) ),
		);
	}

	public function test_parse_valid_seen_response(): void {
		$out = pipepay_phash_parse_response( 200, $this->signed_body( 'a1b2c3d4e5f60789', true ), 'a1b2c3d4e5f60789', $this->public_b64 );
		$this->assertSame( array( 'seen_elsewhere' => true ), $out );
	}

	public function test_parse_valid_not_seen_response(): void {
		$out = pipepay_phash_parse_response( 200, $this->signed_body( 'a1b2c3d4e5f60789', false ), 'a1b2c3d4e5f60789', $this->public_b64 );
		$this->assertSame( array( 'seen_elsewhere' => false ), $out );
	}

	public function test_parse_http_500_returns_null(): void {
		$this->assertNull( pipepay_phash_parse_response( 500, $this->signed_body( 'a1b2c3d4e5f60789', true ), 'a1b2c3d4e5f60789', $this->public_b64 ) );
	}

	public function test_parse_missing_signature_returns_null(): void {
		$body = $this->signed_body( 'a1b2c3d4e5f60789', true );
		unset( $body['signature'] );
		$this->assertNull( pipepay_phash_parse_response( 200, $body, 'a1b2c3d4e5f60789', $this->public_b64 ) );
	}

	public function test_parse_signature_for_other_hash_returns_null(): void {
		$body = $this->signed_body( 'ffff000011112222', true ); // signed for a different hash
		$this->assertNull( pipepay_phash_parse_response( 200, $body, 'a1b2c3d4e5f60789', $this->public_b64 ) );
	}

	public function test_parse_non_array_body_returns_null(): void {
		$this->assertNull( pipepay_phash_parse_response( 200, null, 'a1b2c3d4e5f60789', $this->public_b64 ) );
	}
```

- [ ] **Step 2: Run to verify failure.**

- [ ] **Step 3: Create the client file**

`pipe-pay/includes/pipepay-phash-network.php`:

```php
<?php
/**
 * Cross-store screenshot-hash network client.
 *
 * Submits the existing per-upload dHash (never the image) to pipepay.app and
 * learns whether the same hash was already seen on a DIFFERENT Pipe Pay store.
 * Fail-open by design: no license, network error, timeout, or bad signature
 * all return null and the upload proceeds exactly as it does today. A
 * confirmed cross-store hit forces manual review (fail-safe direction only).
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'PIPEPAY_PHASH_ENDPOINT' ) ) {
	define( 'PIPEPAY_PHASH_ENDPOINT', 'https://pipepay.app/wp-json/pipepay-phash/v1/submit' );
}
if ( ! defined( 'PIPEPAY_PHASH_BULK_ENDPOINT' ) ) {
	define( 'PIPEPAY_PHASH_BULK_ENDPOINT', 'https://pipepay.app/wp-json/pipepay-phash/v1/submit-bulk' );
}
if ( ! defined( 'PIPEPAY_PHASH_TIMEOUT' ) ) {
	define( 'PIPEPAY_PHASH_TIMEOUT', 3 ); // seconds; upload path latency budget
}

/** Network participation: gateway toggle (default on) AND an activated license. */
function pipepay_phash_network_enabled() {
	$settings = get_option( 'woocommerce_pipepay_settings', array() );
	$toggle   = isset( $settings['phash_network'] ) ? $settings['phash_network'] : 'yes';
	return 'yes' === $toggle && '' !== (string) get_option( 'pipepay_license_api_key', '' );
}

/**
 * Pure response parser (unit-tested). Returns array{seen_elsewhere:bool} on a
 * valid signed verdict, null on anything else.
 */
function pipepay_phash_parse_response( $http_code, $body, $hash, $override_public_key_b64 = null ) {
	if ( 200 !== (int) $http_code || ! is_array( $body ) || empty( $body['success'] ) ) {
		return null;
	}
	$seen   = ! empty( $body['seen_elsewhere'] );
	$sig    = isset( $body['signature'] ) ? (string) $body['signature'] : '';
	$issued = isset( $body['issued_at'] ) ? (int) $body['issued_at'] : 0;
	if ( '' === $sig ) {
		return null;
	}
	if ( true !== pipepay_license_verify_phash_signature( $hash, $seen, $issued, $sig, $override_public_key_b64 ) ) {
		return null;
	}
	return array( 'seen_elsewhere' => $seen );
}

/** Thin HTTP wrapper around the parser. Returns array{seen_elsewhere} or null. */
function pipepay_phash_network_submit( $hash ) {
	if ( ! pipepay_phash_network_enabled() ) {
		return null;
	}
	if ( ! is_string( $hash ) || 1 !== preg_match( '/^[0-9a-f]{16}$/', $hash ) || pipepay_dhash_is_degenerate( $hash ) ) {
		return null;
	}
	$response = wp_remote_post( PIPEPAY_PHASH_ENDPOINT, array(
		'timeout'           => PIPEPAY_PHASH_TIMEOUT,
		'redirection'       => 0,
		'sslverify'         => true,
		'reject_unsafe_urls'=> true,
		'body'              => array(
			'api_key'  => (string) get_option( 'pipepay_license_api_key', '' ),
			'instance' => function_exists( 'pipepay_license_get_instance' ) ? pipepay_license_get_instance() : '',
			'hash'     => $hash,
		),
	) );
	if ( is_wp_error( $response ) ) {
		if ( function_exists( 'pipepay_log' ) ) {
			pipepay_log( 'info', 'phash network unreachable', array( 'error' => $response->get_error_code() ) );
		}
		return null;
	}
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	return pipepay_phash_parse_response( wp_remote_retrieve_response_code( $response ), is_array( $body ) ? $body : null, $hash );
}
```

Then add the require in `pipe-pay/pipe-pay.php` next to the existing `require` for `pipepay-dhash.php`:

```php
require_once PIPEPAY_PLUGIN_DIR . 'includes/pipepay-phash-network.php';
```

(Match the exact require style/constant used by the neighboring lines.)

- [ ] **Step 4: Run** — `composer exec phpunit` full suite green. If the bootstrap lacks any stub the new file needs at load time, add it to `tests/bootstrap.php` following the existing stub patterns.

- [ ] **Step 5: Commit** — `git add -A && git commit -m "feat: phash network client (parser TDD, fail-open wrapper)"`

---

### Task 7: Plugin — upload-flow integration (force manual review on cross-store hit)

**Files:**
- Modify: `pipe-pay-extracted/pipe-pay/includes/pipepay-hooks.php` — inside the existing dHash block. Anchor: the line `$dhash_duplicates = pipepay_dhash_find_duplicates( $dhash, $order->get_id() );` (currently ~line 407).

- [ ] **Step 1: Insert the network check**

Immediately AFTER the local-duplicates `if ( ! empty( $dhash_duplicates ) ) { ... }` block closes (still inside the `else` branch where `$dhash` is valid), add:

```php
				// ── Cross-store network check (v1.10.0) ──────────────────
				// Same hash seen on a DIFFERENT Pipe Pay store. Fail-open:
				// null (disabled / unreachable / unsigned) changes nothing.
				$phash_network = function_exists( 'pipepay_phash_network_submit' )
					? pipepay_phash_network_submit( $dhash )
					: null;
				if ( is_array( $phash_network ) && ! empty( $phash_network['seen_elsewhere'] ) ) {
					$order->update_meta_data( '_pipepay_proof_dhash_network_hit', (string) time() );
					$dhash_force_manual_review = true;
					$order->add_order_note(
						'Pipe Pay: this screenshot\'s fingerprint matches one already submitted on another Pipe Pay store. Routed to manual review.'
					);
					if ( function_exists( 'pipepay_log' ) ) {
						pipepay_log( 'warning', 'phash network hit', array(
							'order_id' => $order->get_id(),
							// hash deliberately not logged (same rule as local dhash)
						) );
					}
				}
```

- [ ] **Step 2: Lint + full suite**

Run: `php -l pipe-pay/includes/pipepay-hooks.php && composer exec phpunit`
Expected: clean + green (this block has no unit-test surface — `wc_get_orders`-style integration; it's covered by the live E2E in Task 12, same as the local dhash scan).

- [ ] **Step 3: Commit** — `git commit -am "feat: force manual review on cross-store phash network hit"`

---

### Task 8: Plugin — Proofs-queue badge + gateway opt-out setting

**Files:**
- Modify: `pipe-pay-extracted/pipe-pay/includes/class-pipepay-admin.php` — anchor: the existing duplicate-badge render reading `_pipepay_proof_dhash_duplicate_of` (~line 452-455).
- Modify: `pipe-pay-extracted/pipe-pay/includes/class-pipepay-gateway.php` — `init_form_fields()` (built lazily via `get_form_fields()`, ~line 270).

- [ ] **Step 1: Badge.** Directly after the existing duplicate-badge block in the Proofs queue row render, add (reuse the same badge markup/classes as the duplicate badge so styling is inherited — copy its wrapper element exactly and change only text + meta key):

```php
						$network_hit = $order->get_meta( '_pipepay_proof_dhash_network_hit', true );
						if ( ! empty( $network_hit ) ) {
							echo '<div class="pipepay-dhash-badge" style="color:#b32d2e;font-weight:600;margin-top:4px;">'
								. esc_html__( 'Screenshot seen on another Pipe Pay store', 'pipe-pay' )
								. '</div>';
						}
```

(If the duplicate badge uses a different class name, mirror it; the listing's inline style is the fallback.) Apply the same snippet in BOTH render sites if the queue and the order meta box each render badges — grep `_pipepay_proof_dhash_duplicate_of` and mirror every render site.

- [ ] **Step 2: Setting.** In `init_form_fields()`, after the AI-provider fields, add:

```php
			'phash_network' => array(
				'title'       => __( 'Cross-store fraud network', 'pipe-pay' ),
				'type'        => 'checkbox',
				'label'       => __( 'Check uploads against the Pipe Pay network (fingerprints only — images never leave your site)', 'pipe-pay' ),
				'default'     => 'yes',
				'description' => __( 'Each screenshot\'s 64-bit one-way fingerprint is compared against fingerprints from other Pipe Pay stores. A match routes the order to manual review. The screenshot itself is never transmitted.', 'pipe-pay' ),
			),
```

- [ ] **Step 3: Lint + suite + commit**

```bash
php -l pipe-pay/includes/class-pipepay-admin.php && php -l pipe-pay/includes/class-pipepay-gateway.php && composer exec phpunit
git commit -am "feat: network-hit badge + cross-store network opt-out setting"
```

---

### Task 9: Plugin — backfill cron (seeds the network with historical hashes)

**Files:**
- Modify: `pipe-pay-extracted/pipe-pay/includes/pipepay-phash-network.php` (append)
- Modify: `pipe-pay-extracted/pipe-pay/pipe-pay.php` (activation + upgrade scheduling; deactivation cleanup)
- Test: `pipe-pay-extracted/tests/PhashNetworkTest.php` (append, batch-filter helper only)

- [ ] **Step 1: Failing test for the pure batch filter** (append):

```php
	public function test_backfill_filter_drops_invalid_and_degenerate_and_dupes(): void {
		$in  = array( 'a1b2c3d4e5f60789', 'ZZZ', '0000000000000000', 'a1b2c3d4e5f60789', 'ffffffffffffffff', 'cafe0123beef4567', '' );
		$out = pipepay_phash_backfill_filter( $in );
		$this->assertSame( array( 'a1b2c3d4e5f60789', 'cafe0123beef4567' ), $out );
	}
```

- [ ] **Step 2: Run to verify failure.**

- [ ] **Step 3: Implement** (append to `pipepay-phash-network.php`):

```php
/** Pure: dedupe + drop invalid/degenerate hashes. Order-preserving. */
function pipepay_phash_backfill_filter( $hashes ) {
	$out = array();
	foreach ( (array) $hashes as $hash ) {
		if ( is_string( $hash )
			&& 1 === preg_match( '/^[0-9a-f]{16}$/', $hash )
			&& ! pipepay_dhash_is_degenerate( $hash )
			&& ! in_array( $hash, $out, true ) ) {
			$out[] = $hash;
		}
	}
	return $out;
}

/**
 * One-time backfill: every order ever processed already carries
 * _pipepay_proof_dhash meta (stored permanently since v1.8.8). Walk it in
 * batches of 200, bulk-submit, self-reschedule until done. Progress survives
 * restarts via an option cursor.
 */
add_action( 'pipepay_phash_backfill', 'pipepay_phash_backfill_run' );

function pipepay_phash_backfill_run() {
	if ( ! pipepay_phash_network_enabled() || get_option( 'pipepay_phash_backfill_done' ) ) {
		return;
	}
	global $wpdb;
	$cursor = (int) get_option( 'pipepay_phash_backfill_cursor', 0 );
	// HPOS vs classic postmeta - same branch shape as the v1.8.11 dhash scan.
	if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class )
		&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id AS cursor_id, meta_value FROM {$wpdb->prefix}wc_orders_meta
			 WHERE meta_key = '_pipepay_proof_dhash' AND id > %d ORDER BY id ASC LIMIT 200",
			$cursor
		) );
	} else {
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT meta_id AS cursor_id, meta_value FROM {$wpdb->postmeta}
			 WHERE meta_key = '_pipepay_proof_dhash' AND meta_id > %d ORDER BY meta_id ASC LIMIT 200",
			$cursor
		) );
	}
	if ( empty( $rows ) ) {
		update_option( 'pipepay_phash_backfill_done', (string) time() );
		delete_option( 'pipepay_phash_backfill_cursor' );
		if ( function_exists( 'pipepay_log' ) ) {
			pipepay_log( 'info', 'phash backfill complete', array() );
		}
		return;
	}
	$hashes = pipepay_phash_backfill_filter( wp_list_pluck( $rows, 'meta_value' ) );
	if ( ! empty( $hashes ) ) {
		$response = wp_remote_post( PIPEPAY_PHASH_BULK_ENDPOINT, array(
			'timeout'            => 15,
			'redirection'        => 0,
			'sslverify'          => true,
			'reject_unsafe_urls' => true,
			'body'               => array(
				'api_key'  => (string) get_option( 'pipepay_license_api_key', '' ),
				'instance' => function_exists( 'pipepay_license_get_instance' ) ? pipepay_license_get_instance() : '',
				'hashes'   => $hashes,
			),
		) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			// Leave the cursor where it is; retry this batch in an hour.
			wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'pipepay_phash_backfill' );
			return;
		}
	}
	update_option( 'pipepay_phash_backfill_cursor', (int) end( $rows )->cursor_id );
	wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'pipepay_phash_backfill' );
}

/** Schedule the backfill once after upgrade/activation (idempotent). */
function pipepay_phash_backfill_maybe_schedule() {
	if ( pipepay_phash_network_enabled()
		&& ! get_option( 'pipepay_phash_backfill_done' )
		&& ! wp_next_scheduled( 'pipepay_phash_backfill' ) ) {
		wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, 'pipepay_phash_backfill' );
	}
}
add_action( 'admin_init', 'pipepay_phash_backfill_maybe_schedule' );
add_action( 'init', 'pipepay_phash_backfill_maybe_schedule', 20 ); // headless installs (v1.8.4 lesson)
```

In `pipe-pay/pipe-pay.php`, find `pipepay_deactivate()` (it already unschedules `pipepay_license_revalidate`) and add:

```php
	wp_clear_scheduled_hook( 'pipepay_phash_backfill' );
```

- [ ] **Step 4: Run filter test + full suite — green. Commit:** `git commit -am "feat: phash network backfill cron"`

---

### Task 10: Plugin — uninstall cleanup (v1.8.10 lesson: never orphan options/meta)

**Files:**
- Modify: `pipe-pay-extracted/pipe-pay/uninstall.php`

- [ ] **Step 1:** Find the existing option-cleanup list (it already deletes `pipepay_license_*` options) and add:

```php
delete_option( 'pipepay_phash_backfill_cursor' );
delete_option( 'pipepay_phash_backfill_done' );
```

Find the existing dHash meta-key cleanup (HPOS-aware branch deleting `_pipepay_proof_dhash` etc.) and add `_pipepay_proof_dhash_network_hit` to the same key list. Also add `wp_clear_scheduled_hook( 'pipepay_phash_backfill' );` next to the existing cron cleanup.

- [ ] **Step 2: Lint + suite + commit** — `git commit -am "chore: uninstall cleanup for phash network"`

---

### Task 11: Version bump, zip, tag, deploy to dogfood

- [ ] **Step 1:** Bump `PIPEPAY_VERSION` to `1.10.0` in `pipe-pay/pipe-pay.php` — BOTH the header comment `Version:` line AND the `define` (the sed-misses-the-header mistake has happened twice; verify with `grep -c "1\.10\.0" pipe-pay/pipe-pay.php` → expect 2).
- [ ] **Step 2:** Full suite: `composer exec phpunit` → green. Push: `git add -A && git commit -m "release: v1.10.0 cross-store screenshot network" && git tag v1.10.0 && git push origin main v1.10.0`. Wait for CI green (PHP 8.1/8.2/8.3).
- [ ] **Step 3:** Build the zip exactly like prior releases (zip the `pipe-pay/` dir as `~/Desktop/Pipe Pay/pipe-pay-v1.10.0.zip`, excluding `._*`).
- [ ] **Step 4:** Deploy to dogfood per the CLAUDE.md cheatsheet (`wp plugin install /tmp/pipe-pay-v1.10.0.zip --force` path), stage the zip in `woocommerce_uploads/`, repoint all 7 WC products' `_downloadable_files` + `_product_version` via direct `update_post_meta` (NOT `WC_Product->save()` — it clobbers these keys).
- [ ] **Step 5:** Verify: `wp plugin list | grep pipe-pay` shows 1.10.0 active; no fatals in php-fpm log; `wp eval 'var_dump( function_exists("pipepay_phash_network_submit") );'` → true.

---

### Task 12: Live end-to-end test on dogfood

- [ ] **Step 1: Backfill runs.** `wp cron event run pipepay_phash_backfill` repeatedly until `wp option get pipepay_phash_backfill_done` is set. Then `wp db query "SELECT COUNT(*) FROM wp_pipepay_screenshot_hashes"` — expect ≥ the number of dogfood orders that have `_pipepay_proof_dhash` meta (compare with a COUNT on the meta key).
- [ ] **Step 2: Cross-store hit, simulated.** Insert a synthetic row under a DIFFERENT bucket for a known dogfood hash, then run `pipepay_phash_network_submit($hash)` via `wp eval` — expect `array( 'seen_elsewhere' => true )`. Delete the synthetic row; rerun — expect `seen_elsewhere => false` (own-bucket rows don't self-flag).
- [ ] **Step 3: Full customer-path test.** Place a test order on the dogfood (Pipe Pay gateway), upload a screenshot whose hash was pre-seeded under a foreign bucket → order must land in `awaiting-approval` with the "Screenshot seen on another Pipe Pay store" badge and the order note. Clean up the test order + synthetic rows.
- [ ] **Step 4: Fail-open test.** Temporarily set `PIPEPAY_PHASH_ENDPOINT` unreachable via `wp config set` of a test constant or by uploading with the toggle off → upload must succeed normally with no flag and an info log line.

---

### Task 13: Docs, privacy disclosure, CLAUDE.md, memory

- [ ] **Step 1:** `pipe-pay-site/pipepay-child/page-data-handling.php` + `page-privacy.php`: add the network disclosure — what's sent (64-bit one-way fingerprint), what's never sent (the image, amounts, names), the opt-out toggle, retention (indefinite, non-personal). Bump each file's `$last_updated`.
- [ ] **Step 2:** `pipe-pay-site/CLAUDE.md`: move "cross-merchant pHash detection" from the V1.5/V2 roadmap to shipped (one entry in Resolved, pointing at this plan file); note the table name, endpoint, bucket design, and the exact-match-only limitation with the band-index upgrade path.
- [ ] **Step 3:** Update the doc article on fraud/AI verification (`page-doc-stub.php`, `ai-verification` and/or `security` entries) with a short merchant-facing paragraph.
- [ ] **Step 4:** Memory file: add the network to `pipepay-stripe-subs-v0.1.md`'s sibling or a new memory (`pipepay-phash-network.md`) with architecture + gotchas; add MEMORY.md index line.
- [ ] **Step 5:** Deploy theme (cheatsheet tar/scp), commit + push both repos.

---

## Self-Review (done at planning time)

- **Spec coverage:** server ingest ✓ (T1-3), plugin submit ✓ (T6-7), backfill ✓ (T9), collision response ✓ (T7-8), opt-out ✓ (T8), privacy/docs ✓ (T13), uninstall hygiene ✓ (T10), live verification ✓ (T3, T12).
- **Known judgment calls an implementer must respect:** match sibling code conventions over this plan's listings when they conflict (verifier error codes, badge markup, require style); never paste a real license key into chat or shell history (Task 3's server-side eval pattern exists for this reason).
- **Deliberately out of scope (do not add):** Hamming-near server matching, server admin dashboard, alerting, hash pruning, per-merchant analytics. All YAGNI until the network has real volume.
