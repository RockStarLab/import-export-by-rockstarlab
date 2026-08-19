/**
 * Manual E2E (Playwright): Users export/import check.
 *
 * Usage:
 *   AIE_HEADLESS=true PLAYWRIGHT_BROWSERS_PATH=./e2e/.playwright-browsers node scripts/aie-users-import-export-check.js
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );
const { chromium } = require( 'playwright' );

const FREE_PLUGIN_FILE =
	'import-export-by-rockstarlab/import-export-by-rockstarlab.php';
const PRO_PLUGIN_FILE =
	'import-export-pro-by-rockstarlab/import-export-pro-by-rockstarlab.php';

function env() {
	const sourceWpPath = path.resolve( process.cwd(), '../../..' );
	return {
		headless: ! /^(false|0|no)$/i.test(
			process.env.AIE_HEADLESS || 'true'
		),
		wpBin: process.env.AIE_WP_BIN || '/opt/homebrew/bin/wp',
		localPhp:
			process.env.AIE_LOCAL_PHP ||
			'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php',
		mysqlBin:
			process.env.AIE_LOCAL_MYSQL ||
			'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/mysql-8.4.0/bin/darwin-arm64/bin/mysql',
		source: {
			baseUrl: process.env.AIE_SOURCE_URL || 'http://aie.local',
			username: process.env.AIE_SOURCE_ADMIN_USER || 'admin',
			password: process.env.AIE_SOURCE_ADMIN_PASSWORD || 'admin',
			wpPath: process.env.AIE_SOURCE_WP_PATH || sourceWpPath,
		},
		target: {
			baseUrl: process.env.AIE_TARGET_URL || 'http://aie2.local',
			username: process.env.AIE_TARGET_ADMIN_USER || 'admin',
			password: process.env.AIE_TARGET_ADMIN_PASSWORD || 'admin',
			wpPath:
				process.env.AIE_TARGET_WP_PATH ||
				sourceWpPath.replace(
					`${ path.sep }Local Sites${ path.sep }aie${ path.sep }`,
					`${ path.sep }Local Sites${ path.sep }aie2${ path.sep }`
				),
		},
		targetDbSql: process.env.AIE_TARGET_DB_SQL || 'db.sql',
	};
}

function stamp() {
	return new Date().toISOString().replace( /[:.]/g, '-' );
}

function wp( cfg, site, args, options = {} ) {
	return execFileSync(
		cfg.localPhp,
		[
			'-d',
			'display_errors=0',
			'-d',
			'error_reporting=0',
			'-d',
			'html_errors=0',
			'-d',
			'memory_limit=512M',
			cfg.wpBin,
			`--path=${ site.wpPath || site }`,
			...args,
		],
		{
			encoding: 'utf8',
			stdio: [ 'ignore', 'pipe', 'pipe' ],
			timeout: options.timeout || 180_000,
		}
	).trim();
}

function wpJson( cfg, site, code ) {
	return JSON.parse(
		wp( cfg, site, [ 'eval', code ], { timeout: 180_000 } ) || 'null'
	);
}

function ensurePluginActive( cfg, site, pluginFile ) {
	try {
		wp( cfg, site, [ 'plugin', 'is-active', pluginFile ] );
	} catch {
		wp( cfg, site, [ 'plugin', 'activate', pluginFile ] );
	}
}

function restoreTargetDb( cfg ) {
	const sqlPath = path.join( cfg.target.wpPath, cfg.targetDbSql );
	const dbName = wp( cfg, cfg.target, [ 'config', 'get', 'DB_NAME' ] );
	const dbUser = wp( cfg, cfg.target, [ 'config', 'get', 'DB_USER' ] );
	const dbPass = wp( cfg, cfg.target, [ 'config', 'get', 'DB_PASSWORD' ] );
	const dbHost = wp( cfg, cfg.target, [ 'config', 'get', 'DB_HOST' ] );
	const socket = String( dbHost ).startsWith( ':' )
		? String( dbHost ).slice( 1 )
		: '';
	if ( ! socket ) throw new Error( `Unsupported DB_HOST: ${ dbHost }` );
	execFileSync(
		cfg.mysqlBin,
		[
			'--protocol=socket',
			`--socket=${ socket }`,
			`-u${ dbUser }`,
			`-p${ dbPass }`,
			dbName,
		],
		{ input: fs.readFileSync( sqlPath ), timeout: 180_000 }
	);
	wp( cfg, cfg.target, [ 'core', 'update-db', '--quiet' ] );
}

function installAcfFixture( cfg, site, withValues ) {
	wp(
		cfg,
		site,
		[
			'eval',
			`
if (!function_exists('acf_update_field_group')) { echo 'no-acf'; return; }
foreach (['group_rsl_ie_user_e2e'] as $key) {
  $posts = get_posts(['post_type'=>'acf-field-group','post_status'=>'any','name'=>$key,'posts_per_page'=>-1]);
  foreach ($posts as $post) { wp_delete_post($post->ID, true); }
}
foreach (['field_rsl_ie_user_badge','field_rsl_ie_user_note'] as $key) {
  $posts = get_posts(['post_type'=>'acf-field','post_status'=>'any','name'=>$key,'posts_per_page'=>-1]);
  foreach ($posts as $post) { wp_delete_post($post->ID, true); }
}
if (function_exists('acf_import_field_group')) {
  acf_import_field_group([
    'key'=>'group_rsl_ie_user_e2e',
    'title'=>'RSL IE User E2E',
    'fields'=>[
      ['key'=>'field_rsl_ie_user_badge','label'=>'User Badge','name'=>'rsl_ie_user_badge','type'=>'text'],
      ['key'=>'field_rsl_ie_user_note','label'=>'User Note','name'=>'rsl_ie_user_note','type'=>'textarea'],
    ],
    'location'=>[[['param'=>'user_form','operator'=>'==','value'=>'all']]],
    'position'=>'normal','style'=>'default','active'=>true,
  ]);
}
if (${ withValues ? 'true' : 'false' }) {
  $users = get_users(['orderby'=>'ID','order'=>'ASC']);
  foreach ($users as $user) {
    $login = sanitize_title($user->user_login);
    wp_update_user([
      'ID' => $user->ID,
      'first_name' => 'First '.$user->user_login,
      'last_name' => 'Last '.$user->user_login,
      'display_name' => 'Display '.$user->user_login,
      'description' => 'Bio for '.$user->user_login,
      'user_url' => 'https://example.test/users/'.$login,
    ]);
    update_user_meta($user->ID, 'nickname', 'Nick '.$user->user_login);
    update_user_meta($user->ID, 'locale', 'en_US');
    update_user_meta($user->ID, 'admin_color', 'midnight');
    update_user_meta($user->ID, 'rich_editing', 'true');
    update_user_meta($user->ID, 'facebook', 'https://facebook.example/'.$login);
    update_user_meta($user->ID, 'instagram', 'https://instagram.example/'.$login);
    update_user_meta($user->ID, 'linkedin', 'https://linkedin.example/'.$login);
    update_user_meta($user->ID, 'twitter', 'x_'.$login);
    update_user_meta($user->ID, 'youtube', 'https://youtube.example/'.$login);
    update_user_meta($user->ID, 'rsl_ie_user_plain_meta', 'plain-'.$login);
    update_field('field_rsl_ie_user_badge', 'badge-'.$login, 'user_'.$user->ID);
    update_field('field_rsl_ie_user_note', 'Note for '.$user->user_login, 'user_'.$user->ID);
  }
}
echo 'ok';
`,
		],
		{ timeout: 180_000 }
	);
}

function userSnapshot( cfg, site ) {
	return wpJson(
		cfg,
		site,
		`
$users = get_users(['orderby'=>'user_login','order'=>'ASC']);
$out = [];
foreach ($users as $u) {
  $acf = function_exists('get_fields') ? get_fields('user_'.$u->ID) : [];
  $out[] = [
    'user_login' => (string) $u->user_login,
    'user_email' => (string) $u->user_email,
    'user_nicename' => (string) $u->user_nicename,
    'user_url' => (string) $u->user_url,
    'user_registered' => (string) $u->user_registered,
    'display_name' => (string) $u->display_name,
    'first_name' => (string) get_user_meta($u->ID, 'first_name', true),
    'last_name' => (string) get_user_meta($u->ID, 'last_name', true),
    'nickname' => (string) get_user_meta($u->ID, 'nickname', true),
    'description' => (string) get_user_meta($u->ID, 'description', true),
    'roles' => array_values($u->roles),
    'locale' => (string) get_user_meta($u->ID, 'locale', true),
    'admin_color' => (string) get_user_meta($u->ID, 'admin_color', true),
    'rich_editing' => (string) get_user_meta($u->ID, 'rich_editing', true),
    'facebook' => (string) get_user_meta($u->ID, 'facebook', true),
    'instagram' => (string) get_user_meta($u->ID, 'instagram', true),
    'linkedin' => (string) get_user_meta($u->ID, 'linkedin', true),
    'twitter' => (string) get_user_meta($u->ID, 'twitter', true),
    'youtube' => (string) get_user_meta($u->ID, 'youtube', true),
    'rsl_ie_user_plain_meta' => (string) get_user_meta($u->ID, 'rsl_ie_user_plain_meta', true),
    'acf' => is_array($acf) ? $acf : [],
  ];
}
echo wp_json_encode($out, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`
	);
}

function byLogin( rows ) {
	return new Map( rows.map( ( row ) => [ row.user_login, row ] ) );
}

async function login( page, site ) {
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
}

async function selectUserType( page ) {
	await page.locator( '#rsl-ie-content-type-search' ).fill( 'none' );
	await page.locator( '#rsl-ie-content-type-search' ).fill( 'user' );
	await page.evaluate( () => {
		const input = document.querySelector(
			'input[name="content_type"][value="user"]'
		);
		if ( ! input ) throw new Error( 'User content type not found' );
		input.checked = true;
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'click', { bubbles: true } ) );
	} );
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

async function handleBackupModal( page ) {
	const warning = page.locator( '.rsl-ie-backup-warning-overlay' );
	if ( await warning.count() ) {
		await warning
			.waitFor( { state: 'visible', timeout: 3000 } )
			.catch( () => null );
		if ( await warning.isVisible().catch( () => false ) ) {
			await page
				.locator( '#rsl-ie-backup-created' )
				.check( { force: true } );
			await page
				.locator( '#rsl-ie-backup-dont-show' )
				.check( { force: true } )
				.catch( () => null );
			await page.locator( '.rsl-ie-backup-confirm' ).click();
		}
	}
}

async function setSelectValue( page, selector, value ) {
	await page.evaluate(
		( args ) => {
			const el = document.querySelector( args.selector );
			if ( ! el ) return;
			el.value = args.value;
			el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		},
		{ selector, value }
	);
}

async function addAllVisibleFields( page ) {
	await page.waitForSelector( '.rsl-ie-step-3.active' );
	await page
		.locator( '#rsl-ie-fields-search' )
		.fill( 'acf' )
		.catch( () => null );
	await page
		.waitForFunction(
			() =>
				document.querySelector(
					'.rsl-ie-step-3.active .rsl-ie-field-item[data-field="acf_rsl_ie_user_badge"]'
				),
			null,
			{ timeout: 60_000 }
		)
		.catch( () => null );
	await page
		.locator( '.rsl-ie-clear-search' )
		.first()
		.click()
		.catch( () => null );
	await page.evaluate( () => {
		document
			.querySelectorAll( '.rsl-ie-step-3.active .rsl-ie-add-all-fields' )
			.forEach( ( button ) => {
				const category = button.closest( '.rsl-ie-field-category' );
				if (
					category &&
					getComputedStyle( category ).display !== 'none'
				) {
					button.click();
				}
			} );
		const step3 = window.rslIeExportModule?.step3Instance;
		if (
			step3 &&
			! step3.selectedFields.some(
				( field ) => field.field === 'user_meta'
			)
		) {
			step3.addFieldToCSV( {
				field: 'user_meta',
				label: 'User meta',
				type: 'object',
			} );
		}
	} );
	await page.waitForFunction(
		() =>
			Number(
				document.querySelector( '.rsl-ie-columns-count' )
					?.textContent || '0'
			) > 0,
		null,
		{ timeout: 60_000 }
	);
}

async function exerciseFilterUi( page, cfg ) {
	await login( page, cfg.source );
	await page.goto(
		`${ cfg.source.baseUrl }/wp-admin/admin.php?page=rsl-ie-export`,
		{ waitUntil: 'domcontentloaded' }
	);
	await page.waitForSelector( '#rsl-ie-export' );
	await selectUserType( page );
	await clickNext( page );
	await page.waitForSelector( '.rsl-ie-step-2.active' );
	await page
		.locator( '.rsl-ie-refresh-count' )
		.click()
		.catch( () => null );
	await page.locator( '.rsl-ie-add-filter' ).click();
	await page.waitForTimeout( 500 );
	await page
		.locator(
			'.rsl-ie-filter-row select, .rsl-ie-filter-row input, .rsl-ie-filter-row button'
		)
		.first()
		.focus()
		.catch( () => null );
	await page
		.locator( '.rsl-ie-remove-filter' )
		.last()
		.click()
		.catch( () => null );
}

async function exportUsers( page, cfg, outDir ) {
	await login( page, cfg.source );
	await page.goto(
		`${ cfg.source.baseUrl }/wp-admin/admin.php?page=rsl-ie-export`,
		{ waitUntil: 'domcontentloaded' }
	);
	await page.waitForSelector( '#rsl-ie-export' );
	await selectUserType( page );
	await clickNext( page );
	await page.waitForSelector( '.rsl-ie-step-2.active' );
	await clickNext( page );
	await page.waitForSelector( '.rsl-ie-step-3.active' );
	await addAllVisibleFields( page );
	await clickNext( page );
	await page.waitForSelector( '.rsl-ie-step-4.active' );
	for ( const delimiter of [ ';', 'tab', '|', 'custom', ',' ] ) {
		await setSelectValue( page, 'select[name="csv_delimiter"]', delimiter );
	}
	await page.evaluate( () => {
		for ( const name of [ 'csv_include_header', 'json_pretty_print' ] ) {
			const el = document.querySelector( `input[name="${ name }"]` );
			if ( el ) {
				el.checked = ! el.checked;
				el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				el.checked = ! el.checked;
				el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		}
		const batch = document.querySelector(
			'input[name="items_per_iteration"]'
		);
		if ( batch ) {
			batch.value = '2';
			batch.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	} );
	await page.locator( '.rsl-ie-start-export' ).click();
	await page
		.locator( '.rsl-ie-export-complete-card' )
		.waitFor( { state: 'visible', timeout: 10 * 60_000 } );
	const [ download ] = await Promise.all( [
		page.waitForEvent( 'download' ),
		page.locator( '.rsl-ie-download-file' ).click(),
	] );
	const exportPath = path.join( outDir, 'users-export.csv' );
	await download.saveAs( exportPath );
	const csv = fs.readFileSync( exportPath, 'utf8' );
	const header = csv.split( /\r?\n/ )[ 0 ] || '';
	if ( csv.split( /\r?\n/ ).filter( Boolean ).length <= 1 ) {
		throw new Error( 'Users export has no data rows' );
	}
	for ( const field of [
		'user_login',
		'user_email',
		'roles',
		'user_meta',
		'acf_rsl_ie_user_badge',
	] ) {
		if ( ! header.includes( field ) )
			throw new Error( `Users export is missing ${ field }` );
	}
	return exportPath;
}

async function importUsers( page, cfg, csvPath ) {
	await login( page, cfg.target );
	await page.goto(
		`${ cfg.target.baseUrl }/wp-admin/admin.php?page=rsl-ie-import`,
		{ waitUntil: 'domcontentloaded' }
	);
	await page.waitForSelector( '#rsl-ie-import' );
	await selectUserType( page );
	await clickNext( page );
	await handleBackupModal( page );
	await page.waitForSelector( '.rsl-ie-step-2.active' );
	for ( const delimiter of [ ';', 'tab', '|', 'custom', ',' ] ) {
		await setSelectValue( page, '#csv_delimiter', delimiter );
	}
	await page.setInputFiles( '#rsl-ie-file-input', csvPath );
	await page.waitForFunction(
		() =>
			! document.querySelector(
				'.rsl-ie-step-2.active .rsl-ie-next-step'
			)?.disabled
	);
	await page.locator( '.rsl-ie-remove-file' ).click();
	await page.setInputFiles( '#rsl-ie-file-input', csvPath );
	await clickNext( page );
	await page.waitForSelector( '.rsl-ie-step-3.active' );
	await clickNext( page );
	await page.waitForSelector( '.rsl-ie-step-4.active' );
	await page.waitForFunction(
		() =>
			document.querySelector(
				'#rsl-ie-target-fields .rsl-ie-target-field[data-target-field="acf_rsl_ie_user_badge"]'
			),
		null,
		{ timeout: 60_000 }
	);
	await page.locator( '.rsl-ie-clear-map' ).click();
	await page.locator( '.rsl-ie-auto-map' ).click();
	await page.waitForFunction(
		() =>
			Number(
				document.querySelector( '.rsl-ie-mapped-count' )?.textContent ||
					'0'
			) > 0
	);
	const mapping = await page.evaluate( () => ( {
		mapped: Number(
			document.querySelector( '.rsl-ie-mapped-count' )?.textContent || '0'
		),
		total: Number(
			document.querySelector( '.rsl-ie-total-fields' )?.textContent || '0'
		),
	} ) );
	await clickNext( page );
	await page.waitForSelector( '.rsl-ie-step-5.active' );
	await page
		.locator( '#rsl-ie-unique-field' )
		.selectOption( 'user_login' )
		.catch( () => null );
	for ( const value of [ 'skip', 'update' ] ) {
		await page
			.locator( `input[name="if_exists"][value="${ value }"]` )
			.check( { force: true } )
			.catch( () => null );
	}
	await page
		.locator( 'input[name="if_not_exists"][value="create"]' )
		.check( { force: true } )
		.catch( () => null );
	await page.locator( '.rsl-ie-start-import' ).click();
	await handleBackupModal( page );
	await page
		.locator( '.rsl-ie-import-complete-card' )
		.waitFor( { state: 'visible', timeout: 10 * 60_000 } );
	return mapping;
}

async function verifyUsersInBrowser( page, cfg, sourceRows ) {
	await login( page, cfg.target );
	const missing = [];
	for ( const user of sourceRows ) {
		await page.goto(
			`${ cfg.target.baseUrl }/wp-admin/users.php?s=${ encodeURIComponent(
				user.user_login
			) }`,
			{
				waitUntil: 'domcontentloaded',
			}
		);
		const body = await page.locator( 'body' ).innerText();
		if ( ! body.includes( user.user_login ) )
			missing.push( user.user_login );
	}
	return missing;
}

async function main() {
	const cfg = env();
	const outDir = path.resolve(
		process.cwd(),
		'e2e/artifacts/users-import-export',
		stamp()
	);
	fs.mkdirSync( outDir, { recursive: true } );

	for ( const site of [ cfg.source, cfg.target ] ) {
		ensurePluginActive( cfg, site, FREE_PLUGIN_FILE );
		ensurePluginActive( cfg, site, PRO_PLUGIN_FILE );
	}

	installAcfFixture( cfg, cfg.source, true );
	const sourceRaw = userSnapshot( cfg, cfg.source );
	fs.writeFileSync(
		path.join( outDir, 'source-users.json' ),
		JSON.stringify( sourceRaw, null, 2 )
	);

	restoreTargetDb( cfg );
	ensurePluginActive( cfg, cfg.target, FREE_PLUGIN_FILE );
	ensurePluginActive( cfg, cfg.target, PRO_PLUGIN_FILE );
	installAcfFixture( cfg, cfg.target, false );

	const browser = await chromium.launch( { headless: cfg.headless } );
	const context = await browser.newContext( { acceptDownloads: true } );
	const page = await context.newPage();
	try {
		await exerciseFilterUi( page, cfg );
		const exportPath = await exportUsers( page, cfg, outDir );
		const mapping = await importUsers( page, cfg, exportPath );
		const targetRaw = userSnapshot( cfg, cfg.target );
		fs.writeFileSync(
			path.join( outDir, 'target-users.json' ),
			JSON.stringify( targetRaw, null, 2 )
		);
		const browserMissing = await verifyUsersInBrowser(
			page,
			cfg,
			sourceRaw
		);

		const targetByLogin = byLogin( targetRaw );
		const issues = [];
		for ( const expected of sourceRaw ) {
			const actual = targetByLogin.get( expected.user_login );
			if ( ! actual ) {
				issues.push( {
					user: expected.user_login,
					field: 'missing_user',
				} );
				continue;
			}
			for ( const field of Object.keys( expected ) ) {
				if ( field === 'user_registered' ) {
					if (
						String( expected[ field ] ).slice( 0, 10 ) !==
						String( actual[ field ] ).slice( 0, 10 )
					) {
						issues.push( {
							user: expected.user_login,
							field,
							expected: expected[ field ],
							actual: actual[ field ],
						} );
					}
					continue;
				}
				if (
					JSON.stringify( expected[ field ] ) !==
					JSON.stringify( actual[ field ] )
				) {
					issues.push( {
						user: expected.user_login,
						field,
						expected: expected[ field ],
						actual: actual[ field ],
					} );
				}
			}
		}
		for ( const loginName of browserMissing ) {
			issues.push( { user: loginName, field: 'browser_missing' } );
		}

		const summary = {
			sourceCount: sourceRaw.length,
			targetCount: targetRaw.length,
			exportPath,
			mapping,
			browserMissing,
			issueCount: issues.length,
			issues,
			outDir,
		};
		fs.writeFileSync(
			path.join( outDir, 'summary.json' ),
			JSON.stringify( summary, null, 2 )
		);
		console.log( JSON.stringify( summary, null, 2 ) );
		if ( issues.length )
			throw new Error( `Users import/export issues: ${ issues.length }` );
	} finally {
		await context.close().catch( () => null );
		await browser.close().catch( () => null );
	}
}

main().catch( ( error ) => {
	console.error( error.stack || error.message || error );
	process.exit( 1 );
} );
