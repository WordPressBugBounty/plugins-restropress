<?php
/**
 * Emails
 *
 * This class handles all emails sent through RPRESS
 *
 * @package     RPRESS
 * @subpackage  Classes/Emails
 * @copyright   Copyright (c) 2018, Magnigenie
 * @license     http://opensource.org/licenses/gpl-2.1.php GNU Public License
 * @since       2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RPRESS_Emails {

	/**
	 * Holds the from address
	 *
	 * @since 1.0.0
	 */
	private $from_address;

	/**
	 * Holds legacy from email (PHP 8.2 fix)
	 *
	 * @since 1.0.0
	 */
	private $from_email;

	/**
	 * Holds the from name
	 *
	 * @since 1.0.0
	 */
	private $from_name;

	/**
	 * Holds the email content type
	 *
	 * @since 1.0.0
	 */
	private $content_type;

	/**
	 * Holds the email headers
	 *
	 * @since 1.0.0
	 */
	private $headers;

	/**
	 * Whether to send email in HTML
	 *
	 * @since 1.0.0
	 */
	private $html = true;

	/**
	 * The email template to use
	 *
	 * @since 1.0.0
	 */
	private $template;

	/**
	 * The header text for the email
	 *
	 * @since 2.1
	 */
	private $heading = '';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		if ( 'none' === $this->get_template() ) {
			$this->html = false;
		}

		add_action( 'rpress_email_send_before', array( $this, 'send_before' ) );
		add_action( 'rpress_email_send_after', array( $this, 'send_after' ) );
	}

	/**
	 * Magic setter (guarded)
	 *
	 * @since 1.0.0
	 */
	public function __set( $key, $value ) {
		if ( property_exists( $this, $key ) ) {
			$this->$key = $value;
		}
	}

	/**
	 * Magic getter
	 *
	 * @since 1.0.0
	 */
	public function __get( $key ) {
		return property_exists( $this, $key ) ? $this->$key : null;
	}

	/**
	 * Get from name
	 *
	 * @since 1.0.0
	 */
	public function get_from_name() {

		if ( empty( $this->from_name ) ) {
			$this->from_name = rpress_get_option( 'from_name', get_bloginfo( 'name' ) );
		}

		return apply_filters(
			'rpress_email_from_name',
			wp_specialchars_decode( $this->from_name ),
			$this
		);
	}

	/**
	 * Get from address
	 *
	 * @since 1.0.0
	 */
	public function get_from_address() {

		if ( empty( $this->from_address ) ) {
			$this->from_address = $this->from_email ?: rpress_get_option( 'from_email' );
		}

		if ( empty( $this->from_address ) || ! is_email( $this->from_address ) ) {
			$this->from_address = get_option( 'admin_email' );
		}

		return apply_filters(
			'rpress_email_from_address',
			$this->from_address,
			$this
		);
	}

	/**
	 * Get content type
	 *
	 * @since 1.0.0
	 */
	public function get_content_type() {

		if ( ! $this->content_type && $this->html ) {
			$this->content_type = apply_filters(
				'rpress_email_default_content_type',
				'text/html',
				$this
			);
		} elseif ( ! $this->html ) {
			$this->content_type = 'text/plain';
		}

		return apply_filters(
			'rpress_email_content_type',
			$this->content_type,
			$this
		);
	}

	/**
	 * Get headers
	 *
	 * @since 1.0.0
	 */
	public function get_headers() {

		if ( empty( $this->headers ) ) {

			$this->headers  = "From: {$this->get_from_name()} <{$this->get_from_address()}>\r\n";
			$this->headers .= "Reply-To: {$this->get_from_address()}\r\n";
			$this->headers .= "Content-Type: {$this->get_content_type()}; charset=utf-8\r\n";
		}

		return apply_filters(
			'rpress_email_headers',
			$this->headers,
			$this
		);
	}

	/**
	 * Email templates
	 *
	 * @since 1.0.0
	 */
	public function get_templates() {

		$templates = array(
			'default' => __( 'Default Template', 'restropress' ),
			'none'    => __( 'No template, plain text only', 'restropress' ),
		);

		return apply_filters( 'rpress_email_templates', $templates );
	}

	/**
	 * Get template
	 *
	 * @since 1.0.0
	 */
	public function get_template() {

		if ( empty( $this->template ) ) {
			$this->template = rpress_get_option( 'email_template', 'default' );
		}

		return apply_filters( 'rpress_email_template', $this->template );
	}

	/**
	 * Get heading
	 *
	 * @since 1.0.0
	 */
	public function get_heading() {
		return apply_filters( 'rpress_email_heading', $this->heading );
	}

	/**
	 * Parse tags (placeholder)
	 *
	 * @since 1.0.0
	 */
	public function parse_tags( $content ) {
		return $content;
	}

	/**
	 * Build email body
	 *
	 * @since 1.0.0
	 */
	public function build_email( $message ) {

		if ( ! $this->html ) {
			return apply_filters(
				'rpress_email_message',
				wp_strip_all_tags( $message ),
				$this
			);
		}

		$message = $this->text_to_html( $message );

		ob_start();

		rpress_get_template_part( 'emails/header', $this->get_template(), true );
		do_action( 'rpress_email_header', $this );

		if ( has_action( 'rpress_email_template_' . $this->get_template() ) ) {
			do_action( 'rpress_email_template_' . $this->get_template() );
		} else {
			rpress_get_template_part( 'emails/body', $this->get_template(), true );
		}

		do_action( 'rpress_email_body', $this );

		rpress_get_template_part( 'emails/footer', $this->get_template(), true );
		do_action( 'rpress_email_footer', $this );

		$body = ob_get_clean();

		$message = str_replace( '{email}', $message, $body );

		return apply_filters( 'rpress_email_message', $message, $this );
	}

	/**
	 * Send email
	 *
	 * @since 1.0.0
	 */
	public function send( $to, $subject, $message, $attachments = '' ) {

		if ( ! did_action( 'init' ) && ! did_action( 'admin_init' ) ) {
			_doing_it_wrong(
				__FUNCTION__,
				esc_html__( 'You cannot send email with RPRESS_Emails until init/admin_init has been reached', 'restropress' ),
				null
			);
			return false;
		}

		do_action( 'rpress_email_send_before', $this );

		$subject = $this->parse_tags( $subject );
		$message = $this->build_email( $this->parse_tags( $message ) );

		$attachments = apply_filters( 'rpress_email_attachments', $attachments, $this );

		$sent = wp_mail( $to, $subject, $message, $this->get_headers(), $attachments );

		do_action( 'rpress_email_send_after', $this );

		return $sent;
	}

	/**
	 * Before send hooks
	 *
	 * @since 1.0.0
	 */
	public function send_before() {

		add_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
		add_filter( 'wp_mail_content_type', array( $this, 'get_content_type' ) );
	}

	/**
	 * After send cleanup
	 *
	 * @since 1.0.0
	 */
	public function send_after() {

		remove_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );
		remove_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
		remove_filter( 'wp_mail_content_type', array( $this, 'get_content_type' ) );

		$this->heading = '';
	}

	/**
	 * Convert text to HTML
	 *
	 * @since 1.0.0
	 */
	public function text_to_html( $message ) {

		if ( $this->html ) {
			$message = apply_filters( 'rpress_email_template_wpautop', true )
				? wpautop( $message )
				: $message;

			$message = apply_filters( 'rpress_email_template_make_clickable', true )
				? make_clickable( $message )
				: $message;

			$message = str_replace( '&#038;', '&amp;', $message );
		}

		return $message;
	}
}