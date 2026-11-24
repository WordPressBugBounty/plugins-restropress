<?php
/**
 * Customer dashboard ([customer_dashboard]): sidebar plus the open section.
 *
 * Each section has its own URL (/my-account/orders/ and so on, see
 * includes/features/account/loader.php) and its own template in
 * templates/account/, which themes can override one by one.
 *
 * @package RestroPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	$redirect_url = wp_login_url();
	?>
	<div class="user-dashboard-wrapper user-profile">
		<div class="row">
			<div style="text-align: center; padding-bottom:20px;"><?php esc_html_e( 'You are not logged in, please Log In', 'restropress' ); ?></div>
			<form action="<?php echo esc_url( $redirect_url ); ?>" method="post" style="text-align: center; padding-bottom:20px;">
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_url ); ?>">
				<input type="submit" value="<?php esc_attr_e( 'Log In', 'restropress' ); ?>" />
			</form>
		</div>
	</div>
	<?php
	return;
}

$rpress_page_id  = (int) get_queried_object_id();
$rpress_page_id  = ( $rpress_page_id && 'page' === get_post_type( $rpress_page_id ) ) ? $rpress_page_id : rpress_account_page_id();
$rpress_sections = rpress_account_sections();
$rpress_current  = rpress_account_current_section();
$rpress_legacy   = isset( $rpress_sections[ $rpress_current ]['legacy_id'] ) ? $rpress_sections[ $rpress_current ]['legacy_id'] : 'user-' . $rpress_current;

do_action( 'rpress_user_dashboard_before' );
?>
<div class="user-dashboard-wrapper user-profile rpress-account rpress-account-<?php echo esc_attr( $rpress_current ); ?>" data-section="<?php echo esc_attr( $rpress_current ); ?>" data-section-urls="<?php echo esc_attr( wp_json_encode( rpress_account_legacy_hash_map( $rpress_page_id ) ) ); ?>">
	<div class="row">
		<div class="col-lg-3 col-12">
			<nav class="box-bg height-100" aria-label="<?php esc_attr_e( 'Account', 'restropress' ); ?>">
				<ul class="sidebar-menu">
					<?php
					rpress_account_sidebar( $rpress_current, $rpress_page_id );
					/**
					 * Extra sidebar items (3.5). Prefer the rpress_account_sections
					 * filter: each <li class="user-xyz"> printed here opens the panel
					 * with id "user-xyz" from rpress_user_dashboard_panels.
					 *
					 * @since 3.5
					 */
					do_action( 'rpress_user_dashboard_menu_items' );
					?>
					<li class="logout">
						<a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">
							<img alt="" src="<?php echo esc_url( RP_PLUGIN_URL . 'assets/images/user/logout.png' ); ?>" />
							<span><?php esc_html_e( 'Logout', 'restropress' ); ?></span>
						</a>
					</li>
				</ul>
			</nav>
		</div>
		<div class="col-lg-9 col-12 rpress-account-section" id="<?php echo esc_attr( $rpress_legacy ); ?>">
			<?php rpress_account_render_section( $rpress_current ); ?>
		</div>
		<?php
		/**
		 * Extra panels (3.5), one per rpress_user_dashboard_menu_items entry:
		 * <div class="col-lg-9 col-12" id="user-xyz" style="display:none;">.
		 *
		 * @since 3.5
		 */
		do_action( 'rpress_user_dashboard_panels' );
		?>
	</div>
</div>
<div class="modal micromodal-slide" id="rpressModal" aria-hidden="true">
	<div class="modal__overlay" tabindex="-1" data-micromodal-close>
		<div class="modal__container modal-content" role="dialog" aria-modal="true" aria-labelledby="rpressModal-title">
			<header class="modal__header modal-header">
				<h2 id="rpressModal-title" class="modal__title modal-title"></h2>
				<button class="modal__close" aria-label="<?php esc_attr_e( 'Close modal', 'restropress' ); ?>" data-micromodal-close></button>
			</header>
			<main class="modal__content modal-body">
			</main>
			<footer class="modal__footer modal-footer">
				<div class="rpress-popup-actions edit-rpress-popup-actions rp-col-md-12">
					<div class="rp-col-md-4 rp-col-xs-4 btn-count">
						<div class="rp-col-md-4 rp-col-sm-4 rp-col-xs-4">
							<input type="button" value="&#8722;" class="qtyminus qtyminus-style qtyminus-style-edit">
						</div>
						<div class="rp-col-md-4 rp-col-sm-4 rp-col-xs-4">
							<input type="text" name="quantity" value="1" class="qty qty-style" readonly>
						</div>
						<div class="rp-col-md-4 rp-col-sm-4 rp-col-xs-4">
							<input type="button" value="&#43;" class="qtyplus qtyplus-style qtyplus-style-edit">
						</div>
					</div>
					<div class="rp-col-md-8 rp-col-xs-8">
						<a href="javascript:void(0);" data-title="" data-item-qty="1" data-cart-key="" data-item-id="" data-variable-id="" data-item-price="" data-cart-action="" class="center submit-fooditem-button text-center inline rp-col-md-6">
							<span class="cart-action-text rp-ajax-toggle-text"></span>
							<span class="cart-item-price"></span>
						</a>
					</div>
				</div>
			</footer>
		</div>
	</div>
</div>
