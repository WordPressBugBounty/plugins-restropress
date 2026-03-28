<?php
use Firebase\JWT\JWT;
use WP_REST_Response as response;
/**
 * Description of RP_REST_Auth_V1_Controller
 *
 * @author Magnigeeks <info@magnigeeks.com>
 */
class RP_REST_Auth_V1_Controller {
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
	protected $rest_base = 'auth';
	/**
	 * Response.
	 *
	 * @var string
	 */
	protected $response;
	/**
	 * API key
	 *
	 * @var string
	 * **/
	protected $api_key = '';
	public function __construct() {
		$this->response = new response();
	}

	/**
	 * Get the requested user ID from the request.
	 *
	 * @param WP_REST_Request $request Rest request object.
	 * @return int
	 */
	protected function get_requested_user_id( WP_REST_Request $request ): int {
		$user_id = $request->get_param( 'user_id' );

		if ( is_null( $user_id ) ) {
			$user_id = $request->get_header( 'x-user-id' );
		}

		return absint( $user_id );
	}

	/**
	 * Get the API key used to sign the token request.
	 *
	 * @param WP_REST_Request $request Rest request object.
	 * @return string
	 */
	protected function get_request_api_key( WP_REST_Request $request ): string {
		$api_key = $request->get_header( 'x-api-key' );

		if ( empty( $api_key ) ) {
			$api_key = $request->get_header( 'authorization' );
		}

		if ( ! is_string( $api_key ) ) {
			return '';
		}

		$api_key = trim( $api_key );

		if ( preg_match( '/^Bearer\s/i', $api_key ) ) {
			return '';
		}

		return $api_key;
	}
	/**
	 * Register the routes for foods.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_token' ),
					'permission_callback' => array( $this, 'get_auth_permissions_check' ),
					'args'                => array(),
				),
			)
		);
	}
	/**
	 *
	 * * */
	/**
	 * Callback of generating token
	 *
	 * @access public
	 * @return WP_REST_Response  | Response object
	 * @throws InvalidArgumentException
	 * @throws DomainException
	 * @throws BeforeValidException
	 * @throws UnexpectedValueException
	 * @throws Exception
	 * @since 3.0.0
	 * * */
	public function get_token( WP_REST_Request $request ): WP_REST_Response {
		// Initialize Expire
		$expire = null;
		// Generating unique token id
		try {
			$token_id = base64_encode( random_bytes( 16 ) );
		} catch ( Exception $exc ) {
			$token_id = wp_generate_password( 24, false, false );
		}
		// Get DateTimeImmutable Object for further use at Issuer time and not before
		$obj = new DateTimeImmutable();
		// Set expire time limit if it set at admin
		if ( ! empty( rpress_get_option( 'api_expire' ) ) ) {
			$exp    = rpress_get_option( 'api_expire' );
			$expire = $obj->modify( '+' . $exp )->getTimestamp();      // Add expire time limit
		}
		// Initialize server name furether use for Issuer
		$server_name = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( empty( $server_name ) && ! empty( $_SERVER['SERVER_NAME'] ) ) {
			$server_name = sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) );
		}
		$user_id = $this->get_requested_user_id( $request );
		// Create the token as an array
		$data = array(
			'iat'  => $obj->getTimestamp(), // Issued at: time when the token was generated
			'jti'  => $token_id, // Json Token Id: an unique identifier for the token
			'iss'  => $server_name, // Issuer
			'aud'  => $server_name, // Audience
			'nbf'  => $obj->getTimestamp(), // Not before
			'data' => array(
				'api_key' => $this->api_key, // API key
				'user_id' => $user_id,
			),
		);
		// Adding Expire time limit
		if ( ! is_null( $expire ) ) {
			$data['exp'] = $expire;
		}
		// Applying a filter for future adding data to token create array
		$data = apply_filters( 'rp_api_token_generate_data', $data );
		// Adding Links
		$this->response->add_links( $this->prepare_links() );
		// Checking data is array type
		if ( ! is_array( $data ) ) {
			$this->response->set_status( 401 );
			$this->response->set_data( array( 'message' => apply_filters( 'rp_api_token_generate_error_message', __( 'Data should be an array', 'restropress' ) ) ) );
			return $this->response;
		}
		// Get token on try catch block
		try {
			// Encode the array to a JWT string.
			$token = JWT::encode( $data, $this->api_key, 'HS512' );
			$this->response->set_status( 200 );
			$this->response->set_data( array( 'token' => $token ) );
		} catch ( InvalidArgumentException $exc ) {
			$error = $exc->getMessage();
			$this->response->set_status( 401 );
			$this->response->add_headers( array( 'X-WP-RP-error' => $error ) );
			$this->response->set_data( array( 'message' => apply_filters( 'rp_api_token_generate_error_message', __( $error, 'restropress' ) ) ) );
		} catch ( DomainException $exc ) {
			$error = $exc->getMessage();
			$this->response->set_status( 401 );
			$this->response->add_headers( array( 'X-WP-RP-error' => $error ) );
			$this->response->set_data( array( 'message' => apply_filters( 'rp_api_token_generate_error_message', __( $error, 'restropress' ) ) ) );
		} catch ( BeforeValidException $exc ) {
			$error = $exc->getMessage();
			$this->response->set_status( 401 );
			$this->response->add_headers( array( 'X-WP-RP-error' => $error ) );
			$this->response->set_data( array( 'message' => apply_filters( 'rp_api_token_generate_error_message', __( $error, 'restropress' ) ) ) );
		} catch ( UnexpectedValueException $exc ) {
			$error = $exc->getMessage();
			$this->response->set_status( 401 );
			$this->response->add_headers( array( 'X-WP-RP-error' => $error ) );
			$this->response->set_data( array( 'message' => apply_filters( 'rp_api_token_generate_error_message', __( $error, 'restropress' ) ) ) );
		} catch ( Exception $exc ) {
			$error = $exc->getMessage();
			$this->response->set_status( 401 );
			$this->response->add_headers( array( 'X-WP-RP-error' => $error ) );
			$this->response->set_data( array( 'message' => apply_filters( 'rp_api_token_generate_error_message', __( $error, 'restropress' ) ) ) );
		}
		// Return response;
		return $this->response;
	}
	/**
	 * protected method for preparing Links
	 *
	 * @return array | array of Links
	 * @since 3.0.0
	 * @access protected
	 * * */
	protected function prepare_links(): array {
		$links = array(
			'self'       => array(
				'href' => rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ),
			),
		);
		return $links;
	}
	/**
	 * Checking Permission before generating Token
	 *
	 * @param WP_REST_Request $request  Rest request Object
	 * @return bool|WP_Error permission return can be Boolean or WP_Error
	 * @access public
	 * @since 3.0.0
	 * * */
	public function get_auth_permissions_check( WP_REST_Request $request ) {
		$api_key        = $this->get_request_api_key( $request );
		$user_id        = $this->get_requested_user_id( $request );
		$is_api_enabled = rpress_get_option( 'activate_api' );

		if ( ! $is_api_enabled ) {
			return new WP_Error(
				'rest_forbidden',
				apply_filters( 'rp_api_not_enabled_error_message', __( 'API has not enabled!!!.', 'restropress' ), $request ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				apply_filters( 'rp_api_token_generate_error_message', __( 'You must be logged in to generate API tokens.', 'restropress' ), $request ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		if ( empty( $user_id ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				apply_filters( 'rp_api_token_generate_error_message', __( 'You are not allowed to generate an API token for this user.', 'restropress' ), $request ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'rest_forbidden',
				apply_filters( 'rp_api_token_generate_error_message', __( 'A valid API key is required to generate a token.', 'restropress' ), $request ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$this->api_key = $api_key;

		return true;
	}
}
