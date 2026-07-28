<?php
/**
 * RestroPress onboarding - v4 guided setup (rail + steps + live preview).
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
$completed    = isset( $state['completed_tasks'] ) && is_array( $state['completed_tasks'] ) ? $state['completed_tasks'] : array();
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
$open_time    = rpress_get_option( 'open_time', '10:00' );
$close_time   = rpress_get_option( 'close_time', '22:30' );
$alert_email  = rpress_get_option( 'admin_notice_emails', get_option( 'admin_email' ) );
$enable_vegmark = rpress_get_option( 'enable_food_type', false );
$cur_service  = rpress_get_option( 'enable_service', 'delivery_and_pickup' );
$cur_service  = in_array( $cur_service, array( 'delivery_and_pickup', 'delivery', 'pickup' ), true ) ? $cur_service : 'delivery_and_pickup';
$asap_on      = (string) rpress_get_option( 'enable_asap_option', '1' );
$notify_on    = rpress_get_option( 'enable_order_notification', '1' );
$min_delivery = rpress_get_option( 'minimum_order_price', '' );
$min_pickup   = rpress_get_option( 'minimum_order_price_pickup', '' );
// Appearance step (Styles settings surfaced during onboarding).
$cur_pack     = rpress_get_option( 'template_pack', 'classic' );
$cur_pack     = in_array( $cur_pack, array( 'classic', 'modern' ), true ) ? $cur_pack : 'classic';
$theme_color  = rpress_get_option( 'primary_color', '#ED5575' );
$theme_color  = sanitize_hex_color( $theme_color ) ? $theme_color : '#ED5575';
$menu_layout  = rpress_get_option( 'template', 'list' );
$menu_layout  = in_array( $menu_layout, array( 'list', 'grid' ), true ) ? $menu_layout : 'list';
$service_labels = array(
	'delivery_and_pickup' => __( 'Pickup & delivery', 'restropress' ),
	'pickup'              => __( 'Pickup only', 'restropress' ),
	'delivery'            => __( 'Delivery only', 'restropress' ),
);
$tz_string    = get_option( 'timezone_string' );
$wp_offset    = (float) get_option( 'gmt_offset', 0 );
$time_format  = get_option( 'time_format', 'g:i A' );
$is_24h       = ( false === strpos( $time_format, 'A' ) && false === strpos( $time_format, 'a' ) );
$ai_ready     = ! empty( $ai_status['ready'] );
// Display "HH:MM" option values in the format chosen on the profile step.
$rp_ob_fmt_time = function ( $t ) use ( $is_24h ) {
	$ts = strtotime( (string) $t );
	if ( ! $ts ) {
		return (string) $t;
	}
	return $is_24h ? date( 'H:i', $ts ) : date( 'g:i A', $ts );
};
$hours_label = $rp_ob_fmt_time( $open_time ) . '–' . $rp_ob_fmt_time( $close_time );
// Public storefront = the "Order Online" food-listing page (food_items_page);
// fall back to the checkout page, then the site home.
$order_page_id = (int) rpress_get_option( 'food_items_page', 0 );
if ( ! $order_page_id || 'publish' !== get_post_status( $order_page_id ) ) {
	$order_page_id = (int) rpress_get_option( 'purchase_page', 0 );
}
$store_url = ( $order_page_id && 'publish' === get_post_status( $order_page_id ) ) ? get_permalink( $order_page_id ) : home_url( '/' );

// Enabled gateways + global test mode (configuration lives on Settings -> Payments).
$enabled_gateways = (array) rpress_get_option( 'gateways', array() );
$all_gateways     = function_exists( 'rpress_get_payment_gateways' ) ? (array) rpress_get_payment_gateways() : array();
$is_test_mode     = function_exists( 'rpress_is_test_mode' ) ? rpress_is_test_mode() : (bool) rpress_get_option( 'test_mode', false );

// PayPal status.
$pp_email      = rpress_get_option( 'paypal_email', '' );
$pp_configured = ( $pp_email || rpress_get_option( 'paypal_rest_client_id', '' ) );
$pp_enabled    = ! empty( $enabled_gateways['paypal'] );
$pp_settings_url = function_exists( 'rpress_get_paypal_settings_page_url' ) ? rpress_get_paypal_settings_page_url() : admin_url( 'admin.php?page=rpress-settings&tab=gateways&section=paypal' );

// Stripe status (core Stripe, ships with RestroPress).
$stripe_available  = isset( $all_gateways['stripe'] );
$stripe_enabled    = ! empty( $enabled_gateways['stripe'] );
$stripe_secret     = $is_test_mode ? rpress_get_option( 'test_secret_key', '' ) : rpress_get_option( 'live_secret_key', '' );
$stripe_pub        = $is_test_mode ? rpress_get_option( 'test_publishable_key', '' ) : rpress_get_option( 'live_publishable_key', '' );
$stripe_configured = ( $stripe_secret && $stripe_pub );
$stripe_settings_url = admin_url( 'admin.php?page=rpress-settings&tab=gateways&section=stripe' );

// Steps for the rail.
$steps = array(
	'profile'    => array( 'label' => __( 'Restaurant profile', 'restropress' ), 'sub' => __( 'Name, location & basics', 'restropress' ) ),
	'appearance' => array( 'label' => __( 'Storefront look', 'restropress' ),     'sub' => __( 'Template & brand colour', 'restropress' ) ),
	'menu'     => array( 'label' => __( 'Your menu', 'restropress' ),          'sub' => __( 'Import or build', 'restropress' ) ),
	'config'   => array( 'label' => __( 'How you sell', 'restropress' ),       'sub' => __( 'Service, hours, labels', 'restropress' ) ),
	'payments' => array( 'label' => __( 'Payments', 'restropress' ),           'sub' => __( 'Cash + online options', 'restropress' ) ),
	'golive'   => array( 'label' => __( 'Test & go live', 'restropress' ),     'sub' => __( 'Launch your store', 'restropress' ) ),
);
?>
<div class="rp-onboard rp-admin-scope<?php echo $is_launched ? ' is-launched' : ''; ?>"
	data-cur="<?php echo esc_attr( $cur_symbol ); ?>"
	data-completed="<?php echo esc_attr( implode( ',', array_map( 'sanitize_key', $completed ) ) ); ?>"
	data-vegmark="<?php echo $enable_vegmark ? '1' : '0'; ?>"
	data-ai-ready="<?php echo $ai_ready ? '1' : '0'; ?>"
	data-store-name="<?php echo esc_attr( $store_name ); ?>">

	<!-- RAIL -->
	<nav class="rp-ob-rail">
		<div class="rp-ob-brand"><img class="rp-ob-logo-img" src="<?php echo esc_url( RP_PLUGIN_URL . 'assets/images/restropress-logo.png' ); ?>" alt="RestroPress"></div>
		<h4><?php esc_html_e( 'Get set up', 'restropress' ); ?></h4>
		<div class="rp-ob-progress"><div class="rp-ob-pbar"><i id="rp-ob-pbar"></i></div><div class="rp-ob-pmeta" id="rp-ob-pmeta"></div></div>
		<ul class="rp-ob-steps" id="rp-ob-steps">
			<?php $n = 1; foreach ( $steps as $key => $s ) : ?>
				<li class="rp-ob-step" data-step="<?php echo esc_attr( $key ); ?>">
					<span class="rp-ob-dot"><?php echo (int) $n; ?></span>
					<span class="rp-ob-lbl"><b><?php echo esc_html( $s['label'] ); ?></b><small><?php echo esc_html( $s['sub'] ); ?></small></span>
				</li>
			<?php $n++; endforeach; ?>
		</ul>
		<div class="rp-ob-railfoot"><?php esc_html_e( 'Go in order, or jump to any step. Your progress is saved.', 'restropress' ); ?></div>
	</nav>

	<!-- MAIN -->
	<div class="rp-ob-main">
		<div class="rp-ob-top"><span class="rp-ob-stepof" id="rp-ob-stepof"></span><a class="rp-ob-skip" href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-payment-history' ) ); ?>"><?php esc_html_e( 'Save & finish later', 'restropress' ); ?></a></div>
		<div class="rp-ob-scroll"><div class="rp-ob-wrap">

			<!-- PROFILE -->
			<section class="rp-ob-pane" data-pane="profile">
				<h2 class="rp-ob-title"><?php esc_html_e( 'Tell us about your restaurant', 'restropress' ); ?></h2>
				<p class="rp-ob-sub"><?php esc_html_e( 'This is the foundation - it powers your storefront, receipts, taxes and delivery.', 'restropress' ); ?></p>

				<div class="rp-ob-group"><?php esc_html_e( 'Identity', 'restropress' ); ?></div>
				<div class="rp-ob-two">
					<div class="rp-ob-field"><label><?php esc_html_e( 'Restaurant name', 'restropress' ); ?></label><input type="text" name="restaurant_name" id="rp-ob-name" value="<?php echo esc_attr( $store_name ); ?>"><p class="rp-ob-hint rp-help-text"><?php esc_html_e( 'Shown on your storefront, receipts & emails.', 'restropress' ); ?></p></div>
					<div class="rp-ob-field"><label><?php esc_html_e( 'Contact phone', 'restropress' ); ?></label><input type="text" name="store_phone" value="<?php echo esc_attr( $store_phone ); ?>" placeholder="+1 555 0100"><p class="rp-ob-hint rp-help-text"><?php esc_html_e( 'For customer callbacks & receipts.', 'restropress' ); ?></p></div>
				</div>

				<div class="rp-ob-group"><?php esc_html_e( 'Location', 'restropress' ); ?></div>
				<div class="rp-ob-two">
					<div class="rp-ob-field"><label><?php esc_html_e( 'Country', 'restropress' ); ?></label>
						<select name="base_country" id="rp-ob-country" class="rp-ob-select">
							<option value=""><?php esc_html_e( 'Select country', 'restropress' ); ?></option>
							<?php foreach ( $countries as $code => $cname ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $base_country, $code ); ?>><?php echo esc_html( $cname ); ?></option>
							<?php endforeach; ?>
						</select></div>
					<div class="rp-ob-field"><label><?php esc_html_e( 'State / Province', 'restropress' ); ?></label>
						<select name="base_state" id="rp-ob-state" class="rp-ob-select" data-selected="<?php echo esc_attr( $base_state ); ?>"><option value=""><?php esc_html_e( 'Select state / province', 'restropress' ); ?></option></select></div>
				</div>
				<div class="rp-ob-field"><label><?php esc_html_e( 'Street address', 'restropress' ); ?></label><input type="text" name="store_address" value="<?php echo esc_attr( $store_addr ); ?>" placeholder="<?php esc_attr_e( 'Street address', 'restropress' ); ?>"></div>
				<div class="rp-ob-two">
					<div class="rp-ob-field"><label><?php esc_html_e( 'City', 'restropress' ); ?></label><input type="text" name="store_city" value="<?php echo esc_attr( $store_city ); ?>"></div>
					<div class="rp-ob-field"><label><?php esc_html_e( 'Postcode / ZIP', 'restropress' ); ?></label><input type="text" name="store_postcode" value="<?php echo esc_attr( $store_zip ); ?>"></div>
				</div>

				<div class="rp-ob-group"><?php esc_html_e( 'Currency', 'restropress' ); ?></div>
				<div class="rp-ob-field" style="max-width:320px"><label><?php esc_html_e( 'Store currency', 'restropress' ); ?></label>
					<select name="currency" id="rp-ob-currency" class="rp-ob-select">
						<?php foreach ( $currencies as $ccode => $clabel ) : ?>
							<option value="<?php echo esc_attr( $ccode ); ?>" <?php selected( $cur_code, $ccode ); ?>><?php echo esc_html( $clabel ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="rp-ob-hint rp-help-text"><?php esc_html_e( 'You’ll choose pickup / delivery and timing in “How you sell”.', 'restropress' ); ?></p></div>

				<div class="rp-ob-group"><?php esc_html_e( 'Time & clock', 'restropress' ); ?></div>
				<div class="rp-ob-two">
					<div class="rp-ob-field"><label><?php esc_html_e( 'Timezone', 'restropress' ); ?></label>
						<?php $tz_choice = function_exists( 'wp_timezone_choice' ) ? wp_timezone_choice( $tz_string ) : ''; ?>
						<select name="timezone_string" id="rp-ob-tz" class="rp-ob-select"><?php echo $tz_choice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper returns escaped option markup ?></select>
						<p class="rp-ob-hint rp-help-text"><?php esc_html_e( 'Used for order times, hours & reports.', 'restropress' ); ?></p></div>
					<div class="rp-ob-field"><label><?php esc_html_e( 'Time format', 'restropress' ); ?></label>
						<div class="rp-ob-seg" id="rp-ob-tf">
							<button type="button" data-tf="12" class="<?php echo $is_24h ? '' : 'on'; ?>"><?php esc_html_e( '1:30 PM', 'restropress' ); ?></button>
							<button type="button" data-tf="24" class="<?php echo $is_24h ? 'on' : ''; ?>"><?php esc_html_e( '13:30', 'restropress' ); ?></button>
						</div>
						<input type="hidden" name="time_format" id="rp-ob-tf-val" value="<?php echo $is_24h ? '24' : '12'; ?>">
						<div class="rp-ob-clock">🕒 <?php esc_html_e( 'Current time at your restaurant:', 'restropress' ); ?> <b id="rp-ob-clocknow">-</b></div>
					</div>
				</div>
				<p class="rp-ob-hint rp-help-text"><?php esc_html_e( 'Saves to your WordPress timezone & time format - applies across your whole site.', 'restropress' ); ?></p>
			</section>

			<!-- APPEARANCE -->
			<section class="rp-ob-pane" data-pane="appearance" hidden>
				<h2 class="rp-ob-title"><?php esc_html_e( 'Choose your storefront look', 'restropress' ); ?></h2>
				<p class="rp-ob-sub"><?php esc_html_e( 'Pick a design and your brand colour. You can change these any time under Settings → Styles.', 'restropress' ); ?></p>

				<div class="rp-ob-group"><?php esc_html_e( 'Template', 'restropress' ); ?></div>
				<div class="rp-ob-packs">
					<label class="rp-ob-pack">
						<input type="radio" name="template_pack" value="classic" <?php checked( $cur_pack, 'classic' ); ?>>
						<span class="rp-ob-pack-in">
							<span class="rp-ob-pack-thumb"><svg viewBox="0 0 76 66" xmlns="http://www.w3.org/2000/svg"><rect width="76" height="66" fill="#f1f1f2"/><rect x="14" y="18" width="30" height="7" rx="3.5" fill="#e0521f"/><rect x="14" y="32" width="48" height="5" rx="2.5" fill="#d5d7da"/><rect x="14" y="43" width="48" height="5" rx="2.5" fill="#d5d7da"/></svg></span>
							<span class="rp-ob-pack-body"><b><?php esc_html_e( 'Classic', 'restropress' ); ?></b><small><?php esc_html_e( 'The original RestroPress look: clean and neutral, inherits your theme.', 'restropress' ); ?></small></span>
						</span>
					</label>
					<label class="rp-ob-pack">
						<input type="radio" name="template_pack" value="modern" <?php checked( $cur_pack, 'modern' ); ?>>
						<span class="rp-ob-pack-in">
							<span class="rp-ob-pack-thumb"><svg viewBox="0 0 76 66" xmlns="http://www.w3.org/2000/svg"><rect width="76" height="66" fill="#1b1b1f"/><rect x="16" y="14" width="17" height="17" rx="4" fill="#e0521f"/><rect x="43" y="14" width="17" height="17" rx="4" fill="#6c5ce7"/><rect x="16" y="36" width="17" height="17" rx="4" fill="#2e9e4f"/><rect x="43" y="36" width="17" height="17" rx="4" fill="#f0c040"/></svg></span>
							<span class="rp-ob-pack-body"><b><?php esc_html_e( 'Modern', 'restropress' ); ?></b><small><?php esc_html_e( 'Image-forward, food-delivery-app style: big photos, rounded cards.', 'restropress' ); ?></small></span>
						</span>
					</label>
				</div>

				<div class="rp-ob-two">
					<div class="rp-ob-field">
						<div class="rp-ob-group" style="margin-top:4px"><?php esc_html_e( 'Brand colour', 'restropress' ); ?></div>
						<label><?php esc_html_e( 'Theme colour', 'restropress' ); ?></label>
						<span class="rp-ob-colorwrap"><input type="color" name="primary_color" id="rp-ob-theme-color" value="<?php echo esc_attr( $theme_color ); ?>"><code id="rp-ob-theme-hex"><?php echo esc_html( strtoupper( $theme_color ) ); ?></code></span>
						<p class="rp-ob-hint rp-help-text"><?php esc_html_e( 'Used for buttons, links and highlights across your storefront.', 'restropress' ); ?></p>
					</div>
					<div class="rp-ob-field">
						<div class="rp-ob-group" style="margin-top:4px"><?php esc_html_e( 'Menu layout', 'restropress' ); ?></div>
						<label><?php esc_html_e( 'How items are arranged', 'restropress' ); ?></label>
						<div class="rp-ob-seg" id="rp-ob-layout"><button type="button" data-layout="list" class="<?php echo 'list' === $menu_layout ? 'on' : ''; ?>"><?php esc_html_e( 'List', 'restropress' ); ?></button><button type="button" data-layout="grid" class="<?php echo 'grid' === $menu_layout ? 'on' : ''; ?>"><?php esc_html_e( 'Grid', 'restropress' ); ?></button></div>
						<input type="hidden" name="template" id="rp-ob-layout-val" value="<?php echo esc_attr( $menu_layout ); ?>">
						<p class="rp-ob-hint rp-help-text"><?php esc_html_e( 'Works with either template.', 'restropress' ); ?></p>
					</div>
				</div>
			</section>

			<!-- MENU -->
			<?php include __DIR__ . '/menu-pane.php'; ?>

			<!-- CONFIG -->
			<section class="rp-ob-pane" data-pane="config" hidden>
				<h2 class="rp-ob-title"><?php esc_html_e( 'How you sell', 'restropress' ); ?></h2>
				<p class="rp-ob-sub"><?php esc_html_e( 'Pre-filled with sensible defaults - review and tweak anything you like.', 'restropress' ); ?></p>

				<div class="rp-ob-cfg">
					<div class="rp-ob-cfg-head"><span class="rp-ob-cic">🛵</span><span class="rp-ob-ctitle"><?php esc_html_e( 'Service & timing', 'restropress' ); ?></span><span class="rp-ob-chint"><?php esc_html_e( 'What you offer and how long orders take', 'restropress' ); ?></span></div>
					<div class="rp-ob-cfg-body">
						<div class="rp-ob-field"><label><?php esc_html_e( 'Services offered', 'restropress' ); ?></label>
							<div class="rp-ob-seg" id="rp-ob-svc"><button type="button" data-svc="delivery_and_pickup" class="<?php echo 'delivery_and_pickup' === $cur_service ? 'on' : ''; ?>"><?php esc_html_e( 'Pickup & delivery', 'restropress' ); ?></button><button type="button" data-svc="pickup" class="<?php echo 'pickup' === $cur_service ? 'on' : ''; ?>"><?php esc_html_e( 'Pickup', 'restropress' ); ?></button><button type="button" data-svc="delivery" class="<?php echo 'delivery' === $cur_service ? 'on' : ''; ?>"><?php esc_html_e( 'Delivery', 'restropress' ); ?></button></div>
							<input type="hidden" name="enable_service" id="rp-ob-svc-val" value="<?php echo esc_attr( $cur_service ); ?>"></div>
						<div class="rp-ob-two"><div class="rp-ob-field"><label><?php esc_html_e( 'Prep time (min)', 'restropress' ); ?></label><input type="number" name="prep_time" value="<?php echo esc_attr( $prep_time ); ?>"><p class="rp-ob-hint rp-help-text"><?php esc_html_e( 'Sets the earliest pickup / delivery time quoted to guests.', 'restropress' ); ?></p></div><div class="rp-ob-field"><label><?php esc_html_e( 'Allow ASAP orders', 'restropress' ); ?></label><select name="enable_asap_option" class="rp-ob-select"><option value="1" <?php selected( '1', $asap_on ); ?>><?php esc_html_e( 'Yes', 'restropress' ); ?></option><option value="" <?php selected( '', $asap_on ); ?>><?php esc_html_e( 'Scheduled only', 'restropress' ); ?></option></select></div></div>
						<div class="rp-ob-two"><div class="rp-ob-field"><label><?php esc_html_e( 'Minimum order - delivery', 'restropress' ); ?></label><input type="number" min="0" step="0.01" name="minimum_order_price" value="<?php echo esc_attr( $min_delivery ); ?>" placeholder="0"><p class="rp-ob-hint rp-help-text"><?php esc_html_e( 'Leave 0 / empty for no minimum.', 'restropress' ); ?></p></div><div class="rp-ob-field"><label><?php esc_html_e( 'Minimum order - pickup', 'restropress' ); ?></label><input type="number" min="0" step="0.01" name="minimum_order_price_pickup" value="<?php echo esc_attr( $min_pickup ); ?>" placeholder="0"></div></div>
					</div>
				</div>

				<div class="rp-ob-cfg">
					<div class="rp-ob-cfg-head"><span class="rp-ob-cic">🕒</span><span class="rp-ob-ctitle"><?php esc_html_e( 'Ordering hours', 'restropress' ); ?></span><span class="rp-ob-chint"><?php esc_html_e( 'When guests can place orders', 'restropress' ); ?></span></div>
					<div class="rp-ob-cfg-body"><div class="rp-ob-two"><div class="rp-ob-field"><label><?php esc_html_e( 'Opens', 'restropress' ); ?></label><input type="time" name="open_time" value="<?php echo esc_attr( $open_time ); ?>"></div><div class="rp-ob-field"><label><?php esc_html_e( 'Closes', 'restropress' ); ?></label><input type="time" name="close_time" value="<?php echo esc_attr( $close_time ); ?>"></div></div></div>
				</div>

				<div class="rp-ob-cfg">
					<div class="rp-ob-cfg-head"><span class="rp-ob-cic">🥗</span><span class="rp-ob-ctitle"><?php esc_html_e( 'Menu labelling', 'restropress' ); ?></span><span class="rp-ob-chint"><?php esc_html_e( 'How items are tagged for diners', 'restropress' ); ?></span></div>
					<div class="rp-ob-cfg-body">
						<label class="rp-ob-check"><input type="checkbox" checked disabled> <?php esc_html_e( 'Dietary preferences (Vegetarian, Vegan, Gluten-free, Halal…) - worldwide, always available', 'restropress' ); ?></label>
						<label class="rp-ob-check"><input type="checkbox" name="enable_food_type" id="rp-ob-vegmark" value="1" <?php checked( $enable_vegmark ); ?>> <?php esc_html_e( 'Show Veg / Non-veg marks (common in India & similar markets; off by default)', 'restropress' ); ?></label>
					</div>
				</div>

				<div class="rp-ob-cfg">
					<div class="rp-ob-cfg-head"><span class="rp-ob-cic">🔔</span><span class="rp-ob-ctitle"><?php esc_html_e( 'Order alerts', 'restropress' ); ?></span><span class="rp-ob-chint"><?php esc_html_e( 'How you hear about new orders', 'restropress' ); ?></span></div>
					<div class="rp-ob-cfg-body"><div class="rp-ob-field"><label><?php esc_html_e( 'Send new-order alerts to', 'restropress' ); ?></label><input type="email" name="admin_notice_emails" value="<?php echo esc_attr( $alert_email ); ?>"></div><label class="rp-ob-check"><input type="checkbox" name="enable_order_notification" value="1" <?php checked( ! empty( $notify_on ) ); ?>> <span><?php esc_html_e( 'Ring & pop up a desktop notification when a new order arrives', 'restropress' ); ?><small class="rp-ob-checkhint"><?php esc_html_e( 'Plays a built-in chime (change it under Settings) and pops a notification while this dashboard or Live Orders is open - ideal for a counter tablet or kitchen screen. Your browser asks permission once. Use the email above for alerts when nobody is at the screen.', 'restropress' ); ?></small></span></label></div>
				</div>
			</section>

			<!-- PAYMENTS -->
			<section class="rp-ob-pane" data-pane="payments" hidden>
				<h2 class="rp-ob-title"><?php esc_html_e( 'How you’ll get paid', 'restropress' ); ?></h2>
				<p class="rp-ob-sub"><?php esc_html_e( 'Cash is on by default so you can launch today. Add online options now or later - they never block going live.', 'restropress' ); ?></p>
				<div class="rp-ob-pay"><div class="rp-ob-pic">💵</div><div class="rp-ob-pb"><b><?php esc_html_e( 'Pay by cash', 'restropress' ); ?></b><small><?php esc_html_e( 'On delivery / at the counter · no setup needed', 'restropress' ); ?></small></div><span class="rp-ob-switch on" data-pay="cash"><i></i></span></div>
				<?php if ( $stripe_available ) : ?>
				<div class="rp-ob-pay">
					<div class="rp-ob-pic">💳</div>
					<div class="rp-ob-pb">
						<b><?php esc_html_e( 'Stripe', 'restropress' ); ?></b>
						<small><?php esc_html_e( 'Accept cards, Apple Pay & Google Pay - built into RestroPress.', 'restropress' ); ?></small>
						<div class="rp-ob-paymeta"<?php echo $stripe_enabled ? '' : ' hidden'; ?> id="rp-ob-stripe-meta">
							<?php if ( $stripe_configured ) : ?>
								<span class="rp-ob-payok">✓ <?php echo $is_test_mode ? esc_html__( 'Configured (test keys)', 'restropress' ) : esc_html__( 'Configured', 'restropress' ); ?></span>
								<a href="<?php echo esc_url( $stripe_settings_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Edit settings', 'restropress' ); ?></a>
							<?php else : ?>
								<span class="rp-ob-paywarn">⚠ <?php esc_html_e( 'Needs API keys before it can take payments', 'restropress' ); ?></span>
								<a href="<?php echo esc_url( $stripe_settings_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Configure Stripe →', 'restropress' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
					<span class="rp-ob-switch<?php echo $stripe_enabled ? ' on' : ''; ?>" data-pay="stripe"><i></i></span>
				</div>
				<input type="hidden" name="stripe_enabled" id="rp-ob-stripe-enabled" value="<?php echo $stripe_enabled ? '1' : '0'; ?>">
				<?php endif; ?>
				<div class="rp-ob-pay">
					<div class="rp-ob-pic">🅿️</div>
					<div class="rp-ob-pb">
						<b><?php esc_html_e( 'PayPal', 'restropress' ); ?></b>
						<small><?php esc_html_e( 'Let guests pay online with PayPal.', 'restropress' ); ?></small>
						<div class="rp-ob-paymeta"<?php echo $pp_enabled ? '' : ' hidden'; ?> id="rp-ob-paypal-meta">
							<?php if ( $pp_configured ) : ?>
								<span class="rp-ob-payok">✓ <?php echo esc_html( $pp_email ? $pp_email : __( 'Configured', 'restropress' ) ); ?></span>
								<a href="<?php echo esc_url( $pp_settings_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Edit settings', 'restropress' ); ?></a>
							<?php else : ?>
								<span class="rp-ob-paywarn">⚠ <?php esc_html_e( 'Needs setup before it can take payments', 'restropress' ); ?></span>
								<a href="<?php echo esc_url( $pp_settings_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Configure PayPal →', 'restropress' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
					<span class="rp-ob-switch<?php echo $pp_enabled ? ' on' : ''; ?>" data-pay="paypal"><i></i></span>
				</div>
				<div class="rp-ob-pay"><div class="rp-ob-pic">➕</div><div class="rp-ob-pb"><b><?php esc_html_e( 'More payment methods', 'restropress' ); ?></b><small><?php esc_html_e( 'Braintree, Authorize.Net, Square & more via RestroPress add-ons - set up after launch', 'restropress' ); ?></small></div><a class="rp-ob-defer" href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-extensions&s=payment+gateway' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Browse gateways', 'restropress' ); ?></a></div>
				<input type="hidden" name="cash_gateway" id="rp-ob-cash" value="1">
				<input type="hidden" name="paypal_enabled" id="rp-ob-paypal-enabled" value="<?php echo $pp_enabled ? '1' : '0'; ?>">
			</section>

			<!-- GO LIVE -->
			<section class="rp-ob-pane" data-pane="golive" hidden>
				<h2 class="rp-ob-title"><?php esc_html_e( 'Test it, then go live', 'restropress' ); ?></h2>
				<p class="rp-ob-sub"><?php esc_html_e( 'One real test order proves the whole loop. We verify the basics automatically.', 'restropress' ); ?></p>
				<div class="rp-ob-testcta"><div><b><?php esc_html_e( 'Place a guided test order', 'restropress' ); ?></b><div><?php esc_html_e( 'Opens your storefront so you can run checkout end-to-end.', 'restropress' ); ?></div></div><a class="rp-btn rp-btn-primary" href="<?php echo esc_url( $store_url ); ?>" target="_blank" id="rp-ob-openstore"><?php esc_html_e( 'Open storefront →', 'restropress' ); ?></a></div>
				<div class="rp-ob-verify ok"><span class="rp-ob-vd">✓</span><div><b><?php esc_html_e( 'Menu published', 'restropress' ); ?></b><span id="rp-ob-vmenu"><?php esc_html_e( 'items live on the ordering page', 'restropress' ); ?></span></div></div>
				<div class="rp-ob-verify ok"><span class="rp-ob-vd">✓</span><div><b><?php esc_html_e( 'Payment path ready', 'restropress' ); ?></b><span><?php esc_html_e( 'Pay by cash enabled', 'restropress' ); ?></span></div></div>
				<div class="rp-ob-verify ok"><span class="rp-ob-vd">✓</span><div><b><?php esc_html_e( 'Hours & alerts set', 'restropress' ); ?></b><span><?php echo esc_html( $hours_label . ' · ' . $alert_email ); ?></span></div></div>
				<label class="rp-ob-verify" id="rp-ob-testrow"><span class="rp-ob-vd rp-ob-vd-pending">○</span><div><b><?php esc_html_e( 'Test order confirmed', 'restropress' ); ?></b><span><label class="rp-ob-check" style="margin:0"><input type="checkbox" id="rp-ob-testconfirm"> <?php esc_html_e( 'I placed a test order and received the alert.', 'restropress' ); ?></label></span></div></label>
				<div class="rp-notice is-warning rp-ob-tip">🌙 <div><b><?php esc_html_e( 'Pro tip:', 'restropress' ); ?></b> <?php esc_html_e( 'launch on a slower day (Tue/Wed) so you can ease into your first online orders calmly.', 'restropress' ); ?></div></div>
			</section>

			<!-- DONE -->
			<section class="rp-ob-pane rp-ob-done" data-pane="done" hidden>
				<div class="rp-ob-sring">✓</div>
				<h2 class="rp-ob-title"><?php esc_html_e( 'You’re live 🎉', 'restropress' ); ?></h2>
				<p class="rp-ob-sub"><?php esc_html_e( 'Guests can order right now. Share your link to drive direct orders.', 'restropress' ); ?></p>
				<div class="rp-ob-share"><input type="text" value="<?php echo esc_url( $store_url ); ?>" readonly><button type="button" class="rp-btn rp-btn-primary" id="rp-ob-copy"><?php esc_html_e( 'Copy', 'restropress' ); ?></button></div>
				<div class="rp-ob-nextcards">
					<a class="rp-ob-nc" href="<?php echo esc_url( $store_url ); ?>" target="_blank"><span>🛍️</span><div><b><?php esc_html_e( 'View storefront', 'restropress' ); ?></b></div></a>
					<a class="rp-ob-nc" href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-payment-history' ) ); ?>"><span>📋</span><div><b><?php esc_html_e( 'Go to Orders', 'restropress' ); ?></b></div></a>
					<a class="rp-ob-nc" href="<?php echo esc_url( admin_url( 'edit.php?post_type=fooditem' ) ); ?>"><span>🍽️</span><div><b><?php esc_html_e( 'Manage menu', 'restropress' ); ?></b></div></a>
					<a class="rp-ob-nc" href="<?php echo esc_url( admin_url( 'admin.php?page=rpress-settings' ) ); ?>"><span>⚙️</span><div><b><?php esc_html_e( 'Settings', 'restropress' ); ?></b></div></a>
				</div>
			</section>

		</div></div>
		<div class="rp-ob-footer" id="rp-ob-footer"><button type="button" class="rp-btn rp-btn-secondary" id="rp-ob-back"><?php esc_html_e( '← Back', 'restropress' ); ?></button><button type="button" class="rp-btn rp-btn-primary" id="rp-ob-next"><?php esc_html_e( 'Continue', 'restropress' ); ?></button></div>
	</div>

	<!-- PREVIEW -->
	<div class="rp-ob-preview">
		<div class="rp-ob-canvas-head"><span><?php esc_html_e( 'Live preview', 'restropress' ); ?></span><span class="rp-ob-live"><i></i> <?php esc_html_e( 'Updating', 'restropress' ); ?></span></div>
		<div class="rp-ob-phone"><div class="rp-ob-screen">
			<div class="rp-ob-ph-hero"><div class="rp-ob-ph-nm" id="rp-ob-pv-name"><?php echo esc_html( $store_name ); ?></div><div class="rp-ob-ph-meta"><span id="rp-ob-pv-svc"><?php echo esc_html( $service_labels[ $cur_service ] ); ?></span> · <span id="rp-ob-pv-hours"><?php echo esc_html( $hours_label ); ?></span></div></div>
			<div class="rp-ob-ph-body" id="rp-ob-pv-body"><div class="rp-ob-ph-empty"><div>🍽️</div><div><?php esc_html_e( 'Your menu will appear here as you add it.', 'restropress' ); ?></div></div></div>
			<div class="rp-ob-ph-foot"><div class="rp-ob-ph-cta" id="rp-ob-pv-cart"><?php esc_html_e( 'View cart', 'restropress' ); ?> · <?php echo esc_html( $cur_symbol ); ?>0</div></div>
		</div></div>
		<div class="rp-ob-canvas-note"><?php esc_html_e( 'Exactly what your guests will see. Updates as you build.', 'restropress' ); ?></div>
	</div>
</div>
