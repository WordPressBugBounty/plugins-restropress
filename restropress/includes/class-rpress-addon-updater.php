<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Allows plugins to use their own update API.
 *
 * @author RestroPress
 * @version 1.6.19
 */
class RestroPress_Addon_Updater {
	private $api_url     = '';
	private $api_data    = array();
	private $name        = '';
	private $slug        = '';
	private $version     = '';
	private $wp_override = false;
	private $cache_key   = '';
	private $beta   = false;
	private $health_check_timeout = 5;
	/**
	 * Class constructor.
	 *
	 * @uses plugin_basename()
	 * @uses hook()
	 *
	 * @param string  $_api_url     The URL pointing to the custom API endpoint.
	 * @param string  $_plugin_file Path to the plugin file.
	 * @param array   $_api_data    Optional data to send with API calls.
	 */
	public function __construct( $_api_url, $_plugin_file, $_api_data = null ) {
		global $edd_plugin_data;
		$this->api_url     = trailingslashit( $_api_url );
		$this->api_data    = $_api_data;
		$this->name        = plugin_basename( $_plugin_file );
		$this->slug        = basename( $_plugin_file, '.php' );
		$this->version     = $_api_data['version'];
		$this->wp_override = isset( $_api_data['wp_override'] ) ? (bool) $_api_data['wp_override'] : false;
		$this->beta        = ! empty( $this->api_data['beta'] ) ? true : false;
		$this->cache_key   = 'rpress_sl_' . md5( serialize( $this->slug . $this->api_data['license'] . $this->beta ) );
		$edd_plugin_data[ $this->slug ] = $this->api_data;
		/**
		 * Fires after the $edd_plugin_data is setup.
		 *
		 * @since x.x.x
		 *
		 * @param array $edd_plugin_data Array of EDD SL plugin data.
		 */
		do_action( 'post_restropress_addon_updater_setup', $edd_plugin_data );
		// Set up hooks.
		$this->init();
	}
	/**
	 * Set up WordPress filters to hook into WP's update process.
	 *
	 * @uses add_filter()
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api_filter' ), 10, 3 );
		// remove_action( 'after_plugin_row_' . $this->name, 'wp_plugin_update_row', 10 );
		add_action( 'after_plugin_row', array( $this, 'show_update_notification' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'show_changelog' ) );
	}
	/**
	 * Check for Updates at the defined API endpoint and modify the update array.
	 *
	 * This function dives into the update API just when WordPress creates its update array,
	 * then adds a custom API call and injects the custom plugin data retrieved from the API.
	 * It is reassembled from parts of the native WordPress plugin update code.
	 * See wp-includes/update.php line 121 for the original wp_update_plugins() function.
	 *
	 * @uses api_request()
	 *
	 * @param array   $_transient_data Update array build by WordPress.
	 * @return array Modified update array with custom plugin data.
	 */
	public function check_update( $_transient_data ) {
		global $pagenow;
		if ( ! is_object( $_transient_data ) ) {
			$_transient_data = new stdClass;
		}
		if ( 'plugins.php' == $pagenow && is_multisite() ) {
			return $_transient_data;
		}
		if ( ! empty( $_transient_data->response ) && ! empty( $_transient_data->response[ $this->name ] ) && false === $this->wp_override ) {
			return $_transient_data;
		}
		$version_info = $this->get_cached_version_info();
		if ( false === $version_info ) {
			$textdomain = str_replace( '-', '_', $this->api_data['item_name']);
			$items = get_transient( 'restropress_add_ons_feed' );
			if( ! $items ) {
				$items = rpress_fetch_items();
			}
			$item_id = '';
			foreach ($items as $key => $item) {
				
				if ( $textdomain == $item->text_domain ) {
					$item_id = $item->id;
				}
			}
			$version_info = $this->api_request( 'plugin_latest_version', array( 'slug' => $this->slug, 'beta' => $this->beta, 'item_id' => $item_id, ) );
			$this->set_version_info_cache( $version_info );
		}
		if ( false !== $version_info && is_object( $version_info ) && isset( $version_info->new_version ) ) {
			if ( version_compare( $this->version, $version_info->new_version, '<' ) ) {
				$_transient_data->response[ $this->name ] = $version_info;
				// Make sure the plugin property is set to the plugin's name/location. See issue 1463 on Software Licensing's GitHub repo.
				$_transient_data->response[ $this->name ]->plugin = $this->name;
			}
			$_transient_data->last_checked           = time();
			$_transient_data->checked[ $this->name ] = $this->version;
		}
		return $_transient_data;
	}
	/**
	 * show update nofication row -- needed for multisite subsites, because WP won't tell you otherwise!
	 *
	 * @param string  $file
	 * @param array   $plugin
	 */
	public function show_update_notification( $file, $plugin ) {
		// Return early if in the network admin, or if this is not a multisite install.
		// if ( is_network_admin() || ! is_multisite() ) {
		// 	return;
		// }
		// Allow single site admins to see that an update is available.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( $this->name !== $file ) {
			return;
		}
		// Do not print any message if update does not exist.
		$update_cache = get_site_transient( 'update_plugins' );
		if ( ! isset( $update_cache->response[ $this->name ] ) ) {
			if ( ! is_object( $update_cache ) ) {
				$update_cache = new \stdClass();
			}
			$update_cache->response[ $this->name ] = $this->get_repo_api_data();
		}
		if ( empty( $update_cache->response[ $this->name ]->new_version ) ) {
			$update_cache->response[ $this->name ] = $this->get_repo_api_data();
		}
		// Return early if this plugin isn't in the transient->response or if the site is running the current or newer version of the plugin.
		if ( empty( $update_cache->response[ $this->name ] ) || version_compare( $this->version, $update_cache->response[ $this->name ]->new_version, '>=' ) ) {
			return;
		}
		printf(
			'<tr class="plugin-update-tr mgaddon-update %3$s" id="%1$s-update" data-slug="%1$s" data-plugin="%2$s">',
			esc_attr( $this->slug ),
    		esc_attr( $file ),
			in_array( $this->name, $this->get_active_plugins(), true ) ? 'active' : 'inactive'
		);
		echo '<td colspan="4" class="plugin-update colspanchange">';
		echo '<div class="update-message notice inline notice-warning notice-alt"><p>';
		$changelog_link = '';
		if ( ! empty( $update_cache->response[ $this->name ]->sections->changelog ) ) {
			$changelog_link = add_query_arg(
				array(
					'edd_sl_action' => 'view_plugin_changelog',
					'plugin'        => urlencode( $this->name ),
					'slug'          => urlencode( $this->slug ),
					'TB_iframe'     => 'true',
					'width'         => 77,
					'height'        => 911,
				),
				self_admin_url( 'index.php' )
			);
		}
		$update_link = add_query_arg(
			array(
				'action' => 'upgrade-plugin',
				'plugin' => urlencode( $this->name ),
			),
			self_admin_url( 'update.php' )
		);
		printf(
			/* translators: the plugin name. */
			esc_html__( 'There is a new version of %1$s available.', 'restropress' ),
			esc_html( $plugin['Name'] )
		);
		if ( ! current_user_can( 'update_plugins' ) ) {
			echo ' ';
			esc_html_e( 'Contact your network administrator to install the update.', 'restropress' );
		} elseif ( empty( $update_cache->response[ $this->name ]->package ) && ! empty( $changelog_link ) ) {
			echo ' ';
			printf(
				/* translators: 1. opening anchor tag, do not translate 2. the new plugin version 3. closing anchor tag, do not translate. */
				esc_html__( '%1$sView version %2$s details%3$s.', 'restropress' ),
				'<a target="_blank" class="thickbox open-plugin-details-modal" href="' . esc_url( $changelog_link ) . '">',
				esc_html( $update_cache->response[ $this->name ]->new_version ),
				'</a>'
			);
		} elseif ( ! empty( $changelog_link ) ) {
			echo ' ';
			printf(
				/* translators: 1. opening anchor tag, do not translate 2. the new plugin version 3. closing anchor tag, do not translate 4. opening anchor tag, do not translate 5. closing anchor tag, do not translate. */
				esc_html__( '%1$sView version %2$s details%3$s or %4$supdate now%5$s.', 'restropress' ),
				'<a target="_blank" class="thickbox open-plugin-details-modal" href="' . esc_url( $changelog_link ) . '">',
				esc_html( $update_cache->response[ $this->name ]->new_version ),
				'</a>',
				'<a target="_blank" class="update-link" href="' . esc_url( wp_nonce_url( $update_link, 'upgrade-plugin_' . $file ) ) . '">',
				'</a>'
			);
		} else {
			printf(
				' %1$s%2$s%3$s',
				'<a target="_blank" class="update-link" href="' . esc_url( wp_nonce_url( $update_link, 'upgrade-plugin_' . $file ) ) . '">',
				esc_html__( 'Update now.', 'restropress' ),
				'</a>'
			);
		}
		do_action( "in_plugin_update_message-{$file}", $plugin, $plugin ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		echo '</p></div></td></tr>';
	}
	/**
	 * Updates information on the "View version x.x details" page with custom data.
	 *
	 * @uses api_request()
	 *
	 * @param mixed   $_data
	 * @param string  $_action
	 * @param object  $_args
	 * @return object $_data
	 */
	public function plugins_api_filter( $_data, $_action = '', $_args = null ) {
		if ( $_action != 'plugin_information' ) {
			return $_data;
		}
		if ( ! isset( $_args->slug ) || ( $_args->slug != $this->slug ) ) {
			return $_data;
		}
		$to_send = array(
			'slug'   => $this->slug,
			'is_ssl' => is_ssl(),
			'fields' => array(
				'banners' => array(),
				'reviews' => false,
				'icons'   => array(),
			)
		);
		$cache_key = 'edd_api_request_' . md5( serialize( $this->slug . $this->api_data['license'] . $this->beta ) );
		// Get the transient where we store the api request for this plugin for 24 hours
		$edd_api_request_transient = $this->get_cached_version_info( $cache_key );
		//If we have no transient-saved value, run the API, set a fresh transient with the API value, and return that value too right now.
		if ( empty( $edd_api_request_transient ) ) {
			$api_response = $this->api_request( 'plugin_information', $to_send );
			// Expires in 3 hours
			$this->set_version_info_cache( $api_response, $cache_key );
			if ( false !== $api_response ) {
				$_data = $api_response;
			}
		} else {
			$_data = $edd_api_request_transient;
		}
		// Convert sections into an associative array, since we're getting an object, but Core expects an array.
		if ( isset( $_data->sections ) && ! is_array( $_data->sections ) ) {
			$_data->sections = $this->convert_object_to_array( $_data->sections );
		}
		// Convert banners into an associative array, since we're getting an object, but Core expects an array.
		if ( isset( $_data->banners ) && ! is_array( $_data->banners ) ) {
			$_data->banners = $this->convert_object_to_array( $_data->banners );
		}
		// Convert icons into an associative array, since we're getting an object, but Core expects an array.
		if ( isset( $_data->icons ) && ! is_array( $_data->icons ) ) {
			$_data->icons = $this->convert_object_to_array( $_data->icons );
		}
		// Convert contributors into an associative array, since we're getting an object, but Core expects an array.
		if ( isset( $_data->contributors ) && ! is_array( $_data->contributors ) ) {
			$_data->contributors = $this->convert_object_to_array( $_data->contributors );
		}
		if( ! isset( $_data->plugin ) ) {
			$_data->plugin = $this->name;
		}
		return $_data;
	}
	/**
	 * Convert some objects to arrays when injecting data into the update API
	 *
	 * Some data like sections, banners, and icons are expected to be an associative array, however due to the JSON
	 * decoding, they are objects. This method allows us to pass in the object and return an associative array.
	 *
	 * @since 3.6.5
	 *
	 * @param stdClass $data
	 *
	 * @return array
	 */
	private function convert_object_to_array( $data ) {
		$new_data = array();
		if ( !empty( $data ) && ( is_array( $data ) || is_object( $data ) ) ) {
			foreach ( $data as $key => $value ) {
				$new_data[ $key ] = is_object( $value ) ? $this->convert_object_to_array( $value ) : $value;
			}
		}
		return $new_data;
	}
	/**
	 * Disable SSL verification in order to prevent download update failures
	 *
	 * @param array   $args
	 * @param string  $url
	 * @return object $array
	 */
	public function http_request_args( $args, $url ) {
		$verify_ssl = $this->verify_ssl();
		if ( strpos( $url, 'https://' ) !== false && strpos( $url, 'edd_action=package_download' ) ) {
			$args['sslverify'] = $verify_ssl;
		}
		return $args;
	}
	/**
	 * Calls the API and, if successfull, returns the object delivered by the API.
	 *
	 * @uses get_bloginfo()
	 * @uses wp_remote_post()
	 * @uses is_wp_error()
	 *
	 * @param string  $_action The requested action.
	 * @param array   $_data   Parameters for the API action.
	 * @return false|object
	 */
	private function api_request( $_action, $_data ) {
		global $wp_version, $edd_plugin_url_available;
		$verify_ssl = $this->verify_ssl();
		// Do a quick status check on this domain if we haven't already checked it.
		$store_hash = md5( $this->api_url );
		if ( ! is_array( $edd_plugin_url_available ) || ! isset( $edd_plugin_url_available[ $store_hash ] ) ) {
			$test_url_parts = parse_url( $this->api_url );
			$scheme = ! empty( $test_url_parts['scheme'] ) ? $test_url_parts['scheme']     : 'http';
			$host   = ! empty( $test_url_parts['host'] )   ? $test_url_parts['host']       : '';
			$port   = ! empty( $test_url_parts['port'] )   ? ':' . $test_url_parts['port'] : '';
			if ( empty( $host ) ) {
				$edd_plugin_url_available[ $store_hash ] = false;
			} else {
				$test_url = $scheme . '://' . $host . $port;
				$response = wp_remote_get( $test_url, array( 'timeout' => $this->health_check_timeout, 'sslverify' => $verify_ssl ) );
				$edd_plugin_url_available[ $store_hash ] = is_wp_error( $response ) ? false : true;
			}
		}
		if ( false === $edd_plugin_url_available[ $store_hash ] ) {
			return;
		}
		$data = array_merge( $this->api_data, $_data );
		if ( $data['slug'] != $this->slug ) {
			return;
		}
		if( $this->api_url == trailingslashit ( home_url() ) ) {
			return false; // Don't allow a plugin to ping itself
		}
		$api_params = array(
			'edd_action' => 'get_version',
			'license'    => ! empty( $data['license'] ) ? $data['license'] : '',
			'item_name'  => isset( $data['item_name'] ) ? $data['item_name'] : false,
			'item_id'    => isset( $data['item_id'] ) ? $data['item_id'] : false,
			'version'    => isset( $data['version'] ) ? $data['version'] : false,
			'slug'       => $data['slug'],
			'author'     => $data['author'],
			'url'        => home_url(),
			'beta'       => ! empty( $data['beta'] ),
		);
		$request    = wp_remote_post( $this->api_url, array( 'timeout' => 15, 'sslverify' => $verify_ssl, 'body' => $api_params ) );
		if ( ! is_wp_error( $request ) ) {
			$request = json_decode( wp_remote_retrieve_body( $request ) );
		}
		if ( $request && isset( $request->sections ) ) {
			$request->sections = maybe_unserialize( $request->sections );
		} else {
			$request = false;
		}
		if ( $request && isset( $request->banners ) ) {
			$request->banners = maybe_unserialize( $request->banners );
		}
		if ( $request && isset( $request->icons ) ) {
			$request->icons = maybe_unserialize( $request->icons );
		}
		if( ! empty( $request->sections ) ) {
			foreach( $request->sections as $key => $section ) {
				$request->$key = (array) $section;
			}
		}
		return $request;
	}
	public function show_changelog() {
		// global $edd_plugin_data;
		if( empty( $_REQUEST['edd_sl_action'] ) || 'view_plugin_changelog' != $_REQUEST['edd_sl_action'] ) {
			return;
		}
		if( empty( $_REQUEST['plugin'] ) ) {
			return;
		}
		if( empty( $_REQUEST['slug'] ) ) {
			return;
		}
		if( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to install plugin updates', 'restropress' ), esc_html__( 'Error', 'restropress' ), array( 'response' => 403 ) );
		}
		$update_cache = get_site_transient( 'update_plugins' );
		$version_info = $update_cache->response[$_REQUEST['plugin']];
		if ( isset( $version_info->sections ) ) {
			$sections = $this->convert_object_to_array( $version_info->sections );
			if ( ! empty( $sections['changelog'] ) ) {
				echo '<div style="background:#fff;padding:10px;">' . wp_kses_post( $sections['changelog'] ) . '</div>';
			}
		}
		exit;
	}
	public function get_cached_version_info( $cache_key = '' ) {
		if( empty( $cache_key ) ) {
			$cache_key = $this->cache_key;
		}
		$cache = get_option( $cache_key );
		if( empty( $cache['timeout'] ) || time() > $cache['timeout'] ) {
			return false; // Cache is expired
		}
		// We need to turn the icons into an array, thanks to WP Core forcing these into an object at some point.
		$cache['value'] = json_decode( $cache['value'] );
		if ( ! empty( $cache['value']->icons ) ) {
			$cache['value']->icons = (array) $cache['value']->icons;
		}
		return $cache['value'];
	}
	public function set_version_info_cache( $value = '', $cache_key = '' ) {
		if( empty( $cache_key ) ) {
			$cache_key = $this->cache_key;
		}
		$data = array(
			'timeout' => strtotime( '+3 hours', time() ),
			'value'   => json_encode( $value )
		);
		update_option( $cache_key, $data, 'no' );
	}
	/**
	 * Returns if the SSL of the store should be verified.
	 *
	 * @since  1.6.13
	 * @return bool
	 */
	private function verify_ssl() {
		return (bool) apply_filters( 'edd_sl_api_request_verify_ssl', true, $this );
	}
	public function get_repo_api_data() {
		$version_info = $this->get_cached_version_info();
		$textdomain = str_replace( '-', '_', $this->api_data['item_name']);
		$items = get_transient( 'restropress_add_ons_feed' );
		if( ! $items ) {
			$items = rpress_fetch_items();
		}
		$item_id = '';
		foreach ($items as $key => $item) {
			
			if ( $textdomain == $item->text_domain ) {
				$item_id = $item->id;
			}
		}
		// $version_info = false;
		if ( false === $version_info || empty( $version_info->new_version ) ) {
			$version_info = $this->api_request(
				'plugin_latest_version',
				array(
					'slug' => $this->slug,
					'beta' => $this->beta,
					'item_id' => $item_id,
				)
			);
			if ( ! $version_info ) {
				return false;
			}
			// This is required for your plugin to support auto-updates in WordPress 5.5.
			$version_info->plugin = $this->name;
			$version_info->id     = $item_id;
			$version_info->tested = $this->get_tested_version( $version_info );
			$this->set_version_info_cache( $version_info );
		}
		return $version_info;
	}
	private function get_tested_version( $version_info ) {
		// There is no tested version.
		if ( empty( $version_info->tested ) ) {
			return null;
		}
		// Strip off extra version data so the result is x.y or x.y.z.
		list( $current_wp_version ) = explode( '-', get_bloginfo( 'version' ) );
		// The tested version is greater than or equal to the current WP version, no need to do anything.
		if ( version_compare( $version_info->tested, $current_wp_version, '>=' ) ) {
			return $version_info->tested;
		}
		$current_version_parts = explode( '.', $current_wp_version );
		$tested_parts          = explode( '.', $version_info->tested );
		// The current WordPress version is x.y.z, so update the tested version to match it.
		if ( isset( $current_version_parts[2] ) && $current_version_parts[0] === $tested_parts[0] && $current_version_parts[1] === $tested_parts[1] ) {
			$tested_parts[2] = $current_version_parts[2];
		}
		return implode( '.', $tested_parts );
	}
	private function get_active_plugins() {
		$active_plugins         = (array) get_option( 'active_plugins' );
		$active_network_plugins = (array) get_site_option( 'active_sitewide_plugins' );
		return array_merge( $active_plugins, array_keys( $active_network_plugins ) );
	}
}