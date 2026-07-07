/**
 * Manual E2E (Playwright): Comments export/import check.
 *
 * Usage:
 *   AIE_HEADLESS=true PLAYWRIGHT_BROWSERS_PATH=./e2e/.playwright-browsers node scripts/aie-comments-import-export-check.js
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
foreach (['group_rsl_ie_comment_e2e'] as $key) {
  $posts = get_posts(['post_type'=>'acf-field-group','post_status'=>'any','name'=>$key,'posts_per_page'=>-1]);
  foreach ($posts as $post) { wp_delete_post($post->ID, true); }
}
foreach (['field_rsl_ie_comment_badge','field_rsl_ie_comment_note'] as $key) {
  $posts = get_posts(['post_type'=>'acf-field','post_status'=>'any','name'=>$key,'posts_per_page'=>-1]);
  foreach ($posts as $post) { wp_delete_post($post->ID, true); }
}
if (function_exists('acf_import_field_group')) {
  acf_import_field_group([
    'key'=>'group_rsl_ie_comment_e2e',
    'title'=>'RSL IE Comment E2E',
    'fields'=>[
      ['key'=>'field_rsl_ie_comment_badge','label'=>'Comment Badge','name'=>'rsl_ie_comment_badge','type'=>'text'],
      ['key'=>'field_rsl_ie_comment_note','label'=>'Comment Note','name'=>'rsl_ie_comment_note','type'=>'textarea'],
    ],
    'location'=>[[['param'=>'comment','operator'=>'==','value'=>'all']]],
    'position'=>'normal','style'=>'default','active'=>true,
  ]);
}
if (${ withValues ? 'true' : 'false' }) {
  $comments = get_comments(['status'=>'all','number'=>0,'orderby'=>'comment_ID','order'=>'ASC']);
  foreach ($comments as $comment) {
    update_comment_meta($comment->comment_ID, 'rsl_ie_comment_plain_meta', 'plain-comment-'.$comment->comment_ID);
    update_field('field_rsl_ie_comment_badge', 'badge-comment-'.$comment->comment_ID, 'comment_'.$comment->comment_ID);
    update_field('field_rsl_ie_comment_note', 'Note for comment '.$comment->comment_ID, 'comment_'.$comment->comment_ID);
  }
}
echo 'ok';
`,
		],
		{ timeout: 180_000 }
	);
}

function commentSnapshot( cfg, site, mode = 'source' ) {
	const sourceFilter =
		mode === 'target'
			? "'meta_key'=>'_aie_source_comment_id','meta_compare'=>'EXISTS',"
			: '';
	return wpJson(
		cfg,
		site,
		`
$comments = get_comments(['status'=>'all','number'=>0,'orderby'=>'comment_ID','order'=>'ASC',${ sourceFilter }]);
$out = [];
foreach ($comments as $c) {
  $acf = function_exists('get_fields') ? get_fields('comment_'.$c->comment_ID) : [];
  $meta_raw = get_comment_meta($c->comment_ID);
  $meta = [];
  foreach ($meta_raw as $key => $values) {
    if (strpos($key, '_') === 0) { continue; }
    $meta[$key] = isset($values[0]) ? maybe_unserialize($values[0]) : '';
  }
	  $source_id = get_comment_meta($c->comment_ID, '_aie_source_comment_id', true);
	  $out[] = [
	    'source_comment_ID' => $source_id !== '' ? (int) $source_id : (int) $c->comment_ID,
	    'target_comment_ID' => (int) $c->comment_ID,
	    'comment_post_ID' => (int) $c->comment_post_ID,
    'post_title' => (string) get_the_title((int) $c->comment_post_ID),
    'comment_author' => (string) $c->comment_author,
    'comment_author_email' => (string) $c->comment_author_email,
    'comment_author_url' => (string) $c->comment_author_url,
    'comment_author_IP' => (string) $c->comment_author_IP,
    'comment_date' => (string) $c->comment_date,
    'comment_date_gmt' => (string) $c->comment_date_gmt,
    'comment_content' => (string) $c->comment_content,
    'comment_karma' => (int) $c->comment_karma,
    'comment_approved' => (string) $c->comment_approved,
    'comment_agent' => (string) $c->comment_agent,
    'comment_type' => (string) $c->comment_type,
    'comment_parent_source' => (int) get_comment_meta($c->comment_ID, '_aie_source_comment_parent_id', true),
    'user_email' => $c->user_id ? (string) (get_userdata((int) $c->user_id)->user_email ?? '') : '',
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
		rows.map( ( row ) => [ Number( row.source_comment_ID ), row ] )
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
		page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
		page.click( '#wp-submit' ),
	] );
}

async function selectCommentType( page ) {
	await page.locator( '#rsl-ie-content-type-search' ).fill( 'none' );
	await page.locator( '#rsl-ie-content-type-search' ).fill( 'comment' );
	await page.evaluate( () => {
		const input = document.querySelector(
			'input[name="content_type"][value="comment"]'
		);
		if ( ! input ) throw new Error( 'Comment content type not found' );
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

async function exerciseFilterUi( page, cfg ) {
	await login( page, cfg.source );
	await page.goto(
		`${ cfg.source.baseUrl }/wp-admin/admin.php?page=rsl-ie-export`,
		{ waitUntil: 'domcontentloaded' }
	);
	await page.waitForSelector( '#rsl-ie-export' );
	await selectCommentType( page );
	await clickNext( page );
	await page.waitForSelector( '.rsl-ie-step-2.active' );
	await page
		.locator( '.rsl-ie-refresh-count' )
		.click()
		.catch( () => null );
	await page.locator( '.rsl-ie-add-filter' ).click();
	await page.waitForTimeout( 500 );
	await page
		.locator( '.rsl-ie-remove-filter' )
		.last()
		.click()
		.catch( () => null );
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
					'.rsl-ie-step-3.active .rsl-ie-field-item[data-field="acf_rsl_ie_comment_badge"]'
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
				( field ) => field.field === 'comment_meta'
			)
		) {
			step3.addFieldToCSV( {
				field: 'comment_meta',
				label: 'Comment meta',
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

async function exportComments( page, cfg, outDir ) {
	await login( page, cfg.source );
	await page.goto(
		`${ cfg.source.baseUrl }/wp-admin/admin.php?page=rsl-ie-export`,
		{ waitUntil: 'domcontentloaded' }
	);
	await page.waitForSelector( '#rsl-ie-export' );
	await selectCommentType( page );
	await clickNext( page );
	await page.waitForSelector( '.rsl-ie-step-2.active' );
	await clickNext( page );
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
			batch.value = '3';
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
	const exportPath = path.join( outDir, 'comments-export.csv' );
	await download.saveAs( exportPath );
	const csv = fs.readFileSync( exportPath, 'utf8' );
	const header = csv.split( /\r?\n/ )[ 0 ] || '';
	if ( csv.split( /\r?\n/ ).filter( Boolean ).length <= 1 ) {
		throw new Error( 'Comments export has no data rows' );
	}
	for ( const field of [
		'comment_ID',
		'comment_post_ID',
		'post_permalink',
		'comment_meta',
		'acf_rsl_ie_comment_badge',
	] ) {
		if ( ! header.includes( field ) )
			throw new Error( `Comments export is missing ${ field }` );
	}
	return exportPath;
}

async function importComments( page, cfg, csvPath ) {
	await login( page, cfg.target );
	await page.goto(
		`${ cfg.target.baseUrl }/wp-admin/admin.php?page=rsl-ie-import`,
		{ waitUntil: 'domcontentloaded' }
	);
	await page.waitForSelector( '#rsl-ie-import' );
	await selectCommentType( page );
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
				'#rsl-ie-target-fields .rsl-ie-target-field[data-target-field="acf_rsl_ie_comment_badge"]'
			),
		null,
		{ timeout: 60_000 }
	);
	await page.locator( '.rsl-ie-clear-map' ).click();
	await page.locator( '.rsl-ie-auto-map' ).click();
	await page.evaluate( () => {
		const mod = window.rslIeImportModule || window.ImportModule;
		const manualFields = [
			'comment_meta',
			'post_permalink',
			'post_type',
			'post_slug',
			'comment_agent',
		];
		for ( const field of manualFields ) {
			const source = document.querySelector(
				`.rsl-ie-field-card[data-source-field="${ field }"]`
			);
			const target = document.querySelector(
				`.rsl-ie-target-field[data-target-field="${ field }"]`
			);
			if (
				! source ||
				! target ||
				target.classList.contains( 'has-mapping' )
			) {
				continue;
			}
			if (
				mod &&
				typeof mod.createMapping === 'function' &&
				window.jQuery
			) {
				mod.createMapping(
					field,
					Number( source.dataset.sourceIndex ),
					field,
					target.dataset.fieldType || 'string',
					window.jQuery( target )
				);
			}
		}
	} );
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
		.selectOption( 'comment_ID' )
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
	await page.evaluate( () => {
		for ( const name of [
			'validate_post_exists',
			'create_missing_posts',
		] ) {
			const el = document.querySelector( `input[name="${ name }"]` );
			if ( el ) {
				el.checked = true;
				el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		}
		const skip = document.querySelector(
			'input[name="skip_missing_posts"]'
		);
		if ( skip ) {
			skip.checked = false;
			skip.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	} );
	await page.locator( '.rsl-ie-start-import' ).click();
	await handleBackupModal( page );
	await page
		.locator( '.rsl-ie-import-complete-card' )
		.waitFor( { state: 'visible', timeout: 10 * 60_000 } );
	return mapping;
}

async function verifyCommentsInBrowser( page, cfg, sourceRows, targetRows ) {
	await login( page, cfg.target );
	const targetBySource = bySourceId( targetRows );
	const missing = [];
	for ( const row of sourceRows.slice( 0, 10 ) ) {
		const target = targetBySource.get( Number( row.source_comment_ID ) );
		if ( ! target?.target_comment_ID ) {
			missing.push( row.source_comment_ID );
			continue;
		}
		await page.goto(
			`${ cfg.target.baseUrl }/wp-admin/comment.php?action=editcomment&c=${ target.target_comment_ID }`,
			{ waitUntil: 'domcontentloaded' }
		);
		await page.waitForSelector( '#namediv, #postdiv, body' );
		const pageText = await page.evaluate( () => {
			const values = Array.from(
				document.querySelectorAll( 'input, textarea, select' )
			)
				.map( ( el ) => el.value || el.textContent || '' )
				.filter( Boolean );
			return [ document.body.innerText, ...values ].join( '\n' );
		} );
		const expected = [
			row.comment_author,
			row.comment_author_email,
			row.comment_content.slice( 0, 80 ),
		].filter( Boolean );
		if (
			expected.some( ( value ) => ! pageText.includes( String( value ) ) )
		) {
			missing.push( row.source_comment_ID );
		}
	}
	return missing;
}

async function main() {
	const cfg = env();
	const outDir = path.resolve(
		process.cwd(),
		'e2e/artifacts/comments-import-export',
		stamp()
	);
	fs.mkdirSync( outDir, { recursive: true } );

	for ( const site of [ cfg.source, cfg.target ] ) {
		ensurePluginActive( cfg, site, FREE_PLUGIN_FILE );
		ensurePluginActive( cfg, site, PRO_PLUGIN_FILE );
	}
	installAcfFixture( cfg, cfg.source, true );
	const sourceRaw = commentSnapshot( cfg, cfg.source );
	fs.writeFileSync(
		path.join( outDir, 'source-comments.json' ),
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
		const exportPath = await exportComments( page, cfg, outDir );
		const mapping = await importComments( page, cfg, exportPath );
		const targetRaw = commentSnapshot( cfg, cfg.target, 'target' );
		fs.writeFileSync(
			path.join( outDir, 'target-comments.json' ),
			JSON.stringify( targetRaw, null, 2 )
		);
		const browserMissing = await verifyCommentsInBrowser(
			page,
			cfg,
			sourceRaw,
			targetRaw
		);
		const targetBySource = bySourceId( targetRaw );
		const issues = [];
		for ( const expected of sourceRaw ) {
			const actual = targetBySource.get(
				Number( expected.source_comment_ID )
			);
			if ( ! actual ) {
				issues.push( {
					comment: expected.source_comment_ID,
					field: 'missing_comment',
				} );
				continue;
			}
			for ( const field of [
				'comment_author',
				'comment_author_email',
				'comment_author_url',
				'comment_author_IP',
				'comment_date',
				'comment_date_gmt',
				'comment_content',
				'comment_karma',
				'comment_approved',
				'comment_agent',
				'comment_type',
				'meta',
				'acf',
			] ) {
				if (
					JSON.stringify( expected[ field ] ) !==
					JSON.stringify( actual[ field ] )
				) {
					issues.push( {
						comment: expected.source_comment_ID,
						field,
						expected: expected[ field ],
						actual: actual[ field ],
					} );
				}
			}
		}
		for ( const id of browserMissing ) {
			issues.push( { comment: id, field: 'browser_missing' } );
		}
		const summary = {
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
				`Comments import/export issues: ${ issues.length }`
			);
	} finally {
		await context.close().catch( () => null );
		await browser.close().catch( () => null );
	}
}

main().catch( ( error ) => {
	console.error( error.stack || error.message || error );
	process.exit( 1 );
} );
