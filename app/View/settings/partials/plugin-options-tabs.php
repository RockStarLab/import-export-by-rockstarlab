<?php
/**
 * Plugin Options navigation tabs.
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

$rsl_ie_options_active_tab = isset( $rsl_ie_options_active_tab ) ? $rsl_ie_options_active_tab : 'ai';
?>
<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Plugin options navigation', 'import-export-by-rockstarlab' ); ?>">
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-plugin-settings' ) ); ?>" class="nav-tab <?php echo 'settings' === $rsl_ie_options_active_tab ? 'nav-tab-active' : ''; ?>">
		<?php esc_html_e( 'Settings', 'import-export-by-rockstarlab' ); ?>
	</a>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-button-settings' ) ); ?>" class="nav-tab <?php echo 'buttons' === $rsl_ie_options_active_tab ? 'nav-tab-active' : ''; ?>">
		<?php esc_html_e( 'Export / Sync Buttons', 'import-export-by-rockstarlab' ); ?>
	</a>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-plugin-options' ) ); ?>" class="nav-tab <?php echo 'ai' === $rsl_ie_options_active_tab ? 'nav-tab-active' : ''; ?>">
		<?php esc_html_e( 'AI Integration', 'import-export-by-rockstarlab' ); ?>
	</a>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-agent-connection' ) ); ?>" class="nav-tab <?php echo 'agent' === $rsl_ie_options_active_tab ? 'nav-tab-active' : ''; ?>">
		<?php esc_html_e( 'AI Agent Connection', 'import-export-by-rockstarlab' ); ?>
	</a>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-servers' ) ); ?>" class="nav-tab <?php echo 'servers' === $rsl_ie_options_active_tab ? 'nav-tab-active' : ''; ?>">
		<?php esc_html_e( 'FTP / SFTP Servers', 'import-export-by-rockstarlab' ); ?>
	</a>
</nav>
