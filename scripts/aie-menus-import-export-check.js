/**
 * Manual E2E (Playwright): Menus export/import check.
 *
 * Usage:
 *   AIE_HEADLESS=true PLAYWRIGHT_BROWSERS_PATH=./e2e/.playwright-browsers node scripts/aie-menus-import-export-check.js
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

function installAcfFixture( cfg, site ) {
	wp(
		cfg,
		site,
		[
			'eval',
			`
if (!function_exists('acf_update_field_group')) { echo 'no-acf'; return; }
foreach (['group_rsl_ie_menu_e2e','group_rsl_ie_menu_item_e2e'] as $key) {
  $posts = get_posts(['post_type'=>'acf-field-group','post_status'=>'any','name'=>$key,'posts_per_page'=>-1]);
  foreach ($posts as $post) { wp_delete_post($post->ID, true); }
}
foreach (['field_rsl_ie_menu_badge','field_rsl_ie_menu_note','field_rsl_ie_item_badge'] as $key) {
  $posts = get_posts(['post_type'=>'acf-field','post_status'=>'any','name'=>$key,'posts_per_page'=>-1]);
  foreach ($posts as $post) { wp_delete_post($post->ID, true); }
}
if (function_exists('acf_import_field_group')) {
  acf_import_field_group([
    'key'=>'group_rsl_ie_menu_e2e',
    'title'=>'RSL IE Menu E2E',
    'fields'=>[
      ['key'=>'field_rsl_ie_menu_badge','label'=>'Menu Badge','name'=>'rsl_ie_menu_badge','type'=>'text'],
      ['key'=>'field_rsl_ie_menu_note','label'=>'Menu Note','name'=>'rsl_ie_menu_note','type'=>'textarea'],
    ],
    'location'=>[[['param'=>'nav_menu','operator'=>'==','value'=>'all']]],
    'position'=>'normal','style'=>'default','active'=>true,
  ]);
  acf_import_field_group([
    'key'=>'group_rsl_ie_menu_item_e2e',
    'title'=>'RSL IE Menu Item E2E',
    'fields'=>[
      ['key'=>'field_rsl_ie_item_badge','label'=>'Item Badge','name'=>'rsl_ie_item_badge','type'=>'text'],
    ],
    'location'=>[[['param'=>'nav_menu_item','operator'=>'==','value'=>'all']]],
    'position'=>'normal','style'=>'default','active'=>true,
  ]);
} else {
  $group = ['key'=>'group_rsl_ie_menu_e2e','title'=>'RSL IE Menu E2E','fields'=>[],'location'=>[[['param'=>'nav_menu','operator'=>'==','value'=>'all']]],'position'=>'normal','style'=>'default','active'=>true];
  acf_update_field_group($group);
  $g = acf_get_field_group('group_rsl_ie_menu_e2e');
  acf_update_field(['key'=>'field_rsl_ie_menu_badge','label'=>'Menu Badge','name'=>'rsl_ie_menu_badge','type'=>'text','parent'=>$g['ID']]);
  acf_update_field(['key'=>'field_rsl_ie_menu_note','label'=>'Menu Note','name'=>'rsl_ie_menu_note','type'=>'textarea','parent'=>$g['ID']]);
  $item_group = ['key'=>'group_rsl_ie_menu_item_e2e','title'=>'RSL IE Menu Item E2E','fields'=>[],'location'=>[[['param'=>'nav_menu_item','operator'=>'==','value'=>'all']]],'position'=>'normal','style'=>'default','active'=>true];
  acf_update_field_group($item_group);
  $ig = acf_get_field_group('group_rsl_ie_menu_item_e2e');
  acf_update_field(['key'=>'field_rsl_ie_item_badge','label'=>'Item Badge','name'=>'rsl_ie_item_badge','type'=>'text','parent'=>$ig['ID']]);
}
$menus = wp_get_nav_menus(['hide_empty'=>false]);
if (!empty($menus)) {
  $menu = $menus[0];
  update_field('field_rsl_ie_menu_badge', 'menu-badge-'.$menu->slug, 'term_'.$menu->term_id);
  update_field('field_rsl_ie_menu_note', 'Menu note for '.$menu->name, 'term_'.$menu->term_id);
  $items = wp_get_nav_menu_items($menu->term_id);
  if (is_array($items) && !empty($items)) {
    update_field('field_rsl_ie_item_badge', 'item-badge-'.sanitize_title($items[0]->title), $items[0]->ID);
  }
}
echo 'ok';
`,
		],
		{ timeout: 180_000 }
	);
}

function menuSnapshot( cfg, site ) {
	return wpJson(
		cfg,
		site,
		`
$menus = wp_get_nav_menus(['hide_empty' => false]);
$home = home_url();
$locations = get_nav_menu_locations();
$out = [];
foreach ($menus as $m) {
  $items = wp_get_nav_menu_items($m->term_id);
  if (!is_array($items)) { $items = []; }
  usort($items, function($a,$b){ return (int)$a->menu_order <=> (int)$b->menu_order; });
  $flat = [];
  foreach ($items as $it) {
    $fields = function_exists('get_fields') ? get_fields($it->ID) : [];
    $flat[] = [
      'title' => (string) $it->title,
      'url' => (string) $it->url,
      'type' => (string) $it->type,
      'object' => (string) $it->object,
      'menu_order' => (int) $it->menu_order,
      'parent_title' => $it->menu_item_parent ? (string) get_the_title((int) $it->menu_item_parent) : '',
      'target' => (string) $it->target,
      'attr_title' => (string) $it->attr_title,
      'classes' => is_array($it->classes) ? array_values(array_filter($it->classes)) : [],
      'xfn' => (string) $it->xfn,
      'description' => (string) $it->description,
      'acf' => is_array($fields) ? $fields : [],
    ];
  }
  $term_fields = function_exists('get_fields') ? get_fields('term_'.$m->term_id) : [];
  $out[] = [
    'name' => (string) $m->name,
    'slug' => (string) $m->slug,
    'description' => (string) $m->description,
    'count' => (int) $m->count,
    'locations' => array_values(array_keys(array_filter($locations, function($id) use ($m) { return (int)$id === (int)$m->term_id; }))),
    'acf' => is_array($term_fields) ? $term_fields : [],
    'items' => $flat,
  ];
}
echo wp_json_encode(['home'=>$home,'menus'=>$out], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`
	);
}

function normalizeSnapshot( snapshot, baseUrl ) {
	const norm = ( value ) =>
		String( value || '' ).replaceAll( baseUrl, '__BASE__' );
	return ( snapshot.menus || [] ).map( ( menu ) => ( {
		name: menu.name,
		slug: menu.slug,
		description: menu.description,
		locations: menu.locations || [],
		acf: menu.acf || {},
		items: ( menu.items || [] ).map( ( item ) => ( {
			title: item.title,
			url: norm( item.url ),
			type: item.type,
			object: item.object,
			menu_order: item.menu_order,
			target: item.target,
			attr_title: item.attr_title,
			classes: item.classes || [],
			xfn: item.xfn,
			description: item.description,
			acf: item.acf || {},
		} ) ),
	} ) );
}

function dependencyKey( item ) {
	return [
		item.type || '',
		item.object || '',
		item.url || '',
		item.title || '',
	].join( '|' );
}

function dependencyAvailability( cfg, site, sourceMenus ) {
	const deps = [];
	for ( const menu of sourceMenus.menus || [] ) {
		for ( const item of menu.items || [] ) {
			if ( item.type === 'post_type' || item.type === 'taxonomy' ) {
				deps.push( {
					key: dependencyKey( {
						type: item.type,
						object: item.object,
						url: String( item.url || '' ).replaceAll(
							cfg.source.baseUrl,
							'__BASE__'
						),
						title: item.title,
					} ),
					type: item.type,
					object: item.object,
					url: String( item.url || '' ).replaceAll(
						cfg.source.baseUrl,
						site.baseUrl
					),
					title: item.title,
				} );
			}
		}
	}

	const encoded = Buffer.from( JSON.stringify( deps ), 'utf8' ).toString(
		'base64'
	);
	const rows = wpJson(
		cfg,
		site,
		`
$deps = json_decode(base64_decode('${ encoded }'), true);
$out = [];
foreach ($deps as $dep) {
  $exists = false;
  if ($dep['type'] === 'post_type') {
    $exists = url_to_postid($dep['url']) > 0;
  } elseif ($dep['type'] === 'taxonomy') {
    $path = wp_parse_url($dep['url'], PHP_URL_PATH);
    $slug = is_string($path) ? basename(trim($path, '/')) : '';
    $term = $slug !== '' ? get_term_by('slug', $slug, $dep['object']) : false;
    if (!$term || is_wp_error($term)) {
      $term = get_term_by('name', $dep['title'], $dep['object']);
    }
    $exists = $term && !is_wp_error($term);
  }
  $out[$dep['key']] = (bool) $exists;
}
echo wp_json_encode($out, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`
	);

	return rows || {};
}

function itemsMatchWithAllowedFallback(
	expectedItems,
	actualItems,
	dependencyExists
) {
	if ( expectedItems.length !== actualItems.length ) {
		return false;
	}

	for ( let index = 0; index < expectedItems.length; index++ ) {
		const expected = expectedItems[ index ];
		const actual = actualItems[ index ];
		const comparableExpected = { ...expected };
		const comparableActual = { ...actual };
		const key = dependencyKey( expected );
		const allowFallback =
			( expected.type === 'post_type' || expected.type === 'taxonomy' ) &&
			dependencyExists[ key ] === false &&
			actual.type === 'custom' &&
			actual.object === 'custom' &&
			expected.url === actual.url;

		if ( allowFallback ) {
			comparableExpected.type = 'custom';
			comparableExpected.object = 'custom';
		}

		if (
			JSON.stringify( comparableExpected ) !==
			JSON.stringify( comparableActual )
		) {
			return false;
		}
	}

	return true;
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

async function selectMenuType( page ) {
	await page.locator( '#rsl-ie-content-type-search' ).fill( 'none' );
	await page.locator( '#rsl-ie-content-type-search' ).fill( 'menu' );
	await page.evaluate( () => {
		const input = document.querySelector(
			'input[name="content_type"][value="menu"]'
		);
		if ( ! input ) throw new Error( 'Menu content type not found' );
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
	await page
		.waitForFunction(
			() =>
				document.querySelectorAll(
					'.rsl-ie-step-3.active .rsl-ie-acf-fields-grid .rsl-ie-field-item'
				).length > 0,
			null,
			{ timeout: 60_000 }
		)
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

async function exportMenus( page, cfg, outDir ) {
	await login( page, cfg.source );
	await page.goto(
		`${ cfg.source.baseUrl }/wp-admin/admin.php?page=rsl-ie-export`,
		{ waitUntil: 'domcontentloaded' }
	);
	await page.waitForSelector( '#rsl-ie-export' );
	await selectMenuType( page );
	await clickNext( page );
	await page.waitForSelector( '.rsl-ie-step-2.active' );
	await page
		.locator( '.rsl-ie-refresh-count' )
		.click()
		.catch( () => null );
	await page.locator( '.rsl-ie-add-filter' ).click();
	await page.waitForTimeout( 300 );
	await page.locator( '.rsl-ie-remove-filter' ).last().click();
	await page.goto(
		`${ cfg.source.baseUrl }/wp-admin/admin.php?page=rsl-ie-export`,
		{ waitUntil: 'domcontentloaded' }
	);
	await page.waitForSelector( '#rsl-ie-export' );
	await selectMenuType( page );
	await clickNext( page );
	await page.waitForSelector( '.rsl-ie-step-2.active' );
	await clickNext( page );
	await page.waitForSelector( '.rsl-ie-step-3.active' );
	await page
		.locator( '#rsl-ie-fields-search' )
		.fill( 'acf' )
		.catch( () => null );
	await page
		.locator( '.rsl-ie-clear-search' )
		.first()
		.click()
		.catch( () => null );
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
	const exportPath = path.join( outDir, 'menus-export.csv' );
	await download.saveAs( exportPath );
	const csv = fs.readFileSync( exportPath, 'utf8' );
	if ( csv.split( /\r?\n/ ).filter( Boolean ).length <= 1 ) {
		throw new Error( 'Menu export has no data rows' );
	}
	if ( ! csv.split( /\r?\n/ )[ 0 ].includes( 'acf_rsl_ie_menu_badge' ) ) {
		throw new Error( 'Menu export is missing ACF menu fields' );
	}
	return exportPath;
}

async function importMenus( page, cfg, csvPath ) {
	await login( page, cfg.target );
	await page.goto(
		`${ cfg.target.baseUrl }/wp-admin/admin.php?page=rsl-ie-import`,
		{ waitUntil: 'domcontentloaded' }
	);
	await page.waitForSelector( '#rsl-ie-import' );
	await selectMenuType( page );
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
				'#rsl-ie-target-fields .rsl-ie-target-field[data-target-field="acf_rsl_ie_menu_badge"]'
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
		.selectOption( 'slug' )
		.catch( async () => {
			await page.locator( '#rsl-ie-unique-field' ).selectOption( 'name' );
		} );
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

async function verifyMenusInBrowser( page, cfg, sourceMenus ) {
	await login( page, cfg.target );
	const missing = [];
	for ( const menu of sourceMenus ) {
		await page.goto(
			`${
				cfg.target.baseUrl
			}/wp-admin/nav-menus.php?action=edit&menu=${ encodeURIComponent(
				menu.slug
			) }`,
			{ waitUntil: 'domcontentloaded' }
		);
		const body = await page.locator( 'body' ).innerText();
		if ( ! body.includes( menu.name ) ) {
			missing.push( menu.name );
		}
	}
	return missing;
}

async function exerciseJobsPage( page, cfg ) {
	page.on( 'dialog', ( dialog ) => dialog.accept().catch( () => null ) );
	await login( page, cfg.target );
	await page.goto(
		`${ cfg.target.baseUrl }/wp-admin/admin.php?page=rsl-ie-jobs-log`,
		{
			waitUntil: 'domcontentloaded',
		}
	);
	await page
		.locator( '#rsl-ie-jobs-log' )
		.waitFor( { state: 'visible', timeout: 30_000 } );
	await page.waitForFunction(
		() => document.querySelectorAll( '#jobs-table-body tr' ).length > 0,
		null,
		{ timeout: 60_000 }
	);
	await page
		.locator( '#filter-type' )
		.selectOption( 'import' )
		.catch( () => null );
	await page
		.locator( '.rsl-ie-filter-apply' )
		.click()
		.catch( () => null );
	await page.waitForFunction(
		() => {
			const row = document.querySelector( '#jobs-table-body tr' );
			return row && ! row.classList.contains( 'no-items' );
		},
		null,
		{ timeout: 60_000 }
	);
	const firstRowText = await page
		.locator( '#jobs-table-body tr' )
		.first()
		.innerText();
	const view = page.locator( '#jobs-table-body .job-action-view' ).first();
	if ( await view.count() ) {
		await view.click();
		await page
			.locator( '#job-details-modal' )
			.waitFor( { state: 'visible', timeout: 30_000 } );
		await page
			.locator( '#job-details-modal .rsl-ie-modal-close' )
			.first()
			.click();
	}

	const restart = page
		.locator( '#jobs-table-body .job-action-restart' )
		.first();
	if ( ! ( await restart.count() ) ) {
		return {
			firstRowText,
			restartClicked: false,
			restartCompleted: false,
			finalUrl: page.url(),
		};
	}

	await restart.click();
	await page.waitForLoadState( 'domcontentloaded' ).catch( () => null );
	await handleBackupModal( page );
	const restartCompleted = await page
		.locator( '.rsl-ie-import-complete-card, .rsl-ie-export-complete-card' )
		.waitFor( { state: 'visible', timeout: 10 * 60_000 } )
		.then( () => true )
		.catch( () => false );

	return {
		firstRowText,
		restartClicked: true,
		restartCompleted,
		finalUrl: page.url(),
	};
}

async function main() {
	const cfg = env();
	const outDir = path.resolve(
		process.cwd(),
		'e2e/artifacts/menus-import-export',
		stamp()
	);
	fs.mkdirSync( outDir, { recursive: true } );

	for ( const site of [ cfg.source, cfg.target ] ) {
		ensurePluginActive( cfg, site, FREE_PLUGIN_FILE );
		ensurePluginActive( cfg, site, PRO_PLUGIN_FILE );
	}
	installAcfFixture( cfg, cfg.source );
	const sourceRaw = menuSnapshot( cfg, cfg.source );
	fs.writeFileSync(
		path.join( outDir, 'source-menus.json' ),
		JSON.stringify( sourceRaw, null, 2 )
	);
	restoreTargetDb( cfg );
	ensurePluginActive( cfg, cfg.target, FREE_PLUGIN_FILE );
	ensurePluginActive( cfg, cfg.target, PRO_PLUGIN_FILE );
	installAcfFixture( cfg, cfg.target );
	const targetDependencyExists = dependencyAvailability(
		cfg,
		cfg.target,
		sourceRaw
	);

	const browser = await chromium.launch( { headless: cfg.headless } );
	const context = await browser.newContext( { acceptDownloads: true } );
	const page = await context.newPage();
	try {
		const exportPath = await exportMenus( page, cfg, outDir );
		const mapping = await importMenus( page, cfg, exportPath );
		const source = normalizeSnapshot( sourceRaw, cfg.source.baseUrl );
		const targetRaw = menuSnapshot( cfg, cfg.target );
		fs.writeFileSync(
			path.join( outDir, 'target-menus.json' ),
			JSON.stringify( targetRaw, null, 2 )
		);
		const target = normalizeSnapshot( targetRaw, cfg.target.baseUrl );
		const browserMissing = await verifyMenusInBrowser(
			page,
			cfg,
			sourceRaw.menus
		);
		const jobs = await exerciseJobsPage( page, cfg );
		const targetAfterRestartRaw = menuSnapshot( cfg, cfg.target );
		fs.writeFileSync(
			path.join( outDir, 'target-menus-after-jobs-restart.json' ),
			JSON.stringify( targetAfterRestartRaw, null, 2 )
		);
		const targetAfterRestart = normalizeSnapshot(
			targetAfterRestartRaw,
			cfg.target.baseUrl
		);
		const targetBySlug = new Map(
			target.map( ( menu ) => [ menu.slug, menu ] )
		);
		const targetAfterRestartBySlug = new Map(
			targetAfterRestart.map( ( menu ) => [ menu.slug, menu ] )
		);
		const issues = [];
		for ( const menu of source ) {
			const actual = targetBySlug.get( menu.slug );
			if ( ! actual ) {
				issues.push( { menu: menu.name, field: 'missing_menu' } );
				continue;
			}
			for ( const field of [ 'name', 'description', 'locations' ] ) {
				if (
					JSON.stringify( menu[ field ] ) !==
					JSON.stringify( actual[ field ] )
				) {
					issues.push( {
						menu: menu.name,
						field,
						expected: menu[ field ],
						actual: actual[ field ],
					} );
				}
			}
			if (
				JSON.stringify( menu.acf || {} ) !==
				JSON.stringify( actual.acf || {} )
			) {
				issues.push( {
					menu: menu.name,
					field: 'term_acf',
					expected: menu.acf,
					actual: actual.acf,
				} );
			}
			if (
				! itemsMatchWithAllowedFallback(
					menu.items,
					actual.items,
					targetDependencyExists
				)
			) {
				issues.push( {
					menu: menu.name,
					field: 'items_or_item_acf',
					expected: menu.items,
					actual: actual.items,
				} );
			}
			const afterRestart = targetAfterRestartBySlug.get( menu.slug );
			if ( ! afterRestart ) {
				issues.push( {
					menu: menu.name,
					field: 'missing_after_jobs_restart',
				} );
			} else if (
				! itemsMatchWithAllowedFallback(
					menu.items,
					afterRestart.items,
					targetDependencyExists
				)
			) {
				issues.push( {
					menu: menu.name,
					field: 'items_after_jobs_restart',
					expected: menu.items,
					actual: afterRestart.items,
				} );
			}
		}
		for ( const name of browserMissing ) {
			issues.push( { menu: name, field: 'browser_missing' } );
		}
		if ( ! jobs.restartClicked || ! jobs.restartCompleted ) {
			issues.push( { field: 'jobs_restart', actual: jobs } );
		}
		const summary = {
			sourceCount: source.length,
			targetCount: target.length,
			exportPath,
			mapping,
			targetDependencyExists,
			browserMissing,
			jobs,
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
			throw new Error( `Menu import/export issues: ${ issues.length }` );
	} finally {
		await context.close().catch( () => null );
		await browser.close().catch( () => null );
	}
}

main().catch( ( error ) => {
	console.error( error.stack || error.message || error );
	process.exit( 1 );
} );
