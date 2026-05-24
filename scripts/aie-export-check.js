/**
 * Manual E2E (Playwright): Export wizard checks
 *
 * What it covers:
 * - Opens `/wp-admin/admin.php?page=rsl-ie-export`
 * - Runs exports for all available content types (skips disabled/locked)
 * - Tries multiple options (CSV delimiter, JSON format)
 * - Tries filters (ID equals; required selectors for taxonomy/custom_post_types)
 * - Downloads export files and compares basic expectations (count + sample fields)
 *
 * Usage:
 *   node scripts/aie-export-check.js
 *
 * Env (defaults read from .env.e2e if present):
 *   AIE_SOURCE_URL, AIE_SOURCE_ADMIN_USER, AIE_SOURCE_ADMIN_PASSWORD
 *   AIE_HEADLESS=true|false
 *   AIE_SOURCE_WP_PATH=/path/to/wp/root
 *   AIE_LOCAL_PHP=/path/to/php (Local.app bundled PHP works well)
 *   AIE_WP_BIN=/path/to/wp (wp-cli wrapper)
 *   AIE_EXPORT_TYPES=post,page,custom_post_types,media,menu,user,comment,taxonomy,woo_product,woo_order,woo_coupon,woo_attribute,database_table
 */

const fs = require( 'fs' );
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

	const typesRaw = String(
		get(
			'AIE_EXPORT_TYPES',
			'post,page,custom_post_types,media,menu,user,comment,taxonomy,woo_product,woo_order,woo_coupon,woo_attribute,database_table'
		)
	);
	const types = typesRaw
		.split( ',' )
		.map( ( x ) => String( x ).trim() )
		.filter( Boolean );

	const sourceWpPathDefault = path.resolve( process.cwd(), '../../..' );

	const localPhpFromLocalApp =
		'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php';
	const localPhpDefault = fs.existsSync( localPhpFromLocalApp )
		? localPhpFromLocalApp
		: 'php';

	return {
		headless,
		types,
		source: {
			baseUrl: get( 'AIE_SOURCE_URL', 'http://aie.local' ),
			username: get( 'AIE_SOURCE_ADMIN_USER', 'admin' ),
			password: get( 'AIE_SOURCE_ADMIN_PASSWORD', 'admin' ),
			wpPath: String( get( 'AIE_SOURCE_WP_PATH', sourceWpPathDefault ) ),
		},
		localPhp: String( get( 'AIE_LOCAL_PHP', localPhpDefault ) ),
		wpBin: String( get( 'AIE_WP_BIN', '/opt/homebrew/bin/wp' ) ),
	};
}

function wp( env, args, { trim = true } = {} ) {
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
		[ ...phpArgs, env.wpBin, `--path=${ env.source.wpPath }`, ...args ],
		{
			encoding: 'utf8',
			stdio: [ 'ignore', 'pipe', 'pipe' ],
		}
	);
	return trim ? String( out ).trim() : String( out );
}

function wpEval( env, code ) {
	return wp( env, [ 'eval', code ], { trim: true } );
}

function wpEvalJson( env, code ) {
	const raw = wp( env, [ 'eval', code ], { trim: true } );
	try {
		return JSON.parse( raw || 'null' );
	} catch {
		return null;
	}
}

async function ensureLoggedIn( page, { baseUrl, username, password } ) {
	// If logged-in, wp-admin bar is visible.
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

async function gotoAdminPage( page, env, adminPathWithQuery ) {
	await page.goto( `${ env.source.baseUrl }${ adminPathWithQuery }`, {
		waitUntil: 'domcontentloaded',
	} );
	if ( await page.locator( 'form#loginform' ).count() ) {
		await ensureLoggedIn( page, env.source );
		await page.goto( `${ env.source.baseUrl }${ adminPathWithQuery }`, {
			waitUntil: 'domcontentloaded',
		} );
	}
}

async function waitStep( page, stepNum ) {
	await page.waitForSelector( `.rsl-ie-step-${ stepNum }.active`, {
		timeout: 45_000,
	} );
}

async function clickNextStep( page ) {
	const next = page.locator( '.rsl-ie-step.active .rsl-ie-next-step' );
	await next.waitFor( { state: 'visible', timeout: 45_000 } );
	// Some steps disable "Next" until async validation/count completes.
	await page
		.waitForFunction(
			() => {
				const btn = document.querySelector(
					'.rsl-ie-step.active .rsl-ie-next-step'
				);
				return btn && ! btn.disabled;
			},
			null,
			{ timeout: 60_000 }
		)
		.catch( () => null );
	const enabled = await next.isEnabled().catch( () => false );
	if ( ! enabled ) {
		const stepClass = await page
			.locator( '.rsl-ie-step.active' )
			.first()
			.getAttribute( 'class' )
			.catch( () => '' );
		throw new Error(
			`Next Step button stayed disabled (activeStep=${
				stepClass || 'unknown'
			})`
		);
	}
	await next.click();
}

async function selectContentType( page, contentType ) {
	// Only select if enabled.
	const input = page
		.locator(
			`.rsl-ie-step-1.active input[name="content_type"][value="${ contentType }"]`
		)
		.first();
	if ( ! ( await input.count() ) ) return false;
	const disabled = await input.isDisabled().catch( () => true );
	if ( disabled ) return false;

	const label = page
		.locator( '.rsl-ie-step-1.active label.rsl-ie-content-type', {
			has: input,
		} )
		.first();
	if ( await label.count() ) {
		await label.click( { force: true } );
	} else {
		await page.evaluate( ( ct ) => {
			const el = document.querySelector(
				`.rsl-ie-step-1.active input[name="content_type"][value="${ ct }"]`
			);
			if ( ! el ) return;
			el.checked = true;
			el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			el.dispatchEvent( new Event( 'click', { bubbles: true } ) );
		}, contentType );
	}
	return true;
}

async function resetWizardToStep1( page, env ) {
	await gotoAdminPage( page, env, '/wp-admin/admin.php?page=rsl-ie-export' );
	await page.waitForSelector( '#rsl-ie-export', { timeout: 30_000 } );
	await waitStep( page, 1 );
}

async function addFilterRow( page ) {
	const btn = page
		.locator( '.rsl-ie-step-2.active .rsl-ie-add-filter' )
		.first();
	await btn.waitFor( { state: 'visible', timeout: 30_000 } );
	await btn.click();
	const rows = page.locator( '.rsl-ie-step-2.active .rsl-ie-filter-row' );
	await rows.last().waitFor( { state: 'visible', timeout: 30_000 } );
	return rows.last();
}

async function selectFilterFieldByDataType( page, row, dataType ) {
	const ok = await page.evaluate(
		( { rowSelector, dataTypeWanted } ) => {
			const rowEl = document.querySelector( rowSelector );
			if ( ! rowEl ) return false;
			const sel = rowEl.querySelector( 'select.rsl-ie-filter-field' );
			if ( ! sel ) return false;
			const opts = Array.from( sel.querySelectorAll( 'option' ) );
			const found = opts.find(
				( o ) => ( o.dataset && o.dataset.type ) === dataTypeWanted
			);
			if ( ! found ) return false;
			sel.value = found.value;
			sel.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			return true;
		},
		{
			rowSelector: await row.evaluate(
				( el ) => el.getAttribute( 'data-test-row' ) || ''
			),
			dataTypeWanted: dataType,
		}
	);
	return ok;
}

async function tagRowForEval( row ) {
	// Add a stable attribute for page.evaluate selection.
	const tag = `row-${ Date.now() }-${ Math.random()
		.toString( 16 )
		.slice( 2 ) }`;
	await row.evaluate(
		( el, t ) => el.setAttribute( 'data-test-row', t ),
		tag
	);
	return `[data-test-row="${ tag }"]`;
}

async function selectOptionByValueOrText( select, { value, textRe } ) {
	const options = select.locator( 'option' );
	const count = await options.count();
	const items = [];
	for ( let i = 0; i < count; i++ ) {
		const opt = options.nth( i );
		const v = await opt.getAttribute( 'value' );
		const t = ( await opt.innerText().catch( () => '' ) ) || '';
		items.push( { v: v ?? '', t: t.trim() } );
	}
	if ( value && items.some( ( x ) => x.v === value ) ) {
		await select.selectOption( { value } );
		return value;
	}
	if ( textRe ) {
		const found = items.find( ( x ) => textRe.test( x.t ) );
		if ( found ) {
			await select.selectOption( { value: found.v } );
			return found.v;
		}
	}
	const firstNonEmpty = items.find( ( x ) => x.v && x.v !== 'custom' );
	if ( firstNonEmpty ) {
		await select.selectOption( { value: firstNonEmpty.v } );
		return firstNonEmpty.v;
	}
	return '';
}

async function configureRequiredSelectorsOnStep2( page, env, contentType ) {
	if ( contentType === 'custom_post_types' ) {
		const row = await addFilterRow( page );
		const rowSel = await tagRowForEval( row );

		// Choose the special field that turns value into a post type selector.
		// In UI it is the "_post_type" filter option.
		await page
			.locator( `${ rowSel } select.rsl-ie-filter-field` )
			.selectOption( { value: '_post_type' } );

		const postTypeSelect = page
			.locator( `${ rowSel } select.rsl-ie-post-type-selector` )
			.first();
		await postTypeSelect.waitFor( { state: 'visible', timeout: 45_000 } );
		// Wait for AJAX to populate options.
		await page
			.waitForFunction(
				( sel ) => {
					const el = document.querySelector( sel );
					return el && el.querySelectorAll( 'option' ).length > 1;
				},
				`${ rowSel } select.rsl-ie-post-type-selector`,
				{ timeout: 60_000 }
			)
			.catch( () => null );
		// Prefer portfolio, otherwise first non-empty.
		await selectOptionByValueOrText( postTypeSelect, {
			value: 'portfolio',
			textRe: /portfolio/i,
		} );
		return {
			customPostType: await postTypeSelect.inputValue().catch( () => '' ),
		};
	}

	if ( contentType === 'taxonomy' ) {
		const row = await addFilterRow( page );
		const rowSel = await tagRowForEval( row );

		// In UI it is the "_taxonomy" filter option.
		await page
			.locator( `${ rowSel } select.rsl-ie-filter-field` )
			.selectOption( { value: '_taxonomy' } );

		const taxSelect = page
			.locator( `${ rowSel } select.rsl-ie-taxonomy-selector` )
			.first();
		await taxSelect.waitFor( { state: 'visible', timeout: 45_000 } );
		await page
			.waitForFunction(
				( sel ) => {
					const el = document.querySelector( sel );
					return el && el.querySelectorAll( 'option' ).length > 1;
				},
				`${ rowSel } select.rsl-ie-taxonomy-selector`,
				{ timeout: 60_000 }
			)
			.catch( () => null );
		await selectOptionByValueOrText( taxSelect, {
			value: 'category',
			textRe: /category/i,
		} );
		return { taxonomy: await taxSelect.inputValue().catch( () => '' ) };
	}

	if ( contentType === 'database_table' ) {
		const tableSel = page
			.locator( '.rsl-ie-step-2.active #rsl-ie-table-name' )
			.first();
		await tableSel.waitFor( { state: 'visible', timeout: 45_000 } );
		// Wait for tables to load (option count > 1).
		await page.waitForFunction( () => {
			const sel = document.querySelector(
				'.rsl-ie-step-2.active #rsl-ie-table-name'
			);
			return sel && sel.querySelectorAll( 'option' ).length > 1;
		} );

		// Prefer wp_users if present, else first non-empty.
		await selectOptionByValueOrText( tableSel, {
			value: 'wp_users',
			textRe: /users/i,
		} );
		return { tableName: await tableSel.inputValue().catch( () => '' ) };
	}

	return {};
}

async function addIdEqualsFilterIfPossible(
	page,
	env,
	{ contentType, idValue }
) {
	const row = await addFilterRow( page );
	const rowSel = await tagRowForEval( row );

	// Select ID-like field (prefer known IDs; fallback to first non-empty).
	const fieldSel = page
		.locator( `${ rowSel } select.rsl-ie-filter-field` )
		.first();
	await fieldSel.waitFor( { state: 'visible', timeout: 30_000 } );
	const preferredValues = [
		'ID',
		'term_id',
		'user_id',
		'comment_ID',
		'attribute_id',
		'order_id',
		'coupon_id',
	];
	let selectedValue = '';
	for ( const v of preferredValues ) {
		// eslint-disable-next-line no-await-in-loop
		const has = await fieldSel.locator( `option[value="${ v }"]` ).count();
		if ( has ) {
			selectedValue = v;
			break;
		}
	}
	if ( selectedValue ) {
		await fieldSel.selectOption( { value: selectedValue } );
	} else {
		// fallback: first non-empty option
		const first = await fieldSel
			.locator( 'option[value]' )
			.first()
			.getAttribute( 'value' )
			.catch( () => '' );
		if ( first ) await fieldSel.selectOption( { value: first } );
	}

	const condition = page
		.locator( `${ rowSel } select.rsl-ie-filter-condition` )
		.first();
	await condition.waitFor( { state: 'visible', timeout: 30_000 } );
	// Wait for conditions to populate after field change.
	await page
		.locator(
			`${ rowSel } select.rsl-ie-filter-condition option[value="equals"]`
		)
		.first()
		.waitFor( { state: 'attached', timeout: 20_000 } )
		.catch( () => null );
	await selectOptionByValueOrText( condition, {
		value: 'equals',
		textRe: /equals|=/i,
	} );

	const input = page
		.locator(
			`${ rowSel } input.rsl-ie-filter-value, select.rsl-ie-filter-value`
		)
		.first();
	await input.waitFor( { state: 'attached', timeout: 30_000 } );
	if ( await input.evaluate( ( el ) => el.tagName === 'SELECT' ) ) {
		// For selectors, try to select by value.
		await input.selectOption( String( idValue ) ).catch( () => {} );
	} else {
		const v = String( idValue );
		await input.fill( v );
		let got = await input.evaluate( ( el ) => el.value ).catch( () => '' );
		if ( String( got || '' ) !== v ) {
			await input.evaluate( ( el, value ) => {
				el.value = value;
				el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}, v );
			got = await input.evaluate( ( el ) => el.value ).catch( () => '' );
		}
		if ( String( got || '' ) !== v ) {
			// Last resort for number inputs: click+type.
			await input.click( { force: true } ).catch( () => {} );
			await input.press( 'Control+A' ).catch( () => {} );
			await input.type( v, { delay: 10 } ).catch( () => {} );
		}
	}
}

async function addSlugEqualsFilter( page, { slugValue } ) {
	const row = await addFilterRow( page );
	const rowSel = await tagRowForEval( row );

	const fieldSel = page
		.locator( `${ rowSel } select.rsl-ie-filter-field` )
		.first();
	await fieldSel.waitFor( { state: 'visible', timeout: 30_000 } );
	if ( await fieldSel.locator( 'option[value="slug"]' ).count() ) {
		await fieldSel.selectOption( { value: 'slug' } );
	} else {
		const first = await fieldSel
			.locator( 'option[value]' )
			.first()
			.getAttribute( 'value' )
			.catch( () => '' );
		if ( first ) await fieldSel.selectOption( { value: first } );
	}

	const condition = page
		.locator( `${ rowSel } select.rsl-ie-filter-condition` )
		.first();
	await condition.waitFor( { state: 'visible', timeout: 30_000 } );
	await page
		.locator(
			`${ rowSel } select.rsl-ie-filter-condition option[value="equals"]`
		)
		.first()
		.waitFor( { state: 'attached', timeout: 20_000 } )
		.catch( () => null );
	await selectOptionByValueOrText( condition, {
		value: 'equals',
		textRe: /equals|=/i,
	} );

	const input = page
		.locator( `${ rowSel } input.rsl-ie-filter-value` )
		.first();
	await input.waitFor( { state: 'attached', timeout: 30_000 } );
	await input.fill( String( slugValue || '' ) );
}

async function selectAllFieldsOnStep3( page ) {
	await waitStep( page, 3 );

	// Wait for dynamic fields to load (best effort).
	await page.waitForTimeout( 600 );

	const addAllButtons = page.locator(
		'.rsl-ie-step-3.active .rsl-ie-add-all-fields'
	);
	const btnCount = await addAllButtons.count();
	for ( let i = 0; i < btnCount; i++ ) {
		const btn = addAllButtons.nth( i );
		if ( ! ( await btn.isVisible().catch( () => false ) ) ) continue;
		await btn.click();
		await page.waitForTimeout( 50 );
	}

	// Next should become enabled.
	const next = page.locator( '.rsl-ie-step-3.active .rsl-ie-next-step' );
	await next.waitFor( { state: 'visible', timeout: 30_000 } );
	await page.waitForFunction( () => {
		const btn = document.querySelector(
			'.rsl-ie-step-3.active .rsl-ie-next-step'
		);
		return btn && ! btn.disabled;
	} );
	await next.click();
}

async function configureFormatOnStep4( page, { format, csvDelimiter } ) {
	await waitStep( page, 4 );

	if ( format === 'json' ) {
		const jsonRadio = page
			.locator(
				'.rsl-ie-step-4.active input[name="format"][value="json"]'
			)
			.first();
		if ( await jsonRadio.count() ) {
			const label = page
				.locator( '.rsl-ie-step-4.active label.rsl-ie-format-option', {
					has: jsonRadio,
				} )
				.first();
			if ( await label.count() ) {
				await label.click( { force: true } );
			} else {
				await page.evaluate( () => {
					const el = document.querySelector(
						'.rsl-ie-step-4.active input[name="format"][value="json"]'
					);
					if ( ! el ) return;
					el.checked = true;
					el.dispatchEvent(
						new Event( 'change', { bubbles: true } )
					);
					el.dispatchEvent( new Event( 'click', { bubbles: true } ) );
				} );
			}
		}
	} else {
		const csvRadio = page
			.locator(
				'.rsl-ie-step-4.active input[name="format"][value="csv"]'
			)
			.first();
		if ( await csvRadio.count() ) {
			const label = page
				.locator( '.rsl-ie-step-4.active label.rsl-ie-format-option', {
					has: csvRadio,
				} )
				.first();
			if ( await label.count() ) {
				await label.click( { force: true } );
			} else {
				await page.evaluate( () => {
					const el = document.querySelector(
						'.rsl-ie-step-4.active input[name="format"][value="csv"]'
					);
					if ( ! el ) return;
					el.checked = true;
					el.dispatchEvent(
						new Event( 'change', { bubbles: true } )
					);
					el.dispatchEvent( new Event( 'click', { bubbles: true } ) );
				} );
			}
		}
	}

	if ( format === 'csv' && csvDelimiter ) {
		const delSel = page
			.locator( '.rsl-ie-step-4.active select[name="csv_delimiter"]' )
			.first();
		if ( await delSel.count() ) {
			// Options use literal values: ",", ";", "\\t", "|", "custom"
			await delSel
				.selectOption( { value: csvDelimiter } )
				.catch( () => {} );
		}
	}
}

async function startExportAndDownload( page, artifactsDir ) {
	await waitStep( page, 4 );
	// Starting export does NOT download immediately; it creates a background job and
	// advances to Step 5 with progress UI.
	await page.locator( '.rsl-ie-step-4.active .rsl-ie-start-export' ).click();

	await waitStep( page, 5 );
	const completeCard = page.locator( '.rsl-ie-export-complete-card' );
	await completeCard.waitFor( { state: 'visible', timeout: 10 * 60_000 } );

	// Download file from Step 5.
	const downloadBtn = page.locator( '.rsl-ie-download-file' ).first();
	await downloadBtn.waitFor( { state: 'visible', timeout: 60_000 } );
	const [ finalDl ] = await Promise.all( [
		page.waitForEvent( 'download', { timeout: 120_000 } ),
		downloadBtn.click(),
	] );
	const suggested = finalDl.suggestedFilename() || `export-${ Date.now() }`;
	const outPath = path.join( artifactsDir, suggested );
	await finalDl.saveAs( outPath );
	return outPath;
}

function parseCsv( content, delimiter = ',' ) {
	const rows = [];
	let row = [];
	let cur = '';
	let inQuotes = false;
	for ( let i = 0; i < content.length; i++ ) {
		const ch = content[ i ];
		const next = content[ i + 1 ];
		if ( inQuotes ) {
			if ( ch === '"' && next === '"' ) {
				cur += '"';
				i++;
				continue;
			}
			if ( ch === '"' ) {
				inQuotes = false;
				continue;
			}
			cur += ch;
			continue;
		}
		if ( ch === '"' ) {
			inQuotes = true;
			continue;
		}
		if ( ch === delimiter ) {
			row.push( cur );
			cur = '';
			continue;
		}
		if ( ch === '\n' ) {
			row.push( cur );
			rows.push( row );
			row = [];
			cur = '';
			continue;
		}
		if ( ch === '\r' ) continue;
		cur += ch;
	}
	row.push( cur );
	rows.push( row );
	// Trim trailing empty row.
	while (
		rows.length &&
		rows[ rows.length - 1 ].every(
			( c ) => String( c || '' ).trim() === ''
		)
	)
		rows.pop();
	return rows;
}

function readExportFile( filePath ) {
	const buf = fs.readFileSync( filePath );
	const text = buf.toString( 'utf8' );
	return text;
}

function guessDelimiterFromStep4( csvDelimiterValue ) {
	if ( ! csvDelimiterValue ) return ',';
	if ( csvDelimiterValue === '\\t' ) return '\t';
	return csvDelimiterValue;
}

function normalizeContentTypeToExpectedQuery( {
	exportType,
	customPostType,
	taxonomy,
	tableName,
} ) {
	if ( exportType === 'post' ) return { kind: 'post', postType: 'post' };
	if ( exportType === 'page' ) return { kind: 'post', postType: 'page' };
	if ( exportType === 'media' )
		return { kind: 'post', postType: 'attachment' };
	if ( exportType === 'woo_product' )
		return { kind: 'post', postType: 'product' };
	if ( exportType === 'woo_coupon' )
		return { kind: 'post', postType: 'shop_coupon' };
	if ( exportType === 'woo_order' ) return { kind: 'woo_order' };
	if ( exportType === 'custom_post_types' )
		return { kind: 'post', postType: String( customPostType || '' ) };
	if ( exportType === 'taxonomy' )
		return { kind: 'term', taxonomy: String( taxonomy || '' ) };
	if ( exportType === 'menu' ) return { kind: 'term', taxonomy: 'nav_menu' };
	if ( exportType === 'user' ) return { kind: 'user' };
	if ( exportType === 'comment' ) return { kind: 'comment' };
	if ( exportType === 'woo_attribute' ) return { kind: 'woo_attribute' };
	if ( exportType === 'database_table' )
		return { kind: 'db', tableName: String( tableName || '' ) };
	return { kind: 'unknown' };
}

function getExpectedCountAndSample( env, query ) {
	const code = ( () => {
		if ( query.kind === 'post' ) {
			return `
$pt = ${ JSON.stringify( String( query.postType || 'post' ) ) };
if (!$pt) { echo json_encode(['count'=>0,'sample'=>null]); return; }
$ids = get_posts([
  'post_type' => $pt,
  'post_status' => 'any',
  'posts_per_page' => 50,
  'orderby' => 'ID',
  'order' => 'ASC',
  'fields' => 'ids',
]);
$count = (int) wp_count_posts($pt)->publish;
// For attachments, wp_count_posts() doesn't reflect inherit well; do a cheap count query.
if ($pt === 'attachment') {
  $count = (int) count(get_posts(['post_type'=>'attachment','post_status'=>'inherit','posts_per_page'=>-1,'fields'=>'ids']));
} else {
  // Include all statuses by counting IDs; avoids edge-cases where wp_count_posts() omits custom statuses.
  $count = (int) count(get_posts(['post_type'=>$pt,'post_status'=>'any','posts_per_page'=>-1,'fields'=>'ids']));
}
$sample_id = !empty($ids) ? (int) $ids[0] : 0;
$sample = null;
if ($sample_id) {
  $title = (string) get_post_field('post_title', $sample_id);
  // WooCommerce coupons treat the code as case-insensitive and normalize to a canonical form.
  // The exporter uses WC_Coupon::get_code(), so align the expected sample title to that.
  if ($pt === 'shop_coupon' && class_exists('WC_Coupon')) {
    $c = new WC_Coupon($sample_id);
    if ($c && method_exists($c, 'get_code')) {
      $title = (string) $c->get_code();
    }
  }
  $sample = [
    'id' => $sample_id,
    'title' => $title,
    'slug' => (string) get_post_field('post_name', $sample_id),
    'status' => (string) get_post_status($sample_id),
  ];
}
echo json_encode(['count'=>$count,'sample'=>$sample]);
`;
		}
		if ( query.kind === 'term' ) {
			return `
$tax = ${ JSON.stringify( String( query.taxonomy || '' ) ) };
if (!$tax || !taxonomy_exists($tax)) { echo json_encode(['count'=>0,'sample'=>null]); return; }
$ids = get_terms(['taxonomy'=>$tax,'hide_empty'=>false,'fields'=>'ids']);
$count = is_array($ids) ? count($ids) : 0;
$sample_id = ($count > 0) ? (int) $ids[0] : 0;
$sample = null;
if ($sample_id) {
  $t = get_term($sample_id, $tax);
  if ($t && !is_wp_error($t)) {
    $sample = ['id'=>(int)$t->term_id,'name'=>(string)$t->name,'slug'=>(string)$t->slug];
  }
}
echo json_encode(['count'=>$count,'sample'=>$sample]);
`;
		}
		if ( query.kind === 'user' ) {
			return `
$users = get_users(['fields'=>['ID','user_login','user_email'],'number'=>50,'orderby'=>'ID','order'=>'ASC']);
$count = (int) count_users()['total_users'];
$sample = null;
if (!empty($users)) {
  $u = $users[0];
  $sample = ['id'=>(int)$u->ID,'login'=>(string)$u->user_login,'email'=>(string)$u->user_email];
}
echo json_encode(['count'=>$count,'sample'=>$sample]);
`;
		}
		if ( query.kind === 'comment' ) {
			return `
$ids = get_comments(['status'=>'all','number'=>50,'orderby'=>'comment_ID','order'=>'ASC','fields'=>'ids']);
$count = (int) get_comments(['status'=>'all','count'=>true]);
$sample_id = !empty($ids) ? (int) $ids[0] : 0;
$sample = null;
if ($sample_id) {
  $c = get_comment($sample_id);
  if ($c) {
    $sample = ['id'=>(int)$c->comment_ID,'content'=>(string)$c->comment_content];
  }
}
echo json_encode(['count'=>$count,'sample'=>$sample]);
`;
		}
		if ( query.kind === 'woo_order' ) {
			return `
if (!function_exists('wc_get_orders')) { echo json_encode(['count'=>0,'sample'=>null]); return; }
$ids = wc_get_orders(['limit'=>50,'orderby'=>'ID','order'=>'ASC','return'=>'ids']);
$count = (int) wc_get_orders(['limit'=>1,'paginate'=>true,'return'=>'ids'])->total;
$sample_id = !empty($ids) ? (int) $ids[0] : 0;
$sample = null;
if ($sample_id) {
  $o = wc_get_order($sample_id);
  if ($o) { $sample = ['id'=>$sample_id,'status'=>$o->get_status()]; }
}
echo json_encode(['count'=>$count,'sample'=>$sample]);
`;
		}
		if ( query.kind === 'woo_attribute' ) {
			return `
if (!function_exists('wc_get_attribute_taxonomies')) { echo json_encode(['count'=>0,'sample'=>null]); return; }
$attrs = wc_get_attribute_taxonomies();
$count = is_array($attrs) ? count($attrs) : 0;
$sample = null;
if (!empty($attrs)) {
  $a = $attrs[0];
  $sample = ['id'=>(int)$a->attribute_id,'name'=>(string)$a->attribute_name,'label'=>(string)$a->attribute_label];
}
echo json_encode(['count'=>$count,'sample'=>$sample]);
`;
		}
		if ( query.kind === 'db' ) {
			return `
global $wpdb;
$table = ${ JSON.stringify( String( query.tableName || '' ) ) };
if (!$table) { echo json_encode(['count'=>0,'sample'=>null]); return; }
// Basic sanitize: allow only [a-zA-Z0-9_]
if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) { echo json_encode(['count'=>0,'sample'=>null]); return; }
$count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
// Best-effort sample: first row as associative array.
$sample = $wpdb->get_row("SELECT * FROM {$table} LIMIT 1", ARRAY_A);
echo json_encode(['count'=>$count,'sample'=>$sample]);
`;
		}
		return `echo json_encode(['count'=>0,'sample'=>null]);`;
	} )();

	const out = wpEvalJson( env, code );
	return out && typeof out.count === 'number'
		? out
		: { count: 0, sample: null };
}

function findColumnIndex( headers, candidates ) {
	const lower = headers.map( ( h ) => String( h || '' ).toLowerCase() );
	for ( const c of candidates ) {
		const idx = lower.indexOf( String( c ).toLowerCase() );
		if ( idx !== -1 ) return idx;
	}
	// Fuzzy
	for ( const c of candidates ) {
		const lc = String( c ).toLowerCase();
		const idx = lower.findIndex(
			( h ) => h === lc || h.endsWith( `_${ lc }` ) || h.includes( lc )
		);
		if ( idx !== -1 ) return idx;
	}
	return -1;
}

function summarizeMismatch( { label, expected, actual } ) {
	return { label, expected, actual };
}

function verifyExportFile( {
	filePath,
	format,
	delimiterValue,
	expectedCount,
	sample,
	exportType,
} ) {
	const text = readExportFile( filePath );
	const issues = [];

	if ( format === 'json' ) {
		let data;
		try {
			data = JSON.parse( text );
		} catch ( e ) {
			issues.push(
				summarizeMismatch( {
					label: 'json_parse',
					expected: 'valid json',
					actual: String( e && e.message ? e.message : e ),
				} )
			);
			return issues;
		}
		const arr = Array.isArray( data )
			? data
			: data && Array.isArray( data.items )
			? data.items
			: null;
		if ( ! arr ) {
			issues.push(
				summarizeMismatch( {
					label: 'json_shape',
					expected: 'array or {items:[]}',
					actual: typeof data,
				} )
			);
			return issues;
		}
		if (
			typeof expectedCount === 'number' &&
			expectedCount >= 0 &&
			arr.length !== expectedCount
		) {
			issues.push(
				summarizeMismatch( {
					label: 'count',
					expected: expectedCount,
					actual: arr.length,
				} )
			);
		}
		if ( sample && sample.id ) {
			const idKey =
				Object.keys( arr[ 0 ] || {} ).find(
					( k ) => String( k ).toLowerCase() === 'id'
				) || 'ID';
			const found = arr.find(
				( x ) => String( x[ idKey ] ?? '' ) === String( sample.id )
			);
			if ( ! found )
				issues.push(
					summarizeMismatch( {
						label: 'sample_present',
						expected: `id=${ sample.id }`,
						actual: 'not found',
					} )
				);
		}
		return issues;
	}

	const delimiter = guessDelimiterFromStep4( delimiterValue );
	const rows = parseCsv( text, delimiter );
	if ( ! rows.length ) {
		issues.push(
			summarizeMismatch( {
				label: 'csv_rows',
				expected: '>0',
				actual: 0,
			} )
		);
		return issues;
	}

	const headers = rows[ 0 ] || [];
	const dataRows = rows.slice( 1 );
	if (
		typeof expectedCount === 'number' &&
		expectedCount >= 0 &&
		dataRows.length !== expectedCount
	) {
		issues.push(
			summarizeMismatch( {
				label: 'count',
				expected: expectedCount,
				actual: dataRows.length,
			} )
		);
	}

	if ( sample && sample.id ) {
		const idIdx = findColumnIndex( headers, [
			'ID',
			'id',
			'term_id',
			'user_id',
			'comment_ID',
			'attribute_id',
			'order_id',
			'coupon_id',
		] );
		if ( idIdx !== -1 ) {
			const found = dataRows.find(
				( r ) => String( r[ idIdx ] ?? '' ) === String( sample.id )
			);
			if ( ! found )
				issues.push(
					summarizeMismatch( {
						label: 'sample_present',
						expected: `id=${ sample.id }`,
						actual: 'not found',
					} )
				);
		}

		// Basic title/name check for known types if columns exist.
		if (
			exportType === 'post' ||
			exportType === 'page' ||
			exportType === 'custom_post_types' ||
			exportType === 'woo_product' ||
			exportType === 'woo_coupon'
		) {
			const titleIdx = findColumnIndex( headers, [
				'post_title',
				'title',
			] );
			if ( titleIdx !== -1 && sample.title ) {
				const row = dataRows.find(
					( r ) =>
						String( r[ titleIdx ] ?? '' ).trim() ===
						String( sample.title ).trim()
				);
				if ( ! row ) {
					issues.push(
						summarizeMismatch( {
							label: 'sample_title_present',
							expected: sample.title,
							actual: 'not found in post_title/title column',
						} )
					);
				}
			}
		}
		if ( exportType === 'taxonomy' || exportType === 'menu' ) {
			const nameIdx = findColumnIndex( headers, [ 'name', 'term_name' ] );
			if ( nameIdx !== -1 && sample.name ) {
				const row = dataRows.find(
					( r ) =>
						String( r[ nameIdx ] ?? '' ).trim() ===
						String( sample.name ).trim()
				);
				if ( ! row ) {
					issues.push(
						summarizeMismatch( {
							label: 'sample_name_present',
							expected: sample.name,
							actual: 'not found',
						} )
					);
				}
			}
		}
		if ( exportType === 'user' ) {
			const loginIdx = findColumnIndex( headers, [
				'user_login',
				'login',
			] );
			if ( loginIdx !== -1 && sample.login ) {
				const row = dataRows.find(
					( r ) =>
						String( r[ loginIdx ] ?? '' ).trim() ===
						String( sample.login ).trim()
				);
				if ( ! row ) {
					issues.push(
						summarizeMismatch( {
							label: 'sample_login_present',
							expected: sample.login,
							actual: 'not found',
						} )
					);
				}
			}
		}
	}

	return issues;
}

async function runOneExportCase(
	page,
	env,
	{ exportType, label, format, csvDelimiter, addIdFilter }
) {
	await resetWizardToStep1( page, env );
	const selected = await selectContentType( page, exportType );
	if ( ! selected )
		return {
			skipped: true,
			label,
			exportType,
			reason: 'disabled-or-missing',
		};

	await clickNextStep( page ); // step 2
	await waitStep( page, 2 );

	const required = await configureRequiredSelectorsOnStep2(
		page,
		env,
		exportType
	);

	// If required selectors failed to select a value, skip early (prevents false "no-items").
	if ( exportType === 'custom_post_types' && ! required.customPostType ) {
		return {
			skipped: true,
			label,
			exportType,
			reason: 'post-type-not-selected',
			required,
		};
	}
	if ( exportType === 'taxonomy' && ! required.taxonomy ) {
		return {
			skipped: true,
			label,
			exportType,
			reason: 'taxonomy-not-selected',
			required,
		};
	}
	if ( exportType === 'database_table' && ! required.tableName ) {
		return {
			skipped: true,
			label,
			exportType,
			reason: 'table-not-selected',
			required,
		};
	}

	// Force refresh after required selector changes (taxonomy/custom post type selectors attach handlers late).
	const refreshBtn = page
		.locator( '.rsl-ie-step-2.active .rsl-ie-refresh-count' )
		.first();
	if ( await refreshBtn.count() ) {
		await refreshBtn.click().catch( () => {} );
	}

	// Wait for item count to resolve (from "-" to number).
	await page
		.waitForFunction(
			() => {
				const el = document.querySelector(
					'.rsl-ie-step-2.active .rsl-ie-count-value'
				);
				if ( ! el ) return false;
				const t = String( el.textContent || '' ).trim();
				if ( ! t || t === '-' ) return false;
				return /^[0-9]+$/.test( t );
			},
			null,
			{ timeout: 60_000 }
		)
		.catch( () => null );

	// For taxonomy/database_table, "0" often indicates the selector hasn't been applied yet.
	// If we know there should be >0 items, wait briefly for it to become non-zero.
	if ( exportType === 'taxonomy' || exportType === 'database_table' ) {
		const preQuery = normalizeContentTypeToExpectedQuery( {
			exportType,
			...required,
		} );
		const preExpected = getExpectedCountAndSample( env, preQuery );
		if (
			preExpected &&
			typeof preExpected.count === 'number' &&
			preExpected.count > 0
		) {
			await page
				.waitForFunction(
					() => {
						const el = document.querySelector(
							'.rsl-ie-step-2.active .rsl-ie-count-value'
						);
						if ( ! el ) return false;
						const t = String( el.textContent || '' ).trim();
						if ( ! /^[0-9]+$/.test( t ) ) return false;
						return parseInt( t, 10 ) > 0;
					},
					null,
					{ timeout: 60_000 }
				)
				.catch( () => null );
		}
	}
	const countText = (
		await page
			.locator( '.rsl-ie-step-2.active .rsl-ie-count-value' )
			.innerText()
			.catch( () => '-' )
	).trim();
	const countNum = Number.parseInt( countText, 10 );
	if ( Number.isFinite( countNum ) && countNum === 0 ) {
		return {
			skipped: true,
			label,
			exportType,
			reason: 'no-items',
			required,
		};
	}

	// Optional ID equals filter (if we can derive an ID).
	let idUsed = null;
	if ( addIdFilter ) {
		const query = normalizeContentTypeToExpectedQuery( {
			exportType,
			...required,
		} );
		const expected0 = getExpectedCountAndSample( env, query );
		if ( expected0 && expected0.sample && expected0.sample.id ) {
			idUsed = expected0.sample.id;
			if ( exportType === 'taxonomy' && expected0.sample.slug ) {
				await addSlugEqualsFilter( page, {
					slugValue: expected0.sample.slug,
				} );
			} else {
				await addIdEqualsFilterIfPossible( page, env, {
					contentType: exportType,
					idValue: idUsed,
				} );
			}
		}
	}

	await clickNextStep( page ); // step 3
	await selectAllFieldsOnStep3( page );

	await configureFormatOnStep4( page, { format, csvDelimiter } );

	const artifactsDir = path.resolve(
		process.cwd(),
		'e2e',
		'artifacts',
		'aie-export-check'
	);
	fs.mkdirSync( artifactsDir, { recursive: true } );

	const filePath = await startExportAndDownload( page, artifactsDir );

	const query = normalizeContentTypeToExpectedQuery( {
		exportType,
		...required,
	} );
	const expected = getExpectedCountAndSample( env, query );

	let expectedCount = expected.count;
	let sample = expected.sample;

	// If we applied an ID filter, expected is 1 and sample should be that ID.
	if ( idUsed ) {
		expectedCount = 1;
		sample = { ...( sample || {} ), id: idUsed };
	}

	const issues = verifyExportFile( {
		filePath,
		format,
		delimiterValue: csvDelimiter || ',',
		expectedCount,
		sample,
		exportType,
	} );

	return {
		skipped: false,
		label,
		exportType,
		format,
		csvDelimiter: csvDelimiter || ',',
		required,
		filePath,
		expectedCount,
		issues,
	};
}

async function main() {
	const env = loadEnv();

	const browser = await chromium.launch( { headless: env.headless } );
	const context = await browser.newContext( {
		acceptDownloads: true,
		viewport: { width: 1440, height: 900 },
	} );
	const page = await context.newPage();

	try {
		const cases = [];

		// Baseline: all types CSV default.
		for ( const t of env.types ) {
			cases.push( {
				exportType: t,
				label: `${ t }:csv:default`,
				format: 'csv',
				csvDelimiter: ',',
				addIdFilter: false,
			} );
		}

		// Extra coverage: options + filters on key types.
		cases.push( {
			exportType: 'post',
			label: 'post:csv:semicolon',
			format: 'csv',
			csvDelimiter: ';',
			addIdFilter: false,
		} );
		cases.push( {
			exportType: 'post',
			label: 'post:json',
			format: 'json',
			csvDelimiter: ',',
			addIdFilter: false,
		} );
		cases.push( {
			exportType: 'post',
			label: 'post:csv:filter-id',
			format: 'csv',
			csvDelimiter: ',',
			addIdFilter: true,
		} );
		cases.push( {
			exportType: 'taxonomy',
			label: 'taxonomy:csv:filter-id',
			format: 'csv',
			csvDelimiter: ',',
			addIdFilter: true,
		} );
		cases.push( {
			exportType: 'custom_post_types',
			label: 'custom_post_types:csv:filter-id',
			format: 'csv',
			csvDelimiter: ',',
			addIdFilter: true,
		} );

		const results = [];
		for ( const c of cases ) {
			// Skip duplicates when base list doesn't include the type.
			if (
				! env.types.includes( c.exportType ) &&
				! [ 'post', 'taxonomy', 'custom_post_types' ].includes(
					c.exportType
				)
			)
				continue;
			// eslint-disable-next-line no-console
			console.log( `[export-check] running ${ c.label }` );
			let r;
			try {
				// eslint-disable-next-line no-await-in-loop
				r = await runOneExportCase( page, env, c );
			} catch ( e ) {
				r = {
					skipped: false,
					label: c.label,
					exportType: c.exportType,
					format: c.format,
					csvDelimiter: c.csvDelimiter,
					error: String( e && e.message ? e.message : e ),
				};
				// eslint-disable-next-line no-console
				console.log(
					`[export-check] error ${ c.label }: ${ r.error }`
				);
			}
			results.push( r );
			if ( r.skipped ) {
				// eslint-disable-next-line no-console
				console.log(
					`[export-check] skipped ${ c.label }: ${ r.reason }`
				);
				continue;
			}
			if ( r.error ) {
				// eslint-disable-next-line no-console
				console.log(
					`[export-check] error ${ c.label }: ${ r.error }`
				);
				continue;
			}
			if ( r.issues && r.issues.length ) {
				// eslint-disable-next-line no-console
				console.log(
					`[export-check] issues ${ c.label }:\n${ JSON.stringify(
						r.issues,
						null,
						2
					) }`
				);
			} else {
				// eslint-disable-next-line no-console
				console.log(
					`[export-check] ok ${ c.label } (expected=${ r.expectedCount }) -> ${ r.filePath }`
				);
			}
		}

		const issuesAll = results.filter(
			( r ) =>
				! r.skipped && ( ( r.issues && r.issues.length ) || r.error )
		);
		const outDir = path.resolve(
			process.cwd(),
			'e2e',
			'artifacts',
			'aie-export-check'
		);
		const summaryPath = path.join(
			outDir,
			`summary-${ new Date().toISOString().replace( /[:.]/g, '-' ) }.json`
		);
		fs.writeFileSync( summaryPath, JSON.stringify( { results }, null, 2 ) );

		// eslint-disable-next-line no-console
		console.log( `[export-check] summary saved: ${ summaryPath }` );

		if ( issuesAll.length ) {
			// eslint-disable-next-line no-console
			console.log(
				`[export-check] FAIL: ${
					issuesAll.length
				} case(s) with issues. First 10 labels:\n- ${ issuesAll
					.slice( 0, 10 )
					.map( ( x ) => x.label )
					.join( '\n- ' ) }`
			);
			process.exitCode = 2;
		} else {
			// eslint-disable-next-line no-console
			console.log( '[export-check] PASS: no issues found.' );
		}
	} finally {
		await context.close().catch( () => {} );
		await browser.close().catch( () => {} );
	}
}

main().catch( ( e ) => {
	// eslint-disable-next-line no-console
	console.error( e );
	process.exit( 1 );
} );
