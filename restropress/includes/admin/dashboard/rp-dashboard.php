<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-rpress-command-center-dashboard.php';

$dashboard = RPRESS_Command_Center_Dashboard::build_data();
?>

<div class="wrap rp-admin-scope rp-command-center">
	<div class="rp-command-topline">
		<div class="rp-command-title">
			<p class="rp-command-kicker"><?php echo esc_html( date_i18n( 'l, F j', current_time( 'timestamp' ) ) ); ?></p>
			<h1><?php esc_html_e( 'Command Center', 'restropress' ); ?></h1>
			<p><?php esc_html_e( 'Orders for service today: what needs attention now, where the kitchen is loaded, and what to fix before customers feel it.', 'restropress' ); ?></p>
		</div>
		<div class="rp-command-updated">
			<span><?php esc_html_e( 'Last updated', 'restropress' ); ?></span>
			<strong id="rp-command-updated-time"><?php echo esc_html( $dashboard['updated_label'] ); ?></strong>
			<a class="button" id="rp-command-refresh" href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-dashboard' ) ); ?>"><?php esc_html_e( 'Refresh', 'restropress' ); ?></a>
			<small class="rp-command-autorefresh-note"><?php esc_html_e( 'Auto-refreshes while this page is open.', 'restropress' ); ?></small>
		</div>
	</div>

	<div id="rp-command-center-panels">
		<?php RPRESS_Command_Center_Dashboard::render_panels( $dashboard ); ?>
	</div>
</div>
