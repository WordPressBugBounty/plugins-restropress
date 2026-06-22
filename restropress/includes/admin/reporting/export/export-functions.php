<?php
/**
 * Exports Functions
 *
 * These are functions are used for exporting data from RestroPress.
 *
 * @package     RPRESS
 * @subpackage  Admin/Export
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
require_once RP_PLUGIN_DIR . 'includes/admin/reporting/class-export.php';
require_once RP_PLUGIN_DIR . 'includes/admin/reporting/export/export-actions.php';

/**
 * Validate a requested batch-export class name before instantiating it.
 *
 * The class name arrives from the request, so we must never instantiate it
 * blindly: an attacker-supplied name is a PHP object-injection primitive. A
 * legitimate exporter always extends RPRESS_Batch_Export, which keeps this
 * open to extension-registered exporters while rejecting arbitrary classes.
 * The export capability is also required here, up front, rather than relying
 * on a post-instantiation can_export() check.
 *
 * @since 3.3
 * @param string $class Requested export class name.
 * @return bool True when the class is safe to instantiate for this user.
 */
function rpress_is_allowed_export_class( $class ) {
	$class = is_string( $class ) ? trim( $class ) : '';
	if ( '' === $class || ! class_exists( $class ) ) {
		return false;
	}
	if ( ! is_subclass_of( $class, 'RPRESS_Batch_Export' ) ) {
		return false;
	}
	return (bool) apply_filters( 'rpress_export_capability', current_user_can( 'export_shop_reports' ) );
}
/**
 * Process batch exports via ajax
 *
 * @since 2.4
 * @return void
 */
function rpress_do_ajax_export() {
	require_once RP_PLUGIN_DIR . 'includes/admin/reporting/export/class-batch-export.php';
	parse_str( $_POST['form'], $form );
	$_REQUEST = $form = (array) $form;
	if( ! wp_verify_nonce( $_REQUEST['rpress_ajax_export'], 'rpress_ajax_export' ) ) {
		die( '-2' );
	}
	do_action( 'rpress_batch_export_class_include', $form['rpress-export-class'] );
	$step     = absint( $_POST['step'] );
	$class    = sanitize_text_field( $form['rpress-export-class'] );
	// Validate the class (exists, is a real exporter, user is capable) before
	// instantiating - never call `new $class` on an unvalidated request value.
	if( ! rpress_is_allowed_export_class( $class ) ) {
		die( '-1' );
	}
	$export   = new $class( $step );

	if( ! $export->can_export() ) {
		die( '-1' );
	}
	if ( ! $export->is_writable ) {
		echo wp_json_encode( array( 'error' => true, 'message' => esc_html__( 'Export location or file not writable', 'restropress' ) ) ); exit;
	}
	$export->set_properties( $_REQUEST );
	
	// Added in 2.5 to allow a bulk processor to pre-fetch some data to speed up the remaining steps and cache data
	$export->pre_fetch();
	$ret = $export->process_step( $step );
	$percentage = $export->get_percentage_complete();
	if( $ret ) {
		$step += 1;
		echo wp_json_encode( array( 'step' => $step, 'percentage' => $percentage ) ); exit;
	} elseif ( true === $export->is_empty ) {
		echo wp_json_encode( array( 'error' => true, 'message' => esc_html__( 'No data found for export parameters', 'restropress' ) ) ); exit;
	} elseif ( true === $export->done && true === $export->is_void ) {
		$message = ! empty( $export->message ) ? $export->message : esc_html__( 'Batch Processing Complete', 'restropress' );
		echo wp_json_encode( array( 'success' => true, 'message' => $message ) ); exit;
	} else {
		$args = array_merge( $form, array(
			'step'       => $step,
			'class'      => $class,
			'nonce'      => wp_create_nonce( 'rpress-batch-export' ),
			'rpress_action' => 'fooditem_batch_export',
		) );
		$fooditem_url = add_query_arg( $args, admin_url() );
		echo json_encode( array( 'step' => 'done', 'url' => $fooditem_url ) ); exit;
	}
}
add_action( 'wp_ajax_rpress_do_ajax_export', 'rpress_do_ajax_export' );
