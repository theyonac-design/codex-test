<?php
/**
 * AJAX handlers.
 * Version: 1.2.8
 *
 * @package 7788-machiaruki-card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'TWKM_Machiaruki_API' ) ) {
	class TWKM_Machiaruki_API {
		protected $plugin;

		public function __construct( $plugin ) {
			$this->plugin = $plugin;
		}

		public function register_hooks() {
			add_action( 'wp_ajax_twkm_machiaruki_picker', array( $this, 'handle_picker' ) );
			add_action( 'wp_ajax_nopriv_twkm_machiaruki_picker', array( $this, 'handle_picker' ) );
		}

		public function handle_picker() {
			$search     = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
			$post_types = array();

			if ( isset( $_POST['post_types'] ) ) {
				$post_types = wp_unslash( $_POST['post_types'] );
			}

			if ( is_string( $post_types ) ) {
				$decoded = json_decode( $post_types, true );
				if ( is_array( $decoded ) ) {
					$post_types = $decoded;
				} else {
					$post_types = array_map( 'trim', explode( ',', $post_types ) );
				}
			}

			$per_page = isset( $_POST['per_page'] ) ? absint( wp_unslash( $_POST['per_page'] ) ) : 18;
			$items    = $this->plugin->storage->get_picker_items(
				array(
					'search'     => $search,
					'post_types' => is_array( $post_types ) ? $post_types : array(),
					'per_page'   => $per_page,
				)
			);
			$html     = $this->plugin->render->render_picker_items_html(
				$items,
				array(
					'search'     => $search,
					'post_types' => is_array( $post_types ) ? $post_types : array(),
				)
			);

			wp_send_json_success(
				array(
					'html' => $html,
				)
			);
		}
	}
}
