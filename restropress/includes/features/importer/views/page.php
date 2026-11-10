<?php
/**
 * "Switch to RestroPress" screen.
 *
 * Steps: 1 choose source, 2 upload or confirm, 3 preview (dry run),
 * 4 import with progress, 5 summary with undo. importer.js drives the steps.
 *
 * @package RestroPress/Importer
 * @since   3.5
 *
 * @var array  $sources  Registered sources.
 * @var array  $recent   Recent jobs.
 * @var string $selected Preselected source id.
 * @var string $ai_url   AI menu import URL.
 */

defined( 'ABSPATH' ) || exit;

$max_upload    = size_format( rpress_importer_max_upload_size() );
$status_labels = array(
	'running' => __( 'Not finished', 'restropress' ),
	'done'    => __( 'Imported', 'restropress' ),
	'undoing' => __( 'Undo not finished', 'restropress' ),
	'undone'  => __( 'Undone', 'restropress' ),
);
?>
<div class="wrap rp-admin-scope rpi-wrap">
	<div class="rp-page-header">
		<div class="rp-page-header-titles">
			<p class="rp-page-eyebrow"><?php esc_html_e( 'Menu Items', 'restropress' ); ?></p>
			<h1 class="wp-heading-inline rp-page-title"><?php esc_html_e( 'Switch to RestroPress', 'restropress' ); ?></h1>
			<p class="rp-page-subtitle"><?php esc_html_e( 'Bring your menu over from the system you are leaving. You see everything before it is saved, and you can undo the import later.', 'restropress' ); ?></p>
		</div>
		<div class="rp-page-actions">
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=fooditem' ) ); ?>" class="button rp-btn rp-btn-secondary"><?php esc_html_e( 'Back to Menu Items', 'restropress' ); ?></a>
		</div>
	</div>
	<hr class="wp-header-end">

	<ol class="rpi-steps" aria-label="<?php esc_attr_e( 'Import steps', 'restropress' ); ?>">
		<li data-step="source" class="is-active"><?php esc_html_e( 'Source', 'restropress' ); ?></li>
		<li data-step="upload"><?php esc_html_e( 'Upload', 'restropress' ); ?></li>
		<li data-step="preview"><?php esc_html_e( 'Preview', 'restropress' ); ?></li>
		<li data-step="run"><?php esc_html_e( 'Import', 'restropress' ); ?></li>
		<li data-step="summary"><?php esc_html_e( 'Done', 'restropress' ); ?></li>
	</ol>

	<div class="rpi-notice" id="rpi-error" role="alert" hidden></div>

	<!-- Step 1: source -->
	<section class="rpi-panel" data-panel="source" data-selected="<?php echo esc_attr( $selected ); ?>">
		<h2><?php esc_html_e( 'Where is your menu now?', 'restropress' ); ?></h2>
		<div class="rpi-sources">
			<?php foreach ( $sources as $source_id => $source ) : ?>
				<?php $available = rpress_import_source_available( $source ); ?>
				<button type="button" class="rpi-source<?php echo $available ? '' : ' is-disabled'; ?>"
					data-source="<?php echo esc_attr( $source_id ); ?>"
					data-accepts="<?php echo esc_attr( implode( ',', $source['accepts'] ) ); ?>"
					<?php disabled( ! $available ); ?>>
					<span class="rpi-source-title"><?php echo esc_html( $source['label'] ); ?></span>
					<span class="rpi-source-desc"><?php echo esc_html( $source['description'] ); ?></span>
					<?php if ( 'woocommerce' === $source_id && $available ) : ?>
						<span class="rpi-source-meta">
							<?php
							$count = RPress_Import_Source_WooCommerce::product_count();
							/* translators: %d: number of products */
							echo esc_html( sprintf( _n( '%d product found on this site', '%d products found on this site', $count, 'restropress' ), $count ) );
							?>
						</span>
					<?php elseif ( ! $available ) : ?>
						<span class="rpi-source-meta"><?php esc_html_e( 'Not available on this site', 'restropress' ); ?></span>
					<?php endif; ?>
				</button>
			<?php endforeach; ?>
			<a class="rpi-source rpi-source--ai" href="<?php echo esc_url( $ai_url ); ?>">
				<span class="rpi-source-title"><?php esc_html_e( 'Something else (PDF, photo or menu link)', 'restropress' ); ?></span>
				<span class="rpi-source-desc"><?php esc_html_e( 'Use AI menu import when your system has no export we can read.', 'restropress' ); ?></span>
			</a>
		</div>

		<?php if ( $recent ) : ?>
			<h2 class="rpi-recent-title"><?php esc_html_e( 'Recent imports', 'restropress' ); ?></h2>
			<table class="widefat striped rpi-recent">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'restropress' ); ?></th>
						<th><?php esc_html_e( 'Source', 'restropress' ); ?></th>
						<th><?php esc_html_e( 'File', 'restropress' ); ?></th>
						<th><?php esc_html_e( 'Result', 'restropress' ); ?></th>
						<th><?php esc_html_e( 'Status', 'restropress' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent as $job ) : ?>
						<tr>
							<td><?php echo esc_html( $job['date'] ); ?></td>
							<td><?php echo esc_html( $job['source'] ); ?></td>
							<td><?php echo esc_html( $job['label'] ); ?></td>
							<td>
								<?php
								/* translators: 1: created, 2: updated, 3: skipped */
								echo esc_html( sprintf( __( '%1$d created, %2$d updated, %3$d skipped', 'restropress' ), $job['counts']['created'], $job['counts']['updated'], $job['counts']['skipped'] ) );
								?>
							</td>
							<td><span class="rpi-status rpi-status--<?php echo esc_attr( $job['status'] ); ?>"><?php echo esc_html( isset( $status_labels[ $job['status'] ] ) ? $status_labels[ $job['status'] ] : $job['status'] ); ?></span></td>
							<td class="rpi-recent-actions">
								<?php if ( in_array( $job['status'], array( 'done', 'running', 'undoing' ), true ) ) : ?>
									<button type="button" class="button-link rpi-undo-recent" data-job="<?php echo esc_attr( $job['id'] ); ?>"><?php esc_html_e( 'Undo this import', 'restropress' ); ?></button>
								<?php endif; ?>
								<?php if ( 'running' === $job['status'] ) : ?>
									<button type="button" class="button-link rpi-resume" data-job="<?php echo esc_attr( $job['id'] ); ?>"><?php esc_html_e( 'Resume', 'restropress' ); ?></button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</section>

	<!-- Step 2: upload / confirm -->
	<section class="rpi-panel" data-panel="upload" hidden>
		<h2 id="rpi-upload-title"></h2>
		<?php foreach ( $sources as $source_id => $source ) : ?>
			<div class="rpi-help" data-help="<?php echo esc_attr( $source_id ); ?>" hidden><?php echo wp_kses_post( $source['help'] ); ?></div>
		<?php endforeach; ?>
		<form id="rpi-upload-form" class="rpi-upload" enctype="multipart/form-data">
			<div class="rpi-file" id="rpi-file-wrap">
				<label for="rpi-file"><?php esc_html_e( 'Import file', 'restropress' ); ?></label>
				<input type="file" id="rpi-file" name="file">
				<p class="description">
					<?php
					/* translators: %s: max upload size */
					echo esc_html( sprintf( __( 'Maximum file size: %s. The file is read once and not kept on the server.', 'restropress' ), $max_upload ) );
					?>
				</p>
			</div>
			<p class="rpi-actions">
				<button type="button" class="button rp-btn rp-btn-secondary" data-back="source"><?php esc_html_e( 'Back', 'restropress' ); ?></button>
				<button type="submit" class="button button-primary rp-btn rp-btn-primary" id="rpi-read"><?php esc_html_e( 'Preview import', 'restropress' ); ?></button>
			</p>
		</form>
	</section>

	<!-- Step 3: preview -->
	<section class="rpi-panel" data-panel="preview" hidden>
		<h2><?php esc_html_e( 'Preview', 'restropress' ); ?></h2>
		<p class="rpi-lead"><?php esc_html_e( 'Nothing has been saved to your menu yet. Check the rows and warnings, pick how to merge, then import.', 'restropress' ); ?></p>
		<div class="rpi-counts" id="rpi-counts"></div>
		<ul class="rpi-warnings" id="rpi-global-warnings" hidden></ul>

		<div class="rpi-options">
			<fieldset>
				<legend><?php esc_html_e( 'Your current menu', 'restropress' ); ?></legend>
				<label><input type="radio" name="rpi-mode" value="add" checked> <?php esc_html_e( 'Add to it', 'restropress' ); ?></label>
				<label><input type="radio" name="rpi-mode" value="replace"> <?php esc_html_e( 'Replace it (current items move to the trash; undo brings them back)', 'restropress' ); ?></label>
			</fieldset>
			<fieldset id="rpi-dupes">
				<legend><?php esc_html_e( 'Items with the same name', 'restropress' ); ?> <span id="rpi-existing-count"></span></legend>
				<label><input type="radio" name="rpi-duplicates" value="skip" checked> <?php esc_html_e( 'Skip them', 'restropress' ); ?></label>
				<label><input type="radio" name="rpi-duplicates" value="update"> <?php esc_html_e( 'Update them (prices, sizes, add-ons, description)', 'restropress' ); ?></label>
			</fieldset>
			<fieldset id="rpi-settings-box" hidden>
				<legend><?php esc_html_e( 'Store settings', 'restropress' ); ?></legend>
				<ul id="rpi-settings-list"></ul>
				<label><input type="checkbox" id="rpi-settings" value="1"> <?php esc_html_e( 'Also import these settings', 'restropress' ); ?></label>
			</fieldset>
			<fieldset id="rpi-customers-box" hidden>
				<legend><?php esc_html_e( 'Customers', 'restropress' ); ?></legend>
				<p class="description"><?php esc_html_e( 'Only import customers who agreed to hear from you. RestroPress does not send them any email; existing customers are left as they are.', 'restropress' ); ?></p>
				<label><input type="checkbox" id="rpi-customers" value="1"> <span id="rpi-customers-label"></span></label>
			</fieldset>
		</div>

		<details class="rpi-map">
			<summary><?php esc_html_e( 'How fields are mapped', 'restropress' ); ?></summary>
			<table class="widefat striped"><tbody id="rpi-field-map"></tbody></table>
		</details>

		<div class="rpi-table-wrap">
			<table class="widefat striped rpi-preview" id="rpi-preview">
				<thead><tr></tr></thead>
				<tbody></tbody>
			</table>
		</div>

		<p class="rpi-actions">
			<button type="button" class="button rp-btn rp-btn-secondary" id="rpi-discard"><?php esc_html_e( 'Cancel', 'restropress' ); ?></button>
			<button type="button" class="button button-primary rp-btn rp-btn-primary" id="rpi-start"></button>
		</p>
	</section>

	<!-- Step 4: progress -->
	<section class="rpi-panel" data-panel="run" hidden>
		<h2 id="rpi-run-title"><?php esc_html_e( 'Importing', 'restropress' ); ?></h2>
		<div class="rpi-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span id="rpi-bar"></span></div>
		<p id="rpi-progress-text" aria-live="polite"></p>
		<p class="description"><?php esc_html_e( 'Keep this page open until the import finishes.', 'restropress' ); ?></p>
	</section>

	<!-- Step 5: summary -->
	<section class="rpi-panel" data-panel="summary" hidden>
		<h2 id="rpi-summary-title"></h2>
		<div class="rpi-counts" id="rpi-result"></div>
		<details class="rpi-log" open>
			<summary><?php esc_html_e( 'Import log', 'restropress' ); ?></summary>
			<ul id="rpi-log"></ul>
		</details>
		<p class="rpi-actions">
			<a class="button button-primary rp-btn rp-btn-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=fooditem' ) ); ?>"><?php esc_html_e( 'View menu items', 'restropress' ); ?></a>
			<button type="button" class="button rp-btn rp-btn-secondary" id="rpi-undo"><?php esc_html_e( 'Undo this import', 'restropress' ); ?></button>
			<a class="button rp-btn rp-btn-secondary" href="<?php echo esc_url( rpress_importer_url() ); ?>"><?php esc_html_e( 'Start another import', 'restropress' ); ?></a>
		</p>
	</section>
</div>
