<?php
/**
 * Google Maps URL helper.
 * Version: 1.2.8
 *
 * @package 7788-machiaruki-card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'TWKM_Machiaruki_Map' ) ) {
	class TWKM_Machiaruki_Map {
		protected $plugin;

		public function __construct( $plugin ) {
			$this->plugin = $plugin;
		}

		public function build_route_map( $spots ) {
			$usable   = array();
			$excluded = array();
			$spots    = array_values( array_filter( (array) $spots, 'is_array' ) );

			foreach ( $spots as $spot ) {
				$location = $this->get_route_location( $spot );
				if ( '' === $location ) {
					$excluded[] = $spot;
					continue;
				}

				$usable[] = array(
					'title'    => ! empty( $spot['title'] ) ? sanitize_text_field( $spot['title'] ) : '',
					'location' => $location,
				);
			}

			$data = array(
				'url'                 => '',
				'valid_spot_count'    => count( $usable ),
				'excluded_spot_count' => count( $excluded ),
				'excluded_titles'     => wp_list_pluck( $excluded, 'title' ),
				'excluded_reasons'    => wp_list_pluck( $excluded, 'map_excluded_reason' ),
				'has_route'           => false,
			);

			if ( empty( $usable ) ) {
				if ( 1 === count( $spots ) ) {
					$fallback_url = $this->plugin->storage->get_spot_map_link_url( $spots[0] );
					if ( '' !== $fallback_url ) {
						$data['url']                 = $fallback_url;
						$data['valid_spot_count']    = 1;
						$data['excluded_spot_count'] = 0;
						$data['excluded_titles']     = array();
						$data['excluded_reasons']    = array();
						$data['has_route']           = true;
						return $data;
					}
				}

				return $data;
			}

			if ( 1 === count( $usable ) ) {
				$data['url'] = add_query_arg(
					array(
						'api'   => 1,
						'query' => $usable[0]['location'],
					),
					'https://www.google.com/maps/search/'
				);

				$data['has_route'] = true;

				return $data;
			}

			$origin      = $usable[0]['location'];
			$destination = $usable[ count( $usable ) - 1 ]['location'];
			$waypoints   = array();
			$max         = (int) apply_filters( 'twkm_machiaruki_max_waypoints', 8 );

			if ( count( $usable ) > 2 ) {
				$middle = array_slice( $usable, 1, -1 );
				foreach ( $middle as $index => $waypoint ) {
					if ( $index >= $max ) {
						break;
					}
					$waypoints[] = $waypoint['location'];
				}
			}

			$args = array(
				'api'         => 1,
				'travelmode'  => apply_filters( 'twkm_machiaruki_map_travelmode', 'walking' ),
				'origin'      => $origin,
				'destination' => $destination,
			);

			if ( ! empty( $waypoints ) ) {
				$args['waypoints'] = implode( '|', $waypoints );
			}

			$data['url']       = add_query_arg( $args, 'https://www.google.com/maps/dir/' );
			$data['has_route'] = true;

			return $data;
		}

		protected function get_route_location( $spot ) {
			$spot  = is_array( $spot ) ? $spot : array();
			$title = ! empty( $spot['title'] ) ? sanitize_text_field( $spot['title'] ) : '';

			$details = $this->plugin->storage->build_map_details(
				isset( $spot['post_type'] ) ? $spot['post_type'] : '',
				array(
					'title'          => $title,
					'googlemaps_url' => isset( $spot['googlemaps_url'] ) ? $spot['googlemaps_url'] : '',
					'address'        => isset( $spot['address'] ) ? $spot['address'] : '',
					'lat'            => isset( $spot['lat'] ) ? $spot['lat'] : '',
					'lng'            => isset( $spot['lng'] ) ? $spot['lng'] : '',
				)
			);

			if ( empty( $details['map_route_value'] ) ) {
				return '';
			}

			return $this->build_preferred_route_query( $title, $details['map_route_value'], $details['map_source_type'] );
		}

		protected function build_preferred_route_query( $title, $route_value, $source_type ) {
			$title       = sanitize_text_field( $title );
			$route_value = sanitize_text_field( $route_value );
			$source_type = sanitize_key( $source_type );

			if ( '' === $route_value ) {
				return '';
			}

			if ( '' === $title || 'latlng' === $source_type ) {
				return $route_value;
			}

			if ( function_exists( 'mb_strpos' ) ) {
				if ( false !== mb_strpos( $route_value, $title ) ) {
					return $route_value;
				}
			} elseif ( false !== strpos( $route_value, $title ) ) {
				return $route_value;
			}

			return trim( $title . ' ' . $route_value );
		}
	}
}
