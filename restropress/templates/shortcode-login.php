<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This template is used to display the login form with [rpress_login]
 */
global $rpress_login_redirect;
if ( ! is_user_logged_in() ) :
	$style = rpress_get_option( 'button_style', 'button' );
	// Show any error messages after form submission
	rpress_print_errors(); ?>
	<form id="rpress_login_form" class="rpress_form" action="" method="post">
		<div>
			<!-- <legend><?php esc_html_e( 'Log into Your Account', 'restropress' ); ?></legend> -->
			 <h1 class="rpress-login-hd"><?php esc_html_e( 'Login', 'restropress' ); ?></h1>
			 <div class="welcome-text-wrap"><?php esc_html_e( 'Hi, Welcome back', 'restropress' ); ?>
			</div>
			<?php
			$login_method = rpress_get_option( 'login_method', 'login_guest' );
			if( ! is_user_logged_in() && $login_method != 'guest_only' ){
				ob_start();
				do_action( 'rpress_purchase_form_before_register_login' );
				$login_provider_markup = trim( ob_get_clean() );

				if ( ! empty( $login_provider_markup ) ) {
				?>
				<div class="gmail-login-link-wrap">
					<?php echo $login_provider_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="hr-lines"><?php esc_html_e( 'or Login with', 'restropress' ); ?></div>
				<?php
				}
			}
			?>
			<?php do_action( 'rpress_login_fields_before' ); ?>
			<p class="rpress-login-username">
				<label for="rpress_user_login"><?php esc_html_e( 'Username or Email', 'restropress' ); ?></label>
				<input name="rpress_user_login" id="rpress_user_login" class="rpress-required rpress-input" placeholder="<?php esc_attr_e( 'E.g. johndoe@email.com', 'restropress' ); ?>" type="text"/>
			</p>
			<p class="rpress-login-password">
				<label for="rpress_user_pass"><?php esc_html_e( 'Password', 'restropress' ); ?></label>
				<input name="rpress_user_pass" id="rpress_user_pass" class="rpress-password rpress-required rpress-input" placeholder="<?php esc_attr_e( 'Enter your password', 'restropress' ); ?>" type="password"/>
			</p>
			<p class="rpress-login-remember">
				<span>
					<input name="rememberme" type="checkbox" id="rememberme" value="forever" />
					<label for="rememberme"><?php esc_html_e( 'Remember Me', 'restropress' ); ?></label>
				</span>
				<a class="rpress-lost-password" href="<?php echo esc_url( rpress_get_lostpassword_url() ); ?>">
					<?php esc_html_e( 'Forgot Password?', 'restropress' ); ?>
				</a>
			</p>
			<p class="rpress-login-submit">
				<input type="hidden" name="rpress_redirect" value="<?php echo esc_url( $rpress_login_redirect ); ?>"/>
				<input type="hidden" name="rpress_login_nonce" value="<?php echo esc_attr(wp_create_nonce( 'rpress-login-nonce' )); ?>"/>
				<input type="hidden" name="rpress_action" value="user_login"/>
				<input type="submit" class="rpress-submit <?php echo wp_kses_post( $style ); ?>" id="rpress_login_submit"  value="<?php esc_attr_e( 'Login', 'restropress' ); ?>"/>
			</p>
			<p class="register-link-wrap"><?php esc_html_e( 'Not registered yet?', 'restropress' ); ?> <a href="<?php echo esc_url( site_url('/register') ); ?>" class="reglink"><?php esc_html_e( 'Create an account', 'restropress' ); ?>
			<svg width="13" height="13" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M1.04342 1.01938L1.05784 2.04356L4.92859 2.05799L8.79934 2.0676L4.39967 6.46727L0 10.8669L0.735682 11.5978L1.46656 12.3335L5.86622 7.93383L10.2659 3.53416L10.2755 7.40491L10.2899 11.2757L11.3141 11.2901L12.3335 11.2997V5.64985V-9.53674e-07H6.68365H1.0338L1.04342 1.01938Z" fill="#ED5575"/>
			</svg>
				</a>
			</p>
			<?php do_action( 'rpress_login_fields_after' ); ?>
		</div>
	</form>
<?php else : ?>
	<?php do_action( 'rpress_login_form_logged_in' ); ?>
<?php endif; ?>
