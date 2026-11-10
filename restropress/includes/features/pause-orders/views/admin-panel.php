<?php
/**
 * Live Orders: pause orders / busy mode panel.
 *
 * @package RestroPress
 * @since   3.5
 *
 * @var array $state RPRESS_Pause_Orders::get_state()
 */

defined( 'ABSPATH' ) || exit;

$rp_po_paused   = ! empty( $state['paused'] );
$rp_po_until    = (int) $state['until'];
$rp_po_extra    = (int) $state['extra_prep'];
$rp_po_base     = RPRESS_Pause_Orders::base_prep_time();
$rp_po_preorder = rpress_is_preorder_enabled();

if ( $rp_po_paused && $rp_po_until ) {
	/* translators: %s: time ordering resumes, e.g. 7:30 PM */
	$rp_po_status = sprintf( __( 'Paused until %s', 'restropress' ), RPRESS_Pause_Orders::format_time( $rp_po_until ) );
} elseif ( $rp_po_paused ) {
	$rp_po_status = __( 'Paused until resumed', 'restropress' );
} else {
	$rp_po_status = __( 'Taking orders', 'restropress' );
}
?>
<div class="rp-pause-orders<?php echo $rp_po_paused ? ' is-paused' : ''; ?><?php echo $rp_po_extra ? ' is-busy' : ''; ?>"
     data-until="<?php echo esc_attr( $rp_po_until ); ?>"
     data-extra-until="<?php echo esc_attr( (int) $state['extra_until'] ); ?>"
     data-now="<?php echo esc_attr( time() ); ?>">
	<div class="rp-pause-orders-status" role="status" aria-live="polite">
		<span class="rp-pause-orders-dot" aria-hidden="true"></span>
		<strong class="rp-pause-orders-state"><?php echo esc_html( $rp_po_status ); ?></strong>
		<?php if ( $rp_po_extra ) : ?>
			<span class="rp-pause-orders-busy-note">
				<?php
				printf(
					/* translators: 1: extra minutes, 2: total prep minutes */
					esc_html__( 'Busy: +%1$d min (about %2$d min per order)', 'restropress' ),
					(int) $rp_po_extra,
					(int) ( $rp_po_base + $rp_po_extra )
				);
				?>
			</span>
		<?php endif; ?>
		<?php if ( $rp_po_paused ) : ?>
			<button type="button" class="button button-primary rp-pause-orders-btn rp-pause-orders-resume" data-op="resume">
				<?php esc_html_e( 'Resume now', 'restropress' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<div class="rp-pause-orders-controls">
		<div class="rp-pause-orders-group" role="group" aria-label="<?php esc_attr_e( 'Pause new orders', 'restropress' ); ?>">
			<span class="rp-pause-orders-label"><?php esc_html_e( 'Pause new orders', 'restropress' ); ?></span>
			<?php foreach ( RPRESS_Pause_Orders::pause_durations() as $rp_po_minutes ) : ?>
				<button type="button" class="button rp-pause-orders-btn" data-op="pause" data-minutes="<?php echo esc_attr( $rp_po_minutes ); ?>">
					<?php
					if ( $rp_po_minutes ) {
						/* translators: %d: minutes */
						printf( esc_html__( '%d min', 'restropress' ), (int) $rp_po_minutes );
					} else {
						esc_html_e( 'Until resumed', 'restropress' );
					}
					?>
				</button>
			<?php endforeach; ?>
		</div>

		<div class="rp-pause-orders-group" role="group" aria-label="<?php esc_attr_e( 'Add to prep time', 'restropress' ); ?>">
			<span class="rp-pause-orders-label"><?php esc_html_e( 'Add to prep time', 'restropress' ); ?></span>
			<?php foreach ( RPRESS_Pause_Orders::busy_steps() as $rp_po_minutes ) : ?>
				<button type="button"
				        class="button rp-pause-orders-btn<?php echo $rp_po_extra === (int) $rp_po_minutes ? ' is-active' : ''; ?>"
				        data-op="busy"
				        data-minutes="<?php echo esc_attr( $rp_po_minutes ); ?>"
				        aria-pressed="<?php echo $rp_po_extra === (int) $rp_po_minutes ? 'true' : 'false'; ?>">
					<?php
					/* translators: %d: minutes */
					printf( esc_html__( '+%d min', 'restropress' ), (int) $rp_po_minutes );
					?>
				</button>
			<?php endforeach; ?>
			<?php if ( $rp_po_extra ) : ?>
				<button type="button" class="button-link rp-pause-orders-btn rp-pause-orders-reset" data-op="busy" data-minutes="0">
					<?php esc_html_e( 'Reset', 'restropress' ); ?>
				</button>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $rp_po_paused ) : ?>
		<p class="rp-pause-orders-hint">
			<?php
			echo $rp_po_preorder
				? esc_html__( 'Customers see a notice and can only schedule orders for after the pause.', 'restropress' )
				: esc_html__( 'Customers see a notice and cannot add to cart or check out until you resume.', 'restropress' );
			?>
		</p>
	<?php endif; ?>
</div>
