<?php
/**
 * Content Updater Field Functions Modal
 * Modal for assigning transformation functions to update fields
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Field Functions Modal -->
<div id="rsl-ie-updater-functions-modal" class="rsl-ie-modal" style="display:none;">
	<div class="rsl-ie-modal-backdrop"></div>
	<div class="rsl-ie-modal-content rsl-ie-field-functions-modal-content">
		<div class="rsl-ie-modal-header">
			<h2 class="rsl-ie-modal-title">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'Field Transformation Functions', 'import-export-by-rockstarlab' ); ?>
			</h2>
			<button type="button" class="rsl-ie-modal-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="rsl-ie-modal-body">
			<!-- Field Info -->
			<div class="rsl-ie-field-info">
				<div class="rsl-ie-field-info-item">
					<strong><?php esc_html_e( 'Field:', 'import-export-by-rockstarlab' ); ?></strong>
					<span class="rsl-ie-current-field-label"></span>
				</div>
				<div class="rsl-ie-field-info-item">
					<strong><?php esc_html_e( 'Type:', 'import-export-by-rockstarlab' ); ?></strong>
					<span class="rsl-ie-current-field-type"></span>
				</div>
			</div>

			<!-- Applied Functions List -->
			<div class="rsl-ie-applied-functions">
				<h3>
					<?php esc_html_e( 'Applied Functions', 'import-export-by-rockstarlab' ); ?>
					<span class="rsl-ie-functions-count">(0)</span>
				</h3>
				
				<div class="rsl-ie-functions-pipeline" id="rsl-ie-updater-functions-pipeline">
					<div class="rsl-ie-no-functions">
						<span class="dashicons dashicons-info"></span>
						<p><?php esc_html_e( 'No functions applied yet. Add functions from the list below.', 'import-export-by-rockstarlab' ); ?></p>
					</div>
					
					<!-- Functions will be added here -->
					<div class="rsl-ie-function-items" id="rsl-ie-updater-function-items">
						<!-- Functions will be dynamically added here -->
					</div>
				</div>

				<div class="rsl-ie-pipeline-hint">
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'Functions are applied in order from top to bottom. Drag to reorder.', 'import-export-by-rockstarlab' ); ?>
				</div>
			</div>

			<!-- Available Functions -->
			<div class="rsl-ie-available-functions">
				<h3><?php esc_html_e( 'Available Functions', 'import-export-by-rockstarlab' ); ?></h3>
				
				<!-- Search Functions -->
				<div class="rsl-ie-functions-search">
					<input 
						type="text" 
						id="rsl-ie-updater-functions-search" 
						class="regular-text" 
						placeholder="<?php esc_attr_e( 'Search functions...', 'import-export-by-rockstarlab' ); ?>"
					>
					<span class="dashicons dashicons-search"></span>
				</div>

				<!-- Functions Filter -->
				<div class="rsl-ie-functions-filter">
					<label>
						<input type="radio" name="updater-functions-filter" value="all" checked>
						<?php esc_html_e( 'All', 'import-export-by-rockstarlab' ); ?>
					</label>
					<label>
						<input type="radio" name="updater-functions-filter" value="library">
						<?php esc_html_e( 'Library', 'import-export-by-rockstarlab' ); ?>
					</label>
					<label>
						<input type="radio" name="updater-functions-filter" value="custom">
						<?php esc_html_e( 'Custom', 'import-export-by-rockstarlab' ); ?>
					</label>
				</div>

				<!-- Functions List -->
				<div class="rsl-ie-functions-list" id="rsl-ie-updater-functions-list">
					<div class="rsl-ie-functions-loading">
						<span class="spinner is-active"></span>
						<p><?php esc_html_e( 'Loading functions...', 'import-export-by-rockstarlab' ); ?></p>
					</div>
					
					<!-- Functions will be loaded here -->
				</div>

				<!-- Quick Add Link -->
				<div class="rsl-ie-functions-quick-add">
					<a href="#" class="rsl-ie-create-new-function">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Create New Function', 'import-export-by-rockstarlab' ); ?>
					</a>
				</div>
			</div>

			<!-- Preview Section -->
			<div class="rsl-ie-function-preview">
				<h3><?php esc_html_e( 'Preview Transformation', 'import-export-by-rockstarlab' ); ?></h3>
				
				<div class="rsl-ie-preview-controls">
					<div class="rsl-ie-preview-input-group">
						<label for="rsl-ie-updater-preview-input">
							<?php esc_html_e( 'Test Value:', 'import-export-by-rockstarlab' ); ?>
						</label>
						<input 
							type="text" 
							id="rsl-ie-updater-preview-input" 
							class="regular-text" 
							placeholder="<?php esc_attr_e( 'Enter test value...', 'import-export-by-rockstarlab' ); ?>"
						>
					</div>
					<button type="button" class="button rsl-ie-test-updater-pipeline">
						<span class="dashicons dashicons-media-code"></span>
						<?php esc_html_e( 'Test Pipeline', 'import-export-by-rockstarlab' ); ?>
					</button>
				</div>

				<div class="rsl-ie-preview-result" id="rsl-ie-updater-preview-result" style="display:none;">
					<div class="rsl-ie-preview-steps">
						<!-- Steps will be added dynamically -->
					</div>
				</div>
			</div>
		</div>

		<div class="rsl-ie-modal-footer">
			<button type="button" class="button button-secondary rsl-ie-modal-cancel">
				<?php esc_html_e( 'Cancel', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary rsl-ie-save-updater-functions">
				<span class="dashicons dashicons-yes"></span>
				<?php esc_html_e( 'Apply Functions', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
	</div>
</div>
