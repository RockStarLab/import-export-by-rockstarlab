<?php
/**
 * Content Updater Step 2: Filter Data
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 2: Filters -->
<div class="aie-step aie-updater-step-2" data-step="2">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Step 2: Filter Data (Optional)', 'advanced-import-export' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Apply filters to select specific items to update. Leave empty to update all items.', 'advanced-import-export' ); ?></p>
	</div>

	<div class="aie-step-content">
		
		<!-- Database Table Selection (shown only for database_table type) -->
		<div class="aie-table-selection-section" style="display:none;">
			<div class="aie-section-header">
				<h3>
					<span class="dashicons dashicons-database-view"></span>
					<?php esc_html_e( 'Select Database Table', 'advanced-import-export' ); ?>
				</h3>
				<p class="description"><?php esc_html_e( 'Choose which database table you want to update', 'advanced-import-export' ); ?></p>
			</div>

			<div class="aie-table-selector">
				<label for="aie-updater-table-name"><?php esc_html_e( 'Database Table:', 'advanced-import-export' ); ?></label>
				<select id="aie-updater-table-name" name="table_name" class="aie-table-dropdown">
					<option value=""><?php esc_html_e( 'Loading tables...', 'advanced-import-export' ); ?></option>
				</select>
				<span class="spinner" style="float:none;margin:0 10px;"></span>
			</div>

			<div class="aie-table-info" style="display:none;">
				<div class="aie-info-card">
					<h4><?php esc_html_e( 'Table Information', 'advanced-import-export' ); ?></h4>
					<div class="aie-table-stats">
						<div class="aie-stat">
							<span class="label"><?php esc_html_e( 'Total Rows:', 'advanced-import-export' ); ?></span>
							<span class="value aie-table-row-count">-</span>
						</div>
						<div class="aie-stat">
							<span class="label"><?php esc_html_e( 'Total Columns:', 'advanced-import-export' ); ?></span>
							<span class="value aie-table-column-count">-</span>
						</div>
					</div>
					<div class="aie-table-columns">
						<h5><?php esc_html_e( 'Available Columns:', 'advanced-import-export' ); ?></h5>
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
					<?php esc_html_e( 'Filters are optional. If you don\'t add any filters, all items of the selected content type will be updated.', 'advanced-import-export' ); ?>
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
					<div class="aie-summary-label"><?php esc_html_e( 'Total Items to Update', 'advanced-import-export' ); ?></div>
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
					<?php esc_html_e( 'Customize Filters', 'advanced-import-export' ); ?>
				</h3>
				<p class="description"><?php esc_html_e( 'Add custom filters to narrow down which items to update', 'advanced-import-export' ); ?></p>
			</div>

			<!-- Filters Container -->
			<div class="aie-filters-list aie-updater-filters-list" id="aie-updater-filters-list">
				<!-- Filters will be added here dynamically -->
			</div>

			<!-- Add Filter Button -->
			<div class="aie-add-filter-wrap">
				<button type="button" class="button button-secondary aie-updater-add-filter">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add Filter', 'advanced-import-export' ); ?>
				</button>
			</div>
		</div>

		<!-- Hidden Template for Filter Row -->
		<template id="aie-updater-filter-row-template">
			<div class="aie-filter-row">
				<div class="aie-filter-row-inner">
					<!-- Field Selection -->
					<div class="aie-filter-field-wrap">
						<label><?php esc_html_e( 'Field', 'advanced-import-export' ); ?></label>
						<select class="aie-updater-filter-field" name="updater_filter_field[]">
							<option value=""><?php esc_html_e( 'Select Field...', 'advanced-import-export' ); ?></option>
						</select>
					</div>

					<!-- Condition Selection -->
					<div class="aie-filter-condition-wrap">
						<label><?php esc_html_e( 'Condition', 'advanced-import-export' ); ?></label>
						<select class="aie-updater-filter-condition" name="updater_filter_condition[]">
							<option value=""><?php esc_html_e( 'Select...', 'advanced-import-export' ); ?></option>
						</select>
					</div>

					<!-- Value Input -->
					<div class="aie-filter-value-wrap">
						<label><?php esc_html_e( 'Value', 'advanced-import-export' ); ?></label>
						<input type="text" class="aie-updater-filter-value" name="updater_filter_value[]" placeholder="<?php esc_attr_e( 'Enter value...', 'advanced-import-export' ); ?>">
					</div>

					<!-- Remove Button -->
					<div class="aie-filter-actions">
						<button type="button" class="button button-link-delete aie-updater-remove-filter" title="<?php esc_attr_e( 'Remove filter', 'advanced-import-export' ); ?>">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</div>
				</div>
			</div>
		</template>

		<div class="aie-step-actions">
			<button type="button" class="button button-secondary aie-updater-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'advanced-import-export' ); ?>
			</button>
			<button type="button" class="button button-primary button-large aie-updater-next-step">
				<?php esc_html_e( 'Next Step', 'advanced-import-export' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
