# Pipe Pay - Site Operations Notes

> Self-hosted **WooCommerce** site for `pipepay.app`. The site sells Pipe Pay licenses through Pipe Pay itself (dogfood). First WordPress site on this OptiPlex (the rest are static Jekyll). Don't apply Jekyll deploy patterns here - content lives in the DB and uploads on disk.

## Live URL
- https://pipepay.app (apex)
- https://www.pipepay.app (canonical 301 → apex)
- Admin: https://pipepay.app/wp-admin/

## Server
- Host: Dell OptiPlex, Ubuntu 24.04.4 LTS
- SSH: `ssh witt-scafidi@100.102.251.125` (key auth)
- Site root: `/var/www/pipepay/` (owner `www-data:www-data`)
- This server also serves: `hellfireindustries.com`, `dashboard.hellfireindustries.com`, `peptideselect.com` - be careful not to reload nginx in a way that affects them; always `nginx -t` first.

## Stack versions (current state 2026-05-08)
- WordPress: 6.9.4 (core)
- WooCommerce: 10.7.0
- Pipe Pay plugin: **1.7.1** (updated 2026-05-08) - same version on the dogfood gateway AND in the customer-facing zip wired into the WC products. v1.7.1 adds the `wc-awaiting-approval` custom status for the post-upload manual-review landing (replacing the prior `on-hold` reuse), a dedicated `pipepay_review_pending` customer email, and bundles the `_wpnonce` fix on the proof-image proxy URL (admin meta box + Pipe Pay Proofs queue + history). **Requires PHP 8.0** (was 7.4 pre-1.6.4). The dogfood install has `PIPEPAY_DISABLE_LICENSING` set in `wp-config.php` (it hosts the license server itself; can't license against itself). Source zip: `~/Desktop/Pipe Pay/pipe-pay-v1.7.1.zip`. License-server URL hardcoded to `https://pipepay.app/`. Staged on the server at `/var/www/pipepay/wp-content/uploads/woocommerce_uploads/pipe-pay-v1.7.1.zip` and wired into all four WC products via `_downloadable_files` + `_product_version=1.7.1`. Older zips (1.5.1 → 1.7.0) are still on disk in that uploads dir as rollback options but are not referenced by any product.
- API Manager for WooCommerce (Kestrel): 3.7.6
- WooCommerce.com Helper (`woo-update-manager`): 1.0.3 - the bridge that pulls API Manager from the WC.com subscription
- License resolver mu-plugin: **1.2.0** (updated 2026-05-08) - `wp-content/mu-plugins/pipepay-license-resolve.php`. Custom REST endpoint at `POST /wp-json/pipepay-license/v1/resolve` that maps a license key to its product ID. v1.1.0 (2026-05-07) closed the enumeration oracle and added race-safer rate-limiting. v1.2.0 (2026-05-08) adds Ed25519 response signing - success responses include `X-Pipepay-Signature` / `X-Pipepay-Signature-IssuedAt` headers; the plugin verifies before trusting `product_id`. See "Kestrel API Manager licensing" below.
- License renewals mu-plugin: **1.0.0** - `wp-content/mu-plugins/pipepay-license-renewals.php`. HMAC-signed `/renew/` landing page for trial conversions and paid renewals. Carries the `?intent=34/35/36` tier preference from initial trial signup through to the renewal CTA so the customer's intended tier is preselected at conversion. Local source of truth: `pipe-pay-site/mu-plugins/pipepay-license-renewals.php`.
- nginx: 1.24.0 (Ubuntu)
- PHP: 8.3 via php-fpm; socket `/run/php/php8.3-fpm.sock`
- MariaDB: server (system default on 24.04), root auth via unix_socket (i.e. `sudo mysql`)
- wp-cli: 2.12.0 at `/usr/local/bin/wp`

## nginx
- Site config: `/etc/nginx/sites-available/pipepay.app`, symlinked into `sites-enabled/`
- Listens on `:80` only - Cloudflare Tunnel terminates TLS
- Trusts `CF-Connecting-IP` for real client IPs (`set_real_ip_from 0.0.0.0/0`)
- `client_max_body_size 32M` for plugin/theme uploads

## Database
- DB: `pipepay` (utf8mb4 / utf8mb4_unicode_ci)
- App user: `pipepay@localhost` - password file `~/.pipepay-db-password` on the OptiPlex (also in `.secrets/` on the Mac)
- Root: unix_socket auth - use `sudo mysql` from the shell

## WordPress admin
- Username: `pipepayadmin`
- Email: wittscafidi@gmail.com
- Password file: `~/.pipepay-wp-admin-password` on the OptiPlex; `.secrets/pipepay-wp-admin-password.txt` on the Mac

## wp-config.php highlights
Located at `/var/www/pipepay/wp-config.php` (DO NOT commit). Custom additions:
- Trusts `HTTP_X_FORWARDED_PROTO=https` from Cloudflare → sets `$_SERVER['HTTPS'] = 'on'`
- `WP_HOME` and `WP_SITEURL` hardcoded to `https://pipepay.app`
- `DISALLOW_FILE_EDIT` = true (no theme/plugin editor in admin)
- `WP_AUTO_UPDATE_CORE` = `'minor'`
- `PIPEPAY_DISABLE_LICENSING` = `true` - bypasses the Pipe Pay plugin's license-activation UI. **Set on this dogfood install only.** The site IS the license server; activating against itself would be circular. Backup of the original wp-config.php from the 1.5.1 deploy is at `/tmp/wp-config-backup-20260506-230111.php` if you ever need the pre-constant version.
- `PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY` = `<base64 Ed25519 secret key>` - added 2026-05-08 with v1.7.0. Used by the `pipepay-license-resolve.php` mu-plugin to sign success responses. The matching public key is bundled in the customer plugin (`pipe-pay/includes/pipepay-licensing.php` const `PIPEPAY_LICENSE_SIGNING_PUBLIC_KEY`). **Never commit this key to git.** Backup at `~/Desktop/Pipe Pay/.secrets/pipepay-license-signing-keypair.txt`. Rotate by generating a new keypair (`php -r 'echo base64_encode(sodium_crypto_sign_keypair());'` then split secret/public via `sodium_crypto_sign_secretkey`/`publickey`), updating both ends, and shipping a new plugin version.

## Cloudflare
- Zone: `pipepay.app` (DNS at Cloudflare; registrar Porkbun, NS pointed at CF)
- Tunnel: existing `hellfire` Cloudflare Tunnel (token-based, configured via Zero Trust dashboard, not local config.yml)
  - Public hostnames added: `pipepay.app` → `http://localhost:80`, `www.pipepay.app` → `http://localhost:80`
  - **Watch out:** the prompt mentions port 3005 as a *reserved* port for a hypothetical sidecar - do NOT use it for this site. WP is on :80.
- SSL/TLS: Full (NOT Full Strict - origin has no cert), Always Use HTTPS: On

### Cache Rules (Caching → Cache Rules)
Two rules. **Order matters: later rule wins for cache eligibility** (Cloudflare's documented semantics). Order in the dashboard list MUST be:
1. **Cache anonymous HTML** - match `(http.host eq "pipepay.app")`, set Cache eligibility = Eligible for cache, Edge TTL 2h, Browser TTL = Respect existing headers (WP sends `no-cache` so browsers always revalidate)
2. **Bypass dynamic + logged-in** - match the long expression covering `/wp-admin/*`, `/wp-login*`, `/checkout/*`, `/cart/*`, `/my-account/*`, `/wp-json/*`, `/wp-cron*`, query containing `add-to-cart=`, and cookies `wordpress_logged_in_*`/`woocommerce_session*`/`wp_woocommerce_session_*`/`comment_author_*`. Set Cache eligibility = Bypass cache.

If the bypass rule is above the cache rule, cache wins for /cart/, /my-account/, etc., and Cloudflare WILL serve cached cart/account responses to other visitors - **session leak**. Don't reverse the order. After publishing site changes, Caching → Configuration → Purge Everything (or wait up to 2h for edge TTL).

### Verifying changes immediately without a CF purge

Cloudflare keys its cache by the full URL **including query string**, so adding any throwaway query param produces a cache MISS and forces a fresh origin fetch. Use this to verify a change yourself before deciding whether to purge for real visitors:

```
https://pipepay.app/pricing/?bust=1
https://pipepay.app/changelog/?v=now
https://pipepay.app/?check=42
```

WordPress ignores the unknown param, the page renders normally, and you see the latest HTML. Each unique value lands in CF's edge cache as its own entry for 2h, so don't spam - drop one or two `?bust=N` per change, then purge for everyone else if it looks right.

**Only useful on URLs caught by the Cache Rule (Rule 1):** `/`, `/how-it-works/`, `/pricing/`, `/changelog/`, `/docs/` (+ docs sub-pages), `/contact/`, `/privacy/`, `/terms/`, `/refund-policy/`. Anything in the Rule 2 bypass list (`/checkout/*`, `/cart/*`, `/my-account/*`, `/wp-admin/*`, `/wp-login*`, `/wp-json/*`, `/wp-cron*`, plus the cookie/query-arg matchers) is already bypassed and `?bust=` does nothing. If a checkout/cart/account page looks stale, the cache layer is browser, PHP opcache, or WP transients - not CF.

Pair `?bust=` with Cmd-Shift-R or a private window so you don't see a stale browser-cached copy. For static asset URLs (style.css, JS, fonts), WordPress already appends `?ver=filemtime()` so updated files auto-bust - manual `?bust=` not needed there.

## Backups
- Daily DB dump at 03:00 UTC via root crontab → `/home/witt-scafidi/backups/pipepay-db-YYYY-MM-DD.sql.gz`
- Script: `/usr/local/sbin/pipepay-backup.sh` (root, 700)
- Credentials file: `/root/.pipepay-backup.cnf` (mysqldump `--defaults-extra-file`, 600)
- Retention: 30 days (script auto-deletes older)
- Log: `/var/log/pipepay-backup.log`
- **Not yet set up:** off-server backup of `/var/www/pipepay/wp-content/uploads/` - recommend UpdraftPlus → S3/B2 once any content exists.

## Repository / version control
- **Plugin repo:** https://github.com/WittScafidi/pipe-pay-plugin (private). Contains `pipe-pay/` (source), `tests/` (PHPUnit suite), `specs/` (internal docs including Pro V1 spec), `reviews/` (audit reports), `composer.json`, `phpunit.xml.dist`, `.github/workflows/tests.yml`, `README.md`, `.gitignore`. Local clone at `~/Desktop/Pipe Pay/pipe-pay-extracted/`. Latest tag: `v1.7.0`. Tag releases as `v1.x.y` matching `PIPEPAY_VERSION`. Push tags via `git push origin v1.x.y`. Tests run automatically on push/PR via GitHub Actions on PHP 8.1/8.2/8.3.
- **Site repo:** https://github.com/WittScafidi/pipe-pay-site (private). Contains `pipepay-child/`, `mu-plugins/`, `CLAUDE.md`, `homepage-copy.md`, `README.md`, `.gitignore`. Local clone is the working dir at `~/Desktop/Pipe Pay/pipe-pay-site/`. **Note:** `.secrets/` is at `~/Desktop/Pipe Pay/.secrets/` (sibling, OUTSIDE this repo) - it is NOT in scope for git but be careful never to move/copy it inside the site dir.
- Server-side: WordPress core, content, uploads, and DB still live on the OptiPlex. The repo is a developer source-of-truth, not a deploy mechanism. Theme syncs are still done via the `tar czf`/`scp`/`tar xzf` cheatsheet command. Plugin updates ship via `wp plugin install <zip> --force` over SSH (or via WP Admin → Plugins → Add New → Upload).

## Sudo posture
- During setup, NOPASSWD sudo was granted to `witt-scafidi` via `/etc/sudoers.d/99-witt-scafidi-nopasswd`. Consider removing once the site is stable: `sudo rm /etc/sudoers.d/99-witt-scafidi-nopasswd`.

## Plugins (active)
- **WooCommerce** 10.7.0 - the commerce engine.
- **Pipe Pay** 1.7.0 - the plugin we sell, installed here as the live payment gateway AND as the same build customers receive. License layer is bypassed via `PIPEPAY_DISABLE_LICENSING` in `wp-config.php` (see above). To upgrade the dogfood install: easiest path is `sudo -u www-data wp --path=/var/www/pipepay plugin install /tmp/pipe-pay-vX.Y.Z.zip --force`. wp-cli does the deactivate/swap/reactivate dance for you and preserves option-stored settings.
- **mu-plugins/pipepay-license-resolve.php** 1.2.0 - must-use plugin (auto-loaded, not in the regular plugins list). License-key → product-ID resolver endpoint with Ed25519 response signing. See "Kestrel API Manager licensing" below.
- **mu-plugins/pipepay-license-renewals.php** 1.0.0 - must-use plugin. HMAC-signed `/renew/` landing for trial conversions and paid renewals.
- **API Manager for WooCommerce** (Kestrel) 3.7.6 - license generation + auto-update server for the Pipe Pay plugin sold via this site. (See "Kestrel licensing" below.)
- **Woo Update Manager** 1.0.3 - the WooCommerce.com Helper. Authenticates to `wittscafidi@gmail.com`'s WC.com account so the API Manager subscription pulls in updates automatically. Don't deactivate or API Manager stops getting updates.
- **Wordfence** 8.2.0 - firewall, malware scan, login throttling. Free tier; runs out of the box.
- **Two Factor** 0.16.0 - official WP.org plugin. Users enable 2FA from `Users → Profile → Two-Factor Options`. **Enable on the `pipepayadmin` account before launch.**
- **GenerateBlocks** 2.2.1 - block library, available for any future page that wants block-built sections. The homepage doesn't use it (custom theme template instead).

### Plugins inactive but on disk (rollback option)
- **Easy Digital Downloads Pro** 3.6.7 - abandoned in favor of WooCommerce + API Manager. Plugin files retained for the 14-day refund window. Can be removed once refund clears.

## Kestrel API Manager licensing (the dogfood + auto-update infrastructure)
This is what makes "selling a WordPress plugin" work end-to-end on this site.

**What it does:**
1. When a customer checks out via WC, API Manager generates a license key and ties it to their WC user account.
2. The Pipe Pay plugin embeds Kestrel's free [PHP SDK](https://github.com/kestrelcommerce/wc-api-manager-php-library) (verbatim, dropped in at `includes/wc-am-client.php`) which talks back to `pipepay.app` for license activation, deactivation, and version checks.
3. When we ship a new Pipe Pay zip, customers see "Update Available" in their standard WordPress admin notification flow - **no zip re-uploads required**. This is the entire reason Kestrel was chosen over alternative licensing systems.

**One-field activation (since v1.6.0):**
The Kestrel SDK natively requires the customer to enter both a license key AND a product ID. We sell four tiers (#34/#35/#36/#38) so we can't hardcode a single product ID, and asking customers to look up the product ID is bad UX. To dodge this, the plugin ships with a custom License page (`WP Admin → Pipe Pay → License`) that has a single field - license key. On activation, the plugin POSTs the key to a custom resolver endpoint on this site, gets the product ID back, then hands both to the Kestrel SDK.

**Where the resolver lives:**
- File: `/var/www/pipepay/wp-content/mu-plugins/pipepay-license-resolve.php`
- Endpoint: `POST https://pipepay.app/wp-json/pipepay-license/v1/resolve`
- Body: `api_key=<license-key>` (POST body, NOT query string - keeps the key out of nginx access logs)
- Response 200: `{"success": true, "product_id": 34, "product_title": "Pipe Pay (Single Site)"}`
- Response 404 / 403 / 429: `{"success": false, "code": "...", "message": "..."}`
- Backed by a direct `wpdb` query against `wp_wc_am_api_resource` (the API Manager table), filtered to `active=1` rows.
- Per-IP rate limit: 60 requests/hour using transients, IP via `REMOTE_ADDR` (nginx already does the trusted CF rewrite - see `set_real_ip_from 0.0.0.0/0` in nginx config).
- Source of truth: `/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site/mu-plugins/pipepay-license-resolve.php`. Sync to the server on changes.

**How the license/update flow works (end-to-end):**
1. Customer pastes license key into `WP Admin → Pipe Pay → License` on their store.
2. Plugin POSTs to `pipepay.app/wp-json/pipepay-license/v1/resolve` → gets back `product_id`.
3. Plugin POSTs to `pipepay.app/?wc-api=wc-am-api&wc_am_action=activate&...` (the legacy WC API shim Kestrel uses) with the resolved `product_id`, the customer's `api_key`, a stable per-site `instance` token, and the site URL.
4. Kestrel records the activation, returns success. Plugin populates the SDK's option storage (`wc_am_client_<pid>` array + `wc_am_<pid>_activated`) so the SDK's update hooks find the api_key on subsequent requests.
5. WordPress's standard `pre_set_site_transient_update_plugins` filter (registered by the SDK) checks for new versions on its normal cron schedule. When a new version is staged, customer sees "Update Available" in their plugins list, clicks "Update now," WP downloads + installs.

**Tier upgrades:** customer clicks Deactivate, pastes new key, clicks Activate. Plugin re-resolves, gets new product_id, cleans up the OLD product's SDK options before binding the new one. No zip swap required.

**Idempotency:** re-clicking Activate with an already-active key is a local no-op (no second server call, no second seat burned). Deactivation only clears local state when the server confirms - prevents orphan seats when the deactivate request fails mid-flight.

**Where the API Manager itself is licensed from:**
- Bought through [woocommerce.com](https://woocommerce.com/products/woocommerce-api-manager/) (the WC.com Marketplace listing for Kestrel's plugin), $199/yr.
- **There is NO traditional license key for API Manager.** Activation is via the WC.com Helper "Connect your store" flow - the site authenticates against the `wittscafidi@gmail.com` woocommerce.com account and pulls the subscription's plugin + updates over the Helper API. Don't disconnect that connection or API Manager stops getting updates and may eventually stop functioning.
- To verify or re-authenticate: WP Admin → WooCommerce → Extensions → My Subscriptions.

**Where the WC products (license tiers) live:**
| Product | WC ID | Slug | Price | Activations | Expiry | Visibility |
|---|---|---|---|---|---|---|
| Pipe Pay (Single Site) | 34 | `pipe-pay-single-site` | $249 | 1 | 365 days | shop |
| Pipe Pay (5 Sites) | 35 | `pipe-pay-five-sites` | $499 | 5 | 365 days | shop |
| Pipe Pay (Unlimited Sites) | 36 | `pipe-pay-unlimited` | $999 | unlimited | 365 days | shop |
| Pipe Pay 7-Day Trial | 38 | `pipe-pay-trial` | $0 | 1 | 7 days | hidden (only via direct add-to-cart URL) |

API Manager meta on each product: `_is_api=yes`, `_api_resource_type=wp_plugin`, `_api_activations` + `_api_activations_unlimited`, `_access_expires` (days). All four products self-reference for `_api_resource_product_id` (single-product = single-resource pattern).

## WooCommerce config
- Default country: `US:NY` (placeholder - change if you're elsewhere)
- Currency: USD
- Tax: off (revisit after LLC formation + nexus check)
- Guest checkout: enabled
- Account creation at checkout + on /my-account: enabled
- Coming Soon mode: disabled
- Onboarding wizard: skipped, marketing suggestions disabled
- Default WC pages: `/shop` (id 27), `/cart` (id 28), `/checkout` (id 29 - slug taken back from the deleted EDD checkout), `/my-account` (id 30)

## Pipe Pay (the gateway, configured on this site)
- Registered as the only enabled WC payment gateway. Settings live in option `woocommerce_pipepay_settings`.
- Title shown to customer: "Pipe Pay"
- Description (configured-on-this-site value, set in WP Admin): "Pay with Venmo, Cash App, PayPal, or Zelle. After placing the order you will be shown payment instructions; upload a screenshot of the payment to complete checkout." Plugin's built-in default is the shorter "Send payment using your preferred app. You'll receive instructions on the next page."
- Brand accent color: `#1336a8`
- Reminder cadence: 5 / 20 / 45 minutes; auto-cancel at 60 min (matches the brief)
- Proof retention: 90 days
- AI provider: **empty** → manual review mode. Every order lands in `wc-awaiting-approval` status (custom; introduced v1.7.1) after the customer uploads a screenshot, surfaces in the Proofs queue, and requires admin Approve/Reject from there. Pre-v1.7.1 orders use the standard `on-hold` status; the queue and meta-box buttons match both for backward compat.
- **Placeholder P2P handles must be replaced before launch** (see open to-dos)

## CTA wiring (homepage + /pricing → WC)
Every pricing card has TWO stacked CTAs as of 2026-05-08: a primary "Start 7-day trial" button (trial product with `intent=` carrying the customer's tier preference) and a quieter "Buy now - skip the trial" `.pp-btn--ghost` button (direct add-to-cart for the paid tier). The hero and final-CTA show a primary trial button plus a small `.pp-cta-skip` text link below pointing to `/pricing` for ready-to-buy visitors.

| Button | Lands at | Behavior |
|---|---|---|
| Header "Start free trial" (homepage + subpages) | `/checkout/?add-to-cart=38` | Adds the trial product to cart |
| Hero "Start 7-day free trial" | `/checkout/?add-to-cart=38` | Trial |
| Hero skip-link "or skip the trial - buy a license now →" | `/pricing` | Sends ready-to-buy visitors to the pricing cards |
| Final-CTA "Start 7-day free trial" (homepage) | `/checkout/?add-to-cart=38` | Trial |
| Final-CTA skip-link (homepage) | `/pricing` | Same as hero skip-link |
| Final-CTA skip-link (`/pricing` page) | `#tiers` | Anchor scroll up to the pricing cards (already on /pricing, so no cross-page hop) |
| Pricing card "Single Site" - Start 7-day trial | `/checkout/?add-to-cart=38&intent=34` | Trial with Single Site tier preference for the renewal flow |
| Pricing card "Single Site" - Buy now - skip the trial | `/checkout/?add-to-cart=34` | Direct paid purchase, no trial |
| Pricing card "5 Sites" - Start 7-day trial | `/checkout/?add-to-cart=38&intent=35` | Trial with 5 Sites preference |
| Pricing card "5 Sites" - Buy now - skip the trial | `/checkout/?add-to-cart=35` | Direct paid purchase |
| Pricing card "Unlimited Sites" - Start 7-day trial | `/checkout/?add-to-cart=38&intent=36` | Trial with Unlimited preference |
| Pricing card "Unlimited Sites" - Buy now - skip the trial | `/checkout/?add-to-cart=36` | Direct paid purchase |

A `woocommerce_add_cart_item_data` filter in `functions.php` empties the cart before adding any Pipe Pay product, so the user clicking trial then buy-now (or vice versa) doesn't pile both in the cart. `page-checkout.php` is cart-aware and renders the "Complete your purchase" hero when a paid tier (34/35/36) is in the cart vs the "Start your 7-day free trial" hero when product 38 is in the cart.

## Theme
- **Active theme:** `pipepay-child` (custom child of GeneratePress 3.6.1)
- Source of truth: `pipe-pay-site/pipepay-child/` in this directory (sync to server with `tar czf`/`scp`/`tar xzf`).
- On server: `/var/www/pipepay/wp-content/themes/pipepay-child/`
- Files:
  - `style.css` - variables + brand styles + per-section markup + page-hero + sub-page templates. Loaded on every page. **Note:** GeneratePress auto-enqueues this with `filemtime()` cache-busting under handle `generate-child` - do NOT also `wp_enqueue_style` it from functions.php (creates a duplicate `<link>` and stale CDN-cached versions can win cascade).
  - `woocommerce.css` - WC-page-only override block (~1057 lines). Brand-matches the classic + Block Checkout, shop, cart, my-account. Conditionally enqueued in `functions.php` via `is_woocommerce()/is_cart()/is_checkout()/is_account_page()` so marketing pages don't ship the override styles. Was previously appended to `style.css`; split out 2026-05-08 in v1.7.0.
  - **Logo / brand mark assets:**
    - `partials/logo-svg.php` - inline Pipe Pay logo SVG, single source of truth for in-page rendering. Uses `currentColor` so the same markup serves both the brand-blue header/footer placement and the white-on-blue final-CTA via `$pp_logo_variant = 'inverse'`. Included from `header.php`, `footer.php`, and both spots in `front-page.php` (header + final-CTA inverse variant).
    - `favicon.svg` - same brand mark with the brand color baked in (no CSS context for a favicon to inherit `currentColor`). Wired up in `functions.php` via the `wp_head` action as both `<link rel="icon" type="image/svg+xml">` and `<link rel="apple-touch-icon">`; also overrides any WP admin Site Icon via the `get_site_icon_url` filter so this file is the authoritative favicon.
  - `functions.php` - Manrope + Geist Mono font loading, body class, title/meta filters, robots.txt, WC hooks (page hero injection via `woocommerce_before_main_content`, no-sidebar layout via `generate_sidebar_layout`, cart deduplication via `woocommerce_add_cart_item_data`), inline mobile hamburger toggle JS via `wp_footer`, **dequeue of all WC frontend scripts/styles + jQuery on non-WC pages** (`wp_enqueue_scripts` priority 99) - saves ~6 JS files and ~4 CSS files on home/how-it-works/pricing/docs/changelog/contact/legal pages
  - `front-page.php` - homepage (8 sections after the IA split): Hero, persona triptych ("You're probably one of three kinds of store"), 4-step How it works (with "Read the full breakdown →" link to /how-it-works), Pricing cards, Compat, Testimonials, Ship log, Final CTA. Bypasses `get_header()`/`get_footer()` and inlines its own header markup.
  - `header.php` + `footer.php` - used by all non-homepage pages. Both contain the inline blue-background hamburger button (`.pp-nav-toggle`) that toggles a drawer at ≤760px viewports
  - `page.php` - default sub-page template (privacy, terms, refunds)
  - `page-checkout.php` - wraps WC's `[woocommerce_checkout]` shortcode in a Pipe Pay page hero. Cart-aware: shows "Start your 7-day free trial" hero when the trial product is in cart, "Complete your purchase" hero for paid tiers.
  - `page-how-it-works.php` - long-form pitch: pain, founder story, traceable workflow features, AI deep-dive, security, onboarding (now ICP2-aware), final CTA
  - `page-pricing.php` - pricing cards, "Is Pipe Pay for you?" yes/no qualification (5 bullets each side), "What Pipe Pay isn't," FAQ, final CTA
  - `page-changelog.php` / `page-docs.php` / `page-contact.php` - custom templates for those slugs
  - `page-doc-stub.php` - single template for all 9 `/docs/{slug}/` child pages. Holds full article bodies in a `$docs` PHP array (HEREDOC) keyed by slug; renders the article when present or falls back to a "coming soon" stub + topic outline when absent
- **Layout convention:** the first content section after a `pp-page-hero` uses `pp-section--tight` (60px top/bottom padding) so the gap between the hero and the body stays tight. Applied across all subpage templates and the WC hero hook in functions.php.
- **Mobile nav:** ≤760px shows a blue rounded-square hamburger; tap opens a full-width drawer with all 4 nav links + the Start free trial button. Closes on ESC, link click, or hamburger toggle. Markup lives in both `front-page.php` and `header.php`; JS in `functions.php` via `wp_footer` action.
- The homepage renders directly from `front-page.php`. It bypasses the child's `header.php`/`footer.php` (it inlines its own header markup and skips `get_header()`/`get_footer()`) and also hides any GeneratePress chrome via `display:none` on `body.is-front-page`. Subpages get the child's `header.php`/`footer.php`.
- Brand: `#1336a8` royal blue, Manrope sans + Geist Mono, white + `#f7f8fa` alternating sections. Matches the brief.
- **Logo lives inline as SVG in FOUR places - keep them in sync when changing the mark:**
  1. `front-page.php` `$logo_svg` (homepage header, currentColor)
  2. `header.php` `$logo_svg` (subpage header, currentColor)
  3. `footer.php` `$logo_svg` (subpage + homepage footer, currentColor)
  4. `front-page.php` final-CTA inline `<svg>` (white-on-blue inverse variant)
- Screenshot placeholders are intentional - replace with real product screenshots from a v1.4.0 install (see open to-dos).

## Open to-dos

### Before launching the store (you, in WP Admin)
- [ ] **Replace placeholder Pipe Pay P2P handles** - `WP Admin → WooCommerce → Settings → Payments → Pipe Pay → Manage`. Currently:
  - Venmo: `@your-venmo-handle`
  - Cash App: `$your-cashapp`
  - PayPal F&F: `wittscafidi@gmail.com` (confirm or replace)
  - Zelle: `wittscafidi@gmail.com` (confirm or replace)
- [ ] **Add an AI provider key** in the same settings panel (Claude / OpenAI / OpenRouter), or stay in manual review mode. Without a key every order requires you to approve from the Proofs queue manually.
- [ ] **Set up SMTP relay** so order receipts, license-key emails, and trial reminders deliver. Server can't send mail directly (residential IP). Recommended: WP Mail SMTP plugin + Resend (free 3k/mo) or Postmark (free 100/day) + the SMTP creds from whichever you pick.
- [ ] **Refund EDD Pro** within the 14-day window (purchased 2026-05-06). EDD support email or use the link in your purchase receipt. The plugin is already deactivated; safe to remove plugin files after refund clears: `sudo -u www-data wp --path=/var/www/pipepay plugin delete easy-digital-downloads-pro`.
- [ ] **Enable Two-Factor on `pipepayadmin`** - Users → Profile → Two-Factor Options.
- [ ] **Update the default WC country** if you're not in NY - WP Admin → WooCommerce → Settings → General.
- [ ] **File for an LLC** (mentioned in earlier conversation) and update WC store legal name + the Terms of Service / Privacy Policy / Refund Policy pages once incorporated.

### End-to-end test path (run before launch)
- [ ] Visit https://pipepay.app in an incognito window. Click any "Start 7-day free trial" CTA. Should land on `/checkout/?add-to-cart=38` with hero kicker "TRIAL," title "Start your 7-day free trial."
- [ ] Fill in dummy email/address. "Place Order." Order should complete (no payment step needed for $0 trial).
- [ ] Confirm in WP Admin → WooCommerce → Orders that the order exists, status "Completed."
- [ ] Confirm in WP Admin → WooCommerce → API Manager → Licenses that a 7-day license was issued for the test customer.
- [ ] Check the WP Admin Mail Log (or your inbox once SMTP is wired) for: order-confirmation email + license-key email.
- [ ] Repeat with a paid tier: visit homepage, scroll to pricing, click "5 Sites." Should land on `/checkout/?add-to-cart=35` with hero kicker "CHECKOUT."
- [ ] Confirm Pipe Pay payment option is preselected with the description "Pay with Venmo, Cash App, PayPal, or Zelle..."
- [ ] Place order. Status should be "Pending payment."
- [ ] (Once you've replaced the P2P handles) confirm the post-checkout page shows your real handles + the customer's amount + order number in the upload-screenshot UI.
- [ ] (Once a real test order exists) manually mark the order "Processing" or "Completed" in WC admin. API Manager should auto-issue a 1-year license.
- [ ] **End-to-end license activation test for v1.7.0.** All four WC products (34/35/36/38) point at `pipe-pay-v1.7.0.zip` with `_product_version=1.7.0` (done 2026-05-08). Existing 1.5.x/1.6.x customers will see "Update available" within ~12h of next WP cron. Smoke test before publicly recommending the upgrade:
  1. Buy a Pipe Pay tier (or manually create an order + mark it Processing) so API Manager issues a real license key.
  2. On a separate test WC install (PHP 8.0+ - required since v1.6.4), install `pipe-pay-v1.7.0.zip` (do NOT set `PIPEPAY_DISABLE_LICENSING` on the test site - that constant is for the dogfood install only; the test site needs the License page visible).
  3. Go to *WP Admin → Pipe Pay → License*. Paste **only the license key** - no product ID field should appear.
  4. Click Activate. Confirm: notice says "License activated for [tier name]…", page now shows the activated card with masked key + tier title + Deactivate button.
  5. Verify the network: open DevTools → Network. The resolver call should be `POST /wp-json/pipepay-license/v1/resolve` with the api_key in the request body, NOT in the URL.
  6. In `pipepay.app/wp-admin/`, check API Manager → Activations: the test site should appear with activation count = 1.
  7. On the test site, go to *Plugins → Installed Plugins → Pipe Pay → "View details"*. Confirm the Plugin Info popup loads (means the SDK's plugins_api hook resolved against pipepay.app).
  8. Idempotency: click Deactivate, then immediately re-paste the same key and click Activate twice quickly. Server should record only ONE re-activation (check API Manager → Activations).
  9. Tier upgrade: click Deactivate. Buy/create an order on a different tier (e.g. unlimited, product 36). Paste the new key into the same License page. Confirm it resolves to product 36 and re-activates without any zip swap. Check the WP options table - the OLD product's `wc_am_client_<old_pid>` row should be gone.
  10. Auto-update flow: bump `pipe-pay.php` version to the next patch (e.g. 1.7.1) locally, rebuild the zip, replace product files + bump `_product_version` on product 34 (or whichever tier you tested with). On the test site, *Dashboard → Updates* should show "Update available" within ~12h or after clicking "Check Again."
  11. Signature verification: in DevTools → Network on the test site during activation, the resolver response should include an `X-Pipepay-Signature` header. Watch the WC log (Status → Logs, source=pipepay) for `license response missing signature` warnings - only legitimate during the rollout window if the test site happens to talk to a pre-v1.2.0 mu-plugin.

- [ ] **Hands-on verification of the trial-tier-intent flow (Stages 1-4 shipped 2026-05-07).** Automated tests are 30/30 green: button URLs + cart-item filter + order-item persistence + HMAC round-trip + `/renew/` redirect for intent-bearing licenses + picker fallback + edge cases. What still needs human eyeballs:
  1. **Update the Cloudflare Cache Rule** "Bypass dynamic + logged-in" to add `/renew/*` alongside `/checkout/*`, `/cart/*`, `/my-account/*`, `/wp-json/*`. The mu-plugin already sends `Cache-Control: no-store, no-cache` headers, so worst case CF respects those - but explicit edge bypass is the belt-and-suspenders move per the cache rules section above. Two-line dashboard change.
  2. **Real customer browser walkthrough.** In an incognito window: visit `/pricing/`, click "Start 7-day trial" on the Unlimited card, complete the trial checkout (real `WC()->cart` session, real billing form). Then in `/wp-admin/`, manually mark the order completed. API Manager should mint a real trial license. Generate the renewal URL (snippet in the bottom of `pipepay-license-renewals.php`), paste into a fresh incognito window, confirm it 302s to `/checkout/` with the **Unlimited Sites** product preloaded in the cart. Repeat for "no intent" by visiting `/checkout/?add-to-cart=38` directly (no `?intent`) - the renewal URL should render the tier picker, click any tier, confirm cart populates correctly.
  3. **Visual QA of the tier picker page** at `/renew/?key=...&token=...` on a trial without intent. The page uses the existing `.pp-pricing-grid` styling but renders inside the renewal flow. Check mobile (≤720px) + desktop layouts; confirm hero spacing matches the rest of the site; confirm the "Continue with [tier]" buttons are visually distinct enough.
  4. **Paid-renewal branch (year-2+ scenario).** The mu-plugin's logic for `product_id ∈ {34, 35, 36}` (i.e. paid licenses, not trials) is implemented but never exercised in our automated tests because we only have trial test data. When the first real paid customer's renewal cycle approaches, run the renewal URL against their license and confirm the cart-item meta `_pipepay_renewal_for_license` is attached on the line item. (Effect of this meta is wired up by the future order-completion hook in the renewal-cadence work below.)
  5. **Real API Manager license issuance.** Our automated test seeded synthetic rows in `wp_wc_am_api_resource` because the `wc_create_order()` path doesn't fire `woocommerce_payment_complete`, which is what API Manager listens for. Before relying on real customer data, place ONE real test order through the actual checkout flow (Block Checkout, complete payment, mark completed) and verify (a) API Manager auto-mints a license row, (b) the row's `master_api_key` is what shows on the customer's `/my-account` page, (c) the renewal URL built from that real key resolves correctly.

- [ ] **License renewal email cadence + one-click renewal flow.** Server-side on pipepay.app - drives renewal rate without bricking customer gateways. The plugin's gateway always keeps working; only auto-updates and support are gated on license status (per the *Competitive defense → License model* section below). All implementation lives in a new mu-plugin `wp-content/mu-plugins/pipepay-license-renewals.php` next to the existing resolver mu-plugin. Hard prerequisite: SMTP relay must be wired up first (Resend or Postmark free tier; see Operations subsection below).
  - **Cadence (paid tiers - products 34, 35, 36):**
    - T-30: friendly heads-up
    - T-7: 1 week left, slightly firmer
    - T-0 (expiry day): "your gateway keeps working - only updates pause"
    - T+7: first post-lapse, 23 days of grace remaining
    - T+30: final notice, no further reminders
  - **Cadence (trial - product 38):**
    - T-2: trial ending in 2 days, here's how to convert
    - T+0: trial ended, link to paid tiers
    - No -30/-7/+7/+30 for trials.
  - **Daily cron** at 02:00 UTC via Action Scheduler. Handler queries `wp_wc_am_api_resource WHERE active=1`, computes `days_to_expiry = ceil((access_expires - now) / 86400)`, matches each license against the cadence schedule with ±1 day tolerance.
  - **Idempotency:** stamp `_pipepay_renewal_stage_<N>_sent_at` per license per stage so a double-firing cron doesn't double-send. If `wp_mail` returns false, don't stamp - next day retries. After 3 consecutive failed sends per license, log to `error_log` and skip.
  - **Renewal URL - Option B (one-click HMAC):**
    - Format: `https://pipepay.app/renew/?key=<license_key>&token=<hmac>`
    - HMAC inputs: license key + tier product_id + current `access_expires` + a server secret (new constant in `wp-config.php`, e.g. `PIPEPAY_RENEWAL_HMAC_SECRET`)
    - Lands on a custom WP page that validates the HMAC, looks up the license, pre-fills the cart with the matching tier, and pre-fills checkout fields from the customer's WC record
    - On successful payment, a `woocommerce_order_status_completed` hook detects the renewal (via a hidden cart-item meta carrying the original license key) and **extends the existing license's `access_expires` by 365 days** instead of letting Kestrel mint a new license. Same key, fresh expiry.
    - Fallback: if HMAC validation fails or license is unknown, route to `/checkout/?add-to-cart=<tier_id>` and let them complete a normal purchase.
  - **Edge cases to handle in the handler:**
    - Customer renews mid-cadence (e.g. between T-30 and T-7) → new `access_expires` puts them out of all schedule windows, no further reminders fire automatically.
    - Multi-license customers → each license gets its own series, addressed to its own billing email; body disambiguates with "the [tier] license you bought on [date]".
    - License manually deactivated by admin (`active=0`) → skipped by the WHERE clause.
    - Customer changed billing email → use the email currently on the WC order via join through `order_id`.
  - **Testing path before going live:**
    1. Manually `UPDATE wp_wc_am_api_resource SET access_expires = UNIX_TIMESTAMP() + (30*86400) WHERE api_resource_id=<id>` on a test license to set expiry exactly 30 days out.
    2. Run cron handler manually: `sudo -u www-data wp --path=/var/www/pipepay action-scheduler run --hooks=pipepay_license_check_renewals`. Confirm one email lands and the stamp is set.
    3. Re-run cron, confirm NO duplicate email (idempotency check).
    4. Repeat for -7, 0, +7, +30 by adjusting `access_expires` and clearing the appropriate stage stamp.
    5. Click the renewal URL from a test email, complete checkout, confirm `access_expires` extends by 365 days on the EXISTING license row (no new row issued).
  - **Estimated effort:** ~1.5 working days (1 day for cadence + cron + emails, half-day for Option B HMAC renewal flow).
  - **Local source of truth:** `pipe-pay-site/mu-plugins/pipepay-license-renewals.php`. Sync to server with the existing mu-plugin sync command in the cheatsheet.

### Pipe Pay plugin - next patch (v1.7.5)
- [ ] **Register delete capabilities on `wc-awaiting-proof` + `wc-awaiting-approval` post statuses.** Symptom: WC admin orders UI's bulk Trash/Delete and `wp wc shop_order delete` both return 403 "Sorry, you are not allowed to delete this resource" against orders in either custom status. Root cause: the `register_post_status()` calls in `pipe-pay.php` (lines ~54-89) don't pass a `capabilities` map, so WP/WC fall back to a default that doesn't grant delete to anyone. Fix: add a `capability_type => 'shop_order'` (or an explicit map) so admins can trash/delete via the standard UI without resorting to direct-DB cleanup. Discovered 2026-05-08 when wiping the test-order table required `DELETE FROM wp_wc_orders` SQL - see the order-wipe in CLAUDE.md history below.

### Operations
- [ ] **Add `pipepay.app` to Google Search Console** - verify via DNS TXT record (Cloudflare DNS panel makes this easy), then submit `https://pipepay.app/wp-sitemap.xml`. Leave the existing `pipepay.money` property in place so the 301 traffic stays monitored as it migrates over.
- [ ] Off-server uploads backup (UpdraftPlus → S3/B2/Backblaze; needs creds)
- [ ] Decide whether to remove NOPASSWD sudo for `witt-scafidi` after this push of work is done
- [ ] Wire smoke-test ALERT log to a real notification channel (Discord/Slack webhook, or external monitor like UptimeRobot/BetterStack). Currently logs only to `/var/log/pipepay-uptime.log` and `journalctl -p err`.

### Theme + content
- [ ] Take real product screenshots from a working install of v1.4.0 and drop them in `/var/www/pipepay/wp-content/uploads/`. Replace the inline placeholders in `front-page.php` (search for `screenshot-placeholder`):
  - Hero: customer payment page (QR, handle, sticky upload bar)
  - How-it-works composite: end-to-end customer flow
  - AI deep-dive: admin Proofs review queue with confidence badges
- [ ] Replace placeholder testimonials in section 11 of `front-page.php` with real ones once collected
- [ ] Wire `/contact` to a real contact form (Fluent Forms recommended; needs SMTP first)

### Resolved
- [x] **Custom `wc-awaiting-approval` status for manual review** (2026-05-08, plugin v1.7.1): replaces the prior `on-hold` landing for AI-disabled / low-medium-confidence orders. Status flow is now `awaiting-proof` (pre-upload) → `awaiting-approval` (post-upload, pre-admin-decision) → `processing`/`cancelled`. High-confidence AI auto-approval path unchanged (`awaiting-proof` → `processing` directly). Stock-holding parity with on-hold via `woocommerce_order_is_pending_statuses`. New customer email class `pipepay_review_pending` (subject "we received your payment", heading "We've got your screenshot") replaces the prior reuse of `customer_on_hold_order` so the copy matches the actual state. Pipe Pay Proofs queue and meta-box approve/reject buttons accept both `awaiting-approval` and legacy `on-hold` so pre-1.7.1 orders stay actionable. Also bundled the `_wpnonce` fix on the proof-image proxy URL (admin meta box + Pipe Pay Proofs queue + history) - was a separate hot-patch landed earlier today as 7cf1848 on the plugin repo. No DB migration: too few existing rows to justify the risk on this dogfood install.
- [x] **Direct-purchase CTA path** (2026-05-08): "Buy now - skip the trial" added across the site for visitors who want to bypass the 7-day trial. Six new buttons: each pricing card on `/` and `/pricing` now stacks a primary "Start 7-day trial" CTA (existing trial-with-intent flow) above a quieter `.pp-btn--ghost` "Buy now" CTA pointing directly at `?add-to-cart=34/35/36`. Two new small `.pp-cta-skip` text links: hero + homepage final-CTA both go to `/pricing`; the `/pricing` final-CTA anchors back up to `#tiers` instead of cross-page-hopping. Header/inline-header left alone - the existing "Pricing" nav link covers that discoverability slot. New CSS: `.pp-btn--ghost` (transparent + blue text, lower emphasis than `--secondary`) and `.pp-cta-skip[--inverse]` (text link with specificity bumped via `.pp-cta-skip.pp-cta-skip--inverse a` to beat `.pp-section--blue a:not(.pp-btn)` on the inverse-blue final CTA). Trial remains the visually-primary CTA everywhere; the buy-now path is an escape hatch, not a competing equal-weight CTA. `page-checkout.php` was already cart-aware so direct-tier purchases automatically get the "Complete your purchase" hero. Updated the CTA wiring table above to reflect the new dual-CTA pattern.
- [x] www vs apex canonical: **apex** is canonical, `www` 301s to apex
- [x] Daily DB backup cron + 30-day retention
- [x] Smoke-test cron (every 5 min)
- [x] Wordfence + Two-Factor + GenerateBlocks installed
- [x] Theme: hand-coded child of GeneratePress
- [x] Homepage v1: all 16 sections from the brief rendering
- [x] Sub-pages built (changelog, docs, 9 doc stubs, contact, refund-policy, privacy, terms)
- [x] robots.txt + WP core sitemap configured (`/wp-sitemap.xml`)
- [x] Commerce stack: WooCommerce + Kestrel API Manager + Pipe Pay (dogfood) replaces the abandoned EDD path
- [x] 4 WC products created with API Manager licensing meta (3 paid tiers + free trial)
- [x] Pipe Pay registered as the only enabled WC gateway
- [x] WooCommerce pages (shop, cart, checkout, my-account) restyled to match site brand including Block Checkout
- [x] Cart deduplication: only one Pipe Pay product can be in the cart at a time
- [x] Trial vs paid checkout flow: `/checkout/?add-to-cart=38` for trial, `?add-to-cart=34/35/36` for paid tiers
- [x] WC checkout hero is cart-aware (Trial vs Checkout copy)
- [x] **Pipe Pay v1.8.0 - anti-piracy Phases A + D** (2026-05-09): plugin tagged + pushed to `wittscafidi/pipe-pay-plugin@v1.8.0`, deployed to dogfood, all 4 WC products bumped to `_product_version=1.8.0` with `_downloadable_files` repointed at `pipe-pay-v1.8.0.zip`. Test coverage: 105 → 117 tests, 258 → 273 assertions; CI green on PHP 8.1/8.2/8.3. Triggered by user-reported piracy ("people are starting to share the plugin"). Closes the highest-leverage piracy vector - a fresh install of the zip can no longer enable the gateway without a real activation. **Phase A - first-install gateway-enable gate.** New helper `pipepay_license_has_ever_activated()` in `pipepay-license-verify.php` (side-effect-free, unit-tested). Returns true if `PIPEPAY_DISABLE_LICENSING` is defined (dogfood escape hatch, slated for removal in Phase 5) OR if Kestrel SDK has marked this install as activated (option `wc_am_<pid>_activated = "Activated"`, set after a successful Kestrel server response). Three enforcement points in `class-pipepay-gateway.php`: (1) `process_admin_options()` refuses to set `enabled=yes` on save if not activated + surfaces a `WC_Admin_Settings::add_error` pointing at Pipe Pay → License; (2) `is_available()` returns false at checkout-render time as defense-in-depth against direct `update_option()` writes; (3) `admin_options()` renders a prominent inline notice at the top of the gateway settings page so the merchant sees the activation requirement before configuring anything. Existing activated installs are unaffected - the helper returns true for any install where Kestrel previously confirmed an activation, even after the license later expires. Per CLAUDE.md, in-flight customer orders must keep flowing for expired-license customers; only fresh-install pirates with zero activation history hit the gate. **Phase D - tighter site fingerprinting.** `pipepay_license_get_instance()` now derives the per-site instance from `sha256(home_url() + "|" + wp_salt('auth'))` instead of random bytes. Same site (URL + salt) → same instance → same Kestrel seat. Different site OR salt rotation → different instance → counts as a new seat against the license's activation cap. Closes the gap where Single Site licenses could previously be silently shared across multiple sites (each random instance looking like a fresh activation up to the seat cap). Compute logic extracted to `pipepay_license_compute_instance()` in `pipepay-license-verify.php` - pure function, fully unit-testable. Backward compat: existing activated installs already have a random instance stored in option; we DO NOT overwrite it. Same-site re-activations continue to land on the same seat. **12 new tests:** 6 for `pipepay_license_has_ever_activated` (fresh-install / missing-pid / marker-mismatch / full-activated / exact-marker-value / persists-after-status-flip); 6 for `pipepay_license_compute_instance` (deterministic / different-URL / different-salt / 32-hex / path-affects-output / trailing-slash-sensitive). Bootstrap stubs added: `get_option`, `update_option`, `delete_option`, `home_url`, `wp_salt`, `wp_generate_password` - backed by an in-memory `$GLOBALS` array, reset between tests via `pipepay_test_reset_options()`. **End-to-end smoke test on dogfood:** dogfood gate (with `PIPEPAY_DISABLE_LICENSING` defined) correctly returns true; site responds 200 with no PHP errors; awaiting-proof test order renders the payment page; deterministic instance compute round-trip verified across 4 input combinations. **Roadmap:** A + D shipped today as v1.8.0. C (daily revalidation + revocation, AI-disabled wedge for degraded mode) targeted for v1.8.1. B (zip watermarking with Ed25519-signed download fingerprint) targeted for v1.8.2. Phase 5 (remove `PIPEPAY_DISABLE_LICENSING` + issue real complimentary licenses for pipepay.app and WWP) targeted for v1.8.3.
- [x] **Pipe Pay v1.7.7 - pre-Hunter polish from second 5-agent review** (2026-05-08): plugin tagged + pushed to `wittscafidi/pipe-pay-plugin@v1.7.7`, deployed to dogfood, all 4 WC products bumped to `_product_version=1.7.7` with `_downloadable_files` repointed at `pipe-pay-v1.7.7.zip`. Test coverage: 103 → 105 tests, 255 → 258 assertions; CI green on PHP 8.1/8.2/8.3. Driven by a second 5-agent code review (security adversarial, regression, packaging, test-quality, UX-copy) of v1.7.6 before sending to Hunter. None of the findings were security ship-blockers but four were real customer-impacting bugs. **Ship-blockers fixed:** (1) **License-page admin banner screen ID was wrong** - coded `pipe-pay_page_pipepay-license` but WP builds submenu IDs from `sanitize_title()` of the parent menu's title ("Pipe Pay Proofs" → "pipe-pay-proofs"); real ID is `pipe-pay-proofs_page_pipepay-license`. Banner silently never showed on the License page. Verified via `sanitize_title()` round-trip on dogfood. (2) **`pipepay_get_methods_missing_qr()` ignored `_slot_count`** - iterated all 3 slots regardless of the configured count. If `venmo_slot_count=1` but `venmo_handle_2` held stale data from a prior config, banner falsely flagged "Venmo (account 2) missing QR" for a slot the admin couldn't even see. Now caps consistently with the rest of the plugin. The existing test `test_qr_detection_flags_multiple_slots_per_method` failed CI on first push because it implicitly relied on the bug; updated test to set `slot_count=3` explicitly + added 2 new regression tests (`test_qr_detection_caps_iteration_at_slot_count`, `test_qr_detection_caps_iteration_at_2_when_slot_count_is_2`) to lock in the cap. (3) **Zelle handle-only callout was a dead-end** - `pipepay_dp_get_method_link()` returns `''` for Zelle (no deep link exists), but the v1.7.4 callout copy ("Tap the button below…") promised a button gated on `if ( $callout_link )`. Zelle handle-only customers saw the title + subhead promising a button + no button. Now branches: methods with a deep link get "Pay <handle> on <Method>" + Open <Method> CTA; methods without (Zelle) get "Send payment to <handle> on <Method>" + bank-app instruction copy ("Open your bank's Zelle feature, send to the handle above, then upload your payment screenshot below to complete the order"). (4) **Admin banner copy reframed** - pre-v1.7.7 wording ("haven't…uploaded yet", "Customers can still pay using the handle, but…") subtly framed the supported handle-only mode as second-class. Rewritten as a status report: "<methods> are set up with handle-only payments. Customers can pay using the handle on the checkout page - uploading a QR code is optional and mainly helps customers paying from a desktop browser scan with their phone." Dismiss CTA shortened from "I'll use handle-only mode (hide for 30 days)" to "Hide this notice for 30 days"; primary CTA renamed from "Upload QR codes" to "Add a QR code" (less imperative). **Strongly-recommended fixes:** (5) **WC settings notice scope narrowed** from every WC settings tab to specifically `?tab=checkout&section=pipepay`. Two agents independently flagged this as noise. (6+7) **Customer-page copy made device-neutral** - "Tap the button below" → "Use the button below"; "copy the handle and pay manually" → "send to the handle yourself in the <Method> app". (9) **`get_all_slots_for_method()` now `trim()`s the handle** before the truthiness check - symmetric with `pipepay_get_methods_missing_qr()`. Without it a whitespace-only handle would propagate to the customer page as a literal "Pay     with Venmo" - visibly empty handle. **Bonus rolled in:** (8) callout icon changed from credit-card glyph (which implied card payment - wrong for P2P) to a checkmark-in-checkbox glyph matching the business-profile callout. **Smoke tests on dogfood:** Zelle no-deep-link order renders bank-app instruction copy with no button (the bug is fixed); Venmo deep-link order renders new "Pay @handle on Venmo" + "Use the button below" + button (3 callout-btn instances); business profile order renders unchanged "Open Venmo to add an order memo" (no regression from v1.7.6 CSS removal of `max-width: 280px`). **Deferred to v1.7.8:** test-quality agent's recommendation to extract `pipepay_method_has_valid_slot()` from `process_admin_options` for unit-testability; polarity assertions on the three "skips" tests; "second account" labeling refinement; AJAX-aware dismissal handler; investigate the `Deprecations: 1` PHPUnit warning (pre-existing, not a regression). Customers on v1.7.6 will pull v1.7.7 automatically on the next plugin update check (~12h via WP cron).
- [x] **Pipe Pay v1.7.6 - center the handle-only callout** (2026-05-08): plugin tagged + pushed to `wittscafidi/pipe-pay-plugin@v1.7.6`, deployed to dogfood, all 4 WC products bumped to `_product_version=1.7.6` with `_downloadable_files` repointed at `pipe-pay-v1.7.6.zip`. CI green on PHP 8.1/8.2/8.3 (CSS-only fix, no test surface change). **Bug:** the v1.7.4 handle-only callout looked visually off-center - three combining issues. (1) `.wwp-dp-qr-callout-sub` had `max-width: 280px` + `margin: 0 auto`, constraining the subhead narrower than the callout's content area; the wrap put the second line off-center under the centered icon/title/button. (2) Neither title nor subhead carried explicit `text-align: center` declarations, so a customer theme that reset `p { text-align: left }` (common in WC themes) would leave the text left-aligned despite the parent's `text-align: center`. (3) `.wwp-dp-qr-callout-icon` was `display: inline-flex` and relied on parent text-align inheritance - fragile against the same theme override. **Fix (CSS-only in `templates/pipe-pay-page.php`):** dropped `max-width: 280px` on the subhead so it uses the full callout content width; added explicit `text-align: center` on title + subhead; switched icon to `display: flex` + `margin: 0 auto 12px` so its centering is a block-level property that doesn't depend on parent text-align inheritance. All three changes are defensive against customer-theme overrides. Smoke-tested on dogfood: callout markup still renders ("Pay <strong>@your-venmo-handle</strong> with Venmo", "Tap the button below to open Venmo"), new CSS rules confirmed in the served HTML. Customers on v1.7.5 will pull v1.7.6 automatically on the next plugin update check (~12h via WP cron).
- [x] **Pipe Pay v1.7.5 - handle-only save fix (gateway validation relaxed)** (2026-05-08): plugin tagged + pushed to `wittscafidi/pipe-pay-plugin@v1.7.5`, deployed to dogfood, all 4 WC products bumped to `_product_version=1.7.5` with `_downloadable_files` repointed at `pipe-pay-v1.7.5.zip`. CI green on PHP 8.1/8.2/8.3 (no test count change - bug was in production-only code, not in test surface). **Bug:** v1.7.4 added handle-only support to the customer-page template + a yellow admin banner suggesting QR upload, but missed a hard-fail validation in `class-pipepay-gateway.php::process_admin_options()` (lines 1086-1158) that force-disabled any method whose slots didn't have BOTH a handle AND a QR. Symptom: enabling Venmo with just a handle showed `Pipe Pay: Venmo (missing QR code) could not be enabled. Each method requires both a handle and a QR code...` and flipped `venmo_enabled` back to `no` on save - directly contradicting the v1.7.4 contract that handle-only is supported. **Fix:** the slot-validity check at line 1106-1118 now requires only a non-empty handle (the `$qr_ok = $is_business || '' !== $qr` check is gone). A method with no handle on any slot still force-disables (no payment destination at all). Error message updated from "requires both a handle and a QR code" to "needs a handle on at least one account slot before it can be saved as active. (QR codes are optional - methods can ship with a handle alone.)". **Smoke test on dogfood:** simulated WC settings form submission via `$_POST` injection - `enabled=yes, handle=@test-handle, qr=empty, business=no` → save persists with `enabled=yes, handle=@test-handle, qr=[empty], business=no` ✓. Negative test (`enabled=yes` but no handles on any slot) still correctly force-disables ✓. Customers on v1.7.4 will pull v1.7.5 automatically on the next plugin update check (~12h via WP cron).
- [x] **Pipe Pay v1.7.4 - pay-page button text-color hot-fix + handle-only QR mode** (2026-05-08): plugin tagged + pushed to `wittscafidi/pipe-pay-plugin@v1.7.4`, deployed to dogfood, all 4 WC products bumped to `_product_version=1.7.4` with `_downloadable_files` repointed at `pipe-pay-v1.7.4.zip`. Test coverage: 95 → 103 tests, 239 → 255 assertions; CI green on PHP 8.1/8.2/8.3. **Button text-color hot-fix:** the "Open Venmo / Cash App / PayPal / Zelle" buttons (`.wwp-dp-open-btn`) and the "View order confirmation" success button (`.wwp-dp-done-btn`) shown after upload are `<a>` elements; on the dogfood install (and any customer site whose theme sets `a { color: ... }` with equal-or-higher specificity) the brand-blue link color won and overrode the white text, leaving e.g. dark-blue text on a Venmo-blue button. Pinned `color: #fff !important` across every link state (`:link, :visited, :hover, :focus, :active`) using `a.classname` for higher specificity. Brand backgrounds unchanged. **Handle-only QR mode + yellow banner:** customer-facing template previously rendered an empty space when a non-business method had no QR uploaded. Now falls back to the same callout shape used by business profiles (Open <Method> deep-link button) but with handle-flow copy ("Pay <handle> with <Method>" / "Tap the button below to open <Method>, or copy the handle and pay manually") instead of the business-only memo-field rationale. New admin yellow banner via `pipepay_admin_qr_missing_notice()` hooked on `admin_notices`, scoped to Pipe Pay-relevant screens (gateway settings, Proofs queue, License page) - does NOT follow admin around the dashboard. Lists which enabled non-business slots are missing QRs (e.g. "Venmo, Cash App (account 2)"); links to gateway settings; offers a "use handle-only mode (hide for 30 days)" dismissal that stores a per-user expiry timestamp in `_pipepay_qr_notice_dismissed_until` user-meta. Dismissal is nonce-protected, runs on `admin_init`, redirects without the params on success. Detection helper `pipepay_get_methods_missing_qr( $settings )` lives in pipepay-helpers.php - pure-PHP, side-effect-free, fully unit-testable. Iterates all 3 slots per method (slot 1 = bare key, slots 2/3 = suffixed `_2`/`_3`). Flags slots where the method is enabled, slot has a non-empty handle, slot is NOT a business profile (business intentionally uses URL/Open-button flow regardless of QR), AND the QR field is empty. **8 new tests** covering: enabled non-business with empty QR (flagged), business profiles (skipped - intentional), disabled methods (skipped even with leftover handle), unused slots (empty handle = skipped), set-QR (skipped), multi-slot per method (slot_label format `"Venmo (account 2)"`), cross-method mixed scenarios, empty settings. The Venmo QR field description was updated from "Required for personal profiles..." to "Optional. If you upload a QR..." - now accurate. End-to-end smoke-tested on dogfood: helper correctly returns `missing count=1` with `handle=@your-venmo-handle`; customer page renders new fallback markup ("Pay <strong>@your-venmo-handle</strong> with Venmo", "Tap the button below to open Venmo"); no fatals. Customers on v1.7.3 will pull v1.7.4 automatically on the next plugin update check (~12h via WP cron).
- [x] **Pipe Pay v1.7.3 - post-ship hardening + docs polish** (2026-05-08): plugin tagged + pushed to `wittscafidi/pipe-pay-plugin@v1.7.3`, deployed to dogfood, all 4 WC products bumped to `_product_version=1.7.3` with `_downloadable_files` repointed at `pipe-pay-v1.7.3.zip`. Test coverage: 76 → 95 tests, 200 → 239 assertions; CI green on PHP 8.1/8.2/8.3. Driven by a 5-agent post-ship code review of v1.7.2 (security adversarial, regression, packaging, test-quality, diff-cohesion). None of the findings were ship-blockers but several closed real defense-in-depth gaps. **Security:** `pipepay_log()` now scrubs the `$message` argument for credential-shaped substrings (Bearer/Token/Basic auth schemes, `sk-`/`ck_`/JWT key prefixes) - previously only `$context` keys were redacted; AI-provider exception messages routinely contain echoed Authorization headers from upstream 401 errors. URL validator (`pipepay_validate_ai_endpoint`) hardened: trailing-dot rstrip, urldecode percent-encoded host bytes, IPv6 bracket-form stripping, expanded literal blocklist (cloud metadata: `metadata.google.internal`, `metadata.azure.com`, `instance-data`; IPv6 forms: `ip6-localhost`, `ip6-loopback`). New `pre_update_option_woocommerce_pipepay_settings` filter validates `ai_custom_endpoint` on EVERY write of the gateway settings option - closes the bypass via direct `update_option`/CLI/migration plugins (process_admin_options is only the live-save flow). Custom-endpoint and Anthropic `wp_remote_post` calls add `redirection => 0` - defends against a compromised "OpenAI-compatible" upstream 30x'ing to a private host with the merchant's Authorization header still attached. `WC_Admin_Settings::add_error` call now `class_exists`-guarded so a bad-URL save from a non-WC-settings context doesn't crash with "Class not found". DNS rebinding documented as an inherent limitation of any URL allowlist that doesn't pin DNS resolution; not patched (architectural). **Robustness:** `wp_remote_retrieve_header` guards against array-typed return on duplicate headers (RFC permits but unlikely; was a TypeError vector). AI-throw sentinel switched from string-literal `provider_used === 'error'` to a dedicated `$ai_threw` boolean set only in the catch - robust against a future provider literally named "error". **Docs/UX:** SECURITY.md gains 3 new sections (license-server response signing, custom AI endpoint URL allowlist, log redaction). License-server error notices rewritten with plain-English explanations FIRST and the technical error code in parentheses for support intake (was leaking jargon like `sodium_extension_missing`, `signature_stale`, etc.); 6 internal codes mapped to plain English via new `pipepay_license_signature_error_message()` helper. "license server" capitalization unified across pipepay-licensing.php (12 occurrences, was using 3 inconsistent forms). Stale comments cleaned in pipe-pay.php (on-hold→awaiting-approval), pipepay-hooks.php (5 vs 8 in per-email comment, status-list staleness, email-recipient-filter doc). Orphaned docblock in uninstall.php replaced with a current one. bootstrap.php docstring scope brought up through v1.7.3. Duplicate `global $wpdb` removed. **Tests:** golden-vector Ed25519 test that hand-builds the canonical message in the test itself (literal string template, NOT the production sprintf) so a coordinated production+test format change gets caught - biggest mutation-resistance gap from the test-quality review. Field-order pinning test. Boundary tests at exactly 300s (accept) and 301s (reject) for the replay window - catches off-by-one. 8 new `pipepay_log_scrub_message` tests. 7 new URL validator tests for the v1.7.3 hardening (trailing dot, localhost.localdomain literal, cloud metadata, IPv6 ::1, IPv6 fc00, percent-encoded loopback, uppercase loopback). Tightened depth-cap test that asserts the EXACT level the truncation marker appears at, not "somewhere in the structure" - would catch a future cap raise.
- [x] **Pipe Pay v1.7.2 - security hardening for public release** (2026-05-08): plugin tagged + pushed to `wittscafidi/pipe-pay-plugin@v1.7.2`, deployed to dogfood, all 4 WC products bumped to `_product_version=1.7.2` with `_downloadable_files` repointed at `pipe-pay-v1.7.2.zip`. Test coverage: 43 → 76 tests, 89 → 200 assertions; CI green on PHP 8.1/8.2/8.3. **Mandatory Ed25519 signatures** - closes a CRITICAL security finding from the pre-Hunter agent review. The v1.7.0–v1.7.1 "warn + accept unsigned" backward-compat path was a silent integrity bypass: any attacker stripping the `X-Pipepay-Signature` header was trusted. v1.7.2 makes signatures mandatory by default; opt-out via `define('PIPEPAY_LICENSE_ALLOW_UNSIGNED', true)` in wp-config (visible, audited, deliberate). The verify function moved from `pipepay-licensing.php` to a new side-effect-free `pipepay-license-verify.php` so the unit test suite can require it without booting the WC API Manager SDK. **`ai_custom_endpoint` URL validation** - closes the SSRF + key-exfil HIGH finding. A `manage_woocommerce` user could previously aim it at `http://169.254.169.254/` (AWS IMDS), `http://localhost:6379/`, or any RFC1918 host with the merchant's BYOK API key in the Authorization header. Now: `wp_http_validate_url` + HTTPS-only + reject single-label hosts + reject loopback / private / reserved IP ranges, validated both at save (`process_admin_options`) and at use (`PipePay_Vision_Client::call_openai_compatible`). **TLS-hardening filter priority** bumped from 100 to `PHP_INT_MAX` so no plugin/theme can downgrade `sslverify` after us; host match switched from substring to exact-equality via `wp_parse_url`; every `wp_remote_post` to pipepay.app and AI providers also passes `sslverify=true` + `reject_unsafe_urls=true` per-call as belt-and-braces. **Log redaction tightened** - list expanded from 7 keys to 22 (`token|bearer|authorization|cookie|refresh_token|access_token|master_api_key|private_key|pwd|session|credential|...`), now recurses one level into nested arrays so `['response' => ['headers' => ['authorization' => '...']]]` gets cleaned, coerces objects to `[object: ClassName]` to prevent `__toString` leakage, truncates long string values to 500 chars to limit Imagick-style filesystem path leakage. **Gmail alias normalization** in the per-billing-email upload cap closes the bypass where `user@gmail.com`, `user+1@gmail.com`, and `u.s.e.r@gmail.com` hash differently - all now collapse to one identical bucket via `pipepay_normalize_email_for_rate_limit()`. Same normalizer canonicalizes `googlemail.com → gmail.com`. **Proof meta-box render** gains an early `current_user_can('manage_woocommerce')` guard so a custom `edit_shop_orders`-only role can't see the rendered nonce-bearing proof URL. **Test additions:** `LicenseSignatureTest.php` (12 tests - round-trip, tamper detection, replay-window, api_key binding, malformed input, bundled-key sanity), `HelpersTest.php` (21 tests - redaction key list, recursion, depth cap, string truncation, Gmail normalization full alias-collapse scenario, AI endpoint validation against AWS IMDS / RFC1918 / loopback / single-label / non-https / non-URL inputs). One refactor required to enable testing: `pipepay_license_verify_signature()` extracted to its own side-effect-free file with an optional `$override_public_key_b64` parameter (production callers never pass it; tests use it to round-trip with a fresh keypair).
- [x] **Pipe Pay v1.7.1 - pre-Hunter robustness + capability alignment** (2026-05-08): plugin tagged at v1.7.1, deployed to dogfood, all 4 WC products bumped to `_product_version=1.7.1`. Driven by a 4-agent code review (security, functional, WP/WC compat + packaging, test coverage) before sending the zip to Hunter for external testing. **Ship-blockers fixed:** `uninstall.php` `require_once` for the path-containment guard moved to the top of the file (was at line 136, AFTER the first call site at line 111 - fatal `Call to undefined function pipepay_uninstall_safe_path()` mid-uninstall, leaking proof files and showing a WSOD). Capability alignment: `class-pipepay-admin.php` menu cap, render gate, and a new defense-in-depth check in `handle_proof_actions()` all moved from `manage_options` → `manage_woocommerce` (the License submenu lived under a `manage_options` parent so shop-managers couldn't reach it even though its own cap declaration said they could; Hunter testing as anything but super-admin would have hit a broken admin). Plugin URI / Author URI placeholders (`https://github.com/your-repo/pipe-pay`, `https://your-site.com`) replaced with `https://pipepay.app`. Author "Big Red" → "Pipe Pay". v1.7.0 zip was missing `includes/emails/class-wc-email-pipepay-review-pending.php` (the file existed in source but wasn't included in the build) - confirmed present in the v1.7.1 source dir before re-zipping; v1.7.1 zip will fatal-crash on first WC email init if the email class is omitted again, watch for it. **Robustness fixes:** inflight upload lock in `pipepay_handle_proof_upload()` (transient-based, 60s TTL) prevents two-tab / double-submit double-fire (was burning 2× AI cost + sending duplicate `new_order` admin emails); `pipepay_handle_expire()` now defers by 5 minutes if the inflight lock is held, so a 60-min auto-cancel can't fire mid-upload and resurrect a cancelled order with inconsistent stock. Per-order upload count (`_pipepay_proof_upload_count`) increment moved from BEFORE the AI call to AFTER, conditional on the AI returning a non-error response - AI provider outages no longer burn the customer's 5-attempt retry quota. Resolver call: signature header lookup uses `wp_remote_retrieve_header()` instead of raw `$response['headers'][...]` array access (case-insensitive across WP HTTP transport mocks); HTTP response code now checked explicitly before parsing the JSON body - 5xx surfaces as "License server is temporarily unavailable", 429 as a rate-limit message, anything else unexpected as "HTTP %d unexpected response", instead of the misleading "license key not recognized" when the resolver was actually 500ing. **Test coverage gaps from the audit deferred to v1.7.2** (Ed25519 round-trip + log redaction + per-billing-email cap tests).
- [x] **Phase 6 of remediation plan - site theme polish** (2026-05-08): WC overrides extracted from `style.css` (3988 → 2931 lines, ~1057 lines / ~30KB) into a separate `woocommerce.css`, conditionally enqueued in `functions.php` only on WC pages (mirrors the existing JS-dequeue pattern but inverted). Marketing pages no longer ship the WC override styles to every visitor. Verified live: home loads style.css only; /shop loads style.css + woocommerce.css. Replaced the dead footer-signup form (which posted to /contact with no handler) with a "Release notes →" link to /changelog on both `footer.php` and the inline footer in `front-page.php`. CSS rewritten to match. `date('Y')` → `wp_date('Y')` in the footer ledger to use site timezone instead of server timezone (DST/year-boundary edge case). Stale release-bar copy ("critical Block Checkout payment fix") replaced with current ("license-server response signing · recommended update for everyone"). Skipped: aggressive `!important` reduction (45 declarations, mostly legitimate WC specificity wars; not worth the risk for cosmetic cleanup). Skipped: mobile breakpoint review (760px is fine - iPad portrait users will read either way). Skipped: cart-emptying scope-narrow (no non-Pipe-Pay products on the site today).
- [x] **Phase 5 (b/c/d/f) of remediation plan - Pipe Pay v1.7.0 release** (2026-05-08): plugin tagged + pushed to `wittscafidi/pipe-pay-plugin@v1.7.0`, deployed to dogfood, all 4 WC products bumped to `_product_version=1.7.0`. **License-server response signing (Ed25519)** - biggest item: mu-plugin signs success responses with the private key in wp-config (`PIPEPAY_LICENSE_SIGNING_PRIVATE_KEY`); plugin verifies with the bundled public key (`PIPEPAY_LICENSE_SIGNING_PUBLIC_KEY` constant in `pipepay-licensing.php`) before trusting `product_id`. Defeats a network-position attacker (or compromised pipepay.app) returning substituted product mappings even with HTTPS active. Includes 5-minute max-age replay defense and api_key binding so a captured response for one customer can't be replayed against another. Backward-compat: missing-signature responses are accepted with a warning during the rollout window so older mu-plugin versions don't break plugin activations. Keypair generated 2026-05-08 + backup at `~/Desktop/Pipe Pay/.secrets/pipepay-license-signing-keypair.txt`. **WC structured logging** via `wc_get_logger()` helper at `pipepay_log( $level, $message, $context )` - events visible at WC -> Status -> Logs (source=pipepay), falls back to `error_log()` when WC isn't loaded, defensively redacts api_key/license/secret/password/email keys in context. 5 `error_log()` call sites converted. **Inline admin JS extraction** - the meta-box `<script>` block in `pipepay-hooks.php` (3 fetch handlers) moved to `assets/js/pipepay-admin.js`, properly enqueued via `admin_enqueue_scripts` only on shop-order edit screens. CSP-friendly, browser-cached, easier to maintain. **Block checkout JS** verified correct in v1.6.x - no changes needed. **i18n** scaffolded (`load_plugin_textdomain` + 28 REST error-message wraps); full template + gateway-settings pass deferred to V2 with documented promotion criteria. **Heavy gateway-core refactor** (audit-recommended 5-class split) deferred to V2/V3 - high risk, low immediate payoff, no current iteration pressure on those files; the audit's cheap-piece recommendations (extracted JS, helper-function file) are done.
- [x] **Phase 5a of remediation plan - initial PHPUnit test infrastructure** (2026-05-07): plugin repo gains a real test harness. composer.json + phpunit.xml.dist + tests/bootstrap.php with minimal WP function stubs (no wp-phpunit, no MySQL service - runs in <1s in CI). 43 tests across 3 files cover the v1.6.4/v1.6.5 surfaces: Cloudflare IP detection (positive matches across multiple v4 + v6 ranges, edge boundaries, spoofing scenarios), vision cross-check (parse_amount_string US/European formats, recipient_matches normalization, full parse_response integration including the new cents comparison), uninstall path containment (legitimate proofs dirs allowed; wp-content/plugins/themes/uploads/mu-plugins all refused even with sentinel; traversal attempts canonicalized through realpath then refused). `.github/workflows/tests.yml` runs the suite on PHP 8.1/8.2/8.3 matrix (8.0 dropped because PHPUnit 10.5.62+ requires 8.1+; production code is PHP 8.0-compatible and protected by the runtime guard for older runtimes). Pure-PHP helpers extracted from `pipepay-hooks.php` → `pipepay-helpers.php` and from `uninstall.php` → `pipepay-uninstall-guard.php` so tests can require them without booting the WP runtime or destructive code. **First bug caught by the suite within minutes**: v1.6.4 amount cross-check used `abs(float - float) > 0.01` which falsely tripped on near-edge orders due to floating-point precision (87.50 - 87.49 yielded ~0.010000000000005). Fixed in v1.6.5 by comparing integer cents.
- [x] **Phase 3 of remediation plan - Pipe Pay v1.6.4 → v1.6.5 security release** (2026-05-07): plugin tagged + pushed to `wittscafidi/pipe-pay-plugin@v1.6.4` then immediately to `v1.6.5` (cents-comparison fix), deployed to dogfood, all 4 WC products bumped to `_product_version=1.6.5`. Security: Cloudflare-only IP detection (closes per-IP rate-limit spoofing - 15 v4 + 7 v6 CIDR ranges bundled, refresh quarterly from cloudflare.com/ips); server-side amount + recipient cross-checks (forces confidence='low' if extracted_amount differs from order total by more than 1¢ OR extracted_recipient doesn't substring-match the configured handle - kills prompt-injection screenshots that talk the model into reporting a higher amount); `http_request_args` filter forces sslverify=true + reject_unsafe_urls=true on every pipepay.app call (defeats Kestrel SDK's staging-host TLS downgrade for legit corporate staging hosts); `wc-awaiting-proof` status now `exclude_from_search` + `publicly_queryable=false` (was leaking pre-payment billing data into theme search); per-billing-email upload cap (8/24h, sha1-keyed; was claimed in SECURITY.md but never enforced); tighter uninstall path containment (allowed-roots whitelist + min 2-segment depth + WP-core dirs explicitly forbidden); license key mask shows last 4 only (was first 4 + last 4, leaking the Kestrel `ck_` prefix in support screenshots). Bugs: status registration on `init` priority 1 (defeats race with cart/checkout consumers); admin badge counter capped at 200 (was unbounded `limit=-1` blocking every admin page); per-AI-provider key storage (switching providers no longer sends previous provider's key to new vendor - process_admin_options mirrors saves into `ai_api_key_<provider>`, get_ai_settings reads per-provider first); block checkout i18n. Compat: PHP 8.0 minimum (header bump + runtime guard with admin notice - code already used `match` expressions). Site: `PIPEPAY_SITE_VERSION` constant bumped, v1.6.5 shiplog entry added on home.
- [x] **Phase 4 of remediation plan - license-resolver hardening** (2026-05-07): mu-plugin bumped to v1.1.0. Closed the enumeration oracle: `key_not_found` / `key_inactive` / shape-validation-failure all collapse to one opaque 404 with `code=invalid_key` (was previously distinguishable by status + message - confirmed which keys were real-but-deactivated). Strict charset whitelist (`/^[A-Za-z0-9_-]{8,190}$/`) rejects malformed shapes BEFORE the rate-limit counter increments, so attackers spoofing bad keys can't burn a real customer's bucket on shared NAT. Rate-limit counter now race-safer via `wp_cache_add` + `wp_cache_incr` with transient backstop (was vulnerable to read-modify-write races under burst load). Explicit `is_ssl()` guard returns 400 `https_required` if anyone hits the endpoint on plaintext (defense in depth - nginx + Cloudflare are still primary). `$wpdb->last_error` checked after the DB call; on DB failure we 503 with `service_unavailable` instead of pretending the key was bad (keeps support volume low when Kestrel renames a column). Operational logging via `error_log` on every 4xx/5xx outcome - `[ip key_last4 status reason]` - with the real internal reason (`key_not_found` vs `key_inactive` etc.) preserved for ops even though the client sees the unified response. Probed live with three different malformed-key shapes - all return identical 404 + body.
- [x] **Phase 2 of remediation plan - site a11y + SEO** (2026-05-07): skip-to-content link added to header.php and front-page.php (visually-hidden until focused, blue-on-white), `<main id="content">` everywhere. Mobile hamburger drawer now manages focus correctly: opens with focus moved to first nav link, closes with focus returned to the hamburger button (except on link click), ESC closes and returns focus, content/footer/release-bar marked `inert` while drawer is open. Hamburger bumped from 40x40 to 44x44 (WCAG 2.5.5). `:focus-visible` outlines added to drawer links + toggle. Per-page SEO meta now fires on every template (was home-only) - description + og:title + og:description + og:url + twitter:card on /how-it-works, /pricing, /docs, /docs/*, /changelog, /contact, /privacy, /terms, /refund-policy. Per-template descriptions; post-excerpt + generic fallback for anything unmapped. Canonical URL emitted only on home (WP's `rel_canonical` handles singular pages; would have duplicated). Logo SVG extracted to `partials/logo-svg.php` - single source of truth across header.php, footer.php, and the two copies in front-page.php (header + final-CTA inverse variant via `$pp_logo_variant = 'inverse'`). Hardcoded `https://pipepay.app/...` URLs in front-page.php replaced with `home_url()`. `page-contact.php` `mailto:` href subject now `esc_attr()`-wrapped (was a bare echo of url-encoded data). Twitter card uses `summary` (not `summary_large_image`) until we ship a 1200×630 og:image asset - Phase 6 polish item.
- [x] **Phase 1 of remediation plan - doc truth-up** (2026-05-07): all 9 customer-facing doc articles audited against the v1.6.3 plugin source and corrected. Fixed 4 load-bearing fictions (`PIPEPAY_PROOF_STORAGE_PATH` constant name, fictional `PIPEPAY_AUTOCANCEL_MINUTES` constant, fictional `wc-on-hold-review` status, fictional `pipepay-reminders.php` file). Fixed rate-limit numbers (10/hr per IP-per-order, 50/hr brute-force, 5 lifetime per order - was 20/5/3 + per-customer that doesn't exist). Fixed default auto-approve cap ($200 → $500). Fixed default storage path. Fixed license-server URL (`/wp-json/wc-am-api/v1/` → `/?wc-api=wc-am-api`). Fixed license tier prices ($249/$499/$999 → $299/$599/$1,199). Fixed QR breakpoint (600px → 720px). Fixed cron cadence (6h → daily). Replaced false GD-fallback claim with the real "Imagick required for HEIC" message. Replaced inflated log-retention claim with the real "Order Notes + PHP error_log" path. CLAUDE.md version sweep 1.6.1 → 1.6.3 with v1.6.2 added to local-zips inventory. New `PIPEPAY_SITE_VERSION` constant in `functions.php` consumed by `footer.php` + `front-page.php` (release bar + ledger), so future bumps are one place. Plan: [`plans/2026-05-07-remediation-plan.md`](plans/2026-05-07-remediation-plan.md). Reviews: [`reviews/`](reviews/) and [`../pipe-pay-extracted/reviews/`](../pipe-pay-extracted/reviews/).
- [x] **Domain migration `pipepay.money` → `pipepay.app`** (2026-05-06): new Cloudflare zone, tunnel public hostnames, nginx server block (with `pipepay.money` permanent 301 to `pipepay.app`), `WP_HOME`/`WP_SITEURL` flipped, full DB `wp search-replace` (93 replacements across 11 tables), theme files updated + synced, Pipe Pay plugin rebuilt as v1.5.1 with `pipepay.app` license-server URL, v1.5.1 zip wired into all 4 WC products via `_downloadable_files` + `_product_version`
- [x] **Multi-page IA split** (2026-05-07): homepage trimmed from 16 sections → 8; new `/how-it-works` and `/pricing` pages absorb the deep sections (problem, story, features, AI deep-dive, security, onboarding, what-Pipe-Pay-isn't, FAQ). Header nav switched from in-page anchors to page links; "Start free trial" button now goes straight to `/checkout/?add-to-cart=38` instead of a `#pricing` anchor jump.
- [x] **Persona triptych on home** (2026-05-07): replaces the original "What it is" section. Three cards in their own voice - high-risk vertical / validating an idea / tired of paying fees - each with a pull-quote and resolution paragraph.
- [x] **"Is Pipe Pay for you?" yes/no qualification on `/pricing`** (2026-05-07): 5 green ✓ "yes if any of these are you" bullets covering all three ICPs, 5 red × "probably not" structural disqualifiers (cards, subscriptions, customers won't move off card, no chargeback insurance, non-WooCommerce platform).
- [x] **All 9 doc articles written** (2026-05-07): `getting-started`, `ai-verification`, `admin-guide`, `configuration`, `order-lifecycle`, `refunds`, `security`, `license-management`, `troubleshooting`. Bodies live in the `$docs` array in `page-doc-stub.php`; the template auto-renders the body when a `body` key is present and falls back to "coming soon" + outline otherwise.
- [x] **Mobile hamburger nav** (2026-05-07): blue rounded-square button at ≤760px opens a full-width drawer. Replaces the previous broken behavior where nav links just `display: none`'d with no replacement. Root cause of the visible bug was a duplicate `wp_enqueue_style` of the child stylesheet - Cloudflare CDN was serving an old `?ver=0.8.5` copy on top of the fresh GeneratePress-enqueued one. Removed the duplicate enqueue.
- [x] **Speed wins** (2026-05-07): Cloudflare Cache Rules cache HTML for anonymous visitors with bypasses for `/wp-admin/*`, `/wp-login*`, `/checkout/*`, `/cart/*`, `/my-account/*`, `/wp-json/*`, `/wp-cron*`, `add-to-cart=` queries, and logged-in cookies. Plus dequeue of all WC frontend JS + jQuery on marketing pages - home went from 9 JS files → 2. Marketing-page TTFB dropped from ~290ms to ~50ms cached at edge.
- [x] **Page-hero alignment** (2026-05-07): removed the 880px container override from `.pp-page-hero` and the auto-centering on `.pp-pricing-grid` so kicker, title, and pricing card borders align flush-left with the standard container edge.
- [x] **First-section padding tightened** (2026-05-07): the section right under any `pp-page-hero` now uses `pp-section--tight` (60px top) instead of the default 104px; applied across `/docs`, `/docs/*`, `/how-it-works`, `/pricing`, `/changelog`, `/contact`, `/privacy`, `/terms`, `/refund-policy`, `/checkout`, and the WC hero hook.
- [x] **Pipe Pay v1.5.0 - Kestrel SDK integration** (2026-05-06): embedded the Kestrel WC API Manager PHP SDK (`includes/wc-am-client.php`), wired update hooks via `pre_set_site_transient_update_plugins` / `plugins_api`, added `PIPEPAY_DISABLE_LICENSING` escape hatch.
- [x] **Pipe Pay v1.6.0 - one-field license activation** (2026-05-06): custom resolver mu-plugin on pipepay.app maps license key → product ID. Plugin's License page is a single field (no product ID). Tier upgrades work without zip swap. Migration path for existing 1.5.x customers is automatic - they just upgrade and re-paste their key.
- [x] **Pipe Pay v1.6.3 - security/correctness audit hardening** (2026-05-07): fixes from a parallel three-agent code review of the Kestrel implementation. Resolver call moved to POST body (was URL query string - keys were landing in nginx access logs). Tier-upgrade cleanup of old SDK options. Deactivate only clears local state on server confirm. Idempotent re-activation (no double-burn). `manage_woocommerce` capability instead of `manage_options`. Stale-nonce friendly notice. `esc_html` on remote response messages. Resolver IP detection simplified to `REMOTE_ADDR` (nginx already does the trusted CF rewrite). uninstall.php sweeps license options + SDK option residue.

## Common operations cheatsheet

```bash
# SSH
ssh witt-scafidi@100.102.251.125

# wp-cli (always run as www-data, with --path)
sudo -u www-data wp --path=/var/www/pipepay <command>

# Disable a misbehaving plugin
sudo -u www-data wp --path=/var/www/pipepay plugin deactivate <slug>

# View the Pipe Pay gateway settings as JSON
sudo -u www-data wp --path=/var/www/pipepay option get woocommerce_pipepay_settings --format=json

# List all WC orders
sudo -u www-data wp --path=/var/www/pipepay wc shop_order list --user=pipepayadmin

# Manually mark an order complete (issues the API Manager license)
sudo -u www-data wp --path=/var/www/pipepay wc shop_order update <order_id> --status=completed --user=pipepayadmin

# Run a backup right now
sudo /usr/local/sbin/pipepay-backup.sh

# Tail nginx errors
sudo tail -f /var/log/nginx/error.log

# Tail cloudflared
sudo journalctl -u cloudflared -f

# Reload nginx after a config change
sudo nginx -t && sudo systemctl reload nginx

# Sync the local theme to the server
cd "/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site" && tar czf /tmp/pipepay-child.tgz --exclude='._*' -C . pipepay-child && scp /tmp/pipepay-child.tgz witt-scafidi@100.102.251.125:/tmp/ && ssh witt-scafidi@100.102.251.125 'sudo rm -rf /var/www/pipepay/wp-content/themes/pipepay-child && sudo tar xzf /tmp/pipepay-child.tgz -C /var/www/pipepay/wp-content/themes/ && sudo find /var/www/pipepay/wp-content/themes/pipepay-child -name "._*" -delete && sudo chown -R www-data:www-data /var/www/pipepay/wp-content/themes/pipepay-child && sudo systemctl reload php8.3-fpm'

# Sync the resolver mu-plugin to the server
scp "/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site/mu-plugins/pipepay-license-resolve.php" witt-scafidi@100.102.251.125:/tmp/ && ssh witt-scafidi@100.102.251.125 'sudo install -o www-data -g www-data -m 644 /tmp/pipepay-license-resolve.php /var/www/pipepay/wp-content/mu-plugins/pipepay-license-resolve.php && rm /tmp/pipepay-license-resolve.php && sudo systemctl reload php8.3-fpm'

# Replace the dogfood gateway with a new zip (deactivate, swap files, reactivate)
ssh witt-scafidi@100.102.251.125 'sudo tar -czf /tmp/pipe-pay-backup-$(date +%Y%m%d-%H%M%S).tar.gz -C /var/www/pipepay/wp-content/plugins pipe-pay && sudo -u www-data wp --path=/var/www/pipepay plugin deactivate pipe-pay && sudo rm -rf /var/www/pipepay/wp-content/plugins/pipe-pay && sudo unzip -q /var/www/pipepay/wp-content/uploads/woocommerce_uploads/pipe-pay-v1.7.0.zip -d /var/www/pipepay/wp-content/plugins/ && sudo chown -R www-data:www-data /var/www/pipepay/wp-content/plugins/pipe-pay && sudo find /var/www/pipepay/wp-content/plugins/pipe-pay -type f -exec chmod 644 {} \; && sudo find /var/www/pipepay/wp-content/plugins/pipe-pay -type d -exec chmod 755 {} \; && sudo -u www-data wp --path=/var/www/pipepay plugin activate pipe-pay && sudo systemctl reload php8.3-fpm'

# Smoke-test the resolver endpoint (with an invalid key - should 404)
curl -s -X POST -d "api_key=test_invalid_key_xxxx" https://pipepay.app/wp-json/pipepay-license/v1/resolve

# Inspect a live license key in API Manager (returns product_id, activations, instance, etc.)
ssh witt-scafidi@100.102.251.125 'sudo -u www-data wp --path=/var/www/pipepay db query "SELECT api_resource_id, master_api_key, product_id, product_title, active, activations_total, activations_purchased FROM wp_wc_am_api_resource\G"'
```

## Local zips
- `/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-v1.7.1.zip` - **current customer-facing release AND what's installed on the dogfood gateway.** Adds `wc-awaiting-approval` custom status for the post-upload manual-review state (replaces the prior `on-hold` reuse), a dedicated `pipepay_review_pending` customer email so the copy matches the actual state ("we received your screenshot, reviewing now"), and bundles the `_wpnonce` fix on the proof-image proxy URL (admin meta box + Pipe Pay Proofs queue + history). Wired into all 4 WC products. Staged on the server at `/var/www/pipepay/wp-content/uploads/woocommerce_uploads/`.
- `/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-v1.7.0.zip` - predecessor of 1.7.1. License-server response signing (Ed25519), structured `wc_get_logger()` logging, inline admin JS extracted to `assets/js/pipepay-admin.js`, i18n scaffolding. Rollback option.
- `/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-v1.6.5.zip` - predecessor of 1.7.0. Security hardening release (Cloudflare-only IP, amount/recipient cross-check in cents, TLS verify on license calls, per-email upload cap, per-AI-provider keys, PHP 8.0 min). Rollback option.
- `/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-v1.6.4.zip` - predecessor of 1.6.5. Same security hardening as 1.6.5 BUT with a floating-point bug in the amount cross-check that falsely failed orders within $0.01 of the model's read. Use 1.6.5+ instead; keep 1.6.4 only as a historical artifact.
- `/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-v1.6.3.zip` - predecessor of 1.6.4. Last build at PHP 7.4 minimum.
- `/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-v1.6.2.zip` - predecessor of 1.6.3; superseded but kept as rollback option.
- `/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-v1.6.1.zip` - first build after the v1.6.0 → v1.6.1 audit hardening. Rollback option.
- `/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-v1.6.0.zip` - first build with one-field activation. Superseded; keep for rollback only.
- `/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-v1.5.1.zip` - last release before the one-field flow. Forced customers to enter both license key + product ID. Keep for rollback.
- `/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-v1.5.0.zip` - initial Kestrel SDK integration; URL still on `pipepay.money`. Not safe to ship.
- `/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-v1.4.2.zip`, `pipe-pay-v1.4.1.zip`, `pipe-pay-v1.4.0.zip` - pre-licensing builds. Functional gateway only, no auto-update. Rollback floor.
- `/Users/wittscafidi/Desktop/Pipe Pay/woocommerce-api-manager.zip` - Kestrel API Manager 3.7.6, kept as a backup. Live install pulls updates via WC.com Helper, not from this zip.
- `/Users/wittscafidi/Desktop/Pipe Pay/easy-digital-downloads-pro-3.6.7.zip` - abandoned commerce stack. Delete after EDD refund clears.

---

# Competitive defense

> AI made it cheap to clone the code. It did NOT make it cheap to acquire 50 paying merchants in a tightly-networked high-risk vertical. That asymmetry is the moat.

This section lives here so future-me doesn't redirect engineering effort into things that look like defense but aren't. Re-read before adding any "anti-piracy" feature.

> **Public-copy voice note (2026-05-09):** the strategic positioning here - "tightly-networked high-risk vertical," processor-terminated merchants as ICP, MATCH-list / personal-guarantee pain points - stays intact in this internal doc, BUT the customer-facing site (front-page.php, page-pricing.php, page-how-it-works.php, homepage-copy.md) deliberately uses softer wording. Reasons: (1) banking compliance reviewers scanning pipepay.app for KYB on the LLC application, (2) plaintiff-attorney exposure, (3) Venmo/Cash App/PayPal/Zelle ToS scrutiny that could endanger the deep-link URL handlers, (4) "borderline black market" brand-association risk. Public copy says "underserved by major processors," "your processor doesn't fit your business," "funds held longer than your cash flow can absorb," "use multiple P2P apps so a single account's weekly receive limit doesn't cap your daily volume." It does NOT say "MATCH list," "high-risk processor/vertical," "MCC," "rolling reserve," "180 days," "single-account exposure," or "Stripe terminated me." Same product, same ICP, different shelf placement. Keep this distinction when authoring new public-facing copy; the strategic vocabulary belongs in private channels (founder DMs, niche forum posts, direct outreach), not on the marketing site.

> **Public-copy voice note (2026-05-09):** the strategic positioning here - "tightly-networked high-risk vertical," processor-terminated merchants as ICP, MATCH-list / personal-guarantee pain points - stays intact in this internal doc, BUT the customer-facing site (front-page.php, page-pricing.php, page-how-it-works.php, homepage-copy.md) deliberately uses softer wording. Reasons: (1) banking compliance reviewers scanning pipepay.app for KYB on the LLC application, (2) plaintiff-attorney exposure, (3) Venmo/Cash App/PayPal/Zelle ToS scrutiny that could endanger the deep-link URL handlers, (4) "borderline black market" brand-association risk. Public copy says "underserved by major processors," "your processor doesn't fit your business," "funds held longer than your cash flow can absorb," "use multiple P2P apps so a single account's weekly receive limit doesn't cap your daily volume." It does NOT say "MATCH list," "high-risk processor/vertical," "MCC," "rolling reserve," "180 days," "single-account exposure," or "Stripe terminated me." Same product, same ICP, different shelf placement. Keep this distinction when authoring new public-facing copy; the strategic vocabulary belongs in private channels (founder DMs, niche forum posts, direct outreach), not on the marketing site.

## What the code can't defend (don't waste time here)

- **Code copying.** GPL by inheritance - WordPress core (GPL-2) + the embedded Kestrel SDK (GPL-3) make the whole derived work GPL. Any paying customer can legally redistribute or modify the code. DRM in the plugin breaks for legitimate customers, gets stripped by anyone determined, and creates a GPL-violation counterclaim risk if we ever try to enforce it.
- **Functional cloning.** Custom WC order status + screenshot upload + AI verification + admin queue + reminders are well-known patterns. AI lowered the build cost from "weeks" to "weekend." Patents/copyrights don't apply to the workflow.
- **AI prompts.** They live in `class-pipepay-vision-client.php` and run with the customer's own API key - already visible to anyone with a paid license. Treat as public.
- **Determined pirates.** Patching out the activation check is trivial for a motivated developer. Fighting that is theater.

## What actually defends the business

These are business moats, not code moats. AI doesn't lower the cost of building any of them.

1. **Trademark "Pipe Pay"** once the LLC is filed. Single biggest legal lever - a competitor can rebuild the workflow but can't call it "Pipe Pay" in SERP / marketplace listings / cold outreach.
2. **Distribution lock-in.** WC.com Marketplace + CodeCanyon + high-risk host partnerships (SiteGround Cloud, Liquid Web). Each has weeks-long approval cycles a clone has to wait through while our reviews accumulate.
3. **Shipping cadence.** Public changelog with regular updates IS a moat. A clone with no movement looks abandoned next to a plugin shipping monthly. Don't let the changelog go quiet.
4. **Cross-merchant pHash fraud detection** (V1.5 in plugin roadmap). The only thing on the table that gets STRONGER as more merchants use it. New entrants start at zero coverage. Highest-leverage thing to ship for long-term defense.
5. **Vertical relationships.** WWP dogfood + private high-risk-merchant channels. Being the name people recommend privately beats any code lock.
6. **Customer count + public reviews.** AI doesn't write reviews. First-mover with 50 paying merchants and 30 public reviews is hard to displace.

## Targeted near-term additions

### Anti-abuse on free trial signup
Today, nothing stops an AI agent (or a determined human) from scripting `/checkout/?add-to-cart=38` and farming unlimited trial license keys to keep an unlicensed install on auto-updates. Cheap fixes, high leverage:
- **Cloudflare Turnstile** (free tier covers our volume) on the trial-tier checkout form
- **Email verification before issue:** the trial license isn't generated by API Manager until the customer clicks a confirmation link. Standard double-opt-in pattern; one mu-plugin or a small WC hook
- **One-trial-per-email guard** server-side: subsequent trial signups by the same email return "you've already used your trial" instead of minting a new key

### Domain telemetry on the resolver endpoint
The resolver mu-plugin already logs IP + key-prefix on every lookup. The plugin's activation call also sends `instance` + `object` (the site domain). Capture and store the activating domain. Free side benefits:
- Dashboard of "where is Pipe Pay actually running" - useful for both abuse detection and outreach
- Outreach list - sites that ran the trial but never converted
- Abuse signal - same key activating across many domains past its tier's seat limit

### Activation badge in gateway settings
Visible "Activated • [Tier name]" pill in the gateway settings header. Doesn't gate anything. Makes the legitimate path feel premium and the unlicensed path feel stale. Two-line CSS + a small PHP tweak in `class-pipepay-gateway.php::admin_options()`.

### Persistent inactive nag (no functional gate)
For installs without an active license: keep the existing `pipepay_license_inactive_admin_notice` (already in `pipepay-licensing.php`). Don't escalate it past a banner. See "License model" below.

## License model - keep the gateway working past expiry

The gateway must NOT stop accepting orders when a license lapses. Why:

- **In-flight orders die mid-checkout** - customer's customer at the moment of expiry sees a broken cart, bounces, blames Pipe Pay
- **Network hiccups become outages** - fail-closed bricks customers when our infra burps; fail-open defeats the enforcement anyway
- **Trust signal collapse** - Pipe Pay customers chose us *because* Stripe terminated them; bricking their store replicates that pain
- **Support volume** - every expiry-related order failure becomes a ticket
- **It's not the standard model** - Yoast Premium, WP Rocket, GravityForms, ACF Pro all gate updates+support on license, never runtime. Customers expect this model

**What lapsed license SHOULD do:**
- Hard-stop auto-updates immediately ✓ already in place
- Hard-stop email/Discord support (operational, you control)
- Persistent admin banner: "Your license expired on [date]. Renew to receive security updates."
- 30-day grace period where the banner is informational only
- After grace: banner intensifies (yellow → red), still informational, gateway still works
- Email reminders at expiry-30 / -7 / 0 / +7 / +30

**Don't build a "stop accepting orders past expiry" toggle either.** Even merchant-opt-in, the customers who turn it on will regret it the first time auto-renewal fails on an expired card - they wake up to a broken store and blame Pipe Pay. The actual lever for renewal rate is the email cadence above and a working one-click renewal URL, not gateway bricking. If a future merchant requests this loudly, point them at the cadence and ask what specific renewal-rate problem they're solving that emails don't already address.

## Anti-patterns - never do these

- ❌ Code obfuscation / PHP minification (anti-GPL, breaks debugging, defeated in minutes)
- ❌ Hardware or browser fingerprinting (invasive; CDN strips most of it anyway)
- ❌ Phone-home-or-die on every page load (breaks customers behind firewalls; one bad incident wipes goodwill)
- ❌ DMCA whack-a-mole on GPL-licensed code (we'd lose; it advertises a GPL violation)
- ❌ Disabling the payment gateway when a license expires (see above)
- ❌ Trying to detect "AI-generated competitors" specifically (you can't, and the right frame is "any competitor")

## Mental model

- The defensible thing isn't the code. It's the business - brand, customer count, marketplace listings, relationships, shipping cadence, and (eventually) the cross-merchant fraud network.
- Every hour spent defending the CODE is an hour not spent building the BUSINESS moats above.
- AI lowered the cost of building a clone. It did NOT lower the cost of acquiring 50 paying merchants in a tightly-networked high-risk vertical. That asymmetry is the moat.

---

# Product & strategy considerations (from external planning review, 2026-05-06)

> The block below was drafted in a separate planning conversation and has not been validated against the current site or plugin state. Treat it as a backlog and decision queue, not as shipped commitments. Items that conflict with what is already live on `pipepay.app` are flagged inline.

# Deferred plugin work (V2 / V3)

These items came out of the 2026-05-07 code review but were intentionally deferred when reviewed honestly against current ICP and demand. Promote when real demand shows up; until then, the work is busywork.

## Full i18n / translation pass - V2 (revisit when non-English customer demand surfaces)

We added `load_plugin_textdomain( 'pipe-pay', ... )` to `pipe-pay.php` on 2026-05-08 and bulk-wrapped 28 REST error-response messages in `pipepay-hooks.php` with `__()`. That's the structural infrastructure. **Skipped:** wrapping the 1470-line `templates/pipe-pay-page.php` (customer payment page) and the ~50 `class-pipepay-gateway.php` settings labels. Reasons:
- All Pipe Pay merchants today read English; the product is fundamentally US-rails (Venmo, Cash App, Zelle are US-only). Merchant-facing translations have near-zero current demand.
- Customer-facing translation (the template) is where i18n would actually move the needle for WWP-style multi-country shops, but wrapping in `__()` without real translations does nothing visible. Real value requires both wrapping AND translators.
- Nobody has asked. Zero customers in the wild yet.

Promote when: WWP cohort (or another store) reports non-English customers confused at checkout, OR any customer asks. At that point: complete the template wrapping + run `wp i18n make-pot` to generate `languages/pipe-pay.pot`, then commission translations for the highest-demand language.

## Heavy gateway-core refactor - V2/V3

The audit recommended splitting `class-pipepay-gateway.php` (1899 lines) into 5+ files: `PipePay_Settings`, `PipePay_Account_Rotation`, `PipePay_REST_Controller`, `PipePay_Admin_Meta_Box`, etc. v1.7.0 did the cheap pieces (extracted inline admin JS to `assets/js/pipepay-admin.js`, extracted reminder-scheduler functions to their own file). The deep gateway-core split is deferred. Reasons:
- High risk: WC payment gateway lifecycle has many subtle hooks; splitting touches everything.
- Low immediate payoff: nothing currently iterating on these files; Pipe Pay Pro is greenfield code in a separate file/class.
- Tests we have cover the pure-PHP units (vision, IP, uninstall) which are already split out.

Promote when: a refactor would actually unblock work - e.g., adding a new payment rail forces touching the gateway in 5 places, or a Pipe Pay Pro design needs to share rotation logic with the core gateway.

---

# Pipe Pay Pro - subscription tier (DEFERRED - not current work; revisit post-Core-launch)

> **Status as of 2026-05-08: DEFERRED.** Not active engineering work. Pipe Pay Core is the bread and butter and the only product currently being built, sold, and supported. Pro is a roadmap item to revisit after Core has paying customers and the open Core to-dos (license-renewal cadence, SMTP relay, real-product screenshots, end-to-end license test for v1.7.0, etc.) are shipped. Do not begin Pro engineering or change the Core zip in service of Pro until this section is explicitly un-deferred.
>
> **Why this is here at all:** keeping the spec, pricing, and architectural decisions in one place so future-me doesn't have to re-derive them when Pro is promoted off the backlog. Treat the rest of this section as a frozen snapshot from 2026-05-07, not a build plan. The "Data invariant" subsection below is the one piece that applies to BOTH Core and Pro and is NOT deferred - it's a present-tense rule.
>
> **When to un-defer:** any of (a) Core has 50+ paying merchants and renewal-rate data justifies a higher tier, (b) a specific Core customer requests subscription billing and is willing to pre-commit, (c) Hunter or another stakeholder explicitly prioritizes the work over current Core gaps. Until then: ignore.

> Strategic shift (deferred snapshot): **Pipe Pay Pro** is a separate paid tier above Pipe Pay core. NOT an add-on. Customers pick core OR Pro at signup. Adds native recurring P2P subscriptions, prepaid balance ledger, and smart refunds. Full engineering spec lives in the **plugin repo** at [`pipe-pay-extracted/specs/pipe-pay-pro-v1.md`](../pipe-pay-extracted/specs/pipe-pay-pro-v1.md) (sibling of the plugin source dir, so it doesn't ship inside the customer-download zip).

## Pricing changes that fall out of this

### Core (revised) - **LIVE on the site as of 2026-05-07**
| Tier | Old | New |
|---|---|---|
| Single Site | $249/yr | **$299/yr** |
| 5 Sites | $499/yr | **$599/yr** |
| Unlimited | $999/yr | **$1,199/yr** |

Hunter's recommendation. $249 was anchored to the B2C "just below $250" psych threshold which doesn't apply to merchants evaluating on ROI math. $299 reads as "real software" rather than "side project" without changing buyer consideration meaningfully. Shipped: WC products 34/35/36 `_regular_price`/`_price` updated, hardcoded prices in `front-page.php` and `page-pricing.php` bumped, persona triptych ICP3 body recomputed (breakeven $8,500 → $10,300 in card volume).

### Pro (new tier) - NOT launchable until plugin is built (~6 weeks of engineering)
| Tier | Price |
|---|---|
| Single Site | $699/yr |
| 5 Sites | $1,399/yr |
| Unlimited | $2,899/yr |

Pro is ~2.3x core. Replaces WooCommerce Subscriptions ($279/yr) plus eliminates integration time, dual-system maintenance, and dual-vendor support burden. At 50+ active subscribers, Pro pays for itself in the first month.

## Architectural principle

We are NOT rebuilding WooCommerce Subscriptions or a card-rail recurring billing engine. We are building a **recurrence schedule + balance ledger + customer-initiated payment flow** for P2P rails. Closer to "gift card with a calendar trigger" than Stripe Billing. The hard parts of card recurring billing (stored tokens, decline retries, dunning, complex proration) don't apply to P2P rails.

### Data invariant: pipepay.app stores ZERO customer payment screenshots - Core and Pro

**The rule, stated precisely:** pipepay.app NEVER stores screenshot bytes. Pipepay.app MAY (V1.5/V2, by deliberate roadmap decision) store a perceptual hash (pHash, ~32 bytes per image) for cross-merchant scam cross-reference. **Hash ≠ screenshot.** A pHash is a one-way fingerprint - you cannot reconstruct an image from it, you cannot extract a phone number, handle, amount, or face from it. It exists only to answer the question "has this exact-or-near-duplicate image been used somewhere else in the network?"

| Data | Stored on merchant's WP install | Stored on pipepay.app |
|---|---|---|
| Screenshot bytes (the image file) | **Yes** - `wp-content/uploads/`, deleted per `proof_retention_days` (default 90) | **Never** |
| AI extraction output (amount, recipient, timestamp) | Yes - order meta | Never |
| Order ID, license key, customer email | Yes | License key + activation only (Kestrel) |
| Perceptual hash (pHash) for fraud cross-reference | n/a | **Maybe** - V1.5/V2 only, no bytes, no merchant attribution, no PII |

Applies to Pipe Pay Core today and to Pipe Pay Pro by design. Screenshots are uploaded to, stored in, processed by (BYOK AI), and deleted by **the merchant's own WordPress install** - full stop. Nothing routes the bytes to pipepay.app: not the license-resolver mu-plugin, not the Kestrel update server, not Pro's subscription/balance/refund endpoints. Pro's DB tables (`subscriptions`, `balances`, `balance_transactions`, `refunds`, etc.) reference order IDs and balance txn metadata only - no image data, no image paths, and no per-merchant pHashes either. The Refund Type B "outbound proof" upload (`sent_screenshot_id` on the `refunds` table) is also a merchant-side audit artifact, not vendor telemetry.

Why this invariant is load-bearing (do not relax it):

- **Privacy posture.** Screenshots contain phone numbers, payment-app handles, sometimes profile photos. The vendor never seeing the bytes removes pipepay.app from the merchant's data-breach blast radius and from any "are you a money transmitter / data processor" framing.
- **BYOK isolation.** AI verification runs on the merchant's own OpenAI/Anthropic key. Centralizing screenshots would collapse the cost-shifting story AND the per-merchant fraud-isolation story.
- **Storage cost / scaling.** Image storage at vendor scale (1,000 merchants × 100 orders/mo × 500KB) is multi-GB/mo we don't carry today and won't. pHashes at the same scale are KB/mo.
- **Legal posture.** The vendor not holding payment-related images materially simplifies the FinCEN / state-MSB / data-processor questions in the ToS work (see pre-launch must-haves above).

**Confusable thing this rule does NOT prohibit (so we don't accidentally re-litigate it later):** sending a 32-byte pHash to pipepay.app for cross-merchant duplicate-screenshot detection is **on the roadmap** under `## Fraud detection` (V1.5/V2). That's been discussed and is allowed - because it isn't the screenshot. The rule prohibits the bytes, not the fingerprint.

**This invariant cannot change inside Pipe Pay Pro V1.** Anything that would require centralizing screenshot bytes (cross-merchant fraud beyond pHash, merchant-of-record audit, a "managed AI tier") is a V2+ architecture-review item, not a V1 build-order shortcut.

## V1 scope (one-paragraph summary, full detail in spec)

Two subscription approaches: (1) reminder-driven manual recurring (reuses existing one-off flow); (2) prepaid balance ledger with auto-deduct. Six first-class subscription states with a real Pause that defers (not skips) the next billing date - critical for protocol-driven verticals like peptides where pause is more common than cancel. Three refund types: A (instant balance credit, default), B (manual external send via P2P app, queued), C (full prepay refund). Customer portal + merchant admin views + email notifications + per-merchant chargeback fraud signal feed. **Anything not in the spec's V1 list is V2.** Default response to scope creep: "V2."

## Build order (V1) - DO NOT INVERT

1. Subscription data model + state machine
2. WooCommerce subscription product type integration
3. Approach 1: reminder-driven recurring
4. Refund Type B/C
5. Approach 2: prepaid balance ledger
6. Refund Type A (depends on balance ledger)
7. Subscription states (full - pause, suspend)
8. Customer portal
9. Merchant admin views
10. Email notifications
11. Chargeback fraud signal feed

## Marketing-site work that falls out of this

**Shipped 2026-05-07:**
- [x] WC products 34/35/36 `_regular_price`/`_price` flipped to 299/599/1199
- [x] Pricing card display in `front-page.php` and `page-pricing.php` updated
- [x] Persona triptych ICP3 body: "$249 a year, flat" → "$299 a year, flat"; breakeven recomputed to $10,300

**Still pending (depend on Pro launch):**
- [ ] When Pro is buyable: add three new WC products at $699/$1,399/$2,899 with the same `_is_api`/`_api_resource_type` meta pattern as the core tiers (do NOT reuse 34/35/36 - those stay as core)
- [ ] Two-tier pricing page presentation (core + Pro side-by-side) with the comparison table from the spec
- [ ] Upgrade path UX (core → Pro prorated) - handled by the SaaS billing system, no custom code

**Decision still open:**
- [ ] "Coming soon: Pipe Pay Pro" teaser on `/pricing`. Risk: telegraphs roadmap to competitors and signals "wait to buy until Pro ships." Recommendation: skip until Pro is shippable.

---

## Decisions that conflict with current state (need a user call)

- ~~**Domain: `pipepay.app` vs `pipepay.money`.**~~ **Resolved 2026-05-06:** migrated to `pipepay.app`. `pipepay.money` zone + nginx server block kept in place to permanent-301 inbound links; do not delete.
- **SaaS billing rails for our own subscription.** External review flags the long-term risk that Stripe (or any mainstream processor we use to collect Pipe Pay subscription dollars) will eventually classify Pipe Pay itself as "facilitating high-risk activity" and shut us down. Recommended merchant-of-record alternatives: Paddle, Lemon Squeezy, FastSpring. **Current state: Pipe Pay is dogfooded as the gateway - no Stripe in the loop at all,** so this risk does not apply today. Revisit before introducing any Stripe-based billing path (e.g. a future managed-key tier that auto-bills).
- **Pipe Pay gateway has a single `ai_provider` setting.** External review wants merchants to choose between OpenAI / Anthropic / Gemini at minimum. Plugin-side change, not site-side.

## Pre-launch must-haves (in addition to the to-dos already listed above)

- [ ] **Refund / dispute flow.** A "mark as refunded" state inside WC that closes the loop, plus user-facing copy on the order detail + a docs page explaining that merchants must issue refunds manually inside Cash App / Zelle / Venmo / PayPal / Chime - Pipe Pay cannot push refunds back through P2P rails. Expected to be the single biggest source of confused support tickets.
- [ ] **Terms of Service drafted by a fintech lawyer** (budget $1,500-$3,000). Three required functions: (a) frame Pipe Pay as a verification tool, NOT a payment processor or money transmitter (FinCEN / state MSB licensing exposure), (b) put final approval responsibility on the merchant, (c) prohibit merchants in actually-illegal verticals so we have grounds to terminate.
- [ ] **Privacy + data retention policy.** Screenshots contain phone numbers, payment handles, amounts, sometimes profile photos. Lean short: 90 days for verified screenshots, then delete or keep only a perceptual hash. Already aligned with the gateway's `proof_retention_days = 90` setting - make sure the policy page matches.
- [ ] **Manual review fallback mode** - if AI verification is unavailable (provider outage, expired key, rate-limit hit), screenshots must still flow through the approval queue marked "manual review - AI unavailable." Orders should never be auto-rejected due to infrastructure failure. Verify the Pipe Pay plugin handles this gracefully before single-rail launches (especially WWP).

## Pipe Pay product roadmap (plugin-level, not site)

V1 / pre-scale:
1. **60-second onboarding video** walking through OpenAI / Anthropic account creation, payment-method setup, API-key generation, and pasting into Pipe Pay. Screenshots for every step as a fallback.
2. **Concierge setup** as an Unlimited-tier perk - we set up the API key for them.
3. **Per-error-source messaging.** Distinguish API-key-side failures (expired card, usage cap, invalid key) from Pipe Pay platform failures. Each error type gets its own user-facing message with the fix.
4. **AI cost transparency on the pricing page.** Show estimated cost per 100 orders so merchants aren't surprised by their first OpenAI / Anthropic bill.
5. **Multi-provider support** - OpenAI, Anthropic, Gemini at minimum (gateway change).
6. **PayPal F&F vs G&S detection.** Admin-configurable G&S behavior, two options: auto-reject, or flag-and-warn for manual review. Default to flag-and-warn (avoids false-rejecting customers who tapped the wrong button but intended F&F). Marketing angle: "Pipe Pay protects your PayPal account by flagging G&S payments."
7. **Chime support** - recognize both Chime-to-Chime UI and Zelle-via-Chime flows.
8. **Manual-review queue as a universal safety valve** - any P2P app that produces a screenshot flows through the queue without AI verification, explicitly marked "manual review." Lets marketing say "supports any P2P app" without committing engineering to AI accuracy on every layout.
9. **Status page** for Pipe Pay infrastructure so merchants (especially WWP under single-rail) can distinguish "AI down" from "site broken."

V2 / deferred:
- Apple Cash (iPhone-only, screenshot lives inside Messages, high visual variation, low ROI vs eng cost)
- Crypto wallet screenshots → ship as a paid add-on ("Pipe Pay Crypto," $99-199/yr on top of base) once V1 has 50-100 paying customers and we know which chains/tokens to prioritize
- Detect when the PayPal sender is a **business account** (these F&F payments often get auto-reversed by PayPal)
- **Managed-key tier** for non-technical merchants who bounce on BYOK setup. Mark up AI costs, capture the segment that would otherwise churn at signup.

## Fraud detection (V1 design)

**Hard rule: pipepay.app stores zero customer screenshots.** Screenshots live in the merchant's own WordPress (`wp-content/uploads/`), processed by the merchant's own AI key, deleted on the merchant's own retention schedule. See the data invariant under `## Architectural principle` in the Pipe Pay Pro section above - same rule applies to Core today.

BYOK + per-merchant screenshot isolation means **centralized screenshot storage for cross-merchant duplicate detection breaks the privacy posture.** V1 sticks to intra-screenshot signals only, all enforced inside the AI prompt with structured JSON output (per-check pass/fail + confidence score):

1. **Timestamp recency** - reject or flag if transaction time is > 30 minutes old, or in the future.
2. **Recipient handle exact match** against the merchant's configured handle for that rail.
3. **Amount edit detection** - anti-aliasing inconsistencies, font mismatches, pixel-level smudging around the amount field.
4. **Font + layout consistency** across the whole screenshot (photoshopped amounts often have subtle differences).
5. **Pending vs completed status** - reject "pending" or "cancelled"; only completed/sent transactions are valid.

V1.5 / V2 - **perceptual-hash-only cross-merchant detection.** Store *only* a pHash of each verified screenshot in a central Pipe Pay DB (no bytes, no merchant attribution, no PII). Hash collision = "this screenshot may have been used elsewhere - please verify manually." Preserves merchant data isolation while catching the dominant fraud pattern (fraudsters spraying the same fake screenshot across multiple stores). Not a launch blocker, but without some form of cross-merchant signal the fraud-detection ceiling is low.

## WWP (peptide store) single-rail dogfood

External review endorses launching WWP with Pipe Pay as the **only** payment option. Required guardrails before going single-rail:

1. **Manual review fallback** (above) - orders never auto-rejected on infra failure.
2. **Conversion monitoring** for 30-60 days vs prior baseline. Within ~15% of baseline → keep the play. > 15% drop → add a backup option (likely BTCPay Server for crypto; brand-aligned, no third-party processor).
3. **Public status page** for Pipe Pay so WWP customers can see a known issue rather than assuming the site is broken.

Why this is defensible specifically for WWP (and not for a mainstream e-commerce store): peptide buyers are already conditioned to P2P payment flows, and many actively prefer not putting peptide purchases on a credit card. The conversion penalty that would kill a mainstream store is much smaller in this vertical.

## Marketing / SEO backlog

- **Headline + subhead alternatives.** Five options drafted for post-launch A/B testing: pain-led ("Stop manually matching Cash App screenshots to WooCommerce orders"), category-led ("Accept Cash App, Zelle, Venmo, and PayPal on your WooCommerce store"), outcome-led ("Your WooCommerce checkout for when Stripe says no"), direct/blunt ("P2P payments on WooCommerce, finally automated"), and time-saving ("Turn 12 hours a week of payment matching into 12 minutes"). Current hero copy on `front-page.php` predates these - keep current copy live; test alternatives once there is real traffic.
- **"What this is" disambiguation paragraph** - required to prevent confusion with consumer P2P apps. One paragraph anchored under the hero, before features or pricing: "Pipe Pay sits inside your WooCommerce checkout. When a customer pays you via Cash App, Zelle, Venmo, PayPal, Chime, or another P2P app, they upload a screenshot of the payment. Pipe Pay's AI verifies the screenshot matches the order - correct amount, correct recipient handle, no signs of tampering - then queues it for your approval. One click releases the order."
- **Naming + terminology guidance:** keep "P2P" as a supporting term, never the headline category (carries Venmo/Zelle consumer-app baggage). Always anchor "P2P" to "WooCommerce store" or "your store" in the same sentence. Name the actual apps as often as possible - app names convert better than abstract categories. Avoid: "peer-to-peer payment platform," "send and receive money." Use: "P2P checkout for WooCommerce," "customer-to-merchant P2P verification."
- **SEO keyword priorities** (long-tail, high-intent, instead of fighting for "P2P payments"): `accept Cash App on WooCommerce`, `accept Zelle WooCommerce plugin`, `WooCommerce Venmo payment`, `WooCommerce PayPal Friends and Family`, `accept PayPal F&F WooCommerce`, `Stripe alternative high-risk WooCommerce`, `WooCommerce manual payment verification`, `accept P2P payments WooCommerce store`, `Cash App for online business WooCommerce`.
- **Priority blog post: "What to do when your Stripe account gets flagged."** Target reader: merchant who just got the Stripe email, panicked, searching, ready to buy. Outline: why Stripe flags accounts, what "flagged" actually means (review hold vs funds freeze vs termination), 24-hour checklist (download history, notify customers, pause orders), options ranked by effort (appeal / high-risk processor / P2P + verification / crypto), why P2P + verification works specifically for high-risk WC, soft CTA. Internal links to plan: pricing, AI cost estimator, demo video, comparison page.
- **Sibling post: "What to do when your PayPal account gets frozen."** Same structure, different audience moment. PayPal F&F merchants will eventually have an account die - capture that search intent.
- **Comparison page** "Pipe Pay vs high-risk processors" (Authorize.net, NMI, Easy Pay Direct, etc.). $249/yr + 0% per transaction looks incredible against 4-8% per transaction.

## Pre-50-customer decisions (don't block launch, decide before scaling)

- **Merchant verification at signup.** Fully open vs light verification ("must have a published WC store with at least one product"). Filters out bad actors, adds signup friction.
- **"Site" definition for pricing tiers.** Multi-store WC networks count as one or many? Staging environments? Two completely separate stores in different verticals? Define on the pricing page to head off month-two refund disputes.
- **Pricing test: $29/month tier** converting to annual after 3 months. May lift conversion among panicked just-banned merchants who don't have $249 cash on hand. A/B test post-launch.

## Referral / affiliate program

**V1 decision: no formal program at launch.** 15% commission + 10% discount = ~24% revenue haircut per referred customer ($60 lost on the $249 tier, $240 lost on the $999 tier). Too steep while bootstrapping AI / infra / legal costs out of revenue. Acquisition strategy doesn't depend on it (WWP anchor, Hunter's network, direct outreach, "Stripe banned my account" SEO). Easy to launch later, hard to remove once active.

Compensating mechanisms (cheap, no structural commitment):
- Ad-hoc thank-you gestures when a customer mentions referring someone (free month, tier upgrade for a quarter, $50 gift card).
- "How did you hear about us?" onboarding question. Names that come up repeatedly = personal thank-you email + first-invite list when a formal program eventually launches.

Reconsider when growth stalls 30+ days without organic referral momentum, or 6 months post-launch (whichever comes first).

If/when launched (parked spec): 15% recurring lifetime, tier bump to 20% at 10+ active referrals, 180-day cookie, monthly payout, $50 minimum threshold, existing customers grandfather in immediately as charter affiliates.

## Domain + email setup (if migrating to PipePay.app)

User-facing branding stays unified (`@pipepay.app`); sending splits across subdomains so each has its own deliverability reputation pool (the Stripe pattern):

- `you@pipepay.app` - human business / customer replies / partner conversations
- `support@pipepay.app` - support inbox
- `noreply@notifications.pipepay.app` - transactional (signup confirmations, billing receipts, license-key emails, AI verification result notifications). Subdomain isolates transactional sending reputation from human email.
- `outreach@send.pipepay.app` - volume cold outreach. Subdomain isolates reputation so deliverability issues don't poison the main domain. Not needed at launch (direct outreach is 1-to-1 to ~10-50 merchants/week); set up before any volume campaign.

Required DNS for any sending domain: **SPF + DKIM + DMARC.** Without all three, even `.com` lands in spam; with them, `.app` delivers indistinguishably from `.com` in practice.

Transactional service: Resend or Postmark (NOT the Workspace inbox). Both walk through SPF/DKIM/DMARC and have free/cheap early tiers. This dovetails with the existing SMTP relay to-do above.

Human inbox: Google Workspace ($6/user/mo) or Fastmail ($5/user/mo). Workspace integrates better if Hunter or others need access later; Fastmail is cheaper and more privacy-respecting.

Sender names: configure as "Pipe Pay Support," "Pipe Pay Notifications" - not raw addresses. Reduces the half-second "is this real?" pause on a non-`.com` TLD; disappears entirely after first interaction.

## Post-launch roadmap (not blocking)

- Comparison page (above)
- Merchant analytics dashboard: approval rate, denial rate, time-to-approval, dollar volume processed. Plan the data model now to avoid refactoring later.
- WooCommerce.com marketplace + CodeCanyon listings - long approval cycles, audience is mostly mainstream, but provides credibility signals when high-risk merchants evaluate Pipe Pay.
- High-risk hosting partnerships (SiteGround Cloud, Liquid Web, specialty high-risk WC hosts) around month 3.
- Pipe Pay Crypto paid add-on (above) once V1 has 50-100 paying customers and clear chain/token demand signal.
- Managed-key tier (above).
