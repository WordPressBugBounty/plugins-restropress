<?php
/**
 * Setup wizard (3.5): store, services, hours, delivery, payments, menu and a
 * test order, from a fresh install to a first order in about 10 minutes.
 *
 * The screen is the existing onboarding page (admin.php?page=rpress-setup,
 * RPress_Onboarding view + assets/js/admin-home.js), upgraded to these steps.
 * This module adds the step handlers, progress, launch checklist, the
 * activation notice for existing stores and the wizard stylesheet.
 *
 * @package     RPRESS
 * @subpackage  Features/SetupWizard
 * @since       3.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/functions.php';

add_action( 'wp_ajax_rpress_setup_wizard_save', 'rpress_setup_wizard_ajax_save' );
add_action( 'wp_ajax_rpress_setup_wizard_checklist', 'rpress_setup_wizard_ajax_checklist' );
add_action( 'admin_enqueue_scripts', 'rpress_setup_wizard_enqueue', 20 );
add_action( 'admin_init', 'rpress_setup_wizard_handle_dismiss' );
add_action( 'admin_notices', 'rpress_setup_wizard_notice' );
add_action( 'admin_init', 'rpress_setup_wizard_page_title' );

/**
 * Check capability and nonce for wizard AJAX calls.
 *
 * @return void
 */
function rpress_setup_wizard_verify_ajax() {
	if ( ! current_user_can( 'manage_shop_settings' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'restropress' ) ), 403 );
	}
	check_ajax_referer( 'rpress_onboarding', 'nonce' );
}

/**
 * AJAX: save or skip one step.
 *
 * @return void
 */
function rpress_setup_wizard_ajax_save() {
	rpress_setup_wizard_verify_ajax();

	$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
	$skip = ! empty( $_POST['skip'] );
	// Each handler sanitizes and validates its own fields.
	$data = isset( $_POST['data'] ) && is_array( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	$result = $skip ? rpress_setup_wizard_skip_step( $step ) : rpress_setup_wizard_save_step( $step, $data );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
	}

	$progress = rpress_setup_wizard_progress();
	wp_send_json_success(
		array(
			'completed' => $progress['completed'],
			'skipped'   => $progress['skipped'],
			'launched'  => rpress_setup_wizard_is_complete(),
			'checklist' => rpress_setup_wizard_checklist(),
		)
	);
}

/**
 * AJAX: current launch checklist.
 *
 * @return void
 */
function rpress_setup_wizard_ajax_checklist() {
	rpress_setup_wizard_verify_ajax();
	wp_send_json_success( array( 'checklist' => rpress_setup_wizard_checklist() ) );
}

/**
 * Wizard stylesheet and strings on the setup and menu import screens.
 *
 * @return void
 */
function rpress_setup_wizard_enqueue() {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! in_array( $page, array( 'rpress-setup', 'rpress-menu-import' ), true ) ) {
		return;
	}
	$file = RP_PLUGIN_DIR . 'assets/features/setup-wizard/setup-wizard.css';
	wp_enqueue_style( 'rpress-setup-wizard', RP_PLUGIN_URL . 'assets/features/setup-wizard/setup-wizard.css', array(), file_exists( $file ) ? filemtime( $file ) : RP_VERSION );

	if ( wp_script_is( 'rpress-home', 'registered' ) ) {
		wp_localize_script(
			'rpress-home',
			'rpressSetupWizard',
			array(
				'nextText'     => __( 'Save and continue', 'restropress' ),
				'finishText'   => __( 'Finish setup', 'restropress' ),
				'skippedLabel' => __( 'Skipped', 'restropress' ),
				/* translators: 1: current step number, 2: number of steps */
				'stepOf'       => __( 'Step %1$s of %2$s', 'restropress' ),
				/* translators: 1: steps done, 2: number of steps */
				'progress'     => __( '%1$s of %2$s done', 'restropress' ),
				'readyText'    => __( 'You are ready', 'restropress' ),
				'fixText'      => __( 'Fix', 'restropress' ),
				'openText'     => __( 'Open', 'restropress' ),
				'doneSr'       => __( 'Done', 'restropress' ),
				'missingSr'    => __( 'Still missing', 'restropress' ),
				'savedText'    => __( 'Saved.', 'restropress' ),
				'skipText'     => __( 'Step skipped. You can come back to it any time.', 'restropress' ),
				/* translators: %s: timezone name, e.g. Europe/London */
				'tzBrowser'    => __( 'Your computer is set to %s. Is that where your restaurant is?', 'restropress' ),
				'tzUse'        => __( 'Use this timezone', 'restropress' ),
				'copiedText'   => __( 'Copied', 'restropress' ),
			)
		);
	}
}

/**
 * Persist the notice dismissal (per user).
 *
 * @return void
 */
function rpress_setup_wizard_handle_dismiss() {
	if ( empty( $_GET['rpress_setup_wizard_dismiss'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	if ( ! current_user_can( 'manage_shop_settings' ) ) {
		return;
	}
	check_admin_referer( 'rpress_setup_wizard_dismiss' );
	update_user_meta( get_current_user_id(), 'rpress_setup_wizard_notice_dismissed', '1' );
	wp_safe_redirect( remove_query_arg( array( 'rpress_setup_wizard_dismiss', '_wpnonce' ) ) );
	exit;
}

/**
 * Notice for stores that have not run the wizard (no auto-redirect for them).
 *
 * @return void
 */
function rpress_setup_wizard_notice() {
	if ( ! current_user_can( 'manage_shop_settings' ) || rpress_setup_wizard_is_complete() || get_option( 'rpress_show_setup_wizard' ) ) {
		return;
	}
	if ( get_user_meta( get_current_user_id(), 'rpress_setup_wizard_notice_dismissed', true ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$id     = $screen ? (string) $screen->id : '';
	if ( 'dashboard' !== $id && false === strpos( $id, 'rpress' ) && false === strpos( $id, 'restropress' ) && false === strpos( $id, 'fooditem' ) ) {
		return;
	}
	if ( false !== strpos( $id, 'rpress-setup' ) ) {
		return;
	}
	$dismiss = wp_nonce_url( add_query_arg( 'rpress_setup_wizard_dismiss', '1' ), 'rpress_setup_wizard_dismiss' );
	?>
	<div class="notice notice-info rpress-setup-wizard-notice">
		<p>
			<strong><?php esc_html_e( 'RestroPress setup wizard', 'restropress' ); ?></strong>
			<?php esc_html_e( 'Check your store details, hours, delivery, payments and menu in a few guided steps, then place a test order.', 'restropress' ); ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-setup' ) ); ?>"><?php esc_html_e( 'Run the setup wizard', 'restropress' ); ?></a>
			<a class="button-link" href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Dismiss', 'restropress' ); ?></a>
		</p>
	</div>
	<?php
}

/**
 * The wizard page is registered without a parent menu, so WordPress finds no
 * page title: the browser tab is empty and admin-header.php passes null to
 * strip_tags() (a PHP 8.1 deprecation). get_admin_page_title() keeps a title
 * that is already set.
 */
function rpress_setup_wizard_page_title() {
	global $title;
	if ( empty( $title ) && isset( $_GET['page'] ) && 'rpress-setup' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$title = __( 'RestroPress setup', 'restropress' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}
}
