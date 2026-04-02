<?php
/**
 * Export Field Functions Modal
 * Modal for assigning transformation functions to export fields
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Field Functions Modal -->
<div id="aie-field-functions-modal" class="aie-modal" style="display:none;">
	<div class="aie-modal-backdrop"></div>
	<div class="aie-modal-content aie-field-functions-modal-content">
		<div class="aie-modal-header">
			<h2 class="aie-modal-title">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'Field Transformation Functions', 'amplified-import-export' ); ?>
			</h2>
			<button type="button" class="aie-modal-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="aie-modal-body">
			<!-- Field Info -->
			<div class="aie-field-info">
				<div class="aie-field-info-item">
					<strong><?php esc_html_e( 'Field:', 'amplified-import-export' ); ?></strong>
					<span class="aie-current-field-label"></span>
				</div>
				<div class="aie-field-info-item">
					<strong><?php esc_html_e( 'Type:', 'amplified-import-export' ); ?></strong>
					<span class="aie-current-field-type"></span>
				</div>
			</div>

			<!-- Applied Functions List -->
			<div class="aie-applied-functions">
				<h3>
					<?php esc_html_e( 'Applied Functions', 'amplified-import-export' ); ?>
					<span class="aie-functions-count">(0)</span>
				</h3>
				
				<div class="aie-functions-pipeline" id="aie-functions-pipeline">
					<div class="aie-no-functions">
						<span class="dashicons dashicons-info"></span>
						<p><?php esc_html_e( 'No functions applied yet. Add functions from the list below.', 'amplified-import-export' ); ?></p>
					</div>
					
					<!-- Functions will be added here -->
					<div class="aie-function-items" id="aie-function-items">
						<!-- Example structure:
						<div class="aie-function-item" data-function-id="1">
							<span class="aie-function-handle dashicons dashicons-menu"></span>
							<div class="aie-function-info">
								<strong class="aie-function-name">uppercase</strong>
								<span class="aie-function-desc">Convert to uppercase</span>
							</div>
							<div class="aie-function-actions">
								<button type="button" class="button-small aie-remove-function">
									<span class="dashicons dashicons-no-alt"></span>
								</button>
							</div>
						</div>
						-->
					</div>
				</div>

				<div class="aie-pipeline-hint">
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'Functions are applied in order from top to bottom. Drag to reorder.', 'amplified-import-export' ); ?>
				</div>
			</div>

			<!-- Available Functions -->
			<div class="aie-available-functions">
				<h3><?php esc_html_e( 'Available Functions', 'amplified-import-export' ); ?></h3>
				
				<!-- Search Functions -->
				<div class="aie-functions-search">
					<input 
						type="text" 
						id="aie-functions-search" 
						class="regular-text" 
						placeholder="<?php esc_attr_e( 'Search functions...', 'amplified-import-export' ); ?>"
					>
					<span class="dashicons dashicons-search"></span>
				</div>

				<!-- Functions Filter -->
				<div class="aie-functions-filter">
					<label>
						<input type="radio" name="functions-filter" value="all" checked>
						<?php esc_html_e( 'All', 'amplified-import-export' ); ?>
					</label>
					<label>
						<input type="radio" name="functions-filter" value="library">
						<?php esc_html_e( 'Library', 'amplified-import-export' ); ?>
					</label>
					<label>
						<input type="radio" name="functions-filter" value="custom">
						<?php esc_html_e( 'Custom', 'amplified-import-export' ); ?>
					</label>
				</div>

				<!-- Functions List -->
				<div class="aie-functions-list" id="aie-functions-list">
					<div class="aie-functions-loading">
						<span class="spinner is-active"></span>
						<p><?php esc_html_e( 'Loading functions...', 'amplified-import-export' ); ?></p>
					</div>
					
					<!-- Functions will be loaded here -->
				</div>

				<!-- Quick Add Link -->
				<div class="aie-functions-quick-add">
					<a href="#" class="aie-create-new-function">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Create New Function', 'amplified-import-export' ); ?>
					</a>
				</div>
			</div>

			<!-- Preview Section -->
			<div class="aie-function-preview">
				<h3><?php esc_html_e( 'Preview Transformation', 'amplified-import-export' ); ?></h3>
				
				<div class="aie-preview-controls">
					<div class="aie-preview-input-group">
						<label for="aie-preview-input">
							<?php esc_html_e( 'Test Value:', 'amplified-import-export' ); ?>
						</label>
						<input 
							type="text" 
							id="aie-preview-input" 
							class="regular-text" 
							placeholder="<?php esc_attr_e( 'Enter test value...', 'amplified-import-export' ); ?>"
						>
					</div>
					<button type="button" class="button aie-test-pipeline">
						<span class="dashicons dashicons-media-code"></span>
						<?php esc_html_e( 'Test Pipeline', 'amplified-import-export' ); ?>
					</button>
				</div>

				<div class="aie-preview-result" id="aie-preview-result" style="display:none;">
					<div class="aie-preview-steps">
						<!-- Steps will be added dynamically -->
					</div>
				</div>
			</div>
		</div>

		<div class="aie-modal-footer">
			<button type="button" class="button button-secondary aie-modal-cancel">
				<?php esc_html_e( 'Cancel', 'amplified-import-export' ); ?>
			</button>
			<button type="button" class="button button-primary aie-save-field-functions">
				<span class="dashicons dashicons-yes"></span>
				<?php esc_html_e( 'Apply Functions', 'amplified-import-export' ); ?>
			</button>
		</div>
	</div>
</div>
