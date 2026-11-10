<?php
/**
 * RestroPress > Settings > General > AI Assistant.
 *
 * Posts `rpress_ai[...]` fields with the normal settings form; saved into the
 * `rpress_ai_settings` option by RPRESS_AI_Admin::save_settings_section().
 * The API key is never printed back: only its last four characters.
 *
 * @var array $settings  RPRESS_AI::get_settings().
 * @var array $status    RPRESS_AI::get_status().
 * @var array $labels    Provider labels.
 * @var array $key_hints Last four characters of each saved key.
 *
 * @package RPRESS\Features\AI
 */

defined( 'ABSPATH' ) || exit;

$rp_ai_provider = $settings['provider'];
$rp_ai_hint     = isset( $key_hints[ $rp_ai_provider ] ) ? $key_hints[ $rp_ai_provider ] : '';
$rp_ai_features = array(
	'writer'   => array( __( 'Write and improve descriptions', 'restropress' ), __( 'Adds "Write with AI" and "Improve" to the description fields of the menu item editor.', 'restropress' ) ),
	'dietary'  => array( __( 'Dietary and allergen suggestions', 'restropress' ), __( 'Pre-selects likely dietary labels (and allergens when available) for you to confirm.', 'restropress' ) ),
	'alt_text' => array( __( 'Image alt text', 'restropress' ), __( 'Drafts alt text for food photos that have none, for accessibility and search.', 'restropress' ) ),
	'bulk'     => array( __( 'Bulk descriptions', 'restropress' ), __( 'Adds "Generate missing descriptions (AI)" to the Menu Items bulk actions, with a review screen.', 'restropress' ) ),
);
?>
<div class="rp-ai-settings" id="rp-ai-settings">
	<p class="rp-ai-intro">
		<?php esc_html_e( 'AI helps you write menu copy, suggest dietary labels and import menus. Everything it writes is shown to you first: nothing is saved or published until you approve it, and prices are never changed.', 'restropress' ); ?>
	</p>

	<div class="rp-ai-status-card <?php echo ! empty( $status['ready'] ) ? 'is-ready' : 'is-off'; ?>">
		<span class="dashicons <?php echo ! empty( $status['ready'] ) ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
		<span id="rp-ai-status-summary">
			<?php
			if ( ! empty( $status['ready'] ) ) {
				/* translators: %s: provider label */
				echo esc_html( sprintf( __( 'Connected: %s', 'restropress' ), $status['provider_label'] ) );
			} else {
				esc_html_e( 'Not connected yet. Choose a provider, add its API key and click Save & test connection.', 'restropress' );
			}
			?>
		</span>
	</div>

	<table class="rp-ai-fields" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'AI features', 'restropress' ); ?></th>
			<td>
				<input type="hidden" name="rpress_ai[enabled]" value="no">
				<label><input type="checkbox" id="rp-ai-enabled" name="rpress_ai[enabled]" value="yes" <?php checked( 'yes', $settings['enabled'] ); ?>> <?php esc_html_e( 'Enable AI features', 'restropress' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="rp-ai-provider"><?php esc_html_e( 'Provider', 'restropress' ); ?></label></th>
			<td>
				<select id="rp-ai-provider" name="rpress_ai[provider]">
					<option value="wordpress" <?php selected( $rp_ai_provider, 'wordpress' ); ?>><?php esc_html_e( 'WordPress AI (built-in)', 'restropress' ); ?></option>
					<option value="openai" <?php selected( $rp_ai_provider, 'openai' ); ?>><?php esc_html_e( 'OpenAI (your key)', 'restropress' ); ?></option>
					<option value="gemini" <?php selected( $rp_ai_provider, 'gemini' ); ?>><?php esc_html_e( 'Google Gemini (your key)', 'restropress' ); ?></option>
					<option value="claude" <?php selected( $rp_ai_provider, 'claude' ); ?>><?php esc_html_e( 'Anthropic Claude (your key)', 'restropress' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'WordPress AI uses the provider connected to WordPress itself (WordPress 7.0+ or a compatible AI plugin). With OpenAI, Gemini or Claude, requests go straight from your site to that provider and are billed to your own API account. RestroPress adds no AI charges.', 'restropress' ); ?></p>
			</td>
		</tr>
		<tr class="rp-ai-key-row"<?php echo 'wordpress' === $rp_ai_provider ? ' hidden' : ''; ?>>
			<th scope="row"><label for="rp-ai-key"><?php esc_html_e( 'API key', 'restropress' ); ?></label></th>
			<td>
				<div class="rp-ai-secret">
					<input type="password" id="rp-ai-key" class="regular-text" name="rpress_ai[api_key]" value="" autocomplete="new-password" data-lpignore="true" data-1p-ignore autocapitalize="none" spellcheck="false"
						placeholder="<?php echo '' !== $rp_ai_hint ? esc_attr( sprintf( /* translators: %s: last 4 characters of the saved key */ __( 'Saved key ending in %s. Enter a new key to replace it.', 'restropress' ), $rp_ai_hint ) ) : esc_attr__( 'Paste your provider API key', 'restropress' ); ?>">
					<button type="button" class="button rp-ai-key-toggle" id="rp-ai-key-toggle" aria-label="<?php esc_attr_e( 'Show API key', 'restropress' ); ?>" aria-pressed="false"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></button>
				</div>
				<label class="rp-ai-remove-key"<?php echo '' === $rp_ai_hint ? ' hidden' : ''; ?>><input type="checkbox" name="rpress_ai[remove_key]" value="1"> <?php esc_html_e( 'Remove the saved key for this provider', 'restropress' ); ?></label>
				<p class="description"><?php esc_html_e( 'Stored in your site database and only sent to the provider you choose. Leave blank to keep the saved key.', 'restropress' ); ?></p>
			</td>
		</tr>
		<tr class="rp-ai-model-row"<?php echo 'wordpress' === $rp_ai_provider ? ' hidden' : ''; ?>>
			<th scope="row"><label for="rp-ai-model"><?php esc_html_e( 'Default model', 'restropress' ); ?></label></th>
			<td>
				<input type="text" id="rp-ai-model" class="regular-text" name="rpress_ai[model]" list="rp-ai-model-list" value="<?php echo esc_attr( $settings['model'] ); ?>" placeholder="<?php echo esc_attr( RPRESS_AI::default_model( $rp_ai_provider ) ); ?>">
				<datalist id="rp-ai-model-list"></datalist>
				<p class="description"><?php esc_html_e( 'Leave blank for the recommended low-cost model shown in the field.', 'restropress' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Connection', 'restropress' ); ?></th>
			<td>
				<button type="button" class="button button-secondary" id="rp-ai-test"><?php esc_html_e( 'Save & test connection', 'restropress' ); ?></button>
				<span id="rp-ai-test-result" class="rp-ai-msg" role="status" aria-live="polite"></span>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Where AI helps', 'restropress' ); ?></th>
			<td>
				<input type="hidden" name="rpress_ai[features][_]" value="1">
				<?php foreach ( $rp_ai_features as $rp_ai_key => $rp_ai_feature ) : ?>
					<label class="rp-ai-feature">
						<input type="checkbox" name="rpress_ai[features][<?php echo esc_attr( $rp_ai_key ); ?>]" value="yes" <?php checked( 'yes', $settings['features'][ $rp_ai_key ] ); ?>>
						<span><b><?php echo esc_html( $rp_ai_feature[0] ); ?></b><small><?php echo esc_html( $rp_ai_feature[1] ); ?></small></span>
					</label>
				<?php endforeach; ?>
				<input type="hidden" name="rpress_ai[generate_descriptions]" value="no">
				<label class="rp-ai-feature">
					<input type="checkbox" name="rpress_ai[generate_descriptions]" value="yes" <?php checked( 'yes', $settings['generate_descriptions'] ); ?>>
					<span><b><?php esc_html_e( 'Menu import: write missing descriptions by default', 'restropress' ); ?></b><small><?php esc_html_e( 'Pre-ticks "Write menu descriptions with AI" on the Import Menu screen.', 'restropress' ); ?></small></span>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Shortcuts', 'restropress' ); ?></th>
			<td>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=fooditem&page=rpress-menu-import' ) ); ?>"><?php esc_html_e( 'Import a menu with AI', 'restropress' ); ?></a>
				<?php if ( 'yes' === $settings['features']['bulk'] ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=fooditem&page=' . RPRESS_AI_Admin::REVIEW_PAGE ) ); ?>"><?php esc_html_e( 'Find items missing descriptions', 'restropress' ); ?></a>
				<?php endif; ?>
			</td>
		</tr>
	</table>
</div>
