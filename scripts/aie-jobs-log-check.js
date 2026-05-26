/**
 * Manual E2E (Playwright): Jobs Log checks (target = aie2.local)
 *
 * What it covers:
 * - Jobs Log actions work across job types:
 *   - Resume (export job in failed state)
 *   - Retry (export + import + update jobs)
 *   - Restart (media_sync job)
 * - Each rerun redirects to the right wizard page and completes correctly
 * - After each case: re-import db2.sql + remove temp files created by the test
 *
 * Usage:
 *   /usr/local/bin/node scripts/aie-jobs-log-check.js
 *
 * Env (defaults read from .env.e2e if present):
 *   AIE_TARGET_URL, AIE_TARGET_ADMIN_USER, AIE_TARGET_ADMIN_PASSWORD
 *   AIE_TARGET_WP_PATH=/path/to/target/wp/root
 *   AIE_HEADLESS=true|false
 *   AIE_LOCAL_PHP=/path/to/php (Local.app bundled PHP works well)
 *   AIE_WP_BIN=/path/to/wp (wp-cli wrapper)
 *   AIE_LOCAL_MYSQL=/path/to/mysql
 */

const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

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

	const localMysqlCandidates = [
		'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/mysql-8.0.35+4/bin/darwin-arm64/bin/mysql',
	];
	const localMysqlDefault =
		localMysqlCandidates.find( ( p ) => fs.existsSync( p ) ) || 'mysql';

	return {
		headless,
		target: {
			baseUrl: get( 'AIE_TARGET_URL', 'http://aie2.local' ),
			username: get( 'AIE_TARGET_ADMIN_USER', 'admin' ),
			password: get( 'AIE_TARGET_ADMIN_PASSWORD', 'admin' ),
			wpPath: String( get( 'AIE_TARGET_WP_PATH', targetWpPathGuess ) ),
		},
		localPhp: String( get( 'AIE_LOCAL_PHP', localPhpDefault ) ),
		wpBin: String( get( 'AIE_WP_BIN', '/opt/homebrew/bin/wp' ) ),
		mysqlBin: String( get( 'AIE_LOCAL_MYSQL', localMysqlDefault ) ),
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

function wpEval( env, wpPath, code, { trim = true } = {} ) {
	return wp( env, wpPath, [ 'eval', code ], { trim } );
}

function wpEvalJson( env, wpPath, code ) {
	const raw = wpEval( env, wpPath, code, { trim: true } );
	try {
		return JSON.parse( raw || 'null' );
	} catch {
		return null;
	}
}

function sleepMs( ms ) {
	try {
		Atomics.wait( new Int32Array( new SharedArrayBuffer( 4 ) ), 0, 0, ms );
	} catch {
		// ignore
	}
}

function importDb2Sql( env ) {
	const wpPath = env.target.wpPath;
	const db2Sql = path.join( wpPath, 'db2.sql' );
	if ( ! fs.existsSync( db2Sql ) )
		throw new Error( `db2.sql not found at: ${ db2Sql }` );

	const dbName = wp( env, wpPath, [ 'config', 'get', 'DB_NAME' ] );
	const dbUser = wp( env, wpPath, [ 'config', 'get', 'DB_USER' ] );
	const dbPass = wp( env, wpPath, [ 'config', 'get', 'DB_PASSWORD' ] );
	const dbHost = wp( env, wpPath, [ 'config', 'get', 'DB_HOST' ] );
	const sock = String( dbHost || '' ).startsWith( ':' )
		? String( dbHost ).slice( 1 )
		: '';
	if ( ! sock )
		throw new Error(
			`Unsupported DB_HOST (expected :/path.sock): ${ dbHost }`
		);

	const sql = fs.readFileSync( db2Sql );
	execFileSync(
		env.mysqlBin,
		[
			'--protocol=socket',
			`--socket=${ sock }`,
			`-u${ dbUser }`,
			`-p${ dbPass }`,
			dbName,
		],
		{ input: sql }
	);

	// Ensure DB upgrades applied.
	wp( env, wpPath, [ 'core', 'update-db', '--quiet' ] );
}

function rmrf( p ) {
	try {
		fs.rmSync( p, { recursive: true, force: true } );
	} catch {}
}

function getUploadsBaseDir( env ) {
	const php = `echo wp_json_encode(wp_upload_dir()['basedir']);`;
	const basedir = wpEvalJson( env, env.target.wpPath, php );
	if ( ! basedir ) throw new Error( 'Failed to get uploads basedir' );
	return String( basedir );
}

function cleanupTempFiles( env ) {
	const uploadsBase = getUploadsBaseDir( env );
	// Export output folder (plugin creates secure hash subdirs here).
	rmrf( path.join( uploadsBase, 'import-export-by-rockstarlab-files' ) );
	// Our temp import CSV.
	rmrf( path.join( uploadsBase, 'rsl-ie-uploads', 'jobs-log-import.csv' ) );
	// Our media sync test folder.
	rmrf( path.join( uploadsBase, 'test-jobs-log-media' ) );
}

function getJobRow( env, jobId ) {
	const php = `
global $wpdb;
$jobId = (int) ${ Number( jobId ) };
$table = $wpdb->prefix . 'rsl_ie_jobs';
$row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $jobId), ARRAY_A );
echo wp_json_encode($row ?: null, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	return wpEvalJson( env, env.target.wpPath, php );
}

function waitForJobTerminalStatus(
	env,
	jobId,
	{ timeoutMs = 10 * 60_000 } = {}
) {
	const start = Date.now();
	while ( Date.now() - start < timeoutMs ) {
		const row = getJobRow( env, jobId );
		if ( row ) {
			const status = String( row.status || '' );
			if ( [ 'completed', 'failed', 'cancelled' ].includes( status ) )
				return row;
		}
		sleepMs( 500 );
	}
	return null;
}

function getWpTablePrefix( env ) {
	const php = `global $wpdb; echo wp_json_encode($wpdb->prefix);`;
	const prefix = wpEvalJson( env, env.target.wpPath, php );
	return String( prefix || 'wp_' );
}

function ensureOneWooOrderId( env ) {
	const php = `
if ( ! class_exists('WooCommerce') ) { echo wp_json_encode(null); return; }
$orders = wc_get_orders([ 'limit' => 1, 'return' => 'ids' ]);
if ( ! empty($orders) ) { echo wp_json_encode((int)$orders[0]); return; }

// Ensure a simple product exists.
$products = wc_get_products([ 'limit' => 1, 'return' => 'ids' ]);
$product_id = ! empty($products) ? (int)$products[0] : 0;
if ( ! $product_id ) {
  $p = new WC_Product_Simple();
  $p->set_name('Jobs Log Test Product');
  $p->set_regular_price('10');
  $product_id = $p->save();
}

$order = wc_create_order();
$order->add_product( wc_get_product($product_id), 1 );
$order->calculate_totals();
$order->update_status('processing');
echo wp_json_encode((int)$order->get_id());
`;
	return wpEvalJson( env, env.target.wpPath, php );
}

function createExportJob(
	env,
	{ exportType, format = 'csv', tableName = '' }
) {
	const params = {
		export_type: exportType,
		format,
		options: {},
		filters: {},
		fields: [],
		format_options: format === 'csv' ? { csv_include_header: true } : {},
		dynamic_filters: [],
		custom_fields: [],
		taxonomy: [],
		field_functions: {},
		table_name: tableName || '',
	};

	const php = `
$params = json_decode(${ JSON.stringify( JSON.stringify( params ) ) }, true);
$user = get_user_by('login', ${ JSON.stringify( env.target.username ) });
$uid = $user ? (int) $user->ID : 1;
$job_model = rsl_ie()->Model->job;
$job_id = $job_model->create([
  'type' => 'export',
  'status' => 'pending',
  'user_id' => $uid,
  'data_type' => ${ JSON.stringify( exportType ) },
  'file_format' => ${ JSON.stringify( format ) },
  'parameters' => wp_json_encode($params),
]);
echo wp_json_encode((int)$job_id);
`;
	return wpEvalJson( env, env.target.wpPath, php );
}

function createFailedExportJob( env, { exportType, format = 'csv' } ) {
	const params = {
		export_type: exportType,
		format,
		options: {},
		filters: {},
		fields: [],
		format_options: format === 'csv' ? { csv_include_header: true } : {},
		dynamic_filters: [],
		custom_fields: [],
		taxonomy: [],
		field_functions: {},
	};

	const php = `
$params = json_decode(${ JSON.stringify( JSON.stringify( params ) ) }, true);
$user = get_user_by('login', ${ JSON.stringify( env.target.username ) });
$uid = $user ? (int) $user->ID : 1;
$job_model = rsl_ie()->Model->job;
$job_id = $job_model->create([
  'type' => 'export',
  'status' => 'failed',
  'user_id' => $uid,
  'data_type' => ${ JSON.stringify( exportType ) },
  'file_format' => ${ JSON.stringify( format ) },
  'parameters' => wp_json_encode($params),
]);
echo wp_json_encode((int)$job_id);
`;
	return wpEvalJson( env, env.target.wpPath, php );
}

function writeImportCsv( env ) {
	const uploadsBase = getUploadsBaseDir( env );
	const dir = path.join( uploadsBase, 'rsl-ie-uploads' );
	fs.mkdirSync( dir, { recursive: true } );
	const csvPath = path.join( dir, 'jobs-log-import.csv' );
	const csv = [
		'post_title,post_content,post_status,post_type',
		'"Jobs Log Import Test","Hello from Jobs Log","publish","post"',
	].join( '\n' );
	fs.writeFileSync( csvPath, csv );
	return csvPath;
}

function createImportJob( env, { importType = 'post' } ) {
	const filePath = writeImportCsv( env );
	const mapping = {
		post_title: 'post_title',
		post_content: 'post_content',
		post_status: 'post_status',
		post_type: 'post_type',
	};
	const params = {
		import_type: importType,
		format: 'csv',
		delimiter: ',',
		mapping,
		options: { batch_size: 20 },
		offset: 0,
	};

	const php = `
$params = json_decode(${ JSON.stringify( JSON.stringify( params ) ) }, true);
$user = get_user_by('login', ${ JSON.stringify( env.target.username ) });
$uid = $user ? (int) $user->ID : 1;
$job_model = rsl_ie()->Model->job;
$job_id = $job_model->create([
  'type' => 'import',
  'status' => 'pending',
  'user_id' => $uid,
  'file_path' => ${ JSON.stringify( filePath ) },
  'parameters' => wp_json_encode($params),
]);
echo wp_json_encode((int)$job_id);
`;
	return { jobId: wpEvalJson( env, env.target.wpPath, php ), filePath };
}

function createUpdateJob(
	env,
	{
		contentType = 'post',
		fields = [ 'post_title' ],
		// Update_Processor expects field_functions keyed by field index (0..n-1).
		fieldFunctions = { 0: [ 'snippet_uppercase' ] },
		options = { items_per_iteration: 10 },
	} = {}
) {
	const params = {
		content_type: contentType,
		exporter_type: contentType,
		fields,
		field_functions: fieldFunctions,
		options,
	};

	const php = `
$params = json_decode(${ JSON.stringify( JSON.stringify( params ) ) }, true);
$user = get_user_by('login', ${ JSON.stringify( env.target.username ) });
$uid = $user ? (int) $user->ID : 1;
$job_model = rsl_ie()->Model->job;
$job_id = $job_model->create([
  'user_id' => $uid,
  'type' => 'update',
  'data_type' => ${ JSON.stringify( contentType ) },
  'file_format' => 'none',
  'status' => 'pending',
  'parameters' => wp_json_encode($params),
]);
echo wp_json_encode((int)$job_id);
`;
	return wpEvalJson( env, env.target.wpPath, php );
}

function createMediaSyncTestFiles( env ) {
	const uploadsBase = getUploadsBaseDir( env );
	const baseDir = path.join( uploadsBase, 'test-jobs-log-media' );
	const subDir = path.join( baseDir, 'sub' );
	fs.mkdirSync( subDir, { recursive: true } );

	// Tiny 1x1 PNG bytes.
	const png = Buffer.from(
		'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMB/auYfWQAAAAASUVORK5CYII=',
		'base64'
	);

	const files = [
		path.join( baseDir, 'one.png' ),
		path.join( baseDir, 'two.png' ),
		path.join( subDir, 'three.png' ),
	];
	for ( const f of files ) fs.writeFileSync( f, png );

	const all = files.map( ( f ) => ( {
		path: f,
		name: path.basename( f ),
		size: fs.statSync( f ).size,
	} ) );
	return { baseDir, allFiles: all };
}

function createMediaSyncJob( env ) {
	const { baseDir, allFiles } = createMediaSyncTestFiles( env );
	const syncOptions = {
		duplicate_check: 'hash',
		duplicate_handling: 'skip',
		file_operation: 'copy',
		copy_files: true,
		generate_thumbnails: true,
		rml_integration: false,
		batch_size: 2,
	};
	const scanOptions = { recursive: true, file_types: 'images' };

	const settings = {
		folder_path: baseDir,
		all_files: allFiles,
		total_files: allFiles.length,
		scan_options: scanOptions,
		sync_options: syncOptions,
		offset: 0,
		processed_count: 0,
	};

	const php = `
$settings = json_decode(${ JSON.stringify(
		JSON.stringify( settings )
	) }, true);
$user = get_user_by('login', ${ JSON.stringify( env.target.username ) });
$uid = $user ? (int) $user->ID : 1;
$job_model = rsl_ie()->Model->job;
$job_id = $job_model->create([
  'type' => 'media_sync',
  'status' => 'pending',
  'user_id' => $uid,
  'settings' => wp_json_encode($settings),
  'total_items' => (int) ($settings['total_files'] ?? 0),
]);
echo wp_json_encode((int)$job_id);
`;
	return wpEvalJson( env, env.target.wpPath, php );
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

function getResumeJobIdFromUrl( urlStr ) {
	try {
		const u = new URL( urlStr );
		const id = u.searchParams.get( 'resume_job' );
		return id ? Number( id ) : null;
	} catch {
		return null;
	}
}

async function clickJobActionAndWaitNav( page, jobId, actionSelector ) {
	const row = page.locator( `tr[data-job-id="${ jobId }"]` ).first();
	await row.waitFor( { state: 'attached', timeout: 60_000 } );

	const btn = row.locator( actionSelector ).first();
	await btn.waitFor( { state: 'visible', timeout: 60_000 } );

	page.once( 'dialog', ( d ) => d.accept().catch( () => null ) );
	await Promise.all( [
		page.waitForNavigation( {
			waitUntil: 'domcontentloaded',
			timeout: 60_000,
		} ),
		btn.click(),
	] );

	const resumeJobId = getResumeJobIdFromUrl( page.url() );
	if ( ! resumeJobId ) {
		throw new Error(
			`Expected navigation to include resume_job, got url=${ page.url() }`
		);
	}
	return resumeJobId;
}

async function handleBackupWarningModalIfPresent( page ) {
	const overlay = page.locator( '.rsl-ie-backup-warning-overlay' ).first();
	if ( ! ( await overlay.count().catch( () => 0 ) ) ) return;
	const visible = await overlay.isVisible().catch( () => false );
	if ( ! visible ) return;

	// Required checkbox.
	const created = page.locator( '#rsl-ie-backup-created' ).first();
	if ( await created.count().catch( () => 0 ) )
		await created.check( { force: true } ).catch( () => null );

	// "Don't show again"
	const dontShow = page.locator( '#rsl-ie-backup-dont-show' ).first();
	if ( await dontShow.count().catch( () => 0 ) )
		await dontShow.check( { force: true } ).catch( () => null );

	// Confirm
	const confirm = page.locator( '.rsl-ie-backup-confirm' ).first();
	if ( await confirm.count().catch( () => 0 ) ) {
		await confirm.click( { force: true } ).catch( () => null );
		await overlay
			.waitFor( { state: 'detached', timeout: 20_000 } )
			.catch( () => null );
		return;
	}
}

async function runUpdateJobOnce( page, env ) {
	await gotoAdminPage(
		page,
		env.target,
		'/wp-admin/admin.php?page=rsl-ie-content-updater'
	);

	// Step 1: select "post"
	const postRadio = page
		.locator(
			'.rsl-ie-updater-step-1.active input[name="updater_content_type"][value="post"]'
		)
		.first();
	await postRadio.waitFor( { state: 'attached', timeout: 60_000 } );
	await postRadio.check( { force: true } );
	await page
		.locator( '.rsl-ie-step.active .rsl-ie-updater-next-step' )
		.first()
		.click();
	await handleBackupWarningModalIfPresent( page ).catch( () => null );

	// Step 2: no filters, next
	await page
		.waitForSelector( '.rsl-ie-updater-step-2.active', { timeout: 60_000 } )
		.catch( () => null );
	await page
		.locator( '.rsl-ie-step.active .rsl-ie-updater-next-step' )
		.first()
		.click();

	// Step 3: select post_title
	await page
		.waitForSelector( '.rsl-ie-updater-step-3.active', { timeout: 60_000 } )
		.catch( () => null );
	const fieldItem = page
		.locator(
			'.rsl-ie-updater-step-3.active #rsl-ie-updater-fields-library .rsl-ie-field-item[data-field="post_title"]'
		)
		.first();
	await fieldItem.waitFor( { state: 'attached', timeout: 60_000 } );
	// Ensure the category is expanded if the item is hidden.
	const visible = await fieldItem.isVisible().catch( () => false );
	if ( ! visible ) {
		const catTitle = fieldItem
			.locator(
				'xpath=ancestor::div[contains(@class,"rsl-ie-field-category")][1]//h4[contains(@class,"rsl-ie-field-category-title")]'
			)
			.first();
		if ( await catTitle.count().catch( () => 0 ) ) {
			await catTitle.click( { force: true } ).catch( () => null );
			await page.waitForTimeout( 100 );
		}
	}

	// Click-to-add sometimes misses if the list is still rendering; try a few times.
	let added = false;
	for ( let i = 0; i < 3; i++ ) {
		await fieldItem.click( { force: true } ).catch( () => null );
		const selected = page.locator(
			'#rsl-ie-updater-fields-list .rsl-ie-selected-field[data-field="post_title"]'
		);
		const ok = await selected
			.first()
			.waitFor( { state: 'visible', timeout: 5_000 } )
			.then( () => true )
			.catch( () => false );
		if ( ok ) {
			added = true;
			break;
		}
		await page.waitForTimeout( 200 );
	}
	if ( ! added ) {
		throw new Error( 'Failed to add post_title field on updater step 3' );
	}

	// Step 3 "Next" can remain disabled until at least one field is selected.
	await page
		.waitForFunction(
			() => {
				const btn = document.querySelector(
					'.rsl-ie-updater-step-3.active .rsl-ie-updater-next-step'
				);
				return btn && ! btn.disabled;
			},
			null,
			{ timeout: 60_000 }
		)
		.catch( () => null );
	await page
		.locator( '.rsl-ie-step.active .rsl-ie-updater-next-step' )
		.first()
		.click();

	// Step 4: assign uppercase function
	await page
		.waitForSelector( '.rsl-ie-updater-step-4.active', { timeout: 60_000 } )
		.catch( () => null );
	const assignBtn = page
		.locator(
			'.rsl-ie-updater-step-4.active .rsl-ie-assign-functions[data-field="post_title"]'
		)
		.first();
	await assignBtn.waitFor( { state: 'visible', timeout: 60_000 } );
	await assignBtn.click();

	const modal = page.locator( '#rsl-ie-updater-functions-modal' ).first();
	await modal.waitFor( { state: 'visible', timeout: 60_000 } );

	// Wait for list populated, then add uppercase snippet.
	await page
		.waitForFunction( () => {
			return (
				document.querySelectorAll(
					'#rsl-ie-updater-functions-list .rsl-ie-function-list-item'
				).length > 0
			);
		} )
		.catch( () => null );

	const addUpper = page.locator(
		'#rsl-ie-updater-functions-list .rsl-ie-add-function-btn[data-function-id="snippet_uppercase"]'
	);
	if ( await addUpper.count().catch( () => 0 ) ) {
		await addUpper.first().click();
	} else {
		const search = page
			.locator( '#rsl-ie-updater-functions-search' )
			.first();
		if ( await search.count().catch( () => 0 ) ) {
			await search.fill( 'upper' ).catch( () => null );
		}
		await page
			.locator(
				'#rsl-ie-updater-functions-list .rsl-ie-add-function-btn'
			)
			.first()
			.click();
	}

	await page.locator( '.rsl-ie-save-updater-functions' ).first().click();
	await modal.waitFor( { state: 'hidden', timeout: 60_000 } );
	await page
		.locator( '.rsl-ie-step.active .rsl-ie-updater-next-step' )
		.first()
		.click();

	// Step 5: start update
	await page
		.waitForSelector( '.rsl-ie-updater-step-5.active', { timeout: 60_000 } )
		.catch( () => null );
	await page.locator( '.rsl-ie-start-update-btn' ).first().click();
	await handleBackupWarningModalIfPresent( page ).catch( () => null );

	// Wait until DB shows a terminal state.
	const latestJob = wpEvalJson(
		env,
		env.target.wpPath,
		`global $wpdb; $t=$wpdb->prefix.'rsl_ie_jobs'; $r=$wpdb->get_row(\"SELECT id FROM {$t} WHERE type='update' ORDER BY id DESC LIMIT 1\"); echo wp_json_encode($r? (int)$r->id : null);`
	);
	const jobId = Number( latestJob ) || 0;
	if ( ! jobId ) throw new Error( 'Failed to detect update job id' );

	const done = waitForJobTerminalStatus( env, jobId, {
		timeoutMs: 10 * 60_000,
	} );
	if ( ! done )
		throw new Error( `Update job did not finish (jobId=${ jobId })` );
	if ( String( done.status ) !== 'completed' )
		throw new Error(
			`Update job status=${ done.status } (jobId=${ jobId })`
		);
	return jobId;
}

async function run() {
	const env = loadEnv();

	// Quick sanity check: prefer a Node 18+ binary.
	const major = Number(
		String( process.versions.node || '' ).split( '.' )[ 0 ] || 0
	);
	if ( major < 18 ) {
		throw new Error(
			`Node.js ${ process.versions.node } detected. Use /usr/local/bin/node (>=18) to run this script.`
		);
	}

	console.log( `Target: ${ env.target.baseUrl }` );
	console.log( `WP path: ${ env.target.wpPath }` );

	// Global cleanup + reset before run.
	cleanupTempFiles( env );
	importDb2Sql( env );

	// Prefer local Playwright browsers bundled under `e2e/.playwright-browsers` to avoid
	// forcing a global download into the user's home directory.
	if ( ! process.env.PLAYWRIGHT_BROWSERS_PATH ) {
		const bundled = path.resolve(
			process.cwd(),
			'e2e/.playwright-browsers'
		);
		if ( fs.existsSync( bundled ) ) {
			process.env.PLAYWRIGHT_BROWSERS_PATH = bundled;
		}
	}

	// Load Playwright after setting PLAYWRIGHT_BROWSERS_PATH.
	const { chromium } = require( 'playwright' );

	const downloadsDir = fs.mkdtempSync(
		path.join( os.tmpdir(), 'rsl-ie-jobs-log-downloads-' )
	);

	const browser = await chromium.launch( { headless: env.headless } );
	const context = await browser.newContext( {
		acceptDownloads: true,
		downloadsPath: downloadsDir,
	} );
	const page = await context.newPage();

	try {
		// Ensure Woo has at least one order for woo_order export case.
		ensureOneWooOrderId( env );

		const prefix = getWpTablePrefix( env );
		const postsTable = `${ prefix }posts`;

		const cases = [
			{
				name: 'Jobs Log: export resume (failed post)',
				run: async () => {
					const jobId = createFailedExportJob( env, {
						exportType: 'post',
					} );
					if ( ! jobId )
						throw new Error( 'Failed to create export job' );

					await gotoAdminPage(
						page,
						env.target,
						'/wp-admin/admin.php?page=rsl-ie-jobs-log'
					);

					// Resume should exist on failed job.
					await page
						.locator(
							`tr[data-job-id="${ jobId }"] .job-action-resume`
						)
						.first()
						.waitFor( { state: 'visible', timeout: 60_000 } );

					const resumedId = await clickJobActionAndWaitNav(
						page,
						jobId,
						'.job-action-resume'
					);
					if ( resumedId !== jobId )
						throw new Error(
							`Resume should keep same job id (expected ${ jobId }, got ${ resumedId })`
						);

					const done = waitForJobTerminalStatus( env, resumedId, {
						timeoutMs: 10 * 60_000,
					} );
					if ( ! done )
						throw new Error(
							`Export resume did not finish (jobId=${ jobId })`
						);
					if ( String( done.status ) !== 'completed' )
						throw new Error(
							`Export resume status=${ done.status } (jobId=${ jobId })`
						);
				},
			},
			{
				name: 'Jobs Log: export retry (post)',
				run: async () => {
					const jobId = createExportJob( env, {
						exportType: 'post',
					} );
					if ( ! jobId )
						throw new Error( 'Failed to create export job' );

					await gotoAdminPage(
						page,
						env.target,
						'/wp-admin/admin.php?page=rsl-ie-jobs-log'
					);
					// Restart button should exist for all jobs.
					await page
						.locator(
							`tr[data-job-id="${ jobId }"] .job-action-restart`
						)
						.first()
						.waitFor( { state: 'visible', timeout: 60_000 } );

					const newJobId = await clickJobActionAndWaitNav(
						page,
						jobId,
						'.job-action-retry'
					);
					const done = waitForJobTerminalStatus( env, newJobId, {
						timeoutMs: 10 * 60_000,
					} );
					if ( ! done )
						throw new Error(
							`Export retry did not finish (jobId=${ newJobId })`
						);
					if ( String( done.status ) !== 'completed' )
						throw new Error(
							`Export retry status=${ done.status } (jobId=${ newJobId })`
						);
				},
			},
			{
				name: 'Jobs Log: export retry (database_table)',
				run: async () => {
					const jobId = createExportJob( env, {
						exportType: 'database_table',
						tableName: postsTable,
					} );
					if ( ! jobId )
						throw new Error( 'Failed to create export job' );

					await gotoAdminPage(
						page,
						env.target,
						'/wp-admin/admin.php?page=rsl-ie-jobs-log'
					);
					const newJobId = await clickJobActionAndWaitNav(
						page,
						jobId,
						'.job-action-retry'
					);
					const done = waitForJobTerminalStatus( env, newJobId, {
						timeoutMs: 10 * 60_000,
					} );
					if ( ! done )
						throw new Error(
							`DB table export retry did not finish (jobId=${ newJobId })`
						);
					if ( String( done.status ) !== 'completed' )
						throw new Error(
							`DB table export retry status=${ done.status } (jobId=${ newJobId })`
						);
				},
			},
			{
				name: 'Jobs Log: export retry (woo_order)',
				run: async () => {
					const jobId = createExportJob( env, {
						exportType: 'woo_order',
					} );
					if ( ! jobId )
						throw new Error( 'Failed to create export job' );

					await gotoAdminPage(
						page,
						env.target,
						'/wp-admin/admin.php?page=rsl-ie-jobs-log'
					);
					const newJobId = await clickJobActionAndWaitNav(
						page,
						jobId,
						'.job-action-retry'
					);
					const done = waitForJobTerminalStatus( env, newJobId, {
						timeoutMs: 10 * 60_000,
					} );
					if ( ! done )
						throw new Error(
							`Woo order export retry did not finish (jobId=${ newJobId })`
						);
					if ( String( done.status ) !== 'completed' )
						throw new Error(
							`Woo order export retry status=${ done.status } (jobId=${ newJobId })`
						);
				},
			},
			{
				name: 'Jobs Log: import retry (post)',
				run: async () => {
					const { jobId } = createImportJob( env, {
						importType: 'post',
					} );
					if ( ! jobId )
						throw new Error( 'Failed to create import job' );

					await gotoAdminPage(
						page,
						env.target,
						'/wp-admin/admin.php?page=rsl-ie-jobs-log'
					);
					const newJobId = await clickJobActionAndWaitNav(
						page,
						jobId,
						'.job-action-retry'
					);
					const done = waitForJobTerminalStatus( env, newJobId, {
						timeoutMs: 10 * 60_000,
					} );
					if ( ! done )
						throw new Error(
							`Import retry did not finish (jobId=${ newJobId })`
						);
					if ( String( done.status ) !== 'completed' )
						throw new Error(
							`Import retry status=${ done.status } (jobId=${ newJobId })`
						);
				},
			},
			{
				name: 'Jobs Log: update retry (post_title uppercase)',
				run: async () => {
					const jobId = createUpdateJob( env, {
						contentType: 'post',
						fields: [ 'post_title' ],
						fieldFunctions: { 0: [ 'snippet_uppercase' ] },
						options: { items_per_iteration: 10 },
					} );
					if ( ! jobId )
						throw new Error( 'Failed to create update job' );
					await gotoAdminPage(
						page,
						env.target,
						'/wp-admin/admin.php?page=rsl-ie-jobs-log'
					);
					const newJobId = await clickJobActionAndWaitNav(
						page,
						jobId,
						'.job-action-retry'
					);
					const done = waitForJobTerminalStatus( env, newJobId, {
						timeoutMs: 10 * 60_000,
					} );
					if ( ! done )
						throw new Error(
							`Update retry did not finish (jobId=${ newJobId })`
						);
					if ( String( done.status ) !== 'completed' )
						throw new Error(
							`Update retry status=${ done.status } (jobId=${ newJobId })`
						);
				},
			},
			{
				name: 'Jobs Log: media sync restart',
				run: async () => {
					const jobId = createMediaSyncJob( env );
					if ( ! jobId )
						throw new Error( 'Failed to create media_sync job' );

					await gotoAdminPage(
						page,
						env.target,
						'/wp-admin/admin.php?page=rsl-ie-jobs-log'
					);

					// Retry is intentionally hidden for media_sync; restart should be present.
					await page
						.locator(
							`tr[data-job-id="${ jobId }"] .job-action-restart`
						)
						.first()
						.waitFor( { state: 'visible', timeout: 60_000 } );

					const newJobId = await clickJobActionAndWaitNav(
						page,
						jobId,
						'.job-action-restart'
					);
					const done = waitForJobTerminalStatus( env, newJobId, {
						timeoutMs: 10 * 60_000,
					} );
					if ( ! done )
						throw new Error(
							`Media sync restart did not finish (jobId=${ newJobId })`
						);
					if ( String( done.status ) !== 'completed' )
						throw new Error(
							`Media sync restart status=${ done.status } (jobId=${ newJobId })`
						);
				},
			},
		];

		for ( const c of cases ) {
			console.log( `\n=== ${ c.name } ===` );
			// Clean temp files + reset DB before each case for determinism.
			cleanupTempFiles( env );
			importDb2Sql( env );
			await c.run();
			// Cleanup after each case to avoid disk clutter.
			cleanupTempFiles( env );
			importDb2Sql( env );
			console.log( `OK: ${ c.name }` );
		}
	} finally {
		await context.close().catch( () => null );
		await browser.close().catch( () => null );
		rmrf( downloadsDir );
		// Final cleanup & reset.
		cleanupTempFiles( env );
		importDb2Sql( env );
	}

	console.log( '\nAll Jobs Log cases passed.' );
}

run().catch( ( err ) => {
	console.error( err && err.stack ? err.stack : String( err ) );
	process.exit( 1 );
} );
