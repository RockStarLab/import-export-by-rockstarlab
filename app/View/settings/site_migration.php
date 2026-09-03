<?php
/**
 * Content Migration page.
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

$rsl_ie_migration_pro_active       = \RockStarLab\ImportExport\Helper\Pro_Addon::is_pro_active();
$rsl_ie_migration_promo_cta        = \RockStarLab\ImportExport\Helper\Pro_Addon::get_promo_cta();
$rsl_ie_migration_promo_dismissed  = (bool) get_user_meta( get_current_user_id(), 'rsl_ie_dismiss_pro_promo_migration', true );
$rsl_ie_migration_promo_features   = \RockStarLab\ImportExport\Helper\Pro_Addon::get_promo_features( 'export' );
$rsl_ie_migration_intro_dismissed  = (bool) get_user_meta( get_current_user_id(), 'rsl_ie_dismiss_content_migration_intro', true );
$rsl_ie_migration_current_site_url = \RockStarLab\ImportExport\Helper\Site_URL::current_request_site_url();
?>

<div id="rsl-ie-site-migration" class="import-export-by-rockstarlab wrap">
	<h1><?php esc_html_e( 'Content Migration', 'import-export-by-rockstarlab' ); ?></h1>

	<div class="rsl-ie-migration-wizard">
		<section class="rsl-ie-migration-step active" data-step="1">
			<div class="rsl-ie-step-header">
				<h2><?php esc_html_e( 'Choose migration method', 'import-export-by-rockstarlab' ); ?></h2>
			</div>

			<?php if ( ! $rsl_ie_migration_intro_dismissed ) : ?>
				<div class="rsl-ie-migration-intro-notice">
					<div class="rsl-ie-migration-intro-icon">
						<span class="dashicons dashicons-info"></span>
					</div>
					<div class="rsl-ie-migration-intro-copy">
						<?php if ( $rsl_ie_migration_pro_active ) : ?>
							<p><?php esc_html_e( 'Move site content through one export package or sync the data through a connected site. This migrates content data such as users, posts, pages, custom post types, terms, comments, WooCommerce records, and media references. Site options, theme settings, Customizer values, widgets, templates, and other theme territory are not migrated.', 'import-export-by-rockstarlab' ); ?></p>
						<?php else : ?>
							<p><?php esc_html_e( 'Move site content through one export package or sync the data through a connected site. Without PRO, Content Migration migrates only posts, pages, and media. Site options, theme settings, Customizer values, widgets, templates, and other theme territory are not migrated.', 'import-export-by-rockstarlab' ); ?></p>
						<?php endif; ?>
						<div class="rsl-ie-migration-intro-actions">
							<button type="button" class="button-link rsl-ie-migration-intro-hide">
								<?php esc_html_e( 'Hide', 'import-export-by-rockstarlab' ); ?>
							</button>
							<span class="rsl-ie-migration-intro-dismiss-sep">·</span>
							<button type="button" class="button-link rsl-ie-migration-intro-dismiss-forever">
								<?php esc_html_e( "Don't show again", 'import-export-by-rockstarlab' ); ?>
							</button>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<div class="rsl-ie-migration-choice-grid rsl-ie-migration-method-grid">
				<label class="rsl-ie-migration-choice rsl-ie-migration-method-card">
					<input type="radio" name="rsl_ie_migration_method" value="file" checked>
					<span class="rsl-ie-migration-card-icon"><span class="dashicons dashicons-media-archive"></span></span>
					<span class="rsl-ie-migration-card-copy">
						<strong><?php esc_html_e( 'Migrate through export file', 'import-export-by-rockstarlab' ); ?></strong>
						<small><?php esc_html_e( 'Create or upload one ZIP with ordered export files.', 'import-export-by-rockstarlab' ); ?></small>
					</span>
					<span class="rsl-ie-migration-card-check"><span class="dashicons dashicons-yes"></span></span>
				</label>
				<label class="rsl-ie-migration-choice rsl-ie-migration-method-card rsl-ie-sync-choice">
					<input type="radio" name="rsl_ie_migration_method" value="sync">
					<span class="rsl-ie-migration-card-icon"><span class="dashicons dashicons-update"></span></span>
					<span class="rsl-ie-migration-card-copy">
						<strong><?php esc_html_e( 'Migrate through content sync', 'import-export-by-rockstarlab' ); ?></strong>
						<small><?php esc_html_e( 'Push or pull content through the Content Sync API.', 'import-export-by-rockstarlab' ); ?></small>
					</span>
					<span class="rsl-ie-migration-card-check"><span class="dashicons dashicons-yes"></span></span>
				</label>
			</div>

			<?php if ( ! $rsl_ie_migration_pro_active && ! $rsl_ie_migration_promo_dismissed ) : ?>
				<div class="rsl-ie-pro-migration-note">
					<div class="rsl-ie-pro-addon-header">
						<div class="rsl-ie-pro-addon-icon">
							<span class="dashicons dashicons-star-filled"></span>
						</div>
						<div class="rsl-ie-pro-addon-copy">
							<h3><?php esc_html_e( 'Need more migration types? Buy PRO addon', 'import-export-by-rockstarlab' ); ?></h3>
							<p><?php esc_html_e( 'Without PRO, Content Migration includes only posts, pages, and media. PRO adds more content types, including custom post types, users, taxonomy terms, and WooCommerce data when available.', 'import-export-by-rockstarlab' ); ?></p>
						</div>
					</div>

					<?php if ( ! empty( $rsl_ie_migration_promo_features ) ) : ?>
						<ul class="rsl-ie-pro-addon-features">
							<?php foreach ( $rsl_ie_migration_promo_features as $rsl_ie_feature ) : ?>
								<li>
									<span class="dashicons dashicons-yes-alt"></span>
									<div class="rsl-ie-pro-addon-feature-text">
										<strong><?php echo esc_html( $rsl_ie_feature['title'] ?? '' ); ?></strong>
										<span><?php echo esc_html( $rsl_ie_feature['description'] ?? '' ); ?></span>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<div class="rsl-ie-pro-addon-actions">
						<a href="<?php echo esc_url( $rsl_ie_migration_promo_cta['url'] ); ?>" class="button button-primary rsl-ie-pro-addon-cta">
							<?php echo esc_html( $rsl_ie_migration_promo_cta['label'] ); ?>
						</a>
						<div class="rsl-ie-pro-addon-dismiss">
							<button type="button" class="button-link rsl-ie-pro-addon-hide" data-context="migration">
								<?php esc_html_e( 'Hide', 'import-export-by-rockstarlab' ); ?>
							</button>
							<span class="rsl-ie-pro-addon-dismiss-sep">·</span>
							<button type="button" class="button-link rsl-ie-pro-addon-dismiss-forever" data-context="migration">
								<?php esc_html_e( "Don't show again", 'import-export-by-rockstarlab' ); ?>
							</button>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<div class="rsl-ie-sync-unavailable notice notice-warning" style="display:none;">
				<p><?php esc_html_e( 'Content Sync is not configured yet. Add and test a remote site before using sync migration.', 'import-export-by-rockstarlab' ); ?></p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-content-sync' ) ); ?>"><?php esc_html_e( 'Setup connection', 'import-export-by-rockstarlab' ); ?></a>
			</div>

			<div class="rsl-ie-step-actions">
				<button type="button" class="button button-primary button-large rsl-ie-migration-next"><?php esc_html_e( 'Continue', 'import-export-by-rockstarlab' ); ?></button>
			</div>
		</section>

		<section class="rsl-ie-migration-step" data-step="2">
			<div class="rsl-ie-file-mode-panel">
				<div class="rsl-ie-step-header">
					<h2><?php esc_html_e( 'Export package', 'import-export-by-rockstarlab' ); ?></h2>
				</div>
				<div class="rsl-ie-migration-choice-grid rsl-ie-migration-operation-grid">
					<label class="rsl-ie-migration-choice rsl-ie-migration-operation-card">
						<input type="radio" name="rsl_ie_file_operation" value="create" checked>
						<span class="rsl-ie-migration-operation-main">
							<span class="dashicons dashicons-download"></span>
							<span>
								<strong><?php esc_html_e( 'Create export file', 'import-export-by-rockstarlab' ); ?></strong>
								<small><?php esc_html_e( 'Run ordered export jobs and pack results into one ZIP.', 'import-export-by-rockstarlab' ); ?></small>
							</span>
						</span>
						<span class="rsl-ie-migration-card-check"><span class="dashicons dashicons-yes"></span></span>
					</label>
					<label class="rsl-ie-migration-choice rsl-ie-migration-operation-card">
						<input type="radio" name="rsl_ie_file_operation" value="upload">
						<span class="rsl-ie-migration-operation-main">
							<span class="dashicons dashicons-upload"></span>
							<span>
								<strong><?php esc_html_e( 'Upload export file', 'import-export-by-rockstarlab' ); ?></strong>
								<small><?php esc_html_e( 'Import an existing content migration ZIP package.', 'import-export-by-rockstarlab' ); ?></small>
							</span>
						</span>
						<span class="rsl-ie-migration-card-check"><span class="dashicons dashicons-yes"></span></span>
					</label>
				</div>
				<div class="rsl-ie-migration-upload rsl-ie-upload-area" id="rsl-ie-migration-upload-area" style="display:none;">
					<div class="rsl-ie-upload-placeholder">
						<span class="dashicons dashicons-upload"></span>
						<h3><?php esc_html_e( 'Drag & Drop your migration ZIP here', 'import-export-by-rockstarlab' ); ?></h3>
						<p><?php esc_html_e( 'or', 'import-export-by-rockstarlab' ); ?></p>
						<button type="button" class="button button-secondary" id="rsl-ie-select-migration-file">
							<?php esc_html_e( 'Select ZIP File', 'import-export-by-rockstarlab' ); ?>
						</button>
						<input type="file" id="rsl-ie-migration-file" accept=".zip" style="display:none;">
						<p class="description"><?php esc_html_e( 'Upload a ZIP created by Content Migration export.', 'import-export-by-rockstarlab' ); ?></p>
					</div>
				</div>
				<div class="rsl-ie-migration-file-info rsl-ie-file-info" style="display:none;">
					<div class="rsl-ie-file-details">
						<span class="dashicons dashicons-media-archive"></span>
						<div class="rsl-ie-file-meta">
							<strong class="rsl-ie-migration-file-name"></strong>
							<span class="rsl-ie-migration-file-size"></span>
						</div>
						<button type="button" class="button button-link-delete rsl-ie-remove-migration-file">
							<span class="dashicons dashicons-no"></span>
						</button>
					</div>
				</div>
				<div class="rsl-ie-migration-replace-links" style="display:none;">
					<label class="rsl-ie-migration-replace-toggle">
						<input type="checkbox" id="rsl-ie-migration-enable-replace-links" name="migration_replace_links" value="1">
						<span><?php esc_html_e( 'Replace URLs', 'import-export-by-rockstarlab' ); ?></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'Find and replace links or other text values across all imported migration files before data is saved.', 'import-export-by-rockstarlab' ); ?>
					</p>

					<div id="rsl-ie-migration-replace-links-repeater" class="rsl-ie-replace-links-repeater" style="display: none;" data-current-site-url="<?php echo esc_attr( $rsl_ie_migration_current_site_url ); ?>">
						<div class="rsl-ie-replace-links-header">
							<span><?php esc_html_e( 'Replace what', 'import-export-by-rockstarlab' ); ?></span>
							<span><?php esc_html_e( 'Replace to', 'import-export-by-rockstarlab' ); ?></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Actions', 'import-export-by-rockstarlab' ); ?></span>
						</div>
						<div class="rsl-ie-replace-links-rows">
							<div class="rsl-ie-replace-links-row">
								<input type="text" class="regular-text rsl-ie-migration-replace-what" name="migration_replace_links_from[]" placeholder="<?php esc_attr_e( 'https://old-site.com', 'import-export-by-rockstarlab' ); ?>">
								<input type="text" class="regular-text rsl-ie-migration-replace-to" name="migration_replace_links_to[]" value="<?php echo esc_attr( $rsl_ie_migration_current_site_url ); ?>">
								<button type="button" class="button button-secondary rsl-ie-migration-remove-replace-link" aria-label="<?php esc_attr_e( 'Remove replacement row', 'import-export-by-rockstarlab' ); ?>">
									<span class="dashicons dashicons-no-alt"></span>
								</button>
							</div>
						</div>
						<button type="button" class="button button-secondary rsl-ie-migration-add-replace-link">
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php esc_html_e( 'Add More', 'import-export-by-rockstarlab' ); ?>
						</button>
					</div>
				</div>
			</div>

			<div class="rsl-ie-sync-mode-panel" style="display:none;">
				<div class="rsl-ie-step-header">
					<h2><?php esc_html_e( 'Sync direction', 'import-export-by-rockstarlab' ); ?></h2>
				</div>
				<div class="rsl-ie-migration-choice-grid rsl-ie-migration-operation-grid rsl-ie-migration-direction-grid">
					<label class="rsl-ie-migration-choice rsl-ie-migration-operation-card">
						<input type="radio" name="rsl_ie_migration_direction" value="pull" checked>
						<span class="rsl-ie-migration-operation-main">
							<span class="dashicons dashicons-download"></span>
							<span>
								<strong><?php esc_html_e( 'Pull from remote site', 'import-export-by-rockstarlab' ); ?></strong>
								<small><?php esc_html_e( 'Bring content migration data into this site.', 'import-export-by-rockstarlab' ); ?></small>
							</span>
						</span>
						<span class="rsl-ie-migration-card-check"><span class="dashicons dashicons-yes"></span></span>
					</label>
					<label class="rsl-ie-migration-choice rsl-ie-migration-operation-card">
						<input type="radio" name="rsl_ie_migration_direction" value="push">
						<span class="rsl-ie-migration-operation-main">
							<span class="dashicons dashicons-upload"></span>
							<span>
								<strong><?php esc_html_e( 'Push to remote site', 'import-export-by-rockstarlab' ); ?></strong>
								<small><?php esc_html_e( 'Send content migration data to the connected site.', 'import-export-by-rockstarlab' ); ?></small>
							</span>
						</span>
						<span class="rsl-ie-migration-card-check"><span class="dashicons dashicons-yes"></span></span>
					</label>
				</div>
				<div class="rsl-ie-migration-site-panel">
					<div class="rsl-ie-migration-site-panel-icon">
						<span class="dashicons dashicons-admin-site-alt3"></span>
					</div>
					<div class="rsl-ie-migration-site-panel-copy">
						<strong><?php esc_html_e( 'Remote site', 'import-export-by-rockstarlab' ); ?></strong>
						<span><?php esc_html_e( 'Select the connected site used for this sync migration.', 'import-export-by-rockstarlab' ); ?></span>
					</div>
					<select id="rsl-ie-migration-site"></select>
				</div>
			</div>

			<div class="rsl-ie-step-actions">
				<button type="button" class="button button-secondary rsl-ie-migration-prev"><?php esc_html_e( 'Back', 'import-export-by-rockstarlab' ); ?></button>
				<button type="button" class="button button-primary button-large rsl-ie-migration-start"><?php esc_html_e( 'Start migration', 'import-export-by-rockstarlab' ); ?></button>
			</div>
		</section>

		<section class="rsl-ie-migration-step" data-step="3">
			<div class="rsl-ie-migration-progress-panel">
				<div class="rsl-ie-step-header">
					<h2><?php esc_html_e( 'Migration progress', 'import-export-by-rockstarlab' ); ?></h2>
				</div>
				<div class="rsl-ie-migration-progress">
					<div class="rsl-ie-progress-bar"><div class="rsl-ie-progress-bar-fill" style="width:0%;"></div></div>
					<div class="rsl-ie-migration-progress-text">0%</div>
				</div>
				<div class="rsl-ie-migration-log"><?php esc_html_e( 'Waiting to start...', 'import-export-by-rockstarlab' ); ?></div>
			</div>

			<div class="rsl-ie-migration-result rsl-ie-export-results" style="display:none;">
				<div class="rsl-ie-export-complete-card">
					<div class="rsl-ie-complete-icon">
						<span class="dashicons dashicons-yes-alt"></span>
					</div>
					<h3 class="rsl-ie-complete-title rsl-ie-migration-complete-title"><?php esc_html_e( 'Migration Package Ready!', 'import-export-by-rockstarlab' ); ?></h3>
					<p class="rsl-ie-complete-subtitle rsl-ie-migration-complete-subtitle"><?php esc_html_e( 'Your content migration export has been packaged and is ready to download.', 'import-export-by-rockstarlab' ); ?></p>

					<div class="rsl-ie-results-grid">
						<div class="rsl-ie-result-item">
							<div class="rsl-ie-result-icon">
								<span class="dashicons dashicons-list-view"></span>
							</div>
							<div class="rsl-ie-result-details">
								<span class="rsl-ie-result-label rsl-ie-migration-result-files-label"><?php esc_html_e( 'Export Files', 'import-export-by-rockstarlab' ); ?></span>
								<strong class="rsl-ie-result-value rsl-ie-migration-result-files">0</strong>
							</div>
						</div>
						<div class="rsl-ie-result-item">
							<div class="rsl-ie-result-icon">
								<span class="dashicons dashicons-database rsl-ie-migration-result-items-icon"></span>
							</div>
							<div class="rsl-ie-result-details">
								<span class="rsl-ie-result-label rsl-ie-migration-result-items-label"><?php esc_html_e( 'Exported Items', 'import-export-by-rockstarlab' ); ?></span>
								<strong class="rsl-ie-result-value rsl-ie-migration-result-items">0</strong>
							</div>
						</div>
						<div class="rsl-ie-result-item">
							<div class="rsl-ie-result-icon">
								<span class="dashicons dashicons-media-archive"></span>
							</div>
							<div class="rsl-ie-result-details">
								<span class="rsl-ie-result-label rsl-ie-migration-result-size-label"><?php esc_html_e( 'Package Size', 'import-export-by-rockstarlab' ); ?></span>
								<strong class="rsl-ie-result-value rsl-ie-migration-result-size">0 KB</strong>
							</div>
						</div>
						<div class="rsl-ie-result-item">
							<div class="rsl-ie-result-icon">
								<span class="dashicons dashicons-clock"></span>
							</div>
							<div class="rsl-ie-result-details">
								<span class="rsl-ie-result-label"><?php esc_html_e( 'Duration', 'import-export-by-rockstarlab' ); ?></span>
								<strong class="rsl-ie-result-value rsl-ie-migration-result-duration">0s</strong>
							</div>
						</div>
					</div>

					<p class="rsl-ie-download-action">
						<a class="button button-primary button-hero rsl-ie-migration-download-file" href="#">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Download Migration Package', 'import-export-by-rockstarlab' ); ?>
						</a>
					</p>
				</div>
			</div>
		</section>

		<div class="rsl-ie-migration-steps rsl-ie-steps-indicator">
			<div class="rsl-ie-step-indicator active" data-step="1">
				<div class="rsl-ie-step-number">1</div>
				<div class="rsl-ie-step-label"><?php esc_html_e( 'Method', 'import-export-by-rockstarlab' ); ?></div>
			</div>
			<div class="rsl-ie-step-indicator" data-step="2">
				<div class="rsl-ie-step-number">2</div>
				<div class="rsl-ie-step-label"><?php esc_html_e( 'Options', 'import-export-by-rockstarlab' ); ?></div>
			</div>
			<div class="rsl-ie-step-indicator" data-step="3">
				<div class="rsl-ie-step-number">3</div>
				<div class="rsl-ie-step-label"><?php esc_html_e( 'Migration', 'import-export-by-rockstarlab' ); ?></div>
			</div>
		</div>
	</div>
</div>
