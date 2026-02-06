<?php
/**
 * RP_REST_Others_V1_Controller
 */
class RP_REST_Others_V1_Controller extends WP_REST_Controller {
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
	protected $rest_base = 'others';
	
	/**
	 * Registering Route
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/statuses',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rpress_order_status_callback' ),
					'permission_callback' => array( $this, 'get_permissions_check' ),
					'args'                => array(),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/services',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rpress_services_callback' ),
					'permission_callback' => array( $this, 'get_permissions_check' ),
					'args'                => array(),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/tax',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rpress_tax_callback' ),
					'permission_callback' => array( $this, 'get_permissions_check' ),
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * Permission checking for get request using Application Passwords
	 *
	 * @param WP_REST_Request $request 
	 * @since 3.0.0
	 * @return bool | WP_Error 
	 */
	public function get_permissions_check( WP_REST_Request $request ) {
		return $this->check_application_password_auth( $request );
	}

	/**
	 * Check authentication using WordPress Application Passwords
	 *
	 * @param WP_REST_Request $request
	 * @return boolean|WP_Error
	 */
	private function check_application_password_auth( WP_REST_Request $request ) {
		// Check if user is already authenticated (e.g., via cookies for web users)
		if (is_user_logged_in() && current_user_can('manage_options')) {
			return true;
		}
		return new WP_Error(
			'rest_forbidden',
			apply_filters('rp_api_auth_error_message', __('Authentication failed. Please check your credentials.', 'restropress')),
			array('status' => rest_authorization_required_code())
		);
	}

	/**
	 * RestroPress Order Status list callback.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rpress_order_status_callback( WP_REST_Request $request ) {
		$payment_statuses = rpress_get_payment_statuses();
		
		if ( function_exists( 'rpress_get_payment_status_colors' ) ) {
			$payment_color_codes = rpress_get_payment_status_colors();
		} else {
			$payment_color_codes = array(
				'pending'         => '#fcbdbd',
				'pending_text'    => '#333333',
				'publish'         => '#e0f0d7',
				'publish_text'    => '#3a773a',
				'refunded'        => '#e5e5e5',
				'refunded_text'   => '#777777',
				'failed'          => '#e76450',
				'failed_text'     => '#ffffff',
				'processing'      => '#f7ae18',
				'processing_text' => '#ffffff',
			);
		}
		
		$statuses = rpress_get_order_statuses();
		
		if ( function_exists( 'rpress_get_order_status_colors' ) ) {
			$color_codes = rpress_get_order_status_colors();
		} else {
			$color_codes = array(
				'pending'    => '#800000',
				'accepted'   => '#008000',
				'processing' => '#808000',
				'ready'      => '#00FF00',
				'transit'    => '#800080',
				'cancelled'  => '#FF0000',
				'completed'  => '#FFFF00',
			);
		}
		
		$response_array = array(
			'statuses'         => $statuses,
			'status_colors'    => $color_codes,
			'payment_statuses' => $payment_statuses,
			'payment_colors'   => $payment_color_codes,
		);
		
		$response = new WP_REST_Response( $response_array );
		$response->set_status( 200 );
		return $response;
	}

	/**
	 * RestroPress services list callback.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rpress_services_callback( WP_REST_Request $request ) {
		$statuses = rpress_get_service_types();
		$response_array = array(
			'services' => $statuses,
		);
		
		$response = new WP_REST_Response( $response_array );
		$response->set_status( 200 );
		return $response;
	}

	/**
	 * RestroPress tax callback.
	 * Have multiple arguments to filter the results with.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function rpress_tax_callback( WP_REST_Request $request ) {
		$result = new stdClass();
		$result->is_enable = rpress_use_taxes();
		$result->is_prices_include = rpress_prices_include_tax();
		$result->name = rpress_get_tax_name();
		$result->rate = rpress_get_formatted_tax_rate();
		$result->currency = rpress_currency_symbol();
		
		$response = array(
			'message' => __( 'Successful', 'restropress' ),
			'data'    => $result,
		);
		
		return new WP_REST_Response( $response, 200 );
	}
}