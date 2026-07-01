/**
 * Manual E2E (Playwright): Content Updater checks (aie2.local only)
 *
 * What it covers:
 * - Content Updater wizard for Blog Posts (post)
 * - Step 1: checks "Don't show this warning again" in backup warning modal
 * - Applies a library function pipeline (uppercase) to `post_title`
 * - Filters by ID=1 to update a single post
 * - Verifies the post title changed as expected
 * - Prints summary JSON to stdout
 *
 * Usage:
 *   PLAYWRIGHT_BROWSERS_PATH=./e2e/.playwright-browsers node scripts/aie-content-updater-check.js
 *
 * Env (defaults read from .env.e2e if present):
 *   AIE_TARGET_URL, AIE_TARGET_ADMIN_USER, AIE_TARGET_ADMIN_PASSWORD
 *   AIE_HEADLESS=true|false
 *   AIE_TARGET_WP_PATH=/path/to/target/wp/root
 *   AIE_LOCAL_PHP=/path/to/php (Local.app bundled PHP works well)
 *   AIE_WP_BIN=/path/to/wp (wp-cli wrapper)
 */

const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );
const { chromium } = require( 'playwright' );

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
	if ( fs.existsSync( envPath ) )
		fileEnv = parseDotEnv( fs.readFileSync( envPath, 'utf8' ) );
	const get = ( key, fallback ) =>
		process.env[ key ] ?? fileEnv[ key ] ?? fallback;

	const headlessRaw = String( get( 'AIE_HEADLESS', 'true' ) ).toLowerCase();
	const headless =
		headlessRaw === '1' || headlessRaw === 'true' || headlessRaw === 'yes';

	const contentType = String( get( 'AIE_UPDATER_CONTENT_TYPE', 'post' ) )
		.trim()
		.toLowerCase();
	const fieldKeyOverride = String(
		get( 'AIE_UPDATER_FIELD_KEY', '' )
	).trim();
	const functionIdOverride = String(
		get( 'AIE_UPDATER_FUNCTION_ID', 'snippet_uppercase' )
	).trim();
	const expectedMode = String( get( 'AIE_UPDATER_EXPECT_KIND', 'uppercase' ) )
		.trim()
		.toLowerCase();
	const expectedValueOverride = String(
		get( 'AIE_UPDATER_EXPECT_VALUE', '' )
	);

	const wpPathDefault = path.resolve( process.cwd(), '../../..' );
	const targetWpPathGuess = ( () => {
		const marker = `${ path.sep }Local Sites${ path.sep }aie${ path.sep }`;
		if ( wpPathDefault.includes( marker ) ) {
			return wpPathDefault.replace(
				marker,
				`${ path.sep }Local Sites${ path.sep }aie2${ path.sep }`
			);
		}
		return wpPathDefault;
	} )();

	const localPhpCandidates = [
		'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php',
		'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php',
	];
	const localPhpDefault =
		localPhpCandidates.find( ( p ) => fs.existsSync( p ) ) || 'php';

	return {
		headless,
		contentType,
		fieldKeyOverride,
		functionIdOverride,
		expectedMode,
		expectedValueOverride,
		target: {
			baseUrl: get( 'AIE_TARGET_URL', 'http://aie2.local' ),
			username: get( 'AIE_TARGET_ADMIN_USER', 'admin' ),
			password: get( 'AIE_TARGET_ADMIN_PASSWORD', 'admin' ),
			wpPath: String( get( 'AIE_TARGET_WP_PATH', targetWpPathGuess ) ),
		},
		localPhp: String( get( 'AIE_LOCAL_PHP', localPhpDefault ) ),
		wpBin: String( get( 'AIE_WP_BIN', '/opt/homebrew/bin/wp' ) ),
	};
}

function wp( env, wpPath, args, { trim = true } = {} ) {
	const phpArgs = [
		'-d',
		'display_errors=0',
		'-d',
		'error_reporting=0',
		'-d',
		'html_errors=0',
	];
	const out = execFileSync(
		env.localPhp,
		[ ...phpArgs, env.wpBin, `--path=${ wpPath }`, ...args ],
		{
			encoding: 'utf8',
			stdio: [ 'ignore', 'pipe', 'pipe' ],
		}
	);
	return trim ? String( out ).trim() : String( out );
}

function wpEvalJson( env, wpPath, code ) {
	const raw = wp( env, wpPath, [ 'eval', code ], { trim: true } );
	try {
		return JSON.parse( raw || 'null' );
	} catch {
		return null;
	}
}

function nowStamp() {
	return new Date().toISOString().replace( /[:.]/g, '-' );
}

function mkdirp( dir ) {
	fs.mkdirSync( dir, { recursive: true } );
}

function rmrf( p ) {
	try {
		fs.rmSync( p, { recursive: true, force: true } );
	} catch {}
}

async function ensureLoggedIn( page, { baseUrl, username, password } ) {
	if ( await page.locator( '#wpadminbar' ).count() ) return;
	await page.goto( `${ baseUrl }/wp-login.php`, {
		waitUntil: 'domcontentloaded',
	} );
	await page.fill( '#user_login', username );
	await page.fill( '#user_pass', password );
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
		page.click( '#wp-submit' ),
	] );
	await page.waitForSelector( '#wpadminbar', { timeout: 30_000 } );
}

async function gotoAdminPage( page, site, adminPathWithQuery ) {
	await page.goto( `${ site.baseUrl }${ adminPathWithQuery }`, {
		waitUntil: 'domcontentloaded',
	} );
	if ( await page.locator( 'form#loginform' ).count() ) {
		await ensureLoggedIn( page, site );
		await page.goto( `${ site.baseUrl }${ adminPathWithQuery }`, {
			waitUntil: 'domcontentloaded',
		} );
	}
}

async function handleBackupModalIfPresent(
	page,
	{ checkDontShow = true } = {}
) {
	const overlay = page.locator( '.rsl-ie-backup-warning-overlay' );
	if ( ! ( await overlay.count().catch( () => 0 ) ) ) return false;
	await overlay.waitFor( { state: 'visible', timeout: 20_000 } );
	const created = page.locator( '#rsl-ie-backup-created' ).first();
	const dontShow = page.locator( '#rsl-ie-backup-dont-show' ).first();
	if ( await created.count().catch( () => 0 ) )
		await created.check( { force: true } );
	if ( checkDontShow && ( await dontShow.count().catch( () => 0 ) ) )
		await dontShow.check( { force: true } );
	await page.locator( '.rsl-ie-backup-confirm' ).first().click();
	await overlay.waitFor( { state: 'detached', timeout: 20_000 } );
	return true;
}

async function waitUpdaterStep( page, stepNum ) {
	await page.waitForSelector( `.rsl-ie-updater-step-${ stepNum }.active`, {
		timeout: 60_000,
	} );
}

async function selectUpdaterContentTypeOnStep1( page, contentType ) {
	await waitUpdaterStep( page, 1 );
	const ct = String( contentType || 'post' ).trim();
	const input = page
		.locator(
			`.rsl-ie-updater-step-1.active input[name="updater_content_type"][value="${ ct }"]`
		)
		.first();
	if ( await input.count().catch( () => 0 ) ) {
		await input.check( { force: true } ).catch( () => null );
		let ok = await input.isChecked().catch( () => false );
		if ( ! ok ) {
			await page
				.evaluate( ( v ) => {
					const el = document.querySelector(
						`.rsl-ie-updater-step-1.active input[name="updater_content_type"][value="${ v }"]`
					);
					if ( ! el ) return;
					el.checked = true;
					el.dispatchEvent(
						new Event( 'change', { bubbles: true } )
					);
					el.dispatchEvent( new Event( 'click', { bubbles: true } ) );
				}, ct )
				.catch( () => null );
			ok = await input.isChecked().catch( () => false );
		}
		if ( ! ok ) throw new Error( `Failed to select content type: ${ ct }` );
	}
}

async function selectUpdaterContentTypeOnStep1Any( page, values ) {
	const list = Array.isArray( values ) ? values : [];
	for ( const v of list ) {
		// eslint-disable-next-line no-await-in-loop
		const input = page
			.locator(
				`.rsl-ie-updater-step-1.active input[name="updater_content_type"][value="${ String(
					v
				).trim() }"]`
			)
			.first();
		// eslint-disable-next-line no-await-in-loop
		if ( ! ( await input.count().catch( () => 0 ) ) ) continue;
		// eslint-disable-next-line no-await-in-loop
		await selectUpdaterContentTypeOnStep1( page, v );
		return String( v ).trim();
	}
	return '';
}

async function clickUpdaterNext( page, { expectBackupModal = false } = {} ) {
	const btn = page.locator( '.rsl-ie-step.active .rsl-ie-updater-next-step' );
	await btn.waitFor( { state: 'visible', timeout: 60_000 } );
	const enabled = await btn.isEnabled().catch( () => false );
	if ( ! enabled ) throw new Error( 'Updater Next Step is disabled' );
	await btn.click();
	if ( expectBackupModal ) {
		const ok = await handleBackupModalIfPresent( page, {
			checkDontShow: true,
		} );
		if ( ! ok )
			throw new Error( 'Expected backup warning modal on step 1' );
	}
}

async function setStep2IdEqualsFilter(
	page,
	idValue,
	{ fieldPreferredValues = [] } = {}
) {
	await waitUpdaterStep( page, 2 );
	const currentCt = await page
		.evaluate( () => {
			const el = document.querySelector(
				'.rsl-ie-updater-step-1 input[name="updater_content_type"]:checked'
			);
			return el ? el.value : '';
		} )
		.catch( () => '' );

	const addBtn = page.locator( '.rsl-ie-updater-add-filter' ).first();
	await addBtn.waitFor( { state: 'visible', timeout: 60_000 } );
	await addBtn.click();

	const row = page
		.locator( '.rsl-ie-updater-step-2.active .rsl-ie-filter-row' )
		.last();
	await row.waitFor( { state: 'visible', timeout: 30_000 } );

	const fieldSel = row
		.locator( 'select.rsl-ie-updater-filter-field' )
		.first();
	await fieldSel.waitFor( { state: 'visible', timeout: 30_000 } );
	await page
		.waitForFunction( ( sel ) => {
			const el = document.querySelector( sel );
			return el && el.querySelectorAll( 'option' ).length > 1;
		}, 'select.rsl-ie-updater-filter-field' )
		.catch( () => null );
	let picked = '';
	const prefers = Array.isArray( fieldPreferredValues )
		? fieldPreferredValues
		: [];
	for ( const v of prefers ) {
		// eslint-disable-next-line no-await-in-loop
		const has = await fieldSel.locator( `option[value="${ v }"]` ).count();
		if ( has ) {
			picked = v;
			break;
		}
	}
	if (
		! picked &&
		( await fieldSel.locator( 'option[value="ID"]' ).count() )
	)
		picked = 'ID';
	if (
		! picked &&
		( await fieldSel.locator( 'option[value="comment_ID"]' ).count() )
	)
		picked = 'comment_ID';
	if ( picked ) {
		await fieldSel.selectOption( { value: picked } );
	} else {
		// fallback: pick first non-empty option
		const first = await fieldSel
			.locator( 'option[value]' )
			.nth( 1 )
			.getAttribute( 'value' )
			.catch( () => '' );
		if ( first ) await fieldSel.selectOption( { value: first } );
	}

	// Wait for dependent UI to refresh after field change (conditions + value input may be re-rendered).
	await page
		.waitForFunction( () => {
			const sel = document.querySelector(
				'.rsl-ie-updater-step-2.active .rsl-ie-filter-row:last-child select.rsl-ie-updater-filter-condition'
			);
			return sel && sel.querySelectorAll( 'option' ).length > 1;
		} )
		.catch( () => null );

	const condSel = row
		.locator( 'select.rsl-ie-updater-filter-condition' )
		.first();
	await condSel.waitFor( { state: 'visible', timeout: 30_000 } );
	await page.waitForTimeout( 50 );
	if ( await condSel.locator( 'option[value="equals"]' ).count() ) {
		await condSel.selectOption( { value: 'equals' } );
	} else {
		await condSel.selectOption( { index: 1 } ).catch( () => null );
	}

	const val = row.locator( 'input.rsl-ie-updater-filter-value' ).first();
	await val.waitFor( { state: 'visible', timeout: 30_000 } );
	await val.fill( String( idValue ) );

	const refresh = page.locator( '.rsl-ie-updater-refresh-count' ).first();
	if ( await refresh.count().catch( () => 0 ) ) {
		const spinner = page
			.locator( '.rsl-ie-filter-summary-top .spinner' )
			.first();
		await refresh.click( { force: true } ).catch( () => null );
		await spinner
			.waitFor( { state: 'attached', timeout: 10_000 } )
			.catch( () => null );
		await page
			.waitForFunction( () => {
				const sp = document.querySelector(
					'.rsl-ie-filter-summary-top .spinner'
				);
				return sp && sp.classList.contains( 'is-active' );
			} )
			.catch( () => null );
		await page
			.waitForFunction( () => {
				const sp = document.querySelector(
					'.rsl-ie-filter-summary-top .spinner'
				);
				return sp && ! sp.classList.contains( 'is-active' );
			} )
			.catch( () => null );
	}

	await page.waitForFunction( () => {
		const el = document.querySelector(
			'.rsl-ie-updater-step-2.active .rsl-ie-count-value'
		);
		if ( ! el ) return false;
		const t = String( el.textContent || '' ).trim();
		return t !== '-' && t !== '';
	} );

	const countText = await page
		.locator( '.rsl-ie-updater-step-2.active .rsl-ie-count-value' )
		.first()
		.innerText()
		.catch( () => '-' );
	const countNum = Number( String( countText ).replace( /[^0-9]/g, '' ) );
	if ( ! Number.isFinite( countNum ) || countNum < 1 ) {
		const debug = await row
			.evaluate( ( el ) => {
				const q = ( s ) => el.querySelector( s );
				return {
					field:
						q( 'select.rsl-ie-updater-filter-field' )?.value || '',
					condition:
						q( 'select.rsl-ie-updater-filter-condition' )?.value ||
						'',
					value:
						q( 'input.rsl-ie-updater-filter-value' )?.value || '',
				};
			} )
			.catch( () => ( {} ) );
		throw new Error(
			`Updater Step 2 count expected >=1, got ${ countText } (content_type=${ currentCt }, row=${ JSON.stringify(
				debug
			) })`
		);
	}

	// For ID-based tests, require the ID to be present in the preview list before proceeding.
	// This prevents "count" race conditions where the count is stale for the previous filter set.
	const expectedId = String( idValue || '' ).trim();
	if ( expectedId ) {
		const previewOk = await page
			.evaluate(
				( want ) => {
					const rows = Array.from(
						document.querySelectorAll( '.rsl-ie-filter-row' )
					);
					const hasIdFilter = rows.some( ( r ) => {
						const f =
							r.querySelector(
								'select.rsl-ie-updater-filter-field'
							)?.value || '';
						const c =
							r.querySelector(
								'select.rsl-ie-updater-filter-condition'
							)?.value || '';
						const v =
							r.querySelector(
								'input.rsl-ie-updater-filter-value'
							)?.value || '';
						return (
							f === want.field &&
							c === 'equals' &&
							String( v ) === String( want.value )
						);
					} );
					return hasIdFilter;
				},
				{ field: picked || 'ID', value: expectedId }
			)
			.catch( () => false );
		if ( ! previewOk ) {
			throw new Error(
				`Updater Step 2 ID filter did not stick (expected ${
					picked || 'ID'
				}=${ expectedId })`
			);
		}
	}
}

async function addStep2PostTypeSelectorFilter( page, postTypeValue ) {
	await waitUpdaterStep( page, 2 );

	const addBtn = page.locator( '.rsl-ie-updater-add-filter' ).first();
	await addBtn.waitFor( { state: 'visible', timeout: 60_000 } );
	await addBtn.click();

	const row = page
		.locator( '.rsl-ie-updater-step-2.active .rsl-ie-filter-row' )
		.last();
	await row.waitFor( { state: 'visible', timeout: 30_000 } );

	const fieldSel = row
		.locator( 'select.rsl-ie-updater-filter-field' )
		.first();
	await fieldSel.waitFor( { state: 'visible', timeout: 30_000 } );
	if ( await fieldSel.locator( 'option[value="_post_type"]' ).count() ) {
		await fieldSel.selectOption( { value: '_post_type' } );
	} else {
		throw new Error(
			'Post type selector filter (_post_type) not available'
		);
	}

	// Wait for the post type selector <select> to be rendered in place of the value input.
	const ptSel = row
		.locator(
			'select.rsl-ie-updater-filter-value.rsl-ie-post-type-selector'
		)
		.first();
	await ptSel.waitFor( { state: 'visible', timeout: 60_000 } );
	await page
		.waitForFunction( ( sel ) => {
			const el = document.querySelector( sel );
			return el && el.querySelectorAll( 'option' ).length > 1;
		}, 'select.rsl-ie-post-type-selector' )
		.catch( () => null );
	await ptSel.selectOption( { value: String( postTypeValue || '' ) } );

	// Refresh count after selecting post type.
	const refresh = page.locator( '.rsl-ie-updater-refresh-count' ).first();
	if ( await refresh.count().catch( () => 0 ) )
		await refresh.click( { force: true } );

	// Wait for spinner to finish.
	await page
		.waitForFunction( () => {
			const sp = document.querySelector(
				'.rsl-ie-filter-summary-top .spinner'
			);
			return sp && sp.classList.contains( 'is-active' );
		} )
		.catch( () => null );
	await page
		.waitForFunction( () => {
			const sp = document.querySelector(
				'.rsl-ie-filter-summary-top .spinner'
			);
			return sp && ! sp.classList.contains( 'is-active' );
		} )
		.catch( () => null );
}

async function addStep2TaxonomySelectorFilter( page, taxonomySlug ) {
	await waitUpdaterStep( page, 2 );

	const addBtn = page.locator( '.rsl-ie-updater-add-filter' ).first();
	await addBtn.waitFor( { state: 'visible', timeout: 60_000 } );
	await addBtn.click();

	const row = page
		.locator( '.rsl-ie-updater-step-2.active .rsl-ie-filter-row' )
		.last();
	await row.waitFor( { state: 'visible', timeout: 30_000 } );

	const fieldSel = row
		.locator( 'select.rsl-ie-updater-filter-field' )
		.first();
	await fieldSel.waitFor( { state: 'visible', timeout: 30_000 } );
	await page
		.waitForFunction( ( sel ) => {
			const el = document.querySelector( sel );
			return el && el.querySelectorAll( 'option' ).length > 1;
		}, 'select.rsl-ie-updater-filter-field' )
		.catch( () => null );

	if ( await fieldSel.locator( 'option[value="_taxonomy"]' ).count() ) {
		await fieldSel.selectOption( { value: '_taxonomy' } );
	} else if ( await fieldSel.locator( 'option[value="taxonomy"]' ).count() ) {
		await fieldSel.selectOption( { value: 'taxonomy' } );
	} else {
		throw new Error( 'Taxonomy selector filter not available (_taxonomy)' );
	}

	// Wait for taxonomy selector <select> to be rendered in place of the value input.
	const taxSel = row
		.locator(
			'select.rsl-ie-updater-filter-value.rsl-ie-taxonomy-selector'
		)
		.first();
	await taxSel.waitFor( { state: 'visible', timeout: 60_000 } );
	await page
		.waitForFunction( ( sel ) => {
			const el = document.querySelector( sel );
			return el && el.querySelectorAll( 'option' ).length > 1;
		}, 'select.rsl-ie-taxonomy-selector' )
		.catch( () => null );

	await taxSel.selectOption( { value: String( taxonomySlug || '' ) } );

	// Refresh count after selecting taxonomy.
	const refresh = page.locator( '.rsl-ie-updater-refresh-count' ).first();
	if ( await refresh.count().catch( () => 0 ) ) {
		const spinner = page
			.locator( '.rsl-ie-filter-summary-top .spinner' )
			.first();
		await refresh.click( { force: true } ).catch( () => null );
		await page
			.waitForFunction( () => {
				const sp = document.querySelector(
					'.rsl-ie-filter-summary-top .spinner'
				);
				return sp && sp.classList.contains( 'is-active' );
			} )
			.catch( () => null );
		await page
			.waitForFunction( () => {
				const sp = document.querySelector(
					'.rsl-ie-filter-summary-top .spinner'
				);
				return sp && ! sp.classList.contains( 'is-active' );
			} )
			.catch( () => null );
	}
}

async function selectStep2DatabaseTable( page, tableName ) {
	await waitUpdaterStep( page, 2 );
	const dropdown = page.locator( '#rsl-ie-updater-table-name' ).first();
	await dropdown.waitFor( { state: 'visible', timeout: 60_000 } );
	await page
		.waitForFunction(
			() => {
				const el = document.querySelector(
					'#rsl-ie-updater-table-name'
				);
				return !! el && ! el.disabled;
			},
			{ timeout: 60_000 }
		)
		.catch( () => null );

	await page
		.waitForFunction(
			() => {
				const el = document.querySelector(
					'#rsl-ie-updater-table-name'
				);
				return el && el.querySelectorAll( 'option' ).length > 1;
			},
			{ timeout: 60_000 }
		)
		.catch( () => null );

	await dropdown.selectOption( { value: String( tableName || '' ) } );

	// Wait for columns to load and appear in table info pane.
	await page
		.waitForFunction(
			() => {
				const list = document.querySelector( '.rsl-ie-columns-list' );
				const items = list ? list.querySelectorAll( 'li' ) : [];
				return items && items.length > 0;
			},
			{ timeout: 60_000 }
		)
		.catch( () => null );
}

async function selectFieldOnStep3( page, fieldKey ) {
	await waitUpdaterStep( page, 3 );
	const item = page
		.locator(
			`.rsl-ie-updater-step-3.active #rsl-ie-updater-fields-library .rsl-ie-field-item[data-field="${ fieldKey }"]`
		)
		.first();
	await item.waitFor( { state: 'attached', timeout: 60_000 } );
	const visible = await item.isVisible().catch( () => false );
	if ( ! visible ) {
		// Some content types (e.g. custom_post_types) may render the first visible category as collapsed.
		const catTitle = item
			.locator(
				'xpath=ancestor::div[contains(@class,"rsl-ie-field-category")][1]//h4[contains(@class,"rsl-ie-field-category-title")]'
			)
			.first();
		if ( await catTitle.count().catch( () => 0 ) ) {
			await catTitle.click( { force: true } ).catch( () => null );
			await page.waitForTimeout( 100 );
		}
	}
	await page
		.evaluate( ( key ) => {
			const itemEl = document.querySelector(
				`.rsl-ie-updater-step-3.active #rsl-ie-updater-fields-library .rsl-ie-field-item[data-field="${ key }"]`
			);
			const category = itemEl
				? itemEl.closest( '.rsl-ie-field-category' )
				: null;
			if ( category ) {
				category.classList.remove( 'rsl-ie-collapsed' );
				category.style.display = '';
			}
			if ( itemEl ) itemEl.click();
		}, fieldKey )
		.catch( ( clickError ) => {
			throw clickError;
		} );

	await page.waitForSelector(
		`.rsl-ie-updater-step-3.active #rsl-ie-updater-fields-list .rsl-ie-selected-field[data-field="${ fieldKey }"]`,
		{ timeout: 30_000 }
	);
}

async function assignUppercaseFunctionToField( page, fieldKey ) {
	await assignFunctionToField( page, fieldKey, 'snippet_uppercase' );
}

async function assignFunctionToField( page, fieldKey, functionId ) {
	await waitUpdaterStep( page, 4 );

	const functionIds = String( functionId || '' )
		.split( ',' )
		.map( ( id ) => id.trim() )
		.filter( Boolean );
	if ( ! functionIds.length ) {
		throw new Error( 'No updater functions configured' );
	}

	const assignBtn = page
		.locator(
			`.rsl-ie-updater-step-4.active .rsl-ie-assign-functions[data-field="${ fieldKey }"]`
		)
		.first();
	await assignBtn.waitFor( { state: 'visible', timeout: 60_000 } );
	await assignBtn.click();

	const modal = page.locator( '#rsl-ie-updater-functions-modal' ).first();
	await modal.waitFor( { state: 'visible', timeout: 60_000 } );

	// Wait for functions list to populate (AJAX).
	await page
		.waitForFunction( () => {
			return (
				document.querySelectorAll(
					'#rsl-ie-updater-functions-list .rsl-ie-function-list-item'
				).length > 0
			);
		} )
		.catch( () => null );

	for ( const oneFunctionId of functionIds ) {
		// eslint-disable-next-line no-await-in-loop
		const search = page
			.locator( '#rsl-ie-updater-functions-search' )
			.first();
		// eslint-disable-next-line no-await-in-loop
		if ( await search.count().catch( () => 0 ) ) {
			// eslint-disable-next-line no-await-in-loop
			await search.fill( '' ).catch( () => null );
		}

		// Prefer the requested function.
		const addRequested = page.locator(
			`#rsl-ie-updater-functions-list .rsl-ie-add-function-btn[data-function-id="${ oneFunctionId }"]`
		);
		// eslint-disable-next-line no-await-in-loop
		if ( await addRequested.count().catch( () => 0 ) ) {
			// eslint-disable-next-line no-await-in-loop
			await addRequested.first().click();
		} else {
			// Fallback: search by name.
			// eslint-disable-next-line no-await-in-loop
			if ( await search.count().catch( () => 0 ) ) {
				// eslint-disable-next-line no-await-in-loop
				await search
					.fill( String( oneFunctionId ).replace( /^snippet_/, '' ) )
					.catch( () => null );
			}
			const firstAdd = page.locator(
				'#rsl-ie-updater-functions-list .rsl-ie-add-function-btn'
			);
			// eslint-disable-next-line no-await-in-loop
			await firstAdd.first().click();
		}
	}

	await page
		.waitForSelector(
			'#rsl-ie-updater-function-items .rsl-ie-function-item',
			{ timeout: 30_000 }
		)
		.catch( () => null );

	await page.locator( '.rsl-ie-save-updater-functions' ).first().click();
	await modal.waitFor( { state: 'hidden', timeout: 60_000 } );

	// Ensure stats reflect assigned transformations.
	await page
		.waitForFunction( () => {
			const el = document.querySelector(
				'.rsl-ie-updater-step-4.active .rsl-ie-functions-assigned-stat'
			);
			const n = el ? Number( String( el.textContent || '' ).trim() ) : 0;
			return Number.isFinite( n ) && n >= 1;
		} )
		.catch( () => null );
}

async function startUpdateAndWaitForResults( page ) {
	await waitUpdaterStep( page, 5 );
	const startBtn = page.locator( '.rsl-ie-start-update-btn' ).first();
	await startBtn.waitFor( { state: 'visible', timeout: 60_000 } );
	await startBtn.click();

	// In some runs the modal can still appear (if localStorage wasn't set).
	await handleBackupModalIfPresent( page, { checkDontShow: true } ).catch(
		() => null
	);

	// Wait until UI shows results (job completed/failed/cancelled).
	// Some flows can complete the job but fail to reveal the results panel promptly,
	// so also accept "progress section hidden" as a completion signal.
	const results = page.locator( '#rsl-ie-updater-results' ).first();
	const progress = page.locator( '#rsl-ie-updater-progress' ).first();
	await Promise.race( [
		results.waitFor( { state: 'visible', timeout: 10 * 60_000 } ),
		page.waitForFunction(
			() => {
				const p = document.querySelector( '#rsl-ie-updater-progress' );
				const r = document.querySelector( '#rsl-ie-updater-results' );
				if ( r && r.offsetParent !== null ) return true;
				// fallback: progress hidden implies completion UI transition happened
				return p && p.style && p.style.display === 'none';
			},
			{ timeout: 10 * 60_000 }
		),
	] ).catch( () => null );

	const stats = await page.evaluate( () => {
		const num = ( sel ) => {
			const el = document.querySelector( sel );
			const t = el ? String( el.textContent || '' ).trim() : '';
			const n = Number( t.replace( /[^0-9]/g, '' ) );
			return Number.isFinite( n ) ? n : null;
		};
		return {
			processed: num( '.rsl-ie-final-processed' ),
			updated: num( '.rsl-ie-final-updated' ),
			skipped: num( '.rsl-ie-final-skipped' ),
			errors: num( '.rsl-ie-final-errors' ),
		};
	} );

	return stats;
}

function getLatestUpdateJob( env, wpPath, { dataType = '' } = {} ) {
	const dt = String( dataType || '' ).trim();
	const php = `
global $wpdb;
$table = $wpdb->prefix . 'rsl_ie_jobs';
$where = "WHERE type = 'update'";
if (${ JSON.stringify( !! dt ) }) {
  $dt = ${ JSON.stringify( dt ) };
  $where .= $wpdb->prepare(' AND data_type = %s', $dt);
}
$row = $wpdb->get_row("SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT 1", ARRAY_A);
echo wp_json_encode($row ?: null, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	return wpEvalJson( env, wpPath, php );
}

function waitForUpdateJobCompletion(
	env,
	wpPath,
	{ dataType, minUpdatedAt = '', timeoutMs = 10 * 60_000 } = {}
) {
	const start = Date.now();
	// Small skew allowance because timestamps are 1-second resolution.
	const min = String( minUpdatedAt || '' ).trim();
	const minFloor =
		min && min.length >= 19
			? min.slice( 0, 17 ) + '00' // effectively disable strict second match? keep date/hour/minute
			: '';
	while ( Date.now() - start < timeoutMs ) {
		const row = getLatestUpdateJob( env, wpPath, { dataType } );
		if ( row ) {
			const status = String( row.status || '' );
			const updatedAt = String( row.updated_at || '' );
			const okTime = min
				? updatedAt >= min || ( minFloor && updatedAt >= minFloor )
				: true;
			if (
				okTime &&
				[ 'completed', 'failed', 'cancelled' ].includes( status )
			) {
				return row;
			}
		}
		// sleep ~500ms
		try {
			Atomics.wait(
				new Int32Array( new SharedArrayBuffer( 4 ) ),
				0,
				0,
				500
			);
		} catch {}
	}
	return null;
}

function getPostTitle( env, wpPath, postId ) {
	const php = `
$id = (int) ${ JSON.stringify( Number( postId || 0 ) ) };
$p = get_post($id);
echo wp_json_encode([
  'id' => $p ? (int)$p->ID : 0,
  'title' => $p ? (string) get_the_title($id) : '',
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	const out = wpEvalJson( env, wpPath, php );
	return out && out.id ? out : { id: 0, title: '' };
}

function getCommentContent( env, wpPath, commentId ) {
	const php = `
$id = (int) ${ JSON.stringify( Number( commentId || 0 ) ) };
$c = get_comment($id);
echo wp_json_encode([
  'id' => $c ? (int)$c->comment_ID : 0,
  'content' => $c ? (string) $c->comment_content : '',
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	const out = wpEvalJson( env, wpPath, php );
	return out && out.id ? out : { id: 0, content: '' };
}

function pickOneIdByPostType( env, wpPath, postType ) {
	const pt = String( postType || 'post' );
	const php = `
$pt = ${ JSON.stringify( pt ) };
$ids = get_posts([
  'post_type' => $pt,
  'post_status' => 'any',
  'fields' => 'ids',
  'posts_per_page' => 1,
  'orderby' => 'ID',
  'order' => 'ASC',
]);
echo wp_json_encode(['id' => !empty($ids) ? (int)$ids[0] : 0], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.id ? out.id : 0 );
}

function ensureTempUpdaterDbTable( env, wpPath ) {
	const php = `
global $wpdb;
$table = $wpdb->prefix . 'rsl_ie_tmp_updater';
$wpdb->query("DROP TABLE IF EXISTS {$table}");
$charset = $wpdb->get_charset_collate();
$sql = "CREATE TABLE {$table} (
  id INT NOT NULL,
  val VARCHAR(200) NOT NULL,
  PRIMARY KEY (id)
) {$charset}";
$wpdb->query($sql);
$wpdb->insert($table, ['id' => 1, 'val' => 'Hello db']);
echo wp_json_encode(['table' => $table, 'id' => 1], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	return wpEvalJson( env, wpPath, php );
}

function getDbTableValById( env, wpPath, tableName, idValue ) {
	const php = `
global $wpdb;
$t = ${ JSON.stringify( String( tableName || '' ) ) };
$id = ${ JSON.stringify( Number( idValue || 0 ) ) };
$val = '';
if ($t) {
  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is controlled by test
  $val = (string) $wpdb->get_var( $wpdb->prepare("SELECT val FROM {$t} WHERE id = %d", $id) );
}
echo wp_json_encode(['id' => (int)$id, 'val' => $val], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	const out = wpEvalJson( env, wpPath, php );
	return out && typeof out.val === 'string'
		? out
		: { id: Number( idValue || 0 ), val: '' };
}

function ensureOneProductId( env, wpPath ) {
	let id = pickOneIdByPostType( env, wpPath, 'product' );
	if ( id ) return { id, created: false };

	const stamp = new Date()
		.toISOString()
		.replace( /[^0-9]/g, '' )
		.slice( 0, 14 );
	const title = `Updater Product ${ stamp }`;
	id = Number(
		wp( env, wpPath, [
			'post',
			'create',
			'--post_type=product',
			'--post_status=publish',
			`--post_title=${ title }`,
			'--porcelain',
		] )
	);
	if ( Number.isFinite( id ) && id > 0 ) {
		// Minimal meta so WooCommerce recognizes pricing.
		wp( env, wpPath, [
			'post',
			'meta',
			'update',
			String( id ),
			'_regular_price',
			'9.99',
		] );
		wp( env, wpPath, [
			'post',
			'meta',
			'update',
			String( id ),
			'_price',
			'9.99',
		] );
	}
	return { id: Number.isFinite( id ) ? id : 0, created: true };
}

function pickOneCommentId( env, wpPath ) {
	const php = `
$ids = get_comments([
  'status' => 'all',
  'number' => 50,
  'orderby' => 'comment_ID',
  'order' => 'ASC',
]);
$picked = 0;
foreach ($ids as $c) {
  $content = (string) ($c->comment_content ?? '');
  if ($content === '') continue;
  // Prefer plain-text comments to avoid KSES / link-rel mutations affecting comparisons.
  if (strpos($content, '<') === false) { $picked = (int) $c->comment_ID; break; }
  if (!$picked) $picked = (int) $c->comment_ID;
}
echo wp_json_encode(['id' => $picked], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.id ? out.id : 0 );
}

function pickOneTermIdByTaxonomy( env, wpPath, taxonomySlug ) {
	const tax = String( taxonomySlug || 'category' );
	const php = `
$tax = ${ JSON.stringify( tax ) };
$terms = get_terms([
  'taxonomy' => $tax,
  'hide_empty' => false,
  'number' => 1,
  'orderby' => 'term_id',
  'order' => 'ASC',
]);
$picked = 0;
foreach ($terms as $t) {
  $picked = (int) $t->term_id;
  break;
}
echo wp_json_encode(['term_id' => $picked], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.term_id ? out.term_id : 0 );
}

function getTermName( env, wpPath, termId, taxonomySlug ) {
	const tax = String( taxonomySlug || 'category' );
	const php = `
$id = (int) ${ JSON.stringify( Number( termId || 0 ) ) };
$tax = ${ JSON.stringify( tax ) };
$t = get_term($id, $tax);
echo wp_json_encode([
  'term_id' => ($t && !is_wp_error($t)) ? (int)$t->term_id : 0,
  'taxonomy' => ($t && !is_wp_error($t)) ? (string)$t->taxonomy : '',
  'name' => ($t && !is_wp_error($t)) ? (string)$t->name : '',
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	const out = wpEvalJson( env, wpPath, php );
	return out && out.term_id ? out : { term_id: 0, taxonomy: '', name: '' };
}

function pickOneNonAdminUserId( env, wpPath ) {
	const php = `
$ids = get_users([
  'fields' => 'ids',
  'number' => 1,
  'orderby' => 'ID',
  'order' => 'ASC',
  'role__not_in' => [ 'administrator' ],
]);
echo wp_json_encode(['id' => !empty($ids) ? (int)$ids[0] : 0], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.id ? out.id : 0 );
}

function createUpdaterTestUser( env, wpPath ) {
	const stamp = new Date()
		.toISOString()
		.replace( /[^0-9]/g, '' )
		.slice( 0, 14 );
	const login = `rsl_updater_test_${ stamp }`;
	const email = `${ login }@example.com`;
	const displayName = `Updater Test ${ stamp }`;
	const userId = Number(
		wp( env, wpPath, [
			'user',
			'create',
			login,
			email,
			'--role=subscriber',
			'--user_pass=Test1234!',
			`--display_name=${ displayName }`,
			'--porcelain',
		] )
	);
	return Number.isFinite( userId ) && userId > 0 ? userId : 0;
}

function ensureUpdaterTestUserId( env, wpPath ) {
	let id = pickOneNonAdminUserId( env, wpPath );
	if ( id ) return { id, created: false };
	id = createUpdaterTestUser( env, wpPath );
	return { id, created: true };
}

function getUserDisplayName( env, wpPath, userId ) {
	const php = `
$id = (int) ${ JSON.stringify( Number( userId || 0 ) ) };
$u = get_user_by('id', $id);
echo wp_json_encode([
  'id' => $u ? (int) $u->ID : 0,
  'display_name' => $u ? (string) $u->display_name : '',
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	const out = wpEvalJson( env, wpPath, php );
	return out && out.id ? out : { id: 0, display_name: '' };
}

function uppercaseAscii( s ) {
	return String( s || '' ).toUpperCase();
}

function getFeaturedImageId( env, wpPath, postId ) {
	const php = `
$id = (int) ${ JSON.stringify( Number( postId || 0 ) ) };
echo wp_json_encode([ 'id' => (int) get_post_thumbnail_id( $id ) ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.id ? out.id : 0 );
}

function getAttachmentUrl( env, wpPath, attachmentId ) {
	const id = Number( attachmentId || 0 );
	if ( ! id ) return '';
	const php = `
$id = (int) ${ JSON.stringify( id ) };
echo wp_json_encode([ 'url' => (string) wp_get_attachment_url( $id ) ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	const out = wpEvalJson( env, wpPath, php );
	return out && typeof out.url === 'string' ? out.url : '';
}

function getAttachmentField( env, wpPath, attachmentId, field ) {
	const id = Number( attachmentId || 0 );
	if ( ! id ) return '';
	const php = `
$id = (int) ${ JSON.stringify( id ) };
$p = get_post( $id );
echo wp_json_encode([
  'title' => $p ? (string) $p->post_title : '',
  'caption' => $p ? (string) $p->post_excerpt : '',
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	const out = wpEvalJson( env, wpPath, php );
	return out && typeof out[ field ] === 'string' ? out[ field ] : '';
}

function getFieldValueByKey(
	env,
	wpPath,
	contentType,
	objectId,
	fieldKey,
	{ dbTableName = '' } = {}
) {
	const key = String( fieldKey || '' ).trim();
	const postId = Number( objectId || 0 );
	const ct = String( contentType || '' ).trim();

	if ( key === 'post_title' )
		return getPostTitle( env, wpPath, postId ).title;
	if ( key === 'post_content' ) {
		const php = `
$id = (int) ${ JSON.stringify( postId ) };
echo wp_json_encode([ 'value' => (string) get_post_field( 'post_content', $id ) ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
		const out = wpEvalJson( env, wpPath, php );
		return out && typeof out.value === 'string' ? out.value : '';
	}
	if ( key === 'post_excerpt' ) {
		const php = `
$id = (int) ${ JSON.stringify( postId ) };
echo wp_json_encode([ 'value' => (string) get_post_field( 'post_excerpt', $id ) ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
		const out = wpEvalJson( env, wpPath, php );
		return out && typeof out.value === 'string' ? out.value : '';
	}
	if ( key === 'featured_image_id' )
		return String( getFeaturedImageId( env, wpPath, postId ) );
	if ( key === 'featured_image_url' )
		return getAttachmentUrl(
			env,
			wpPath,
			getFeaturedImageId( env, wpPath, postId )
		);
	if ( key === 'featured_image_title' )
		return getAttachmentField(
			env,
			wpPath,
			getFeaturedImageId( env, wpPath, postId ),
			'title'
		);
	if ( key === 'featured_image_caption' )
		return getAttachmentField(
			env,
			wpPath,
			getFeaturedImageId( env, wpPath, postId ),
			'caption'
		);

	if ( key.startsWith( 'acf_' ) ) {
		const fieldName = key.slice( 4 );
		const php = `
$id = (int) ${ JSON.stringify( postId ) };
$field = ${ JSON.stringify( fieldName ) };
$value = function_exists( 'get_field' ) ? get_field( $field, $id, false ) : get_post_meta( $id, $field, true );
echo wp_json_encode([ 'value' => $value ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
		const out = wpEvalJson( env, wpPath, php );
		return out && Object.prototype.hasOwnProperty.call( out, 'value' )
			? out.value
			: '';
	}

	if ( key.startsWith( 'yoast_' ) || key.startsWith( '_yoast_wpseo' ) ) {
		const metaKey = key.startsWith( 'yoast_' ) ? key.slice( 6 ) : key;
		const php = `
$id = (int) ${ JSON.stringify( postId ) };
$meta = ${ JSON.stringify( metaKey ) };
echo wp_json_encode([ 'value' => (string) get_post_meta( $id, $meta, true ) ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
		const out = wpEvalJson( env, wpPath, php );
		return out && Object.prototype.hasOwnProperty.call( out, 'value' )
			? out.value
			: '';
	}

	if ( ct === 'comment' )
		return getCommentContent( env, wpPath, postId ).content;
	if ( ct === 'user' )
		return getUserDisplayName( env, wpPath, postId ).display_name;
	if ( ct === 'taxonomy' )
		return getTermName( env, wpPath, postId, 'category' ).name;
	if ( ct === 'database_table' ) {
		return getDbTableValById(
			env,
			wpPath,
			dbTableName || ensureTempUpdaterDbTable( env, wpPath ).table,
			postId
		).val;
	}

	return '';
}

function computeExpectedValue( env, wpPath, mode, beforeValue ) {
	const kind = String( mode || 'uppercase' )
		.trim()
		.toLowerCase();
	if ( kind === 'raw' ) return String( beforeValue ?? '' );
	if ( kind === 'uppercase' ) return uppercaseAscii( beforeValue );
	if ( kind === 'numeric_plus_one' ) {
		const n = Number(
			String( beforeValue ?? '' ).replace( /[^0-9.-]/g, '' )
		);
		return Number.isFinite( n )
			? String( n + 1 )
			: String( beforeValue ?? '' );
	}
	if ( kind === 'next_attachment_url' ) {
		const currentId = Number(
			String( beforeValue ?? '' ).replace( /[^0-9]/g, '' )
		);
		const nextId = Number.isFinite( currentId ) ? currentId + 1 : 0;
		return getAttachmentUrl( env, wpPath, nextId );
	}
	return String( beforeValue ?? '' );
}

function computeExpectedValueFromPipeline(
	env,
	wpPath,
	functionId,
	beforeValue
) {
	const functionIds = String( functionId || '' )
		.split( ',' )
		.map( ( id ) => id.trim() )
		.filter( Boolean );
	if ( ! functionIds.length ) return beforeValue;

	const php = `
$value = ${ JSON.stringify( beforeValue ) };
$ids = ${ JSON.stringify( functionIds ) };
$context = array('source' => 'content-updater-check');
$value = \\RockStarLab\\ImportExport\\Helper\\Field_Transformation_Bridge::apply($value, $ids, $context);
echo wp_json_encode(['value' => $value], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	const out = wpEvalJson( env, wpPath, php );
	return out && Object.prototype.hasOwnProperty.call( out, 'value' )
		? out.value
		: beforeValue;
}

function normalizeCommentHtmlForCompare( s ) {
	return (
		String( s || '' )
			.replace( /\r\n/g, '\n' )
			.replace( /\r/g, '\n' )
			// Ignore rel attribute variants injected by WP on comment links.
			.replace( /\srel="[^"]*"/gi, '' )
			// Normalize <a ...> tag name casing.
			.replace( /<\/?a\b/gi, ( m ) => m.toLowerCase() )
			// Normalize attribute name casing.
			.replace( /\bhref=/gi, 'href=' )
			// Normalize URL scheme casing inside attributes.
			.replace( /"https:\/\//gi, '"https://' )
			.replace( /"http:\/\//gi, '"http://' )
	);
}

async function run() {
	const env = loadEnv();

	const artifactsRoot = path.resolve(
		os.tmpdir(),
		'rsl-ie-aie-content-updater-check',
		nowStamp()
	);
	mkdirp( artifactsRoot );

	const summary = {
		startedAt: new Date().toISOString(),
		target: { baseUrl: env.target.baseUrl, wpPath: env.target.wpPath },
		result: null,
		issues: [],
		finishedAt: null,
	};

	const userDataDir = path.join( artifactsRoot, 'playwright-profile' );
	const ctx = await chromium.launchPersistentContext( userDataDir, {
		headless: env.headless,
	} );
	const page = await ctx.newPage();

	try {
		await gotoAdminPage(
			page,
			env.target,
			'/wp-admin/admin.php?page=rsl-ie-content-updater'
		);
		await page.waitForSelector( '#rsl-ie-content-updater', {
			timeout: 60_000,
		} );

		const ct =
			env.contentType === 'page' || env.contentType === 'pages'
				? 'page'
				: env.contentType === 'comment' ||
				  env.contentType === 'comments'
				? 'comment'
				: env.contentType === 'user' || env.contentType === 'users'
				? 'user'
				: env.contentType === 'woo_product' ||
				  env.contentType === 'product' ||
				  env.contentType === 'products'
				? 'woo_product'
				: env.contentType === 'database_table' ||
				  env.contentType === 'db_table' ||
				  env.contentType === 'mysql_table'
				? 'database_table'
				: env.contentType === 'taxonomy' ||
				  env.contentType === 'taxonomy_terms' ||
				  env.contentType === 'term' ||
				  env.contentType === 'terms'
				? 'taxonomy'
				: env.contentType === 'portfolio'
				? 'portfolio'
				: 'post';

		let objectId = 0;
		let beforeValue = '';
		let expectedValue = '';
		let fieldKey = '';
		let step1Value = ct;
		let needsCptSelector = false;
		let postType = ct === 'page' ? 'page' : ct === 'post' ? 'post' : '';
		let taxonomySlug = 'category';
		let dbTableName = '';
		let dbRowId = 0;

		if ( ct === 'comment' ) {
			objectId = pickOneCommentId( env, env.target.wpPath );
			if ( ! objectId ) throw new Error( 'No comments found' );
			const before = getCommentContent(
				env,
				env.target.wpPath,
				objectId
			);
			beforeValue = before.content;
		} else if ( ct === 'user' ) {
			const picked = ensureUpdaterTestUserId( env, env.target.wpPath );
			objectId = Number( picked.id || 0 );
			if ( ! objectId )
				throw new Error(
					'No users found and failed to create test user'
				);
			const before = getUserDisplayName(
				env,
				env.target.wpPath,
				objectId
			);
			beforeValue = before.display_name;
		} else if ( ct === 'woo_product' ) {
			const picked = ensureOneProductId( env, env.target.wpPath );
			objectId = Number( picked.id || 0 );
			if ( ! objectId )
				throw new Error(
					'No products found and failed to create product'
				);
			const before = getPostTitle( env, env.target.wpPath, objectId );
			beforeValue = before.title;
			step1Value = 'woo_product';
			postType = 'product';
		} else if ( ct === 'database_table' ) {
			const created = ensureTempUpdaterDbTable( env, env.target.wpPath );
			dbTableName =
				created && created.table ? String( created.table ) : '';
			dbRowId = Number( created && created.id ? created.id : 0 );
			if ( ! dbTableName || ! dbRowId )
				throw new Error(
					'Failed to create temp database table for updater test'
				);
			objectId = dbRowId;
			const before = getDbTableValById(
				env,
				env.target.wpPath,
				dbTableName,
				objectId
			);
			beforeValue = before.val;
			step1Value = 'database_table';
		} else if ( ct === 'taxonomy' ) {
			objectId = pickOneTermIdByTaxonomy(
				env,
				env.target.wpPath,
				taxonomySlug
			);
			if ( ! objectId )
				throw new Error(
					`No terms found for taxonomy=${ taxonomySlug }`
				);
			const before = getTermName(
				env,
				env.target.wpPath,
				objectId,
				taxonomySlug
			);
			beforeValue = before.name;
		} else if ( ct === 'portfolio' ) {
			postType = 'portfolio';
			objectId = pickOneIdByPostType( env, env.target.wpPath, postType );
			if ( ! objectId )
				throw new Error(
					`No content found for post_type=${ postType }`
				);
			const before = getPostTitle( env, env.target.wpPath, objectId );
			beforeValue = before.title;

			// Prefer a dedicated "post_type_portfolio" card if PRO registers it; otherwise use "custom_post_types".
			step1Value = 'portfolio';
			needsCptSelector = true;
		} else {
			postType = ct === 'page' ? 'page' : 'post';
			objectId = pickOneIdByPostType( env, env.target.wpPath, postType );
			if ( ! objectId )
				throw new Error(
					`No content found for post_type=${ postType }`
				);
			const before = getPostTitle( env, env.target.wpPath, objectId );
			beforeValue = before.title;
		}

		const defaultFieldKey =
			ct === 'comment'
				? 'comment_content'
				: ct === 'user'
				? 'display_name'
				: ct === 'taxonomy'
				? 'name'
				: ct === 'database_table'
				? 'val'
				: 'post_title';
		fieldKey = env.fieldKeyOverride || defaultFieldKey;
		const functionId = env.functionIdOverride || 'snippet_uppercase';
		beforeValue = getFieldValueByKey(
			env,
			env.target.wpPath,
			ct,
			objectId,
			fieldKey,
			{ dbTableName }
		);
		const expectedMode =
			env.expectedMode ||
			( fieldKey === 'featured_image_url'
				? 'next_attachment_url'
				: fieldKey === 'featured_image_id' ||
				  fieldKey === 'acf_image_field'
				? 'numeric_plus_one'
				: 'uppercase' );
		if ( env.expectedValueOverride !== '' ) {
			expectedValue = env.expectedValueOverride;
		} else if (
			[ 'executor', 'function', 'pipeline' ].includes( expectedMode )
		) {
			expectedValue = computeExpectedValueFromPipeline(
				env,
				env.target.wpPath,
				functionId,
				beforeValue
			);
		} else {
			expectedValue = computeExpectedValue(
				env,
				env.target.wpPath,
				expectedMode,
				beforeValue
			);
		}

		// Step 1: select content type; click next and REQUIRED: set dont-show-again on backup modal.
		let selectedCt = '';
		if ( ct === 'portfolio' ) {
			selectedCt = await selectUpdaterContentTypeOnStep1Any( page, [
				'post_type_portfolio',
				'portfolio',
				'custom_post_types',
			] );
			if ( ! selectedCt ) {
				throw new Error(
					'Portfolio updater content type not found (expected one of: post_type_portfolio, portfolio, custom_post_types)'
				);
			}
			if ( selectedCt !== 'custom_post_types' ) {
				needsCptSelector = false;
			}
		} else if ( ct === 'woo_product' ) {
			selectedCt = await selectUpdaterContentTypeOnStep1Any( page, [
				'woo_product',
				'product',
				'post_type_product',
				'custom_post_types',
			] );
			if ( ! selectedCt ) {
				throw new Error(
					'WooCommerce product updater content type not found (expected one of: woo_product, product, post_type_product, custom_post_types)'
				);
			}
			if ( selectedCt !== 'custom_post_types' ) {
				needsCptSelector = false;
			} else {
				needsCptSelector = true;
			}
		} else if ( ct === 'database_table' ) {
			selectedCt = await selectUpdaterContentTypeOnStep1Any( page, [
				'database_table',
				'database',
			] );
			if ( ! selectedCt ) {
				throw new Error(
					'Database table updater content type not found (expected: database_table)'
				);
			}
		} else {
			selectedCt = ct;
			await selectUpdaterContentTypeOnStep1( page, ct );
		}
		await clickUpdaterNext( page, { expectBackupModal: true } );

		const jobDataType = selectedCt || ct;

		// If using generic CPT mode, select the concrete post type first.
		if ( needsCptSelector ) {
			await addStep2PostTypeSelectorFilter( page, postType );
		}

		// For taxonomy, we must select a taxonomy first (required filter).
		if ( ct === 'taxonomy' ) {
			await addStep2TaxonomySelectorFilter( page, taxonomySlug );
		}

		// For database_table, we must select a table first (required before adding filters).
		if ( ct === 'database_table' ) {
			await selectStep2DatabaseTable( page, dbTableName );
		}

		// Step 2: filter by a single ID to keep the update small and deterministic.
		await setStep2IdEqualsFilter( page, objectId, {
			fieldPreferredValues:
				ct === 'comment'
					? [ 'comment_ID' ]
					: ct === 'taxonomy'
					? [ 'term_id' ]
					: ct === 'database_table'
					? [ 'id', 'ID' ]
					: [ 'ID' ],
		} );
		await clickUpdaterNext( page );

		// Step 3: select the requested field.
		await selectFieldOnStep3( page, fieldKey );
		await clickUpdaterNext( page );

		// Step 4: assign the configured function pipeline.
		await assignFunctionToField( page, fieldKey, functionId );
		await clickUpdaterNext( page );

		// Step 5: run update.
		const minUpdatedAt = wp( env, env.target.wpPath, [
			'eval',
			'echo current_time("mysql");',
		] );
		const stats = await startUpdateAndWaitForResults( page );
		const jobRow = waitForUpdateJobCompletion( env, env.target.wpPath, {
			dataType: jobDataType,
			minUpdatedAt,
			timeoutMs: 10 * 60_000,
		} );
		if ( ! jobRow )
			throw new Error( 'Updater job did not complete in time' );

		const afterValue = getFieldValueByKey(
			env,
			env.target.wpPath,
			ct,
			objectId,
			fieldKey,
			{ dbTableName }
		);

		summary.result = {
			contentType: ct,
			objectId,
			field: fieldKey,
			function: functionId,
			beforeValue,
			expectedValue,
			afterValue,
			updateStats: stats,
			job: {
				id: Number( jobRow.id || 0 ) || null,
				status: String( jobRow.status || '' ),
				total_items: Number( jobRow.total_items || 0 ),
				processed_items: Number( jobRow.processed_items || 0 ),
				updated_items: Number( jobRow.imported_items || 0 ),
				skipped_items: Number( jobRow.skipped_items || 0 ),
				error_items: Number( jobRow.error_items || 0 ),
			},
		};

		// For deterministic E2E, require only one item to be processed for object-ID tests.
		if ( summary.result.job.processed_items !== 1 ) {
			summary.issues.push( {
				kind: 'unexpected-processed-count',
				expected: 1,
				actual: summary.result.job.processed_items,
				total_items: summary.result.job.total_items,
			} );
		}

		const actualComparable =
			ct === 'comment'
				? normalizeCommentHtmlForCompare( afterValue )
				: String( afterValue );
		const expectedComparable =
			ct === 'comment'
				? normalizeCommentHtmlForCompare( expectedValue )
				: String( expectedValue );

		if ( String( actualComparable ) !== String( expectedComparable ) ) {
			summary.issues.push( {
				kind: 'value-mismatch',
				expected: expectedComparable,
				actual: actualComparable,
			} );
		}
		if ( stats && typeof stats.errors === 'number' && stats.errors > 0 ) {
			summary.issues.push( { kind: 'updater-errors', stats } );
		}

		summary.finishedAt = new Date().toISOString();
		if ( summary.issues.length ) process.exitCode = 1;
		console.log( JSON.stringify( summary, null, 2 ) );
	} catch ( e ) {
		summary.issues.push( {
			kind: 'exception',
			message: String( e && e.message ? e.message : e ),
		} );
		summary.finishedAt = new Date().toISOString();
		process.exitCode = 1;
		console.log( JSON.stringify( summary, null, 2 ) );
	} finally {
		await page.close().catch( () => {} );
		await ctx.close().catch( () => {} );
		rmrf( artifactsRoot );
	}
}

run().catch( ( e ) => {
	console.error( e );
	process.exitCode = 1;
} );
