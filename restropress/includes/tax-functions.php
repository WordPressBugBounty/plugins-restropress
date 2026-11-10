<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
/**
 * Tax Functions
 *
 * These are functions used for checking if taxes are enabled, calculating taxes, etc.
 * Functions for retrieving tax amounts and such for individual payments are in
 * includes/payment-functions.php and includes/cart-functions.php
 *
 * @package     RPRESS
 * @subpackage  Functions/Taxes
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since 1.0
*/
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;
/**
 * Checks if taxes are enabled by using the option set from the RPRESS Settings.
 * The value returned can be filtered.
 *
 * @since 1.0.0
 * @return bool Whether or not taxes are enabled
 */
function rpress_use_taxes() {
	$ret = rpress_get_option( 'enable_taxes', false );
	return (bool) apply_filters( 'rpress_use_taxes', $ret );
}
/**
 * Retrieve tax rates
 *
 * @since  1.0.0
 * @return array Defined tax rates
 */
function rpress_get_tax_rates() {
	$rates = get_option( 'rpress_tax_rates', array() );
	return apply_filters( 'rpress_get_tax_rates', $rates );
}
/**
 * Get taxation rate
 *
 * @since 1.0.0
 * @param bool $country
 * @param bool $state
 * @return mixed|void
 */
function rpress_get_tax_rate( $country = false, $state = false ) {
    $rate = (float) rpress_get_option( 'tax_rate', 0 );
    // Convert to a number we can use
    $rate = $rate / 100;
    return apply_filters( 'rpress_tax_rate', $rate, $country, $state );
}
/**
 * Retrieve a fully formatted tax rate
 *
 * @since  1.0.0
 * @param string $country The country to retrieve a rate for
 * @param string $state The state to retrieve a rate for
 * @return string Formatted rate
 */
function rpress_get_formatted_tax_rate( $country = false, $state = false ) {
	$rate = rpress_get_tax_rate( $country, $state );
	$rate = round( $rate * 100, 4 );
	$formatted = $rate .= '%';
	return apply_filters( 'rpress_formatted_tax_rate', $formatted, $rate, $country, $state );
}
/**
 * Calculate the taxed amount
 *
 * @since 1.0.0
 * @param $amount float The original amount to calculate a tax cost
 * @param $country string The country to calculate tax for. Will use default if not passed
 * @param $state string The state to calculate tax for. Will use default if not passed
 * @return float $tax Taxed amount
 */
function rpress_calculate_tax( $amount = 0, $country = false, $state = false ,$ignore_inclusive =false, $rate = null ) {
	// $rate (decimal, e.g. 0.05) lets callers tax at a specific tax class rate.
	if ( null === $rate ) {
		$rate = rpress_get_tax_rate( $country, $state );
	}
	$tax  = 0.00;
	if ( rpress_use_taxes() && $amount > 0 ) {
        if($ignore_inclusive){
            $amount = floatval($amount);  // Ensure $amount is rounded first
            $tax = $amount * $rate;  
            $tax  = floor($tax * 100) / 100;
        }else if ( rpress_prices_include_tax()) {
            
			$pre_tax = ( $amount / ( 1 + $rate ) );
			$tax = floatval($amount) - $pre_tax;
		} else {
			$tax = floatval($amount) * $rate;
		}
	}
	return apply_filters( 'rpress_taxed_amount', floatval( $tax ), $rate, $country, $state );
}
/**
 * Returns the formatted tax amount for the given year
 *
 * @since 1.0.0
 * @param $year int The year to retrieve taxes for, i.e. 2012
 * @uses rpress_get_sales_tax_for_year()
 * @return void
*/
function rpress_sales_tax_for_year( $year = null ) {
	echo esc_html( rpress_currency_filter( rpress_format_amount( rpress_get_sales_tax_for_year( $year ) ) ) );
}
/**
 * Gets the sales tax for the given year
 *
 * @since 1.0.0
 * @param $year int The year to retrieve taxes for, i.e. 2012
 * @uses rpress_get_payment_tax()
 * @return float $tax Sales tax
 */
function rpress_get_sales_tax_for_year( $year = null ) {
	global $wpdb;
	// Start at zero
	$tax = 0;
	if ( ! empty( $year ) ) {
		$tax = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(CAST(pm.meta_value AS DECIMAL(18,2))), 0)
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_rpress_payment_tax'
					AND p.post_type = 'rpress_payment'
					AND p.post_status IN ('publish', 'revoked')
					AND YEAR(p.post_date) = %d",
				absint( $year )
			)
		);
	}
	return apply_filters( 'rpress_get_sales_tax_for_year', $tax, $year );
}
/**
 * Is the cart taxed?
 *
 * This used to include a check for local tax opt-in, but that was ripped out in v1.6, so this is just a wrapper now
 *
 * @since 1.0
 * @return bool
 */
function rpress_is_cart_taxed() {
	return rpress_use_taxes();
}
/**
 * Check if the individual product prices include tax
 *
 * @since 1.0
 * @return bool $include_tax
*/
function rpress_prices_include_tax() {
	$ret = ( rpress_get_option( 'prices_include_tax', false ) == 'yes' && rpress_use_taxes() );
	return apply_filters( 'rpress_prices_include_tax', $ret );
}
/**
 * Checks whether the user has enabled display of taxes on the checkout
 *
 * @since 1.0
 * @return bool $include_tax
 */
function rpress_prices_show_tax_on_checkout() {
	$ret = ( rpress_get_option( 'checkout_include_tax', false ) == 'yes' && rpress_use_taxes() );
	return apply_filters( 'rpress_taxes_on_prices_on_checkout', $ret );
}
/**
 * Should we show address fields for taxation purposes?
 *
 * @since 1.y
 * @return bool
 */
function rpress_cart_needs_tax_address_fields() {
	if( ! rpress_is_cart_taxed() )
		return false;
	return ! did_action( 'rpress_after_cc_fields', 'rpress_default_cc_address_fields' );
}
/**
 * Is this Item excluded from tax?
 *
 * @since  1.0.0
 * @return bool
 */
/**
 * Tax classes.
 *
 * The Standard class always exists and its rate is the classic `tax_rate`
 * setting, so a site that never adds classes behaves exactly as before.
 * Other classes are stored in the `rpress_tax_classes` option as
 * slug => array( name, rate (percent), code ).
 *
 * @since 3.5
 * @return array slug => array( 'name', 'rate', 'code' )
 */
function rpress_get_tax_classes() {
	$classes = array(
		'standard' => array(
			'name' => __( 'Standard', 'restropress' ),
			'rate' => (float) rpress_get_option( 'tax_rate', 0 ),
			'code' => (string) rpress_get_option( 'tax_class_standard_code', '' ),
		),
	);
	foreach ( (array) get_option( 'rpress_tax_classes', array() ) as $slug => $class ) {
		$slug = sanitize_key( $slug );
		if ( '' === $slug || 'standard' === $slug || ! is_array( $class ) || ! isset( $class['name'] ) ) {
			continue;
		}
		$classes[ $slug ] = array(
			'name' => (string) $class['name'],
			'rate' => isset( $class['rate'] ) ? (float) $class['rate'] : 0.0,
			'code' => isset( $class['code'] ) ? (string) $class['code'] : '',
		);
	}
	return apply_filters( 'rpress_tax_classes', $classes );
}

/**
 * Tax class of a food item: item override, then its categories, then Standard.
 *
 * @since 3.5
 * @param int $fooditem_id Food item ID.
 * @return string Tax class slug.
 */
function rpress_get_fooditem_tax_class( $fooditem_id = 0 ) {
	$classes = rpress_get_tax_classes();
	$class   = (string) get_post_meta( absint( $fooditem_id ), '_rpress_tax_class', true );
	if ( '' === $class || ! isset( $classes[ $class ] ) ) {
		$class = 'standard';
		$terms = get_the_terms( absint( $fooditem_id ), 'food-category' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_class = (string) get_term_meta( $term->term_id, '_rpress_tax_class', true );
				if ( '' !== $term_class && isset( $classes[ $term_class ] ) ) {
					$class = $term_class;
					break;
				}
			}
		}
	}
	return apply_filters( 'rpress_fooditem_tax_class', $class, $fooditem_id );
}

/**
 * Decimal tax rate for a food item (0.05 for 5%).
 *
 * Standard goes through rpress_get_tax_rate() so its existing filters apply.
 *
 * @since 3.5
 * @param int         $fooditem_id Food item ID.
 * @param string|bool $country     Country.
 * @param string|bool $state       State.
 * @return float
 */
function rpress_get_fooditem_tax_rate( $fooditem_id = 0, $country = false, $state = false ) {
	$class = rpress_get_fooditem_tax_class( $fooditem_id );
	if ( 'standard' === $class ) {
		$rate = rpress_get_tax_rate( $country, $state );
	} else {
		$classes = rpress_get_tax_classes();
		$rate    = (float) $classes[ $class ]['rate'] / 100;
	}
	return (float) apply_filters( 'rpress_fooditem_tax_rate', $rate, $fooditem_id, $class, $country, $state );
}

/**
 * Tax rate in percent for showing a food item's price on the menu.
 *
 * Standard returns the raw `tax_rate` setting, exactly what the menu used
 * before tax classes, so single-rate stores display unchanged prices.
 *
 * @since 3.5
 * @param int $fooditem_id Food item ID.
 * @return float
 */
function rpress_get_fooditem_tax_percent( $fooditem_id = 0 ) {
	$class = rpress_get_fooditem_tax_class( $fooditem_id );
	if ( 'standard' === $class ) {
		return (float) rpress_get_option( 'tax_rate', 0 );
	}
	$classes = rpress_get_tax_classes();
	return (float) $classes[ $class ]['rate'];
}

/**
 * Per-class tax totals for a list of cart lines (cart or order details).
 *
 * @since 3.5
 * @param array      $lines Lines with 'tax', and 'tax_class' / 'tax_rate' when known.
 * @param float|null $total Optional tax total to match. A flat discount is taxed
 *                          after the line taxes are worked out, so the rows are
 *                          scaled to add up to the tax actually charged.
 * @return array slug => array( 'name', 'rate', 'code', 'tax' )
 */
function rpress_get_tax_breakdown( $lines, $total = null ) {
	$classes   = rpress_get_tax_classes();
	$breakdown = array();
	foreach ( (array) $lines as $line ) {
		if ( ! is_array( $line ) ) {
			continue;
		}
		$slug = isset( $line['tax_class'] ) && isset( $classes[ $line['tax_class'] ] ) ? $line['tax_class'] : 'standard';
		if ( ! isset( $breakdown[ $slug ] ) ) {
			$breakdown[ $slug ] = array(
				'name' => $classes[ $slug ]['name'],
				'rate' => isset( $line['tax_rate'] ) ? round( (float) $line['tax_rate'] * 100, 4 ) : (float) $classes[ $slug ]['rate'],
				'code' => $classes[ $slug ]['code'],
				'tax'  => 0.0,
			);
		}
		$breakdown[ $slug ]['tax'] += isset( $line['tax'] ) ? (float) $line['tax'] : 0.0;
	}
	$decimals = rpress_currency_decimal_filter();
	$sum      = array_sum( wp_list_pluck( $breakdown, 'tax' ) );
	$scale    = ( null !== $total && $sum > 0 ) ? (float) $total / $sum : 1;
	$left     = null !== $total ? round( (float) $total, $decimals ) : null;
	$slugs    = array_keys( $breakdown );
	foreach ( $slugs as $i => $slug ) {
		$tax = round( $breakdown[ $slug ]['tax'] * $scale, $decimals );
		if ( null !== $left && $sum > 0 ) {
			// The last row takes the rounding remainder so the rows add up exactly.
			$tax   = ( count( $slugs ) - 1 === $i ) ? round( $left, $decimals ) : $tax;
			$left -= $tax;
		}
		$breakdown[ $slug ]['tax'] = $tax;
	}
	return apply_filters( 'rpress_tax_breakdown', $breakdown, $lines );
}

/**
 * Whether the given lines are taxed at more than one rate.
 *
 * @since 3.5
 * @param array $lines Cart or order lines.
 * @return bool
 */
function rpress_lines_have_mixed_tax_rates( $lines ) {
	$rates = array();
	foreach ( (array) $lines as $line ) {
		if ( is_array( $line ) && isset( $line['tax_rate'] ) ) {
			$rates[ (string) round( (float) $line['tax_rate'], 6 ) ] = true;
		}
	}
	return count( $rates ) > 1;
}

function rpress_fooditem_is_tax_exclusive( $fooditem_id = 0 ) {
	$ret = false;
	return apply_filters( 'rpress_fooditem_is_tax_exclusive', $ret, $fooditem_id );
}
/**
 * Is this Addon excluded from tax?
 *
 * @since  2.7.3
 * @return bool
 */
function rpress_addon_is_tax_exclusive( $addon_id = 0 ) {
	$ret = 'no';
	return apply_filters( 'rpress_addon_is_tax_exclusive', $ret, $addon_id );
}
/**
 * Get tax name
 *
 * @since  2.6
 * @return string
 */
function rpress_get_tax_name() {
    $tax_name = rpress_get_option( 'tax_name', '' );
    if ( empty( $tax_name ) ) {
        $tax_name = __( 'Estimated Tax', 'restropress' );
    }
    $tax_name = apply_filters( 'rpress_tax_name', $tax_name );
    return $tax_name;
}
/**
 * Checks whether it needs to show the billing details or not.
 *
 * @since 2.5.5
 * @return bool Whether or the fields needs to be shown
 */
function rpress_show_billing_fields() {
    $enable_fields = rpress_get_option( 'enable_billing_fields', false );
    return (bool) apply_filters( 'rpress_show_billing_fields', $enable_fields );
}
