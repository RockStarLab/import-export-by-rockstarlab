<?php
/**
 * Export Step 3: Select Fields with Drag & Drop
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 3: Select Fields -->
<div class="aie-step aie-step-3" data-step="3">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Step 3: Select Fields', 'advanced-import-export' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Drag and drop fields to build your export structure. Click on a field to assign functions.', 'advanced-import-export' ); ?></p>
	</div>

	<div class="aie-step-content">
		<div class="aie-step-3-columns">
			<!-- Left Column: Export File Structure (CSV Builder) -->
			<div class="aie-csv-builder">
			<div class="aie-csv-builder-header">
				<h3>
					<span class="dashicons dashicons-media-spreadsheet"></span>
					<?php esc_html_e( 'Export File Structure', 'advanced-import-export' ); ?>
				</h3>
				<div class="aie-csv-builder-actions">
					<button type="button" class="button button-small aie-clear-all-fields" title="<?php esc_attr_e( 'Clear all fields', 'advanced-import-export' ); ?>">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Clear All', 'advanced-import-export' ); ?>
					</button>
					<button type="button" class="button button-small aie-add-custom-column" title="<?php esc_attr_e( 'Add custom column', 'advanced-import-export' ); ?>">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Custom', 'advanced-import-export' ); ?>
					</button>
				</div>
			</div>

			<div class="aie-csv-builder-body">
				<!-- Drop Zone -->
				<div class="aie-csv-dropzone" id="aie-csv-dropzone">
					<div class="aie-csv-dropzone-placeholder">
						<span class="dashicons dashicons-download"></span>
						<p><?php esc_html_e( 'Drag fields here to build your export file', 'advanced-import-export' ); ?></p>
						<span class="aie-csv-hint"><?php esc_html_e( 'or click "Add Custom" to create a custom column', 'advanced-import-export' ); ?></span>
					</div>
					
					<!-- Selected Fields Container -->
					<div class="aie-csv-columns" id="aie-csv-columns">
						<!-- Columns will be added here dynamically -->
					</div>
				</div>

				<!-- Field Counter -->
				<div class="aie-csv-stats">
					<span class="aie-field-count">
						<strong><?php esc_html_e( 'Columns:', 'advanced-import-export' ); ?></strong> 
						<span class="aie-count-value aie-columns-count">0</span>
					</span>
				</div>
			</div>
		</div>

		<!-- Right Column: Available Fields Library -->
		<div class="aie-fields-library">
			<div class="aie-fields-library-header">
				<h3>
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'Available Fields', 'advanced-import-export' ); ?>
				</h3>
			</div>
			
			<!-- Search/Filter -->
			<div class="aie-fields-search">
				<input 
					type="text" 
					id="aie-fields-search" 
					class="regular-text" 
					placeholder="<?php esc_attr_e( 'Search fields...', 'advanced-import-export' ); ?>"
				>
				<span class="dashicons dashicons-search"></span>
				<button type="button" class="aie-clear-search" title="<?php esc_attr_e( 'Clear search', 'advanced-import-export' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>

			<div class="aie-fields-library-body">
						<!-- Static fields will be loaded here dynamically based on content type -->
						
						<!-- Taxonomies (Dynamic - loaded via AJAX) -->
						<div class="aie-field-category aie-collapsed aie-taxonomies-category" style="display:none;">
							<h4 class="aie-field-category-title">
								<span class="dashicons dashicons-arrow-down-alt2 aie-category-toggle"></span>
								<span class="dashicons dashicons-category"></span>
								<?php esc_html_e( 'Taxonomies', 'advanced-import-export' ); ?>
								<button type="button" class="aie-add-all-fields" title="<?php esc_attr_e( 'Add all fields from this category', 'advanced-import-export' ); ?>">
									<?php esc_html_e( 'Add all', 'advanced-import-export' ); ?>
								</button>
							</h4>
							<div class="aie-fields-grid aie-taxonomies-grid">
								<!-- Taxonomies will be loaded dynamically based on post type -->
							</div>
						</div>

						<!-- Custom Fields (Dynamic - loaded via AJAX) -->
						<div class="aie-field-category aie-collapsed aie-custom-fields-category" style="display:none;">
							<h4 class="aie-field-category-title">
								<span class="dashicons dashicons-arrow-down-alt2 aie-category-toggle"></span>
								<span class="dashicons dashicons-admin-generic"></span>
								<?php esc_html_e( 'Custom Fields', 'advanced-import-export' ); ?>
								<button type="button" class="aie-add-all-fields" title="<?php esc_attr_e( 'Add all fields from this category', 'advanced-import-export' ); ?>">
									<?php esc_html_e( 'Add all', 'advanced-import-export' ); ?>
								</button>
							</h4>
							<div class="aie-fields-grid aie-custom-fields-grid">
								<!-- Custom fields will be loaded dynamically based on post type -->
							</div>
						</div>

						<!-- ACF Fields (Dynamic - loaded via AJAX) -->
						<div class="aie-field-category aie-collapsed aie-acf-fields-category" style="display:none;">
							<h4 class="aie-field-category-title">
								<span class="dashicons dashicons-arrow-down-alt2 aie-category-toggle"></span>
								<span class="dashicons dashicons-admin-settings"></span>
								<?php esc_html_e( 'ACF Fields', 'advanced-import-export' ); ?>
								<button type="button" class="aie-add-all-fields" title="<?php esc_attr_e( 'Add all fields from this category', 'advanced-import-export' ); ?>">
									<?php esc_html_e( 'Add all', 'advanced-import-export' ); ?>
								</button>
							</h4>
							<div class="aie-fields-grid aie-acf-fields-grid">
								<div class="aie-acf-loading">
									<span class="spinner is-active"></span>
									<p><?php esc_html_e( 'Loading ACF fields...', 'advanced-import-export' ); ?></p>
								</div>
							</div>
						</div>

						<!-- Yoast SEO Fields (Dynamic - loaded via AJAX) -->
						<div class="aie-field-category aie-collapsed aie-yoast-fields-category" style="display:none;">
							<h4 class="aie-field-category-title">
								<span class="dashicons dashicons-arrow-down-alt2 aie-category-toggle"></span>
								<span class="dashicons dashicons-chart-line"></span>
								<?php esc_html_e( 'Yoast SEO', 'advanced-import-export' ); ?>
								<button type="button" class="aie-add-all-fields" title="<?php esc_attr_e( 'Add all fields from this category', 'advanced-import-export' ); ?>">
									<?php esc_html_e( 'Add all', 'advanced-import-export' ); ?>
								</button>
							</h4>
							<div class="aie-fields-grid aie-yoast-fields-grid">
								<div class="aie-yoast-loading">
									<span class="spinner is-active"></span>
									<p><?php esc_html_e( 'Loading Yoast SEO fields...', 'advanced-import-export' ); ?></p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

		<!-- Step Actions -->
		<div class="aie-step-actions">
			<button type="button" class="button button-secondary aie-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'advanced-import-export' ); ?>
			</button>
			<button type="button" class="button button-primary button-large aie-next-step" disabled>
				<?php esc_html_e( 'Next Step', 'advanced-import-export' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
