<?php
/**
 * Content Sync Settings Page
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

// Check if premium is active
$is_premium = function_exists( 'rsl_ie_fs' ) && rsl_ie_fs()->can_use_premium_code();

$activate_license_url = add_query_arg( 'rsl-ie-activate-license', '1', admin_url( 'plugins.php' ) );
$activate_license_url = wp_nonce_url( $activate_license_url, 'rsl_ie_activate_license', '_wpnonce' );
?>

<div id="rsl-ie-content-sync" class="import-export-by-rockstarlab wrap">
	<h1><?php esc_html_e( 'Content Sync', 'import-export-by-rockstarlab' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Manage connections between WordPress sites for content synchronization. Connect multiple sites and sync posts, pages, and other content types.', 'import-export-by-rockstarlab' ); ?>
	</p>

	<?php if ( ! $is_premium ) : ?>
	<!-- Premium Notice -->
	<div class="aie-premium-notice">
		<div class="aie-premium-notice-icon">
			<span class="dashicons dashicons-lock"></span>
		</div>
		<div class="aie-premium-notice-content">
			<h3><?php esc_html_e( 'Premium Feature', 'import-export-by-rockstarlab' ); ?></h3>			<p><?php esc_html_e( 'In the Premium version, you can synchronize ALL Pages and Custom Post Types between WordPress sites. Free version supports Posts synchronization only.', 'import-export-by-rockstarlab' ); ?></p>
			<?php if ( function_exists( 'rsl_ie_fs' ) ) : ?>
				<a href="<?php echo esc_url( rsl_ie_fs()->get_upgrade_url() ); ?>" class="button button-primary button-large">
					<span class="dashicons dashicons-star-filled"></span>
					<?php esc_html_e( 'Upgrade to Premium', 'import-export-by-rockstarlab' ); ?>
				</a>
				<a href="<?php echo esc_url( $activate_license_url ); ?>" class="button button-secondary button-large">
					<span class="dashicons dashicons-admin-network"></span>
					<?php esc_html_e( 'Activate License', 'import-export-by-rockstarlab' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<!-- Stats Cards -->
	<div class="aie-content-sync-stats">
		<div class="aie-stat-card">
			<div class="aie-stat-icon">
				<span class="dashicons dashicons-admin-site"></span>
			</div>
			<div class="aie-stat-info">
				<div class="aie-stat-value" id="aie-stat-total">0</div>
				<div class="aie-stat-label"><?php esc_html_e( 'Total Sites', 'import-export-by-rockstarlab' ); ?></div>
			</div>
		</div>

		<div class="aie-stat-card">
			<div class="aie-stat-icon aie-stat-active">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>
			<div class="aie-stat-info">
				<div class="aie-stat-value" id="aie-stat-active">0</div>
				<div class="aie-stat-label"><?php esc_html_e( 'Active', 'import-export-by-rockstarlab' ); ?></div>
			</div>
		</div>

		<div class="aie-stat-card">
			<div class="aie-stat-icon aie-stat-error">
				<span class="dashicons dashicons-warning"></span>
			</div>
			<div class="aie-stat-info">
				<div class="aie-stat-value" id="aie-stat-error">0</div>
				<div class="aie-stat-label"><?php esc_html_e( 'Error', 'import-export-by-rockstarlab' ); ?></div>
			</div>
		</div>
	</div>

	<!-- This Site Info Section -->
	<div class="aie-content-sync-section aie-my-site-section">
		<div class="aie-section-header">
			<h2><?php esc_html_e( 'This Site Configuration', 'import-export-by-rockstarlab' ); ?></h2>
			<button type="button" class="button button-secondary" id="aie-toggle-my-site">
				<span class="dashicons dashicons-visibility"></span>
				<?php esc_html_e( 'Show Details', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
		<div class="aie-my-site-info" style="display: none;">
			<p class="description">
				<?php esc_html_e( 'Use these details to connect this site from another WordPress installation:', 'import-export-by-rockstarlab' ); ?>
			</p>
			<div class="aie-info-grid">
				<div class="aie-info-item">
					<label><?php esc_html_e( 'Site Name:', 'import-export-by-rockstarlab' ); ?></label>
					<input type="text" class="regular-text" id="aie-my-site-name" readonly />
				</div>
				<div class="aie-info-item">
					<label><?php esc_html_e( 'Site URL:', 'import-export-by-rockstarlab' ); ?></label>
					<input type="text" class="regular-text" id="aie-my-site-url" readonly />
				</div>
				<div class="aie-info-item aie-info-item-full">
					<label><?php esc_html_e( 'API Key:', 'import-export-by-rockstarlab' ); ?></label>
					<div class="aie-api-key-field">
						<input type="text" class="regular-text" id="aie-my-site-key" readonly />
						<button type="button" class="button button-secondary" id="aie-copy-my-key">
							<span class="dashicons dashicons-clipboard"></span>
							<?php esc_html_e( 'Copy', 'import-export-by-rockstarlab' ); ?>
						</button>
						<button type="button" class="button button-secondary" id="aie-regenerate-my-key">
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
	<div class="aie-content-sync-section">
		<div class="aie-section-header">
			<h2><?php esc_html_e( 'Connected Sites', 'import-export-by-rockstarlab' ); ?></h2>
			<button type="button" class="button button-primary" id="aie-add-site-btn">
				<span class="dashicons dashicons-plus-alt"></span>
				<?php esc_html_e( 'Add New Site', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>

		<!-- Sites Table -->
		<div class="aie-sites-table-wrapper">
			<table class="wp-list-table widefat fixed striped aie-sites-table">
				<thead>
					<tr>
						<th class="column-name"><?php esc_html_e( 'Site Name', 'import-export-by-rockstarlab' ); ?></th>
						<th class="column-url"><?php esc_html_e( 'URL', 'import-export-by-rockstarlab' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'import-export-by-rockstarlab' ); ?></th>
						<th class="column-last-sync"><?php esc_html_e( 'Last Sync', 'import-export-by-rockstarlab' ); ?></th>
						<th class="column-actions"><?php esc_html_e( 'Actions', 'import-export-by-rockstarlab' ); ?></th>
					</tr>
				</thead>
				<tbody id="aie-sites-list">
					<tr class="aie-no-sites">
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
<div id="aie-site-modal" class="aie-modal" style="display: none;">
	<div class="aie-modal-content">
		<div class="aie-modal-header">
			<h2 id="aie-modal-title"><?php esc_html_e( 'Add New Site', 'import-export-by-rockstarlab' ); ?></h2>
			<button type="button" class="aie-modal-close">
				<span class="dashicons dashicons-no"></span>
			</button>
		</div>
		<div class="aie-modal-body">
			<!-- Modal Notification Area -->
			<div id="aie-modal-notification" class="aie-modal-notification" style="display: none;"></div>
			
			<form id="aie-site-form">
				<input type="hidden" id="aie-site-id" name="site_id" value="" />
				
				<div class="aie-form-row">
					<label for="aie-site-name">
						<?php esc_html_e( 'Site Name', 'import-export-by-rockstarlab' ); ?>
						<span class="required">*</span>
					</label>
					<input type="text" id="aie-site-name" name="name" class="regular-text" required />
					<p class="description"><?php esc_html_e( 'A friendly name for this connection', 'import-export-by-rockstarlab' ); ?></p>
				</div>

				<div class="aie-form-row">
					<label for="aie-site-url">
						<?php esc_html_e( 'Remote Site URL', 'import-export-by-rockstarlab' ); ?>
						<span class="required">*</span>
					</label>
					<input type="url" id="aie-site-url" name="remote_url" class="regular-text" placeholder="https://example.com" required />
					<p class="description"><?php esc_html_e( 'The full URL of the WordPress site to connect', 'import-export-by-rockstarlab' ); ?></p>
				</div>

				<div class="aie-form-row">
					<label for="aie-site-api-key">
						<?php esc_html_e( 'Remote API Key', 'import-export-by-rockstarlab' ); ?>
						<span class="required">*</span>
					</label>
					<input type="text" id="aie-site-api-key" name="api_key" class="regular-text" />
					<p class="description"><?php esc_html_e( 'API key from the remote site', 'import-export-by-rockstarlab' ); ?></p>
				</div>
			</form>
		</div>
		<div class="aie-modal-footer">
			<button type="button" class="button button-secondary aie-modal-close">
				<?php esc_html_e( 'Cancel', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary" id="aie-save-site-btn">
				<?php esc_html_e( 'Save Connection', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
	</div>
</div>
