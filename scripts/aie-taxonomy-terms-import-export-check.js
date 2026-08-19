/**
 * Manual E2E (Playwright): Taxonomy terms export/import check.
 *
 * Usage:
 *   AIE_HEADLESS=true PLAYWRIGHT_BROWSERS_PATH=./e2e/.playwright-browsers node scripts/aie-taxonomy-terms-import-export-check.js
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );
const { chromium } = require( 'playwright' );

const FREE_PLUGIN_FILE =
	'import-export-by-rockstarlab/import-export-by-rockstarlab.php';
const PRO_PLUGIN_FILE =
	'import-export-pro-by-rockstarlab/import-export-pro-by-rockstarlab.php';
const TAXONOMY = process.env.AIE_TAXONOMY || 'category';

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
	wp( cfg, cfg.target, [
		'user',
		'update',
		'admin',
		'--user_pass=admin',
		'--skip-email',
	] );
}

function installTermFixture( cfg, site, withValues ) {
	wp(
		cfg,
		site,
		[
			'eval',
			`
$taxonomy = '${ TAXONOMY }';
if (!taxonomy_exists($taxonomy)) { echo 'no-taxonomy'; return; }
if (function_exists('acf_update_field_group')) {
  foreach (['group_rsl_ie_taxonomy_e2e'] as $key) {
    $posts = get_posts(['post_type'=>'acf-field-group','post_status'=>'any','name'=>$key,'posts_per_page'=>-1]);
    foreach ($posts as $post) { wp_delete_post($post->ID, true); }
  }
  foreach (['field_rsl_ie_term_badge','field_rsl_ie_term_note'] as $key) {
    $posts = get_posts(['post_type'=>'acf-field','post_status'=>'any','name'=>$key,'posts_per_page'=>-1]);
    foreach ($posts as $post) { wp_delete_post($post->ID, true); }
  }
  if (function_exists('acf_import_field_group')) {
    acf_import_field_group([
      'key'=>'group_rsl_ie_taxonomy_e2e',
      'title'=>'RSL IE Taxonomy E2E',
      'fields'=>[
        ['key'=>'field_rsl_ie_term_badge','label'=>'Term Badge','name'=>'rsl_ie_term_badge','type'=>'text'],
        ['key'=>'field_rsl_ie_term_note','label'=>'Term Note','name'=>'rsl_ie_term_note','type'=>'textarea'],
      ],
      'location'=>[[['param'=>'taxonomy','operator'=>'==','value'=>$taxonomy]]],
      'active'=>true,
    ]);
  } else {
    acf_update_field_group(['key'=>'group_rsl_ie_taxonomy_e2e','title'=>'RSL IE Taxonomy E2E','location'=>[[['param'=>'taxonomy','operator'=>'==','value'=>$taxonomy]]],'active'=>true]);
    $g = acf_get_field_group('group_rsl_ie_taxonomy_e2e');
    acf_update_field(['key'=>'field_rsl_ie_term_badge','label'=>'Term Badge','name'=>'rsl_ie_term_badge','type'=>'text','parent'=>$g['ID']]);
    acf_update_field(['key'=>'field_rsl_ie_term_note','label'=>'Term Note','name'=>'rsl_ie_term_note','type'=>'textarea','parent'=>$g['ID']]);
  }
}
if (${ withValues ? 'true' : 'false' }) {
  $parent = term_exists('rsl-ie-tax-parent', $taxonomy);
  if (!$parent) { $parent = wp_insert_term('RSL IE Tax Parent', $taxonomy, ['slug'=>'rsl-ie-tax-parent','description'=>'RSL taxonomy parent fixture']); }
  $parent_id = is_array($parent) ? (int) $parent['term_id'] : (int) $parent;
  $child = term_exists('rsl-ie-tax-child', $taxonomy);
  if (!$child) { $child = wp_insert_term('RSL IE Tax Child', $taxonomy, ['slug'=>'rsl-ie-tax-child','description'=>'RSL taxonomy child fixture','parent'=>$parent_id]); }
  $child_id = is_array($child) ? (int) $child['term_id'] : (int) $child;
  foreach (get_terms(['taxonomy'=>$taxonomy,'hide_empty'=>false]) as $term) {
    update_term_meta($term->term_id, 'rsl_ie_term_plain_meta', 'plain-term-'.$term->slug);
    if (function_exists('update_field')) {
      update_field('field_rsl_ie_term_badge', 'badge-term-'.$term->slug, 'term_'.$term->term_id);
      update_field('field_rsl_ie_term_note', 'Note for term '.$term->slug, 'term_'.$term->term_id);
    }
  }
}
echo 'ok';
`,
		],
		{ timeout: 180_000 }
	);
}

function cancelProcessingTaxonomyJobs( cfg, site ) {
	wp(
		cfg,
		site,
		[
			'eval',
			`
global $wpdb;
$table = $wpdb->prefix . 'rsl_ie_jobs';
$wpdb->query($wpdb->prepare("UPDATE {$table} SET status = %s WHERE status = %s AND data_type = %s", 'cancelled', 'processing', 'taxonomy'));
echo 'ok';
`,
		],
		{ timeout: 60_000 }
	);
}

function getJobFilePath( cfg, site, jobId ) {
	return wpJson(
		cfg,
		site,
		`
global $wpdb;
$table = $wpdb->prefix . 'rsl_ie_jobs';
$job = $wpdb->get_row($wpdb->prepare("SELECT status, file_path FROM {$table} WHERE id = %d", ${ Number(
			jobId
		) }), ARRAY_A);
echo wp_json_encode($job ?: []);
`
	);
}

function termSnapshot( cfg, site, targetOnly = false ) {
	const sourceFilter = targetOnly
		? `
    'meta_query'=>[
      ['key'=>'_aie_source_term_id','compare'=>'EXISTS'],
    ],`
		: '';
	return wpJson(
		cfg,
		site,
		`
$taxonomy = '${ TAXONOMY }';
$terms = get_terms(['taxonomy'=>$taxonomy,'hide_empty'=>false,'orderby'=>'term_id','order'=>'ASC',${ sourceFilter }]);
$out = [];
foreach ($terms as $term) {
  $acf = function_exists('get_fields') ? get_fields('term_'.$term->term_id) : [];
  $meta_raw = get_term_meta($term->term_id);
  $meta = [];
  foreach ($meta_raw as $key => $values) {
    if (strpos($key, '_') === 0) { continue; }
    $meta[$key] = isset($values[0]) ? maybe_unserialize($values[0]) : '';
  }
  $source_id = get_term_meta($term->term_id, '_aie_source_term_id', true);
  $out[] = [
    'source_term_id' => $source_id !== '' ? (int) $source_id : (int) $term->term_id,
    'target_term_id' => (int) $term->term_id,
    'term_id' => (int) $term->term_id,
    'name' => (string) $term->name,
    'slug' => (string) $term->slug,
    'taxonomy' => (string) $term->taxonomy,
    'description' => (string) $term->description,
    'parent_source' => (int) get_term_meta($term->term_id, '_aie_source_parent_term_id', true),
    'parent_slug' => $term->parent ? (string) get_term((int)$term->parent, $term->taxonomy)->slug : (string) get_term_meta($term->term_id, '_aie_source_parent_slug', true),
    'count' => (int) $term->count,
    'meta' => $meta,
    'acf' => is_array($acf) ? $acf : [],
  ];
}
echo wp_json_encode($out, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`
	);
}

function bySourceId( rows ) {
	return new Map(
		rows.map( ( row ) => [ Number( row.source_term_id ), row ] )
	);
}

async function login( page, site ) {
	await page.goto( `${ site.baseUrl }/wp-admin/`, {
		waitUntil: 'domcontentloaded',
	} );
	if ( ! ( await page.locator( 'form#loginform' ).count() ) ) return;
	await page.fill( '#user_login', site.username );
	await page.fill( '#user_pass', site.password );
	await Promise.all( [
		page
			.waitForNavigation( { waitUntil: 'domcontentloaded' } )
			.catch( () => {} ),
		page.click( '#wp-submit' ),
	] );
	if ( page.url().includes( 'wp-login.php' ) )
		throw new Error( `Login failed for ${ site.baseUrl }` );
}

async function clickNext( page ) {
	await page
		.locator( '.rsl-ie-step.active .rsl-ie-next-step' )
		.click( { force: true, timeout: 10_000 } );
}

async function handleBackupModal( page ) {
	const overlay = page.locator( '.rsl-ie-backup-warning-overlay' );
	await overlay
		.waitFor( { state: 'attached', timeout: 2_000 } )
		.catch( () => {} );
	if ( await overlay.isVisible().catch( () => false ) ) {
		await page.locator( '#rsl-ie-backup-created' ).check( { force: true } );
		await page.locator( '.rsl-ie-backup-confirm' ).click( { force: true } );
		await overlay
			.waitFor( { state: 'hidden', timeout: 20_000 } )
			.catch( () => {} );
	}
}

async function selectTaxonomyType( page ) {
	const radio = page
		.locator( 'input[name="content_type"][value="taxonomy"]' )
		.first();
	await radio.waitFor( { state: 'attached', timeout: 60_000 } );
	await page.evaluate( () => {
		const input = document.querySelector(
			'input[name="content_type"][value="taxonomy"]'
		);
		if ( ! input ) {
			return;
		}
		input.checked = true;
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		input.closest( 'label' )?.classList.add( 'selected' );
	} );
	await page.waitForFunction(
		() =>
			document.querySelector(
				'input[name="content_type"][value="taxonomy"]'
			)?.checked === true
	);
}

async function chooseTaxonomy( page ) {
	const selector = page.locator( 'select.rsl-ie-taxonomy-selector' ).first();
	if ( ! ( await selector.count() ) ) {
		await page.locator( 'button.rsl-ie-add-filter' ).click();
		const row = page
			.locator( '#rsl-ie-filters-list .rsl-ie-filter-row' )
			.last();
		await row.waitFor( { state: 'visible', timeout: 60_000 } );
		await row
			.locator( 'select.rsl-ie-filter-field' )
			.selectOption( '_taxonomy' );
	}
	const taxSel = page.locator( 'select.rsl-ie-taxonomy-selector' ).first();
	await taxSel.waitFor( { state: 'visible', timeout: 60_000 } );
	await page.waitForFunction(
		( el ) => el && el.options && el.options.length > 1,
		await taxSel.elementHandle(),
		{ timeout: 60_000 }
	);
	await taxSel.selectOption( TAXONOMY );
}

async function exerciseExportFilters( page ) {
	for ( const field of [
		'name',
		'slug',
		'description',
		'parent',
		'count',
	] ) {
		await page.locator( 'button.rsl-ie-add-filter' ).click();
		const row = page
			.locator( '#rsl-ie-filters-list .rsl-ie-filter-row' )
			.last();
		await row.waitFor( { state: 'visible', timeout: 30_000 } );
		await row
			.locator( 'select.rsl-ie-filter-field' )
			.selectOption( field )
			.catch( () => {} );
		const condition = row.locator( 'select.rsl-ie-filter-condition' );
		if ( await condition.count() ) {
			const value = await condition.evaluate(
				( select ) =>
					Array.from( select.options ).find(
						( opt ) => opt.value && ! opt.disabled
					)?.value || ''
			);
			if ( value )
				await condition.selectOption( value ).catch( () => {} );
		}
		const input = row.locator( 'input.rsl-ie-filter-value' );
		if ( await input.count() )
			await input
				.fill( field === 'count' || field === 'parent' ? '0' : 'rsl' )
				.catch( () => {} );
		await row
			.locator( '.rsl-ie-remove-filter' )
			.click()
			.catch( () => {} );
	}
}

async function exportTerms( page, cfg, outDir ) {
	await login( page, cfg.source );
	await page.goto(
		`${ cfg.source.baseUrl }/wp-admin/admin.php?page=rsl-ie-export`,
		{ waitUntil: 'domcontentloaded' }
	);
	await page.waitForSelector( '#rsl-ie-export' );
	await selectTaxonomyType( page );
	await clickNext( page );
	await page.waitForSelector( '.rsl-ie-step-2.active' );
	await chooseTaxonomy( page );
	await exerciseExportFilters( page );
	await page
		.locator(
			'.rsl-ie-step-2.active .rsl-ie-refresh-count, .rsl-ie-refresh-count'
		)
		.first()
		.click()
		.catch( () => {} );
	await clickNext( page );
	await page.waitForSelector( '.rsl-ie-step-3.active' );
	await page
		.locator( '.rsl-ie-field-search' )
		.fill( 'term' )
		.catch( () => {} );
	await page
		.locator( '.rsl-ie-field-search' )
		.fill( '' )
		.catch( () => {} );
	await page.evaluate( () => {
		const step3 = window.rslIeExportModule?.step3Instance;
		const fields = [
			{ field: 'term_id', label: 'Term ID', type: 'number' },
			{ field: 'name', label: 'Name', type: 'string' },
			{ field: 'slug', label: 'Slug', type: 'string' },
			{ field: 'taxonomy', label: 'Taxonomy', type: 'string' },
			{ field: 'term_group', label: 'Term Group', type: 'number' },
			{
				field: 'term_taxonomy_id',
				label: 'Term Taxonomy ID',
				type: 'number',
			},
			{ field: 'description', label: 'Description', type: 'string' },
			{ field: 'parent', label: 'Parent Term ID', type: 'number' },
			{ field: 'parent_slug', label: 'Parent Slug', type: 'string' },
			{ field: 'count', label: 'Count', type: 'number' },
			{ field: 'term_meta', label: 'Term Meta', type: 'object' },
			{
				field: 'acf_rsl_ie_term_badge',
				label: 'Term Badge',
				type: 'string',
			},
			{
				field: 'acf_rsl_ie_term_note',
				label: 'Term Note',
				type: 'string',
			},
		];
		for ( const field of fields ) {
			if (
				step3 &&
				! step3.selectedFields.some(
					( selected ) => selected.field === field.field
				)
			) {
				step3.addFieldToCSV( field );
			}
		}
	} );
	await clickNext( page );
	await page.waitForSelector( '.rsl-ie-step-4.active' );
	for ( const delimiter of [ ';', 'tab', '|', 'custom', ',' ] ) {
		await page
			.locator( 'select[name="csv_delimiter"]' )
			.selectOption( delimiter )
			.catch( () => {} );
		if ( delimiter === 'custom' )
			await page
				.locator( 'input[name="csv_custom_delimiter"]' )
				.fill( '***' )
				.catch( () => {} );
	}
	await page
		.locator( 'select[name="csv_delimiter"]' )
		.selectOption( ',' )
		.catch( () => {} );
	await page
		.locator( 'input[name="include_headers"]' )
		.check( { force: true } )
		.catch( () => {} );
	await page
		.locator( '.rsl-ie-step-4.active input[name="items_per_iteration"]' )
		.fill( '100', { force: true } )
		.catch( () => {} );
	const startButton = page
		.locator(
			'.rsl-ie-step-4.active button.rsl-ie-start-export, .rsl-ie-step-4.active button.rsl-ie-export-btn'
		)
		.first();
	if ( ! ( await startButton.count() ) ) {
		const text = await page
			.locator( '.rsl-ie-step-4.active' )
			.innerText()
			.catch( () => page.locator( 'body' ).innerText() );
		throw new Error(
			`Start export button not found on active step 4. Visible text: ${ text.slice(
				0,
				500
			) }`
		);
	}
	await startButton.click( { force: true, timeout: 10_000 } );
	const jobId = await page
		.waitForFunction( () => window.rslIeExportModule?.jobId || 0, null, {
			timeout: 60_000,
		} )
		.then( ( handle ) => handle.jsonValue() );
	await page
		.locator( '.rsl-ie-export-complete-card, .rsl-ie-export-success' )
		.first()
		.waitFor( { state: 'visible', timeout: 10 * 60_000 } );
	const downloadPromise = page
		.waitForEvent( 'download', { timeout: 20_000 } )
		.catch( () => null );
	await page.locator( 'button.rsl-ie-download-file' ).click();
	const download = await downloadPromise;
	const csvPath = path.join( outDir, 'taxonomy-terms-export.csv' );
	if ( download ) {
		await download.saveAs( csvPath );
	} else {
		const job = getJobFilePath( cfg, cfg.source, jobId );
		if (
			job.status !== 'completed' ||
			! job.file_path ||
			! fs.existsSync( job.file_path )
		) {
			throw new Error(
				`Export completed in UI but download was unavailable for job ${ jobId }`
			);
		}
		fs.copyFileSync( job.file_path, csvPath );
	}
	const header = fs.readFileSync( csvPath, 'utf8' ).split( /\r?\n/ )[ 0 ];
	for ( const required of [
		'taxonomy',
		'term_meta',
		'acf_rsl_ie_term_badge',
		'acf_rsl_ie_term_note',
	] ) {
		if ( ! header.includes( required ) )
			throw new Error( `Export CSV missing ${ required }` );
	}
	return csvPath;
}

async function importTerms( page, cfg, csvPath ) {
	console.log( 'import: login target' );
	await login( page, cfg.target );
	console.log( 'import: open page' );
	await page.goto(
		`${ cfg.target.baseUrl }/wp-admin/admin.php?page=rsl-ie-import`,
		{ waitUntil: 'domcontentloaded' }
	);
	await page.waitForSelector( '#rsl-ie-import' );
	console.log( 'import: select taxonomy' );
	await selectTaxonomyType( page );
	console.log( 'import: next step 1' );
	await clickNext( page );
	await handleBackupModal( page );
	if ( ! ( await page.locator( '.rsl-ie-step-2.active' ).count() ) ) {
		console.log( 'import: retry next step 1' );
		await clickNext( page );
		await handleBackupModal( page );
	}
	console.log( 'import: wait step 2' );
	await page.waitForSelector( '.rsl-ie-step-2.active' );
	console.log( 'import: step 2 active' );
	for ( const delimiter of [ ';', 'tab', '|', 'custom', ',' ] ) {
		await page
			.locator( '#csv_delimiter' )
			.selectOption( delimiter, { timeout: 2_000 } )
			.catch( () => {} );
	}
	await page
		.locator( '#csv_delimiter' )
		.selectOption( ',', { timeout: 2_000 } )
		.catch( () => {} );
	console.log( 'import: check upload area' );
	if (
		! ( await page
			.locator( '.rsl-ie-step-2.active #rsl-ie-upload-area' )
			.count() )
	) {
		const text = await page
			.locator( '.rsl-ie-step-2.active' )
			.innerText()
			.catch( () => page.locator( 'body' ).innerText() );
		throw new Error(
			`Import upload area not found on active step 2. Visible text: ${ text.slice(
				0,
				800
			) }`
		);
	}
	const fileInput = page
		.locator( '.rsl-ie-step-2.active input[type="file"]' )
		.first();
	if ( ! ( await fileInput.count() ) ) {
		const text = await page
			.locator( '.rsl-ie-step-2.active' )
			.innerText()
			.catch( () => page.locator( 'body' ).innerText() );
		throw new Error(
			`Import file input not found on active step 2. Visible text: ${ text.slice(
				0,
				500
			) }`
		);
	}
	await fileInput.setInputFiles( csvPath );
	await page.waitForFunction(
		() =>
			! document.querySelector(
				'.rsl-ie-step-2.active .rsl-ie-next-step'
			)?.disabled
	);
	await clickNext( page );
	await page.waitForSelector( '.rsl-ie-step-3.active' );
	await clickNext( page );
	await page.waitForSelector( '.rsl-ie-step-4.active' );
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
		rows: Array.from(
			document.querySelectorAll( '.rsl-ie-mapping-row' )
		).map( ( row ) => {
			const sourceIndex = row.dataset.sourceIndex;
			const source = document.querySelector(
				`.rsl-ie-field-card[data-source-index="${ sourceIndex }"]`
			);
			return {
				source_index: sourceIndex,
				source_field: source?.dataset.sourceField || '',
				target_field: row.dataset.targetField || '',
			};
		} ),
	} ) );
	await clickNext( page );
	await page.waitForSelector( '.rsl-ie-step-5.active' );
	await page
		.locator( '#rsl-ie-unique-field' )
		.selectOption( 'term_id' )
		.catch( () => null );
	for ( const value of [ 'skip', 'update' ] ) {
		await page
			.locator( `input[name="if_exists"][value="${ value }"]` )
			.check( { force: true } )
			.catch( () => null );
	}
	await page
		.locator( 'input[name="if_exists"][value="update"]' )
		.check( { force: true } )
		.catch( () => null );
	for ( const value of [ 'skip', 'create' ] ) {
		await page
			.locator( `input[name="if_not_exists"][value="${ value }"]` )
			.check( { force: true } )
			.catch( () => null );
	}
	await page
		.locator( 'input[name="if_not_exists"][value="create"]' )
		.check( { force: true } )
		.catch( () => null );
	await page
		.locator( 'input[name="batch_size"]' )
		.fill( '5' )
		.catch( () => null );
	await page.locator( '.rsl-ie-start-import' ).click();
	await handleBackupModal( page );
	await page
		.locator( '.rsl-ie-import-complete-card' )
		.waitFor( { state: 'visible', timeout: 10 * 60_000 } );
	return mapping;
}

async function verifyTermsInBrowser( page, cfg, sourceRows, targetRows ) {
	await login( page, cfg.target );
	const targetBySource = bySourceId( targetRows );
	const missing = [];
	for ( const row of sourceRows ) {
		const target = targetBySource.get( Number( row.source_term_id ) );
		if ( ! target?.target_term_id ) {
			missing.push( row.source_term_id );
			continue;
		}
		try {
			await page.goto(
				`${ cfg.target.baseUrl }/wp-admin/term.php?taxonomy=${ TAXONOMY }&tag_ID=${ target.target_term_id }&post_type=post`,
				{ waitUntil: 'domcontentloaded', timeout: 20_000 }
			);
			await page.waitForSelector( 'body', { timeout: 10_000 } );
		} catch {
			missing.push( row.source_term_id );
			continue;
		}
		const pageText = await page.evaluate( () => {
			const values = Array.from(
				document.querySelectorAll( 'input, textarea, select' )
			)
				.map( ( el ) => el.value || el.textContent || '' )
				.filter( Boolean );
			return [ document.body.innerText, ...values ].join( '\n' );
		} );
		const expected = [
			row.name,
			row.slug,
			row.description,
			row.acf.rsl_ie_term_badge,
			row.acf.rsl_ie_term_note,
		].filter( Boolean );
		if (
			expected.some( ( value ) => ! pageText.includes( String( value ) ) )
		) {
			missing.push( row.source_term_id );
		}
	}
	return missing;
}

async function main() {
	const cfg = env();
	const outDir = path.resolve(
		process.cwd(),
		'e2e/artifacts/taxonomy-terms-import-export',
		stamp()
	);
	fs.mkdirSync( outDir, { recursive: true } );

	for ( const site of [ cfg.source, cfg.target ] ) {
		ensurePluginActive( cfg, site, FREE_PLUGIN_FILE );
		ensurePluginActive( cfg, site, PRO_PLUGIN_FILE );
	}
	cancelProcessingTaxonomyJobs( cfg, cfg.source );
	installTermFixture( cfg, cfg.source, true );
	const sourceRaw = termSnapshot( cfg, cfg.source );
	fs.writeFileSync(
		path.join( outDir, 'source-terms.json' ),
		JSON.stringify( sourceRaw, null, 2 )
	);

	restoreTargetDb( cfg );
	ensurePluginActive( cfg, cfg.target, FREE_PLUGIN_FILE );
	ensurePluginActive( cfg, cfg.target, PRO_PLUGIN_FILE );
	installTermFixture( cfg, cfg.target, false );

	const browser = await chromium.launch( { headless: cfg.headless } );
	const context = await browser.newContext( { acceptDownloads: true } );
	const page = await context.newPage();
	try {
		const exportPath =
			process.env.AIE_EXPORT_CSV ||
			( await exportTerms( page, cfg, outDir ) );
		if ( process.env.AIE_EXPORT_CSV ) {
			fs.copyFileSync(
				process.env.AIE_EXPORT_CSV,
				path.join( outDir, 'taxonomy-terms-export.csv' )
			);
		}
		const mapping = await importTerms( page, cfg, exportPath );
		const targetRaw = termSnapshot( cfg, cfg.target, true );
		fs.writeFileSync(
			path.join( outDir, 'target-terms.json' ),
			JSON.stringify( targetRaw, null, 2 )
		);
		const browserMissing = await verifyTermsInBrowser(
			page,
			cfg,
			sourceRaw,
			targetRaw
		);
		const targetBySource = bySourceId( targetRaw );
		const issues = [];
		for ( const expected of sourceRaw ) {
			const actual = targetBySource.get(
				Number( expected.source_term_id )
			);
			if ( ! actual ) {
				issues.push( {
					term: expected.source_term_id,
					field: 'missing_term',
				} );
				continue;
			}
			for ( const field of [
				'name',
				'slug',
				'taxonomy',
				'description',
				'parent_slug',
			] ) {
				if (
					String( actual[ field ] ?? '' ) !==
					String( expected[ field ] ?? '' )
				) {
					issues.push( {
						term: expected.source_term_id,
						field,
						expected: expected[ field ],
						actual: actual[ field ],
					} );
				}
			}
			const expectedMeta = {
				rsl_ie_term_plain_meta: expected.meta.rsl_ie_term_plain_meta,
				rsl_ie_term_badge: expected.acf.rsl_ie_term_badge,
				rsl_ie_term_note: expected.acf.rsl_ie_term_note,
			};
			const actualMeta = {
				rsl_ie_term_plain_meta: actual.meta.rsl_ie_term_plain_meta,
				rsl_ie_term_badge: actual.acf.rsl_ie_term_badge,
				rsl_ie_term_note: actual.acf.rsl_ie_term_note,
			};
			if (
				JSON.stringify( actualMeta ) !== JSON.stringify( expectedMeta )
			) {
				issues.push( {
					term: expected.source_term_id,
					field: 'meta_acf',
					expected: expectedMeta,
					actual: actualMeta,
				} );
			}
		}
		for ( const id of browserMissing ) {
			issues.push( { term: id, field: 'browser_missing' } );
		}
		const summary = {
			taxonomy: TAXONOMY,
			sourceCount: sourceRaw.length,
			targetImportedCount: targetRaw.length,
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
			throw new Error(
				`Taxonomy terms import/export issues: ${ issues.length }`
			);
	} finally {
		await context.close().catch( () => {} );
		await browser.close().catch( () => {} );
	}
}

main().catch( ( error ) => {
	console.error( error && error.stack ? error.stack : error );
	process.exitCode = 1;
} );
