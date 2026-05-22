<?php
/**
 * Sync Content Modal Template
 *
 * @var array $sites Connected sites
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="rsl-ie-sync-modal" class="rsl-ie-modal" style="display: none;">
	<div class="rsl-ie-modal-content">
		<div class="rsl-ie-modal-header">
			<h2><?php esc_html_e( 'Sync Content', 'import-export-by-rockstarlab' ); ?></h2>
			<button type="button" class="rsl-ie-modal-close">&times;</button>
		</div>
		<div class="rsl-ie-modal-body">
			<div class="rsl-ie-sync-info">
				<p>
					<strong><?php esc_html_e( 'Selected posts:', 'import-export-by-rockstarlab' ); ?></strong>
					<span id="rsl-ie-selected-count">0</span>
				</p>
			</div>
			
			<div class="rsl-ie-form-group">
				<label for="rsl-ie-sync-site-select">
					<?php esc_html_e( 'Select Site', 'import-export-by-rockstarlab' ); ?>
				</label>
				<select id="rsl-ie-sync-site-select" class="rsl-ie-form-control">
					<option value=""><?php esc_html_e( '-- Select Site --', 'import-export-by-rockstarlab' ); ?></option>
					<?php foreach ( $sites as $rsl_ie_site ) : ?>
						<option value="<?php echo esc_attr( $rsl_ie_site['id'] ); ?>" data-site-name="<?php echo esc_attr( $rsl_ie_site['name'] ); ?>">
							<?php echo esc_html( $rsl_ie_site['name'] ); ?>
							(<?php echo esc_html( $rsl_ie_site['remote_url'] ); ?>)
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="rsl-ie-sync-direction">
				<button type="button" id="rsl-ie-sync-push-btn" class="button button-primary" disabled>
					<span class="dashicons dashicons-upload"></span>
					<?php esc_html_e( 'Push to Site', 'import-export-by-rockstarlab' ); ?>
				</button>
				<button type="button" id="rsl-ie-sync-pull-btn" class="button button-primary" disabled>
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Pull from Site', 'import-export-by-rockstarlab' ); ?>
				</button>
			</div>

			<div id="rsl-ie-sync-progress" style="display: none;">
				<div class="rsl-ie-progress-bar">
					<div class="rsl-ie-progress-fill"></div>
				</div>
				<p class="rsl-ie-progress-text"></p>
			</div>

			<div id="rsl-ie-sync-result" style="display: none;"></div>
		</div>
	</div>
</div>
