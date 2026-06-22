<?php
/**
 * Admin Add-ons
 *
 * @package     RPRESS
 * @subpackage  Admin/Add-ons
 * @copyright   Copyright (c) 2019,
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Add-ons Page
 *
 * Renders the add-ons page content.
 *
 * @since 1.0
 * @return void
 */
function rpress_extensions_page() {
	ob_start(); ?>
	<div class="wrap rp-admin-scope rp-extensions-page" id="rpress-add-ons">
		<div class="rp-page-header">
			<div class="rp-page-header-main">
				<h1 class="wp-heading-inline rp-page-title">
					<span class="dashicons dashicons-admin-plugins rp-page-title-icon" aria-hidden="true"></span>
					<?php esc_html_e( 'RestroPress Extensions & Apps', 'restropress' ); ?>
				</h1>
				<p class="rp-page-subtitle">
					<?php esc_html_e( 'Browse, activate, and manage extensions that add ordering, restaurant operations, and growth tools to RestroPress.', 'restropress' ); ?>
				</p>
			</div>
		</div>
		<hr class="wp-header-end">
		<div class="rpress-plugin-filter-wrapper rp-table-toolbar rp-extensions-toolbar">
			<div class="rpress-plugin-filter rp-table-toolbar-primary">
				<div>
				<?php
					$base    = admin_url( 'admin.php?page=rpress-extensions' );
					$current = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
					echo sprintf( '<a class="rp-filter-chip%s" href="%s">%s</a>', $current === 'all' || $current === '' ? ' current is-active' : '', esc_url( remove_query_arg( 'status', $base ) ), esc_html__( 'All', 'restropress' ) );
					echo sprintf( '<a class="rp-filter-chip%s" href="%s">%s</a>', $current === 'active' ? ' current is-active' : '', esc_url( add_query_arg( 'status', 'active', $base ) ), esc_html__( 'Active', 'restropress' ) );
					echo sprintf( '<a class="rp-filter-chip%s" href="%s">%s</a>', $current === 'inactive' ? ' current is-active' : '', esc_url( add_query_arg( 'status', 'inactive', $base ) ), esc_html__( 'Inactive', 'restropress' ) );
					?>
				</div>
			</div>
			<div class="rpress-search-view-wrapper rp-table-toolbar-secondary">
				<label class="rpress-search-wrap rpress-live-search rp-filters-search" for="rpress-plugin-search">
					<span class="dashicons dashicons-search rp-filters-search-icon" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Search extensions', 'restropress' ); ?></span>
					<input id="rpress-plugin-search" class="rp-input" type="search" placeholder="<?php esc_attr_e( 'Search extensions', 'restropress' ); ?>">
				</label>
			</div>
		</div>
		<!-- RestroPress Addons Ends Here -->
		<div class="rpress-add-ons-view-wrapper">
			<?php rpress_add_ons_get_feed(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Function prints fully escaped admin markup. ?>
		</div>
	</div>
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output assembled with escaped values and trusted admin markup.
	echo ob_get_clean();
}
/**
 * Add-ons Get Feed
 *
 * Gets the add-ons page feed.
 *
 * @since 1.0
 * @return void
 */
function rpress_add_ons_get_feed() {
	$items = get_transient( 'restropress_add_ons_feed' );
	if ( ! $items ) {
		$items = rpress_fetch_items();
	}

	$data = '';
	$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'all';

	$filtered_items = array();

	if ( is_array( $items ) && ! empty( $items ) ) {
		// First pass: filter items based on active/inactive status
		foreach ( $items as $item ) {
			$class_name = trim( $item->class_name );
			if ( $status === 'active' && ! class_exists( $class_name ) ) {
				continue;
			} elseif ( $status === 'inactive' && class_exists( $class_name ) ) {
				continue;
			}
			$filtered_items[] = $item;
		}

		// Check if no filtered items exist
		if ( empty( $filtered_items ) ) {
			$data .= '<div class="no-addons-wrapper rp-empty-state">';
			$data .= '<span class="dashicons dashicons-admin-plugins rp-empty-state-icon" aria-hidden="true"></span>';
			$data .= '<h2 class="rp-empty-state-title">' . esc_html__( 'No extensions found', 'restropress' ) . '</h2>';
			$data .= '<p class="rp-empty-state-description">' . esc_html__( 'Try a different status filter or search term.', 'restropress' ) . '</p>';
			$data .= '</div>';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $data is assembled from escaped strings above.
			echo $data;
			return;
		}


		// Proceed to render filtered addons.
		echo '<div class="restropress-addons-all rp-grid rp-extensions-grid">';
		foreach ( $filtered_items as $key => $item ) {
			$class = 'inactive';
			$class_name = trim( $item->class_name );

			if ( class_exists( $class_name ) ) {
				$class = 'installed';
			}

			$updated_class = '';
			$deactive_class = 'hide';
			if ( get_option( $item->text_domain . '_license_status' ) == 'valid' ) {
				$updated_class = 'rpress-updated';
				$deactive_class = 'show';
			}

			$item_link = isset( $item->link ) ? esc_url( $item->link ) : '';
			$status_badge_class = class_exists( $class_name ) ? 'is-success' : 'is-info';
			$status_label       = class_exists( $class_name ) ? __( 'Installed', 'restropress' ) : __( 'Available', 'restropress' );

			ob_start();
			?>
			<div class="restropress-addon-item rp-card rp-extension-card <?php echo esc_attr( $class ); ?>">
				<!-- Addons Inner Wrap Starts Here -->
				<div class="rp-addin-item-inner-wrap rp-extension-card-inner">
					<div class="rp-extension-card-header">
						<div class="rp-extension-card-title-wrap">
							<h3 class="rpress-addon-title rp-extension-title"><?php echo esc_html( $item->title ); ?></h3>
							<span class="rp-status-badge <?php echo esc_attr( $status_badge_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
						</div>
					</div>
					<!-- Addons Image Starts Here -->
					<div class="restropress-addon-img-wrap rp-extension-image">
						<img alt="<?php echo esc_attr( $item->title ); ?>" src="<?php echo esc_url( $item->product_image ); ?>">
					</div>
					<div class="rp-addon-main-wrap rp-extension-card-body">
						<!-- Addons Image Ends Here -->
						<div class="rp-addon-info rp-extension-description">
							<span><?php echo esc_html( $item->short_content ); ?></span>
						</div>
						<div class="rpress-purchased-wrap rp-extension-card-footer">
							<div class="rpress-license-wrapper rp-extension-license-form <?php echo esc_attr( $updated_class ); ?>">
								<input type="hidden" class="rpress_license_string" name="rpress_license" value="<?php echo esc_attr( $item->text_domain . '_license' ); ?>">
								<input type="text" data-license-key="" placeholder="<?php esc_attr_e( 'Enter license key', 'restropress' ); ?>" data-item-name="<?php echo esc_attr( $item->title ); ?>" data-item-id="<?php echo esc_attr( $item->id ); ?>" class="rpress-license-field pull-left rp-input" name="rpress-license">
								<button data-action="rpress_activate_addon_license" class="button button-medium button-primary pull-right rpress-validate-license rp-btn rp-btn-primary"><?php esc_html_e( 'Activate License', 'restropress' ); ?></button>
								<div class="clear"></div>

								<!--activated-->
								<!-- <div class="card-footer d-flex justify-content-between align-items-center mt-3">
									<span class="status-text text-green">Activated</span>
									<label class="switch">
										<input type="checkbox" checked>
										<span class="slider round"></span>
									</label>
								</div>						 -->

							</div><!-- .rpress-license-wrapper-->
							<!-- License Deactivate Starts Here -->
							<div class="clear"></div>
							<div class="rpress-license-deactivate-wrapper <?php echo esc_attr( $deactive_class ); ?>">
								<div class="rp-license-deactivate-inner">
									<!--deactivate license-->
									<div class="card-footer d-flex justify-content-between align-items-center mt-3 rp-extension-license-status">
										<span class="status-text text-green rp-status-badge is-success"><?php esc_html_e( 'License Activated', 'restropress' ); ?></span>
										<label class="switch rp-toggle">
											<input type="checkbox" data-action="rpress_deactivate_addon_license" class="pull-left rpress-deactivate-license" <?php checked( get_option( $item->text_domain . '_license_status' ), 'valid' ); ?>>
											<span class="slider round"></span>
										</label>
									</div>	

								</div>
							</div>
							<div class="rpress-license-default-wrapper <?php echo esc_attr( $deactive_class ); ?>">
								<div class="restropress-btn-group rpress-addon-details-section pull-left rp-extension-actions">
								<a class="button button-medium button-secondary rp-btn rp-btn-secondary" target="_blank" href="<?php echo esc_url( add_query_arg( array(
									'utm_source'   => 'plugin',
									'utm_medium'   => 'addon_page',
									'utm_campaign' => 'promote_addon',
								), $item_link ) ); ?>" ><?php esc_html_e( 'View Details', 'restropress' ); ?></a>
								<small class="rpress-addon-item-pricing rp-extension-price">
									<?php echo esc_html__( 'From ', 'restropress' ) . wp_kses_post( rpress_currency_filter( rpress_format_amount( $item->price_range ) ) ); ?>
								</small>
								</div>
							</div>
							<!-- License Deactiave Ends Here -->
						</div>
					</div>
				</div>
				<!-- Addons Inner Wrap Ends Here -->
			</div>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Card markup is assembled with escaped item data.
			echo ob_get_clean();
		}
		echo '</div>';
	} else { ?>
		<div class="restropress-addons-all rp-empty-state">
			<span class="dashicons dashicons-warning rp-empty-state-icon" aria-hidden="true"></span>
			<h2 class="rp-empty-state-title"><?php esc_html_e( 'Extensions could not be loaded', 'restropress' ); ?></h2>
			<p class="rp-empty-state-description"><?php esc_html_e( 'Please try again in a few minutes.', 'restropress' ); ?></p>
		</div>
	<?php }
}
function rpress_fetch_items() {
	$url = 'https://www.restropress.com/wp-json/restropress-server/';
	$version = '1.0';
	$remote_url = $url . 'v' . $version;
	$verify_ssl = (bool) apply_filters( 'rpress_remote_request_verify_ssl', true );
	$feed = wp_remote_get( esc_url_raw( $remote_url ), array( 'sslverify' => $verify_ssl ) );
	$items = array();
	if ( ! is_wp_error( $feed ) ) {
		if ( isset( $feed['body'] ) && strlen( $feed['body'] ) > 0 ) {
			$items = wp_remote_retrieve_body( $feed );
			$items = json_decode($items);
			set_transient( 'restropress_add_ons_feed', $items, 3600 );
		}
	} else {
		$items = '<div class="error"><p>' . esc_html__( 'There was an error retrieving the extensions list from the server. Please try again later.', 'restropress' ) . '</div>';
	}
	return $items;
}
