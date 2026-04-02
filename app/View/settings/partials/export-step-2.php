<?php
/**
 * Export Step 2: Filter Data
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 2: Filters -->
<div class="aie-step aie-step-2" data-step="2">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Step 2: Filter Data', 'advanced-import-export' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Apply filters to select specific data to export', 'advanced-import-export' ); ?></p>
	</div>

	<div class="aie-step-content">
		
		<!-- Database Table Selection (shown only for database_table type) -->
		<div class="aie-table-selection-section" style="display:none;">
			<div class="aie-section-header">
				<h3>
					<span class="dashicons dashicons-database-view"></span>
					<?php esc_html_e( 'Select Database Table', 'advanced-import-export' ); ?>
				</h3>
				<p class="description"><?php esc_html_e( 'Choose which database table you want to export', 'advanced-import-export' ); ?></p>
			</div>

			<div class="aie-table-selector">
				<label for="aie-table-name"><?php esc_html_e( 'Database Table:', 'advanced-import-export' ); ?></label>
				<select id="aie-table-name" name="table_name" class="aie-table-dropdown">
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

		<!-- Item Count Summary (Top) -->
		<div class="aie-filter-summary-top">
			<div class="aie-summary-card">
				<div class="aie-summary-icon">
					<span class="dashicons dashicons-database"></span>
				</div>
				<div class="aie-summary-content">
					<div class="aie-summary-label"><?php esc_html_e( 'Total Items Available', 'advanced-import-export' ); ?></div>
					<div class="aie-item-count">
						<span class="aie-count-value">-</span>
						<div class="spinner"></div>
					</div>
				</div>
				<button type="button" class="button aie-refresh-count">
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
				<p class="description"><?php esc_html_e( 'Add custom filters to narrow down your export', 'advanced-import-export' ); ?></p>
			</div>

			<!-- Filters Container -->
			<div class="aie-filters-list" id="aie-filters-list">
				<!-- Filters will be added here dynamically -->
			</div>

			<!-- Add Filter Button -->
			<div class="aie-add-filter-wrap">
				<button type="button" class="button button-secondary aie-add-filter">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add Filter', 'advanced-import-export' ); ?>
				</button>
			</div>
		</div>

		<!-- Hidden Template for Filter Row -->
		<template id="aie-filter-row-template">
			<div class="aie-filter-row">
				<div class="aie-filter-row-inner">
					<!-- Field Selection -->
					<div class="aie-filter-field-wrap">
						<label><?php esc_html_e( 'Field', 'advanced-import-export' ); ?></label>
						<select class="aie-filter-field" name="filter_field[]">
							<option value=""><?php esc_html_e( 'Select Field...', 'advanced-import-export' ); ?></option>
						</select>
					</div>

					<!-- Condition Selection -->
					<div class="aie-filter-condition-wrap">
						<label><?php esc_html_e( 'Condition', 'advanced-import-export' ); ?></label>
						<select class="aie-filter-condition" name="filter_condition[]">
							<option value=""><?php esc_html_e( 'Select...', 'advanced-import-export' ); ?></option>
						</select>
					</div>

					<!-- Value Input -->
					<div class="aie-filter-value-wrap">
						<label><?php esc_html_e( 'Value', 'advanced-import-export' ); ?></label>
						<input type="text" class="aie-filter-value" name="filter_value[]" placeholder="<?php esc_attr_e( 'Enter value...', 'advanced-import-export' ); ?>">
					</div>

					<!-- Remove Button -->
					<div class="aie-filter-actions">
						<button type="button" class="button button-link-delete aie-remove-filter" title="<?php esc_attr_e( 'Remove filter', 'advanced-import-export' ); ?>">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</div>
				</div>
			</div>
		</template>

		<div class="aie-step-actions">
			<button type="button" class="button button-secondary aie-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'advanced-import-export' ); ?>
			</button>
			<button type="button" class="button button-primary button-large aie-next-step">
				<?php esc_html_e( 'Next Step', 'advanced-import-export' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
