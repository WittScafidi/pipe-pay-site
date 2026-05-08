# Pipe Pay License Resolver Review — 2026-05-07

> Source: `superpowers:code-reviewer` agent. Scope: `mu-plugins/pipepay-license-resolve.php` (single file, public REST endpoint hit by every Pipe Pay plugin install in the world).

## Findings

### Critical
None. No SQLi, no auth bypass, no RCE.

### High

**H1. Rate-limit transient race condition** — `pipepay-license-resolve.php:68-76`
`get_transient` + `set_transient` is read-modify-write with no atomicity. Concurrent requests from the same IP both read `$count`, both write `$count+1`, the limit is effectively bypassed. Under burst attack (60 parallel connections), an attacker can drive far more than 60 lookups/hour through. **Fix:** use `wp_cache_add` for first hit (atomic) then `wp_cache_incr` thereafter, with transient as a TTL-backed fallback. Or accept the slop and tighten the threshold significantly (e.g. 20/hr).

**H2. Rate limit applied before key-shape validation** — `pipepay-license-resolve.php:66-89`
The IP rate-limit counter is incremented for every request, including malformed ones. An attacker can exhaust a real customer's bucket by spoofing nothing — they just need to share an outbound NAT (corporate office, mobile carrier CGNAT). **Fix:** validate key shape first, only increment the counter on requests that actually hit the DB. Alternatively, add a separate higher-threshold "bad request" bucket.

**H3. Key-not-found vs key-inactive distinction is an enumeration oracle** — `pipepay-license-resolve.php:110-124`
`key_not_found` returns 404 with one message; `key_inactive` returns 403 with a different message ("This license key has been deactivated"). An attacker who guesses a key (or harvests one from a leaked support ticket / customer email) gets confirmation it's a real key just deactivated. The file's own header comment (line 30-31) claims this doesn't happen — it does. **Fix:** collapse both to the same generic 404 + `invalid_key` body. Distinguish only in server-side logs if needed.

### Medium

**M1. No HTTPS enforcement at the endpoint** — entire file
The mu-plugin trusts WP's globally-configured HTTPS state (set from `X-Forwarded-Proto` in wp-config). If Cloudflare were ever bypassed and someone hit the OptiPlex on `:80` directly, this endpoint would happily accept license keys over plaintext. **Fix:** add an explicit `is_ssl()` guard at the top of the handler returning 400 if false.

**M2. `sanitize_text_field` strips characters silently before length check** — `pipepay-license-resolve.php:48,78,83`
`sanitize_text_field` strips tabs/newlines/extra whitespace. A key entered with stray whitespace gets silently mutated, then length-validated, then queried. Low risk (Kestrel keys are alnum), but if Kestrel ever issues keys with special chars, validation passes but lookup fails confusingly. **Fix:** add an explicit charset whitelist regex (`/^[A-Za-z0-9_-]{8,190}$/`) before the DB call.

**M3. No defense against `wpdb` returning `WP_Error` / DB outage** — `pipepay-license-resolve.php:100-110`
If `$wpdb->get_row` errors (table missing, DB down, Kestrel renamed `wp_wc_am_api_resource`), it returns null/false and the code falls through to the generic `key_not_found` response. Customers see "License key not recognized" when the real cause is a Kestrel schema change or DB outage — they'll spam support thinking they pasted wrong. **Fix:** check `$wpdb->last_error` after the query; if non-empty, return 503 + `service_unavailable` with a generic message, and `error_log` the actual error for ops visibility.

**M4. No logging at all** — entire file
This is "secure" in the leak-nothing sense, but operationally blind — you can't tell if the endpoint is being probed, if real customers are 429ing, or if Kestrel schema drift is silently breaking lookups. **Fix:** add `error_log` of `[ip, last-4-of-key, response-code]` (never full key) on 4xx/5xx outcomes only.

### Low

**L1. `0.0.0.0` shared-bucket fallback is reasonable but undocumented in the rate-limit response** — `pipepay-license-resolve.php:152`
A real customer behind a misconfigured proxy could get pooled into the global bucket and rate-limited spuriously.

**L2. No version pinning to Kestrel's `wc_am_api_resource` schema** — `pipepay-license-resolve.php:92,101`
Mu-plugin assumes columns `master_api_key`, `product_id`, `product_title`, `active`, `api_resource_id`. If Kestrel renames any of these in 3.8.x, the resolver fails open-as-404 (see M3) and every customer activation breaks silently. **Fix:** on plugin load, do a one-time `SHOW COLUMNS FROM {$table}` check and `error_log` a warning if expected columns are missing.

**L3. WP/PHP version assumptions are fine** — entire file
File uses `: WP_REST_Response` return type (PHP 7+) and standard WP REST API. Compatible with WP 6.9.4 and PHP 8.3 in production.

**L4. No idempotency concern in this file** — entire file
Idempotency is the SDK/Kestrel's responsibility (and per CLAUDE.md is already handled). Not in scope here.

**L5. Permission callback is correctly `__return_true`** — `pipepay-license-resolve.php:43`
Intentional public access for an anonymous license-resolution endpoint.

## Overall Posture

Solid foundation: parameterized SQL, no info-leak in error bodies (mostly), POST body keeps keys out of nginx logs, real-IP resolution via nginx (not header trust), conservative response payload (only product_id + title). The two issues that matter for production hardening are the **rate-limit race** (H1) and the **key_inactive enumeration oracle** (H3) — both are real-attacker-tractable, both are five-line fixes. After those, add operational visibility (M3 + M4) so you can see when something breaks. Everything else is polish. Ship the H-tier fixes before WWP single-rail launch puts real adversarial load on this endpoint.
