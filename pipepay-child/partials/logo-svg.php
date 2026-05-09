<?php
/**
 * Pipe Pay logo SVG. Single source of truth - included from header.php,
 * footer.php, and front-page.php (header + final-CTA inverse variant).
 *
 * Variant: pass `$pp_logo_variant = 'inverse'` before include to get the
 * white-on-blue version used inside the final-CTA pp-section--blue block
 * (the SVG has no CSS context inheritance there because the pp-section
 * background overrides currentColor at that depth).
 *
 * Default variant uses currentColor so the parent's color drives both
 * stroke and fill, letting the same SVG render dark blue on a light
 * header and white on the dark footer ledger.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$pp_logo_variant = $pp_logo_variant ?? 'current';
$pp_logo_stroke  = $pp_logo_variant === 'inverse' ? '#fff' : 'currentColor';
$pp_logo_fill    = $pp_logo_variant === 'inverse' ? '#fff' : 'currentColor';
$pp_logo_inner   = $pp_logo_variant === 'inverse' ? '#1336a8' : '#fff';
$pp_logo_text    = $pp_logo_variant === 'inverse' ? '#fff' : 'currentColor';
?>
<svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <rect x="2"  y="26" width="8" height="12" fill="<?php echo esc_attr( $pp_logo_fill ); ?>"/>
  <rect x="9"  y="22" width="3" height="20" fill="<?php echo esc_attr( $pp_logo_fill ); ?>"/>
  <rect x="54" y="26" width="8" height="12" fill="<?php echo esc_attr( $pp_logo_fill ); ?>"/>
  <rect x="52" y="22" width="3" height="20" fill="<?php echo esc_attr( $pp_logo_fill ); ?>"/>
  <path d="M 12 32 L 32 14" stroke="<?php echo esc_attr( $pp_logo_stroke ); ?>" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <path d="M 12 32 L 22 32" stroke="<?php echo esc_attr( $pp_logo_stroke ); ?>" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <path d="M 12 32 L 32 50" stroke="<?php echo esc_attr( $pp_logo_stroke ); ?>" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <path d="M 52 32 L 32 14" stroke="<?php echo esc_attr( $pp_logo_stroke ); ?>" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <path d="M 52 32 L 42 32" stroke="<?php echo esc_attr( $pp_logo_stroke ); ?>" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <path d="M 52 32 L 32 50" stroke="<?php echo esc_attr( $pp_logo_stroke ); ?>" stroke-width="1.6" fill="none" stroke-linecap="round"/>
  <circle cx="12" cy="32" r="1.8" fill="<?php echo esc_attr( $pp_logo_fill ); ?>"/>
  <circle cx="52" cy="32" r="1.8" fill="<?php echo esc_attr( $pp_logo_fill ); ?>"/>
  <circle cx="32" cy="32" r="10.5" fill="<?php echo esc_attr( $pp_logo_fill ); ?>"/>
  <circle cx="32" cy="32" r="8.8"  fill="<?php echo esc_attr( $pp_logo_inner ); ?>"/>
  <text x="32" y="38" text-anchor="middle" font-family="Manrope, Inter, sans-serif" font-size="17" font-weight="800" fill="<?php echo esc_attr( $pp_logo_text ); ?>">$</text>
</svg>
<?php
// Reset the variant so subsequent includes default to currentColor unless explicitly set.
unset( $pp_logo_variant );
