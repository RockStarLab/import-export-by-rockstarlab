/**
 * Manual E2E (Playwright): Functions page checks on aie.local.
 *
 * Covers:
 * - PRO addon off: direct Functions page is unavailable and menu item is absent.
 * - PRO addon on: Functions UI loads and its table/search/pagination work.
 * - New/edit/test/save/delete custom function.
 * - Snippet library categories/search/preview/customize flow.
 * - AI generator UI: generate when an API key exists, otherwise verify the
 *   disabled state and persistent API-key warning.
 *
 * Usage:
 *   PLAYWRIGHT_BROWSERS_PATH=./e2e/.playwright-browsers node scripts/aie-functions-check.js
 *
 * Env (defaults read from .env.e2e if present):
 *   AIE_SOURCE_URL, AIE_SOURCE_ADMIN_USER, AIE_SOURCE_ADMIN_PASSWORD
 *   AIE_HEADLESS=true|false (defaults to true)
 */

const fs = require( 'fs' );
const path = require( 'path' );

const localBrowsersPath = path.resolve(
	process.cwd(),
	'e2e/.playwright-browsers'
);
if (
	! process.env.PLAYWRIGHT_BROWSERS_PATH &&
	fs.existsSync( localBrowsersPath )
) {
	process.env.PLAYWRIGHT_BROWSERS_PATH = localBrowsersPath;
}

const { chromium } = require( 'playwright' );

const PRO_PLUGIN_FILE =
	'import-export-pro-by-rockstarlab/import-export-pro-by-rockstarlab.php';
const FREE_PLUGIN_FILE =
	'import-export-by-rockstarlab/import-export-by-rockstarlab.php';

function parseDotEnv( contents ) {
	const env = {};
	for ( const line of contents.split( /\r?\n/ ) ) {
		const trimmed = line.trim();
		if ( ! trimmed || trimmed.startsWith( '#' ) ) continue;
		const idx = trimmed.indexOf( '=' );
		if ( idx === -1 ) continue;
		const key = trimmed.slice( 0, idx ).trim();
		const value = trimmed.slice( idx + 1 ).trim();
		env[ key ] = value;
	}
	return env;
}

function loadEnv() {
	const envPath = path.resolve( process.cwd(), '.env.e2e' );
	let fileEnv = {};
	if ( fs.existsSync( envPath ) ) {
		fileEnv = parseDotEnv( fs.readFileSync( envPath, 'utf8' ) );
	}
	const get = ( key, fallback ) =>
		process.env[ key ] ?? fileEnv[ key ] ?? fallback;
	const headlessRaw = String( get( 'AIE_HEADLESS', 'true' ) ).toLowerCase();

	return {
		headless:
			headlessRaw === '1' ||
			headlessRaw === 'true' ||
			headlessRaw === 'yes',
		site: {
			baseUrl: get( 'AIE_SOURCE_URL', 'http://aie.local' ),
			username: get( 'AIE_SOURCE_ADMIN_USER', 'admin' ),
			password: get( 'AIE_SOURCE_ADMIN_PASSWORD', 'admin' ),
		},
		runId: new Date()
			.toISOString()
			.replace( /[-:.TZ]/g, '' )
			.slice( 0, 14 ),
	};
}

function artifactPath( fileName ) {
	const dir = path.resolve(
		process.cwd(),
		'e2e/artifacts/functions-deep-check'
	);
	fs.mkdirSync( dir, { recursive: true } );
	return path.join( dir, fileName );
}

function assert( condition, message ) {
	if ( ! condition ) {
		throw new Error( message );
	}
}

function logStep( message ) {
	console.log( `[functions-check] ${ message }` );
}

async function ensureLoggedIn( page, site ) {
	await page.goto( `${ site.baseUrl }/wp-admin/`, {
		waitUntil: 'domcontentloaded',
	} );
	if ( ! ( await page.locator( 'form#loginform' ).count() ) ) return;

	await page.fill( '#user_login', site.username );
	await page.fill( '#user_pass', site.password );
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
		page.click( '#wp-submit' ),
	] );
	await page.waitForSelector( '#wpadminbar', { timeout: 30_000 } );
}

async function gotoAdmin( page, site, adminPathWithQuery ) {
	await ensureLoggedIn( page, site );
	const response = await page.goto(
		`${ site.baseUrl }${ adminPathWithQuery }`,
		{
			waitUntil: 'domcontentloaded',
		}
	);
	if ( await page.locator( 'form#loginform' ).count() ) {
		await ensureLoggedIn( page, site );
		return page.goto( `${ site.baseUrl }${ adminPathWithQuery }`, {
			waitUntil: 'domcontentloaded',
		} );
	}
	await assertPageHealthy( page, adminPathWithQuery, response );
	return response;
}

async function assertPageHealthy( page, label, response = null ) {
	if ( response && response.status() >= 500 ) {
		throw new Error( `${ label } returned HTTP ${ response.status() }` );
	}
	const bodyText = await page
		.locator( 'body' )
		.innerText()
		.catch( () => '' );
	if ( ! bodyText || ! bodyText.trim() ) {
		throw new Error( `${ label } rendered a blank page` );
	}
}

async function openPluginsPage( page, site ) {
	await gotoAdmin(
		page,
		site,
		'/wp-admin/plugins.php?plugin_status=all&s=rockstarlab'
	);
	await page.locator( '#the-list' ).waitFor( {
		state: 'visible',
		timeout: 30_000,
	} );
}

async function getPluginRow( page, pluginFile ) {
	const row = page.locator( `tr[data-plugin="${ pluginFile }"]` ).first();
	await row.waitFor( { state: 'attached', timeout: 30_000 } );
	return row;
}

async function isPluginActive( row ) {
	return row.evaluate( ( el ) => el.classList.contains( 'active' ) );
}

async function setPluginActive( page, site, pluginFile, active ) {
	await openPluginsPage( page, site );
	let row = await getPluginRow( page, pluginFile );
	const current = await isPluginActive( row );
	if ( current === active ) return;

	const actionSelector = active
		? '.activate a, a[href*="action=activate"]'
		: '.deactivate a, a[href*="action=deactivate"]';
	const action = row.locator( actionSelector ).first();
	await action.waitFor( { state: 'visible', timeout: 30_000 } );

	const actionNavigation = page
		.waitForNavigation( {
			waitUntil: 'domcontentloaded',
			timeout: 30_000,
		} )
		.catch( () => null );
	await action.click( { noWaitAfter: true } );

	if ( ! active ) {
		await Promise.race( [
			actionNavigation,
			page
				.locator(
					'button:has-text("Skip & Deactivate"), .button:has-text("Skip & Deactivate"), a:has-text("Skip & Deactivate")'
				)
				.first()
				.waitFor( { state: 'visible', timeout: 10_000 } )
				.catch( () => null ),
		] );
		await handleDeactivationFeedbackModal( page );
	} else {
		await actionNavigation;
	}

	await openPluginsPage( page, site );
	row = await getPluginRow( page, pluginFile );
	const nowActive = await isPluginActive( row );
	assert(
		nowActive === active,
		`${ pluginFile } expected active=${ active }, got ${ nowActive }`
	);
}

async function handleDeactivationFeedbackModal( page ) {
	const skipButton = page
		.locator(
			'button:has-text("Skip & Deactivate"), .button:has-text("Skip & Deactivate"), a:has-text("Skip & Deactivate")'
		)
		.first();

	if ( ! ( await skipButton.isVisible().catch( () => false ) ) ) {
		return;
	}

	const skipNavigation = page
		.waitForNavigation( {
			waitUntil: 'domcontentloaded',
			timeout: 60_000,
		} )
		.catch( () => null );
	await skipButton.click( { noWaitAfter: true } );
	await skipNavigation;
}

async function ensureFreePluginActive( page, site ) {
	await setPluginActive( page, site, FREE_PLUGIN_FILE, true );
}

async function verifyFunctionsUnavailableWithoutPro( page, site, summary ) {
	await setPluginActive( page, site, PRO_PLUGIN_FILE, false );
	await gotoAdmin( page, site, '/wp-admin/' );

	const menuCount = await page
		.locator( '#adminmenu a[href*="page=rsl-ie-functions"]' )
		.count();
	assert(
		menuCount === 0,
		'Functions menu item is visible while PRO is off'
	);

	const response = await page.goto(
		`${ site.baseUrl }/wp-admin/admin.php?page=rsl-ie-functions`,
		{ waitUntil: 'domcontentloaded' }
	);
	await assertPageHealthy(
		page,
		'Functions direct URL with PRO off',
		response
	);

	const hasFunctionsUi = await page.locator( '#rsl-ie-functions' ).count();
	const bodyText = await page.locator( 'body' ).innerText();
	assert(
		hasFunctionsUi === 0,
		'Functions UI rendered while PRO addon is deactivated'
	);
	assert(
		/not allowed|do not have permission|cannot load|invalid|sorry/i.test(
			bodyText
		) || ! /Custom Functions/i.test( bodyText ),
		'Direct Functions page did not show a blocked/unavailable state'
	);

	summary.steps.push( {
		name: 'free_unavailable',
		ok: true,
		menuCount,
		pageTitle: await page.title(),
	} );
}

async function verifyFunctionsAvailableWithPro( page, site, summary ) {
	await setPluginActive( page, site, PRO_PLUGIN_FILE, true );
	await gotoAdmin( page, site, '/wp-admin/admin.php?page=rsl-ie-functions' );

	await page
		.locator( '#rsl-ie-functions' )
		.waitFor( { state: 'visible', timeout: 30_000 } );

	const menuCount = await page
		.locator( '#adminmenu a[href*="page=rsl-ie-functions"]' )
		.count();
	assert( menuCount > 0, 'Functions menu item is absent while PRO is on' );

	await waitForFunctionsLoaded( page );
	const tableState = await getTableState( page );
	assert( tableState.rows > 0, 'Functions table did not render any rows' );

	summary.steps.push( {
		name: 'pro_available',
		ok: true,
		menuCount,
		tableState,
	} );
}

async function waitForFunctionsLoaded( page ) {
	await page.waitForFunction(
		() => {
			const tbody = document.querySelector( '#rsl-ie-functions-tbody' );
			if ( ! tbody ) return false;
			if ( tbody.querySelector( '.rsl-ie-loading-row' ) ) return false;
			return tbody.querySelectorAll( 'tr' ).length > 0;
		},
		{ timeout: 60_000 }
	);
}

async function getTableState( page ) {
	return page.evaluate( () => {
		const rows = Array.from(
			document.querySelectorAll( '#rsl-ie-functions-tbody tr' )
		);
		const info =
			document.querySelector( '.rsl-ie-pagination-info' )?.textContent ||
			'';
		const currentPage =
			document.querySelector( '.rsl-ie-current-page' )?.textContent || '';
		const totalPages =
			document.querySelector( '.rsl-ie-total-pages' )?.textContent || '';
		const nextDisabled = !! document.querySelector(
			'.rsl-ie-next-page[disabled]'
		);
		const prevDisabled = !! document.querySelector(
			'.rsl-ie-prev-page[disabled]'
		);
		return {
			rows: rows.length,
			firstRow: rows[ 0 ]?.innerText || '',
			info,
			currentPage,
			totalPages,
			nextDisabled,
			prevDisabled,
		};
	} );
}

async function exerciseSearchAndPagination( page, summary ) {
	const before = await getTableState( page );

	if ( ! before.nextDisabled ) {
		await page.locator( '.rsl-ie-next-page' ).click();
		await waitForFunctionsLoaded( page );
		const next = await getTableState( page );
		assert(
			next.currentPage === '2',
			'Next pagination did not move to page 2'
		);

		await page.locator( '.rsl-ie-prev-page' ).click();
		await waitForFunctionsLoaded( page );
		const prev = await getTableState( page );
		assert(
			prev.currentPage === '1',
			'Previous pagination did not return to page 1'
		);
	}

	await page.fill( '#rsl-ie-filter-search', 'uppercase' );
	await page.waitForTimeout( 700 );
	await waitForFunctionsLoaded( page );
	const searchText = await page
		.locator( '#rsl-ie-functions-tbody' )
		.innerText();
	assert(
		/uppercase|upper/i.test( searchText ),
		'Search did not find uppercase snippets'
	);

	await page.locator( '.rsl-ie-filter-clear' ).click();
	const afterClear = await page.inputValue( '#rsl-ie-filter-search' );
	assert( afterClear === '', 'Clear Filters did not empty the search field' );
	await page.waitForTimeout( 700 );
	await waitForFunctionsLoaded( page );

	summary.steps.push( {
		name: 'search_pagination',
		ok: true,
		before,
		after: await getTableState( page ),
	} );
}

async function openNewFunctionModal( page ) {
	await page.locator( '.rsl-ie-new-function' ).click();
	await page
		.locator( '#rsl-ie-function-editor-modal' )
		.waitFor( { state: 'visible', timeout: 30_000 } );
	await page.locator( '#rsl-ie-function-name' ).waitFor( {
		state: 'visible',
		timeout: 30_000,
	} );
}

async function closeModal( page, modalSelector ) {
	const modal = page.locator( modalSelector );
	if ( ! ( await modal.count() ) ) return;
	if ( ! ( await modal.isVisible().catch( () => false ) ) ) return;
	const close = modal
		.locator( '.rsl-ie-modal-cancel, .rsl-ie-modal-close' )
		.first();
	await close.click();
	await page.waitForFunction(
		( selector ) => {
			const el = document.querySelector( selector );
			return ! el || getComputedStyle( el ).display === 'none';
		},
		modalSelector,
		{ timeout: 15_000 }
	);
}

async function setEditorCode( page, code ) {
	const modalSelector = '#rsl-ie-function-editor-modal';
	const cm = page.locator( `${ modalSelector } .CodeMirror` ).first();
	if ( await cm.count() ) {
		await cm.click();
		await page.keyboard.press(
			process.platform === 'darwin' ? 'Meta+A' : 'Control+A'
		);
		await page.keyboard.insertText( code );
	}

	await page.evaluate( ( value ) => {
		const editor = document.querySelector(
			'#rsl-ie-function-editor-modal .CodeMirror'
		)?.CodeMirror;
		if ( editor ) {
			editor.setValue( value );
		}
		const textarea = document.getElementById( 'rsl-ie-function-code' );
		if ( textarea ) {
			textarea.value = value;
		}
	}, code );
}

async function getEditorCode( page ) {
	return page.evaluate( () => {
		const editor = document.querySelector(
			'#rsl-ie-function-editor-modal .CodeMirror'
		)?.CodeMirror;
		if ( editor ) return editor.getValue();
		return document.getElementById( 'rsl-ie-function-code' )?.value || '';
	} );
}

async function waitForEditorLoaded( page, expectedNamePart ) {
	await page.waitForFunction(
		( namePart ) => {
			const id =
				document.getElementById( 'rsl-ie-function-id' )?.value || '';
			const name =
				document.getElementById( 'rsl-ie-function-name' )?.value || '';
			const editor = document.querySelector(
				'#rsl-ie-function-editor-modal .CodeMirror'
			)?.CodeMirror;
			const code = editor
				? editor.getValue()
				: document.getElementById( 'rsl-ie-function-code' )?.value ||
				  '';

			return (
				id !== '' &&
				name.includes( namePart ) &&
				code.trim() !== '' &&
				! code.trim().match( /^<\?php\s*$/ )
			);
		},
		expectedNamePart,
		{ timeout: 30_000 }
	);
}

async function assertAiUiState( page, summary ) {
	await openNewFunctionModal( page );
	const hasApiKey = await page.evaluate(
		() => !! window.rslIeData?.hasOpenAIApiKey
	);
	const aiButton = page.locator( '.rsl-ie-generate-with-ai' );
	await aiButton.waitFor( { state: 'visible', timeout: 30_000 } );

	if ( ! hasApiKey ) {
		assert(
			await aiButton.isDisabled(),
			'AI generate button is enabled without an OpenAI API key'
		);
		const warningText = await page
			.locator( '#rsl-ie-function-editor-modal .notice-warning' )
			.innerText()
			.catch( () => '' );
		assert(
			/API Key Required|OpenAI API key is not configured/i.test(
				warningText
			),
			'Missing OpenAI API key warning is not visible'
		);

		await closeModal( page, '#rsl-ie-function-editor-modal' );
		await openNewFunctionModal( page );
		const warningAfterReopen = await page
			.locator( '#rsl-ie-function-editor-modal .notice-warning' )
			.innerText()
			.catch( () => '' );
		assert(
			/API Key Required|OpenAI API key is not configured/i.test(
				warningAfterReopen
			),
			'Missing OpenAI API key warning disappeared after reopening the modal'
		);
		await closeModal( page, '#rsl-ie-function-editor-modal' );

		summary.steps.push( {
			name: 'ai_without_key',
			ok: true,
			hasApiKey,
		} );
		return;
	}

	await aiButton.click();
	await page
		.locator( '#rsl-ie-ai-prompt-modal' )
		.waitFor( { state: 'visible', timeout: 30_000 } );

	await page.locator( '.rsl-ie-generate-code' ).click();
	await page
		.locator( '#rsl-ie-ai-prompt-modal .rsl-ie-modal-error' )
		.waitFor( { state: 'visible', timeout: 15_000 } );

	await page.locator( '.rsl-ie-use-example' ).first().click();
	const promptValue = await page.inputValue( '#rsl-ie-ai-prompt' );
	assert(
		promptValue.length > 5,
		'AI example prompt did not fill the textarea'
	);

	await page.locator( '.rsl-ie-generate-code' ).click();
	await Promise.race( [
		page
			.locator( '#rsl-ie-ai-prompt-modal' )
			.waitFor( { state: 'hidden', timeout: 90_000 } ),
		page
			.locator( '#rsl-ie-ai-prompt-modal .rsl-ie-modal-error' )
			.waitFor( { state: 'visible', timeout: 90_000 } ),
	] );

	const aiModalVisible = await page
		.locator( '#rsl-ie-ai-prompt-modal' )
		.isVisible()
		.catch( () => false );
	const generatedCode = await getEditorCode( page );
	const aiError = aiModalVisible
		? await page
				.locator( '#rsl-ie-ai-prompt-modal .rsl-ie-modal-error' )
				.innerText()
				.catch( () => '' )
		: '';

	assert(
		! aiModalVisible || aiError.length > 0,
		'AI generation neither completed nor displayed an error'
	);
	if ( ! aiModalVisible ) {
		assert(
			/return/i.test( generatedCode ),
			'AI generation did not insert code'
		);
	}

	await closeModal( page, '#rsl-ie-ai-prompt-modal' );
	await closeModal( page, '#rsl-ie-function-editor-modal' );

	summary.steps.push( {
		name: 'ai_with_key',
		ok: true,
		hasApiKey,
		completed: ! aiModalVisible,
		error: aiError,
	} );
}

async function testCurrentFunctionCode( page, input, expectedOutput ) {
	await page.fill( '#rsl-ie-test-value', input );
	await page.locator( '.rsl-ie-test-function' ).click();
	const result = page
		.locator( '.rsl-ie-test-results' )
		.waitFor( { state: 'visible', timeout: 30_000 } )
		.then( () => 'result' )
		.catch( () => null );
	const modalError = page
		.locator( '#rsl-ie-function-editor-modal .rsl-ie-modal-error' )
		.waitFor( { state: 'visible', timeout: 30_000 } )
		.then( () => 'error' )
		.catch( () => null );
	const outcome = await Promise.race( [ result, modalError ] );
	if ( outcome === 'error' ) {
		const errorText = await page
			.locator( '#rsl-ie-function-editor-modal .rsl-ie-modal-error' )
			.innerText()
			.catch( () => '' );
		throw new Error( `Function test failed: ${ errorText }` );
	}
	assert(
		outcome === 'result',
		'Function test did not show results or an error'
	);
	const output = await page.locator( '.rsl-ie-test-output' ).innerText();
	assert(
		output === expectedOutput,
		`Function test expected "${ expectedOutput }", got "${ output }"`
	);
}

async function saveFunctionAndWait( page, name ) {
	await page.locator( '.rsl-ie-save-function' ).click();
	await page
		.locator( '.notice-success', {
			hasText: /Function saved|saved successfully/i,
		} )
		.first()
		.waitFor( { state: 'visible', timeout: 30_000 } );
	await waitForFunctionsLoaded( page );
	await searchFunction( page, name );
	const row = page.locator( '#rsl-ie-functions-tbody tr', { hasText: name } );
	await row.first().waitFor( { state: 'visible', timeout: 30_000 } );
	return row.first();
}

async function searchFunction( page, query ) {
	await page.fill( '#rsl-ie-filter-search', query );
	await page.waitForTimeout( 700 );
	await waitForFunctionsLoaded( page );
}

async function clearFunctionSearch( page ) {
	await page.locator( '.rsl-ie-filter-clear' ).click();
	await page.waitForTimeout( 700 );
	await waitForFunctionsLoaded( page );
}

async function deleteFunctionByName( page, name ) {
	await searchFunction( page, name );
	const row = page
		.locator( '#rsl-ie-functions-tbody tr', { hasText: name } )
		.first();
	if ( ! ( await row.count() ) ) return false;
	const deleteButton = row.locator( '.rsl-ie-delete-function' ).first();
	if ( ! ( await deleteButton.count() ) ) return false;
	page.once( 'dialog', async ( dialog ) => {
		await dialog.accept();
	} );
	await deleteButton.click();
	await page
		.locator( '.notice-success', { hasText: /deleted successfully/i } )
		.first()
		.waitFor( { state: 'visible', timeout: 30_000 } );
	await searchFunction( page, name );
	const remaining = await page
		.locator( '#rsl-ie-functions-tbody tr', { hasText: name } )
		.count();
	await clearFunctionSearch( page );
	return remaining === 0;
}

async function deleteFunctionsByPrefix( page, prefix, maxDeletes = 20 ) {
	let deleted = 0;
	for ( let i = 0; i < maxDeletes; i++ ) {
		await searchFunction( page, prefix );
		const row = page
			.locator( '#rsl-ie-functions-tbody tr', { hasText: prefix } )
			.first();
		if ( ! ( await row.count() ) ) break;
		const rowText = await row.innerText().catch( () => '' );
		if ( ! rowText.includes( prefix ) ) break;
		const deleteButton = row.locator( '.rsl-ie-delete-function' ).first();
		if ( ! ( await deleteButton.count() ) ) break;
		page.once( 'dialog', async ( dialog ) => {
			await dialog.accept();
		} );
		await deleteButton.click();
		await page
			.locator( '.notice-success', { hasText: /deleted successfully/i } )
			.first()
			.waitFor( { state: 'visible', timeout: 30_000 } );
		deleted++;
	}
	await clearFunctionSearch( page );
	return deleted;
}

async function exerciseCrud( page, runId, summary ) {
	const name = `aie_functions_ui_${ runId }`;
	const editedName = `${ name }_edited`;

	const oldDeleted = await deleteFunctionsByPrefix(
		page,
		'aie_functions_ui_'
	);
	await deleteFunctionByName( page, name ).catch( () => false );
	await deleteFunctionByName( page, editedName ).catch( () => false );

	await openNewFunctionModal( page );
	await page.fill( '#rsl-ie-function-name', name );
	await page.fill(
		'#rsl-ie-function-description',
		'Created by headless Playwright Functions E2E'
	);
	await setEditorCode( page, 'return strtoupper(trim((string) $value));' );
	await testCurrentFunctionCode(
		page,
		'  hello functions  ',
		'HELLO FUNCTIONS'
	);
	const createdRow = await saveFunctionAndWait( page, name );
	assert(
		await createdRow.isVisible(),
		'Created custom function is not visible'
	);

	await createdRow.locator( '.rsl-ie-edit-function' ).click();
	await page
		.locator( '#rsl-ie-function-editor-modal' )
		.waitFor( { state: 'visible', timeout: 30_000 } );
	await waitForEditorLoaded( page, name );
	await page.fill( '#rsl-ie-function-name', editedName );
	await page.fill(
		'#rsl-ie-function-description',
		'Edited by headless Playwright Functions E2E'
	);
	await setEditorCode(
		page,
		"return str_replace('a', 'z', (string) $value);"
	);
	await testCurrentFunctionCode( page, 'abc123', 'zbc123' );
	await saveFunctionAndWait( page, editedName );

	const deleted = await deleteFunctionByName( page, editedName );
	assert( deleted, 'Edited custom function was not deleted through the UI' );

	summary.steps.push( {
		name: 'crud_custom_function',
		ok: true,
		created: name,
		edited: editedName,
		oldDeleted,
	} );
}

async function exerciseLibrary( page, runId, summary ) {
	const snippetFunctionName = `aie_snippet_uppercase_${ runId }`;
	const oldDeleted = await deleteFunctionsByPrefix(
		page,
		'aie_snippet_uppercase_'
	);
	await deleteFunctionByName( page, snippetFunctionName ).catch(
		() => false
	);

	await page.locator( '.rsl-ie-browse-library' ).click();
	await page
		.locator( '#rsl-ie-snippets-library-modal' )
		.waitFor( { state: 'visible', timeout: 30_000 } );
	await page
		.locator( '#rsl-ie-snippets-grid .rsl-ie-snippet-card' )
		.first()
		.waitFor( { state: 'visible', timeout: 60_000 } );

	const categories = await page
		.locator( '#rsl-ie-categories-list .rsl-ie-category-item' )
		.count();
	assert( categories > 1, 'Snippet library categories did not load' );

	await page.fill( '#rsl-ie-snippet-search', 'uppercase' );
	await page.waitForTimeout( 600 );
	const card = page
		.locator( '#rsl-ie-snippets-grid .rsl-ie-snippet-card' )
		.first();
	await card.waitFor( { state: 'visible', timeout: 30_000 } );
	const cardText = await card.innerText();
	assert(
		/upper/i.test( cardText ),
		'Snippet search did not find uppercase snippet'
	);

	await card.locator( '.rsl-ie-preview-snippet' ).click();
	await page
		.locator( '#rsl-ie-snippet-preview-modal' )
		.waitFor( { state: 'visible', timeout: 30_000 } );
	const previewText = await page
		.locator( '#rsl-ie-snippet-preview-modal' )
		.innerText();
	assert(
		/Convert to Uppercase|strtoupper|HELLO WORLD/i.test( previewText ),
		'Snippet preview content is incomplete'
	);

	await page
		.locator( '#rsl-ie-snippet-preview-modal .rsl-ie-customize-snippet' )
		.click();
	await page
		.locator( '#rsl-ie-function-editor-modal' )
		.waitFor( { state: 'visible', timeout: 30_000 } );
	await page.fill( '#rsl-ie-function-name', snippetFunctionName );
	await page.fill(
		'#rsl-ie-function-description',
		'Customized uppercase snippet from library'
	);
	await testCurrentFunctionCode( page, 'hello world', 'HELLO WORLD' );
	await saveFunctionAndWait( page, snippetFunctionName );

	const deleted = await deleteFunctionByName( page, snippetFunctionName );
	assert(
		deleted,
		'Customized library snippet was not deleted through the UI'
	);

	summary.steps.push( {
		name: 'snippet_library',
		ok: true,
		categories,
		customized: snippetFunctionName,
		oldDeleted,
	} );
}

function wireDiagnostics( page, summary ) {
	page.on( 'pageerror', ( error ) => {
		summary.pageErrors.push( error.message );
	} );
	page.on( 'console', ( message ) => {
		if ( message.type() === 'error' ) {
			summary.consoleErrors.push( message.text() );
		}
	} );
	page.on( 'response', ( response ) => {
		if ( response.status() >= 500 ) {
			summary.http500.push( {
				url: response.url(),
				status: response.status(),
			} );
		}
	} );
	page.on( 'requestfailed', ( request ) => {
		const failure = request.failure();
		summary.requestFailures.push( {
			url: request.url(),
			error: failure ? failure.errorText : 'unknown',
		} );
	} );
}

async function main() {
	const env = loadEnv();
	const summary = {
		runId: env.runId,
		headless: env.headless,
		baseUrl: env.site.baseUrl,
		steps: [],
		consoleErrors: [],
		pageErrors: [],
		requestFailures: [],
		http500: [],
	};

	const browser = await chromium.launch( { headless: env.headless } );
	const context = await browser.newContext( {
		viewport: { width: 1440, height: 1000 },
		acceptDownloads: true,
	} );
	const page = await context.newPage();
	wireDiagnostics( page, summary );

	try {
		logStep( 'login' );
		await ensureLoggedIn( page, env.site );
		await ensureFreePluginActive( page, env.site );

		logStep( 'verify PRO-off access' );
		await verifyFunctionsUnavailableWithoutPro( page, env.site, summary );
		logStep( 'verify PRO-on UI' );
		await verifyFunctionsAvailableWithPro( page, env.site, summary );
		logStep( 'search and pagination' );
		await exerciseSearchAndPagination( page, summary );
		logStep( 'AI generation UI' );
		await assertAiUiState( page, summary );
		logStep( 'CRUD custom functions' );
		await exerciseCrud( page, env.runId, summary );
		logStep( 'snippet library' );
		await exerciseLibrary( page, env.runId, summary );

		logStep( 'final page reload' );
		await gotoAdmin(
			page,
			env.site,
			'/wp-admin/admin.php?page=rsl-ie-functions'
		);
		await waitForFunctionsLoaded( page );
		const finalTableState = await getTableState( page );
		assert(
			finalTableState.rows > 0,
			'Final Functions page reload did not show available functions'
		);
		summary.finalTableState = finalTableState;

		await page.screenshot( {
			path: artifactPath( `functions-final-${ env.runId }.png` ),
			fullPage: true,
		} );

		const hardErrors = [
			...summary.pageErrors,
			...summary.http500.map(
				( item ) => `${ item.status } ${ item.url }`
			),
		];
		assert(
			hardErrors.length === 0,
			`Browser diagnostics captured hard errors: ${ hardErrors.join(
				'; '
			) }`
		);

		summary.ok = true;
	} catch ( error ) {
		summary.ok = false;
		summary.error =
			error && error.message ? error.message : String( error );
		await page
			.screenshot( {
				path: artifactPath( `functions-failure-${ env.runId }.png` ),
				fullPage: true,
			} )
			.catch( () => null );
		throw error;
	} finally {
		try {
			await setPluginActive( page, env.site, PRO_PLUGIN_FILE, true );
		} catch ( error ) {
			summary.finalProActivationWarning =
				error && error.message ? error.message : String( error );
		}
		fs.writeFileSync(
			artifactPath( `summary-${ env.runId }.json` ),
			JSON.stringify( summary, null, 2 )
		);
		await context.close().catch( () => null );
		await browser.close().catch( () => null );
	}

	console.log( JSON.stringify( summary, null, 2 ) );
}

main().catch( ( error ) => {
	console.error( error && error.stack ? error.stack : error );
	process.exitCode = 1;
} );
