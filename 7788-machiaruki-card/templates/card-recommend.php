<?php
/**
 * Recommendation template.
 * Version: 1.2.2
 *
 * @var array $items
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $items ) ) {
	return;
}
?>
<section class="twkm-machiaruki-recommend">
	<div class="twkm-machiaruki-recommend__head">
		<div class="twkm-machiaruki-recommend__eyebrow">まち歩き一覧</div>
		<h3 class="twkm-machiaruki-recommend__title">近い雰囲気のまち歩きカード</h3>
	</div>

	<div class="twkm-machiaruki-recommend__grid">
		<?php foreach ( $items as $item ) : ?>
			<article class="twkm-machiaruki-recommend-card">
				<a class="twkm-machiaruki-recommend-card__media" href="<?php echo esc_url( $item['permalink'] ); ?>">
					<img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" loading="lazy">
				</a>
				<div class="twkm-machiaruki-recommend-card__body">
					<?php if ( ! empty( $item['area'] ) ) : ?>
						<span class="twkm-machiaruki-chip twkm-machiaruki-chip--area"><?php echo esc_html( $item['area'] ); ?></span>
					<?php endif; ?>
					<h4 class="twkm-machiaruki-recommend-card__title"><a href="<?php echo esc_url( $item['permalink'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h4>
					<div class="twkm-machiaruki-recommend-card__meta">
						<span><?php echo esc_html( $item['spot_count'] ); ?>スポット</span>
						<span><?php echo esc_html( $item['duration_label'] ); ?></span>
					</div>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
</section>
