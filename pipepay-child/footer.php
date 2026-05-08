<?php
/**
 * Footer used by all non-homepage pages.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
</main>

<footer class="pp-footer">
    <div class="pp-container">
        <div class="pp-footer-row">
            <a class="pp-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                <?php include __DIR__ . '/partials/logo-svg.php'; ?>
                <span>Pipe Pay</span>
            </a>
            <nav class="pp-footer-links" aria-label="Footer">
                <a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>">Pricing</a>
                <a href="<?php echo esc_url( home_url( '/docs' ) ); ?>">Docs</a>
                <a href="<?php echo esc_url( home_url( '/changelog' ) ); ?>">Changelog</a>
                <a href="<?php echo esc_url( home_url( '/contact' ) ); ?>">Contact</a>
                <a href="<?php echo esc_url( home_url( '/refund-policy' ) ); ?>">Refunds</a>
                <a href="<?php echo esc_url( home_url( '/privacy' ) ); ?>">Privacy</a>
                <a href="<?php echo esc_url( home_url( '/terms' ) ); ?>">Terms</a>
            </nav>
            <form class="pp-footer-signup" action="<?php echo esc_url( home_url( '/contact' ) ); ?>" method="post">
                <label for="pp-footer-email">Release notes:</label>
                <input id="pp-footer-email" type="email" name="email" placeholder="you@yourstore.com" required>
                <button type="submit">Subscribe</button>
            </form>
        </div>
        <div class="pp-footer-ledger">
            <strong>&copy; <?php echo esc_html( date( 'Y' ) ); ?> Pipe Pay</strong>
            <span class="pp-footer-ledger__sep">/</span>
            <span>Independent software</span>
            <span class="pp-footer-ledger__sep">/</span>
            <span>v<?php echo esc_html( PIPEPAY_SITE_VERSION ); ?></span>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
