/**
 * Manual E2E (Playwright): Media Sync checks (aie2.local only)
 *
 * What it covers (per user request):
 * - Download 20 random internet images into wp-content/uploads/test (+ random subfolders)
 * - Use Media Sync UI (/wp-admin/admin.php?page=rsl-ie-media-sync):
 *   - Click Browse and choose the folder
 *   - Scan Folder
 *   - Toggle "Include files from subdirectories"
 *   - Select all vs select multiple files
 *   - Exercise all Step 2 Sync Options across test cases:
 *     - duplicate_check: hash / filename / filesize
 *     - duplicate_handling: skip / overwrite / rename
 *     - file_operation: keep / copy / move
 *     - batch_size: varied
 *     - rml_integration: on/off (requires Real Media Library)
 * - Verify expected results via WP-CLI (attachments count, original files kept/moved, RML folders created)
 * - After each test case: re-import db2.sql to reset DB
 * - End: remove temporary files (uploads/test + uploads/YYYY/MM/test + Playwright artifacts)
 *
 * Usage:
 *   PLAYWRIGHT_BROWSERS_PATH=./e2e/.playwright-browsers node scripts/aie-media-sync-check.js
 *
 * Env (defaults read from .env.e2e if present):
 *   AIE_TARGET_URL, AIE_TARGET_ADMIN_USER, AIE_TARGET_ADMIN_PASSWORD
 *   AIE_HEADLESS=true|false
 *   AIE_TARGET_WP_PATH=/path/to/target/wp/root
 *   AIE_LOCAL_PHP=/path/to/php (Local.app bundled PHP works well)
 *   AIE_WP_BIN=/path/to/wp (wp-cli wrapper)
 *   AIE_LOCAL_MYSQL=/path/to/mysql (Local.app bundled mysql works well)
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

function wpEvalJson( env, wpPath, code ) {
	const raw = wp( env, wpPath, [ 'eval', code ], { trim: true } );
	try {
		return JSON.parse( raw || 'null' );
	} catch {
		return null;
	}
}

function mkdirp( dir ) {
	fs.mkdirSync( dir, { recursive: true } );
}

function rmrf( p ) {
	try {
		fs.rmSync( p, { recursive: true, force: true } );
	} catch {}
}

function nowStamp() {
	return new Date().toISOString().replace( /[:.]/g, '-' );
}

function downloadJpg( url, outPath ) {
	mkdirp( path.dirname( outPath ) );
	execFileSync(
		'curl',
		[
			'--fail',
			'--location',
			'--silent',
			'--show-error',
			'--retry',
			'3',
			'--retry-delay',
			'1',
			'-o',
			outPath,
			url,
		],
		{ stdio: [ 'ignore', 'pipe', 'pipe' ] }
	);
}

function getUploadsDirs( env ) {
	const php = `
$u = wp_upload_dir();
echo wp_json_encode(['basedir' => $u['basedir'], 'path' => $u['path']], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	const out = wpEvalJson( env, env.target.wpPath, php );
	if ( ! out || ! out.basedir || ! out.path )
		throw new Error( 'Failed to resolve wp_upload_dir() paths' );
	return out;
}

function prepareTwentyInternetImages( uploadsBaseDir, stamp ) {
	const base = path.join( uploadsBaseDir, 'test' );
	rmrf( base );

	const folders = [
		'',
		'',
		'',
		'',
		'',
		'',
		'',
		'',
		'',
		'',
		'',
		'',
		'sub-a',
		'sub-a',
		'sub-a',
		'sub-b/nested',
		'sub-b/nested',
		'rand-1',
		'rand-2/nest',
		'rand-2/nest',
	];

	const files = [];
	let overwritePath = '';
	for ( let i = 1; i <= 20; i++ ) {
		const folder = folders[ i - 1 ] || '';
		const filename =
			i === 1
				? 'overwrite-target.jpg'
				: `img${ String( i ).padStart( 2, '0' ) }.jpg`;
		const outPath = path.join( base, folder, filename );
		const seed = `${ stamp }-${ i }-${ Math.floor( Math.random() * 1e9 ) }`;
		const url = `https://picsum.photos/seed/${ encodeURIComponent(
			seed
		) }/800/600.jpg`;
		downloadJpg( url, outPath );
		files.push( outPath );
		if ( i === 1 ) overwritePath = outPath;
	}

	return {
		baseDir: base,
		files,
		overwritePath:
			overwritePath || path.join( base, 'overwrite-target.jpg' ),
	};
}

function countFilesByRecursion( baseDir, files, recursive ) {
	const base = path.resolve( baseDir );
	if ( recursive )
		return files.filter( ( f ) => f.startsWith( base + path.sep ) ).length;
	// Only files directly inside baseDir
	return files.filter( ( f ) => path.dirname( f ) === base ).length;
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

function getLatestMediaSyncJob( env, { minUpdatedAt = '' } = {} ) {
	const php = `
global $wpdb;
$table = $wpdb->prefix . 'rsl_ie_jobs';
$row = $wpdb->get_row("SELECT * FROM {$table} WHERE type = 'media_sync' ORDER BY id DESC LIMIT 1", ARRAY_A);
echo wp_json_encode($row ?: null, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	const row = wpEvalJson( env, env.target.wpPath, php );
	if ( ! row ) return null;
	if (
		minUpdatedAt &&
		String( row.updated_at || '' ) < String( minUpdatedAt )
	)
		return null;
	return row;
}

function waitForMediaSyncCompletion(
	env,
	{ minUpdatedAt = '', timeoutMs = 10 * 60_000 } = {}
) {
	const start = Date.now();
	while ( Date.now() - start < timeoutMs ) {
		const row = getLatestMediaSyncJob( env, { minUpdatedAt } );
		if ( row ) {
			const status = String( row.status || '' );
			const total = Number( row.total_items || 0 );
			const processed = Number( row.processed_items || 0 );
			const pct = Number( row.progress || 0 );
			if ( [ 'completed', 'failed', 'cancelled' ].includes( status ) )
				return row;
			// Fallback: treat 100% + processed==total as completion.
			if ( total > 0 && processed >= total && pct >= 100 ) return row;
		}
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

function countImportedAttachmentsByOriginalPathPrefix( env, originalPrefix ) {
	const prefix = String( originalPrefix || '' )
		.replace( /\/+$/g, '' )
		.concat( '/' );
	const php = `
$prefix = ${ JSON.stringify( prefix ) };
$ids = get_posts([
  'post_type' => 'attachment',
  'post_status' => 'inherit',
  'fields' => 'ids',
  'posts_per_page' => -1,
  'meta_query' => [
    [
      'key' => 'rsl_ie_original_path',
      'value' => $prefix,
      'compare' => 'LIKE',
    ]
  ],
]);
echo wp_json_encode(['count' => is_array($ids) ? count($ids) : 0], JSON_UNESCAPED_SLASHES);
`;
	const out = wpEvalJson( env, env.target.wpPath, php );
	return Number( out && out.count ? out.count : 0 );
}

function getAttachmentIdsByOriginalPathExact( env, originalPath ) {
	const php = `
$p = ${ JSON.stringify( String( originalPath || '' ) ) };
$ids = get_posts([
  'post_type' => 'attachment',
  'post_status' => 'inherit',
  'fields' => 'ids',
  'posts_per_page' => -1,
  'meta_query' => [
    [
      'key' => 'rsl_ie_original_path',
      'value' => $p,
      'compare' => '=',
    ]
  ],
]);
echo wp_json_encode(['ids' => array_map('intval', is_array($ids) ? $ids : [])], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	const out = wpEvalJson( env, env.target.wpPath, php );
	return Array.isArray( out && out.ids ) ? out.ids : [];
}

function getRmlFoldersByNames( env, names ) {
	const php = `
global $wpdb;
$names = ${ JSON.stringify( Array.isArray( names ) ? names : [] ) };
$out = [];
foreach ($names as $n) {
  $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}realmedialibrary WHERE name = %s LIMIT 1", $n));
  $out[$n] = $id ? (int)$id : 0;
}
echo wp_json_encode($out, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	return wpEvalJson( env, env.target.wpPath, php ) || {};
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

async function chooseFolderViaBrowseModal( page, absoluteFolderPath ) {
	await page.locator( '#rsl-ie-browse-folders-btn' ).click();
	const modal = page.locator( '#rsl-ie-folder-browser-modal' );
	await modal.waitFor( { state: 'visible', timeout: 60_000 } );

	// Wait for list render.
	await page
		.waitForFunction(
			() => {
				return (
					document.querySelectorAll(
						'#rsl-ie-folder-browser-list .rsl-ie-folder-item'
					).length > 0
				);
			},
			{ timeout: 60_000 }
		)
		.catch( () => null );

	const item = page.locator(
		`#rsl-ie-folder-browser-list .rsl-ie-folder-item[data-path="${ absoluteFolderPath.replace(
			/"/g,
			'\\"'
		) }"]`
	);
	if ( ! ( await item.count().catch( () => 0 ) ) ) {
		throw new Error(
			`Folder not found in browser: ${ absoluteFolderPath }`
		);
	}
	await item.first().click();

	const chooseBtn = page.locator( '#rsl-ie-choose-folder-btn' );
	await chooseBtn.waitFor( { state: 'visible', timeout: 30_000 } );
	await chooseBtn.click();
	await modal.waitFor( { state: 'hidden', timeout: 60_000 } );

	// Ensure the input is set.
	const actual = await page.locator( '#rsl-ie-folder-path' ).inputValue();
	if ( String( actual || '' ) !== String( absoluteFolderPath || '' ) ) {
		throw new Error(
			`Folder path input mismatch (expected ${ absoluteFolderPath }, got ${ actual })`
		);
	}
}

async function scanFolder( page, { recursive, fileTypes = 'images' } ) {
	await page.locator( '#rsl-ie-scan-recursive' ).setChecked( !! recursive );
	await page.locator( '#rsl-ie-file-types' ).selectOption( fileTypes );
	await page.locator( '#rsl-ie-scan-folder-btn' ).click();

	const results = page.locator( '#rsl-ie-scan-results' );
	await results.waitFor( { state: 'visible', timeout: 60_000 } );

	const totalText = await page.locator( '#rsl-ie-total-files' ).innerText();
	const total = Number( String( totalText ).replace( /[^0-9]/g, '' ) );
	return Number.isFinite( total ) ? total : 0;
}

async function setSelectionMode( page, mode, { count = 3 } = {} ) {
	if ( mode === 'all' ) {
		await page.locator( '#rsl-ie-select-all-files' ).setChecked( true );
		return;
	}
	// Multiple: select exactly N.
	await page.locator( '#rsl-ie-select-all-files' ).setChecked( false );
	const boxes = page.locator( '.rsl-ie-file-checkbox' );
	const total = await boxes.count();
	const n = Math.max( 1, Math.min( total, Number( count ) || 1 ) );
	for ( let i = 0; i < total; i++ ) {
		// eslint-disable-next-line no-await-in-loop
		await boxes.nth( i ).setChecked( i < n );
	}
	await page.waitForTimeout( 100 );
	const selectedText = await page
		.locator( '#rsl-ie-selected-count' )
		.innerText();
	const selected = Number( String( selectedText ).replace( /[^0-9]/g, '' ) );
	if ( selected !== n ) {
		throw new Error(
			`Selected count mismatch (expected ${ n }, got ${ selected })`
		);
	}
}

async function selectSpecificFilesByMatchers( page, matchers ) {
	const list = Array.isArray( matchers ) ? matchers : [];
	const boxes = page.locator( '.rsl-ie-file-checkbox' );
	const total = await boxes.count();
	// Clear all first
	for ( let i = 0; i < total; i++ ) {
		// eslint-disable-next-line no-await-in-loop
		await boxes.nth( i ).setChecked( false );
	}

	let selected = 0;
	for ( const m of list ) {
		// eslint-disable-next-line no-await-in-loop
		const loc = page.locator(
			`.rsl-ie-file-checkbox[value*="${ String( m ).replace(
				/"/g,
				'\\"'
			) }"]`
		);
		// eslint-disable-next-line no-await-in-loop
		if ( await loc.count().catch( () => 0 ) ) {
			// eslint-disable-next-line no-await-in-loop
			await loc.first().setChecked( true );
			selected++;
		}
	}

	const selectedText = await page
		.locator( '#rsl-ie-selected-count' )
		.innerText();
	const uiSelected = Number(
		String( selectedText ).replace( /[^0-9]/g, '' )
	);
	if ( uiSelected !== selected ) {
		throw new Error(
			`Selected count mismatch (expected ${ selected }, got ${ uiSelected })`
		);
	}
	return selected;
}

async function setSyncOptions( page, opts ) {
	await page
		.locator( '#rsl-ie-duplicate-check' )
		.selectOption( String( opts.duplicate_check ) );
	await page
		.locator( '#rsl-ie-duplicate-handling' )
		.selectOption( String( opts.duplicate_handling ) );
	await page
		.locator( '#rsl-ie-copy-files' )
		.selectOption( String( opts.file_operation ) );
	await page
		.locator( '#rsl-ie-batch-size' )
		.fill( String( opts.batch_size ) );
	await page
		.locator( '#rsl-ie-rml-integration' )
		.setChecked( !! opts.rml_integration );
}

async function getSelectedFilePaths( page ) {
	return (
		( await page
			.evaluate( () =>
				Array.from(
					document.querySelectorAll(
						'#rsl-ie-file-list .rsl-ie-file-checkbox:checked'
					)
				).map( ( el ) => el.value )
			)
			.catch( () => [] ) ) || []
	);
}

async function startSyncAndWait( page, env, { minUpdatedAt } ) {
	await page.locator( '#rsl-ie-start-sync-btn' ).click();
	// Prefer the real completion panel, but don't hang forever if the job reached 100% yet status didn't flip.
	const progress = page.locator( '#rsl-ie-sync-progress-section' );
	await progress.waitFor( { state: 'visible', timeout: 60_000 } );

	const jobRow = waitForMediaSyncCompletion( env, {
		minUpdatedAt,
		timeoutMs: 15 * 60_000,
	} );
	if ( ! jobRow )
		throw new Error( 'Media sync job did not complete in time' );
	const status = String( jobRow.status || '' );
	const total = Number( jobRow.total_items || 0 );
	const processed = Number( jobRow.processed_items || 0 );
	const pct = Number( jobRow.progress || 0 );
	const pseudoCompleted =
		! [ 'completed', 'failed', 'cancelled' ].includes( status ) &&
		total > 0 &&
		processed >= total &&
		pct >= 100;
	const uiDone = await page
		.locator( '#rsl-ie-sync-completion' )
		.isVisible()
		.catch( () => false );
	return { row: jobRow, uiDone, pseudoCompleted };
}

function getAttachmentMeta( env, attachId, metaKey ) {
	const php = `
$id = (int) ${ JSON.stringify( Number( attachId || 0 ) ) };
$k = ${ JSON.stringify( String( metaKey || '' ) ) };
echo wp_json_encode(['v' => get_post_meta($id, $k, true)], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	const out = wpEvalJson( env, env.target.wpPath, php );
	return out ? out.v : null;
}

async function run() {
	const env = loadEnv();
	const artifactsRoot = path.resolve(
		os.tmpdir(),
		'rsl-ie-aie-media-sync-check',
		nowStamp()
	);
	mkdirp( artifactsRoot );

	const summary = {
		startedAt: new Date().toISOString(),
		target: { baseUrl: env.target.baseUrl, wpPath: env.target.wpPath },
		cases: [],
		issues: [],
		finishedAt: null,
	};

	const userDataDir = path.join( artifactsRoot, 'playwright-profile' );
	const ctx = await chromium.launchPersistentContext( userDataDir, {
		headless: env.headless,
	} );
	const page = await ctx.newPage();

	let uploadsBaseDir = '';
	let uploadsYmDir = '';
	let testFolderAbs = '';

	try {
		const uploads = getUploadsDirs( env );
		uploadsBaseDir = uploads.basedir;
		uploadsYmDir = uploads.path;
		testFolderAbs = path.join( uploadsBaseDir, 'test' );

		const cases = [
			{
				name: 'scan-nonrecursive-selectall-keep-no-rml',
				recursive: false,
				selection: { mode: 'all' },
				options: {
					duplicate_check: 'hash',
					duplicate_handling: 'skip',
					file_operation: 'keep',
					batch_size: 3,
					rml_integration: false,
				},
				expect: { importedCountMode: 'success' },
				verify: { rmlFolders: [] },
			},
			{
				name: 'scan-recursive-selectmultiple-copy-rml',
				recursive: true,
				selection: { mode: 'multiple', count: 5 },
				options: {
					duplicate_check: 'filesize',
					duplicate_handling: 'rename',
					file_operation: 'copy',
					batch_size: 2,
					rml_integration: true,
				},
				expect: { importedCount: 5 },
				verify: {
					rmlFolders: [
						'test',
						'sub-a',
						'sub-b',
						'nested',
						'rand-2',
						'nest',
					],
				},
				selectMatchers: [
					'/overwrite-target.jpg',
					'/sub-a/',
					'/sub-b/nested/',
					'/rand-1/',
					'/rand-2/nest/',
				],
			},
			{
				name: 'move-selectmultiple-hash-skip-rml',
				recursive: false,
				selection: { mode: 'multiple', count: 4 },
				options: {
					duplicate_check: 'hash',
					duplicate_handling: 'skip',
					file_operation: 'move',
					batch_size: 3,
					rml_integration: true,
				},
				expect: { importedCount: 4 },
				verify: { rmlFolders: [ 'test' ] },
			},
			{
				name: 'overwrite-filename-copy',
				recursive: false,
				selection: { mode: 'multiple', count: 1 }, // will select overwrite-target only
				options: {
					duplicate_check: 'filename',
					duplicate_handling: 'overwrite',
					file_operation: 'copy',
					batch_size: 1,
					rml_integration: false,
				},
				expect: { importedCount: 1, overwrite: true },
				verify: { rmlFolders: [] },
			},
		];

		for ( const tc of cases ) {
			// Per-case: reset DB first (ensures a stable baseline even if prior run failed).
			importDb2Sql( env );

			// Create fresh internet images into uploads/test for this test case.
			const prepared = prepareTwentyInternetImages(
				uploadsBaseDir,
				nowStamp()
			);
			const expectedScanCount = countFilesByRecursion(
				prepared.baseDir,
				prepared.files,
				!! tc.recursive
			);

			// eslint-disable-next-line no-await-in-loop
			await gotoAdminPage(
				page,
				env.target,
				'/wp-admin/admin.php?page=rsl-ie-media-sync'
			);
			// eslint-disable-next-line no-await-in-loop
			await page.waitForSelector( '#rsl-ie-media-sync', {
				timeout: 60_000,
			} );

			// Step 1: Browse and choose folder.
			// eslint-disable-next-line no-await-in-loop
			await chooseFolderViaBrowseModal( page, testFolderAbs );

			// Step 1: Scan (exercise recursive toggle).
			// eslint-disable-next-line no-await-in-loop
			const scanCount = await scanFolder( page, {
				recursive: tc.recursive,
				fileTypes: 'images',
			} );
			if ( scanCount !== expectedScanCount ) {
				throw new Error(
					`${ tc.name }: scanCount mismatch (expected ${ expectedScanCount }, got ${ scanCount })`
				);
			}

			// Selection behavior.
			// eslint-disable-next-line no-await-in-loop
			await setSelectionMode( page, tc.selection.mode, {
				count: tc.selection.count,
			} );

			// For cases that need coverage of subdirectories, select a mixed set by matchers.
			if (
				Array.isArray( tc.selectMatchers ) &&
				tc.selectMatchers.length
			) {
				await selectSpecificFilesByMatchers( page, tc.selectMatchers );
			}

			// For overwrite test: ensure only overwrite-target.jpg is selected.
			if ( tc.expect && tc.expect.overwrite ) {
				const boxes = page.locator( '.rsl-ie-file-checkbox' );
				const total = await boxes.count();
				for ( let i = 0; i < total; i++ ) {
					// eslint-disable-next-line no-await-in-loop
					await boxes.nth( i ).setChecked( false );
				}
				// Locate checkbox by its value/path.
				const overwriteBox = page.locator(
					`.rsl-ie-file-checkbox[value="${ prepared.overwritePath.replace(
						/"/g,
						'\\"'
					) }"]`
				);
				if ( ! ( await overwriteBox.count().catch( () => 0 ) ) ) {
					throw new Error(
						`${ tc.name }: overwrite target not present in scan results`
					);
				}
				await overwriteBox.first().setChecked( true );
				const selectedText = await page
					.locator( '#rsl-ie-selected-count' )
					.innerText();
				const selected = Number(
					String( selectedText ).replace( /[^0-9]/g, '' )
				);
				if ( selected !== 1 )
					throw new Error(
						`${ tc.name }: expected exactly 1 selected, got ${ selected }`
					);
			}

			const selectedPaths = await getSelectedFilePaths( page );

			// Step 2: set options.
			// eslint-disable-next-line no-await-in-loop
			await setSyncOptions( page, tc.options );

			// Start sync.
			const minUpdatedAt = wp( env, env.target.wpPath, [
				'eval',
				'echo current_time("mysql");',
			] );
			// eslint-disable-next-line no-await-in-loop
			const jobInfo = await startSyncAndWait( page, env, {
				minUpdatedAt,
			} );
			const jobRow = jobInfo.row;

			// For overwrite: run a second sync where the source file changes but filename stays.
			let overwriteMetaBefore = null;
			let overwriteMetaAfter = null;
			if ( tc.expect && tc.expect.overwrite ) {
				const ids = getAttachmentIdsByOriginalPathExact(
					env,
					prepared.overwritePath
				);
				if ( ids.length !== 1 ) {
					throw new Error(
						`${ tc.name }: expected 1 attachment for overwrite target, got ${ ids.length }`
					);
				}
				const attachId = ids[ 0 ];
				overwriteMetaBefore = getAttachmentMeta(
					env,
					attachId,
					'rsl_ie_file_hash'
				);

				// Replace the source file bytes (same filename).
				downloadJpg(
					`https://picsum.photos/seed/${ encodeURIComponent(
						`${ nowStamp() }-overwrite-b`
					) }/800/600.jpg`,
					prepared.overwritePath
				);

				// Re-open page and rescan (same folder), select overwrite-target only and sync again.
				await gotoAdminPage(
					page,
					env.target,
					'/wp-admin/admin.php?page=rsl-ie-media-sync'
				);
				await chooseFolderViaBrowseModal( page, testFolderAbs );
				await scanFolder( page, {
					recursive: false,
					fileTypes: 'images',
				} );

				const boxes = page.locator( '.rsl-ie-file-checkbox' );
				const total = await boxes.count();
				for ( let i = 0; i < total; i++ ) {
					// eslint-disable-next-line no-await-in-loop
					await boxes.nth( i ).setChecked( false );
				}
				await page
					.locator(
						`.rsl-ie-file-checkbox[value="${ prepared.overwritePath.replace(
							/"/g,
							'\\"'
						) }"]`
					)
					.first()
					.setChecked( true );
				await setSyncOptions( page, tc.options );
				const minUpdatedAt2 = wp( env, env.target.wpPath, [
					'eval',
					'echo current_time("mysql");',
				] );
				await startSyncAndWait( page, env, {
					minUpdatedAt: minUpdatedAt2,
				} );

				const idsAfter = getAttachmentIdsByOriginalPathExact(
					env,
					prepared.overwritePath
				);
				if ( idsAfter.length !== 1 || idsAfter[ 0 ] !== attachId ) {
					throw new Error(
						`${
							tc.name
						}: overwrite should keep same attachment (before=${ attachId }, after=${ idsAfter.join(
							','
						) })`
					);
				}
				overwriteMetaAfter = getAttachmentMeta(
					env,
					attachId,
					'rsl_ie_file_hash'
				);
				if (
					overwriteMetaBefore &&
					overwriteMetaAfter &&
					overwriteMetaBefore === overwriteMetaAfter
				) {
					throw new Error(
						`${ tc.name }: expected rsl_ie_file_hash to change after overwrite`
					);
				}
			}

			// Verify import result in DB (attachments meta written by Media_Sync).
			const importedCount = countImportedAttachmentsByOriginalPathPrefix(
				env,
				prepared.baseDir
			);
			if (
				tc.expect &&
				typeof tc.expect.importedCount === 'number' &&
				importedCount !== tc.expect.importedCount
			) {
				throw new Error(
					`${ tc.name }: importedCount mismatch (expected ${ tc.expect.importedCount }, got ${ importedCount })`
				);
			}
			if (
				tc.expect &&
				tc.expect.importedCountMode === 'scan' &&
				importedCount !== scanCount
			) {
				throw new Error(
					`${ tc.name }: importedCount mismatch (expected == scanCount ${ scanCount }, got ${ importedCount })`
				);
			}
			if (
				tc.expect &&
				tc.expect.importedCountMode === 'success' &&
				importedCount !== Number( jobRow.success_items || 0 )
			) {
				throw new Error(
					`${ tc.name }: importedCount mismatch (expected == success_items ${ jobRow.success_items }, got ${ importedCount })`
				);
			}

			// Verify file_operation semantics for move (source files in uploads/test should be removed).
			if ( tc.options.file_operation === 'move' ) {
				const stillExists = selectedPaths.some( ( f ) =>
					fs.existsSync( f )
				);
				if ( stillExists ) {
					throw new Error(
						`${ tc.name }: expected move to remove selected source files from uploads/test`
					);
				}
			}
			if ( tc.options.file_operation === 'copy' ) {
				const copiedDir = path.join( uploadsYmDir, 'test' );
				if ( ! fs.existsSync( copiedDir ) ) {
					throw new Error(
						`${ tc.name }: expected copy to create ${ copiedDir }`
					);
				}
			}

			// Verify RML folders exist if requested.
			let rml = {};
			if (
				tc.options.rml_integration &&
				tc.verify &&
				tc.verify.rmlFolders.length
			) {
				rml = getRmlFoldersByNames( env, tc.verify.rmlFolders );
				for ( const name of tc.verify.rmlFolders ) {
					if ( ! rml[ name ] ) {
						throw new Error(
							`${ tc.name }: RML folder not created: ${ name }`
						);
					}
				}
			}

			summary.cases.push( {
				name: tc.name,
				scanCount,
				importedCount,
				options: tc.options,
				job: {
					id: Number( jobRow.id || 0 ) || null,
					status: String( jobRow.status || '' ),
					total_items: Number( jobRow.total_items || 0 ),
					processed_items: Number( jobRow.processed_items || 0 ),
					success_items: Number( jobRow.success_items || 0 ),
					failed_items: Number( jobRow.failed_items || 0 ),
					uiDone: !! jobInfo.uiDone,
					pseudoCompleted: !! jobInfo.pseudoCompleted,
				},
				rml,
				overwriteHashBefore: overwriteMetaBefore,
				overwriteHashAfter: overwriteMetaAfter,
			} );

			// Cleanup filesystem created by this case (uploads/test and uploads/YYYY/MM/test)
			rmrf( prepared.baseDir );
			rmrf( path.join( uploadsYmDir, 'test' ) );

			// Reset DB to db2.sql after each case, as requested.
			importDb2Sql( env );
		}

		summary.finishedAt = new Date().toISOString();
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
		// Best-effort cleanup.
		if ( testFolderAbs ) rmrf( testFolderAbs );
		if ( uploadsYmDir ) rmrf( path.join( uploadsYmDir, 'test' ) );
		await page.close().catch( () => {} );
		await ctx.close().catch( () => {} );
		rmrf( artifactsRoot );
	}
}

run().catch( ( e ) => {
	console.error( e );
	process.exitCode = 1;
} );
