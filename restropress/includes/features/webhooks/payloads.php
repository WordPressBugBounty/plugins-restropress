<?php
/**
 * Webhooks: the JSON payload (envelope and resource objects) and samples.
 *
 * The schema is public and versioned (api_version "1"). Field lists are
 * documented in docs/payloads.md: keep both in sync. Core's own REST order
 * formatter is deliberately not reused, it merges the raw payment meta
 * (purchase key, customer IP) into its output.
 *
 * @package RestroPress
 * @since   3.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Money value as a JSON number.
 *
 * @param mixed $amount Amount.
 * @return float
 */
function rpress_webhooks_money( $amount ) {
	return round( (float) $amount, 2 );
}

/**
 * Stored titles and term names are HTML-encoded: JSON consumers want plain text.
 *
 * @param mixed $text Text.
 * @return string
 */
function rpress_webhooks_plain_text( $text ) {
	return html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Order resource.
 *
 * @param int $order_id Order (payment) ID.
 * @return array|null Null when the order does not exist.
 */
function rpress_webhooks_order_resource( $order_id ) {
	$order_id = absint( $order_id );
	$post     = $order_id ? get_post( $order_id ) : null;
	if ( ! $post || 'rpress_payment' !== $post->post_type ) {
		return null;
	}
	$payment = new RPRESS_Payment( $order_id );
	if ( empty( $payment->ID ) ) {
		return null;
	}

	$order_status = (string) rpress_get_order_status( $order_id );
	$status_label = rpress_get_order_status_label( $order_status );
	$meta         = is_array( $payment->payment_meta ) ? $payment->payment_meta : array();
	$user_info    = is_array( $payment->user_info ) ? $payment->user_info : array();

	$phone = '';
	if ( ! empty( $meta['phone'] ) && is_scalar( $meta['phone'] ) ) {
		$phone = (string) $meta['phone'];
	} elseif ( ! empty( $user_info['phone'] ) && is_scalar( $user_info['phone'] ) ) {
		$phone = (string) $user_info['phone'];
	}

	$codes = array();
	foreach ( explode( ',', (string) ( is_array( $payment->discounts ) ? implode( ',', $payment->discounts ) : $payment->discounts ) ) as $code ) {
		$code = trim( $code );
		if ( '' !== $code && 'none' !== strtolower( $code ) ) {
			$codes[] = $code;
		}
	}

	$address     = null;
	$raw_address = get_post_meta( $order_id, '_rpress_delivery_address', true );
	if ( is_array( $raw_address ) ) {
		$candidate = array(
			'address'  => isset( $raw_address['address'] ) ? (string) $raw_address['address'] : '',
			'flat'     => isset( $raw_address['flat'] ) ? (string) $raw_address['flat'] : '',
			'city'     => isset( $raw_address['city'] ) ? (string) $raw_address['city'] : '',
			'postcode' => isset( $raw_address['postcode'] ) ? (string) $raw_address['postcode'] : '',
		);
		if ( '' !== implode( '', $candidate ) ) {
			$address = $candidate;
		}
	}

	$items = array();
	foreach ( (array) $payment->cart_details as $line ) {
		if ( ! is_array( $line ) ) {
			continue;
		}
		$addons = array();
		if ( ! empty( $line['addon_items'] ) && is_array( $line['addon_items'] ) ) {
			foreach ( $line['addon_items'] as $addon ) {
				if ( ! is_array( $addon ) || empty( $addon['addon_id'] ) ) {
					continue;
				}
				$addons[] = array(
					'id'       => (int) $addon['addon_id'],
					'name'     => isset( $addon['addon_item_name'] ) ? (string) $addon['addon_item_name'] : '',
					'quantity' => isset( $addon['quantity'] ) ? max( 1, (int) $addon['quantity'] ) : 1,
					'price'    => rpress_webhooks_money( isset( $addon['price'] ) ? $addon['price'] : 0 ),
				);
			}
		}
		$items[] = array(
			'id'          => isset( $line['id'] ) ? (int) $line['id'] : 0,
			'name'        => isset( $line['name'] ) ? (string) $line['name'] : '',
			'variation'   => isset( $line['variation_name'] ) ? (string) $line['variation_name'] : '',
			'quantity'    => isset( $line['quantity'] ) ? (int) $line['quantity'] : 1,
			'unit_price'  => rpress_webhooks_money( isset( $line['item_price'] ) ? $line['item_price'] : 0 ),
			'line_total'  => rpress_webhooks_money( isset( $line['price'] ) ? $line['price'] : 0 ),
			'instruction' => isset( $line['instruction'] ) ? (string) $line['instruction'] : '',
			'addons'      => $addons,
		);
	}

	// WordPress leaves post_date_gmt at zero for posts in status pending (an unpaid order): use the local date then.
	$date_created = rpress_webhooks_iso_from_mysql( $post->post_date_gmt );
	if ( null === $date_created ) {
		$date_created = rpress_webhooks_iso_from_mysql( $post->post_date, true );
	}

	return array(
		'id'                 => (int) $payment->ID,
		'number'             => (string) $payment->number,
		'payment_status'     => 'publish' === $payment->status ? 'paid' : (string) $payment->status,
		'order_status'       => $order_status,
		'order_status_label' => $status_label ? (string) $status_label : $order_status,
		'service_type'       => (string) get_post_meta( $order_id, '_rpress_delivery_type', true ),
		'service_date'       => (string) get_post_meta( $order_id, '_rpress_delivery_date', true ),
		'service_time'       => (string) get_post_meta( $order_id, '_rpress_delivery_time', true ),
		'currency'           => (string) $payment->currency,
		'subtotal'           => rpress_webhooks_money( $payment->subtotal ),
		'tax'                => rpress_webhooks_money( $payment->tax ),
		'fees_total'         => rpress_webhooks_money( $payment->fees_total ),
		'discount_codes'     => $codes,
		'total'              => rpress_webhooks_money( $payment->total ),
		'gateway'            => (string) $payment->gateway,
		'note'               => (string) get_post_meta( $order_id, '_rpress_order_note', true ),
		'customer'           => array(
			'id'         => (int) $payment->customer_id,
			'user_id'    => (int) $payment->user_id,
			'email'      => (string) $payment->email,
			'first_name' => (string) $payment->first_name,
			'last_name'  => (string) $payment->last_name,
			'phone'      => $phone,
		),
		'delivery_address'   => $address,
		'items'              => $items,
		'date_created'       => $date_created,
		'date_completed'     => rpress_webhooks_iso_from_mysql( (string) $payment->completed_date, true ),
	);
}

/**
 * Customer resource.
 *
 * @param int $customer_id Customer ID (rpress_customers table).
 * @return array|null
 */
function rpress_webhooks_customer_resource( $customer_id ) {
	$customer_id = absint( $customer_id );
	$customer    = $customer_id ? new RPRESS_Customer( $customer_id ) : null;
	if ( ! $customer || empty( $customer->id ) ) {
		return null;
	}
	return array(
		'id'             => (int) $customer->id,
		'user_id'        => (int) $customer->user_id,
		'email'          => (string) $customer->email,
		'name'           => (string) $customer->name,
		'purchase_count' => (int) $customer->purchase_count,
		'purchase_value' => rpress_webhooks_money( $customer->purchase_value ),
		// The customers table stores this in UTC.
		'date_created'   => rpress_webhooks_iso_from_mysql( (string) $customer->date_created ),
	);
}

/**
 * Reservation resource.
 *
 * @param int $reservation_id Reservation ID.
 * @return array|null
 */
function rpress_webhooks_reservation_resource( $reservation_id ) {
	$reservation = function_exists( 'rpress_get_reservation' ) ? rpress_get_reservation( absint( $reservation_id ) ) : false;
	if ( ! is_array( $reservation ) ) {
		return null;
	}
	return array(
		'id'     => (int) $reservation['id'],
		'status' => (string) $reservation['status'],
		'date'   => (string) $reservation['date'],
		'time'   => rpress_webhooks_iso( $reservation['ts'] ),
		'party'  => (int) $reservation['party'],
		'name'   => (string) $reservation['name'],
		'phone'  => (string) $reservation['phone'],
		'email'  => (string) $reservation['email'],
		'note'   => (string) $reservation['note'],
	);
}

/**
 * Menu item resource.
 *
 * @param int $item_id Fooditem ID.
 * @return array|null
 */
function rpress_webhooks_menu_item_resource( $item_id ) {
	$item_id = absint( $item_id );
	$post    = $item_id ? get_post( $item_id ) : null;
	if ( ! $post || 'fooditem' !== $post->post_type ) {
		return null;
	}
	$sku = function_exists( 'rpress_get_fooditem_sku' ) ? rpress_get_fooditem_sku( $item_id ) : '';
	if ( ! is_scalar( $sku ) || '-' === $sku ) {
		$sku = '';
	}
	$terms      = wp_get_post_terms( $item_id, 'food-category', array( 'fields' => 'names' ) );
	$categories = is_array( $terms ) ? array_values( array_map( 'rpress_webhooks_plain_text', $terms ) ) : array();
	$permalink  = get_permalink( $item_id );

	return array(
		'id'           => (int) $item_id,
		'name'         => rpress_webhooks_plain_text( $post->post_title ),
		'sku'          => (string) $sku,
		'price'        => rpress_webhooks_money( rpress_get_fooditem_price( $item_id ) ),
		'stock_status' => 'unavailable' === get_post_meta( $item_id, 'rp_stock_status', true ) ? 'unavailable' : 'available',
		'permalink'    => $permalink ? (string) $permalink : '',
		'categories'   => $categories,
	);
}

/**
 * Resource object (public API): the same object the payloads carry.
 *
 * @param string $type      order, customer, reservation or menu_item.
 * @param int    $object_id Object ID.
 * @return array|null
 */
function rpress_webhooks_get_resource( $type, $object_id ) {
	switch ( $type ) {
		case 'order':
			return rpress_webhooks_order_resource( $object_id );
		case 'customer':
			return rpress_webhooks_customer_resource( $object_id );
		case 'reservation':
			return rpress_webhooks_reservation_resource( $object_id );
		case 'menu_item':
			return rpress_webhooks_menu_item_resource( $object_id );
	}
	return null;
}

/**
 * Wrap event data in the envelope every delivery uses.
 *
 * @param string $event Event key or 'ping'.
 * @param array  $data  Data object.
 * @param array  $extra Optional { id, created_at (unix seconds), test }.
 * @return array
 */
function rpress_webhooks_envelope( $event, $data, $extra = array() ) {
	return array(
		'id'          => ! empty( $extra['id'] ) ? (string) $extra['id'] : rpress_webhooks_generate_event_id(),
		'event'       => (string) $event,
		'api_version' => '1',
		'created_at'  => rpress_webhooks_iso( ! empty( $extra['created_at'] ) ? (int) $extra['created_at'] : time() ),
		'site_url'    => home_url( '/' ),
		'test'        => ! empty( $extra['test'] ),
		'data'        => $data,
	);
}

/**
 * Build the envelope for an event about one object.
 *
 * @param string $event     Event key.
 * @param int    $object_id Order, customer, reservation or fooditem ID.
 * @param array  $extra     Optional { id, created_at, test, previous_status, status }.
 * @return array|WP_Error
 */
function rpress_webhooks_build_payload( $event, $object_id, $extra = array() ) {
	$extra = is_array( $extra ) ? $extra : array();
	if ( 'ping' === $event ) {
		return rpress_webhooks_envelope(
			'ping',
			array( 'message' => __( 'Test delivery from RestroPress Webhooks. If you can read this, the endpoint works.', 'restropress' ) ),
			array_merge( $extra, array( 'test' => true ) )
		);
	}

	$type = rpress_webhooks_event_resource( $event );
	if ( '' === $type ) {
		return new WP_Error( 'rpress_webhooks_unknown_event', __( 'Unknown event.', 'restropress' ) );
	}
	$resource = rpress_webhooks_get_resource( $type, $object_id );
	if ( null === $resource ) {
		return new WP_Error( 'rpress_webhooks_object_missing', __( 'The record this event is about no longer exists.', 'restropress' ) );
	}

	$data = array();
	if ( 'order.status_changed' === $event ) {
		$data['previous_status'] = isset( $extra['previous_status'] ) && '' !== $extra['previous_status'] ? (string) $extra['previous_status'] : null;
		$data['status']          = isset( $extra['status'] ) && '' !== $extra['status'] ? (string) $extra['status'] : $resource['order_status'];
	}
	$data[ $type ] = $resource;

	return rpress_webhooks_envelope( $event, $data, $extra );
}

/* ------------------------------------------------------------------ */
/* Samples (GET /samples/<event>, Zapier test data, docs)              */
/* ------------------------------------------------------------------ */

/**
 * Built-in example resource, used when the store has no real record yet.
 *
 * @param string $type order, customer, reservation or menu_item.
 * @return array
 */
function rpress_webhooks_static_resource( $type ) {
	switch ( $type ) {
		case 'customer':
			return array(
				'id'             => 12,
				'user_id'        => 0,
				'email'          => 'alex@example.com',
				'name'           => 'Alex Sample',
				'purchase_count' => 3,
				'purchase_value' => 86.4,
				'date_created'   => '2026-01-15T18:05:00Z',
			);
		case 'reservation':
			return array(
				'id'     => 3301,
				'status' => 'pending',
				'date'   => '2026-01-20',
				'time'   => '2026-01-20T19:30:00Z',
				'party'  => 4,
				'name'   => 'Alex Sample',
				'phone'  => '+1 555 0100',
				'email'  => 'alex@example.com',
				'note'   => 'Window table if possible',
			);
		case 'menu_item':
			return array(
				'id'           => 215,
				'name'         => 'Margherita Pizza',
				'sku'          => 'PIZ-MARG',
				'price'        => 11.5,
				'stock_status' => 'available',
				'permalink'    => home_url( '/fooditem/margherita-pizza/' ),
				'categories'   => array( 'Pizza' ),
			);
	}
	return array(
		'id'                 => 1042,
		'number'             => '1042',
		'payment_status'     => 'paid',
		'order_status'       => 'pending',
		'order_status_label' => 'Pending',
		'service_type'       => 'delivery',
		'service_date'       => '2026-01-15',
		'service_time'       => '7:30pm',
		'currency'           => 'USD',
		'subtotal'           => 25.5,
		'tax'                => 2.04,
		'fees_total'         => 3,
		'discount_codes'     => array( 'WELCOME10' ),
		'total'              => 27.99,
		'gateway'            => 'cash_on_delivery',
		'note'               => 'Ring the bell twice',
		'customer'           => array(
			'id'         => 12,
			'user_id'    => 0,
			'email'      => 'alex@example.com',
			'first_name' => 'Alex',
			'last_name'  => 'Sample',
			'phone'      => '+1 555 0100',
		),
		'delivery_address'   => array(
			'address'  => '12 Sample Street',
			'flat'     => 'Apt 4',
			'city'     => 'Springfield',
			'postcode' => '12345',
		),
		'items'              => array(
			array(
				'id'          => 215,
				'name'        => 'Margherita Pizza',
				'variation'   => 'Large',
				'quantity'    => 2,
				'unit_price'  => 11.5,
				'line_total'  => 25.5,
				'instruction' => 'Well done',
				'addons'      => array(
					array(
						'id'       => 31,
						'name'     => 'Extra cheese',
						'quantity' => 2,
						'price'    => 1.25,
					),
				),
			),
		),
		'date_created'       => '2026-01-15T18:05:00Z',
		'date_completed'     => null,
	);
}

/**
 * Make a sample resource agree with the event it illustrates.
 *
 * @param string $event    Event key.
 * @param array  $resource Resource.
 * @return array { resource, extra }
 */
function rpress_webhooks_shape_sample( $event, $resource ) {
	$extra = array();
	switch ( $event ) {
		case 'order.status_changed':
			$status = (string) $resource['order_status'];
			$model  = function_exists( 'rpress_order_status_model' ) ? rpress_order_status_model() : array();
			$order  = isset( $model[ $status ]['order'] ) ? (int) $model[ $status ]['order'] : 0;
			$prev   = null;
			$best   = -1;
			if ( 'pending' !== $status ) {
				$prev = 'pending';
				foreach ( $model as $key => $row ) {
					$services = isset( $row['services'] ) ? (array) $row['services'] : array();
					$fits     = '' === $resource['service_type'] || in_array( $resource['service_type'], $services, true );
					if ( $fits && $order < 99 && isset( $row['order'] ) && $row['order'] < $order && $row['order'] > $best && ! in_array( $key, array( 'out_for_delivery', 'failed', 'refunded' ), true ) ) {
						$best = (int) $row['order'];
						$prev = $key;
					}
				}
			}
			$extra = array(
				'previous_status' => $prev,
				'status'          => $status,
			);
			break;
		case 'menu_item.sold_out':
			$resource['stock_status'] = 'unavailable';
			break;
		case 'menu_item.back_in_stock':
			$resource['stock_status'] = 'available';
			break;
	}
	return array(
		'resource' => $resource,
		'extra'    => $extra,
	);
}

/**
 * One built-in sample envelope for an event.
 *
 * @param string $event Event key.
 * @return array
 */
function rpress_webhooks_static_sample( $event ) {
	$type     = rpress_webhooks_event_resource( $event );
	$resource = rpress_webhooks_static_resource( $type );
	if ( 'order.refunded' === $event ) {
		$resource['payment_status']     = 'refunded';
		$resource['order_status']       = 'cancelled';
		$resource['order_status_label'] = 'Cancelled';
	} elseif ( 'order.cancelled' === $event ) {
		$resource['order_status']       = 'cancelled';
		$resource['order_status_label'] = 'Cancelled';
	} elseif ( 'order.status_changed' === $event ) {
		$resource['order_status']       = 'accepted';
		$resource['order_status_label'] = 'Accepted';
	} elseif ( 'order.created' === $event ) {
		$resource['payment_status'] = 'pending';
	}
	$shaped = rpress_webhooks_shape_sample( $event, $resource );
	$data   = array();
	if ( 'order.status_changed' === $event ) {
		$data['previous_status'] = $shaped['extra']['previous_status'];
		$data['status']          = $shaped['extra']['status'];
	}
	$data[ $type ] = $shaped['resource'];
	return rpress_webhooks_envelope(
		$event,
		$data,
		array(
			'id'         => 'evt_' . substr( md5( 'sample:' . $event ), 0, 24 ),
			'created_at' => 1768500300,
			'test'       => true,
		)
	);
}

/**
 * IDs of the most recent real records that fit an event.
 *
 * @param string $event Event key.
 * @param int    $limit How many.
 * @return int[]
 */
function rpress_webhooks_recent_object_ids( $event, $limit = 3 ) {
	$type = rpress_webhooks_event_resource( $event );
	if ( 'customer' === $type ) {
		$ids = array();
		foreach ( (array) RPRESS()->customers->get_customers(
			array(
				'number'  => $limit,
				'orderby' => 'id',
				'order'   => 'DESC',
			)
		) as $customer ) {
			$ids[] = (int) $customer->id;
		}
		return $ids;
	}

	$args = array(
		'posts_per_page'   => $limit,
		'orderby'          => 'ID',
		'order'            => 'DESC',
		'fields'           => 'ids',
		'no_found_rows'    => true,
		'suppress_filters' => true,
	);
	if ( 'reservation' === $type ) {
		$args['post_type']   = 'rpress_reservation';
		$args['post_status'] = 'any';
		return array_map( 'intval', get_posts( $args ) );
	}
	if ( 'menu_item' === $type ) {
		$args['post_type']   = 'fooditem';
		$args['post_status'] = 'publish';
		return array_map( 'intval', get_posts( $args ) );
	}

	$args['post_type']   = 'rpress_payment';
	$args['post_status'] = array_keys( rpress_get_payment_statuses() );
	$narrow              = $args;
	if ( 'order.paid' === $event ) {
		$narrow['post_status'] = 'publish';
	} elseif ( 'order.refunded' === $event ) {
		$narrow['post_status'] = 'refunded';
	} elseif ( 'order.cancelled' === $event ) {
		$narrow['meta_key']   = '_order_status'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$narrow['meta_value'] = 'cancelled'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	}
	$ids = get_posts( $narrow );
	if ( ! $ids && $narrow !== $args ) {
		$ids = get_posts( $args );
	}
	return array_map( 'intval', $ids );
}

/**
 * Sample envelopes for an event (public API): up to three built from the most
 * recent real records, or one built-in sample when the store has none.
 *
 * @param string $event Event key.
 * @return array List of envelopes, or an empty array for an unknown event.
 */
function rpress_webhooks_samples( $event ) {
	if ( ! isset( rpress_webhooks_events()[ (string) $event ] ) ) {
		return array();
	}
	$type    = rpress_webhooks_event_resource( $event );
	$samples = array();
	foreach ( rpress_webhooks_recent_object_ids( $event, 3 ) as $object_id ) {
		$resource = rpress_webhooks_get_resource( $type, $object_id );
		if ( null === $resource ) {
			continue;
		}
		$shaped = rpress_webhooks_shape_sample( $event, $resource );
		$data   = array();
		if ( 'order.status_changed' === $event ) {
			$data['previous_status'] = $shaped['extra']['previous_status'];
			$data['status']          = $shaped['extra']['status'];
		}
		$data[ $type ] = $shaped['resource'];
		$samples[]     = rpress_webhooks_envelope(
			$event,
			$data,
			array(
				'id'   => 'evt_' . substr( md5( 'sample:' . $event . ':' . $object_id ), 0, 24 ),
				'test' => true,
			)
		);
	}
	if ( ! $samples ) {
		$samples[] = rpress_webhooks_static_sample( $event );
	}
	return $samples;
}
