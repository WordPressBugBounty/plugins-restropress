<div id="rpress-payment-processing">
	<p>
		<?php
		printf(
			wp_kses(
				/* translators: %s - success page URL */
				__( 'Your order is processing. This page will reload automatically in 8 seconds. If it does not, click <a href="%s">here</a>.', 'restropress' ),
				array(
					'a' => array(
						'href' => array(),
					),
				)
			),
			esc_url( rpress_get_success_page_uri() )
		);
		?>
	</p>

	<span class="rpress-cart-ajax">
		<i class="rpress-icon-spinner rpress-icon-spin"></i>
	</span>

	<script type="text/javascript">
		setTimeout(function() {
			window.location = '<?php echo esc_js( rpress_get_success_page_uri() ); ?>';
		}, 8000);
	</script>
</div>