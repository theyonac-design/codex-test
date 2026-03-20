<?php
/**
 * Card list template.
 * Version: 1.2.2
 *
 * @var array $items
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="twkm-machiaruki-list">
	<?php foreach ( $items as $item ) : ?>
		<article class="twkm-machiaruki-summary-card">
			<a class="twkm-machiaruki-summary-card__media" href="<?php echo esc_url( $item['permalink'] ); ?>">
				<img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" loading="lazy">
			</a>

			<div class="twkm-machiaruki-summary-card__body">
				<div class="twkm-machiaruki-summary-card__labels">
					<?php if ( ! empty( $item['area'] ) ) : ?>
						<span class="twkm-machiaruki-chip twkm-machiaruki-chip--area"><?php echo esc_html( $item['area'] ); ?></span>
					<?php endif; ?>
					<?php foreach ( (array) $item['labels'] as $label ) : ?>
						<span class="twkm-machiaruki-chip"><?php echo esc_html( $label ); ?></span>
					<?php endforeach; ?>
				</div>

				<h3 class="twkm-machiaruki-summary-card__title">
					<a href="<?php echo esc_url( $item['permalink'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
				</h3>

				<div class="twkm-machiaruki-summary-card__meta">
					<span><?php echo esc_html( $item['spot_count'] ); ?>スポット</span>
					<span><?php echo esc_html( $item['duration_label'] ); ?></span>
				</div>

				<?php if ( ! empty( $item['description'] ) ) : ?>
					<p class="twkm-machiaruki-summary-card__description"><?php echo esc_html( $item['description'] ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $item['representative_spots'] ) ) : ?>
					<ul class="twkm-machiaruki-summary-card__spots">
						<?php foreach ( (array) $item['representative_spots'] as $spot_name ) : ?>
							<li><?php echo esc_html( $spot_name ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<a class="twkm-machiaruki-button twkm-machiaruki-button--ghost" href="<?php echo esc_url( $item['permalink'] ); ?>">ルートを見る</a>
			</div>
		</article>
	<?php endforeach; ?>
</div>
