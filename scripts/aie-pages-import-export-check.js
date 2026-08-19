/**
 * Manual E2E (Playwright): Pages export/import check.
 *
 * Flow:
 * - PRO off: export all pages from aie.local, import into aie2.local.
 * - PRO on: repeat and exercise transformation side UI without mutating data.
 * - Restore aie2 from db.sql before each import iteration.
 * - Compare every source page against target in browser: editor fields,
 *   ACF DOM values, SEO/Elementor-related form/meta inputs, featured image,
 *   editor content, and frontend body text.
 *
 * Usage:
 *   PLAYWRIGHT_BROWSERS_PATH=./e2e/.playwright-browsers node scripts/aie-pages-import-export-check.js
 */

const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

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

const FREE_PLUGIN_FILE =
	'import-export-by-rockstarlab/import-export-by-rockstarlab.php';
const PRO_PLUGIN_FILE =
	'import-export-pro-by-rockstarlab/import-export-pro-by-rockstarlab.php';
const REQUIRED_PLUGIN_FILES = [
	'elementor/elementor.php',
	'pro-elements/pro-elements.php',
	'advanced-custom-fields-pro/acf.php',
	'wordpress-seo/wp-seo.php',
	'woocommerce/woocommerce.php',
];

function parseDotEnv( contents ) {
	const env = {};
	for ( const line of contents.split( /\r?\n/ ) ) {
		const trimmed = line.trim();
		if ( ! trimmed || trimmed.startsWith( '#' ) ) continue;
		const idx = trimmed.indexOf( '=' );
		if ( idx === -1 ) continue;
		env[ trimmed.slice( 0, idx ).trim() ] = trimmed.slice( idx + 1 ).trim();
	}
	return env;
}

function loadEnv() {
	const envPath = path.resolve( process.cwd(), '.env.e2e' );
	const fileEnv = fs.existsSync( envPath )
		? parseDotEnv( fs.readFileSync( envPath, 'utf8' ) )
		: {};
	const get = ( key, fallback ) =>
		process.env[ key ] ?? fileEnv[ key ] ?? fallback;
	const sourceWpPath = path.resolve( process.cwd(), '../../..' );
	const targetWpPath = sourceWpPath.replace(
		`${ path.sep }Local Sites${ path.sep }aie${ path.sep }`,
		`${ path.sep }Local Sites${ path.sep }aie2${ path.sep }`
	);
	const headlessRaw = String( get( 'AIE_HEADLESS', 'true' ) ).toLowerCase();
	const phpCandidates = [
		'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php',
		'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php',
	];
	const mysqlCandidates = [
		'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/mysql-8.4.0/bin/darwin-arm64/bin/mysql',
		'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/mysql-8.0.35+4/bin/darwin-arm64/bin/mysql',
	];

	return {
		headless:
			headlessRaw === '1' ||
			headlessRaw === 'true' ||
			headlessRaw === 'yes',
		source: {
			baseUrl: get( 'AIE_SOURCE_URL', 'http://aie.local' ),
			username: get( 'AIE_SOURCE_ADMIN_USER', 'admin' ),
			password: get( 'AIE_SOURCE_ADMIN_PASSWORD', 'admin' ),
			wpPath: get( 'AIE_SOURCE_WP_PATH', sourceWpPath ),
		},
		target: {
			baseUrl: get( 'AIE_TARGET_URL', 'http://aie2.local' ),
			username: get( 'AIE_TARGET_ADMIN_USER', 'admin' ),
			password: get( 'AIE_TARGET_ADMIN_PASSWORD', 'admin' ),
			wpPath: get( 'AIE_TARGET_WP_PATH', targetWpPath ),
		},
		localPhp:
			get( 'AIE_LOCAL_PHP', '' ) ||
			phpCandidates.find( ( p ) => fs.existsSync( p ) ) ||
			'php',
		mysqlBin:
			get( 'AIE_LOCAL_MYSQL', '' ) ||
			mysqlCandidates.find( ( p ) => fs.existsSync( p ) ) ||
			'mysql',
		wpBin: get( 'AIE_WP_BIN', '/opt/homebrew/bin/wp' ),
		targetDbSql: get( 'AIE_TARGET_DB_SQL', 'db.sql' ),
		saveScreenshots:
			String( get( 'AIE_SAVE_SCREENSHOTS', 'false' ) ).toLowerCase() ===
			'true',
	};
}

function nowStamp() {
	return new Date().toISOString().replace( /[:.]/g, '-' );
}

function mkdirp( dir ) {
	fs.mkdirSync( dir, { recursive: true } );
}

function wp( env, wpPath, args, { trim = true } = {} ) {
	const out = execFileSync(
		env.localPhp,
		[
			'-d',
			'display_errors=0',
			'-d',
			'error_reporting=0',
			'-d',
			'html_errors=0',
			'-d',
			'memory_limit=512M',
			env.wpBin,
			`--path=${ wpPath }`,
			...args,
		],
		{
			encoding: 'utf8',
			stdio: [ 'ignore', 'pipe', 'pipe' ],
			timeout: 60_000,
		}
	);
	return trim ? String( out ).trim() : String( out );
}

function wpEvalJson( env, wpPath, code ) {
	const raw = wp( env, wpPath, [ 'eval', code ] );
	try {
		return JSON.parse( raw || 'null' );
	} catch {
		return null;
	}
}

function getLatestImportJob( env, site ) {
	return wpEvalJson(
		env,
		site.wpPath,
		`
global $wpdb;
$table = $wpdb->prefix . 'rsl_ie_jobs';
$job = $wpdb->get_row("SELECT id, type, status, progress, total_items, processed_items, success_items, failed_items, result FROM {$table} WHERE type = 'import' ORDER BY id DESC LIMIT 1", ARRAY_A);
if ($job && ! empty($job['result'])) {
  $decoded = json_decode($job['result'], true);
  if (is_array($decoded)) {
    $job['result_decoded'] = $decoded;
  }
}
echo wp_json_encode($job ?: null, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`
	);
}

function isImportJobComplete( job ) {
	const result = job?.result_decoded || {};
	return (
		job?.status === 'completed' &&
		Number( job?.progress || 0 ) === 100 &&
		Number( result.total || job?.total_items || 0 ) <=
			Number( result.success || 0 ) +
				Number( result.failed || 0 ) +
				Number( result.skipped || 0 )
	);
}

async function waitForLatestImportJobComplete(
	env,
	site,
	timeoutMs = 5 * 60_000
) {
	const started = Date.now();
	let latestJob = null;
	while ( Date.now() - started < timeoutMs ) {
		latestJob = getLatestImportJob( env, site );
		if ( isImportJobComplete( latestJob ) ) {
			return latestJob;
		}
		if ( [ 'failed', 'cancelled' ].includes( latestJob?.status ) ) {
			throw new Error(
				`Latest import job ended as ${
					latestJob.status
				}: ${ JSON.stringify( latestJob ) }`
			);
		}
		await new Promise( ( resolve ) => setTimeout( resolve, 5000 ) );
	}
	throw new Error(
		`Latest import job did not complete within ${ timeoutMs }ms: ${ JSON.stringify(
			latestJob
		) }`
	);
}

function restoreTargetDb( env ) {
	const sqlPath = path.join( env.target.wpPath, env.targetDbSql );
	if ( ! fs.existsSync( sqlPath ) ) {
		throw new Error( `Target DB baseline not found: ${ sqlPath }` );
	}
	const dbName = wp( env, env.target.wpPath, [ 'config', 'get', 'DB_NAME' ] );
	const dbUser = wp( env, env.target.wpPath, [ 'config', 'get', 'DB_USER' ] );
	const dbPass = wp( env, env.target.wpPath, [
		'config',
		'get',
		'DB_PASSWORD',
	] );
	const dbHost = wp( env, env.target.wpPath, [ 'config', 'get', 'DB_HOST' ] );
	const sock = String( dbHost || '' ).startsWith( ':' )
		? String( dbHost ).slice( 1 )
		: '';
	if ( ! sock ) throw new Error( `Unsupported DB_HOST: ${ dbHost }` );

	execFileSync(
		env.mysqlBin,
		[
			'--protocol=socket',
			`--socket=${ sock }`,
			`-u${ dbUser }`,
			`-p${ dbPass }`,
			dbName,
		],
		{ input: fs.readFileSync( sqlPath ) }
	);
	wp( env, env.target.wpPath, [ 'core', 'update-db', '--quiet' ] );
}

function listSourcePages( env ) {
	const php = `
$posts = get_posts([
  'post_type' => 'page',
  'post_status' => 'any',
  'orderby' => 'ID',
  'order' => 'ASC',
  'posts_per_page' => -1,
]);
$out = [];
foreach ($posts as $p) {
  if ('auto-draft' === $p->post_status || 'trash' === $p->post_status) { continue; }
  $out[] = [
    'ID' => (int) $p->ID,
    'post_title' => (string) $p->post_title,
    'post_name' => (string) $p->post_name,
    'post_status' => (string) $p->post_status,
  ];
}
echo wp_json_encode($out, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	return wpEvalJson( env, env.source.wpPath, php ) || [];
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

async function assertPageHealthy( page, label, response = null ) {
	if ( response && response.status() >= 500 ) {
		throw new Error( `${ label } returned HTTP ${ response.status() }` );
	}
	const body = await page
		.locator( 'body' )
		.innerText()
		.catch( () => '' );
	if ( ! body.trim() ) throw new Error( `${ label } rendered blank page` );
}

async function gotoAdmin( page, site, adminPathWithQuery ) {
	await ensureLoggedIn( page, site );
	let response = null;
	let lastError = null;
	for ( let attempt = 1; attempt <= 3; attempt++ ) {
		try {
			response = await page.goto(
				`${ site.baseUrl }${ adminPathWithQuery }`,
				{
					waitUntil: 'domcontentloaded',
					timeout: 60_000,
				}
			);
			lastError = null;
			break;
		} catch ( error ) {
			lastError = error;
			const message = String( error?.message || error );
			if (
				attempt === 3 ||
				! /ERR_ABORTED|frame was detached|Navigation interrupted/i.test(
					message
				)
			) {
				throw error;
			}
			await page.waitForTimeout( 1000 * attempt );
		}
	}
	if ( lastError ) throw lastError;
	await assertPageHealthy( page, adminPathWithQuery, response );
	return response;
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

async function openAllPluginsPage( page, site ) {
	await gotoAdmin( page, site, '/wp-admin/plugins.php?plugin_status=all' );
	await page.locator( '#the-list' ).waitFor( {
		state: 'visible',
		timeout: 30_000,
	} );
}

async function pluginRow( page, pluginFile ) {
	const row = page.locator( `tr[data-plugin="${ pluginFile }"]` ).first();
	await row.waitFor( { state: 'attached', timeout: 10_000 } );
	return row;
}

async function isPluginActive( row ) {
	return row.evaluate( ( el ) => el.classList.contains( 'active' ) );
}

async function handleDeactivateModal( page ) {
	const skip = page
		.locator(
			'button:has-text("Skip & Deactivate"), .button:has-text("Skip & Deactivate"), a:has-text("Skip & Deactivate")'
		)
		.first();
	if ( ! ( await skip.isVisible().catch( () => false ) ) ) return;
	await Promise.all( [
		page
			.waitForNavigation( { waitUntil: 'domcontentloaded' } )
			.catch( () => null ),
		skip.click( { noWaitAfter: true } ),
	] );
}

async function setPluginActive( page, site, pluginFile, active ) {
	console.log(
		`[plugins] ${ site.baseUrl } ${
			active ? 'activate' : 'deactivate'
		} ${ pluginFile }`
	);
	await openPluginsPage( page, site );
	let row = await pluginRow( page, pluginFile ).catch( async () => {
		await openAllPluginsPage( page, site );
		return pluginRow( page, pluginFile );
	} );
	if ( ( await isPluginActive( row ) ) === active ) {
		console.log(
			`[plugins] ${ site.baseUrl } ${ pluginFile } already ${
				active ? 'active' : 'inactive'
			}`
		);
		return;
	}
	const selector = active
		? '.activate a, a[href*="action=activate"]'
		: '.deactivate a, a[href*="action=deactivate"]';
	const href = await row
		.locator(
			active ? 'a:has-text("Activate")' : 'a:has-text("Deactivate")'
		)
		.first()
		.getAttribute( 'href' )
		.catch( () => '' );
	const navigation = page
		.waitForNavigation( {
			waitUntil: 'domcontentloaded',
			timeout: 30_000,
		} )
		.catch( () => null );
	if ( href ) {
		await page.goto( new URL( href, page.url() ).toString(), {
			waitUntil: 'domcontentloaded',
		} );
	} else {
		await row.locator( selector ).first().click( { noWaitAfter: true } );
	}
	if ( ! active ) {
		await Promise.race( [
			navigation,
			page
				.locator(
					'button:has-text("Skip & Deactivate"), .button:has-text("Skip & Deactivate"), a:has-text("Skip & Deactivate")'
				)
				.first()
				.waitFor( { state: 'visible', timeout: 10_000 } )
				.catch( () => null ),
		] );
		await handleDeactivateModal( page );
	} else {
		await navigation;
	}
	await page.waitForLoadState( 'domcontentloaded' ).catch( () => null );
	await openAllPluginsPage( page, site );
	row = await pluginRow( page, pluginFile );
	const actual = await isPluginActive( row );
	if ( actual !== active ) {
		throw new Error(
			`${ pluginFile } active=${ actual }, expected ${ active }`
		);
	}
	console.log(
		`[plugins] ${ site.baseUrl } ${ pluginFile } set to ${
			active ? 'active' : 'inactive'
		}`
	);
}

async function setProStateBothSites( sourcePage, targetPage, env, active ) {
	await setPluginActive( sourcePage, env.source, FREE_PLUGIN_FILE, true );
	await setPluginActive( targetPage, env.target, FREE_PLUGIN_FILE, true );
	ensureRequiredPluginsActive( env, env.source );
	ensureRequiredPluginsActive( env, env.target );
	await setPluginActive( sourcePage, env.source, PRO_PLUGIN_FILE, active );
	await setPluginActive( targetPage, env.target, PRO_PLUGIN_FILE, active );
}

function ensureRequiredPluginsActive( env, site ) {
	for ( const pluginFile of REQUIRED_PLUGIN_FILES ) {
		try {
			console.log(
				`[plugins] ${ site.baseUrl } wp-cli activate ${ pluginFile }`
			);
			wp( env, site.wpPath, [ 'plugin', 'is-installed', pluginFile ] );
			wp( env, site.wpPath, [
				'plugin',
				'activate',
				pluginFile,
				'--quiet',
			] );
		} catch ( error ) {
			console.warn(
				`Could not activate required plugin ${ pluginFile } on ${ site.baseUrl }: ${ error.message }`
			);
		}
	}
}

async function clickNextStep( page ) {
	const next = page
		.locator( '.rsl-ie-step.active .rsl-ie-next-step' )
		.first();
	await next.waitFor( { state: 'visible', timeout: 60_000 } );
	await page.waitForFunction(
		() => {
			const btn = document.querySelector(
				'.rsl-ie-step.active .rsl-ie-next-step'
			);
			return btn && ! btn.disabled;
		},
		null,
		{ timeout: 60_000 }
	);
	await next.click();
}

async function handleBackupModalIfPresent( page ) {
	const modal = page
		.locator(
			'.rsl-ie-backup-warning-overlay, .rsl-ie-backup-modal, #rsl-ie-backup-modal'
		)
		.first();
	await modal
		.waitFor( { state: 'visible', timeout: 5000 } )
		.catch( () => null );
	if ( ! ( await modal.isVisible().catch( () => false ) ) ) return;
	await page
		.locator( '#rsl-ie-backup-created' )
		.check( { force: true } )
		.catch( () => null );
	await page
		.locator( '#rsl-ie-backup-dont-show' )
		.check( { force: true } )
		.catch( () => null );
	const proceed = page
		.locator(
			'.rsl-ie-backup-confirm, button:has-text("Continue"), button:has-text("Proceed"), .rsl-ie-backup-continue, .rsl-ie-skip-backup'
		)
		.first();
	await proceed.click( { force: true } );
	await modal
		.waitFor( { state: 'hidden', timeout: 30_000 } )
		.catch( () => null );
}

async function selectPostContentType( page ) {
	const input = page
		.locator(
			'.rsl-ie-step-1.active input[name="content_type"][value="page"]'
		)
		.first();
	await input.waitFor( { state: 'attached', timeout: 30_000 } );
	await page.evaluate( () => {
		const el = document.querySelector(
			'.rsl-ie-step-1.active input[name="content_type"][value="page"]'
		);
		if ( ! el ) throw new Error( 'Page content type radio not found' );
		el.checked = true;
		el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	} );
}

async function exerciseContentTypeSearch( page ) {
	const search = page.locator( '#rsl-ie-content-type-search' ).first();
	if ( ! ( await search.count() ) ) return {};
	await search.fill( 'zz-no-such-type' );
	const noResultsVisible = await page
		.locator( '.rsl-ie-no-results' )
		.isVisible()
		.catch( () => false );
	await search.fill( 'page' );
	await page.waitForTimeout( 200 );
	await search.fill( '' );
	return { noResultsVisible };
}

async function selectAllExportFields( page, { proMode } = {} ) {
	await page.waitForSelector( '.rsl-ie-step-3.active', { timeout: 60_000 } );
	await page.waitForFunction(
		() =>
			document.querySelectorAll(
				'.rsl-ie-step-3.active .rsl-ie-add-all-fields'
			).length > 0,
		null,
		{ timeout: 60_000 }
	);

	await page
		.locator( '#rsl-ie-fields-search' )
		.fill( 'acf' )
		.catch( () => null );
	await page.waitForTimeout( 150 );
	await page
		.locator( '.rsl-ie-clear-search' )
		.first()
		.click()
		.catch( () => null );

	await page.evaluate( () => {
		document
			.querySelectorAll( '.rsl-ie-step-3.active .rsl-ie-add-all-fields' )
			.forEach( ( btn ) => btn.click() );
	} );
	await page.waitForFunction(
		() => {
			const n = Number(
				document.querySelector(
					'.rsl-ie-step-3.active .rsl-ie-columns-count'
				)?.textContent || '0'
			);
			return n > 0;
		},
		null,
		{ timeout: 60_000 }
	);

	const transformation = proMode
		? await exerciseExportTransformationModal( page )
		: { available: false };

	const next = page.locator( '.rsl-ie-step-3.active .rsl-ie-next-step' );
	await page.waitForFunction(
		() => {
			const btn = document.querySelector(
				'.rsl-ie-step-3.active .rsl-ie-next-step'
			);
			return btn && ! btn.disabled;
		},
		null,
		{ timeout: 60_000 }
	);
	await next.click();
	return transformation;
}

async function exerciseExportTransformationModal( page ) {
	const btn = page
		.locator(
			'.rsl-ie-step-3.active .rsl-ie-add-function, .rsl-ie-step-3.active .rsl-ie-field-functions-btn'
		)
		.first();
	if ( ! ( await btn.count().catch( () => 0 ) ) ) {
		return { available: false };
	}
	await btn.click();
	const modal = page.locator( '#rsl-ie-field-functions-modal' ).first();
	await modal.waitFor( { state: 'visible', timeout: 30_000 } );
	await page
		.locator( '#rsl-ie-functions-search' )
		.fill( 'trim' )
		.catch( () => null );
	await page.waitForTimeout( 250 );
	await page
		.locator( 'input[name="functions-filter"][value="library"]' )
		.check( { force: true } )
		.catch( () => null );
	await page
		.locator( 'input[name="functions-filter"][value="all"]' )
		.check( { force: true } )
		.catch( () => null );
	await page
		.locator( '.rsl-ie-test-pipeline' )
		.click()
		.catch( () => null );
	await page
		.locator(
			'.rsl-ie-modal-close, #rsl-ie-field-functions-modal .button-secondary'
		)
		.first()
		.click();
	await modal
		.waitFor( { state: 'hidden', timeout: 30_000 } )
		.catch( () => null );
	return { available: true };
}

async function exportPages( page, env, outDir, { proMode } ) {
	await gotoAdmin(
		page,
		env.source,
		'/wp-admin/admin.php?page=rsl-ie-export'
	);
	await page.waitForSelector( '#rsl-ie-export', { timeout: 30_000 } );
	const contentSearch = await exerciseContentTypeSearch( page );
	await selectPostContentType( page );
	await clickNextStep( page );

	await page.waitForSelector( '.rsl-ie-step-2.active', { timeout: 30_000 } );
	await page
		.locator( '.rsl-ie-refresh-count' )
		.click()
		.catch( () => null );
	await page.locator( '.rsl-ie-add-filter' ).click();
	await page.locator( '.rsl-ie-remove-filter' ).last().click();
	await clickNextStep( page );

	const transformation = await selectAllExportFields( page, { proMode } );

	await page.waitForSelector( '.rsl-ie-step-4.active', { timeout: 30_000 } );
	await selectHiddenRadio( page, 'format', 'json' );
	await setInputChecked( page, 'json_pretty_print', false );
	await selectHiddenRadio( page, 'format', 'csv' );
	await page.locator( 'select[name="csv_delimiter"]' ).selectOption( ';' );
	await setInputChecked( page, 'csv_include_header', false );
	await setInputChecked( page, 'csv_include_header', true );
	await setInputValue( page, 'items_per_iteration', '2' );

	await page.locator( '.rsl-ie-start-export' ).click();
	await page.waitForSelector( '.rsl-ie-step-5.active', { timeout: 60_000 } );
	await page.locator( '.rsl-ie-export-complete-card' ).waitFor( {
		state: 'visible',
		timeout: 10 * 60_000,
	} );

	const [ download ] = await Promise.all( [
		page.waitForEvent( 'download', { timeout: 60_000 } ),
		page.locator( '.rsl-ie-download-file' ).click(),
	] );
	const exportPath = path.join(
		outDir,
		`${ proMode ? 'pro' : 'free' }-pages.csv`
	);
	await download.saveAs( exportPath );

	await page
		.locator( '.rsl-ie-new-export' )
		.click()
		.catch( () => null );
	return { exportPath, contentSearch, transformation };
}

async function selectHiddenRadio( page, name, value ) {
	await page.evaluate(
		( args ) => {
			const el = document.querySelector(
				`input[name="${ args.name }"][value="${ args.value }"]`
			);
			if ( ! el ) return;
			el.checked = true;
			el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			el.dispatchEvent( new Event( 'click', { bubbles: true } ) );
		},
		{ name, value }
	);
}

async function setInputChecked( page, name, checked ) {
	await page.evaluate(
		( args ) => {
			const el = document.querySelector( `input[name="${ args.name }"]` );
			if ( ! el ) return;
			el.checked = !! args.checked;
			el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			el.dispatchEvent( new Event( 'click', { bubbles: true } ) );
		},
		{ name, checked }
	);
}

async function setInputValue( page, name, value ) {
	await page.evaluate(
		( args ) => {
			const el = document.querySelector( `input[name="${ args.name }"]` );
			if ( ! el ) return;
			el.value = args.value;
			el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		},
		{ name, value }
	);
}

async function importPages( page, env, csvPath, { proMode } ) {
	await gotoAdmin(
		page,
		env.target,
		'/wp-admin/admin.php?page=rsl-ie-import'
	);
	await page.waitForSelector( '#rsl-ie-import', { timeout: 30_000 } );
	const contentSearch = await exerciseContentTypeSearch( page );
	await selectPostContentType( page );
	await page.locator( '.rsl-ie-step-1.active .rsl-ie-next-step' ).click();
	await handleBackupModalIfPresent( page );

	await page.waitForSelector( '.rsl-ie-step-2.active', { timeout: 30_000 } );
	await page.setInputFiles( '#rsl-ie-file-input', csvPath );
	await page
		.locator( '#csv_delimiter' )
		.selectOption( ';' )
		.catch( () => null );
	await page.waitForFunction(
		() => {
			const btn = document.querySelector(
				'.rsl-ie-step-2.active .rsl-ie-next-step'
			);
			return btn && ! btn.disabled;
		},
		null,
		{ timeout: 60_000 }
	);
	await page.locator( '.rsl-ie-remove-file' ).click();
	await page.setInputFiles( '#rsl-ie-file-input', csvPath );
	await page.waitForFunction(
		() => {
			const btn = document.querySelector(
				'.rsl-ie-step-2.active .rsl-ie-next-step'
			);
			return btn && ! btn.disabled;
		},
		null,
		{ timeout: 60_000 }
	);
	await clickNextStep( page );

	await page.waitForSelector( '.rsl-ie-step-3.active', { timeout: 30_000 } );
	await clickNextStep( page );

	await page.waitForSelector( '.rsl-ie-step-4.active', { timeout: 60_000 } );
	await page
		.locator( '.rsl-ie-search-source' )
		.fill( 'post' )
		.catch( () => null );
	await page
		.locator( '.rsl-ie-search-source + .dashicons + .rsl-ie-clear-search' )
		.click()
		.catch( () => null );
	await page.locator( '.rsl-ie-clear-map' ).click();
	await page.waitForTimeout( 250 );
	await page.locator( '.rsl-ie-auto-map' ).click();
	await page.waitForFunction(
		() => {
			const mapped = Number(
				document.querySelector(
					'.rsl-ie-step-4.active .rsl-ie-mapped-count'
				)?.textContent || '0'
			);
			return mapped > 0;
		},
		null,
		{ timeout: 60_000 }
	);
	const mapping = await page.evaluate( () => ( {
		mapped: Number(
			document.querySelector(
				'.rsl-ie-step-4.active .rsl-ie-mapped-count'
			)?.textContent || '0'
		),
		total: Number(
			document.querySelector(
				'.rsl-ie-step-4.active .rsl-ie-total-fields'
			)?.textContent || '0'
		),
		hasTransformationButtons:
			document.querySelectorAll(
				'.rsl-ie-step-4.active .rsl-ie-add-function'
			).length > 0,
	} ) );
	if ( proMode && mapping.hasTransformationButtons ) {
		await exerciseImportTransformationModal( page );
	}
	await clickNextStep( page );

	await page.waitForSelector( '.rsl-ie-step-5.active', { timeout: 30_000 } );
	await selectUniqueField( page, [ 'post_name', 'post_title', 'ID' ] );
	await page
		.locator(
			'.rsl-ie-step-5.active input[name="if_exists"][value="update"]'
		)
		.check( { force: true } );
	await page
		.locator(
			'.rsl-ie-step-5.active input[name="if_not_exists"][value="create"]'
		)
		.check( { force: true } );
	await page
		.locator( '.rsl-ie-step-5.active input[name="batch_size"]' )
		.fill( '1' );
	await page
		.locator( '.rsl-ie-step-5.active input[name="batch_size"]' )
		.fill( '10' );
	await page
		.locator( '.rsl-ie-step-5.active #rsl-ie-auto-import-media' )
		.check( { force: true } )
		.catch( () => null );
	await page
		.locator(
			'.rsl-ie-step-5.active input[name="media_duplicate_mode"][value="skip"]'
		)
		.check( { force: true } )
		.catch( () => null );
	await page
		.locator(
			'.rsl-ie-step-5.active input[name="media_duplicate_mode"][value="create"]'
		)
		.check( { force: true } )
		.catch( () => null );
	await page
		.locator(
			'.rsl-ie-step-5.active input[name="media_duplicate_mode"][value="replace"]'
		)
		.check( { force: true } )
		.catch( () => null );
	await page
		.locator(
			'.rsl-ie-step-5.active input[name="media_duplicate_mode"][value="skip"]'
		)
		.check( { force: true } )
		.catch( () => null );

	const startButtonState = await page
		.locator( '.rsl-ie-step-5.active .rsl-ie-start-import' )
		.evaluate( ( el ) => ( {
			text: el.textContent,
			disabled: el.disabled,
			visible: !! (
				el.offsetWidth ||
				el.offsetHeight ||
				el.getClientRects().length
			),
		} ) );
	const importStartResponse = page
		.waitForResponse(
			( response ) =>
				response.url().includes( 'admin-ajax.php' ) &&
				( response.request().postData() || '' ).includes(
					'action=rsl_ie_import_start'
				),
			{ timeout: 10_000 }
		)
		.catch( () => null );
	await page.evaluate( () => {
		document
			.querySelector( '.rsl-ie-step-5.active .rsl-ie-start-import' )
			?.click();
	} );
	const startResponse = await importStartResponse;
	const startResponseDebug = startResponse
		? {
				status: startResponse.status(),
				body: await startResponse.text().catch( () => '' ),
		  }
		: null;
	await handleBackupModalIfPresent( page );
	await page
		.waitForSelector( '.rsl-ie-step-6.active', { timeout: 60_000 } )
		.catch( async () => {
			const debug = await page.evaluate(
				( args ) => ( {
					url: window.location.href,
					activeStep:
						document.querySelector( '.rsl-ie-step.active' )
							?.className || '',
					activeStepText:
						document
							.querySelector( '.rsl-ie-step.active' )
							?.textContent?.slice( 0, 3000 ) || '',
					startButtonInitial: args.initialButtonState,
					startButtonNow: {
						text:
							document.querySelector(
								'.rsl-ie-step-5.active .rsl-ie-start-import'
							)?.textContent || '',
						disabled:
							document.querySelector(
								'.rsl-ie-step-5.active .rsl-ie-start-import'
							)?.disabled || false,
					},
					importStartRequestSeen: !! args.importStartRequestSeen,
					importStartResponse: args.startResponseDebug,
					uniqueField:
						document.querySelector(
							'.rsl-ie-step-5.active #rsl-ie-unique-field'
						)?.value || '',
					options: {
						ifExists:
							document.querySelector(
								'.rsl-ie-step-5.active input[name="if_exists"]:checked'
							)?.value || '',
						ifNotExists:
							document.querySelector(
								'.rsl-ie-step-5.active input[name="if_not_exists"]:checked'
							)?.value || '',
						batchSize:
							document.querySelector(
								'.rsl-ie-step-5.active input[name="batch_size"]'
							)?.value || '',
					},
					notices: Array.from(
						document.querySelectorAll(
							'.notice, .rsl-ie-notice, .updated, .error'
						)
					).map( ( el ) => el.textContent.trim() ),
					body: document.body.textContent.slice( 0, 3000 ),
				} ),
				{
					initialButtonState: startButtonState,
					importStartRequestSeen: !! startResponse,
					startResponseDebug,
				}
			);
			throw new Error(
				`Import did not advance to step 6: ${ JSON.stringify( debug ) }`
			);
		} );
	let completionCardVisible = true;
	await page
		.locator( '.rsl-ie-import-complete-card' )
		.waitFor( {
			state: 'visible',
			timeout: 15_000,
		} )
		.catch( async () => {
			await waitForLatestImportJobComplete( env, env.target );
			completionCardVisible = false;
		} );
	await page
		.locator( '.rsl-ie-new-import' )
		.click()
		.catch( () => null );
	return { contentSearch, mapping, completionCardVisible };
}

async function selectUniqueField( page, preferredValues ) {
	const select = page.locator( '#rsl-ie-unique-field' ).first();
	await select.waitFor( { state: 'visible', timeout: 60_000 } );
	for ( const value of preferredValues ) {
		if ( await select.locator( `option[value="${ value }"]` ).count() ) {
			await select.selectOption( value );
			return value;
		}
	}
	const first = await select
		.locator( 'option[value]:not([value=""])' )
		.first()
		.getAttribute( 'value' );
	if ( first ) await select.selectOption( first );
	return first || '';
}

async function exerciseImportTransformationModal( page ) {
	await page
		.locator( '.rsl-ie-step-4.active .rsl-ie-add-function' )
		.first()
		.click();
	const modal = page.locator( '#rsl-ie-field-functions-modal' ).first();
	await modal.waitFor( { state: 'visible', timeout: 30_000 } );
	await page
		.locator( '#rsl-ie-functions-search' )
		.fill( 'trim' )
		.catch( () => null );
	await page
		.locator( 'input[name="functions-filter"][value="library"]' )
		.check( { force: true } )
		.catch( () => null );
	await page
		.locator( 'input[name="functions-filter"][value="all"]' )
		.check( { force: true } )
		.catch( () => null );
	await page
		.locator( '.rsl-ie-test-pipeline' )
		.click()
		.catch( () => null );
	await page
		.locator(
			'.rsl-ie-modal-close, #rsl-ie-field-functions-modal .button-secondary'
		)
		.first()
		.click();
	await modal
		.waitFor( { state: 'hidden', timeout: 30_000 } )
		.catch( () => null );
}

async function openSourcePageEdit( page, env, postId ) {
	await gotoAdmin(
		page,
		env.source,
		`/wp-admin/post.php?post=${ postId }&action=edit`
	);
	await page.waitForSelector( 'body', { timeout: 30_000 } );
}

async function openTargetPageEditByTitle( page, env, title, slug = '' ) {
	for ( const term of [ title, slug ].filter( Boolean ) ) {
		await gotoAdmin(
			page,
			env.target,
			`/wp-admin/edit.php?post_type=page&s=${ encodeURIComponent(
				term
			) }`
		);
		const exactTitle = page
			.locator( 'a.row-title', { hasText: title } )
			.first();
		if ( await exactTitle.count() ) {
			const href = await exactTitle.getAttribute( 'href' );
			if ( href ) {
				const editUrl = new URL( href, env.target.baseUrl );
				await gotoAdmin(
					page,
					env.target,
					`${ editUrl.pathname }${ editUrl.search }`
				);
			} else {
				await exactTitle.click();
				await page
					.waitForLoadState( 'domcontentloaded' )
					.catch( () => null );
			}
			await assertPageHealthy( page, `target page edit ${ title }` );
			return;
		}
		const first = page.locator( 'a.row-title' ).first();
		if ( slug && term === slug && ( await first.count() ) ) {
			const href = await first.getAttribute( 'href' );
			if ( href ) {
				const editUrl = new URL( href, env.target.baseUrl );
				await gotoAdmin(
					page,
					env.target,
					`${ editUrl.pathname }${ editUrl.search }`
				);
			} else {
				await first.click();
				await page
					.waitForLoadState( 'domcontentloaded' )
					.catch( () => null );
			}
			await assertPageHealthy( page, `target page edit ${ title }` );
			return;
		}
	}
	if ( slug ) {
		const resolved = wpEvalJson(
			env,
			env.target.wpPath,
			`
$slug = ${ JSON.stringify( slug ) };
$title = ${ JSON.stringify( title ) };
$p = get_page_by_path($slug, OBJECT, 'page');
if (!$p) {
  global $wpdb;
  $p = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status <> 'trash' AND (post_name = %s OR post_title = %s) ORDER BY ID ASC LIMIT 1",
    $slug,
    $title
  ));
}
echo wp_json_encode(['id' => $p ? (int) $p->ID : 0], JSON_UNESCAPED_SLASHES);
`
		);
		if ( resolved && resolved.id ) {
			await gotoAdmin(
				page,
				env.target,
				`/wp-admin/post.php?post=${ resolved.id }&action=edit`
			);
			await assertPageHealthy( page, `target page edit ${ title }` );
			return;
		}
	}
	throw new Error(
		`Target page not found in browser by title/slug: ${ title } / ${ slug }`
	);
}

function normalizeText( value, site ) {
	return String( value ?? '' )
		.split( site.baseUrl )
		.join( '__BASE__' )
		.replace( /https?:\/\/aie2?\.local/g, '__BASE__' )
		.replace(
			/https?:\/\/wpthemetestdata\.files\.wordpress\.com\/\d{4}\/\d{2}\/([^"'\s)<>]+)/g,
			( match, file ) => {
				return `/wp-content/uploads/__MEDIA__/${ file
					.replace( /\?.*$/, '' )
					.replace( /(-\d+)+(?=\.[a-z0-9]+$)/i, '' ) }`;
			}
		)
		.replace(
			/\/wp-content\/uploads\/\d{4}\/\d{2}\/([^"'\s)<>]+)/g,
			( match, file ) => {
				return `/wp-content/uploads/__MEDIA__/${ file
					.replace( /\?.*$/, '' )
					.replace( /(-\d+)+(?=\.[a-z0-9]+$)/i, '' ) }`;
			}
		)
		.replace(
			/__BASE__\/wp-content\/uploads\/__MEDIA__/g,
			'/wp-content/uploads/__MEDIA__'
		)
		.replace(
			/\/wp-content\/uploads\/__MEDIA__\/([^"'\s)<>]+)/g,
			( match, file ) => {
				return `/wp-content/uploads/__MEDIA__/${ file
					.replace( /\?.*$/, '' )
					.replace( /(-\d+)+(?=\.[a-z0-9]+$)/i, '' ) }`;
			}
		)
		.replace(
			/-\d+(?=\.(?:jpg|jpeg|png|gif|webp|avif|svg|pdf|mp3|m4a|ogg|wav|mp4|m4v|webm|ogv|flv|mov|avi|wmv))/gi,
			''
		)
		.replace( /\s+/g, ' ' )
		.trim();
}

function normalizeComparableValue( value ) {
	return String( value ?? '' )
		.replace( /https?:\/\/aie2?\.local/g, '__BASE__' )
		.replace(
			/https?:\/\/wpthemetestdata\.files\.wordpress\.com\/\d{4}\/\d{2}\/([^"'\s)<>]+)/g,
			( match, file ) => {
				return `/wp-content/uploads/__MEDIA__/${ file
					.replace( /\?.*$/, '' )
					.replace( /(-\d+)+(?=\.[a-z0-9]+$)/i, '' ) }`;
			}
		)
		.replace(
			/\/wp-content\/uploads\/\d{4}\/\d{2}\/([^"'\s)<>]+)/g,
			( match, file ) => {
				return `/wp-content/uploads/__MEDIA__/${ file
					.replace( /\?.*$/, '' )
					.replace( /(-\d+)+(?=\.[a-z0-9]+$)/i, '' ) }`;
			}
		)
		.replace(
			/__BASE__\/wp-content\/uploads\/__MEDIA__/g,
			'/wp-content/uploads/__MEDIA__'
		)
		.replace(
			/\/wp-content\/uploads\/__MEDIA__\/([^"'\s)<>]+)/g,
			( match, file ) => {
				return `/wp-content/uploads/__MEDIA__/${ file
					.replace( /\?.*$/, '' )
					.replace( /(-\d+)+(?=\.[a-z0-9]+$)/i, '' ) }`;
			}
		)
		.replace(
			/-\d+(?=\.(?:jpg|jpeg|png|gif|webp|avif|svg|pdf|mp3|m4a|ogg|wav|mp4|m4v|webm|ogv|flv|mov|avi|wmv))/gi,
			''
		)
		.replace( /post=(\d+)/g, 'post=__NUMERIC__' )
		.replace( /attachment_id=(\d+)/g, 'attachment_id=__NUMERIC__' )
		.replace( /\b\d{10,}\b/g, '__NUMERIC__' )
		.replace( /\s+/g, ' ' )
		.trim();
}

function normalizeEditorContentValue( value ) {
	return normalizeComparableValue( value )
		.replace(
			/\[(audio|video)\s+((?:\/wp-content\/uploads\/__MEDIA__\/|__BASE__\/wp-content\/uploads\/__MEDIA__\/)[^\]\s]+)\]/gi,
			'[$1 src="$2"]'
		)
		.replace(
			/"providerNameSlug"\s*:\s*"twitter"/g,
			'"providerNameSlug":"x"'
		)
		.replace( /\bis-provider-twitter\b/g, 'is-provider-x' )
		.replace( /\bwp-block-embed-twitter\b/g, 'wp-block-embed-x' )
		.replace( /\[gallery([^\]]*)\]/gi, ( match, attrs ) => {
			const normalizedAttrs = attrs
				.replace( /\s+ids=(["'])[^"']*\1/gi, '' )
				.replace( /\s+/g, ' ' )
				.trim();
			return `[gallery${
				normalizedAttrs ? ` ${ normalizedAttrs }` : ''
			}]`;
		} )
		.replace( /\bwp-image-\d+\b/g, 'wp-image-__NUMERIC__' )
		.replace( /\battachment_\d+\b/g, 'attachment___NUMERIC__' )
		.replace( /"(?:id|mediaId)"\s*:\s*\d+/g, ( match ) =>
			match.replace( /\d+$/, '__NUMERIC__' )
		)
		.replace( /\s\/>/g, '/>' )
		.replace( /&gt;/g, '>' )
		.replace( /&lt;/g, '<' );
}

function extractRawPageMetaSnapshot( env, site, postId ) {
	if ( ! postId ) return {};
	return (
		wpEvalJson(
			env,
			site.wpPath,
			`
$post_id = (int) ${ JSON.stringify( postId ) };
$all = get_post_meta($post_id);
$out = [];
foreach ($all as $key => $values) {
  if (0 === strpos($key, '_elementor_') || '_wp_page_template' === $key || 0 === strpos($key, '_yoast_wpseo_') || 0 === strpos($key, 'rank_math_') || 0 === strpos($key, 'acf_')) {
    $out[$key] = array_map('maybe_unserialize', $values);
  }
}
ksort($out);
echo wp_json_encode($out, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`
		) || {}
	);
}

function stableRawMetaForCompare( meta ) {
	const stable = {};
	for ( const [ key, value ] of Object.entries( meta || {} ) ) {
		if (
			/(_edit_lock|nonce|css|controls_usage|element_cache|page_assets)/i.test(
				key
			)
		)
			continue;
		if (
			/_yoast_wpseo_(estimated-reading-time-minutes|linkdex|content_score|inclusive_language_score|primary_category_term)$/i.test(
				key
			)
		)
			continue;
		const comparableValue =
			Array.isArray( value ) &&
			/^(_yoast_wpseo_|rank_math_|_wp_page_template$|_elementor_(?:version|pro_version|edit_mode|template_type))/i.test(
				key
			)
				? [
						...new Set(
							value.map( ( item ) => JSON.stringify( item ) )
						),
				  ].map( ( item ) => JSON.parse( item ) )
				: value;
		stable[ normalizeComparableValue( key ) ] =
			normalizeElementorLikeRawValueForCompare( comparableValue );
	}
	return stable;
}

function normalizeElementorLikeRawValueForCompare( value ) {
	let raw = JSON.stringify( value ?? '' );
	for ( let i = 0; i < 3; i++ ) {
		try {
			const decoded = JSON.parse(
				Array.isArray( value ) && value.length === 1 ? value[ 0 ] : raw
			);
			raw = JSON.stringify( decoded );
			value = decoded;
		} catch {
			break;
		}
	}

	let normalized = normalizeComparableValue( raw )
		.replace( /\\\//g, '/' )
		.replace( /https?:\/\/aie2?\.local/g, '__BASE__' )
		.replace( /https?:\\\/\\\/aie2?\.local/g, '__BASE__' )
		.replace(
			/\/wp-content\/uploads\/\d{4}\/\d{2}\//g,
			'/wp-content/uploads/__DATE__/'
		)
		.replace(
			/\/wp-content\/uploads\/test-media-uploads\/\d{2}\//g,
			'/wp-content/uploads/__DATE__/'
		)
		.replace(
			/__BASE__\/wp-content\/uploads\/__DATE__\//g,
			'/wp-content/uploads/__MEDIA__/'
		)
		.replace(
			/\/wp-content\/uploads\/__DATE__\//g,
			'/wp-content/uploads/__MEDIA__/'
		)
		.replace( /"id"\s*:\s*\d+/g, '"id":__NUMERIC__' )
		.replace( /\\"id\\"\s*:\s*\d+/g, '\\"id\\":__NUMERIC__' )
		.replace( /"post_id"\s*:\s*\d+/g, '"post_id":__NUMERIC__' )
		.replace( /\\"post_id\\"\s*:\s*\d+/g, '\\"post_id\\":__NUMERIC__' )
		.replace( /\b(elementor-page-|post-|page-id-)\d+\b/g, '$1__NUMERIC__' )
		.replace( /\bwp-image-\d+\b/g, 'wp-image-__NUMERIC__' )
		.replace( /\battachment_\d+\b/g, 'attachment___NUMERIC__' )
		.replace( /\b(ids=\\?["'])[\d,]+/g, '$1__NUMERIC__' );
	let previous = '';
	while ( previous !== normalized ) {
		previous = normalized;
		normalized = normalized.replace(
			/\/([^/"]+?)-\d+(\.(?:jpe?g|png|gif|webp|svg|avif|pdf|mp4|webm)\b)/gi,
			'/$1$2'
		);
	}
	return normalized;
}

async function extractPostBrowserSnapshot( page, site ) {
	await page.waitForTimeout( 1000 );
	return page.evaluate( ( baseUrl ) => {
		const norm = ( value ) =>
			String( value ?? '' )
				.split( baseUrl )
				.join( '__BASE__' )
				.replace( /\s+/g, ' ' )
				.trim();
		const val = ( selector ) => {
			const el = document.querySelector( selector );
			if ( ! el ) return '';
			return 'value' in el ? el.value : el.textContent;
		};
		const checkedValues = ( selector ) =>
			Array.from( document.querySelectorAll( selector ) )
				.filter( ( el ) => el.checked )
				.map( ( el ) => norm( el.value ) );
		const selectedValues = ( selector ) =>
			Array.from( document.querySelectorAll( selector ) ).flatMap(
				( el ) =>
					Array.from( el.selectedOptions || [] ).map( ( o ) =>
						norm( o.value || o.textContent )
					)
			);
		const editor = ( () => {
			try {
				const select = window.wp?.data?.select?.( 'core/editor' );
				const post = select?.getCurrentPost?.() || {};
				const content =
					select?.getEditedPostContent?.() || post.content?.raw || '';
				return {
					id: post.id || '',
					title: norm(
						post.title?.raw || post.title || val( '#title' )
					),
					slug: norm(
						post.slug || val( '#editable-post-name-full' )
					),
					status: norm(
						post.status ||
							selectedValues(
								'select[name="post_status"]'
							)[ 0 ] ||
							''
					),
					date: norm( post.date || val( '#aa' ) ),
					content: norm( content ),
					excerpt: norm( post.excerpt?.raw || val( '#excerpt' ) ),
					featured_media:
						post.featured_media || val( '#_thumbnail_id' ),
				};
			} catch ( e ) {
				return {
					title: norm( val( '#title' ) ),
					slug: norm( val( '#editable-post-name-full' ) ),
					status: norm(
						selectedValues( 'select[name="post_status"]' )[ 0 ] ||
							''
					),
					date: norm( val( '#aa' ) ),
					content: norm( val( '#content' ) ),
					excerpt: norm( val( '#excerpt' ) ),
					featured_media: norm( val( '#_thumbnail_id' ) ),
				};
			}
		} )();

		const acf = Array.from( document.querySelectorAll( '.acf-field' ) )
			.filter(
				( field ) =>
					! field.closest( '.acf-clone, [data-id="acfcloneindex"]' )
			)
			.map( ( field ) => {
				const name = field.getAttribute( 'data-name' ) || '';
				const type = field.getAttribute( 'data-type' ) || '';
				const label =
					field.querySelector( '.acf-label label' )?.textContent ||
					name;
				const inputRoot =
					field.querySelector( ':scope > .acf-input' ) || field;
				const values = [];
				if (
					! [
						'repeater',
						'flexible_content',
						'group',
						'accordion',
						'tab',
						'message',
					].includes( type )
				) {
					inputRoot
						.querySelectorAll( 'input, textarea, select' )
						.forEach( ( el ) => {
							if (
								el.closest(
									'.acf-clone, [data-id="acfcloneindex"]'
								)
							)
								return;
							if (
								[ 'button', 'submit', 'reset' ].includes(
									el.type
								)
							)
								return;
							if (
								el.type === 'hidden' &&
								/^acf\[field_/.test( el.name || '' ) &&
								type !== 'icon_picker'
							)
								return;
							if (
								el.type === 'checkbox' ||
								el.type === 'radio'
							) {
								if ( ! el.checked ) return;
								if ( type === 'taxonomy' ) {
									values.push(
										el.closest( 'label' )?.textContent ||
											el.value
									);
								} else {
									values.push( `${ el.name }=${ el.value }` );
								}
							} else if ( el.tagName === 'SELECT' ) {
								values.push(
									Array.from( el.selectedOptions || [] )
										.map( ( o ) =>
											type === 'taxonomy'
												? o.textContent || o.value
												: o.value || o.textContent
										)
										.join( ',' )
								);
							} else {
								values.push( el.value );
							}
						} );
				}
				const visibleText = inputRoot.textContent || '';
				return {
					name: norm( name ),
					type: norm( type ),
					label: norm( label ),
					values: values.map( norm ).filter( Boolean ),
					text: norm( visibleText ).slice( 0, 500 ),
				};
			} )
			.filter( ( x ) => x.name || x.label || x.values.length );

		const metaInputs = Array.from(
			document.querySelectorAll(
				'input[name], textarea[name], select[name]'
			)
		)
			.filter(
				( el ) =>
					/(yoast|rank_math|elementor|_thumbnail_id|post_status|hidden_post_status|post_name|post_author|post_date|comment_status|ping_status)/i.test(
						el.name || ''
					) && ! el.closest( '.acf-clone, [data-id="acfcloneindex"]' )
			)
			.filter(
				( el ) => ! [ 'button', 'submit', 'reset' ].includes( el.type )
			)
			.map( ( el ) => ( {
				name: norm( el.name || '' ),
				value:
					el.type === 'checkbox' || el.type === 'radio'
						? el.checked
							? norm( el.value )
							: ''
						: norm( el.value || '' ),
			} ) )
			.filter( ( x ) => x.name && x.value !== '' );

		const featuredText =
			document.querySelector(
				'.editor-post-featured-image, #postimagediv'
			)?.textContent || '';
		const categories = checkedValues(
			'#categorychecklist input[type="checkbox"], .editor-post-taxonomies__hierarchical-terms-list input[type="checkbox"]'
		);
		const tags = norm(
			val( '.components-form-token-field__input-container' ) ||
				val( '#new-tag-post_tag' ) ||
				document.querySelector( '.tagchecklist' )?.textContent ||
				''
		);

		return {
			editor,
			acf,
			metaInputs,
			featuredText: norm( featuredText ),
			categories,
			tags,
			bodySignals: norm( document.body.textContent || '' ).slice(
				0,
				4000
			),
		};
	}, site.baseUrl );
}

async function extractFrontendSnapshot( page, site, browserSnapshot ) {
	const slug = browserSnapshot.editor.slug;
	if ( ! slug ) return { ok: false, reason: 'no-slug' };
	const url = `${ site.baseUrl.replace( /\/$/, '' ) }/${ slug }/`;
	const response = await page
		.goto( url, { waitUntil: 'domcontentloaded' } )
		.catch( () => null );
	if ( ! response || response.status() >= 400 ) {
		return { ok: false, url, status: response ? response.status() : 0 };
	}
	await page.waitForTimeout( 500 );
	const rawText = await page.evaluate( () => {
		const source =
			document.querySelector( 'main' ) ||
			document.querySelector( 'article' ) ||
			document.body;
		const clone = source.cloneNode( true );
		clone
			.querySelectorAll(
				'#wpadminbar, #comments, .comments-area, .comment-respond, script, style, noscript, iframe'
			)
			.forEach( ( el ) => el.remove() );
		return clone.innerText || clone.textContent || '';
	} );
	const text = normalizeText( rawText, site );
	return {
		ok: true,
		url,
		title: await page.title(),
		text: text.slice( 0, 6000 ),
	};
}

function stableSnapshotForCompare( snap ) {
	const copy = JSON.parse( JSON.stringify( snap || {} ) );
	delete copy.editor.id;
	delete copy.editor.featured_media;
	copy.metaInputs = ( copy.metaInputs || [] )
		.filter(
			( x ) =>
				! /^(_wpnonce|_acf|_acf_|acf_nonce|post_ID)$/i.test( x.name ) &&
				! /nonce|acfcloneindex|_wp_http_referer/i.test( x.name ) &&
				! /yoast_wpseo_(estimated-reading-time-minutes|linkdex|content_score|inclusive_language_score|primary_category_term)$/i.test(
					x.name
				)
		)
		.map( ( x ) => ( {
			name: normalizeComparableValue( x.name ),
			value: /^\d+$/.test( x.value )
				? '__NUMERIC__'
				: normalizeComparableValue( x.value ),
		} ) )
		.sort( ( a, b ) =>
			( a.name + a.value ).localeCompare( b.name + b.value )
		);
	copy.acf = ( copy.acf || [] )
		.filter( ( x ) => ! /acfcloneindex/i.test( JSON.stringify( x ) ) )
		.map( ( x ) => ( {
			name: normalizeComparableValue( x.name ),
			type: normalizeComparableValue( x.type ),
			label: normalizeComparableValue( x.label ),
			values: ( x.values || [] )
				.map( ( v ) =>
					/^\d+$/.test( v )
						? '__NUMERIC__'
						: normalizeComparableValue( v )
				)
				.filter(
					( v ) =>
						! [
							'Clear',
							'Bulk actions',
							'Select post type',
							'Select taxonomy',
						].includes( v ) &&
						! /^acf-icon-picker-list-icon-radio=/.test( v )
				)
				.sort(),
		} ) )
		.filter( ( x ) => {
			if ( x.values.length ) return true;
			return ! [
				'accordion',
				'tab',
				'message',
				'repeater',
				'flexible_content',
				'group',
			].includes( x.type );
		} )
		.sort( ( a, b ) =>
			( a.name + a.label ).localeCompare( b.name + b.label )
		);
	copy.editor = Object.fromEntries(
		Object.entries( copy.editor || {} ).map( ( [ key, value ] ) => [
			key,
			key === 'content'
				? normalizeEditorContentValue( value )
				: normalizeComparableValue( value ),
		] )
	);
	copy.featuredText = normalizeComparableValue( copy.featuredText || '' );
	copy.categories = ( copy.categories || [] )
		.map( normalizeComparableValue )
		.sort();
	copy.tags = normalizeComparableValue( copy.tags || '' );
	delete copy.bodySignals;
	return copy;
}

function signatureForCompare( value ) {
	return JSON.stringify( value );
}

function diffMultiset( expected, actual, prefix ) {
	const left = new Map();
	const right = new Map();
	for ( const item of expected || [] ) {
		const sig = signatureForCompare( item );
		left.set( sig, ( left.get( sig ) || 0 ) + 1 );
	}
	for ( const item of actual || [] ) {
		const sig = signatureForCompare( item );
		right.set( sig, ( right.get( sig ) || 0 ) + 1 );
	}
	const diffs = [];
	const keys = new Set( [ ...left.keys(), ...right.keys() ] );
	for ( const key of keys ) {
		const l = left.get( key ) || 0;
		const r = right.get( key ) || 0;
		if ( l !== r ) {
			const marker = l > r ? 'missing on target' : 'extra on target';
			diffs.push(
				`${ prefix }: ${ marker } x${ Math.abs( l - r ) } ${ key.slice(
					0,
					500
				) }`
			);
		}
		if ( diffs.length >= 25 ) break;
	}
	return diffs;
}

function diffObjects( expected, actual, prefix = '' ) {
	const diffs = [];
	if ( typeof expected !== typeof actual ) {
		return [
			`${ prefix }: type ${ typeof expected } != ${ typeof actual }`,
		];
	}
	if (
		expected &&
		typeof expected === 'object' &&
		! Array.isArray( expected )
	) {
		const keys = new Set( [
			...Object.keys( expected ),
			...Object.keys( actual || {} ),
		] );
		for ( const key of keys ) {
			diffs.push(
				...diffObjects(
					expected[ key ],
					actual ? actual[ key ] : undefined,
					prefix ? `${ prefix }.${ key }` : key
				)
			);
			if ( diffs.length > 50 ) break;
		}
		return diffs;
	}
	const left = JSON.stringify( expected );
	const right = JSON.stringify( actual );
	return left === right ? [] : [ `${ prefix }: ${ left } != ${ right }` ];
}

async function compareAllPagesInBrowser(
	sourcePage,
	targetPage,
	frontPage,
	env,
	posts,
	outDir,
	label
) {
	const results = [];
	for ( let index = 0; index < posts.length; index++ ) {
		const post = posts[ index ];
		const dir = path.join( outDir, `${ label }-page-${ post.ID }` );
		mkdirp( dir );
		fs.writeFileSync(
			path.join( outDir, 'compare-progress.json' ),
			JSON.stringify(
				{
					index: index + 1,
					total: posts.length,
					id: post.ID,
					title: post.post_title,
					startedAt: new Date().toISOString(),
				},
				null,
				2
			)
		);
		console.log(
			`[${ label }] compare ${ index + 1 }/${ posts.length }: ${
				post.ID
			} ${ post.post_title }`
		);
		await openSourcePageEdit( sourcePage, env, post.ID );
		const sourceSnap = await extractPostBrowserSnapshot(
			sourcePage,
			env.source
		);
		const sourceRawMeta = extractRawPageMetaSnapshot(
			env,
			env.source,
			sourceSnap.editor.id || post.ID
		);
		if ( env.saveScreenshots ) {
			await sourcePage.screenshot( {
				path: path.join( dir, 'source-editor.png' ),
				fullPage: true,
			} );
		}
		const sourceFront = await extractFrontendSnapshot(
			frontPage,
			env.source,
			sourceSnap
		);

		await openTargetPageEditByTitle(
			targetPage,
			env,
			sourceSnap.editor.title || post.post_title,
			sourceSnap.editor.slug || post.post_name
		);
		const targetSnap = await extractPostBrowserSnapshot(
			targetPage,
			env.target
		);
		const targetRawMeta = extractRawPageMetaSnapshot(
			env,
			env.target,
			targetSnap.editor.id
		);
		if ( env.saveScreenshots ) {
			await targetPage.screenshot( {
				path: path.join( dir, 'target-editor.png' ),
				fullPage: true,
			} );
		}
		const targetFront = await extractFrontendSnapshot(
			frontPage,
			env.target,
			targetSnap
		);

		const sourceStable = stableSnapshotForCompare( sourceSnap );
		const targetStable = stableSnapshotForCompare( targetSnap );
		const editorDiffs = diffObjects(
			sourceStable.editor,
			targetStable.editor,
			'editor'
		);
		const acfDiffs = diffMultiset(
			sourceStable.acf,
			targetStable.acf,
			'acf'
		);
		const metaDiffs = diffMultiset(
			sourceStable.metaInputs,
			targetStable.metaInputs,
			'metaInputs'
		);
		const rawMetaDiffs = diffObjects(
			stableRawMetaForCompare( sourceRawMeta ),
			stableRawMetaForCompare( targetRawMeta ),
			'rawMeta'
		);
		const hasDynamicCrossContentBlocks =
			/wp:(navigation|query|page-list|archives|categories|latest-posts|latest-comments|rss|calendar|search|tag-cloud)/.test(
				sourceSnap.editor.content || ''
			);
		const hasRandomGalleryShortcode =
			/\[gallery\b[^\]]*\borderby=(["'])?rand\1?/i.test(
				sourceSnap.editor.content || ''
			);
		const hasDynamicCommerceFrontend =
			/^shop$/i.test( sourceSnap.editor.slug || post.post_name || '' ) ||
			/\b(Showing \d|Default sorting|Add to cart|No products were found matching your selection)\b/i.test(
				sourceFront.text || targetFront.text || ''
			);
		const elementorRawForDynamicCheck = JSON.stringify(
			sourceRawMeta._elementor_data || ''
		);
		const hasDynamicElementorFrontend =
			/widgetType\\*"?\s*:\s*\\*"?(?:posts|portfolio|products|woocommerce-products|loop-grid|loop-carousel|archive-posts|archive-products|taxonomy-filter|sidebar|global|template|counter|progress|nav-menu|countdown|contact-buttons[^"\\]*|wp-widget-[^"\\]+)\\*"?/i.test(
				elementorRawForDynamicCheck
			);
		const frontendDiffs =
			! hasDynamicCrossContentBlocks &&
			! hasRandomGalleryShortcode &&
			! hasDynamicCommerceFrontend &&
			! hasDynamicElementorFrontend &&
			sourceFront.ok &&
			targetFront.ok &&
			sourceFront.text !== targetFront.text
				? [ 'frontend body text differs' ]
				: [];
		const result = {
			sourceId: post.ID,
			sourceType: 'page',
			title: sourceSnap.editor.title,
			targetUrl: targetPage.url(),
			sourceFront,
			targetFront,
			diffs: [
				...editorDiffs,
				...acfDiffs,
				...metaDiffs,
				...rawMetaDiffs,
				...frontendDiffs,
			],
			source: sourceSnap,
			target: targetSnap,
			sourceRawMeta,
			targetRawMeta,
		};
		fs.writeFileSync(
			path.join( dir, 'result.json' ),
			JSON.stringify( result, null, 2 )
		);
		results.push( result );
	}
	return results;
}

async function exerciseJobsPage( page, site ) {
	page.on( 'dialog', ( dialog ) => dialog.accept().catch( () => null ) );
	await gotoAdmin( page, site, '/wp-admin/admin.php?page=rsl-ie-jobs-log' );
	if (
		! ( await page
			.locator( '#rsl-ie-jobs-log' )
			.waitFor( { state: 'visible', timeout: 30_000 } )
			.then( () => true )
			.catch( () => false ) )
	) {
		return {
			firstRowText: '',
			restartClicked: false,
			finalUrl: page.url(),
			issue: 'Jobs table did not become visible',
		};
	}
	await page.waitForFunction(
		() => document.querySelectorAll( '#jobs-table-body tr' ).length > 0,
		null,
		{ timeout: 60_000 }
	);
	await page
		.locator( '#filter-type' )
		.selectOption( 'export' )
		.catch( () => null );
	await page
		.locator( '.rsl-ie-filter-apply' )
		.click()
		.catch( () => null );
	await page.waitForTimeout( 1000 );
	const firstRowText = await page
		.locator( '#jobs-table-body tr' )
		.first()
		.innerText()
		.catch( () => '' );
	const view = page.locator( '#jobs-table-body .job-action-view' ).first();
	if ( await view.count() ) {
		await view.click();
		const modalVisible = await page
			.locator( '#job-details-modal' )
			.waitFor( { state: 'visible', timeout: 30_000 } )
			.then( () => true )
			.catch( () => false );
		if ( modalVisible ) {
			await page
				.locator( '#job-details-modal .rsl-ie-modal-close' )
				.first()
				.click();
		}
	}
	const restart = page
		.locator( '#jobs-table-body .job-action-restart' )
		.first();
	let restartClicked = false;
	if ( await restart.count() ) {
		await restart.click();
		await page.waitForLoadState( 'domcontentloaded' ).catch( () => null );
		restartClicked = true;
	}
	return { firstRowText, restartClicked, finalUrl: page.url() };
}

async function runIteration( ctx, env, posts, outRoot, { proMode } ) {
	const sourcePage = await ctx.newPage();
	const targetPage = await ctx.newPage();
	const frontPage = await ctx.newPage();
	const label = proMode ? 'pro-on' : 'pro-off';
	const outDir = path.join( outRoot, label );
	mkdirp( outDir );

	console.log( `[${ label }] restore target db` );
	restoreTargetDb( env );
	console.log( `[${ label }] set plugin state` );
	await setProStateBothSites( sourcePage, targetPage, env, proMode );
	console.log( `[${ label }] export pages` );
	const exported = await exportPages( sourcePage, env, outDir, { proMode } );
	console.log( `[${ label }] import pages` );
	const imported = await importPages( targetPage, env, exported.exportPath, {
		proMode,
	} );
	console.log( `[${ label }] compare pages` );
	const comparisons = await compareAllPagesInBrowser(
		sourcePage,
		targetPage,
		frontPage,
		env,
		posts,
		outDir,
		label
	);
	console.log( `[${ label }] jobs page` );
	const jobs = await exerciseJobsPage( sourcePage, env.source );
	await sourcePage.close();
	await targetPage.close();
	await frontPage.close();
	return { label, exported, imported, comparisons, jobs };
}

async function run() {
	const env = loadEnv();
	const posts = listSourcePages( env );
	if ( ! posts.length ) throw new Error( 'No source pages found' );
	const outRoot = path.resolve(
		process.cwd(),
		'e2e/artifacts/pages-import-export',
		nowStamp()
	);
	mkdirp( outRoot );
	const summary = {
		startedAt: new Date().toISOString(),
		source: env.source.baseUrl,
		target: env.target.baseUrl,
		pageCount: posts.length,
		results: [],
		issues: [],
		artifacts: outRoot,
	};

	const browser = await chromium.launch( { headless: env.headless } );
	const ctx = await browser.newContext( { acceptDownloads: true } );
	try {
		for ( const proMode of [ false, true ] ) {
			const result = await runIteration( ctx, env, posts, outRoot, {
				proMode,
			} );
			summary.results.push( result );
			for ( const cmp of result.comparisons ) {
				if ( cmp.diffs.length ) {
					summary.issues.push( {
						iteration: result.label,
						sourceId: cmp.sourceId,
						title: cmp.title,
						diffs: cmp.diffs.slice( 0, 25 ),
					} );
				}
			}
		}
	} catch ( e ) {
		summary.issues.push( {
			kind: 'exception',
			message: String( e && e.message ? e.message : e ),
		} );
		process.exitCode = 1;
	} finally {
		summary.finishedAt = new Date().toISOString();
		fs.writeFileSync(
			path.join( outRoot, 'summary.json' ),
			JSON.stringify( summary, null, 2 )
		);
		console.log(
			JSON.stringify(
				{
					startedAt: summary.startedAt,
					finishedAt: summary.finishedAt,
					pageCount: summary.pageCount,
					issueCount: summary.issues.length,
					artifacts: summary.artifacts,
					results: summary.results.map( ( result ) => ( {
						label: result.label,
						comparisons: result.comparisons.length,
						diffPages: result.comparisons.filter(
							( cmp ) => cmp.diffs.length
						).length,
						exportPath: result.exported.exportPath,
						importJob: result.imported.job || null,
						jobs: result.jobs,
					} ) ),
					issues: summary.issues.slice( 0, 20 ),
				},
				null,
				2
			)
		);
		await ctx.close().catch( () => null );
		await browser.close().catch( () => null );
	}

	if ( summary.issues.length ) process.exitCode = 1;
}

run().catch( ( e ) => {
	console.error( e );
	process.exitCode = 1;
} );
