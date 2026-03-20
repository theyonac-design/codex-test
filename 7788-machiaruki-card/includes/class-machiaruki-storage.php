<?php
/**
 * Storage and data helpers.
 * Version: 1.2.8
 *
 * @package 7788-machiaruki-card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'TWKM_Machiaruki_Storage' ) ) {
	class TWKM_Machiaruki_Storage {
		const META_ROUTE_DATA       = '_twkm_machiaruki_route_data';
		const META_ROUTE_AREA       = '_twkm_machiaruki_route_area';
		const META_ROUTE_LABELS     = '_twkm_machiaruki_route_labels';
		const META_ROUTE_DURATION   = '_twkm_machiaruki_route_duration';
		const META_ROUTE_SPOT_COUNT = '_twkm_machiaruki_route_spot_count';
		const LOCAL_STORAGE_KEY     = 'twkmMachiarukiRouteDraft';

		protected $plugin;

		public function __construct( $plugin ) {
			$this->plugin = $plugin;
		}

		public function get_supported_post_types() {
			$defaults = array(
				'tourist-spot',
				'events',
				'gourmet',
				'shops',
				'hotels',
			);

			$supported = apply_filters( 'twkm_machiaruki_supported_post_types', $defaults );

			if ( ! is_array( $supported ) ) {
				$supported = $defaults;
			}

			$supported = array_filter(
				array_map( 'sanitize_key', $supported )
			);

			return array_values( array_unique( $supported ) );
		}

		public function get_location_meta_map() {
			$defaults = array(
				'tourist-spot' => array(
					'googlemaps_url' => array( '_googlemaps' ),
					'address'        => array( '_location' ),
					'lat'            => array(),
					'lng'            => array(),
					'latlng_raw'     => array(),
				),
				'events'       => array(
					'googlemaps_url' => array( 'googlemaps_url' ),
					'address'        => array( 'venue-location' ),
					'lat'            => array( 'event_latitude' ),
					'lng'            => array( 'event_longitude' ),
					'latlng_raw'     => array( 'event_latlng_raw' ),
				),
				'gourmet'      => array(
					'googlemaps_url' => array( '_field_94040' ),
					'address'        => array( '_field_11686' ),
					'lat'            => array(),
					'lng'            => array(),
					'latlng_raw'     => array(),
				),
				'shops'        => array(
					'googlemaps_url' => array( 'shops_google_map_url' ),
					'address'        => array( 'shops_address' ),
					'lat'            => array( 'shops_lat' ),
					'lng'            => array( 'shops_lng' ),
					'latlng_raw'     => array(),
				),
				'hotels'       => array(
					'googlemaps_url' => array( 'hotel_gmap_url' ),
					'address'        => array( 'hotel_address' ),
					'lat'            => array(),
					'lng'            => array(),
					'latlng_raw'     => array(),
				),
			);

			$map = apply_filters( 'twkm_machiaruki_location_meta_map', $defaults );

			if ( ! is_array( $map ) ) {
				$map = $defaults;
			}

			foreach ( $map as $post_type => $fields ) {
				$post_type = sanitize_key( $post_type );
				$fields    = is_array( $fields ) ? $fields : array();

				$map[ $post_type ] = array(
					'googlemaps_url' => $this->sanitize_meta_key_list( isset( $fields['googlemaps_url'] ) ? $fields['googlemaps_url'] : array() ),
					'address'        => $this->sanitize_meta_key_list( isset( $fields['address'] ) ? $fields['address'] : array() ),
					'lat'            => $this->sanitize_meta_key_list( isset( $fields['lat'] ) ? $fields['lat'] : array() ),
					'lng'            => $this->sanitize_meta_key_list( isset( $fields['lng'] ) ? $fields['lng'] : array() ),
					'latlng_raw'     => $this->sanitize_meta_key_list( isset( $fields['latlng_raw'] ) ? $fields['latlng_raw'] : array() ),
				);
			}

			return $map;
		}

		public function is_supported_post( $post ) {
			$post = get_post( $post );

			if ( ! $post instanceof WP_Post ) {
				return false;
			}

			if ( TWKM_Machiaruki_Admin::POST_TYPE === $post->post_type ) {
				return false;
			}

			return in_array( $post->post_type, $this->get_supported_post_types(), true );
		}

		public function has_location_data( $post_id ) {
			$map_details = $this->get_post_map_details( $post_id );

			return ! empty( $map_details['map_usable'] );
		}

		public function build_spot_payload( $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post instanceof WP_Post ) {
				return array();
			}

			$thumbnail = get_the_post_thumbnail_url( $post, 'large' );
			if ( empty( $thumbnail ) ) {
				$thumbnail = $this->get_placeholder_image();
			}

			$duration    = $this->get_duration_minutes( $post_id );
			$map_details = $this->get_post_map_details( $post_id );

			return $this->normalize_spot(
				array(
					'post_id'             => $post_id,
					'title'               => get_the_title( $post ),
					'permalink'           => get_permalink( $post ),
					'thumbnail'           => $thumbnail,
					'post_type'           => $post->post_type,
					'post_type_label'     => $this->get_post_type_label( $post->post_type ),
					'area'                => $this->get_area( $post_id ),
					'excerpt'             => $this->get_excerpt( $post ),
					'googlemaps_url'      => $map_details['googlemaps_url'],
					'lat'                 => $map_details['lat'],
					'lng'                 => $map_details['lng'],
					'address'             => $map_details['address'],
					'map_source_type'     => $map_details['map_source_type'],
					'map_usable'          => $map_details['map_usable'],
					'map_excluded_reason' => $map_details['map_excluded_reason'],
					'memo'                => '',
					'duration'            => $duration,
					'duration_label'      => $this->format_duration( $duration > 0 ? $duration : 45 ),
					'labels'              => $this->get_labels( $post_id ),
				)
			);
		}

		public function get_picker_items( $args = array() ) {
			$args = wp_parse_args(
				$args,
				array(
					'search'     => '',
					'post_types' => array(),
					'per_page'   => 18,
				)
			);

			$post_types = $this->sanitize_picker_post_types( $args['post_types'] );
			$search     = sanitize_text_field( $args['search'] );
			$per_page   = max( 1, min( 30, absint( $args['per_page'] ) ) );
			$items      = array();
			$seen_ids   = array();
			$paged      = 1;
			$max_passes = 4;

			while ( count( $items ) < $per_page && $paged <= $max_passes ) {
				$query_args          = $this->build_picker_query_args( $post_types, $search, $per_page );
				$query_args['paged'] = $paged;
				$query               = new WP_Query( $query_args );

				if ( ! $query->have_posts() ) {
					wp_reset_postdata();
					break;
				}

				foreach ( $query->posts as $post ) {
					if ( ! $this->is_supported_post( $post ) ) {
						continue;
					}

					if ( isset( $seen_ids[ $post->ID ] ) ) {
						continue;
					}

					$seen_ids[ $post->ID ] = true;

					if ( 'events' === $post->post_type && ! $this->is_event_active_or_upcoming( $post->ID ) ) {
						continue;
					}

					$item = $this->build_spot_payload( $post->ID );

					if ( empty( $item ) ) {
						continue;
					}

					if ( 'events' === $post->post_type ) {
						$item['event_status_label'] = $this->get_event_status_label( $post->ID );
						$item['event_date_label']   = $this->get_event_date_label( $post->ID );
					}

					$items[] = $item;

					if ( count( $items ) >= $per_page ) {
						break;
					}
				}

				wp_reset_postdata();
				$paged++;
			}

			return $items;
		}

		public function get_event_start_timestamp( $post_id ) {
			$value = get_post_meta( $post_id, 'event_date_calender_start', true );
			$value = is_scalar( $value ) ? trim( (string) $value ) : '';

			if ( '' === $value ) {
				return 0;
			}

			$timestamp = strtotime( $value . ' 00:00:00' );

			return false !== $timestamp ? (int) $timestamp : 0;
		}

		public function get_event_end_timestamp( $post_id ) {
			$value = get_post_meta( $post_id, 'event_date_calender_end', true );
			$value = is_scalar( $value ) ? trim( (string) $value ) : '';

			if ( '' === $value ) {
				return 0;
			}

			$timestamp = strtotime( $value . ' 23:59:59' );

			return false !== $timestamp ? (int) $timestamp : 0;
		}

		public function is_event_active_or_upcoming( $post_id ) {
			$post_type = get_post_type( $post_id );

			if ( 'events' !== $post_type ) {
				return true;
			}

			$end_ts = $this->get_event_end_timestamp( $post_id );

			if ( $end_ts < 1 ) {
				return false;
			}

			return $end_ts >= current_time( 'timestamp' );
		}

		public function get_event_status_label( $post_id ) {
			if ( 'events' !== get_post_type( $post_id ) ) {
				return '';
			}

			$now      = current_time( 'timestamp' );
			$start_ts = $this->get_event_start_timestamp( $post_id );
			$end_ts   = $this->get_event_end_timestamp( $post_id );

			if ( $start_ts > $now ) {
				return '開催前';
			}

			if ( $end_ts >= $now ) {
				return '開催中';
			}

			return '終了';
		}

		public function get_event_date_label( $post_id ) {
			if ( 'events' !== get_post_type( $post_id ) ) {
				return '';
			}

			$start = get_post_meta( $post_id, 'event_date_calender_start', true );
			$end   = get_post_meta( $post_id, 'event_date_calender_end', true );
			$start = is_scalar( $start ) ? trim( (string) $start ) : '';
			$end   = is_scalar( $end ) ? trim( (string) $end ) : '';

			if ( '' !== $start && '' !== $end && $start !== $end ) {
				return $start . ' - ' . $end;
			}

			return $start ? $start : $end;
		}

		public function normalize_spot( $spot, $sort_order = 0 ) {
			$post_id   = isset( $spot['post_id'] ) ? absint( $spot['post_id'] ) : 0;
			$post_type = isset( $spot['post_type'] ) ? sanitize_key( $spot['post_type'] ) : '';
			$duration  = isset( $spot['duration'] ) ? $this->parse_duration_to_minutes( $spot['duration'] ) : 0;
			$labels    = array();

			if ( ! empty( $spot['labels'] ) && is_array( $spot['labels'] ) ) {
				foreach ( $spot['labels'] as $label ) {
					$label = sanitize_text_field( wp_unslash( $label ) );
					if ( '' !== $label ) {
						$labels[] = $label;
					}
				}
			}

			$normalized = array(
				'post_id'             => $post_id,
				'title'               => isset( $spot['title'] ) ? sanitize_text_field( wp_unslash( $spot['title'] ) ) : '',
				'permalink'           => isset( $spot['permalink'] ) ? esc_url_raw( $spot['permalink'] ) : '',
				'thumbnail'           => isset( $spot['thumbnail'] ) ? esc_url_raw( $spot['thumbnail'] ) : '',
				'post_type'           => $post_type,
				'post_type_label'     => isset( $spot['post_type_label'] ) ? sanitize_text_field( wp_unslash( $spot['post_type_label'] ) ) : $this->get_post_type_label( $post_type ),
				'area'                => isset( $spot['area'] ) ? sanitize_text_field( wp_unslash( $spot['area'] ) ) : '',
				'excerpt'             => isset( $spot['excerpt'] ) ? sanitize_textarea_field( wp_unslash( $spot['excerpt'] ) ) : '',
				'googlemaps_url'      => isset( $spot['googlemaps_url'] ) ? $this->sanitize_googlemaps_url( $spot['googlemaps_url'] ) : '',
				'lat'                 => $this->sanitize_coordinate( isset( $spot['lat'] ) ? $spot['lat'] : '', 'lat' ),
				'lng'                 => $this->sanitize_coordinate( isset( $spot['lng'] ) ? $spot['lng'] : '', 'lng' ),
				'address'             => isset( $spot['address'] ) ? $this->sanitize_address( $spot['address'] ) : '',
				'map_source_type'     => isset( $spot['map_source_type'] ) ? sanitize_key( $spot['map_source_type'] ) : '',
				'map_usable'          => ! empty( $spot['map_usable'] ),
				'map_excluded_reason' => isset( $spot['map_excluded_reason'] ) ? sanitize_key( $spot['map_excluded_reason'] ) : '',
				'memo'                => isset( $spot['memo'] ) ? sanitize_textarea_field( wp_unslash( $spot['memo'] ) ) : '',
				'duration'            => $duration,
				'duration_label'      => $this->format_duration( $duration > 0 ? $duration : 45 ),
				'sort_order'          => absint( isset( $spot['sort_order'] ) ? $spot['sort_order'] : $sort_order ),
				'labels'              => array_values( array_unique( $labels ) ),
			);

			if ( empty( $normalized['thumbnail'] ) ) {
				$normalized['thumbnail'] = $this->get_placeholder_image();
			}

			if ( empty( $normalized['title'] ) && $post_id ) {
				$normalized['title'] = get_the_title( $post_id );
			}

			if ( empty( $normalized['permalink'] ) && $post_id ) {
				$normalized['permalink'] = get_permalink( $post_id );
			}

			if ( empty( $normalized['post_type'] ) && $post_id ) {
				$normalized['post_type'] = sanitize_key( get_post_type( $post_id ) );
			}

			if ( empty( $normalized['post_type_label'] ) && ! empty( $normalized['post_type'] ) ) {
				$normalized['post_type_label'] = $this->get_post_type_label( $normalized['post_type'] );
			}

			$map_details = $this->build_map_details(
				$normalized['post_type'],
				array(
					'title'          => $normalized['title'],
					'googlemaps_url' => $normalized['googlemaps_url'],
					'address'        => $normalized['address'],
					'lat'            => $normalized['lat'],
					'lng'            => $normalized['lng'],
				)
			);

			$normalized['googlemaps_url']      = $map_details['googlemaps_url'];
			$normalized['address']             = $map_details['address'];
			$normalized['lat']                 = $map_details['lat'];
			$normalized['lng']                 = $map_details['lng'];
			$normalized['map_source_type']     = $map_details['map_source_type'];
			$normalized['map_usable']          = $map_details['map_usable'];
			$normalized['map_excluded_reason'] = $map_details['map_excluded_reason'];

			return $normalized;
		}

		public function get_default_route() {
			$timestamp = gmdate( 'c' );

			return array(
				'route_id'             => 'local-draft',
				'route_title'          => 'わたしのまち歩きルート',
				'route_description'    => '',
				'route_spots'          => array(),
				'route_labels'         => array(),
				'route_area'           => '',
				'route_duration'       => 0,
				'route_duration_label' => '約0分',
				'route_status'         => 'local',
				'created_at'           => $timestamp,
				'updated_at'           => $timestamp,
				'route_spot_count'     => 0,
			);
		}

		public function normalize_route( $route ) {
			$default = $this->get_default_route();
			$route   = is_array( $route ) ? $route : array();

			$normalized = array(
				'route_id'          => isset( $route['route_id'] ) ? sanitize_text_field( wp_unslash( $route['route_id'] ) ) : $default['route_id'],
				'route_title'       => isset( $route['route_title'] ) ? sanitize_text_field( wp_unslash( $route['route_title'] ) ) : $default['route_title'],
				'route_description' => isset( $route['route_description'] ) ? sanitize_textarea_field( wp_unslash( $route['route_description'] ) ) : $default['route_description'],
				'route_spots'       => array(),
				'route_labels'      => array(),
				'route_area'        => isset( $route['route_area'] ) ? sanitize_text_field( wp_unslash( $route['route_area'] ) ) : '',
				'route_duration'    => isset( $route['route_duration'] ) ? absint( $route['route_duration'] ) : 0,
				'route_status'      => isset( $route['route_status'] ) ? sanitize_key( wp_unslash( $route['route_status'] ) ) : $default['route_status'],
				'created_at'        => isset( $route['created_at'] ) ? sanitize_text_field( wp_unslash( $route['created_at'] ) ) : $default['created_at'],
				'updated_at'        => isset( $route['updated_at'] ) ? sanitize_text_field( wp_unslash( $route['updated_at'] ) ) : $default['updated_at'],
			);

			if ( empty( $normalized['route_title'] ) ) {
				$normalized['route_title'] = $default['route_title'];
			}

			if ( empty( $route['route_spots'] ) || ! is_array( $route['route_spots'] ) ) {
				$route['route_spots'] = array();
			}

			foreach ( $route['route_spots'] as $index => $spot ) {
				$normalized['route_spots'][] = $this->normalize_spot( $spot, $index );
			}

			if ( ! empty( $route['route_labels'] ) && is_array( $route['route_labels'] ) ) {
				foreach ( $route['route_labels'] as $label ) {
					$label = sanitize_text_field( wp_unslash( $label ) );
					if ( '' !== $label ) {
						$normalized['route_labels'][] = $label;
					}
				}
			}

			$normalized['route_labels'] = array_values( array_unique( $normalized['route_labels'] ) );

			if ( empty( $normalized['route_labels'] ) ) {
				$normalized['route_labels'] = $this->derive_route_labels( $normalized['route_spots'] );
			}

			if ( empty( $normalized['route_area'] ) ) {
				$normalized['route_area'] = $this->derive_route_area( $normalized['route_spots'] );
			}

			if ( empty( $normalized['route_duration'] ) ) {
				$normalized['route_duration'] = $this->estimate_route_duration_minutes( $normalized['route_spots'] );
			}

			$allowed_statuses = array( 'local', 'draft', 'publish', 'private', 'pending' );
			if ( ! in_array( $normalized['route_status'], $allowed_statuses, true ) ) {
				$normalized['route_status'] = $default['route_status'];
			}

			$normalized['route_duration_label'] = $this->format_duration( $normalized['route_duration'] );
			$normalized['route_spot_count']     = count( $normalized['route_spots'] );

			$passthrough_keys = array(
				'route_permalink',
				'route_featured_image',
				'route_gallery',
				'route_author_name',
				'route_edit_link',
			);

			foreach ( $passthrough_keys as $key ) {
				if ( ! isset( $route[ $key ] ) ) {
					continue;
				}

				if ( 'route_gallery' === $key && is_array( $route[ $key ] ) ) {
					$normalized[ $key ] = array_values(
						array_filter(
							array_map( 'esc_url_raw', $route[ $key ] )
						)
					);
					continue;
				}

				if ( false !== strpos( $key, 'link' ) || false !== strpos( $key, 'permalink' ) || false !== strpos( $key, 'image' ) ) {
					$normalized[ $key ] = esc_url_raw( $route[ $key ] );
					continue;
				}

				$normalized[ $key ] = sanitize_text_field( wp_unslash( $route[ $key ] ) );
			}

			return $normalized;
		}

		public function estimate_route_duration_minutes( $spots ) {
			if ( empty( $spots ) || ! is_array( $spots ) ) {
				return 0;
			}

			$total          = 0;
			$fallback_count = 0;

			foreach ( $spots as $spot ) {
				$minutes = isset( $spot['duration'] ) ? $this->parse_duration_to_minutes( $spot['duration'] ) : 0;
				if ( $minutes > 0 ) {
					$total += $minutes;
				} else {
					$fallback_count++;
				}
			}

			if ( 0 === $total ) {
				return max( count( $spots ), 1 ) * 45;
			}

			return $total + ( $fallback_count * 45 );
		}

		public function format_duration( $minutes ) {
			$minutes = absint( $minutes );

			if ( 0 === $minutes ) {
				return '約0分';
			}

			if ( $minutes < 60 ) {
				return sprintf( '約%d分', $minutes );
			}

			$hours   = (int) floor( $minutes / 60 );
			$remain  = $minutes % 60;
			$message = sprintf( '約%d時間', $hours );

			if ( $remain > 0 ) {
				$message .= sprintf( '%d分', $remain );
			}

			return $message;
		}
		public function get_route_from_post( $post ) {
			$post = get_post( $post );

			if ( ! $post instanceof WP_Post || TWKM_Machiaruki_Admin::POST_TYPE !== $post->post_type ) {
				return array();
			}

			$route = get_post_meta( $post->ID, self::META_ROUTE_DATA, true );
			if ( ! is_array( $route ) ) {
				$route = array();
			}

			$route['route_id']          = (string) $post->ID;
			$route['route_title']       = get_the_title( $post );
			$route['route_description'] = ! empty( $route['route_description'] ) ? $route['route_description'] : ( $post->post_excerpt ? $post->post_excerpt : wp_strip_all_tags( $post->post_content ) );
			$route['route_area']        = get_post_meta( $post->ID, self::META_ROUTE_AREA, true );
			$route['route_labels']      = get_post_meta( $post->ID, self::META_ROUTE_LABELS, true );
			$route['route_duration']    = (int) get_post_meta( $post->ID, self::META_ROUTE_DURATION, true );
			$route['route_status']      = $post->post_status;
			$route['created_at']        = get_post_time( 'c', true, $post );
			$route['updated_at']        = get_post_modified_time( 'c', true, $post );

			$route = $this->normalize_route( $route );

			$route['route_permalink']      = get_permalink( $post );
			$route['route_featured_image'] = get_the_post_thumbnail_url( $post, 'large' );
			$route['route_gallery']        = $this->build_route_gallery( $route, $post->ID );
			$route['route_author_name']    = $this->get_post_author_name( $post );
			$route['route_edit_link']      = current_user_can( 'edit_post', $post->ID ) ? get_edit_post_link( $post->ID, '' ) : '';

			return $route;
		}

		public function save_route_post( $route, $post_id = 0, $status = 'publish' ) {
			$route            = $this->normalize_route( $route );
			$post_id          = absint( $post_id );
			$post_type_object = get_post_type_object( TWKM_Machiaruki_Admin::POST_TYPE );
			$create_cap       = 'edit_posts';
			$publish_cap      = 'publish_posts';

			if ( $post_type_object && ! empty( $post_type_object->cap ) ) {
				if ( ! empty( $post_type_object->cap->create_posts ) ) {
					$create_cap = (string) $post_type_object->cap->create_posts;
				} elseif ( ! empty( $post_type_object->cap->edit_posts ) ) {
					$create_cap = (string) $post_type_object->cap->edit_posts;
				}

				if ( ! empty( $post_type_object->cap->publish_posts ) ) {
					$publish_cap = (string) $post_type_object->cap->publish_posts;
				}
			}

			if ( $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
				return new WP_Error( 'twkm_machiaruki_forbidden', 'このまち歩きカードを更新する権限がありません。' );
			}

			if ( 0 === $post_id && ! current_user_can( $create_cap ) ) {
				return new WP_Error( 'twkm_machiaruki_forbidden', 'まち歩きカードを作成する権限がありません。' );
			}

			$postarr = array(
				'post_type'    => TWKM_Machiaruki_Admin::POST_TYPE,
				'post_title'   => $route['route_title'],
				'post_excerpt' => $route['route_description'],
				'post_content' => $route['route_description'],
				'post_status'  => in_array( $status, array( 'publish', 'draft', 'private', 'pending' ), true ) ? $status : 'publish',
			);

			if ( in_array( $postarr['post_status'], array( 'publish', 'private' ), true ) && ! current_user_can( $publish_cap ) ) {
				return new WP_Error( 'twkm_machiaruki_forbidden', 'まち歩きカードを公開状態で保存する権限がありません。' );
			}

			if ( $post_id > 0 ) {
				$postarr['ID'] = $post_id;
			} else {
				$postarr['post_author'] = get_current_user_id();
			}

			$saved_post_id = wp_insert_post( wp_slash( $postarr ), true );

			if ( is_wp_error( $saved_post_id ) ) {
				return $saved_post_id;
			}

			$route['route_id']     = (string) $saved_post_id;
			$route['route_status'] = $postarr['post_status'];

			update_post_meta( $saved_post_id, self::META_ROUTE_DATA, $route );
			update_post_meta( $saved_post_id, self::META_ROUTE_AREA, $route['route_area'] );
			update_post_meta( $saved_post_id, self::META_ROUTE_LABELS, $route['route_labels'] );
			update_post_meta( $saved_post_id, self::META_ROUTE_DURATION, (int) $route['route_duration'] );
			update_post_meta( $saved_post_id, self::META_ROUTE_SPOT_COUNT, (int) $route['route_spot_count'] );

			if ( ! empty( $route['route_area'] ) ) {
				wp_set_object_terms( $saved_post_id, array( $route['route_area'] ), TWKM_Machiaruki_Admin::AREA_TAXONOMY, false );
			} else {
				wp_set_object_terms( $saved_post_id, array(), TWKM_Machiaruki_Admin::AREA_TAXONOMY, false );
			}

			if ( ! empty( $route['route_labels'] ) ) {
				wp_set_object_terms( $saved_post_id, $route['route_labels'], TWKM_Machiaruki_Admin::CATEGORY_TAXONOMY, false );
			} else {
				wp_set_object_terms( $saved_post_id, array(), TWKM_Machiaruki_Admin::CATEGORY_TAXONOMY, false );
			}

			return $saved_post_id;
		}

		public function get_post_type_label( $post_type ) {
			$object = get_post_type_object( $post_type );

			if ( $object && ! empty( $object->labels->singular_name ) ) {
				return $object->labels->singular_name;
			}

			return ucfirst( (string) $post_type );
		}

		public function get_excerpt( $post ) {
			$post = get_post( $post );

			if ( ! $post instanceof WP_Post ) {
				return '';
			}

			$text = $post->post_excerpt;

			if ( empty( $text ) ) {
				$text = wp_strip_all_tags( $post->post_content );
			}

			$text = preg_replace( '/\s+/u', ' ', (string) $text );
			$text = trim( (string) $text );

			if ( function_exists( 'mb_strimwidth' ) ) {
				return mb_strimwidth( $text, 0, 120, '…', 'UTF-8' );
			}

			return wp_trim_words( $text, 38, '…' );
		}

		public function get_area( $post_id ) {
			$post_type  = get_post_type( $post_id );
			$taxonomies = apply_filters(
				'twkm_machiaruki_area_taxonomies',
				array(
					'area',
					'shops_area',
					'event_area',
					'hotel_area',
					'gourmet_area',
				),
				$post_type,
				$post_id
			);

			$registered_taxonomies = get_object_taxonomies( $post_type, 'names' );
			foreach ( (array) $registered_taxonomies as $taxonomy ) {
				if ( false !== strpos( $taxonomy, 'area' ) ) {
					$taxonomies[] = $taxonomy;
				}
			}

			$taxonomies = array_values( array_unique( array_filter( (array) $taxonomies ) ) );

			foreach ( $taxonomies as $taxonomy ) {
				if ( ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}

				$terms = get_the_terms( $post_id, $taxonomy );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					return sanitize_text_field( $terms[0]->name );
				}
			}

			$meta_keys = apply_filters(
				'twkm_machiaruki_area_meta_keys',
				array(
					'area',
					'region',
					'event_area',
					'shops_area',
					'gourmet_area',
				),
				$post_type,
				$post_id
			);

			return $this->get_first_string_meta( $post_id, $meta_keys );
		}

		public function get_labels( $post_id ) {
			$post_type  = get_post_type( $post_id );
			$taxonomies = apply_filters(
				'twkm_machiaruki_label_taxonomies',
				array(
					'category',
					'post_tag',
					'shops_category',
					'shops_scene',
					'event_category',
					'gourmet_category',
					'hotel_category',
				),
				$post_type,
				$post_id
			);

			$registered = get_object_taxonomies( $post_type, 'names' );
			foreach ( (array) $registered as $taxonomy ) {
				if ( false !== strpos( $taxonomy, 'area' ) ) {
					continue;
				}

				if ( false !== strpos( $taxonomy, 'category' ) || false !== strpos( $taxonomy, 'scene' ) || 'post_tag' === $taxonomy ) {
					$taxonomies[] = $taxonomy;
				}
			}

			$labels = array();

			foreach ( array_values( array_unique( $taxonomies ) ) as $taxonomy ) {
				if ( ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}

				$terms = get_the_terms( $post_id, $taxonomy );
				if ( is_wp_error( $terms ) || empty( $terms ) ) {
					continue;
				}

				foreach ( $terms as $term ) {
					$labels[] = sanitize_text_field( $term->name );
				}
			}

			$labels = array_values( array_unique( array_filter( $labels ) ) );

			return array_slice( $labels, 0, 6 );
		}
		public function get_googlemaps_url( $post_id ) {
			$post_type = sanitize_key( get_post_type( $post_id ) );
			$meta_map  = $this->get_post_type_location_meta( $post_type );

			return $this->get_first_url_meta( $post_id, $meta_map['googlemaps_url'] );
		}

		public function get_lat( $post_id ) {
			$post_type = sanitize_key( get_post_type( $post_id ) );
			$meta_map  = $this->get_post_type_location_meta( $post_type );
			$lat       = $this->get_first_coordinate_meta( $post_id, $meta_map['lat'], 'lat' );

			if ( '' !== $lat ) {
				return $lat;
			}

			$latlng = $this->get_first_latlng_raw( $post_id, $meta_map['latlng_raw'] );

			return isset( $latlng['lat'] ) ? $latlng['lat'] : '';
		}

		public function get_lng( $post_id ) {
			$post_type = sanitize_key( get_post_type( $post_id ) );
			$meta_map  = $this->get_post_type_location_meta( $post_type );
			$lng       = $this->get_first_coordinate_meta( $post_id, $meta_map['lng'], 'lng' );

			if ( '' !== $lng ) {
				return $lng;
			}

			$latlng = $this->get_first_latlng_raw( $post_id, $meta_map['latlng_raw'] );

			return isset( $latlng['lng'] ) ? $latlng['lng'] : '';
		}

		public function get_address( $post_id ) {
			$post_type = sanitize_key( get_post_type( $post_id ) );
			$meta_map  = $this->get_post_type_location_meta( $post_type );

			return $this->get_first_address_meta( $post_id, $meta_map['address'] );
		}

		public function get_post_map_details( $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post instanceof WP_Post ) {
				return $this->build_map_details( '', array() );
			}

			return $this->build_map_details(
				$post->post_type,
				array(
					'googlemaps_url' => $this->get_googlemaps_url( $post->ID ),
					'address'        => $this->get_address( $post->ID ),
					'lat'            => $this->get_lat( $post->ID ),
					'lng'            => $this->get_lng( $post->ID ),
				)
			);
		}

		public function build_map_details( $post_type, $raw_values ) {
			$post_type       = sanitize_key( $post_type );
			$title           = isset( $raw_values['title'] ) ? sanitize_text_field( $raw_values['title'] ) : '';
			$googlemaps_url  = isset( $raw_values['googlemaps_url'] ) ? $this->sanitize_googlemaps_url( $raw_values['googlemaps_url'] ) : '';
			$address         = isset( $raw_values['address'] ) ? $this->sanitize_address( $raw_values['address'] ) : '';
			$lat             = $this->sanitize_coordinate( isset( $raw_values['lat'] ) ? $raw_values['lat'] : '', 'lat' );
			$lng             = $this->sanitize_coordinate( isset( $raw_values['lng'] ) ? $raw_values['lng'] : '', 'lng' );
			$route_location  = '';
			$map_source_type = 'none';
			$map_usable      = false;
			$excluded_reason = 'no_route_source';

			if ( '' !== $googlemaps_url ) {
				$route_location = $this->extract_route_location_from_googlemaps_url( $googlemaps_url );

				if ( '' !== $route_location ) {
					$map_source_type = 'googlemaps_url';
					$map_usable      = true;
					$excluded_reason = '';
				} elseif ( '' !== $address ) {
					$route_location  = $address;
					$map_source_type = 'address';
					$map_usable      = true;
					$excluded_reason = '';
				} elseif ( '' !== $lat && '' !== $lng ) {
					$route_location  = $lat . ',' . $lng;
					$map_source_type = 'latlng';
					$map_usable      = true;
					$excluded_reason = '';
				} else {
					$map_source_type = 'googlemaps_url';
					$excluded_reason = 'invalid_map_data';
				}
			} elseif ( '' !== $address ) {
				$route_location  = $address;
				$map_source_type = 'address';
				$map_usable      = true;
				$excluded_reason = '';
			} elseif ( '' !== $lat && '' !== $lng ) {
				$route_location  = $lat . ',' . $lng;
				$map_source_type = 'latlng';
				$map_usable      = true;
				$excluded_reason = '';
			} elseif ( '' !== $lat || '' !== $lng ) {
				$excluded_reason = 'invalid_map_data';
			}

			return array(
				'post_type'           => $post_type,
				'googlemaps_url'      => $googlemaps_url,
				'address'             => $address,
				'lat'                 => $lat,
				'lng'                 => $lng,
				'map_source_type'     => $map_source_type,
				'map_usable'          => $map_usable,
				'map_excluded_reason' => $excluded_reason,
				'map_route_value'     => $route_location,
				'map_link_url'        => $this->build_spot_map_link_url( $googlemaps_url, $address, $lat, $lng, $title ),
			);
		}

		public function build_spot_map_link_url( $googlemaps_url, $address = '', $lat = '', $lng = '', $title = '' ) {
			$googlemaps_url = $this->sanitize_googlemaps_url( $googlemaps_url );
			if ( '' !== $googlemaps_url ) {
				return $googlemaps_url;
			}

			$address = $this->sanitize_address( $address );
			if ( '' !== $address ) {
				return $this->build_google_maps_search_url( $this->build_preferred_map_query( $title, $address ) );
			}

			$lat = $this->sanitize_coordinate( $lat, 'lat' );
			$lng = $this->sanitize_coordinate( $lng, 'lng' );
			if ( '' !== $lat && '' !== $lng ) {
				return $this->build_google_maps_search_url( $lat . ',' . $lng );
			}

			return '';
		}

		protected function build_preferred_map_query( $title, $location ) {
			$title    = sanitize_text_field( $title );
			$location = sanitize_text_field( $location );

			if ( '' === $title || '' === $location ) {
				return $location;
			}

			if ( function_exists( 'mb_strpos' ) ) {
				if ( false !== mb_strpos( $location, $title ) ) {
					return $location;
				}
			} elseif ( false !== strpos( $location, $title ) ) {
				return $location;
			}

			return trim( $title . ' ' . $location );
		}

		public function get_spot_map_link_url( $spot ) {
			$spot = is_array( $spot ) ? $spot : array();

			return $this->build_spot_map_link_url(
				isset( $spot['googlemaps_url'] ) ? $spot['googlemaps_url'] : '',
				isset( $spot['address'] ) ? $spot['address'] : '',
				isset( $spot['lat'] ) ? $spot['lat'] : '',
				isset( $spot['lng'] ) ? $spot['lng'] : ''
			);
		}

		public function extract_route_location_from_googlemaps_url( $url ) {
			$url = $this->sanitize_googlemaps_url( $url );

			if ( '' === $url ) {
				return '';
			}

			$parsed = wp_parse_url( $url );
			if ( empty( $parsed ) || empty( $parsed['host'] ) ) {
				return '';
			}

			$query_args = array();
			if ( ! empty( $parsed['query'] ) ) {
				wp_parse_str( $parsed['query'], $query_args );
			}

			$query_keys = array( 'q', 'query', 'destination', 'daddr' );
			foreach ( $query_keys as $query_key ) {
				if ( empty( $query_args[ $query_key ] ) ) {
					continue;
				}

				$value = $this->clean_route_location( $query_args[ $query_key ] );
				if ( '' !== $value ) {
					return $value;
				}
			}

			foreach ( array( 'll', 'sll' ) as $coord_key ) {
				if ( empty( $query_args[ $coord_key ] ) ) {
					continue;
				}

				$pair = $this->parse_coordinate_pair( $query_args[ $coord_key ] );
				if ( ! empty( $pair['lat'] ) && ! empty( $pair['lng'] ) ) {
					return $pair['lat'] . ',' . $pair['lng'];
				}
			}

			$path = isset( $parsed['path'] ) ? (string) $parsed['path'] : '';

			if ( preg_match( '#/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)#', $path, $matches ) ) {
				$lat = $this->sanitize_coordinate( $matches[1], 'lat' );
				$lng = $this->sanitize_coordinate( $matches[2], 'lng' );
				if ( '' !== $lat && '' !== $lng ) {
					return $lat . ',' . $lng;
				}
			}

			if ( preg_match( '#/place/([^/]+)#', $path, $matches ) ) {
				$value = rawurldecode( str_replace( '+', ' ', $matches[1] ) );
				$value = $this->clean_route_location( $value );
				if ( '' !== $value ) {
					return $value;
				}
			}

			return '';
		}

		public function get_map_excluded_reason_label( $reason ) {
			$reason = sanitize_key( $reason );

			$labels = array(
				'missing_googlemaps_url' => 'Googleマップリンク未設定',
				'missing_address'        => '住所未設定',
				'invalid_map_data'       => '地図用データ不足',
				'no_route_source'        => 'Googleマップリンクと住所が未設定です',
			);

			return isset( $labels[ $reason ] ) ? $labels[ $reason ] : '地図用データ不足';
		}

		public function get_duration_minutes( $post_id ) {
			$keys = apply_filters(
				'twkm_machiaruki_duration_meta_keys',
				array(
					'duration',
					'stay_duration',
					'estimated_time',
					'time_required',
					'minutes',
				),
				$post_id
			);

			foreach ( (array) $keys as $key ) {
				$value   = get_post_meta( $post_id, $key, true );
				$minutes = $this->parse_duration_to_minutes( $value );
				if ( $minutes > 0 ) {
					return $minutes;
				}
			}

			return 0;
		}
		public function build_route_gallery( $route, $post_id = 0 ) {
			$images = array();

			if ( $post_id ) {
				$featured = get_the_post_thumbnail_url( $post_id, 'large' );
				if ( $featured ) {
					$images[] = $featured;
				}
			}

			if ( ! empty( $route['route_featured_image'] ) ) {
				$images[] = esc_url_raw( $route['route_featured_image'] );
			}

			if ( ! empty( $route['route_spots'] ) && is_array( $route['route_spots'] ) ) {
				foreach ( $route['route_spots'] as $spot ) {
					if ( ! empty( $spot['thumbnail'] ) ) {
						$images[] = esc_url_raw( $spot['thumbnail'] );
					}
				}
			}

			$images = array_values( array_unique( array_filter( $images ) ) );

			if ( empty( $images ) ) {
				$images[] = $this->get_placeholder_image();
			}

			return array_slice( $images, 0, 4 );
		}

		public function get_placeholder_image() {
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800" viewBox="0 0 1200 800"><rect fill="#f3efe7" width="1200" height="800"/><circle cx="280" cy="180" r="120" fill="#e1d5c5"/><circle cx="950" cy="160" r="90" fill="#d7e6d8"/><rect x="120" y="430" width="960" height="190" rx="48" fill="#ffffff"/><text x="50%" y="46%" text-anchor="middle" fill="#615544" font-family="sans-serif" font-size="44">まち歩きカード</text><text x="50%" y="54%" text-anchor="middle" fill="#94816f" font-family="sans-serif" font-size="24">Towakomyu</text></svg>';

			return 'data:image/svg+xml;charset=utf-8,' . rawurlencode( $svg );
		}

		public function get_post_author_name( $post ) {
			$post = get_post( $post );

			if ( ! $post instanceof WP_Post ) {
				return get_bloginfo( 'name' );
			}

			$name = get_the_author_meta( 'display_name', $post->post_author );

			return $name ? $name : get_bloginfo( 'name' );
		}

		protected function sanitize_picker_post_types( $post_types ) {
			$post_types = is_array( $post_types ) ? $post_types : array( $post_types );
			$post_types = array_values(
				array_filter(
					array_map( 'sanitize_key', $post_types )
				)
			);

			$supported  = $this->get_supported_post_types();
			$post_types = array_values( array_intersect( $supported, $post_types ) );

			if ( empty( $post_types ) ) {
				return $supported;
			}

			return $post_types;
		}

		protected function build_picker_query_args( $post_types, $search, $per_page ) {
			$post_types     = $this->sanitize_picker_post_types( $post_types );
			$search         = sanitize_text_field( $search );
			$per_page       = max( 1, min( 30, absint( $per_page ) ) );
			$is_events_only = 1 === count( $post_types ) && 'events' === $post_types[0];

			$query_args = array(
				'post_type'           => $post_types,
				'post_status'         => 'publish',
				'posts_per_page'      => $is_events_only ? max( $per_page * 3, 36 ) : max( $per_page * 4, 48 ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'suppress_filters'    => false,
			);

			if ( '' !== $search ) {
				$query_args['s'] = $search;
			}

			if ( $is_events_only ) {
				$query_args['meta_key']   = 'event_date_calender_start';
				$query_args['orderby']    = 'meta_value';
				$query_args['meta_type']  = 'DATE';
				$query_args['order']      = 'ASC';
				$query_args['meta_query'] = array(
					array(
						'key'     => 'event_date_calender_end',
						'value'   => current_time( 'Y-m-d' ),
						'compare' => '>=',
						'type'    => 'DATE',
					),
				);
			} else {
				$query_args['orderby'] = 'date';
				$query_args['order']   = 'DESC';
			}

			return $query_args;
		}

		protected function sanitize_meta_key_list( $keys ) {
			$keys = is_array( $keys ) ? $keys : array( $keys );

			return array_values(
				array_filter(
					array_map(
						static function ( $key ) {
							$key = is_scalar( $key ) ? trim( (string) $key ) : '';
							return '' !== $key ? $key : '';
						},
						$keys
					)
				)
			);
		}

		protected function get_post_type_location_meta( $post_type ) {
			$post_type = sanitize_key( $post_type );
			$meta_map  = $this->get_location_meta_map();

			if ( isset( $meta_map[ $post_type ] ) && is_array( $meta_map[ $post_type ] ) ) {
				return $meta_map[ $post_type ];
			}

			return array(
				'googlemaps_url' => array(),
				'address'        => array(),
				'lat'            => array(),
				'lng'            => array(),
				'latlng_raw'     => array(),
			);
		}

		protected function get_first_url_meta( $post_id, $keys ) {
			foreach ( (array) $keys as $key ) {
				$value = get_post_meta( $post_id, $key, true );
				$value = $this->sanitize_googlemaps_url( $value );

				if ( '' !== $value ) {
					return $value;
				}
			}

			return '';
		}

		protected function get_first_address_meta( $post_id, $keys ) {
			foreach ( (array) $keys as $key ) {
				$value = get_post_meta( $post_id, $key, true );
				$value = $this->sanitize_address( $value );

				if ( '' !== $value ) {
					return $value;
				}
			}

			return '';
		}

		protected function get_first_coordinate_meta( $post_id, $keys, $component ) {
			foreach ( (array) $keys as $key ) {
				$value      = get_post_meta( $post_id, $key, true );
				$coordinate = $this->sanitize_coordinate( $value, $component );

				if ( '' !== $coordinate ) {
					return $coordinate;
				}
			}

			return '';
		}

		protected function get_first_latlng_raw( $post_id, $keys ) {
			foreach ( (array) $keys as $key ) {
				$value = get_post_meta( $post_id, $key, true );
				$pair  = $this->parse_coordinate_pair( $value );

				if ( ! empty( $pair['lat'] ) && ! empty( $pair['lng'] ) ) {
					return $pair;
				}
			}

			return array();
		}

		protected function get_first_string_meta( $post_id, $keys ) {
			foreach ( (array) $keys as $key ) {
				$value = get_post_meta( $post_id, $key, true );
				if ( is_scalar( $value ) ) {
					$value = sanitize_text_field( wp_unslash( (string) $value ) );
					if ( '' !== $value ) {
						return $value;
					}
				}
			}

			return '';
		}

		protected function derive_route_area( $spots ) {
			$areas = array();

			foreach ( (array) $spots as $spot ) {
				if ( empty( $spot['area'] ) ) {
					continue;
				}

				$area = sanitize_text_field( $spot['area'] );
				if ( ! isset( $areas[ $area ] ) ) {
					$areas[ $area ] = 0;
				}
				$areas[ $area ]++;
			}

			if ( empty( $areas ) ) {
				return '';
			}

			arsort( $areas );

			return (string) key( $areas );
		}

		protected function derive_route_labels( $spots ) {
			$labels = array();

			foreach ( (array) $spots as $spot ) {
				if ( empty( $spot['labels'] ) || ! is_array( $spot['labels'] ) ) {
					continue;
				}

				foreach ( $spot['labels'] as $label ) {
					$label = sanitize_text_field( $label );
					if ( '' === $label ) {
						continue;
					}
					if ( ! isset( $labels[ $label ] ) ) {
						$labels[ $label ] = 0;
					}
					$labels[ $label ]++;
				}
			}

			if ( empty( $labels ) ) {
				return array();
			}

			arsort( $labels );

			return array_slice( array_keys( $labels ), 0, 6 );
		}

		protected function sanitize_googlemaps_url( $value ) {
			$value = is_scalar( $value ) ? trim( (string) $value ) : '';
			if ( '' === $value ) {
				return '';
			}

			$value = esc_url_raw( $value );
			if ( '' === $value ) {
				return '';
			}

			$parsed = wp_parse_url( $value );
			if ( empty( $parsed['host'] ) ) {
				return '';
			}

			$host = strtolower( (string) $parsed['host'] );
			if ( ! $this->is_allowed_google_maps_host( $host ) ) {
				return '';
			}

			return $value;
		}

		protected function is_allowed_google_maps_host( $host ) {
			$host = strtolower( trim( (string) $host ) );

			if ( '' === $host ) {
				return false;
			}

			if ( preg_match( '/(^|\.)goo\.gl$/', $host ) ) {
				return true;
			}

			return (bool) preg_match( '/(^|\.)google\.[a-z.]+$/', $host );
		}

		protected function sanitize_address( $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ' ', array_map( 'strval', $value ) );
			}

			$value = is_scalar( $value ) ? sanitize_text_field( wp_unslash( (string) $value ) ) : '';
			$value = trim( preg_replace( '/\s+/u', ' ', $value ) );

			return is_numeric( $value ) ? '' : $value;
		}

		protected function clean_route_location( $value ) {
			$pair = $this->parse_coordinate_pair( $value );
			if ( ! empty( $pair['lat'] ) && ! empty( $pair['lng'] ) ) {
				return $pair['lat'] . ',' . $pair['lng'];
			}

			return $this->sanitize_address( rawurldecode( (string) $value ) );
		}

		protected function parse_coordinate_pair( $value ) {
			if ( is_array( $value ) ) {
				if ( isset( $value['lat'], $value['lng'] ) ) {
					$lat = $this->sanitize_coordinate( $value['lat'], 'lat' );
					$lng = $this->sanitize_coordinate( $value['lng'], 'lng' );

					if ( '' !== $lat && '' !== $lng ) {
						return array(
							'lat' => $lat,
							'lng' => $lng,
						);
					}
				}

				return array();
			}

			$value = trim( (string) $value );
			if ( '' === $value ) {
				return array();
			}

			if ( preg_match( '/(-?\d+(?:\.\d+)?)\s*[, ]\s*(-?\d+(?:\.\d+)?)/', $value, $matches ) ) {
				$lat = $this->sanitize_coordinate( $matches[1], 'lat' );
				$lng = $this->sanitize_coordinate( $matches[2], 'lng' );

				if ( '' !== $lat && '' !== $lng ) {
					return array(
						'lat' => $lat,
						'lng' => $lng,
					);
				}
			}

			return array();
		}

		protected function sanitize_coordinate( $value, $component ) {
			if ( '' === $value || null === $value ) {
				return '';
			}

			if ( is_array( $value ) ) {
				return '';
			}

			$value = str_replace( '、', '.', trim( (string) $value ) );
			$value = str_replace( ',', '.', $value );

			if ( ! is_numeric( $value ) ) {
				return '';
			}

			$value = (float) $value;

			if ( 'lat' === $component && ( $value < -90 || $value > 90 ) ) {
				return '';
			}

			if ( 'lng' === $component && ( $value < -180 || $value > 180 ) ) {
				return '';
			}

			return (string) $value;
		}

		protected function build_google_maps_search_url( $query ) {
			$query = $this->clean_route_location( $query );

			if ( '' === $query ) {
				return '';
			}

			return add_query_arg(
				array(
					'api'   => 1,
					'query' => $query,
				),
				'https://www.google.com/maps/search/'
			);
		}

		protected function parse_duration_to_minutes( $value ) {
			if ( empty( $value ) && '0' !== (string) $value ) {
				return 0;
			}

			if ( is_numeric( $value ) ) {
				return absint( round( (float) $value ) );
			}

			$value   = wp_strip_all_tags( wp_unslash( (string) $value ) );
			$minutes = 0;

			if ( preg_match( '/(\d+(?:\.\d+)?)\s*時間/u', $value, $matches ) ) {
				$minutes += (int) round( (float) $matches[1] * 60 );
			}

			if ( preg_match( '/(\d+)\s*分/u', $value, $matches ) ) {
				$minutes += (int) $matches[1];
			}

			if ( 0 === $minutes && preg_match( '/^(\d+):(\d+)$/', $value, $matches ) ) {
				$minutes = ( (int) $matches[1] * 60 ) + (int) $matches[2];
			}

			if ( 0 === $minutes && preg_match( '/^\d+(?:\.\d+)?$/', $value ) ) {
				$minutes = (int) round( (float) $value );
			}

			return absint( $minutes );
		}
	}
}
