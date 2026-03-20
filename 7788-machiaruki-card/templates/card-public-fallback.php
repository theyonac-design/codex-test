<?php
/**
 * Public page fallback template.
 * Version: 1.2.5
 *
 * @var string $title
 * @var string $message
 * @var string $support_title
 * @var string $support_body
 * @var array  $items
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="twkm-machiaruki-public-fallback">
	<div class="twkm-machiaruki-public-fallback__intro">
		<div class="twkm-machiaruki-public-fallback__eyebrow">公開まち歩きカード</div>
		<h2 class="twkm-machiaruki-public-fallback__title"><?php echo esc_html( $title ); ?></h2>
		<p class="twkm-machiaruki-public-fallback__message"><?php echo esc_html( $message ); ?></p>
	</div>

	<div class="twkm-machiaruki-public-fallback__support">
		<h3 class="twkm-machiaruki-public-fallback__support-title"><?php echo esc_html( $support_title ); ?></h3>
		<p class="twkm-machiaruki-public-fallback__support-body"><?php echo esc_html( $support_body ); ?></p>
	</div>

	<?php if ( ! empty( $items ) ) : ?>
		<div class="twkm-machiaruki-public-fallback__head">
			<div class="twkm-machiaruki-public-fallback__eyebrow">まずはここから</div>
			<h3 class="twkm-machiaruki-public-fallback__spots-title">十和田周辺の注目スポット</h3>
			<p class="twkm-machiaruki-public-fallback__spots-message">気になるスポットを見ながら、公開まち歩きカードの雰囲気を先に楽しめます。</p>
		</div>

		<div class="twkm-machiaruki-public-fallback__grid">
			<?php foreach ( $items as $item ) : ?>
				<article class="twkm-machiaruki-public-fallback-card">
					<a class="twkm-machiaruki-public-fallback-card__media" href="<?php echo esc_url( $item['permalink'] ); ?>">
						<img src="<?php echo esc_url( $item['thumbnail'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" loading="lazy">
					</a>
					<div class="twkm-machiaruki-public-fallback-card__body">
						<div class="twkm-machiaruki-public-fallback-card__labels">
							<?php if ( ! empty( $item['post_type_label'] ) ) : ?>
								<span class="twkm-machiaruki-chip twkm-machiaruki-chip--type"><?php echo esc_html( $item['post_type_label'] ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $item['area'] ) ) : ?>
								<span class="twkm-machiaruki-chip twkm-machiaruki-chip--area"><?php echo esc_html( $item['area'] ); ?></span>
							<?php endif; ?>
						</div>
						<h4 class="twkm-machiaruki-public-fallback-card__title">
							<a href="<?php echo esc_url( $item['permalink'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
						</h4>
						<?php if ( ! empty( $item['excerpt'] ) ) : ?>
							<p class="twkm-machiaruki-public-fallback-card__excerpt"><?php echo esc_html( $item['excerpt'] ); ?></p>
						<?php endif; ?>
						<a class="twkm-machiaruki-button twkm-machiaruki-button--ghost" href="<?php echo esc_url( $item['permalink'] ); ?>">スポットを見る</a>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
