<?php
/**
 * License handler for RestroPress
 *
 * This class should simplify the process of adding license information
 * to new and existing RestroPress extensions.
 *
 * @version 1.1
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'RestroPress_License' ) ) :
/**
 * RestroPress_License Class
 */
class RestroPress_License {
	private $file;
	private $license;
	private $item_name;
	private $item_id;
	private $item_shortname;
	private $version;
	private $author;
	private $api_url = 'https://www.restropress.com';
	private $updater = null;
	/**
	 * Class constructor
	 *
	 * @param string  $_file
	 * @param string  $_item_name
	 * @param string  $_version
	 * @param string  $_author
	 * @param string  $_optname
	 * @param string  $_api_url
	 * @param int     $_item_id
	 */
	function __construct( $_file, $_item_name, $_version, $_author, $_optname = null, $_api_url = null, $_item_id = null ) {
		$this->file = $_file;
		$this->item_name = $_item_name;
		if ( is_numeric( $_item_id ) ) {
			$this->item_id = absint( $_item_id );
		}
		$this->item_shortname = $_optname;
		$this->version        = $_version;
		$this->license        = trim( get_option( $this->item_shortname, '' ) );
		$this->author         = $_author;
		$this->api_url        = is_null( $_api_url ) ? $this->api_url : $_api_url;
		// Setup hooks
		$this->includes();
		$this->hooks();
	}
	/**
	 * Include the updater class
	 *
	 * @access  private
	 * @return  void
	 */
	private function includes() {
		if ( ! class_exists( 'RestroPress_Addon_Updater' ) )  {
			require_once 'class-rpress-addon-updater.php';
		}
	}
	/**
	 * Setup hooks
	 *
	 * @access  private
	 * @return  void
	 */
	private function hooks() {
		// Register immediately so WordPress cron, AJAX, and admin update checks can see extension updates.
		$this->auto_updater();
	}
	/**
	 * Auto updater
	 *
	 * @access  private
	 * @return  void
	 */
	public function auto_updater() {
		if ( $this->updater instanceof RestroPress_Addon_Updater ) {
			return $this->updater;
		}

		$args = array(
			'version'   => $this->version,
			'license'   => $this->license,
			'author'    => $this->author,
		);
		if( ! empty( $this->item_id ) ) {
			$args['item_id']   = $this->item_id;
		} else {
			$args['item_name'] = $this->item_name;
		}
		// Setup the updater
		$this->updater = new RestroPress_Addon_Updater(
			$this->api_url,
			$this->file,
			$args
		);

		return $this->updater;
	}
}
endif; // end class_exists check
