<?php
/**
 * Action buttons template.
 * Version: 1.2.2
 *
 * @var string $favorite_html
 * @var string $add_button
 * @var string $wrapper_class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="twkm-machiaruki-actions <?php echo esc_attr( $wrapper_class ); ?>">
	<?php if ( ! empty( $favorite_html ) ) : ?>
		<div class="twkm-machiaruki-actions__item twkm-machiaruki-actions__item--favorite">
			<?php echo $favorite_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $add_button ) ) : ?>
		<div class="twkm-machiaruki-actions__item twkm-machiaruki-actions__item--machiaruki">
			<?php echo $add_button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	<?php endif; ?>
</div>
