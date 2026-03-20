<?php
/**
 * Empty state template.
 * Version: 1.2.2
 *
 * @var string $title
 * @var string $message
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="twkm-machiaruki-empty">
	<div class="twkm-machiaruki-empty__icon" aria-hidden="true">まち歩き</div>
	<h3 class="twkm-machiaruki-empty__title"><?php echo esc_html( $title ); ?></h3>
	<p class="twkm-machiaruki-empty__message"><?php echo esc_html( $message ); ?></p>
</div>
