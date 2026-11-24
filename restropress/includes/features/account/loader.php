<?php
/**
 * Customer account sections (3.5.1).
 *
 * Every section of the [customer_dashboard] page has its own URL, the way
 * WooCommerce My Account does: /my-account/ (Profile), /my-account/orders/,
 * /my-account/addresses/ and /my-account/reservations/ (plain permalinks:
 * ?page_id=N&orders). Only the open section is built, Back/refresh/bookmarks
 * work, and extensions add a section through the rpress_account_sections
 * filter.
 *
 * @package RestroPress
 * @since   3.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/orders.php';

/**
 * The account sections, in sidebar order.
 *
 * Each section: label, icon (image URL), endpoint (URL slug, '' for the
 * default section), callback (prints the section), visible (bool or
 * callable returning bool), legacy_id (the 3.5 panel id, kept on the section
 * wrapper for theme CSS and old #user-xyz links).
 *
 * @since 3.5.1
 * @return array id => section.
 */
function rpress_account_sections() {
	$icons    = RP_PLUGIN_URL . 'assets/images/user/';
	$sections = array(
		'profile'   => array(
			'label'     => __( 'Profile', 'restropress' ),
			'icon'      => $icons . 'profile.png',
			'endpoint'  => '',
			'callback'  => 'rpress_account_render_profile',
			'legacy_id' => 'user-profile',
		),
		'orders'    => array(
			'label'     => __( 'Orders', 'restropress' ),
			'icon'      => $icons . 'my-order.png',
			'endpoint'  => 'orders',
			'callback'  => 'rpress_account_render_orders',
			'legacy_id' => 'user-my-orders',
		),
		'addresses' => array(
			'label'     => __( 'Addresses', 'restropress' ),
			'icon'      => $icons . 'address.png',
			'endpoint'  => 'addresses',
			'callback'  => 'rpress_account_render_addresses',
			'legacy_id' => 'user-my-address',
		),
	);

	/**
	 * Filter the customer account sections.
	 *
	 * @since 3.5.1
	 * @param array $sections id => array( label, icon, endpoint, callback, visible, legacy_id ).
	 */
	$sections = (array) apply_filters( 'rpress_account_sections', $sections );

	$slugs = rpress_account_filter_slugs( $sections );
	foreach ( $sections as $id => $section ) {
		$sections[ $id ] = wp_parse_args(
			(array) $section,
			array(
				'label'     => $id,
				'icon'      => '',
				'endpoint'  => '',
				'callback'  => '',
				'visible'   => true,
				'legacy_id' => 'user-' . $id,
			)
		);
		$sections[ $id ]['endpoint'] = isset( $slugs[ $id ] ) ? $slugs[ $id ] : '';
	}
	return $sections;
}

/**
 * Section id => URL slug, through the rpress_account_endpoints filter.
 *
 * @param array $sections Raw sections.
 * @return array
 */
function rpress_account_filter_slugs( $sections ) {
	$slugs = array();
	foreach ( $sections as $id => $section ) {
		if ( ! empty( $section['endpoint'] ) ) {
			$slugs[ $id ] = $section['endpoint'];
		}
	}
	/**
	 * Filter the account section URL slugs, e.g. to translate "orders".
	 *
	 * @since 3.5.1
	 * @param array $slugs Section id => slug.
	 */
	return array_filter( array_map( 'sanitize_title', (array) apply_filters( 'rpress_account_endpoints', $slugs ) ) );
}

/**
 * Section id => URL slug for every section that has its own URL.
 *
 * @since 3.5.1
 * @return array
 */
function rpress_account_endpoints() {
	return array_filter( wp_list_pluck( rpress_account_sections(), 'endpoint' ) );
}

/**
 * Register the section URLs under the account page only
 * (/my-account/orders/ => page_id=N&rpress_account_section=orders), and flush
 * the rewrite rules once when they change.
 *
 * Not add_rewrite_endpoint( EP_PAGES ): that would claim /<any-page>/orders/,
 * including the Order History page a default install creates at /checkout/orders/.
 *
 * @since 3.5.1
 */
function rpress_account_register_endpoints() {
	$slugs   = rpress_account_endpoints();
	$page_id = rpress_account_page_id();
	$uri     = $page_id ? get_page_uri( $page_id ) : '';
	if ( $uri && $slugs ) {
		add_rewrite_rule(
			preg_quote( $uri, '#' ) . '/(' . implode( '|', array_map( 'preg_quote', $slugs ) ) . ')/?$',
			'index.php?page_id=' . $page_id . '&rpress_account_section=$matches[1]',
			'top'
		);
	}
	$hash = md5( wp_json_encode( array( array_values( $slugs ), $page_id, $uri ) ) . '|' . RP_VERSION );
	if ( get_option( 'rpress_account_endpoints_hash' ) !== $hash ) {
		flush_rewrite_rules( false );
		update_option( 'rpress_account_endpoints_hash', $hash, true );
	}
}
add_action( 'init', 'rpress_account_register_endpoints', 20 );

/**
 * Query var carrying the section slug from the rewrite rule.
 *
 * @param array $vars Public query vars.
 * @return array
 */
function rpress_account_query_vars( $vars ) {
	$vars[] = 'rpress_account_section';
	return $vars;
}
add_filter( 'query_vars', 'rpress_account_query_vars' );

/**
 * ID of the published page with the [customer_dashboard] shortcode, 0 when none.
 *
 * @since 3.5.1
 * @return int
 */
function rpress_account_page_id() {
	$page_id = get_transient( 'rpress_customer_dashboard_page' );
	if ( false === $page_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- cached right below.
		$page_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s ORDER BY ID ASC LIMIT 1",
				'%' . $wpdb->esc_like( '[customer_dashboard' ) . '%'
			)
		);
		set_transient( 'rpress_customer_dashboard_page', $page_id, DAY_IN_SECONDS );
	}
	return (int) apply_filters( 'rpress_account_page_id', (int) $page_id );
}

/**
 * Forget the cached account page when a page is saved or deleted.
 *
 * @param int $post_id Post ID.
 */
function rpress_account_clear_page_cache( $post_id ) {
	if ( 'page' === get_post_type( $post_id ) ) {
		delete_transient( 'rpress_customer_dashboard_page' );
	}
}
add_action( 'save_post', 'rpress_account_clear_page_cache' );
add_action( 'before_delete_post', 'rpress_account_clear_page_cache' );

/**
 * URL of the account page, '' when there is none.
 *
 * @since 3.5.1
 * @return string
 */
function rpress_account_page_url() {
	$page_id = rpress_account_page_id();
	$url     = $page_id ? get_permalink( $page_id ) : '';
	return (string) apply_filters( 'rpress_account_page_url', $url ? $url : '', $page_id );
}

/**
 * URL of an account section, '' when there is no account page.
 *
 * @since 3.5.1
 * @param string $section Section id (profile, orders, addresses, reservations, ...).
 * @param int    $page_id Account page to build on; defaults to the [customer_dashboard] page.
 * @return string
 */
function rpress_account_url( $section = 'profile', $page_id = 0 ) {
	$page_id  = $page_id ? (int) $page_id : rpress_account_page_id();
	$base_url = $page_id ? get_permalink( $page_id ) : '';
	if ( ! $base_url ) {
		return '';
	}
	$slugs = rpress_account_endpoints();
	$url   = $base_url;
	if ( isset( $slugs[ $section ] ) ) {
		$slug     = $slugs[ $section ];
		$on_front = 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $page_id;
		$pretty   = get_option( 'permalink_structure' ) && ! $on_front && $page_id === rpress_account_page_id() && false === strpos( $base_url, '?' );
		if ( $pretty ) {
			$url = trailingslashit( $base_url ) . user_trailingslashit( $slug );
		} else {
			// Plain permalinks, the front page, or a second dashboard page:
			// the section rides in the query string (?orders).
			$url = add_query_arg( $slug, '', $base_url );
		}
	}
	return (string) apply_filters( 'rpress_account_url', $url, $section, $page_id );
}

/**
 * The section being viewed: the section whose endpoint is in the request,
 * else the default (first) section. A hidden section falls back to the default.
 *
 * @since 3.5.1
 * @return string Section id.
 */
function rpress_account_current_section() {
	$sections = rpress_account_sections();
	$default  = (string) key( $sections );
	$var      = (string) get_query_var( 'rpress_account_section' );
	foreach ( $sections as $id => $section ) {
		if ( '' === $section['endpoint'] ) {
			continue;
		}
		// Pretty: set by the rewrite rule. Plain, front page or another page: ?orders.
		if ( $var === $section['endpoint'] || isset( $_GET[ $section['endpoint'] ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- routing only.
			return rpress_account_section_visible( $section ) ? (string) $id : $default;
		}
	}
	return $default;
}

/**
 * Whether a section shows for the current customer.
 *
 * @param array $section Section.
 * @return bool
 */
function rpress_account_section_visible( $section ) {
	$visible = isset( $section['visible'] ) ? $section['visible'] : true;
	if ( is_callable( $visible ) ) {
		$visible = call_user_func( $visible );
	}
	return (bool) $visible;
}

/**
 * The sidebar: one link per visible section, the current one marked active.
 *
 * @since 3.5.1
 * @param string $current Current section id.
 * @param int    $page_id Account page ID.
 */
function rpress_account_sidebar( $current, $page_id ) {
	foreach ( rpress_account_sections() as $id => $section ) {
		if ( ! rpress_account_section_visible( $section ) ) {
			continue;
		}
		$active = ( $id === $current );
		printf(
			'<li class="%1$s"><a href="%2$s"%3$s>%4$s<span>%5$s</span></a></li>',
			esc_attr( sanitize_html_class( $section['legacy_id'] ) . ' rpress-account-nav-' . sanitize_html_class( $id ) . ( $active ? ' active' : '' ) ),
			esc_url( rpress_account_url( $id, $page_id ) ),
			$active ? ' aria-current="page"' : '',
			$section['icon'] ? '<img alt="" src="' . esc_attr( $section['icon'] ) . '" />' : '',
			esc_html( $section['label'] )
		);
	}
}

/**
 * Print a section.
 *
 * @since 3.5.1
 * @param string $id Section id.
 */
function rpress_account_render_section( $id ) {
	$sections = rpress_account_sections();
	if ( isset( $sections[ $id ] ) && is_callable( $sections[ $id ]['callback'] ) ) {
		call_user_func( $sections[ $id ]['callback'], $id );
	}
}

/**
 * Load an account template (theme override: restropress/account/<name>.php).
 *
 * @since 3.5.1
 * @param string $name Template name.
 * @param array  $args Variables for the template.
 */
function rpress_account_template( $name, $args = array() ) {
	$rpress_template = rpress_get_template_part( 'account/' . $name, null, false );
	if ( $rpress_template ) {
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template variables.
		extract( $args, EXTR_SKIP );
		include $rpress_template;
	}
}

/** Profile section. */
function rpress_account_render_profile() {
	rpress_account_template( 'profile' );
}

/** Orders section. */
function rpress_account_render_orders() {
	rpress_account_template( 'orders' );
}

/** Addresses section. */
function rpress_account_render_addresses() {
	rpress_account_template( 'addresses' );
}

/**
 * Old panel id => section URL, so 3.5 links like /my-account/#user-my-reservations
 * still open the right section.
 *
 * @since 3.5.1
 * @param int $page_id Account page ID.
 * @return array
 */
function rpress_account_legacy_hash_map( $page_id ) {
	$map = array();
	foreach ( rpress_account_sections() as $id => $section ) {
		$map[ $section['legacy_id'] ] = rpress_account_url( $id, $page_id );
	}
	return $map;
}
