<?php
/**
 * Food Item variable price - compact inline row.
 *
 * @package RestroPress/Admin
 */
defined( 'ABSPATH' ) || exit;
$count  = ! empty( $current ) ? $current : 0;
$name   = ! empty( $price ) && is_array( $price ) ? $price['name']   : '';
$amount = ! empty( $price ) && is_array( $price ) ? $price['amount'] : 0;
?>
<div class="rp-metabox variable-price rp-varprice-row">
	<span class="price_name" style="display:none"><?php echo $name !== '' ? esc_html( $name ) : esc_html__( 'Option Name', 'restropress' ); ?></span>
	<span class="dashicons dashicons-menu rp-varprice-drag tips sort"
		data-tip="<?php esc_attr_e( 'Drag to reorder', 'restropress' ); ?>"></span>
	<input type="text"
		value="<?php echo esc_attr( $name ); ?>"
		name="rpress_variable_prices[<?php echo absint( $count ); ?>][name]"
		class="rp-input rp-input-variable-name rp-varprice-name"
		placeholder="<?php esc_attr_e( 'Option name (e.g. Small)', 'restropress' ); ?>">
	<span class="rp-varprice-currency"><?php echo esc_html( rpress_currency_symbol() ); ?></span>
	<input type="number"
		step="any" min="0.00"
		value="<?php echo esc_attr( rpress_sanitize_amount( $amount ) ); ?>"
		name="rpress_variable_prices[<?php echo absint( $count ); ?>][amount]"
		class="rp-input rp-varprice-amount"
		placeholder="0.00">
	<a href="#" class="remove_row delete rp-varprice-remove"
		title="<?php esc_attr_e( 'Remove option', 'restropress' ); ?>">
		<span class="dashicons dashicons-trash"></span>
	</a>
</div>