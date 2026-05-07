# Pipe Pay Homepage Copy (v1)

Draft homepage copy following the structure in `website-copy-brief.md`. Tone: pragmatic, no hype, no em dashes (only commas, parentheses, colons, and hyphens). All trial buttons link to `https://pipepay.app/checkout`.

---

## 1. Hero

**Headline:**
Accept Venmo, Cash App, PayPal, and Zelle in WooCommerce, without manual reconciliation.

**Subheadline:**
A WooCommerce plugin that captures customer P2P payment screenshots and verifies them with AI, so the only orders you touch are the ones the AI actually flagged.

**Primary CTA (royal blue button):** Start 7-day free trial →
**Secondary CTA (text link):** How it works ↓

**Visual:** Screenshot of the customer-facing payment page (QR code + handle + step-by-step + sticky upload bar).

**Trust strip (small inline row beneath hero):**
7-day free trial  ·  No card required to start  ·  WooCommerce 8.0+  ·  HPOS compatible

---

## 2. What it is

**Headline:**
A workflow layer, not a payment processor.

**Body (~80 words):**
Pipe Pay is a WooCommerce plugin that lets your store accept Venmo, Cash App, PayPal, and Zelle by capturing the customer's payment screenshot and verifying it with AI. Your customers pay you directly through the P2P apps they already use; you stop doing 30 minutes of manual ledger reconciliation a day. It is built for WooCommerce merchants in restricted verticals who can't use standard card processors, and for anyone who'd rather not pay 2.9% plus 30 cents on every order.

---

## 3. The problem

**Headline:**
Manual reconciliation breaks at 30 orders a day.

**Body (4 short paragraphs):**

You already accept Venmo, Cash App, PayPal, or Zelle, because card processors won't onboard you, or because the fees are eating margin you can't spare. The hard part isn't the payment. The hard part is everything that happens after.

Customers pay through their P2P app and then either email you a screenshot, send the wrong amount, send the right amount with no order tag, or quietly never pay at all and assume you'll ship anyway. You cross-reference your P2P transaction history against your WooCommerce orders by hand. At 5 orders a day this is annoying. At 30 a day it's a job.

Ghost orders sit in your queue forever. Untagged payments sit in your P2P account forever. You spot a discrepancy and lose 20 minutes chasing it. You ship something that was never paid. You fail to ship something that was.

Pipe Pay automates the workflow without changing how your customers pay.

---

## 4. How it works

**Headline:**
Four steps from cart to confirmed order.

**Subheadline:**
The customer experience is a guided checkout. Yours is a Proofs queue that only shows the orders the AI couldn't auto-approve.

**4-step grid (each card: bold one-line headline + 2-3 sentence body):**

**1. Customer chooses Pipe Pay at checkout.**
The plugin places the order in a custom "Awaiting Proof" status. No order confirmation email yet, the confirmation is the customer's incentive to upload.

**2. Customer pays via their P2P app.**
The payment page shows a QR code (desktop), the merchant handle, and an "Open Venmo / Cash App / PayPal / Zelle" button that takes the customer straight to the right account.

**3. Customer uploads a screenshot.**
A sticky bar at the bottom of the page stays visible until the upload completes. If the customer tries to close the tab before uploading, the browser warns them.

**4. AI verifies and the order processes.**
High-confidence verifications auto-transition to Processing and trigger your normal order confirmation emails. Medium and low confidence verifications go to On Hold and surface in the admin Proofs review queue.

**Bonus visual under the grid:** wide screenshot of the full customer flow.

---

## 5. The story

**Headline:**
Built by someone who needed it.

**Body (first person, ~180 words):**

I run businesses in restricted verticals. Every traditional payment processor I tried either refused to onboard me, terminated me after a few months without explanation, or held my funds while my customers waited.

I shifted to accepting Venmo, Cash App, PayPal, and Zelle directly. That solved the access problem and immediately created a new one: reconciling 50+ payments a day by hand against my WooCommerce orders. I tried other plugins. None of them did the workflow correctly, and most of them assumed you were a low-risk Stripe-compatible merchant who'd just landed on the wrong page.

I built Pipe Pay because I needed it. The AI verification piece exists because manual review at scale is a tax on growth. The security hardening exists because storing customer payment screenshots casually is unacceptable. The honest-about-its-limits framing exists because I've been burned by overpromising plugins, and I assume you have too.

If you're in a similar position, this is the tool I wish I'd had two years ago.

---

## 6. Features

**Headline:**
What you get.

**8-card grid (4 columns desktop, 2 tablet, 1 mobile). Each card: icon, short headline, 2-3 sentence body.**

**1. AI verification, your choice of provider.**
Plug in Claude, OpenAI, OpenRouter, or any OpenAI-compatible custom endpoint. Pipe Pay never sees the screenshots; they go straight from your store to the provider you configured.

**2. Multiple accounts per method, with rotation.**
Add up to three handles per payment method and rotate between them (LRU or round-robin). Useful when one account hits P2P throughput limits during a launch.

**3. Configurable auto-approval cap.**
Set a dollar threshold for auto-approval (for example, only auto-approve orders up to $500). Anything above that always lands in your manual review queue.

**4. Test AI Connection button.**
Verify your provider key without placing a real order. One click, real round-trip, plain pass/fail.

**5. Per-order reminders + 60-minute auto-cancel.**
Three escalating reminder emails fire at 5, 20, and 45 minutes after checkout. If the customer hasn't uploaded by 60 minutes, the order auto-cancels and stock is restored.

**6. Admin Proofs review queue.**
Pending and History tabs. Approve, reject, or re-run AI analysis on demand. Built for triage at volume, not one order at a time.

**7. Mobile-aware UI.**
QR codes hide on phone-sized screens (since the customer is already on the device). HEIC iPhone screenshots auto-convert server-side via Imagick, with a graceful fallback.

**8. WordPress 6.0+, WooCommerce 8.0+.**
HPOS compatible. Works with both classic shortcode checkout and the block checkout. Standalone plugin, not theme-dependent.

---

## 7. AI verification deep-dive

**Headline:**
AI verifies each payment. You only see what needs your attention.

**Body (~200 words):**

The AI checks the things you'd check by hand: that the amount matches the order total, that the recipient handle matches the one you configured, and that the screenshot doesn't show signs of editing. It also flags app/method mismatches (for example, a Cash App screenshot uploaded against a Venmo order) and implausible amounts.

Confidence is graded as high, medium, or low. High-confidence verifications auto-approve and the order moves straight to Processing. Medium and low confidence land in the Proofs queue for manual review. If any fraud signal trips during analysis, confidence is capped at "medium" no matter how clean the rest of the screenshot looks.

You stay in control of the threshold. Set a configurable auto-approval cap so that orders above a certain amount always get your eyes on them. Use the Test AI Connection button to confirm the provider integration works before you go live.

We don't lock you into one AI vendor. Pipe Pay supports Claude (Anthropic), OpenAI, OpenRouter, and any OpenAI-compatible custom endpoint. Bring your own key, swap providers when pricing or quality changes, or run against a self-hosted model.

**Visual:** screenshot of the admin Proofs review queue showing high / medium / low confidence badges.

---

## 8. Security and data handling

**Headline:**
Customer screenshots stay private.

**Body (~180 words):**

Payment proofs are stored outside the web-accessible directory, with a triple denial-file layer on every storage root. There is no public URL for any screenshot, ever. Viewing a proof in the admin goes through an authenticated proxy endpoint gated by the `manage_woocommerce` capability.

Every uploaded file gets a 32-character hex random filename (128 bits of entropy), so screenshots can't be guessed or enumerated. A `wp-config.php` constant lets you store proofs on a separate volume if you want them off the main webroot disk entirely.

Auto-expiration is configurable. The default retention is 90 days; you can set it anywhere from 0 to 10 years. Per-IP abuse rate limiting, per-customer success rate limiting, and a per-order lifetime upload cap are all on by default.

**On AI provider data handling:** screenshots are sent to whichever AI provider you configure (Claude, OpenAI, OpenRouter, or a custom endpoint). Pipe Pay does not see, store, or have access to those screenshots in transit. If you handle particularly sensitive data, review your chosen provider's data retention policy, or point Pipe Pay at a self-hosted or zero-retention OpenAI-compatible endpoint.

---

## 9. Onboarding

**Headline:**
Live in about 10 minutes.

**Body (~70 words):**
Setup is short. Enter your license key. Add the handles for whichever P2P methods you want to accept. Optionally upload your QR codes. Paste in an AI provider API key, or skip it and review uploads manually. Then place a test order on your own store to confirm the full flow. If you can configure a Stripe gateway, you can configure Pipe Pay in less time.

---

## 10. Compatibility

**Headline:**
Works with your stack.

**Body (compact icon row or table):**

- WordPress 6.0 or higher
- WooCommerce 8.0 or higher
- PHP 7.4 or higher
- HPOS (High-Performance Order Storage) compatible
- Classic shortcode checkout and block checkout both supported
- HEIC support for iPhone screenshots (auto-converts via Imagick when available)
- Multisite-friendly
- Standalone plugin, no theme dependency

---

## 11. Testimonials (placeholder)

**Headline:**
From merchants running Pipe Pay today.

**3-card grid (placeholder copy, will be replaced before launch):**

> "I was manually checking 60 Venmo payments a day against my orders. Pipe Pay cut that to maybe five flagged ones I actually need to look at. The other 55 just process themselves."
> Marcus R. - NorthRange Supply - *Peptide retailer*

> "Stripe shut us down twice in a year. Switched to Pipe Pay six months ago and we haven't had a single payment-related issue since. The AI verification is genuinely good."
> Priya S. - CleanStack Labs - *Supplement brand*

> "I'm not in a restricted vertical, I just hate paying 2.9% on every order. Pipe Pay paid for itself in the first month and now I'm running about 80% of my volume through it."
> Dan W. - Mountain Forge Goods - *Outdoor gear*

---

## 12. What Pipe Pay isn't

**Headline:**
What Pipe Pay isn't.

**Subheadline:**
We'd rather you have an honest picture than a sale.

**7-bullet list:**

- **Not a card processor.** No Visa, Mastercard, or Amex acceptance. Customers pay through their own P2P apps.
- **Not a chargeback shield.** P2P payments don't carry the dispute machinery that cards do. The reversal risk is non-zero but materially lower than card processing: PayPal Friends & Family has no built-in dispute pathway and can only be reversed via unauthorized-access claims through PayPal or, if the F&F payment was funded by a credit card, a chargeback through the customer's card issuer. Cash App reversals are limited to unauthorized-account claims. Zelle payments are effectively irreversible once received. Venmo follows similar rules: no buyer protection on personal payments.
- **Not a Venmo or Cash App account creator.** You configure your own existing accounts. We don't open accounts on your behalf.
- **Not a refund engine.** Refunds happen merchant-to-customer outside Pipe Pay. With Venmo and Cash App business profiles you can use the in-app refund button. With personal Venmo, Cash App, PayPal Friends & Family, and Zelle there is no refund function: you simply send a new payment in the reverse direction.
- **Not a regulatory workaround.** Pipe Pay does not change what you can legally sell. You are responsible for what your store offers. We don't screen products, which, for a payment processor, is a feature.
- **Not a substitute for traditional gateways for low-risk businesses.** If you can use Stripe, you should. We're built for the merchants who can't.
- **Not for WooCommerce Subscriptions.** Pipe Pay supports single-payment orders only in this version. Recurring billing is not supported.

---

## 13. Pricing

**Headline:**
Simple pricing. Annual licenses with a 7-day free trial.

**Subheadline:**
Every tier includes the same features. The license you pick controls the number of sites you can activate, nothing else.

**3-column tier table (highlight 5 Sites as "Most Popular" with a subtle blue ribbon):**

| | Single Site | 5 Sites *(Most Popular)* | Unlimited Sites |
|---|---|---|---|
| **Price** | $249 / year | $499 / year | $999 / year |
| **Sites** | 1 | Up to 5 | Unlimited |
| **Updates and support** | 1 year | 1 year | 1 year |
| **Free trial** | 7 days | 7 days | 7 days |
| **CTA** | Start 7-day trial | Start 7-day trial | Start 7-day trial |

**Below the cards (small grey text):**
*License entitles you to 1 year of updates and support. The plugin requires an active license to process payments; if your license lapses, the plugin stops accepting new orders until renewed. Cancel anytime before the trial ends and you won't be charged. Once your trial converts to a paid license, all sales are final, no refunds. The 7-day trial is your evaluation window.*

---

## 14. FAQ

**Headline:**
Questions worth answering before you trial.

**Accordion list:**

**How is this different from manually accepting Venmo?**
The payment part is identical. The reconciliation part isn't. Pipe Pay captures the screenshot at checkout, verifies it with AI, and only surfaces the orders that actually need your attention. The manual workflow you're doing now scales linearly with order volume; this one doesn't.

**Can I use my existing Venmo, Cash App, PayPal, and Zelle accounts?**
Yes. Pipe Pay connects to the accounts you already have. We don't open new accounts and we don't move money on your behalf.

**What happens if a customer pays but never uploads the screenshot?**
Three escalating reminder emails fire at 5, 20, and 45 minutes after checkout. If there's still no upload at 60 minutes, the order auto-cancels and stock is restored. The customer gets a cancellation email.

**Does the AI catch all fraud?**
Honest answer: no. The AI catches the obvious cases (wrong amount, wrong recipient, signs of image editing) and flags anything ambiguous for your manual review. It is a triage layer that makes manual review tractable, not a guarantee against fraud.

**How do I issue refunds to customers?**
Refunds happen outside Pipe Pay. You send the money back through whichever P2P app the customer originally paid with. With a Venmo or Cash App business profile you can use the in-app refund button (typically 5 to 10 business days to settle). For personal Venmo, Cash App, PayPal Friends & Family, and Zelle there is no refund button: you simply send a new payment in the reverse direction. Pipe Pay marks the order refunded in WooCommerce; the actual money movement is on you.

**Do you offer refunds on the plugin license?**
No. The 7-day free trial is the evaluation period. No card is charged until day 8, and you can cancel any time before then. Once the trial converts to a paid license, all sales are final. Full details on the [refund policy page](https://pipepay.app/refund-policy).

**What if I don't want to use AI verification?**
The plugin works in fully manual review mode. Every uploaded screenshot lands in your admin queue; you approve or reject each one yourself. No API key required.

**What happens if my license expires?**
The plugin stops processing new payments at the next license check. Existing orders, settings, and historical data all remain intact. Renew and the plugin starts accepting orders again immediately.

**Does it work with WooCommerce Subscriptions?**
No. Pipe Pay supports single-payment orders only in the current version.

**What versions of WordPress and WooCommerce does it require?**
WordPress 6.0+, WooCommerce 8.0+, PHP 7.4+. HPOS compatible. Works with both classic shortcode checkout and the block checkout.

**How do updates work?**
Through WordPress's standard "Update Available" notification, the same way any plugin from wordpress.org updates. Drop your license key into the plugin settings; updates flow automatically as long as the license is active.

---

## 15. Final CTA

**Section style:** full-width royal blue (`#1336a8`), white text, single button.

**Headline:**
Ready to stop reconciling by hand?

**Subheadline:**
Start your 7-day free trial. No card required.

**Primary CTA (white background, blue text):** Start 7-day free trial →

(No secondary CTA. The page has already given the reader plenty of opportunities to read more.)

---

## 16. Footer

**Three columns:**

**Product**
- Pricing
- Documentation
- Changelog

**Company**
- Contact
- Refund policy
- Privacy policy
- Terms of service

**Connect**
- Email signup: "Get release notes when we ship updates."
  (Single email field + "Subscribe" button.)

**Bottom line (small, grey, centered):**
© 2026 Pipe Pay. Built for WooCommerce.

---

## Microcopy (used throughout)

**Buttons:**
- Primary trial CTA: `Start 7-day free trial →`
- Secondary anchor: `How it works ↓`
- Pricing card CTA: `Start 7-day trial`
- Final CTA: `Start 7-day free trial →`

**Form labels and tooltips (for any contact / signup / checkout forms):**
- Email field placeholder: `you@yourstore.com`
- Required-field marker: `*`
- Subscribe success: `Got it. You'll get an email when we ship updates.`
- Subscribe error: `That email didn't go through. Try again, or email us at wittscafidi@gmail.com.`
- Generic form error: `Something didn't work. Refresh the page and try again, or contact support.`

**Trust strip (under hero, repeated in pricing page footer):**
- `7-day free trial`
- `No card required to start`
- `WooCommerce 8.0+`
- `HPOS compatible`

**Pricing-card "Most Popular" ribbon:** `Most Popular`

**Hover state for outline / secondary buttons:** background fades to `#1336a8` at 8% opacity, border deepens to `#1336a8`.

**Link hover state (in-body links):** color stays `#1336a8`, underline appears.

---

## Notes for the implementer

- All trial / pricing buttons go to `https://pipepay.app/checkout`. Once EDD is configured with separate product IDs per tier, the buttons inside the pricing cards can deep-link to the specific tier (e.g. `?edd_action=add_to_cart&download_id=XX`).
- The hero screenshot, the "How it works" composite, the "AI deep-dive" Proofs queue screenshot, the security section accent (optional), and the feature card icons are all visual placeholders that need real screenshots from a working install of v1.4.0.
- Headlines are deliberately verbal sentence fragments ("Manual reconciliation breaks at 30 orders a day"), not titles. They should typeset slightly larger than headers in conventional landing pages, with line-height ~1.15 and weight 700.
- Body copy is target ~16-17px with line-height 1.6.
- All sections except the hero, mid-page CTA, and final CTA are on a white or light-grey alternating background. The royal blue sections only appear three times on the page so they retain their punch.
