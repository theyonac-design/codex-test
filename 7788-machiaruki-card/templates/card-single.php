<?php
/**
 * Single card template.
 * Version: 1.2.2
 *
 * @var array  $route
 * @var array  $card
 * @var array  $map
 * @var string $recommendations_html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gallery_images = ! empty( $card['gallery_images'] ) ? $card['gallery_images'] : array();
?>
<article class="twkm-machiaruki-public-card js-twkm-public-card" data-route="<?php echo esc_attr( $card['route_json'] ); ?>">
	<div class="twkm-machiaruki-public-card__media">
		<div class="twkm-machiaruki-public-card__main-image">
			<img src="<?php echo esc_url( $card['image'] ); ?>" alt="<?php echo esc_attr( $card['title'] ); ?>" loading="lazy">
		</div>

		<?php if ( ! empty( $gallery_images ) ) : ?>
			<div class="twkm-machiaruki-public-card__sub-images">
				<?php foreach ( $gallery_images as $gallery_image ) : ?>
					<div class="twkm-machiaruki-public-card__sub-image">
						<img src="<?php echo esc_url( $gallery_image ); ?>" alt="" loading="lazy">
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="twkm-machiaruki-public-card__body">
		<div class="twkm-machiaruki-public-card__labels">
			<?php if ( ! empty( $card['area'] ) ) : ?>
				<span class="twkm-machiaruki-chip twkm-machiaruki-chip--area"><?php echo esc_html( $card['area'] ); ?></span>
			<?php endif; ?>

			<?php foreach ( (array) $card['labels'] as $label ) : ?>
				<span class="twkm-machiaruki-chip"><?php echo esc_html( $label ); ?></span>
			<?php endforeach; ?>
		</div>

		<h2 class="twkm-machiaruki-public-card__title"><?php echo esc_html( $card['title'] ); ?></h2>

		<?php if ( ! empty( $card['description'] ) ) : ?>
			<p class="twkm-machiaruki-public-card__description"><?php echo esc_html( $card['description'] ); ?></p>
		<?php endif; ?>

		<div class="twkm-machiaruki-public-card__facts">
			<div class="twkm-machiaruki-public-card__fact">
				<span class="twkm-machiaruki-public-card__fact-label">スポット数</span>
				<strong><?php echo esc_html( $card['spot_count'] ); ?>スポット</strong>
			</div>

			<div class="twkm-machiaruki-public-card__fact">
				<span class="twkm-machiaruki-public-card__fact-label">所要時間目安</span>
				<strong><?php echo esc_html( $card['duration_label'] ); ?></strong>
			</div>

			<?php if ( ! empty( $card['representative_spots'] ) ) : ?>
				<div class="twkm-machiaruki-public-card__fact twkm-machiaruki-public-card__fact--wide">
					<span class="twkm-machiaruki-public-card__fact-label">代表スポット</span>
					<strong><?php echo esc_html( implode( ' / ', $card['representative_spots'] ) ); ?></strong>
				</div>
			<?php endif; ?>
		</div>

		<div class="twkm-machiaruki-public-card__actions">
			<?php if ( ! empty( $map['url'] ) ) : ?>
				<a class="twkm-machiaruki-button twkm-machiaruki-button--map" href="<?php echo esc_url( $map['url'] ); ?>" target="_blank" rel="noopener noreferrer">Googleマップでルートをみる</a>
			<?php endif; ?>
			<button type="button" class="twkm-machiaruki-button twkm-machiaruki-button--ghost js-twkm-save-route">保存</button>
			<button type="button" class="twkm-machiaruki-button twkm-machiaruki-button--secondary js-twkm-share-route">共有</button>
			<?php if ( ! empty( $card['edit_link'] ) ) : ?>
				<a class="twkm-machiaruki-button twkm-machiaruki-button--ghost" href="<?php echo esc_url( $card['edit_link'] ); ?>">編集</a>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $map['excluded_spot_count'] ) ) : ?>
			<p class="twkm-machiaruki-public-card__notice"><?php echo esc_html( sprintf( '%d件はカードに含まれていますが、位置情報不足のため地図ルートには含めていません。', (int) $map['excluded_spot_count'] ) ); ?></p>
		<?php endif; ?>

		<div class="twkm-machiaruki-public-card__footer">
			<span><?php echo esc_html( $card['author_name'] ); ?></span>
			<?php if ( ! empty( $card['updated_label'] ) ) : ?>
				<span><?php echo esc_html( $card['updated_label'] ); ?></span>
			<?php endif; ?>
		</div>
	</div>
</article>

<?php if ( ! empty( $recommendations_html ) ) : ?>
	<?php echo $recommendations_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php endif; ?>
