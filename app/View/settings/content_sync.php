<?php
/**
 * Content Sync Settings Page
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="rsl-ie-content-sync" class="import-export-by-rockstarlab wrap">
	<h1><?php esc_html_e( 'Content Sync', 'import-export-by-rockstarlab' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Manage connections between WordPress sites for content synchronization. Connect multiple sites and sync posts, pages, and other content types.', 'import-export-by-rockstarlab' ); ?>
	</p>

	<!-- Stats Cards -->
	<div class="rsl-ie-content-sync-stats">
		<div class="rsl-ie-stat-card">
			<div class="rsl-ie-stat-icon">
				<span class="dashicons dashicons-admin-site"></span>
			</div>
			<div class="rsl-ie-stat-info">
				<div class="rsl-ie-stat-value" id="rsl-ie-stat-total">0</div>
				<div class="rsl-ie-stat-label"><?php esc_html_e( 'Total Sites', 'import-export-by-rockstarlab' ); ?></div>
			</div>
		</div>

		<div class="rsl-ie-stat-card">
			<div class="rsl-ie-stat-icon rsl-ie-stat-active">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>
			<div class="rsl-ie-stat-info">
				<div class="rsl-ie-stat-value" id="rsl-ie-stat-active">0</div>
				<div class="rsl-ie-stat-label"><?php esc_html_e( 'Active', 'import-export-by-rockstarlab' ); ?></div>
			</div>
		</div>

		<div class="rsl-ie-stat-card">
			<div class="rsl-ie-stat-icon rsl-ie-stat-error">
				<span class="dashicons dashicons-warning"></span>
			</div>
			<div class="rsl-ie-stat-info">
				<div class="rsl-ie-stat-value" id="rsl-ie-stat-error">0</div>
				<div class="rsl-ie-stat-label"><?php esc_html_e( 'Error', 'import-export-by-rockstarlab' ); ?></div>
			</div>
		</div>
	</div>

	<!-- This Site Info Section -->
	<div class="rsl-ie-content-sync-section rsl-ie-my-site-section">
		<div class="rsl-ie-section-header">
			<h2><?php esc_html_e( 'This Site Configuration', 'import-export-by-rockstarlab' ); ?></h2>
			<button type="button" class="button button-secondary" id="rsl-ie-toggle-my-site">
				<span class="dashicons dashicons-visibility"></span>
				<?php esc_html_e( 'Show Details', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
		<div class="rsl-ie-my-site-info" style="display: none;">
			<p class="description">
				<?php esc_html_e( 'Use these details to connect this site from another WordPress installation:', 'import-export-by-rockstarlab' ); ?>
			</p>
			<div class="rsl-ie-info-grid">
				<div class="rsl-ie-info-item">
					<label><?php esc_html_e( 'Site Name:', 'import-export-by-rockstarlab' ); ?></label>
					<input type="text" class="regular-text" id="rsl-ie-my-site-name" readonly />
				</div>
				<div class="rsl-ie-info-item">
					<label><?php esc_html_e( 'Site URL:', 'import-export-by-rockstarlab' ); ?></label>
					<input type="text" class="regular-text" id="rsl-ie-my-site-url" readonly />
				</div>
				<div class="rsl-ie-info-item rsl-ie-info-item-full">
					<label><?php esc_html_e( 'API Key:', 'import-export-by-rockstarlab' ); ?></label>
					<div class="rsl-ie-api-key-field">
						<input type="text" class="regular-text" id="rsl-ie-my-site-key" readonly />
						<button type="button" class="button button-secondary" id="rsl-ie-copy-my-key">
							<span class="dashicons dashicons-clipboard"></span>
							<?php esc_html_e( 'Copy', 'import-export-by-rockstarlab' ); ?>
						</button>
						<button type="button" class="button button-secondary" id="rsl-ie-regenerate-my-key">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Regenerate', 'import-export-by-rockstarlab' ); ?>
						</button>
					</div>
					<p class="description">
						<?php esc_html_e( 'Keep this API key secure. It allows other sites to connect to this site.', 'import-export-by-rockstarlab' ); ?>
					</p>
				</div>
			</div>
		</div>
	</div>

	<!-- Connected Sites Section -->
	<div class="rsl-ie-content-sync-section">
		<div class="rsl-ie-section-header">
			<h2><?php esc_html_e( 'Connected Sites', 'import-export-by-rockstarlab' ); ?></h2>
			<button type="button" class="button button-primary" id="rsl-ie-add-site-btn">
				<span class="dashicons dashicons-plus-alt"></span>
				<?php esc_html_e( 'Add New Site', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>

		<!-- Sites Table -->
		<div class="rsl-ie-sites-table-wrapper">
			<table class="wp-list-table widefat fixed striped rsl-ie-sites-table">
				<thead>
					<tr>
						<th class="column-name"><?php esc_html_e( 'Site Name', 'import-export-by-rockstarlab' ); ?></th>
						<th class="column-url"><?php esc_html_e( 'URL', 'import-export-by-rockstarlab' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'import-export-by-rockstarlab' ); ?></th>
						<th class="column-last-sync"><?php esc_html_e( 'Last Sync', 'import-export-by-rockstarlab' ); ?></th>
						<th class="column-actions"><?php esc_html_e( 'Actions', 'import-export-by-rockstarlab' ); ?></th>
					</tr>
				</thead>
				<tbody id="rsl-ie-sites-list">
					<tr class="rsl-ie-no-sites">
						<td colspan="5" style="text-align: center; padding: 40px;">
							<span class="dashicons dashicons-admin-site" style="font-size: 48px; opacity: 0.3;"></span>
							<p style="margin-top: 40px"><?php esc_html_e( 'No connected sites yet. Add your first connection!', 'import-export-by-rockstarlab' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- Add/Edit Site Modal -->
<div id="rsl-ie-site-modal" class="rsl-ie-modal" style="display: none;">
	<div class="rsl-ie-modal-content">
		<div class="rsl-ie-modal-header">
			<h2 id="rsl-ie-modal-title"><?php esc_html_e( 'Add New Site', 'import-export-by-rockstarlab' ); ?></h2>
			<button type="button" class="rsl-ie-modal-close">
				<span class="dashicons dashicons-no"></span>
			</button>
		</div>
		<div class="rsl-ie-modal-body">
			<!-- Modal Notification Area -->
			<div id="rsl-ie-modal-notification" class="rsl-ie-modal-notification" style="display: none;"></div>
			
			<form id="rsl-ie-site-form">
				<input type="hidden" id="rsl-ie-site-id" name="site_id" value="" />
				
				<div class="rsl-ie-form-row">
					<label for="rsl-ie-site-name">
						<?php esc_html_e( 'Site Name', 'import-export-by-rockstarlab' ); ?>
						<span class="required">*</span>
					</label>
					<input type="text" id="rsl-ie-site-name" name="name" class="regular-text" required />
					<p class="description"><?php esc_html_e( 'A friendly name for this connection', 'import-export-by-rockstarlab' ); ?></p>
				</div>

				<div class="rsl-ie-form-row">
					<label for="rsl-ie-site-url">
						<?php esc_html_e( 'Remote Site URL', 'import-export-by-rockstarlab' ); ?>
						<span class="required">*</span>
					</label>
					<input type="url" id="rsl-ie-site-url" name="remote_url" class="regular-text" placeholder="https://example.com" required />
					<p class="description"><?php esc_html_e( 'The full URL of the WordPress site to connect', 'import-export-by-rockstarlab' ); ?></p>
				</div>

				<div class="rsl-ie-form-row">
					<label for="rsl-ie-site-api-key">
						<?php esc_html_e( 'Remote API Key', 'import-export-by-rockstarlab' ); ?>
						<span class="required">*</span>
					</label>
					<input type="text" id="rsl-ie-site-api-key" name="api_key" class="regular-text" />
					<p class="description"><?php esc_html_e( 'API key from the remote site', 'import-export-by-rockstarlab' ); ?></p>
				</div>
			</form>
		</div>
		<div class="rsl-ie-modal-footer">
			<button type="button" class="button button-secondary rsl-ie-modal-close">
				<?php esc_html_e( 'Cancel', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary" id="rsl-ie-save-site-btn">
				<?php esc_html_e( 'Save Connection', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
	</div>
</div>
