<?php
/**
 * "Switch to RestroPress" import framework (3.5).
 *
 * One import engine plus small source adapters. Adapters turn a source
 * (an uploaded file, or data already on this site) into the intermediate
 * model (see class-rpress-importer-model.php); the engine previews it,
 * imports it in batches, logs it and can undo it.
 *
 * Add a source:
 *
 *   add_action( 'rpress_register_import_sources', function () {
 *       rpress_register_import_source( 'my-pos', array(
 *           'label'       => 'My POS',
 *           'description' => 'Menu export from My POS (.json).',
 *           'accepts'     => array( 'json' ),
 *           'detect'      => function ( $file ) { return false !== strpos( $file['contents'], '"mypos"' ); },
 *           'parse'       => function ( $file ) { return array( 'categories' => array() ); },
 *           'field_map'   => function () { return array( 'title' => 'Item name' ); },
 *       ) );
 *   } );
 *
 * @package RestroPress/Importer
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

define( 'RPRESS_IMPORTER_DIR', __DIR__ . '/' );

require_once RPRESS_IMPORTER_DIR . 'class-rpress-importer-model.php';
require_once RPRESS_IMPORTER_DIR . 'class-rpress-importer-engine.php';
require_once RPRESS_IMPORTER_DIR . 'class-rpress-importer-admin.php';
require_once RPRESS_IMPORTER_DIR . 'adapters/csv.php';
require_once RPRESS_IMPORTER_DIR . 'adapters/gloriafood.php';
require_once RPRESS_IMPORTER_DIR . 'adapters/woocommerce.php';

/**
 * Registered sources (internal store).
 *
 * @return array
 */
function &rpress_import_sources_store() {
	static $sources = array();
	return $sources;
}

/**
 * Register an import source.
 *
 * @since 3.5
 * @param string $id   Unique source id (sanitize_key).
 * @param array  $args {
 *     @type string        $label       Source name shown on the start screen.
 *     @type string        $description One-line description.
 *     @type string[]      $accepts     Allowed file extensions (empty = no upload; reads this site).
 *     @type callable|null $available   Returns true when the source can be used (optional).
 *     @type callable|null $detect      detect( $file ) => bool. $file is array( path, name, ext, contents ).
 *     @type callable      $parse       parse( $file_or_null ) => model array or WP_Error.
 *     @type callable|null $field_map   field_map() => array( source field => RestroPress field ).
 *     @type string        $help        Format notes (HTML, run through wp_kses_post).
 *     @type int           $priority    Sort order on the start screen.
 * }
 * @return bool
 */
function rpress_register_import_source( $id, $args ) {
	$id = sanitize_key( $id );
	if ( '' === $id || empty( $args['parse'] ) || ! is_callable( $args['parse'] ) ) {
		return false;
	}
	$defaults = array(
		'label'       => $id,
		'description' => '',
		'accepts'     => array(),
		'available'   => null,
		'detect'      => null,
		'parse'       => null,
		'field_map'   => null,
		'help'        => '',
		'priority'    => 50,
	);
	$args            = wp_parse_args( $args, $defaults );
	$args['id']      = $id;
	$args['accepts'] = array_values( array_filter( array_map( 'sanitize_key', (array) $args['accepts'] ) ) );

	$store        = &rpress_import_sources_store();
	$store[ $id ] = $args;
	return true;
}

/**
 * Remove a source.
 *
 * @since 3.5
 * @param string $id Source id.
 * @return void
 */
function rpress_unregister_import_source( $id ) {
	$store = &rpress_import_sources_store();
	unset( $store[ sanitize_key( $id ) ] );
}

/**
 * All registered sources, sorted by priority.
 *
 * @since 3.5
 * @return array
 */
function rpress_get_import_sources() {
	static $fired = false;
	if ( ! $fired && did_action( 'init' ) ) {
		$fired = true;
		/**
		 * Register import sources with rpress_register_import_source().
		 *
		 * @since 3.5
		 */
		do_action( 'rpress_register_import_sources' );
	}
	$sources = rpress_import_sources_store();
	uasort(
		$sources,
		function ( $a, $b ) {
			return (int) $a['priority'] - (int) $b['priority'];
		}
	);
	return apply_filters( 'rpress_import_sources', $sources );
}

/**
 * Whether a source can be used right now.
 *
 * @since 3.5
 * @param array $source Source args.
 * @return bool
 */
function rpress_import_source_available( $source ) {
	return empty( $source['available'] ) || ! is_callable( $source['available'] ) || (bool) call_user_func( $source['available'] );
}

/**
 * URL of the "Switch to RestroPress" screen, for the setup wizard and docs.
 *
 * @since 3.5
 * @param string $source Optional source id to preselect.
 * @return string
 */
function rpress_importer_url( $source = '' ) {
	$args = array(
		'post_type' => 'fooditem',
		'page'      => 'rpress-switch',
	);
	if ( $source ) {
		$args['source'] = sanitize_key( $source );
	}
	return apply_filters( 'rpress_importer_url', add_query_arg( $args, admin_url( 'edit.php' ) ), $source );
}

/**
 * Max upload size for import files, in bytes.
 *
 * @since 3.5
 * @return int
 */
function rpress_importer_max_upload_size() {
	return (int) min( wp_max_upload_size(), (int) apply_filters( 'rpress_importer_max_upload_size', 10 * MB_IN_BYTES ) );
}

add_action( 'init', array( 'RPress_Importer_Engine', 'register_post_type' ), 5 );
add_action( 'rpress_register_import_sources', 'rpress_importer_register_core_sources', 5 );

/**
 * Register the sources that ship with core.
 *
 * @return void
 */
function rpress_importer_register_core_sources() {
	rpress_register_import_source( 'gloriafood', RPress_Import_Source_GloriaFood::args() );
	rpress_register_import_source( 'woocommerce', RPress_Import_Source_WooCommerce::args() );
	rpress_register_import_source( 'csv', RPress_Import_Source_CSV::args() );
}

RPress_Importer_Admin::init();
