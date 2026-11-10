<?php
/**
 * Menu Items > Generate missing descriptions (review screen).
 *
 * Drafts are generated one item at a time by ai-admin.js and shown here for
 * review. Only rows the merchant accepts are saved, and only into fields that
 * are still empty.
 *
 * @var WP_Post[] $items    Items missing a description or short description.
 * @var bool      $enabled  Bulk feature switched on.
 * @var bool      $ready    Provider configured.
 * @var bool      $selected Items came from a bulk selection.
 *
 * @package RPRESS\Features\AI
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap rp-ai-review" id="rp-ai-review">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Generate missing descriptions', 'restropress' ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url( admin_url( 'edit.php?post_type=fooditem' ) ); ?>"><?php esc_html_e( 'Back to Menu Items', 'restropress' ); ?></a>
	<hr class="wp-header-end">

	<?php if ( ! $enabled ) : ?>
		<div class="notice notice-warning inline"><p>
			<?php esc_html_e( 'Bulk descriptions are switched off.', 'restropress' ); ?>
			<a href="<?php echo esc_url( RPRESS_AI::settings_url() ); ?>"><?php esc_html_e( 'Open AI settings', 'restropress' ); ?></a>
		</p></div>
	<?php elseif ( ! $ready ) : ?>
		<div class="notice notice-warning inline"><p>
			<?php esc_html_e( 'No AI provider is set up yet. Choose a provider and add your API key first.', 'restropress' ); ?>
			<a href="<?php echo esc_url( RPRESS_AI::settings_url() ); ?>"><?php esc_html_e( 'Open AI settings', 'restropress' ); ?></a>
		</p></div>
	<?php endif; ?>

	<?php if ( empty( $items ) ) : ?>
		<p><?php echo $selected ? esc_html__( 'The selected menu items already have a description and a short description.', 'restropress' ) : esc_html__( 'Every menu item already has a description and a short description.', 'restropress' ); ?></p>
	<?php else : ?>
		<p class="rp-ai-review-intro">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of menu items */
					_n( '%d menu item is missing a description or short description.', '%d menu items are missing a description or short description.', count( $items ), 'restropress' ),
					count( $items )
				)
			);
			?>
			<?php esc_html_e( 'AI writes a draft for each one (one request per item, billed to your provider account). Nothing is saved until you accept it, existing text is never overwritten, and prices and publish status are not touched.', 'restropress' ); ?>
		</p>

		<div class="rp-ai-review-toolbar">
			<button type="button" class="button button-primary" id="rp-ai-review-generate"<?php disabled( ! $enabled || ! $ready ); ?>><?php esc_html_e( 'Generate drafts', 'restropress' ); ?></button>
			<button type="button" class="button" id="rp-ai-review-accept-all" disabled><?php esc_html_e( 'Accept all', 'restropress' ); ?></button>
			<button type="button" class="button button-primary" id="rp-ai-review-save" disabled><?php esc_html_e( 'Save accepted', 'restropress' ); ?></button>
			<span class="rp-ai-msg" id="rp-ai-review-status" role="status" aria-live="polite"></span>
		</div>
		<div class="rp-ai-progress" id="rp-ai-review-progress" hidden><i></i></div>

		<table class="widefat striped rp-ai-review-table">
			<thead>
				<tr>
					<th scope="col" class="rp-ai-col-accept"><?php esc_html_e( 'Accept', 'restropress' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Menu item', 'restropress' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Short description', 'restropress' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Full description', 'restropress' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $items as $rp_ai_post ) :
					$rp_ai_missing = RPRESS_AI_Admin::missing_fields( $rp_ai_post );
					$rp_ai_terms   = get_the_terms( $rp_ai_post->ID, 'food-category' );
					?>
					<tr class="rp-ai-review-row" data-id="<?php echo esc_attr( $rp_ai_post->ID ); ?>">
						<td class="rp-ai-col-accept">
							<input type="checkbox" class="rp-ai-accept" disabled aria-label="<?php echo esc_attr( sprintf( /* translators: %s: item name */ __( 'Accept drafts for %s', 'restropress' ), $rp_ai_post->post_title ) ); ?>">
						</td>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $rp_ai_post->ID ) ); ?>"><strong><?php echo esc_html( $rp_ai_post->post_title ? $rp_ai_post->post_title : __( '(no title)', 'restropress' ) ); ?></strong></a>
							<?php if ( is_array( $rp_ai_terms ) ) : ?>
								<br><small><?php echo esc_html( implode( ', ', wp_list_pluck( $rp_ai_terms, 'name' ) ) ); ?></small>
							<?php endif; ?>
							<br><small class="rp-ai-row-state"><?php echo esc_html( 'publish' === $rp_ai_post->post_status ? __( 'Published', 'restropress' ) : ucfirst( $rp_ai_post->post_status ) ); ?></small>
						</td>
						<td>
							<?php if ( $rp_ai_missing['excerpt'] ) : ?>
								<textarea class="rp-ai-draft-excerpt large-text" rows="2" disabled placeholder="<?php esc_attr_e( 'Draft appears here', 'restropress' ); ?>"></textarea>
							<?php else : ?>
								<span class="description"><?php echo esc_html( wp_trim_words( $rp_ai_post->post_excerpt, 15 ) ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $rp_ai_missing['description'] ) : ?>
								<textarea class="rp-ai-draft-description large-text" rows="3" disabled placeholder="<?php esc_attr_e( 'Draft appears here', 'restropress' ); ?>"></textarea>
							<?php else : ?>
								<span class="description"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $rp_ai_post->post_content ), 20 ) ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
