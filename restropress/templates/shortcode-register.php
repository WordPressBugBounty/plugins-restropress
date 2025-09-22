<?php
/**
 * This template is used to display the registration form with [rpress_register]
 */
global $rpress_register_redirect;
do_action( 'rpress_print_errors' );
if ( ! is_user_logged_in() ) :
	$style = rpress_get_option( 'button_style', 'button' ); ?>
	<form id="rpress_register_form" class="rpress_form" action="" method="post">
		<?php do_action( 'rpress_register_form_fields_top' ); ?>
		<div>
			<!-- <legend><?php esc_html_e( 'Register New User', 'restropress' ); ?></legend> -->
			 <h1 class="rpress-login-hd">Create an Account</h1>
			  <div class="welcome-text-wrap">Welcome! Let’s set things up
			</div>
			<?php
			$login_method = rpress_get_option( 'login_method', 'login_guest' );
			if( ! is_user_logged_in() && $login_method != 'guest_only' ){
				?>
				<div class="gmail-login-link-wrap">
					<?php
						do_action( 'rpress_purchase_form_before_register_login' );
					?>
				</div>
				<div class="hr-lines">or Register with</div>
				<?php
			}	
			?>
			<?php do_action( 'rpress_register_form_fields_before' ); ?>
			<p>
				<label for="rpress-user-login"><?php esc_html_e( 'Username', 'restropress' ); ?></label>
				<input id="rpress-user-login" class="required rpress-input" type="text" name="rpress_user_login" placeholder="Enter your name" />
			</p>
			<p>
				<label for="rpress-user-email"><?php esc_html_e( 'Email', 'restropress' ); ?></label>
				<input id="rpress-user-email" class="required rpress-input" type="email" name="rpress_user_email" placeholder="E.g. johndoe@email.com" />
			</p>
			<p>
				<label for="rpress-user-pass"><?php esc_html_e( 'Password', 'restropress' ); ?></label>
				<input id="rpress-user-pass" class="password required rpress-input" type="password" name="rpress_user_pass" placeholder="Enter your password" />
			</p>
			<p>
				<label for="rpress-user-pass2"><?php esc_html_e( 'Confirm Password', 'restropress' ); ?></label>
				<input id="rpress-user-pass2" class="password required rpress-input" type="password" name="rpress_user_pass2" placeholder="Enter confirm password" />
			</p>
			<?php do_action( 'rpress_register_form_fields_before_submit' ); ?>
			<p>
				<input type="hidden" name="rpress_honeypot" value="" />
				<input type="hidden" name="rpress_action" value="user_register" />
				<input type="hidden" name="rpress_redirect" value="<?php echo esc_url( $rpress_register_redirect ); ?>"/>
				<input type="submit" class="rpress-submit <?php echo wp_kses_post( $style ); ?>" id="rpress-purchase-button" name="rpress_register_submit" value="<?php esc_attr_e( 'Register', 'restropress' ); ?>"/>
			</p>
			<p class="register-link-wrap">Already have an account? <a href="<?php echo site_url('/login'); ?>" class="reglink">Sign In
				<svg width="13" height="13" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M1.04342 1.01938L1.05784 2.04356L4.92859 2.05799L8.79934 2.0676L4.39967 6.46727L0 10.8669L0.735682 11.5978L1.46656 12.3335L5.86622 7.93383L10.2659 3.53416L10.2755 7.40491L10.2899 11.2757L11.3141 11.2901L12.3335 11.2997V5.64985V-9.53674e-07H6.68365H1.0338L1.04342 1.01938Z" fill="#ED5575"/>
				</svg>
				</a>
			</p>
			<?php do_action( 'rpress_register_form_fields_after' ); ?>
		</div>
		<?php do_action( 'rpress_register_form_fields_bottom' ); ?>
	</form>
<?php else : ?>
	<?php do_action( 'rpress_register_form_logged_in' ); ?>
<?php endif; ?>
