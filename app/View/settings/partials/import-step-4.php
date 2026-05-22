<?php
/**
 * Import Step 4: Field Mapping
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 4: Field Mapping -->
<div class="rsl-ie-step rsl-ie-step-4" data-step="4">
	<div class="rsl-ie-step-header">
		<h2><?php esc_html_e( 'Step 4: Field Mapping', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Drag columns from your file to WordPress fields', 'import-export-by-rockstarlab' ); ?></p>
	</div>

	<div class="rsl-ie-step-content">
		<!-- Post Type Selector for Custom Post Types -->
		<div class="rsl-ie-post-type-selector" style="display: none;">
			<label for="rsl-ie-custom-post-type">
				<?php esc_html_e( 'Choose post type:', 'import-export-by-rockstarlab' ); ?>
			</label>
			<select id="rsl-ie-custom-post-type" class="regular-text">
				<option value=""><?php esc_html_e( '-- Select Post Type --', 'import-export-by-rockstarlab' ); ?></option>
				<!-- Will be populated by JavaScript -->
			</select>
		</div>

		<!-- Database Table Selection (shown only for database_table type) -->
		<div class="rsl-ie-table-selection-section" style="display:none;">
			<div class="rsl-ie-section-header">
				<h3>
					<span class="dashicons dashicons-database-view"></span>
					<?php esc_html_e( 'Select Database Table', 'import-export-by-rockstarlab' ); ?>
				</h3>
				<p class="description"><?php esc_html_e( 'Choose which database table you want to import data into', 'import-export-by-rockstarlab' ); ?></p>
			</div>

			<div class="rsl-ie-table-selector">
				<label for="rsl-ie-import-table-name"><?php esc_html_e( 'Database Table:', 'import-export-by-rockstarlab' ); ?></label>
				<select id="rsl-ie-import-table-name" name="table_name" class="rsl-ie-table-dropdown">
					<option value=""><?php esc_html_e( 'Loading tables...', 'import-export-by-rockstarlab' ); ?></option>
				</select>
				<span class="spinner" style="float:none;margin:0 10px;"></span>
			</div>

			<div class="rsl-ie-table-info" style="display:none;">
				<div class="rsl-ie-info-card">
					<h4><?php esc_html_e( 'Table Information', 'import-export-by-rockstarlab' ); ?></h4>
					<div class="rsl-ie-table-stats">
						<div class="rsl-ie-stat">
							<span class="label"><?php esc_html_e( 'Total Rows:', 'import-export-by-rockstarlab' ); ?></span>
							<span class="value rsl-ie-table-row-count">-</span>
						</div>
						<div class="rsl-ie-stat">
							<span class="label"><?php esc_html_e( 'Total Columns:', 'import-export-by-rockstarlab' ); ?></span>
							<span class="value rsl-ie-table-column-count">-</span>
						</div>
					</div>
					<div class="rsl-ie-table-columns">
						<h5><?php esc_html_e( 'Available Columns:', 'import-export-by-rockstarlab' ); ?></h5>
						<div class="rsl-ie-columns-list"></div>
					</div>
				</div>
			</div>
		</div>

		<!-- Mapping Controls -->
		<div class="rsl-ie-mapping-controls">
			<button type="button" class="button rsl-ie-auto-map">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'Auto Map', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button rsl-ie-clear-map">
				<span class="dashicons dashicons-dismiss"></span>
				<?php esc_html_e( 'Clear All', 'import-export-by-rockstarlab' ); ?>
			</button>
			<div class="rsl-ie-mapping-stats">
				<span class="rsl-ie-mapped-count">0</span> / <span class="rsl-ie-total-fields">0</span> <?php esc_html_e( 'fields mapped', 'import-export-by-rockstarlab' ); ?>
			</div>
		</div>

		<!-- Drag & Drop Mapping Interface -->
		<div class="rsl-ie-mapping-container">
			<!-- Source Fields (from file) -->
			<div class="rsl-ie-mapping-source">
				<h3>
					<span class="dashicons dashicons-media-spreadsheet"></span>
					<?php esc_html_e( 'Your File Columns', 'import-export-by-rockstarlab' ); ?>
				</h3>
				<div class="rsl-ie-search-box">
					<input type="text" class="rsl-ie-search-source" placeholder="<?php esc_attr_e( 'Search columns...', 'import-export-by-rockstarlab' ); ?>">
					<span class="dashicons dashicons-search"></span>
					<button type="button" class="rsl-ie-clear-search" title="<?php esc_attr_e( 'Clear search', 'import-export-by-rockstarlab' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="rsl-ie-source-fields" id="rsl-ie-source-fields">
					<!-- Populated by JavaScript -->
				</div>
			</div>

			<!-- Target Fields (WordPress) -->
			<div class="rsl-ie-mapping-target">
				<h3>
					<span class="dashicons dashicons-wordpress"></span>
					<?php esc_html_e( 'WordPress Fields', 'import-export-by-rockstarlab' ); ?>
				</h3>
				<div class="rsl-ie-search-box">
					<input type="text" class="rsl-ie-search-target" placeholder="<?php esc_attr_e( 'Search fields...', 'import-export-by-rockstarlab' ); ?>">
					<span class="dashicons dashicons-search"></span>
					<button type="button" class="rsl-ie-clear-search" title="<?php esc_attr_e( 'Clear search', 'import-export-by-rockstarlab' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="rsl-ie-target-fields" id="rsl-ie-target-fields">
					<!-- Populated by JavaScript -->
				</div>
			</div>
		</div>

		<!-- Mapped Fields Area -->
		<div class="rsl-ie-mapped-fields-section">
			<h3>
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Mapped Fields', 'import-export-by-rockstarlab' ); ?>
			</h3>
			<div class="rsl-ie-mapped-fields" id="rsl-ie-mapped-fields">
				<div class="rsl-ie-empty-state">
					<span class="dashicons dashicons-info"></span>
					<p><?php esc_html_e( 'Drag source columns to WordPress fields to create mappings', 'import-export-by-rockstarlab' ); ?></p>
				</div>
			</div>
		</div>

		<div class="rsl-ie-step-actions">
			<button type="button" class="button button-secondary rsl-ie-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary button-large rsl-ie-next-step">
				<?php esc_html_e( 'Next Step', 'import-export-by-rockstarlab' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
