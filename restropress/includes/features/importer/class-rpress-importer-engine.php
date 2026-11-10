<?php
/**
 * Import framework: the engine.
 *
 * Jobs are private posts of type rpress_switch_import. A job holds the parsed
 * (and sanitized) model, the options chosen on the preview screen, progress
 * state, counts and a log. The job's post ID is the import ID: every post,
 * attachment and term the import creates gets meta _rpress_import_id = job ID,
 * so "Undo this import" removes exactly those records.
 *
 * Nothing touches the menu before RPress_Importer_Engine::start(). Work then
 * runs in small batches through run_step() so large menus never hit a PHP
 * time limit; the admin screen calls it over AJAX until it reports done.
 *
 * The meta written for food items mirrors the AI menu importer
 * (RPress_Onboarding::publish_payload and its save_fooditem_* helpers):
 * rpress_price, _variable_pricing, _rpress_price_options_mode,
 * rpress_variable_prices (0-based index => name/amount), food-category,
 * addon_category parent/child terms with _type/_price term meta, _addon_items,
 * and dietary terms. On top of that it stores what the AI path cannot:
 * per-size add-on prices, required/min/max and default options, in the same
 * _addon_items keys the food item editor saves.
 *
 * @package RestroPress/Importer
 * @since   3.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * Import engine.
 */
class RPress_Importer_Engine {

	const POST_TYPE = 'rpress_switch_import';
	const META_KEY  = '_rpress_import_id';
	const LOG_LIMIT = 500;

	/**
	 * Register the private job post type.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Switch Imports', 'restropress' ),
					'singular_name' => __( 'Switch Import', 'restropress' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'query_var'           => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'author' ),
				'can_export'          => false,
				'exclude_from_search' => true,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Jobs
	 * ------------------------------------------------------------------- */

	/**
	 * Create a job from adapter output. Saves only the job record.
	 *
	 * @param string $source_id Source id.
	 * @param array  $raw_model Adapter output (sanitized here).
	 * @param string $label     What was imported (file name or source label).
	 * @return int|WP_Error Job ID.
	 */
	public static function create_job( $source_id, $raw_model, $label = '' ) {
		$model = RPress_Importer_Model::normalize( $raw_model );
		if ( empty( $model['categories'] ) && empty( $model['customers'] ) && empty( $model['settings'] ) ) {
			return new WP_Error( 'rpress_import_empty', __( 'Nothing to import was found in this source.', 'restropress' ) );
		}

		$job_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'private',
					'post_title'  => sanitize_text_field( $label ? $label : $source_id ),
					'post_author' => get_current_user_id(),
				)
			),
			true
		);
		if ( is_wp_error( $job_id ) ) {
			return $job_id;
		}

		update_post_meta( $job_id, '_rpress_import_source', sanitize_key( $source_id ) );
		self::update_meta( $job_id, '_rpress_import_model', $model );
		update_post_meta( $job_id, '_rpress_import_status', 'preview' );
		self::update_meta( $job_id, '_rpress_import_counts', self::empty_counts() );
		self::update_meta( $job_id, '_rpress_import_log', array() );

		return (int) $job_id;
	}

	/**
	 * Whether a job exists.
	 *
	 * @param int $job_id Job ID.
	 * @return bool
	 */
	public static function is_job( $job_id ) {
		$post = get_post( absint( $job_id ) );
		return $post && self::POST_TYPE === $post->post_type;
	}

	/**
	 * Whether the current user may act on a job.
	 *
	 * @param int $job_id Job ID.
	 * @return bool
	 */
	public static function can_access( $job_id ) {
		$post = get_post( absint( $job_id ) );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}
		return (int) $post->post_author === get_current_user_id() || current_user_can( 'manage_shop_settings' );
	}

	/**
	 * Job status.
	 *
	 * @param int $job_id Job ID.
	 * @return string
	 */
	public static function get_status( $job_id ) {
		return (string) get_post_meta( $job_id, '_rpress_import_status', true );
	}

	/**
	 * Stored model.
	 *
	 * @param int $job_id Job ID.
	 * @return array
	 */
	public static function get_model( $job_id ) {
		$model = get_post_meta( $job_id, '_rpress_import_model', true );
		return is_array( $model ) ? array_merge( RPress_Importer_Model::empty_model(), $model ) : RPress_Importer_Model::empty_model();
	}

	/**
	 * Counters.
	 *
	 * @return array
	 */
	protected static function empty_counts() {
		return array(
			'created'            => 0,
			'updated'            => 0,
			'skipped'            => 0,
			'failed'             => 0,
			'trashed'            => 0,
			'terms'              => 0,
			'images'             => 0,
			'image_failures'     => 0,
			'customers'          => 0,
			'customers_skipped'  => 0,
			'settings'           => 0,
			'undo_deleted'       => 0,
			'undo_terms'         => 0,
			'undo_kept_terms'    => 0,
			'undo_restored'      => 0,
			'undo_customers'     => 0,
		);
	}

	/**
	 * Read counters.
	 *
	 * @param int $job_id Job ID.
	 * @return array
	 */
	public static function get_counts( $job_id ) {
		$counts = get_post_meta( $job_id, '_rpress_import_counts', true );
		return array_merge( self::empty_counts(), is_array( $counts ) ? $counts : array() );
	}

	/**
	 * Add to counters.
	 *
	 * @param int   $job_id Job ID.
	 * @param array $delta  Key => increment.
	 * @return void
	 */
	protected static function bump( $job_id, $delta ) {
		$counts = self::get_counts( $job_id );
		foreach ( $delta as $key => $value ) {
			$counts[ $key ] = ( isset( $counts[ $key ] ) ? (int) $counts[ $key ] : 0 ) + (int) $value;
		}
		self::update_meta( $job_id, '_rpress_import_counts', $counts );
	}

	/**
	 * Append to the job log.
	 *
	 * @param int    $job_id  Job ID.
	 * @param string $level   info|warning|error.
	 * @param string $message Message.
	 * @return void
	 */
	public static function log( $job_id, $level, $message ) {
		$log = get_post_meta( $job_id, '_rpress_import_log', true );
		$log = is_array( $log ) ? $log : array();
		if ( count( $log ) >= self::LOG_LIMIT ) {
			return;
		}
		$log[] = array(
			'level'   => in_array( $level, array( 'info', 'warning', 'error' ), true ) ? $level : 'info',
			'message' => sanitize_text_field( $message ),
		);
		self::update_meta( $job_id, '_rpress_import_log', $log );
	}

	/**
	 * Job log.
	 *
	 * @param int $job_id Job ID.
	 * @return array
	 */
	public static function get_log( $job_id ) {
		$log = get_post_meta( $job_id, '_rpress_import_log', true );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * update_post_meta unslashes; slash first so backslashes survive.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Value.
	 * @return void
	 */
	protected static function update_meta( $post_id, $key, $value ) {
		update_post_meta( $post_id, $key, wp_slash( $value ) );
	}

	/**
	 * Recent jobs for the start screen.
	 *
	 * @param int $limit Max jobs.
	 * @return array
	 */
	public static function recent_jobs( $limit = 10 ) {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_rpress_import_status',
						'value'   => array( 'running', 'done', 'undoing', 'undone' ),
						'compare' => 'IN',
					),
				),
			)
		);
		$jobs = array();
		foreach ( $ids as $job_id ) {
			$jobs[] = self::summary( $job_id );
		}
		return $jobs;
	}

	/**
	 * Delete preview jobs nobody confirmed (they hold parsed data only).
	 *
	 * @return void
	 */
	public static function cleanup_stale_previews() {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'date_query'     => array( array( 'before' => '1 day ago' ) ),
				'meta_key'       => '_rpress_import_status',
				'meta_value'     => 'preview',
			)
		);
		foreach ( $ids as $job_id ) {
			wp_delete_post( $job_id, true );
		}
	}

	/**
	 * Job summary for the UI.
	 *
	 * @param int $job_id Job ID.
	 * @return array
	 */
	public static function summary( $job_id ) {
		$post    = get_post( $job_id );
		$sources = function_exists( 'rpress_get_import_sources' ) ? rpress_get_import_sources() : array();
		$source  = (string) get_post_meta( $job_id, '_rpress_import_source', true );
		$state   = self::get_state( $job_id );
		return array(
			'id'       => (int) $job_id,
			'label'    => $post ? $post->post_title : '',
			'source'   => isset( $sources[ $source ] ) ? $sources[ $source ]['label'] : $source,
			'date'     => $post ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $post->post_date ) : '',
			'status'   => self::get_status( $job_id ),
			'counts'   => self::get_counts( $job_id ),
			'log'      => self::get_log( $job_id ),
			'progress' => self::progress( $state ),
			'options'  => isset( $state['options'] ) ? $state['options'] : array(),
		);
	}

	/* ---------------------------------------------------------------------
	 * Preview (dry run)
	 * ------------------------------------------------------------------- */

	/**
	 * Build the dry-run preview. Reads only; saves nothing.
	 *
	 * @param int $job_id Job ID.
	 * @return array
	 */
	public static function preview( $job_id ) {
		$model    = self::get_model( $job_id );
		$existing = self::existing_titles();
		$seen     = array();
		$rows     = array();
		$currency = function_exists( 'rpress_get_currency' ) ? rpress_get_currency() : '';

		foreach ( $model['categories'] as $category ) {
			foreach ( $category['items'] as $item ) {
				$key      = strtolower( $item['name'] );
				$warnings = $item['warnings'];
				if ( isset( $seen[ $key ] ) ) {
					$warnings[] = __( 'Appears more than once in this import.', 'restropress' );
				}
				$seen[ $key ] = true;

				$sizes = array();
				foreach ( $item['sizes'] as $size ) {
					$sizes[] = $size['name'] . ' ' . $size['price'];
				}
				$groups = array();
				foreach ( $item['addon_groups'] as $group ) {
					$flags = array();
					if ( $group['required'] ) {
						$flags[] = __( 'required', 'restropress' );
					}
					if ( $group['max'] ) {
						/* translators: %d: max choices */
						$flags[] = sprintf( __( 'max %d', 'restropress' ), $group['max'] );
					}
					$groups[] = $group['name'] . ' (' . count( $group['options'] ) . ( $flags ? ', ' . implode( ', ', $flags ) : '' ) . ')';
				}

				$rows[] = array(
					'category' => $category['name'],
					'name'     => $item['name'],
					'price'    => $item['sizes'] ? '' : $item['price'],
					'sizes'    => implode( ', ', $sizes ),
					'addons'   => implode( '; ', $groups ),
					'dietary'  => implode( ', ', array_merge( $item['dietary'], $item['allergens'] ) ),
					'image'    => ( $item['image'] || $item['image_id'] ) ? 1 : 0,
					'status'   => $item['status'],
					'exists'   => isset( $existing[ $key ] ) ? 1 : 0,
					'warnings' => array_values( array_unique( $warnings ) ),
				);
			}
		}

		$warnings = array_merge( $model['warnings'], self::permission_notices() );
		if ( $model['currency'] && $currency && strtoupper( $currency ) !== $model['currency'] ) {
			/* translators: 1: source currency, 2: store currency */
			$warnings[] = sprintf( __( 'Prices in this import are in %1$s but your store uses %2$s. Amounts are imported as they are, without conversion.', 'restropress' ), $model['currency'], $currency );
		}

		$source  = (string) get_post_meta( $job_id, '_rpress_import_source', true );
		$sources = rpress_get_import_sources();
		$map     = array();
		if ( isset( $sources[ $source ] ) && is_callable( $sources[ $source ]['field_map'] ) ) {
			$map = (array) call_user_func( $sources[ $source ]['field_map'] );
		}

		return array(
			'job_id'      => (int) $job_id,
			'permissions' => array(
				'replace' => self::can_replace() && self::can_trash(),
				'update'  => self::can_update_existing(),
			),
			'counts'      => RPress_Importer_Model::counts( $model ),
			'existing'    => count( array_filter( wp_list_pluck( $rows, 'exists' ) ) ),
			'rows'        => $rows,
			'warnings'    => array_values( array_unique( $warnings ) ),
			'settings'    => self::describe_settings( $model['settings'] ),
			'customers'   => count( $model['customers'] ),
			'field_map'   => $map,
		);
	}

	/**
	 * Human-readable settings list for the preview.
	 *
	 * @param array $settings Clean settings.
	 * @return array
	 */
	protected static function describe_settings( $settings ) {
		$out = array();
		if ( ! empty( $settings['store_hours'] ) ) {
			/* translators: %d: number of days */
			$out[] = sprintf( _n( 'Store hours for %d day', 'Store hours for %d days', count( $settings['store_hours'] ), 'restropress' ), count( $settings['store_hours'] ) );
		}
		if ( ! empty( $settings['holidays'] ) ) {
			/* translators: %s: dates */
			$out[] = sprintf( __( 'Holidays: %s', 'restropress' ), implode( ', ', array_slice( $settings['holidays'], 0, 5 ) ) );
		}
		if ( isset( $settings['delivery_fee'] ) ) {
			/* translators: %s: fee */
			$out[] = sprintf( __( 'Flat delivery fee: %s', 'restropress' ), $settings['delivery_fee'] );
		}
		if ( ! empty( $settings['delivery_zones'] ) ) {
			/* translators: %d: number of zones */
			$out[] = sprintf( __( '%d delivery zones (RestroPress core uses one flat fee; zones need the Delivery Fee extension)', 'restropress' ), count( $settings['delivery_zones'] ) );
		}
		if ( isset( $settings['minimum_order'] ) ) {
			/* translators: %s: amount */
			$out[] = sprintf( __( 'Minimum delivery order: %s', 'restropress' ), $settings['minimum_order'] );
		}
		if ( isset( $settings['minimum_order_pickup'] ) ) {
			/* translators: %s: amount */
			$out[] = sprintf( __( 'Minimum pickup order: %s', 'restropress' ), $settings['minimum_order_pickup'] );
		}
		if ( isset( $settings['tax_rate'] ) ) {
			/* translators: %s: rate */
			$out[] = sprintf( __( 'Tax rate: %s%%', 'restropress' ), $settings['tax_rate'] );
		}
		return $out;
	}

	/**
	 * Lower-cased titles of existing (non-trashed) food items.
	 *
	 * @return array title => true
	 */
	protected static function existing_titles() {
		global $wpdb;
		$titles = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft','inherit')",
				'fooditem'
			)
		);
		$out = array();
		foreach ( (array) $titles as $title ) {
			$out[ strtolower( trim( wp_specialchars_decode( $title, ENT_QUOTES ) ) ) ] = true;
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Import
	 * ------------------------------------------------------------------- */

	/**
	 * Sanitize run options.
	 *
	 * @param array $raw Raw options.
	 * @return array
	 */
	public static function sanitize_options( $raw ) {
		$raw        = is_array( $raw ) ? $raw : array();
		$can        = current_user_can( 'manage_shop_settings' );
		$mode       = isset( $raw['mode'] ) && 'replace' === $raw['mode'] ? 'replace' : 'add';
		$duplicates = isset( $raw['duplicates'] ) && 'update' === $raw['duplicates'] ? 'update' : 'skip';
		$notices    = array();
		if ( 'replace' === $mode && ! self::can_replace() ) {
			$mode      = 'add';
			$notices[] = self::notice_text( 'replace' );
		}
		if ( 'update' === $duplicates && ! self::can_update_existing() ) {
			$duplicates = 'skip';
			$notices[]  = self::notice_text( 'update' );
		}
		return array(
			'mode'       => $mode,
			'duplicates' => $duplicates,
			'settings'   => $can && ! empty( $raw['settings'] ),
			'customers'  => $can && ! empty( $raw['customers'] ),
			'notices'    => $notices,
		);
	}

	/**
	 * Whether the current user may use Replace mode (trashes every menu item).
	 *
	 * @return bool
	 */
	public static function can_replace() {
		return current_user_can( 'delete_others_products' ) || current_user_can( 'manage_shop_settings' );
	}

	/**
	 * Whether the current user may update existing same-name items.
	 *
	 * @return bool
	 */
	public static function can_update_existing() {
		return current_user_can( 'edit_others_products' );
	}

	/**
	 * Whether items can be moved to the trash. With EMPTY_TRASH_DAYS set to 0
	 * WordPress deletes posts permanently instead, so Replace mode (which
	 * relies on undo restoring trashed items) is refused.
	 *
	 * @return bool
	 */
	public static function can_trash() {
		$default = defined( 'EMPTY_TRASH_DAYS' ) ? (bool) EMPTY_TRASH_DAYS : true;
		/**
		 * Filter whether the importer may move menu items to the trash.
		 *
		 * @since 3.5
		 * @param bool $can_trash Defaults to (bool) EMPTY_TRASH_DAYS.
		 */
		return (bool) apply_filters( 'rpress_importer_can_trash', $default );
	}

	/**
	 * Message for an option the current user or site cannot use.
	 *
	 * @param string $which 'replace', 'update' or 'trash'.
	 * @return string
	 */
	public static function notice_text( $which ) {
		switch ( $which ) {
			case 'replace':
				return __( 'Your account cannot replace the whole menu, so this import adds items instead.', 'restropress' );
			case 'update':
				return __( 'Your account cannot edit menu items created by other users, so items with the same name are skipped instead of updated.', 'restropress' );
			default:
				return __( 'Replace mode is not available because the trash is turned off on this site (EMPTY_TRASH_DAYS is 0). Replaced items would be deleted permanently and undo could not bring them back. Use Add mode instead.', 'restropress' );
		}
	}

	/**
	 * Warnings for the preview about options the current user or site cannot use.
	 *
	 * @return array
	 */
	protected static function permission_notices() {
		$notices = array();
		if ( ! self::can_replace() ) {
			$notices[] = self::notice_text( 'replace' );
		} elseif ( ! self::can_trash() ) {
			$notices[] = self::notice_text( 'trash' );
		}
		if ( ! self::can_update_existing() ) {
			$notices[] = self::notice_text( 'update' );
		}
		return $notices;
	}

	/**
	 * Confirm a previewed job and queue the work.
	 *
	 * @param int   $job_id  Job ID.
	 * @param array $options Raw options.
	 * @return true|WP_Error
	 */
	public static function start( $job_id, $options ) {
		if ( 'preview' !== self::get_status( $job_id ) ) {
			return new WP_Error( 'rpress_import_state', __( 'This import has already been started.', 'restropress' ) );
		}
		$options = self::sanitize_options( $options );
		if ( 'replace' === $options['mode'] && ! self::can_trash() ) {
			return new WP_Error( 'rpress_import_no_trash', self::notice_text( 'trash' ) );
		}
		$notices = $options['notices'];
		unset( $options['notices'] );
		$model = self::get_model( $job_id );
		$total   = 0;
		foreach ( $model['categories'] as $category ) {
			$total += count( $category['items'] );
		}

		self::update_meta(
			$job_id,
			'_rpress_import_state',
			array(
				'phase'   => 'replace' === $options['mode'] ? 'trash' : 'settings',
				'offset'  => 0,
				'total'   => $total,
				'options' => $options,
			)
		);
		update_post_meta( $job_id, '_rpress_import_status', 'running' );
		/* translators: 1: mode, 2: duplicate handling */
		self::log( $job_id, 'info', sprintf( __( 'Import started (mode: %1$s, same-name items: %2$s).', 'restropress' ), $options['mode'], $options['duplicates'] ) );
		foreach ( $notices as $notice ) {
			self::log( $job_id, 'warning', $notice );
		}

		/**
		 * Fires when an import is confirmed, before any data is written.
		 *
		 * @param int   $job_id  Job ID (the import ID).
		 * @param array $options Options.
		 */
		do_action( 'rpress_import_started', $job_id, $options );
		return true;
	}

	/**
	 * Current state.
	 *
	 * @param int $job_id Job ID.
	 * @return array
	 */
	protected static function get_state( $job_id ) {
		$state = get_post_meta( $job_id, '_rpress_import_state', true );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Progress numbers for a state.
	 *
	 * @param array $state State.
	 * @return array
	 */
	protected static function progress( $state ) {
		$phase = isset( $state['phase'] ) ? $state['phase'] : '';
		$total = isset( $state['total'] ) ? (int) $state['total'] : 0;
		$done  = 'items' === $phase ? (int) $state['offset'] : ( in_array( $phase, array( 'customers', 'done' ), true ) ? $total : 0 );
		return array(
			'phase' => $phase,
			'done'  => min( $done, $total ),
			'total' => $total,
		);
	}

	/**
	 * Items handled per request.
	 *
	 * @return int
	 */
	protected static function batch_size() {
		return max( 1, (int) apply_filters( 'rpress_importer_batch_size', 10 ) );
	}

	/**
	 * Take a short lock so two tabs cannot run the same job at once.
	 *
	 * @param int $job_id Job ID.
	 * @return bool
	 */
	protected static function lock( $job_id ) {
		$key = 'rpress_import_lock_' . $job_id;
		if ( get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Release the lock.
	 *
	 * @param int $job_id Job ID.
	 * @return void
	 */
	protected static function unlock( $job_id ) {
		delete_transient( 'rpress_import_lock_' . $job_id );
	}

	/**
	 * Run one batch of an import.
	 *
	 * @param int $job_id Job ID.
	 * @return array|WP_Error Progress { phase, done, total, finished, busy }.
	 */
	public static function run_step( $job_id ) {
		$status = self::get_status( $job_id );
		if ( 'done' === $status ) {
			return array_merge( self::progress( self::get_state( $job_id ) ), array( 'finished' => true ) );
		}
		if ( 'running' !== $status ) {
			return new WP_Error( 'rpress_import_state', __( 'This import is not running.', 'restropress' ) );
		}
		if ( ! self::lock( $job_id ) ) {
			return array_merge( self::progress( self::get_state( $job_id ) ), array( 'finished' => false, 'busy' => true ) );
		}

		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		$state   = self::get_state( $job_id );
		$options = $state['options'];
		$model   = self::get_model( $job_id );

		switch ( $state['phase'] ) {
			case 'trash':
				$state = self::step_trash( $job_id, $state );
				break;
			case 'settings':
				if ( $options['settings'] && ! empty( $model['settings'] ) ) {
					self::apply_settings( $job_id, $model['settings'] );
				}
				$state['phase']  = 'items';
				$state['offset'] = 0;
				break;
			case 'items':
				$state = self::step_items( $job_id, $state, $model );
				break;
			case 'customers':
				if ( $options['customers'] && ! empty( $model['customers'] ) ) {
					self::import_customers( $job_id, $model['customers'] );
				}
				$state['phase'] = 'done';
				break;
		}

		self::update_meta( $job_id, '_rpress_import_state', $state );
		$finished = 'done' === $state['phase'];
		if ( $finished ) {
			update_post_meta( $job_id, '_rpress_import_status', 'done' );
			delete_post_meta( $job_id, '_rpress_import_images' );
			$counts = self::get_counts( $job_id );
			/* translators: 1: created, 2: updated, 3: skipped, 4: failed */
			self::log( $job_id, 'info', sprintf( __( 'Import finished: %1$d created, %2$d updated, %3$d skipped, %4$d failed.', 'restropress' ), $counts['created'], $counts['updated'], $counts['skipped'], $counts['failed'] ) );
			/**
			 * Fires when an import has finished.
			 *
			 * @param int $job_id Job ID (the import ID).
			 */
			do_action( 'rpress_import_finished', $job_id );
		}
		self::unlock( $job_id );

		return array_merge( self::progress( $state ), array( 'finished' => $finished ) );
	}

	/**
	 * Replace mode: move existing food items to the trash (undo restores them).
	 *
	 * @param int   $job_id Job ID.
	 * @param array $state  State.
	 * @return array
	 */
	protected static function step_trash( $job_id, $state ) {
		// Never delete permanently: without a trash, undo could not restore.
		if ( ! self::can_trash() ) {
			self::log( $job_id, 'error', self::notice_text( 'trash' ) );
			$state['phase'] = 'settings';
			return $state;
		}

		// Items that could not be trashed stay excluded, so the loop moves on.
		$failed = get_post_meta( $job_id, '_rpress_import_trash_failed', true );
		$failed = is_array( $failed ) ? array_map( 'intval', $failed ) : array();
		$ids    = get_posts(
			array(
				'post_type'      => 'fooditem',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'post__not_in'   => $failed,
			)
		);
		$trashed = get_post_meta( $job_id, '_rpress_import_trashed', true );
		$trashed = is_array( $trashed ) ? $trashed : array();
		$moved   = 0;
		foreach ( $ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( ! current_user_can( 'delete_post', $post_id ) ) {
				$failed[] = $post_id;
				/* translators: 1: item name, 2: item ID */
				self::log( $job_id, 'warning', sprintf( __( 'Left "%1$s" (#%2$d) in place: your account cannot delete it.', 'restropress' ), get_the_title( $post_id ), $post_id ) );
				continue;
			}
			if ( wp_trash_post( $post_id ) ) {
				$trashed[] = $post_id;
				$moved++;
			} else {
				$failed[] = $post_id;
				/* translators: 1: item name, 2: item ID */
				self::log( $job_id, 'warning', sprintf( __( 'Could not move "%1$s" (#%2$d) to the trash; it was left in place.', 'restropress' ), get_the_title( $post_id ), $post_id ) );
			}
		}
		self::update_meta( $job_id, '_rpress_import_trashed', $trashed );
		self::update_meta( $job_id, '_rpress_import_trash_failed', array_values( array_unique( $failed ) ) );
		if ( $moved ) {
			self::bump( $job_id, array( 'trashed' => $moved ) );
		}
		if ( count( $ids ) < 50 ) {
			/* translators: %d: number of items */
			self::log( $job_id, 'info', sprintf( __( 'Replace mode: moved %d existing menu items to the trash.', 'restropress' ), count( $trashed ) ) );
			$state['phase'] = 'settings';
		}
		return $state;
	}

	/**
	 * Import the next batch of items.
	 *
	 * @param int   $job_id Job ID.
	 * @param array $state  State.
	 * @param array $model  Model.
	 * @return array
	 */
	protected static function step_items( $job_id, $state, $model ) {
		$start = (int) $state['offset'];
		$end   = $start + self::batch_size();
		$index = 0;
		foreach ( $model['categories'] as $category ) {
			foreach ( $category['items'] as $item ) {
				if ( $index >= $start && $index < $end ) {
					self::import_item( $job_id, $category, $item, $state['options'] );
				}
				$index++;
				if ( $index >= $end ) {
					break 2;
				}
			}
		}
		$state['offset'] = min( $end, (int) $state['total'] );
		if ( $state['offset'] >= (int) $state['total'] ) {
			$state['phase'] = 'customers';
		}
		return $state;
	}

	/**
	 * Create or update one food item.
	 *
	 * @param int   $job_id   Job ID.
	 * @param array $category Category (name, description).
	 * @param array $item     Clean item.
	 * @param array $options  Options.
	 * @return int Post ID or 0.
	 */
	public static function import_item( $job_id, $category, $item, $options ) {
		$existing = 'replace' === $options['mode'] ? 0 : self::find_same_name( $item['name'], $job_id );
		$updating = false;

		if ( $existing && 'skip' === $options['duplicates'] ) {
			self::bump( $job_id, array( 'skipped' => 1 ) );
			/* translators: %s: item name */
			self::log( $job_id, 'info', sprintf( __( 'Skipped "%s": an item with this name already exists.', 'restropress' ), $item['name'] ) );
			return 0;
		}

		if ( $existing && ! current_user_can( 'edit_post', $existing ) ) {
			self::bump( $job_id, array( 'skipped' => 1 ) );
			/* translators: %s: item name */
			self::log( $job_id, 'warning', sprintf( __( 'Skipped "%s": an item with this name exists and your account cannot edit it.', 'restropress' ), $item['name'] ) );
			return 0;
		}

		if ( $existing ) {
			$updating = true;
			$post_id  = $existing;
			self::snapshot( $job_id, $post_id );
			if ( '' !== $item['description'] ) {
				wp_update_post(
					wp_slash(
						array(
							'ID'           => $post_id,
							'post_content' => $item['description'],
							'post_excerpt' => $item['description'],
						)
					)
				);
			}
		} else {
			$post_id = wp_insert_post(
				wp_slash(
					array(
						'post_type'    => 'fooditem',
						'post_status'  => $item['status'],
						'post_title'   => $item['name'],
						'post_content' => $item['description'],
						'post_excerpt' => $item['description'],
						'post_author'  => get_current_user_id(),
					)
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				self::bump( $job_id, array( 'failed' => 1 ) );
				/* translators: 1: item name, 2: error */
				self::log( $job_id, 'error', sprintf( __( 'Could not create "%1$s": %2$s', 'restropress' ), $item['name'], $post_id->get_error_message() ) );
				return 0;
			}
			update_post_meta( $post_id, self::META_KEY, (int) $job_id );
		}

		// Categories.
		$term_ids = array();
		foreach ( array_merge( array( $category['name'] ), $item['also_categories'] ) as $index => $category_name ) {
			$term_id = self::ensure_term( $job_id, $category_name, 'food-category', 0, 0 === $index && ! empty( $category['description'] ) ? $category['description'] : '' );
			if ( $term_id ) {
				$term_ids[] = $term_id;
			}
		}
		if ( $term_ids ) {
			wp_set_object_terms( $post_id, array_values( array_unique( $term_ids ) ), 'food-category', $updating );
		}

		self::save_prices( $post_id, $item );
		self::save_addons( $job_id, $post_id, $item );
		self::save_labels( $job_id, $post_id, $item, $updating );

		if ( $item['food_type'] ) {
			update_post_meta( $post_id, 'rpress_food_type', $item['food_type'] );
		}

		// Images.
		if ( ! $updating || ! has_post_thumbnail( $post_id ) ) {
			if ( $item['image_id'] && wp_attachment_is_image( $item['image_id'] ) ) {
				set_post_thumbnail( $post_id, $item['image_id'] );
			} elseif ( $item['image'] ) {
				self::attach_image( $job_id, $post_id, $item );
			}
		}

		foreach ( $item['warnings'] as $warning ) {
			self::log( $job_id, 'warning', $item['name'] . ': ' . $warning );
		}

		self::bump( $job_id, array( $updating ? 'updated' : 'created' => 1 ) );

		/**
		 * Fires after an imported item is saved.
		 *
		 * @param int   $post_id  Food item ID.
		 * @param array $item     Clean model item.
		 * @param int   $job_id   Import ID.
		 * @param bool  $updating Whether an existing item was updated.
		 */
		do_action( 'rpress_import_item_saved', $post_id, $item, $job_id, $updating );
		return (int) $post_id;
	}

	/**
	 * Find an existing food item with the same title, ignoring items this
	 * import created itself (two categories may hold items with one name).
	 *
	 * @param string $name   Title.
	 * @param int    $job_id Job ID.
	 * @return int
	 */
	protected static function find_same_name( $name, $job_id ) {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				WHERE p.post_type = 'fooditem' AND p.post_status NOT IN ('trash','auto-draft','inherit')
				AND p.post_title = %s AND ( m.meta_value IS NULL OR m.meta_value <> %s )
				ORDER BY p.ID ASC LIMIT 1",
				self::META_KEY,
				$name,
				(string) $job_id
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Save what an updated item looked like, once per job, for undo.
	 *
	 * @param int $job_id  Job ID.
	 * @param int $post_id Post ID.
	 * @return void
	 */
	protected static function snapshot( $job_id, $post_id ) {
		$snapshots = get_post_meta( $job_id, '_rpress_import_updated', true );
		$snapshots = is_array( $snapshots ) ? $snapshots : array();
		if ( isset( $snapshots[ $post_id ] ) ) {
			return;
		}
		$post = get_post( $post_id );
		$meta = array();
		foreach ( self::snapshot_meta_keys() as $key ) {
			$meta[ $key ] = metadata_exists( 'post', $post_id, $key ) ? get_post_meta( $post_id, $key, true ) : null;
		}
		$terms = array();
		foreach ( array( 'food-category', 'addon_category', 'dietary', 'fooditem_tag' ) as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				$ids                = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
				$terms[ $taxonomy ] = is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
			}
		}
		$snapshots[ $post_id ] = array(
			'post_content' => $post ? $post->post_content : '',
			'post_excerpt' => $post ? $post->post_excerpt : '',
			'meta'         => $meta,
			'terms'        => $terms,
		);
		self::update_meta( $job_id, '_rpress_import_updated', $snapshots );
	}

	/**
	 * Meta keys an update may change.
	 *
	 * @return array
	 */
	protected static function snapshot_meta_keys() {
		return array( 'rpress_price', '_variable_pricing', '_rpress_price_options_mode', 'rpress_variable_prices', '_addon_items', '_thumbnail_id', 'rpress_food_type' );
	}

	/**
	 * Prices, same meta as RPress_Onboarding::save_fooditem_prices().
	 *
	 * @param int   $post_id Post ID.
	 * @param array $item    Clean item.
	 * @return void
	 */
	protected static function save_prices( $post_id, $item ) {
		if ( $item['sizes'] ) {
			$prices = array();
			foreach ( array_values( $item['sizes'] ) as $index => $size ) {
				$prices[ $index ] = array(
					'name'   => $size['name'],
					'amount' => $size['price'],
				);
			}
			update_post_meta( $post_id, '_variable_pricing', 1 );
			update_post_meta( $post_id, '_rpress_price_options_mode', 'on' );
			self::update_meta( $post_id, 'rpress_variable_prices', $prices );
			update_post_meta( $post_id, 'rpress_price', $item['price'] );
		} else {
			update_post_meta( $post_id, 'rpress_price', $item['price'] );
			delete_post_meta( $post_id, '_variable_pricing' );
			delete_post_meta( $post_id, '_rpress_price_options_mode' );
			delete_post_meta( $post_id, 'rpress_variable_prices' );
		}
	}

	/**
	 * Add-on groups as addon_category terms plus the _addon_items meta the
	 * food item editor reads (category, items, prices, default, is_required,
	 * min_addons, max_addons).
	 *
	 * @param int   $job_id  Job ID.
	 * @param int   $post_id Post ID.
	 * @param array $item    Clean item.
	 * @return void
	 */
	protected static function save_addons( $job_id, $post_id, $item ) {
		$size_names  = wp_list_pluck( $item['sizes'], 'name' );
		$addon_items = array();
		$term_ids    = array();

		foreach ( $item['addon_groups'] as $group ) {
			$created  = false;
			$group_id = self::ensure_term( $job_id, $group['name'], 'addon_category', 0, '', $created );
			if ( ! $group_id ) {
				continue;
			}
			if ( $created ) {
				update_term_meta( $group_id, '_type', $group['type'] );
			}
			$term_ids[] = $group_id;
			$row        = isset( $addon_items[ $group_id ] ) ? $addon_items[ $group_id ] : array(
				'category'    => $group_id,
				'items'       => array(),
				'prices'      => array(),
				'default'     => array(),
				'is_required' => $group['required'] ? 'yes' : 'no',
				'min_addons'  => $group['min'] ? (string) $group['min'] : '',
				'max_addons'  => $group['max'] ? (string) $group['max'] : '',
			);

			foreach ( $group['options'] as $option ) {
				$option_created = false;
				$option_id      = self::ensure_term( $job_id, $option['name'], 'addon_category', $group_id, '', $option_created );
				if ( ! $option_id ) {
					continue;
				}
				if ( $option_created ) {
					update_term_meta( $option_id, '_price', $option['price'] );
				}
				$term_ids[]     = $option_id;
				$row['items'][] = $option_id;

				if ( $size_names ) {
					// Every size needs a value: core prices a missing size at 0.
					$per_size = array();
					foreach ( $size_names as $size_name ) {
						$per_size[ $size_name ] = isset( $option['prices'][ $size_name ] ) ? $option['prices'][ $size_name ] : $option['price'];
						if ( $option['default'] ) {
							$row['default'][] = $option_id . '|' . $size_name;
						}
					}
					$row['prices'][ $option_id ] = $per_size;
				} else {
					$row['prices'][ $option_id ] = $option['price'];
					if ( $option['default'] ) {
						$row['default'][] = (string) $option_id;
					}
				}
			}
			$row['items']               = array_values( array_unique( $row['items'] ) );
			$addon_items[ $group_id ] = $row;
		}

		wp_set_object_terms( $post_id, array_values( array_unique( $term_ids ) ), 'addon_category', false );
		if ( $addon_items ) {
			self::update_meta( $post_id, '_addon_items', $addon_items );
		} else {
			delete_post_meta( $post_id, '_addon_items' );
		}
	}

	/**
	 * Dietary labels (dietary taxonomy) and allergens (product tags).
	 *
	 * @param int   $job_id   Job ID.
	 * @param int   $post_id  Post ID.
	 * @param array $item     Clean item.
	 * @param bool  $updating Append instead of replace.
	 * @return void
	 */
	protected static function save_labels( $job_id, $post_id, $item, $updating ) {
		$dietary_tax = taxonomy_exists( 'dietary' ) ? 'dietary' : 'fooditem_tag';
		$sets        = array(
			$dietary_tax => $item['dietary'],
		);
		if ( $item['allergens'] ) {
			$allergen_terms = array();
			foreach ( $item['allergens'] as $allergen ) {
				/* translators: %s: allergen name */
				$allergen_terms[] = sprintf( __( 'Contains %s', 'restropress' ), strtolower( $allergen ) );
			}
			$sets['fooditem_tag'] = isset( $sets['fooditem_tag'] ) ? array_merge( $sets['fooditem_tag'], $allergen_terms ) : $allergen_terms;
		}
		foreach ( $sets as $taxonomy => $names ) {
			if ( empty( $names ) || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$ids = array();
			foreach ( $names as $name ) {
				$term_id = self::ensure_term( $job_id, $name, $taxonomy );
				if ( $term_id ) {
					$ids[] = $term_id;
				}
			}
			if ( $ids ) {
				wp_set_object_terms( $post_id, $ids, $taxonomy, $updating );
			}
		}
	}

	/**
	 * Find a term by name under a parent, or create and tag it.
	 *
	 * @param int    $job_id      Job ID.
	 * @param string $name        Term name.
	 * @param string $taxonomy    Taxonomy.
	 * @param int    $parent      Parent term ID.
	 * @param string $description Description for a new term.
	 * @param bool   $created     Set to true when the term was created.
	 * @return int Term ID or 0.
	 */
	protected static function ensure_term( $job_id, $name, $taxonomy, $parent = 0, $description = '', &$created = false ) {
		$created = false;
		$name    = trim( (string) $name );
		if ( '' === $name || ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}

		// term_exists() reads a numeric string as a term ID, so look those up by name only.
		$existing = is_numeric( $name ) ? null : term_exists( $name, $taxonomy, is_taxonomy_hierarchical( $taxonomy ) ? (int) $parent : null );
		// Name lookup also matches the seeded dietary labels case-insensitively.
		if ( ! $existing ) {
			$by_name = get_term_by( 'name', $name, $taxonomy );
			if ( $by_name && ( ! is_taxonomy_hierarchical( $taxonomy ) || (int) $by_name->parent === (int) $parent ) ) {
				$existing = array( 'term_id' => $by_name->term_id );
			}
		}
		if ( is_array( $existing ) && ! empty( $existing['term_id'] ) ) {
			return (int) $existing['term_id'];
		}
		if ( $existing && is_numeric( $existing ) ) {
			return (int) $existing;
		}

		$args = array( 'parent' => (int) $parent );
		if ( '' !== $description ) {
			$args['description'] = $description;
		}
		$term = wp_insert_term( wp_slash( $name ), $taxonomy, wp_slash( $args ) );
		if ( is_wp_error( $term ) ) {
			$existing_id = (int) $term->get_error_data( 'term_exists' );
			if ( $existing_id ) {
				return $existing_id;
			}
			/* translators: 1: term name, 2: error */
			self::log( $job_id, 'error', sprintf( __( 'Could not create "%1$s": %2$s', 'restropress' ), $name, $term->get_error_message() ) );
			return 0;
		}
		$created = true;
		update_term_meta( $term['term_id'], self::META_KEY, (int) $job_id );
		self::bump( $job_id, array( 'terms' => 1 ) );
		return (int) $term['term_id'];
	}

	/**
	 * Sideload an image URL as the item's featured image. Failures are
	 * warnings, never fatal. The same URL is downloaded once per import.
	 *
	 * @param int   $job_id  Job ID.
	 * @param int   $post_id Post ID.
	 * @param array $item    Clean item.
	 * @return void
	 */
	protected static function attach_image( $job_id, $post_id, $item ) {
		$cache = get_post_meta( $job_id, '_rpress_import_images', true );
		$cache = is_array( $cache ) ? $cache : array();
		$hash  = md5( $item['image'] );
		if ( ! empty( $cache[ $hash ] ) && wp_attachment_is_image( $cache[ $hash ] ) ) {
			set_post_thumbnail( $post_id, (int) $cache[ $hash ] );
			return;
		}

		/**
		 * Short-circuit image sideloading (tests, or custom media handling).
		 *
		 * @param null|int|WP_Error $attachment_id Attachment ID to use instead.
		 * @param string            $url           Image URL.
		 * @param int               $post_id       Food item ID.
		 */
		$attachment_id = apply_filters( 'rpress_import_pre_sideload_image', null, $item['image'], $post_id );
		if ( null === $attachment_id ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$attachment_id = media_sideload_image( $item['image'], $post_id, $item['name'], 'id' );
		}

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			self::bump( $job_id, array( 'image_failures' => 1 ) );
			/* translators: 1: item name, 2: error */
			self::log( $job_id, 'warning', sprintf( __( 'Image for "%1$s" could not be downloaded: %2$s', 'restropress' ), $item['name'], is_wp_error( $attachment_id ) ? $attachment_id->get_error_message() : __( 'unknown error', 'restropress' ) ) );
			return;
		}

		update_post_meta( $attachment_id, self::META_KEY, (int) $job_id );
		set_post_thumbnail( $post_id, (int) $attachment_id );
		$cache[ $hash ] = (int) $attachment_id;
		self::update_meta( $job_id, '_rpress_import_images', $cache );
		self::bump( $job_id, array( 'images' => 1 ) );
	}

	/**
	 * Store settings, keeping the old values for undo.
	 *
	 * @param int   $job_id   Job ID.
	 * @param array $settings Clean settings.
	 * @return void
	 */
	protected static function apply_settings( $job_id, $settings ) {
		$changes = array();

		if ( ! empty( $settings['store_hours'] ) ) {
			if ( function_exists( 'rpress_basic_store_hours_active' ) && ! rpress_basic_store_hours_active() ) {
				self::log( $job_id, 'warning', __( 'Store hours were not imported: the Store Timing extension manages your hours.', 'restropress' ) );
			} else {
				$changes['store_hours'] = $settings['store_hours'];
			}
		}
		if ( ! empty( $settings['holidays'] ) ) {
			if ( count( $settings['holidays'] ) > 5 ) {
				/* translators: %d: number of dates */
				self::log( $job_id, 'warning', sprintf( __( '%d holidays found; RestroPress core keeps the first five.', 'restropress' ), count( $settings['holidays'] ) ) );
			}
			$changes['basic_holidays'] = array_slice( $settings['holidays'], 0, 5 );
		}

		$fee = isset( $settings['delivery_fee'] ) ? $settings['delivery_fee'] : '';
		if ( ! empty( $settings['delivery_zones'] ) ) {
			$zone_fees = array_filter( wp_list_pluck( $settings['delivery_zones'], 'fee' ), 'strlen' );
			if ( '' === $fee && $zone_fees ) {
				$fee = number_format( min( array_map( 'floatval', $zone_fees ) ), 2, '.', '' );
			}
			/* translators: %d: number of zones */
			self::log( $job_id, 'warning', sprintf( __( '%d delivery zones found. RestroPress core charges one flat delivery fee (the lowest zone fee was used); set up zones with the Delivery Fee extension.', 'restropress' ), count( $settings['delivery_zones'] ) ) );
		}
		if ( '' !== $fee ) {
			if ( function_exists( 'rpress_flat_delivery_fee_active' ) && ! rpress_flat_delivery_fee_active() ) {
				self::log( $job_id, 'warning', __( 'Delivery fee was not imported: the Delivery Fee extension manages fees.', 'restropress' ) );
			} else {
				$changes['flat_delivery_fee'] = $fee;
			}
		}
		if ( isset( $settings['minimum_order'] ) ) {
			$changes['minimum_order_price'] = $settings['minimum_order'];
			$changes['allow_minimum_order'] = '1';
		}
		if ( isset( $settings['minimum_order_pickup'] ) ) {
			$changes['minimum_order_price_pickup'] = $settings['minimum_order_pickup'];
			$changes['allow_minimum_order']        = '1';
		}
		if ( isset( $settings['tax_rate'] ) ) {
			$changes['tax_rate'] = $settings['tax_rate'];
			if ( ! rpress_get_option( 'enable_taxes', false ) ) {
				self::log( $job_id, 'warning', __( 'Tax rate imported, but taxes are turned off. Turn them on under Settings > Taxes.', 'restropress' ) );
			}
		}

		$stored = get_option( 'rpress_settings', array() );
		$stored = is_array( $stored ) ? $stored : array();
		$before = array();
		foreach ( $changes as $key => $value ) {
			$before[ $key ] = array(
				'existed' => array_key_exists( $key, $stored ),
				'value'   => array_key_exists( $key, $stored ) ? $stored[ $key ] : null,
			);
			rpress_update_option( $key, $value );
		}
		self::update_meta( $job_id, '_rpress_import_settings_before', $before );
		self::bump( $job_id, array( 'settings' => count( $changes ) ) );
		if ( $changes ) {
			/* translators: %s: setting keys */
			self::log( $job_id, 'info', sprintf( __( 'Store settings updated: %s.', 'restropress' ), implode( ', ', array_keys( $changes ) ) ) );
		}
	}

	/**
	 * Create customers that do not exist yet. Never emails them.
	 *
	 * @param int   $job_id    Job ID.
	 * @param array $customers Clean customers.
	 * @return void
	 */
	protected static function import_customers( $job_id, $customers ) {
		if ( ! class_exists( 'RPRESS_Customer' ) || ! function_exists( 'RPRESS' ) ) {
			return;
		}
		$created = 0;
		$skipped = 0;
		foreach ( $customers as $customer ) {
			if ( RPRESS()->customers->get_customer_by( 'email', $customer['email'] ) ) {
				$skipped++;
				continue;
			}
			$record = new RPRESS_Customer();
			$name   = trim( $customer['first_name'] . ' ' . $customer['last_name'] );
			$id     = $record->create(
				array(
					'email' => $customer['email'],
					'name'  => $name,
					'notes' => '',
				)
			);
			if ( ! $id ) {
				$skipped++;
				continue;
			}
			$record->update_meta( self::META_KEY, (int) $job_id );
			if ( $customer['phone'] ) {
				$record->update_meta( 'phone', $customer['phone'] );
			}
			$created++;
		}
		self::bump(
			$job_id,
			array(
				'customers'         => $created,
				'customers_skipped' => $skipped,
			)
		);
		/* translators: 1: created, 2: skipped */
		self::log( $job_id, 'info', sprintf( __( 'Customers: %1$d added, %2$d already existed or were invalid. No emails were sent.', 'restropress' ), $created, $skipped ) );
	}

	/* ---------------------------------------------------------------------
	 * Undo
	 * ------------------------------------------------------------------- */

	/**
	 * Begin undoing a finished import.
	 *
	 * @param int $job_id Job ID.
	 * @return true|WP_Error
	 */
	public static function start_undo( $job_id ) {
		$status = self::get_status( $job_id );
		if ( ! in_array( $status, array( 'done', 'running' ), true ) ) {
			return new WP_Error( 'rpress_import_state', __( 'Only a finished import can be undone.', 'restropress' ) );
		}
		self::update_meta(
			$job_id,
			'_rpress_import_undo_state',
			array(
				'phase' => 'restore',
			)
		);
		update_post_meta( $job_id, '_rpress_import_status', 'undoing' );
		self::log( $job_id, 'info', __( 'Undo started.', 'restropress' ) );
		return true;
	}

	/**
	 * Run one batch of an undo.
	 *
	 * Order: restore updated items, untrash replaced items, delete created
	 * posts and attachments, delete created terms that nothing else uses,
	 * delete created customers, restore settings.
	 *
	 * @param int $job_id Job ID.
	 * @return array|WP_Error { phase, finished, busy }.
	 */
	public static function undo_step( $job_id ) {
		$status = self::get_status( $job_id );
		if ( 'undone' === $status ) {
			return array(
				'phase'    => 'done',
				'finished' => true,
			);
		}
		if ( 'undoing' !== $status ) {
			return new WP_Error( 'rpress_import_state', __( 'This import is not being undone.', 'restropress' ) );
		}
		if ( ! self::lock( $job_id ) ) {
			return array(
				'phase'    => 'busy',
				'finished' => false,
				'busy'     => true,
			);
		}

		$state = get_post_meta( $job_id, '_rpress_import_undo_state', true );
		$state = is_array( $state ) ? $state : array( 'phase' => 'restore' );
		$batch = 25;

		switch ( $state['phase'] ) {
			case 'restore':
				$snapshots = get_post_meta( $job_id, '_rpress_import_updated', true );
				foreach ( is_array( $snapshots ) ? $snapshots : array() as $post_id => $snapshot ) {
					self::restore_snapshot( (int) $post_id, $snapshot );
					self::bump( $job_id, array( 'undo_restored' => 1 ) );
				}
				$state['phase'] = 'untrash';
				break;

			case 'untrash':
				$trashed = get_post_meta( $job_id, '_rpress_import_trashed', true );
				$trashed = is_array( $trashed ) ? $trashed : array();
				$chunk   = array_splice( $trashed, 0, 50 );
				foreach ( $chunk as $post_id ) {
					if ( 'trash' === get_post_status( $post_id ) ) {
						wp_untrash_post( $post_id );
						// wp_untrash_post() restores to draft since WP 5.6; put back the old status.
						$old = get_post_meta( $post_id, '_wp_trash_meta_status', true );
						if ( $old && get_post_status( $post_id ) !== $old ) {
							wp_update_post(
								array(
									'ID'          => $post_id,
									'post_status' => $old,
								)
							);
						}
					}
				}
				self::update_meta( $job_id, '_rpress_import_trashed', $trashed );
				if ( empty( $trashed ) ) {
					$state['phase'] = 'posts';
				}
				break;

			case 'posts':
				$ids = get_posts(
					array(
						'post_type'      => array( 'fooditem', 'attachment' ),
						'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'inherit' ),
						'posts_per_page' => $batch,
						'fields'         => 'ids',
						'meta_key'       => self::META_KEY,
						'meta_value'     => (string) $job_id,
						'no_found_rows'  => true,
					)
				);
				foreach ( $ids as $post_id ) {
					if ( 'attachment' === get_post_type( $post_id ) ) {
						wp_delete_attachment( $post_id, true );
					} else {
						wp_delete_post( $post_id, true );
					}
				}
				self::bump( $job_id, array( 'undo_deleted' => count( $ids ) ) );
				if ( count( $ids ) < $batch ) {
					$state['phase'] = 'terms';
				}
				break;

			case 'terms':
				self::undo_terms( $job_id );
				$state['phase'] = 'customers';
				break;

			case 'customers':
				self::undo_customers( $job_id );
				$state['phase'] = 'settings';
				break;

			case 'settings':
				$before = get_post_meta( $job_id, '_rpress_import_settings_before', true );
				foreach ( is_array( $before ) ? $before : array() as $key => $old ) {
					if ( ! empty( $old['existed'] ) ) {
						rpress_update_option( $key, $old['value'] );
					} else {
						rpress_delete_option( $key );
					}
				}
				$state['phase'] = 'done';
				break;
		}

		self::update_meta( $job_id, '_rpress_import_undo_state', $state );
		$finished = 'done' === $state['phase'];
		if ( $finished ) {
			update_post_meta( $job_id, '_rpress_import_status', 'undone' );
			$counts = self::get_counts( $job_id );
			/* translators: 1: deleted posts, 2: deleted terms, 3: restored items */
			self::log( $job_id, 'info', sprintf( __( 'Undo finished: %1$d items and images deleted, %2$d terms deleted, %3$d updated items restored.', 'restropress' ), $counts['undo_deleted'], $counts['undo_terms'], $counts['undo_restored'] ) );
			/**
			 * Fires when an import has been undone.
			 *
			 * @param int $job_id Job ID (the import ID).
			 */
			do_action( 'rpress_import_undone', $job_id );
		}
		self::unlock( $job_id );

		return array(
			'phase'    => $state['phase'],
			'finished' => $finished,
		);
	}

	/**
	 * Put an updated item back the way it was.
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $snapshot Snapshot.
	 * @return void
	 */
	protected static function restore_snapshot( $post_id, $snapshot ) {
		if ( ! get_post( $post_id ) || ! is_array( $snapshot ) ) {
			return;
		}
		wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => $snapshot['post_content'],
					'post_excerpt' => $snapshot['post_excerpt'],
				)
			)
		);
		foreach ( $snapshot['meta'] as $key => $value ) {
			if ( null === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, wp_slash( $value ) );
			}
		}
		foreach ( $snapshot['terms'] as $taxonomy => $ids ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				wp_set_object_terms( $post_id, array_map( 'intval', $ids ), $taxonomy, false );
			}
		}
	}

	/**
	 * Delete the terms this import created, children first. A term that is
	 * still used by an item the import did not create is kept and logged.
	 *
	 * @param int $job_id Job ID.
	 * @return void
	 */
	protected static function undo_terms( $job_id ) {
		$taxonomies = array_values( array_filter( array( 'food-category', 'addon_category', 'dietary', 'fooditem_tag' ), 'taxonomy_exists' ) );
		$terms      = get_terms(
			array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => false,
				'meta_key'   => self::META_KEY,
				'meta_value' => (string) $job_id,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}
		usort(
			$terms,
			function ( $a, $b ) {
				return (int) ( 0 === (int) $a->parent ) - (int) ( 0 === (int) $b->parent );
			}
		);
		$deleted = 0;
		$kept    = 0;
		foreach ( $terms as $term ) {
			$objects = get_objects_in_term( $term->term_id, $term->taxonomy );
			$in_use  = ! is_wp_error( $objects ) && ! empty( $objects );
			if ( ! $in_use && 'addon_category' === $term->taxonomy && 0 === (int) $term->parent ) {
				// A group is in use when another group's items still sit under it.
				$children = get_terms(
					array(
						'taxonomy'   => 'addon_category',
						'parent'     => $term->term_id,
						'hide_empty' => false,
						'fields'     => 'ids',
					)
				);
				$in_use = ! is_wp_error( $children ) && ! empty( $children );
			}
			if ( $in_use ) {
				$kept++;
				/* translators: %s: term name */
				self::log( $job_id, 'warning', sprintf( __( 'Kept "%s": other menu items use it.', 'restropress' ), $term->name ) );
				delete_term_meta( $term->term_id, self::META_KEY );
				continue;
			}
			if ( ! is_wp_error( wp_delete_term( $term->term_id, $term->taxonomy ) ) ) {
				$deleted++;
			}
		}
		self::bump(
			$job_id,
			array(
				'undo_terms'      => $deleted,
				'undo_kept_terms' => $kept,
			)
		);
	}

	/**
	 * Delete customers this import created.
	 *
	 * @param int $job_id Job ID.
	 * @return void
	 */
	protected static function undo_customers( $job_id ) {
		global $wpdb;
		if ( ! function_exists( 'RPRESS' ) || empty( RPRESS()->customer_meta ) ) {
			return;
		}
		$table = RPRESS()->customer_meta->table_name;
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT customer_id FROM {$table} WHERE meta_key = %s AND meta_value = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::META_KEY,
				(string) $job_id
			)
		);
		$count = 0;
		foreach ( (array) $ids as $customer_id ) {
			$customer_id = (int) $customer_id;
			$customer    = RPRESS()->customers->get_customer_by( 'id', $customer_id );
			// Leave customers who have ordered since the import.
			if ( $customer && ! empty( $customer->payment_ids ) ) {
				RPRESS()->customer_meta->delete_meta( $customer_id, self::META_KEY );
				continue;
			}
			if ( $customer && RPRESS()->customers->delete( $customer_id ) ) {
				RPRESS()->customer_meta->delete_meta( $customer_id, self::META_KEY );
				RPRESS()->customer_meta->delete_meta( $customer_id, 'phone' );
				$count++;
			}
		}
		self::bump( $job_id, array( 'undo_customers' => $count ) );
	}
}
