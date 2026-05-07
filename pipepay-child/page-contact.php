<?php
/**
 * Template for the Contact page (slug: contact).
 * No SMTP relay is configured yet, so the page leans on a direct mailto link.
 * Once SMTP is wired up, swap in a real form (Fluent Forms, etc.) here.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

$contact_email = 'wittscafidi@gmail.com';
$mail_subject  = rawurlencode( 'Pipe Pay - ' );
?>

<section class="pp-page-hero">
    <div class="pp-container">
        <span class="pp-page-hero__kicker">Contact</span>
        <h1 class="pp-page-title">Get in touch.</h1>
        <p class="pp-page-hero__sub">Pipe Pay is built and supported by one person. The fastest way to reach me is email. I read everything; expect a reply within one business day.</p>
    </div>
</section>

<section class="pp-section pp-section--tight">
    <div class="pp-container pp-container--narrow">
        <div class="pp-contact-card">
            <span class="pp-contact-card__label">Email</span>
            <a class="pp-contact-card__email" href="mailto:<?php echo antispambot( $contact_email ); ?>?subject=<?php echo $mail_subject; // already URL-encoded ?>">
                <?php echo esc_html( $contact_email ); ?>
            </a>
            <p class="pp-contact-card__hint">Click the address to open your mail client with the subject pre-filled. Or copy and paste it.</p>
        </div>

        <div class="pp-contact-grid">
            <article class="pp-contact-block">
                <h3>Pre-sales questions</h3>
                <p>Wondering whether Pipe Pay fits your store? Tell me what you sell, which P2P methods you already accept, and roughly your daily order volume. I will tell you honestly whether it is a fit.</p>
            </article>
            <article class="pp-contact-block">
                <h3>Trial &amp; license help</h3>
                <p>Stuck on activation, license key, or wiring up an AI provider in Pipe Pay's settings? Email me with your store URL and the version of Pipe Pay you are running.</p>
            </article>
            <article class="pp-contact-block">
                <h3>Bug reports</h3>
                <p>For issues with Pipe Pay's own behavior. Include the WooCommerce order ID, the customer's payment method, and the rough timestamp. If the AI flagged the order, paste the confidence reason from the Proofs queue.</p>
            </article>
            <article class="pp-contact-block">
                <h3>Feature requests</h3>
                <p>I am happy to hear them. I cannot promise to ship every request, and I will tell you so. The roadmap is short by design.</p>
            </article>
        </div>

        <div class="pp-contact-scope">
            <span class="pp-contact-scope__label">Scope of support</span>
            <p>Pipe Pay support covers the plugin itself: configuration, the upload and verification flow, the Proofs queue, license management. We do not troubleshoot WooCommerce issues unrelated to Pipe Pay, or fix bugs that originate inside an AI provider (Claude, OpenAI, OpenRouter, or your custom endpoint).</p>
        </div>

        <div class="pp-contact-fineprint">
            <p>For everything else, including legal, partnership, and security disclosure, please email and use a clear subject line. There is no on-call phone line; this is a small operation.</p>
        </div>
    </div>
</section>

<?php get_footer(); ?>
