<?php
/**
 * Shared menu-import pane (upload / AI / spreadsheet / sample / review).
 * Included by the onboarding wizard and by the standalone Menu Items ->
 * Import screen. Expects \$ai_settings and \$ai_status in scope.
 *
 * @package RPRESS\Admin\Home
 */
defined( 'ABSPATH' ) || exit;
?>
			<section class="rp-ob-pane" data-pane="menu" hidden>
				<div data-sub="choose">
					<h2 class="rp-ob-title"><?php esc_html_e( 'Add your menu', 'restropress' ); ?></h2>
					<p class="rp-ob-sub"><?php esc_html_e( 'Pick the way that fits what you have. Everything stays in review until you approve it.', 'restropress' ); ?></p>
					<div class="rp-ob-tracks">
						<div class="rp-ob-track sel" data-track="ai"><span class="rp-ob-tag"><?php esc_html_e( 'Fastest', 'restropress' ); ?></span><div class="rp-ob-ic">✨</div><h3><?php esc_html_e( 'AI import', 'restropress' ); ?></h3><p><?php esc_html_e( 'A PDF, photo or messy file - AI reads it into items, prices, sizes & add-ons.', 'restropress' ); ?></p></div>
						<div class="rp-ob-track" data-track="csv"><div class="rp-ob-ic">📄</div><h3><?php esc_html_e( 'Spreadsheet', 'restropress' ); ?></h3><p><?php esc_html_e( 'A clean CSV or XLSX. Parsed directly - no AI needed.', 'restropress' ); ?></p></div>
						<div class="rp-ob-track" data-track="sample"><span class="rp-ob-tag rp-ob-tag-g"><?php esc_html_e( 'No data? Start here', 'restropress' ); ?></span><div class="rp-ob-ic">🍽️</div><h3><?php esc_html_e( 'Sample menu', 'restropress' ); ?></h3><p><?php esc_html_e( 'One click loads a ready-made menu so you can see RestroPress working right away.', 'restropress' ); ?></p></div>
					</div>
					<p class="rp-ob-hint rp-help-text" style="margin-top:12px"><?php esc_html_e( 'Prefer a blank slate?', 'restropress' ); ?> <a href="#" class="rp-ob-pick-manual"><?php esc_html_e( 'Start from scratch', 'restropress' ); ?></a>.</p>
				</div>

				<div data-sub="ai" hidden>
					<div class="rp-ob-crumb"><button type="button" class="rp-ob-sub-back"><?php esc_html_e( '← Menu', 'restropress' ); ?></button><span><?php esc_html_e( 'AI import', 'restropress' ); ?></span></div>
					<h2 class="rp-ob-title"><?php esc_html_e( 'Upload your menu', 'restropress' ); ?></h2>
					<p class="rp-ob-sub"><?php esc_html_e( 'PDF, image, CSV or XLSX up to 10 MB. Private & in review until you approve.', 'restropress' ); ?></p>

					<?php
					$ai_provider = isset( $ai_settings['provider'] ) ? $ai_settings['provider'] : 'wordpress';
					$ai_has_key  = ! empty( $ai_settings['api_key'] );
					$wp_ai_ok    = ! empty( $ai_status['wp_ai_configured'] );
					?>
					<div class="rp-ob-two">
						<div class="rp-ob-field">
							<label><?php esc_html_e( 'AI provider', 'restropress' ); ?></label>
							<select id="rp-ob-ai-provider" class="rp-ob-select">
								<option value="wordpress" <?php selected( $ai_provider, 'wordpress' ); ?>><?php esc_html_e( 'WordPress AI (built-in)', 'restropress' ); ?></option>
								<option value="openai" <?php selected( $ai_provider, 'openai' ); ?>><?php esc_html_e( 'OpenAI (your key)', 'restropress' ); ?></option>
								<option value="gemini" <?php selected( $ai_provider, 'gemini' ); ?>><?php esc_html_e( 'Google Gemini (your key)', 'restropress' ); ?></option>
								<option value="claude" <?php selected( $ai_provider, 'claude' ); ?>><?php esc_html_e( 'Anthropic Claude (your key)', 'restropress' ); ?></option>
							</select>
							<p class="rp-ob-hint rp-help-text"><?php esc_html_e( 'Use WordPress’ built-in AI if your site already has an AI provider connected (WordPress 6.8+), or bring your own OpenAI, Gemini, or Claude key.', 'restropress' ); ?></p>
						</div>
						<div class="rp-ob-field" id="rp-ob-ai-key-wrap"<?php echo 'wordpress' === $ai_provider ? ' hidden' : ''; ?>>
							<label for="rp-ob-ai-key"><?php esc_html_e( 'API key', 'restropress' ); ?></label>
							<div class="rp-ob-secret">
								<input type="password" id="rp-ob-ai-key" autocomplete="off" autocapitalize="none" spellcheck="false" name="rp_ai_key_field" placeholder="<?php echo $ai_has_key ? esc_attr__( 'Saved - enter a new key to replace', 'restropress' ) : esc_attr__( 'Paste your provider API key', 'restropress' ); ?>">
								<button type="button" class="rp-ob-secret-toggle" id="rp-ob-ai-key-toggle" aria-label="<?php esc_attr_e( 'Show API key', 'restropress' ); ?>" aria-pressed="false"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></button>
							</div>
							<p class="rp-ob-hint rp-help-text"><?php esc_html_e( 'Stored securely; only used to read your menu.', 'restropress' ); ?></p>
						</div>
					</div>
					<div class="rp-ob-ai-status">
						<button type="button" class="rp-btn rp-btn-secondary rp-ob-btn-sm" id="rp-ob-ai-test"><?php esc_html_e( 'Save & test connection', 'restropress' ); ?></button>
						<span id="rp-ob-ai-status-text" class="rp-ob-ai-msg" data-ready="<?php echo ! empty( $ai_status['ready'] ) ? '1' : '0'; ?>"><?php
							if ( ! empty( $ai_status['ready'] ) ) {
								esc_html_e( 'Configured - test the connection before importing.', 'restropress' );
							} else {
								esc_html_e( 'Not connected yet - choose a provider and test.', 'restropress' );
							}
						?></span>
					</div>

					<label class="rp-ob-aiopt on"><input type="checkbox" id="rp-ob-gendesc" checked><span><b><?php esc_html_e( 'Write menu descriptions with AI', 'restropress' ); ?></b><small><?php esc_html_e( 'Fills a short description for items that have none - you review before publishing.', 'restropress' ); ?></small></span></label>
					<div class="rp-ob-dz" id="rp-ob-dz">
						<input type="file" id="rp-ob-file" accept=".pdf,.jpg,.jpeg,.png,.webp,.csv,.xlsx" multiple hidden>
						<div class="rp-ob-dz-big">⬆️</div><b><?php esc_html_e( 'Drop or choose your menu file(s)', 'restropress' ); ?></b><small id="rp-ob-dzhint"><?php esc_html_e( 'PDF, images, CSV or XLSX up to 10 MB each - select multiple pages at once', 'restropress' ); ?></small>
					</div>
					<div class="rp-ob-parse" id="rp-ob-parse" hidden>
						<div class="rp-ob-parse-label" id="rp-ob-parse-label"><?php esc_html_e( 'Parsing…', 'restropress' ); ?></div>
						<div class="rp-ob-parse-bar"><i id="rp-ob-parse-fill"></i></div>
					</div>
					<label class="rp-ob-consent"><input type="checkbox" id="rp-ob-consent" checked> <?php esc_html_e( 'Menu content may be sent to the AI provider for parsing - I’ll review before publishing.', 'restropress' ); ?></label>
					<p class="rp-ob-hint rp-help-text"><?php esc_html_e( 'No AI set up or can’t read your file?', 'restropress' ); ?> <a href="#" class="rp-ob-pick-csv"><?php esc_html_e( 'Import a spreadsheet', 'restropress' ); ?></a> <?php esc_html_e( 'or', 'restropress' ); ?> <a href="#" class="rp-ob-pick-manual"><?php esc_html_e( 'start manually', 'restropress' ); ?></a>.</p>
				</div>

				<div data-sub="review" hidden>
					<div class="rp-ob-crumb"><button type="button" class="rp-ob-sub-back"><?php esc_html_e( '← Menu', 'restropress' ); ?></button><span><?php esc_html_e( 'Review', 'restropress' ); ?></span></div>
					<h2 class="rp-ob-title"><?php esc_html_e( 'Review before publishing', 'restropress' ); ?></h2>
					<p class="rp-ob-sub"><?php esc_html_e( 'Edit items, prices, sizes, add-ons and dietary labels. Nothing publishes until you save.', 'restropress' ); ?></p>
					<div id="rp-ob-review"></div>
				</div>

				<div data-sub="csv" hidden>
					<div class="rp-ob-crumb"><button type="button" class="rp-ob-sub-back"><?php esc_html_e( '← Menu', 'restropress' ); ?></button><span><?php esc_html_e( 'Spreadsheet', 'restropress' ); ?></span></div>
					<h2 class="rp-ob-title"><?php esc_html_e( 'Import a spreadsheet', 'restropress' ); ?></h2>
					<p class="rp-ob-sub"><?php esc_html_e( 'A clean CSV in the RestroPress layout - parsed directly, no AI. Rows with a matching ID or SKU update the existing item instead of creating a duplicate.', 'restropress' ); ?></p>
					<div class="rp-ob-tmpl">
						<div>
							<b><?php esc_html_e( 'RestroPress menu template', 'restropress' ); ?></b>
							<div class="rp-ob-tmpl-cols"><?php esc_html_e( 'Columns: ID · Name · Categories · Price · Variable Price Label · Description · Short Description · Veg / Non-Veg · Dietary · Tags · Add-ons · Add-on Prices · Max Add-ons · Default Add-ons · Add-ons Required · Featured Image · SKU · Notes · Status · Slug · Date Created · Author · Order Count · Total Revenue', 'restropress' ); ?></div>
							<div class="rp-ob-tmpl-cols"><?php esc_html_e( 'Same layout you get from Export. Worked examples: simple, by-size (variants) and with add-ons. Leave ID blank for new items.', 'restropress' ); ?></div>
						</div>
						<a class="rp-btn rp-btn-secondary" href="<?php echo esc_url( RP_PLUGIN_URL . 'assets/sample/menu-csv-template.csv' ); ?>" download>⬇ CSV</a>
					</div>
					<?php
					// Direct RestroPress CSV importer (RPRESS_Batch_FoodItems_Import via the
					// shared RPRESS_Import JS). This deliberately bypasses AI - AI is only
					// used on the "AI import" track.
					if ( function_exists( 'rpress_menu_csv_importer_form' ) ) {
						rpress_menu_csv_importer_form();
					}
					?>
				</div>

				<div data-sub="sample" hidden>
					<div class="rp-ob-crumb"><button type="button" class="rp-ob-sub-back"><?php esc_html_e( '← Menu', 'restropress' ); ?></button><span><?php esc_html_e( 'Sample menu', 'restropress' ); ?></span></div>
					<h2 class="rp-ob-title"><?php esc_html_e( 'Pick a sample menu to start', 'restropress' ); ?></h2>
					<p class="rp-ob-sub"><?php esc_html_e( 'A complete menu - categories, sizes, add-ons & dietary labels - so you can see how RestroPress works. Edit or remove it anytime.', 'restropress' ); ?></p>
					<div class="rp-ob-tracks rp-ob-tracks-2">
						<div class="rp-ob-track sel" data-sample="cafe"><div class="rp-ob-ic">☕</div><h3><?php esc_html_e( 'Cafe & Bakery', 'restropress' ); ?></h3></div>
						<div class="rp-ob-track" data-sample="pizza"><div class="rp-ob-ic">🍕</div><h3><?php esc_html_e( 'Pizzeria', 'restropress' ); ?></h3></div>
						<div class="rp-ob-track" data-sample="diner"><div class="rp-ob-ic">🍔</div><h3><?php esc_html_e( 'Burger Diner', 'restropress' ); ?></h3></div>
						<div class="rp-ob-track" data-sample="healthy"><div class="rp-ob-ic">🥗</div><h3><?php esc_html_e( 'Healthy Kitchen', 'restropress' ); ?></h3></div>
					</div>
				</div>

				<div data-sub="manual" hidden>
					<div class="rp-ob-crumb"><button type="button" class="rp-ob-sub-back"><?php esc_html_e( '← Menu', 'restropress' ); ?></button><span><?php esc_html_e( 'Manual', 'restropress' ); ?></span></div>
					<h2 class="rp-ob-title"><?php esc_html_e( 'Build your menu by hand', 'restropress' ); ?></h2>
					<p class="rp-ob-sub"><?php esc_html_e( 'Type items into the table. Want a head start? Use the sample-menu path for sizes & add-ons.', 'restropress' ); ?></p>
					<div class="rp-ob-rev"><div class="rp-ob-rev-head"><?php esc_html_e( 'Item', 'restropress' ); ?></div><div id="rp-ob-manualrows"></div></div>
					<button type="button" class="rp-btn rp-btn-secondary" id="rp-ob-addrow">+ <?php esc_html_e( 'Add item', 'restropress' ); ?></button>
				</div>
			</section>
