<?php
/**
 * RestroPress API for creating Email template tags
 *
 * Email tags are wrapped in { }
 *
 * A few examples:
 *
 * {fooditem_list}
 * {name}
 * {sitename}
 *
 *
 * To replace tags in content, use: rpress_do_email_tags( $content, payment_id );
 *
 * To add tags, use: rpress_add_email_tag( $tag, $description, $func ). Be sure to wrap rpress_add_email_tag()
 * in a function hooked to the 'rpress_email_tags' action
 *
 * @package     RPRESS
 * @subpackage  Emails
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since  1.0.0
 * @author      RestroPress
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
class RPRESS_Email_Template_Tags {
	/**
	 * Container for storing all tags
	 *
	 * @since  1.0.0
	 */
	private $tags;
	/**
	 * Payment ID
	 *
	 * @since  1.0.0
	 */
	private $payment_id;
	/**
	 * Add an email tag
	 *
	 * @since  1.0.0
	 *
	 * @param string   $tag  Email tag to be replace in email
	 * @param callable $func Hook to run when email tag is found
	 */
	public function add( $tag, $description, $func ) {
		if ( is_callable( $func ) ) {
			$this->tags[$tag] = array(
				'tag'         => $tag,
				'description' => $description,
				'func'        => $func
			);
		}
	}
	/**
	 * Remove an email tag
	 *
	 * @since  1.0.0
	 *
	 * @param string $tag Email tag to remove hook from
	 */
	public function remove( $tag ) {
		unset( $this->tags[$tag] );
	}
	/**
	 * Check if $tag is a registered email tag
	 *
	 * @since  1.0.0
	 *
	 * @param string $tag Email tag that will be searched
	 *
	 * @return bool
	 */
	public function email_tag_exists( $tag ) {
		return array_key_exists( $tag, $this->tags );
	}
	/**
	 * Returns a list of all email tags
	 *
	 * @since  1.0.0
	 *
	 * @return array
	 */
	public function get_tags() {
		return $this->tags;
	}
	/**
	 * Search content for email tags and filter email tags through their hooks
	 *
	 * @param string $content Content to search for email tags
	 * @param int $payment_id The payment id
	 *
	 * @since  1.0.0
	 *
	 * @return string Content with email tags filtered out.
	 */
	public function do_tags( $content, $payment_id ) {
		// Check if there is atleast one tag added
		if ( empty( $this->tags ) || ! is_array( $this->tags ) ) {
			return $content;
		}
		$this->payment_id = $payment_id;
		$new_content = preg_replace_callback( "/{([A-z0-9\-\_]+)}/s", array( $this, 'do_tag' ), $content );
		$this->payment_id = null;
		return $new_content;
	}
	/**
	 * Do a specific tag, this function should not be used. Please use rpress_do_email_tags instead.
	 *
	 * @since  1.0.0
	 *
	 * @param $m message
	 *
	 * @return mixed
	 */
	public function do_tag( $m ) {
		// Get tag
		$tag = $m[1];
		// Return tag if tag not set
		if ( ! $this->email_tag_exists( $tag ) ) {
			return $m[0];
		}
		return call_user_func( $this->tags[$tag]['func'], $this->payment_id, $tag );
	}
}
