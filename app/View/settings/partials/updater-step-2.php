<?php
/**
 * Content Updater Step 2: Filter Data
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 2: Filters -->
<div class="rsl-ie-step rsl-ie-updater-step-2" data-step="2">
	<div class="rsl-ie-step-header">
		<h2><?php esc_html_e( 'Step 2: Filter Data (Optional)', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Apply filters to select specific items to update. Leave empty to update all items.', 'import-export-by-rockstarlab' ); ?></p>
	</div>

	<div class="rsl-ie-step-content">
		
		<!-- Database Table Selection (shown only for database_table type) -->
		<div class="rsl-ie-table-selection-section" style="display:none;">
			<div class="rsl-ie-section-header">
				<h3>
					<span class="dashicons dashicons-database-view"></span>
					<?php esc_html_e( 'Select Database Table', 'import-export-by-rockstarlab' ); ?>
				</h3>
				<p class="description"><?php esc_html_e( 'Choose which database table you want to update', 'import-export-by-rockstarlab' ); ?></p>
			</div>

			<div class="rsl-ie-table-selector">
				<label for="rsl-ie-updater-table-name"><?php esc_html_e( 'Database Table:', 'import-export-by-rockstarlab' ); ?></label>
				<select id="rsl-ie-updater-table-name" name="table_name" class="rsl-ie-table-dropdown">
					<option value=""><?php esc_html_e( 'Loading tables...', 'import-export-by-rockstarlab' ); ?></option>
				</select>
				<span class="spinner" style="float:none;margin:0 10px;"></span>
			</div>

			<div class="rsl-ie-table-info" style="display:none;">
				<div class="rsl-ie-info-card">
					<h4><?php esc_html_e( 'Table Information', 'import-export-by-rockstarlab' ); ?></h4>
					<div class="rsl-ie-table-stats">
						<div class="rsl-ie-stat">
							<span class="label"><?php esc_html_e( 'Total Rows:', 'import-export-by-rockstarlab' ); ?></span>
							<span class="value rsl-ie-table-row-count">-</span>
						</div>
						<div class="rsl-ie-stat">
							<span class="label"><?php esc_html_e( 'Total Columns:', 'import-export-by-rockstarlab' ); ?></span>
							<span class="value rsl-ie-table-column-count">-</span>
						</div>
					</div>
					<div class="rsl-ie-table-columns">
						<h5><?php esc_html_e( 'Available Columns:', 'import-export-by-rockstarlab' ); ?></h5>
						<div class="rsl-ie-columns-list"></div>
					</div>
				</div>
			</div>
		</div>
		
		<!-- Info Notice -->
		<div class="rsl-ie-updater-filters-notice">
			<div class="notice notice-info inline">
				<p>
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'Filters are optional. If you don\'t add any filters, all items of the selected content type will be updated.', 'import-export-by-rockstarlab' ); ?>
				</p>
			</div>
		</div>

		<!-- Item Count Summary (Top) -->
		<div class="rsl-ie-filter-summary-top">
			<div class="rsl-ie-summary-card">
				<div class="rsl-ie-summary-icon">
					<span class="dashicons dashicons-database"></span>
				</div>
				<div class="rsl-ie-summary-content">
					<div class="rsl-ie-summary-label"><?php esc_html_e( 'Total Items to Update', 'import-export-by-rockstarlab' ); ?></div>
					<div class="rsl-ie-item-count">
						<span class="rsl-ie-count-value">-</span>
						<div class="spinner"></div>
					</div>
				</div>
				<button type="button" class="button rsl-ie-updater-refresh-count">
					<span class="dashicons dashicons-update"></span>
				</button>
			</div>
		</div>

		<!-- Custom Filters Section -->
		<div class="rsl-ie-custom-filters-section">
			<div class="rsl-ie-section-header">
				<h3>
					<span class="dashicons dashicons-filter"></span>
					<?php esc_html_e( 'Customize Filters', 'import-export-by-rockstarlab' ); ?>
				</h3>
				<p class="description"><?php esc_html_e( 'Add custom filters to narrow down which items to update', 'import-export-by-rockstarlab' ); ?></p>
			</div>

			<!-- Filters Container -->
			<div class="rsl-ie-filters-list rsl-ie-updater-filters-list" id="rsl-ie-updater-filters-list">
				<!-- Filters will be added here dynamically -->
			</div>

			<!-- Add Filter Button -->
			<div class="rsl-ie-add-filter-wrap">
				<button type="button" class="button button-secondary rsl-ie-updater-add-filter">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add Filter', 'import-export-by-rockstarlab' ); ?>
				</button>
			</div>
		</div>

		<!-- Hidden Template for Filter Row -->
		<template id="rsl-ie-updater-filter-row-template">
			<div class="rsl-ie-filter-row">
				<div class="rsl-ie-filter-row-inner">
					<!-- Field Selection -->
					<div class="rsl-ie-filter-field-wrap">
						<label><?php esc_html_e( 'Field', 'import-export-by-rockstarlab' ); ?></label>
						<select class="rsl-ie-updater-filter-field" name="updater_filter_field[]">
							<option value=""><?php esc_html_e( 'Select Field...', 'import-export-by-rockstarlab' ); ?></option>
						</select>
					</div>

					<!-- Condition Selection -->
					<div class="rsl-ie-filter-condition-wrap">
						<label><?php esc_html_e( 'Condition', 'import-export-by-rockstarlab' ); ?></label>
						<select class="rsl-ie-updater-filter-condition" name="updater_filter_condition[]">
							<option value=""><?php esc_html_e( 'Select...', 'import-export-by-rockstarlab' ); ?></option>
						</select>
					</div>

					<!-- Value Input -->
					<div class="rsl-ie-filter-value-wrap">
						<label><?php esc_html_e( 'Value', 'import-export-by-rockstarlab' ); ?></label>
						<input type="text" class="rsl-ie-updater-filter-value" name="updater_filter_value[]" placeholder="<?php esc_attr_e( 'Enter value...', 'import-export-by-rockstarlab' ); ?>">
					</div>

					<!-- Remove Button -->
					<div class="rsl-ie-filter-actions">
						<button type="button" class="button button-link-delete rsl-ie-updater-remove-filter" title="<?php esc_attr_e( 'Remove filter', 'import-export-by-rockstarlab' ); ?>">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</div>
				</div>
			</div>
		</template>

		<div class="rsl-ie-step-actions">
			<button type="button" class="button button-secondary rsl-ie-updater-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary button-large rsl-ie-updater-next-step">
				<?php esc_html_e( 'Next Step', 'import-export-by-rockstarlab' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
