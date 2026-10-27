<?php
/**
 * Plugin Compatibility
 *
 * Functions for compatibility with other plugins.
 *
 * @package     RPRESS
 * @subpackage  Functions/Compatibility
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Disables admin sorting of Post Types Order
 *
 * When sorting fooditems by price, earnings, sales, date, or name,
 * we need to remove the posts_orderby that Post Types Order imposes
 *
 * @since  1.0.0
 * @return void
 */
function rpress_remove_post_types_order() {
	remove_filter( 'posts_orderby', 'CPTOrderPosts' );
}
add_action( 'load-edit.php', 'rpress_remove_post_types_order' );
/**
 * Disables opengraph tags on the checkout page
 *
 * There is a bizarre conflict that makes the checkout errors not get displayed
 * when the Jetpack opengraph tags are displayed
 *
 * @since 1.0.0.1
 * @return bool
 */
function rpress_disable_jetpack_og_on_checkout() {
	if ( rpress_is_checkout() ) {
		remove_action( 'wp_head', 'jetpack_og_tags' );
	}
}
add_action( 'template_redirect', 'rpress_disable_jetpack_og_on_checkout' );
/**
 * Checks if a caching plugin is active
 *
 * @since 1.0
 * @return bool $caching True if caching plugin is enabled, false otherwise
 */
function rpress_is_caching_plugin_active() {
	$caching = ( function_exists( 'wpsupercache_site_admin' ) || defined( 'W3TC' ) || function_exists( 'rocket_init' ) );
	return apply_filters( 'rpress_is_caching_plugin_active', $caching );
}
/**
 * Adds a ?nocache option for the checkout page
 *
 * This ensures the checkout page remains uncached when plugins like WP Super Cache are activated
 *
 * @since 1.0
 * @param array $settings Misc Settings
 * @return array $settings Updated Misc Settings
 */
function rpress_append_no_cache_param( $settings ) {
	if ( ! rpress_is_caching_plugin_active() )
		return $settings;
	$settings[] = array(
		'id' => 'no_cache_checkout',
		'name' => __('No Caching on Checkout?','restropress' ),
		'desc' => __('Check this box in order to append a ?nocache parameter to the checkout URL to prevent caching plugins from caching the page.','restropress' ),
		'type' => 'checkbox'
	);
	return $settings;
}
add_filter( 'rpress_settings_misc', 'rpress_append_no_cache_param', -1 );
/**
 * Show the correct language on the [fooditems] shortcode if qTranslate is active
 *
 * @since  1.0.0
 * @param string $content 
 * @return string $content 
 */
function rpress_qtranslate_content( $content ) {
	if( defined( 'QT_LANGUAGE' ) )
		$content = qtrans_useCurrentLanguageIfNotFoundShowAvailable( $content );
	return $content;
}
add_filter( 'rpress_fooditems_content', 'rpress_qtranslate_content' );
add_filter( 'rpress_fooditems_excerpt', 'rpress_qtranslate_content' );
/**
 * Prevents qTranslate from redirecting to language-specific URL when fooditeming purchased files
 *
 * @since  1.0.0
 * @param string       $target Target URL
 * @return string|bool $target Target URL. False if redirect is disabled
 */
function rpress_qtranslate_prevent_redirect( $target ) {
	if( strpos( $target, 'rpressfile' ) ) {
		$target = false;
		global $q_config;
		$q_config['url_mode'] = '';
	}
	return $target;
}
add_filter( 'qtranslate_language_detect_redirect', 'rpress_qtranslate_prevent_redirect' );
/**
 * Disable the WooCommerce 'Un-force SSL when leaving checkout' option on RPRESS checkout
 * to prevent redirect loops
 *
 * @since  1.0.0
 * @return void
 */
function rpress_disable_woo_ssl_on_checkout() {
	if( rpress_is_checkout() && rpress_is_ssl_enforced() ) {
		remove_action( 'template_redirect', array( 'WC_HTTPS', 'unforce_https_template_redirect' ) );
	}
}
add_action( 'template_redirect', 'rpress_disable_woo_ssl_on_checkout', 9 );
/**
 * Disables the mandrill_nl2br filter while sending RPRESS emails
 *
 * @since  1.0.0
 * @return void
 */
function rpress_disable_mandrill_nl2br() {
	add_filter( 'mandrill_nl2br', '__return_false' );
}
add_action( 'rpress_email_send_before', 'rpress_disable_mandrill_nl2br');
/**
 * Prevents the Purchase Confirmation screen from being detected as a 404 error in the 404 Redirected plugin
 *
 * @since 1.0.0
 * @return void
 */
function rpress_disable_404_redirected_redirect() {
	if( ! defined( 'WBZ404_VERSION' ) ) {
		return;
	}
	if( rpress_is_success_page() ) {
		remove_action( 'template_redirect', 'wbz404_process404', 10 );
	}
}
add_action( 'template_redirect', 'rpress_disable_404_redirected_redirect', 9 );
/**
 * Adds 'rpress' to the list of Say What aliases after moving to WordPress.org language packs
 *
 * @since 1.0.0
 * @param  array $aliases Say What domain aliases
 * @return array          Say What domain alises with 'rpress' added
 */
function rpress_say_what_domain_aliases( $aliases ) {
	$aliases['restropress'][] = 'rpress';
	return $aliases;
}
add_filter( 'say_what_domain_aliases', 'rpress_say_what_domain_aliases', 10, 1 );
/**
 * Removes the Really Simple SSL mixed content filter during file fooditems to avoid
 * errors with chunked file delivery
 *
 * @see https://github.com/rlankhorst/really-simple-ssl/issues/30
 *
 * @since 1.0.10
 * @return void
 */
function rpress_rsssl_remove_mixed_content_filter() {
	if ( class_exists( 'REALLY_SIMPLE_SSL' ) && did_action( 'rpress_process_verified_fooditem' ) ) {
		remove_action( 'init', array( RSSSL()->rsssl_mixed_content_fixer, 'start_buffer' ) );
		remove_action( 'shutdown', array( RSSSL()->rsssl_mixed_content_fixer, 'end_buffer' ) );
	}
}
add_action( 'plugins_loaded', 'rpress_rsssl_remove_mixed_content_filter', 999 );

/**
 * Weglot URL translation support
 *
 * Ensures language prefix is preserved for checkout, success, and failed transaction URIs.
 *
 * @since 3.4.2
 * @param string $url The URL to translate
 * @return string The translated URL
 */
function rpress_weglot_translate_url( $url ) {
	if ( function_exists( 'weglot_get_service' ) && function_exists( 'weglot_get_current_language' ) && function_exists( 'weglot_get_original_language' ) ) {
		try {
			$current_language = weglot_get_current_language();
			$original_language = weglot_get_original_language();
			if ( $current_language !== $original_language ) {
				$request_url_services = weglot_get_service( 'Request_Url_Service_Weglot' );
				$language_services    = weglot_get_service( 'Language_Service_Weglot' );
				if ( $request_url_services && $language_services ) {
					$wg_url   = $request_url_services->create_url_object( $url );
					$language = $language_services->get_language_from_internal( $current_language );
					if ( $wg_url && $language ) {
						return $wg_url->getForLanguage( $language );
					}
				}
			}
		} catch ( Exception $e ) {
			// Do nothing and return original URL
		}
	}
	return $url;
}
add_filter( 'rpress_get_checkout_uri', 'rpress_weglot_translate_url', 999 );
add_filter( 'rpress_get_success_page_uri', 'rpress_weglot_translate_url', 999 );
add_filter( 'rpress_get_failed_transaction_uri', 'rpress_weglot_translate_url', 999 );
add_filter( 'rpress_get_lostpassword_url', 'rpress_weglot_translate_url', 999 );

/**
 * Check if WPML is active
 *
 * @since 3.4.3
 * @return bool
 */
if ( ! function_exists( 'rpress_wpml_is_active' ) ) {
	function rpress_wpml_is_active() {
		return ( defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_object_id' ) || has_filter( 'wpml_current_language' ) );
	}
}

/**
 * Get active language code in WPML
 *
 * @since 3.4.3
 * @return string|null
 */
if ( ! function_exists( 'rpress_wpml_get_current_language' ) ) {
	function rpress_wpml_get_current_language() {
		if ( ! rpress_wpml_is_active() ) {
			return null;
		}

		$current_lang = apply_filters( 'wpml_current_language', null );

		// Fallback to WPML cookie if running in an AJAX or early context
		if ( empty( $current_lang ) && isset( $_COOKIE['wp-wpml_current_language'] ) ) {
			$current_lang = sanitize_text_field( wp_unslash( $_COOKIE['wp-wpml_current_language'] ) );
		}

		return $current_lang;
	}
}

/**
 * Translate a RestroPress core page URL into the active WPML language
 *
 * @since 3.4.3
 * @param string $url The default URL
 * @param string $page_key The RestroPress settings option key (e.g. 'success_page', 'purchase_page')
 * @return string
 */
if ( ! function_exists( 'rpress_wpml_translate_page_url' ) ) {
	function rpress_wpml_translate_page_url( $url, $page_key ) {
		if ( ! rpress_wpml_is_active() || ! function_exists( 'rpress_get_option' ) ) {
			return $url;
		}

		$page_id = rpress_get_option( $page_key, 0 );
		if ( ! $page_id ) {
			return $url;
		}

		$current_lang = rpress_wpml_get_current_language();
		if ( empty( $current_lang ) ) {
			return $url;
		}

		$translated_id = apply_filters( 'wpml_object_id', (int) $page_id, 'page', true, $current_lang );

		if ( $translated_id && (int) $translated_id !== (int) $page_id ) {
			$translated_url = get_permalink( $translated_id );
			$query          = parse_url( $url, PHP_URL_QUERY );
			if ( ! empty( $query ) ) {
				parse_str( $query, $query_args );
				$translated_url = add_query_arg( $query_args, $translated_url );
			}
			return $translated_url;
		}

		return $url;
	}
}

if ( ! function_exists( 'rpress_wpml_filter_success_page_uri' ) ) {
	function rpress_wpml_filter_success_page_uri( $url ) {
		return rpress_wpml_translate_page_url( $url, 'success_page' );
	}
}

if ( ! function_exists( 'rpress_wpml_filter_checkout_page_uri' ) ) {
	function rpress_wpml_filter_checkout_page_uri( $url ) {
		return rpress_wpml_translate_page_url( $url, 'purchase_page' );
	}
}

if ( ! function_exists( 'rpress_wpml_filter_lostpassword_url' ) ) {
	function rpress_wpml_filter_lostpassword_url( $url ) {
		return rpress_wpml_translate_page_url( $url, 'order_login_page' );
	}
}

/**
 * Filter is_a_restropress_page for all translated RestroPress pages.
 *
 * @since 3.4.3
 * @param bool $is_rp_page Current check
 * @return bool
 */
if ( ! function_exists( 'rpress_wpml_filter_is_a_restropress_page' ) ) {
	function rpress_wpml_filter_is_a_restropress_page( $is_rp_page ) {
		if ( $is_rp_page ) {
			return true;
		}

		if ( ! rpress_wpml_is_active() || ! function_exists( 'rpress_get_option' ) ) {
			return $is_rp_page;
		}

		$current_lang = rpress_wpml_get_current_language();
		if ( empty( $current_lang ) ) {
			return $is_rp_page;
		}

		$pages_to_check = array();
		foreach ( array( 'food_items_page', 'purchase_page', 'success_page' ) as $key ) {
			$base_id = (int) rpress_get_option( $key, 0 );
			if ( $base_id ) {
				$trans_id = apply_filters( 'wpml_object_id', $base_id, 'page', false, $current_lang );
				if ( $trans_id ) {
					$pages_to_check[] = (int) $trans_id;
				}
			}
		}

		if ( ! empty( $pages_to_check ) && is_page( $pages_to_check ) ) {
			return true;
		}

		$queried_id = (int) get_queried_object_id();
		if ( ! empty( $pages_to_check ) && in_array( $queried_id, $pages_to_check, true ) ) {
			return true;
		}

		return $is_rp_page;
	}
}

/**
 * Filter rpress_is_checkout for translated checkout pages.
 *
 * @since 3.4.3
 * @param bool $is_checkout Current check
 * @return bool
 */
if ( ! function_exists( 'rpress_wpml_filter_is_checkout' ) ) {
	function rpress_wpml_filter_is_checkout( $is_checkout ) {
		if ( $is_checkout ) {
			return true;
		}

		if ( ! rpress_wpml_is_active() || ! function_exists( 'rpress_get_option' ) ) {
			return $is_checkout;
		}

		$current_lang = rpress_wpml_get_current_language();
		if ( empty( $current_lang ) ) {
			return $is_checkout;
		}

		$checkout_page_id = (int) rpress_get_option( 'purchase_page', 0 );
		if ( $checkout_page_id ) {
			$trans_id = (int) apply_filters( 'wpml_object_id', $checkout_page_id, 'page', false, $current_lang );
			if ( $trans_id && is_page( $trans_id ) ) {
				return true;
			}
		}

		return $is_checkout;
	}
}

/**
 * Prevent WordPress canonical redirects on translated RestroPress pages.
 *
 * Prevents redirect_canonical from erroneously redirecting translated confirmation
 * or checkout pages back to parent or default URLs.
 *
 * @since 3.4.3
 * @param string|false $redirect_url The target redirect URL
 * @param string $requested_url The original requested URL
 * @return string|false
 */
if ( ! function_exists( 'rpress_wpml_disable_canonical_redirect' ) ) {
	function rpress_wpml_disable_canonical_redirect( $redirect_url, $requested_url ) {
		if ( ! rpress_wpml_is_active() || ! function_exists( 'rpress_get_option' ) ) {
			return $redirect_url;
		}

		$current_lang = rpress_wpml_get_current_language();
		if ( empty( $current_lang ) ) {
			return $redirect_url;
		}

		$success_page_id  = (int) rpress_get_option( 'success_page', 0 );
		$checkout_page_id = (int) rpress_get_option( 'purchase_page', 0 );

		$pages_to_check = array_filter( array(
			(int) apply_filters( 'wpml_object_id', $success_page_id, 'page', false, $current_lang ),
			(int) apply_filters( 'wpml_object_id', $checkout_page_id, 'page', false, $current_lang ),
		) );

		if ( ! empty( $pages_to_check ) && is_page( $pages_to_check ) ) {
			return false;
		}

		$queried_id = get_queried_object_id();
		if ( ! empty( $pages_to_check ) && in_array( $queried_id, $pages_to_check, true ) ) {
			return false;
		}

		return $redirect_url;
	}
}

/**
 * Ensure rpress_is_success_page returns true on translated confirmation pages.
 *
 * @since 3.4.3
 * @param bool $is_success Current determination
 * @return bool
 */
if ( ! function_exists( 'rpress_wpml_filter_is_success_page' ) ) {
	function rpress_wpml_filter_is_success_page( $is_success ) {
		if ( $is_success ) {
			return true;
		}

		$success_page_id = (int) rpress_get_option( 'success_page', 0 );
		if ( $success_page_id ) {
			$current_lang  = rpress_wpml_get_current_language();
			$translated_id = apply_filters( 'wpml_object_id', $success_page_id, 'page', true, $current_lang );
			if ( $translated_id && is_page( $translated_id ) ) {
				return true;
			}
		}

		return $is_success;
	}
}

if ( ! has_filter( 'rpress_get_success_page_uri', 'rpress_wpml_filter_success_page_uri' ) ) {
	add_filter( 'rpress_get_success_page_uri', 'rpress_wpml_filter_success_page_uri', 20 );
}
if ( ! has_filter( 'rpress_get_checkout_uri', 'rpress_wpml_filter_checkout_page_uri' ) ) {
	add_filter( 'rpress_get_checkout_uri', 'rpress_wpml_filter_checkout_page_uri', 20 );
}
if ( ! has_filter( 'rpress_get_failed_transaction_uri', 'rpress_wpml_filter_checkout_page_uri' ) ) {
	add_filter( 'rpress_get_failed_transaction_uri', 'rpress_wpml_filter_checkout_page_uri', 20 );
}
if ( ! has_filter( 'rpress_get_lostpassword_url', 'rpress_wpml_filter_lostpassword_url' ) ) {
	add_filter( 'rpress_get_lostpassword_url', 'rpress_wpml_filter_lostpassword_url', 20 );
}
if ( ! has_filter( 'rpress_is_success_page', 'rpress_wpml_filter_is_success_page' ) ) {
	add_filter( 'rpress_is_success_page', 'rpress_wpml_filter_is_success_page', 20 );
}
if ( ! has_filter( 'rpress_is_checkout', 'rpress_wpml_filter_is_checkout' ) ) {
	add_filter( 'rpress_is_checkout', 'rpress_wpml_filter_is_checkout', 20 );
}
if ( ! has_filter( 'is_a_restropress_page', 'rpress_wpml_filter_is_a_restropress_page' ) ) {
	add_filter( 'is_a_restropress_page', 'rpress_wpml_filter_is_a_restropress_page', 20 );
}
if ( ! has_filter( 'redirect_canonical', 'rpress_wpml_disable_canonical_redirect' ) ) {
	add_filter( 'redirect_canonical', 'rpress_wpml_disable_canonical_redirect', 10, 2 );
}
/**
 * Retrieve the language code for an order.
 *
 * @since 3.4.4
 * @param int $payment_id Payment ID
 * @return string|null
 */
if ( ! function_exists( 'rpress_wpml_get_payment_language' ) ) {
	function rpress_wpml_get_payment_language( $payment_id ) {
		if ( empty( $payment_id ) ) {
			return null;
		}

		$lang = apply_filters( 'wpml_element_language_code', null, array(
			'element_id'   => $payment_id,
			'element_type' => 'post_rpress_payment',
		) );

		if ( empty( $lang ) ) {
			$lang = get_post_meta( $payment_id, 'wpml_language', true );
		}
		if ( empty( $lang ) ) {
			$lang = get_post_meta( $payment_id, '_order_language', true );
		}

		return ! empty( $lang ) ? $lang : null;
	}
}

/**
 * Save language code when an order is created.
 *
 * @since 3.4.4
 * @param int $payment_id Payment ID
 * @param array $payment_data Payment metadata
 * @return void
 */
if ( ! function_exists( 'rpress_wpml_save_payment_language' ) ) {
	function rpress_wpml_save_payment_language( $payment_id, $payment_data = array() ) {
		if ( rpress_wpml_is_active() && ! empty( $payment_id ) ) {
			$current_lang = rpress_wpml_get_current_language();
			if ( ! empty( $current_lang ) ) {
				update_post_meta( $payment_id, 'wpml_language', $current_lang );
			}
		}
	}
}

/**
 * Translate a setting value using WPML String Translation or per-language option.
 *
 * @since 3.4.4
 * @param mixed $value Setting value
 * @param string $key Setting key
 * @param string $lang Target language code
 * @param mixed $default Default fallback
 * @return mixed
 */
if ( ! function_exists( 'rpress_wpml_translate_setting_value' ) ) {
	function rpress_wpml_translate_setting_value( $value, $key, $lang, $default = false ) {
		if ( empty( $lang ) ) {
			return $value;
		}

		$context = 'admin_texts_rpress_settings';

		if ( is_array( $value ) ) {
			foreach ( $value as $sub_key => $sub_val ) {
				if ( is_string( $sub_val ) ) {
					$string_name = '[rpress_settings][' . $key . ']' . $sub_key;
					$translated  = apply_filters( 'wpml_translate_single_string', $sub_val, $context, $string_name, $lang );

					if ( $translated === $sub_val ) {
						$string_name_alt = '[rpress_settings-en][' . $key . ']' . $sub_key;
						$translated      = apply_filters( 'wpml_translate_single_string', $sub_val, 'admin_texts_rpress_settings-en', $string_name_alt, $lang );
					}

					if ( $translated === $sub_val ) {
						$lang_options = get_option( 'rpress_settings-' . $lang );
						if ( is_array( $lang_options ) && isset( $lang_options[ $key ][ $sub_key ] ) ) {
							$translated = $lang_options[ $key ][ $sub_key ];
						}
					}

					$value[ $sub_key ] = $translated;
				}
			}
		} elseif ( is_string( $value ) ) {
			$string_name = '[rpress_settings]' . $key;
			$translated  = apply_filters( 'wpml_translate_single_string', $value, $context, $string_name, $lang );

			if ( $translated === $value ) {
				$string_name_alt = '[rpress_settings-en]' . $key;
				$translated      = apply_filters( 'wpml_translate_single_string', $value, 'admin_texts_rpress_settings-en', $string_name_alt, $lang );
			}

			if ( $translated === $value ) {
				$lang_options = get_option( 'rpress_settings-' . $lang );
				if ( is_array( $lang_options ) && isset( $lang_options[ $key ] ) ) {
					$translated = $lang_options[ $key ];
				}
			}

			$value = $translated;
		}

		return $value;
	}
}

/**
 * Filter rpress_get_option to dynamically resolve translated strings via WPML.
 *
 * @since 3.4.4
 * @param mixed $value Existing option value
 * @param string $key Setting key
 * @param mixed $default Default value
 * @return mixed
 */
if ( ! function_exists( 'rpress_wpml_filter_rpress_get_option' ) ) {
	function rpress_wpml_filter_rpress_get_option( $value, $key, $default ) {
		if ( ! rpress_wpml_is_active() ) {
			return $value;
		}

		global $rpress_wpml_email_lang;

		$target_lang = ! empty( $rpress_wpml_email_lang ) ? $rpress_wpml_email_lang : rpress_wpml_get_current_language();

		return rpress_wpml_translate_setting_value( $value, $key, $target_lang, $default );
	}
}

/**
 * Switch WPML context to the order language prior to email rendering.
 *
 * @since 3.4.4
 * @param int $payment_id Payment ID
 * @return void
 */
if ( ! function_exists( 'rpress_wpml_switch_language_for_order' ) ) {
	function rpress_wpml_switch_language_for_order( $payment_id ) {
		if ( ! rpress_wpml_is_active() || empty( $payment_id ) ) {
			return;
		}

		$order_lang = rpress_wpml_get_payment_language( $payment_id );
		if ( ! empty( $order_lang ) ) {
			global $rpress_wpml_email_lang, $rpress_wpml_orig_lang;

			$rpress_wpml_email_lang = $order_lang;

			if ( $rpress_wpml_orig_lang === null ) {
				$rpress_wpml_orig_lang = apply_filters( 'wpml_current_language', null );
			}

			do_action( 'wpml_switch_language', $order_lang );
		}
	}
}

/**
 * Hook into rpress_purchase_from_name to ensure order language is switched before receipt email is constructed.
 *
 * @since 3.4.4
 * @param string $from_name Sender name
 * @param int $payment_id Payment ID
 * @param array $payment_data Payment metadata
 * @return string
 */
if ( ! function_exists( 'rpress_wpml_before_purchase_receipt_email' ) ) {
	function rpress_wpml_before_purchase_receipt_email( $from_name, $payment_id, $payment_data = array() ) {
		if ( ! empty( $payment_id ) ) {
			rpress_wpml_switch_language_for_order( $payment_id );
		}
		return $from_name;
	}
}

/**
 * Restore original WPML language after email dispatch.
 *
 * @since 3.4.4
 * @return void
 */
if ( ! function_exists( 'rpress_wpml_restore_language' ) ) {
	function rpress_wpml_restore_language() {
		global $rpress_wpml_email_lang, $rpress_wpml_orig_lang;

		$rpress_wpml_email_lang = null;

		if ( rpress_wpml_is_active() && $rpress_wpml_orig_lang !== null ) {
			do_action( 'wpml_switch_language', $rpress_wpml_orig_lang );
			$rpress_wpml_orig_lang = null;
		}
	}
}

// Hook into RestroPress option lookup to support WPML translations
if ( ! has_filter( 'rpress_get_option', 'rpress_wpml_filter_rpress_get_option' ) ) {
	add_filter( 'rpress_get_option', 'rpress_wpml_filter_rpress_get_option', 10, 3 );
}

// Order language tracking
if ( ! has_action( 'rpress_insert_payment', 'rpress_wpml_save_payment_language' ) ) {
	add_action( 'rpress_insert_payment', 'rpress_wpml_save_payment_language', 10, 2 );
}

// Switch order language when email triggers fire
if ( ! has_action( 'rpress_complete_purchase', 'rpress_wpml_switch_language_for_order' ) ) {
	add_action( 'rpress_complete_purchase', 'rpress_wpml_switch_language_for_order', 1, 1 );
}
if ( ! has_action( 'rpress_update_order_status', 'rpress_wpml_switch_language_for_order' ) ) {
	add_action( 'rpress_update_order_status', 'rpress_wpml_switch_language_for_order', 1, 1 );
}
if ( ! has_filter( 'rpress_purchase_from_name', 'rpress_wpml_before_purchase_receipt_email' ) ) {
	add_filter( 'rpress_purchase_from_name', 'rpress_wpml_before_purchase_receipt_email', 1, 3 );
}

// Restore original language after email dispatch
if ( ! has_action( 'rpress_email_send_after', 'rpress_wpml_restore_language' ) ) {
	add_action( 'rpress_email_send_after', 'rpress_wpml_restore_language', 999 );
}
if ( ! has_action( 'rpress_admin_order_notice', 'rpress_wpml_restore_language' ) ) {
	add_action( 'rpress_admin_order_notice', 'rpress_wpml_restore_language', 999 );
}
