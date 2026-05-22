<?php
/**
 * Import Step 3: Preview Data
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 3: Preview Data -->
<div class="rsl-ie-step rsl-ie-step-3" data-step="3">
	<div class="rsl-ie-step-header">
		<h2><?php esc_html_e( 'Step 3: Preview Data', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Review the first few rows from your file', 'import-export-by-rockstarlab' ); ?></p>
	</div>

	<div class="rsl-ie-step-content">
		<div class="rsl-ie-preview-container">
			<div class="rsl-ie-preview-stats">
				<div class="rsl-ie-stat">
					<span class="rsl-ie-stat-label"><?php esc_html_e( 'Total Rows:', 'import-export-by-rockstarlab' ); ?></span>
					<span class="rsl-ie-stat-value rsl-ie-total-rows">-</span>
				</div>
				<div class="rsl-ie-stat">
					<span class="rsl-ie-stat-label"><?php esc_html_e( 'Columns:', 'import-export-by-rockstarlab' ); ?></span>
					<span class="rsl-ie-stat-value rsl-ie-total-columns">-</span>
				</div>
			</div>

			<div class="rsl-ie-preview-table-container">
				<table class="wp-list-table widefat fixed striped rsl-ie-preview-table">
					<thead></thead>
					<tbody></tbody>
				</table>
			</div>

			<!-- JSON Preview -->
			<div class="rsl-ie-json-preview-container" style="display:none;">
				<div class="rsl-ie-json-preview"></div>
			</div>

			<p class="description rsl-ie-preview-note">
				<?php esc_html_e( 'Showing first 5 rows.', 'import-export-by-rockstarlab' ); ?>
			</p>
		</div>

		<div class="rsl-ie-step-actions">
			<button type="button" class="button button-secondary rsl-ie-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary button-large rsl-ie-next-step">
				<?php esc_html_e( 'Next Step', 'import-export-by-rockstarlab' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
