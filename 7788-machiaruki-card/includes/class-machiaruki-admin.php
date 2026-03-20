<?php
/**
 * Admin setup.
 * Version: 1.2.2
 *
 * @package 7788-machiaruki-card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'TWKM_Machiaruki_Admin' ) ) {
	class TWKM_Machiaruki_Admin {
		const POST_TYPE         = 'twkm_machiaruki_card';
		const AREA_TAXONOMY     = 'twkm_machiaruki_area';
		const CATEGORY_TAXONOMY = 'twkm_machiaruki_category';

		protected $plugin;

		public function __construct( $plugin ) {
			$this->plugin = $plugin;
		}

		public function register_hooks() {
			add_action( 'init', array( $this, 'register_content_types' ), 9 );
			add_filter( 'manage_edit-' . self::POST_TYPE . '_columns', array( $this, 'register_columns' ) );
			add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
			add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		}

		public function register_content_types() {
			register_post_type(
				self::POST_TYPE,
				array(
					'labels' => array(
						'name'               => 'まち歩きカード',
						'singular_name'      => 'まち歩きカード',
						'add_new_item'       => 'まち歩きカードを追加',
						'edit_item'          => 'まち歩きカードを編集',
						'new_item'           => '新しいまち歩きカード',
						'view_item'          => 'まち歩きカードを見る',
						'search_items'       => 'まち歩きカードを検索',
						'not_found'          => 'まち歩きカードが見つかりません',
						'not_found_in_trash' => 'ゴミ箱にまち歩きカードはありません',
					),
					'public'             => true,
					'show_ui'            => true,
					'show_in_rest'       => true,
					'show_in_menu'       => true,
					'has_archive'        => true,
					'rewrite'            => array(
						'slug' => 'machiaruki-card',
					),
					'supports'           => array(
						'title',
						'editor',
						'excerpt',
						'thumbnail',
						'author',
					),
					'menu_icon'          => 'dashicons-location-alt',
				)
			);

			register_taxonomy(
				self::AREA_TAXONOMY,
				array( self::POST_TYPE ),
				array(
					'labels'       => array(
						'name'          => 'まち歩きエリア',
						'singular_name' => 'まち歩きエリア',
					),
					'public'       => true,
					'show_ui'      => true,
					'show_in_rest' => true,
					'rewrite'      => array(
						'slug' => 'machiaruki-area',
					),
				)
			);

			register_taxonomy(
				self::CATEGORY_TAXONOMY,
				array( self::POST_TYPE ),
				array(
					'labels'       => array(
						'name'          => 'まち歩きラベル',
						'singular_name' => 'まち歩きラベル',
					),
					'public'       => true,
					'show_ui'      => true,
					'show_in_rest' => true,
					'rewrite'      => array(
						'slug' => 'machiaruki-label',
					),
				)
			);
		}

		public function register_columns( $columns ) {
			$columns['twkm_area']       = 'エリア';
			$columns['twkm_spot_count'] = 'スポット数';
			$columns['twkm_duration']   = '所要時間';
			return $columns;
		}

		public function render_column( $column, $post_id ) {
			$route = $this->plugin->storage->get_route_from_post( $post_id );

			switch ( $column ) {
				case 'twkm_area':
					echo esc_html( ! empty( $route['route_area'] ) ? $route['route_area'] : '—' );
					break;
				case 'twkm_spot_count':
					echo esc_html( ! empty( $route['route_spot_count'] ) ? $route['route_spot_count'] . '件' : '0件' );
					break;
				case 'twkm_duration':
					echo esc_html( ! empty( $route['route_duration_label'] ) ? $route['route_duration_label'] : '—' );
					break;
			}
		}

		public function add_meta_boxes() {
			add_meta_box(
				'twkm-machiaruki-summary',
				'まち歩きカード情報',
				array( $this, 'render_meta_box' ),
				self::POST_TYPE,
				'side',
				'default'
			);
		}

		public function render_meta_box( $post ) {
			$route = $this->plugin->storage->get_route_from_post( $post->ID );
			?>
			<p><strong>スポット数:</strong> <?php echo esc_html( ! empty( $route['route_spot_count'] ) ? $route['route_spot_count'] . '件' : '0件' ); ?></p>
			<p><strong>所要時間:</strong> <?php echo esc_html( ! empty( $route['route_duration_label'] ) ? $route['route_duration_label'] : '約0分' ); ?></p>
			<p><strong>エリア:</strong> <?php echo esc_html( ! empty( $route['route_area'] ) ? $route['route_area'] : '未設定' ); ?></p>
			<p><strong>一覧表示:</strong><br>[twkm_machiaruki_cards]</p>
			<p><strong>個別表示:</strong><br>[twkm_machiaruki_card route_id="<?php echo esc_attr( $post->ID ); ?>"]</p>
			<?php
		}
	}
}
