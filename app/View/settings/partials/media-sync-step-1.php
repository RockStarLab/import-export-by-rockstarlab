<?php
/**
 * Media Sync Step 1: Scan Folder
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

// Get upload directory for suggestions
$rsl_ie_upload_dir = wp_upload_dir();
$rsl_ie_base_dir   = $rsl_ie_upload_dir['basedir'];
?>

<!-- Step 1: Scan Folder -->
<div class="rsl-ie-card rsl-ie-scan-section">
	<div class="rsl-ie-card-header">
		<h2>
			<span class="dashicons dashicons-search"></span>
			<?php esc_html_e( 'Step 1: Scan Server Folder', 'import-export-by-rockstarlab' ); ?>
		</h2>
		<p class="description">
			<?php esc_html_e( 'Enter the absolute path to scan for media files', 'import-export-by-rockstarlab' ); ?>
		</p>
	</div>

	<div class="rsl-ie-card-body">
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="rsl-ie-folder-path">
						<?php esc_html_e( 'Folder Path', 'import-export-by-rockstarlab' ); ?>
					</label>
				</th>
				<td>
					<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
						<input 
							type="text" 
							id="rsl-ie-folder-path" 
							class="regular-text" 
							placeholder="my-folder"
							value=""
							style="flex: 1;"
						>
						<button type="button" id="rsl-ie-browse-folders-btn" class="button">
							<span class="dashicons dashicons-open-folder"></span>
							<?php esc_html_e( 'Browse', 'import-export-by-rockstarlab' ); ?>
						</button>
					</div>
					<p class="description">
						<?php
						printf(
							/* translators: %s: uploads directory path */
							esc_html__( 'Enter folder name relative to uploads directory. Example: %1$s will scan %2$s', 'import-export-by-rockstarlab' ),
							'<code>ftp-import</code>',
							'<code>' . esc_html( $rsl_ie_base_dir ) . '/ftp-import/</code>'
						);
						?>
						<br>
						<?php esc_html_e( 'Use / for root uploads directory.', 'import-export-by-rockstarlab' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="rsl-ie-scan-recursive">
						<?php esc_html_e( 'Scan Subdirectories', 'import-export-by-rockstarlab' ); ?>
					</label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="rsl-ie-scan-recursive" checked>
						<?php esc_html_e( 'Include files from subdirectories', 'import-export-by-rockstarlab' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="rsl-ie-file-types">
						<?php esc_html_e( 'File Types', 'import-export-by-rockstarlab' ); ?>
					</label>
				</th>
				<td>
					<select id="rsl-ie-file-types" class="regular-text">
						<option value="all"><?php esc_html_e( 'All Media Types', 'import-export-by-rockstarlab' ); ?></option>
						<option value="images"><?php esc_html_e( 'Images Only (jpg, png, gif, etc.)', 'import-export-by-rockstarlab' ); ?></option>
						<option value="videos"><?php esc_html_e( 'Videos Only (mp4, avi, etc.)', 'import-export-by-rockstarlab' ); ?></option>
						<option value="audio"><?php esc_html_e( 'Audio Only (mp3, wav, etc.)', 'import-export-by-rockstarlab' ); ?></option>
						<option value="documents"><?php esc_html_e( 'Documents (pdf, doc, etc.)', 'import-export-by-rockstarlab' ); ?></option>
						<option value="custom"><?php esc_html_e( 'Custom Extensions', 'import-export-by-rockstarlab' ); ?></option>
					</select>

					<div id="rsl-ie-custom-extensions" style="display: none; margin-top: 10px;">
						<input 
							type="text" 
							id="rsl-ie-custom-extensions-input" 
							class="regular-text" 
							placeholder="<?php esc_attr_e( 'jpg, png, pdf, mp4', 'import-export-by-rockstarlab' ); ?>"
						>
						<p class="description">
							<?php esc_html_e( 'Comma-separated list of file extensions (without dots)', 'import-export-by-rockstarlab' ); ?>
						</p>
					</div>
				</td>
			</tr>
		</table>

		<div class="rsl-ie-actions">
			<button type="button" id="rsl-ie-scan-folder-btn" class="button button-primary button-large">
				<span class="dashicons dashicons-search"></span>
				<?php esc_html_e( 'Scan Folder', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>

		<!-- Scan Results -->
		<div id="rsl-ie-scan-results" style="display: none;">
			<hr>
			<h3><?php esc_html_e( 'Scan Results', 'import-export-by-rockstarlab' ); ?></h3>
			<div class="rsl-ie-scan-stats">
				<span class="rsl-ie-stat">
					<strong><?php esc_html_e( 'Total Files:', 'import-export-by-rockstarlab' ); ?></strong>
					<span id="rsl-ie-total-files">0</span>
				</span>
				<span class="rsl-ie-stat">
					<strong><?php esc_html_e( 'Total Size:', 'import-export-by-rockstarlab' ); ?></strong>
					<span id="rsl-ie-total-size">0 B</span>
				</span>
			</div>

			<div class="rsl-ie-file-list-controls">
				<label>
					<input type="checkbox" id="rsl-ie-select-all-files" checked>
					<?php esc_html_e( 'Select All', 'import-export-by-rockstarlab' ); ?>
				</label>
				<span class="rsl-ie-selected-count">
					<span id="rsl-ie-selected-count">0</span> <?php esc_html_e( 'files selected', 'import-export-by-rockstarlab' ); ?>
				</span>
			</div>

			<div id="rsl-ie-file-list" class="rsl-ie-file-list">
				<!-- Files will be populated by JS -->
			</div>
		</div>
	</div>
</div>
