<?php
/**
 * AI URL Importer Page
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

// Check if API key is available
$has_api_key = \RockStarLab\ImportExport\Helper\AI_Function_Generator::has_api_key();
?>

<div id="rsl-ie-ai-url-importer" class="import-export-by-rockstarlab wrap">
	<h1>
		<?php esc_html_e( 'AI URL Importer', 'import-export-by-rockstarlab' ); ?>
	</h1>
	<p class="description">
		<?php esc_html_e( 'Import clean content from URLs using AI. Automatically extracts titles, content, and images while removing sidebars, ads, and clutter.', 'import-export-by-rockstarlab' ); ?>
	</p>

	<?php if ( ! $has_api_key ) : ?>
		<!-- API Key Notice -->
		<div class="aie-premium-notice aie-api-key-notice">
			<div class="aie-premium-notice-icon">
				<span class="dashicons dashicons-admin-network"></span>
			</div>
			<div class="aie-premium-notice-content">
				<h3><?php esc_html_e( 'OpenAI API Key Required', 'import-export-by-rockstarlab' ); ?></h3>
				<p><?php esc_html_e( 'To use AI URL Importer, you need to configure your OpenAI API key. The AI uses GPT-4o-mini model to extract clean content from web pages.', 'import-export-by-rockstarlab' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-plugin-options' ) ); ?>" class="button button-primary button-large">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'Configure API Key', 'import-export-by-rockstarlab' ); ?>
				</a>
			</div>
		</div>
	<?php endif; ?>

		<?php
		// Check if feature is ready to use
		$feature_ready = $has_api_key;
		?>

	<div class="aie-ai-url-importer-container <?php echo ! $feature_ready ? 'aie-disabled' : ''; ?>">

		<!-- Step 1: URL Input -->
		<div class="aie-step aie-step-1 aie-step-active" data-step="1">
			<div class="aie-step-header">
				<h2><?php esc_html_e( 'Step 1: Add URLs', 'import-export-by-rockstarlab' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Enter URLs to import (one per line) or upload a TXT file with URLs.', 'import-export-by-rockstarlab' ); ?>
				</p>
			</div>

			<div class="aie-step-content">
				<div class="aie-url-input-methods">
					<div class="aie-input-method">
						<h3><?php esc_html_e( 'Manual Input', 'import-export-by-rockstarlab' ); ?></h3>
						<textarea 
							id="aie-urls-textarea" 
							class="large-text" 
							rows="10" 
							placeholder="<?php esc_attr_e( 'Enter URLs, one per line...', 'import-export-by-rockstarlab' ); ?>"
							<?php echo ! $feature_ready ? 'disabled' : ''; ?>></textarea>
						<p class="description">
							<?php esc_html_e( 'One URL per line, e.g.: https://example.com/article-1', 'import-export-by-rockstarlab' ); ?>
						</p>
					</div>

					<div class="aie-input-method-divider">
						<span><?php esc_html_e( 'OR', 'import-export-by-rockstarlab' ); ?></span>
					</div>

					<div class="aie-input-method">
						<h3><?php esc_html_e( 'Upload TXT File', 'import-export-by-rockstarlab' ); ?></h3>
						<div class="aie-file-upload-area" id="aie-csv-upload-area">
							<input type="file" id="aie-csv-file-input" accept=".txt" style="display: none;" <?php echo ! $feature_ready ? 'disabled' : ''; ?>>
							<div class="aie-upload-placeholder">
								<span class="dashicons dashicons-media-text"></span>
								<p><?php esc_html_e( 'Click to upload or drag & drop TXT file', 'import-export-by-rockstarlab' ); ?></p>
								<button type="button" class="button" id="aie-browse-csv-btn" <?php echo ! $feature_ready ? 'disabled' : ''; ?>>
									<?php esc_html_e( 'Browse', 'import-export-by-rockstarlab' ); ?>
								</button>
							</div>
							<div class="aie-file-info" style="display: none;">
								<span class="file-name"></span>
								<button type="button" class="button aie-remove-file" <?php echo ! $feature_ready ? 'disabled' : ''; ?>>
									<?php esc_html_e( 'Remove', 'import-export-by-rockstarlab' ); ?>
								</button>
							</div>
						</div>
						<p class="description">
							<?php esc_html_e( 'TXT file should have one URL per line.', 'import-export-by-rockstarlab' ); ?>
						</p>
					</div>
				</div>

				<div class="aie-url-count" style="display: none;">
					<strong><?php esc_html_e( 'URLs found:', 'import-export-by-rockstarlab' ); ?></strong>
					<span class="count">0</span>
				</div>

				<div class="aie-step-actions">
					<button type="button" class="button button-primary aie-next-step" data-next-step="2" disabled>
						<?php esc_html_e( 'Next Step', 'import-export-by-rockstarlab' ); ?>
					</button>
				</div>
			</div>
		</div>			<!-- Step 2: Post Type & Field Mapping -->
			<div class="aie-step aie-step-2" data-step="2" style="display: none;">
				<div class="aie-step-header">
					<h2><?php esc_html_e( 'Step 2: Configure Import Settings', 'import-export-by-rockstarlab' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Select post type and configure where to save content.', 'import-export-by-rockstarlab' ); ?>
					</p>
				</div>

				<div class="aie-step-content">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="aie-post-type"><?php esc_html_e( 'Post Type', 'import-export-by-rockstarlab' ); ?></label>
							</th>
							<td>
								<select id="aie-post-type" class="regular-text">
									<option value=""><?php esc_html_e( 'Loading...', 'import-export-by-rockstarlab' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Select the post type where content will be imported.', 'import-export-by-rockstarlab' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="aie-content-field"><?php esc_html_e( 'Content Field', 'import-export-by-rockstarlab' ); ?></label>
							</th>
							<td>
								<select id="aie-content-field" class="regular-text">
									<option value="post_content"><?php esc_html_e( 'Post Content (default)', 'import-export-by-rockstarlab' ); ?></option>
									<option value="custom_field"><?php esc_html_e( 'Custom Field', 'import-export-by-rockstarlab' ); ?></option>
									<option value="acf_field"><?php esc_html_e( 'ACF Field', 'import-export-by-rockstarlab' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Where to save the extracted content.', 'import-export-by-rockstarlab' ); ?>
								</p>
							</td>
						</tr>

						<tr id="aie-custom-field-row" style="display: none;">
							<th scope="row">
								<label for="aie-custom-field-name"><?php esc_html_e( 'Custom Field Name', 'import-export-by-rockstarlab' ); ?></label>
							</th>
							<td>
								<input type="text" id="aie-custom-field-name" class="regular-text" placeholder="<?php esc_attr_e( 'Enter custom field name...', 'import-export-by-rockstarlab' ); ?>">
								<p class="description">
									<?php esc_html_e( 'Enter the meta key name where content will be saved.', 'import-export-by-rockstarlab' ); ?>
								</p>
							</td>
						</tr>

					<tr id="aie-acf-field-row" style="display: none;">
						<th scope="row">
							<label for="aie-acf-field-select"><?php esc_html_e( 'ACF Field', 'import-export-by-rockstarlab' ); ?></label>
						</th>
						<td>
							<div id="aie-acf-field-browser">
								<input type="text" id="aie-acf-field-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search fields...', 'import-export-by-rockstarlab' ); ?>">
								<div id="aie-acf-field-tree" class="aie-acf-tree">
									<p class="description"><?php esc_html_e( 'Select post type first to load ACF fields...', 'import-export-by-rockstarlab' ); ?></p>
								</div>
								<input type="hidden" id="aie-acf-field-select" value="">
								<p class="description">
									<?php esc_html_e( 'Select the ACF field where content will be saved. Only text, textarea, and WYSIWYG fields are available.', 'import-export-by-rockstarlab' ); ?>
								</p>
							</div>
						</td>
					</tr>						<tr>
							<th scope="row">
								<label for="aie-request-timeout"><?php esc_html_e( 'Request Timeout', 'import-export-by-rockstarlab' ); ?></label>
							</th>
							<td>
								<input type="number" id="aie-request-timeout" class="small-text" value="2" min="0" max="60" step="1">
								<span><?php esc_html_e( 'seconds', 'import-export-by-rockstarlab' ); ?></span>
								<p class="description">
									<?php esc_html_e( 'Delay between requests to avoid rate limiting (0 = no delay).', 'import-export-by-rockstarlab' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<div class="aie-step-actions">
						<button type="button" class="button aie-prev-step" data-prev-step="1">
							<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
						</button>
						<button type="button" class="button button-primary aie-next-step" data-next-step="3">
							<?php esc_html_e( 'Next Step', 'import-export-by-rockstarlab' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Step 3: Test & Preview -->
			<div class="aie-step aie-step-3" data-step="3" style="display: none;">
				<div class="aie-step-header">
					<h2><?php esc_html_e( 'Step 3: Test & Preview', 'import-export-by-rockstarlab' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Test the connection and preview content extraction from the first URL.', 'import-export-by-rockstarlab' ); ?>
					</p>
				</div>

				<div class="aie-step-content">
					<div class="aie-test-connection-section">
						<h3><?php esc_html_e( 'Test OpenAI Connection', 'import-export-by-rockstarlab' ); ?></h3>
						<button type="button" class="button" id="aie-test-connection-btn">
							<?php esc_html_e( 'Test Connection', 'import-export-by-rockstarlab' ); ?>
						</button>
						<div class="aie-test-result" style="display: none;"></div>
					</div>

					<div class="aie-preview-section">
						<h3><?php esc_html_e( 'Preview Content', 'import-export-by-rockstarlab' ); ?></h3>
						<p class="description">
							<?php esc_html_e( 'Preview content extraction from the first URL in your list.', 'import-export-by-rockstarlab' ); ?>
						</p>
						<div class="aie-preview-url-display">
							<strong><?php esc_html_e( 'Preview URL:', 'import-export-by-rockstarlab' ); ?></strong>
							<span id="aie-preview-url"></span>
						</div>
						<button type="button" class="button" id="aie-preview-btn">
							<?php esc_html_e( 'Generate Preview', 'import-export-by-rockstarlab' ); ?>
						</button>
						<button type="button" class="button" id="aie-regenerate-preview-btn" style="display: none;">
							<?php esc_html_e( 'Regenerate Preview', 'import-export-by-rockstarlab' ); ?>
						</button>

						<div class="aie-preview-result" style="display: none;">
							<div class="aie-preview-title">
								<h4><?php esc_html_e( 'Title:', 'import-export-by-rockstarlab' ); ?></h4>
								<div class="preview-title-content"></div>
							</div>
							<div class="aie-preview-excerpt">
								<h4><?php esc_html_e( 'Excerpt:', 'import-export-by-rockstarlab' ); ?></h4>
								<div class="preview-excerpt-content"></div>
							</div>
							<div class="aie-preview-content">
								<h4><?php esc_html_e( 'Content:', 'import-export-by-rockstarlab' ); ?></h4>
								<div class="preview-content-html"></div>
							</div>
							<div class="aie-preview-images">
								<h4><?php esc_html_e( 'Images Found:', 'import-export-by-rockstarlab' ); ?></h4>
								<div class="preview-images-list"></div>
							</div>
							<div class="aie-preview-featured">
								<h4><?php esc_html_e( 'Featured Image:', 'import-export-by-rockstarlab' ); ?></h4>
								<div class="preview-featured-image"></div>
							</div>
						</div>
					</div>

					<div class="aie-step-actions">
						<button type="button" class="button aie-prev-step" data-prev-step="2">
							<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
						</button>
						<button type="button" class="button button-primary" id="aie-start-import-btn" disabled>
							<?php esc_html_e( 'Start Import', 'import-export-by-rockstarlab' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Step 4: Import Progress -->
			<div class="aie-step aie-step-4" data-step="4" style="display: none;">
				<div class="aie-step-header">
					<h2><?php esc_html_e( 'Step 4: Importing...', 'import-export-by-rockstarlab' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Importing content from URLs. This may take a while depending on the number of URLs.', 'import-export-by-rockstarlab' ); ?>
					</p>
				</div>

				<div class="aie-step-content">
					<div class="aie-import-progress">
						<div class="aie-progress-bar">
							<div class="aie-progress-fill" style="width: 0%;"></div>
						</div>
						<div class="aie-progress-text">
							<span class="current">0</span> / <span class="total">0</span> URLs processed
						</div>
					</div>

					<div class="aie-import-status">
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

					<div class="aie-import-log">
						<h3><?php esc_html_e( 'Import Log', 'import-export-by-rockstarlab' ); ?></h3>
						<div class="aie-log-entries"></div>
					</div>

					<div class="aie-step-actions">
						<button type="button" class="button" id="aie-cancel-import-btn">
							<?php esc_html_e( 'Cancel Import', 'import-export-by-rockstarlab' ); ?>
						</button>
						<button type="button" class="button button-primary" id="aie-view-results-btn" style="display: none;">
						<?php esc_html_e( 'View Imported Posts', 'import-export-by-rockstarlab' ); ?>
					</button>
					<button type="button" class="button" id="aie-start-new-import-btn" style="display: none;">
						<?php esc_html_e( 'Start New Import', 'import-export-by-rockstarlab' ); ?>
					</button>
				</div>
			</div>
		</div>

	</div>
</div>
