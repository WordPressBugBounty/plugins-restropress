<?php
/**
 * Reports Intelligence partial.
 *
 * @package RPRESS
 * @since   3.3
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Render an Overview metric card.
 *
 * @since 3.3
 *
 * @param string $label Label.
 * @param string $value Value.
 * @param array  $delta Delta data.
 * @param string $icon Dashicon slug.
 * @param string $tone Tone class.
 * @return void
 */
function rpress_reports_metric_card( $label, $value, $delta, $icon, $tone ) {
	?>
	<div class="rp-card rp-reports-metric tone-<?php echo esc_attr( $tone ); ?>">
		<span class="rp-metric-icon" aria-hidden="true">
			<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
		</span>
		<span class="rp-reports-metric-body">
			<span class="rp-reports-metric-label"><?php echo esc_html( $label ); ?></span>
			<strong><?php echo esc_html( $value ); ?></strong>
			<small class="rp-delta-<?php echo esc_attr( $delta['tone'] ); ?>"><?php echo esc_html( $delta['label'] ); ?></small>
		</span>
	</div>
	<?php
}

/**
 * Render compact SVG trend chart.
 *
 * @since 3.3
 *
 * @param array  $trend Trend rows.
 * @param string $primary_key Primary series key.
 * @param string $secondary_key Secondary series key.
 * @return void
 */
function rpress_reports_trend_svg( $trend, $primary_key, $secondary_key ) {
	$primary = wp_list_pluck( $trend, $primary_key );
	$secondary = wp_list_pluck( $trend, $secondary_key );
	?>
	<svg viewBox="0 0 760 260" preserveAspectRatio="none" focusable="false" aria-hidden="true">
		<path class="rp-reports-grid-line" d="M0 52H760M0 104H760M0 156H760M0 208H760" />
		<polyline class="rp-reports-line is-blue" points="<?php echo esc_attr( rpress_reports_svg_points( $primary, 760, 260 ) ); ?>" />
		<polyline class="rp-reports-line is-green" points="<?php echo esc_attr( rpress_reports_svg_points( $secondary, 760, 260 ) ); ?>" />
	</svg>
	<?php
}

/**
 * Convert values to SVG polyline points.
 *
 * @since 3.3
 *
 * @param array $values Values.
 * @param int   $width SVG width.
 * @param int   $height SVG height.
 * @return string
 */
function rpress_reports_svg_points( $values, $width, $height ) {
	$values = array_values( array_map( 'floatval', (array) $values ) );
	$count = count( $values );
	if ( 0 === $count ) {
		return '';
	}

	$max = max( $values );
	$max = $max > 0 ? $max : 1;
	$step = $count > 1 ? $width / ( $count - 1 ) : $width;
	$points = array();

	foreach ( $values as $index => $value ) {
		$x = $count > 1 ? $index * $step : $width / 2;
		$y = $height - ( ( $value / $max ) * ( $height - 32 ) ) - 16;
		$points[] = round( $x, 2 ) . ',' . round( $y, 2 );
	}

	return implode( ' ', $points );
}



/**
 * Convert menu rows to existing report bar row shape.
 *
 * @since 3.3
 *
 * @param array  $items Item rows.
 * @param string $value_key Value key.
 * @return array
 */
function rpress_reports_menu_rows( $items, $value_key ) {
	$rows = array();
	foreach ( (array) $items as $item ) {
		if ( empty( $item['name'] ) ) {
			continue;
		}
		$rows[] = array(
			'label' => $item['name'],
			'value' => isset( $item[ $value_key ] ) ? (float) $item[ $value_key ] : 0,
		);
	}
	if ( empty( $rows ) ) {
		$rows[] = array( 'label' => __( 'No menu movement', 'restropress' ), 'value' => 0 );
	}
	return $rows;
}

/**
 * Convert named amount rows to existing report bar row shape.
 *
 * @since 3.3
 *
 * @param array $items Rows with name and net_sales.
 * @return array
 */
function rpress_reports_named_amount_rows( $items ) {
	$rows = array();
	foreach ( (array) $items as $item ) {
		$rows[] = array(
			'label' => ! empty( $item['name'] ) ? $item['name'] : __( 'Unknown', 'restropress' ),
			'value' => isset( $item['net_sales'] ) ? (float) $item['net_sales'] : 0,
		);
	}
	if ( empty( $rows ) ) {
		$rows[] = array( 'label' => __( 'No period data', 'restropress' ), 'value' => 0 );
	}
	return $rows;
}

/**
 * Sum a numeric key in rows.
 *
 * @since 3.3
 *
 * @param array  $rows Rows.
 * @param string $key Key.
 * @return float
 */
function rpress_reports_sum_key( $rows, $key ) {
	$total = 0.0;
	foreach ( (array) $rows as $row ) {
		$total += isset( $row[ $key ] ) ? (float) $row[ $key ] : 0.0;
	}
	return $total;
}

/**
 * Render a single-line trend SVG.
 *
 * @since 3.3
 *
 * @param array $values Values.
 * @return void
 */
function rpress_reports_single_trend_svg( $values ) {
	?>
	<svg viewBox="0 0 760 260" preserveAspectRatio="none" focusable="false" aria-hidden="true">
		<path class="rp-reports-grid-line" d="M0 52H760M0 104H760M0 156H760M0 208H760" />
		<polyline class="rp-reports-line is-blue" points="<?php echo esc_attr( rpress_reports_svg_points( $values, 760, 260 ) ); ?>" />
	</svg>
	<?php
}

/**
 * Build service rows for bar list.
 *
 * @since 3.3
 *
 * @param array $service_sales Service totals.
 * @return array
 */
function rpress_reports_service_rows( $service_sales ) {
	$labels = array(
		'delivery' => function_exists( 'rpress_service_label' ) ? rpress_service_label( 'delivery' ) : __( 'Delivery', 'restropress' ),
		'pickup'   => function_exists( 'rpress_service_label' ) ? rpress_service_label( 'pickup' ) : __( 'Pickup', 'restropress' ),
		'dinein'   => __( 'Dine-in', 'restropress' ),
	);
	$rows = array();
	foreach ( (array) $service_sales as $key => $total ) {
		$rows[] = array(
			'label' => isset( $labels[ $key ] ) ? $labels[ $key ] : ucwords( str_replace( '-', ' ', $key ) ),
			'value' => (float) $total,
		);
	}
	return $rows;
}

/**
 * Build payment rows for bar list.
 *
 * @since 3.3
 *
 * @param array $payment_totals Payment totals.
 * @return array
 */
function rpress_reports_payment_rows( $payment_totals ) {
	$gateways = function_exists( 'rpress_get_payment_gateways' ) ? rpress_get_payment_gateways() : array();
	$rows = array();
	foreach ( (array) $payment_totals as $key => $total ) {
		$rows[] = array(
			'label' => isset( $gateways[ $key ]['admin_label'] ) ? $gateways[ $key ]['admin_label'] : ucwords( str_replace( array( '-', '_' ), ' ', $key ) ),
			'value' => (float) $total,
		);
	}
	if ( empty( $rows ) ) {
		$rows[] = array( 'label' => __( 'No paid orders', 'restropress' ), 'value' => 0 );
	}
	return $rows;
}

/**
 * Render bar list.
 *
 * @since 3.3
 *
 * @param array $rows Rows.
 * @param float $total Total.
 * @return void
 */
function rpress_reports_bar_list( $rows, $total ) {
	$total = max( 0.0, (float) $total );
	?>
	<div class="rp-reports-bar-list">
		<?php foreach ( $rows as $row ) :
			$value = (float) $row['value'];
			$percent = $total > 0 ? min( 100, ( $value / $total ) * 100 ) : 0;
			?>
			<div class="rp-reports-bar-row">
				<span><?php echo esc_html( $row['label'] ); ?></span>
				<div class="rp-reports-bar-track"><i style="width: <?php echo esc_attr( number_format( $percent, 2, '.', '' ) ); ?>%;"></i></div>
				<strong><?php echo esc_html( RPRESS_Reports_Intelligence::money( $value ) ); ?></strong>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Render count based bar list.
 *
 * @since 3.3
 *
 * @param array $rows Rows.
 * @param int   $total Total.
 * @return void
 */
function rpress_reports_count_bar_list( $rows, $total ) {
	$total = max( 1, (int) $total );
	?>
	<div class="rp-reports-bar-list">
		<?php foreach ( $rows as $row ) :
			$value = (int) $row['value'];
			$percent = min( 100, ( $value / $total ) * 100 );
			?>
			<div class="rp-reports-bar-row">
				<span><?php echo esc_html( $row['label'] ); ?></span>
				<div class="rp-reports-bar-track"><i style="width: <?php echo esc_attr( number_format( $percent, 2, '.', '' ) ); ?>%;"></i></div>
				<strong><?php echo esc_html( number_format_i18n( $value ) ); ?></strong>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Render status timeline bars.
 *
 * @since 3.3
 *
 * @param array $trend Trend rows.
 * @return void
 */
function rpress_reports_status_timeline( $trend ) {
	$max = 1;
	foreach ( (array) $trend as $row ) {
		$max = max( $max, (int) $row['orders'] );
	}
	?>
	<div class="rp-reports-status-timeline">
		<?php foreach ( (array) $trend as $day => $row ) :
			$height = max( 4, ( (int) $row['orders'] / $max ) * 100 );
			$segments = array(
				'completed' => (int) $row['completed'],
				'late'      => (int) $row['late'],
				'cancelled' => (int) $row['cancelled'],
				'refunded'  => (int) $row['refunded'],
				'failed'    => (int) $row['failed'],
			);
			?>
			<div class="rp-reports-status-day" title="<?php echo esc_attr( date_i18n( get_option( 'date_format' ), strtotime( $day ) ) ); ?>">
				<div class="rp-reports-status-stack" style="height: <?php echo esc_attr( number_format( $height, 2, '.', '' ) ); ?>%;">
					<?php foreach ( $segments as $key => $count ) :
						if ( $count <= 0 ) {
							continue;
						}
						$segment_height = max( 8, ( $count / max( 1, (int) $row['orders'] ) ) * 100 );
						?>
						<i class="is-<?php echo esc_attr( $key ); ?>" style="height: <?php echo esc_attr( number_format( $segment_height, 2, '.', '' ) ); ?>%;"></i>
					<?php endforeach; ?>
				</div>
				<small><?php echo esc_html( date_i18n( 'M j', strtotime( $day ) ) ); ?></small>
			</div>
		<?php endforeach; ?>
	</div>
	<div class="rp-reports-legend">
		<span><i class="rp-reports-dot is-green"></i><?php esc_html_e( 'Completed', 'restropress' ); ?></span>
		<span><i class="rp-reports-dot is-amber"></i><?php esc_html_e( 'Late', 'restropress' ); ?></span>
		<span><i class="rp-reports-dot is-red"></i><?php esc_html_e( 'Cancelled / Refund / Failed', 'restropress' ); ?></span>
	</div>
	<?php
}

/**
 * Render weekday and hour rush heatmap.
 *
 * @since 3.3
 *
 * @param array $payment_ids Payment IDs.
 * @return void
 */
function rpress_reports_rush_heatmap( $payment_ids ) {
	$days = array(
		1 => __( 'Mon', 'restropress' ),
		2 => __( 'Tue', 'restropress' ),
		3 => __( 'Wed', 'restropress' ),
		4 => __( 'Thu', 'restropress' ),
		5 => __( 'Fri', 'restropress' ),
		6 => __( 'Sat', 'restropress' ),
		0 => __( 'Sun', 'restropress' ),
	);
	$cells = array();
	foreach ( $days as $day_key => $label ) {
		$cells[ $day_key ] = array_fill( 0, 24, 0 );
	}
	foreach ( (array) $payment_ids as $payment_id ) {
		$timestamp = (int) get_post_time( 'U', false, $payment_id );
		$weekday   = (int) date_i18n( 'w', $timestamp );
		$hour      = (int) date_i18n( 'G', $timestamp );
		if ( isset( $cells[ $weekday ][ $hour ] ) ) {
			$cells[ $weekday ][ $hour ]++;
		}
	}
	$max = 0;
	foreach ( $cells as $hours ) {
		$max = max( $max, max( $hours ) );
	}
	?>
	<div class="rp-reports-rush-heatmap">
		<span class="rp-reports-rush-corner"></span>
		<?php for ( $hour = 0; $hour < 24; $hour++ ) : ?>
			<span class="rp-reports-rush-hour"><?php echo esc_html( date_i18n( 'ga', strtotime( $hour . ':00' ) ) ); ?></span>
		<?php endfor; ?>
		<?php foreach ( $days as $day_key => $label ) : ?>
			<strong><?php echo esc_html( $label ); ?></strong>
			<?php foreach ( $cells[ $day_key ] as $count ) :
				$level = $max > 0 ? (int) ceil( ( $count / $max ) * 4 ) : 0;
				?>
				<span class="level-<?php echo esc_attr( $level ); ?>" title="<?php echo esc_attr( sprintf( _n( '%d order', '%d orders', $count, 'restropress' ), $count ) ); ?>"></span>
			<?php endforeach; ?>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Build weekly risk table rows.
 *
 * @since 3.3
 *
 * @param array $trend Trend rows.
 * @return array
 */
function rpress_reports_risk_period_rows( $trend ) {
	$chunks = array_chunk( (array) $trend, 7, true );
	$rows = array();
	foreach ( $chunks as $chunk ) {
		$keys = array_keys( $chunk );
		$first = reset( $keys );
		$last = end( $keys );
		$totals = array(
			'orders'    => 0,
			'late'      => 0,
			'cancelled' => 0,
			'refunded'  => 0,
			'failed'    => 0,
		);
		foreach ( $chunk as $row ) {
			foreach ( $totals as $key => $value ) {
				$totals[ $key ] += isset( $row[ $key ] ) ? (int) $row[ $key ] : 0;
			}
		}
		$drivers = array(
			__( 'Late orders', 'restropress' )       => $totals['late'],
			__( 'Cancellations', 'restropress' )     => $totals['cancelled'],
			__( 'Refunds', 'restropress' )           => $totals['refunded'],
			__( 'Failed / unpaid', 'restropress' )   => $totals['failed'],
		);
		arsort( $drivers );
		$driver = max( $drivers ) > 0 ? key( $drivers ) : __( 'No major risk', 'restropress' );
		$orders = max( 1, $totals['orders'] );
		$rows[] = array(
			'period'      => sprintf( '%s - %s', date_i18n( 'M j', strtotime( $first ) ), date_i18n( 'M j', strtotime( $last ) ) ),
			'orders'      => $totals['orders'],
			'late_rate'   => ( $totals['late'] / $orders ) * 100,
			'cancel_rate' => ( $totals['cancelled'] / $orders ) * 100,
			'refund_rate' => ( $totals['refunded'] / $orders ) * 100,
			'failed_rate' => ( $totals['failed'] / $orders ) * 100,
			'driver'      => $driver,
		);
	}

	return array_reverse( $rows );
}

/**
 * Render hour heatmap from order timestamps.
 *
 * @since 3.3
 *
 * @param array $payment_ids Payment IDs.
 * @return void
 */
function rpress_reports_sales_heatmap( $payment_ids ) {
	$hours = array_fill( 0, 24, 0.0 );
	foreach ( (array) $payment_ids as $payment_id ) {
		if ( 'publish' !== get_post_status( $payment_id ) ) {
			continue;
		}
		$timestamp = (int) get_post_time( 'U', false, $payment_id );
		$hour      = (int) date_i18n( 'G', $timestamp );
		$hours[ $hour ] += (float) get_post_meta( $payment_id, '_rpress_payment_total', true );
	}
	$max = max( $hours );
	?>
	<div class="rp-reports-heatmap">
		<?php foreach ( $hours as $hour => $value ) :
			$level = $max > 0 ? (int) ceil( ( $value / $max ) * 4 ) : 0;
			?>
			<span class="level-<?php echo esc_attr( $level ); ?>">
				<small><?php echo esc_html( date_i18n( 'ga', strtotime( $hour . ':00' ) ) ); ?></small>
				<strong><?php echo esc_html( RPRESS_Reports_Intelligence::money( $value ) ); ?></strong>
			</span>
		<?php endforeach; ?>
	</div>
	<?php
}
