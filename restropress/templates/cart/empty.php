<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<li class="cart_item empty"><?php echo wp_kses_post( rpress_empty_cart_message() ) ; ?></li>
