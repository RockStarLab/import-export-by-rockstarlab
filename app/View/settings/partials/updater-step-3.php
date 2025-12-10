<?php
/**
 * Content Updater Step 3: Assign Functions to Fields
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 3: Assign Functions -->
<div class="aie-step aie-updater-step-3" data-step="3">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Step 3: Assign Functions to Fields', 'wp-advanced-import-export' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Assign transformation functions to each field', 'wp-advanced-import-export' ); ?></p>
	</div>

	<div class="aie-step-content">
		<div class="aie-functions-assignment">
			<div class="aie-functions-header">
				<div class="aie-functions-header-content">
					<span class="dashicons dashicons-admin-tools"></span>
					<h3><?php esc_html_e( 'Field Functions', 'wp-advanced-import-export' ); ?></h3>
				</div>
				<div class="aie-functions-actions">
					<button type="button" class="button button-secondary aie-apply-function-to-all">
						<span class="dashicons dashicons-controls-repeat"></span>
						<?php esc_html_e( 'Apply to All', 'wp-advanced-import-export' ); ?>
					</button>
					<button type="button" class="button button-secondary aie-clear-all-functions">
						<span class="dashicons dashicons-dismiss"></span>
						<?php esc_html_e( 'Clear All', 'wp-advanced-import-export' ); ?>
					</button>
				</div>
			</div>

			<div class="aie-functions-notice notice notice-info">
				<p>
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'Assign custom functions to transform field values during the update process. You can select "None" to skip a field.', 'wp-advanced-import-export' ); ?>
				</p>
			</div>

			<!-- Functions Assignment Table -->
			<div class="aie-functions-table-wrapper">
				<table class="wp-list-table widefat fixed striped aie-functions-table">
					<thead>
						<tr>
							<th class="aie-field-name-col"><?php esc_html_e( 'Field', 'wp-advanced-import-export' ); ?></th>
							<th class="aie-field-type-col"><?php esc_html_e( 'Type', 'wp-advanced-import-export' ); ?></th>
							<th class="aie-functions-col"><?php esc_html_e( 'Functions', 'wp-advanced-import-export' ); ?></th>
							<th class="aie-actions-col"><?php esc_html_e( 'Actions', 'wp-advanced-import-export' ); ?></th>
						</tr>
					</thead>
					<tbody id="aie-updater-functions-tbody">
						<!-- Function rows will be added here dynamically -->
						<tr class="aie-no-fields-row">
							<td colspan="4" class="aie-no-fields-message">
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e( 'No fields selected. Please go back and select fields first.', 'wp-advanced-import-export' ); ?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Stats Summary -->
			<div class="aie-functions-stats">
				<div class="aie-stat-card">
					<span class="aie-stat-icon dashicons dashicons-admin-generic"></span>
					<div class="aie-stat-content">
						<span class="aie-stat-label"><?php esc_html_e( 'Total Fields', 'wp-advanced-import-export' ); ?></span>
						<span class="aie-stat-value aie-total-fields-stat">0</span>
					</div>
				</div>
				<div class="aie-stat-card">
					<span class="aie-stat-icon dashicons dashicons-yes-alt"></span>
					<div class="aie-stat-content">
						<span class="aie-stat-label"><?php esc_html_e( 'Functions Assigned', 'wp-advanced-import-export' ); ?></span>
						<span class="aie-stat-value aie-functions-assigned-stat">0</span>
					</div>
				</div>
				<div class="aie-stat-card">
					<span class="aie-stat-icon dashicons dashicons-dismiss"></span>
					<div class="aie-stat-content">
						<span class="aie-stat-label"><?php esc_html_e( 'No Function', 'wp-advanced-import-export' ); ?></span>
						<span class="aie-stat-value aie-no-function-stat">0</span>
					</div>
				</div>
			</div>
		</div>

		<div class="aie-step-actions">
			<button type="button" class="button button-secondary aie-updater-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'wp-advanced-import-export' ); ?>
			</button>
			<button type="button" class="button button-primary button-large aie-updater-next-step">
				<?php esc_html_e( 'Next Step', 'wp-advanced-import-export' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
