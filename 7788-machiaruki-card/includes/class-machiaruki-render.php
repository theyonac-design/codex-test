<?php
/**
 * Render helpers.
 * Version: 1.2.8
 *
 * @package 7788-machiaruki-card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'TWKM_Machiaruki_Render' ) ) {
	class TWKM_Machiaruki_Render {
		protected $plugin;

		public function __construct( $plugin ) {
			$this->plugin = $plugin;
		}

		public function render_add_button( $atts = array() ) {
			$atts = shortcode_atts(
				array(
					'post_id' => 0,
					'label'   => 'まち歩きカードに追加',
					'class'   => '',
					'wrap'    => '1',
				),
				$atts,
				'twkm_machiaruki_add_button'
			);

			$post_id = absint( $atts['post_id'] );
			if ( $post_id < 1 ) {
				$post_id = get_the_ID();
			}

			if ( $post_id < 1 || ! $this->plugin->storage->is_supported_post( $post_id ) ) {
				return '';
			}

			$spot = $this->plugin->storage->build_spot_payload( $post_id );

			if ( empty( $spot ) ) {
				return '';
			}

			$this->plugin->enqueue_assets();

			$button = sprintf(
				'<button type="button" class="twkm-machiaruki-button twkm-machiaruki-button--primary js-twkm-machiaruki-add-button %1$s" data-post-id="%2$d" data-spot="%3$s" data-add-context="detail" aria-pressed="false">%4$s</button>',
				esc_attr( $atts['class'] ),
				(int) $post_id,
				esc_attr( wp_json_encode( $spot ) ),
				esc_html( $atts['label'] )
			);

			if ( '0' === (string) $atts['wrap'] ) {
				return $button;
			}

			return '<div class="twkm-machiaruki-add-button-wrap">' . $button . '</div>';
		}

		public function render_action_buttons( $atts = array() ) {
			$atts = shortcode_atts(
				array(
					'post_id'            => 0,
					'favorite_html'      => '',
					'favorite_shortcode' => '',
					'class'              => '',
				),
				$atts,
				'twkm_action_buttons'
			);

			$post_id = absint( $atts['post_id'] );
			if ( $post_id < 1 ) {
				$post_id = get_the_ID();
			}

			$favorite_html = $this->get_favorite_button_html( $post_id, $atts );
			$add_button    = $this->render_add_button(
				array(
					'post_id' => $post_id,
					'wrap'    => '0',
				)
			);

			if ( empty( $favorite_html ) && empty( $add_button ) ) {
				return '';
			}

			$this->plugin->enqueue_assets();

			return $this->render_template(
				'action-buttons.php',
				array(
					'favorite_html' => $favorite_html,
					'add_button'    => $add_button,
					'wrapper_class' => sanitize_text_field( $atts['class'] ),
				)
			);
		}

		public function render_modal_trigger( $atts = array() ) {
			$atts = shortcode_atts(
				array(
					'label'       => 'まち歩きカード',
					'modal_id'    => 'twkm-machiaruki-modal',
					'class'       => '',
					'show_count'  => '1',
					'lead'        => '',
					'description' => '',
				),
				$atts,
				'twkm_machiaruki_modal_trigger'
			);

			$this->plugin->enqueue_assets();

			$wrapper_classes = array_merge(
				array( 'twkm-machiaruki-modal-trigger' ),
				$this->sanitize_html_classes( $atts['class'] )
			);
			$modal_id        = sanitize_html_class( $atts['modal_id'] );
			$show_count      = filter_var( $atts['show_count'], FILTER_VALIDATE_BOOLEAN );

			ob_start();
			?>
			<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>" data-plugin-version="<?php echo esc_attr( TWKM_Machiaruki_Card_Plugin::VERSION ); ?>">
				<?php if ( '' !== trim( (string) $atts['lead'] ) ) : ?>
					<p class="twkm-machiaruki-page-entry__lead"><?php echo esc_html( $atts['lead'] ); ?></p>
				<?php endif; ?>
				<button
					type="button"
					class="twkm-machiaruki-button twkm-machiaruki-button--primary js-twkm-open-modal"
					data-modal-target="<?php echo esc_attr( $modal_id ); ?>"
					aria-haspopup="dialog"
					aria-controls="<?php echo esc_attr( $modal_id ); ?>"
					aria-expanded="false"
				>
					<span class="twkm-machiaruki-modal-trigger__label"><?php echo esc_html( $atts['label'] ); ?></span>
					<?php if ( $show_count ) : ?>
						<span class="twkm-machiaruki-modal-trigger__count js-twkm-modal-count" hidden>0</span>
					<?php endif; ?>
				</button>
				<?php if ( '' !== trim( (string) $atts['description'] ) ) : ?>
					<p class="twkm-machiaruki-page-entry__description"><?php echo esc_html( $atts['description'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php
			return ob_get_clean();
		}

		public function render_manager( $atts = array() ) {
			$atts = shortcode_atts(
				array(
					'title'        => 'まち歩きカードを編集',
					'description'  => '追加したスポットを並び替えながら、ルート名や説明文をまとめられます。位置情報がないスポットも追加できますが、地図ルートには含まれない場合があります。',
					'route_id'     => 0,
					'class'        => '',
					'show_publish' => '0',
					'context'      => 'inline',
					'hide_header'  => '0',
				),
				$atts,
				'twkm_machiaruki_manager'
			);

			$this->plugin->enqueue_assets();

			$context         = 'modal' === sanitize_key( $atts['context'] ) ? 'modal' : 'inline';
			$hide_header     = filter_var( $atts['hide_header'], FILTER_VALIDATE_BOOLEAN );
			$manager_classes = array_merge(
				array(
					'twkm-machiaruki-manager',
					'js-twkm-machiaruki-manager',
					'twkm-machiaruki-manager--' . $context,
				),
				$this->sanitize_html_classes( $atts['class'] )
			);

			$initial_route = $this->plugin->storage->get_default_route();
			$force_initial = false;
			if ( ! empty( $atts['route_id'] ) ) {
				$route = $this->plugin->storage->get_route_from_post( absint( $atts['route_id'] ) );
				if ( ! empty( $route ) ) {
					$initial_route = $route;
					$force_initial = true;
				}
			}

			$empty_html      = $this->render_template(
				'card-empty.php',
				array(
					'title'   => '気になるスポットを追加して、自分だけのまち歩きカードを作りましょう',
					'message' => '各ページの「まち歩きカードに追加」から登録できます',
				)
			);
			$picker_filters  = $this->render_picker_filters_html();
			$picker_title_id = wp_unique_id( 'twkm-machiaruki-picker-title-' );

			ob_start();
			?>
			<section class="<?php echo esc_attr( implode( ' ', $manager_classes ) ); ?>" data-context="<?php echo esc_attr( $context ); ?>" data-plugin-version="<?php echo esc_attr( TWKM_Machiaruki_Card_Plugin::VERSION ); ?>" data-initial-route="<?php echo esc_attr( wp_json_encode( $initial_route ) ); ?>" data-show-publish="<?php echo esc_attr( $atts['show_publish'] ); ?>" data-is-admin-viewer="<?php echo esc_attr( current_user_can( 'manage_options' ) ? '1' : '0' ); ?>" data-force-initial-route="<?php echo esc_attr( $force_initial ? '1' : '0' ); ?>">
				<?php if ( ! $hide_header ) : ?>
					<div class="twkm-machiaruki-manager__header">
						<div class="twkm-machiaruki-manager__eyebrow">まち歩きカード</div>
						<h2 class="twkm-machiaruki-manager__title"><?php echo esc_html( $atts['title'] ); ?></h2>
						<p class="twkm-machiaruki-manager__description"><?php echo esc_html( $atts['description'] ); ?></p>
					</div>
				<?php endif; ?>

				<div class="twkm-machiaruki-manager__panel">
					<label class="twkm-machiaruki-field">
						<span class="twkm-machiaruki-field__label">ルート名</span>
						<input type="text" class="twkm-machiaruki-field__input js-twkm-route-title" maxlength="120" placeholder="例: 十和田市中心部をゆったり楽しむまち歩き">
					</label>

					<label class="twkm-machiaruki-field">
						<span class="twkm-machiaruki-field__label">説明文</span>
						<textarea class="twkm-machiaruki-field__textarea js-twkm-route-description" rows="3" maxlength="240" placeholder="このルートの楽しみ方や、立ち寄りたい順番のポイントを短くまとめられます。"></textarea>
					</label>
				</div>

				<div class="twkm-machiaruki-manager__toolbar">
					<div class="twkm-machiaruki-manager__stats">
						<span class="twkm-machiaruki-stat">
							<span class="twkm-machiaruki-stat__label">スポット数</span>
							<strong class="js-twkm-route-count">0スポット</strong>
						</span>
						<span class="twkm-machiaruki-stat">
							<span class="twkm-machiaruki-stat__label">所要時間</span>
							<strong class="js-twkm-route-duration">約0分</strong>
						</span>
					</div>

					<div class="twkm-machiaruki-manager__labels js-twkm-route-labels" aria-live="polite"></div>
				</div>

				<div class="twkm-machiaruki-manager__actions">
					<button type="button" class="twkm-machiaruki-button twkm-machiaruki-button--ghost js-twkm-route-save" disabled aria-disabled="true">保存</button>
					<button type="button" class="twkm-machiaruki-button twkm-machiaruki-button--secondary js-twkm-route-share" disabled aria-disabled="true">共有</button>
					<?php if ( '1' === (string) $atts['show_publish'] ) : ?>
						<button type="button" class="twkm-machiaruki-button twkm-machiaruki-button--primary js-twkm-route-publish" disabled aria-disabled="true">公開用に保存</button>
					<?php endif; ?>
					<a href="#" class="twkm-machiaruki-button twkm-machiaruki-button--map js-twkm-route-map is-disabled" target="_blank" rel="noopener noreferrer" aria-disabled="true">Googleマップでルートをみる</a>
				</div>

				<section class="twkm-machiaruki-picker js-twkm-picker" aria-labelledby="<?php echo esc_attr( $picker_title_id ); ?>">
					<div class="twkm-machiaruki-picker__header">
						<div class="twkm-machiaruki-manager__section-eyebrow">選択候補</div>
						<h3 id="<?php echo esc_attr( $picker_title_id ); ?>" class="twkm-machiaruki-picker__title">追加候補から選ぶ</h3>
						<p class="twkm-machiaruki-picker__description">まだ追加していないスポット候補を見ながら、まとめてまち歩きカードへ追加できます。</p>
					</div>

					<div class="twkm-machiaruki-picker__controls">
						<label class="twkm-machiaruki-field twkm-machiaruki-picker__search">
							<span class="twkm-machiaruki-field__label">キーワード</span>
							<div class="twkm-machiaruki-picker__search-row">
								<input type="search" class="twkm-machiaruki-field__input js-twkm-picker-search" placeholder="スポット名や気になる言葉で探せます">
								<button type="button" class="twkm-machiaruki-button twkm-machiaruki-button--secondary js-twkm-picker-search-button">検索</button>
							</div>
						</label>

						<div class="twkm-machiaruki-picker__filters" role="group" aria-label="表示する投稿タイプ">
							<?php echo $picker_filters; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>

						<div class="twkm-machiaruki-picker__bulk">
							<p class="twkm-machiaruki-picker__selection js-twkm-picker-selected-count">0件選択中</p>
							<button type="button" class="twkm-machiaruki-button twkm-machiaruki-button--secondary js-twkm-picker-add-selected" disabled>選択したスポットを追加</button>
						</div>
					</div>

					<div class="twkm-machiaruki-picker__results js-twkm-picker-results-wrap" aria-live="polite" aria-busy="true">
						<div class="twkm-machiaruki-empty twkm-machiaruki-picker__message twkm-machiaruki-picker__message--loading">
							<div class="twkm-machiaruki-empty__icon" aria-hidden="true">まち歩き</div>
							<h3 class="twkm-machiaruki-empty__title">スポット一覧を準備しています</h3>
							<p class="twkm-machiaruki-empty__message">初回表示では数秒かかる場合があります。開いたままお待ちください。</p>
						</div>
					</div>
				</section>

				<div class="twkm-machiaruki-manager__section-head">
					<div class="twkm-machiaruki-manager__section-eyebrow">選択済み</div>
					<h3 class="twkm-machiaruki-manager__section-title">ルートに追加したスポット</h3>
					<p class="twkm-machiaruki-manager__section-description">追加済みのスポットはここに並びます。スマホでは長押し、PCではドラッグで順番を入れ替えできます。</p>
				</div>

				<div class="twkm-machiaruki-manager__empty js-twkm-route-empty">
					<?php echo $empty_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>

				<div class="twkm-machiaruki-manager__list js-twkm-route-list" aria-live="polite"></div>

				<p class="twkm-machiaruki-manager__map-note js-twkm-route-map-note" hidden></p>
				<p class="twkm-machiaruki-manager__note">スマホでは長押し、PCではドラッグで「並び替え」できます。</p>
			</section>
			<?php
			return ob_get_clean();
		}

		public function render_modal( $atts = array() ) {
			$atts = shortcode_atts(
				array(
					'modal_id'            => 'twkm-machiaruki-modal',
					'title'               => 'まち歩きカード',
					'route_id'            => 0,
					'class'               => '',
					'dialog_class'        => '',
					'manager_class'       => '',
					'manager_title'       => 'まち歩きカードを編集',
					'manager_description' => '追加したスポットを並び替えながら、ルート名や説明文をまとめられます。位置情報がないスポットも追加できますが、地図ルートには含まれない場合があります。',
					'show_publish'        => '0',
					'close_label'         => '閉じる',
				),
				$atts,
				'twkm_machiaruki_modal'
			);

			$this->plugin->enqueue_assets();

			$modal_id        = sanitize_html_class( $atts['modal_id'] );
			$title_id        = $modal_id . '-title';
			$modal_classes   = array_merge(
				array( 'twkm-machiaruki-modal', 'js-twkm-machiaruki-modal' ),
				$this->sanitize_html_classes( $atts['class'] )
			);
			$dialog_classes  = array_merge(
				array( 'twkm-machiaruki-modal__dialog' ),
				$this->sanitize_html_classes( $atts['dialog_class'] )
			);
			$manager_classes = array_merge(
				array( 'twkm-machiaruki-manager--modal-inner' ),
				$this->sanitize_html_classes( $atts['manager_class'] )
			);

			$manager_html = $this->render_manager(
				array(
					'title'        => $atts['manager_title'],
					'description'  => $atts['manager_description'],
					'route_id'     => $atts['route_id'],
					'show_publish' => $atts['show_publish'],
					'context'      => 'modal',
					'hide_header'  => '1',
					'class'        => implode( ' ', $manager_classes ),
				)
			);

			ob_start();
			?>
			<section id="<?php echo esc_attr( $modal_id ); ?>" class="<?php echo esc_attr( implode( ' ', $modal_classes ) ); ?>" data-plugin-version="<?php echo esc_attr( TWKM_Machiaruki_Card_Plugin::VERSION ); ?>" hidden aria-hidden="true">
				<button type="button" class="twkm-machiaruki-modal__backdrop js-twkm-close-modal js-twkm-modal-backdrop" aria-label="<?php echo esc_attr( $atts['close_label'] ); ?>" tabindex="-1"></button>
				<div class="<?php echo esc_attr( implode( ' ', $dialog_classes ) ); ?>" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
					<div class="twkm-machiaruki-modal__header">
						<h2 id="<?php echo esc_attr( $title_id ); ?>" class="twkm-machiaruki-modal__title"><?php echo esc_html( $atts['title'] ); ?></h2>
						<button type="button" class="twkm-machiaruki-button twkm-machiaruki-button--ghost twkm-machiaruki-modal__close js-twkm-close-modal" aria-label="<?php echo esc_attr( $atts['close_label'] ); ?>">
							<span aria-hidden="true">×</span>
						</button>
					</div>
					<div class="twkm-machiaruki-modal__body">
						<?php echo $manager_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</div>
			</section>
			<?php
			return ob_get_clean();
		}

		public function render_picker_items_html( $items, $args = array() ) {
			$items = is_array( $items ) ? $items : array();

			if ( empty( $items ) ) {
				return $this->render_template(
					'card-empty.php',
					array(
						'title'   => '追加できるスポットがまだ見つかりません',
						'message' => '条件を変えて、もう一度お試しください。',
					)
				);
			}

			ob_start();
			?>
			<div class="twkm-machiaruki-picker__grid">
				<?php foreach ( $items as $item ) : ?>
					<?php
					$spot_json    = wp_json_encode( $item );
					$event_status = ! empty( $item['event_status_label'] ) ? sanitize_text_field( $item['event_status_label'] ) : '';
					$event_date   = ! empty( $item['event_date_label'] ) ? sanitize_text_field( $item['event_date_label'] ) : '';
					?>
					<article class="twkm-machiaruki-picker-item js-twkm-picker-item" data-post-id="<?php echo esc_attr( isset( $item['post_id'] ) ? (int) $item['post_id'] : 0 ); ?>">
						<div class="twkm-machiaruki-picker-item__media">
							<img src="<?php echo esc_url( ! empty( $item['thumbnail'] ) ? $item['thumbnail'] : $this->plugin->storage->get_placeholder_image() ); ?>" alt="<?php echo esc_attr( ! empty( $item['title'] ) ? $item['title'] : '' ); ?>">
						</div>
						<div class="twkm-machiaruki-picker-item__content">
							<div class="twkm-machiaruki-picker-item__labels">
								<?php if ( ! empty( $item['post_type_label'] ) ) : ?>
									<span class="twkm-machiaruki-chip twkm-machiaruki-chip--type"><?php echo esc_html( $item['post_type_label'] ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $item['area'] ) ) : ?>
									<span class="twkm-machiaruki-chip twkm-machiaruki-chip--area"><?php echo esc_html( $item['area'] ); ?></span>
								<?php endif; ?>
								<?php if ( $event_status ) : ?>
									<span class="twkm-machiaruki-chip twkm-machiaruki-chip--status"><?php echo esc_html( $event_status ); ?></span>
								<?php endif; ?>
							</div>
							<h4 class="twkm-machiaruki-picker-item__title">
								<a href="<?php echo esc_url( ! empty( $item['permalink'] ) ? $item['permalink'] : '#' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( ! empty( $item['title'] ) ? $item['title'] : '' ); ?></a>
							</h4>
							<?php if ( $event_date ) : ?>
								<p class="twkm-machiaruki-picker-item__meta"><?php echo esc_html( $event_date ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $item['excerpt'] ) ) : ?>
								<p class="twkm-machiaruki-picker-item__excerpt"><?php echo esc_html( $item['excerpt'] ); ?></p>
							<?php endif; ?>
							<div class="twkm-machiaruki-picker-item__actions">
								<label class="twkm-machiaruki-picker-item__select">
									<input type="checkbox" class="js-twkm-picker-select" data-post-id="<?php echo esc_attr( isset( $item['post_id'] ) ? (int) $item['post_id'] : 0 ); ?>" data-spot="<?php echo esc_attr( $spot_json ); ?>">
									<span>選択</span>
								</label>
								<button type="button" class="twkm-machiaruki-button twkm-machiaruki-button--secondary js-twkm-machiaruki-add-button" data-post-id="<?php echo esc_attr( isset( $item['post_id'] ) ? (int) $item['post_id'] : 0 ); ?>" data-spot="<?php echo esc_attr( $spot_json ); ?>" data-add-context="picker" aria-pressed="false">まち歩きカードに追加</button>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
			<?php
			return ob_get_clean();
		}

		public function render_public_card( $route_id, $args = array() ) {
			$route = $this->plugin->storage->get_route_from_post( $route_id );

			if ( empty( $route ) ) {
				return '';
			}

			$this->plugin->enqueue_assets();

			$summary = $this->prepare_card_summary( $route );
			$map     = $this->plugin->map->build_route_map( $route['route_spots'] );
			$related = $this->plugin->recommend->get_related_cards( $route, 3 );

			return $this->render_template(
				'card-single.php',
				array(
					'route'                => $route,
					'card'                 => $summary,
					'map'                  => $map,
					'args'                 => $args,
					'recommendations'      => $related,
					'recommendations_html' => ! empty( $related ) ? $this->render_template( 'card-recommend.php', array( 'items' => $related ) ) : '',
				)
			);
		}

		public function render_cards_list( $atts = array() ) {
			$this->plugin->enqueue_assets();

			$items = $this->get_cards_for_output( $atts );

			if ( empty( $items ) ) {
				if ( $this->is_public_machiaruki_page() ) {
					return $this->render_public_page_fallback();
				}

				return $this->render_template(
					'card-empty.php',
					array(
						'title'   => '公開中のまち歩きカードはまだありません',
						'message' => '公開カードが追加されると、ここに一覧で表示されます。',
					)
				);
			}

			return $this->render_template(
				'card-list.php',
				array(
					'items' => $items,
				)
			);
		}

		public function render_cards_slider( $atts = array() ) {
			$items = $this->get_cards_for_output( $atts );

			if ( empty( $items ) ) {
				return '';
			}

			$this->plugin->enqueue_assets();

			$atts = shortcode_atts(
				array(
					'autoplay' => 'false',
					'loop'     => 'false',
				),
				$atts,
				'twkm_machiaruki_slider'
			);

			return $this->render_template(
				'card-slider.php',
				array(
					'items'    => $items,
					'autoplay' => filter_var( $atts['autoplay'], FILTER_VALIDATE_BOOLEAN ),
					'loop'     => filter_var( $atts['loop'], FILTER_VALIDATE_BOOLEAN ),
				)
			);
		}
		protected function get_cards_for_output( $atts ) {
			$atts = shortcode_atts(
				array(
					'limit'        => 6,
					'area'         => '',
					'category'     => '',
					'orderby'      => 'date',
					'order'        => 'DESC',
					'show_private' => '0',
				),
				$atts
			);

			$allowed_orderby = array( 'date', 'modified', 'title', 'menu_order', 'rand' );
			$orderby         = sanitize_key( $atts['orderby'] );
			if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
				$orderby = 'date';
			}

			$query_args = array(
				'post_type'           => TWKM_Machiaruki_Admin::POST_TYPE,
				'posts_per_page'      => max( 1, absint( $atts['limit'] ) ),
				'orderby'             => $orderby,
				'order'               => 'ASC' === strtoupper( $atts['order'] ) ? 'ASC' : 'DESC',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'post_status'         => array( 'publish' ),
			);

			if ( filter_var( $atts['show_private'], FILTER_VALIDATE_BOOLEAN ) && current_user_can( 'read_private_posts' ) ) {
				$query_args['post_status'] = array( 'publish', 'private' );
			}

			$tax_query = array();

			if ( ! empty( $atts['area'] ) ) {
				$tax_query[] = array(
					'taxonomy' => TWKM_Machiaruki_Admin::AREA_TAXONOMY,
					'field'    => 'slug',
					'terms'    => sanitize_title( $atts['area'] ),
				);
			}

			if ( ! empty( $atts['category'] ) ) {
				$categories = array_map( 'sanitize_title', array_map( 'trim', explode( ',', (string) $atts['category'] ) ) );
				$categories = array_filter( $categories );
				if ( ! empty( $categories ) ) {
					$tax_query[] = array(
						'taxonomy' => TWKM_Machiaruki_Admin::CATEGORY_TAXONOMY,
						'field'    => 'slug',
						'terms'    => $categories,
					);
				}
			}

			if ( ! empty( $tax_query ) ) {
				$query_args['tax_query'] = $tax_query;
			}

			$query = new WP_Query( $query_args );

			if ( ! $query->have_posts() ) {
				return array();
			}

			$items = array();
			foreach ( $query->posts as $post ) {
				$route = $this->plugin->storage->get_route_from_post( $post->ID );
				if ( empty( $route ) ) {
					continue;
				}

				$items[] = $this->prepare_card_summary( $route );
			}

			wp_reset_postdata();

			return $items;
		}

		public function prepare_card_summary( $route ) {
			$raw_route = is_array( $route ) ? $route : array();
			$route     = $this->plugin->storage->normalize_route( $route );
			$gallery   = $this->plugin->storage->build_route_gallery( $route );
			$image     = ! empty( $gallery[0] ) ? $gallery[0] : $this->plugin->storage->get_placeholder_image();
			$spots     = array();

			$permalink = '';
			if ( ! empty( $raw_route['route_permalink'] ) ) {
				$permalink = esc_url_raw( $raw_route['route_permalink'] );
			} elseif ( ! empty( $route['route_permalink'] ) ) {
				$permalink = esc_url_raw( $route['route_permalink'] );
			} elseif ( ! empty( $route['route_id'] ) && is_numeric( $route['route_id'] ) ) {
				$permalink = get_permalink( absint( $route['route_id'] ) );
			}

			$edit_link = '';
			if ( ! empty( $raw_route['route_edit_link'] ) ) {
				$edit_link = esc_url_raw( $raw_route['route_edit_link'] );
			} elseif ( ! empty( $route['route_edit_link'] ) ) {
				$edit_link = esc_url_raw( $route['route_edit_link'] );
			} elseif ( ! empty( $route['route_id'] ) && is_numeric( $route['route_id'] ) && current_user_can( 'edit_post', absint( $route['route_id'] ) ) ) {
				$edit_link = get_edit_post_link( absint( $route['route_id'] ), '' );
			}

			$author_name = '';
			if ( ! empty( $raw_route['route_author_name'] ) ) {
				$author_name = sanitize_text_field( $raw_route['route_author_name'] );
			} elseif ( ! empty( $route['route_author_name'] ) ) {
				$author_name = sanitize_text_field( $route['route_author_name'] );
			}

			if ( ! empty( $route['route_spots'] ) ) {
				foreach ( array_slice( $route['route_spots'], 0, 3 ) as $spot ) {
					if ( ! empty( $spot['title'] ) ) {
						$spots[] = sanitize_text_field( $spot['title'] );
					}
				}
			}

			return array(
				'id'                   => isset( $route['route_id'] ) ? $route['route_id'] : '',
				'title'                => $route['route_title'],
				'description'          => $route['route_description'],
				'permalink'            => $permalink,
				'image'                => $image,
				'gallery_images'       => array_slice( $gallery, 1, 3 ),
				'area'                 => $route['route_area'],
				'labels'               => array_slice( (array) $route['route_labels'], 0, 3 ),
				'spot_count'           => isset( $route['route_spot_count'] ) ? (int) $route['route_spot_count'] : count( $route['route_spots'] ),
				'duration_label'       => $route['route_duration_label'],
				'representative_spots' => $spots,
				'author_name'          => $author_name ? $author_name : get_bloginfo( 'name' ),
				'updated_label'        => ! empty( $route['updated_at'] ) ? wp_date( get_option( 'date_format' ), strtotime( $route['updated_at'] ) ) : '',
				'edit_link'            => $edit_link,
				'route_json'           => wp_json_encode( $route ),
			);
		}

		protected function get_favorite_button_html( $post_id, $atts ) {
			$html = '';

			if ( ! empty( $atts['favorite_html'] ) ) {
				$html = (string) $atts['favorite_html'];
			}

			if ( empty( $html ) && ! empty( $atts['favorite_shortcode'] ) ) {
				$shortcode = html_entity_decode( (string) $atts['favorite_shortcode'], ENT_QUOTES, 'UTF-8' );
				$html      = do_shortcode( $shortcode );
			}

			if ( empty( $html ) ) {
				$html = apply_filters( 'twkm_machiaruki_favorite_button_html', '', $post_id, $atts );
			}

			return $html;
		}

		protected function render_template( $template_name, $data = array() ) {
			$template_path = TWKM_MACHIARUKI_CARD_DIR . 'templates/' . ltrim( $template_name, '/' );

			if ( ! file_exists( $template_path ) ) {
				return '';
			}

			ob_start();
			extract( $data, EXTR_SKIP );
			include $template_path;
			return ob_get_clean();
		}

		protected function sanitize_html_classes( $class_string ) {
			if ( ! is_scalar( $class_string ) ) {
				return array();
			}

			$classes = preg_split( '/\s+/', trim( (string) $class_string ) );
			if ( ! is_array( $classes ) ) {
				return array();
			}

			$classes = array_filter(
				array_map( 'sanitize_html_class', $classes )
			);

			return array_values( array_unique( $classes ) );
		}

		protected function render_picker_filters_html() {
			$post_types = $this->plugin->storage->get_supported_post_types();
			$buttons    = array(
				'<button type="button" class="twkm-machiaruki-chip twkm-machiaruki-picker__filter js-twkm-picker-filter is-active" data-post-type="all" aria-pressed="true">すべて</button>',
			);

			foreach ( $post_types as $post_type ) {
				$buttons[] = sprintf(
					'<button type="button" class="twkm-machiaruki-chip twkm-machiaruki-picker__filter js-twkm-picker-filter" data-post-type="%1$s" aria-pressed="false">%2$s</button>',
					esc_attr( $post_type ),
					esc_html( $this->plugin->storage->get_post_type_label( $post_type ) )
				);
			}

			return implode( '', $buttons );
		}

		protected function is_public_machiaruki_page() {
			if ( function_exists( 'is_page' ) && is_page( array( 'machi-aruki', 'machiaruki' ) ) ) {
				return true;
			}

			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';

			if ( false !== strpos( $request_uri, '/machi-aruki' ) || false !== strpos( $request_uri, '/machiaruki' ) ) {
				return true;
			}

			return false;
		}

		protected function render_public_page_fallback() {
			$this->plugin->enqueue_assets();

			$fallback_items = $this->plugin->storage->get_picker_items(
				array(
					'per_page' => 6,
				)
			);

			return $this->render_template(
				'card-public-fallback.php',
				array(
					'title'         => '公開まち歩きカードはこれから増えていきます',
					'message'       => '公開カードがそろうまでの間は、十和田周辺の注目スポットからまち歩きの雰囲気をご覧ください。',
					'items'         => $fallback_items,
					'support_title' => 'まち歩きカードとは？',
					'support_body'  => '観光スポット、イベント、グルメ、店舗、ホテルを組み合わせて、自分だけのまち歩きルートを作れる機能です。詳しい編集はアプリ内のカード機能からご利用ください。',
				)
			);
		}
	}
}
