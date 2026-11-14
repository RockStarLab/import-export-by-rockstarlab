/* Admin wizard: credentials are processed in memory only, never sent or persisted. */
( function () {
	'use strict';
	const page = document.getElementById( 'rsl-ie-agent-connection' );
	if ( ! page ) return;
	page.querySelectorAll( '[data-agent-access]' ).forEach( function ( accessForm ) {
		const toggle = accessForm.querySelector( '[role="switch"]' );
		toggle.addEventListener( 'change', function () { accessForm.requestSubmit(); } );
		accessForm.addEventListener( 'submit', function () {
			const message = accessForm.querySelector( '.rsl-agent-save-status' );
			message.textContent = message.dataset.saving;
			accessForm.setAttribute( 'aria-busy', 'true' );
			// Keep the checkbox successful in the POST while preventing double toggles.
			toggle.setAttribute( 'aria-disabled', 'true' );
			toggle.addEventListener( 'click', function ( event ) { event.preventDefault(); } );
		} );
	} );
	const url = new URL( window.location.href );
	if ( url.searchParams.get( 'abilities_enabled' ) === '1' ) {
		// Consume the post-save marker so reloads and normal visits do not jump.
		url.searchParams.delete( 'abilities_enabled' );
		window.history.replaceState( window.history.state, '', url.href );
		const heading = page.querySelector( '#rsl-agent-wizard-title' );
		if ( heading ) {
			page.querySelector( '.rsl-agent-enabled-notice' ).hidden = false;
			requestAnimationFrame( function () {
				heading.focus( { preventScroll: true } );
				page.querySelector( '#rsl-agent-wizard' ).scrollIntoView( {
					behavior: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth',
					block: 'start'
				} );
			} );
		}
	}
	const generator = page.querySelector( '[data-agent-generator]' );
	if ( generator ) {
		const client = generator.querySelector( '[data-client]' );
		const method = generator.querySelector( '[data-method]' );
		const shell = generator.querySelector( '[data-shell]' );
		const endpoint = generator.querySelector( '[data-endpoint]' );
		const username = generator.querySelector( '[data-username]' );
		const password = generator.querySelector( '[data-password]' );
		const output = generator.querySelector( '#rsl-agent-settings' );
		const result = generator.querySelector( '[data-generated]' );
		const feedback = generator.querySelector( '[data-generator-status]' );
		const invalidate = function () {
			output.value = '';
			result.hidden = true;
			feedback.textContent = '';
		};
		const updateMethod = function () {
			const command = method.value === 'command';
			generator.querySelector( '[data-shell-field]' ).hidden = ! command;
			generator.querySelector( '[data-command-help]' ).hidden = ! command;
			generator.querySelectorAll( '[data-command-client]' ).forEach( function ( note ) {
				note.hidden = note.dataset.commandClient !== client.value;
			} );
		};
		[ client, method ].forEach( function ( input ) { input.addEventListener( 'change', updateMethod ); } );
		updateMethod();
		const base64 = function ( value ) {
			return btoa( Array.from( new TextEncoder().encode( value ), function ( byte ) { return String.fromCharCode( byte ); } ).join( '' ) );
		};
		// Quote shell arguments, not JSON: URLs may contain quotes, dollars or backticks.
		const quote = function ( value ) {
			return "'" + value.replace( /'/g, shell.value === 'powershell' ? "''" : "'\"'\"'" ) + "'";
		};
		const makeCommand = function ( entry, config ) {
			const windows = shell.value === 'powershell';
			if ( client.value === 'claude' || client.value === 'gemini' ) {
				// Name and URL go before --header: the header option accepts several values and would swallow them.
				return client.value + ' mcp add --transport http --scope user wordpress ' + quote( entry.url ) + ' --header ' + quote( 'Authorization: ' + entry.headers.Authorization );
			}
			if ( client.value === 'copilot' ) {
				const json = JSON.stringify( Object.assign( { name: 'wordpress', type: 'http' }, entry ) );
				// code.cmd uses Windows legacy native argument passing, including in PowerShell 7.
				const argument = windows ? json.replace( /(\\*)"/g, function ( match, slashes ) { return slashes + slashes + '\\"'; } ) : json;
				return ( windows ? "& { $PSNativeCommandArgumentPassing = 'Legacy'; " : '' ) + 'code --add-mcp ' + quote( argument ) + ( windows ? ' }' : '' );
			}
			// These clients have no native add command accepting our Basic auth header.
			// Parse before writing; never overwrite an existing WordPress connection.
			const codex = client.value === 'codex';
			const desktop = client.value === 'claude-desktop';
			const payload = base64( codex ? config : JSON.stringify( entry ) );
			let target = 'Path.home()/".cursor"/"mcp.json"';
			if ( codex ) {
				target = 'Path(os.environ.get("CODEX_HOME") or Path.home()/".codex")/"config.toml"';
			} else if ( desktop ) {
				// Claude Desktop's config folder per system.
				target = '(Path(os.environ["APPDATA"])/"Claude" if os.name=="nt" else Path.home()/"Library"/"Application Support"/"Claude" if sys.platform=="darwin" else Path.home()/".config"/"Claude")/"claude_desktop_config.json"';
			}
			const script = [
				'import os,sys,json,base64,tempfile',
				codex ? 'sys.version_info >= (3,11) or sys.exit("Codex One command requires Python 3.11 or newer. Choose Custom config to connect without Python, or run this command with Python 3.11+.")' : '',
				codex ? 'import tomllib' : '',
				'from pathlib import Path',
				'p=' + target,
				'old=p.read_text(encoding="utf-8") if p.exists() else ""',
				'd=' + ( codex ? 'tomllib.loads(old)' : 'json.loads(old) if p.exists() else {}' ),
				'm=' + JSON.stringify( codex ? 'mcp_servers' : 'mcpServers' ),
				'type(d) is dict and type(d.get(m,{})) is dict or sys.exit("Invalid config. Use Custom config.")',
				'"wordpress" not in d.get(m,{}) or sys.exit("WordPress already exists. Use Custom config.")',
				'new=base64.b64decode("' + payload + '").decode("utf-8")',
				codex ? 'new=old+"\\n"+new+"\\n"' : 'd.setdefault(m,{})["wordpress"]=json.loads(new)',
				codex ? 'tomllib.loads(new)' : 'new=json.dumps(d,ensure_ascii=False,indent=2)+"\\n"',
				'p.parent.mkdir(parents=True,exist_ok=True)',
				'fd,tmp=tempfile.mkstemp(dir=p.parent)',
				'f=os.fdopen(fd,"w",encoding="utf-8")',
				'f.write(new)',
				'f.close()',
				'os.replace(tmp,p)',
				'print("WordPress added. Restart your AI app.")'
			].filter( Boolean ).join( '; ' );
			return ( windows ? "& { $PSNativeCommandArgumentPassing = 'Standard'; python" : 'python3' ) + ' -c ' + quote( script ) + ( windows ? ' }' : '' );
		};
		[ client, method, shell, endpoint, username, password ].forEach( function ( input ) {
			input.addEventListener( 'input', invalidate );
			input.addEventListener( 'change', invalidate );
		} );
		generator.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			invalidate();
			const name = username.value.trim();
			const secret = password.value.replace( /\s/g, '' );
			if ( ! name || name.includes( ':' ) || ! secret ) {
				feedback.textContent = feedback.dataset.invalid;
				return;
			}
			const bytes = new TextEncoder().encode( name + ':' + secret );
			const auth = 'Basic ' + btoa( Array.from( bytes, function ( byte ) { return String.fromCharCode( byte ); } ).join( '' ) );
			let entry = { url: endpoint.value, headers: { Authorization: auth } };
			if ( client.value === 'claude-desktop' ) {
				// Claude Desktop starts local (stdio) servers only; Automattic's bridge forwards to the site.
				entry = {
					command: 'npx',
					args: [ '-y', '@automattic/mcp-wordpress-remote' ],
					env: { WP_API_URL: endpoint.value, WP_API_USERNAME: name, WP_API_PASSWORD: secret }
				};
				output.value = JSON.stringify( { mcpServers: { wordpress: entry } }, null, 2 );
			} else if ( client.value === 'codex' ) {
				output.value = '[mcp_servers.wordpress]\nurl = ' + JSON.stringify( endpoint.value ) + '\nhttp_headers = { Authorization = ' + JSON.stringify( auth ) + ' }';
			} else if ( client.value === 'claude' ) {
				entry.type = 'http';
				output.value = JSON.stringify( entry, null, 2 );
			} else if ( client.value === 'copilot' ) {
				entry.type = 'http';
				output.value = JSON.stringify( { servers: { wordpress: entry } }, null, 2 );
			} else if ( client.value === 'gemini' ) {
				output.value = JSON.stringify( { mcpServers: { wordpress: { httpUrl: endpoint.value, headers: entry.headers } } }, null, 2 );
			} else {
				output.value = JSON.stringify( { mcpServers: { wordpress: entry } }, null, 2 );
			}
			if ( method.value === 'command' ) output.value = makeCommand( entry, output.value );
			generator.querySelector( '[data-command-instructions]' ).hidden = method.value !== 'command';
			generator.querySelectorAll( '[data-instructions]' ).forEach( function ( instruction ) {
				instruction.hidden = method.value !== 'config' || instruction.dataset.instructions !== client.value;
			} );
			result.hidden = false;
			feedback.textContent = feedback.dataset.ready;
		} );
		const clear = function () { password.value = ''; invalidate(); };
		generator.querySelector( '[data-clear]' ).addEventListener( 'click', clear );
		window.addEventListener( 'pagehide', clear );
	}
	const status = document.getElementById( 'rsl-agent-copy-status' );
	page.querySelectorAll( '[data-agent-copy]' ).forEach( function ( button ) {
		const originalLabel = button.textContent;
		let resetTimer;
		button.addEventListener( 'click', async function () {
			const input = document.getElementById( button.dataset.agentCopy );
			let copied = false;
			try {
				await navigator.clipboard.writeText( input.value );
				copied = true;
			} catch ( error ) {
				input.focus();
				input.select();
				try { copied = document.execCommand( 'copy' ); } catch ( ignored ) {}
			}
			status.classList.toggle( 'screen-reader-text', copied );
			status.textContent = copied ? '' : status.dataset.failure;
			if ( copied ) {
				button.focus();
				if ( button.dataset.copiedLabel ) {
					clearTimeout( resetTimer );
					button.textContent = button.dataset.copiedLabel;
					button.classList.add( 'is-copied' );
					resetTimer = setTimeout( function () {
						button.textContent = originalLabel;
						button.classList.remove( 'is-copied' );
					}, 2000 );
				}
			} else {
				clearTimeout( resetTimer );
				button.textContent = originalLabel;
				button.classList.remove( 'is-copied' );
			}
		} );
	} );
	const form = page.querySelector( '[data-agent-install]' );
	if ( form ) form.addEventListener( 'submit', function () {
		const button = form.querySelector( 'button' );
		button.disabled = true;
		button.textContent = button.dataset.workingLabel;
	} );
} )();
