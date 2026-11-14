<?php
/**
 * Browser-only MCP client settings generator.
 *
 * @package RockStarLab\ImportExport\View
 */

// phpcs:disable WordPress.WP.CapitalPDangit.MisspelledInText -- wordpress is the literal MCP server identifier in generated settings.
defined( 'ABSPATH' ) || exit;
?>
<p><?php esc_html_e( 'Choose your AI app and connection method, then paste the Application Password from step 3. Generate and copy the result, then follow the instructions below.', 'import-export-by-rockstarlab' ); ?></p>
<form data-agent-generator autocomplete="off">
	<div class="rsl-agent-generator-grid">
		<label><?php esc_html_e( 'AI app', 'import-export-by-rockstarlab' ); ?><select data-client><option value="codex">Codex</option><option value="cursor">Cursor</option><option value="claude">Claude Code</option><option value="claude-desktop">Claude Desktop</option><option value="copilot">GitHub Copilot (VS Code)</option><option value="gemini">Gemini CLI</option></select></label>
		<label><?php esc_html_e( 'Connection method', 'import-export-by-rockstarlab' ); ?><select data-method><option value="command"><?php esc_html_e( 'One command', 'import-export-by-rockstarlab' ); ?></option><option value="config" selected><?php esc_html_e( 'Custom config', 'import-export-by-rockstarlab' ); ?></option></select></label>
		<label data-shell-field hidden><?php esc_html_e( 'Terminal', 'import-export-by-rockstarlab' ); ?><select data-shell><option value="posix">Mac / Linux (Bash or Zsh)</option><option value="powershell">Windows (PowerShell 7.3+)</option></select></label>
		<label><?php esc_html_e( 'MCP server', 'import-export-by-rockstarlab' ); ?><select data-endpoint>
		<?php
		foreach ( $data['servers'] as $rsl_ie_server ) :
			?>
			<option value="<?php echo esc_attr( $rsl_ie_server['url'] ); ?>"><?php echo esc_html( $rsl_ie_server['id'] ); ?></option><?php endforeach; ?></select></label>
		<label><?php esc_html_e( 'WordPress username', 'import-export-by-rockstarlab' ); ?><input type="text" data-username value="<?php echo esc_attr( $data['username'] ); ?>" required autocomplete="off"></label>
		<label><?php esc_html_e( 'Application Password', 'import-export-by-rockstarlab' ); ?><input data-password type="password" required autocomplete="new-password" spellcheck="false"></label>
	</div>
	<p class="description"><?php esc_html_e( 'Generated only in this browser; the password is not sent or saved by this form. The copied settings contain your credential. Keep them private. Use HTTPS on public sites.', 'import-export-by-rockstarlab' ); ?></p>
	<div data-command-help hidden>
		<p class="rsl-agent-inline-warning"><?php esc_html_e( 'The command contains your Application Password in an encoded, reversible form. It may remain in terminal history or be visible to other local processes. Keep it private. Choose Custom config if you do not want credentials in terminal history.', 'import-export-by-rockstarlab' ); ?></p>
		<p data-command-client="codex"><?php esc_html_e( 'Requires Python 3.11 or newer. This command adds WordPress to your personal Codex config (using CODEX_HOME if set). It keeps existing settings and stops if WordPress already exists. Close Codex before running it; use Custom config to update an existing connection.', 'import-export-by-rockstarlab' ); ?></p>
		<p data-command-client="cursor" hidden><?php esc_html_e( 'Requires Python 3. This command adds WordPress to ~/.cursor/mcp.json and keeps other settings. Close Cursor before running it. If WordPress already exists, or your file uses comments or trailing commas, use Custom config instead.', 'import-export-by-rockstarlab' ); ?></p>
		<p data-command-client="claude" hidden><?php esc_html_e( 'Requires the claude command in your terminal. Adds a user-level WordPress server with an HTTP authorization header.', 'import-export-by-rockstarlab' ); ?></p>
		<p data-command-client="claude-desktop" hidden><?php esc_html_e( 'Requires Python 3. This command adds WordPress to claude_desktop_config.json and keeps your other servers. Quit Claude Desktop before running it. If WordPress already exists, or the file is not valid JSON, use Custom config instead. Claude Desktop also needs Node.js 22 or newer for the npx bridge.', 'import-export-by-rockstarlab' ); ?></p>
		<p data-command-client="gemini" hidden><?php esc_html_e( 'Requires the gemini command in your terminal. Adds a user-level WordPress server with an HTTP authorization header.', 'import-export-by-rockstarlab' ); ?></p>
		<p data-command-client="copilot" hidden><?php esc_html_e( 'Requires the code command in your terminal. Adds WordPress to your VS Code user profile. On macOS, install the code shell command from the VS Code Command Palette first.', 'import-export-by-rockstarlab' ); ?></p>
	</div>
	<button type="submit" class="button button-primary"><?php esc_html_e( 'Generate connection', 'import-export-by-rockstarlab' ); ?></button>
	<button type="button" class="button" data-clear><?php esc_html_e( 'Clear password and result', 'import-export-by-rockstarlab' ); ?></button>
	<p data-generator-status role="status" data-invalid="<?php esc_attr_e( 'Enter your WordPress username (without a colon) and Application Password.', 'import-export-by-rockstarlab' ); ?>" data-ready="<?php esc_attr_e( 'Connection details generated. Follow the instructions below. This does not test the connection.', 'import-export-by-rockstarlab' ); ?>"></p>
	<div data-generated hidden>
		<label for="rsl-agent-settings"><strong><?php esc_html_e( 'Your connection command or config', 'import-export-by-rockstarlab' ); ?></strong></label>
		<textarea id="rsl-agent-settings" rows="8" readonly spellcheck="false"></textarea>
		<button type="button" class="button button-primary" data-agent-copy="rsl-agent-settings" data-copied-label="<?php esc_attr_e( 'Copied!', 'import-export-by-rockstarlab' ); ?>"><?php esc_html_e( 'Copy result', 'import-export-by-rockstarlab' ); ?></button>
		<div data-command-instructions hidden><ol>
			<li><?php esc_html_e( 'Copy the entire command and run it in the selected terminal, outside an AI conversation.', 'import-export-by-rockstarlab' ); ?></li>
			<li><?php esc_html_e( 'After the command succeeds, open or restart your AI app. Check WordPress in its MCP settings (/mcp in Claude Code or Gemini CLI; MCP: List Servers in VS Code), then send the first request below. Review any trust prompts.', 'import-export-by-rockstarlab' ); ?></li>
		</ol></div>
		<div data-instructions="codex" hidden><h3><?php esc_html_e( 'Finish in Codex', 'import-export-by-rockstarlab' ); ?></h3><ol>
			<li><?php esc_html_e( 'Open your personal config.toml: on Mac/Linux, ~/.codex/config.toml; on Windows, %USERPROFILE%\\.codex\\config.toml. Create the file as plain text if it does not exist.', 'import-export-by-rockstarlab' ); ?></li>
			<li><?php esc_html_e( 'Paste the generated block at the end. If a WordPress server already exists, replace only its section. Keep your other settings.', 'import-export-by-rockstarlab' ); ?></li>
			<li><?php esc_html_e( 'Save, restart Codex, and check Settings → MCP servers (or /mcp in the CLI). Send the first request below in a new conversation.', 'import-export-by-rockstarlab' ); ?></li>
		</ol></div>
		<div data-instructions="cursor" hidden><h3><?php esc_html_e( 'Finish in Cursor', 'import-export-by-rockstarlab' ); ?></h3><ol>
			<li><?php esc_html_e( 'Open your personal mcp.json: on Mac/Linux, ~/.cursor/mcp.json; on Windows, %USERPROFILE%\\.cursor\\mcp.json. Create the file as plain text if it does not exist.', 'import-export-by-rockstarlab' ); ?></li>
			<li><?php esc_html_e( 'For a new file, paste the complete settings. If you have other MCP servers, add only the WordPress entry inside the existing mcpServers object, separating entries with a comma. Do not overwrite other servers.', 'import-export-by-rockstarlab' ); ?></li>
			<li><?php esc_html_e( 'Save, restart Cursor, and find WordPress in its MCP settings. Open an Agent conversation and send the first request below.', 'import-export-by-rockstarlab' ); ?></li>
		</ol></div>
		<div data-instructions="claude" hidden><h3><?php esc_html_e( 'Finish in Claude Code', 'import-export-by-rockstarlab' ); ?></h3><ol>
			<li><?php esc_html_e( 'Save the settings as wordpress-mcp.json in your home folder using a plain-text editor.', 'import-export-by-rockstarlab' ); ?></li>
			<li><?php esc_html_e( 'In your terminal, outside a Claude conversation, run the command for your system:', 'import-export-by-rockstarlab' ); ?><p>Mac / Linux</p><pre>claude mcp add-json --scope user WordPress "$(cat "$HOME/wordpress-mcp.json")"</pre><p>Windows PowerShell</p><pre>claude mcp add-json --scope user WordPress (Get-Content -Raw "$env:USERPROFILE\wordpress-mcp.json")</pre></li>
			<li><?php esc_html_e( 'Restart Claude Code and enter /mcp to check WordPress. Send the first request below. After importing the settings, delete the temporary wordpress-mcp.json file.', 'import-export-by-rockstarlab' ); ?></li>
		</ol></div>
		<div data-instructions="claude-desktop" hidden><h3><?php esc_html_e( 'Finish in Claude Desktop', 'import-export-by-rockstarlab' ); ?></h3><ol>
			<li><?php esc_html_e( 'Install Node.js 22 or newer if you do not have it. Claude Desktop runs the npx bridge from Automattic, which forwards requests to this site.', 'import-export-by-rockstarlab' ); ?></li>
			<li><?php esc_html_e( 'In Claude Desktop, open Settings → Developer and click Edit Config. This opens claude_desktop_config.json (Mac: ~/Library/Application Support/Claude; Windows: %APPDATA%\\Claude).', 'import-export-by-rockstarlab' ); ?></li>
			<li><?php esc_html_e( 'For an empty file, paste the complete settings. Otherwise, add only the wordpress entry inside the existing mcpServers object, separating entries with a comma. Do not overwrite other servers.', 'import-export-by-rockstarlab' ); ?></li>
			<li><?php esc_html_e( 'Save, quit Claude Desktop completely and open it again. Send the first request below in a new chat and allow the WordPress tools when Claude asks.', 'import-export-by-rockstarlab' ); ?></li>
		</ol></div>
		<div data-instructions="copilot" hidden><h3><?php esc_html_e( 'Finish in GitHub Copilot (VS Code)', 'import-export-by-rockstarlab' ); ?></h3><ol>
			<li><?php esc_html_e( 'In VS Code, open the Command Palette (Command+Shift+P on Mac, Ctrl+Shift+P on Windows/Linux). Run MCP: Open User Configuration to open your personal mcp.json.', 'import-export-by-rockstarlab' ); ?></li>
			<li><?php esc_html_e( 'For an empty file, paste the complete settings. Otherwise, merge the wordpress entry into the existing servers object, keeping other servers and settings. Save the file.', 'import-export-by-rockstarlab' ); ?></li>
			<li><?php esc_html_e( 'Run MCP: List Servers from the Command Palette, select wordpress and start it. Review the trust prompt. Open Copilot Chat in Agent mode and send the first request below.', 'import-export-by-rockstarlab' ); ?></li>
		</ol><p><a href="https://code.visualstudio.com/docs/agent-customization/mcp-servers" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'VS Code MCP setup documentation', 'import-export-by-rockstarlab' ); ?></a></p></div>
		<div data-instructions="gemini" hidden><h3><?php esc_html_e( 'Finish in Gemini CLI', 'import-export-by-rockstarlab' ); ?></h3><ol>
			<li><?php esc_html_e( 'Open your personal settings.json: on Mac/Linux, ~/.gemini/settings.json; on Windows, %USERPROFILE%\\.gemini\\settings.json. Create the folder and plain-text file if needed.', 'import-export-by-rockstarlab' ); ?></li>
			<li><?php esc_html_e( 'For an empty file, paste the complete settings. Otherwise, merge the wordpress entry into the existing mcpServers object, preserving other settings. The generated httpUrl selects Streamable HTTP.', 'import-export-by-rockstarlab' ); ?></li>
			<li><?php esc_html_e( 'Save, restart Gemini CLI and enter /mcp to check wordpress. Send the first request below. Review any tool approval prompts before allowing a request.', 'import-export-by-rockstarlab' ); ?></li>
		</ol><p><a href="https://geminicli.com/docs/tools/mcp-server/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Gemini CLI MCP setup documentation', 'import-export-by-rockstarlab' ); ?></a></p></div>

	</div>
</form>
<noscript><p><?php esc_html_e( 'Enable JavaScript to generate client settings.', 'import-export-by-rockstarlab' ); ?></p></noscript>
