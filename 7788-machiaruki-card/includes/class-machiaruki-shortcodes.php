<?php
/**
 * Shortcodes.
 * Version: 1.2.8
 *
 * @package 7788-machiaruki-card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'TWKM_Machiaruki_Shortcodes' ) ) {
	class TWKM_Machiaruki_Shortcodes {
		protected $plugin;

		public function __construct( $plugin ) {
			$this->plugin = $plugin;
		}

		public function register_hooks() {
			add_action( 'init', array( $this, 'register_shortcodes' ), 20 );
		}

		public function register_shortcodes() {
			add_shortcode( 'twkm_machiaruki_add_button', array( $this, 'shortcode_add_button' ) );
			add_shortcode( 'twkm_action_buttons', array( $this, 'shortcode_action_buttons' ) );
			add_shortcode( 'twkm_machiaruki_manager', array( $this, 'shortcode_manager' ) );
			add_shortcode( 'twkm_machiaruki_modal_trigger', array( $this, 'shortcode_modal_trigger' ) );
			add_shortcode( 'twkm_machiaruki_modal', array( $this, 'shortcode_modal' ) );
			add_shortcode( 'twkm_machiaruki_card', array( $this, 'shortcode_card' ) );
			add_shortcode( 'twkm_machiaruki_cards', array( $this, 'shortcode_cards' ) );
			add_shortcode( 'twkm_machiaruki_slider', array( $this, 'shortcode_slider' ) );
		}

		public function shortcode_add_button( $atts = array() ) {
			ob_start();
			echo $this->plugin->render->render_add_button( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return ob_get_clean();
		}

		public function shortcode_action_buttons( $atts = array() ) {
			ob_start();
			echo $this->plugin->render->render_action_buttons( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return ob_get_clean();
		}

		public function shortcode_manager( $atts = array() ) {
			if ( $this->should_hide_editor_shortcodes_on_public_page() ) {
				return '';
			}

			if ( $this->should_render_manager_as_modal_entry( $atts ) ) {
				return $this->render_manager_as_modal_entry( $atts );
			}

			ob_start();
			echo $this->plugin->render->render_manager( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return ob_get_clean();
		}

		public function shortcode_modal_trigger( $atts = array() ) {
			ob_start();
			echo $this->plugin->render->render_modal_trigger( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return ob_get_clean();
		}

		public function shortcode_modal( $atts = array() ) {
			ob_start();
			echo $this->plugin->render->render_modal( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return ob_get_clean();
		}

		public function shortcode_card( $atts = array() ) {
			$atts     = shortcode_atts(
				array(
					'route_id' => 0,
				),
				$atts,
				'twkm_machiaruki_card'
			);
			$route_id = absint( $atts['route_id'] );

			if ( $route_id < 1 && is_singular( TWKM_Machiaruki_Admin::POST_TYPE ) ) {
				$route_id = get_the_ID();
			}

			if ( $route_id < 1 ) {
				return '';
			}

			ob_start();
			echo $this->plugin->render->render_public_card( $route_id, $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return ob_get_clean();
		}

		public function shortcode_cards( $atts = array() ) {
			ob_start();
			echo $this->plugin->render->render_cards_list( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return ob_get_clean();
		}

		public function shortcode_slider( $atts = array() ) {
			ob_start();
			echo $this->plugin->render->render_cards_slider( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return ob_get_clean();
		}

		protected function should_render_manager_as_modal_entry( $atts ) {
			if ( is_admin() || ! function_exists( 'is_page' ) || ! is_page( array( 'machi-aruki', 'machiaruki' ) ) ) {
				return false;
			}

			$display = '';
			if ( isset( $atts['display'] ) && is_scalar( $atts['display'] ) ) {
				$display = sanitize_key( (string) $atts['display'] );
			}

			if ( 'inline' === $display ) {
				return false;
			}

			return true;
		}

		protected function render_manager_as_modal_entry( $atts ) {
			$atts = shortcode_atts(
				array(
					'modal_id'            => 'twkm-machiaruki-modal',
					'manager_title'       => 'まち歩きカードを編集',
					'manager_description' => '追加したスポットを並び替えながら、ルート名や説明文をまとめられます。位置情報がないスポットも追加できますが、地図ルートには含まれない場合があります。',
					'show_publish'        => '0',
				),
				$atts,
				'twkm_machiaruki_manager'
			);

			$trigger = $this->plugin->render->render_modal_trigger(
				array(
					'label'       => 'まち歩きカードを作る',
					'modal_id'    => $atts['modal_id'],
					'class'       => 'twkm-machiaruki-modal-trigger--page-entry twkm-machiaruki-page-entry',
					'lead'        => 'まずはここから始めましょう',
					'description' => '気になるスポットを追加しながら、自分だけのまち歩きカードを作れます。',
				)
			);

			$modal = $this->plugin->render->render_modal(
				array(
					'modal_id'            => $atts['modal_id'],
					'title'               => 'まち歩きカード',
					'manager_title'       => $atts['manager_title'],
					'manager_description' => $atts['manager_description'],
					'show_publish'        => $atts['show_publish'],
				)
			);

			return $trigger . $modal;
		}

		protected function should_hide_editor_shortcodes_on_public_page() {
			if ( is_admin() || ! function_exists( 'is_page' ) ) {
				return false;
			}

			if ( is_page( array( 'machi-aruki', 'machiaruki' ) ) ) {
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
