<?php
/**
 * Jobs and Schedules navigation tabs.
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

$rsl_ie_active_tab = isset( $rsl_ie_active_tab ) ? $rsl_ie_active_tab : 'jobs';
?>
<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Jobs navigation', 'import-export-by-rockstarlab' ); ?>">
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-jobs-log' ) ); ?>" class="nav-tab <?php echo 'jobs' === $rsl_ie_active_tab ? 'nav-tab-active' : ''; ?>">
		<?php esc_html_e( 'Jobs Log', 'import-export-by-rockstarlab' ); ?>
	</a>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-schedules' ) ); ?>" class="nav-tab <?php echo 'schedules' === $rsl_ie_active_tab ? 'nav-tab-active' : ''; ?>">
		<?php esc_html_e( 'Schedules', 'import-export-by-rockstarlab' ); ?>
	</a>
</nav>
