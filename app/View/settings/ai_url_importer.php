<?php
/**
 * AI URL Importer Page
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

// Check if API key is available
$rsl_ie_has_api_key = \RockStarLab\ImportExport\Helper\OpenAI_API_Key::has_api_key();
$rsl_ie_is_wp7_plus = \RockStarLab\ImportExport\Helper\OpenAI_API_Key::is_wp7_plus();
?>

<div id="rsl-ie-ai-url-importer" class="import-export-by-rockstarlab wrap">
	<h1>
		<?php esc_html_e( 'AI URL Importer', 'import-export-by-rockstarlab' ); ?>
	</h1>
	<p class="description">
		<?php esc_html_e( 'Import clean content from URLs using AI. Automatically extracts titles, content, and images while removing sidebars, ads, and clutter.', 'import-export-by-rockstarlab' ); ?>
	</p>

	<?php if ( ! $rsl_ie_has_api_key ) : ?>
		<!-- API Key Notice -->
		<div class="rsl-ie-premium-notice rsl-ie-api-key-notice">
			<div class="rsl-ie-premium-notice-icon">
				<span class="dashicons dashicons-admin-network"></span>
			</div>
			<div class="rsl-ie-premium-notice-content">
				<h3><?php esc_html_e( 'AI Provider Required', 'import-export-by-rockstarlab' ); ?></h3>
				<?php if ( $rsl_ie_is_wp7_plus ) : ?>
					<p><?php esc_html_e( 'To use AI URL Importer, configure a text-generation provider in WordPress AI Client or add an API key in Plugin Options.', 'import-export-by-rockstarlab' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>" class="button button-primary button-large">
						<span class="dashicons dashicons-admin-generic"></span>
						<?php esc_html_e( 'Open Settings → Connectors', 'import-export-by-rockstarlab' ); ?>
					</a>
					<p class="description" style="margin-top: 10px;">
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: plugin options URL */
								__( 'If you prefer, you can also configure a key in <a href="%s">Plugin Options</a>.', 'import-export-by-rockstarlab' ),
								esc_url( admin_url( 'admin.php?page=rsl-ie-plugin-options' ) )
							)
						);
						?>
					</p>
				<?php else : ?>
					<p><?php esc_html_e( 'To use AI URL Importer, you need to configure your OpenAI API key. The AI uses GPT-4o-mini model to extract clean content from web pages.', 'import-export-by-rockstarlab' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-plugin-options' ) ); ?>" class="button button-primary button-large">
						<span class="dashicons dashicons-admin-generic"></span>
						<?php esc_html_e( 'Configure API Key', 'import-export-by-rockstarlab' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

		<?php
		// Check if feature is ready to use
		$rsl_ie_feature_ready = $rsl_ie_has_api_key;
		?>

	<div class="rsl-ie-ai-url-importer-container <?php echo ! $rsl_ie_feature_ready ? 'rsl-ie-disabled' : ''; ?>">

		<!-- Step 1: URL Input -->
		<div class="rsl-ie-step rsl-ie-step-1 rsl-ie-step-active" data-step="1">
			<div class="rsl-ie-step-header">
				<h2><?php esc_html_e( 'Step 1: Add URLs', 'import-export-by-rockstarlab' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Enter URLs to import (one per line) or upload a TXT file with URLs.', 'import-export-by-rockstarlab' ); ?>
				</p>
			</div>

			<div class="rsl-ie-step-content">
				<div class="rsl-ie-url-input-methods">
					<div class="rsl-ie-input-method">
						<h3><?php esc_html_e( 'Manual Input', 'import-export-by-rockstarlab' ); ?></h3>
						<textarea 
							id="rsl-ie-urls-textarea" 
							class="large-text" 
							rows="10" 
							placeholder="<?php esc_attr_e( 'Enter URLs, one per line...', 'import-export-by-rockstarlab' ); ?>"
							<?php echo ! $rsl_ie_feature_ready ? 'disabled' : ''; ?>></textarea>
						<p class="description">
							<?php esc_html_e( 'One URL per line, e.g.: https://example.com/article-1', 'import-export-by-rockstarlab' ); ?>
						</p>
					</div>

					<div class="rsl-ie-input-method-divider">
						<span><?php esc_html_e( 'OR', 'import-export-by-rockstarlab' ); ?></span>
					</div>

					<div class="rsl-ie-input-method">
						<h3><?php esc_html_e( 'Upload TXT File', 'import-export-by-rockstarlab' ); ?></h3>
						<div class="rsl-ie-file-upload-area" id="rsl-ie-csv-upload-area">
							<input type="file" id="rsl-ie-csv-file-input" accept=".txt" style="display: none;" <?php echo ! $rsl_ie_feature_ready ? 'disabled' : ''; ?>>
							<div class="rsl-ie-upload-placeholder">
								<span class="dashicons dashicons-media-text"></span>
								<p><?php esc_html_e( 'Click to upload or drag & drop TXT file', 'import-export-by-rockstarlab' ); ?></p>
								<button type="button" class="button" id="rsl-ie-browse-csv-btn" <?php echo ! $rsl_ie_feature_ready ? 'disabled' : ''; ?>>
									<?php esc_html_e( 'Browse', 'import-export-by-rockstarlab' ); ?>
								</button>
							</div>
							<div class="rsl-ie-file-info" style="display: none;">
								<span class="file-name"></span>
								<button type="button" class="button rsl-ie-remove-file" <?php echo ! $rsl_ie_feature_ready ? 'disabled' : ''; ?>>
									<?php esc_html_e( 'Remove', 'import-export-by-rockstarlab' ); ?>
								</button>
							</div>
						</div>
						<p class="description">
							<?php esc_html_e( 'TXT file should have one URL per line.', 'import-export-by-rockstarlab' ); ?>
						</p>
					</div>
				</div>

				<div class="rsl-ie-url-count" style="display: none;">
					<strong><?php esc_html_e( 'URLs found:', 'import-export-by-rockstarlab' ); ?></strong>
					<span class="count">0</span>
				</div>

				<div class="rsl-ie-step-actions">
					<button type="button" class="button button-primary rsl-ie-next-step" data-next-step="2" disabled>
						<?php esc_html_e( 'Next Step', 'import-export-by-rockstarlab' ); ?>
					</button>
				</div>
			</div>
		</div>			<!-- Step 2: Post Type & Field Mapping -->
			<div class="rsl-ie-step rsl-ie-step-2" data-step="2" style="display: none;">
				<div class="rsl-ie-step-header">
					<h2><?php esc_html_e( 'Step 2: Configure Import Settings', 'import-export-by-rockstarlab' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Select post type and configure where to save content.', 'import-export-by-rockstarlab' ); ?>
					</p>
				</div>

				<div class="rsl-ie-step-content">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="rsl-ie-post-type"><?php esc_html_e( 'Post Type', 'import-export-by-rockstarlab' ); ?></label>
							</th>
							<td>
								<select id="rsl-ie-post-type" class="regular-text">
									<option value=""><?php esc_html_e( 'Loading...', 'import-export-by-rockstarlab' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Select the post type where content will be imported.', 'import-export-by-rockstarlab' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="rsl-ie-content-field"><?php esc_html_e( 'Content Field', 'import-export-by-rockstarlab' ); ?></label>
							</th>
							<td>
								<select id="rsl-ie-content-field" class="regular-text">
									<option value="post_content"><?php esc_html_e( 'Post Content (default)', 'import-export-by-rockstarlab' ); ?></option>
									<option value="custom_field"><?php esc_html_e( 'Custom Field', 'import-export-by-rockstarlab' ); ?></option>
									<option value="acf_field"><?php esc_html_e( 'ACF Field', 'import-export-by-rockstarlab' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Where to save the extracted content.', 'import-export-by-rockstarlab' ); ?>
								</p>
							</td>
						</tr>

						<tr id="rsl-ie-custom-field-row" style="display: none;">
							<th scope="row">
								<label for="rsl-ie-custom-field-name"><?php esc_html_e( 'Custom Field Name', 'import-export-by-rockstarlab' ); ?></label>
							</th>
							<td>
								<input type="text" id="rsl-ie-custom-field-name" class="regular-text" placeholder="<?php esc_attr_e( 'Enter custom field name...', 'import-export-by-rockstarlab' ); ?>">
								<p class="description">
									<?php esc_html_e( 'Enter the meta key name where content will be saved.', 'import-export-by-rockstarlab' ); ?>
								</p>
							</td>
						</tr>

					<tr id="rsl-ie-acf-field-row" style="display: none;">
						<th scope="row">
							<label for="rsl-ie-acf-field-select"><?php esc_html_e( 'ACF Field', 'import-export-by-rockstarlab' ); ?></label>
						</th>
						<td>
							<div id="rsl-ie-acf-field-browser">
								<input type="text" id="rsl-ie-acf-field-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search fields...', 'import-export-by-rockstarlab' ); ?>">
								<div id="rsl-ie-acf-field-tree" class="rsl-ie-acf-tree">
									<p class="description"><?php esc_html_e( 'Select post type first to load ACF fields...', 'import-export-by-rockstarlab' ); ?></p>
								</div>
								<input type="hidden" id="rsl-ie-acf-field-select" value="">
								<p class="description">
									<?php esc_html_e( 'Select the ACF field where content will be saved. Only text, textarea, and WYSIWYG fields are available.', 'import-export-by-rockstarlab' ); ?>
								</p>
							</div>
						</td>
					</tr>						<tr>
							<th scope="row">
								<label for="rsl-ie-request-timeout"><?php esc_html_e( 'Delay Between Requests', 'import-export-by-rockstarlab' ); ?></label>
							</th>
							<td>
								<input type="number" id="rsl-ie-request-timeout" class="small-text" value="2" min="0" max="60" step="1">
								<span><?php esc_html_e( 'seconds', 'import-export-by-rockstarlab' ); ?></span>
								<p class="description">
									<?php esc_html_e( 'Delay between requests to avoid rate limiting (0 = no delay).', 'import-export-by-rockstarlab' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<div class="rsl-ie-step-actions">
						<button type="button" class="button rsl-ie-prev-step" data-prev-step="1">
							<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
						</button>
						<button type="button" class="button button-primary rsl-ie-next-step" data-next-step="3">
							<?php esc_html_e( 'Next Step', 'import-export-by-rockstarlab' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Step 3: Test & Preview -->
			<div class="rsl-ie-step rsl-ie-step-3" data-step="3" style="display: none;">
				<div class="rsl-ie-step-header">
					<h2><?php esc_html_e( 'Step 3: Test & Preview', 'import-export-by-rockstarlab' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Test the connection and preview content extraction from the first URL.', 'import-export-by-rockstarlab' ); ?>
					</p>
				</div>

				<div class="rsl-ie-step-content">
					<div class="rsl-ie-test-connection-section">
						<h3><?php esc_html_e( 'Test OpenAI Connection', 'import-export-by-rockstarlab' ); ?></h3>
						<button type="button" class="button" id="rsl-ie-test-connection-btn">
							<?php esc_html_e( 'Test Connection', 'import-export-by-rockstarlab' ); ?>
						</button>
						<div class="rsl-ie-test-result" style="display: none;"></div>
					</div>

					<div class="rsl-ie-preview-section">
						<h3><?php esc_html_e( 'Preview Content', 'import-export-by-rockstarlab' ); ?></h3>
						<p class="description">
							<?php esc_html_e( 'Preview content extraction from the first URL in your list.', 'import-export-by-rockstarlab' ); ?>
						</p>
						<div class="rsl-ie-preview-url-display">
							<strong><?php esc_html_e( 'Preview URL:', 'import-export-by-rockstarlab' ); ?></strong>
							<span id="rsl-ie-preview-url"></span>
						</div>
						<button type="button" class="button" id="rsl-ie-preview-btn">
							<?php esc_html_e( 'Generate Preview', 'import-export-by-rockstarlab' ); ?>
						</button>
						<button type="button" class="button" id="rsl-ie-regenerate-preview-btn" style="display: none;">
							<?php esc_html_e( 'Regenerate Preview', 'import-export-by-rockstarlab' ); ?>
						</button>

						<div class="rsl-ie-preview-result" style="display: none;">
							<div class="rsl-ie-preview-title">
								<h4><?php esc_html_e( 'Title:', 'import-export-by-rockstarlab' ); ?></h4>
								<div class="preview-title-content"></div>
							</div>
							<div class="rsl-ie-preview-excerpt">
								<h4><?php esc_html_e( 'Excerpt:', 'import-export-by-rockstarlab' ); ?></h4>
								<div class="preview-excerpt-content"></div>
							</div>
							<div class="rsl-ie-preview-content">
								<h4><?php esc_html_e( 'Content:', 'import-export-by-rockstarlab' ); ?></h4>
								<div class="preview-content-html"></div>
							</div>
							<div class="rsl-ie-preview-images">
								<h4><?php esc_html_e( 'Images Found:', 'import-export-by-rockstarlab' ); ?></h4>
								<div class="preview-images-list"></div>
							</div>
							<div class="rsl-ie-preview-featured">
								<h4><?php esc_html_e( 'Featured Image:', 'import-export-by-rockstarlab' ); ?></h4>
								<div class="preview-featured-image"></div>
							</div>
						</div>
					</div>

					<div class="rsl-ie-step-actions">
						<button type="button" class="button rsl-ie-prev-step" data-prev-step="2">
							<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
						</button>
						<button type="button" class="button button-primary" id="rsl-ie-start-import-btn" disabled>
							<?php esc_html_e( 'Start Import', 'import-export-by-rockstarlab' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Step 4: Import Progress -->
			<div class="rsl-ie-step rsl-ie-step-4" data-step="4" style="display: none;">
				<div class="rsl-ie-step-header">
					<h2><?php esc_html_e( 'Step 4: Importing...', 'import-export-by-rockstarlab' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Importing content from URLs. This may take a while depending on the number of URLs.', 'import-export-by-rockstarlab' ); ?>
					</p>
				</div>

				<div class="rsl-ie-step-content">
					<div class="rsl-ie-import-progress">
						<div class="rsl-ie-progress-bar">
							<div class="rsl-ie-progress-fill" style="width: 0%;"></div>
						</div>
						<div class="rsl-ie-progress-text">
							<span class="current">0</span> / <span class="total">0</span> URLs processed
						</div>
					</div>

					<div class="rsl-ie-import-status">
						<div class="status-item">
							<strong><?php esc_html_e( 'Success:', 'import-export-by-rockstarlab' ); ?></strong>
							<span class="success-count">0</span>
						</div>
						<div class="status-item">
							<strong><?php esc_html_e( 'Failed:', 'import-export-by-rockstarlab' ); ?></strong>
							<span class="failed-count">0</span>
						</div>
						<div class="status-item">
							<strong><?php esc_html_e( 'Status:', 'import-export-by-rockstarlab' ); ?></strong>
							<span class="import-status-text"><?php esc_html_e( 'Processing...', 'import-export-by-rockstarlab' ); ?></span>
						</div>
					</div>

					<div class="rsl-ie-import-log">
						<h3><?php esc_html_e( 'Import Log', 'import-export-by-rockstarlab' ); ?></h3>
						<div class="rsl-ie-log-entries"></div>
					</div>

					<div class="rsl-ie-step-actions">
						<button type="button" class="button" id="rsl-ie-cancel-import-btn">
							<?php esc_html_e( 'Cancel Import', 'import-export-by-rockstarlab' ); ?>
						</button>
						<button type="button" class="button button-primary" id="rsl-ie-view-results-btn" style="display: none;">
						<?php esc_html_e( 'View Imported Posts', 'import-export-by-rockstarlab' ); ?>
					</button>
					<button type="button" class="button" id="rsl-ie-start-new-import-btn" style="display: none;">
						<?php esc_html_e( 'Start New Import', 'import-export-by-rockstarlab' ); ?>
					</button>
				</div>
			</div>
		</div>

	</div>
</div>
