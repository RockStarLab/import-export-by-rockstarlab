<?php
/**
 * Sync Content Modal Template
 *
 * @var array $sites Connected sites
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="aie-sync-modal" class="aie-modal" style="display: none;">
	<div class="aie-modal-content">
		<div class="aie-modal-header">
			<h2><?php esc_html_e( 'Sync Content', 'wp-advanced-import-export' ); ?></h2>
			<button type="button" class="aie-modal-close">&times;</button>
		</div>
		<div class="aie-modal-body">
			<div class="aie-sync-info">
				<p>
					<strong><?php esc_html_e( 'Selected posts:', 'wp-advanced-import-export' ); ?></strong>
					<span id="aie-selected-count">0</span>
				</p>
			</div>
			
			<div class="aie-form-group">
				<label for="aie-sync-site-select">
					<?php esc_html_e( 'Select Site', 'wp-advanced-import-export' ); ?>
				</label>
				<select id="aie-sync-site-select" class="aie-form-control">
					<option value=""><?php esc_html_e( '-- Select Site --', 'wp-advanced-import-export' ); ?></option>
					<?php foreach ( $sites as $site ) : ?>
						<option value="<?php echo esc_attr( $site['id'] ); ?>" data-site-name="<?php echo esc_attr( $site['name'] ); ?>">
							<?php echo esc_html( $site['name'] ); ?>
							(<?php echo esc_html( $site['remote_url'] ); ?>)
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="aie-sync-direction">
				<button type="button" id="aie-sync-push-btn" class="button button-primary" disabled>
					<span class="dashicons dashicons-upload"></span>
					<?php esc_html_e( 'Push to Site', 'wp-advanced-import-export' ); ?>
				</button>
				<button type="button" id="aie-sync-pull-btn" class="button button-primary" disabled>
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Pull from Site', 'wp-advanced-import-export' ); ?>
				</button>
			</div>

			<div id="aie-sync-progress" style="display: none;">
				<div class="aie-progress-bar">
					<div class="aie-progress-fill"></div>
				</div>
				<p class="aie-progress-text"></p>
			</div>

			<div id="aie-sync-result" style="display: none;"></div>
		</div>
	</div>
</div>
