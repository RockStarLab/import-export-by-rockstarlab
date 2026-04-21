<?php
/**
 * Content Updater Step 2: Filter Data
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 2: Filters -->
<div class="aie-step aie-updater-step-2" data-step="2">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Step 2: Filter Data (Optional)', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Apply filters to select specific items to update. Leave empty to update all items.', 'import-export-by-rockstarlab' ); ?></p>
	</div>

	<div class="aie-step-content">
		
		<!-- Database Table Selection (shown only for database_table type) -->
		<div class="aie-table-selection-section" style="display:none;">
			<div class="aie-section-header">
				<h3>
					<span class="dashicons dashicons-database-view"></span>
					<?php esc_html_e( 'Select Database Table', 'import-export-by-rockstarlab' ); ?>
				</h3>
				<p class="description"><?php esc_html_e( 'Choose which database table you want to update', 'import-export-by-rockstarlab' ); ?></p>
			</div>

			<div class="aie-table-selector">
				<label for="aie-updater-table-name"><?php esc_html_e( 'Database Table:', 'import-export-by-rockstarlab' ); ?></label>
				<select id="aie-updater-table-name" name="table_name" class="aie-table-dropdown">
					<option value=""><?php esc_html_e( 'Loading tables...', 'import-export-by-rockstarlab' ); ?></option>
				</select>
				<span class="spinner" style="float:none;margin:0 10px;"></span>
			</div>

			<div class="aie-table-info" style="display:none;">
				<div class="aie-info-card">
					<h4><?php esc_html_e( 'Table Information', 'import-export-by-rockstarlab' ); ?></h4>
					<div class="aie-table-stats">
						<div class="aie-stat">
							<span class="label"><?php esc_html_e( 'Total Rows:', 'import-export-by-rockstarlab' ); ?></span>
							<span class="value aie-table-row-count">-</span>
						</div>
						<div class="aie-stat">
							<span class="label"><?php esc_html_e( 'Total Columns:', 'import-export-by-rockstarlab' ); ?></span>
							<span class="value aie-table-column-count">-</span>
						</div>
					</div>
					<div class="aie-table-columns">
						<h5><?php esc_html_e( 'Available Columns:', 'import-export-by-rockstarlab' ); ?></h5>
						<div class="aie-columns-list"></div>
					</div>
				</div>
			</div>
		</div>
		
		<!-- Info Notice -->
		<div class="aie-updater-filters-notice">
			<div class="notice notice-info inline">
				<p>
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'Filters are optional. If you don\'t add any filters, all items of the selected content type will be updated.', 'import-export-by-rockstarlab' ); ?>
				</p>
			</div>
		</div>

		<!-- Item Count Summary (Top) -->
		<div class="aie-filter-summary-top">
			<div class="aie-summary-card">
				<div class="aie-summary-icon">
					<span class="dashicons dashicons-database"></span>
				</div>
				<div class="aie-summary-content">
					<div class="aie-summary-label"><?php esc_html_e( 'Total Items to Update', 'import-export-by-rockstarlab' ); ?></div>
					<div class="aie-item-count">
						<span class="aie-count-value">-</span>
						<div class="spinner"></div>
					</div>
				</div>
				<button type="button" class="button aie-updater-refresh-count">
					<span class="dashicons dashicons-update"></span>
				</button>
			</div>
		</div>

		<!-- Custom Filters Section -->
		<div class="aie-custom-filters-section">
			<div class="aie-section-header">
				<h3>
					<span class="dashicons dashicons-filter"></span>
					<?php esc_html_e( 'Customize Filters', 'import-export-by-rockstarlab' ); ?>
				</h3>
				<p class="description"><?php esc_html_e( 'Add custom filters to narrow down which items to update', 'import-export-by-rockstarlab' ); ?></p>
			</div>

			<!-- Filters Container -->
			<div class="aie-filters-list aie-updater-filters-list" id="aie-updater-filters-list">
				<!-- Filters will be added here dynamically -->
			</div>

			<!-- Add Filter Button -->
			<div class="aie-add-filter-wrap">
				<button type="button" class="button button-secondary aie-updater-add-filter">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add Filter', 'import-export-by-rockstarlab' ); ?>
				</button>
			</div>
		</div>

		<!-- Hidden Template for Filter Row -->
		<template id="aie-updater-filter-row-template">
			<div class="aie-filter-row">
				<div class="aie-filter-row-inner">
					<!-- Field Selection -->
					<div class="aie-filter-field-wrap">
						<label><?php esc_html_e( 'Field', 'import-export-by-rockstarlab' ); ?></label>
						<select class="aie-updater-filter-field" name="updater_filter_field[]">
							<option value=""><?php esc_html_e( 'Select Field...', 'import-export-by-rockstarlab' ); ?></option>
						</select>
					</div>

					<!-- Condition Selection -->
					<div class="aie-filter-condition-wrap">
						<label><?php esc_html_e( 'Condition', 'import-export-by-rockstarlab' ); ?></label>
						<select class="aie-updater-filter-condition" name="updater_filter_condition[]">
							<option value=""><?php esc_html_e( 'Select...', 'import-export-by-rockstarlab' ); ?></option>
						</select>
					</div>

					<!-- Value Input -->
					<div class="aie-filter-value-wrap">
						<label><?php esc_html_e( 'Value', 'import-export-by-rockstarlab' ); ?></label>
						<input type="text" class="aie-updater-filter-value" name="updater_filter_value[]" placeholder="<?php esc_attr_e( 'Enter value...', 'import-export-by-rockstarlab' ); ?>">
					</div>

					<!-- Remove Button -->
					<div class="aie-filter-actions">
						<button type="button" class="button button-link-delete aie-updater-remove-filter" title="<?php esc_attr_e( 'Remove filter', 'import-export-by-rockstarlab' ); ?>">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</div>
				</div>
			</div>
		</template>

		<div class="aie-step-actions">
			<button type="button" class="button button-secondary aie-updater-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary button-large aie-updater-next-step">
				<?php esc_html_e( 'Next Step', 'import-export-by-rockstarlab' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
