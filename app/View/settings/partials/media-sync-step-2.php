<?php
/**
 * Media Sync Step 2: Sync Options
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 2: Sync Options -->
<div class="aie-card aie-options-section" id="aie-sync-options" style="display: none;">
	<div class="aie-card-header">
		<h2>
			<span class="dashicons dashicons-admin-settings"></span>
			<?php esc_html_e( 'Step 2: Sync Options', 'import-export-by-rockstarlab' ); ?>
		</h2>
	</div>

	<div class="aie-card-body">
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="aie-duplicate-check">
						<?php esc_html_e( 'Duplicate Detection', 'import-export-by-rockstarlab' ); ?>
					</label>
				</th>
				<td>
					<select id="aie-duplicate-check" class="regular-text">
						<option value="hash"><?php esc_html_e( 'MD5 Hash (most accurate)', 'import-export-by-rockstarlab' ); ?></option>
						<option value="filename"><?php esc_html_e( 'Filename Match', 'import-export-by-rockstarlab' ); ?></option>
						<option value="filesize"><?php esc_html_e( 'Filesize + Filename', 'import-export-by-rockstarlab' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'How to detect if file already exists in media library', 'import-export-by-rockstarlab' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="aie-duplicate-handling">
						<?php esc_html_e( 'Duplicate Handling', 'import-export-by-rockstarlab' ); ?>
					</label>
				</th>
				<td>
					<select id="aie-duplicate-handling" class="regular-text">
						<option value="skip"><?php esc_html_e( 'Skip Duplicates', 'import-export-by-rockstarlab' ); ?></option>
						<option value="overwrite"><?php esc_html_e( 'Overwrite Existing', 'import-export-by-rockstarlab' ); ?></option>
						<option value="rename"><?php esc_html_e( 'Rename and Import', 'import-export-by-rockstarlab' ); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="aie-copy-files">
						<?php esc_html_e( 'File Operation', 'import-export-by-rockstarlab' ); ?>
					</label>
				</th>
				<td>
					<select id="aie-copy-files" class="regular-text">
						<option value="keep"><?php esc_html_e( 'Keep in current directory', 'import-export-by-rockstarlab' ); ?></option>
						<option value="copy"><?php esc_html_e( 'Copy Files (keep originals)', 'import-export-by-rockstarlab' ); ?></option>
						<option value="move"><?php esc_html_e( 'Move Files (delete originals)', 'import-export-by-rockstarlab' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Choose how to handle files during import', 'import-export-by-rockstarlab' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="aie-batch-size">
						<?php esc_html_e( 'Batch Size', 'import-export-by-rockstarlab' ); ?>
					</label>
				</th>
				<td>
					<input type="number" id="aie-batch-size" class="small-text" value="3" min="1" max="100" step="1">
					<p class="description">
						<?php esc_html_e( 'Number of files to process per batch. Lower values show more progress updates but take longer.', 'import-export-by-rockstarlab' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="aie-rml-integration">
						<?php esc_html_e( 'Real Media Library', 'import-export-by-rockstarlab' ); ?>
						<?php if ( ! rsl_ie_fs()->can_use_premium_code() ) : ?>
							<span class="aie-premium-badge" title="<?php esc_attr_e( 'Premium Feature', 'import-export-by-rockstarlab' ); ?>">
								<span class="dashicons dashicons-star-filled"></span>
								<?php esc_html_e( 'PRO', 'import-export-by-rockstarlab' ); ?>
							</span>
						<?php endif; ?>
					</label>
				</th>
				<td>
					<?php if ( rsl_ie_fs()->can_use_premium_code() ) : ?>
						<label>
							<input type="checkbox" id="aie-rml-integration">
							<?php
							// translators: %s is the Real Media Library plugin link.
							printf( esc_html__( 'Create virtual folders in %s plugin', 'import-export-by-rockstarlab' ), '<a href="https://devowl.io/wordpress-real-media-library/" target="_blank">Real Media Library</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML link is hardcoded.
							?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Automatically create folders in Real Media Library and organize imported files based on their folder structure.', 'import-export-by-rockstarlab' ); ?>
						</p>
					<?php else : ?>
						<p class="description">
							<?php
							printf(
								/* translators: %s: upgrade link */
								esc_html__( 'This feature requires a premium subscription. %s to unlock Real Media Library integration.', 'import-export-by-rockstarlab' ),
								'<a href="' . esc_url( rsl_ie_fs()->get_upgrade_url() ) . '" target="_blank">' . esc_html__( 'Upgrade now', 'import-export-by-rockstarlab' ) . '</a>'
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
				<?php esc_html_e( 'Start Synchronization', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
	</div>
</div>
