<?php
/**
 * Export Class
 *
 * This is the base class for all export methods. Each data export type (customers, payments, etc) extend this class
 *
 * @package     RPRESS
 * @subpackage  Admin/Reports
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since  1.0.0.4
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * RPRESS_Export Class
 *
 * @since  1.0.0
 */
class RPRESS_Export {
	/**
	 * Our export type. Used for export-type specific filters/actions
	 * @var string
	 * @since  1.0.0
	 */
	public $export_type = 'default';
	/**
	 * Can we export?
	 *
	 * @since  1.0.0
	 * @return bool Whether we can export or not
	 */
	public function can_export() {
		return (bool) apply_filters( 'rpress_export_capability', current_user_can( 'export_shop_reports' ) );
	}
	/**
	 * Set the export headers
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function headers() {
		ignore_user_abort( true );
		if ( ! rpress_is_func_disabled( 'set_time_limit' ) )
			set_time_limit( 0 );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=rpress-export-' . $this->export_type . '-' . gmdate( 'm-d-Y' ) . '.csv' );
		header( "Expires: 0" );
	}
	/**
	 * Set the CSV columns
	 *
	 * @since  1.0.0
	 * @return array $cols All the columns
	 */
	public function csv_cols() {
		$cols = array(
			'id'   => esc_html__( 'ID',   'restropress' ),
			'date' => esc_html__( 'Date', 'restropress' )
		);
		return $cols;
	}
	/**
	 * Retrieve the CSV columns
	 *
	 * @since  1.0.0
	 * @return array $cols Array of the columns
	 */
	public function get_csv_cols() {
		$cols = $this->csv_cols();
		return apply_filters( 'rpress_export_csv_cols_' . $this->export_type, $cols );
	}
	/**
	 * Output the CSV columns
	 *
	 * @since  1.0.0
	 * @uses RPRESS_Export::get_csv_cols()
	 * @return void
	 */
	public function csv_cols_out() {
		$cols = $this->get_csv_cols();
		$i = 1;
		foreach( $cols as $col_id => $column ) {
			echo '"' . addslashes( $column ) . '"';
			echo $i == count( $cols ) ? '' : ',';
			$i++;
		}
		echo "\r\n";
	}
	/**
	 * Get the data being exported
	 *
	 * @since  1.0.0
	 * @return array $data Data for Export
	 */
	public function get_data() {
		// Just a sample data array
		$data = array(
			0 => array(
				'id'   => '',
				'data' => gmdate( 'F j, Y' )
			),
			1 => array(
				'id'   => '',
				'data' => gmdate( 'F j, Y' )
			)
		);
		$data = apply_filters( 'rpress_export_get_data', $data );
		$data = apply_filters( 'rpress_export_get_data_' . $this->export_type, $data );
		return $data;
	}
	/**
	 * Output the CSV rows
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function csv_rows_out() {
		$data = $this->get_data();
		$cols = $this->get_csv_cols();
		// Output each row
		foreach ( $data as $row ) {
			$i = 1;
			foreach ( $row as $col_id => $column ) {
				// Make sure the column is valid
				if ( array_key_exists( $col_id, $cols ) ) {
					echo '"' . addslashes( $column ) . '"';
					echo $i == count( $cols ) ? '' : ',';
					$i++;
				}
			}
			echo "\r\n";
		}
	}
	/**
	 * Perform the export
	 *
	 * @since  1.0.0
	 * @uses RPRESS_Export::can_export()
	 * @uses RPRESS_Export::headers()
	 * @uses RPRESS_Export::csv_cols_out()
	 * @uses RPRESS_Export::csv_rows_out()
	 * @return void
	 */
	public function export() {
		if ( ! $this->can_export() )
			wp_die( esc_html__( 'You do not have permission to export data.', 'restropress' ), esc_html__( 'Error', 'restropress' ), array( 'response' => 403 ) );
		// Set headers
		$this->headers();
		// Output CSV columns (headers)
		$this->csv_cols_out();
		// Output CSV rows
		$this->csv_rows_out();
		rpress_die();
	}
}
