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
		<h2><?php esc_html_e( 'Step 4: Field Mapping', 'wp-aie' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Drag columns from your file to WordPress fields', 'wp-aie' ); ?></p>
	</div>

	<div class="aie-step-content">
		<!-- Mapping Controls -->
		<div class="aie-mapping-controls">
			<button type="button" class="button aie-auto-map">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'Auto Map', 'wp-aie' ); ?>
			</button>
			<button type="button" class="button aie-clear-map">
				<span class="dashicons dashicons-dismiss"></span>
				<?php esc_html_e( 'Clear All', 'wp-aie' ); ?>
			</button>
			<div class="aie-mapping-stats">
				<span class="aie-mapped-count">0</span> / <span class="aie-total-fields">0</span> <?php esc_html_e( 'fields mapped', 'wp-aie' ); ?>
			</div>
		</div>

		<!-- Drag & Drop Mapping Interface -->
		<div class="aie-mapping-container">
			<!-- Source Fields (from file) -->
			<div class="aie-mapping-source">
				<h3>
					<span class="dashicons dashicons-media-spreadsheet"></span>
					<?php esc_html_e( 'Your File Columns', 'wp-aie' ); ?>
				</h3>
				<div class="aie-search-box">
					<input type="text" class="aie-search-source" placeholder="<?php esc_attr_e( 'Search columns...', 'wp-aie' ); ?>">
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
					<?php esc_html_e( 'WordPress Fields', 'wp-aie' ); ?>
				</h3>
				<div class="aie-search-box">
					<input type="text" class="aie-search-target" placeholder="<?php esc_attr_e( 'Search fields...', 'wp-aie' ); ?>">
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
				<?php esc_html_e( 'Mapped Fields', 'wp-aie' ); ?>
			</h3>
			<div class="aie-mapped-fields" id="aie-mapped-fields">
				<div class="aie-empty-state">
					<span class="dashicons dashicons-info"></span>
					<p><?php esc_html_e( 'Drag source columns to WordPress fields to create mappings', 'wp-aie' ); ?></p>
				</div>
			</div>
		</div>

		<div class="aie-step-actions">
			<button type="button" class="button button-secondary aie-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'wp-aie' ); ?>
			</button>
			<button type="button" class="button button-primary button-large aie-next-step">
				<?php esc_html_e( 'Next Step', 'wp-aie' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
