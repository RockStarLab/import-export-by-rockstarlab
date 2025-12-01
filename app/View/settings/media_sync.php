<?php
/**
 * Media Sync Settings Page
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;

// Get upload directory for suggestions
$upload_dir = wp_upload_dir();
$base_dir   = $upload_dir['basedir'];
?>

<div id="wp-aie-media-sync" class="wp-advanced-import-export wrap">
	<h1><?php esc_html_e( 'Media Sync', 'wp-advanced-import-export' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Synchronize media files from server folders to WordPress Media Library', 'wp-advanced-import-export' ); ?>
	</p>

	<div class="aie-media-sync-container">
		
		<!-- Step 1: Scan Folder -->
		<div class="aie-card aie-scan-section">
			<div class="aie-card-header">
				<h2>
					<span class="dashicons dashicons-search"></span>
					<?php esc_html_e( 'Step 1: Scan Server Folder', 'wp-advanced-import-export' ); ?>
				</h2>
				<p class="description">
					<?php esc_html_e( 'Enter the absolute path to scan for media files', 'wp-advanced-import-export' ); ?>
				</p>
			</div>

			<div class="aie-card-body">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="aie-folder-path">
								<?php esc_html_e( 'Folder Path', 'wp-advanced-import-export' ); ?>
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
									<?php esc_html_e( 'Browse', 'wp-advanced-import-export' ); ?>
								</button>
							</div>
							<p class="description">
								<?php
								printf(
									/* translators: %s: uploads directory path */
									esc_html__( 'Enter folder name relative to uploads directory. Example: %1$s will scan %2$s', 'wp-advanced-import-export' ),
									'<code>ftp-import</code>',
									'<code>' . esc_html( $base_dir ) . '/ftp-import/</code>'
								);
								?>
								<br>
								<?php esc_html_e( 'Use / for root uploads directory.', 'wp-advanced-import-export' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="aie-scan-recursive">
								<?php esc_html_e( 'Scan Subdirectories', 'wp-advanced-import-export' ); ?>
							</label>
						</th>
						<td>
							<label>
								<input type="checkbox" id="aie-scan-recursive" checked>
								<?php esc_html_e( 'Include files from subdirectories', 'wp-advanced-import-export' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="aie-file-types">
								<?php esc_html_e( 'File Types', 'wp-advanced-import-export' ); ?>
							</label>
						</th>
						<td>
							<select id="aie-file-types" class="regular-text">
								<option value="all"><?php esc_html_e( 'All Media Types', 'wp-advanced-import-export' ); ?></option>
								<option value="images"><?php esc_html_e( 'Images Only (jpg, png, gif, etc.)', 'wp-advanced-import-export' ); ?></option>
								<option value="videos"><?php esc_html_e( 'Videos Only (mp4, avi, etc.)', 'wp-advanced-import-export' ); ?></option>
								<option value="audio"><?php esc_html_e( 'Audio Only (mp3, wav, etc.)', 'wp-advanced-import-export' ); ?></option>
								<option value="documents"><?php esc_html_e( 'Documents (pdf, doc, etc.)', 'wp-advanced-import-export' ); ?></option>
								<option value="custom"><?php esc_html_e( 'Custom Extensions', 'wp-advanced-import-export' ); ?></option>
							</select>

							<div id="aie-custom-extensions" style="display: none; margin-top: 10px;">
								<input 
									type="text" 
									id="aie-custom-extensions-input" 
									class="regular-text" 
									placeholder="<?php esc_attr_e( 'jpg, png, pdf, mp4', 'wp-advanced-import-export' ); ?>"
								>
								<p class="description">
									<?php esc_html_e( 'Comma-separated list of file extensions (without dots)', 'wp-advanced-import-export' ); ?>
								</p>
							</div>
						</td>
					</tr>
				</table>

				<div class="aie-actions">
					<button type="button" id="aie-scan-folder-btn" class="button button-primary button-large">
						<span class="dashicons dashicons-search"></span>
						<?php esc_html_e( 'Scan Folder', 'wp-advanced-import-export' ); ?>
					</button>
				</div>

				<!-- Scan Results -->
				<div id="aie-scan-results" style="display: none;">
					<hr>
					<h3><?php esc_html_e( 'Scan Results', 'wp-advanced-import-export' ); ?></h3>
					<div class="aie-scan-stats">
						<span class="aie-stat">
							<strong><?php esc_html_e( 'Total Files:', 'wp-advanced-import-export' ); ?></strong>
							<span id="aie-total-files">0</span>
						</span>
						<span class="aie-stat">
							<strong><?php esc_html_e( 'Total Size:', 'wp-advanced-import-export' ); ?></strong>
							<span id="aie-total-size">0 B</span>
						</span>
					</div>

					<div class="aie-file-list-controls">
						<label>
							<input type="checkbox" id="aie-select-all-files" checked>
							<?php esc_html_e( 'Select All', 'wp-advanced-import-export' ); ?>
						</label>
						<span class="aie-selected-count">
							<span id="aie-selected-count">0</span> <?php esc_html_e( 'files selected', 'wp-advanced-import-export' ); ?>
						</span>
					</div>

					<div id="aie-file-list" class="aie-file-list">
						<!-- Files will be populated by JS -->
					</div>
				</div>
			</div>
		</div>

		<!-- Step 2: Sync Options -->
		<div class="aie-card aie-options-section" id="aie-sync-options" style="display: none;">
			<div class="aie-card-header">
				<h2>
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Step 2: Sync Options', 'wp-advanced-import-export' ); ?>
				</h2>
			</div>

			<div class="aie-card-body">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="aie-duplicate-check">
								<?php esc_html_e( 'Duplicate Detection', 'wp-advanced-import-export' ); ?>
							</label>
						</th>
						<td>
							<select id="aie-duplicate-check" class="regular-text">
								<option value="hash"><?php esc_html_e( 'MD5 Hash (most accurate)', 'wp-advanced-import-export' ); ?></option>
								<option value="filename"><?php esc_html_e( 'Filename Match', 'wp-advanced-import-export' ); ?></option>
								<option value="filesize"><?php esc_html_e( 'Filesize + Filename', 'wp-advanced-import-export' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'How to detect if file already exists in media library', 'wp-advanced-import-export' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="aie-duplicate-handling">
								<?php esc_html_e( 'Duplicate Handling', 'wp-advanced-import-export' ); ?>
							</label>
						</th>
						<td>
							<select id="aie-duplicate-handling" class="regular-text">
								<option value="skip"><?php esc_html_e( 'Skip Duplicates', 'wp-advanced-import-export' ); ?></option>
								<option value="overwrite"><?php esc_html_e( 'Overwrite Existing', 'wp-advanced-import-export' ); ?></option>
								<option value="rename"><?php esc_html_e( 'Rename and Import', 'wp-advanced-import-export' ); ?></option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="aie-copy-files">
								<?php esc_html_e( 'File Operation', 'wp-advanced-import-export' ); ?>
							</label>
						</th>
						<td>
							<select id="aie-copy-files" class="regular-text">
								<option value="keep"><?php esc_html_e( 'Keep in current directory', 'wp-advanced-import-export' ); ?></option>
								<option value="copy"><?php esc_html_e( 'Copy Files (keep originals)', 'wp-advanced-import-export' ); ?></option>
								<option value="move"><?php esc_html_e( 'Move Files (delete originals)', 'wp-advanced-import-export' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Choose how to handle files during import', 'wp-advanced-import-export' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="aie-batch-size">
								<?php esc_html_e( 'Batch Size', 'wp-advanced-import-export' ); ?>
							</label>
						</th>
						<td>
							<input type="number" id="aie-batch-size" class="small-text" value="3" min="1" max="100" step="1">
							<p class="description">
								<?php esc_html_e( 'Number of files to process per batch. Lower values show more progress updates but take longer.', 'wp-advanced-import-export' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="aie-rml-integration">
								<?php esc_html_e( 'Real Media Library', 'wp-advanced-import-export' ); ?>
								<?php if ( ! waie_fs()->can_use_premium_code() ) : ?>
									<span class="aie-premium-badge" title="<?php esc_attr_e( 'Premium Feature', 'wp-advanced-import-export' ); ?>">
										<span class="dashicons dashicons-star-filled"></span>
										<?php esc_html_e( 'PRO', 'wp-advanced-import-export' ); ?>
									</span>
								<?php endif; ?>
							</label>
						</th>
						<td>
							<?php if ( waie_fs()->can_use_premium_code() ) : ?>
								<label>
									<input type="checkbox" id="aie-rml-integration">
									<?php printf( esc_html__( 'Create virtual folders in %s plugin', 'wp-advanced-import-export' ), '<a href="https://devowl.io/wordpress-real-media-library/" target="_blank">Real Media Library</a>' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Automatically create folders in Real Media Library and organize imported files based on their folder structure.', 'wp-advanced-import-export' ); ?>
								</p>
							<?php else : ?>
								<p class="description">
									<?php
									printf(
										/* translators: %s: upgrade link */
										esc_html__( 'This feature requires a premium subscription. %s to unlock Real Media Library integration.', 'wp-advanced-import-export' ),
										'<a href="' . esc_url( waie_fs()->get_upgrade_url() ) . '" target="_blank">' . esc_html__( 'Upgrade now', 'wp-advanced-import-export' ) . '</a>'
									);
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<div class="aie-actions">
					<button type="button" id="aie-start-sync-btn" class="button button-primary button-large">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Start Synchronization', 'wp-advanced-import-export' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Step 3: Progress -->
		<div class="aie-card aie-progress-section" id="aie-sync-progress-section" style="display: none;">
			<div class="aie-card-header">
				<h2>
					<span class="dashicons dashicons-update aie-spin"></span>
					<?php esc_html_e( 'Synchronization in Progress', 'wp-advanced-import-export' ); ?>
				</h2>
			</div>

			<div class="aie-card-body">
				<div class="aie-progress-bar-container">
					<div class="aie-progress-bar">
						<div id="aie-progress-fill" class="aie-progress-fill" style="width: 0%"></div>
					</div>
					<div class="aie-progress-text">
						<span id="aie-progress-percentage">0%</span>
						<span id="aie-progress-status"><?php esc_html_e( 'Starting...', 'wp-advanced-import-export' ); ?></span>
					</div>
				</div>

				<div class="aie-sync-stats">
					<div class="aie-stat-item">
						<span class="aie-stat-label"><?php esc_html_e( 'Processed:', 'wp-advanced-import-export' ); ?></span>
						<span id="aie-stat-processed" class="aie-stat-value">0</span>
					</div>
					<div class="aie-stat-item aie-stat-success">
						<span class="aie-stat-label"><?php esc_html_e( 'Success:', 'wp-advanced-import-export' ); ?></span>
						<span id="aie-stat-success" class="aie-stat-value">0</span>
					</div>
					<div class="aie-stat-item aie-stat-skipped">
						<span class="aie-stat-label"><?php esc_html_e( 'Skipped:', 'wp-advanced-import-export' ); ?></span>
						<span id="aie-stat-skipped" class="aie-stat-value">0</span>
					</div>
					<div class="aie-stat-item aie-stat-failed">
						<span class="aie-stat-label"><?php esc_html_e( 'Failed:', 'wp-advanced-import-export' ); ?></span>
						<span id="aie-stat-failed" class="aie-stat-value">0</span>
					</div>
				</div>

				<div class="aie-progress-actions">
					<button type="button" id="aie-pause-sync-btn" class="button">
						<span class="dashicons dashicons-controls-pause"></span>
						<?php esc_html_e( 'Pause', 'wp-advanced-import-export' ); ?>
					</button>
					<button type="button" id="aie-cancel-sync-btn" class="button">
						<span class="dashicons dashicons-no"></span>
						<?php esc_html_e( 'Cancel', 'wp-advanced-import-export' ); ?>
					</button>
				</div>

				<!-- Error Log -->
				<div id="aie-error-log" style="display: none;">
					<hr>
					<h3><?php esc_html_e( 'Errors', 'wp-advanced-import-export' ); ?></h3>
					<ul id="aie-error-list" class="aie-error-list"></ul>
				</div>
			</div>
		</div>

		<!-- Completion Message -->
		<div class="aie-card aie-completion-section" id="aie-sync-completion" style="display: none;">
			<div class="aie-card-header">
				<h2>
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Synchronization Complete', 'wp-advanced-import-export' ); ?>
				</h2>
			</div>

			<div class="aie-card-body">
				<div class="notice notice-success inline">
					<p id="aie-completion-message">
						<?php esc_html_e( 'Media synchronization has been completed successfully!', 'wp-advanced-import-export' ); ?>
					</p>
				</div>

				<div class="aie-completion-stats">
					<!-- Stats will be populated by JS -->
				</div>

				<div class="aie-actions">
					<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="button button-primary">
						<span class="dashicons dashicons-admin-media"></span>
						<?php esc_html_e( 'View Media Library', 'wp-advanced-import-export' ); ?>
					</a>
					<button type="button" id="aie-sync-another-btn" class="button">
						<span class="dashicons dashicons-image-rotate"></span>
						<?php esc_html_e( 'Sync Another Folder', 'wp-advanced-import-export' ); ?>
					</button>
				</div>
			</div>
		</div>

	</div>

	<!-- Folder Browser Modal -->
	<div id="aie-folder-browser-modal" class="aie-modal" style="display: none;">
		<div class="aie-modal-overlay"></div>
		<div class="aie-modal-content">
			<div class="aie-modal-header">
				<h2>
					<span class="dashicons dashicons-category"></span>
					<?php esc_html_e( 'Browse Server Folders', 'wp-advanced-import-export' ); ?>
				</h2>
				<button type="button" class="aie-modal-close">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>

			<div class="aie-modal-body">
				<p class="description" style="margin-bottom: 15px; padding: 8px 12px; background: #f0f0f1; border-left: 3px solid #2271b1;">
					<span class="dashicons dashicons-info" style="color: #2271b1;"></span>
					<?php esc_html_e( 'Click to select a folder, double-click to open it.', 'wp-advanced-import-export' ); ?>
				</p>

				<div class="aie-folder-browser-path">
					<span class="dashicons dashicons-admin-home"></span>
					<span id="aie-current-path"><?php echo esc_html( $base_dir ); ?></span>
				</div>

				<div id="aie-folder-browser-error" class="notice notice-error inline" style="display: none; margin: 10px 0;">
					<p id="aie-folder-browser-error-message"></p>
				</div>

				<div id="aie-folder-browser-loading" class="aie-loading" style="display: none;">
					<span class="spinner is-active"></span>
					<?php esc_html_e( 'Loading folders...', 'wp-advanced-import-export' ); ?>
				</div>

				<div id="aie-folder-browser-list" class="aie-folder-list">
					<!-- Folders will be populated by JS -->
				</div>

				<div id="aie-folder-browser-empty" style="display: none;">
					<p class="description">
						<?php esc_html_e( 'No subfolders found in this directory.', 'wp-advanced-import-export' ); ?>
					</p>
				</div>
			</div>

			<div class="aie-modal-footer">
				<input 
					type="text" 
					id="aie-selected-folder-path" 
					class="regular-text" 
					readonly
					placeholder="<?php esc_attr_e( 'No folder selected', 'wp-advanced-import-export' ); ?>"
				>
				<button type="button" id="aie-choose-folder-btn" class="button button-primary" disabled>
					<span class="dashicons dashicons-yes"></span>
					<?php esc_html_e( 'Choose', 'wp-advanced-import-export' ); ?>
				</button>
				<button type="button" class="button aie-modal-close">
					<?php esc_html_e( 'Cancel', 'wp-advanced-import-export' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
