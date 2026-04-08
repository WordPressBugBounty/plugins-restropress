<?php
/**
 * Abstract Rest Posts Controller Class
 */
if (!defined('ABSPATH')) {
	exit;
}

class RP_REST_Posts_Controller extends WP_REST_Posts_Controller
{
	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'rp/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = '';

	/**
	 * Post type.
	 *
	 * @var string
	 */
	protected $post_type = '';

	/**
	 * Controls visibility on frontend.
	 *
	 * @var string
	 */
	protected $public = false;

	public function __construct($post_type = '', $instance = '')
	{
		$this->post_type = $post_type;
		add_filter("rest_pre_insert_{$this->post_type}", array($instance, "{$this->post_type}_pre_insert"), 10, 2);
	}

	/**
	 * Check if a given request has access to read items.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check($request)
	{
		return $this->check_application_password_auth($request);
	}

	public function get_item_permissions_check($request)
	{
		return $this->check_application_password_auth($request);
	}

	public function update_item_permissions_check($request)
	{
		return $this->check_application_password_auth($request);
	}

	public function delete_item_permissions_check($request)
	{
		return $this->check_application_password_auth($request);
	}

	public function create_item_permissions_check($request)
	{
		return $this->check_application_password_auth($request);
	}

	/**
	 * Check authentication using WordPress Application Passwords
	 *
	 * @param WP_REST_Request $request
	 * @return boolean|WP_Error
	 */
	private function check_application_password_auth(WP_REST_Request $request)
	{
		// Check if user is already authenticated (e.g., via cookies for web users)
		if (is_user_logged_in() && current_user_can('manage_options')) {
			return parent::get_items_permissions_check($request);
		}
		return new WP_Error(
			'rest_forbidden',
			apply_filters('rp_api_auth_error_message', __('Authentication failed. Please check your credentials.', 'restropress')),
			array('status' => rest_authorization_required_code())
		);


	}

	public function dump_data($param)
	{
		echo '<pre>';
		print_r($param);
		echo '</pre>';
	}
}