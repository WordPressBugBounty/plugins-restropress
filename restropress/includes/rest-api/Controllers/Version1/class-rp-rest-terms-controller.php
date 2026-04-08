<?php
class RP_REST_Terms_Controller extends WP_REST_Terms_Controller {
    
    public function __construct( $taxonomy ) {
        parent::__construct( $taxonomy );
    }
    
    /**
     * Get terms permission | Get 10 terms at a time
     * @param WP_REST_Request $request
     * @return WP_Error | bool
     * @since 3.0.0
     */
    public function get_items_permissions_check( $request ) {
        return $this->check_with_parent_permission( $request, __FUNCTION__ );
    }
    
    /**
     * Get term permission | Get single terms at a time
     * @param WP_REST_Request $request
     * @return WP_Error | bool
     * @since 3.0.0
     */
    public function get_item_permissions_check( $request ) {
        return $this->check_with_parent_permission( $request, __FUNCTION__ );
    }
    
    /**
     * Create term permission 
     * @param WP_REST_Request $request
     * @since 3.0.0
     * @return bool| WP_Error
     */
    public function create_item_permissions_check( $request ) {
        return $this->check_with_parent_permission( $request, __FUNCTION__ );
    }
    
    /**
     * Update term permission 
     * @param WP_REST_Request $request
     * @since 3.0.0
     * @return bool| WP_Error
     */
    public function update_item_permissions_check( $request ) {
        return $this->check_with_parent_permission( $request, __FUNCTION__ );
    }
    
    /**
     * Delete term permission 
     * @param WP_REST_Request $request
     * @since 3.0.0
     * @return bool| WP_Error
     */
    public function delete_item_permissions_check( $request ) {
        return $this->check_with_parent_permission( $request, __FUNCTION__ );
    }
    
    /**
     * Check authentication with Application Passwords and parent permission
     * 
     * @param WP_REST_Request $request | Rest request
     * @param string $function_name | Method name
     * @return boolean or WP_Error
     * @since 3.0.0
     */
    private function check_with_parent_permission( WP_REST_Request $request, string $function_name ) {
        // First, check Application Password authentication
        $auth_result = $this->check_application_password_auth($request);
        
        if ( is_wp_error( $auth_result ) ) {
            return $auth_result;
        }
        
        // If authentication passed, check parent permissions
        return parent::$function_name( $request );
    }
    
    /**
     * Check authentication using WordPress Application Passwords
     *
     * @return boolean|WP_Error
     */
    private function check_application_password_auth(WP_REST_Request $request) {
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
}