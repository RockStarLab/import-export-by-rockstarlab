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
			<?php esc_html_e( 'Step 2: Sync Options', 'amplified-import-export' ); ?>
		</h2>
	</div>

	<div class="aie-card-body">
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="aie-duplicate-check">
						<?php esc_html_e( 'Duplicate Detection', 'amplified-import-export' ); ?>
					</label>
				</th>
				<td>
					<select id="aie-duplicate-check" class="regular-text">
						<option value="hash"><?php esc_html_e( 'MD5 Hash (most accurate)', 'amplified-import-export' ); ?></option>
						<option value="filename"><?php esc_html_e( 'Filename Match', 'amplified-import-export' ); ?></option>
						<option value="filesize"><?php esc_html_e( 'Filesize + Filename', 'amplified-import-export' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'How to detect if file already exists in media library', 'amplified-import-export' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="aie-duplicate-handling">
						<?php esc_html_e( 'Duplicate Handling', 'amplified-import-export' ); ?>
					</label>
				</th>
				<td>
					<select id="aie-duplicate-handling" class="regular-text">
						<option value="skip"><?php esc_html_e( 'Skip Duplicates', 'amplified-import-export' ); ?></option>
						<option value="overwrite"><?php esc_html_e( 'Overwrite Existing', 'amplified-import-export' ); ?></option>
						<option value="rename"><?php esc_html_e( 'Rename and Import', 'amplified-import-export' ); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="aie-copy-files">
						<?php esc_html_e( 'File Operation', 'amplified-import-export' ); ?>
					</label>
				</th>
				<td>
					<select id="aie-copy-files" class="regular-text">
						<option value="keep"><?php esc_html_e( 'Keep in current directory', 'amplified-import-export' ); ?></option>
						<option value="copy"><?php esc_html_e( 'Copy Files (keep originals)', 'amplified-import-export' ); ?></option>
						<option value="move"><?php esc_html_e( 'Move Files (delete originals)', 'amplified-import-export' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Choose how to handle files during import', 'amplified-import-export' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="aie-batch-size">
						<?php esc_html_e( 'Batch Size', 'amplified-import-export' ); ?>
					</label>
				</th>
				<td>
					<input type="number" id="aie-batch-size" class="small-text" value="3" min="1" max="100" step="1">
					<p class="description">
						<?php esc_html_e( 'Number of files to process per batch. Lower values show more progress updates but take longer.', 'amplified-import-export' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="aie-rml-integration">
						<?php esc_html_e( 'Real Media Library', 'amplified-import-export' ); ?>
						<?php if ( ! aie_fs()->can_use_premium_code() ) : ?>
							<span class="aie-premium-badge" title="<?php esc_attr_e( 'Premium Feature', 'amplified-import-export' ); ?>">
								<span class="dashicons dashicons-star-filled"></span>
								<?php esc_html_e( 'PRO', 'amplified-import-export' ); ?>
							</span>
						<?php endif; ?>
					</label>
				</th>
				<td>
					<?php if ( aie_fs()->can_use_premium_code() ) : ?>
						<label>
							<input type="checkbox" id="aie-rml-integration">
							<?php
							// translators: %s is the Real Media Library plugin link.
							printf( esc_html__( 'Create virtual folders in %s plugin', 'amplified-import-export' ), '<a href="https://devowl.io/wordpress-real-media-library/" target="_blank">Real Media Library</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML link is hardcoded.
							?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Automatically create folders in Real Media Library and organize imported files based on their folder structure.', 'amplified-import-export' ); ?>
						</p>
					<?php else : ?>
						<p class="description">
							<?php
							printf(
								/* translators: %s: upgrade link */
								esc_html__( 'This feature requires a premium subscription. %s to unlock Real Media Library integration.', 'amplified-import-export' ),
								'<a href="' . esc_url( aie_fs()->get_upgrade_url() ) . '" target="_blank">' . esc_html__( 'Upgrade now', 'amplified-import-export' ) . '</a>'
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
				<?php esc_html_e( 'Start Synchronization', 'amplified-import-export' ); ?>
			</button>
		</div>
	</div>
</div>
