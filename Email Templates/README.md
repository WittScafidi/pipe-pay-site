# Email Templates

Source of truth for all Pipe Pay transactional email bodies. Lives at
`pipe-pay-site/Email Templates/` in the repo and ships to
`/var/www/pipepay/wp-content/email-templates/` on the server.

The folder name has a space — quote it in shell commands.

## Templates

### Wired (firing today)

| Template | Trigger | Wrapper |
|---|---|---|
| `free-trial.php` | `customer_completed_order` for trial product (#38) | `pipepay-child/woocommerce/emails/customer-completed-order.php` (trial branch) |
| `paid-completed.php` | `customer_completed_order` for paid tiers (#34, #35, #36) | same wrapper, paid-tier branch via `pp_order_is_paid_tier()` |
| `new-account.php` | `customer_new_account` (fires when WC auto-creates account at checkout) | `pipepay-child/woocommerce/emails/customer-new-account.php` |

### Ready, but trigger TBD

> **Removed 2026-06-05:** `payment-pending.php` (the "awaiting-proof" order email) was dropped so the dogfood matches the customer plugin exactly — both now rely solely on the plugin's own 5/20/45-min reminder cadence (`pipepay_handle_reminder`). Don't re-add an order-placement email **here** (site-only) — it would diverge the dogfood from real customers again.
>
> **If you ever want this email back — do it as "Option A" (in the PLUGIN, not the site):**
> Add a customer-facing `WC_Email` subclass to the plugin so **every** customer install sends it (and the dogfood inherits it — no site copy needed):
> 1. New class `pipe-pay/includes/emails/class-wc-email-pipepay-payment-pending.php` + HTML/plain templates under `pipe-pay/templates/emails/`, mirroring the existing `class-wc-email-pipepay-review-pending.php`.
> 2. Trigger on the awaiting-proof transition (from `process_payment`, or `add_action('woocommerce_order_status_awaiting-proof', …)`), gated by `is_enabled()`.
> 3. Body: order #, amount due, "include #&lt;order&gt; in your note", and an **Upload payment screenshot** button → `pipepay_get_pay_url($order)` (the guest/key-authed `/pipe-pay/?order_id=&key=` page). **Do NOT list payment methods** — the `/pipe-pay/` page is the single source of truth; an emailed method list drifts out of sync with the gateway settings (the exact bug we hit and removed).
> 4. Customer-visible feature → minor version bump (e.g. 1.9.x → **1.10.0**), full TDD + new zip.
> This deleted template (`payment-pending.php`, in git history before 2026-06-05) is a good body reference to copy from.

| Template | Trigger needed | Notes |
|---|---|---|
| `trial-ending-soon.php` | Daily cron @ T-2 (trial) | Cron handler in `pipepay-license-renewals.php` mu-plugin still TODO |
| `trial-ended.php` | Daily cron @ T+0 (trial) | same |
| `renewal-30.php` | Daily cron @ T-30 (paid 34/35/36) | same |
| `renewal-7.php` | Daily cron @ T-7 (paid) | same |
| `renewal-expiry.php` | Daily cron @ T-0 (paid) | same |
| `renewal-grace.php` | Daily cron @ T+7 (paid) | same |
| `renewal-final.php` | Daily cron @ T+30 (paid) | same |

The cadence cron handler is documented in CLAUDE.md as "License renewal email cadence + one-click renewal flow." The renewal mu-plugin already handles the `/renew/?key=...&token=...` landing URL — what's missing is the daily Action Scheduler cron that queries `wp_wc_am_api_resource` and triggers these emails.

## Partials

| File | Purpose |
|---|---|
| `partials/helpers.php` | Shared HTML helpers — `pp_email_license_card()`, `pp_email_button()`, `pp_email_paragraph()`, `pp_email_signoff()`, `pp_email_greeting()`. Each template requires this. |

## Assets

| File | Purpose |
|---|---|
| `assets/pipe-pay-email-banner.png` | Old blue-banner logo (no longer used in production; archived) |
| `assets/pipe-pay-logo-blue.png` | 3x retina blue-on-transparent logo (no longer used — replaced by text wordmark) |
| `assets/pipe-pay-logo-white.png` | Dark-mode variant (same status) |
| `assets/pipe-pay-logo-blue.svg` | Vector blue variant (archived) |
| `assets/pipe-pay-logo-white.svg` | Vector white variant (archived) |

**Current header treatment**: text wordmark "Pipe Pay" in royal blue Manrope, rendered inline in `pipepay-child/woocommerce/emails/email-header.php`. No image. The asset files above are kept for future use if we revisit a logo image approach.

## Template scope contract

Every `.php` template in this folder expects standard WC email scope:

| Variable | Type | Required for |
|---|---|---|
| `$email` | `WC_Email` | All templates |
| `$email_heading` | string | All templates |
| `$plain_text` | bool | All templates |
| `$additional_content` | string | All templates |
| `$sent_to_admin` | bool | Order-based templates |
| `$order` | `WC_Order` | `free-trial.php`, `paid-completed.php` |
| `$user_login`, `$user_pass`, `$set_password_url`, `$blogname` | strings | `new-account.php` |
| `$first_name`, `$tier_name`, `$expires_label`, `$renewal_url` | strings | Renewal cadence templates |

Templates branch internally on `$plain_text` to render either plain-text or HTML output. HTML branch always calls `do_action( 'woocommerce_email_header', $email_heading, $email )` and `do_action( 'woocommerce_email_footer', $email )` to inherit WC's chrome.

## Deployment

The sync command in CLAUDE.md ships this folder alongside the theme:

```bash
# from pipe-pay-site/
cd "/Users/wittscafidi/Desktop/Pipe Pay/pipe-pay-site"
tar czf /tmp/email-templates.tgz --exclude='._*' -C . "Email Templates"
scp /tmp/email-templates.tgz witt-scafidi@100.102.251.125:/tmp/
ssh witt-scafidi@100.102.251.125 '
  sudo rm -rf /var/www/pipepay/wp-content/email-templates &&
  sudo mkdir -p /var/www/pipepay/wp-content/email-templates &&
  sudo tar xzf /tmp/email-templates.tgz -C /tmp/ &&
  sudo cp -r "/tmp/Email Templates/." /var/www/pipepay/wp-content/email-templates/ &&
  sudo chown -R www-data:www-data /var/www/pipepay/wp-content/email-templates &&
  sudo systemctl reload php8.3-fpm
'
```

## Adding a new template

1. Create `<name>.php` in this folder. Follow the contract above.
2. Sync via the command above.
3. Wire it: either override a WC default template in `pipepay-child/woocommerce/emails/`, or register a custom email class via the `woocommerce_email_classes` filter, or trigger via cron from a mu-plugin.

## Why a separate folder

- Single source of truth — no duplicate templates in the theme vs the repo
- Survives theme swaps (lives in `wp-content/`, not `wp-content/themes/`)
- Editable without diving into WC's template-override path conventions
- Clean separation: WC core handles wrapping (header + footer + email-styles inlining), this folder handles body content
