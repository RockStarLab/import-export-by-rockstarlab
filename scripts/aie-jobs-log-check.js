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
		'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/mysql-8.4.0/bin/darwin-arm64/bin/mysql',
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

	const sql = fs
		.readFileSync( db2Sql, 'utf8' )
		.replace( /\bINSERT INTO\b/g, 'INSERT IGNORE INTO' );
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
	rmrf( path.join( uploadsBase, 'rsl-ie-uploads', 'jobs-log-imports' ) );
	// Our media sync test folder.
	rmrf( path.join( uploadsBase, 'test-jobs-log-media' ) );
}

function cleanupTempFilesSafe( env ) {
	try {
		cleanupTempFiles( env );
	} catch ( err ) {
		console.warn(
			`Warning: temp cleanup skipped: ${
				err && err.message ? err.message : String( err )
			}`
		);
	}
}

function resetCaseState( env ) {
	importDb2Sql( env );
	cleanupTempFilesSafe( env );
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

function csvEscape( value ) {
	const s = value === null || value === undefined ? '' : String( value );
	if ( /[",\r\n]/.test( s ) ) return `"${ s.replace( /"/g, '""' ) }"`;
	return s;
}

function makeUiMapping( fields ) {
	return fields.map( ( field ) => ( {
		source_field: field,
		target_field: field,
	} ) );
}

function writeCsvRows( env, basename, fields, rows ) {
	const uploadsBase = getUploadsBaseDir( env );
	const dir = path.join( uploadsBase, 'rsl-ie-uploads', 'jobs-log-imports' );
	fs.mkdirSync( dir, { recursive: true } );
	const csvPath = path.join( dir, basename );
	const csv = [
		fields.map( csvEscape ).join( ',' ),
		...rows.map( ( row ) =>
			fields.map( ( f ) => csvEscape( row[ f ] ) ).join( ',' )
		),
	].join( '\n' );
	fs.writeFileSync( csvPath, csv );
	return csvPath;
}

function writeTinyPng( env, basename = 'jobs-log-media-source.png' ) {
	const uploadsBase = getUploadsBaseDir( env );
	const dir = path.join( uploadsBase, 'rsl-ie-uploads', 'jobs-log-imports' );
	fs.mkdirSync( dir, { recursive: true } );
	const filePath = path.join( dir, basename );
	fs.writeFileSync(
		filePath,
		Buffer.from(
			'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMB/auYfWQAAAAASUVORK5CYII=',
			'base64'
		)
	);
	return filePath;
}

function ensureDeepFixture( env, type ) {
	const php = `
$type = ${ JSON.stringify( String( type ) ) };
$result = [ 'type' => $type ];
$stamp = 'jobslog' . wp_rand(1000, 9999);

$ensure_post = function($post_type, $title, $status = 'publish') {
  $ids = get_posts([ 'post_type' => $post_type, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ]);
  $id = ! empty($ids) ? (int) $ids[0] : 0;
  if ( ! $id ) {
    $id = wp_insert_post([ 'post_type' => $post_type, 'post_status' => $status, 'post_title' => $title, 'post_content' => $title ]);
  } else {
    wp_update_post([ 'ID' => $id, 'post_title' => $title, 'post_content' => $title, 'post_excerpt' => $title ]);
  }
  return (int) $id;
};

switch ( $type ) {
  case 'post':
    $result['id'] = $ensure_post('post', 'jobs log post fixture');
    break;
  case 'page':
    $result['id'] = $ensure_post('page', 'jobs log page fixture');
    break;
  case 'custom_post_types':
    if ( ! post_type_exists('portfolio') ) { $result['skipped'] = 'portfolio post type missing'; break; }
    $result['post_type'] = 'portfolio';
    $result['id'] = $ensure_post('portfolio', 'jobs log portfolio fixture');
    break;
  case 'media':
    $ids = get_posts([ 'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ]);
    $id = ! empty($ids) ? (int) $ids[0] : 0;
    if ( $id ) { wp_update_post([ 'ID' => $id, 'post_title' => 'jobs log media fixture', 'post_excerpt' => 'jobs log media caption' ] ); }
    $result['id'] = $id;
    break;
  case 'menu':
    $menus = wp_get_nav_menus();
    $menu_id = ! empty($menus) ? (int) $menus[0]->term_id : 0;
    if ( ! $menu_id ) { $menu_id = (int) wp_create_nav_menu('Jobs Log Menu'); }
    if ( $menu_id && ! is_wp_error($menu_id) ) {
      wp_update_term($menu_id, 'nav_menu', [ 'description' => 'jobs log menu fixture' ]);
      $items = wp_get_nav_menu_items($menu_id);
      if ( empty($items) ) {
        wp_update_nav_menu_item($menu_id, 0, [
          'menu-item-title' => 'Jobs Log Menu Item',
          'menu-item-url' => home_url('/jobs-log-menu-fixture/'),
          'menu-item-status' => 'publish',
        ]);
      }
    }
    $result['id'] = (int) $menu_id;
    break;
  case 'user':
    $users = get_users([ 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC' ]);
    $id = ! empty($users) ? (int) $users[0]->ID : 0;
    if ( $id ) { wp_update_user([ 'ID' => $id, 'first_name' => 'jobsloguser', 'display_name' => 'jobs log user fixture' ]); }
    $result['id'] = $id;
    break;
  case 'comment':
    $post_id = $ensure_post('post', 'jobs log comment parent');
    $comments = get_comments([ 'post_id' => $post_id, 'number' => 1, 'status' => 'all', 'fields' => 'ids' ]);
    $comment_id = ! empty($comments) ? (int) $comments[0] : 0;
    if ( ! $comment_id ) {
      $comment_id = (int) wp_insert_comment([
        'comment_post_ID' => $post_id,
        'comment_author' => 'Jobs Log',
        'comment_author_email' => 'jobs-log@example.test',
        'comment_content' => 'jobs log comment fixture',
        'comment_approved' => 1,
      ]);
    } else {
      wp_update_comment([ 'comment_ID' => $comment_id, 'comment_content' => 'jobs log comment fixture' ]);
    }
    $result['id'] = $comment_id;
    $result['post_id'] = $post_id;
    break;
  case 'taxonomy':
    $taxonomy = 'category';
    $term = get_term_by('slug', 'jobs-log-category', $taxonomy);
    if ( ! $term ) {
      $created = wp_insert_term('Jobs Log Category', $taxonomy, [ 'slug' => 'jobs-log-category', 'description' => 'jobs log taxonomy fixture' ]);
      $term_id = is_wp_error($created) ? 0 : (int) $created['term_id'];
    } else {
      $term_id = (int) $term->term_id;
      wp_update_term($term_id, $taxonomy, [ 'description' => 'jobs log taxonomy fixture' ]);
    }
    $result['taxonomy'] = $taxonomy;
    $result['id'] = $term_id;
    break;
  case 'woo_product':
    if ( ! class_exists('WooCommerce') || ! class_exists('WC_Product_Simple') ) { $result['skipped'] = 'woocommerce missing'; break; }
    $products = wc_get_products([ 'limit' => 1, 'return' => 'ids' ]);
    $id = ! empty($products) ? (int) $products[0] : 0;
    if ( ! $id ) {
      $p = new WC_Product_Simple();
      $p->set_name('jobs log product fixture');
      $p->set_regular_price('12');
      $p->set_status('publish');
      $id = (int) $p->save();
    } else {
      $p = wc_get_product($id);
      if ( $p ) { $p->set_name('jobs log product fixture'); $p->set_short_description('jobs log product excerpt'); $p->save(); }
    }
    $result['id'] = $id;
    break;
  case 'woo_order':
    if ( ! function_exists('wc_get_orders') || ! function_exists('wc_create_order') ) { $result['skipped'] = 'woocommerce missing'; break; }
    $orders = wc_get_orders([ 'limit' => 1, 'return' => 'ids' ]);
    $id = ! empty($orders) ? (int) $orders[0] : 0;
    if ( ! $id ) {
      $product_id = 0;
      $products = wc_get_products([ 'limit' => 1, 'return' => 'ids' ]);
      if ( ! empty($products) ) { $product_id = (int) $products[0]; }
      if ( ! $product_id ) {
        $p = new WC_Product_Simple();
        $p->set_name('jobs log order product');
        $p->set_regular_price('10');
        $product_id = (int) $p->save();
      }
      $order = wc_create_order();
      $order->add_product(wc_get_product($product_id), 1);
      $order->set_customer_note('jobs log order fixture');
      $order->calculate_totals();
      $id = (int) $order->save();
    } else {
      $order = wc_get_order($id);
      if ( $order ) { $order->set_customer_note('jobs log order fixture'); $order->save(); }
    }
    $result['id'] = $id;
    break;
  case 'woo_coupon':
    $ids = get_posts([ 'post_type' => 'shop_coupon', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ]);
    $id = ! empty($ids) ? (int) $ids[0] : 0;
    if ( ! $id ) {
      $id = wp_insert_post([ 'post_type' => 'shop_coupon', 'post_status' => 'publish', 'post_title' => 'jobslogcoupon', 'post_excerpt' => 'jobs log coupon fixture' ]);
    } else {
      wp_update_post([ 'ID' => $id, 'post_title' => 'jobslogcoupon', 'post_excerpt' => 'jobs log coupon fixture' ]);
    }
    update_post_meta($id, 'coupon_amount', '5');
    update_post_meta($id, 'discount_type', 'fixed_cart');
    $result['id'] = (int) $id;
    break;
  case 'woo_attribute':
    if ( ! function_exists('wc_get_attribute_taxonomies') || ! function_exists('wc_create_attribute') ) { $result['skipped'] = 'woocommerce attributes unavailable'; break; }
    $attrs = wc_get_attribute_taxonomies();
    $id = 0;
    if ( is_array($attrs) && ! empty($attrs) ) { $first = reset($attrs); $id = (int) ($first->attribute_id ?? 0); }
    if ( ! $id ) {
      $id = (int) wc_create_attribute([ 'name' => 'Jobs Log Color', 'slug' => 'jobslogcolor', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ]);
      if ( function_exists('wc_delete_attribute_transients') ) { wc_delete_attribute_transients(); }
    }
    $result['id'] = $id;
    break;
  case 'database_table':
    global $wpdb;
    $table = $wpdb->prefix . 'rsl_ie_jobs_log_fixture';
    $q = chr(96);
    $wpdb->query('CREATE TABLE IF NOT EXISTS ' . $q . $table . $q . ' (fixture_key varchar(64) NOT NULL, fixture_value text NOT NULL, PRIMARY KEY (fixture_key))');
    $wpdb->replace($table, [ 'fixture_key' => 'jobs', 'fixture_value' => 'jobs log database fixture' ], [ '%s', '%s' ]);
    $result['table'] = $table;
    $result['id'] = 'jobs';
    break;
}

echo wp_json_encode($result, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	return wpEvalJson( env, env.target.wpPath, php ) || { type };
}

function getExportJobConfig( env, exportType ) {
	const fixture = ensureDeepFixture( env, exportType );
	if ( fixture.skipped ) {
		throw new Error(
			`Fixture for ${ exportType } skipped: ${ fixture.skipped }`
		);
	}

	const config = {
		exportType,
		tableName: '',
		options: {},
		dynamicFilters: [],
		fields: [],
		fixture,
	};

	if ( exportType === 'custom_post_types' ) {
		config.options.post_type = fixture.post_type || 'portfolio';
		config.dynamicFilters.push( {
			field: 'post_type',
			condition: 'equals',
			value: fixture.post_type || 'portfolio',
		} );
	}

	if ( exportType === 'taxonomy' ) {
		config.options.taxonomy = fixture.taxonomy || 'category';
		config.dynamicFilters.push( {
			field: 'taxonomy',
			condition: 'equals',
			value: fixture.taxonomy || 'category',
		} );
	}

	if ( exportType === 'database_table' ) {
		config.tableName = fixture.table;
		config.options.table_name = fixture.table;
	}

	return config;
}

function createExportJob(
	env,
	{
		exportType,
		format = 'csv',
		tableName = '',
		options = {},
		dynamicFilters = [],
		fields = [],
		status = 'pending',
		processedItems = 0,
		totalItems = 0,
	}
) {
	const params = {
		export_type: exportType,
		format,
		options,
		filters: {},
		fields,
		format_options: format === 'csv' ? { csv_include_header: true } : {},
		dynamic_filters: dynamicFilters,
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
  'status' => ${ JSON.stringify( status ) },
  'user_id' => $uid,
  'data_type' => ${ JSON.stringify( exportType ) },
  'file_format' => ${ JSON.stringify( format ) },
  'processed_items' => ${ Number( processedItems ) },
  'total_items' => ${ Number( totalItems ) },
  'parameters' => wp_json_encode($params),
]);
echo wp_json_encode((int)$job_id);
`;
	return wpEvalJson( env, env.target.wpPath, php );
}

function createFailedExportJob( env, { exportType, format = 'csv' } ) {
	const config = getExportJobConfig( env, exportType );
	return createExportJob( env, {
		...config,
		format,
		status: 'failed',
	} );
}

function getImportJobConfig( env, importType ) {
	const fixture = ensureDeepFixture( env, importType );
	if ( fixture.skipped ) {
		throw new Error(
			`Fixture for ${ importType } skipped: ${ fixture.skipped }`
		);
	}

	const stamp = String( Date.now() ).slice( -8 );
	const base = {
		importType,
		fields: [],
		rows: [],
		options: { batch_size: 20, duplicate_handling: 'create' },
		fixture,
	};

	switch ( importType ) {
		case 'post':
			base.fields = [
				'post_title',
				'post_content',
				'post_status',
				'post_type',
			];
			base.rows = [
				{
					post_title: `Jobs Log Import Post ${ stamp }`,
					post_content: 'Hello from Jobs Log',
					post_status: 'publish',
					post_type: 'post',
				},
			];
			break;
		case 'page':
			base.fields = [
				'post_title',
				'post_content',
				'post_status',
				'post_type',
			];
			base.rows = [
				{
					post_title: `Jobs Log Import Page ${ stamp }`,
					post_content: 'Hello from Jobs Log',
					post_status: 'publish',
					post_type: 'page',
				},
			];
			base.options.post_type = 'page';
			break;
		case 'custom_post_types':
			base.fields = [ 'post_title', 'post_content', 'post_status' ];
			base.rows = [
				{
					post_title: `Jobs Log Import Portfolio ${ stamp }`,
					post_content: 'Hello from Jobs Log',
					post_status: 'publish',
				},
			];
			base.options.custom_post_type = fixture.post_type || 'portfolio';
			base.options.post_type = fixture.post_type || 'portfolio';
			break;
		case 'user':
			base.fields = [
				'user_login',
				'user_email',
				'first_name',
				'last_name',
				'role',
			];
			base.rows = [
				{
					user_login: `jobs_log_user_${ stamp }`,
					user_email: `jobs-log-user-${ stamp }@example.test`,
					first_name: 'Jobs',
					last_name: 'Log',
					role: 'subscriber',
				},
			];
			base.options.duplicate_check = 'user_login';
			break;
		case 'comment':
			base.fields = [
				'comment_post_ID',
				'comment_content',
				'comment_author',
				'comment_author_email',
				'comment_approved',
			];
			base.rows = [
				{
					comment_post_ID: fixture.post_id,
					comment_content: `Jobs Log Import Comment ${ stamp }`,
					comment_author: 'Jobs Log',
					comment_author_email: `jobs-log-comment-${ stamp }@example.test`,
					comment_approved: '1',
				},
			];
			base.options.validate_post_exists = true;
			base.options.duplicate_check = 'comment_content';
			break;
		case 'taxonomy':
			base.fields = [ 'name', 'taxonomy', 'slug', 'description' ];
			base.rows = [
				{
					name: `Jobs Log Term ${ stamp }`,
					taxonomy: fixture.taxonomy || 'category',
					slug: `jobs-log-term-${ stamp }`,
					description: 'Jobs Log imported term',
				},
			];
			base.options.duplicate_check = 'slug';
			break;
		case 'media': {
			const pngPath = writeTinyPng(
				env,
				`jobs-log-media-${ stamp }.png`
			);
			base.fields = [
				'file',
				'title',
				'caption',
				'alt_text',
				'description',
				'filename',
			];
			base.rows = [
				{
					file: pngPath,
					title: `Jobs Log Media ${ stamp }`,
					caption: 'Jobs Log media caption',
					alt_text: 'Jobs Log media alt',
					description: 'Jobs Log media description',
					filename: `jobs-log-media-${ stamp }.png`,
				},
			];
			base.options.duplicate_check = 'filename';
			break;
		}
		case 'menu':
			base.fields = [ 'name', 'slug', 'description', 'menu_items' ];
			base.rows = [
				{
					name: `Jobs Log Menu ${ stamp }`,
					slug: `jobs-log-menu-${ stamp }`,
					description: 'Jobs Log imported menu',
					menu_items: '[]',
				},
			];
			base.options.duplicate_check = 'slug';
			break;
		case 'database_table':
			base.fields = [ 'fixture_key', 'fixture_value' ];
			base.rows = [
				{
					fixture_key: `jobs-${ stamp }`,
					fixture_value: 'Jobs Log imported database row',
				},
			];
			base.options.table_name = fixture.table;
			base.options.primary_key = 'fixture_key';
			base.options.unique_field = 'fixture_key';
			base.options.duplicate_handling = 'update';
			break;
		case 'woo_product':
			base.fields = [
				'post_title',
				'product_type',
				'sku',
				'regular_price',
				'post_status',
			];
			base.rows = [
				{
					post_title: `Jobs Log Product ${ stamp }`,
					product_type: 'simple',
					sku: `jobs-log-sku-${ stamp }`,
					regular_price: '19.99',
					post_status: 'publish',
				},
			];
			base.options.duplicate_check = 'sku';
			break;
		case 'woo_coupon':
			base.fields = [
				'post_title',
				'coupon_amount',
				'discount_type',
				'post_status',
			];
			base.rows = [
				{
					post_title: `jobslog${ stamp }`,
					coupon_amount: '7',
					discount_type: 'fixed_cart',
					post_status: 'publish',
				},
			];
			base.options.update_existing = true;
			break;
		case 'woo_attribute':
			base.fields = [
				'attribute_name',
				'attribute_label',
				'attribute_terms',
			];
			base.rows = [
				{
					attribute_name: `jobslogattr${ stamp }`,
					attribute_label: `Jobs Log Attribute ${ stamp }`,
					attribute_terms: 'Red, Blue',
				},
			];
			base.options.update_existing = true;
			break;
		case 'woo_order': {
			const productId = ensureDeepFixture( env, 'woo_product' ).id;
			base.fields = [
				'order_items',
				'order_status',
				'billing_email',
				'billing_first_name',
				'billing_last_name',
				'customer_note',
			];
			base.rows = [
				{
					order_items: wpJsonStringify( [
						{
							product_id: Number( productId ),
							quantity: 1,
							total: '19.99',
						},
					] ),
					order_status: 'processing',
					billing_email: `jobs-log-order-${ stamp }@example.test`,
					billing_first_name: 'Jobs',
					billing_last_name: 'Log',
					customer_note: `Jobs Log Order ${ stamp }`,
				},
			];
			base.options.update_existing = true;
			break;
		}
		default:
			throw new Error( `Unsupported import retry type: ${ importType }` );
	}

	base.filePath = writeCsvRows(
		env,
		`jobs-log-import-${ importType }.csv`,
		base.fields,
		base.rows
	);
	base.mapping = makeUiMapping( base.fields );
	return base;
}

function wpJsonStringify( value ) {
	return JSON.stringify( value );
}

function createImportJob(
	env,
	{ importType = 'post', withRuntimeState = false } = {}
) {
	const config = getImportJobConfig( env, importType );
	const params = {
		import_type: importType,
		format: 'csv',
		delimiter: ',',
		mapping: config.mapping,
		options: config.options,
		offset: 0,
	};
	if ( withRuntimeState ) {
		params.prepared_data = [ { stale: 'runtime-state-must-be-stripped' } ];
		params.total_items = 1;
		params.cumulative_result = {
			total: 1,
			success: 0,
			skipped: 1,
			failed: 0,
			updated: 0,
			created: 0,
			errors: [],
		};
		params.offset = 999;
	}

	const php = `
$params = json_decode(${ JSON.stringify( JSON.stringify( params ) ) }, true);
$user = get_user_by('login', ${ JSON.stringify( env.target.username ) });
$uid = $user ? (int) $user->ID : 1;
$job_model = rsl_ie()->Model->job;
$job_id = $job_model->create([
  'type' => 'import',
  'status' => 'failed',
  'user_id' => $uid,
  'file_path' => ${ JSON.stringify( config.filePath ) },
  'parameters' => wp_json_encode($params),
]);
echo wp_json_encode((int)$job_id);
`;
	return {
		jobId: wpEvalJson( env, env.target.wpPath, php ),
		filePath: config.filePath,
		config,
	};
}

function createUpdateJob(
	env,
	{
		contentType = 'post',
		exporterType = contentType,
		fields = [ 'post_title' ],
		// Update_Processor expects field_functions keyed by field index (0..n-1).
		fieldFunctions = { 0: [ 'snippet_uppercase' ] },
		options = { items_per_iteration: 10 },
		status = 'failed',
		processedItems = 0,
		totalItems = 0,
	} = {}
) {
	const params = {
		content_type: contentType,
		exporter_type: exporterType,
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
  'status' => ${ JSON.stringify( status ) },
  'processed_items' => ${ Number( processedItems ) },
  'total_items' => ${ Number( totalItems ) },
  'parameters' => wp_json_encode($params),
]);
echo wp_json_encode((int)$job_id);
`;
	return wpEvalJson( env, env.target.wpPath, php );
}

function getUpdateJobConfig( env, contentType ) {
	const fixture = ensureDeepFixture( env, contentType );
	if ( fixture.skipped ) {
		throw new Error(
			`Fixture for ${ contentType } skipped: ${ fixture.skipped }`
		);
	}

	const config = {
		contentType,
		exporterType: contentType,
		fields: [ 'post_title' ],
		options: { items_per_iteration: 10 },
		fixture,
	};

	switch ( contentType ) {
		case 'post':
		case 'page':
		case 'media':
			config.fields = [ 'post_title' ];
			break;
		case 'custom_post_types':
			config.exporterType = 'custom_post_types';
			config.fields = [ 'post_title' ];
			config.options.post_type = fixture.post_type || 'portfolio';
			break;
		case 'menu':
			config.fields = [ 'description' ];
			break;
		case 'user':
			config.fields = [ 'first_name' ];
			break;
		case 'comment':
			config.fields = [ 'comment_content' ];
			break;
		case 'taxonomy':
			config.fields = [ 'description' ];
			config.options.taxonomy = fixture.taxonomy || 'category';
			break;
		case 'woo_product':
			config.fields = [ 'post_title' ];
			break;
		case 'woo_order':
			config.fields = [ 'customer_note' ];
			break;
		case 'woo_coupon':
			config.fields = [ 'post_excerpt' ];
			break;
		case 'database_table':
			config.fields = [ 'fixture_value' ];
			config.options.table_name = fixture.table;
			break;
		default:
			throw new Error(
				`Unsupported update retry type: ${ contentType }`
			);
	}

	return config;
}

function parseJsonField( value ) {
	if ( ! value || typeof value !== 'string' ) return {};
	try {
		return JSON.parse( value );
	} catch {
		return {};
	}
}

function assertCompletedJob( row, label ) {
	if ( ! row ) throw new Error( `${ label }: job row not found` );
	if ( String( row.status ) !== 'completed' ) {
		throw new Error(
			`${ label }: expected completed, got ${ row.status }`
		);
	}
	const result = parseJsonField( row.result );
	if ( result && result.error ) {
		throw new Error( `${ label }: result error: ${ result.error }` );
	}
	return result;
}

function assertExportCompleted( env, row, label ) {
	assertCompletedJob( row, label );
	const total = Number( row.total_items || 0 );
	const processed = Number( row.processed_items || 0 );
	if ( total <= 0 ) {
		throw new Error(
			`${ label }: expected exported items, got total=${ total }`
		);
	}
	if ( processed <= 0 ) {
		throw new Error(
			`${ label }: expected processed items, got processed=${ processed }`
		);
	}
	if ( ! row.file_path || ! fs.existsSync( row.file_path ) ) {
		throw new Error(
			`${ label }: export file missing: ${ row.file_path || '' }`
		);
	}
	const size = fs.statSync( row.file_path ).size;
	if ( size <= 0 ) throw new Error( `${ label }: export file is empty` );
}

function assertImportCompleted( env, row, label, config ) {
	const result = assertCompletedJob( row, label );
	if ( Number( result.failed || 0 ) !== 0 ) {
		throw new Error(
			`${ label }: import failed rows=${
				result.failed
			} ${ JSON.stringify( result.errors || [] ) }`
		);
	}
	if ( Number( result.success || 0 ) <= 0 ) {
		throw new Error( `${ label }: expected at least one imported row` );
	}
	assertImportedFixture( env, config.importType, config );
}

function assertUpdateCompleted( env, row, label, config ) {
	assertCompletedJob( row, label );
	if ( Number( row.error_items || 0 ) !== 0 ) {
		throw new Error(
			`${ label }: update error_items=${ row.error_items }`
		);
	}
	if ( Number( row.imported_items || 0 ) <= 0 ) {
		throw new Error( `${ label }: expected updated items, got 0` );
	}
	assertUpdatedFixture( env, config.contentType, config );
}

function assertImportedFixture( env, importType, config ) {
	const payload = {
		type: importType,
		row: config.rows[ 0 ],
		fixture: config.fixture,
	};
	const php = `
$payload = json_decode(${ JSON.stringify( JSON.stringify( payload ) ) }, true);
$type = (string) $payload['type'];
$row = $payload['row'];
$fixture = $payload['fixture'];
$ok = false;
global $wpdb;
switch ($type) {
  case 'post':
  case 'page':
    $pt = $type;
    $ok = (bool) $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s LIMIT 1", $pt, $row['post_title']));
    break;
  case 'custom_post_types':
    $pt = $fixture['post_type'] ?? 'portfolio';
    $ok = (bool) $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s LIMIT 1", $pt, $row['post_title']));
    break;
  case 'user':
    $ok = (bool) get_user_by('login', $row['user_login']);
    break;
  case 'comment':
    $ok = (bool) $wpdb->get_var($wpdb->prepare("SELECT comment_ID FROM {$wpdb->comments} WHERE comment_content = %s LIMIT 1", $row['comment_content']));
    break;
  case 'taxonomy':
    $term = get_term_by('slug', $row['slug'], $row['taxonomy']);
    $ok = (bool) ($term && ! is_wp_error($term));
    break;
  case 'media':
    $ok = (bool) $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_title = %s LIMIT 1", $row['title']));
    break;
  case 'menu':
    $term = get_term_by('slug', $row['slug'], 'nav_menu');
    $ok = (bool) ($term && ! is_wp_error($term));
    break;
  case 'database_table':
    $table = (string) ($fixture['table'] ?? '');
    if ( preg_match('/^[A-Za-z0-9_]+$/', $table) ) {
      $q = chr(96);
      $ok = (bool) $wpdb->get_var($wpdb->prepare('SELECT fixture_key FROM ' . $q . $table . $q . ' WHERE fixture_key = %s LIMIT 1', $row['fixture_key']));
    }
    break;
  case 'woo_product':
    $ok = function_exists('wc_get_product_id_by_sku') ? (bool) wc_get_product_id_by_sku($row['sku']) : false;
    break;
  case 'woo_coupon':
    $ok = function_exists('wc_get_coupon_id_by_code') ? (bool) wc_get_coupon_id_by_code($row['post_title']) : false;
    break;
  case 'woo_attribute':
    $ok = function_exists('wc_attribute_taxonomy_id_by_name') ? (bool) wc_attribute_taxonomy_id_by_name($row['attribute_name']) : false;
    break;
  case 'woo_order':
    $orders = function_exists('wc_get_orders') ? wc_get_orders([ 'limit' => 1, 'return' => 'ids', 'billing_email' => $row['billing_email'] ]) : [];
    $ok = ! empty($orders);
    break;
}
echo wp_json_encode([ 'ok' => (bool) $ok ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	const result = wpEvalJson( env, env.target.wpPath, php );
	if ( ! result || ! result.ok ) {
		throw new Error(
			`Import side-effect missing for ${ importType }: ${ JSON.stringify(
				payload.row
			) }`
		);
	}
}

function assertUpdatedFixture( env, contentType, config ) {
	const payload = {
		type: contentType,
		field: config.fields[ 0 ],
		fixture: config.fixture,
	};
	const php = `
$payload = json_decode(${ JSON.stringify( JSON.stringify( payload ) ) }, true);
$type = (string) $payload['type'];
$field = (string) $payload['field'];
$fixture = $payload['fixture'];
$value = '';
global $wpdb;
switch ($type) {
  case 'post':
  case 'page':
  case 'custom_post_types':
  case 'media':
  case 'woo_product':
    $value = (string) get_post_field($field, (int) ($fixture['id'] ?? 0));
    break;
  case 'woo_coupon':
    $value = (string) get_post_field($field, (int) ($fixture['id'] ?? 0));
    break;
  case 'menu':
    $term = get_term((int) ($fixture['id'] ?? 0), 'nav_menu');
    $value = ($term && ! is_wp_error($term)) ? (string) $term->description : '';
    break;
  case 'user':
    $user = get_user_by('id', (int) ($fixture['id'] ?? 0));
    $value = $user ? (string) get_user_meta($user->ID, 'first_name', true) : '';
    break;
  case 'comment':
    $comment = get_comment((int) ($fixture['id'] ?? 0));
    $value = $comment ? (string) $comment->comment_content : '';
    break;
  case 'taxonomy':
    $term = get_term((int) ($fixture['id'] ?? 0), (string) ($fixture['taxonomy'] ?? 'category'));
    $value = ($term && ! is_wp_error($term)) ? (string) $term->description : '';
    break;
  case 'woo_order':
    $order = function_exists('wc_get_order') ? wc_get_order((int) ($fixture['id'] ?? 0)) : null;
    $value = $order ? (string) $order->get_customer_note() : '';
    break;
  case 'database_table':
    $table = (string) ($fixture['table'] ?? '');
    if ( preg_match('/^[A-Za-z0-9_]+$/', $table) ) {
      $q = chr(96);
      $value = (string) $wpdb->get_var($wpdb->prepare('SELECT fixture_value FROM ' . $q . $table . $q . ' WHERE fixture_key = %s LIMIT 1', (string) ($fixture['id'] ?? 'jobs')));
    }
    break;
}
echo wp_json_encode([ 'value' => $value, 'upper' => strtoupper($value) ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
`;
	const result = wpEvalJson( env, env.target.wpPath, php );
	if ( ! result || ! result.value || result.value !== result.upper ) {
		throw new Error(
			`Update side-effect missing for ${ contentType }: ${ JSON.stringify(
				result
			) }`
		);
	}
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

	// Global reset before run. Import first so a previous interrupted run with a
	// broken DB cannot prevent cleanup from locating the uploads directory.
	resetCaseState( env );

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
		void postsTable;

		const exportRetryTypes = [
			'post',
			'page',
			'comment',
			'custom_post_types',
			'media',
			'menu',
			'user',
			'taxonomy',
			'woo_product',
			'woo_order',
			'woo_coupon',
			'woo_attribute',
			'database_table',
		];
		const importRetryTypes = [
			'post',
			'page',
			'custom_post_types',
			'user',
			'comment',
			'taxonomy',
			'media',
			'menu',
			'database_table',
			'woo_product',
			'woo_coupon',
			'woo_attribute',
			'woo_order',
		];
		const updateRetryTypes = [
			'post',
			'page',
			'custom_post_types',
			'media',
			'menu',
			'user',
			'comment',
			'taxonomy',
			'woo_product',
			'woo_order',
			'woo_coupon',
			'database_table',
		];

		const makeExportRetryCase = ( exportType ) => ( {
			name: `Jobs Log: export retry (${ exportType })`,
			run: async () => {
				const config = getExportJobConfig( env, exportType );
				const jobId = createExportJob( env, {
					...config,
					status: 'failed',
					processedItems: 999,
					totalItems: 999,
				} );
				if ( ! jobId )
					throw new Error(
						`Failed to create export job for ${ exportType }`
					);

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
						`Export retry did not finish (${ exportType }, jobId=${ newJobId })`
					);
				assertExportCompleted(
					env,
					done,
					`export retry (${ exportType })`
				);
			},
		} );

		const makeImportRetryCase = ( importType ) => ( {
			name: `Jobs Log: import retry (${ importType })`,
			run: async () => {
				const { jobId, config } = createImportJob( env, {
					importType,
					withRuntimeState: true,
				} );
				if ( ! jobId )
					throw new Error(
						`Failed to create import job for ${ importType }`
					);

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
						`Import retry did not finish (${ importType }, jobId=${ newJobId })`
					);
				assertImportCompleted(
					env,
					done,
					`import retry (${ importType })`,
					config
				);
			},
		} );

		const makeUpdateRetryCase = ( contentType ) => ( {
			name: `Jobs Log: update retry (${ contentType })`,
			run: async () => {
				const config = getUpdateJobConfig( env, contentType );
				const jobId = createUpdateJob( env, {
					contentType: config.contentType,
					exporterType: config.exporterType,
					fields: config.fields,
					options: config.options,
					status: 'failed',
					processedItems: 999,
					totalItems: 999,
				} );
				if ( ! jobId )
					throw new Error(
						`Failed to create update job for ${ contentType }`
					);
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
						`Update retry did not finish (${ contentType }, jobId=${ newJobId })`
					);
				assertUpdateCompleted(
					env,
					done,
					`update retry (${ contentType })`,
					config
				);
			},
		} );

		const extraExportRetryCases = exportRetryTypes
			.filter(
				( t ) =>
					! [ 'post', 'database_table', 'woo_order' ].includes( t )
			)
			.map( makeExportRetryCase );
		const extraImportRetryCases = importRetryTypes
			.filter( ( t ) => t !== 'post' )
			.map( makeImportRetryCase );
		const extraUpdateRetryCases = updateRetryTypes
			.filter( ( t ) => t !== 'post' )
			.map( makeUpdateRetryCase );

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
					const config = getExportJobConfig( env, 'post' );
					const jobId = createExportJob( env, {
						...config,
						status: 'failed',
						processedItems: 999,
						totalItems: 999,
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
					assertExportCompleted( env, done, 'export retry (post)' );
				},
			},
			{
				name: 'Jobs Log: export retry (database_table)',
				run: async () => {
					const config = getExportJobConfig( env, 'database_table' );
					const jobId = createExportJob( env, {
						...config,
						status: 'failed',
						processedItems: 999,
						totalItems: 999,
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
					assertExportCompleted(
						env,
						done,
						'export retry (database_table)'
					);
				},
			},
			{
				name: 'Jobs Log: export retry (woo_order)',
				run: async () => {
					const config = getExportJobConfig( env, 'woo_order' );
					const jobId = createExportJob( env, {
						...config,
						status: 'failed',
						processedItems: 999,
						totalItems: 999,
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
					assertExportCompleted(
						env,
						done,
						'export retry (woo_order)'
					);
				},
			},
			{
				name: 'Jobs Log: import retry (post)',
				run: async () => {
					const { jobId, config } = createImportJob( env, {
						importType: 'post',
						withRuntimeState: true,
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
					assertImportCompleted(
						env,
						done,
						'import retry (post)',
						config
					);
				},
			},
			{
				name: 'Jobs Log: update retry (post_title uppercase)',
				run: async () => {
					const config = getUpdateJobConfig( env, 'post' );
					const jobId = createUpdateJob( env, {
						contentType: config.contentType,
						exporterType: config.exporterType,
						fields: config.fields,
						options: config.options,
						status: 'failed',
						processedItems: 999,
						totalItems: 999,
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
					assertUpdateCompleted(
						env,
						done,
						'update retry (post)',
						config
					);
				},
			},
			...extraExportRetryCases,
			...extraImportRetryCases,
			...extraUpdateRetryCases,
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

		const caseFilter = process.env.AIE_CASE_FILTER
			? new RegExp( process.env.AIE_CASE_FILTER, 'i' )
			: null;
		const selectedCases = caseFilter
			? cases.filter( ( c ) => caseFilter.test( c.name ) )
			: cases;

		if ( ! selectedCases.length ) {
			throw new Error(
				`No Jobs Log cases matched AIE_CASE_FILTER=${ process.env.AIE_CASE_FILTER }`
			);
		}

		for ( const c of selectedCases ) {
			console.log( `\n=== ${ c.name } ===` );
			// Reset DB + clean temp files before each case for determinism.
			resetCaseState( env );
			await c.run();
			// Reset after each case to avoid state drift between retries.
			resetCaseState( env );
			console.log( `OK: ${ c.name }` );
		}
	} finally {
		await context.close().catch( () => null );
		await browser.close().catch( () => null );
		rmrf( downloadsDir );
		// Final cleanup & reset.
		resetCaseState( env );
	}

	console.log( '\nAll Jobs Log cases passed.' );
}

run().catch( ( err ) => {
	console.error( err && err.stack ? err.stack : String( err ) );
	process.exit( 1 );
} );
