<?php
/**
 * Formatting functions for taking care of proper number formats and such
 *
 * @package     RPRESS
 * @subpackage  Functions/Formatting
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.2
*/
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Sanitize Amount
 *
 * Returns a sanitized amount by stripping out thousands separators.
 *
 * @since 1.0
 * @param string $amount Price amount to format
 * @return string $amount Newly sanitized amount
 */
function rpress_sanitize_amount( $amount ) {
	$is_negative   = false;
	$thousands_sep = rpress_get_option( 'thousands_separator', ',' );
	$decimal_sep   = rpress_get_option( 'decimal_separator', '.' );
	// Sanitize the amount
	if ( $decimal_sep == ',' && false !== ( $found = strpos( $amount, $decimal_sep ) ) ) {
		if ( ( $thousands_sep == '.' || $thousands_sep == ' ' ) && false !== ( $found = strpos( $amount, $thousands_sep ) ) ) {
			$amount = str_replace( $thousands_sep, '', $amount );
		} elseif( empty( $thousands_sep ) && false !== ( $found = strpos( $amount, '.' ) ) ) {
			$amount = str_replace( '.', '', $amount );
		}
		$amount = str_replace( $decimal_sep, '.', $amount );
	} elseif( $thousands_sep == ',' && false !== ( $found = strpos( $amount, $thousands_sep ) ) ) {
		$amount = str_replace( $thousands_sep, '', $amount );
	}
	if( $amount < 0 ) {
		$is_negative = true;
	}
	$amount   = preg_replace( '/[^0-9\.]/', '', $amount );
	/**
	 * Filter number of decimals to use for prices
	 *
	 * @since unknown
	 *
	 * @param int $number Number of decimals
	 * @param int|string $amount Price
	 */
	$decimals = apply_filters( 'rpress_sanitize_amount_decimals', 2, $amount );
	$amount   = number_format( (double) $amount, $decimals, '.', '' );
	if( $is_negative ) {
		$amount *= -1;
	}
	/**
	 * Filter the sanitized price before returning
	 *
	 * @since unknown
	 *
	 * @param string $amount Price
	 */
	return apply_filters( 'rpress_sanitize_amount', $amount );
}
/**
 * Returns a nicely formatted amount.
 *
 * @since 1.0
 *
 * @param string $amount   Price amount to format
 * @param string $decimals Whether or not to use decimals.  Useful when set to false for non-currency numbers.
 *
 * @return string $amount Newly formatted amount or Price Not Available
 */
function rpress_format_amount( $amount, $decimals = true ) {
	$thousands_sep = rpress_get_option( 'thousands_separator', ',' );
	$decimal_sep   = rpress_get_option( 'decimal_separator', '.' );
	// Format the amount
	if ( $decimal_sep == ',' && false !== ( $sep_found = strpos( $amount, $decimal_sep ) ) ) {
		$whole = substr( $amount, 0, $sep_found );
		$part = substr( $amount, $sep_found + 1, ( strlen( $amount ) - 1 ) );
		$amount = $whole . '.' . $part;
	}
	// Strip , from the amount (if set as the thousands separator)
	if ( $thousands_sep == ',' && false !== ( $found = strpos( $amount, $thousands_sep ) ) ) {
		$amount = str_replace( ',', '', $amount );
	}
	// Strip ' ' from the amount (if set as the thousands separator)
	if ( $thousands_sep == ' ' && false !== ( $found = strpos( $amount, $thousands_sep ) ) ) {
		$amount = str_replace( ' ', '', $amount );
	}
	if ( empty( $amount ) ) {
		$amount = 0;
	}
	$amount = rpress_sanitize_amount( $amount );
	$decimals  = apply_filters( 'rpress_format_amount_decimals', $decimals ? 2 : 0, $amount );
	$formatted = number_format( $amount, $decimals, $decimal_sep, $thousands_sep );
	return apply_filters( 'rpress_format_amount', $formatted, $amount, $decimals, $decimal_sep, $thousands_sep );
}
/**
 * Formats the currency display
 *
 * @since 1.0
 * @param string $price Price
 * @return array $currency Currencies displayed correctly
 */
function rpress_currency_filter( $price = '', $currency = '' ) {
	if( empty( $currency ) ) {
		$currency = rpress_get_currency();
	}
	$position = rpress_get_option( 'currency_position', 'before' );
	$negative = $price < 0;
	if( $negative ) {
		$price = substr( $price, 1 ); // Remove proceeding "-" -
	}
	$symbol = rpress_currency_symbol( $currency );
	if ( $position == 'before' ):
		switch ( $currency ):
			case "GBP" :
			case "BRL" :
			case "EUR" :
			case "USD" :
			case "AUD" :
			case "CAD" :
			case "HKD" :
			case "MXN" :
			case "NZD" :
			case "SGD" :
			case "JPY" :
			case "INR" :
			case "HUF" :
			case "ILS" :
			case "MYR" :
			case "NOK" :
			case "PKR" :
			case "PHP" :
			case "PLN" :
			case "SEK" :
			case "TWD" :
			case "THB" :
			case "RIAL" :
			case "RUB" :
			case "AED" :
			case "AFN" :
			case "AMD" :
			case "VND" :
			case "CNY" :
			case "KRW" :
			case "BDT" :
			case "NPR" :
			case "TRY" :
			case "AZN" :
				$formatted = $symbol . $price;
				break;
			default :
				$formatted = $currency . ' ' . $price;
				break;
		endswitch;
		$formatted = apply_filters( 'rpress_' . strtolower( $currency ) . '_currency_filter_before', $formatted, $currency, $price );
	else :
		switch ( $currency ) :
			case "GBP" :
			case "BRL" :
			case "EUR" :
			case "USD" :
			case "AUD" :
			case "CAD" :
			case "HKD" :
			case "MXN" :
			case "NZD" :
			case "SGD" :
			case "JPY" :
			case "INR" :
			case "HUF" :
			case "ILS" :
			case "MYR" :
			case "NOK" :
			case "PKR" :
			case "PHP" :
			case "PLN" :
			case "SEK" :
			case "TWD" :
			case "THB" :
			case "RIAL" :
			case "RUB" :
			case "AED" :
			case "AFN" :
			case "AMD" :
			case "VND" :
			case "CNY" :
			case "KRW" :
			case "BDT" :
			case "NPR" :
			case "TRY" :
			case "AZN" :
				$formatted = $price . $symbol;
				break;
			default :
				$formatted = $price . ' ' . $currency;
				break;
		endswitch;
		$formatted = apply_filters( 'rpress_' . strtolower( $currency ) . '_currency_filter_after', $formatted, $currency, $price );
	endif;
	if( $negative && !empty( $price ) ) {
		// Prepend the mins sign before the currency sign
		$formatted = '-' . $formatted;
	}
	return $formatted;
}
/**
 * Set the number of decimal places per currency
 *
 * @since 1.0.0
 * @param int $decimals Number of decimal places
 * @return int $decimals
*/
function rpress_currency_decimal_filter( $decimals = 2 ) {
	$currency = rpress_get_currency();
	switch ( $currency ) {
		case 'RIAL' :
		case 'JPY' :
		case 'TWD' :
		case 'HUF' :
			$decimals = 0;
			break;
	}
	return apply_filters( 'rpress_currency_decimal_count', $decimals, $currency );
}
add_filter( 'rpress_sanitize_amount_decimals', 'rpress_currency_decimal_filter' );
add_filter( 'rpress_format_amount_decimals', 'rpress_currency_decimal_filter' );
/**
 * Sanitizes a string key for RPRESS Settings
 *
 * Keys are used as internal identifiers. Alphanumeric characters, dashes, underscores, stops, colons and slashes are allowed
 *
 * @since 1.0
 * @param  string $key String key
 * @return string Sanitized key
 */
function rpress_sanitize_key( $key ) {
	$raw_key = $key;
	$key = preg_replace( '/[^a-zA-Z0-9_\-\.\:\/]/', '', $key );
	/**
	 * Filter a sanitized key string.
	 *
	 * @since 1.0
	 * @param string $key     Sanitized key.
	 * @param string $raw_key The key prior to sanitization.
	 */
	return apply_filters( 'rpress_sanitize_key', $key, $raw_key );
}
/**
 * Sanitizes array items
 *
 *
 * @since 2.8.3
 * @param  array $array array items
 * @return array sanitized array items
 */
function rpress_sanitize_array( &$array ){
  foreach ( $array as &$value ){  
    if( !is_array( $value ) ){
      // sanitize if value is not an array
      $value = sanitize_text_field( $value );
    }
    else{
      // go inside this function again
      rpress_sanitize_array( $value );
    }
  }
  return $array;
}
