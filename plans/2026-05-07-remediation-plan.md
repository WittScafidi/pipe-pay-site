# Pipe Pay Remediation Plan — 2026-05-07

> Drafted from the five-agent code review on 2026-05-07. Reports live in `pipe-pay-site/reviews/` and `pipe-pay-extracted/reviews/`. This plan organizes those findings into executable phases with effort estimates, success criteria, and ship sequencing.

## Strategy

Six phases, ordered by **risk-adjusted leverage** (do high-impact zero-risk work first; defer the work that needs careful testing or a plugin release).

```
Phase 1: Doc truth-up               (site repo, no risk, ~2h)
Phase 2: Site a11y + SEO            (site repo, low risk, ~half day)
Phase 4: License-resolver hardening (site repo, low risk, ~half day, ships with site deploy)
Phase 3: Plugin v1.6.4 — security + bugs (plugin release, careful, ~2-3 days)
Phase 5: Plugin code quality        (rolling, multi-release, ~2 weeks total)
Phase 6: Site theme polish          (rolling, ~half day)
```

**Why this order:** Phase 1+2+4 ship today and remove the most embarrassing findings (false claims in customer docs, broken accessibility, public-endpoint enumeration oracle) at zero risk to the live gateway. Phase 3 is a plugin release — bigger blast radius because the v1.6.4 zip will hit every customer's "Update Available" notification — so do it after the easier wins build confidence. Phase 5+6 are continuous improvement, not blockers.

---

## Phase 1 — Doc truth-up (~2 hours, site repo only, no risk)

**Goal:** every factual claim in customer-facing docs and CLAUDE.md matches the code.

### Tasks

#### 1.1 Fix the four load-bearing fictions in `page-doc-stub.php`
- [ ] **Storage constant name**: replace `PIPEPAY_PROOF_STORAGE_PATH` with `PIPEPAY_PROOFS_PATH` in the security article (line ~308). Verify the example code block still makes sense.
- [ ] **Auto-cancel constant**: remove the `PIPEPAY_AUTOCANCEL_MINUTES` code block from the order-lifecycle article (~line 246). Replace with: "Adjust the auto-cancel window in WooCommerce → Settings → Payments → Pipe Pay → Reminders & Expiry."
- [ ] **`wc-on-hold-review` status**: remove that paragraph from the order-lifecycle article (~line 219). Medium/low-confidence orders go to standard `wc-on-hold`, not a custom status.
- [ ] **`pipepay-reminders.php` claim**: remove it from the order-lifecycle article (~line 247). Replace with: "Reminder cadence is configurable in WooCommerce → Settings → Payments → Pipe Pay → Reminders & Expiry."

#### 1.2 Fix the rate-limit numbers in the security article
- [ ] Change "20 uploads per hour per source IP" to "10 valid uploads per order per IP per hour."
- [ ] Remove the per-customer-per-day claim (it doesn't exist in code).
- [ ] Change per-order lifetime from "3" to "5."
- [ ] Remove the "tunable via constants in wp-config.php" claim — none of the rate limits are wp-config tunable.
- [ ] Add the brute-force counter: "Failed-key uploads are capped at 50/hour per IP to prevent enumeration."

#### 1.3 Fix the AI verification article
- [ ] Auto-approve cap default: change `$200` → `$500` (~line 75).

#### 1.4 Fix the troubleshooting article
- [ ] Remove "falls back to GD when Imagick is unavailable" (~line 396) — there is no GD fallback. Replace with: "HEIC requires Imagick + the HEIC delegate; without it, HEIC uploads are rejected with an inline error pointing the customer at common workarounds."
- [ ] Change "deletion job runs every six hours" → "daily" (~line 319).
- [ ] Soften the log-retention claim: "Day-to-day Pipe Pay events are written to per-order Order Notes; unexpected failures land in PHP's `error_log`. The Kestrel SDK uses WC's logger for license-server errors; those follow WC's default 30-day retention."

#### 1.5 Fix the license-management article
- [ ] Site-tier prices: $249/$499/$999 → $299/$599/$1,199 (~lines 351-353).
- [ ] License-server URL: replace `https://pipepay.app/wp-json/wc-am-api/v1/` with `https://pipepay.app/?wc-api=wc-am-api` (~line 347).
- [ ] Renewal cadence: soften to "the SDK piggy-backs on WordPress's standard plugin update check (typically every ~12 hours)" — remove the 30/7/1 day notice claim, those are scheduled by Kestrel server-side and not user-tunable from the plugin.

#### 1.6 Fix the configuration article
- [ ] QR auto-hide breakpoint: 600px → 720px (~line 186).

#### 1.7 Fix the storage path claim
- [ ] Default storage path: replace `wp-content/uploads/pipepay-proofs/` with `wp-content/private-pipepay-proofs/` (primary) and mention `wp-content/uploads/pipepay-proofs-private/` as the fallback when the primary isn't writable (~line 298).

#### 1.8 Fix CLAUDE.md version drift
- [ ] Global s/1.6.1/1.6.3/ — currently mixes the two. Special attention to:
  - Line 84 (Plugins active section)
  - Lines 226-237 (smoke-test instructions)
  - Line 321 ("current customer-facing release")
  - Lines 331-332 (deploy cheatsheet `unzip pipe-pay-v1.6.1.zip`)
- [ ] Update "Local zips" section to include 1.6.2 and 1.6.3.

#### 1.9 Fix gateway description in CLAUDE.md
- [ ] CLAUDE.md `:155` claims a default checkout description that doesn't match `class-pipepay-gateway.php:143`. Either update CLAUDE.md to match the actual default, or rephrase as "configured-on-this-site value, set via WP Admin."

#### 1.10 Footer version display
- [ ] `pipepay-child/footer.php:55` and `front-page.php:511` hardcode `v1.6.2`. Define a single `PIPEPAY_SITE_VERSION` constant in `functions.php` set to the current plugin version (`'1.6.3'`), echo it via `esc_html()` in both places. Update on each plugin release.

### Success criteria
- Every claim in the 9 doc articles can be verified against the code by grep or by clicking through the WP admin UI.
- CLAUDE.md no longer mentions 1.6.1.
- Footer reads `v1.6.3` everywhere it appears.

### Deploy
- `tar czf` / `scp` / `tar xzf` the `pipepay-child/` theme.
- Cloudflare → Caching → Configuration → Purge Everything.

---

## Phase 2 — Site accessibility + SEO (~half day, site repo only, low risk)

**Goal:** WCAG 2.4.1 + 2.5.5 compliance on the marketing site; full SEO meta on every template.

### Tasks

#### 2.1 Skip-to-content link
- [ ] Add a visually-hidden-until-focused link as the first child of `<body>` in both `header.php` and `front-page.php`:
  ```html
  <a class="pp-skip" href="#content">Skip to content</a>
  ```
- [ ] Add `id="content"` to `<main>` in both files.
- [ ] Add `.pp-skip` CSS: `position: absolute; top: -100px; left: 0; …; &:focus { top: 0; }`.

#### 2.2 Mobile drawer focus management (`functions.php:140-171`)
- [ ] On open: store the previously-focused element, move focus into the drawer (first link).
- [ ] On close (any path — ESC, link click, button toggle): return focus to `.pp-nav-toggle`.
- [ ] Add `inert` attribute (or focus-trap with JS) on hidden header content + main while the drawer is open. Modern Safari + Chrome support `inert` natively.
- [ ] Add visible focus styles to nav links inside the drawer (currently inheriting browser default ring on white).

#### 2.3 Bump hamburger to 44×44
- [ ] `style.css:423-424`: `width: 44px; height: 44px;` (was 40×40). WCAG 2.5.5 AAA + Apple HIG requirement.

#### 2.4 Per-page SEO meta (`functions.php:67-76`)
- [ ] Currently OG/Twitter/description only fire on `is_front_page()`. Refactor to emit on every template:
  - `<meta name="description">` from the page's excerpt or a per-template default
  - `<link rel="canonical">` from `wp_get_canonical_url()`
  - `og:title`, `og:description`, `og:url`, `og:type`
  - `twitter:card` (summary_large_image), `twitter:title`, `twitter:description`, `twitter:image`
- [ ] Add a default `og:image` (e.g. a 1200×630 brand banner). Without it the Twitter card with `summary_large_image` is broken on every page.
- [ ] Use `esc_url( home_url( '/' ) )` for the OG URL on home (currently hardcoded).

#### 2.5 Replace hardcoded URLs in `front-page.php:19-24`
- [ ] Change all six `https://pipepay.app/...` strings to `home_url('/...')` calls. Recreates the exact class of bug the domain migration just fixed.

#### 2.6 Extract inline logo SVG to a partial
- [ ] Create `pipepay-child/partials/logo-svg.php` taking a `$variant` argument (`'currentColor'` default, `'inverse'` for the white-on-blue final-CTA).
- [ ] Replace the 4 copies in `front-page.php` (header + final-CTA inverse), `header.php`, `footer.php` with `include` calls.
- [ ] Remove the `phpcs:ignore` annotations from those echo lines.

#### 2.7 `page-contact.php:26` — fix unescaped `href` echo
- [ ] Change `echo $mail_subject` to `echo esc_attr( $mail_subject )`. Even though `$mail_subject` is currently safe, the pattern is wrong and a future change becomes an attribute-injection vector.

### Success criteria
- Lighthouse a11y score ≥ 95 on / and /pricing (was lower).
- Tab from page load reaches a visible "Skip to content" link.
- Mobile hamburger touch target measures 44×44 in DevTools.
- Every page (`/`, `/how-it-works`, `/pricing`, `/docs`, `/docs/{any}`, `/changelog`, `/contact`, `/privacy`, `/terms`, `/refund-policy`) has `<meta name="description">` + `<link rel="canonical">` + `og:*` tags in view-source.
- `grep -r 'https://pipepay.app' pipepay-child/` returns only intentional refs in style.css comments and the SVG mockup pseudo-URL.

### Deploy
- Theme sync + Cloudflare purge.

---

## Phase 4 — License-resolver hardening (~half day, site repo, low risk)

**Goal:** kill the enumeration oracle and add the operational visibility we currently have zero of.

### Tasks (all in `mu-plugins/pipepay-license-resolve.php`)

#### 4.1 Collapse `key_inactive` into generic `invalid_key` (H3)
- [ ] Both 404 (key not found) and 403 (key inactive) currently return distinguishable error bodies. Collapse to a single response: HTTP 404, body `{"error": "invalid_key", "message": "License key not recognized."}`. Log the actual reason (active vs not-found) server-side for ops.

#### 4.2 Validate key shape before incrementing rate counter (H2)
- [ ] Move the regex/length validation BEFORE the `pipepay_license_resolve_check_rate_limit()` call. Bad-shape keys should not consume a real customer's per-IP bucket.

#### 4.3 Rate-limit transient race (H1)
- [ ] Replace `get_transient` + `set_transient` with `wp_cache_add` on first hit and `wp_cache_incr` thereafter. Atomic. Falls back to transient TTL if object-cache backend doesn't support `incr` (e.g. plain database transient cache — degrades gracefully).

#### 4.4 Explicit HTTPS guard (M1)
- [ ] Add at top of handler:
  ```php
  if ( ! is_ssl() ) {
      return new WP_REST_Response(['error' => 'https_required'], 400);
  }
  ```
- [ ] Belt-and-braces; nginx + Cloudflare are primary defense.

#### 4.5 DB error visibility (M3)
- [ ] After the `$wpdb->get_row` call, check `$wpdb->last_error`. If non-empty, return HTTP 503 + `{"error": "service_unavailable"}` and `error_log` the actual error. Prevents customers seeing "License not recognized" when the real cause is a DB outage or Kestrel schema drift.

#### 4.6 Operational logging (M4)
- [ ] On 4xx/5xx responses, `error_log` a one-line summary: `[YYYY-MM-DD HH:MM:SS] pipepay-license-resolve: ip=X.X.X.X key_last4=ABCD status=404 reason=invalid_key`. Never log full keys.

#### 4.7 Strict charset whitelist on key (M2)
- [ ] Before the DB query, validate against `/^[A-Za-z0-9_-]{8,190}$/`. Stops `sanitize_text_field` silent-mutation surprises.

### Success criteria
- `curl https://pipepay.app/wp-json/pipepay-license/v1/resolve -d 'license_key=clearly-fake-1234'` returns 404 with `invalid_key`.
- `curl https://pipepay.app/wp-json/pipepay-license/v1/resolve -d 'license_key=<real-deactivated-key>'` returns the SAME 404 with `invalid_key`. (No oracle.)
- Bursting 100 concurrent requests from one IP results in ≤ the configured cap (currently 60/hr) being processed; race condition fixed.
- PHP error log shows the new structured one-liners on 4xx/5xx.

### Deploy
- Theme sync (mu-plugins ships with the theme repo) + Cloudflare purge.
- **Test before purge:** new license activations must still work end-to-end. Regression risk is real — license activation is on the critical path for every customer install.

---

## Phase 3 — Plugin v1.6.4 release: security + critical bugs (~2-3 days, plugin release)

**Goal:** ship a hardened plugin release that customers can update to via the standard WP plugin update notification.

### Tasks

#### 3.1 IP detection trusts only Cloudflare ranges (H-4)
- [ ] In `includes/pipepay-hooks.php:17-30`, `pipepay_get_client_ip()`:
  - Read `REMOTE_ADDR`. If it's NOT in a known Cloudflare edge range (v4 + v6 lists from cloudflare.com/ips), use it directly.
  - If it IS in a Cloudflare range, then honor `HTTP_CF_CONNECTING_IP`.
  - Don't honor `HTTP_X_FORWARDED_FOR` ever (lower-trust, easier to spoof, not needed when `CF-Connecting-IP` is the canonical answer).
- [ ] Bundle the Cloudflare IP list as a static array in code, with a docblock comment pointing at the canonical source URL and the date the list was last refreshed. Plan to refresh quarterly.

#### 3.2 Server-side amount + recipient cross-check (H-3)
- [ ] In `class-pipepay-vision-client.php`, after parsing the AI response:
  - Numerically compare `extracted_amount` to `expected_amount` with a $0.01 tolerance. If mismatch, force `confidence = 'low'` regardless of what the model said.
  - Substring-compare `extracted_recipient` to the configured handle for the rail (case-insensitive, ignoring `@` / `$` / spacing differences). If mismatch, force `confidence = 'low'`.
- [ ] Add a flag in the AI reasoning text so the admin reviewing the proof can see "amount cross-check FAILED — model claimed $87.50 but order total is $97.50."
- [ ] Write tests for the cross-check function (numerically equal, off-by-cent, off-by-dollar, recipient substring match w/ casing variations, etc.).

#### 3.3 Force `sslverify=true` for license calls (H-1)
- [ ] In `includes/pipepay-licensing.php`, add a filter: `add_filter('http_request_args', ...)` that sets `sslverify = true` and `reject_unsafe_urls = true` for any URL containing `pipepay.app`.
- [ ] Verify the Kestrel SDK's staging-host downgrade no longer applies on these calls.
- [ ] Document this in the SDK wrapper file's docblock so a future maintainer doesn't undo it.

#### 3.4 Mark `wc-awaiting-proof` non-public + non-searchable (H-5)
- [ ] In `pipe-pay.php:38-50`, set `exclude_from_search => true` and `publicly_queryable => false`.

#### 3.5 Fix per-customer rate-limit gap (C-1)
- [ ] **Decision required:** either (a) add a real per-billing-email counter (`pipepay_proof_email_<sha256(email)>` checked over 24h), OR (b) remove the false claim from SECURITY.md and the customer doc.
- [ ] Recommended: do (a) — it's a real fraud-mitigation gap. Track per-email + per-rail-handle (not per-customer-account, since checkout is guest-friendly).

#### 3.6 Tighten uninstall path containment (C-2)
- [ ] In `uninstall.php:140-149`, `pipepay_uninstall_safe_path()`:
  - Resolve `realpath()` of the path.
  - Require the resolved path to live under one of the THREE known tier roots (PRIMARY/FALLBACK/OVERRIDE), not just under ABSPATH.
  - Reject if the resolved path equals or is `wp-content`, `wp-content/plugins`, `wp-content/themes`, or `wp-content/uploads`.
  - Reject if the path has fewer than 3 segments past ABSPATH (defense-in-depth).
- [ ] Add a unit test that tries to delete `wp-content/` with a malicious `PIPEPAY_PROOFS_PATH` override and verifies the deletion is refused.

#### 3.7 Bump PHP requirement to 8.0
- [ ] Update plugin header: `Requires PHP: 8.0` (was 7.4). Code uses `match` expressions (`class-pipepay-vision-client.php:306`) which is PHP 8.0+.
- [ ] Add runtime guard in `pipe-pay.php` that bails with admin notice on PHP < 8.0:
  ```php
  if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
      add_action( 'admin_notices', function() { /* render notice */ } );
      return;
  }
  ```

#### 3.8 Register custom status on `init` priority 1 (Q-5)
- [ ] In `pipe-pay.php:37`, change `add_action('init', 'register_status')` to `add_action('init', 'register_status', 1)`.
- [ ] Test that the defensive `set_status() + save()` workaround in `class-pipepay-gateway.php:725-728` is no longer needed (leave it in but add a comment that it's belt-and-braces).

#### 3.9 Cap badge counter query (Q-3, Q-4)
- [ ] `class-pipepay-admin.php:53` — `get_pending_proof_count()` runs `wc_get_orders([... 'limit' => -1 ])` on every admin page load. Replace with a count query (use `wc_get_orders([... 'return' => 'ids', 'limit' => 200 ])` and `count()` the result; show "200+" in the badge if at the cap).

#### 3.10 Per-provider AI key storage (L-5)
- [ ] In gateway settings, store API keys per-provider (`ai_api_key_openai`, `ai_api_key_anthropic`, etc.) instead of a single shared `ai_api_key`. Migrate the existing setting on update.
- [ ] On provider change, the right key is loaded; old keys aren't sent to the wrong vendor.

#### 3.11 Block checkout i18n (Q-2)
- [ ] In `class-pipepay-blocks.php:43-44`, wrap fallback strings with `__( …, 'pipe-pay' )`.
- [ ] Verify the `assets/js/pipepay-blocks.js` file actually registers the payment method correctly (it's referenced but the JS contents weren't reviewed).

#### 3.12 Mask license key display
- [ ] In `pipepay-licensing.php:493-499`, change the mask from "first 4 + last 4" to "last 4 only" to reduce over-shoulder leakage in support screenshots.

### Testing checklist before release
- [ ] PHPUnit covers `PipePay_Storage::resolve_proof_path` containment edge cases (Phase 5 gives us the harness, but ship at minimum the new cross-check tests for H-3 and the uninstall path test for C-2).
- [ ] Manual: place test order through the dogfood flow, verify it still completes end-to-end with the IP-detection change (since the dogfood site IS behind Cloudflare, the path that runs is the new "honor CF-Connecting-IP because REMOTE_ADDR is in CF range" branch — make sure that path actually fires).
- [ ] Manual: place test order with a screenshot whose extracted_amount mismatches the order total. Verify confidence is forced to `low` and the order lands in the manual queue (instead of auto-approving).
- [ ] Manual: place test order with a screenshot whose recipient handle is wrong. Verify confidence is forced to `low`.
- [ ] Manual: license activation on a fresh test site against `pipepay.app` succeeds (sslverify=true didn't break anything).
- [ ] Manual: visit a `wc-awaiting-proof` order URL while logged out — should not appear in WP search or feed.
- [ ] Manual: PHP version notice fires on a PHP 7.4 install (use a Docker container or staging site).
- [ ] Manual: badge counter shows correct number (≤200) and doesn't slow down admin page loads.
- [ ] Verify `pipe-pay.php` version bumped to `1.6.4`, `PIPEPAY_VERSION` constant updated.
- [ ] Build new zip: `cd pipe-pay-extracted && zip -qr ../pipe-pay-v1.6.4.zip pipe-pay -x "*.DS_Store" "*/._*"`.

### Release sequence
1. Commit + tag v1.6.4 in plugin repo.
2. Push commit + tag to `wittscafidi/pipe-pay-plugin`.
3. Deploy to dogfood install: `scp pipe-pay-v1.6.4.zip ...; wp plugin install /tmp/pipe-pay-v1.6.4.zip --force`.
4. Sanity-check the dogfood gateway for ~24h (no error_logs, no broken checkout, no broken license activation).
5. Stage v1.6.4 zip in `wp-content/uploads/woocommerce_uploads/` and update the four WC products' `_downloadable_files` + `_product_version=1.6.4`. Existing customers (none in the wild yet) would see the update notification at this point.
6. Move v1.6.3 zip out of the WC uploads dir (or leave for rollback).

### Success criteria
- All 5 high-severity security findings have a verifiable fix in code.
- v1.6.4 zip published, tagged, and the four WC products serve it.
- Dogfood install runs v1.6.4 cleanly for ≥ 24 hours.

---

## Phase 5 — Plugin code quality (rolling, ~2 weeks total, multiple releases)

**Goal:** structural improvements that don't block today but limit how far the plugin can scale.

### 5a. Test infrastructure (highest priority of this phase)
- [ ] Add `tests/` directory with `phpunit.xml.dist` and a WC test bootstrap (`wp-phpunit/wp-phpunit` + `woocommerce/woocommerce-rest-api-tests` fixtures).
- [ ] Initial tests:
  - `PipePay_Storage::resolve_proof_path` — containment, traversal attempts, missing dir, custom path.
  - `PipePay_Vision_Client::parse_response` — well-formed JSON, malformed JSON, missing fields, unexpected confidence values, the new amount/recipient cross-check.
  - Account rotation logic (LRU and round-robin both).
  - Rate-limit counter atomicity.
- [ ] Wire up GitHub Actions (`.github/workflows/test.yml`) to run on PR + push.

### 5b. Refactor god-files
- [ ] Split `class-pipepay-gateway.php` (1899 lines) into:
  - `class-pipepay-gateway.php` — core `WC_Payment_Gateway` subclass (process_payment, validation, status transitions)
  - `class-pipepay-settings.php` — `init_form_fields()` and per-section configuration
  - `class-pipepay-account-rotation.php` — multi-account assignment + rotation algorithm
  - `class-pipepay-admin-scripts.php` — admin-side script/style enqueueing
- [ ] Split `pipepay-hooks.php` (1222 lines) into:
  - `class-pipepay-rest-controller.php` — REST endpoint registration + handlers
  - `class-pipepay-admin-meta-box.php` — order-edit screen meta box
  - `class-pipepay-reminder-scheduler.php` — Action Scheduler integration for reminders + auto-cancel
  - `class-pipepay-helpers.php` — small utilities (IP detection, log helper)
- [ ] Each split = its own commit; release as v1.7.0 once all done.

### 5c. i18n pass
- [ ] Wrap all user-facing strings in `__()` / `esc_html__()` with the `'pipe-pay'` textdomain.
- [ ] Particular attention: `templates/pipe-pay-page.php` (1470 lines, zero `__()` calls), gateway settings labels (mixed coverage), REST error messages.
- [ ] Add `load_plugin_textdomain( 'pipe-pay', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' )` on `init` in `pipe-pay.php`.
- [ ] Generate POT file via `wp i18n make-pot . languages/pipe-pay.pot`.

### 5d. Move inline JS/CSS to enqueued asset files
- [ ] Extract `pipepay-hooks.php:694-741` `<script>` block to `assets/js/pipepay-admin.js`. Enqueue only on the proofs admin screen.
- [ ] Extract hundreds of inline `style="…"` attrs in `class-pipepay-admin.php` to `assets/css/pipepay-admin.css`.

### 5e. Replace `error_log()` with `wc_get_logger()` helper
- [ ] Create `pipepay_log( $level, $msg, $context = [] )` helper that delegates to `wc_get_logger()` with `'source' => 'pipepay'`. Falls back to `error_log()` if WC isn't loaded.
- [ ] Replace all `error_log()` calls (3 in storage, 2 in hooks, others scattered).
- [ ] Redact API keys + customer emails from logged context.

### 5f. License-server response signature verification (H-2)
- [ ] Generate Ed25519 keypair. Public key bundled with plugin; private key on `pipepay.app`.
- [ ] All resolver responses include a signature header. Plugin verifies before trusting `product_id` / `product_title`.
- [ ] Backward-compat: if signature missing (old server), proceed but log a warning. After all customers are on the signing-aware version, make it required.

### 5g. Block checkout JS verification + completeness
- [ ] Read `assets/js/pipepay-blocks.js`. Verify it calls `wc.wcBlocksRegistry.registerPaymentMethod`, exposes `name: 'pipepay'`, `canMakePayment`, `label`, `content`, `edit`.
- [ ] Manual test: place an order via WooCommerce Block Checkout (not classic).

### Success criteria (Phase 5)
- ≥ 60% line coverage on new tests for storage + vision + rotation.
- No file in `includes/` over 600 lines.
- `wp i18n` PHPCS rule reports zero warnings.
- License resolver signature verification active in production.
- Block checkout end-to-end test passes.

---

## Phase 6 — Site theme polish (~half day, rolling)

### Tasks

#### 6.1 Split WC overrides into a separate stylesheet
- [ ] Move lines 2876–end of `style.css` into `pipepay-child/woocommerce.css`.
- [ ] In `functions.php`, enqueue this CSS only on WC pages (mirror the existing JS dequeue logic but inverted). Reduces CSS payload on marketing pages by ~40%.

#### 6.2 Replace `date('Y')` with `wp_date('Y')`
- [ ] `footer.php:51`, `front-page.php:507`. Uses WP's site timezone instead of server timezone. Edge case fix.

#### 6.3 Audit `<form class="pp-footer-signup">`
- [ ] Currently POSTs to `/contact` with no handler. Decision: either wire to a real subscribe endpoint (Resend/Postmark mailing list) or replace the form with an `<a href="/contact">` link.

#### 6.4 Reduce `!important` rules in CSS
- [ ] 45 declarations across 3,618 lines. Audit each and resolve via specificity instead. Time-bound to ~2h of work; perfect is the enemy of good.

#### 6.5 Mobile breakpoint review
- [ ] Hamburger fires at ≤760px. iPad portrait (768px) currently shows desktop nav. Decision: bump to ≤800px (if iPad portrait should show drawer) or leave (iPad portrait users get the desktop nav).

#### 6.6 Cart-emptying filter scope-narrowing
- [ ] `functions.php:178-187` empties the cart unconditionally when adding a Pipe Pay product. If/when non-Pipe-Pay products ever get added to the catalog, this silently nukes carts. Iterate and remove only non-Pipe-Pay items instead. Not urgent today.

### Success criteria
- Marketing-page CSS payload measurably smaller in Network panel.
- Site footer year correct across DST transitions.
- No POST-to-/contact mystery.

---

## Risk register

| Risk | Phase | Mitigation |
|---|---|---|
| Plugin v1.6.4 breaks license activation for new customers | 3 | Test license activation on a fresh test site BEFORE bumping the WC product `_product_version`. Existing customers can't break (none in the wild yet) but the live store would. |
| IP-range-restricted CF detection breaks the dogfood gateway | 3 | The dogfood site sits behind Cloudflare — `REMOTE_ADDR` will be a CF range, so the new code path is exactly what fires. Sanity test on dogfood for 24h before promoting. |
| Server-side amount cross-check causes false rejections on legit orders | 3 | Test with the screenshots that previously auto-approved (in dogfood test orders). Build a small set of "should approve" + "should not approve" reference screenshots before shipping. |
| License-resolver hardening breaks existing license calls | 4 | Test the resolver flow with a real (test) license activation on a separate site BEFORE deploying. Both old and new responses should be acceptable to the SDK. |
| Phase 1 doc edits accidentally remove correct claims | 1 | Re-grep verification: each fixed claim should be re-confirmed against the source code by file:line. The doc-vs-reality review report is the authoritative checklist. |
| Theme refactor (Phase 6.1) breaks WC page styling | 6 | Manual click-through of `/shop`, `/cart`, `/checkout`, `/my-account`, single product before pushing to live. |

## What this plan does NOT cover

These are flagged in the reviews but deferred:
- **Multisite super-admin handling** in plugin admin (low risk, no demand).
- **WooCommerce Subscriptions migration tooling** for Pro tier customers — separate spec at `pipe-pay-extracted/specs/pipe-pay-pro-v1.md`, V2 work.
- **Public webhook events / API** for third-party integrations (Pipe Pay Pro V2).
- **Third-party Kestrel SDK security** beyond the staging-host MITM fix — full SDK audit would be a separate engagement.
- **Marketing-site analytics** (no GA / Plausible / etc. installed; intentional for now).

## Estimated total effort

| Phase | Effort | Cadence |
|---|---|---|
| 1 — Doc truth-up | ~2h | One sitting |
| 2 — Site a11y + SEO | ~half day | One sitting |
| 4 — Resolver hardening | ~half day | One sitting |
| 3 — Plugin v1.6.4 | ~2-3 days | Spread across 1 week with testing |
| 5 — Plugin quality | ~2 weeks | Multiple releases over a month |
| 6 — Site polish | ~half day | One sitting |

**Aggregate from start to "everything in this plan shipped": ~5-6 weeks** at part-time pace, or ~2 weeks of focused full-time work. The first 1.5 days (Phases 1+2+4) materially improve the public-facing posture without any plugin-release risk.
