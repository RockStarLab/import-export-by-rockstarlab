<?php
/**
 * Media Sync Step 1: Scan Folder
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;

// Get upload directory for suggestions
$upload_dir = wp_upload_dir();
$base_dir   = $upload_dir['basedir'];
?>

<!-- Step 1: Scan Folder -->
<div class="aie-card aie-scan-section">
	<div class="aie-card-header">
		<h2>
			<span class="dashicons dashicons-search"></span>
			<?php esc_html_e( 'Step 1: Scan Server Folder', 'amplified-import-export' ); ?>
		</h2>
		<p class="description">
			<?php esc_html_e( 'Enter the absolute path to scan for media files', 'amplified-import-export' ); ?>
		</p>
	</div>

	<div class="aie-card-body">
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="aie-folder-path">
						<?php esc_html_e( 'Folder Path', 'amplified-import-export' ); ?>
					</label>
				</th>
				<td>
					<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
						<input 
							type="text" 
							id="aie-folder-path" 
							class="regular-text" 
							placeholder="my-folder"
							value=""
							style="flex: 1;"
						>
						<button type="button" id="aie-browse-folders-btn" class="button">
							<span class="dashicons dashicons-open-folder"></span>
							<?php esc_html_e( 'Browse', 'amplified-import-export' ); ?>
						</button>
					</div>
					<p class="description">
						<?php
						printf(
							/* translators: %s: uploads directory path */
							esc_html__( 'Enter folder name relative to uploads directory. Example: %1$s will scan %2$s', 'amplified-import-export' ),
							'<code>ftp-import</code>',
							'<code>' . esc_html( $base_dir ) . '/ftp-import/</code>'
						);
						?>
						<br>
						<?php esc_html_e( 'Use / for root uploads directory.', 'amplified-import-export' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="aie-scan-recursive">
						<?php esc_html_e( 'Scan Subdirectories', 'amplified-import-export' ); ?>
					</label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="aie-scan-recursive" checked>
						<?php esc_html_e( 'Include files from subdirectories', 'amplified-import-export' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="aie-file-types">
						<?php esc_html_e( 'File Types', 'amplified-import-export' ); ?>
					</label>
				</th>
				<td>
					<select id="aie-file-types" class="regular-text">
						<option value="all"><?php esc_html_e( 'All Media Types', 'amplified-import-export' ); ?></option>
						<option value="images"><?php esc_html_e( 'Images Only (jpg, png, gif, etc.)', 'amplified-import-export' ); ?></option>
						<option value="videos"><?php esc_html_e( 'Videos Only (mp4, avi, etc.)', 'amplified-import-export' ); ?></option>
						<option value="audio"><?php esc_html_e( 'Audio Only (mp3, wav, etc.)', 'amplified-import-export' ); ?></option>
						<option value="documents"><?php esc_html_e( 'Documents (pdf, doc, etc.)', 'amplified-import-export' ); ?></option>
						<option value="custom"><?php esc_html_e( 'Custom Extensions', 'amplified-import-export' ); ?></option>
					</select>

					<div id="aie-custom-extensions" style="display: none; margin-top: 10px;">
						<input 
							type="text" 
							id="aie-custom-extensions-input" 
							class="regular-text" 
							placeholder="<?php esc_attr_e( 'jpg, png, pdf, mp4', 'amplified-import-export' ); ?>"
						>
						<p class="description">
							<?php esc_html_e( 'Comma-separated list of file extensions (without dots)', 'amplified-import-export' ); ?>
						</p>
					</div>
				</td>
			</tr>
		</table>

		<div class="aie-actions">
			<button type="button" id="aie-scan-folder-btn" class="button button-primary button-large">
				<span class="dashicons dashicons-search"></span>
				<?php esc_html_e( 'Scan Folder', 'amplified-import-export' ); ?>
			</button>
		</div>

		<!-- Scan Results -->
		<div id="aie-scan-results" style="display: none;">
			<hr>
			<h3><?php esc_html_e( 'Scan Results', 'amplified-import-export' ); ?></h3>
			<div class="aie-scan-stats">
				<span class="aie-stat">
					<strong><?php esc_html_e( 'Total Files:', 'amplified-import-export' ); ?></strong>
					<span id="aie-total-files">0</span>
				</span>
				<span class="aie-stat">
					<strong><?php esc_html_e( 'Total Size:', 'amplified-import-export' ); ?></strong>
					<span id="aie-total-size">0 B</span>
				</span>
			</div>

			<div class="aie-file-list-controls">
				<label>
					<input type="checkbox" id="aie-select-all-files" checked>
					<?php esc_html_e( 'Select All', 'amplified-import-export' ); ?>
				</label>
				<span class="aie-selected-count">
					<span id="aie-selected-count">0</span> <?php esc_html_e( 'files selected', 'amplified-import-export' ); ?>
				</span>
			</div>

			<div id="aie-file-list" class="aie-file-list">
				<!-- Files will be populated by JS -->
			</div>
		</div>
	</div>
</div>
