<?php
/**
 * Import Functions
 *
 * These are functions are used for import data into RestroPress.
 *
 * @package     RPRESS
 * @subpackage  Admin/Import
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Upload an import file with ajax
 *
 * @since 1.0.0
 * @return void
 */
function rpress_do_ajax_import_file_upload() {
	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
	}
	require_once RP_PLUGIN_DIR . 'includes/admin/import/class-batch-import.php';
	if( ! wp_verify_nonce( sanitize_text_field( $_REQUEST['rpress_ajax_import'] ), 'rpress_ajax_import' ) ) {
		wp_send_json_error( array( 'error' => esc_html__( 'Nonce verification failed', 'restropress' ) ) );
	}
	if( empty( $_POST['rpress-import-class'] ) ) {
		wp_send_json_error( array( 'error' => esc_html__( 'Missing import parameters. Import class must be specified.', 'restropress' ), 'request' => $_REQUEST ) );
	}
	if( empty( $_FILES['rpress-import-file'] ) ) {
		wp_send_json_error( array( 'error' => esc_html__( 'Missing import file. Please provide an import file.', 'restropress' ), 'request' => $_REQUEST ) );
	}
	$accepted_mime_types = array(
		'text/csv',
		'text/comma-separated-values',
		'text/plain',
		'text/anytext',
		'text/*',
		'text/plain',
		'text/anytext',
		'text/*',
		'application/csv',
		'application/excel',
		'application/vnd.ms-excel',
		'application/vnd.msexcel',
	);
	if( empty( $_FILES['rpress-import-file']['type'] ) || ! in_array( strtolower( $_FILES['rpress-import-file']['type'] ), $accepted_mime_types ) ) {
		wp_send_json_error( array( 'error' => esc_html__( 'The file you uploaded does not appear to be a CSV file.', 'restropress' ), 'request' => $_REQUEST ) );
	}
	if( ! file_exists( sanitize_text_field( $_FILES['rpress-import-file']['tmp_name'] ) ) ){
		wp_send_json_error( array( 'error' => esc_html__( 'Something went wrong during the upload process, please try again.', 'restropress' ), 'request' => $_REQUEST ) );
	}
	// Let WordPress import the file. We will remove it after import is complete
	$import_file  = wp_handle_upload( $_FILES['rpress-import-file'], array( 'test_form' => false ) );
	$import_class = sanitize_text_field( $_POST['rpress-import-class'] );
	if ( $import_file && empty( $import_file['error'] ) ) {
		do_action( 'rpress_batch_import_class_include', $import_class );
		$import = new $import_class( $import_file['file'] );
		if( ! $import->can_import() ) {
			wp_send_json_error( array( 'error' => esc_html__( 'You do not have permission to import data', 'restropress' ) ) );
		}
		$form = array(
			'rpress-import-class'	=> $import_class,
			'rpress_ajax_import'	=> sanitize_text_field( $_POST['rpress_ajax_import'] ),
			'rpress-import-field'	=> array_map( 'sanitize_key', $_POST['rpress-import-field'] )
		);
		wp_send_json_success( array(
			'form'      => $form,
			'class'     => $import_class,
			'upload'    => $import_file,
			'first_row' => $import->get_first_row(),
			'columns'   => $import->get_columns(),
			'nonce'     => wp_create_nonce( 'rpress_ajax_import', 'rpress_ajax_import' )
		) );
	} else {
		/**
		 * Error generated by _wp_handle_upload()
		 * @see _wp_handle_upload() in wp-admin/includes/file.php
		 */
		wp_send_json_error( array( 'error' => $import_file['error'] ) );
	}
	exit;
}
add_action( 'rpress_upload_import_file', 'rpress_do_ajax_import_file_upload' );
/**
 * Process batch imports via ajax
 *
 * @since 1.0.0
 * @return void
 */
function rpress_do_ajax_import() {
	require_once RP_PLUGIN_DIR . 'includes/admin/import/class-batch-import.php';
	if( ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'rpress_ajax_import' ) ) {
		wp_send_json_error( array( 'error' => esc_html__( 'Nonce verification failed', 'restropress' ) ) );
	}
	if( empty( $_POST['class'] ) ) {
		wp_send_json_error( array( 'error' => esc_html__( 'Missing import parameters. Import class must be specified.', 'restropress' ) ) );
	}
	if( ! file_exists( sanitize_text_field( $_POST['upload']['file'] ) ) ) {
		wp_send_json_error( array( 'error' => esc_html__( 'Something went wrong during the upload process, please try again.', 'restropress' ) ) );
	}
	do_action( 'rpress_batch_import_class_include', sanitize_text_field( $_POST['class'] ) );
	$step     = absint( $_POST['step'] );
	$class    = sanitize_text_field( $_POST['class'] );
	$import   = new $class( sanitize_text_field( $_POST['upload']['file'] ), $step );
	if( ! $import->can_import() ) {
		wp_send_json_error( array( 'error' => esc_html__( 'You do not have permission to import data', 'restropress' ) ) );
	}
	$mapping =  sanitize_text_field( rawurldecode( $_POST['mapping'] ) );
	parse_str( $mapping, $map );
	$import->map_fields( $map['rpress-import-field'] );
	$ret = $import->process_step( $step );
	$percentage = $import->get_percentage_complete();
	if( $ret ) {
		$step += 1;
		wp_send_json_success( array(
			'step'       => $step,
			'percentage' => $percentage,
			'columns'    => $import->get_columns(),
			'mapping'    => $import->field_mapping,
			'total'      => $import->total
		) );
	} elseif ( true === $import->is_empty ) {
		wp_send_json_error( array(
			'error' => esc_html__( 'No data found for import parameters', 'restropress' )
		) );
	} else {
		wp_send_json_success( array(
			'step'    => 'done',
			'message' => sprintf( __( 'Import complete! <a href="%s">View imported %s</a>.', 'restropress' ),
				$import->get_list_table_url(),
				$import->get_import_type_label()
			)
		) );
	}
}
add_action( 'wp_ajax_rpress_do_ajax_import', 'rpress_do_ajax_import' );
