<?php
/**
 * One order card (account Orders section and [order_history]).
 *
 * Override in your theme: restropress/account/order-card.php
 *
 * @package RestroPress
 * @since   3.5.1
 *
 * @var RPRESS_Payment $payment
 * @var string         $context       'account' or 'history'.
 * @var string         $status_key
 * @var string         $status_phase
 * @var string         $status_label
 * @var string         $service_label
 * @var array          $summary       { lines, more }
 * @var bool           $is_live
 * @var string         $track_url
 * @var bool           $can_reorder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rpress_date = date_i18n( get_option( 'date_format' ), strtotime( $payment->date ) );
?>
<article class="rpress_purchase_row rpress-order-card rpress-order-status-<?php echo esc_attr( $status_key ); ?> rpress-order-phase-<?php echo esc_attr( $status_phase ); ?><?php echo $is_live ? ' is-live' : ''; ?>" data-order="<?php echo esc_attr( $payment->ID ); ?>">
	<header class="rpress-order-card-head">
		<div class="rpress-order-card-title">
			<h3>
				<?php
				/* translators: %s: order number. */
				echo esc_html( sprintf( __( 'Order #%s', 'restropress' ), $payment->number ) );
				?>
			</h3>
			<p class="rpress-order-card-meta">
				<span><?php echo esc_html( $rpress_date ); ?></span>
				<?php if ( $service_label ) : ?>
					<span><?php echo esc_html( $service_label ); ?></span>
				<?php endif; ?>
			</p>
		</div>
		<span class="rpress-status-badge rpress-order-badge status-<?php echo esc_attr( $status_key ); ?> phase-<?php echo esc_attr( $status_phase ); ?>"><?php echo esc_html( $status_label ); ?></span>
	</header>

	<?php if ( $summary['lines'] ) : ?>
		<p class="rpress-order-card-items">
			<?php echo esc_html( implode( ', ', $summary['lines'] ) ); ?>
			<?php if ( $summary['more'] ) : ?>
				<span class="rpress-order-card-more">
					<?php
					/* translators: %s: number of further order lines. */
					echo esc_html( sprintf( __( '+%s more', 'restropress' ), number_format_i18n( $summary['more'] ) ) );
					?>
				</span>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<footer class="rpress-order-card-foot">
		<div class="rpress-order-card-total">
			<span><?php echo rpress_is_payment_complete( $payment->ID ) ? esc_html__( 'Total paid', 'restropress' ) : esc_html__( 'Total', 'restropress' ); ?></span>
			<strong><?php echo esc_html( rpress_currency_filter( rpress_format_amount( $payment->total ), rpress_get_payment_currency_code( $payment->ID ) ) ); ?></strong>
		</div>
		<div class="rpress-order-card-actions">
			<a href="#" class="rpress-view-order-btn rpress-order-card-link" data-order-id="<?php echo esc_attr( $payment->ID ); ?>"><span class="rp-ajax-toggle-text"><?php esc_html_e( 'View details', 'restropress' ); ?></span></a>
			<?php
			do_action( 'rpress_order_after_view_details', $payment->ID, $payment->payment_meta );
			if ( 'account' === $context ) {
				do_action( 'rpress_user_dashboard_order_actions', $payment->ID );
			}
			?>
			<?php if ( $track_url ) : ?>
				<a href="<?php echo esc_url( $track_url ); ?>" class="rpress-track-order-btn is-live rpress-order-card-primary"><?php esc_html_e( 'Track order', 'restropress' ); ?></a>
			<?php elseif ( $can_reorder ) : ?>
				<a href="#" class="rpress-reorder-btn rpress-order-card-primary" data-order-id="<?php echo esc_attr( $payment->ID ); ?>"><span class="rp-ajax-toggle-text"><?php esc_html_e( 'Reorder', 'restropress' ); ?></span></a>
			<?php endif; ?>
		</div>
	</footer>
</article>
