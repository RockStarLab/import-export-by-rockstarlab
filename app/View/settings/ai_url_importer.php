<?php
/**
 * AI URL Importer Page
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;

// Check if premium and API key is available
$is_premium = function_exists( 'aie_fs' ) && aie_fs()->can_use_premium_code();
$has_api_key = \WP_AIE\Helper\AI_Function_Generator::has_api_key();
?>

<div id="wp-aie-ai-url-importer" class="amplified-import-export wrap">
	<h1>
		<?php esc_html_e( 'AI URL Importer', 'amplified-import-export' ); ?>
	</h1>
	<p class="description">
		<?php esc_html_e( 'Import clean content from URLs using AI. Automatically extracts titles, content, and images while removing sidebars, ads, and clutter.', 'amplified-import-export' ); ?>
	</p>

	<?php if ( ! $is_premium ) : ?>
		<!-- Premium Notice -->
		<div class="aie-premium-notice">
			<div class="aie-premium-notice-icon">
				<span class="dashicons dashicons-lock"></span>
			</div>
			<div class="aie-premium-notice-content">
				<h3><?php esc_html_e( 'Premium Feature', 'amplified-import-export' ); ?></h3>				<p><?php esc_html_e( 'AI URL Importer is a premium feature. Upgrade to unlock the ability to import clean content from URLs using AI-powered extraction that automatically removes sidebars, ads, and clutter.', 'amplified-import-export' ); ?></p>
				<?php if ( function_exists( 'aie_fs' ) ) : ?>
					<a href="<?php echo esc_url( aie_fs()->get_upgrade_url() ); ?>" class="button button-primary button-large">
						<span class="dashicons dashicons-star-filled"></span>
						<?php esc_html_e( 'Upgrade to Premium', 'amplified-import-export' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'plugins.php?aie-activate-license=1' ) ); ?>" class="button button-secondary button-large">
						<span class="dashicons dashicons-admin-network"></span>
						<?php esc_html_e( 'Activate License', 'amplified-import-export' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( ! $has_api_key && $is_premium ) : ?>
		<!-- API Key Notice -->
		<div class="aie-premium-notice aie-api-key-notice">
			<div class="aie-premium-notice-icon">
				<span class="dashicons dashicons-admin-network"></span>
			</div>
			<div class="aie-premium-notice-content">
				<h3><?php esc_html_e( 'OpenAI API Key Required', 'amplified-import-export' ); ?></h3>
				<p><?php esc_html_e( 'To use AI URL Importer, you need to configure your OpenAI API key. The AI uses GPT-4o-mini model to extract clean content from web pages.', 'amplified-import-export' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-plugin-options' ) ); ?>" class="button button-primary button-large">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'Configure API Key', 'amplified-import-export' ); ?>
				</a>
			</div>
		</div>
	<?php endif; ?>

	<?php
	// Check if feature is ready to use
	$feature_ready = $is_premium && $has_api_key;
	?>

	<div class="aie-ai-url-importer-container <?php echo ! $feature_ready ? 'aie-disabled' : ''; ?>">

		<!-- Step 1: URL Input -->
		<div class="aie-step aie-step-1 aie-step-active" data-step="1">
			<div class="aie-step-header">
				<h2><?php esc_html_e( 'Step 1: Add URLs', 'amplified-import-export' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Enter URLs to import (one per line) or upload a TXT file with URLs.', 'amplified-import-export' ); ?>
				</p>
			</div>

			<div class="aie-step-content">
				<div class="aie-url-input-methods">
					<div class="aie-input-method">
						<h3><?php esc_html_e( 'Manual Input', 'amplified-import-export' ); ?></h3>
						<textarea 
							id="aie-urls-textarea" 
							class="large-text" 
							rows="10" 
							placeholder="<?php esc_attr_e( 'Enter URLs, one per line...', 'amplified-import-export' ); ?>"
							<?php echo ! $feature_ready ? 'disabled' : ''; ?>></textarea>
						<p class="description">
							<?php esc_html_e( 'One URL per line, e.g.: https://example.com/article-1', 'amplified-import-export' ); ?>
						</p>
					</div>

					<div class="aie-input-method-divider">
						<span><?php esc_html_e( 'OR', 'amplified-import-export' ); ?></span>
					</div>

					<div class="aie-input-method">
						<h3><?php esc_html_e( 'Upload TXT File', 'amplified-import-export' ); ?></h3>
						<div class="aie-file-upload-area" id="aie-csv-upload-area">
							<input type="file" id="aie-csv-file-input" accept=".txt" style="display: none;" <?php echo ! $feature_ready ? 'disabled' : ''; ?>>
							<div class="aie-upload-placeholder">
								<span class="dashicons dashicons-media-text"></span>
								<p><?php esc_html_e( 'Click to upload or drag & drop TXT file', 'amplified-import-export' ); ?></p>
								<button type="button" class="button" id="aie-browse-csv-btn" <?php echo ! $feature_ready ? 'disabled' : ''; ?>>
									<?php esc_html_e( 'Browse', 'amplified-import-export' ); ?>
								</button>
							</div>
							<div class="aie-file-info" style="display: none;">
								<span class="file-name"></span>
								<button type="button" class="button aie-remove-file" <?php echo ! $feature_ready ? 'disabled' : ''; ?>>
									<?php esc_html_e( 'Remove', 'amplified-import-export' ); ?>
								</button>
							</div>
						</div>
						<p class="description">
							<?php esc_html_e( 'TXT file should have one URL per line.', 'amplified-import-export' ); ?>
						</p>
					</div>
				</div>

				<div class="aie-url-count" style="display: none;">
					<strong><?php esc_html_e( 'URLs found:', 'amplified-import-export' ); ?></strong>
					<span class="count">0</span>
				</div>

				<div class="aie-step-actions">
					<button type="button" class="button button-primary aie-next-step" data-next-step="2" disabled>
						<?php esc_html_e( 'Next Step', 'amplified-import-export' ); ?>
					</button>
				</div>
			</div>
		</div>			<!-- Step 2: Post Type & Field Mapping -->
			<div class="aie-step aie-step-2" data-step="2" style="display: none;">
				<div class="aie-step-header">
					<h2><?php esc_html_e( 'Step 2: Configure Import Settings', 'amplified-import-export' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Select post type and configure where to save content.', 'amplified-import-export' ); ?>
					</p>
				</div>

				<div class="aie-step-content">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="aie-post-type"><?php esc_html_e( 'Post Type', 'amplified-import-export' ); ?></label>
							</th>
							<td>
								<select id="aie-post-type" class="regular-text">
									<option value=""><?php esc_html_e( 'Loading...', 'amplified-import-export' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Select the post type where content will be imported.', 'amplified-import-export' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="aie-content-field"><?php esc_html_e( 'Content Field', 'amplified-import-export' ); ?></label>
							</th>
							<td>
								<select id="aie-content-field" class="regular-text">
									<option value="post_content"><?php esc_html_e( 'Post Content (default)', 'amplified-import-export' ); ?></option>
									<option value="custom_field"><?php esc_html_e( 'Custom Field', 'amplified-import-export' ); ?></option>
									<option value="acf_field"><?php esc_html_e( 'ACF Field', 'amplified-import-export' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Where to save the extracted content.', 'amplified-import-export' ); ?>
								</p>
							</td>
						</tr>

						<tr id="aie-custom-field-row" style="display: none;">
							<th scope="row">
								<label for="aie-custom-field-name"><?php esc_html_e( 'Custom Field Name', 'amplified-import-export' ); ?></label>
							</th>
							<td>
								<input type="text" id="aie-custom-field-name" class="regular-text" placeholder="<?php esc_attr_e( 'Enter custom field name...', 'amplified-import-export' ); ?>">
								<p class="description">
									<?php esc_html_e( 'Enter the meta key name where content will be saved.', 'amplified-import-export' ); ?>
								</p>
							</td>
						</tr>

					<tr id="aie-acf-field-row" style="display: none;">
						<th scope="row">
							<label for="aie-acf-field-select"><?php esc_html_e( 'ACF Field', 'amplified-import-export' ); ?></label>
						</th>
						<td>
							<div id="aie-acf-field-browser">
								<input type="text" id="aie-acf-field-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search fields...', 'amplified-import-export' ); ?>">
								<div id="aie-acf-field-tree" class="aie-acf-tree">
									<p class="description"><?php esc_html_e( 'Select post type first to load ACF fields...', 'amplified-import-export' ); ?></p>
								</div>
								<input type="hidden" id="aie-acf-field-select" value="">
								<p class="description">
									<?php esc_html_e( 'Select the ACF field where content will be saved. Only text, textarea, and WYSIWYG fields are available.', 'amplified-import-export' ); ?>
								</p>
							</div>
						</td>
					</tr>						<tr>
							<th scope="row">
								<label for="aie-request-timeout"><?php esc_html_e( 'Request Timeout', 'amplified-import-export' ); ?></label>
							</th>
							<td>
								<input type="number" id="aie-request-timeout" class="small-text" value="2" min="0" max="60" step="1">
								<span><?php esc_html_e( 'seconds', 'amplified-import-export' ); ?></span>
								<p class="description">
									<?php esc_html_e( 'Delay between requests to avoid rate limiting (0 = no delay).', 'amplified-import-export' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<div class="aie-step-actions">
						<button type="button" class="button aie-prev-step" data-prev-step="1">
							<?php esc_html_e( 'Previous', 'amplified-import-export' ); ?>
						</button>
						<button type="button" class="button button-primary aie-next-step" data-next-step="3">
							<?php esc_html_e( 'Next Step', 'amplified-import-export' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Step 3: Test & Preview -->
			<div class="aie-step aie-step-3" data-step="3" style="display: none;">
				<div class="aie-step-header">
					<h2><?php esc_html_e( 'Step 3: Test & Preview', 'amplified-import-export' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Test the connection and preview content extraction from the first URL.', 'amplified-import-export' ); ?>
					</p>
				</div>

				<div class="aie-step-content">
					<div class="aie-test-connection-section">
						<h3><?php esc_html_e( 'Test OpenAI Connection', 'amplified-import-export' ); ?></h3>
						<button type="button" class="button" id="aie-test-connection-btn">
							<?php esc_html_e( 'Test Connection', 'amplified-import-export' ); ?>
						</button>
						<div class="aie-test-result" style="display: none;"></div>
					</div>

					<div class="aie-preview-section">
						<h3><?php esc_html_e( 'Preview Content', 'amplified-import-export' ); ?></h3>
						<p class="description">
							<?php esc_html_e( 'Preview content extraction from the first URL in your list.', 'amplified-import-export' ); ?>
						</p>
						<div class="aie-preview-url-display">
							<strong><?php esc_html_e( 'Preview URL:', 'amplified-import-export' ); ?></strong>
							<span id="aie-preview-url"></span>
						</div>
						<button type="button" class="button" id="aie-preview-btn">
							<?php esc_html_e( 'Generate Preview', 'amplified-import-export' ); ?>
						</button>
						<button type="button" class="button" id="aie-regenerate-preview-btn" style="display: none;">
							<?php esc_html_e( 'Regenerate Preview', 'amplified-import-export' ); ?>
						</button>

						<div class="aie-preview-result" style="display: none;">
							<div class="aie-preview-title">
								<h4><?php esc_html_e( 'Title:', 'amplified-import-export' ); ?></h4>
								<div class="preview-title-content"></div>
							</div>
							<div class="aie-preview-excerpt">
								<h4><?php esc_html_e( 'Excerpt:', 'amplified-import-export' ); ?></h4>
								<div class="preview-excerpt-content"></div>
							</div>
							<div class="aie-preview-content">
								<h4><?php esc_html_e( 'Content:', 'amplified-import-export' ); ?></h4>
								<div class="preview-content-html"></div>
							</div>
							<div class="aie-preview-images">
								<h4><?php esc_html_e( 'Images Found:', 'amplified-import-export' ); ?></h4>
								<div class="preview-images-list"></div>
							</div>
							<div class="aie-preview-featured">
								<h4><?php esc_html_e( 'Featured Image:', 'amplified-import-export' ); ?></h4>
								<div class="preview-featured-image"></div>
							</div>
						</div>
					</div>

					<div class="aie-step-actions">
						<button type="button" class="button aie-prev-step" data-prev-step="2">
							<?php esc_html_e( 'Previous', 'amplified-import-export' ); ?>
						</button>
						<button type="button" class="button button-primary" id="aie-start-import-btn" disabled>
							<?php esc_html_e( 'Start Import', 'amplified-import-export' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Step 4: Import Progress -->
			<div class="aie-step aie-step-4" data-step="4" style="display: none;">
				<div class="aie-step-header">
					<h2><?php esc_html_e( 'Step 4: Importing...', 'amplified-import-export' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Importing content from URLs. This may take a while depending on the number of URLs.', 'amplified-import-export' ); ?>
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
							<strong><?php esc_html_e( 'Success:', 'amplified-import-export' ); ?></strong>
							<span class="success-count">0</span>
						</div>
						<div class="status-item">
							<strong><?php esc_html_e( 'Failed:', 'amplified-import-export' ); ?></strong>
							<span class="failed-count">0</span>
						</div>
						<div class="status-item">
							<strong><?php esc_html_e( 'Status:', 'amplified-import-export' ); ?></strong>
							<span class="import-status-text"><?php esc_html_e( 'Processing...', 'amplified-import-export' ); ?></span>
						</div>
					</div>

					<div class="aie-import-log">
						<h3><?php esc_html_e( 'Import Log', 'amplified-import-export' ); ?></h3>
						<div class="aie-log-entries"></div>
					</div>

					<div class="aie-step-actions">
						<button type="button" class="button" id="aie-cancel-import-btn">
							<?php esc_html_e( 'Cancel Import', 'amplified-import-export' ); ?>
						</button>
						<button type="button" class="button button-primary" id="aie-view-results-btn" style="display: none;">
						<?php esc_html_e( 'View Imported Posts', 'amplified-import-export' ); ?>
					</button>
					<button type="button" class="button" id="aie-start-new-import-btn" style="display: none;">
						<?php esc_html_e( 'Start New Import', 'amplified-import-export' ); ?>
					</button>
				</div>
			</div>
		</div>

	</div>
</div>
