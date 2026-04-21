<?php
/**
 * Content Updater Step 2: Select Fields
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 3: Select Fields -->
<div class="aie-step aie-updater-step-3" data-step="3">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Step 3: Select Fields to Update', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Choose which fields you want to update', 'import-export-by-rockstarlab' ); ?></p>
	</div>

	<div class="aie-step-content">
		<div class="aie-step-2-columns">
			<!-- Left Column: Selected Fields -->
			<div class="aie-updater-selected-fields">
				<div class="aie-selected-fields-header">
					<h3>
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'Selected Fields', 'import-export-by-rockstarlab' ); ?>
					</h3>
					<div class="aie-selected-fields-actions">
						<button type="button" class="button button-small aie-updater-clear-all-fields" title="<?php esc_attr_e( 'Clear all fields', 'import-export-by-rockstarlab' ); ?>">
							<span class="dashicons dashicons-trash"></span>
							<?php esc_html_e( 'Clear All', 'import-export-by-rockstarlab' ); ?>
						</button>
					</div>
				</div>

				<div class="aie-selected-fields-body">
					<!-- Drop Zone -->
					<div class="aie-updater-dropzone" id="aie-updater-dropzone">
						<div class="aie-updater-dropzone-placeholder">
							<span class="dashicons dashicons-download"></span>
							<p><?php esc_html_e( 'Drag fields here to select for updating', 'import-export-by-rockstarlab' ); ?></p>
						</div>
						
						<!-- Selected Fields Container -->
						<div class="aie-updater-fields-list" id="aie-updater-fields-list">
							<!-- Fields will be added here dynamically -->
						</div>
					</div>

					<!-- Field Counter -->
					<div class="aie-updater-stats">
						<span class="aie-field-count">
							<strong><?php esc_html_e( 'Fields Selected:', 'import-export-by-rockstarlab' ); ?></strong> 
							<span class="aie-count-value aie-fields-count">0</span>
						</span>
					</div>
				</div>
			</div>

			<!-- Right Column: Available Fields Library -->
			<div class="aie-fields-library">
				<div class="aie-fields-library-header">
					<h3>
						<span class="dashicons dashicons-list-view"></span>
						<?php esc_html_e( 'Available Fields', 'import-export-by-rockstarlab' ); ?>
					</h3>
				</div>
				
				<!-- Search/Filter -->
				<div class="aie-fields-search">
					<input 
						type="text" 
						id="aie-updater-fields-search" 
						class="regular-text" 
						placeholder="<?php esc_attr_e( 'Search fields...', 'import-export-by-rockstarlab' ); ?>"
					>
					<span class="dashicons dashicons-search"></span>
					<button type="button" class="aie-clear-search" aria-label="<?php esc_attr_e( 'Clear search', 'import-export-by-rockstarlab' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>

				<div class="aie-fields-library-body" id="aie-updater-fields-library">
					<div class="aie-fields-loading">
						<span class="spinner is-active"></span>
						<p><?php esc_html_e( 'Loading fields...', 'import-export-by-rockstarlab' ); ?></p>
					</div>
					<!-- Fields will be loaded dynamically based on content type -->
				</div>
			</div>
		</div>

		<div class="aie-step-actions">
			<button type="button" class="button button-secondary aie-updater-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary button-large aie-updater-next-step">
				<?php esc_html_e( 'Next Step', 'import-export-by-rockstarlab' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
