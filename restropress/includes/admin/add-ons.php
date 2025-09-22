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
	<div class="wrap" id="rpress-add-ons">
		<hr class="wp-header-end">
		<!-- RestroPress Addons Starts Here-->
		<div class="rpress-about-body">
			<div class="page-header-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="35" height="35" viewBox="0 0 45.521 45.544">
                    <g id="plugin_1_" data-name="plugin (1)" transform="translate(-0.6 511.659)">
                        <path id="Path_386" data-name="Path 386" d="M26.5-511.565a5.417,5.417,0,0,0-2.956,1.692,5.055,5.055,0,0,0-1.246,2.066,6.836,6.836,0,0,0-.276,2.68l-.036,1.977-5.93.027c-5.787.027-5.947.036-6.25.2a1.83,1.83,0,0,0-.49.49c-.169.3-.178.463-.2,6.25l-.027,5.93-1.977.036c-1.932.036-1.994.036-2.733.3a5.263,5.263,0,0,0-2.288,1.5A5.055,5.055,0,0,0,.84-486.351a4.17,4.17,0,0,0-.24,1.772,3.947,3.947,0,0,0,.231,1.718A5.532,5.532,0,0,0,3.689-479.6a7.789,7.789,0,0,0,1.291.49,19.4,19.4,0,0,0,2.288.1H9.085l.027,6c.027,5.9.027,6.01.214,6.25a2.427,2.427,0,0,0,.427.427c.24.187.338.187,7.051.214,6.686.018,6.811.018,7.158-.16a1.193,1.193,0,0,0,.534-.534c.169-.321.178-.516.178-2.778a15.915,15.915,0,0,1,.125-2.894,2.928,2.928,0,0,1,4.9-1.309,3.636,3.636,0,0,1,.588.8c.223.427.223.472.267,3.214.045,2.626.053,2.787.231,3.018a2.427,2.427,0,0,0,.427.427c.24.187.338.187,7.051.214,6.686.018,6.811.018,7.158-.16a1.193,1.193,0,0,0,.534-.534c.178-.347.178-.472.16-7.158-.027-6.713-.027-6.811-.214-7.051a2.427,2.427,0,0,0-.427-.427c-.231-.178-.392-.187-3.018-.231-2.742-.045-2.787-.045-3.214-.267a2.975,2.975,0,0,1-1.478-3.686,3.369,3.369,0,0,1,1.211-1.487c.614-.374,1.1-.436,3.668-.436,2.261,0,2.457-.009,2.778-.178a1.193,1.193,0,0,0,.534-.534c.178-.347.178-.472.16-7.158-.027-6.713-.027-6.811-.214-7.051a2.427,2.427,0,0,0-.427-.427c-.24-.187-.347-.187-6.25-.214l-6-.027v-1.816a19.4,19.4,0,0,0-.1-2.288,7.788,7.788,0,0,0-.49-1.291,5.462,5.462,0,0,0-4.754-3.107A5.739,5.739,0,0,0,26.5-511.565Zm1.887,2.671a2.939,2.939,0,0,1,1.914,1.611c.2.427.2.561.249,3.161s.053,2.733.24,3.027c.41.659.027.623,6.7.623H43.46v10.221l-2.021.036a6.752,6.752,0,0,0-2.68.267A5.532,5.532,0,0,0,35.5-487.09a4.861,4.861,0,0,0-.588,2.5,5.359,5.359,0,0,0,1.638,3.935,5.306,5.306,0,0,0,2.5,1.46,9.231,9.231,0,0,0,2.555.178H43.46v10.239H33.221v-1.852a9.231,9.231,0,0,0-.178-2.555,5.306,5.306,0,0,0-1.46-2.5,5.359,5.359,0,0,0-3.935-1.638,4.862,4.862,0,0,0-2.5.588,5.532,5.532,0,0,0-2.858,3.259,6.726,6.726,0,0,0-.267,2.671l-.036,2.03H11.765v-5.974c0-6.668.036-6.286-.623-6.7-.294-.187-.427-.2-3.027-.24s-2.733-.053-3.161-.249a2.925,2.925,0,0,1-1.442-3.882,2.727,2.727,0,0,1,1.38-1.407l.588-.294,2.724-.045c2.974-.045,3-.053,3.356-.6.151-.223.16-.7.2-6.259l.045-6.01,6.027-.044c5.921-.045,6.027-.045,6.277-.232.534-.392.561-.543.561-3.25,0-2.724.045-3.018.561-3.793a3.066,3.066,0,0,1,2.333-1.264A3.176,3.176,0,0,1,28.387-508.894Z" transform="translate(0)"></path>
                    </g>
                </svg>
            </div>
			<div>
				<h2>
					<?php esc_html_e( 'Extensions', 'restropress' ); ?>
				</h2>
				<div class="about-text"><?php esc_html_e('Improve your workspace.', 'restropress');?></div>
			</div>
		</div>
		<div class="rpress-plugin-filter-wrapper d-flex">
			<div class="rpress-plugin-filter">
				<div>
				<?php 
					$base  = admin_url('admin.php?page=rpress-extensions'); 
					$current        = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ): '';
					echo sprintf( '<a href="%s"%s>%s</a>', remove_query_arg( 'status', $base ), $current === 'all' || $current == '' ? ' class="current"' : '', esc_html__('All', 'restropress') );
					echo sprintf( '<a href="%s"%s>%s</a>', add_query_arg( 'status', 'active', $base ), $current === 'active' ? ' class="current"' : '', esc_html__('active', 'restropress')  );
					echo sprintf( '<a href="%s"%s>%s</a>', add_query_arg( 'status', 'inactive', $base ), $current === 'inactive' ? ' class="current"' : '', esc_html__('Inactive', 'restropress')  );
					?> 
				</div>
			</div>
			<div class="rpress-search-view-wrapper">
				<div class="rpress-search-wrap rpress-live-search">
				<svg class="rpress-search_icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
				<path fill-rule="evenodd" d="M2.75 11a8.25 8.25 0 1116.5 0 8.25 8.25 0 01-16.5 0zM11 1.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75a9.712 9.712 0 006.344-2.346l3.126 3.126a.75.75 0 101.06-1.06l-3.126-3.126A9.712 9.712 0 0020.75 11c0-5.385-4.365-9.75-9.75-9.75z"></path>
				</svg>
				<input id="rpress-plugin-search" type="text" placeholder="<?php esc_html_e( 'Search', 'restropress' ); ?>">
				</div>			 	    
			</div>
		</div>
		<!-- RestroPress Addons Ends Here -->
		<div class="rpress-add-ons-view-wrapper">
			<?php echo rpress_add_ons_get_feed(); ?>
		</div>
	</div>
	<?php
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
	$status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'all';

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
			$image_url = RP_PLUGIN_URL . 'assets/images/not-active.png'; // Adjust path to match your plugin structure
			$data .= '<div class="no-addons-wrapper">';
			$data .= '<img src="' . esc_url( $image_url ) . '" alt="No Addons" style="max-width:150px; margin-bottom: 10px;" />';
			$data .= '<p class="no-addons-message">No ' . esc_html( ucfirst( $status ) ) . ' addons found.</p>';
			$data .= '</div>';
			echo $data;
			return;
		}


		// Proceed to render filtered addons
		$data = '<div class="restropress-addons-all">';
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
			$slug = '';
			if ( ! empty( $item_link ) ) {
				$slug = basename( parse_url( $item_link, PHP_URL_PATH ) );
			}

			ob_start();
			?>
			<div class="rp-col-xs-12 rp-col-sm-4 rp-col-md-4 rp-col-lg-4 restropress-addon-item <?php echo esc_attr( $class ); ?>">
				<!-- Addons Inner Wrap Starts Here -->
				<div class="rp-addin-item-inner-wrap">
					<h3 class="rpress-addon-title" ><?php echo esc_html( $item->title ); ?></h3>
					<!--<a href="#" class="d-flex align-items-center addon-link-wrap">
                        <span class="me-2"><?php //echo esc_html($slug); ?></span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 17.28 17.265">
                            <g id="maximize" transform="translate(-0.123 511.817)">
                                <path id="Path_391" data-name="Path 391" d="M.862-511.727a1.08,1.08,0,0,0-.689.706c-.051.149-.054.885-.047,7.948l.01,7.783.084.169a1.189,1.189,0,0,0,.523.523l.169.084h15.7l.169-.084a1.189,1.189,0,0,0,.523-.523l.084-.169v-8.138l-.084-.169a1.189,1.189,0,0,0-.523-.523.851.851,0,0,0-.456-.084.851.851,0,0,0-.456.084,1.189,1.189,0,0,0-.523.523l-.084.169-.01,3.383-.007,3.387H2.28v-12.966l3.387-.007,3.383-.01.169-.084a1.206,1.206,0,0,0,.523-.523,1.25,1.25,0,0,0,0-.912,1.206,1.206,0,0,0-.523-.523l-.169-.084-4.018-.007A36.925,36.925,0,0,0,.862-511.727Z" transform="translate(0 -0.039)" fill="#9CA3AD"></path>
                                <path id="Path_392" data-name="Path 392" d="M244.283-511.763a1.077,1.077,0,0,0-.409,1.773,1.024,1.024,0,0,0,.851.321l.368.014L242.44-507a36.894,36.894,0,0,0-2.745,2.84.817.817,0,0,0-.095.473.956.956,0,0,0,.3.763.943.943,0,0,0,.743.3c.513,0,.287.2,3.3-2.809l2.691-2.684.013.365a1.034,1.034,0,0,0,.321.851,1.067,1.067,0,0,0,1.587-.088c.25-.307.25-.3.24-2.293l-.01-1.749-.091-.172a.983.983,0,0,0-.473-.49l-.213-.115-1.773-.007A8.442,8.442,0,0,0,244.283-511.763Z" transform="translate(-231.391)" fill="#9CA3AD"></path>
                            </g>
                        </svg>
                    </a>-->
					<!-- Addons Image Starts Here -->
					<div class="restropress-addon-img-wrap">
						<img alt="<?php echo esc_attr( $item->title ); ?>" src="<?php echo esc_url( $item->product_image ); ?>">
					</div>
					<div class="rp-addon-main-wrap">
						<!-- Addons Image Ends Here -->
						<div class="rp-addon-info">
							<span><?php echo esc_html( $item->short_content ); ?></span>
						</div>
						<div class="rpress-purchased-wrap">
							<div class="rpress-license-wrapper <?php echo esc_attr( $updated_class ); ?>">
								<input type="hidden" class="rpress_license_string" name="rpress_license" value="<?php echo esc_attr( $item->text_domain . '_license' ); ?>">
								<input type="text" data-license-key="" placeholder="<?php esc_html_e('Enter your license key here'); ?>" data-item-name="<?php echo esc_attr( $item->title ); ?>" data-item-id="<?php echo esc_attr( $item->id ); ?>" class="rpress-license-field pull-left" name="rpress-license">
								<button data-action="rpress_activate_addon_license" class="button button-medium button-primary pull-right rpress-validate-license"><?php esc_html_e('Activate License', 'restropress'); ?></button>
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
									<!-- <button data-action="rpress_deactivate_addon_license" class="button  pull-left rpress-deactivate-license"><?php // esc_html_e('Deactivate', 'restropress'); ?></button>
									<small class="rpress-addon-item-pricing"><?php // esc_html_e('From ', 'restropress') . rpress_currency_filter( rpress_format_amount( $item->price_range ) ); ?></small> -->
								

									<!--de-activated-->
									<div class="card-footer d-flex justify-content-between align-items-center mt-3">
										<span class="status-text text-green">License Activated</span>
										<label class="switch">
											<input type="checkbox" data-action="rpress_deactivate_addon_license" class="pull-left rpress-deactivate-license" <?php checked( get_option( $item->text_domain . '_license_status' ), 'valid' ); ?>>
											<span class="slider round"></span>
										</label>
									</div>	

								</div>
							</div>
							<div class="rpress-license-default-wrapper <?php echo esc_attr( $deactive_class ); ?>">
								<div class="restropress-btn-group rpress-addon-details-section pull-left">
								<a class="button button-medium button-primary " target="_blank" href="<?php echo esc_attr( $item_link . '?utm_source=plugin&utm_medium=addon_page&utm_campaign=promote_addon' ); ?>" ><?php esc_html_e('View Details', 'restropress')?></a>
								<small class="rpress-addon-item-pricing">
									<?php echo esc_html__('From ', 'restropress') . rpress_currency_filter( rpress_format_amount( $item->price_range ) ); ?>
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
		}
	} else { ?>
		<div class="restropress-addons-all">
			<span><?php esc_html_e( 'Something went wrong. Please try after sometime..', 'restropress' ); ?>
			</span>
		</div>
	<?php }
	echo ob_get_clean();
}
function rpress_fetch_items() {
	$url = 'https://www.restropress.com/wp-json/restropress-server/';
	$version = '1.0';
	$remote_url = $url . 'v' . $version;
	$feed = wp_remote_get( esc_url_raw( $remote_url ), array( 'sslverify' => false ) );
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