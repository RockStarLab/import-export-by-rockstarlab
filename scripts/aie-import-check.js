/**
 * Manual E2E (Playwright): Import wizard checks (source aie.local -> target aie2.local)
 *
 * What it covers:
 * - Export from source using Export wizard with different filters
 * - Import into target using Import wizard with different options
 * - Verifies auto-mapping maps all source columns
 * - Verifies ACF snapshots are identical after import (for post/term/user objects where applicable)
 * - Re-imports a media-heavy export to ensure attachments don't duplicate (media_duplicate_mode=skip)
 *
 * Usage:
 *   node scripts/aie-import-check.js
 *
 * Env (defaults read from .env.e2e if present):
 *   AIE_SOURCE_URL, AIE_SOURCE_ADMIN_USER, AIE_SOURCE_ADMIN_PASSWORD
 *   AIE_TARGET_URL, AIE_TARGET_ADMIN_USER, AIE_TARGET_ADMIN_PASSWORD
 *   AIE_HEADLESS=true|false
 *   AIE_IMPORT_TYPES=post,page,custom_post_types,taxonomy,menu,user,media,woo_product,woo_order,woo_coupon,woo_attribute,database_table,comment
 *   AIE_SOURCE_WP_PATH=/path/to/source/wp/root
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

	const typesRaw = String(
		get(
			'AIE_IMPORT_TYPES',
			'post,page,custom_post_types,taxonomy,menu,user,media,woo_product,woo_order,woo_coupon,woo_attribute,database_table,comment'
		)
	);
	const types = typesRaw
		.split( ',' )
		.map( ( x ) => String( x ).trim() )
		.filter( Boolean );

	const sourceWpPathDefault = path.resolve( process.cwd(), '../../..' );
	const targetWpPathGuess = ( () => {
		const marker = `${ path.sep }Local Sites${ path.sep }aie${ path.sep }`;
		if ( sourceWpPathDefault.includes( marker ) ) {
			return sourceWpPathDefault.replace(
				marker,
				`${ path.sep }Local Sites${ path.sep }aie2${ path.sep }`
			);
		}
		return '';
	} )();

	const localPhpFromLocalApp =
		'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php';
	const localPhpDefault = fs.existsSync( localPhpFromLocalApp )
		? localPhpFromLocalApp
		: 'php';

	return {
		headless,
		types,
		repeatDedup:
			String( get( 'AIE_IMPORT_REPEAT_DEDUP', 'true' ) ).toLowerCase() !==
			'false',
		source: {
			baseUrl: get( 'AIE_SOURCE_URL', 'http://aie.local' ),
			username: get( 'AIE_SOURCE_ADMIN_USER', 'admin' ),
			password: get( 'AIE_SOURCE_ADMIN_PASSWORD', 'admin' ),
			wpPath: String( get( 'AIE_SOURCE_WP_PATH', sourceWpPathDefault ) ),
		},
		target: {
			baseUrl: get( 'AIE_TARGET_URL', 'http://aie2.local' ),
			username: get( 'AIE_TARGET_ADMIN_USER', 'admin' ),
			password: get( 'AIE_TARGET_ADMIN_PASSWORD', 'admin' ),
			wpPath: String(
				get(
					'AIE_TARGET_WP_PATH',
					targetWpPathGuess || sourceWpPathDefault
				)
			),
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

function wpEval( env, wpPath, code ) {
	return wp( env, wpPath, [ 'eval', code ], { trim: true } );
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

function slugify( str ) {
	return String( str || '' )
		.toLowerCase()
		.replace( /['"]/g, '' )
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /(^-|-$)/g, '' )
		.slice( 0, 60 );
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
	for ( let attempt = 0; attempt < 3; attempt++ ) {
		try {
			await page.goto( `${ site.baseUrl }${ adminPathWithQuery }`, {
				waitUntil: 'domcontentloaded',
			} );
			if ( await page.locator( 'form#loginform' ).count() ) {
				await ensureLoggedIn( page, site );
				await page.goto( `${ site.baseUrl }${ adminPathWithQuery }`, {
					waitUntil: 'domcontentloaded',
				} );
			}
			return;
		} catch ( e ) {
			const msg = String( e && e.message ? e.message : e );
			if (
				attempt < 2 &&
				msg.includes( 'interrupted by another navigation' )
			) {
				await page
					.waitForLoadState( 'domcontentloaded' )
					.catch( () => null );
				continue;
			}
			throw e;
		}
	}
}

async function waitStep( page, stepNum ) {
	await page.waitForSelector( `.rsl-ie-step-${ stepNum }.active`, {
		timeout: 60_000,
	} );
}

async function clickNextStep( page ) {
	const next = page.locator( '.rsl-ie-step.active .rsl-ie-next-step' );
	await next.waitFor( { state: 'visible', timeout: 60_000 } );
	await page
		.waitForFunction( () => {
			const btn = document.querySelector(
				'.rsl-ie-step.active .rsl-ie-next-step'
			);
			return btn && ! btn.disabled;
		} )
		.catch( () => null );
	const ok = await next.isEnabled().catch( () => false );
	if ( ! ok ) {
		const stepClass = await page
			.locator( '.rsl-ie-step.active' )
			.first()
			.getAttribute( 'class' )
			.catch( () => '' );
		throw new Error(
			`Next Step stayed disabled (active=${ stepClass || 'unknown' })`
		);
	}
	await next.click();
}

async function handleBackupModalIfPresent( page ) {
	const overlay = page.locator( '.rsl-ie-backup-warning-overlay' );
	if ( ! ( await overlay.count() ) ) return;
	await overlay.waitFor( { state: 'visible', timeout: 20_000 } );
	const created = page.locator( '#rsl-ie-backup-created' );
	const dontShow = page.locator( '#rsl-ie-backup-dont-show' );
	if ( await created.count() ) await created.check( { force: true } );
	if ( await dontShow.count() ) await dontShow.check( { force: true } );
	await page.locator( '.rsl-ie-backup-confirm' ).click();
	await overlay.waitFor( { state: 'detached', timeout: 20_000 } );
}

async function selectContentType( page, contentType ) {
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
		return true;
	}
	await page.evaluate( ( ct ) => {
		const el = document.querySelector(
			`.rsl-ie-step-1.active input[name="content_type"][value="${ ct }"]`
		);
		if ( ! el ) return;
		el.checked = true;
		el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		el.dispatchEvent( new Event( 'click', { bubbles: true } ) );
	}, contentType );
	return true;
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

async function tagRowForEval( row ) {
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

async function configureRequiredSelectorsOnExportStep2( page, contentType ) {
	if ( contentType === 'custom_post_types' ) {
		const row = await addFilterRow( page );
		const rowSel = await tagRowForEval( row );
		await page
			.locator( `${ rowSel } select.rsl-ie-filter-field` )
			.selectOption( { value: '_post_type' } );
		const postTypeSelect = page
			.locator( `${ rowSel } select.rsl-ie-post-type-selector` )
			.first();
		await postTypeSelect.waitFor( { state: 'visible', timeout: 60_000 } );
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
		await page
			.locator( `${ rowSel } select.rsl-ie-filter-field` )
			.selectOption( { value: '_taxonomy' } );
		const taxSelect = page
			.locator( `${ rowSel } select.rsl-ie-taxonomy-selector` )
			.first();
		await taxSelect.waitFor( { state: 'visible', timeout: 60_000 } );
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
		await tableSel.waitFor( { state: 'visible', timeout: 60_000 } );
		await page.waitForFunction( () => {
			const sel = document.querySelector(
				'.rsl-ie-step-2.active #rsl-ie-table-name'
			);
			return sel && sel.querySelectorAll( 'option' ).length > 1;
		} );

		const options = tableSel.locator( 'option[value]' );
		const count = await options.count();
		const candidates = [];
		for ( let i = 0; i < count; i++ ) {
			const opt = options.nth( i );
			const v = String(
				( await opt.getAttribute( 'value' ) ) || ''
			).trim();
			const t = String(
				( await opt.innerText().catch( () => '' ) ) || ''
			).trim();
			if ( ! v ) continue;
			candidates.push( { v, t } );
		}
		const preferred =
			candidates.find(
				( x ) => /otbo|mask/i.test( x.v ) || /otbo|mask/i.test( x.t )
			) || candidates.find( ( x ) => ! /wp_users/i.test( x.v ) );
		if ( preferred ) await tableSel.selectOption( { value: preferred.v } );
		return { tableName: await tableSel.inputValue().catch( () => '' ) };
	}

	return {};
}

async function addIdEqualsFilter( page, { idValue } ) {
	const row = await addFilterRow( page );
	const rowSel = await tagRowForEval( row );

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
	if ( selectedValue )
		await fieldSel.selectOption( { value: selectedValue } );

	const condition = page
		.locator( `${ rowSel } select.rsl-ie-filter-condition` )
		.first();
	await condition.waitFor( { state: 'visible', timeout: 30_000 } );
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
		await input.selectOption( String( idValue ) ).catch( () => {} );
		return;
	}

	const v = String( idValue );
	await input.fill( v );
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
	} else if (
		await fieldSel.locator( 'option[value="post_name"]' ).count()
	) {
		await fieldSel.selectOption( { value: 'post_name' } );
	} else if (
		await fieldSel.locator( 'option[value="user_login"]' ).count()
	) {
		await fieldSel.selectOption( { value: 'user_login' } );
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

async function exportCsvFromSource(
	page,
	sourceSite,
	contentType,
	{ filter, requiredSelectors } = {}
) {
	await gotoAdminPage(
		page,
		sourceSite,
		'/wp-admin/admin.php?page=rsl-ie-export'
	);
	await page.waitForSelector( '#rsl-ie-export', { timeout: 30_000 } );
	await waitStep( page, 1 );

	const selected = await selectContentType( page, contentType );
	if ( ! selected )
		return { skipped: true, reason: 'type-disabled-or-missing' };

	await clickNextStep( page );
	await waitStep( page, 2 );

	const required =
		requiredSelectors ||
		( await configureRequiredSelectorsOnExportStep2( page, contentType ) );

	if ( filter && filter.kind === 'id' && filter.value ) {
		await addIdEqualsFilter( page, { idValue: filter.value } );
	} else if ( filter && filter.kind === 'slug' && filter.value ) {
		await addSlugEqualsFilter( page, { slugValue: filter.value } );
	}

	await clickNextStep( page );
	await selectAllFieldsOnStep3( page );
	await waitStep( page, 4 );

	await page.locator( '.rsl-ie-start-export' ).click();
	await waitStep( page, 5 );

	const completeCard = page.locator( '.rsl-ie-export-complete-card' );
	await completeCard.waitFor( { state: 'visible', timeout: 5 * 60_000 } );

	const [ download ] = await Promise.all( [
		page.waitForEvent( 'download', { timeout: 60_000 } ),
		page.locator( '.rsl-ie-download-file' ).click(),
	] );
	return { download, required };
}

async function ensureImportDatabaseTableSelected(
	page,
	{ preferredTable, patterns }
) {
	const sel = page
		.locator( '.rsl-ie-step-4.active #rsl-ie-import-table-name' )
		.first();
	await sel.waitFor( { state: 'visible', timeout: 60_000 } );
	await page
		.waitForFunction( () => {
			const s = document.querySelector(
				'.rsl-ie-step-4.active #rsl-ie-import-table-name'
			);
			return s && s.querySelectorAll( 'option' ).length > 1;
		} )
		.catch( () => null );

	const options = sel.locator( 'option' );
	const count = await options.count();
	const list = [];
	for ( let i = 0; i < count; i++ ) {
		const opt = options.nth( i );
		const v = ( await opt.getAttribute( 'value' ) ) || '';
		const t = ( await opt.innerText().catch( () => '' ) ) || '';
		list.push( { v: String( v ).trim(), t: String( t ).trim() } );
	}

	const want = String( preferredTable || '' ).trim();
	if ( want && list.some( ( x ) => x.v === want ) ) {
		await sel.selectOption( { value: want } );
		return want;
	}

	const pats = Array.isArray( patterns )
		? patterns
		: String( patterns || '' )
				.split( ',' )
				.map( ( x ) => x.trim() )
				.filter( Boolean );
	for ( const p of pats ) {
		const re = new RegExp(
			p.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ),
			'i'
		);
		const found = list.find( ( x ) => re.test( x.v ) || re.test( x.t ) );
		if ( found && found.v ) {
			// eslint-disable-next-line no-await-in-loop
			await sel.selectOption( { value: found.v } );
			return found.v;
		}
	}

	const fallback =
		list.find(
			( x ) => x.v && x.v !== 'custom' && ! /wp_users/i.test( x.v )
		) || list.find( ( x ) => x.v && x.v !== 'custom' );
	if ( fallback && fallback.v ) {
		await sel.selectOption( { value: fallback.v } );
		return fallback.v;
	}
	return '';
}

async function selectUniqueField( page, preferredValues ) {
	const uniqueSelect = page.locator( '#rsl-ie-unique-field' ).first();
	await uniqueSelect.waitFor( { state: 'visible', timeout: 60_000 } );
	const values = Array.isArray( preferredValues ) ? preferredValues : [];
	for ( const v of values ) {
		// eslint-disable-next-line no-await-in-loop
		const has = await uniqueSelect
			.locator( `option[value="${ v }"]` )
			.count();
		if ( has ) {
			// eslint-disable-next-line no-await-in-loop
			await uniqueSelect.selectOption( { value: v } );
			return v;
		}
	}
	// fallback: first non-empty option (skip placeholder empty value).
	const options = uniqueSelect.locator( 'option' );
	const count = await options.count();
	for ( let i = 0; i < count; i++ ) {
		// eslint-disable-next-line no-await-in-loop
		const opt = options.nth( i );
		// eslint-disable-next-line no-await-in-loop
		const val = String(
			( await opt.getAttribute( 'value' ).catch( () => '' ) ) || ''
		).trim();
		if ( ! val ) continue;
		// eslint-disable-next-line no-await-in-loop
		await uniqueSelect.selectOption( { value: val } );
		return val;
	}
	return '';
}

async function readMappingStats( page ) {
	const mapped = page
		.locator( '.rsl-ie-step-4.active .rsl-ie-mapped-count' )
		.first();
	const total = page
		.locator( '.rsl-ie-step-4.active .rsl-ie-total-fields' )
		.first();
	const mappedText = ( await mapped.innerText().catch( () => '' ) ).trim();
	const totalText = ( await total.innerText().catch( () => '' ) ).trim();
	const mappedCount = Number( mappedText ) || 0;
	const totalFields = Number( totalText ) || 0;

	const unmapped = await page.evaluate( () => {
		const cards = Array.from(
			document.querySelectorAll(
				'.rsl-ie-step-4.active .rsl-ie-field-card'
			)
		);
		const items = [];
		for ( const c of cards ) {
			const used = c.classList.contains( 'used' );
			const disabled = c.classList.contains( 'disabled' );
			if ( used || disabled ) continue;
			const nameEl =
				c.querySelector( '.rsl-ie-field-name' ) ||
				c.querySelector( '.rsl-ie-field-title' ) ||
				c;
			const name = String(
				nameEl ? nameEl.textContent || '' : ''
			).trim();
			if ( name ) items.push( name );
		}
		return Array.from( new Set( items ) ).slice( 0, 50 );
	} );

	return { mappedCount, totalFields, unmapped };
}

async function readMappingDetails( page ) {
	return await page.evaluate( () => {
		const rows = Array.from(
			document.querySelectorAll( '.rsl-ie-mapping-row' )
		);
		const mappingRows = rows
			.map( ( row ) => {
				const sourceIndex = row.getAttribute( 'data-source-index' );
				const targetField = row.getAttribute( 'data-target-field' );
				const functionId = row.getAttribute( 'data-function-id' );
				const sourceCard = sourceIndex
					? document.querySelector(
							`.rsl-ie-field-card[data-source-index="${ CSS.escape(
								sourceIndex
							) }"]`
					  )
					: null;
				const sourceField = sourceCard
					? sourceCard.getAttribute( 'data-source-field' )
					: null;
				const fieldType = row.getAttribute( 'data-field-type' );
				return {
					sourceIndex:
						sourceIndex != null ? Number( sourceIndex ) : null,
					sourceField: sourceField || null,
					targetField: targetField || null,
					fieldType: fieldType || null,
					functionId: functionId || null,
				};
			} )
			.filter( ( x ) => x.sourceField && x.targetField );

		const targetFields = Array.from(
			document.querySelectorAll(
				'.rsl-ie-target-field:not(.rsl-ie-custom-field-template)'
			)
		)
			.map( ( el ) => el.getAttribute( 'data-target-field' ) )
			.filter( Boolean );

		const sourceFields = Array.from(
			document.querySelectorAll( '.rsl-ie-field-card' )
		)
			.map( ( el ) => el.getAttribute( 'data-source-field' ) )
			.filter( Boolean );

		return {
			mappingRows,
			targetFields: Array.from( new Set( targetFields ) ),
			sourceFields: Array.from( new Set( sourceFields ) ),
		};
	} );
}

async function importIntoTarget(
	page,
	targetSite,
	contentType,
	filePath,
	{ requiredSelectors, importOptions } = {}
) {
	await gotoAdminPage(
		page,
		targetSite,
		'/wp-admin/admin.php?page=rsl-ie-import'
	);
	await page.waitForSelector( '#rsl-ie-import', { timeout: 30_000 } );
	await waitStep( page, 1 );

	const selected = await selectContentType( page, contentType );
	if ( ! selected )
		return { skipped: true, reason: 'type-disabled-or-missing' };

	await page.locator( '.rsl-ie-step-1.active .rsl-ie-next-step' ).click();
	await handleBackupModalIfPresent( page );

	await waitStep( page, 2 );
	await page.setInputFiles( '#rsl-ie-file-input', filePath );
	await page.waitForFunction( () => {
		const btn = document.querySelector(
			'.rsl-ie-step-2.active .rsl-ie-next-step'
		);
		return btn && ! btn.disabled;
	} );
	await page.locator( '.rsl-ie-step-2.active .rsl-ie-next-step' ).click();

	await waitStep( page, 3 );
	await clickNextStep( page );

	await waitStep( page, 4 );

	if ( contentType === 'custom_post_types' ) {
		const postType = String(
			( requiredSelectors && requiredSelectors.customPostType ) || ''
		).trim();
		const postTypeSelect = page
			.locator( '.rsl-ie-step-4.active #rsl-ie-custom-post-type' )
			.first();
		await postTypeSelect.waitFor( { state: 'visible', timeout: 60_000 } );
		if ( postType ) {
			await page
				.waitForFunction(
					( pt ) => {
						const el = document.querySelector(
							'.rsl-ie-step-4.active #rsl-ie-custom-post-type'
						);
						if ( ! el ) return false;
						return Array.from( el.options ).some(
							( o ) => o.value === pt
						);
					},
					postType,
					{ timeout: 60_000 }
				)
				.catch( () => null );
			await postTypeSelect
				.selectOption( { value: postType } )
				.catch( () => null );
		}
		await page.waitForTimeout( 300 );
		// Wait for target fields to refresh after CPT selection.
		await page
			.locator(
				'.rsl-ie-step-4.active .rsl-ie-target-field[data-target-field="post_name"]'
			)
			.first()
			.waitFor( { state: 'attached', timeout: 30_000 } )
			.catch( () => {} );
		await page
			.locator(
				'.rsl-ie-step-4.active .rsl-ie-target-field[data-target-field="post_author"]'
			)
			.first()
			.waitFor( { state: 'attached', timeout: 30_000 } )
			.catch( () => {} );
	}

	if ( contentType === 'database_table' ) {
		const tableName = String(
			( requiredSelectors && requiredSelectors.tableName ) || ''
		).trim();
		await ensureImportDatabaseTableSelected( page, {
			preferredTable: tableName,
			patterns: [ 'otbo', 'mask' ],
		} );
		await page.waitForTimeout( 300 );
	}

	await page.locator( '.rsl-ie-auto-map' ).click();
	await page
		.waitForFunction( () => {
			const totalEl = document.querySelector(
				'.rsl-ie-step-4.active .rsl-ie-total-fields'
			);
			const mappedEl = document.querySelector(
				'.rsl-ie-step-4.active .rsl-ie-mapped-count'
			);
			const total = totalEl
				? Number( String( totalEl.textContent || '' ).trim() )
				: 0;
			const mapped = mappedEl
				? Number( String( mappedEl.textContent || '' ).trim() )
				: 0;
			return (
				Number.isFinite( total ) &&
				total > 0 &&
				Number.isFinite( mapped )
			);
		} )
		.catch( () => null );
	await page.waitForTimeout( 400 );

	const mappingStats = await readMappingStats( page );
	const mappingDetails = await readMappingDetails( page ).catch( () => null );
	await clickNextStep( page );

	await waitStep( page, 5 );

	const opts = importOptions || {};

	if ( opts.ifExists ) {
		const r = page
			.locator(
				`.rsl-ie-step-5.active input[name="if_exists"][value="${ opts.ifExists }"]`
			)
			.first();
		if ( await r.count() ) await r.check( { force: true } );
	}
	if ( opts.ifNotExists ) {
		const r = page
			.locator(
				`.rsl-ie-step-5.active input[name="if_not_exists"][value="${ opts.ifNotExists }"]`
			)
			.first();
		if ( await r.count() ) await r.check( { force: true } );
	}

	if ( contentType === 'database_table' ) {
		await selectUniqueField( page, opts.uniqueFieldPreferred || [] );
	} else if (
		opts.uniqueFieldPreferred &&
		opts.uniqueFieldPreferred.length
	) {
		await selectUniqueField( page, opts.uniqueFieldPreferred );
	}

	const mediaCheckbox = page.locator( '#rsl-ie-auto-import-media' ).first();
	if ( await mediaCheckbox.count() ) {
		if ( opts.autoImportMedia === false ) {
			await mediaCheckbox.uncheck( { force: true } ).catch( () => {} );
		} else {
			await mediaCheckbox.check( { force: true } ).catch( () => {} );
		}
	}

	if ( opts.autoImportMedia !== false && opts.mediaDuplicateMode ) {
		const r = page
			.locator(
				`.rsl-ie-step-5.active input[name="media_duplicate_mode"][value="${ opts.mediaDuplicateMode }"]`
			)
			.first();
		if ( await r.count() ) await r.check( { force: true } );
	}

	await page.locator( '.rsl-ie-start-import' ).click();
	await handleBackupModalIfPresent( page );

	await waitStep( page, 6 );
	await page
		.locator( '.rsl-ie-import-complete-card' )
		.waitFor( { state: 'visible', timeout: 10 * 60_000 } );

	return { mappingStats, mappingDetails };
}

function pickOnePostId( env, wpPath, postType ) {
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

function getPostSlug( env, wpPath, postId ) {
	const id = Number( postId || 0 );
	const php = `
$p = get_post((int) ${ JSON.stringify( id ) });
echo wp_json_encode([
  'id' => $p ? (int)$p->ID : 0,
  'post_type' => $p ? (string)$p->post_type : '',
  'post_name' => $p ? (string)$p->post_name : '',
  'post_title' => $p ? (string)$p->post_title : '',
], JSON_UNESCAPED_SLASHES);
`;
	return wpEvalJson( env, wpPath, php ) || null;
}

function resolvePostIdBySlug( env, wpPath, { postType, slug } ) {
	const pt = String( postType || 'post' );
	const name = String( slug || '' );
	const php = `
$pt = ${ JSON.stringify( pt ) };
$slug = ${ JSON.stringify( name ) };
$q = get_posts([
  'post_type' => $pt,
  'post_status' => 'any',
  'name' => $slug,
  'fields' => 'ids',
  'posts_per_page' => 1,
]);
echo wp_json_encode(['id' => !empty($q) ? (int)$q[0] : 0], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.id ? out.id : 0 );
}

function pickOneUserId( env, wpPath ) {
	const php = `
$users = get_users(['orderby'=>'ID','order'=>'ASC','number'=>20,'fields'=>['ID','user_login']]);
$out = 0;
foreach ($users as $u) {
  if (!is_object($u)) continue;
  $login = isset($u->user_login) ? (string)$u->user_login : '';
  if ($login === 'admin') continue;
  $out = (int) $u->ID;
  break;
}
echo wp_json_encode(['id' => $out], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.id ? out.id : 0 );
}

function getUserLogin( env, wpPath, userId ) {
	const id = Number( userId || 0 );
	const php = `
$u = get_user_by('id', (int) ${ JSON.stringify( id ) });
echo wp_json_encode([
  'id' => $u ? (int) $u->ID : 0,
  'user_login' => $u ? (string) $u->user_login : '',
], JSON_UNESCAPED_SLASHES);
`;
	return wpEvalJson( env, wpPath, php ) || null;
}

function resolveUserIdByLogin( env, wpPath, login ) {
	const php = `
$u = get_user_by('login', ${ JSON.stringify( String( login || '' ) ) });
echo wp_json_encode(['id' => $u ? (int)$u->ID : 0], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.id ? out.id : 0 );
}

function pickOneTerm( env, wpPath, taxonomy ) {
	const tax = String( taxonomy || 'category' );
	const php = `
$tax = ${ JSON.stringify( tax ) };
if (!$tax || !taxonomy_exists($tax)) { echo wp_json_encode(['term_id'=>0,'slug'=>''], JSON_UNESCAPED_SLASHES); return; }
$terms = get_terms(['taxonomy'=>$tax,'hide_empty'=>false,'number'=>1,'orderby'=>'term_id','order'=>'ASC']);
$t = (!empty($terms) && !is_wp_error($terms)) ? $terms[0] : null;
echo wp_json_encode([
  'term_id' => ($t && !is_wp_error($t)) ? (int)$t->term_id : 0,
  'slug' => ($t && !is_wp_error($t)) ? (string)$t->slug : '',
  'taxonomy' => $tax,
], JSON_UNESCAPED_SLASHES);
`;
	return wpEvalJson( env, wpPath, php ) || null;
}

function resolveTermIdBySlug( env, wpPath, { taxonomy, slug } ) {
	const php = `
$tax = ${ JSON.stringify( String( taxonomy || '' ) ) };
$slug = ${ JSON.stringify( String( slug || '' ) ) };
$t = ($tax && $slug) ? get_term_by('slug', $slug, $tax) : null;
echo wp_json_encode(['term_id' => ($t && !is_wp_error($t)) ? (int)$t->term_id : 0], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.term_id ? out.term_id : 0 );
}

function pickOneMenu( env, wpPath ) {
	const php = `
$menus = function_exists('wp_get_nav_menus') ? wp_get_nav_menus() : [];
$m = (!empty($menus) && !is_wp_error($menus)) ? $menus[0] : null;
echo wp_json_encode([
  'term_id' => ($m && !is_wp_error($m)) ? (int)$m->term_id : 0,
  'slug' => ($m && !is_wp_error($m)) ? (string)$m->slug : '',
  'name' => ($m && !is_wp_error($m)) ? (string)$m->name : '',
], JSON_UNESCAPED_SLASHES);
`;
	return wpEvalJson( env, wpPath, php ) || null;
}

function pickOneAttachmentId( env, wpPath ) {
	const php = `
$ids = get_posts([
  'post_type' => 'attachment',
  'post_status' => 'inherit',
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

function pickOneCommentId( env, wpPath ) {
	const php = `
$comments = get_comments(['number'=>1,'orderby'=>'comment_ID','order'=>'ASC']);
$c = (!empty($comments) && !is_wp_error($comments)) ? $comments[0] : null;
echo wp_json_encode(['id' => $c ? (int)$c->comment_ID : 0], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.id ? out.id : 0 );
}

function ensureFeaturedImage( env, wpPath, postId ) {
	const id = Number( postId || 0 );
	const php = `
$pid = (int) ${ JSON.stringify( id ) };
$thumb = (int) get_post_thumbnail_id($pid);
if ($thumb) { echo wp_json_encode(['attachment_id'=>$thumb], JSON_UNESCAPED_SLASHES); return; }
$att = get_posts(['post_type'=>'attachment','post_status'=>'inherit','fields'=>'ids','posts_per_page'=>1,'orderby'=>'ID','order'=>'ASC']);
$aid = !empty($att) ? (int)$att[0] : 0;
if ($aid) { set_post_thumbnail($pid, $aid); }
echo wp_json_encode(['attachment_id'=>$aid], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.attachment_id ? out.attachment_id : 0 );
}

function countAttachments( env, wpPath ) {
	const php = `
$ids = get_posts(['post_type'=>'attachment','post_status'=>'inherit','fields'=>'ids','posts_per_page'=>-1]);
echo wp_json_encode(['count' => is_array($ids) ? count($ids) : 0], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.count ? out.count : 0 );
}

function getAttachmentMd5( env, wpPath, attachmentId ) {
	const id = Number( attachmentId || 0 );
	const php = `
$id = (int) ${ JSON.stringify( id ) };
$path = $id ? get_attached_file($id) : '';
$md5 = ($path && file_exists($path)) ? md5_file($path) : '';
echo wp_json_encode(['md5' => $md5], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return String( ( out && out.md5 ) || '' );
}

function countAttachmentsByFileHash( env, wpPath, md5Hash ) {
	const php = `
$want = ${ JSON.stringify( String( md5Hash || '' ) ) };
$ids = get_posts(['post_type'=>'attachment','post_status'=>'inherit','fields'=>'ids','posts_per_page'=>-1]);
$n = 0;
foreach ((array)$ids as $id) {
  $path = get_attached_file((int)$id);
  if (!$path || !file_exists($path)) continue;
  $h = md5_file($path);
  if ($h && $want && $h === $want) $n++;
}
echo wp_json_encode(['count' => (int)$n], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.count ? out.count : 0 );
}

function fillAllAcfFieldsForPost( env, wpPath, postId, { refPostId } = {} ) {
	const pid = Number( postId || 0 );
	const ref = Number( refPostId || 0 );
	const code = `
$pid = (int) ${ JSON.stringify( pid ) };
if (!$pid || !function_exists('get_field_objects') || !function_exists('update_field')) { echo 'skip'; return; }

// Ensure we have attachments available.
$att_ids = get_posts(['post_type'=>'attachment','post_status'=>'inherit','fields'=>'ids','posts_per_page'=>5,'orderby'=>'ID','order'=>'ASC']);
$att_ids = array_values(array_filter(array_map('intval', (array)$att_ids)));

$ref_post_id = (int) ${ JSON.stringify( ref ) };
if (!$ref_post_id) {
  $ref_ids = get_posts(['post_type'=>'post','post_status'=>'publish','fields'=>'ids','posts_per_page'=>1,'orderby'=>'ID','order'=>'DESC']);
  $ref_post_id = !empty($ref_ids) ? (int)$ref_ids[0] : 0;
  if ($ref_post_id === $pid) $ref_post_id = 0;
}

function aie_gen_value($field, $ctx) {
  $type = isset($field['type']) ? (string) $field['type'] : '';
  $base = (string) (($ctx['prefix'] ?? 'aie') . '-' . ($ctx['stamp'] ?? '0'));

  if (in_array($type, ['tab','accordion','message'], true)) return null;
  if ($type === 'text') return $base;
  if ($type === 'textarea') return $base . "\\nline2";
  if ($type === 'wysiwyg') return "<p>" . esc_html($base) . "</p>";
  if ($type === 'number') return 123;
  if ($type === 'true_false') return true;
  if ($type === 'email') return "test+" . $base . "@example.com";
  if ($type === 'url') return "https://example.com/" . rawurlencode($base);
  if ($type === 'color_picker') return "#00aaff";

  if (in_array($type, ['select','radio','button_group'], true)) {
    $choices = isset($field['choices']) && is_array($field['choices']) ? array_keys($field['choices']) : [];
    return !empty($choices) ? (string)$choices[0] : $base;
  }
  if ($type === 'checkbox') {
    $choices = isset($field['choices']) && is_array($field['choices']) ? array_keys($field['choices']) : [];
    if (empty($choices)) return [$base];
    $out = [];
    foreach (array_slice($choices, 0, 2) as $c) $out[] = (string)$c;
    return $out;
  }

  if (in_array($type, ['image','file'], true)) {
    $ids = (array) ($ctx['att_ids'] ?? []);
    return !empty($ids) ? (int)$ids[0] : 0;
  }
  if ($type === 'gallery') {
    $ids = (array) ($ctx['att_ids'] ?? []);
    return array_slice(array_map('intval', $ids), 0, 3);
  }

  if (in_array($type, ['post_object','relationship'], true)) {
    $rid = (int) ($ctx['ref_post_id'] ?? 0);
    if (!$rid) return $type === 'relationship' ? [] : 0;
    return $type === 'relationship' ? [ $rid ] : $rid;
  }

  if ($type === 'user') {
    $users = get_users(['orderby'=>'ID','order'=>'ASC','number'=>1,'fields'=>['ID']]);
    $u = (!empty($users) && !is_wp_error($users)) ? $users[0] : null;
    return ($u && isset($u->ID)) ? (int)$u->ID : 0;
  }

  if ($type === 'taxonomy') {
    $tax = isset($field['taxonomy']) ? (string)$field['taxonomy'] : '';
    if (!$tax || !taxonomy_exists($tax)) return [];
    $terms = get_terms(['taxonomy'=>$tax,'hide_empty'=>false,'number'=>2,'orderby'=>'term_id','order'=>'ASC']);
    $ids = [];
    foreach ((array)$terms as $t) {
      if ($t && !is_wp_error($t) && isset($t->term_id)) $ids[] = (int)$t->term_id;
    }
    return $ids;
  }

  if ($type === 'link') {
    return [
      'title' => $base,
      'url' => "https://example.com/" . rawurlencode($base),
      'target' => '_blank',
    ];
  }

  if ($type === 'google_map') {
    return [ 'address' => 'Kyiv', 'lat' => 50.45, 'lng' => 30.52 ];
  }

  if ($type === 'group') {
    $out = [];
    foreach (($field['sub_fields'] ?? []) as $sf) {
      if (empty($sf['key']) || empty($sf['name'])) continue;
      $out[$sf['name']] = aie_gen_value($sf, $ctx);
    }
    return $out;
  }

  if ($type === 'repeater') {
    $rows = [];
    $row = [];
    foreach (($field['sub_fields'] ?? []) as $sf) {
      if (empty($sf['key']) || empty($sf['name'])) continue;
      $row[$sf['name']] = aie_gen_value($sf, $ctx);
    }
    if (!empty($row)) $rows[] = $row;
    return $rows;
  }

  if ($type === 'flexible_content') {
    $layouts = (array) ($field['layouts'] ?? []);
    $l = !empty($layouts) ? $layouts[0] : null;
    if (!$l || empty($l['name'])) return [];
    $row = [ 'acf_fc_layout' => (string) $l['name'] ];
    foreach (($l['sub_fields'] ?? []) as $sf) {
      if (empty($sf['key']) || empty($sf['name'])) continue;
      $row[$sf['name']] = aie_gen_value($sf, $ctx);
    }
    return [ $row ];
  }

  return $base;
}

$objs = get_field_objects($pid, false, false);
if (!$objs || !is_array($objs)) { echo 'ok'; return; }
$ctx = [
  'prefix' => 'aie',
  'stamp' => gmdate('YmdHis'),
  'att_ids' => $att_ids,
  'ref_post_id' => $ref_post_id,
];
foreach ($objs as $name => $field) {
  if (empty($field['key'])) continue;
  $val = aie_gen_value($field, $ctx);
  if ($val === null) continue;
  update_field($field['key'], $val, $pid);
}
echo 'ok';
`;
	return wpEval( env, wpPath, code );
}

function getAcfSnapshotForObject( env, wpPath, objectId ) {
	const oid = String( objectId || '' ).trim();
	const code = `
$oid = ${ JSON.stringify( oid ) };
if ($oid === '') { echo wp_json_encode([]); return; }
if (!function_exists('get_field_objects')) { echo wp_json_encode([]); return; }

function aie_file_md5_for_attachment($id) {
  $id = (int) $id;
  if (!$id) return null;
  $path = get_attached_file($id);
  if (!$path || !file_exists($path)) return null;
  return md5_file($path);
}

function aie_norm_post_ref($id) {
  $id = (int) $id;
  if (!$id) return null;
  $p = get_post($id);
  if (!$p) return null;
  return 'slug:' . $p->post_type . ':' . $p->post_name;
}

function aie_norm_term_slugs($taxonomy, $value) {
  if (!$taxonomy || !taxonomy_exists($taxonomy)) return [];
  $ids = [];
  if (is_array($value)) {
    foreach ($value as $v) {
      if (is_object($v) && isset($v->term_id)) $ids[] = (int) $v->term_id;
      elseif (is_array($v) && isset($v['term_id'])) $ids[] = (int) $v['term_id'];
      else $ids[] = (int) $v;
    }
  } else {
    if (is_object($value) && isset($value->term_id)) $ids[] = (int) $value->term_id;
    elseif (is_array($value) && isset($value['term_id'])) $ids[] = (int) $value['term_id'];
    else $ids[] = (int) $value;
  }
  $slugs = [];
  foreach ($ids as $id) {
    if (!$id) continue;
    $t = get_term($id, $taxonomy);
    if ($t && !is_wp_error($t) && !empty($t->slug)) $slugs[] = (string) $t->slug;
  }
  sort($slugs);
  return $slugs;
}

function aie_norm_value($field, $value) {
  $type = isset($field['type']) ? (string) $field['type'] : '';
  if (in_array($type, ['tab','accordion','message'], true)) return null;
  if ($type === 'icon_picker') {
    if (is_array($value) && isset($value['value'])) return (string) $value['value'];
    return is_scalar($value) ? (string) $value : $value;
  }
  if (in_array($type, ['text','textarea','wysiwyg','password','email','url','oembed','date_picker','date_time_picker','time_picker','color_picker','select','radio','button_group'], true)) {
    return is_scalar($value) ? (string) $value : $value;
  }
  if (in_array($type, ['number','range'], true)) return is_numeric($value) ? 0 + $value : $value;
  if ($type === 'true_false') return (bool) $value;
  if ($type === 'google_map') {
    if (!is_array($value)) return $value;
    return [
      'address' => isset($value['address']) ? (string) $value['address'] : '',
      'lat' => isset($value['lat']) ? 0 + $value['lat'] : null,
      'lng' => isset($value['lng']) ? 0 + $value['lng'] : null,
    ];
  }
  if ($type === 'link') {
    if (!is_array($value)) return $value;
    return [
      'title' => isset($value['title']) ? (string) $value['title'] : '',
      'url' => isset($value['url']) ? (string) $value['url'] : '',
      'target' => isset($value['target']) ? (string) $value['target'] : '',
    ];
  }
  if (in_array($type, ['image','file'], true)) {
    $id = 0;
    if (is_numeric($value)) $id = (int) $value;
    elseif (is_array($value) && isset($value['ID'])) $id = (int) $value['ID'];
    elseif (is_object($value) && isset($value->ID)) $id = (int) $value->ID;
    return aie_file_md5_for_attachment($id);
  }
  if ($type === 'gallery') {
    $hashes = [];
    if (is_array($value)) {
      foreach ($value as $v) {
        $id = 0;
        if (is_numeric($v)) $id = (int) $v;
        elseif (is_array($v) && isset($v['ID'])) $id = (int) $v['ID'];
        elseif (is_object($v) && isset($v->ID)) $id = (int) $v->ID;
        $h = aie_file_md5_for_attachment($id);
        if ($h) $hashes[] = $h;
      }
    }
    sort($hashes);
    return $hashes;
  }
  if ($type === 'taxonomy') {
    $tax = isset($field['taxonomy']) ? (string) $field['taxonomy'] : '';
    return aie_norm_term_slugs($tax, $value);
  }
  if (in_array($type, ['post_object','relationship'], true)) {
    if (is_array($value)) {
      $out = [];
      foreach ($value as $v) {
        $id = is_object($v) && isset($v->ID) ? (int) $v->ID : (int) $v;
        $out[] = aie_norm_post_ref($id);
      }
      $out = array_values(array_filter($out));
      sort($out);
      return $out;
    }
    $id = is_object($value) && isset($value->ID) ? (int) $value->ID : (int) $value;
    return aie_norm_post_ref($id);
  }
  if ($type === 'page_link') {
    if (is_array($value)) {
      $out = array_map('strval', $value);
      $out = array_map(
        function ( $v ) {
          $parts = is_string( $v ) ? wp_parse_url( $v ) : null;
          return ( $parts && isset( $parts['path'] ) ) ? (string) $parts['path'] : (string) $v;
        },
        $out
      );
      sort($out);
      return $out;
    }
    if ( is_scalar( $value ) ) {
      $v = (string) $value;
      $parts = wp_parse_url( $v );
      return ( $parts && isset( $parts['path'] ) ) ? (string) $parts['path'] : $v;
    }
    return $value;
  }
  if ($type === 'user') {
    $id = 0;
    if (is_numeric($value)) $id = (int) $value;
    elseif (is_object($value) && isset($value->ID)) $id = (int) $value->ID;
    $u = $id ? get_user_by('id', $id) : null;
    return $u ? (string) $u->user_login : null;
  }
  if ($type === 'checkbox') {
    $out = [];
    if (is_array($value)) $out = array_map('strval', $value);
    elseif ($value !== null && $value !== '') $out = [ (string) $value ];
    sort($out);
    return $out;
  }
  if ($type === 'group') {
    if (!is_array($value)) return $value;
    $out = [];
    foreach (($field['sub_fields'] ?? []) as $sf) {
      if (empty($sf['name'])) continue;
      $out[$sf['name']] = aie_norm_value($sf, $value[$sf['name']] ?? null);
    }
    ksort($out);
    return $out;
  }
  if ($type === 'repeater') {
    if (!is_array($value)) return $value;
    $rows = [];
    foreach ($value as $row) {
      if (!is_array($row)) continue;
      $outRow = [];
      foreach (($field['sub_fields'] ?? []) as $sf) {
        if (empty($sf['name'])) continue;
        $outRow[$sf['name']] = aie_norm_value($sf, $row[$sf['name']] ?? null);
      }
      ksort($outRow);
      $rows[] = $outRow;
    }
    return $rows;
  }
  if ($type === 'flexible_content') {
    if (!is_array($value)) return $value;
    $layoutMap = [];
    foreach (($field['layouts'] ?? []) as $l) {
      if (!empty($l['name'])) $layoutMap[$l['name']] = $l;
    }
    $rows = [];
    foreach ($value as $row) {
      if (!is_array($row)) continue;
      $layoutName = isset($row['acf_fc_layout']) ? (string) $row['acf_fc_layout'] : '';
      $layout = $layoutMap[$layoutName] ?? null;
      $outRow = [ 'acf_fc_layout' => $layoutName ];
      if ($layout && !empty($layout['sub_fields'])) {
        foreach ($layout['sub_fields'] as $sf) {
          if (empty($sf['name'])) continue;
          $outRow[$sf['name']] = aie_norm_value($sf, $row[$sf['name']] ?? null);
        }
      }
      ksort($outRow);
      $rows[] = $outRow;
    }
    return $rows;
  }
  return $value;
}

$objs = get_field_objects($oid, false, true);
if (!$objs || !is_array($objs)) { echo wp_json_encode([]); return; }
$out = [];
foreach ($objs as $name => $field) {
  if (empty($field['type'])) continue;
  $norm = aie_norm_value($field, $field['value'] ?? null);
  if ($norm === null) continue;
  $out[(string) $name] = [ 'type' => (string) $field['type'], 'value' => $norm ];
}
ksort($out);
echo wp_json_encode($out);
`;
	return wpEvalJson( env, wpPath, code ) || {};
}

function diffAcfSnapshots( expected, actual ) {
	const mismatches = [];
	for ( const [ name, e ] of Object.entries( expected || {} ) ) {
		const a = actual ? actual[ name ] : undefined;
		if ( a === undefined ) {
			mismatches.push( { field: name, expected: e, actual: null } );
			continue;
		}
		if ( JSON.stringify( e?.value ) !== JSON.stringify( a?.value ) ) {
			mismatches.push( { field: name, expected: e, actual: a } );
		}
	}
	for ( const name of Object.keys( actual || {} ) ) {
		if (
			! (
				expected &&
				Object.prototype.hasOwnProperty.call( expected, name )
			)
		) {
			mismatches.push( {
				field: name,
				expected: null,
				actual: actual[ name ],
			} );
		}
	}
	return mismatches;
}

function isWooActive( env, wpPath ) {
	const php = `
echo wp_json_encode(['active' => class_exists('WooCommerce') || class_exists('WC_Order')], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Boolean( out && out.active );
}

function resolveProductIdBySku( env, wpPath, sku ) {
	const php = `
$sku = ${ JSON.stringify( String( sku || '' ) ) };
$id = 0;
if ($sku && function_exists('wc_get_product_id_by_sku')) { $id = (int) wc_get_product_id_by_sku($sku); }
echo wp_json_encode(['id' => $id], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.id ? out.id : 0 );
}

function getProductSku( env, wpPath, productId ) {
	const php = `
$pid = (int) ${ JSON.stringify( Number( productId || 0 ) ) };
$sku = '';
if (function_exists('wc_get_product')) {
  $p = wc_get_product($pid);
  if ($p && method_exists($p,'get_sku')) $sku = (string) $p->get_sku();
}
echo wp_json_encode(['sku' => $sku], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return String( ( out && out.sku ) || '' );
}

function pickOneProductId( env, wpPath ) {
	const php = `
$ids = get_posts([
  'post_type' => 'product',
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

function pickOneCouponId( env, wpPath ) {
	const php = `
$ids = get_posts([
  'post_type' => 'shop_coupon',
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

function getCouponCode( env, wpPath, couponId ) {
	const php = `
$cid = (int) ${ JSON.stringify( Number( couponId || 0 ) ) };
$code = '';
if (class_exists('WC_Coupon')) {
  $c = new WC_Coupon($cid);
  if ($c && method_exists($c,'get_code')) $code = (string) $c->get_code();
}
if (!$code) {
  $p = get_post($cid);
  if ($p) $code = (string) $p->post_title;
}
echo wp_json_encode(['code' => $code], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return String( ( out && out.code ) || '' );
}

function resolveCouponIdByCode( env, wpPath, code ) {
	const php = `
$code = ${ JSON.stringify( String( code || '' ) ) };
$id = 0;
if ($code !== '') {
  if (function_exists('wc_get_coupon_id_by_code')) { $id = (int) wc_get_coupon_id_by_code($code); }
  if (!$id) {
    $p = function_exists('get_page_by_title') ? get_page_by_title($code, OBJECT, 'shop_coupon') : null;
    if ($p && !is_wp_error($p)) $id = (int) $p->ID;
  }
}
echo wp_json_encode(['id' => $id], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.id ? out.id : 0 );
}

function pickOneOrderId( env, wpPath ) {
	const php = `
$id = 0;
if (function_exists('wc_get_orders')) {
  $orders = wc_get_orders([
    'limit' => 1,
    'orderby' => 'id',
    'order' => 'ASC',
    'return' => 'objects',
  ]);
  if (!empty($orders) && is_object($orders[0]) && method_exists($orders[0], 'get_id')) {
    $id = (int) $orders[0]->get_id();
  }
}
if (!$id) {
  // Legacy (non-HPOS) fallback.
  $ids = get_posts([
    'post_type' => 'shop_order',
    'post_status' => 'any',
    'fields' => 'ids',
    'posts_per_page' => 1,
    'orderby' => 'ID',
    'order' => 'ASC',
  ]);
  if (!empty($ids)) $id = (int) $ids[0];
}
echo wp_json_encode(['id' => $id], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.id ? out.id : 0 );
}

function getOrderKey( env, wpPath, orderId ) {
	const php = `
$oid = (int) ${ JSON.stringify( Number( orderId || 0 ) ) };
$key = '';
if (function_exists('wc_get_order')) {
  $o = wc_get_order($oid);
  if ($o && method_exists($o,'get_order_key')) $key = (string) $o->get_order_key();
}
if (!$key) {
  $key = (string) get_post_meta($oid, '_order_key', true);
}
echo wp_json_encode(['order_key' => $key], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return String( ( out && out.order_key ) || '' );
}

function resolveOrderIdByOrderKey( env, wpPath, orderKey ) {
	const php = `
$key = ${ JSON.stringify( String( orderKey || '' ) ) };
$id = 0;
if ($key) {
  // HPOS (custom order tables) support.
  global $wpdb;
  if ($wpdb && isset($wpdb->prefix)) {
    $table = $wpdb->prefix . 'wc_orders';
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($exists === $table) {
      $id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE order_key=%s LIMIT 1", $key));
    }
  }

  // Legacy (non-HPOS) fallback.
  if (!$id) {
    $q = get_posts([
      'post_type' => 'shop_order',
      'post_status' => 'any',
      'fields' => 'ids',
      'posts_per_page' => 1,
      'meta_key' => '_order_key',
      'meta_value' => $key,
    ]);
    if (!empty($q)) $id = (int) $q[0];
  }
}
echo wp_json_encode(['id' => $id], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.id ? out.id : 0 );
}

function pickOneWooAttributeId( env, wpPath ) {
	const php = `
$id = 0;
global $wpdb;
if (isset($wpdb) && $wpdb && $wpdb->prefix) {
  $table = $wpdb->prefix . 'woocommerce_attribute_taxonomies';
  $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
  if ($exists === $table) {
    $row = $wpdb->get_row("SELECT attribute_id FROM {$table} ORDER BY attribute_id ASC LIMIT 1");
    if ($row && isset($row->attribute_id)) $id = (int) $row->attribute_id;
  }
}
echo wp_json_encode(['id' => $id], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.id ? out.id : 0 );
}

function getWooAttributeName( env, wpPath, attributeId ) {
	const php = `
$aid = (int) ${ JSON.stringify( Number( attributeId || 0 ) ) };
$name = '';
global $wpdb;
if ($aid && isset($wpdb) && $wpdb && $wpdb->prefix) {
  $table = $wpdb->prefix . 'woocommerce_attribute_taxonomies';
  $row = $wpdb->get_row($wpdb->prepare("SELECT attribute_name FROM {$table} WHERE attribute_id=%d LIMIT 1", $aid));
  if ($row && isset($row->attribute_name)) $name = (string) $row->attribute_name;
}
echo wp_json_encode(['name' => $name], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return String( ( out && out.name ) || '' );
}

function resolveWooAttributeIdByName( env, wpPath, name ) {
	const php = `
$name = ${ JSON.stringify( String( name || '' ) ) };
$id = 0;
global $wpdb;
if ($name && isset($wpdb) && $wpdb && $wpdb->prefix) {
  $table = $wpdb->prefix . 'woocommerce_attribute_taxonomies';
  $row = $wpdb->get_row($wpdb->prepare("SELECT attribute_id FROM {$table} WHERE attribute_name=%s LIMIT 1", $name));
  if ($row && isset($row->attribute_id)) $id = (int) $row->attribute_id;
}
echo wp_json_encode(['id' => $id], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return Number( out && out.id ? out.id : 0 );
}

function getWooAttributeSnapshot( env, wpPath, attributeId ) {
	const php = `
$aid = (int) ${ JSON.stringify( Number( attributeId || 0 ) ) };
$out = null;
if ($aid && function_exists('wc_get_attribute_taxonomies')) {
  $attrs = wc_get_attribute_taxonomies();
  $attr = null;
  if (is_array($attrs)) {
    foreach ($attrs as $a) {
      if ((int) ($a->attribute_id ?? 0) === $aid) { $attr = $a; break; }
    }
  }
  if ($attr) {
    $name = (string) ($attr->attribute_name ?? '');
    $tax = '';
    if ($name) {
      if (function_exists('wc_attribute_taxonomy_name')) {
        $tax = (string) wc_attribute_taxonomy_name($name);
      } else {
        $tax = 'pa_' . $name;
      }
    }

    $termMetaPortable = function($termId) {
      $meta = get_term_meta((int)$termId);
      $convert = function($v) use (&$convert) {
        if (is_string($v)) {
          $u = maybe_unserialize($v);
          if ($u !== $v) return $convert($u);
          return $v;
        }
        if (is_bool($v) || is_int($v) || is_float($v) || is_null($v)) return $v;
        if (is_array($v)) {
          $out = [];
          foreach ($v as $k => $vv) { $out[(string)$k] = $convert($vv); }
          ksort($out);
          return $out;
        }
        if (is_object($v)) {
          // Best-effort: cast to array and recurse.
          return $convert((array)$v);
        }
        return (string) $v;
      };

      $out = [];
      foreach ($meta as $k => $vals) {
        if (!is_array($vals)) continue;
        $val = (count($vals) === 1) ? $vals[0] : $vals;
        $out[$k] = $convert($val);
      }
      ksort($out);
      return $out;
    };

    $terms = [];
    if ($tax && taxonomy_exists($tax)) {
      $rawTerms = get_terms(['taxonomy' => $tax, 'hide_empty' => false]);
      if (!is_wp_error($rawTerms) && is_array($rawTerms)) {
        foreach ($rawTerms as $t) {
          $terms[] = [
            'name' => (string) $t->name,
            'slug' => (string) $t->slug,
            'description' => (string) $t->description,
            'meta' => $termMetaPortable((int)$t->term_id),
          ];
        }
      }
    }
    usort($terms, function($a,$b){
      return strcmp(($a['slug'] ?? '') . '|' . ($a['name'] ?? ''), ($b['slug'] ?? '') . '|' . ($b['name'] ?? ''));
    });

    $out = [
      'attribute_name' => (string) ($attr->attribute_name ?? ''),
      'attribute_label' => (string) ($attr->attribute_label ?? ''),
      'attribute_type' => (string) ($attr->attribute_type ?? ''),
      'attribute_orderby' => (string) ($attr->attribute_orderby ?? ''),
      'attribute_public' => (int) ($attr->attribute_public ?? 0),
      'taxonomy' => (string) $tax,
      'terms' => $terms,
    ];
  }
}
echo wp_json_encode(['snapshot' => $out], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return out && out.snapshot ? out.snapshot : null;
}

function diffWooAttributeSnapshots( expected, actual ) {
	const diffs = [];
	if ( ! expected && ! actual ) return diffs;
	if ( ! expected || ! actual ) {
		diffs.push( {
			kind: 'missing',
			expected: !! expected,
			actual: !! actual,
		} );
		return diffs;
	}

	const fields = [
		'attribute_name',
		'attribute_label',
		'attribute_type',
		'attribute_orderby',
		'attribute_public',
		'taxonomy',
	];
	for ( const f of fields ) {
		if ( String( expected[ f ] ?? '' ) !== String( actual[ f ] ?? '' ) ) {
			diffs.push( {
				kind: 'field',
				field: f,
				expected: expected[ f ],
				actual: actual[ f ],
			} );
		}
	}

	const eTerms = Array.isArray( expected.terms ) ? expected.terms : [];
	const aTerms = Array.isArray( actual.terms ) ? actual.terms : [];
	if ( eTerms.length !== aTerms.length ) {
		diffs.push( {
			kind: 'terms-count',
			expected: eTerms.length,
			actual: aTerms.length,
		} );
	}

	const key = ( t ) =>
		`${ String( t?.slug ?? '' ) }|${ String( t?.name ?? '' ) }`;
	const eMap = new Map( eTerms.map( ( t ) => [ key( t ), t ] ) );
	const aMap = new Map( aTerms.map( ( t ) => [ key( t ), t ] ) );
	for ( const k of eMap.keys() ) {
		if ( ! aMap.has( k ) )
			diffs.push( { kind: 'term-missing', which: 'actual', key: k } );
	}
	for ( const k of aMap.keys() ) {
		if ( ! eMap.has( k ) )
			diffs.push( { kind: 'term-missing', which: 'expected', key: k } );
	}
	for ( const k of eMap.keys() ) {
		const et = eMap.get( k );
		const at = aMap.get( k );
		if ( ! at ) continue;
		if (
			String( et.description ?? '' ) !== String( at.description ?? '' )
		) {
			diffs.push( {
				kind: 'term-field',
				key: k,
				field: 'description',
				expected: et.description,
				actual: at.description,
			} );
		}
		const eMeta =
			et && typeof et.meta === 'object' && et.meta ? et.meta : {};
		const aMeta =
			at && typeof at.meta === 'object' && at.meta ? at.meta : {};
		const eMetaStr = JSON.stringify( eMeta );
		const aMetaStr = JSON.stringify( aMeta );
		if ( eMetaStr !== aMetaStr ) {
			diffs.push( { kind: 'term-meta', key: k } );
		}
	}

	return diffs;
}

function getAllWooAttributeSnapshots( env, wpPath ) {
	const php = `
$out = [];
if (function_exists('wc_get_attribute_taxonomies')) {
  $attrs = wc_get_attribute_taxonomies();
  if (is_array($attrs)) {
    foreach ($attrs as $a) {
      $aid = (int) ($a->attribute_id ?? 0);
      if (!$aid) continue;
      $name = (string) ($a->attribute_name ?? '');
      $tax = '';
      if ($name) {
        if (function_exists('wc_attribute_taxonomy_name')) {
          $tax = (string) wc_attribute_taxonomy_name($name);
        } else {
          $tax = 'pa_' . $name;
        }
      }

      $termMetaPortable = function($termId) {
        $meta = get_term_meta((int)$termId);
        $convert = function($v) use (&$convert) {
          if (is_string($v)) {
            $u = maybe_unserialize($v);
            if ($u !== $v) return $convert($u);
            return $v;
          }
          if (is_bool($v) || is_int($v) || is_float($v) || is_null($v)) return $v;
          if (is_array($v)) {
            $out = [];
            foreach ($v as $k => $vv) { $out[(string)$k] = $convert($vv); }
            ksort($out);
            return $out;
          }
          if (is_object($v)) return $convert((array)$v);
          return (string) $v;
        };
        $out = [];
        foreach ($meta as $k => $vals) {
          if (!is_array($vals)) continue;
          $val = (count($vals) === 1) ? $vals[0] : $vals;
          $out[$k] = $convert($val);
        }
        ksort($out);
        return $out;
      };

      $terms = [];
      if ($tax && taxonomy_exists($tax)) {
        $rawTerms = get_terms(['taxonomy' => $tax, 'hide_empty' => false]);
        if (!is_wp_error($rawTerms) && is_array($rawTerms)) {
          foreach ($rawTerms as $t) {
            $terms[] = [
              'name' => (string) $t->name,
              'slug' => (string) $t->slug,
              'description' => (string) $t->description,
              'meta' => $termMetaPortable((int)$t->term_id),
            ];
          }
        }
      }
      usort($terms, function($a,$b){
        return strcmp(($a['slug'] ?? '') . '|' . ($a['name'] ?? ''), ($b['slug'] ?? '') . '|' . ($b['name'] ?? ''));
      });

      $out[] = [
        'attribute_id' => $aid,
        'attribute_name' => (string) ($a->attribute_name ?? ''),
        'attribute_label' => (string) ($a->attribute_label ?? ''),
        'attribute_type' => (string) ($a->attribute_type ?? ''),
        'attribute_orderby' => (string) ($a->attribute_orderby ?? ''),
        'attribute_public' => (int) ($a->attribute_public ?? 0),
        'taxonomy' => (string) $tax,
        'terms' => $terms,
      ];
    }
  }
}
usort($out, function($x,$y){
  return strcmp((string)($x['attribute_name'] ?? ''), (string)($y['attribute_name'] ?? ''));
});
echo wp_json_encode(['items' => $out], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, wpPath, php );
	return out && Array.isArray( out.items ) ? out.items : [];
}

function diffWooAttributesIndex( expectedItems, actualItems ) {
	const diffs = [];
	const e = Array.isArray( expectedItems ) ? expectedItems : [];
	const a = Array.isArray( actualItems ) ? actualItems : [];
	const eMap = new Map(
		e.map( ( x ) => [ String( x?.attribute_name ?? '' ), x ] )
	);
	const aMap = new Map(
		a.map( ( x ) => [ String( x?.attribute_name ?? '' ), x ] )
	);

	for ( const name of eMap.keys() ) {
		if ( ! aMap.has( name ) )
			diffs.push( {
				kind: 'attribute-missing',
				which: 'actual',
				attribute_name: name,
			} );
	}
	for ( const name of aMap.keys() ) {
		if ( ! eMap.has( name ) )
			diffs.push( {
				kind: 'attribute-extra',
				which: 'actual',
				attribute_name: name,
			} );
	}
	for ( const name of eMap.keys() ) {
		const ex = eMap.get( name );
		const ax = aMap.get( name );
		if ( ! ax ) continue;
		const fieldDiffs = diffWooAttributeSnapshots( ex, ax );
		for ( const d of fieldDiffs )
			diffs.push( { attribute_name: name, ...d } );
	}
	return diffs;
}

async function run() {
	const env = loadEnv();

	const artifactsRoot = path.resolve(
		process.cwd(),
		'e2e',
		'artifacts',
		'aie-import-check',
		nowStamp()
	);
	mkdirp( artifactsRoot );

	const browser = await chromium.launch( { headless: env.headless } );
	const sourceCtx = await browser.newContext();
	const targetCtx = await browser.newContext();
	const sourcePage = await sourceCtx.newPage();
	const targetPage = await targetCtx.newPage();

	const summary = {
		startedAt: new Date().toISOString(),
		source: { baseUrl: env.source.baseUrl, wpPath: env.source.wpPath },
		target: { baseUrl: env.target.baseUrl, wpPath: env.target.wpPath },
		results: [],
		issues: [],
	};

	try {
		const wooActive =
			isWooActive( env, env.source.wpPath ) &&
			isWooActive( env, env.target.wpPath );

		// Prepare a media baseline for dedup checks (featured image of post=1 on source).
		const baselinePostId = 1;
		const baselineAttachmentId = ensureFeaturedImage(
			env,
			env.source.wpPath,
			baselinePostId
		);
		const baselineHash = baselineAttachmentId
			? getAttachmentMd5( env, env.source.wpPath, baselineAttachmentId )
			: '';

		const targetAttachmentsBefore = countAttachments(
			env,
			env.target.wpPath
		);
		const targetHashBefore = baselineHash
			? countAttachmentsByFileHash( env, env.target.wpPath, baselineHash )
			: 0;

		// Build cases (different filters + different import options).
		const cases = [];

		if ( env.types.includes( 'post' ) ) {
			cases.push( {
				type: 'post',
				label: 'post:id-filter:update+create (media skip dup)',
				export: async () => {
					const postId = 1;
					// Ensure ACF has values to compare (best-effort).
					const refPostId = pickOnePostId(
						env,
						env.source.wpPath,
						'post'
					);
					fillAllAcfFieldsForPost( env, env.source.wpPath, postId, {
						refPostId,
					} );
					const slugInfo = getPostSlug(
						env,
						env.source.wpPath,
						postId
					);
					return {
						filter: { kind: 'id', value: postId },
						identity: {
							kind: 'post-slug',
							postType: 'post',
							slug: slugInfo?.post_name || '',
						},
						sourceObjectId: String( postId ),
					};
				},
				importOptions: {
					ifExists: 'update',
					ifNotExists: 'create',
					autoImportMedia: true,
					mediaDuplicateMode: 'skip',
					uniqueFieldPreferred: [ 'post_name', 'post_title', 'ID' ],
				},
				resolveTargetObjectId: ( identity ) => {
					const id = resolvePostIdBySlug( env, env.target.wpPath, {
						postType: identity.postType,
						slug: identity.slug,
					} );
					return id ? String( id ) : '';
				},
				expectAcf: true,
				repeatImportForDedup: env.repeatDedup,
			} );
		}

		if ( env.types.includes( 'page' ) ) {
			cases.push( {
				type: 'page',
				label: 'page:slug-filter:update+create (media skip dup)',
				export: async () => {
					const pageId = pickOnePostId(
						env,
						env.source.wpPath,
						'page'
					);
					if ( ! pageId )
						return { skipped: true, reason: 'no-pages' };
					const refPostId = pickOnePostId(
						env,
						env.source.wpPath,
						'post'
					);
					fillAllAcfFieldsForPost( env, env.source.wpPath, pageId, {
						refPostId,
					} );
					const info = getPostSlug( env, env.source.wpPath, pageId );
					return {
						filter: { kind: 'slug', value: info?.post_name || '' },
						identity: {
							kind: 'post-slug',
							postType: 'page',
							slug: info?.post_name || '',
						},
						sourceObjectId: String( pageId ),
					};
				},
				importOptions: {
					ifExists: 'update',
					ifNotExists: 'create',
					autoImportMedia: true,
					mediaDuplicateMode: 'skip',
					uniqueFieldPreferred: [ 'post_name', 'post_title', 'ID' ],
				},
				resolveTargetObjectId: ( identity ) => {
					const id = resolvePostIdBySlug( env, env.target.wpPath, {
						postType: identity.postType,
						slug: identity.slug,
					} );
					return id ? String( id ) : '';
				},
				expectAcf: true,
			} );
		}

		if ( env.types.includes( 'custom_post_types' ) ) {
			cases.push( {
				type: 'custom_post_types',
				label: 'custom_post_types:portfolio:id-filter:update+create',
				export: async () => {
					// We'll select portfolio in the export UI, then filter by ID for that post type (best-effort).
					const portfolioId = pickOnePostId(
						env,
						env.source.wpPath,
						'portfolio'
					);
					if ( ! portfolioId )
						return { skipped: true, reason: 'no-portfolio' };
					const refPostId = pickOnePostId(
						env,
						env.source.wpPath,
						'post'
					);
					fillAllAcfFieldsForPost(
						env,
						env.source.wpPath,
						portfolioId,
						{ refPostId }
					);
					const info = getPostSlug(
						env,
						env.source.wpPath,
						portfolioId
					);
					return {
						filter: { kind: 'id', value: portfolioId },
						identity: {
							kind: 'post-slug',
							postType: 'portfolio',
							slug: info?.post_name || '',
						},
						sourceObjectId: String( portfolioId ),
					};
				},
				importOptions: {
					ifExists: 'update',
					ifNotExists: 'create',
					autoImportMedia: true,
					mediaDuplicateMode: 'skip',
					uniqueFieldPreferred: [ 'post_name', 'post_title', 'ID' ],
				},
				resolveTargetObjectId: ( identity ) => {
					const id = resolvePostIdBySlug( env, env.target.wpPath, {
						postType: identity.postType,
						slug: identity.slug,
					} );
					return id ? String( id ) : '';
				},
				expectAcf: true,
			} );
		}

		if ( env.types.includes( 'taxonomy' ) ) {
			cases.push( {
				type: 'taxonomy',
				label: 'taxonomy:category:slug-filter:update+create',
				export: async () => {
					const tax = 'category';
					const term = pickOneTerm( env, env.source.wpPath, tax );
					if ( ! term || ! term.term_id )
						return { skipped: true, reason: 'no-terms' };
					return {
						filter: { kind: 'slug', value: term.slug },
						identity: {
							kind: 'term-slug',
							taxonomy: tax,
							slug: term.slug,
						},
						sourceObjectId: `term_${ term.term_id }`,
					};
				},
				importOptions: {
					ifExists: 'update',
					ifNotExists: 'create',
					autoImportMedia: true,
					mediaDuplicateMode: 'skip',
					uniqueFieldPreferred: [ 'slug', 'name', 'term_id' ],
				},
				resolveTargetObjectId: ( identity ) => {
					const termId = resolveTermIdBySlug(
						env,
						env.target.wpPath,
						{ taxonomy: identity.taxonomy, slug: identity.slug }
					);
					return termId ? `term_${ termId }` : '';
				},
				expectAcf: true,
			} );
		}

		if ( env.types.includes( 'menu' ) ) {
			cases.push( {
				type: 'menu',
				label: 'menu:nav_menu:id-filter:update+create',
				export: async () => {
					const menu = pickOneMenu( env, env.source.wpPath );
					if ( ! menu || ! menu.term_id )
						return { skipped: true, reason: 'no-menus' };
					return {
						filter: { kind: 'id', value: menu.term_id },
						identity: {
							kind: 'term-slug',
							taxonomy: 'nav_menu',
							slug: menu.slug,
						},
						sourceObjectId: `term_${ menu.term_id }`,
					};
				},
				importOptions: {
					ifExists: 'update',
					ifNotExists: 'create',
					autoImportMedia: true,
					mediaDuplicateMode: 'skip',
					uniqueFieldPreferred: [ 'slug', 'name', 'term_id' ],
				},
				resolveTargetObjectId: ( identity ) => {
					const termId = resolveTermIdBySlug(
						env,
						env.target.wpPath,
						{ taxonomy: identity.taxonomy, slug: identity.slug }
					);
					return termId ? `term_${ termId }` : '';
				},
				expectAcf: true,
			} );
		}

		if ( env.types.includes( 'user' ) ) {
			cases.push( {
				type: 'user',
				label: 'user:user_login:slug-filter:update+create',
				export: async () => {
					const userId = pickOneUserId( env, env.source.wpPath );
					if ( ! userId )
						return { skipped: true, reason: 'no-non-admin-user' };
					const info = getUserLogin( env, env.source.wpPath, userId );
					if ( ! info || ! info.user_login )
						return { skipped: true, reason: 'user-login-missing' };
					return {
						filter: { kind: 'slug', value: info.user_login },
						identity: {
							kind: 'user-login',
							login: info.user_login,
						},
						sourceObjectId: `user_${ userId }`,
					};
				},
				importOptions: {
					ifExists: 'update',
					ifNotExists: 'create',
					autoImportMedia: true,
					mediaDuplicateMode: 'skip',
					uniqueFieldPreferred: [ 'user_login', 'user_email', 'ID' ],
				},
				resolveTargetObjectId: ( identity ) => {
					const id = resolveUserIdByLogin(
						env,
						env.target.wpPath,
						identity.login
					);
					return id ? `user_${ id }` : '';
				},
				expectAcf: true,
			} );
		}

		if ( env.types.includes( 'media' ) ) {
			cases.push( {
				type: 'media',
				label: 'media:id-filter:update+create (media skip dup)',
				export: async () => {
					const attId = pickOneAttachmentId( env, env.source.wpPath );
					if ( ! attId )
						return { skipped: true, reason: 'no-attachments' };
					return {
						filter: { kind: 'id', value: attId },
						identity: { kind: 'media-id', id: attId },
						sourceObjectId: '',
					};
				},
				importOptions: {
					ifExists: 'update',
					ifNotExists: 'create',
					autoImportMedia: true,
					mediaDuplicateMode: 'skip',
					uniqueFieldPreferred: [
						'file_url',
						'url',
						'filename',
						'file_name',
						'ID',
					],
				},
				resolveTargetObjectId: () => '',
				expectAcf: false,
			} );
		}

		if ( wooActive && env.types.includes( 'woo_product' ) ) {
			cases.push( {
				type: 'woo_product',
				label: 'woo_product:sku:update+create (media skip dup)',
				export: async () => {
					const pid = pickOneProductId( env, env.source.wpPath );
					if ( ! pid )
						return { skipped: true, reason: 'no-products' };
					const refPostId = pickOnePostId(
						env,
						env.source.wpPath,
						'post'
					);
					fillAllAcfFieldsForPost( env, env.source.wpPath, pid, {
						refPostId,
					} );
					const sku = getProductSku( env, env.source.wpPath, pid );
					const info = getPostSlug( env, env.source.wpPath, pid );
					return {
						filter: { kind: 'id', value: pid },
						identity: {
							kind: 'product-sku',
							sku: sku || '',
							fallbackSlug: info?.post_name || '',
						},
						sourceObjectId: String( pid ),
					};
				},
				importOptions: {
					ifExists: 'update',
					ifNotExists: 'create',
					autoImportMedia: true,
					mediaDuplicateMode: 'skip',
					uniqueFieldPreferred: [
						'sku',
						'_sku',
						'post_name',
						'post_title',
						'ID',
					],
				},
				resolveTargetObjectId: ( identity ) => {
					const id = identity.sku
						? resolveProductIdBySku(
								env,
								env.target.wpPath,
								identity.sku
						  )
						: 0;
					if ( id ) return String( id );
					if ( identity.fallbackSlug ) {
						const bySlug = resolvePostIdBySlug(
							env,
							env.target.wpPath,
							{ postType: 'product', slug: identity.fallbackSlug }
						);
						return bySlug ? String( bySlug ) : '';
					}
					return '';
				},
				expectAcf: true,
			} );
		}

		if ( wooActive && env.types.includes( 'woo_coupon' ) ) {
			cases.push( {
				type: 'woo_coupon',
				label: 'woo_coupon:code:update+create',
				export: async () => {
					const cid = pickOneCouponId( env, env.source.wpPath );
					if ( ! cid ) return { skipped: true, reason: 'no-coupons' };
					const code = getCouponCode( env, env.source.wpPath, cid );
					return {
						filter: { kind: 'id', value: cid },
						identity: { kind: 'coupon-code', code },
						sourceObjectId: String( cid ),
					};
				},
				importOptions: {
					ifExists: 'update',
					ifNotExists: 'create',
					autoImportMedia: true,
					mediaDuplicateMode: 'skip',
					uniqueFieldPreferred: [ 'code', 'post_title', 'ID' ],
				},
				resolveTargetObjectId: ( identity ) => {
					const id = resolveCouponIdByCode(
						env,
						env.target.wpPath,
						identity.code
					);
					return id ? String( id ) : '';
				},
				expectAcf: true,
			} );
		}

		if ( wooActive && env.types.includes( 'woo_order' ) ) {
			cases.push( {
				type: 'woo_order',
				label: 'woo_order:order_key:update+create',
				export: async () => {
					const oid = pickOneOrderId( env, env.source.wpPath );
					if ( ! oid ) return { skipped: true, reason: 'no-orders' };
					const key = getOrderKey( env, env.source.wpPath, oid );
					return {
						filter: { kind: 'id', value: oid },
						identity: { kind: 'order-key', orderKey: key },
						sourceObjectId: String( oid ),
					};
				},
				importOptions: {
					ifExists: 'update',
					ifNotExists: 'create',
					autoImportMedia: true,
					mediaDuplicateMode: 'skip',
					uniqueFieldPreferred: [ 'order_key', 'order_number', 'ID' ],
				},
				resolveTargetObjectId: ( identity ) => {
					const id = resolveOrderIdByOrderKey(
						env,
						env.target.wpPath,
						identity.orderKey
					);
					return id ? String( id ) : '';
				},
				// Orders may be HPOS objects (not posts), and ACF attachment is not guaranteed.
				expectAcf: false,
			} );
		}

		if ( wooActive && env.types.includes( 'woo_attribute' ) ) {
			cases.push( {
				type: 'woo_attribute',
				label: 'woo_attribute:all:update+create',
				export: async () => {
					const snapshots = getAllWooAttributeSnapshots(
						env,
						env.source.wpPath
					);
					if ( ! snapshots.length )
						return { skipped: true, reason: 'no-attributes' };
					return {
						filter: null,
						identity: { kind: 'woo-attribute-index', snapshots },
						sourceObjectId: '',
					};
				},
				importOptions: {
					ifExists: 'update',
					ifNotExists: 'create',
					autoImportMedia: true,
					mediaDuplicateMode: 'skip',
					uniqueFieldPreferred: [
						'attribute_name',
						'name',
						'slug',
						'attribute_id',
						'ID',
					],
				},
				expectAcf: false,
				repeatImportForDedup: env.repeatDedup,
			} );
		}

		if ( env.types.includes( 'comment' ) ) {
			cases.push( {
				type: 'comment',
				label: 'comment:id-filter:update+create',
				export: async () => {
					const cid = pickOneCommentId( env, env.source.wpPath );
					if ( ! cid )
						return { skipped: true, reason: 'no-comments' };
					return {
						filter: { kind: 'id', value: cid },
						identity: { kind: 'comment-id', id: cid },
						sourceObjectId: '',
					};
				},
				importOptions: {
					ifExists: 'update',
					ifNotExists: 'create',
					autoImportMedia: true,
					mediaDuplicateMode: 'skip',
					uniqueFieldPreferred: [
						'comment_ID',
						'comment_author_email',
						'comment_date_gmt',
					],
				},
				resolveTargetObjectId: () => '',
				expectAcf: false,
			} );
		}

		if ( env.types.includes( 'database_table' ) ) {
			cases.push( {
				type: 'database_table',
				label: 'database_table:table-selected:auto-map',
				export: async () => {
					// Table selection happens in UI; return placeholder.
					return {
						filter: null,
						identity: { kind: 'db-table' },
						sourceObjectId: '',
					};
				},
				importOptions: {
					ifExists: 'update',
					ifNotExists: 'create',
					autoImportMedia: true,
					mediaDuplicateMode: 'skip',
					uniqueFieldPreferred: [],
				},
				resolveTargetObjectId: () => '',
				expectAcf: false,
			} );
		}

		for ( const c of cases ) {
			const caseDir = path.join(
				artifactsRoot,
				slugify( c.type ),
				slugify( c.label )
			);
			mkdirp( caseDir );

			const result = {
				type: c.type,
				label: c.label,
				status: 'ok',
				skipped: false,
				export: null,
				import: null,
				acf: null,
				mediaDedup: null,
				errors: [],
			};

			try {
				// Prepare export config (may inspect source via wp-cli).
				const prep = await c.export();
				if ( prep && prep.skipped ) {
					result.skipped = true;
					result.status = 'skipped';
					result.errors.push( {
						kind: 'skipped',
						reason: prep.reason || 'skipped',
					} );
					summary.results.push( result );
					// eslint-disable-next-line no-continue
					continue;
				}

				const exportInfo = prep || {};

				// Export from source.
				const exp = await exportCsvFromSource(
					sourcePage,
					env.source,
					c.type,
					{
						filter: exportInfo.filter,
						requiredSelectors: exportInfo.requiredSelectors,
					}
				);
				if ( exp && exp.skipped ) {
					result.skipped = true;
					result.status = 'skipped';
					result.errors.push( {
						kind: 'skipped',
						reason: exp.reason || 'export-skipped',
					} );
					summary.results.push( result );
					// eslint-disable-next-line no-continue
					continue;
				}

				const suggested = exp.download.suggestedFilename();
				const exportPath = path.join(
					caseDir,
					`export-${ suggested }`
				);
				await exp.download.saveAs( exportPath );

				result.export = {
					file: exportPath,
					filter: exportInfo.filter || null,
					required: exp.required || {},
				};

				// Import into target.
				const importRes = await importIntoTarget(
					targetPage,
					env.target,
					c.type,
					exportPath,
					{
						requiredSelectors:
							exp.required || exportInfo.requiredSelectors || {},
						importOptions: c.importOptions || {},
					}
				);
				result.import = {
					mapping: importRes.mappingStats || null,
					options: c.importOptions || {},
				};
				if ( importRes.mappingDetails ) {
					fs.writeFileSync(
						path.join( caseDir, 'mapping-details.json' ),
						JSON.stringify( importRes.mappingDetails, null, 2 )
					);
				}

				// Check mapping completeness.
				if (
					importRes.mappingStats &&
					importRes.mappingStats.totalFields &&
					importRes.mappingStats.mappedCount !==
						importRes.mappingStats.totalFields
				) {
					const allowedUnmappedByType = {
						post: [
							'ID',
							'author_name',
							'featured_image_title',
							'featured_image_caption',
						],
						page: [
							'ID',
							'author_name',
							'featured_image_title',
							'featured_image_caption',
						],
						custom_post_types: [
							'ID',
							'author_name',
							'featured_image_title',
							'featured_image_caption',
						],
						woo_product: [
							'ID',
							'author_name',
							'featured_image_title',
							'featured_image_caption',
						],
						woo_coupon: [ 'ID', 'author_name' ],
						woo_order: [ 'ID', 'author_name' ],
						taxonomy: [ 'parent_slug' ],
						comment: [ 'post_permalink', 'post_type', 'post_slug' ],
					};
					const allowed = new Set(
						allowedUnmappedByType[ c.type ] || []
					);
					const unmapped = Array.isArray(
						importRes.mappingStats.unmapped
					)
						? importRes.mappingStats.unmapped
						: [];
					const important = unmapped.filter(
						( x ) => ! allowed.has( String( x || '' ).trim() )
					);
					if ( important.length ) {
						result.status = 'issues';
						result.errors.push( {
							kind: 'mapping-incomplete',
							mappedCount: importRes.mappingStats.mappedCount,
							totalFields: importRes.mappingStats.totalFields,
							unmapped: unmapped,
							unmappedImportant: important,
						} );
					} else {
						result.errors.push( {
							kind: 'mapping-optional-unmapped',
							mappedCount: importRes.mappingStats.mappedCount,
							totalFields: importRes.mappingStats.totalFields,
							unmapped: unmapped,
						} );
					}
				}

				// Woo attributes: verify the full attribute snapshot (incl. terms) matches.
				if ( c.type === 'woo_attribute' ) {
					const expectedItems = Array.isArray(
						exportInfo?.identity?.snapshots
					)
						? exportInfo.identity.snapshots
						: [];
					const actualItems = getAllWooAttributeSnapshots(
						env,
						env.target.wpPath
					);
					const diffs = diffWooAttributesIndex(
						expectedItems,
						actualItems
					);
					result.wooAttribute = {
						expectedCount: expectedItems.length,
						actualCount: actualItems.length,
						diffsCount: diffs.length,
					};
					if ( diffs.length ) {
						result.status = 'issues';
						result.errors.push( {
							kind: 'woo-attribute-mismatch',
							diffs: diffs.slice( 0, 50 ),
						} );
						fs.writeFileSync(
							path.join( caseDir, 'woo-attribute-expected.json' ),
							JSON.stringify( expectedItems, null, 2 )
						);
						fs.writeFileSync(
							path.join( caseDir, 'woo-attribute-actual.json' ),
							JSON.stringify( actualItems, null, 2 )
						);
						fs.writeFileSync(
							path.join( caseDir, 'woo-attribute-diffs.json' ),
							JSON.stringify( diffs, null, 2 )
						);
					}
				}

				// Verify ACF snapshots (where applicable).
				if ( c.expectAcf ) {
					const srcObjectId = String(
						exportInfo.sourceObjectId || ''
					).trim();
					const targetObjectId = c.resolveTargetObjectId
						? String(
								c.resolveTargetObjectId(
									exportInfo.identity || {}
								) || ''
						  ).trim()
						: '';

					if ( ! srcObjectId ) {
						result.status = 'issues';
						result.errors.push( {
							kind: 'acf-skip',
							reason: 'source-object-id-missing',
						} );
					} else if ( ! targetObjectId ) {
						result.status = 'issues';
						result.errors.push( {
							kind: 'acf-skip',
							reason: 'target-object-id-not-found',
							identity: exportInfo.identity || {},
						} );
					} else {
						const expected = getAcfSnapshotForObject(
							env,
							env.source.wpPath,
							srcObjectId
						);
						const actual = getAcfSnapshotForObject(
							env,
							env.target.wpPath,
							targetObjectId
						);
						const mismatches = diffAcfSnapshots( expected, actual );
						result.acf = {
							sourceObjectId: srcObjectId,
							targetObjectId,
							mismatchesCount: mismatches.length,
						};
						if ( mismatches.length ) {
							result.status = 'issues';
							result.errors.push( {
								kind: 'acf-mismatch',
								mismatches: mismatches.slice( 0, 50 ),
							} );
							fs.writeFileSync(
								path.join( caseDir, 'acf-expected.json' ),
								JSON.stringify( expected, null, 2 )
							);
							fs.writeFileSync(
								path.join( caseDir, 'acf-actual.json' ),
								JSON.stringify( actual, null, 2 )
							);
							fs.writeFileSync(
								path.join( caseDir, 'acf-mismatches.json' ),
								JSON.stringify( mismatches, null, 2 )
							);
						}
					}
				}

				// Media dedup: repeat import and ensure attachments/hash counts do not increase.
				if ( c.repeatImportForDedup && baselineHash ) {
					const targetAttBefore = countAttachments(
						env,
						env.target.wpPath
					);
					const targetHashBeforeCase = countAttachmentsByFileHash(
						env,
						env.target.wpPath,
						baselineHash
					);
					const targetAttrsBefore =
						c.type === 'woo_attribute'
							? getAllWooAttributeSnapshots(
									env,
									env.target.wpPath
							  ).length
							: 0;

					await importIntoTarget(
						targetPage,
						env.target,
						c.type,
						exportPath,
						{
							requiredSelectors:
								exp.required ||
								exportInfo.requiredSelectors ||
								{},
							importOptions: c.importOptions || {},
						}
					);

					const targetAttAfter = countAttachments(
						env,
						env.target.wpPath
					);
					const targetHashAfter = countAttachmentsByFileHash(
						env,
						env.target.wpPath,
						baselineHash
					);
					const targetAttrsAfter =
						c.type === 'woo_attribute'
							? getAllWooAttributeSnapshots(
									env,
									env.target.wpPath
							  ).length
							: 0;

					result.mediaDedup = {
						md5: baselineHash,
						attachments: {
							before: targetAttBefore,
							after: targetAttAfter,
						},
						hashCount: {
							before: targetHashBeforeCase,
							after: targetHashAfter,
						},
					};

					if ( targetAttAfter > targetAttBefore ) {
						result.status = 'issues';
						result.errors.push( {
							kind: 'media-dup-attachments',
							before: targetAttBefore,
							after: targetAttAfter,
						} );
					}
					if (
						targetHashAfter > Math.max( 1, targetHashBeforeCase )
					) {
						result.status = 'issues';
						result.errors.push( {
							kind: 'media-dup-hash',
							md5: baselineHash,
							before: targetHashBeforeCase,
							after: targetHashAfter,
						} );
					}
					if (
						c.type === 'woo_attribute' &&
						targetAttrsAfter !== targetAttrsBefore
					) {
						result.status = 'issues';
						result.errors.push( {
							kind: 'woo-attribute-dup',
							before: targetAttrsBefore,
							after: targetAttrsAfter,
						} );
					}
				}

				// Per-case screenshots (best-effort).
				await sourcePage
					.screenshot( {
						path: path.join( caseDir, 'source-last.png' ),
						fullPage: true,
					} )
					.catch( () => {} );
				await targetPage
					.screenshot( {
						path: path.join( caseDir, 'target-last.png' ),
						fullPage: true,
					} )
					.catch( () => {} );
			} catch ( e ) {
				result.status = 'error';
				result.errors.push( {
					kind: 'exception',
					message: String( e && e.message ? e.message : e ),
				} );
			}

			summary.results.push( result );
			fs.writeFileSync(
				path.join( caseDir, 'result.json' ),
				JSON.stringify( result, null, 2 )
			);
			console.log(
				`[case] ${ c.type } :: ${ c.label } => ${ result.status }${
					result.skipped ? ' (skipped)' : ''
				}`
			);
		}

		// Global dedup baseline stats (target) for visibility.
		const targetAttachmentsAfter = countAttachments(
			env,
			env.target.wpPath
		);
		const targetHashAfterAll = baselineHash
			? countAttachmentsByFileHash( env, env.target.wpPath, baselineHash )
			: 0;
		summary.mediaBaseline = {
			md5: baselineHash,
			targetAttachments: {
				before: targetAttachmentsBefore,
				after: targetAttachmentsAfter,
			},
			targetHashCount: {
				before: targetHashBefore,
				after: targetHashAfterAll,
			},
		};

		// Summarize issues.
		summary.issues = summary.results.filter(
			( r ) => r.status !== 'ok' && r.status !== 'skipped'
		);
		summary.finishedAt = new Date().toISOString();
		fs.writeFileSync(
			path.join( artifactsRoot, 'summary.json' ),
			JSON.stringify( summary, null, 2 )
		);

		if ( summary.issues.length ) process.exitCode = 1;
		console.log(
			`[done] Summary: ${ path.join( artifactsRoot, 'summary.json' ) }`
		);
	} finally {
		await targetPage.close().catch( () => {} );
		await sourcePage.close().catch( () => {} );
		await targetCtx.close().catch( () => {} );
		await sourceCtx.close().catch( () => {} );
		await browser.close().catch( () => {} );
	}
}

run().catch( ( e ) => {
	console.error( e );
	process.exitCode = 1;
} );
