<?php
/**
 * Guided AI agent setup. Client settings are generated in the browser without storing credentials.
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
$rsl_ie_options_active_tab = 'agent';
$rsl_ie_updraft_available  = \RockStarLab\ImportExport\Abilities\Ability_Registry::is_updraftplus_available();
$rsl_ie_updraft_enabled    = \RockStarLab\ImportExport\Abilities\Ability_Registry::is_updraftplus_enabled();
$rsl_ie_enabled            = ! empty( $data['abilities_enabled'] );
$rsl_ie_ready              = $rsl_ie_enabled && $data['rest_status']['available'] && $data['abilities_available'];
$rsl_ie_connected          = $rsl_ie_ready && ! empty( $data['servers'] );
$rsl_ie_credentials        = $rsl_ie_connected && $data['application_passwords_available'];
?>
<div class="wrap" id="rsl-ie-agent-connection">
	<h1><?php esc_html_e( 'AI Agent Connection', 'import-export-by-rockstarlab' ); ?></h1>
	<?php require __DIR__ . '/partials/plugin-options-tabs.php'; ?>
	<section class="rsl-agent-access rsl-agent-primary-access <?php echo $rsl_ie_enabled ? 'is-enabled' : ''; ?>" aria-labelledby="rsl-agent-access-title">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-agent-access>
		<input type="hidden" name="action" value="rsl_ie_toggle_abilities">
		<?php wp_nonce_field( 'rsl_ie_toggle_abilities' ); ?>
		<div class="rsl-agent-access-heading">
			<span class="rsl-agent-access-icon dashicons dashicons-admin-links" aria-hidden="true"></span>
			<div><span class="rsl-agent-eyebrow"><?php esc_html_e( 'AI AGENT ACCESS', 'import-export-by-rockstarlab' ); ?></span><h2 id="rsl-agent-access-title"><label for="rsl-agent-access-toggle"><?php esc_html_e( 'Enable Abilities API', 'import-export-by-rockstarlab' ); ?></label></h2></div>
			<div class="rsl-agent-switch-wrap">
				<span class="rsl-agent-access-state"><?php echo esc_html( $rsl_ie_enabled ? __( 'Enabled', 'import-export-by-rockstarlab' ) : __( 'Disabled', 'import-export-by-rockstarlab' ) ); ?></span>
				<label class="rsl-agent-switch"><input id="rsl-agent-access-toggle" type="checkbox" role="switch" name="abilities_enabled" value="1" <?php checked( $rsl_ie_enabled ); ?> aria-label="<?php esc_attr_e( 'Enable Abilities API', 'import-export-by-rockstarlab' ); ?>" aria-describedby="rsl-agent-access-description"><span class="rsl-agent-switch-track" aria-hidden="true"></span></label>
			</div>
		</div>
		<ul class="rsl-agent-brands" aria-label="<?php esc_attr_e( 'Popular MCP-compatible AI agents', 'import-export-by-rockstarlab' ); ?>">
			<?php
			foreach ( [
				'openai'        => 'CodexGPT',
				'claude'        => 'Claude Code',
				'cursor'        => 'Cursor',
				'githubcopilot' => 'GitHub Copilot',
				'googlegemini'  => 'Gemini CLI',
			] as $rsl_ie_agent_icon => $rsl_ie_agent_name ) :
				?>
				<li><img src="<?php echo esc_url( RSL_IE_URL . 'assets/images/agents/' . $rsl_ie_agent_icon . '.svg' ); ?>" width="24" height="24" alt="" aria-hidden="true"><span><?php echo esc_html( $rsl_ie_agent_name ); ?></span></li>
			<?php endforeach; ?>
		</ul>
		<div id="rsl-agent-access-description">
			<p><?php esc_html_e( 'Let your AI agent work with your WordPress data. Abilities API exposes this plugin’s actions so you can export, import, and sync data between connected sites directly from your AI agent’s interface.', 'import-export-by-rockstarlab' ); ?></p>
			<p><?php esc_html_e( 'Connect with MCP-compatible agents such as Claude Code and Cursor through the WordPress MCP Adapter. Your agent needs compatible authentication or an MCP bridge; the setup wizard below guides you through the connection.', 'import-export-by-rockstarlab' ); ?></p>
		</div>
		<div class="rsl-agent-access-footer"><span class="dashicons dashicons-shield" aria-hidden="true"></span><span><?php esc_html_e( 'Disabled by default. You control access, and every action checks WordPress permissions. Turning this off removes this plugin’s Free and Pro actions from agent access; existing background jobs continue.', 'import-export-by-rockstarlab' ); ?></span></div>
		<p class="rsl-agent-save-status" role="status" data-saving="<?php esc_attr_e( 'Saving your preference…', 'import-export-by-rockstarlab' ); ?>"></p>
		<noscript><button type="submit" class="button button-primary"><?php esc_html_e( 'Save preference', 'import-export-by-rockstarlab' ); ?></button></noscript>
	</form>
	<aside class="rsl-agent-guide" aria-labelledby="rsl-agent-guide-title">
		<span class="rsl-agent-guide-icon dashicons dashicons-book-alt" aria-hidden="true"></span>
		<div class="rsl-agent-guide-copy">
			<h2 id="rsl-agent-guide-title"><?php esc_html_e( 'Get started with Abilities API', 'import-export-by-rockstarlab' ); ?></h2>
			<p><?php esc_html_e( 'Learn how to connect your AI agent and use it for exports, imports, content sync, and backups.', 'import-export-by-rockstarlab' ); ?></p>
		</div>
		<a class="button button-primary rsl-agent-guide-link" href="https://wpimportexport.com/how-to-use-the-wordpress-abilities-api-with-ai-agents-exports-imports-content-sync-and-backups/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Read the guide', 'import-export-by-rockstarlab' ); ?> <span aria-hidden="true">↗</span></a>
	</aside>
	<?php if ( $rsl_ie_enabled ) : ?>
	<div id="rsl-agent-wizard">
	<p class="rsl-agent-enabled-notice" role="status" hidden><?php esc_html_e( 'Abilities API is enabled. Connect your AI agent below.', 'import-export-by-rockstarlab' ); ?></p>
	<div class="rsl-agent-intro">
		<span class="rsl-agent-eyebrow"><?php esc_html_e( 'CONNECT YOUR AI ASSISTANT', 'import-export-by-rockstarlab' ); ?></span>
		<h2 id="rsl-agent-wizard-title" tabindex="-1"><?php esc_html_e( 'Connect your AI agent', 'import-export-by-rockstarlab' ); ?></h2>
		<p><?php esc_html_e( 'Complete these 4 steps to start managing WordPress from your AI assistant.', 'import-export-by-rockstarlab' ); ?></p>
	</div>
		<?php if ( ! $rsl_ie_ready ) : ?>
		<div class="rsl-agent-alert" role="alert">
			<h2><span class="dashicons dashicons-warning" aria-hidden="true"></span> <?php esc_html_e( 'Setup paused: the REST API is not ready', 'import-export-by-rockstarlab' ); ?></h2>
			<p><?php echo esc_html( $data['rest_status']['message'] ); ?></p>
			<?php
			if ( ! $data['abilities_available'] ) :
				?>
				<p><?php esc_html_e( 'The WordPress Abilities API is missing. Update WordPress to a compatible version or install the official Abilities API plugin first.', 'import-export-by-rockstarlab' ); ?></p><?php endif; ?>
			<p><?php esc_html_e( 'Installation and activation are locked until this check passes. A failed local request can also mean a certificate or loopback problem; it does not always mean REST was disabled.', 'import-export-by-rockstarlab' ); ?></p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-agent-connection' ) ); ?>"><?php esc_html_e( 'Check again', 'import-export-by-rockstarlab' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>"><?php esc_html_e( 'Open Site Health', 'import-export-by-rockstarlab' ); ?></a>
		</div>
	<?php endif; ?>
	<ol class="rsl-agent-steps">
		<li class="rsl-agent-step">
			<details class="rsl-agent-step-toggle" <?php echo $rsl_ie_ready ? '' : 'open'; ?>>
			<summary class="rsl-agent-step-heading"><span class="rsl-agent-number" aria-hidden="true">1</span><h2><?php esc_html_e( 'Check your WordPress site', 'import-export-by-rockstarlab' ); ?></h2><span class="rsl-agent-status <?php echo $rsl_ie_ready ? 'is-ready' : 'is-blocked'; ?>"><?php echo esc_html( $rsl_ie_ready ? __( 'Ready', 'import-export-by-rockstarlab' ) : __( 'Needs attention', 'import-export-by-rockstarlab' ) ); ?></span></summary>
			<p><?php echo esc_html( $rsl_ie_ready ? $data['rest_status']['message'] : __( 'Resolve the warning above, then check again to unlock setup.', 'import-export-by-rockstarlab' ) ); ?></p>
			<p><?php esc_html_e( 'Available plugin actions:', 'import-export-by-rockstarlab' ); ?> <strong><?php echo esc_html( $data['ability_count'] ); ?></strong></p>
			</details>
		</li>
		<li class="rsl-agent-step">
			<details class="rsl-agent-step-toggle" <?php echo $rsl_ie_ready && ! $rsl_ie_connected ? 'open' : ''; ?>>
			<summary class="rsl-agent-step-heading"><span class="rsl-agent-number" aria-hidden="true">2</span><h2><?php esc_html_e( 'Enable MCP Adapter', 'import-export-by-rockstarlab' ); ?></h2><span class="rsl-agent-status <?php echo $rsl_ie_connected ? 'is-ready' : ''; ?>"><?php echo esc_html( $rsl_ie_connected ? __( 'Ready', 'import-export-by-rockstarlab' ) : ( $rsl_ie_ready ? __( 'Action needed', 'import-export-by-rockstarlab' ) : __( 'Locked', 'import-export-by-rockstarlab' ) ) ); ?></span></summary>
			<p><?php esc_html_e( 'The official WordPress MCP Adapter connects your AI assistant to this plugin’s actions.', 'import-export-by-rockstarlab' ); ?></p>
			<p><a href="https://wordpress.org/plugins/mcp-adapter/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'MCP Adapter on WordPress.org', 'import-export-by-rockstarlab' ); ?></a></p>
			<?php if ( ! empty( $data['servers'] ) ) : ?>
				<p><?php esc_html_e( 'An MCP server is already registered. You do not need to install another adapter. Once the site check passes, continue to step 3.', 'import-export-by-rockstarlab' ); ?></p>
			<?php elseif ( $data['adapter_active'] ) : ?>
				<p><?php esc_html_e( 'MCP Adapter is active, but no compatible HTTP server is registered. Check that its default server is enabled and that another plugin is not loading an incompatible adapter version.', 'import-export-by-rockstarlab' ); ?></p>
				<a href="https://github.com/WordPress/mcp-adapter" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open official adapter setup instructions', 'import-export-by-rockstarlab' ); ?></a>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-agent-install>
					<input type="hidden" name="action" value="rsl_ie_setup_adapter">
					<?php wp_nonce_field( 'rsl_ie_setup_adapter' ); ?>
					<?php $rsl_ie_can_setup = current_user_can( 'activate_plugins' ) && ( $data['adapter_plugin'] ? current_user_can( 'activate_plugin', $data['adapter_plugin'] ) : current_user_can( 'install_plugins' ) ); ?>
					<button class="button button-primary button-hero" type="submit" <?php disabled( ! $rsl_ie_ready || ! $rsl_ie_can_setup ); ?> data-working-label="<?php esc_attr_e( 'Setting up MCP Adapter…', 'import-export-by-rockstarlab' ); ?>"><?php echo esc_html( $data['adapter_plugin'] ? __( 'Activate MCP Adapter', 'import-export-by-rockstarlab' ) : __( 'Download and activate MCP Adapter', 'import-export-by-rockstarlab' ) ); ?></button>
					<?php
					if ( ! $rsl_ie_can_setup ) :
						?>
						<p><?php esc_html_e( 'Ask your site or network administrator to install and activate MCP Adapter.', 'import-export-by-rockstarlab' ); ?></p><?php endif; ?>
				</form>
			<?php endif; ?>
			<?php
			if ( $data['adapter_available'] ) :
				?>
				<p class="description"><?php esc_html_e( 'Loaded adapter version:', 'import-export-by-rockstarlab' ); ?> <?php echo esc_html( $data['adapter_version'] ); ?></p><?php endif; ?>
			</details>
		</li>
		<li class="rsl-agent-step">
			<details class="rsl-agent-step-toggle" <?php echo $rsl_ie_connected ? 'open' : ''; ?>>
			<summary class="rsl-agent-step-heading"><span class="rsl-agent-number" aria-hidden="true">3</span><h2><?php esc_html_e( 'Create a password for your agent', 'import-export-by-rockstarlab' ); ?></h2><span class="rsl-agent-status"><?php echo esc_html( $rsl_ie_connected ? __( 'In your WordPress profile', 'import-export-by-rockstarlab' ) : __( 'Locked', 'import-export-by-rockstarlab' ) ); ?></span></summary>
			<p><?php esc_html_e( 'Open your profile, find Application Passwords, enter a name such as “My AI agent”, and click Add New Application Password. Copy the generated password immediately — WordPress only shows it once.', 'import-export-by-rockstarlab' ); ?></p>
			<?php if ( $rsl_ie_credentials ) : ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'profile.php#application-passwords-section' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open profile to create a password ↗', 'import-export-by-rockstarlab' ); ?></a>
			<?php else : ?>
				<button class="button" disabled><?php esc_html_e( 'Create an Application Password', 'import-export-by-rockstarlab' ); ?></button>
			<?php endif; ?>
			<?php
			if ( ! $data['application_passwords_available'] ) :
				?>
				<p class="rsl-agent-inline-warning"><?php esc_html_e( 'Application Passwords are unavailable for this account or site. Enable HTTPS (or a local development environment) and check authentication restrictions before continuing.', 'import-export-by-rockstarlab' ); ?></p><?php endif; ?>
			<p class="description"><?php esc_html_e( 'Use an account with manage_options permission. Paste this password into the settings generator in step 4. Your usual WordPress password and Content Sync keys are not MCP credentials.', 'import-export-by-rockstarlab' ); ?></p>
			</details>
		</li>
		<li class="rsl-agent-step">
			<div class="rsl-agent-step-heading"><span class="rsl-agent-number" aria-hidden="true">4</span><h2><?php esc_html_e( 'Add this site to your AI agent', 'import-export-by-rockstarlab' ); ?></h2><span class="rsl-agent-status"><?php echo esc_html( $rsl_ie_credentials ? __( 'In your AI app', 'import-export-by-rockstarlab' ) : __( 'Locked', 'import-export-by-rockstarlab' ) ); ?></span></div>
			<?php if ( $rsl_ie_credentials ) : ?>
				<?php require __DIR__ . '/partials/agent-client-settings.php'; ?>
				<h3><?php esc_html_e( 'Try your first request', 'import-export-by-rockstarlab' ); ?></h3>
				<p><?php esc_html_e( 'After saving the connection, paste this into your agent’s chat. It only reads the available actions and does not change your site.', 'import-export-by-rockstarlab' ); ?></p>
				<textarea id="rsl-agent-prompt" rows="4" readonly><?php echo esc_textarea( __( 'Connect to my WordPress MCP server. Discover its abilities, get the input schema for rsl-ie/get-capabilities, then execute it with empty parameters {}. Summarize what the Content & Media Sync, Import, Export plugin can do. Do not modify any content or settings.', 'import-export-by-rockstarlab' ) ); ?></textarea>
				<button type="button" class="button" data-copied-label="<?php esc_attr_e( 'Copied!', 'import-export-by-rockstarlab' ); ?>" data-agent-copy="rsl-agent-prompt"><?php esc_html_e( 'Copy first request', 'import-export-by-rockstarlab' ); ?></button>
				<p class="description"><?php esc_html_e( 'A successful reply lists the plugin’s available actions. This page checks site setup; the first request verifies the connection from your agent.', 'import-export-by-rockstarlab' ); ?></p>
			<?php else : ?>
				<div class="rsl-agent-locked"><?php esc_html_e( 'Complete the site checks, enable MCP Adapter and make Application Passwords available to unlock your connection details.', 'import-export-by-rockstarlab' ); ?></div>
			<?php endif; ?>
		</li>
	</ol>
	<p id="rsl-agent-copy-status" class="screen-reader-text" role="status" data-failure="<?php esc_attr_e( 'Copy unavailable. Select the text and copy it manually.', 'import-export-by-rockstarlab' ); ?>"></p>
	<details class="rsl-agent-advanced"><summary><?php esc_html_e( 'Advanced: use the Abilities REST API without MCP', 'import-export-by-rockstarlab' ); ?></summary><p><?php esc_html_e( 'For custom integrations, the Abilities REST API works without MCP Adapter. Authenticate with your WordPress username and Application Password. Start with GET on this URL to list abilities, then inspect an ability’s schema before running it.', 'import-export-by-rockstarlab' ); ?></p><code><?php echo esc_html( $data['rest_url'] ); ?></code><p><?php esc_html_e( 'MCP clients discover actions through the adapter’s discovery, information and execution tools. PRO actions require an active licensed PRO add-on; all executions check WordPress permissions.', 'import-export-by-rockstarlab' ); ?></p></details>
	</div>
	<?php endif; ?>
	</section>
	<section class="rsl-agent-integrations" aria-labelledby="rsl-agent-integrations-title">
		<h2 id="rsl-agent-integrations-title"><?php esc_html_e( 'Additional integrations', 'import-export-by-rockstarlab' ); ?></h2>
	<form class="rsl-agent-access rsl-agent-backup-access <?php echo $rsl_ie_updraft_enabled ? 'is-enabled' : ''; ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-agent-access>
		<input type="hidden" name="action" value="rsl_ie_toggle_updraftplus_abilities">
		<?php wp_nonce_field( 'rsl_ie_toggle_updraftplus_abilities' ); ?>
		<div class="rsl-agent-access-heading">
			<span class="rsl-agent-access-icon dashicons dashicons-backup" aria-hidden="true"></span>
			<div><span class="rsl-agent-eyebrow"><?php esc_html_e( 'BACKUP & RECOVERY', 'import-export-by-rockstarlab' ); ?></span><h2><label for="rsl-agent-updraft-toggle"><?php esc_html_e( 'Enable Abilities API for UpdraftPlus', 'import-export-by-rockstarlab' ); ?></label></h2></div>
			<div class="rsl-agent-switch-wrap">
				<span class="rsl-agent-access-state"><?php echo esc_html( $rsl_ie_updraft_enabled ? __( 'Enabled', 'import-export-by-rockstarlab' ) : __( 'Disabled', 'import-export-by-rockstarlab' ) ); ?></span>
				<label class="rsl-agent-switch"><input id="rsl-agent-updraft-toggle" type="checkbox" role="switch" name="updraftplus_abilities_enabled" value="1" <?php checked( $rsl_ie_updraft_enabled ); ?> <?php disabled( ! $rsl_ie_enabled || ! $rsl_ie_updraft_available ); ?> aria-label="<?php esc_attr_e( 'Enable Abilities API for UpdraftPlus', 'import-export-by-rockstarlab' ); ?>" aria-describedby="rsl-agent-updraft-description"><span class="rsl-agent-switch-track" aria-hidden="true"></span></label>
			</div>
		</div>
		<div id="rsl-agent-updraft-description">
			<p><?php esc_html_e( 'Allow your AI agent to create a backup before importing or syncing data, check backup progress, and restore a selected backup when you need to roll back changes. UpdraftPlus will handle the backups; this separate setting controls your agent’s access to those operations.', 'import-export-by-rockstarlab' ); ?></p>
			<p class="description"><?php esc_html_e( 'Your agent can list backups, create backups and monitor progress. Restoring requires a checked plan and your explicit confirmation. Agent restore currently supports UpdraftPlus 1.26.8 or later and standard local backups from this site; older versions and remote-only, encrypted, incremental or multisite restores use the UpdraftPlus dashboard.', 'import-export-by-rockstarlab' ); ?></p>
			<?php
			if ( ! $rsl_ie_enabled ) :
				?>
				<p class="rsl-agent-dependency"><?php esc_html_e( 'Enable Abilities API above to unlock this setting.', 'import-export-by-rockstarlab' ); ?></p><?php endif; ?>
			<?php
			if ( ! $rsl_ie_updraft_available ) :
				?>
				<p class="rsl-agent-dependency"><?php esc_html_e( 'Install and activate UpdraftPlus to enable backup access.', 'import-export-by-rockstarlab' ); ?></p><?php endif; ?>
			<a href="https://teamupdraft.com/ref/3832/" target="_blank" rel="noopener noreferrer sponsored"><?php esc_html_e( 'Get UpdraftPlus ↗', 'import-export-by-rockstarlab' ); ?></a>
		</div>
		<p class="rsl-agent-save-status" role="status" data-saving="<?php esc_attr_e( 'Saving your preference…', 'import-export-by-rockstarlab' ); ?>"></p>
		<noscript><button type="submit" class="button button-primary" <?php disabled( ! $rsl_ie_enabled || ! $rsl_ie_updraft_available ); ?>><?php esc_html_e( 'Save preference', 'import-export-by-rockstarlab' ); ?></button></noscript>
	</form>
	</section>
</div>
