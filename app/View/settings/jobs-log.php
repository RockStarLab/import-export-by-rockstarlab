<?php
/**
 * Jobs Log Page
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="rsl-ie-jobs-log" class="import-export-by-rockstarlab wrap">
	<h1><?php esc_html_e( 'Jobs Log', 'import-export-by-rockstarlab' ); ?></h1>
	<?php
	$rsl_ie_active_tab = 'jobs';
	require RSL_IE_PATH . 'app/View/settings/partials/jobs-tabs.php';
	?>

	<div class="rsl-ie-jobs-log">
		<!-- Filters -->
		<div class="rsl-ie-jobs-filters">
			<div class="rsl-ie-filter-group">
				<label for="filter-type"><?php esc_html_e( 'Type:', 'import-export-by-rockstarlab' ); ?></label>
				<select id="filter-type" class="rsl-ie-filter-select">
					<option value=""><?php esc_html_e( 'All Types', 'import-export-by-rockstarlab' ); ?></option>
					<option value="import"><?php esc_html_e( 'Import', 'import-export-by-rockstarlab' ); ?></option>
					<option value="export"><?php esc_html_e( 'Export', 'import-export-by-rockstarlab' ); ?></option>
					<option value="update"><?php esc_html_e( 'Update', 'import-export-by-rockstarlab' ); ?></option>
					<option value="media_sync"><?php esc_html_e( 'Media Sync', 'import-export-by-rockstarlab' ); ?></option>
				</select>
			</div>

			<div class="rsl-ie-filter-group">
				<label for="filter-status"><?php esc_html_e( 'Status:', 'import-export-by-rockstarlab' ); ?></label>
				<select id="filter-status" class="rsl-ie-filter-select">
					<option value=""><?php esc_html_e( 'All Statuses', 'import-export-by-rockstarlab' ); ?></option>
					<option value="pending"><?php esc_html_e( 'Pending', 'import-export-by-rockstarlab' ); ?></option>
					<option value="processing"><?php esc_html_e( 'Processing', 'import-export-by-rockstarlab' ); ?></option>
					<option value="completed"><?php esc_html_e( 'Completed', 'import-export-by-rockstarlab' ); ?></option>
					<option value="failed"><?php esc_html_e( 'Failed', 'import-export-by-rockstarlab' ); ?></option>
					<option value="paused"><?php esc_html_e( 'Paused', 'import-export-by-rockstarlab' ); ?></option>
					<option value="cancelled"><?php esc_html_e( 'Cancelled', 'import-export-by-rockstarlab' ); ?></option>
				</select>
			</div>

			<div class="rsl-ie-filter-buttons">
				<button class="button rsl-ie-filter-apply"><?php esc_html_e( 'Apply Filters', 'import-export-by-rockstarlab' ); ?></button>
				<button class="button rsl-ie-filter-reset"><?php esc_html_e( 'Reset', 'import-export-by-rockstarlab' ); ?></button>
			</div>
		</div>

		<!-- Loading state -->
		<div class="rsl-ie-jobs-loading" style="display: none;">
			<span class="spinner is-active"></span>
			<p><?php esc_html_e( 'Loading jobs...', 'import-export-by-rockstarlab' ); ?></p>
		</div>

		<!-- Jobs table -->
		<div class="rsl-ie-jobs-table-wrapper">
			<table class="wp-list-table widefat fixed striped rsl-ie-jobs-table">
				<thead>
					<tr>
						<th class="column-id"><?php esc_html_e( 'ID', 'import-export-by-rockstarlab' ); ?></th>
						<th class="column-type"><?php esc_html_e( 'Type', 'import-export-by-rockstarlab' ); ?></th>
						<th class="column-data-type"><?php esc_html_e( 'Data Type', 'import-export-by-rockstarlab' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'import-export-by-rockstarlab' ); ?></th>
						<th class="column-progress"><?php esc_html_e( 'Progress', 'import-export-by-rockstarlab' ); ?></th>
						<th class="column-items"><?php esc_html_e( 'Items', 'import-export-by-rockstarlab' ); ?></th>
						<th class="column-created"><?php esc_html_e( 'Created', 'import-export-by-rockstarlab' ); ?></th>
						<th class="column-elapsed"><?php esc_html_e( 'Elapsed', 'import-export-by-rockstarlab' ); ?></th>
						<th class="column-actions"><?php esc_html_e( 'Actions', 'import-export-by-rockstarlab' ); ?></th>
					</tr>
				</thead>
				<tbody id="jobs-table-body">
					<tr class="no-items">
						<td colspan="9"><?php esc_html_e( 'No jobs found.', 'import-export-by-rockstarlab' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Pagination -->
		<div class="rsl-ie-jobs-pagination" style="display: none;">
			<div class="pagination-info">
				<span class="displaying-num"></span>
			</div>
			<div class="pagination-links">
				<button class="button first-page" disabled>&laquo;</button>
				<button class="button prev-page" disabled>&lsaquo;</button>
				<span class="current-page">1</span> / <span class="total-pages">1</span>
				<button class="button next-page" disabled>&rsaquo;</button>
				<button class="button last-page" disabled>&raquo;</button>
			</div>
		</div>
	</div>
</div>

<!-- Job Details Modal -->
<div id="job-details-modal" class="rsl-ie-modal" style="display: none;">
	<div class="rsl-ie-modal-overlay"></div>
	<div class="rsl-ie-modal-content">
		<div class="rsl-ie-modal-header">
			<h2><?php esc_html_e( 'Job Details', 'import-export-by-rockstarlab' ); ?></h2>
			<button class="rsl-ie-modal-close">&times;</button>
		</div>
		<div class="rsl-ie-modal-body">
			<div id="job-details-content"></div>
		</div>
		<div class="rsl-ie-modal-footer">
			<button class="button rsl-ie-modal-close"><?php esc_html_e( 'Close', 'import-export-by-rockstarlab' ); ?></button>
		</div>
	</div>
</div>

<!-- Confirm Delete Modal -->
<div id="confirm-delete-modal" class="rsl-ie-modal" style="display: none;">
	<div class="rsl-ie-modal-overlay"></div>
	<div class="rsl-ie-modal-content rsl-ie-modal-small">
		<div class="rsl-ie-modal-header">
			<h2><?php esc_html_e( 'Confirm Delete', 'import-export-by-rockstarlab' ); ?></h2>
			<button class="rsl-ie-modal-close">&times;</button>
		</div>
		<div class="rsl-ie-modal-body">
			<p><?php esc_html_e( 'Are you sure you want to delete this job? This action cannot be undone.', 'import-export-by-rockstarlab' ); ?></p>
			<p class="description"><?php esc_html_e( 'Associated files will also be deleted.', 'import-export-by-rockstarlab' ); ?></p>
		</div>
		<div class="rsl-ie-modal-footer">
			<button class="button rsl-ie-modal-close"><?php esc_html_e( 'Cancel', 'import-export-by-rockstarlab' ); ?></button>
			<button class="button button-primary rsl-ie-confirm-delete"><?php esc_html_e( 'Delete', 'import-export-by-rockstarlab' ); ?></button>
		</div>
	</div>
</div>
