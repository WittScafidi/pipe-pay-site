<?php
/**
 * Shared CSS + JS for the Annual/Monthly billing toggle on the homepage and /pricing.
 * Single source of truth — both templates include this after their pricing section.
 *
 * Expects the including template to render:
 *   .pp-billing-toggle[role=group] > button[data-billing][aria-pressed]
 *   [data-billing-show=annual|monthly] blocks
 *   button.pp-stripe-cta[data-price-id]
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<style>
/* `hidden` defaults to display:none but loses specificity vs .pp-btn — force it. */
.pp-pricing [hidden]{ display:none !important; }
.pp-billing-toggle{
    display:inline-flex;
    align-items:center;
    padding:4px;
    background:#f0f2f8;
    border:1px solid #d8ddee;
    border-radius:999px;
    margin:0 auto 12px;
    gap:0;
}
.pp-pricing .pp-container{ text-align:center; }
.pp-pricing-grid{ text-align:left; }
.pp-billing-toggle__btn{
    appearance:none;
    -webkit-appearance:none;
    border:0;
    background:transparent;
    color:#1336a8;
    font-family:inherit;
    font-weight:600;
    font-size:0.95rem;
    padding:10px 22px;
    border-radius:999px;
    cursor:pointer;
    transition:background 0.15s ease, color 0.15s ease;
    line-height:1.2;
    display:inline-flex;
    align-items:center;
    gap:8px;
}
.pp-billing-toggle__btn:hover{ background:#e3e8f5; }
.pp-billing-toggle__btn--active,
.pp-billing-toggle__btn--active:hover{
    background:#1336a8;
    color:#fff;
}
.pp-billing-toggle__save{
    font-size:0.75rem;
    font-weight:500;
    opacity:0.85;
    text-transform:uppercase;
    letter-spacing:0.4px;
}
.pp-billing-toggle__btn:not(.pp-billing-toggle__btn--active) .pp-billing-toggle__save{ color:#3c6e3c; }
.pp-billing-toggle__note{
    font-size:0.9rem;
    color:#5a6173;
    margin:0 auto 32px;
    max-width:560px;
}
/* Reset <button> UA defaults so .pp-btn variants render the <button> identically to
   the <a class="pp-btn"> CTAs. Don't touch border — would strip the outline. */
.pp-stripe-cta{ appearance:none; -webkit-appearance:none; margin:0; cursor:pointer; position:relative; }
.pp-stripe-cta[disabled]{ opacity:0.6; cursor:wait; }
.pp-stripe-cta--loading::after{
    content:"";
    display:inline-block;
    width:14px;
    height:14px;
    margin-left:8px;
    border:2px solid currentColor;
    border-top-color:transparent;
    border-radius:50%;
    animation:pp-cta-spin 0.7s linear infinite;
    vertical-align:-2px;
}
@keyframes pp-cta-spin{ to{ transform:rotate(360deg); } }
.pp-stripe-cta-error{
    margin:10px 0 0;
    font-size:0.85rem;
    line-height:1.45;
    color:#b3261e;
}
@media (max-width:540px){
    .pp-billing-toggle__btn{ padding:10px 16px; font-size:0.9rem; }
    .pp-billing-toggle__save{ display:none; }
}
/* Embedded Stripe Checkout modal */
.pp-checkout-modal{ position:fixed; inset:0; z-index:99999; display:none; }
.pp-checkout-modal.is-open{ display:block; }
.pp-checkout-modal__backdrop{ position:absolute; inset:0; background:rgba(16,22,41,0.55); }
.pp-checkout-modal__panel{
    position:relative;
    margin:4vh auto;
    width:min(1080px, 94vw);
    max-height:92vh;
    overflow-y:auto;
    background:#fff;
    border-radius:14px;
    padding:44px 16px 16px;
    box-shadow:0 24px 64px rgba(16,22,41,0.35);
}
.pp-checkout-modal__close{
    position:absolute;
    top:10px;
    right:12px;
    appearance:none;
    border:0;
    background:transparent;
    font-size:26px;
    line-height:1;
    color:#5a6173;
    cursor:pointer;
    padding:6px 10px;
}
.pp-checkout-modal__close:hover{ color:#1c2333; }
body.pp-checkout-modal-open{ overflow:hidden; }
@media (max-width:540px){
    .pp-checkout-modal__panel{ margin:0 auto; width:100vw; max-height:100vh; border-radius:0; min-height:100vh; }
}
</style>

<div class="pp-checkout-modal" id="pp-checkout-modal" role="dialog" aria-modal="true" aria-label="Checkout">
    <div class="pp-checkout-modal__backdrop" data-pp-modal-close></div>
    <div class="pp-checkout-modal__panel">
        <button type="button" class="pp-checkout-modal__close" data-pp-modal-close aria-label="Close checkout">&times;</button>
        <div id="pp-embedded-checkout"></div>
    </div>
</div>

<script>
(function(){
    var toggle = document.querySelector('.pp-billing-toggle');
    if (!toggle) return;

    function clearErrors(){
        document.querySelectorAll('.pp-stripe-cta-error').forEach(function(p){ p.remove(); });
    }

    function setBilling(billing){
        clearErrors();
        toggle.querySelectorAll('[data-billing]').forEach(function(btn){
            var active = btn.dataset.billing === billing;
            btn.classList.toggle('pp-billing-toggle__btn--active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        document.querySelectorAll('[data-billing-show]').forEach(function(el){
            el.hidden = el.dataset.billingShow !== billing;
        });
    }

    toggle.addEventListener('click', function(e){
        var btn = e.target.closest('[data-billing]');
        if (!btn) return;
        setBilling(btn.dataset.billing);
    });

    var allCtas = document.querySelectorAll('.pp-stripe-cta');

    function setPending(pending, clicked){
        allCtas.forEach(function(b){
            b.disabled = pending;
            b.classList.toggle('pp-stripe-cta--loading', pending && b === clicked);
            if (pending && b === clicked) { b.setAttribute('aria-busy', 'true'); }
            else { b.removeAttribute('aria-busy'); }
        });
    }

    function showError(btn, msg){
        var p = document.createElement('p');
        p.className = 'pp-stripe-cta-error';
        p.setAttribute('role', 'alert');
        p.textContent = msg;
        btn.insertAdjacentElement('afterend', p);
    }

    // ── Embedded Stripe Checkout (modal, no redirect), redirect fallback ──────
    var CHECKOUT_URL = '<?php echo esc_js( esc_url_raw( rest_url( 'pipepay-stripe-subs/v1/checkout' ) ) ); ?>';
    var STRIPE_PK    = '<?php echo esc_js( defined( 'PIPEPAY_STRIPE_PUBLISHABLE_KEY' ) ? PIPEPAY_STRIPE_PUBLISHABLE_KEY : '' ); ?>';
    var modal        = document.getElementById('pp-checkout-modal');
    var mountEl      = document.getElementById('pp-embedded-checkout');
    var stripeJs     = null;   // Promise for the Stripe.js loader
    var embedded     = null;   // active embedded checkout instance

    function loadStripeJs(){
        if (stripeJs) return stripeJs;
        stripeJs = new Promise(function(resolve, reject){
            if (window.Stripe) { resolve(window.Stripe); return; }
            var tag = document.createElement('script');
            tag.src = 'https://js.stripe.com/v3/';
            tag.onload = function(){ window.Stripe ? resolve(window.Stripe) : reject(new Error('stripe missing')); };
            tag.onerror = function(){ reject(new Error('stripe load failed')); };
            document.head.appendChild(tag);
        });
        return stripeJs;
    }

    function closeModal(){
        modal.classList.remove('is-open');
        document.body.classList.remove('pp-checkout-modal-open');
        if (embedded) { try { embedded.destroy(); } catch (e) {} embedded = null; }
        mountEl.innerHTML = '';
    }
    modal.querySelectorAll('[data-pp-modal-close]').forEach(function(el){
        el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    function createSession(priceId, ui){
        var body = new FormData();
        body.append('price_id', priceId);
        if (ui) body.append('ui', ui);
        return fetch(CHECKOUT_URL, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function(r){ return r.json(); });
    }

    // Fallback: the original full-page redirect to Stripe-hosted Checkout.
    function redirectFlow(btn, priceId){
        createSession(priceId, '')
            .then(function(data){
                if (data && data.url) { window.location.href = data.url; }
                else {
                    setPending(false, btn);
                    showError(btn, 'Could not start checkout' + ((data && data.error) ? ': ' + data.error : '') + '. Please try again or email support@pipepay.app.');
                }
            })
            .catch(function(){
                setPending(false, btn);
                showError(btn, 'Network error. Please try again or email support@pipepay.app.');
            });
    }

    allCtas.forEach(function(btn){
        btn.addEventListener('click', function(){
            var priceId = btn.dataset.priceId;
            if (!priceId || btn.disabled) return;
            clearErrors();
            setPending(true, btn);

            if (!STRIPE_PK) { redirectFlow(btn, priceId); return; }

            Promise.all([ loadStripeJs(), createSession(priceId, 'embedded') ])
                .then(function(results){
                    var StripeCtor = results[0];
                    var data       = results[1];
                    if (!data || !data.client_secret) { throw new Error((data && data.error) || 'no client secret'); }
                    var stripe = StripeCtor(STRIPE_PK);
                    return stripe.initEmbeddedCheckout({ clientSecret: data.client_secret });
                })
                .then(function(instance){
                    embedded = instance;
                    embedded.mount('#pp-embedded-checkout');
                    modal.classList.add('is-open');
                    document.body.classList.add('pp-checkout-modal-open');
                    setPending(false, null);
                })
                .catch(function(){
                    // Any embed failure (script blocked, API error) falls back to the
                    // battle-tested redirect flow so the purchase path never dies.
                    redirectFlow(btn, priceId);
                });
        });
    });
})();
</script>
