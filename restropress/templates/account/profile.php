<?php
/**
 * Account: Profile section.
 *
 * Override in your theme: restropress/account/profile.php
 *
 * @package RestroPress
 * @since   3.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user = wp_get_current_user();
$username     = $current_user->user_login;
$useremail    = $current_user->user_email;
$userfname    = $current_user->user_firstname;
$userlname    = $current_user->user_lastname;
$useravatar   = get_avatar( $current_user->ID, 96 );
$user_phone   = get_user_meta( $current_user->ID, '_rpress_phone', true );
?>
<div class="box-bg pd-2">
    <div class="avtar-header border-solid-bg pd-2">
        <div class="flex align-items">
            <span class="avtar-image me-15">
                <?php if($useravatar) {
                    echo wp_kses( $useravatar, array(
                        'img' => array(
                            'src' => array(),
                            'class' => array(),
                            'alt' => array(),
                            'width' => array(),
                            'height' => array()
                        )
                    ) );
                } else {
                    ?><img alt="" src="<?php echo esc_url( RP_PLUGIN_URL . 'assets/images/user/avatar-1.png' ); ?>" class="rounded-circle"><?php
                } ?>
            </span>
            <div class="avtar-details-wrap">
                <?php if( !empty($userfname) && !empty($userlname) ) {
                    ?><h2 class="avtar-name"><?php echo esc_html( $userfname." ".$userlname ); ?></h2><?php
                } else {
                    ?><h2 class="avtar-name"><?php echo esc_html( $username ); ?></h2><?php
                } ?>
                <?php if( !empty($useremail) ) {
                    ?><p class="avtar-email"><?php echo esc_html( $useremail ); ?></p><?php
                } ?>
            </div>                
        </div>             
    </div>
    <div class="border-solid-bg pd-2">
        <div class="box-header">
            <h4 class="box-title"><?php echo esc_html(__( 'Personal Information', 'restropress' ) ); ?></h4>  
        </div>
        <div class="box-body">
            <form method="POST" class="profile-form-wrap">
                <div class="row">
                    <div class="col-6">
                        <div class="input-wrap mb-2">
                            <input type="text" class="form-control" name="first_name" value="<?php echo esc_attr( $userfname ); ?>">
                            <label class="form-label"><?php echo esc_html(__( 'First name', 'restropress' ) ); ?></label>                                        
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="input-wrap mb-2">
                            <input type="text" class="form-control" name="last_name" value="<?php echo esc_attr( $userlname ); ?>">
                            <label class="form-label"><?php echo esc_html(__( 'Last name', 'restropress' ) ); ?></label>                    
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="input-wrap mb-2">
                            <input type="email" class="form-control" name="email" value="<?php echo esc_attr( $useremail ); ?>" data-current="<?php echo esc_attr( $useremail ); ?>">
                            <label class="form-label"><?php echo esc_html(__( 'Email address', 'restropress' ) ); ?></label>                                        
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="input-wrap mb-2">
                            <input type="text" class="form-control" name="phone" value="<?php echo esc_attr( $user_phone ); ?>">
                            <label class="form-label"><?php echo esc_html(__( 'Phone', 'restropress' ) ); ?></label>                    
                        </div>
                    </div>
                </div>
                <?php // Shown once the email is edited: a new email needs the current password (3.5.1). ?>
                <div class="row rpress-email-password" hidden>
                    <div class="col-6">
                        <div class="input-wrap mb-2">
                            <input type="password" class="form-control" id="rpress-current-password" name="current_password" value="" autocomplete="current-password" aria-describedby="rpress-current-password-help">
                            <label class="form-label" for="rpress-current-password"><?php esc_html_e( 'Current password', 'restropress' ); ?></label>
                        </div>
                    </div>
                    <div class="col-6">
                        <p class="rpress-email-password-help" id="rpress-current-password-help">
                            <?php esc_html_e( 'Needed to change your email address.', 'restropress' ); ?>
                            <a href="<?php echo esc_url( wp_lostpassword_url( rpress_account_url() ) ); ?>"><?php esc_html_e( 'Forgot password?', 'restropress' ); ?></a>
                        </p>
                    </div>
                </div>
                <div class="text-end">
                    <?php do_action( 'rpress_user_dashboard_profile_form_fields' ); ?>
                    <input type="submit" name="submit_profile_form" value="<?php esc_attr_e( 'Save Changes', 'restropress' ); ?>">
                </div>
            </form>
        </div>
    </div>
</div>  
