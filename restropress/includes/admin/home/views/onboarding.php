<?php
/**
 * RestroPress setup wizard (3.5): rail + seven skippable steps + live preview.
 *
 * Steps: store basics, services, hours, delivery, payments, menu, test order.
 * Saving goes through rpress_setup_wizard_save_step() (includes/features/setup-wizard),
 * progress lives in the rpress_onboarding option so the wizard resumes where it was left.
 *
 * @package RPRESS\Admin\Home
 *
 * @var array        $state
 * @var array        $ai_settings
 * @var array        $ai_status
 * @var WP_Post|null $latest
 * @var array        $countries
 * @var array        $currencies
 */

defined( 'ABSPATH' ) || exit;

$state        = is_array( $state ) ? $state : array();
$ai_status    = is_array( $ai_status ) ? $ai_status : array();
$countries    = is_array( $countries ) ? $countries : array();
$currencies   = is_array( $currencies ) ? $currencies : array();
$has_wizard   = function_exists( 'rpress_setup_wizard_progress' );
$progress     = $has_wizard ? rpress_setup_wizard_progress() : array( 'completed' => array(), 'skipped' => array() );
$resume_step  = $has_wizard ? rpress_setup_wizard_resume_step() : 'store';
$is_launched  = isset( $state['status'] ) && 'launched' === $state['status'];

$store_name   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
$cur_code     = rpress_get_option( 'currency', 'USD' );
$cur_symbol   = function_exists( 'rpress_currency_symbol' ) ? html_entity_decode( rpress_currency_symbol( $cur_code ) ) : '$';
$store_phone  = rpress_get_option( 'store_phone', '' );
$store_addr   = rpress_get_option( 'store_address', '' );
$store_city   = rpress_get_option( 'store_city', '' );
$store_zip    = rpress_get_option( 'store_postcode', '' );
$base_country = rpress_get_option( 'base_country', '' );
$base_state   = rpress_get_option( 'base_state', '' );
$prep_time    = rpress_get_option( 'prep_time', 20 );
$alert_email  = rpress_get_option( 'admin_notice_emails', get_option( 'admin_email' ) );
$enable_vegmark = rpress_get_option( 'enable_food_type', false );
$cur_service  = rpress_get_option( 'enable_service', 'delivery_and_pickup' );
$cur_service  = in_array( $cur_service, array( 'delivery_and_pickup', 'delivery', 'pickup' ), true ) ? $cur_service : 'delivery_and_pickup';
$def_service  = rpress_get_option( 'default_service', 'delivery' );
$asap_on      = (string) rpress_get_option( 'enable_asap_option', '' );
$notify_on    = rpress_get_option( 'enable_order_notification', '' );
$min_delivery = rpress_get_option( 'allow_minimum_order' ) ? rpress_get_option( 'minimum_order_price', '' ) : '';
$min_pickup   = rpress_get_option( 'allow_minimum_order' ) ? rpress_get_option( 'minimum_order_price_pickup', '' ) : '';
$flat_fee     = rpress_get_option( 'flat_delivery_fee', '' );
$core_fee     = $has_wizard ? rpress_setup_wizard_core_delivery_fee() : true;
$core_hours   = $has_wizard ? rpress_setup_wizard_core_hours() : true;
$week         = $has_wizard ? rpress_setup_wizard_get_weekly_hours() : array();
$day_names    = $has_wizard ? rpress_setup_wizard_day_names() : array();
// Storefront look (Settings > Styles), optional inside store basics.
$cur_pack     = rpress_get_option( 'template_pack', 'classic' );
$cur_pack     = in_array( $cur_pack, array( 'classic', 'modern' ), true ) ? $cur_pack : 'classic';
$theme_color  = rpress_get_option( 'primary_color', '#ED5575' );
$theme_color  = sanitize_hex_color( $theme_color ) ? $theme_color : '#ED5575';
$menu_layout  = rpress_get_option( 'template', 'list' );
$menu_layout  = in_array( $menu_layout, array( 'list', 'grid' ), true ) ? $menu_layout : 'list';
$service_labels = array(
	'delivery_and_pickup' => __( 'Pickup and delivery', 'restropress' ),
	'pickup'              => __( 'Pickup only', 'restropress' ),
	'delivery'            => __( 'Delivery only', 'restropress' ),
);
$tz_string    = get_option( 'timezone_string' );
$tz_choice    = $has_wizard ? rpress_setup_wizard_timezone_choice() : $tz_string;
$tz_named     = $has_wizard ? rpress_setup_wizard_has_named_timezone() : ! empty( $tz_string );
$time_format  = get_option( 'time_format', 'g:i A' );
$is_24h       = ( false === strpos( $time_format, 'A' ) && false === strpos( $time_format, 'a' ) );
$ai_ready     = ! empty( $ai_status['ready'] );
$today_n      = (int) wp_date( 'N' );
$hours_label  = '';
if ( isset( $week[ $today_n ] ) ) {
	$hours_label = ! empty( $week[ $today_n ]['closed'] ) ? __( 'Closed today', 'restropress' ) : $week[ $today_n ]['open'] . ' - ' . $week[ $today_n ]['close'];
}
$store_url    = $has_wizard ? rpress_setup_wizard_storefront_url() : home_url( '/' );
$live_url     = $has_wizard ? rpress_setup_wizard_live_orders_url() : admin_url( 'admin.php?page=rpress-payment-history' );

// Payments: enabled gateways and test mode (full configuration stays on Settings > Payments).
$enabled_gateways = array_filter( (array) rpress_get_option( 'gateways', array() ) );
$all_gateways     = function_exists( 'rpress_get_payment_gateways' ) ? (array) rpress_get_payment_gateways() : array();
$is_test_mode     = function_exists( 'rpress_is_test_mode' ) ? rpress_is_test_mode() : (bool) rpress_get_option( 'test_mode', false );
// Cash on delivery is on by default for a store with no payment method yet.
$cash_on          = ! empty( $enabled_gateways['cash_on_delivery'] ) || empty( $enabled_gateways );

$pp_email        = rpress_get_option( 'paypal_email', '' );
$pp_configured   = ( $pp_email || rpress_get_option( 'paypal_rest_client_id', '' ) );
$pp_enabled      = ! empty( $enabled_gateways['paypal'] );
$pp_settings_url = function_exists( 'rpress_get_paypal_settings_page_url' ) ? rpress_get_paypal_settings_page_url() : admin_url( 'admin.php?page=rpress-settings&tab=gateways&section=paypal' );

$stripe_available    = isset( $all_gateways['stripe'] );
$stripe_enabled      = ! empty( $enabled_gateways['stripe'] );
$stripe_prefix       = $is_test_mode ? 'test_' : 'live_';
$stripe_secret_saved = '' !== (string) rpress_get_option( $stripe_prefix . 'secret_key', '' );
$stripe_pub          = rpress_get_option( $stripe_prefix . 'publishable_key', '' );
$stripe_settings_url = admin_url( 'admin.php?page=rpress-settings&tab=gateways&section=stripe' );

$steps = array(
	'store'    => array( 'label' => __( 'Store basics', 'restropress' ), 'sub' => __( 'Name, address, currency', 'restropress' ) ),
	'services' => array( 'label' => __( 'Services', 'restropress' ), 'sub' => __( 'Delivery, pickup, alerts', 'restropress' ) ),
	'hours'    => array( 'label' => __( 'Hours', 'restropress' ), 'sub' => __( 'When you take orders', 'restropress' ) ),
	'delivery' => array( 'label' => __( 'Delivery', 'restropress' ), 'sub' => __( 'Fee and minimum order', 'restropress' ) ),
	'payments' => array( 'label' => __( 'Payments', 'restropress' ), 'sub' => __( 'Cash, Stripe, PayPal', 'restropress' ) ),
	'menu'     => array( 'label' => __( 'Menu', 'restropress' ), 'sub' => __( 'Import or build', 'restropress' ) ),
	'test'     => array( 'label' => __( 'Test order', 'restropress' ), 'sub' => __( 'Check and go live', 'restropress' ) ),
);
?>
<div class="rp-onboard rp-admin-scope rp-sw<?php echo $is_launched ? ' is-launched' : ''; ?>"
	data-cur="<?php echo esc_attr( $cur_symbol ); ?>"
	data-completed="<?php echo esc_attr( implode( ',', $progress['completed'] ) ); ?>"
	data-skipped="<?php echo esc_attr( implode( ',', $progress['skipped'] ) ); ?>"
	data-resume="<?php echo esc_attr( $resume_step ); ?>"
	data-vegmark="<?php echo $enable_vegmark ? '1' : '0'; ?>"
	data-ai-ready="<?php echo $ai_ready ? '1' : '0'; ?>"
	data-store-name="<?php echo esc_attr( $store_name ); ?>">

	<!-- RAIL -->
	<nav class="rp-ob-rail" aria-label="<?php esc_attr_e( 'Setup steps', 'restropress' ); ?>">
		<div class="rp-ob-brand"><img class="rp-ob-logo-img" src="<?php echo esc_url( RP_PLUGIN_URL . 'assets/images/restropress-logo.png' ); ?>" alt="RestroPress"></div>
		<h4><?php esc_html_e( 'Get set up', 'restropress' ); ?></h4>
		<div class="rp-ob-progress"><div class="rp-ob-pbar"><i id="rp-ob-pbar"></i></div><div class="rp-ob-pmeta" id="rp-ob-pmeta"></div></div>
		<ol class="rp-ob-steps" id="rp-ob-steps">
			<?php $n = 1; foreach ( $steps as $key => $s ) : ?>
				<li class="rp-ob-step" data-step="<?php echo esc_attr( $key ); ?>" role="button" tabindex="0">
					<span class="rp-ob-dot" aria-hidden="true"><?php echo (int) $n; ?></span>
					<span class="rp-ob-lbl"><b><?php echo esc_html( $s['label'] ); ?></b><small><?php echo esc_html( $s['sub'] ); ?></small></span>
				</li>
			<?php $n++; endforeach; ?>
		</ol>
		<div class="rp-ob-railfoot"><?php esc_html_e( 'Go in order, skip, or jump to any step. Your progress is saved.', 'restropress' ); ?></div>
	</nav>

	<!-- MAIN -->
	<div class="rp-ob-main">
		<div class="rp-ob-top">
			<span class="rp-ob-stepof" id="rp-ob-stepof" aria-live="polite"></span>
			<a class="rp-ob-skip" href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-dashboard' ) ); ?>"><?php esc_html_e( 'Save and finish later', 'restropress' ); ?></a>
		</div>
		<div class="rp-sw-mbar" aria-hidden="true"><i id="rp-sw-mbar"></i></div>
		<div class="rp-ob-scroll"><div class="rp-ob-wrap">

			<!-- 1 STORE BASICS -->
			<section class="rp-ob-pane" data-pane="store" hidden>
				<h2 class="rp-ob-title"><?php esc_html_e( 'Store basics', 'restropress' ); ?></h2>
				<p class="rp-ob-sub"><?php esc_html_e( 'Your name, address and currency appear on the storefront, receipts and emails.', 'restropress' ); ?></p>

				<div class="rp-ob-two">
					<div class="rp-ob-field"><label for="rp-ob-name"><?php esc_html_e( 'Restaurant name', 'restropress' ); ?></label><input type="text" name="restaurant_name" id="rp-ob-name" value="<?php echo esc_attr( $store_name ); ?>" required autocomplete="organization"></div>
					<div class="rp-ob-field"><label for="rp-sw-phone"><?php esc_html_e( 'Phone', 'restropress' ); ?></label><input type="tel" name="store_phone" id="rp-sw-phone" value="<?php echo esc_attr( $store_phone ); ?>" placeholder="+1 555 0100" autocomplete="tel"></div>
				</div>
				<div class="rp-ob-field"><label for="rp-sw-address"><?php esc_html_e( 'Street address', 'restropress' ); ?></label><input type="text" name="store_address" id="rp-sw-address" value="<?php echo esc_attr( $store_addr ); ?>" autocomplete="street-address"></div>
				<div class="rp-ob-two">
					<div class="rp-ob-field"><label for="rp-sw-city"><?php esc_html_e( 'City', 'restropress' ); ?></label><input type="text" name="store_city" id="rp-sw-city" value="<?php echo esc_attr( $store_city ); ?>" autocomplete="address-level2"></div>
					<div class="rp-ob-field"><label for="rp-sw-zip"><?php esc_html_e( 'Postcode / ZIP', 'restropress' ); ?></label><input type="text" name="store_postcode" id="rp-sw-zip" value="<?php echo esc_attr( $store_zip ); ?>" autocomplete="postal-code"></div>
				</div>
				<div class="rp-ob-two">
					<div class="rp-ob-field"><label for="rp-ob-country"><?php esc_html_e( 'Country', 'restropress' ); ?></label>
						<select name="base_country" id="rp-ob-country" class="rp-ob-select">
							<option value=""><?php esc_html_e( 'Select country', 'restropress' ); ?></option>
							<?php foreach ( $countries as $code => $cname ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $base_country, $code ); ?>><?php echo esc_html( $cname ); ?></option>
							<?php endforeach; ?>
						</select></div>
					<div class="rp-ob-field"><label for="rp-ob-state"><?php esc_html_e( 'State / Province', 'restropress' ); ?></label>
						<select name="base_state" id="rp-ob-state" class="rp-ob-select" data-selected="<?php echo esc_attr( $base_state ); ?>"><option value=""><?php esc_html_e( 'Select state / province', 'restropress' ); ?></option></select></div>
				</div>
				<div class="rp-ob-two">
					<div class="rp-ob-field"><label for="rp-ob-currency"><?php esc_html_e( 'Currency', 'restropress' ); ?></label>
						<select name="currency" id="rp-ob-currency" class="rp-ob-select">
							<?php foreach ( $currencies as $ccode => $clabel ) : ?>
								<option value="<?php echo esc_attr( $ccode ); ?>" <?php selected( $cur_code, $ccode ); ?>><?php echo esc_html( $clabel ); ?></option>
							<?php endforeach; ?>
						</select></div>
					<div class="rp-ob-field"><span class="rp-sw-label" id="rp-sw-tf-label"><?php esc_html_e( 'Time format', 'restropress' ); ?></span>
						<div class="rp-ob-seg" id="rp-ob-tf" role="group" aria-labelledby="rp-sw-tf-label">
							<button type="button" data-tf="12" class="<?php echo $is_24h ? '' : 'on'; ?>" aria-pressed="<?php echo $is_24h ? 'false' : 'true'; ?>"><?php esc_html_e( '1:30 PM', 'restropress' ); ?></button>
							<button type="button" data-tf="24" class="<?php echo $is_24h ? 'on' : ''; ?>" aria-pressed="<?php echo $is_24h ? 'true' : 'false'; ?>"><?php esc_html_e( '13:30', 'restropress' ); ?></button>
						</div>
						<input type="hidden" name="time_format" id="rp-ob-tf-val" value="<?php echo $is_24h ? '24' : '12'; ?>"></div>
				</div>

				<div class="rp-ob-field">
					<label for="rp-ob-tz"><?php esc_html_e( 'Timezone', 'restropress' ); ?></label>
					<select name="timezone_string" id="rp-ob-tz" class="rp-ob-select" data-wp-tz="<?php echo esc_attr( $tz_choice ); ?>"><?php echo wp_timezone_choice( $tz_choice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper returns escaped option markup ?></select>
					<p class="rp-ob-hint rp-help-text"><?php esc_html_e( 'This is your WordPress timezone. Opening hours, order times and reports use it.', 'restropress' ); ?> <span class="rp-ob-clock"><?php esc_html_e( 'Time at your restaurant now:', 'restropress' ); ?> <b id="rp-ob-clocknow">-</b></span></p>
					<?php if ( ! $tz_named ) : ?>
						<div class="rp-notice is-warning rp-sw-tzwarn" role="status">
							<?php esc_html_e( 'Your site uses a fixed UTC offset, which does not follow daylight saving time. Choose your city above, or fix it in WordPress settings.', 'restropress' ); ?>
							<a href="<?php echo esc_url( admin_url( 'options-general.php#timezone_string' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open General Settings', 'restropress' ); ?></a>
						</div>
					<?php endif; ?>
					<div class="rp-notice rp-sw-tzbrowser" id="rp-sw-tzbrowser" role="status" hidden><span></span> <button type="button" class="rp-btn rp-btn-secondary rp-ob-btn-sm" id="rp-sw-tzuse"></button></div>
				</div>

				<details class="rp-sw-more">
					<summary><?php esc_html_e( 'Storefront look (optional)', 'restropress' ); ?></summary>
					<div class="rp-ob-packs" role="radiogroup" aria-label="<?php esc_attr_e( 'Template', 'restropress' ); ?>">
						<label class="rp-ob-pack">
							<input type="radio" name="template_pack" value="classic" <?php checked( $cur_pack, 'classic' ); ?>>
							<span class="rp-ob-pack-in"><span class="rp-ob-pack-body"><b><?php esc_html_e( 'Classic', 'restropress' ); ?></b><small><?php esc_html_e( 'Clean and neutral, inherits your theme.', 'restropress' ); ?></small></span></span>
						</label>
						<label class="rp-ob-pack">
							<input type="radio" name="template_pack" value="modern" <?php checked( $cur_pack, 'modern' ); ?>>
							<span class="rp-ob-pack-in"><span class="rp-ob-pack-body"><b><?php esc_html_e( 'Modern', 'restropress' ); ?></b><small><?php esc_html_e( 'Image-forward, delivery-app style.', 'restropress' ); ?></small></span></span>
						</label>
					</div>
					<div class="rp-ob-two">
						<div class="rp-ob-field"><label for="rp-ob-theme-color"><?php esc_html_e( 'Brand colour', 'restropress' ); ?></label>
							<span class="rp-ob-colorwrap"><input type="color" name="primary_color" id="rp-ob-theme-color" value="<?php echo esc_attr( $theme_color ); ?>"><code id="rp-ob-theme-hex"><?php echo esc_html( strtoupper( $theme_color ) ); ?></code></span></div>
						<div class="rp-ob-field"><span class="rp-sw-label" id="rp-sw-layout-label"><?php esc_html_e( 'Menu layout', 'restropress' ); ?></span>
							<div class="rp-ob-seg" id="rp-ob-layout" role="group" aria-labelledby="rp-sw-layout-label"><button type="button" data-layout="list" class="<?php echo 'list' === $menu_layout ? 'on' : ''; ?>" aria-pressed="<?php echo 'list' === $menu_layout ? 'true' : 'false'; ?>"><?php esc_html_e( 'List', 'restropress' ); ?></button><button type="button" data-layout="grid" class="<?php echo 'grid' === $menu_layout ? 'on' : ''; ?>" aria-pressed="<?php echo 'grid' === $menu_layout ? 'true' : 'false'; ?>"><?php esc_html_e( 'Grid', 'restropress' ); ?></button></div>
							<input type="hidden" name="template" id="rp-ob-layout-val" value="<?php echo esc_attr( $menu_layout ); ?>"></div>
					</div>
				</details>
			</section>

			<!-- 2 SERVICES -->
			<section class="rp-ob-pane" data-pane="services" hidden>
				<h2 class="rp-ob-title"><?php esc_html_e( 'How do customers get their food?', 'restropress' ); ?></h2>
				<p class="rp-ob-sub"><?php esc_html_e( 'Pick delivery, pickup, or both. You can change this any time.', 'restropress' ); ?></p>
				<div class="rp-ob-field"><span class="rp-sw-label" id="rp-sw-svc-label"><?php esc_html_e( 'Services offered', 'restropress' ); ?></span>
					<div class="rp-ob-seg" id="rp-ob-svc" role="group" aria-labelledby="rp-sw-svc-label">
						<?php foreach ( array( 'delivery_and_pickup', 'delivery', 'pickup' ) as $svc ) : ?>
							<button type="button" data-svc="<?php echo esc_attr( $svc ); ?>" class="<?php echo $svc === $cur_service ? 'on' : ''; ?>" aria-pressed="<?php echo $svc === $cur_service ? 'true' : 'false'; ?>"><?php echo esc_html( $service_labels[ $svc ] ); ?></button>
						<?php endforeach; ?>
					</div>
					<input type="hidden" name="enable_service" id="rp-ob-svc-val" value="<?php echo esc_attr( $cur_service ); ?>"></div>
				<div class="rp-ob-two">
					<div class="rp-ob-field rp-sw-both-only"><label for="rp-sw-default-svc"><?php esc_html_e( 'Selected by default', 'restropress' ); ?></label>
						<select name="default_service" id="rp-sw-default-svc" class="rp-ob-select"><option value="delivery" <?php selected( $def_service, 'delivery' ); ?>><?php esc_html_e( 'Delivery', 'restropress' ); ?></option><option value="pickup" <?php selected( $def_service, 'pickup' ); ?>><?php esc_html_e( 'Pickup', 'restropress' ); ?></option></select></div>
					<div class="rp-ob-field"><label for="rp-sw-prep"><?php esc_html_e( 'Prep time (minutes)', 'restropress' ); ?></label><input type="number" min="0" max="1440" name="prep_time" id="rp-sw-prep" value="<?php echo esc_attr( $prep_time ); ?>" aria-describedby="rp-sw-prep-hint"><p class="rp-ob-hint rp-help-text" id="rp-sw-prep-hint"><?php esc_html_e( 'Sets the earliest time customers can choose.', 'restropress' ); ?></p></div>
				</div>
				<label class="rp-ob-check"><input type="checkbox" name="enable_asap_option" value="1" <?php checked( '1', $asap_on ); ?>> <?php esc_html_e( 'Let customers order "as soon as possible"', 'restropress' ); ?></label>

				<div class="rp-ob-group"><?php esc_html_e( 'New order alerts', 'restropress' ); ?></div>
				<div class="rp-ob-field"><label for="rp-sw-alert-email"><?php esc_html_e( 'Email new orders to', 'restropress' ); ?></label><input type="text" name="admin_notice_emails" id="rp-sw-alert-email" value="<?php echo esc_attr( $alert_email ); ?>" autocomplete="email" aria-describedby="rp-sw-alert-hint"><p class="rp-ob-hint rp-help-text" id="rp-sw-alert-hint"><?php esc_html_e( 'Separate several addresses with commas.', 'restropress' ); ?></p></div>
				<label class="rp-ob-check"><input type="checkbox" name="enable_order_notification" value="1" <?php checked( ! empty( $notify_on ) ); ?>> <?php esc_html_e( 'Play a sound and show a desktop notification while Live Orders is open', 'restropress' ); ?></label>
				<label class="rp-ob-check"><input type="checkbox" name="enable_food_type" id="rp-ob-vegmark" value="1" <?php checked( $enable_vegmark ); ?>> <?php esc_html_e( 'Show Veg / Non-veg marks on menu items', 'restropress' ); ?></label>
			</section>

			<!-- 3 HOURS -->
			<section class="rp-ob-pane" data-pane="hours" hidden>
				<h2 class="rp-ob-title"><?php esc_html_e( 'When do you take orders?', 'restropress' ); ?></h2>
				<p class="rp-ob-sub"><?php esc_html_e( 'Set opening and closing times for each day. Times after midnight are fine (for example 6:00 PM to 2:00 AM).', 'restropress' ); ?></p>
				<?php if ( ! $core_hours ) : ?>
					<div class="rp-notice is-info"><?php esc_html_e( 'The Store Timing extension manages your hours, with separate delivery and pickup schedules.', 'restropress' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-settings&tab=general' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open hour settings', 'restropress' ); ?></a></div>
				<?php else : ?>
					<fieldset class="rp-sw-allday">
						<legend><?php esc_html_e( 'Same hours every day', 'restropress' ); ?></legend>
						<label for="rp-sw-all-open"><?php esc_html_e( 'Open', 'restropress' ); ?></label>
						<input type="time" id="rp-sw-all-open" value="<?php echo esc_attr( isset( $week[1] ) ? $week[1]['open'] : '09:00' ); ?>">
						<label for="rp-sw-all-close"><?php esc_html_e( 'Close', 'restropress' ); ?></label>
						<input type="time" id="rp-sw-all-close" value="<?php echo esc_attr( isset( $week[1] ) ? $week[1]['close'] : '22:00' ); ?>">
						<button type="button" class="rp-btn rp-btn-secondary rp-ob-btn-sm" id="rp-sw-all-apply"><?php esc_html_e( 'Apply to all days', 'restropress' ); ?></button>
					</fieldset>
					<div class="rp-sw-week" role="table" aria-label="<?php esc_attr_e( 'Weekly hours', 'restropress' ); ?>">
						<?php foreach ( $day_names as $num => $label ) : $row = $week[ $num ]; ?>
							<div class="rp-sw-day<?php echo $row['closed'] ? ' is-closed' : ''; ?>" role="row" data-day="<?php echo (int) $num; ?>">
								<span class="rp-sw-dayname" role="rowheader"><?php echo esc_html( $label ); ?></span>
								<span role="cell"><label class="screen-reader-text" for="rp-sw-open-<?php echo (int) $num; ?>"><?php /* translators: %s: day name */ echo esc_html( sprintf( __( '%s opens', 'restropress' ), $label ) ); ?></label><input type="time" id="rp-sw-open-<?php echo (int) $num; ?>" class="rp-sw-open" value="<?php echo esc_attr( $row['open'] ); ?>"<?php disabled( $row['closed'] ); ?>></span>
								<span role="cell"><label class="screen-reader-text" for="rp-sw-close-<?php echo (int) $num; ?>"><?php /* translators: %s: day name */ echo esc_html( sprintf( __( '%s closes', 'restropress' ), $label ) ); ?></label><input type="time" id="rp-sw-close-<?php echo (int) $num; ?>" class="rp-sw-close" value="<?php echo esc_attr( $row['close'] ); ?>"<?php disabled( $row['closed'] ); ?>></span>
								<span role="cell"><label class="rp-ob-check rp-sw-closed"><input type="checkbox" class="rp-sw-closed-cb" <?php checked( $row['closed'] ); ?>> <?php esc_html_e( 'Closed', 'restropress' ); ?></label></span>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="rp-ob-hint rp-help-text"><?php esc_html_e( 'Holidays, slot interval and "always open" are under Settings > General > Service and Hours.', 'restropress' ); ?></p>
				<?php endif; ?>
			</section>

			<!-- 4 DELIVERY -->
			<section class="rp-ob-pane" data-pane="delivery" hidden>
				<h2 class="rp-ob-title"><?php esc_html_e( 'Delivery fee and minimum order', 'restropress' ); ?></h2>
				<p class="rp-ob-sub"><?php esc_html_e( 'Leave a field empty for no fee or no minimum.', 'restropress' ); ?></p>
				<div class="rp-sw-delivery-only">
					<?php if ( $core_fee ) : ?>
						<div class="rp-ob-two">
							<div class="rp-ob-field"><label for="rp-sw-fee"><?php /* translators: %s: currency symbol */ echo esc_html( sprintf( __( 'Flat delivery fee (%s)', 'restropress' ), $cur_symbol ) ); ?></label><input type="number" min="0" step="0.01" name="flat_delivery_fee" id="rp-sw-fee" value="<?php echo esc_attr( $flat_fee ); ?>" placeholder="0"></div>
							<div class="rp-ob-field"><label for="rp-sw-min-del"><?php /* translators: %s: currency symbol */ echo esc_html( sprintf( __( 'Minimum order for delivery (%s)', 'restropress' ), $cur_symbol ) ); ?></label><input type="number" min="0" step="0.01" name="minimum_order_price" id="rp-sw-min-del" value="<?php echo esc_attr( $min_delivery ); ?>" placeholder="0"></div>
						</div>
						<div class="rp-notice is-info rp-sw-upsell"><?php esc_html_e( 'Need delivery zones by postcode or distance, or free delivery above an amount? The Delivery Fee extension adds them.', 'restropress' ); ?> <a href="https://www.restropress.com/downloads/delivery-fees/" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more', 'restropress' ); ?></a></div>
					<?php else : ?>
						<div class="rp-ob-field"><label for="rp-sw-min-del"><?php /* translators: %s: currency symbol */ echo esc_html( sprintf( __( 'Minimum order for delivery (%s)', 'restropress' ), $cur_symbol ) ); ?></label><input type="number" min="0" step="0.01" name="minimum_order_price" id="rp-sw-min-del" value="<?php echo esc_attr( $min_delivery ); ?>" placeholder="0"></div>
						<div class="rp-notice is-info"><?php esc_html_e( 'Your delivery zones and fees are managed by the Delivery Fee extension.', 'restropress' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-settings&tab=general' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open delivery zones', 'restropress' ); ?></a></div>
					<?php endif; ?>
				</div>
				<div class="rp-sw-pickup-only">
					<div class="rp-ob-field" style="max-width:320px"><label for="rp-sw-min-pu"><?php /* translators: %s: currency symbol */ echo esc_html( sprintf( __( 'Minimum order for pickup (%s)', 'restropress' ), $cur_symbol ) ); ?></label><input type="number" min="0" step="0.01" name="minimum_order_price_pickup" id="rp-sw-min-pu" value="<?php echo esc_attr( $min_pickup ); ?>" placeholder="0"></div>
				</div>
				<p class="rp-notice is-info rp-sw-no-delivery" hidden><?php esc_html_e( 'You only offer pickup, so there is no delivery fee to set.', 'restropress' ); ?></p>
			</section>

			<!-- 5 PAYMENTS -->
			<section class="rp-ob-pane" data-pane="payments" hidden>
				<h2 class="rp-ob-title"><?php esc_html_e( 'How will customers pay?', 'restropress' ); ?></h2>
				<p class="rp-ob-sub"><?php esc_html_e( 'Cash on delivery is on so you can start today. Add card payments now or later.', 'restropress' ); ?></p>
				<?php if ( $is_test_mode ) : ?>
					<div class="rp-notice is-warning"><?php esc_html_e( 'Test mode is on: online payments are not charged. Turn it off under Settings > Payments when you go live.', 'restropress' ); ?></div>
				<?php endif; ?>

				<div class="rp-ob-pay">
					<div class="rp-ob-pic" aria-hidden="true">&#128181;</div>
					<div class="rp-ob-pb"><b id="rp-sw-cash-label"><?php esc_html_e( 'Cash on delivery', 'restropress' ); ?></b><small><?php esc_html_e( 'Paid at the door or the counter. No setup needed.', 'restropress' ); ?></small></div>
					<button type="button" class="rp-ob-switch<?php echo $cash_on ? ' on' : ''; ?>" data-pay="cash" role="switch" aria-checked="<?php echo $cash_on ? 'true' : 'false'; ?>" aria-labelledby="rp-sw-cash-label"><i></i></button>
				</div>
				<input type="hidden" name="cash_gateway" id="rp-ob-cash-enabled" value="<?php echo $cash_on ? '1' : '0'; ?>">

				<?php if ( $stripe_available ) : ?>
				<div class="rp-ob-pay">
					<div class="rp-ob-pic" aria-hidden="true">&#128179;</div>
					<div class="rp-ob-pb">
						<b id="rp-sw-stripe-label"><?php esc_html_e( 'Stripe', 'restropress' ); ?></b>
						<small><?php esc_html_e( 'Cards, Apple Pay and Google Pay.', 'restropress' ); ?></small>
						<div class="rp-ob-paymeta" id="rp-ob-stripe-meta"<?php echo $stripe_enabled ? '' : ' hidden'; ?>>
							<p class="rp-ob-hint rp-help-text"><?php echo $is_test_mode ? esc_html__( 'Paste your Stripe test keys (Stripe Dashboard > Developers > API keys).', 'restropress' ) : esc_html__( 'Paste your Stripe live keys (Stripe Dashboard > Developers > API keys).', 'restropress' ); ?></p>
							<div class="rp-ob-field"><label for="rp-sw-stripe-pk"><?php esc_html_e( 'Publishable key', 'restropress' ); ?></label><input type="text" id="rp-sw-stripe-pk" value="<?php echo esc_attr( $stripe_pub ); ?>" autocomplete="off" spellcheck="false" placeholder="<?php echo esc_attr( $is_test_mode ? 'pk_test_...' : 'pk_live_...' ); ?>"></div>
							<div class="rp-ob-field"><label for="rp-sw-stripe-sk"><?php esc_html_e( 'Secret key', 'restropress' ); ?></label><input type="password" id="rp-sw-stripe-sk" value="" autocomplete="off" spellcheck="false" placeholder="<?php echo $stripe_secret_saved ? esc_attr__( 'Saved. Enter a new key to replace it.', 'restropress' ) : esc_attr( $is_test_mode ? 'sk_test_...' : 'sk_live_...' ); ?>"></div>
							<a href="<?php echo esc_url( $stripe_settings_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'All Stripe settings', 'restropress' ); ?></a>
						</div>
					</div>
					<button type="button" class="rp-ob-switch<?php echo $stripe_enabled ? ' on' : ''; ?>" data-pay="stripe" role="switch" aria-checked="<?php echo $stripe_enabled ? 'true' : 'false'; ?>" aria-labelledby="rp-sw-stripe-label"><i></i></button>
				</div>
				<input type="hidden" name="stripe_enabled" id="rp-ob-stripe-enabled" value="<?php echo $stripe_enabled ? '1' : '0'; ?>">
				<?php endif; ?>

				<div class="rp-ob-pay">
					<div class="rp-ob-pic" aria-hidden="true">P</div>
					<div class="rp-ob-pb">
						<b id="rp-sw-paypal-label"><?php esc_html_e( 'PayPal', 'restropress' ); ?></b>
						<small><?php esc_html_e( 'Let customers pay with PayPal.', 'restropress' ); ?></small>
						<div class="rp-ob-paymeta" id="rp-ob-paypal-meta"<?php echo $pp_enabled ? '' : ' hidden'; ?>>
							<?php if ( $pp_configured ) : ?>
								<span class="rp-ob-payok"><?php echo esc_html( $pp_email ? $pp_email : __( 'Connected', 'restropress' ) ); ?></span>
								<a href="<?php echo esc_url( $pp_settings_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Edit PayPal settings', 'restropress' ); ?></a>
							<?php else : ?>
								<span class="rp-ob-paywarn"><?php esc_html_e( 'Needs your PayPal account before it can take payments.', 'restropress' ); ?></span>
								<a href="<?php echo esc_url( $pp_settings_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Connect PayPal', 'restropress' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
					<button type="button" class="rp-ob-switch<?php echo $pp_enabled ? ' on' : ''; ?>" data-pay="paypal" role="switch" aria-checked="<?php echo $pp_enabled ? 'true' : 'false'; ?>" aria-labelledby="rp-sw-paypal-label"><i></i></button>
				</div>
				<input type="hidden" name="paypal_enabled" id="rp-ob-paypal-enabled" value="<?php echo $pp_enabled ? '1' : '0'; ?>">
				<p class="rp-ob-hint rp-help-text"><a href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-extensions&s=payment+gateway' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'More payment methods (Square, Braintree, Authorize.Net and others)', 'restropress' ); ?></a></p>
			</section>

			<!-- 6 MENU -->
			<?php include __DIR__ . '/menu-pane.php'; ?>

			<!-- 7 TEST ORDER -->
			<section class="rp-ob-pane" data-pane="test" hidden>
				<h2 class="rp-ob-title"><?php esc_html_e( 'Place a test order', 'restropress' ); ?></h2>
				<p class="rp-ob-sub"><?php esc_html_e( 'Order something from your storefront like a customer would. It should arrive in Live Orders and in your inbox.', 'restropress' ); ?></p>
				<div class="rp-ob-testcta">
					<div><b><?php esc_html_e( 'Open your menu page', 'restropress' ); ?></b><div><?php esc_html_e( 'Opens in a new tab. Pay with cash on delivery to keep it simple.', 'restropress' ); ?></div></div>
					<a class="rp-btn rp-btn-primary" href="<?php echo esc_url( $store_url ); ?>" target="_blank" rel="noopener" id="rp-ob-openstore"><?php esc_html_e( 'Open storefront', 'restropress' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'restropress' ); ?></span></a>
				</div>
				<div class="rp-ob-group"><?php esc_html_e( 'Launch checklist', 'restropress' ); ?></div>
				<div id="rp-sw-checklist" aria-live="polite">
					<?php
					if ( $has_wizard ) {
						rpress_setup_wizard_render_checklist( rpress_setup_wizard_checklist() );
					}
					?>
				</div>
				<p class="rp-ob-hint rp-help-text"><?php esc_html_e( 'Anything still missing can be fixed later. Finishing never locks you out of a step.', 'restropress' ); ?></p>
			</section>

			<!-- DONE -->
			<section class="rp-ob-pane rp-ob-done" data-pane="done" hidden>
				<div class="rp-ob-sring" aria-hidden="true">&#10003;</div>
				<h2 class="rp-ob-title"><?php esc_html_e( 'You are ready', 'restropress' ); ?></h2>
				<p class="rp-ob-sub"><?php esc_html_e( 'Keep Live Orders open on a tablet or computer at the counter, and share your menu link to get your first orders.', 'restropress' ); ?></p>
				<div class="rp-ob-share"><label for="rp-sw-share" class="screen-reader-text"><?php esc_html_e( 'Your menu link', 'restropress' ); ?></label><input type="text" id="rp-sw-share" value="<?php echo esc_url( $store_url ); ?>" readonly><button type="button" class="rp-btn rp-btn-primary" id="rp-ob-copy"><?php esc_html_e( 'Copy link', 'restropress' ); ?></button></div>
				<div class="rp-ob-nextcards">
					<a class="rp-ob-nc rp-sw-primary-card" href="<?php echo esc_url( $live_url ); ?>"><span aria-hidden="true">&#128276;</span><div><b><?php esc_html_e( 'Open Live Orders', 'restropress' ); ?></b></div></a>
					<a class="rp-ob-nc" href="<?php echo esc_url( $store_url ); ?>" target="_blank" rel="noopener"><span aria-hidden="true">&#128717;</span><div><b><?php esc_html_e( 'View storefront', 'restropress' ); ?></b></div></a>
					<a class="rp-ob-nc" href="<?php echo esc_url( admin_url( 'edit.php?post_type=fooditem' ) ); ?>"><span aria-hidden="true">&#127869;</span><div><b><?php esc_html_e( 'Manage menu', 'restropress' ); ?></b></div></a>
					<a class="rp-ob-nc" href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-settings' ) ); ?>"><span aria-hidden="true">&#9881;</span><div><b><?php esc_html_e( 'Settings', 'restropress' ); ?></b></div></a>
				</div>
				<p class="rp-ob-hint rp-help-text"><button type="button" class="button-link" id="rp-sw-review"><?php esc_html_e( 'Review the setup steps again', 'restropress' ); ?></button></p>
			</section>

		</div></div>
		<div class="rp-ob-footer" id="rp-ob-footer">
			<button type="button" class="rp-btn rp-btn-secondary" id="rp-ob-back"><?php esc_html_e( 'Back', 'restropress' ); ?></button>
			<span class="rp-sw-footer-right">
				<button type="button" class="button-link rp-sw-skip" id="rp-sw-skip"><?php esc_html_e( 'Skip for now', 'restropress' ); ?></button>
				<button type="button" class="rp-btn rp-btn-primary" id="rp-ob-next"><?php esc_html_e( 'Save and continue', 'restropress' ); ?></button>
			</span>
		</div>
	</div>

	<!-- PREVIEW -->
	<div class="rp-ob-preview" aria-hidden="true">
		<div class="rp-ob-canvas-head"><span><?php esc_html_e( 'Live preview', 'restropress' ); ?></span><span class="rp-ob-live"><i></i> <?php esc_html_e( 'Updating', 'restropress' ); ?></span></div>
		<div class="rp-ob-phone"><div class="rp-ob-screen">
			<div class="rp-ob-ph-hero"><div class="rp-ob-ph-nm" id="rp-ob-pv-name"><?php echo esc_html( $store_name ); ?></div><div class="rp-ob-ph-meta"><span id="rp-ob-pv-svc"><?php echo esc_html( $service_labels[ $cur_service ] ); ?></span> &middot; <span id="rp-ob-pv-hours"><?php echo esc_html( $hours_label ); ?></span></div></div>
			<div class="rp-ob-ph-body" id="rp-ob-pv-body"><div class="rp-ob-ph-empty"><div>&#127869;</div><div><?php esc_html_e( 'Your menu will appear here as you add it.', 'restropress' ); ?></div></div></div>
			<div class="rp-ob-ph-foot"><div class="rp-ob-ph-cta" id="rp-ob-pv-cart"><?php esc_html_e( 'View cart', 'restropress' ); ?> &middot; <?php echo esc_html( $cur_symbol ); ?>0</div></div>
		</div></div>
		<div class="rp-ob-canvas-note"><?php esc_html_e( 'A preview of what customers will see.', 'restropress' ); ?></div>
	</div>
</div>
