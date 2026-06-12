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
</style>

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

    allCtas.forEach(function(btn){
        btn.addEventListener('click', function(){
            var priceId = btn.dataset.priceId;
            if (!priceId || btn.disabled) return;
            clearErrors();
            setPending(true, btn);

            var body = new FormData();
            body.append('price_id', priceId);

            fetch('<?php echo esc_js( esc_url_raw( rest_url( 'pipepay-stripe-subs/v1/checkout' ) ) ); ?>', {
                method: 'POST',
                body: body,
                credentials: 'same-origin'
            })
            .then(function(r){ return r.json(); })
            .then(function(data){
                if (data && data.url){
                    window.location.href = data.url;
                } else {
                    setPending(false, btn);
                    showError(btn, 'Could not start checkout' + ((data && data.error) ? ': ' + data.error : '') + '. Please try again or email support@pipepay.app.');
                }
            })
            .catch(function(){
                setPending(false, btn);
                showError(btn, 'Network error. Please try again or email support@pipepay.app.');
            });
        });
    });
})();
</script>
