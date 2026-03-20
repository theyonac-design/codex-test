<?php
/**
 * Version: 1.2.8
 * Plugin Name: 7788-machiaruki-card
 * Description: とわこみゅ向け まち歩きカード機能 MUプラグイン
 * Author: Towakomyu
 *
 * Shortcodes:
 * [twkm_machiaruki_add_button]
 *   各詳細ページで「まち歩きカードに追加」ボタンのみを表示
 *
 * [twkm_action_buttons]
 *   各詳細ページで「この記事を保存」と「まち歩きカードに追加」を横並び表示
 *   例: [twkm_action_buttons favorite_shortcode='[existing_favorite_button]']
 *
 * [twkm_machiaruki_manager]
 *   まち歩きカードの編集・並び替え・削除・保存 UI を表示
 *
 * [twkm_machiaruki_modal_trigger]
 *   まち歩きカード編集モーダルを開くボタンを表示
 *
 * [twkm_machiaruki_modal]
 *   hidden 状態のまち歩きカード編集モーダル本体を表示
 *
 * [twkm_machiaruki_card route_id="123"]
 *   公開済みの個別まち歩きカードを表示
 *
 * [twkm_machiaruki_cards]
 * [twkm_machiaruki_cards limit="6" area="" category="" orderby="date" order="DESC" show_private="0"]
 *   公開済みのまち歩きカード一覧を表示
 *
 * [twkm_machiaruki_slider]
 * [twkm_machiaruki_slider limit="6" area="" category="" autoplay="true" loop="false"]
 *   公開済みのまち歩きカードをスライダー表示
 *
 * Placement Guide:
 * - 観光 / イベント / ホテル / グルメ / 店舗などの詳細テンプレート: [twkm_action_buttons]
 * - まち歩き作成ページ: [twkm_machiaruki_manager]
 * - 固定ページ「まち歩き」(slug: machi-aruki): [twkm_machiaruki_cards limit="12"] [twkm_machiaruki_slider limit="6" autoplay="false" loop="false"]
 *   編集UIはPWAやアプリ内導線から開く前提で、公開ページ本文には常設しない
 * - トップ / 特集ページ: [twkm_machiaruki_slider]
 * - まち歩き一覧ページ: [twkm_machiaruki_cards]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'TWKM_Machiaruki_Card_Plugin' ) ) {
	final class TWKM_Machiaruki_Card_Plugin {
		const VERSION = '1.2.8';

		protected static $instance = null;
		protected $assets_registered = false;
		protected $assets_enqueued   = false;

		public $storage;
		public $api;
		public $render;
		public $map;
		public $shortcodes;
		public $recommend;
		public $admin;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		private function __construct() {
			$this->define_constants();
			$this->load_files();
			$this->boot_services();
			$this->register_hooks();
		}

		private function define_constants() {
			if ( ! defined( 'TWKM_MACHIARUKI_CARD_FILE' ) ) {
				define( 'TWKM_MACHIARUKI_CARD_FILE', __FILE__ );
			}

			if ( ! defined( 'TWKM_MACHIARUKI_CARD_DIR' ) ) {
				define( 'TWKM_MACHIARUKI_CARD_DIR', trailingslashit( __DIR__ ) );
			}

			if ( ! defined( 'TWKM_MACHIARUKI_CARD_URL' ) ) {
				define( 'TWKM_MACHIARUKI_CARD_URL', trailingslashit( content_url( 'mu-plugins/7788-machiaruki-card' ) ) );
			}
		}

		private function load_files() {
			require_once TWKM_MACHIARUKI_CARD_DIR . 'includes/class-machiaruki-storage.php';
			require_once TWKM_MACHIARUKI_CARD_DIR . 'includes/class-machiaruki-api.php';
			require_once TWKM_MACHIARUKI_CARD_DIR . 'includes/class-machiaruki-render.php';
			require_once TWKM_MACHIARUKI_CARD_DIR . 'includes/class-machiaruki-map.php';
			require_once TWKM_MACHIARUKI_CARD_DIR . 'includes/class-machiaruki-shortcodes.php';
			require_once TWKM_MACHIARUKI_CARD_DIR . 'includes/class-machiaruki-recommend.php';
			require_once TWKM_MACHIARUKI_CARD_DIR . 'includes/class-machiaruki-admin.php';
		}

		private function boot_services() {
			$this->storage    = new TWKM_Machiaruki_Storage( $this );
			$this->map        = new TWKM_Machiaruki_Map( $this );
			$this->render     = new TWKM_Machiaruki_Render( $this );
			$this->api        = new TWKM_Machiaruki_API( $this );
			$this->recommend  = new TWKM_Machiaruki_Recommend( $this );
			$this->shortcodes = new TWKM_Machiaruki_Shortcodes( $this );
			$this->admin      = new TWKM_Machiaruki_Admin( $this );

			$services = array(
				$this->api,
				$this->shortcodes,
				$this->admin,
			);

			foreach ( $services as $service ) {
				if ( is_object( $service ) && method_exists( $service, 'register_hooks' ) ) {
					$service->register_hooks();
				}
			}
		}

		private function register_hooks() {
			add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
			add_filter( 'the_content', array( $this, 'inject_card_content' ), 20 );
			add_filter( 'body_class', array( $this, 'filter_body_class' ) );
			add_action( 'wp_head', array( $this, 'output_public_page_head_fixes' ), 99 );
			add_action( 'wp_footer', array( $this, 'output_public_page_footer_fixes' ), 99 );
		}

		public function register_assets() {
			if ( $this->assets_registered ) {
				return;
			}

			$style_version  = $this->get_asset_version( 'assets/css/machiaruki-card.css' );
			$script_version = $this->get_asset_version( 'assets/js/machiaruki-card.js' );

			wp_register_style(
				$this->get_style_handle(),
				TWKM_MACHIARUKI_CARD_URL . 'assets/css/machiaruki-card.css',
				array(),
				$style_version
			);

			wp_register_script(
				$this->get_script_handle(),
				TWKM_MACHIARUKI_CARD_URL . 'assets/js/machiaruki-card.js',
				array(),
				$script_version,
				true
			);

			$this->assets_registered = true;
		}

		public function enqueue_assets() {
			$this->register_assets();

			wp_enqueue_style( $this->get_style_handle() );
			wp_enqueue_script( $this->get_script_handle() );

			if ( ! $this->assets_enqueued ) {
				wp_localize_script(
					$this->get_script_handle(),
					'twkmMachiarukiSettings',
					$this->get_frontend_settings()
				);
				$this->assets_enqueued = true;
			}
		}

		public function get_frontend_settings() {
			return array(
				'version'           => self::VERSION,
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'pickerAction'      => 'twkm_machiaruki_picker',
				'pickerPerPage'     => 18,
				'storageKey'        => TWKM_Machiaruki_Storage::LOCAL_STORAGE_KEY,
				'routeOwnershipKey' => 'twkmMachiarukiOwnedRoutes',
				'isLoggedIn'        => is_user_logged_in(),
				'canPublish'        => false,
				'isAdminViewer'     => current_user_can( 'manage_options' ),
				'strings'           => array(
					'added'               => 'まち歩きカードに追加しました',
					'alreadyAdded'        => 'すでに追加されています',
					'addError'            => '追加できませんでした',
					'removed'             => 'まち歩きカードから外しました',
					'saved'               => '保存しました',
					'sortSaved'           => '並び順を更新しました',
					'mapUnavailable'      => '地図ルートに使えるスポットがまだ足りません',
					'publishSaved'        => 'この端末に保存しました',
					'publishError'        => 'この更新版では公開用の保存は利用できません',
					'shareCopied'         => '共有用の内容をコピーしました',
					'shareUnavailable'    => '共有できませんでした',
					'deleteConfirm'       => 'このスポットをまち歩きカードから外しますか？',
					'localOnly'           => 'この端末に保存しました',
					'loginRequired'       => '公開用の保存はログイン後に利用できます',
					'openEditor'          => 'まち歩きカードを開く',
					'closeEditor'         => 'まち歩きカードを閉じる',
					'editorOpened'        => 'まち歩きカードを開きました',
					'editorClosed'        => 'まち歩きカードを閉じました',
					'emptyTitle'          => '気になるスポットを追加して、自分だけのまち歩きカードを作りましょう',
					'emptyMessage'        => '各ページの「まち歩きカードに追加」から登録できます',
					'mapExcludedTemplate' => '%d件は位置情報が見つからず地図ルートに含めていません',
					'mapPartialNotice'    => '一部スポットは地図ルートに含まれない場合があります',
					'pickerLoading'       => 'スポット一覧を読み込んでいます...',
					'pickerLoadError'     => '読み込みに失敗しました。再読み込みしてください',
					'pickerLoadErrorBody' => '通信状況をご確認のうえ、もう一度お試しください。',
					'pickerEmptyTitle'    => '条件に合うスポットが見つかりません',
					'pickerEmptyMessage'  => 'キーワードやカテゴリを変えて、もう一度お試しください。',
					'pickerRetry'         => '再読み込みする',
					'pickerLoadingTitle'  => 'スポット一覧を準備しています',
					'pickerLoadingBody'   => '初回表示では数秒かかる場合があります。開いたままお待ちください。',
					'pickerSearchLabel'   => 'キーワード',
					'pickerNoSelection'   => '追加するスポットを選んでください',
					'pickerBulkAdded'     => '%d件をまち歩きカードに追加しました',
					'pickerAddedState'    => '追加済み',
					'pickerAddSelected'   => '選択したスポットを追加',
					'pickerSelection'     => '%d件選択中',
					'pageEntryLabel'      => 'まち歩きカードを作る',
					'pageEntryTitle'      => 'まずはここから始めましょう',
					'pageEntryDescription'=> '気になるスポットを追加しながら、自分だけのまち歩きカードを作れます。',
					'modalTitle'          => 'まち歩きカード',
					'mapNeedMoreSpots'    => 'スポットをもう1件追加すると、Googleマップでルートを表示できます。',
					'publicEmptyTitle'    => '公開中のまち歩きカードはまだありません',
				),
			);
		}

		public function inject_card_content( $content ) {
			if ( is_admin() || ! in_the_loop() || ! is_main_query() ) {
				return $content;
			}

			if ( is_page( array( 'machi-aruki', 'machiaruki' ) ) ) {
				$content = $this->cleanup_machiaruki_page_content( $content );
			}

			if ( ! is_singular( TWKM_Machiaruki_Admin::POST_TYPE ) ) {
				return $content;
			}

			if ( has_shortcode( (string) $content, 'twkm_machiaruki_card' ) ) {
				return $content;
			}

			$post = get_post();

			if ( ! $post instanceof WP_Post ) {
				return $content;
			}

			return $content . $this->render->render_public_card(
				$post->ID,
				array(
					'context' => 'singular',
				)
			);
		}

		public function filter_body_class( $classes ) {
			$classes = is_array( $classes ) ? $classes : array();

			if ( $this->is_public_machiaruki_page() ) {
				$classes[] = 'twkm-machiaruki-public-page';
			}

			return array_values( array_unique( $classes ) );
		}

		public function output_public_page_head_fixes() {
			if ( ! $this->is_public_machiaruki_page() ) {
				return;
			}
			?>
			<meta name="twkm-machiaruki-version" content="<?php echo esc_attr( self::VERSION ); ?>">
			<style id="twkm-machiaruki-public-fixes-<?php echo esc_attr( str_replace( '.', '-', self::VERSION ) ); ?>">
				.twkm-machiaruki-public-page .twkm-machiaruki-manager:not(.twkm-machiaruki-manager--modal-inner) {
					display: none !important;
				}
			</style>
			<?php
		}

		public function output_public_page_footer_fixes() {
			if ( ! $this->is_public_machiaruki_page() ) {
				return;
			}
			?>
			<script data-no-optimize="1" data-rocket-bypass="true" id="twkm-machiaruki-public-fixes-js-<?php echo esc_attr( str_replace( '.', '-', self::VERSION ) ); ?>">
				(function () {
					var path = (window.location.pathname || '').replace(/\/+$/, '');
					if (path !== '/machi-aruki' && path !== '/machiaruki') {
						return;
					}

					function clean() {
						var scope = document.querySelector('.elementor-location-single, main, .site-main, .entry-content, article, .elementor-widget-theme-post-content') || document.body;

						scope.querySelectorAll('.twkm-machiaruki-manager:not(.twkm-machiaruki-manager--modal-inner), .js-twkm-machiaruki-manager:not(.twkm-machiaruki-manager--modal-inner)').forEach(function (el) {
							el.remove();
						});

						Array.from(scope.querySelectorAll('p, div, span, h1, h2, h3, h4, h5, h6')).forEach(function (el) {
							if (el.children.length === 0 && (el.textContent || '').trim() === 'H') {
								el.remove();
							}
						});
					}

					if (document.readyState === 'loading') {
						document.addEventListener('DOMContentLoaded', clean, { once: true });
					} else {
						clean();
					}
				}());
			</script>
			<?php
		}

		private function cleanup_machiaruki_page_content( $content ) {
			$patterns = array(
				'@<(p|div|span|h1|h2|h3|h4|h5|h6)([^>]*)>\s*H\s*</\1>@i',
				'@(?:\A|\R)\s*H\s*(?:\R|\z)@u',
			);

			$content = (string) $content;

			foreach ( $patterns as $pattern ) {
				$content = preg_replace( $pattern, '', $content );
			}

			return $content;
		}

		private function get_asset_version( $relative_path ) {
			$file_path = TWKM_MACHIARUKI_CARD_DIR . ltrim( $relative_path, '/' );

			if ( file_exists( $file_path ) ) {
				$modified = filemtime( $file_path );
				if ( false !== $modified ) {
					return self::VERSION . '.' . (string) $modified;
				}
			}

			return self::VERSION;
		}

		private function get_style_handle() {
			return 'twkm-machiaruki-card-' . str_replace( '.', '-', self::VERSION );
		}

		private function get_script_handle() {
			return 'twkm-machiaruki-card-' . str_replace( '.', '-', self::VERSION );
		}

		private function is_public_machiaruki_page() {
			if ( function_exists( 'is_page' ) && is_page( array( 'machi-aruki', 'machiaruki' ) ) ) {
				return true;
			}

			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';

			if ( false !== strpos( $request_uri, '/machi-aruki' ) || false !== strpos( $request_uri, '/machiaruki' ) ) {
				return true;
			}

			return false;
		}
	}
}

if ( ! function_exists( 'twkm_machiaruki_card' ) ) {
	function twkm_machiaruki_card() {
		return TWKM_Machiaruki_Card_Plugin::instance();
	}
}

twkm_machiaruki_card();
