<?php
/**
 * Content Updater Step 2: Select Fields
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 3: Select Fields -->
<div class="rsl-ie-step rsl-ie-updater-step-3" data-step="3">
	<div class="rsl-ie-step-header">
		<h2><?php esc_html_e( 'Step 3: Select Fields to Update', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Choose which fields you want to update', 'import-export-by-rockstarlab' ); ?></p>
	</div>

	<div class="rsl-ie-step-content">
		<div class="rsl-ie-step-2-columns">
			<!-- Left Column: Selected Fields -->
			<div class="rsl-ie-updater-selected-fields">
				<div class="rsl-ie-selected-fields-header">
					<h3>
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'Selected Fields', 'import-export-by-rockstarlab' ); ?>
					</h3>
					<div class="rsl-ie-selected-fields-actions">
						<button type="button" class="button button-small rsl-ie-updater-clear-all-fields" title="<?php esc_attr_e( 'Clear all fields', 'import-export-by-rockstarlab' ); ?>">
							<span class="dashicons dashicons-trash"></span>
							<?php esc_html_e( 'Clear All', 'import-export-by-rockstarlab' ); ?>
						</button>
					</div>
				</div>

				<div class="rsl-ie-selected-fields-body">
					<!-- Drop Zone -->
					<div class="rsl-ie-updater-dropzone" id="rsl-ie-updater-dropzone">
						<div class="rsl-ie-updater-dropzone-placeholder">
							<span class="dashicons dashicons-download"></span>
							<p><?php esc_html_e( 'Drag fields here to select for updating', 'import-export-by-rockstarlab' ); ?></p>
						</div>
						
						<!-- Selected Fields Container -->
						<div class="rsl-ie-updater-fields-list" id="rsl-ie-updater-fields-list">
							<!-- Fields will be added here dynamically -->
						</div>
					</div>

					<!-- Field Counter -->
					<div class="rsl-ie-updater-stats">
						<span class="rsl-ie-field-count">
							<strong><?php esc_html_e( 'Fields Selected:', 'import-export-by-rockstarlab' ); ?></strong> 
							<span class="rsl-ie-count-value rsl-ie-fields-count">0</span>
						</span>
					</div>
				</div>
			</div>

			<!-- Right Column: Available Fields Library -->
			<div class="rsl-ie-fields-library">
				<div class="rsl-ie-fields-library-header">
					<h3>
						<span class="dashicons dashicons-list-view"></span>
						<?php esc_html_e( 'Available Fields', 'import-export-by-rockstarlab' ); ?>
					</h3>
				</div>
				
				<!-- Search/Filter -->
				<div class="rsl-ie-fields-search">
					<input 
						type="text" 
						id="rsl-ie-updater-fields-search" 
						class="regular-text" 
						placeholder="<?php esc_attr_e( 'Search fields...', 'import-export-by-rockstarlab' ); ?>"
					>
					<span class="dashicons dashicons-search"></span>
					<button type="button" class="rsl-ie-clear-search" aria-label="<?php esc_attr_e( 'Clear search', 'import-export-by-rockstarlab' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>

				<div class="rsl-ie-fields-library-body" id="rsl-ie-updater-fields-library">
					<div class="rsl-ie-fields-loading">
						<span class="spinner is-active"></span>
						<p><?php esc_html_e( 'Loading fields...', 'import-export-by-rockstarlab' ); ?></p>
					</div>
					<!-- Fields will be loaded dynamically based on content type -->
				</div>
			</div>
		</div>

		<div class="rsl-ie-step-actions">
			<button type="button" class="button button-secondary rsl-ie-updater-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary button-large rsl-ie-updater-next-step">
				<?php esc_html_e( 'Next Step', 'import-export-by-rockstarlab' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
