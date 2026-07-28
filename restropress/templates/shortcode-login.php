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
				<svg width="23" height="23" viewBox="0 0 23 23" fill="none" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
					<rect width="23" height="23" fill="url(#pattern0_124_49)"/>
					<defs>
					<pattern id="pattern0_124_49" patternContentUnits="objectBoundingBox" width="1" height="1">
					<use xlink:href="#image0_124_49" transform="scale(0.0070922)"/>
					</pattern>
					<image id="image0_124_49" width="141" height="141" preserveAspectRatio="none" xlink:href="data:image/png;base64,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"/>
					</defs>
				</svg>
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
