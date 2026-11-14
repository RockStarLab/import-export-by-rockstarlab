<?php
/**
 * Import Step 2: Upload File
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 2: Upload File -->
<div class="rsl-ie-step rsl-ie-step-2" data-step="2">
	<div class="rsl-ie-step-header">
		<h2><?php esc_html_e( 'Step 2: Upload File', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Upload your data file (CSV, XML, JSON, ZIP, XLSX, or ODS)', 'import-export-by-rockstarlab' ); ?></p>
	</div>

	<div class="rsl-ie-step-content">
		<div class="rsl-ie-upload-area" id="rsl-ie-upload-area">
			<div class="rsl-ie-upload-placeholder">
				<span class="dashicons dashicons-upload"></span>
				<h3><?php esc_html_e( 'Drag & Drop your file here', 'import-export-by-rockstarlab' ); ?></h3>
				<p><?php esc_html_e( 'or', 'import-export-by-rockstarlab' ); ?></p>
				<button type="button" class="button button-secondary" id="rsl-ie-select-file">
					<?php esc_html_e( 'Select File', 'import-export-by-rockstarlab' ); ?>
				</button>
				<input type="file" id="rsl-ie-file-input" accept=".csv,.json,.xml,.xlsx,.ods,.zip" style="display:none;">
				<p class="description">
					<?php esc_html_e( 'Supported formats: CSV, JSON, XML, XLSX, ODS, or ZIP containing one supported import file', 'import-export-by-rockstarlab' ); ?><br>
					<?php esc_html_e( 'No file size limit - large files supported via chunked upload', 'import-export-by-rockstarlab' ); ?>
				</p>
			</div>

			<!-- Import from a URL -->
			<div class="rsl-ie-url-import">
				<label for="rsl-ie-file-url"><?php esc_html_e( 'Or import a file from a URL', 'import-export-by-rockstarlab' ); ?></label>
				<div class="rsl-ie-url-import-row">
					<input type="url" id="rsl-ie-file-url" class="regular-text" placeholder="https://example.com/products.csv">
					<button type="button" class="button button-secondary" id="rsl-ie-fetch-url"><?php esc_html_e( 'Fetch File', 'import-export-by-rockstarlab' ); ?></button>
				</div>
				<p class="description"><?php esc_html_e( 'A direct link to a CSV, XML, JSON, XLSX or ODS file, such as a supplier price list or product feed, or the link of a Google Sheet shared with "Anyone with the link". When you schedule this import, the file is downloaded again on every run.', 'import-export-by-rockstarlab' ); ?></p>
			</div>

			<!-- Import from a saved FTP/SFTP server -->
			<?php $rsl_ie_remote_servers = \RockStarLab\ImportExport\Service\Remote_File_Service::servers(); ?>
			<div class="rsl-ie-url-import rsl-ie-remote-import">
				<label for="rsl-ie-remote-server"><?php esc_html_e( 'Or import a file from an FTP or SFTP server', 'import-export-by-rockstarlab' ); ?></label>
				<?php if ( $rsl_ie_remote_servers ) : ?>
					<div class="rsl-ie-url-import-row">
						<select id="rsl-ie-remote-server">
							<?php foreach ( $rsl_ie_remote_servers as $rsl_ie_remote ) : ?>
								<option value="<?php echo esc_attr( $rsl_ie_remote['id'] ); ?>"><?php echo esc_html( $rsl_ie_remote['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="text" id="rsl-ie-remote-path" class="regular-text code" placeholder="/feeds/products.csv">
						<button type="button" class="button button-secondary" id="rsl-ie-remote-browse"><?php esc_html_e( 'Browse', 'import-export-by-rockstarlab' ); ?></button>
						<button type="button" class="button button-secondary" id="rsl-ie-remote-fetch"><?php esc_html_e( 'Fetch File', 'import-export-by-rockstarlab' ); ?></button>
					</div>
					<div class="rsl-ie-remote-listing" style="display:none;"></div>
					<p class="description"><?php esc_html_e( 'When you schedule this import, the current file is downloaded from the server on every run.', 'import-export-by-rockstarlab' ); ?></p>
				<?php else : ?>
					<p class="description">
						<?php esc_html_e( 'Save the server once, then pick files from it here.', 'import-export-by-rockstarlab' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-servers&add=1' ) ); ?>"><?php esc_html_e( 'Add a server', 'import-export-by-rockstarlab' ); ?></a>
					</p>
				<?php endif; ?>
			</div>

			<!-- Upload Progress Bar -->
			<div class="rsl-ie-upload-progress" style="display:none;">
				<div class="rsl-ie-upload-status">
					<span class="dashicons dashicons-upload"></span>
					<span class="rsl-ie-upload-status-text"><?php esc_html_e( 'Uploading...', 'import-export-by-rockstarlab' ); ?></span>
				</div>
				<div class="rsl-ie-progress-bar">
					<div class="rsl-ie-progress-bar-fill" style="width: 0%;"></div>
				</div>
				<div class="rsl-ie-upload-details">
					<span class="rsl-ie-upload-percentage">0%</span>
					<span class="rsl-ie-upload-speed"></span>
				</div>
			</div>
		</div>

		<div class="rsl-ie-file-info" style="display:none;">
			<div class="rsl-ie-file-details">
				<span class="dashicons dashicons-media-document"></span>
				<div class="rsl-ie-file-meta">
					<strong class="rsl-ie-file-name"></strong>
					<span class="rsl-ie-file-size"></span>
					<span class="rsl-ie-file-format"></span>
					<span class="rsl-ie-file-conversion" style="display:none;"></span>
				</div>
				<button type="button" class="button button-link-delete rsl-ie-remove-file">
					<span class="dashicons dashicons-no"></span>
				</button>
			</div>
		</div>

		<div class="rsl-ie-format-options" style="display:none;">
			<h3><?php esc_html_e( 'Format Options', 'import-export-by-rockstarlab' ); ?></h3>
			
			<div class="rsl-ie-csv-options" style="display:none;">
				<label>
					<?php esc_html_e( 'Delimiter', 'import-export-by-rockstarlab' ); ?>
					<select name="csv_delimiter" id="csv_delimiter" class="regular-text">
						<option value=",">,<?php esc_html_e( ' (Comma)', 'import-export-by-rockstarlab' ); ?></option>
						<option value=";">; <?php esc_html_e( ' (Semicolon)', 'import-export-by-rockstarlab' ); ?></option>
						<option value="tab">	<?php esc_html_e( ' (Tab)', 'import-export-by-rockstarlab' ); ?></option>
						<option value="|">| <?php esc_html_e( ' (Pipe)', 'import-export-by-rockstarlab' ); ?></option>
						<option value="custom"><?php esc_html_e( 'Custom', 'import-export-by-rockstarlab' ); ?></option>
					</select>
				</label>

				<label>
					<?php esc_html_e( 'File encoding', 'import-export-by-rockstarlab' ); ?>
					<select name="csv_encoding" id="csv_encoding" class="regular-text">
						<option value="auto"><?php esc_html_e( 'Detect automatically', 'import-export-by-rockstarlab' ); ?></option>
						<option value="UTF-8">UTF-8</option>
						<option value="Windows-1252"><?php esc_html_e( 'Windows-1252 (Western European)', 'import-export-by-rockstarlab' ); ?></option>
						<option value="Windows-1250"><?php esc_html_e( 'Windows-1250 (Central European)', 'import-export-by-rockstarlab' ); ?></option>
						<option value="Windows-1251"><?php esc_html_e( 'Windows-1251 (Cyrillic)', 'import-export-by-rockstarlab' ); ?></option>
						<option value="ISO-8859-1"><?php esc_html_e( 'ISO-8859-1 (Latin-1)', 'import-export-by-rockstarlab' ); ?></option>
						<option value="ISO-8859-2"><?php esc_html_e( 'ISO-8859-2 (Latin-2)', 'import-export-by-rockstarlab' ); ?></option>
						<option value="ISO-8859-15"><?php esc_html_e( 'ISO-8859-15 (Latin-9)', 'import-export-by-rockstarlab' ); ?></option>
						<option value="UTF-16LE">UTF-16 LE</option>
						<option value="UTF-16BE">UTF-16 BE</option>
					</select>
				</label>

				<label class="rsl-ie-custom-delimiter-wrapper" style="display:none;">
					<?php esc_html_e( 'Custom Delimiter', 'import-export-by-rockstarlab' ); ?>
						<input type="text" name="csv_custom_delimiter" id="csv_custom_delimiter" class="regular-text" placeholder="<?php esc_attr_e( 'Enter custom delimiter (any string)', 'import-export-by-rockstarlab' ); ?>">
				</label>
				<label>
					<input type="checkbox" name="csv_has_header" checked>
					<?php esc_html_e( 'First row contains column names', 'import-export-by-rockstarlab' ); ?>
				</label>
			</div>
		</div>

		<div class="rsl-ie-step-actions">
			<button type="button" class="button button-secondary rsl-ie-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary button-large rsl-ie-next-step" disabled>
				<?php esc_html_e( 'Next Step', 'import-export-by-rockstarlab' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
