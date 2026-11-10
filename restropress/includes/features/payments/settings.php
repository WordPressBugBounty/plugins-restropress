<?php
/**
 * Stripe settings additions: wallet toggle, Apple Pay domain file, webhook
 * URL with a copy button. Also serves the Apple Pay domain association file.
 *
 * @package RestroPress
 * @since   3.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'rpress_core_stripe_settings', 'rpress_payments_stripe_settings' );
/**
 * Add the 3.5 fields to Settings > Payments > Stripe.
 *
 * @since 3.5
 * @param array $settings Core Stripe settings.
 * @return array
 */
function rpress_payments_stripe_settings( $settings ) {
	$wallets = array(
		'stripe_wallets'                      => array(
			'id'      => 'stripe_wallets',
			'name'    => __( 'Show Apple Pay / Google Pay', 'restropress' ),
			'desc'    => __( 'Shows an Apple Pay or Google Pay button at checkout when the customer\'s browser supports it. Needs HTTPS and your publishable key.', 'restropress' ),
			'type'    => 'select',
			'options' => array(
				'show' => __( 'Show', 'restropress' ),
				'hide' => __( 'Hide', 'restropress' ),
			),
			'std'     => 'show',
		),
		'stripe_apple_pay_domain_association' => array(
			'id'   => 'stripe_apple_pay_domain_association',
			'name' => __( 'Apple Pay domain file', 'restropress' ),
			'desc' => sprintf(
				/* translators: %s: URL the file is served from */
				__( 'Optional. Paste the content of the Apple Pay domain association file from your Stripe dashboard (Settings, Payment method domains). RestroPress serves it at %s. Then add this domain in Stripe so Apple Pay can verify it. Google Pay needs no file. Verification only works on a live HTTPS domain.', 'restropress' ),
				'<code>' . esc_html( home_url( '/.well-known/apple-developer-merchantid-domain-association' ) ) . '</code>'
			),
			'type' => 'textarea',
		),
	);

	$out = array();
	foreach ( $settings as $key => $field ) {
		if ( 'stripe_webhook_description' === $key ) {
			$field = array(
				'id'   => 'stripe_webhook_endpoint',
				'name' => __( 'Webhook URL', 'restropress' ),
				'type' => 'hook',
			);
			$key   = 'stripe_webhook_endpoint';
		}
		$out[ $key ] = $field;
		if ( 'live_publishable_key' === $key ) {
			$out += $wallets;
		}
	}

	return $out + $wallets;
}

add_action( 'rpress_stripe_webhook_endpoint', 'rpress_payments_stripe_webhook_endpoint_field' );
/**
 * Webhook URL field with a copy button and the events to subscribe to.
 *
 * @since 3.5
 * @return void
 */
function rpress_payments_stripe_webhook_endpoint_field() {
	$url = rpress_payments_stripe_webhook_url();
	?>
	<div class="rpress-stripe-webhook-endpoint">
		<input type="text" readonly class="regular-text code" id="rpress-stripe-webhook-url" value="<?php echo esc_attr( $url ); ?>" onfocus="this.select();" />
		<button type="button" class="button" id="rpress-stripe-webhook-copy" data-copied="<?php esc_attr_e( 'Copied', 'restropress' ); ?>"><?php esc_html_e( 'Copy', 'restropress' ); ?></button>
		<p class="description">
			<?php
			printf(
				/* translators: %s: Stripe dashboard link */
				esc_html__( 'Add this URL as an endpoint in your %s, then paste its signing secret in the field above. Select these events:', 'restropress' ),
				'<a href="' . esc_url( 'https://dashboard.stripe.com/webhooks' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Stripe dashboard', 'restropress' ) . '</a>'
			);
			?>
			<br /><code><?php echo esc_html( implode( ', ', rpress_payments_stripe_webhook_events() ) ); ?></code>
		</p>
		<?php if ( '' === rpress_core_stripe_get_webhook_secret() ) : ?>
			<p class="description"><strong><?php esc_html_e( 'No signing secret saved yet: webhooks are rejected until you add one.', 'restropress' ); ?></strong></p>
		<?php endif; ?>
	</div>
	<script>
	( function () {
		var btn = document.getElementById( 'rpress-stripe-webhook-copy' );
		var input = document.getElementById( 'rpress-stripe-webhook-url' );
		if ( ! btn || ! input ) { return; }
		btn.addEventListener( 'click', function () {
			var label = btn.textContent;
			var done = function () { btn.textContent = btn.getAttribute( 'data-copied' ); setTimeout( function () { btn.textContent = label; }, 1500 ); };
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( input.value ).then( done );
			} else {
				input.select();
				document.execCommand( 'copy' );
				done();
			}
		} );
	} )();
	</script>
	<?php
}

add_filter( 'rpress_settings_sanitize_textarea', 'rpress_payments_sanitize_apple_pay_file', 10, 2 );
/**
 * Keep the pasted Apple Pay file to printable ASCII.
 *
 * @since 3.5
 * @param mixed  $value Value.
 * @param string $key   Setting key.
 * @return mixed
 */
function rpress_payments_sanitize_apple_pay_file( $value, $key = '' ) {
	if ( 'stripe_apple_pay_domain_association' !== $key ) {
		return $value;
	}
	return trim( preg_replace( '/[^\x20-\x7E\r\n]/', '', (string) $value ) );
}

add_action( 'init', 'rpress_payments_serve_apple_pay_file', 1 );
/**
 * Serve /.well-known/apple-developer-merchantid-domain-association from the
 * pasted setting. Only works when requests for that path reach WordPress
 * (no physical file there and the web server does not block dot folders).
 *
 * @since 3.5
 * @return void
 */
function rpress_payments_serve_apple_pay_file() {
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$path = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
	if ( '/.well-known/apple-developer-merchantid-domain-association' !== untrailingslashit( (string) $path ) ) {
		return;
	}
	$content = (string) rpress_get_option( 'stripe_apple_pay_domain_association', '' );
	if ( '' === trim( $content ) ) {
		return;
	}
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Content-Type-Options: nosniff' );
	echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text file, sanitized to printable ASCII on save, sent as text/plain.
	exit;
}
