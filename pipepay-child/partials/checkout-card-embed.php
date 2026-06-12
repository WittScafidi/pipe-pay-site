<?php
/**
 * Inline Stripe Embedded Checkout for the WC Checkout page.
 *
 * Included by page-checkout.php when the cart contains a license tier.
 * Expects in scope:
 *   $pp_embed_mode        'choice' (annual: card vs payment app) | 'auto' (monthly: card only)
 *   $pp_embed_price_id    Stripe Price ID for the card subscription
 *   $pp_embed_price_label e.g. '$299/yr' or '$35/mo'
 *
 * In 'choice' mode the template wraps the WC checkout form in
 * #pp-wc-checkout-form[hidden]; the payment-app option reveals it, the card
 * option mounts Stripe's embedded checkout inline instead. Any embed failure
 * falls back to a redirect to Stripe-hosted Checkout.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$pp_embed_mode        = isset( $pp_embed_mode ) ? $pp_embed_mode : 'choice';
$pp_embed_price_id    = isset( $pp_embed_price_id ) ? $pp_embed_price_id : '';
$pp_embed_price_label = isset( $pp_embed_price_label ) ? $pp_embed_price_label : '';
?>
<style>
.pp-pay-choice{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
    margin:0 0 28px;
}
@media (max-width:680px){ .pp-pay-choice{ grid-template-columns:1fr; } }
.pp-pay-choice__btn{
    appearance:none;
    -webkit-appearance:none;
    font-family:inherit;
    text-align:left;
    background:#fff;
    border:2px solid #d8ddee;
    border-radius:12px;
    padding:18px 20px;
    cursor:pointer;
    transition:border-color 0.15s ease, box-shadow 0.15s ease;
}
.pp-pay-choice__btn:hover{ border-color:#1336a8; }
.pp-pay-choice__btn.is-selected{ border-color:#1336a8; box-shadow:0 0 0 3px rgba(19,54,168,0.12); }
.pp-pay-choice__title{ display:block; font-weight:700; font-size:16px; color:#1c2333; margin:0 0 4px; }
.pp-pay-choice__sub{ display:block; font-size:13.5px; color:#5a6173; line-height:1.5; }
.pp-card-embed{ display:none; margin:0 0 8px; }
.pp-card-embed.is-active{ display:block; }
.pp-card-embed__back{ margin:0 0 14px; font-size:14px; }
.pp-card-embed__loading{ padding:32px 0; color:#5a6173; font-size:15px; }
.pp-card-embed-error{ margin:10px 0 0; font-size:0.9rem; color:#b3261e; }
</style>

<?php if ( 'choice' === $pp_embed_mode ) : ?>
<div class="pp-pay-choice" role="group" aria-label="How would you like to pay?">
    <button type="button" class="pp-pay-choice__btn" id="pp-choose-card">
        <span class="pp-pay-choice__title">Pay by card &mdash; <?php echo esc_html( $pp_embed_price_label ); ?></span>
        <span class="pp-pay-choice__sub">Auto-renews each year through Stripe. Cancel anytime from your billing portal. License key issued instantly.</span>
    </button>
    <button type="button" class="pp-pay-choice__btn" id="pp-choose-app">
        <span class="pp-pay-choice__title">Pay with a payment app</span>
        <span class="pp-pay-choice__sub">Venmo, Cash App, PayPal, or Zelle. One payment for the year; we email you a renewal link before it expires.</span>
    </button>
</div>
<?php endif; ?>

<div class="pp-card-embed" id="pp-card-embed">
    <?php if ( 'choice' === $pp_embed_mode ) : ?>
    <p class="pp-card-embed__back"><a href="#" id="pp-embed-back">&larr; choose a different payment method</a></p>
    <?php endif; ?>
    <div class="pp-card-embed__loading" id="pp-embed-loading">Loading secure card checkout&hellip;</div>
    <div id="pp-embedded-checkout"></div>
</div>

<script>
(function(){
    var MODE         = '<?php echo esc_js( $pp_embed_mode ); ?>';
    var PRICE_ID     = '<?php echo esc_js( $pp_embed_price_id ); ?>';
    var CHECKOUT_URL = '<?php echo esc_js( esc_url_raw( rest_url( 'pipepay-stripe-subs/v1/checkout' ) ) ); ?>';
    var STRIPE_PK    = '<?php echo esc_js( defined( 'PIPEPAY_STRIPE_PUBLISHABLE_KEY' ) ? PIPEPAY_STRIPE_PUBLISHABLE_KEY : '' ); ?>';

    var embedWrap = document.getElementById('pp-card-embed');
    var loadingEl = document.getElementById('pp-embed-loading');
    var wcForm    = document.getElementById('pp-wc-checkout-form');
    var chooser   = document.querySelector('.pp-pay-choice');
    var stripeJs  = null;
    var embedded  = null;
    var mounted   = false;

    function loadStripeJs(){
        if (stripeJs) return stripeJs;
        stripeJs = new Promise(function(resolve, reject){
            if (window.Stripe) { resolve(window.Stripe); return; }
            var tag = document.createElement('script');
            tag.src = 'https://js.stripe.com/v3/';
            tag.onload = function(){ window.Stripe ? resolve(window.Stripe) : reject(new Error('stripe missing')); };
            tag.onerror = function(){ stripeJs = null; reject(new Error('stripe load failed')); };
            document.head.appendChild(tag);
        });
        return stripeJs;
    }

    function createSession(ui){
        var body = new FormData();
        body.append('price_id', PRICE_ID);
        if (ui) body.append('ui', ui);
        return fetch(CHECKOUT_URL, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function(r){ return r.json(); });
    }

    // Fallback: full-page redirect to Stripe-hosted Checkout. The purchase
    // path must never die because an embed failed.
    function redirectFlow(){
        createSession('')
            .then(function(data){
                if (data && data.url) { window.location.href = data.url; }
                else { showError('Could not start card checkout. Please try the payment-app option or email support@pipepay.app.'); }
            })
            .catch(function(){ showError('Network error. Please try again or email support@pipepay.app.'); });
    }

    function showError(msg){
        loadingEl.style.display = 'none';
        var p = document.createElement('p');
        p.className = 'pp-card-embed-error';
        p.setAttribute('role', 'alert');
        p.textContent = msg;
        embedWrap.appendChild(p);
    }

    function mountEmbed(){
        if (mounted) return;
        mounted = true;
        if (!STRIPE_PK) { redirectFlow(); return; }
        loadStripeJs()
            .then(function(StripeCtor){
                return createSession('embedded').then(function(data){
                    if (!data || !data.client_secret) { throw new Error((data && data.error) || 'no client secret'); }
                    return StripeCtor(STRIPE_PK).initEmbeddedCheckout({ clientSecret: data.client_secret });
                });
            })
            .then(function(instance){
                embedded = instance;
                loadingEl.style.display = 'none';
                embedded.mount('#pp-embedded-checkout');
            })
            .catch(function(){ redirectFlow(); });
    }

    function showCard(){
        embedWrap.classList.add('is-active');
        if (wcForm) wcForm.hidden = true;
        if (chooser) {
            chooser.querySelector('#pp-choose-card').classList.add('is-selected');
            chooser.querySelector('#pp-choose-app').classList.remove('is-selected');
        }
        mountEmbed();
    }

    function showApp(){
        embedWrap.classList.remove('is-active');
        if (wcForm) wcForm.hidden = false;
        if (chooser) {
            chooser.querySelector('#pp-choose-app').classList.add('is-selected');
            chooser.querySelector('#pp-choose-card').classList.remove('is-selected');
        }
        // The embedded instance stays mounted but hidden, so switching back to
        // card is instant and does not create a second session.
    }

    if (MODE === 'auto') {
        showCard();
    } else {
        document.getElementById('pp-choose-card').addEventListener('click', showCard);
        document.getElementById('pp-choose-app').addEventListener('click', showApp);
        var back = document.getElementById('pp-embed-back');
        if (back) back.addEventListener('click', function(e){ e.preventDefault(); showApp(); });
    }
})();
</script>
