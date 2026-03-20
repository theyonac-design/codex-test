<?php
/**
 * Card slider template.
 * Version: 1.2.2
 *
 * @var array $items
 * @var bool  $autoplay
 * @var bool  $loop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="twkm-machiaruki-slider js-twkm-machiaruki-slider" data-autoplay="<?php echo $autoplay ? 'true' : 'false'; ?>" data-loop="<?php echo $loop ? 'true' : 'false'; ?>">
	<div class="twkm-machiaruki-slider__head">
		<div>
			<div class="twkm-machiaruki-slider__eyebrow">まち歩きスライダー</div>
			<h3 class="twkm-machiaruki-slider__title">おすすめのまち歩きカード</h3>
		</div>

		<div class="twkm-machiaruki-slider__controls">
			<button type="button" class="twkm-machiaruki-slider__control js-twkm-slider-prev" aria-label="前へ">‹</button>
			<button type="button" class="twkm-machiaruki-slider__control js-twkm-slider-next" aria-label="次へ">›</button>
		</div>
	</div>

	<div class="twkm-machiaruki-slider__viewport js-twkm-slider-viewport">
		<div class="twkm-machiaruki-slider__track js-twkm-slider-track">
			<?php foreach ( $items as $item ) : ?>
				<article class="twkm-machiaruki-slider-card">
					<a class="twkm-machiaruki-slider-card__media" href="<?php echo esc_url( $item['permalink'] ); ?>">
						<img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" loading="lazy">
					</a>

					<div class="twkm-machiaruki-slider-card__body">
						<div class="twkm-machiaruki-slider-card__labels">
							<?php if ( ! empty( $item['area'] ) ) : ?>
								<span class="twkm-machiaruki-chip twkm-machiaruki-chip--area"><?php echo esc_html( $item['area'] ); ?></span>
							<?php endif; ?>
						</div>

						<h3 class="twkm-machiaruki-slider-card__title">
							<a href="<?php echo esc_url( $item['permalink'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
						</h3>

						<div class="twkm-machiaruki-slider-card__meta">
							<span><?php echo esc_html( $item['spot_count'] ); ?>スポット</span>
							<span><?php echo esc_html( $item['duration_label'] ); ?></span>
						</div>

						<?php if ( ! empty( $item['description'] ) ) : ?>
							<p class="twkm-machiaruki-slider-card__description"><?php echo esc_html( $item['description'] ); ?></p>
						<?php endif; ?>

						<a class="twkm-machiaruki-button twkm-machiaruki-button--ghost" href="<?php echo esc_url( $item['permalink'] ); ?>">ルートを見る</a>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="twkm-machiaruki-slider__dots js-twkm-slider-dots" aria-label="スライド切り替え"></div>
</div>
