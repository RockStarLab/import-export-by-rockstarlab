<?php
/**
 * Content Updater Step 3: Assign Functions to Fields
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 4: Assign Functions -->
<div class="aie-step aie-updater-step-4" data-step="4">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Step 4: Assign Functions to Fields', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Assign transformation functions to each field', 'import-export-by-rockstarlab' ); ?></p>
	</div>

	<div class="aie-step-content">
		<div class="aie-functions-assignment">
			<div class="aie-functions-header">
				<div class="aie-functions-header-content">
					<span class="dashicons dashicons-admin-tools"></span>
					<h3><?php esc_html_e( 'Field Functions', 'import-export-by-rockstarlab' ); ?></h3>
				</div>
				<div class="aie-functions-actions">
					<button type="button" class="button button-secondary aie-clear-all-functions">
						<span class="dashicons dashicons-dismiss"></span>
						<?php esc_html_e( 'Clear All', 'import-export-by-rockstarlab' ); ?>
					</button>
				</div>
			</div>

			<!-- Functions Assignment Table -->
			<div class="aie-functions-table-wrapper">
				<table class="wp-list-table widefat fixed striped aie-functions-table">
					<thead>
						<tr>
							<th class="aie-field-name-col"><?php esc_html_e( 'Field', 'import-export-by-rockstarlab' ); ?></th>
							<th class="aie-field-type-col"><?php esc_html_e( 'Type', 'import-export-by-rockstarlab' ); ?></th>
							<th class="aie-functions-col"><?php esc_html_e( 'Functions', 'import-export-by-rockstarlab' ); ?></th>
							<th class="aie-actions-col"><?php esc_html_e( 'Actions', 'import-export-by-rockstarlab' ); ?></th>
						</tr>
					</thead>
					<tbody id="aie-updater-functions-tbody">
						<!-- Function rows will be added here dynamically -->
						<tr class="aie-no-fields-row">
							<td colspan="4" class="aie-no-fields-message">
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e( 'No fields selected. Please go back and select fields first.', 'import-export-by-rockstarlab' ); ?>
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
						<span class="aie-stat-label"><?php esc_html_e( 'Total Fields', 'import-export-by-rockstarlab' ); ?></span>
						<span class="aie-stat-value aie-total-fields-stat">0</span>
					</div>
				</div>
				<div class="aie-stat-card">
					<span class="aie-stat-icon dashicons dashicons-yes-alt"></span>
					<div class="aie-stat-content">
						<span class="aie-stat-label"><?php esc_html_e( 'Functions Assigned', 'import-export-by-rockstarlab' ); ?></span>
						<span class="aie-stat-value aie-functions-assigned-stat">0</span>
					</div>
				</div>
				<div class="aie-stat-card">
					<span class="aie-stat-icon dashicons dashicons-dismiss"></span>
					<div class="aie-stat-content">
						<span class="aie-stat-label"><?php esc_html_e( 'No Function', 'import-export-by-rockstarlab' ); ?></span>
						<span class="aie-stat-value aie-no-function-stat">0</span>
					</div>
				</div>
			</div>
		</div>

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
