<?php
/**
 * Webhooks: Settings > General > Webhooks.
 *
 * The section has no plain option fields. Its two panels (endpoints and the
 * delivery log, which also lists deliveries of targets registered by
 * extensions) are rendered through core's "hook" field type. The core
 * settings page is one form, so the panels contain no form of their own and
 * no named inputs: every change goes through admin-ajax.php (action
 * rpress_webhooks_admin) with a nonce and the manage_shop_settings capability.
 *
 * Signing secrets are never printed into the page. They are fetched on
 * demand by the "Show signing secret" button.
 *
 * @package RestroPress
 * @since   3.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'rpress_settings_sections_general', 'rpress_webhooks_settings_section' );
/**
 * Register the section.
 *
 * @param array $sections Sections.
 * @return array
 */
function rpress_webhooks_settings_section( $sections ) {
	$sections['webhooks'] = esc_html__( 'Webhooks', 'restropress' );
	return $sections;
}

add_filter( 'rpress_settings_general', 'rpress_webhooks_register_settings' );
/**
 * Register the panels.
 *
 * @param array $settings General settings.
 * @return array
 */
function rpress_webhooks_register_settings( $settings ) {
	$settings['webhooks'] = array(
		'webhooks_header'          => array(
			'id'            => 'webhooks_header',
			'name'          => '<h3>' . esc_html__( 'Webhooks', 'restropress' ) . '</h3>',
			'desc'          => '',
			'type'          => 'header',
			'tooltip_title' => esc_html__( 'Webhooks', 'restropress' ),
			'tooltip_desc'  => esc_html__( 'Send signed order, customer, reservation and menu item events to other systems, for example Make, n8n or your own code.', 'restropress' ),
		),
		'webhooks_endpoints_panel' => array(
			'id'   => 'webhooks_endpoints_panel',
			'name' => esc_html__( 'Endpoints', 'restropress' ),
			'type' => 'hook',
		),
		'webhooks_log_panel'       => array(
			'id'   => 'webhooks_log_panel',
			'name' => esc_html__( 'Delivery log', 'restropress' ),
			'type' => 'hook',
		),
	);
	return $settings;
}

/**
 * Whether the current admin screen is the Webhooks settings section.
 *
 * @return bool
 */
function rpress_webhooks_is_settings_screen() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen detection.
	$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
	// phpcs:enable
	return 'rpress-settings' === $page && 'webhooks' === $section;
}

/**
 * Local date and time for a unix timestamp.
 *
 * @param int $timestamp Unix seconds.
 * @return string
 */
function rpress_webhooks_admin_time( $timestamp ) {
	$timestamp = (int) $timestamp;
	if ( $timestamp <= 0 ) {
		return __( 'Never', 'restropress' );
	}
	return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
}

/**
 * Status labels of a delivery.
 *
 * @return array
 */
function rpress_webhooks_status_labels() {
	return array(
		'pending'  => __( 'Pending', 'restropress' ),
		'sending'  => __( 'Sending', 'restropress' ),
		'retrying' => __( 'Retrying', 'restropress' ),
		'success'  => __( 'Delivered', 'restropress' ),
		'failed'   => __( 'Failed', 'restropress' ),
	);
}

add_action( 'admin_enqueue_scripts', 'rpress_webhooks_admin_assets' );
/**
 * Script and styles, only on the Webhooks section.
 *
 * @return void
 */
function rpress_webhooks_admin_assets() {
	if ( ! rpress_webhooks_is_settings_screen() || ! current_user_can( 'manage_shop_settings' ) ) {
		return;
	}
	$js  = RP_PLUGIN_DIR . 'assets/features/webhooks/admin.js';
	$css = RP_PLUGIN_DIR . 'assets/features/webhooks/admin.css';
	wp_enqueue_style( 'rpress-webhooks-admin', RP_PLUGIN_URL . 'assets/features/webhooks/admin.css', array(), file_exists( $css ) ? (string) filemtime( $css ) : RP_VERSION );
	wp_enqueue_script( 'rpress-webhooks-admin', RP_PLUGIN_URL . 'assets/features/webhooks/admin.js', array( 'jquery' ), file_exists( $js ) ? (string) filemtime( $js ) : RP_VERSION, true );
	wp_localize_script(
		'rpress-webhooks-admin',
		'rpressWebhooksAdmin',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'rpress_webhooks_admin' ),
			'i18n'    => array(
				'working'       => __( 'Working...', 'restropress' ),
				'failed'        => __( 'The request failed. Please try again.', 'restropress' ),
				'confirmDelete' => __( 'Delete this endpoint? Deliveries waiting for it will not be sent.', 'restropress' ),
				'copied'        => __( 'Copied', 'restropress' ),
				'copy'          => __( 'Copy', 'restropress' ),
				'addEndpoint'   => __( 'Add endpoint', 'restropress' ),
				'saveEndpoint'  => __( 'Save endpoint', 'restropress' ),
				'hide'          => __( 'Hide details', 'restropress' ),
				'details'       => __( 'Details', 'restropress' ),
				'queued'        => __( 'Queued. It is sent within a minute or so.', 'restropress' ),
			),
		)
	);
}

add_action( 'rpress_webhooks_endpoints_panel', 'rpress_webhooks_render_endpoints' );
/**
 * Endpoints panel: list plus the add / edit fields.
 *
 * @return void
 */
function rpress_webhooks_render_endpoints() {
	$endpoints = rpress_webhooks_get_endpoints();
	$events    = rpress_webhooks_events();
	?>
	<div class="rpress-webhooks-panel" id="rpress-webhooks-endpoints">
		<p class="description"><?php esc_html_e( 'Each endpoint receives a signed JSON POST for the events you tick. Use it for Make, n8n or your own code.', 'restropress' ); ?></p>
		<div class="rpress-webhooks-notice" role="status" aria-live="polite" hidden></div>
		<?php if ( $endpoints ) : ?>
			<div class="rpress-webhooks-scroll">
				<table class="widefat striped rpress-webhooks-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Endpoint', 'restropress' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Events', 'restropress' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'restropress' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'restropress' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $endpoints as $endpoint ) : ?>
							<?php
							$active = ! empty( $endpoint['active'] );
							$labels = array();
							foreach ( (array) $endpoint['events'] as $event ) {
								$labels[] = $event;
							}
							?>
							<tr data-id="<?php echo esc_attr( $endpoint['id'] ); ?>" data-label="<?php echo esc_attr( $endpoint['label'] ); ?>" data-url="<?php echo esc_attr( $endpoint['url'] ); ?>" data-events="<?php echo esc_attr( implode( ',', (array) $endpoint['events'] ) ); ?>">
								<td>
									<strong><?php echo esc_html( $endpoint['label'] ); ?></strong><br>
									<code class="rpress-webhooks-url"><?php echo esc_html( $endpoint['url'] ); ?></code>
									<div class="rpress-webhooks-secret">
										<button type="button" class="button-link rpress-webhooks-action" data-op="reveal_secret"><?php esc_html_e( 'Show signing secret', 'restropress' ); ?></button>
										<span class="rpress-webhooks-secret-box" hidden>
											<code class="rpress-webhooks-secret-value"></code>
											<button type="button" class="button button-small rpress-webhooks-copy"><?php esc_html_e( 'Copy', 'restropress' ); ?></button>
										</span>
									</div>
								</td>
								<td class="rpress-webhooks-events-cell">
									<?php foreach ( $labels as $label ) : ?>
										<code><?php echo esc_html( $label ); ?></code>
									<?php endforeach; ?>
								</td>
								<td>
									<span class="rpress-webhooks-status rpress-webhooks-status--<?php echo $active ? 'success' : 'paused'; ?>"><?php echo $active ? esc_html__( 'Active', 'restropress' ) : esc_html__( 'Paused', 'restropress' ); ?></span>
								</td>
								<td class="rpress-webhooks-actions">
									<button type="button" class="button button-small rpress-webhooks-action" data-op="send_test"><?php esc_html_e( 'Send test', 'restropress' ); ?></button>
									<button type="button" class="button button-small rpress-webhooks-edit"><?php esc_html_e( 'Edit', 'restropress' ); ?></button>
									<button type="button" class="button button-small rpress-webhooks-action" data-op="toggle_endpoint" data-reload="1"><?php echo $active ? esc_html__( 'Pause', 'restropress' ) : esc_html__( 'Resume', 'restropress' ); ?></button>
									<button type="button" class="button button-small button-link-delete rpress-webhooks-action" data-op="delete_endpoint" data-reload="1" data-confirm="confirmDelete"><?php esc_html_e( 'Delete', 'restropress' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<p class="rpress-webhooks-empty"><?php esc_html_e( 'No endpoints yet. Add one below.', 'restropress' ); ?></p>
		<?php endif; ?>

		<div class="rpress-webhooks-form" id="rpress-webhooks-endpoint-form" data-id="">
			<h4 class="rpress-webhooks-form-title" data-add="<?php esc_attr_e( 'Add an endpoint', 'restropress' ); ?>" data-edit="<?php esc_attr_e( 'Edit endpoint', 'restropress' ); ?>"><?php esc_html_e( 'Add an endpoint', 'restropress' ); ?></h4>
			<p class="rpress-webhooks-field">
				<label for="rpress-webhooks-endpoint-label"><?php esc_html_e( 'Label', 'restropress' ); ?></label>
				<input type="text" id="rpress-webhooks-endpoint-label" class="regular-text" maxlength="100" autocomplete="off" placeholder="<?php esc_attr_e( 'For example: Make, new orders', 'restropress' ); ?>" />
			</p>
			<p class="rpress-webhooks-field">
				<label for="rpress-webhooks-endpoint-url"><?php esc_html_e( 'Target URL', 'restropress' ); ?></label>
				<input type="url" id="rpress-webhooks-endpoint-url" class="large-text" maxlength="2000" autocomplete="off" placeholder="https://" />
			</p>
			<fieldset class="rpress-webhooks-field">
				<legend><?php esc_html_e( 'Events', 'restropress' ); ?></legend>
				<div class="rpress-webhooks-events">
					<?php foreach ( $events as $key => $label ) : ?>
						<label><input type="checkbox" class="rpress-webhooks-endpoint-event" value="<?php echo esc_attr( $key ); ?>" /> <?php echo esc_html( $label ); ?> <code><?php echo esc_html( $key ); ?></code></label>
					<?php endforeach; ?>
				</div>
			</fieldset>
			<p class="rpress-webhooks-form-buttons">
				<button type="button" class="button button-primary" id="rpress-webhooks-endpoint-save"><?php esc_html_e( 'Add endpoint', 'restropress' ); ?></button>
				<button type="button" class="button" id="rpress-webhooks-endpoint-cancel" hidden><?php esc_html_e( 'Cancel', 'restropress' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'A signing secret is generated for you. Requests to private or local addresses are blocked by WordPress.', 'restropress' ); ?></p>
		</div>
	</div>
	<?php
}

add_action( 'rpress_webhooks_log_panel', 'rpress_webhooks_render_log' );
/**
 * Delivery log panel: newest first, paginated, with details and resend.
 *
 * @return void
 */
function rpress_webhooks_render_log() {
	global $wpdb;

	$table    = rpress_webhooks_table();
	$per_page = 20;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
	$page  = isset( $_GET['rpwh_paged'] ) ? max( 1, absint( wp_unslash( $_GET['rpwh_paged'] ) ) ) : 1;
	$total = 0;
	$rows  = array();
	if ( get_option( 'rpress_webhooks_db_version' ) ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		$page  = min( $page, $pages );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, ( $page - 1 ) * $per_page ) );
		// phpcs:enable
	}
	$pages    = max( 1, (int) ceil( $total / $per_page ) );
	$statuses = rpress_webhooks_status_labels();
	$types    = rpress_webhooks_get_target_types();
	$base_url = admin_url( 'admin.php?page=rpress-settings&tab=general&section=webhooks' );
	?>
	<div class="rpress-webhooks-panel" id="rpress-webhooks-log">
		<p class="description"><?php esc_html_e( 'Every delivery of the last 30 days. Failed deliveries are retried up to 5 times (after 1 minute, 5 minutes, 30 minutes and 2 hours).', 'restropress' ); ?></p>
		<div class="rpress-webhooks-notice" role="status" aria-live="polite" hidden></div>
		<?php if ( $rows ) : ?>
			<div class="rpress-webhooks-scroll">
				<table class="widefat rpress-webhooks-table rpress-webhooks-log-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Time', 'restropress' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Event', 'restropress' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Sent to', 'restropress' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'restropress' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Attempts', 'restropress' ); ?></th>
							<th scope="col"><?php esc_html_e( 'HTTP code', 'restropress' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'restropress' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$status = isset( $statuses[ $row->status ] ) ? $statuses[ $row->status ] : $row->status;
							if ( 'endpoint' === $row->target_type ) {
								$target = '' !== $row->target_label ? $row->target_label : $row->url;
							} else {
								// Extension targets: the type label and the URL.
								$target = ( isset( $types[ $row->target_type ] ) ? $types[ $row->target_type ]['label'] : $row->target_type ) . ': ' . $row->url;
							}
							$body   = (string) $row->body;
							$pretty = json_decode( $body );
							if ( null !== $pretty ) {
								$body = wp_json_encode( $pretty, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
							}
							?>
							<tr data-id="<?php echo esc_attr( $row->id ); ?>">
								<td><?php echo esc_html( rpress_webhooks_admin_time( strtotime( $row->created_at . ' UTC' ) ) ); ?></td>
								<td><code><?php echo esc_html( $row->event ); ?></code></td>
								<td class="rpress-webhooks-target"><?php echo esc_html( $target ); ?></td>
								<td><span class="rpress-webhooks-status rpress-webhooks-status--<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( $status ); ?></span></td>
								<td><?php echo esc_html( (int) $row->attempts ); ?></td>
								<td><?php echo $row->response_code ? esc_html( (int) $row->response_code ) : '&ndash;'; ?></td>
								<td class="rpress-webhooks-actions">
									<button type="button" class="button button-small rpress-webhooks-details" aria-expanded="false"><?php esc_html_e( 'Details', 'restropress' ); ?></button>
									<button type="button" class="button button-small rpress-webhooks-action" data-op="resend" data-reload="1"><?php esc_html_e( 'Resend', 'restropress' ); ?></button>
								</td>
							</tr>
							<tr class="rpress-webhooks-detail-row" hidden>
								<td colspan="7">
									<p>
										<strong><?php esc_html_e( 'URL', 'restropress' ); ?>:</strong> <code class="rpress-webhooks-url"><?php echo esc_html( $row->url ); ?></code><br>
										<strong><?php esc_html_e( 'Event id', 'restropress' ); ?>:</strong> <code><?php echo esc_html( $row->event_id ); ?></code>
										<strong><?php esc_html_e( 'Delivery', 'restropress' ); ?>:</strong> <code><?php echo esc_html( (int) $row->id ); ?></code><br>
										<strong><?php esc_html_e( 'Last response code', 'restropress' ); ?>:</strong> <?php echo $row->response_code ? esc_html( (int) $row->response_code ) : esc_html__( 'None', 'restropress' ); ?>
										<?php if ( in_array( $row->status, array( 'pending', 'retrying' ), true ) ) : ?>
											<br><strong><?php esc_html_e( 'Next attempt', 'restropress' ); ?>:</strong> <?php echo esc_html( rpress_webhooks_admin_time( strtotime( $row->next_attempt_at . ' UTC' ) ) ); ?>
										<?php endif; ?>
									</p>
									<p><strong><?php esc_html_e( 'Request body', 'restropress' ); ?></strong></p>
									<pre class="rpress-webhooks-pre"><?php echo '' !== $body ? esc_html( $body ) : esc_html__( 'Not built yet. The body is created at the first attempt.', 'restropress' ); ?></pre>
									<p><strong><?php esc_html_e( 'Response (first 1000 characters)', 'restropress' ); ?></strong></p>
									<pre class="rpress-webhooks-pre"><?php echo '' !== (string) $row->response_body ? esc_html( rpress_webhooks_trim_response( $row->response_body ) ) : esc_html__( 'Empty', 'restropress' ); ?></pre>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="rpress-webhooks-pagination">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: current page, 2: number of pages, 3: number of deliveries */
						__( 'Page %1$d of %2$d (%3$d deliveries)', 'restropress' ),
						$page,
						$pages,
						$total
					)
				);
				?>
				<?php if ( $page > 1 ) : ?>
					<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'rpwh_paged', $page - 1, $base_url ) . '#rpress-webhooks-log' ); ?>"><?php esc_html_e( 'Newer', 'restropress' ); ?></a>
				<?php endif; ?>
				<?php if ( $page < $pages ) : ?>
					<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'rpwh_paged', $page + 1, $base_url ) . '#rpress-webhooks-log' ); ?>"><?php esc_html_e( 'Older', 'restropress' ); ?></a>
				<?php endif; ?>
			</p>
		<?php else : ?>
			<p class="rpress-webhooks-empty"><?php esc_html_e( 'Nothing has been sent yet.', 'restropress' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'wp_ajax_rpress_webhooks_admin', 'rpress_webhooks_ajax_admin' );
/**
 * Every admin change: nonce and capability first, then one operation.
 *
 * @return void
 */
function rpress_webhooks_ajax_admin() {
	check_ajax_referer( 'rpress_webhooks_admin', 'security' );
	if ( ! current_user_can( 'manage_shop_settings' ) ) {
		wp_send_json_error( array( 'message' => __( 'You are not allowed to manage webhooks.', 'restropress' ) ), 403 );
	}

	$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
	$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';

	switch ( $op ) {
		case 'save_endpoint':
			$events = isset( $_POST['events'] ) && is_array( $_POST['events'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['events'] ) ) : array();
			$result = rpress_webhooks_save_endpoint(
				array(
					'label'  => isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '',
					// Not sanitize_text_field(): it strips percent-encoded octets (%2F) and would change the URL.
					// rpress_webhooks_clean_url() validates it (scheme, host, no spaces or quotes, esc_url_raw must leave it unchanged).
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					'url'    => isset( $_POST['url'] ) && is_string( $_POST['url'] ) ? trim( wp_unslash( $_POST['url'] ) ) : '',
					'events' => $events,
				),
				$id
			);
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
			}
			wp_send_json_success( array( 'message' => __( 'Endpoint saved.', 'restropress' ) ) );
			break;

		case 'toggle_endpoint':
			$endpoints = rpress_webhooks_get_endpoints();
			if ( ! isset( $endpoints[ $id ] ) ) {
				wp_send_json_error( array( 'message' => __( 'That endpoint no longer exists.', 'restropress' ) ), 404 );
			}
			$endpoints[ $id ]['active'] = empty( $endpoints[ $id ]['active'] );
			rpress_webhooks_save_list( 'endpoints', $endpoints );
			wp_send_json_success( array( 'message' => $endpoints[ $id ]['active'] ? __( 'Endpoint resumed.', 'restropress' ) : __( 'Endpoint paused.', 'restropress' ) ) );
			break;

		case 'delete_endpoint':
			if ( ! rpress_webhooks_delete_endpoint( $id ) ) {
				wp_send_json_error( array( 'message' => __( 'That endpoint no longer exists.', 'restropress' ) ), 404 );
			}
			wp_send_json_success( array( 'message' => __( 'Endpoint deleted.', 'restropress' ) ) );
			break;

		case 'reveal_secret':
			$endpoints = rpress_webhooks_get_endpoints();
			if ( ! isset( $endpoints[ $id ] ) ) {
				wp_send_json_error( array( 'message' => __( 'That endpoint no longer exists.', 'restropress' ) ), 404 );
			}
			nocache_headers();
			wp_send_json_success( array( 'secret' => (string) $endpoints[ $id ]['secret'] ) );
			break;

		case 'send_test':
			$result = rpress_webhooks_send_test( $id );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 404 );
			}
			// HTTP 200 either way: the request to WordPress worked, the body says how the test went.
			wp_send_json(
				array(
					'success' => (bool) $result['success'],
					'data'    => array(
						'message' => $result['message'],
						'code'    => (int) $result['code'],
					),
				)
			);
			break;

		case 'resend':
			$result = rpress_webhooks_resend( absint( $id ) );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
			}
			wp_send_json_success( array( 'message' => __( 'Queued. It is sent within a minute or so.', 'restropress' ) ) );
			break;
	}

	wp_send_json_error( array( 'message' => __( 'Unknown action.', 'restropress' ) ), 400 );
}
