<?php
/**
 * Footer used by all non-homepage pages.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$logo_svg = <<<'SVG'
<svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <rect x="2"  y="26" width="8" height="12" fill="currentColor"/>
  <rect x="9"  y="22" width="3" height="20" fill="currentColor"/>
  <rect x="54" y="26" width="8" height="12" fill="currentColor"/>
  <rect x="52" y="22" width="3" height="20" fill="currentColor"/>
  <path d="M 12 32 L 32 14" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <path d="M 12 32 L 22 32" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <path d="M 12 32 L 32 50" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <path d="M 52 32 L 32 14" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <path d="M 52 32 L 42 32" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <path d="M 52 32 L 32 50" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <circle cx="12" cy="32" r="1.8" fill="currentColor"/>
  <circle cx="52" cy="32" r="1.8" fill="currentColor"/>
  <circle cx="32" cy="32" r="10.5" fill="currentColor"/>
  <circle cx="32" cy="32" r="8.8"  fill="#fff"/>
  <text x="32" y="38" text-anchor="middle" font-family="Manrope, Inter, sans-serif" font-size="17" font-weight="800" fill="currentColor">$</text>
</svg>
SVG;
?>
</main>

<footer class="pp-footer">
    <div class="pp-container">
        <div class="pp-footer-row">
            <a class="pp-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                <?php echo $logo_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?>
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
            <span>v1.6.2</span>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
