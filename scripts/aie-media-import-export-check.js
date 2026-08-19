/**
 * Manual E2E (Playwright): Media export/import check.
 *
 * Flow:
 * - PRO on: export all media from aie.local, import into aie2.local.
 * - Restore aie2 from db.sql before import.
 * - Exercise export/import wizard options in headless browser.
 * - Verify every exported media filename in the target Media Library UI.
 * - Compare attachment fields via browser edit screens and WP data snapshots.
 *
 * Usage:
 *   AIE_HEADLESS=true PLAYWRIGHT_BROWSERS_PATH=./e2e/.playwright-browsers node scripts/aie-media-import-export-check.js
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );
const { chromium } = require( 'playwright' );

const FREE_PLUGIN_FILE =
	'import-export-by-rockstarlab/import-export-by-rockstarlab.php';
const PRO_PLUGIN_FILE =
	'import-export-pro-by-rockstarlab/import-export-pro-by-rockstarlab.php';

function getEnv( key, fallback ) {
	return process.env[ key ] ?? fallback;
}

function loadEnv() {
	const sourceWpPath = path.resolve( process.cwd(), '../../..' );
	const targetWpPath = sourceWpPath.replace(
		`${ path.sep }Local Sites${ path.sep }aie${ path.sep }`,
		`${ path.sep }Local Sites${ path.sep }aie2${ path.sep }`
	);
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
	const phpCandidates = [
		'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php',
		'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php',
	];
	const mysqlCandidates = [
		'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/mysql-8.4.0/bin/darwin-arm64/bin/mysql',
		'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/mysql-8.0.35+4/bin/darwin-arm64/bin/mysql',
	];
	return {
		headless: ! /^(false|0|no)$/i.test( getEnv( 'AIE_HEADLESS', 'true' ) ),
		source: {
			baseUrl: getEnv( 'AIE_SOURCE_URL', 'http://aie.local' ),
			username: getEnv( 'AIE_SOURCE_ADMIN_USER', 'admin' ),
			password: getEnv( 'AIE_SOURCE_ADMIN_PASSWORD', 'admin' ),
			wpPath: getEnv( 'AIE_SOURCE_WP_PATH', sourceWpPath ),
		},
		target: {
			baseUrl: getEnv( 'AIE_TARGET_URL', 'http://aie2.local' ),
			username: getEnv( 'AIE_TARGET_ADMIN_USER', 'admin' ),
			password: getEnv( 'AIE_TARGET_ADMIN_PASSWORD', 'admin' ),
			wpPath: getEnv( 'AIE_TARGET_WP_PATH', targetWpPath ),
		},
		localPhp:
			getEnv( 'AIE_LOCAL_PHP', '' ) ||
			phpCandidates.find( ( p ) => fs.existsSync( p ) ) ||
			'php',
		mysqlBin:
			getEnv( 'AIE_LOCAL_MYSQL', '' ) ||
			mysqlCandidates.find( ( p ) => fs.existsSync( p ) ) ||
			'mysql',
		wpBin: getEnv( 'AIE_WP_BIN', '/opt/homebrew/bin/wp' ),
		targetDbSql: getEnv( 'AIE_TARGET_DB_SQL', 'db.sql' ),
	};
}

function nowStamp() {
	return new Date().toISOString().replace( /[:.]/g, '-' );
}

function wp( env, wpPath, args, options = {} ) {
	return execFileSync(
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
			timeout: options.timeout || 120_000,
		}
	).trim();
}

function wpJson( env, wpPath, code ) {
	const out = wp( env, wpPath, [ 'eval', code ], { timeout: 180_000 } );
	return JSON.parse( out || 'null' );
}

function ensurePluginActive( env, site, pluginFile ) {
	try {
		wp( env, site.wpPath, [ 'plugin', 'is-active', pluginFile ] );
	} catch {
		wp( env, site.wpPath, [ 'plugin', 'activate', pluginFile ] );
	}
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
	const socket = String( dbHost ).startsWith( ':' )
		? String( dbHost ).slice( 1 )
		: '';
	if ( ! socket ) throw new Error( `Unsupported DB_HOST: ${ dbHost }` );
	execFileSync(
		env.mysqlBin,
		[
			'--protocol=socket',
			`--socket=${ socket }`,
			`-u${ dbUser }`,
			`-p${ dbPass }`,
			dbName,
		],
		{ input: fs.readFileSync( sqlPath ), timeout: 180_000 }
	);
	wp( env, env.target.wpPath, [ 'core', 'update-db', '--quiet' ] );
}

function getMediaSnapshot( env, wpPath ) {
	return wpJson(
		env,
		wpPath,
		`
$posts = get_posts([
  'post_type' => 'attachment',
  'post_status' => 'inherit',
  'orderby' => 'ID',
  'order' => 'ASC',
  'posts_per_page' => -1,
]);
$out = [];
foreach ($posts as $p) {
  $file = (string) get_post_meta($p->ID, '_wp_attached_file', true);
  $meta = wp_get_attachment_metadata($p->ID);
  $out[] = [
    'ID' => (int) $p->ID,
    'post_title' => (string) $p->post_title,
    'post_content' => (string) $p->post_content,
    'post_excerpt' => (string) $p->post_excerpt,
    'post_date' => (string) $p->post_date,
    'post_status' => (string) $p->post_status,
    'post_mime_type' => (string) $p->post_mime_type,
    'file' => $file,
    'filename' => basename($file),
    'url' => (string) wp_get_attachment_url($p->ID),
    'alt_text' => (string) get_post_meta($p->ID, '_wp_attachment_image_alt', true),
    'width' => is_array($meta) && isset($meta['width']) ? (int) $meta['width'] : 0,
    'height' => is_array($meta) && isset($meta['height']) ? (int) $meta['height'] : 0,
    'sizes' => is_array($meta) && isset($meta['sizes']) && is_array($meta['sizes']) ? array_keys($meta['sizes']) : [],
  ];
}
echo wp_json_encode($out, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`
	);
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

async function gotoAdmin( page, site, adminPath ) {
	await ensureLoggedIn( page, site );
	const response = await page.goto( `${ site.baseUrl }${ adminPath }`, {
		waitUntil: 'domcontentloaded',
		timeout: 60_000,
	} );
	if ( response && response.status() >= 500 ) {
		throw new Error(
			`${ adminPath } returned HTTP ${ response.status() }`
		);
	}
	const body = await page
		.locator( 'body' )
		.innerText()
		.catch( () => '' );
	if ( ! body.trim() )
		throw new Error( `${ adminPath } rendered blank page` );
}

async function clickNext( page ) {
	await page.waitForFunction(
		() => {
			const btn = document.querySelector(
				'.rsl-ie-step.active .rsl-ie-next-step'
			);
			return btn && ! btn.disabled;
		},
		null,
		{ timeout: 90_000 }
	);
	await page.locator( '.rsl-ie-step.active .rsl-ie-next-step' ).click();
}

async function selectMediaContentType( page ) {
	await page.locator( '#rsl-ie-content-type-search' ).fill( 'zz-no-match' );
	await page.waitForTimeout( 200 );
	await page.locator( '#rsl-ie-content-type-search' ).fill( 'media' );
	await page.waitForTimeout( 200 );
	await page.evaluate( () => {
		const input = document.querySelector(
			'.rsl-ie-step-1.active input[name="content_type"][value="media"]'
		);
		if ( ! input ) throw new Error( 'Media content type radio not found' );
		input.checked = true;
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'click', { bubbles: true } ) );
	} );
}

async function selectRadio( page, name, value ) {
	await page.evaluate(
		( args ) => {
			const el = document.querySelector(
				`input[name="${ args.name }"][value="${ args.value }"]`
			);
			if ( ! el ) return false;
			el.checked = true;
			el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			el.dispatchEvent( new Event( 'click', { bubbles: true } ) );
			return true;
		},
		{ name, value }
	);
}

async function setCheckbox( page, name, checked ) {
	await page.evaluate(
		( args ) => {
			const el = document.querySelector( `input[name="${ args.name }"]` );
			if ( ! el ) return false;
			el.checked = args.checked;
			el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			return true;
		},
		{ name, checked }
	);
}

async function setNamedInputValue( page, name, value ) {
	await page.evaluate(
		( args ) => {
			const el = document.querySelector( `input[name="${ args.name }"]` );
			if ( ! el ) return false;
			el.value = args.value;
			el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			return true;
		},
		{ name, value }
	);
}

async function setSelectValue( page, selector, value ) {
	await page.evaluate(
		( args ) => {
			const el = document.querySelector( args.selector );
			if ( ! el ) return false;
			el.value = args.value;
			el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			return true;
		},
		{ selector, value }
	);
}

async function exerciseExportTransformModal( page ) {
	const button = page
		.locator( '#rsl-ie-csv-columns .rsl-ie-add-function' )
		.first();
	if ( ! ( await button.count() ) ) return false;
	await button.click();
	const modal = page.locator( '#rsl-ie-field-functions-modal' );
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
		.locator( '.rsl-ie-modal-close' )
		.first()
		.click()
		.catch( () => null );
	await modal
		.waitFor( { state: 'hidden', timeout: 30_000 } )
		.catch( () => null );
	return true;
}

async function exportMedia( page, env, outDir ) {
	await gotoAdmin(
		page,
		env.source,
		'/wp-admin/admin.php?page=rsl-ie-export'
	);
	await page.waitForSelector( '#rsl-ie-export', { timeout: 30_000 } );
	await selectMediaContentType( page );
	await clickNext( page );

	await page.waitForSelector( '.rsl-ie-step-2.active', { timeout: 30_000 } );
	await page
		.locator( '.rsl-ie-refresh-count' )
		.click()
		.catch( () => null );
	await page.locator( '.rsl-ie-add-filter' ).click();
	await page.waitForTimeout( 250 );
	await page.locator( '.rsl-ie-remove-filter' ).last().click();
	await page.evaluate( () => {
		document
			.querySelectorAll( '.rsl-ie-step-2.active .rsl-ie-filter-row' )
			.forEach( ( row ) => row.remove() );
	} );
	await clickNext( page );

	await page.waitForSelector( '.rsl-ie-step-3.active', { timeout: 30_000 } );
	await page
		.locator( '#rsl-ie-fields-search' )
		.fill( 'file' )
		.catch( () => null );
	await page.waitForTimeout( 200 );
	await page
		.locator( '.rsl-ie-clear-search' )
		.first()
		.click()
		.catch( () => null );
	await page
		.locator( '.rsl-ie-clear-all-fields' )
		.click()
		.catch( () => null );
	await page.evaluate( () => {
		document
			.querySelectorAll( '.rsl-ie-step-3.active .rsl-ie-add-all-fields' )
			.forEach( ( button ) => {
				const category = button.closest( '.rsl-ie-field-category' );
				const visible =
					category &&
					getComputedStyle( category ).display !== 'none' &&
					category.getClientRects().length > 0;
				if ( visible ) button.click();
			} );
	} );
	await page.waitForFunction(
		() =>
			Number(
				document.querySelector( '.rsl-ie-columns-count' )
					?.textContent || '0'
			) > 0 ||
			document.querySelectorAll(
				'#rsl-ie-csv-columns .rsl-ie-csv-column'
			).length > 0,
		null,
		{ timeout: 60_000 }
	);
	const transformationModal = await exerciseExportTransformModal( page );
	await clickNext( page );

	await page.waitForSelector( '.rsl-ie-step-4.active', { timeout: 30_000 } );
	await selectRadio( page, 'format', 'json' );
	await setCheckbox( page, 'json_pretty_print', false );
	await setCheckbox( page, 'json_pretty_print', true );
	await selectRadio( page, 'format', 'csv' );
	for ( const delimiter of [ ';', 'tab', '|', 'custom', ',' ] ) {
		await page
			.locator( 'select[name="csv_delimiter"]' )
			.selectOption( delimiter );
		if ( delimiter === 'custom' ) {
			await page
				.locator( 'input[name="csv_custom_delimiter"]' )
				.fill( '*****' );
		}
	}
	await setCheckbox( page, 'csv_include_header', false );
	await setCheckbox( page, 'csv_include_header', true );
	await setNamedInputValue( page, 'items_per_iteration', '1' );
	await setNamedInputValue( page, 'items_per_iteration', '5' );

	await page.locator( '.rsl-ie-start-export' ).click();
	await page.locator( '.rsl-ie-export-complete-card' ).waitFor( {
		state: 'visible',
		timeout: 15 * 60_000,
	} );
	const [ download ] = await Promise.all( [
		page.waitForEvent( 'download', { timeout: 60_000 } ),
		page.locator( '.rsl-ie-download-file' ).click(),
	] );
	const exportPath = path.join( outDir, 'media-export.csv' );
	await download.saveAs( exportPath );
	const lineCount = fs
		.readFileSync( exportPath, 'utf8' )
		.split( /\r?\n/ )
		.filter( ( line ) => line.trim() !== '' ).length;
	if ( lineCount <= 1 ) {
		throw new Error(
			`Media export did not include data rows: ${ exportPath }`
		);
	}
	return { exportPath, transformationModal };
}

async function handleBackupModalIfPresent( page ) {
	const warning = page.locator( '.rsl-ie-backup-warning-overlay' );
	if ( await warning.count() ) {
		await warning
			.waitFor( { state: 'visible', timeout: 3000 } )
			.catch( () => null );
		if ( await warning.isVisible().catch( () => false ) ) {
			await page
				.locator( '#rsl-ie-backup-created' )
				.check( { force: true } )
				.catch( () => null );
			await page
				.locator( '#rsl-ie-backup-dont-show' )
				.check( { force: true } )
				.catch( () => null );
			await page.locator( '.rsl-ie-backup-confirm' ).click();
			await warning
				.waitFor( { state: 'hidden', timeout: 30_000 } )
				.catch( () => null );
			return true;
		}
	}
	const modal = page.locator( '#rsl-ie-import-backup-modal' );
	if ( ! ( await modal.count() ) ) return false;
	await modal
		.waitFor( { state: 'visible', timeout: 3000 } )
		.catch( () => null );
	if ( ! ( await modal.isVisible().catch( () => false ) ) ) return false;
	await page
		.locator( '#rsl-ie-skip-backup' )
		.click()
		.catch( () => null );
	await modal
		.waitFor( { state: 'hidden', timeout: 30_000 } )
		.catch( () => null );
	return true;
}

async function selectUniqueField( page ) {
	const select = page.locator( '#rsl-ie-unique-field' );
	await select.waitFor( { state: 'visible', timeout: 60_000 } );
	const values = await select
		.locator( 'option' )
		.evaluateAll( ( options ) =>
			options.map( ( o ) => o.value ).filter( Boolean )
		);
	for ( const preferred of [
		'file',
		'url',
		'filename',
		'file_name',
		'ID',
	] ) {
		if ( values.includes( preferred ) ) {
			await select.selectOption( preferred );
			return { selected: preferred, values };
		}
	}
	await select.selectOption( values[ 0 ] );
	return { selected: values[ 0 ], values };
}

async function waitForLatestImportJobComplete( env, timeoutMs = 10 * 60_000 ) {
	const started = Date.now();
	let latest = null;
	while ( Date.now() - started < timeoutMs ) {
		latest = wpJson(
			env,
			env.target.wpPath,
			`
global $wpdb;
$table = $wpdb->prefix . 'rsl_ie_jobs';
$job = $wpdb->get_row("SELECT id, type, status, progress, total_items, processed_items, success_items, failed_items, result FROM {$table} WHERE type = 'import' ORDER BY id DESC LIMIT 1", ARRAY_A);
if ($job && ! empty($job['result'])) {
  $decoded = json_decode($job['result'], true);
  if (is_array($decoded)) { $job['result_decoded'] = $decoded; }
}
echo wp_json_encode($job ?: null, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`
		);
		if (
			latest?.status === 'completed' &&
			Number( latest.progress || 0 ) >= 100
		) {
			return latest;
		}
		if ( [ 'failed', 'cancelled' ].includes( latest?.status ) ) {
			throw new Error(
				`Import job failed: ${ JSON.stringify( latest ) }`
			);
		}
		await new Promise( ( resolve ) => setTimeout( resolve, 5000 ) );
	}
	throw new Error( `Import job timeout: ${ JSON.stringify( latest ) }` );
}

async function importMedia( page, env, csvPath ) {
	await gotoAdmin(
		page,
		env.target,
		'/wp-admin/admin.php?page=rsl-ie-import'
	);
	await page.waitForSelector( '#rsl-ie-import', { timeout: 30_000 } );
	await selectMediaContentType( page );
	await clickNext( page );
	await handleBackupModalIfPresent( page );

	await page.waitForSelector( '.rsl-ie-step-2.active', { timeout: 30_000 } );
	for ( const delimiter of [ ';', 'tab', '|', 'custom', ',' ] ) {
		await setSelectValue( page, '#csv_delimiter', delimiter );
		if ( delimiter === 'custom' ) {
			await setNamedInputValue( page, 'csv_custom_delimiter', '*****' );
		}
	}
	await page.setInputFiles( '#rsl-ie-file-input', csvPath );
	await page.waitForFunction(
		() =>
			!! document.querySelector(
				'.rsl-ie-step-2.active .rsl-ie-file-info'
			) &&
			! document.querySelector(
				'.rsl-ie-step-2.active .rsl-ie-next-step'
			)?.disabled,
		null,
		{ timeout: 60_000 }
	);
	await page.locator( '.rsl-ie-remove-file' ).click();
	await page.setInputFiles( '#rsl-ie-file-input', csvPath );
	await clickNext( page );

	await page.waitForSelector( '.rsl-ie-step-3.active', { timeout: 30_000 } );
	await clickNext( page );

	await page.waitForSelector( '.rsl-ie-step-4.active', { timeout: 60_000 } );
	await page
		.locator( '.rsl-ie-search-source' )
		.fill( 'file' )
		.catch( () => null );
	await page
		.locator( '.rsl-ie-clear-search' )
		.first()
		.click()
		.catch( () => null );
	await page.locator( '.rsl-ie-clear-map' ).click();
	await page.waitForTimeout( 250 );
	await page.locator( '.rsl-ie-auto-map' ).click();
	await page.waitForFunction(
		() =>
			Number(
				document.querySelector(
					'.rsl-ie-step-4.active .rsl-ie-mapped-count'
				)?.textContent || '0'
			) > 0,
		null,
		{ timeout: 60_000 }
	);
	const mapped = await page.evaluate( () => ( {
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
		hasFunctions:
			document.querySelectorAll(
				'.rsl-ie-step-4.active .rsl-ie-add-function'
			).length > 0,
	} ) );
	if ( mapped.hasFunctions ) await exerciseExportTransformModal( page );
	await clickNext( page );

	await page.waitForSelector( '.rsl-ie-step-5.active', { timeout: 30_000 } );
	const unique = await selectUniqueField( page );
	for ( const value of [ 'skip', 'update' ] ) {
		await page
			.locator(
				`.rsl-ie-step-5.active input[name="if_exists"][value="${ value }"]`
			)
			.check( { force: true } )
			.catch( () => null );
	}
	await page
		.locator(
			'.rsl-ie-step-5.active input[name="if_exists"][value="update"]'
		)
		.check( { force: true } )
		.catch( () => null );
	for ( const value of [ 'skip', 'create' ] ) {
		await page
			.locator(
				`.rsl-ie-step-5.active input[name="if_not_exists"][value="${ value }"]`
			)
			.check( { force: true } )
			.catch( () => null );
	}
	await page
		.locator(
			'.rsl-ie-step-5.active input[name="if_not_exists"][value="create"]'
		)
		.check( { force: true } )
		.catch( () => null );
	await setNamedInputValue( page, 'batch_size', '1' );
	await setNamedInputValue( page, 'batch_size', '5' );
	for ( const value of [ 'skip', 'create', 'replace', 'skip' ] ) {
		await page
			.locator(
				`.rsl-ie-step-5.active input[name="media_duplicate_mode"][value="${ value }"]`
			)
			.check( { force: true } )
			.catch( () => null );
	}

	await page.locator( '.rsl-ie-step-5.active .rsl-ie-start-import' ).click();
	await handleBackupModalIfPresent( page );
	await page.waitForSelector( '.rsl-ie-step-6.active', { timeout: 60_000 } );
	const completeVisible = await page
		.locator( '.rsl-ie-import-complete-card' )
		.waitFor( { state: 'visible', timeout: 60_000 } )
		.then( () => true )
		.catch( () => false );
	const job = await waitForLatestImportJobComplete( env );
	return { mapped, unique, completeVisible, job };
}

function normalizeUrl( value ) {
	return String( value || '' )
		.replace( /^https?:\/\/aie2?\.local/i, 'http://site.local' )
		.replace( /-\d+(?=\.[a-z0-9]+$)/i, '' );
}

function compareMedia( source, target ) {
	const targetByFile = new Map(
		target.map( ( item ) => [ item.file, item ] )
	);
	const issues = [];
	for ( const item of source ) {
		const actual = targetByFile.get( item.file );
		if ( ! actual ) {
			issues.push( {
				filename: item.filename,
				file: item.file,
				field: 'missing_target_attachment',
				expected: item.post_title,
				actual: null,
			} );
			continue;
		}
		for ( const field of [
			'post_title',
			'post_content',
			'post_excerpt',
			'post_status',
			'post_mime_type',
			'alt_text',
			'width',
			'height',
		] ) {
			if (
				String( item[ field ] || '' ) !==
				String( actual[ field ] || '' )
			) {
				issues.push( {
					filename: item.filename,
					file: item.file,
					field,
					expected: item[ field ],
					actual: actual[ field ],
				} );
			}
		}
		if ( normalizeUrl( item.url ) !== normalizeUrl( actual.url ) ) {
			issues.push( {
				filename: item.filename,
				file: item.file,
				field: 'url',
				expected: normalizeUrl( item.url ),
				actual: normalizeUrl( actual.url ),
			} );
		}
	}
	return issues;
}

async function verifyMediaLibraryInBrowser( page, site, expected, outDir ) {
	const missing = [];
	const checked = [];
	for ( const item of expected ) {
		const search = item.filename.replace( /\.[^.]+$/, '' );
		await gotoAdmin(
			page,
			site,
			`/wp-admin/upload.php?mode=list&s=${ encodeURIComponent( search ) }`
		);
		const body = await page.locator( 'body' ).innerText();
		const ok =
			body.includes( item.filename ) ||
			body.includes( item.post_title ) ||
			body.includes( search );
		checked.push( { filename: item.filename, ok } );
		if ( ! ok ) {
			missing.push( item.filename );
			await page.screenshot( {
				path: path.join(
					outDir,
					`missing-${ item.ID }-${ search }.png`
				),
				fullPage: true,
			} );
		}
	}
	return { checked, missing };
}

async function main() {
	const env = loadEnv();
	const outDir = path.resolve(
		process.cwd(),
		'e2e/artifacts/media-import-export',
		nowStamp()
	);
	fs.mkdirSync( outDir, { recursive: true } );

	for ( const site of [ env.source, env.target ] ) {
		ensurePluginActive( env, site, FREE_PLUGIN_FILE );
		ensurePluginActive( env, site, PRO_PLUGIN_FILE );
	}

	console.log( '[media] snapshot source' );
	const sourceSnapshot = getMediaSnapshot( env, env.source.wpPath );
	fs.writeFileSync(
		path.join( outDir, 'source-media.json' ),
		JSON.stringify( sourceSnapshot, null, 2 )
	);
	if ( ! sourceSnapshot.length )
		throw new Error( 'Source media library is empty' );

	console.log( '[media] restore target db' );
	restoreTargetDb( env );
	ensurePluginActive( env, env.target, FREE_PLUGIN_FILE );
	ensurePluginActive( env, env.target, PRO_PLUGIN_FILE );

	const browser = await chromium.launch( { headless: env.headless } );
	const context = await browser.newContext( { acceptDownloads: true } );
	const page = await context.newPage();

	try {
		console.log( '[media] export all media' );
		const exported = await exportMedia( page, env, outDir );
		console.log( `[media] exported ${ exported.exportPath }` );

		console.log( '[media] import media' );
		const imported = await importMedia( page, env, exported.exportPath );

		console.log( '[media] verify media library in browser' );
		const browserCheck = await verifyMediaLibraryInBrowser(
			page,
			env.target,
			sourceSnapshot,
			outDir
		);

		console.log( '[media] compare snapshots' );
		const targetSnapshot = getMediaSnapshot( env, env.target.wpPath );
		fs.writeFileSync(
			path.join( outDir, 'target-media.json' ),
			JSON.stringify( targetSnapshot, null, 2 )
		);
		const issues = [
			...browserCheck.missing.map( ( filename ) => ( {
				filename,
				field: 'media_library_browser_missing',
			} ) ),
			...compareMedia( sourceSnapshot, targetSnapshot ),
		];
		const summary = {
			sourceCount: sourceSnapshot.length,
			targetCount: targetSnapshot.length,
			exported,
			imported,
			browserChecked: browserCheck.checked.length,
			issueCount: issues.length,
			issues,
			outDir,
		};
		fs.writeFileSync(
			path.join( outDir, 'summary.json' ),
			JSON.stringify( summary, null, 2 )
		);
		console.log( JSON.stringify( summary, null, 2 ) );
		if ( issues.length ) {
			throw new Error( `Media import/export issues: ${ issues.length }` );
		}
	} finally {
		await context.close().catch( () => null );
		await browser.close().catch( () => null );
	}
}

main().catch( ( error ) => {
	console.error( error.stack || error.message || error );
	process.exit( 1 );
} );
