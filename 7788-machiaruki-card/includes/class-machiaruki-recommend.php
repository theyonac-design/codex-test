<?php
/**
 * Recommendation helper.
 * Version: 1.2.2
 *
 * @package 7788-machiaruki-card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'TWKM_Machiaruki_Recommend' ) ) {
	class TWKM_Machiaruki_Recommend {
		protected $plugin;

		public function __construct( $plugin ) {
			$this->plugin = $plugin;
		}

		public function get_related_cards( $route, $limit = 3 ) {
			$route = $this->plugin->storage->normalize_route( $route );

			if ( empty( $route['route_id'] ) || ! is_numeric( $route['route_id'] ) ) {
				return array();
			}

			$args = array(
				'post_type'           => TWKM_Machiaruki_Admin::POST_TYPE,
				'post_status'         => 'publish',
				'posts_per_page'      => max( 1, absint( $limit ) ),
				'post__not_in'        => array( absint( $route['route_id'] ) ),
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			);

			$tax_query = array();

			if ( ! empty( $route['route_area'] ) ) {
				$tax_query[] = array(
					'taxonomy' => TWKM_Machiaruki_Admin::AREA_TAXONOMY,
					'field'    => 'name',
					'terms'    => $route['route_area'],
				);
			}

			if ( ! empty( $route['route_labels'] ) ) {
				$tax_query[] = array(
					'taxonomy' => TWKM_Machiaruki_Admin::CATEGORY_TAXONOMY,
					'field'    => 'name',
					'terms'    => array_slice( (array) $route['route_labels'], 0, 3 ),
				);
			}

			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'OR';
			}

			if ( ! empty( $tax_query ) ) {
				$args['tax_query'] = $tax_query;
			}

			$query = new WP_Query( $args );

			if ( ! $query->have_posts() ) {
				return array();
			}

			$cards = array();
			foreach ( $query->posts as $post ) {
				$related_route = $this->plugin->storage->get_route_from_post( $post->ID );
				if ( empty( $related_route ) ) {
					continue;
				}
				$cards[] = $this->plugin->render->prepare_card_summary( $related_route );
			}

			return $cards;
		}
	}
}
