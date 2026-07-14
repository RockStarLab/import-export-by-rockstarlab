<?php
/**
 * Media Sync Folder Browser Modal
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

// Get upload directory for browser
$rsl_ie_upload_dir = wp_upload_dir();
$rsl_ie_base_dir   = $rsl_ie_upload_dir['basedir'];
?>

<!-- Folder Browser Modal -->
<div id="rsl-ie-folder-browser-modal" class="rsl-ie-modal" style="display: none;">
	<div class="rsl-ie-modal-overlay"></div>
	<div class="rsl-ie-modal-content">
		<div class="rsl-ie-modal-header">
			<h2>
				<span class="dashicons dashicons-category"></span>
				<?php esc_html_e( 'Browse Server Folders', 'import-export-by-rockstarlab' ); ?>
			</h2>
			<button type="button" class="rsl-ie-modal-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="rsl-ie-modal-body">
			<p class="description" style="margin-bottom: 15px; padding: 8px 12px; background: #f0f0f1; border-left: 3px solid #2271b1;">
				<span class="dashicons dashicons-info" style="color: #2271b1;"></span>
				<?php esc_html_e( 'Click to select a folder, double-click to open it.', 'import-export-by-rockstarlab' ); ?>
			</p>

			<div class="rsl-ie-folder-browser-path">
				<span class="dashicons dashicons-admin-home"></span>
				<span id="rsl-ie-current-path"><?php echo esc_html( $rsl_ie_base_dir ); ?></span>
			</div>

			<div id="rsl-ie-folder-browser-error" class="notice notice-error inline" style="display: none; margin: 10px 0;">
				<p id="rsl-ie-folder-browser-error-message"></p>
			</div>

			<div id="rsl-ie-folder-browser-loading" class="rsl-ie-loading" style="display: none;">
				<span class="spinner is-active"></span>
				<?php esc_html_e( 'Loading folders...', 'import-export-by-rockstarlab' ); ?>
			</div>

			<div id="rsl-ie-folder-browser-list" class="rsl-ie-folder-list">
				<!-- Folders will be populated by JS -->
			</div>

			<div id="rsl-ie-folder-browser-empty" style="display: none;">
				<p class="description">
					<?php esc_html_e( 'No subfolders found in this directory.', 'import-export-by-rockstarlab' ); ?>
				</p>
			</div>
		</div>

		<div class="rsl-ie-modal-footer">
			<input 
				type="text" 
				id="rsl-ie-selected-folder-path" 
				class="regular-text" 
				readonly
				placeholder="<?php esc_attr_e( 'No folder selected', 'import-export-by-rockstarlab' ); ?>"
			>
			<button type="button" id="rsl-ie-choose-folder-btn" class="button button-primary" disabled>
				<span class="dashicons dashicons-yes"></span>
				<?php esc_html_e( 'Choose', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button rsl-ie-modal-close">
				<?php esc_html_e( 'Cancel', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
	</div>
</div>
