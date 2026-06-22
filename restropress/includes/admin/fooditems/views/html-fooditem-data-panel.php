<?php
/**
 * Food Item data meta box - section-based layout (no tabs).
 *
 * @package RestroPress/Admin
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="rp-fooditem-editor">
	<?php
	do_action( 'rpress_fooditem_editor_top', $post );
	self::output_tabs();
	do_action( 'rpress_fooditem_data_panels' );
	?>
</div>
