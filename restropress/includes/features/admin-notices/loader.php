<?php
/**
 * Admin notification center (3.5.1).
 *
 * Three kinds of admin messages, three homes:
 * - Suggestions (turn on reorder, run the setup wizard, ...) live in a panel
 *   opened from a bell in the admin bar, with a count. The newest one is also
 *   a card on the RestroPress Dashboard.
 * - Problems (no checkout page, licence keys, ...) are listed in the panel in
 *   red, counted on the RestroPress menu item, and shown as one banner, only
 *   on the screens where they matter.
 * - Confirmations ("Discount code added.") are a short toast on the screen
 *   that triggered them, through the message bar the storefront uses.
 *
 * Extensions register with rpress_register_admin_notice() during the
 * `rpress_register_admin_notices` action, and queue confirmations with
 * rpress_admin_toast().
 *
 * @package RestroPress
 * @since   3.5.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register an admin notice.
 *
 * Call it during the `rpress_register_admin_notices` action.
 *
 * @since 3.5.1
 * @param string $id   Unique id (lowercase letters, digits, - and _).
 * @param array  $args {
 *     @type string   $kind        'suggestion' (default) or 'problem'.
 *     @type string   $title       Short title.
 *     @type string   $message     One or two sentences of plain text.
 *     @type array    $actions     List of { label, url, primary (bool), external (bool) }.
 *     @type string   $capability  Who sees it. Default manage_shop_settings.
 *     @type callable $condition   Returns true while the notice applies.
 *     @type bool     $dismissible Default true for suggestions, false for problems.
 *     @type callable $dismiss     Stores the dismissal (receives the user id). Without
 *                                 it the dismissal is kept in user meta.
 *     @type array    $screens     Problems only: page slugs or screen ids where the
 *                                 banner shows ('*' for every admin screen).
 *     @type int      $priority    Lower comes first. Default 10.
 * }
 * @return void
 */
function rpress_register_admin_notice( $id, $args ) {
	$id = sanitize_key( $id );
	if ( '' === $id ) {
		return;
	}
	$kind = isset( $args['kind'] ) && 'problem' === $args['kind'] ? 'problem' : 'suggestion';
	$GLOBALS['rpress_admin_notice_registry'][ $id ] = wp_parse_args(
		$args,
		array(
			'id'          => $id,
			'kind'        => $kind,
			'title'       => '',
			'message'     => '',
			'actions'     => array(),
			'capability'  => 'manage_shop_settings',
			'condition'   => '__return_true',
			'dismissible' => 'suggestion' === $kind,
			'dismiss'     => null,
			'screens'     => array(),
			'priority'    => 10,
		)
	);
	$GLOBALS['rpress_admin_notice_registry'][ $id ]['id']   = $id;
	$GLOBALS['rpress_admin_notice_registry'][ $id ]['kind'] = $kind;
	unset( $GLOBALS['rpress_admin_notices_cache'] );
}

/**
 * All registered notices (registration runs once per request).
 *
 * @since 3.5.1
 * @return array
 */
function rpress_admin_notice_registry() {
	if ( ! did_action( 'rpress_register_admin_notices' ) ) {
		do_action( 'rpress_register_admin_notices' );
	}
	return isset( $GLOBALS['rpress_admin_notice_registry'] ) ? (array) $GLOBALS['rpress_admin_notice_registry'] : array();
}

/**
 * User meta key for notices without their own dismiss storage.
 *
 * @param string $id Notice id.
 * @return string
 */
function rpress_admin_notice_meta_key( $id ) {
	return '_rpress_notice_' . $id . '_dismissed';
}

/**
 * Notices that apply to the current user right now: problems first.
 *
 * @since 3.5.1
 * @return array
 */
function rpress_get_admin_notices() {
	if ( isset( $GLOBALS['rpress_admin_notices_cache'] ) ) {
		return $GLOBALS['rpress_admin_notices_cache'];
	}
	$user_id = get_current_user_id();
	$list    = array();
	foreach ( rpress_admin_notice_registry() as $id => $notice ) {
		if ( ! current_user_can( $notice['capability'] ) ) {
			continue;
		}
		if ( ! $notice['dismiss'] && $notice['dismissible'] && get_user_meta( $user_id, rpress_admin_notice_meta_key( $id ), true ) ) {
			continue;
		}
		if ( ! is_callable( $notice['condition'] ) || ! call_user_func( $notice['condition'] ) ) {
			continue;
		}
		$list[] = $notice;
	}
	usort(
		$list,
		function ( $a, $b ) {
			if ( $a['kind'] !== $b['kind'] ) {
				return 'problem' === $a['kind'] ? -1 : 1;
			}
			return (int) $a['priority'] - (int) $b['priority'];
		}
	);
	$GLOBALS['rpress_admin_notices_cache'] = apply_filters( 'rpress_admin_notices', $list );
	return $GLOBALS['rpress_admin_notices_cache'];
}

/**
 * Number of problems for the current user (the RestroPress menu count).
 *
 * @since 3.5.1
 * @return int
 */
function rpress_admin_problem_count() {
	return count(
		array_filter(
			rpress_get_admin_notices(),
			function ( $n ) {
				return 'problem' === $n['kind'];
			}
		)
	);
}

/**
 * Whether the bell shows for this user.
 *
 * @return bool
 */
function rpress_admin_notices_enabled() {
	return is_admin() && is_user_logged_in() && current_user_can( apply_filters( 'rpress_admin_notices_capability', 'manage_shop_settings' ) );
}

/**
 * Queue a confirmation toast for this request.
 *
 * @since 3.5.1
 * @param string $message Plain text.
 * @param string $type    'success' (default), 'info' or 'error'.
 * @return void
 */
function rpress_admin_toast( $message, $type = 'success' ) {
	$message = trim( wp_strip_all_tags( (string) $message ) );
	if ( '' === $message ) {
		return;
	}
	$GLOBALS['rpress_admin_toasts'][] = array(
		'message' => $message,
		'type'    => in_array( $type, array( 'success', 'info', 'error' ), true ) ? $type : 'success',
	);
}

/* -------------------------------------------------------------------------- */
/* Core notices                                                               */
/* -------------------------------------------------------------------------- */

/**
 * Register core's suggestions and problems.
 */
function rpress_admin_notices_register_core() {
	$settings_url = admin_url( 'admin.php?page=rpress-settings' );

	// Problems.
	// Same dismiss storage as the old Dismiss Notice links (_rpress_set_*_dismissed).
	$page_missing = function ( $option, $key ) {
		if ( get_user_meta( get_current_user_id(), '_rpress_' . $key . '_dismissed', true ) ) {
			return false;
		}
		$page = rpress_get_option( $option, '' );
		return '' === $page || 'trash' === get_post_status( $page );
	};
	$page_dismiss = function ( $key ) {
		return function ( $user_id ) use ( $key ) {
			update_user_meta( $user_id, '_rpress_' . $key . '_dismissed', 1 );
		};
	};
	rpress_register_admin_notice(
		'set_menupage',
		array(
			'kind'        => 'problem',
			'title'       => __( 'No menu page', 'restropress' ),
			'message'     => __( 'No menu items page has been configured, so customers have nowhere to order from.', 'restropress' ),
			'actions'     => array( array( 'label' => __( 'Choose a page', 'restropress' ), 'url' => $settings_url, 'primary' => true ) ),
			'capability'  => 'edit_pages',
			'condition'   => function () use ( $page_missing ) {
				return $page_missing( 'food_items_page', 'set_menupage' );
			},
			'dismissible' => true,
			'dismiss'     => $page_dismiss( 'set_menupage' ),
			'screens'     => array( 'rpress-settings', 'rpress-dashboard' ),
			'priority'    => 1,
		)
	);
	rpress_register_admin_notice(
		'set_checkout',
		array(
			'kind'        => 'problem',
			'title'       => __( 'No checkout page', 'restropress' ),
			'message'     => __( 'No checkout page has been configured, so customers cannot place orders.', 'restropress' ),
			'actions'     => array( array( 'label' => __( 'Choose a page', 'restropress' ), 'url' => $settings_url, 'primary' => true ) ),
			'capability'  => 'edit_pages',
			'condition'   => function () use ( $page_missing ) {
				return $page_missing( 'purchase_page', 'set_checkout' );
			},
			'dismissible' => true,
			'dismiss'     => $page_dismiss( 'set_checkout' ),
			'screens'     => array( 'rpress-settings', 'rpress-dashboard' ),
			'priority'    => 2,
		)
	);
	rpress_register_admin_notice(
		'test_mode',
		array(
			'kind'       => 'problem',
			'title'      => __( 'Test mode is on', 'restropress' ),
			'message'    => __( 'While test mode is on no live payments are taken.', 'restropress' ),
			'actions'    => array( array( 'label' => __( 'Payment settings', 'restropress' ), 'url' => admin_url( 'admin.php?page=rpress-settings&tab=gateways' ), 'primary' => true ) ),
			'capability' => 'view_shop_reports',
			'condition'  => 'rpress_is_test_mode',
			'screens'    => array( 'rpress-payment-history' ),
			'priority'   => 20,
		)
	);
	if ( function_exists( 'rp_addon_licence_problem' ) ) {
		rpress_register_admin_notice(
			'licences',
			array(
				'kind'      => 'problem',
				'title'     => __( 'Licence keys need attention', 'restropress' ),
				'message'   => __( 'One or more RestroPress extensions have a missing, invalid or expired licence key, so they will not get updates.', 'restropress' ),
				'actions'   => array( array( 'label' => __( 'Update licences', 'restropress' ), 'url' => admin_url( 'admin.php?page=rpress-extensions' ), 'primary' => true ) ),
				'condition' => 'rp_addon_licence_problem',
				'screens'   => array( 'rpress-extensions', 'rpress-dashboard' ),
				'priority'  => 5,
			)
		);
	}
}
add_action( 'rpress_register_admin_notices', 'rpress_admin_notices_register_core', 5 );

/* -------------------------------------------------------------------------- */
/* Dismiss                                                                    */
/* -------------------------------------------------------------------------- */

/**
 * Store a dismissal for the current user.
 *
 * @param string $id Notice id.
 * @return bool
 */
function rpress_dismiss_admin_notice( $id ) {
	$registry = rpress_admin_notice_registry();
	if ( ! isset( $registry[ $id ] ) ) {
		return false;
	}
	$notice = $registry[ $id ];
	if ( ! $notice['dismissible'] || ! current_user_can( $notice['capability'] ) ) {
		return false;
	}
	$user_id = get_current_user_id();
	if ( is_callable( $notice['dismiss'] ) ) {
		call_user_func( $notice['dismiss'], $user_id );
	} else {
		update_user_meta( $user_id, rpress_admin_notice_meta_key( $id ), time() );
	}
	unset( $GLOBALS['rpress_admin_notices_cache'] );
	return true;
}

/**
 * AJAX: dismiss from the panel or the banner.
 */
function rpress_admin_notices_ajax_dismiss() {
	check_ajax_referer( 'rpress-admin-notices', 'nonce' );
	$id = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
	if ( ! rpress_dismiss_admin_notice( $id ) ) {
		wp_send_json_error( array( 'message' => __( 'That notification could not be dismissed.', 'restropress' ) ), 400 );
	}
	$notices = rpress_get_admin_notices();
	wp_send_json_success(
		array(
			'count'    => count( $notices ),
			'problems' => rpress_admin_problem_count(),
		)
	);
}
add_action( 'wp_ajax_rpress_dismiss_admin_notice', 'rpress_admin_notices_ajax_dismiss' );

/* -------------------------------------------------------------------------- */
/* Bell, panel, menu count, banner, Dashboard card                            */
/* -------------------------------------------------------------------------- */

/**
 * Bell with a count in the admin bar.
 *
 * @param WP_Admin_Bar $bar Admin bar.
 */
function rpress_admin_notices_admin_bar( $bar ) {
	if ( ! rpress_admin_notices_enabled() ) {
		return;
	}
	$notices  = rpress_get_admin_notices();
	$count    = count( $notices );
	$problems = rpress_admin_problem_count();
	/* translators: %d: number of notifications */
	$label = $count ? sprintf( _n( 'RestroPress: %d notification', 'RestroPress: %d notifications', $count, 'restropress' ), $count ) : __( 'RestroPress notifications', 'restropress' );
	$title = '<span class="ab-icon rpress-bell-icon" aria-hidden="true"></span>'
		. '<span class="rpress-bell-count' . ( $problems ? ' has-problems' : '' ) . ( $count ? '' : ' is-empty' ) . '" aria-hidden="true">' . esc_html( number_format_i18n( $count ) ) . '</span>'
		. '<span class="screen-reader-text rpress-bell-label">' . esc_html( $label ) . '</span>';
	$bar->add_node(
		array(
			'id'     => 'rpress-notifications',
			'parent' => 'top-secondary',
			'title'  => $title,
			'href'   => '#rpress-notice-panel',
			'meta'   => array(
				'class' => 'rpress-notifications-node',
				'title' => $label,
			),
		)
	);
}
add_action( 'admin_bar_menu', 'rpress_admin_notices_admin_bar', 85 );

/**
 * Markup for one notice (panel, banner and Dashboard card share it).
 *
 * @param array  $notice  Notice.
 * @param string $context 'panel', 'banner' or 'card'.
 * @return string
 */
function rpress_admin_notice_html( $notice, $context = 'panel' ) {
	$out  = '<div class="rpress-notice-item is-' . esc_attr( $notice['kind'] ) . '" data-notice="' . esc_attr( $notice['id'] ) . '">';
	$out .= '<div class="rpress-notice-body">';
	if ( 'problem' === $notice['kind'] && 'banner' !== $context ) {
		$out .= '<span class="rpress-notice-tag">' . esc_html__( 'Needs attention', 'restropress' ) . '</span>';
	}
	$out .= '<p class="rpress-notice-title"><strong>' . esc_html( $notice['title'] ) . '</strong></p>';
	if ( '' !== $notice['message'] ) {
		$out .= '<p class="rpress-notice-message">' . esc_html( $notice['message'] ) . '</p>';
	}
	$out .= '</div><p class="rpress-notice-actions">';
	foreach ( (array) $notice['actions'] as $action ) {
		if ( empty( $action['label'] ) || empty( $action['url'] ) ) {
			continue;
		}
		$external = ! empty( $action['external'] );
		$out     .= '<a class="button' . ( ! empty( $action['primary'] ) ? ' button-primary' : '' ) . '" href="' . esc_url( $action['url'] ) . '"'
			. ( $external ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html( $action['label'] )
			. ( $external ? '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'restropress' ) . '</span>' : '' ) . '</a>';
	}
	if ( $notice['dismissible'] ) {
		$out .= '<button type="button" class="button-link rpress-notice-dismiss" data-notice="' . esc_attr( $notice['id'] ) . '">'
			. esc_html( 'suggestion' === $notice['kind'] ? __( 'No thanks', 'restropress' ) : __( 'Dismiss', 'restropress' ) )
			. '<span class="screen-reader-text">: ' . esc_html( $notice['title'] ) . '</span></button>';
	}
	$out .= '</p></div>';
	return $out;
}

/**
 * The panel (hidden until the bell is clicked) and queued toasts.
 */
function rpress_admin_notices_footer() {
	if ( rpress_admin_notices_enabled() ) {
		$notices = rpress_get_admin_notices();
		echo '<div id="rpress-notice-panel" class="rpress-notice-panel" role="dialog" aria-modal="false" aria-labelledby="rpress-notice-panel-title" hidden>';
		echo '<div class="rpress-notice-panel-head"><h2 id="rpress-notice-panel-title" tabindex="-1">' . esc_html__( 'RestroPress notifications', 'restropress' ) . '</h2>';
		echo '<button type="button" class="rpress-notice-panel-close" aria-label="' . esc_attr__( 'Close notifications', 'restropress' ) . '"><span aria-hidden="true">&times;</span></button></div>';
		echo '<div class="rpress-notice-panel-list">';
		foreach ( $notices as $notice ) {
			echo rpress_admin_notice_html( $notice, 'panel' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the builder.
		}
		echo '</div><p class="rpress-notice-empty"' . ( $notices ? ' hidden' : '' ) . '>' . esc_html__( 'You are all set. New suggestions and anything that needs attention will show here.', 'restropress' ) . '</p>';
		echo '</div>';
	}
	$toasts = isset( $GLOBALS['rpress_admin_toasts'] ) ? (array) $GLOBALS['rpress_admin_toasts'] : array();
	if ( $toasts ) {
		echo '<script id="rpress-admin-toasts" type="application/json">' . wp_json_encode( array_values( $toasts ) ) . '</script>';
	}
}
add_action( 'admin_footer', 'rpress_admin_notices_footer', 5 );

/**
 * Whether a problem's banner belongs on the current screen.
 *
 * @param array $notice Notice.
 * @return bool
 */
function rpress_admin_notice_on_screen( $notice ) {
	$screens = (array) $notice['screens'];
	if ( in_array( '*', $screens, true ) ) {
		return true;
	}
	$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	$screen = function_exists( 'get_current_screen' ) && get_current_screen() ? (string) get_current_screen()->id : '';
	return ( '' !== $page && in_array( $page, $screens, true ) ) || ( '' !== $screen && in_array( $screen, $screens, true ) );
}

/**
 * At most one RestroPress banner per screen, and only for a problem that
 * belongs on it.
 */
function rpress_admin_notices_banner() {
	foreach ( rpress_get_admin_notices() as $notice ) {
		if ( 'problem' === $notice['kind'] && rpress_admin_notice_on_screen( $notice ) ) {
			echo '<div class="notice notice-error rpress-problem-banner">' . rpress_admin_notice_html( $notice, 'banner' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the builder.
			return;
		}
	}
}
add_action( 'admin_notices', 'rpress_admin_notices_banner', 5 );

/**
 * Problem count on the RestroPress menu item.
 */
function rpress_admin_notices_menu_count() {
	global $menu;
	if ( ! rpress_admin_notices_enabled() || ! is_array( $menu ) ) {
		return;
	}
	$problems = rpress_admin_problem_count();
	if ( ! $problems ) {
		return;
	}
	foreach ( $menu as $i => $item ) {
		if ( isset( $item[2] ) && 'restropress' === $item[2] ) {
			/* translators: %d: number of problems */
			$label          = sprintf( _n( '%d problem needs attention', '%d problems need attention', $problems, 'restropress' ), $problems );
			$menu[ $i ][0] .= ' <span class="awaiting-mod rpress-problem-count"><span class="pending-count" aria-hidden="true">' . esc_html( number_format_i18n( $problems ) ) . '</span><span class="screen-reader-text">' . esc_html( $label ) . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			break;
		}
	}
}
add_action( 'admin_menu', 'rpress_admin_notices_menu_count', 999 );

/**
 * Newest suggestion as a card on the RestroPress Dashboard.
 */
function rpress_admin_notices_dashboard_card() {
	if ( ! rpress_admin_notices_enabled() ) {
		return;
	}
	foreach ( rpress_get_admin_notices() as $notice ) {
		if ( 'suggestion' === $notice['kind'] ) {
			echo '<div class="rpress-notice-card">' . rpress_admin_notice_html( $notice, 'card' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the builder.
			return;
		}
	}
}
add_action( 'rpress_command_center_before_panels', 'rpress_admin_notices_dashboard_card' );

/* -------------------------------------------------------------------------- */
/* Assets                                                                     */
/* -------------------------------------------------------------------------- */

/**
 * Message bar (shared with the storefront) and the panel script on every
 * admin screen.
 */
function rpress_admin_notices_assets() {
	$base = RP_PLUGIN_URL . 'assets/';
	$dir  = RP_PLUGIN_DIR . 'assets/';
	$ver  = function ( $file ) use ( $dir ) {
		return file_exists( $dir . $file ) ? (string) filemtime( $dir . $file ) : RP_VERSION;
	};
	wp_enqueue_style( 'rp-notify', $base . 'css/rp-notify.css', array(), $ver( 'css/rp-notify.css' ) );
	if ( ! wp_script_is( 'jquery-tata-toast', 'registered' ) ) {
		rpress_admin_register_notify_script();
	}
	wp_enqueue_script( 'jquery-tata-toast' );
	// Also places the message bar clear of the admin menu, so it loads for everyone.
	wp_enqueue_style( 'rpress-admin-notices', $base . 'features/admin-notices/admin-notices.css', array( 'rp-notify' ), $ver( 'features/admin-notices/admin-notices.css' ) );
	wp_enqueue_script( 'rpress-admin-notices', $base . 'features/admin-notices/admin-notices.js', array( 'jquery-tata-toast' ), $ver( 'features/admin-notices/admin-notices.js' ), true );
	wp_localize_script(
		'rpress-admin-notices',
		'rpress_admin_notices',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'rpress-admin-notices' ),
			/* translators: %d: number of notifications */
			'label'   => __( 'RestroPress: %d notifications', 'restropress' ),
			'label1'  => __( 'RestroPress: 1 notification', 'restropress' ),
			'label0'  => __( 'RestroPress notifications', 'restropress' ),
			'failed'  => __( 'That notification could not be dismissed.', 'restropress' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'rpress_admin_notices_assets', 20 );

/**
 * The admin's `tata` toast handle now loads the message bar; its tata shim
 * keeps tata.success() / tata.error() calls (core and extensions) working.
 */
function rpress_admin_register_notify_script() {
	$file = RP_PLUGIN_DIR . 'assets/js/frontend/rp-notify.js';
	wp_deregister_script( 'jquery-tata-toast' );
	wp_register_script( 'jquery-tata-toast', RP_PLUGIN_URL . 'assets/js/frontend/rp-notify.js', array(), file_exists( $file ) ? (string) filemtime( $file ) : RP_VERSION, false );
	wp_localize_script(
		'jquery-tata-toast',
		'rpress_notify_vars',
		array(
			'generic_error' => __( 'Something went wrong. Please try again.', 'restropress' ),
			'dismiss'       => __( 'Dismiss', 'restropress' ),
			'title_error'   => __( 'Error', 'restropress' ),
			'title_success' => __( 'Success', 'restropress' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'rpress_admin_register_notify_script', 11 );

require_once __DIR__ . '/messages.php';
