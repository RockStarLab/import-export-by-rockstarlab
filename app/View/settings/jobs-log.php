<?php
/**
 * Jobs Log Page
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="wp-aie-jobs-log" class="wp-advanced-import-export wrap">
	<h1><?php esc_html_e( 'Jobs Log', 'wp-advanced-import-export' ); ?></h1>

	<div class="aie-jobs-log">
		<!-- Filters -->
		<div class="aie-jobs-filters">
			<div class="aie-filter-group">
				<label for="filter-type"><?php esc_html_e( 'Type:', 'wp-advanced-import-export' ); ?></label>
				<select id="filter-type" class="aie-filter-select">
					<option value=""><?php esc_html_e( 'All Types', 'wp-advanced-import-export' ); ?></option>
					<option value="import"><?php esc_html_e( 'Import', 'wp-advanced-import-export' ); ?></option>
					<option value="export"><?php esc_html_e( 'Export', 'wp-advanced-import-export' ); ?></option>
					<option value="media_sync"><?php esc_html_e( 'Media Sync', 'wp-advanced-import-export' ); ?></option>
				</select>
			</div>

			<div class="aie-filter-group">
				<label for="filter-status"><?php esc_html_e( 'Status:', 'wp-advanced-import-export' ); ?></label>
				<select id="filter-status" class="aie-filter-select">
					<option value=""><?php esc_html_e( 'All Statuses', 'wp-advanced-import-export' ); ?></option>
					<option value="pending"><?php esc_html_e( 'Pending', 'wp-advanced-import-export' ); ?></option>
					<option value="processing"><?php esc_html_e( 'Processing', 'wp-advanced-import-export' ); ?></option>
					<option value="completed"><?php esc_html_e( 'Completed', 'wp-advanced-import-export' ); ?></option>
					<option value="failed"><?php esc_html_e( 'Failed', 'wp-advanced-import-export' ); ?></option>
					<option value="paused"><?php esc_html_e( 'Paused', 'wp-advanced-import-export' ); ?></option>
					<option value="cancelled"><?php esc_html_e( 'Cancelled', 'wp-advanced-import-export' ); ?></option>
				</select>
			</div>

			<div class="aie-filter-buttons">
				<button class="button aie-filter-apply"><?php esc_html_e( 'Apply Filters', 'wp-advanced-import-export' ); ?></button>
				<button class="button aie-filter-reset"><?php esc_html_e( 'Reset', 'wp-advanced-import-export' ); ?></button>
			</div>
		</div>

		<!-- Loading state -->
		<div class="aie-jobs-loading" style="display: none;">
			<span class="spinner is-active"></span>
			<p><?php esc_html_e( 'Loading jobs...', 'wp-advanced-import-export' ); ?></p>
		</div>

		<!-- Jobs table -->
		<div class="aie-jobs-table-wrapper">
			<table class="wp-list-table widefat fixed striped aie-jobs-table">
				<thead>
					<tr>
						<th class="column-id"><?php esc_html_e( 'ID', 'wp-advanced-import-export' ); ?></th>
						<th class="column-type"><?php esc_html_e( 'Type', 'wp-advanced-import-export' ); ?></th>
						<th class="column-data-type"><?php esc_html_e( 'Data Type', 'wp-advanced-import-export' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'wp-advanced-import-export' ); ?></th>
						<th class="column-progress"><?php esc_html_e( 'Progress', 'wp-advanced-import-export' ); ?></th>
						<th class="column-items"><?php esc_html_e( 'Items', 'wp-advanced-import-export' ); ?></th>
						<th class="column-created"><?php esc_html_e( 'Created', 'wp-advanced-import-export' ); ?></th>
						<th class="column-elapsed"><?php esc_html_e( 'Elapsed', 'wp-advanced-import-export' ); ?></th>
						<th class="column-actions"><?php esc_html_e( 'Actions', 'wp-advanced-import-export' ); ?></th>
					</tr>
				</thead>
				<tbody id="jobs-table-body">
					<tr class="no-items">
						<td colspan="9"><?php esc_html_e( 'No jobs found.', 'wp-advanced-import-export' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Pagination -->
		<div class="aie-jobs-pagination" style="display: none;">
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
<div id="job-details-modal" class="aie-modal" style="display: none;">
	<div class="aie-modal-overlay"></div>
	<div class="aie-modal-content">
		<div class="aie-modal-header">
			<h2><?php esc_html_e( 'Job Details', 'wp-advanced-import-export' ); ?></h2>
			<button class="aie-modal-close">&times;</button>
		</div>
		<div class="aie-modal-body">
			<div id="job-details-content"></div>
		</div>
		<div class="aie-modal-footer">
			<button class="button aie-modal-close"><?php esc_html_e( 'Close', 'wp-advanced-import-export' ); ?></button>
		</div>
	</div>
</div>

<!-- Confirm Delete Modal -->
<div id="confirm-delete-modal" class="aie-modal" style="display: none;">
	<div class="aie-modal-overlay"></div>
	<div class="aie-modal-content aie-modal-small">
		<div class="aie-modal-header">
			<h2><?php esc_html_e( 'Confirm Delete', 'wp-advanced-import-export' ); ?></h2>
			<button class="aie-modal-close">&times;</button>
		</div>
		<div class="aie-modal-body">
			<p><?php esc_html_e( 'Are you sure you want to delete this job? This action cannot be undone.', 'wp-advanced-import-export' ); ?></p>
			<p class="description"><?php esc_html_e( 'Associated files will also be deleted.', 'wp-advanced-import-export' ); ?></p>
		</div>
		<div class="aie-modal-footer">
			<button class="button aie-modal-close"><?php esc_html_e( 'Cancel', 'wp-advanced-import-export' ); ?></button>
			<button class="button button-primary aie-confirm-delete"><?php esc_html_e( 'Delete', 'wp-advanced-import-export' ); ?></button>
		</div>
	</div>
</div>
