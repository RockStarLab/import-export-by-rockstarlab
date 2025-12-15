<?php
/**
 * Import Step 4: Field Mapping
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 4: Field Mapping -->
<div class="aie-step aie-step-4" data-step="4">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Step 4: Field Mapping', 'wp-advanced-import-export' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Drag columns from your file to WordPress fields', 'wp-advanced-import-export' ); ?></p>
	</div>

	<div class="aie-step-content">
		<!-- Post Type Selector for Custom Post Types -->
		<div class="aie-post-type-selector" style="display: none;">
			<label for="aie-custom-post-type">
				<?php esc_html_e( 'Choose post type:', 'wp-advanced-import-export' ); ?>
			</label>
			<select id="aie-custom-post-type" class="regular-text">
				<option value=""><?php esc_html_e( '-- Select Post Type --', 'wp-advanced-import-export' ); ?></option>
				<!-- Will be populated by JavaScript -->
			</select>
		</div>

		<!-- Database Table Selection (shown only for database_table type) -->
		<div class="aie-table-selection-section" style="display:none;">
			<div class="aie-section-header">
				<h3>
					<span class="dashicons dashicons-database-view"></span>
					<?php esc_html_e( 'Select Database Table', 'wp-advanced-import-export' ); ?>
				</h3>
				<p class="description"><?php esc_html_e( 'Choose which database table you want to import data into', 'wp-advanced-import-export' ); ?></p>
			</div>

			<div class="aie-table-selector">
				<label for="aie-import-table-name"><?php esc_html_e( 'Database Table:', 'wp-advanced-import-export' ); ?></label>
				<select id="aie-import-table-name" name="table_name" class="aie-table-dropdown">
					<option value=""><?php esc_html_e( 'Loading tables...', 'wp-advanced-import-export' ); ?></option>
				</select>
				<span class="spinner" style="float:none;margin:0 10px;"></span>
			</div>

			<div class="aie-table-info" style="display:none;">
				<div class="aie-info-card">
					<h4><?php esc_html_e( 'Table Information', 'wp-advanced-import-export' ); ?></h4>
					<div class="aie-table-stats">
						<div class="aie-stat">
							<span class="label"><?php esc_html_e( 'Total Rows:', 'wp-advanced-import-export' ); ?></span>
							<span class="value aie-table-row-count">-</span>
						</div>
						<div class="aie-stat">
							<span class="label"><?php esc_html_e( 'Total Columns:', 'wp-advanced-import-export' ); ?></span>
							<span class="value aie-table-column-count">-</span>
						</div>
					</div>
					<div class="aie-table-columns">
						<h5><?php esc_html_e( 'Available Columns:', 'wp-advanced-import-export' ); ?></h5>
						<div class="aie-columns-list"></div>
					</div>
				</div>
			</div>
		</div>

		<!-- Mapping Controls -->
		<div class="aie-mapping-controls">
			<button type="button" class="button aie-auto-map">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'Auto Map', 'wp-advanced-import-export' ); ?>
			</button>
			<button type="button" class="button aie-clear-map">
				<span class="dashicons dashicons-dismiss"></span>
				<?php esc_html_e( 'Clear All', 'wp-advanced-import-export' ); ?>
			</button>
			<div class="aie-mapping-stats">
				<span class="aie-mapped-count">0</span> / <span class="aie-total-fields">0</span> <?php esc_html_e( 'fields mapped', 'wp-advanced-import-export' ); ?>
			</div>
		</div>

		<!-- Drag & Drop Mapping Interface -->
		<div class="aie-mapping-container">
			<!-- Source Fields (from file) -->
			<div class="aie-mapping-source">
				<h3>
					<span class="dashicons dashicons-media-spreadsheet"></span>
					<?php esc_html_e( 'Your File Columns', 'wp-advanced-import-export' ); ?>
				</h3>
				<div class="aie-search-box">
					<input type="text" class="aie-search-source" placeholder="<?php esc_attr_e( 'Search columns...', 'wp-advanced-import-export' ); ?>">
					<span class="dashicons dashicons-search"></span>
				</div>
				<div class="aie-source-fields" id="aie-source-fields">
					<!-- Populated by JavaScript -->
				</div>
			</div>

			<!-- Target Fields (WordPress) -->
			<div class="aie-mapping-target">
				<h3>
					<span class="dashicons dashicons-wordpress"></span>
					<?php esc_html_e( 'WordPress Fields', 'wp-advanced-import-export' ); ?>
				</h3>
				<div class="aie-search-box">
					<input type="text" class="aie-search-target" placeholder="<?php esc_attr_e( 'Search fields...', 'wp-advanced-import-export' ); ?>">
					<span class="dashicons dashicons-search"></span>
				</div>
				<div class="aie-target-fields" id="aie-target-fields">
					<!-- Populated by JavaScript -->
				</div>
			</div>
		</div>

		<!-- Mapped Fields Area -->
		<div class="aie-mapped-fields-section">
			<h3>
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Mapped Fields', 'wp-advanced-import-export' ); ?>
			</h3>
			<div class="aie-mapped-fields" id="aie-mapped-fields">
				<div class="aie-empty-state">
					<span class="dashicons dashicons-info"></span>
					<p><?php esc_html_e( 'Drag source columns to WordPress fields to create mappings', 'wp-advanced-import-export' ); ?></p>
				</div>
			</div>
		</div>

		<div class="aie-step-actions">
			<button type="button" class="button button-secondary aie-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'wp-advanced-import-export' ); ?>
			</button>
			<button type="button" class="button button-primary button-large aie-next-step">
				<?php esc_html_e( 'Next Step', 'wp-advanced-import-export' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
