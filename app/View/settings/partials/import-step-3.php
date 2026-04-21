<?php
/**
 * Import Step 3: Preview Data
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 3: Preview Data -->
<div class="aie-step aie-step-3" data-step="3">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Step 3: Preview Data', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Review the first few rows from your file', 'import-export-by-rockstarlab' ); ?></p>
	</div>

	<div class="aie-step-content">
		<div class="aie-preview-container">
			<div class="aie-preview-stats">
				<div class="aie-stat">
					<span class="aie-stat-label"><?php esc_html_e( 'Total Rows:', 'import-export-by-rockstarlab' ); ?></span>
					<span class="aie-stat-value aie-total-rows">-</span>
				</div>
				<div class="aie-stat">
					<span class="aie-stat-label"><?php esc_html_e( 'Columns:', 'import-export-by-rockstarlab' ); ?></span>
					<span class="aie-stat-value aie-total-columns">-</span>
				</div>
			</div>

			<div class="aie-preview-table-container">
				<table class="wp-list-table widefat fixed striped aie-preview-table">
					<thead></thead>
					<tbody></tbody>
				</table>
			</div>

			<!-- JSON Preview -->
			<div class="aie-json-preview-container" style="display:none;">
				<div class="aie-json-preview"></div>
			</div>

			<p class="description aie-preview-note">
				<?php esc_html_e( 'Showing first 5 rows.', 'import-export-by-rockstarlab' ); ?>
			</p>
		</div>

		<div class="aie-step-actions">
			<button type="button" class="button button-secondary aie-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary button-large aie-next-step">
				<?php esc_html_e( 'Next Step', 'import-export-by-rockstarlab' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
