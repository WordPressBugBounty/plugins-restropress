<?php
/**
 * Admin Options Page
 *
 * @package     RPRESS
 * @subpackage  Admin/Settings
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
*/
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Options Page
 *
 * Renders the options page contents.
 *
 * @since 1.0
 * @return void
 */
function rpress_options_page() {
	
	$settings_tabs = rpress_get_settings_tabs();
	$settings_tabs = empty($settings_tabs) ? array() : $settings_tabs;
	$active_tab    = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
	$active_tab    = array_key_exists( $active_tab, $settings_tabs ) ? $active_tab : 'general';
	$sections      = rpress_get_settings_tab_sections( $active_tab );
	$key           = 'main';
	if ( ! empty( $sections ) ) {
		$key = key( $sections );
	}
	$registered_sections = rpress_get_settings_tab_sections( $active_tab );
	$request_section     = isset( $_GET['section'] ) ? sanitize_text_field( $_GET['section'] ) : '';
	$request_section     = rpress_map_legacy_settings_section( $active_tab, $request_section );
	$section             = ! empty( $request_section ) && ! empty( $registered_sections ) && array_key_exists( $request_section, $registered_sections ) ? $request_section : $key;
	// Unset 'main' if it's empty and default to the first non-empty if it's the chosen section
	$all_settings = rpress_get_registered_settings();
	// Let's verify we have a 'main' section to show
	$has_main_settings = true;
	if ( empty( $all_settings[ $active_tab ]['main'] ) ) {
		$has_main_settings = false;
	}
	// Check for old non-sectioned settings (see #4211 and #5171)
	if ( ! $has_main_settings ) {
		foreach( $all_settings[ $active_tab ] as $sid => $stitle ) {
			if ( is_string( $sid ) && ! empty( $sections) && array_key_exists( $sid, $sections ) ) {
				continue;
			} else {
				$has_main_settings = true;
				break;
			}
		}
	}
	$override = false;
	if ( false === $has_main_settings ) {
		unset( $sections['main'] );
		if ( 'main' === $section ) {
			foreach ( $sections as $section_key => $section_title ) {
				if ( ! empty( $all_settings[ $active_tab ][ $section_key ] ) ) {
					$section  = $section_key;
					$override = true;
					break;
				}
			}
		}
	}
	ob_start();
	?>
	<div class="wrap rp-admin-scope rp-settings-page <?php echo 'wrap-' . esc_attr($active_tab); ?>">
		<div class="rp-page-header rp-settings-header">
			<div class="rp-page-header-titles">
				<h1 class="wp-heading-inline rp-page-title"><?php esc_html_e( 'RestroPress Settings', 'restropress' ); ?></h1>
				<p class="rp-page-subtitle"><?php esc_html_e( 'Configure restaurant operations, checkout, payments, emails, taxes, and integrations.', 'restropress' ); ?></p>
			</div>
		</div>
		<hr class="wp-header-end">
		<nav class="nav-tab-wrapper rp-tabs rp-tabs-flush-bottom" aria-label="<?php esc_attr_e( 'RestroPress settings tabs', 'restropress' ); ?>">
			<?php
			foreach ( rpress_get_settings_tabs() as $tab_id => $tab_name ) {
				$tab_url = add_query_arg( array(
					'settings-updated' => false,
					'tab'              => $tab_id,
				) );
				// Remove the section from the tabs so we always end up at the main section
				$tab_url = remove_query_arg( 'section', $tab_url );
				$active = $active_tab == $tab_id ? ' nav-tab-active' : '';
				echo '<a href="' . esc_url( $tab_url ) . '" class="nav-tab rp-tab' . esc_attr($active) . '">';
					echo esc_html( $tab_name );
				echo '</a>';
			}
			?>
		</nav>
		<?php
		$number_of_sections = count( $sections );
		$number = 0;
		if ( $number_of_sections > 1 ) {
			echo '<nav class="rp-settings-sections" aria-label="' . esc_attr__( 'Settings sections', 'restropress' ) . '"><ul class="subsubsub">';
			foreach( $sections as $section_id => $section_name ) {
				echo '<li>';
				$number++;
				$tab_url = add_query_arg( array(
					'settings-updated' => false,
					'tab' => $active_tab,
					'section' => $section_id
				) );
				$class = '';
				if ( $section == $section_id ) {
					$class = 'current';
				}
				echo '<a class="' . esc_attr($class) . '" href="' . esc_url( $tab_url ) . '">' . esc_html($section_name) . '</a>';
				if ( $number != $number_of_sections ) {
					echo ' | ';
				}
				echo '</li>';
			}
			echo '</ul></nav>';
		}
		?>
		<div id="tab_container" class="rp-card rp-settings-panel">
			<form method="post" action="options.php" class="rp-settings-form">
				<?php
				settings_fields( 'rpress_settings' );
				if ( 'main' === $section ) {
					do_action( 'rpress_settings_tab_top', $active_tab );
				}
				 do_action( 'rpress_settings_tab_top_' . $active_tab . '_' . $section );
				 do_settings_sections( 'rpress_settings_' . $active_tab . '_' . $section );
				 do_action( 'rpress_settings_tab_bottom_' . $active_tab . '_' . $section  );
				// For backwards compatibility
				if ( 'main' === $section ) {
					do_action( 'rpress_settings_tab_bottom', $active_tab );
				}
				// If the main section was empty and we overrode the view with the next subsection, prepare the section for saving
				if ( true === $override ) {
					?><input type="hidden" name="rpress_section_override" value="<?php echo esc_html( $section ); ?>" /><?php
				}
				?>
				<?php submit_button( null, 'primary rp-btn rp-btn-primary', 'submit', true ); ?>
			</form>
		</div><!-- #tab_container-->
	</div><!-- .wrap -->
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output buffer contains escaped settings page markup.
	echo ob_get_clean();
}

/**
 * Map old settings section slugs to the reorganized General sections.
 *
 * @since 3.3
 *
 * @param string $tab     Active settings tab.
 * @param string $section Requested settings section.
 * @return string
 */
function rpress_map_legacy_settings_section( $tab, $section ) {
	if ( 'general' !== $tab || empty( $section ) ) {
		return $section;
	}

	$legacy_sections = array(
		'main'               => 'store_setup',
		'api'                => 'developer_api',
		'currency'           => 'money_order_numbers',
		'accounting'         => 'money_order_numbers',
		'order_notification' => 'live_orders',
		'service_options'    => 'service_hours',
		'checkout_options'   => 'checkout',
		'print_receipts'     => 'printing',
	);

	return isset( $legacy_sections[ $section ] ) ? $legacy_sections[ $section ] : $section;
}
