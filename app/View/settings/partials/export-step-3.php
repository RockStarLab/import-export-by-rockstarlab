<?php
/**
 * Export Step 3: Select Fields with Drag & Drop
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

$rsl_ie_field_transformations_enabled = isset( $rsl_ie_field_transformations_enabled )
	? (bool) $rsl_ie_field_transformations_enabled
	: \RockStarLab\ImportExport\Helper\Field_Transformation_Bridge::is_enabled();
?>

<!-- Step 3: Select Fields -->
<div class="rsl-ie-step rsl-ie-step-3" data-step="3">
	<div class="rsl-ie-step-header">
		<h2><?php esc_html_e( 'Step 3: Select Fields', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description">
			<?php
			echo esc_html(
				$rsl_ie_field_transformations_enabled
					? __( 'Drag and drop fields to build your export structure. Field transformations are available for selected columns.', 'import-export-by-rockstarlab' )
					: __( 'Drag and drop fields to build your export structure.', 'import-export-by-rockstarlab' )
			);
			?>
		</p>
	</div>

	<div class="rsl-ie-step-content">
		<div class="rsl-ie-step-3-columns">
			<!-- Left Column: Export File Structure (CSV Builder) -->
			<div class="rsl-ie-csv-builder">
			<div class="rsl-ie-csv-builder-header">
				<h3>
					<span class="dashicons dashicons-media-spreadsheet"></span>
					<?php esc_html_e( 'Export File Structure', 'import-export-by-rockstarlab' ); ?>
				</h3>
				<div class="rsl-ie-csv-builder-actions">
					<button type="button" class="button button-small rsl-ie-clear-all-fields" title="<?php esc_attr_e( 'Clear all fields', 'import-export-by-rockstarlab' ); ?>">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Clear All', 'import-export-by-rockstarlab' ); ?>
					</button>
					<button type="button" class="button button-small rsl-ie-add-custom-column" title="<?php esc_attr_e( 'Add custom column', 'import-export-by-rockstarlab' ); ?>">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Custom', 'import-export-by-rockstarlab' ); ?>
					</button>
				</div>
			</div>

			<div class="rsl-ie-csv-builder-body">
				<!-- Drop Zone -->
				<div class="rsl-ie-csv-dropzone" id="rsl-ie-csv-dropzone">
					<div class="rsl-ie-csv-dropzone-placeholder">
						<span class="dashicons dashicons-download"></span>
						<p><?php esc_html_e( 'Drag fields here to build your export file', 'import-export-by-rockstarlab' ); ?></p>
						<span class="rsl-ie-csv-hint"><?php esc_html_e( 'or click "Add Custom" to create a custom column', 'import-export-by-rockstarlab' ); ?></span>
					</div>
					
					<!-- Selected Fields Container -->
					<div class="rsl-ie-csv-columns" id="rsl-ie-csv-columns">
						<!-- Columns will be added here dynamically -->
					</div>
				</div>

				<!-- Field Counter -->
				<div class="rsl-ie-csv-stats">
					<span class="rsl-ie-field-count">
						<strong><?php esc_html_e( 'Columns:', 'import-export-by-rockstarlab' ); ?></strong> 
						<span class="rsl-ie-count-value rsl-ie-columns-count">0</span>
					</span>
				</div>
			</div>
		</div>

		<!-- Right Column: Available Fields Library -->
		<div class="rsl-ie-fields-library">
			<div class="rsl-ie-fields-library-header">
				<h3>
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'Available Fields', 'import-export-by-rockstarlab' ); ?>
				</h3>
			</div>
			
			<!-- Search/Filter -->
			<div class="rsl-ie-fields-search">
				<input 
					type="text" 
					id="rsl-ie-fields-search" 
					class="regular-text" 
					placeholder="<?php esc_attr_e( 'Search fields...', 'import-export-by-rockstarlab' ); ?>"
				>
				<span class="dashicons dashicons-search"></span>
				<button type="button" class="rsl-ie-clear-search" title="<?php esc_attr_e( 'Clear search', 'import-export-by-rockstarlab' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>

			<div class="rsl-ie-fields-library-body">
						<!-- Static fields will be loaded here dynamically based on content type -->
						
						<!-- Taxonomies (Dynamic - loaded via AJAX) -->
						<div class="rsl-ie-field-category rsl-ie-collapsed rsl-ie-taxonomies-category" style="display:none;">
							<h4 class="rsl-ie-field-category-title">
								<span class="dashicons dashicons-arrow-down-alt2 rsl-ie-category-toggle"></span>
								<span class="dashicons dashicons-category"></span>
								<?php esc_html_e( 'Taxonomies', 'import-export-by-rockstarlab' ); ?>
								<button type="button" class="rsl-ie-add-all-fields" title="<?php esc_attr_e( 'Add all fields from this category', 'import-export-by-rockstarlab' ); ?>">
									<?php esc_html_e( 'Add all', 'import-export-by-rockstarlab' ); ?>
								</button>
							</h4>
							<div class="rsl-ie-fields-grid rsl-ie-taxonomies-grid">
								<!-- Taxonomies will be loaded dynamically based on post type -->
							</div>
						</div>

						<!-- Custom Fields (Dynamic - loaded via AJAX) -->
						<div class="rsl-ie-field-category rsl-ie-collapsed rsl-ie-custom-fields-category" style="display:none;">
							<h4 class="rsl-ie-field-category-title">
								<span class="dashicons dashicons-arrow-down-alt2 rsl-ie-category-toggle"></span>
								<span class="dashicons dashicons-admin-generic"></span>
								<?php esc_html_e( 'Custom Fields', 'import-export-by-rockstarlab' ); ?>
								<button type="button" class="rsl-ie-add-all-fields" title="<?php esc_attr_e( 'Add all fields from this category', 'import-export-by-rockstarlab' ); ?>">
									<?php esc_html_e( 'Add all', 'import-export-by-rockstarlab' ); ?>
								</button>
							</h4>
							<div class="rsl-ie-fields-grid rsl-ie-custom-fields-grid">
								<!-- Custom fields will be loaded dynamically based on post type -->
							</div>
						</div>

						<!-- ACF Fields (Dynamic - loaded via AJAX) -->
						<div class="rsl-ie-field-category rsl-ie-collapsed rsl-ie-acf-fields-category" style="display:none;">
							<h4 class="rsl-ie-field-category-title">
								<span class="dashicons dashicons-arrow-down-alt2 rsl-ie-category-toggle"></span>
								<span class="dashicons dashicons-admin-settings"></span>
								<?php esc_html_e( 'ACF Fields', 'import-export-by-rockstarlab' ); ?>
								<button type="button" class="rsl-ie-add-all-fields" title="<?php esc_attr_e( 'Add all fields from this category', 'import-export-by-rockstarlab' ); ?>">
									<?php esc_html_e( 'Add all', 'import-export-by-rockstarlab' ); ?>
								</button>
							</h4>
							<div class="rsl-ie-fields-grid rsl-ie-acf-fields-grid">
								<div class="rsl-ie-acf-loading">
									<span class="spinner is-active"></span>
									<p><?php esc_html_e( 'Loading ACF fields...', 'import-export-by-rockstarlab' ); ?></p>
								</div>
							</div>
						</div>

						<!-- Yoast SEO Fields (Dynamic - loaded via AJAX) -->
						<div class="rsl-ie-field-category rsl-ie-collapsed rsl-ie-yoast-fields-category" style="display:none;">
							<h4 class="rsl-ie-field-category-title">
								<span class="dashicons dashicons-arrow-down-alt2 rsl-ie-category-toggle"></span>
								<span class="dashicons dashicons-chart-line"></span>
								<?php esc_html_e( 'Yoast SEO', 'import-export-by-rockstarlab' ); ?>
								<button type="button" class="rsl-ie-add-all-fields" title="<?php esc_attr_e( 'Add all fields from this category', 'import-export-by-rockstarlab' ); ?>">
									<?php esc_html_e( 'Add all', 'import-export-by-rockstarlab' ); ?>
								</button>
							</h4>
							<div class="rsl-ie-fields-grid rsl-ie-yoast-fields-grid">
								<div class="rsl-ie-yoast-loading">
									<span class="spinner is-active"></span>
									<p><?php esc_html_e( 'Loading Yoast SEO fields...', 'import-export-by-rockstarlab' ); ?></p>
								</div>
							</div>
						</div>

						<!-- Rank Math SEO Fields (Dynamic - loaded via AJAX) -->
						<div class="rsl-ie-field-category rsl-ie-collapsed rsl-ie-rank-math-fields-category" style="display:none;">
							<h4 class="rsl-ie-field-category-title">
								<span class="dashicons dashicons-arrow-down-alt2 rsl-ie-category-toggle"></span>
								<span class="dashicons dashicons-chart-area"></span>
								<?php esc_html_e( 'Rank Math SEO', 'import-export-by-rockstarlab' ); ?>
								<button type="button" class="rsl-ie-add-all-fields" title="<?php esc_attr_e( 'Add all fields from this category', 'import-export-by-rockstarlab' ); ?>">
									<?php esc_html_e( 'Add all', 'import-export-by-rockstarlab' ); ?>
								</button>
							</h4>
							<div class="rsl-ie-fields-grid rsl-ie-rank-math-fields-grid">
								<div class="rsl-ie-rank-math-loading">
									<span class="spinner is-active"></span>
									<p><?php esc_html_e( 'Loading Rank Math SEO fields...', 'import-export-by-rockstarlab' ); ?></p>
								</div>
							</div>
						</div>

						<!-- Elementor Fields (Dynamic - loaded via AJAX) -->
						<div class="rsl-ie-field-category rsl-ie-collapsed rsl-ie-elementor-fields-category" style="display:none;">
							<h4 class="rsl-ie-field-category-title">
								<span class="dashicons dashicons-arrow-down-alt2 rsl-ie-category-toggle"></span>
								<span class="dashicons dashicons-layout"></span>
								<?php esc_html_e( 'Elementor', 'import-export-by-rockstarlab' ); ?>
								<button type="button" class="rsl-ie-add-all-fields" title="<?php esc_attr_e( 'Add all fields from this category', 'import-export-by-rockstarlab' ); ?>">
									<?php esc_html_e( 'Add all', 'import-export-by-rockstarlab' ); ?>
								</button>
							</h4>
							<div class="rsl-ie-fields-grid rsl-ie-elementor-fields-grid">
								<div class="rsl-ie-elementor-loading">
									<span class="spinner is-active"></span>
									<p><?php esc_html_e( 'Loading Elementor fields...', 'import-export-by-rockstarlab' ); ?></p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

		<!-- Step Actions -->
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
