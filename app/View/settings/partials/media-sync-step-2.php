<?php
/**
 * Media Sync Step 2: Sync Options
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 2: Sync Options -->
<div class="aie-card aie-options-section" id="aie-sync-options" style="display: none;">
	<div class="aie-card-header">
		<h2>
			<span class="dashicons dashicons-admin-settings"></span>
			<?php esc_html_e( 'Step 2: Sync Options', 'wp-advanced-import-export' ); ?>
		</h2>
	</div>

	<div class="aie-card-body">
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="aie-duplicate-check">
						<?php esc_html_e( 'Duplicate Detection', 'wp-advanced-import-export' ); ?>
					</label>
				</th>
				<td>
					<select id="aie-duplicate-check" class="regular-text">
						<option value="hash"><?php esc_html_e( 'MD5 Hash (most accurate)', 'wp-advanced-import-export' ); ?></option>
						<option value="filename"><?php esc_html_e( 'Filename Match', 'wp-advanced-import-export' ); ?></option>
						<option value="filesize"><?php esc_html_e( 'Filesize + Filename', 'wp-advanced-import-export' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'How to detect if file already exists in media library', 'wp-advanced-import-export' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="aie-duplicate-handling">
						<?php esc_html_e( 'Duplicate Handling', 'wp-advanced-import-export' ); ?>
					</label>
				</th>
				<td>
					<select id="aie-duplicate-handling" class="regular-text">
						<option value="skip"><?php esc_html_e( 'Skip Duplicates', 'wp-advanced-import-export' ); ?></option>
						<option value="overwrite"><?php esc_html_e( 'Overwrite Existing', 'wp-advanced-import-export' ); ?></option>
						<option value="rename"><?php esc_html_e( 'Rename and Import', 'wp-advanced-import-export' ); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="aie-copy-files">
						<?php esc_html_e( 'File Operation', 'wp-advanced-import-export' ); ?>
					</label>
				</th>
				<td>
					<select id="aie-copy-files" class="regular-text">
						<option value="keep"><?php esc_html_e( 'Keep in current directory', 'wp-advanced-import-export' ); ?></option>
						<option value="copy"><?php esc_html_e( 'Copy Files (keep originals)', 'wp-advanced-import-export' ); ?></option>
						<option value="move"><?php esc_html_e( 'Move Files (delete originals)', 'wp-advanced-import-export' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Choose how to handle files during import', 'wp-advanced-import-export' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="aie-batch-size">
						<?php esc_html_e( 'Batch Size', 'wp-advanced-import-export' ); ?>
					</label>
				</th>
				<td>
					<input type="number" id="aie-batch-size" class="small-text" value="3" min="1" max="100" step="1">
					<p class="description">
						<?php esc_html_e( 'Number of files to process per batch. Lower values show more progress updates but take longer.', 'wp-advanced-import-export' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="aie-rml-integration">
						<?php esc_html_e( 'Real Media Library', 'wp-advanced-import-export' ); ?>
						<?php if ( ! waie_fs()->can_use_premium_code() ) : ?>
							<span class="aie-premium-badge" title="<?php esc_attr_e( 'Premium Feature', 'wp-advanced-import-export' ); ?>">
								<span class="dashicons dashicons-star-filled"></span>
								<?php esc_html_e( 'PRO', 'wp-advanced-import-export' ); ?>
							</span>
						<?php endif; ?>
					</label>
				</th>
				<td>
					<?php if ( waie_fs()->can_use_premium_code() ) : ?>
						<label>
							<input type="checkbox" id="aie-rml-integration">
							<?php
							// translators: %s is the Real Media Library plugin link.
							printf( esc_html__( 'Create virtual folders in %s plugin', 'wp-advanced-import-export' ), '<a href="https://devowl.io/wordpress-real-media-library/" target="_blank">Real Media Library</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML link is hardcoded.
							?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Automatically create folders in Real Media Library and organize imported files based on their folder structure.', 'wp-advanced-import-export' ); ?>
						</p>
					<?php else : ?>
						<p class="description">
							<?php
							printf(
								/* translators: %s: upgrade link */
								esc_html__( 'This feature requires a premium subscription. %s to unlock Real Media Library integration.', 'wp-advanced-import-export' ),
								'<a href="' . esc_url( waie_fs()->get_upgrade_url() ) . '" target="_blank">' . esc_html__( 'Upgrade now', 'wp-advanced-import-export' ) . '</a>'
							);
							?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<div class="aie-actions">
			<button type="button" id="aie-start-sync-btn" class="button button-primary button-large">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Start Synchronization', 'wp-advanced-import-export' ); ?>
			</button>
		</div>
	</div>
</div>
