<?php
/**
 * Content Updater Step 3: Assign Functions to Fields
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 4: Assign Functions -->
<div class="rsl-ie-step rsl-ie-updater-step-4" data-step="4">
	<div class="rsl-ie-step-header">
		<h2><?php esc_html_e( 'Step 4: Assign Functions to Fields', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Assign transformation functions to each field', 'import-export-by-rockstarlab' ); ?></p>
	</div>

	<div class="rsl-ie-step-content">
		<div class="rsl-ie-functions-assignment">
			<div class="rsl-ie-functions-header">
				<div class="rsl-ie-functions-header-content">
					<span class="dashicons dashicons-admin-tools"></span>
					<h3><?php esc_html_e( 'Field Functions', 'import-export-by-rockstarlab' ); ?></h3>
				</div>
				<div class="rsl-ie-functions-actions">
					<button type="button" class="button button-secondary rsl-ie-clear-all-functions">
						<span class="dashicons dashicons-dismiss"></span>
						<?php esc_html_e( 'Clear All', 'import-export-by-rockstarlab' ); ?>
					</button>
				</div>
			</div>

			<!-- Functions Assignment Table -->
			<div class="rsl-ie-functions-table-wrapper">
				<table class="wp-list-table widefat fixed striped rsl-ie-functions-table">
					<thead>
						<tr>
							<th class="rsl-ie-field-name-col"><?php esc_html_e( 'Field', 'import-export-by-rockstarlab' ); ?></th>
							<th class="rsl-ie-field-type-col"><?php esc_html_e( 'Type', 'import-export-by-rockstarlab' ); ?></th>
							<th class="rsl-ie-functions-col"><?php esc_html_e( 'Functions', 'import-export-by-rockstarlab' ); ?></th>
							<th class="rsl-ie-actions-col"><?php esc_html_e( 'Actions', 'import-export-by-rockstarlab' ); ?></th>
						</tr>
					</thead>
					<tbody id="rsl-ie-updater-functions-tbody">
						<!-- Function rows will be added here dynamically -->
						<tr class="rsl-ie-no-fields-row">
							<td colspan="4" class="rsl-ie-no-fields-message">
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e( 'No fields selected. Please go back and select fields first.', 'import-export-by-rockstarlab' ); ?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Stats Summary -->
			<div class="rsl-ie-functions-stats">
				<div class="rsl-ie-stat-card">
					<span class="rsl-ie-stat-icon dashicons dashicons-admin-generic"></span>
					<div class="rsl-ie-stat-content">
						<span class="rsl-ie-stat-label"><?php esc_html_e( 'Total Fields', 'import-export-by-rockstarlab' ); ?></span>
						<span class="rsl-ie-stat-value rsl-ie-total-fields-stat">0</span>
					</div>
				</div>
				<div class="rsl-ie-stat-card">
					<span class="rsl-ie-stat-icon dashicons dashicons-yes-alt"></span>
					<div class="rsl-ie-stat-content">
						<span class="rsl-ie-stat-label"><?php esc_html_e( 'Functions Assigned', 'import-export-by-rockstarlab' ); ?></span>
						<span class="rsl-ie-stat-value rsl-ie-functions-assigned-stat">0</span>
					</div>
				</div>
				<div class="rsl-ie-stat-card">
					<span class="rsl-ie-stat-icon dashicons dashicons-dismiss"></span>
					<div class="rsl-ie-stat-content">
						<span class="rsl-ie-stat-label"><?php esc_html_e( 'No Function', 'import-export-by-rockstarlab' ); ?></span>
						<span class="rsl-ie-stat-value rsl-ie-no-function-stat">0</span>
					</div>
				</div>
			</div>
		</div>

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
