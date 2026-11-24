<?php
/**
 * Webhooks: deliveries table (queue and log in one).
 *
 * A custom table because the worker needs indexed status and next attempt
 * time, and the log can reach thousands of rows. Created by dbDelta with its
 * own DB version option, like core's other tables, and checked on admin_init
 * and before any queue write, so no edit to includes/install.php is needed.
 *
 * @package RestroPress
 * @since   3.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deliveries table name.
 *
 * @return string
 */
function rpress_webhooks_table() {
	global $wpdb;
	return $wpdb->prefix . 'rpress_webhook_deliveries';
}

/**
 * Create or upgrade the deliveries table.
 *
 * @return void
 */
function rpress_webhooks_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = rpress_webhooks_table();
	$charset = $wpdb->get_charset_collate();
	$sql     = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		event_id varchar(40) NOT NULL DEFAULT '',
		event varchar(40) NOT NULL DEFAULT '',
		object_id bigint(20) unsigned NOT NULL DEFAULT 0,
		target_type varchar(20) NOT NULL DEFAULT 'endpoint',
		target_id varchar(32) NOT NULL DEFAULT '',
		target_label varchar(190) NOT NULL DEFAULT '',
		url text NOT NULL,
		extra text NULL,
		body longtext NULL,
		status varchar(12) NOT NULL DEFAULT 'pending',
		attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
		response_code smallint(5) unsigned NOT NULL DEFAULT 0,
		response_body text NULL,
		event_time datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		next_attempt_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		KEY status_next (status,next_attempt_at),
		KEY object_id (object_id),
		KEY event_id (event_id),
		KEY created_at (created_at)
	) {$charset};";
	dbDelta( $sql );

	update_option( 'rpress_webhooks_db_version', RPRESS_WEBHOOKS_DB_VERSION );
}

/**
 * Create or upgrade the table when its DB version option differs. Runs on
 * admin_init and before any queue write or worker run, so updating
 * RestroPress (which does not run the activation hook) is enough.
 *
 * @return bool Whether the table was (re)installed in this call.
 */
function rpress_webhooks_maybe_install() {
	static $checked = false;
	if ( $checked ) {
		return false;
	}
	$checked = true;
	if ( (string) get_option( 'rpress_webhooks_db_version' ) === RPRESS_WEBHOOKS_DB_VERSION ) {
		return false;
	}
	rpress_webhooks_install();
	return true;
}

/**
 * Drop the deliveries table with the site on multisite.
 *
 * @param array $tables  Tables to drop.
 * @param int   $blog_id Site ID.
 * @return array
 */
function rpress_webhooks_wpmu_drop_tables( $tables, $blog_id ) {
	global $wpdb;
	switch_to_blog( $blog_id );
	$tables[] = $wpdb->prefix . 'rpress_webhook_deliveries';
	restore_current_blog();
	return $tables;
}
