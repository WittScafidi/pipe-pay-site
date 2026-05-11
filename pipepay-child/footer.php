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
                <a href="<?php echo esc_url( home_url( '/my-account' ) ); ?>">My account</a>
                <a href="<?php echo esc_url( home_url( '/refund-policy' ) ); ?>">Refunds</a>
                <a href="<?php echo esc_url( home_url( '/privacy' ) ); ?>">Privacy</a>
                <a href="<?php echo esc_url( home_url( '/sub-processors' ) ); ?>">Sub-processors</a>
                <a href="<?php echo esc_url( home_url( '/data-handling' ) ); ?>">Data handling</a>
                <a href="<?php echo esc_url( home_url( '/terms' ) ); ?>">Terms</a>
            </nav>
            <a class="pp-footer-changelog" href="<?php echo esc_url( home_url( '/changelog' ) ); ?>">
                <span>Release notes</span>
                <span aria-hidden="true">&rarr;</span>
            </a>
        </div>
        <div class="pp-footer-ledger">
            <strong>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Pipe Pay</strong>
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
