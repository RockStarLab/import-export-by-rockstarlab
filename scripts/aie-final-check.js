/**
 * Final regression runner (manual-ish, but automated):
 * - Mutates a few fields on SOURCE for each content type (content/meta/featured image, etc.)
 * - Runs the existing Playwright export+import+visual-compare flow (visual-multi script)
 *
 * Usage:
 *   node scripts/aie-final-check.js
 *
 * Env (defaults read from .env.e2e if present):
 *   AIE_FINAL_TYPES=post,page,media,menu,user,comment,taxonomy,woo_product,woo_order,woo_coupon,woo_attribute,database_table,custom_post_types
 *   AIE_FINAL_TAG=any-string
 *   AIE_CUSTOM_POST_TYPE=product (used when custom_post_types is in the list)
 *   AIE_TAXONOMY=category
 *   AIE_DB_TABLE_PATTERNS=otbo,mask
 *   AIE_IF_EXISTS=update|skip|create
 *   AIE_IF_NOT_EXISTS=create|skip
 *   AIE_AUTO_IMPORT_MEDIA=true|false
 *   AIE_MEDIA_DUPLICATE_MODE=skip|create|replace
 *   AIE_COMPARE_LIMIT=5
 *   AIE_TARGET_DB_SQL=db.sql
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { execFileSync, spawnSync } = require( 'child_process' );

let runtimeLocalPhp = 'php';
let runtimeWpBin = '/opt/homebrew/bin/wp';

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
	if ( fs.existsSync( envPath ) ) {
		fileEnv = parseDotEnv( fs.readFileSync( envPath, 'utf8' ) );
	}
	const get = ( key, fallback ) =>
		process.env[ key ] ?? fileEnv[ key ] ?? fallback;

	const typesRaw = String(
		get(
			'AIE_FINAL_TYPES',
			'post,page,custom_post_types,media,menu,user,comment,taxonomy,woo_product,woo_order,woo_coupon,woo_attribute,database_table'
		)
	);
	const types = typesRaw
		.split( ',' )
		.map( ( x ) => String( x ).trim() )
		.filter( Boolean );

	const tag =
		String( get( 'AIE_FINAL_TAG', '' ) ).trim() ||
		new Date().toISOString().replace( /[:.]/g, '-' );
	const compareLimit = Math.max(
		1,
		Number.parseInt( get( 'AIE_COMPARE_LIMIT', '5' ), 10 ) || 5
	);

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

	const localPhpCandidates = [
		'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php',
		'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php',
	];
	const localPhpDefault =
		localPhpCandidates.find( ( p ) => fs.existsSync( p ) ) || 'php';
	runtimeLocalPhp = String( get( 'AIE_LOCAL_PHP', localPhpDefault ) );
	runtimeWpBin = String( get( 'AIE_WP_BIN', '/opt/homebrew/bin/wp' ) );
	const mysqlCandidates = [
		'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/mysql-8.4.0/bin/darwin-arm64/bin/mysql',
		'/Applications/Local.app/Contents/Resources/extraResources/lightning-services/mysql-8.0.35+4/bin/darwin-arm64/bin/mysql',
	];

	return {
		types,
		tag,
		taxonomy:
			String( get( 'AIE_TAXONOMY', 'category' ) ).trim() || 'category',
		customPostType:
			String( get( 'AIE_CUSTOM_POST_TYPE', 'product' ) ).trim() ||
			'product',
		dbTablePatterns: String( get( 'AIE_DB_TABLE_PATTERNS', 'otbo,mask' ) ),
		sourceWpPath: String(
			get( 'AIE_SOURCE_WP_PATH', sourceWpPathDefault )
		),
		targetWpPath: String(
			get(
				'AIE_TARGET_WP_PATH',
				targetWpPathGuess || sourceWpPathDefault
			)
		),
		localPhp: runtimeLocalPhp,
		wpBin: runtimeWpBin,
		mysqlBin:
			String( get( 'AIE_LOCAL_MYSQL', '' ) ) ||
			mysqlCandidates.find( ( candidate ) =>
				fs.existsSync( candidate )
			) ||
			'mysql',
		targetDbSql: String( get( 'AIE_TARGET_DB_SQL', 'db.sql' ) ),
		compareLimit,
	};
}

function sh( cmd, opts = {} ) {
	return execFileSync( '/bin/zsh', [ '-lc', cmd ], {
		encoding: 'utf8',
		...opts,
	} ).trim();
}

function wp( { wpPath, args } ) {
	// Avoid "--path <with spaces>" splitting problems.
	const phpArgs = [
		'-d',
		'display_errors=0',
		'-d',
		'error_reporting=0',
		'-d',
		'html_errors=0',
	];
	return execFileSync(
		runtimeLocalPhp,
		[ ...phpArgs, runtimeWpBin, `--path=${ wpPath }`, ...args ],
		{ encoding: 'utf8' }
	).trim();
}

function safe( fn ) {
	try {
		return { ok: true, value: fn() };
	} catch ( e ) {
		return { ok: false, error: e };
	}
}

function restoreTargetDb( env ) {
	const sqlPath = path.join( env.targetWpPath, env.targetDbSql );
	if ( ! fs.existsSync( sqlPath ) ) {
		throw new Error( `Target DB baseline not found: ${ sqlPath }` );
	}
	const config = ( key ) =>
		wp( { wpPath: env.targetWpPath, args: [ 'config', 'get', key ] } );
	const dbName = config( 'DB_NAME' );
	const dbUser = config( 'DB_USER' );
	const dbPass = config( 'DB_PASSWORD' );
	const dbHost = config( 'DB_HOST' );
	const socket = String( dbHost || '' ).startsWith( ':' )
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
		{ input: fs.readFileSync( sqlPath ) }
	);
	wp( {
		wpPath: env.targetWpPath,
		args: [ 'core', 'update-db', '--quiet' ],
	} );
}

function appendPostContent( { wpPath, postId, marker } ) {
	const content = wp( {
		wpPath,
		args: [ 'post', 'get', String( postId ), '--field=post_content' ],
	} );
	const updated = `${ content }\n\n${ marker }\n`;
	wp( {
		wpPath,
		args: [
			'post',
			'update',
			String( postId ),
			`--post_content=${ updated }`,
		],
	} );
}

function updatePostMeta( { wpPath, postId, key, value } ) {
	wp( {
		wpPath,
		args: [ 'post', 'meta', 'update', String( postId ), key, value ],
	} );
}

function setFeaturedImageToNextAttachment( { wpPath, postId } ) {
	const idsRaw = wp( {
		wpPath,
		args: [
			'post',
			'list',
			'--post_type=attachment',
			'--posts_per_page=5',
			'--format=ids',
		],
	} );
	const ids = idsRaw
		.split( /\s+/ )
		.map( ( x ) => Number( x ) )
		.filter( ( n ) => Number.isFinite( n ) && n > 0 );
	if ( ! ids.length ) return;

	const currentRaw = safe( () =>
		wp( {
			wpPath,
			args: [ 'post', 'meta', 'get', String( postId ), '_thumbnail_id' ],
		} )
	);
	const current = currentRaw.ok
		? Number( String( currentRaw.value || '' ).trim() )
		: 0;
	const chosen = ids.find( ( id ) => id && id !== current ) || ids[ 0 ];
	wp( {
		wpPath,
		args: [
			'post',
			'meta',
			'update',
			String( postId ),
			'_thumbnail_id',
			String( chosen ),
		],
	} );
}

function mutateAcfFieldsBestEffort( { wpPath, postId, marker } ) {
	// Best-effort: update one WYSIWYG field (insert a gallery shortcode) and one flexible content field.
	// Safe to no-op if ACF isn't active or fields aren't present.
	const php = [
		'$post_id = (int)(' + Number( postId ) + ');',
		'$marker = ' + JSON.stringify( String( marker ) ) + ';',
		'if (!$post_id) { return; }',
		'if (!function_exists("get_field_objects") || !function_exists("update_field")) { return; }',
		'$fields = get_field_objects($post_id);',
		'if (!is_array($fields) || empty($fields)) { return; }',
		// Prepare a small gallery shortcode using current-site attachment IDs.
		'$att = get_posts(["post_type"=>"attachment","post_status"=>"inherit","posts_per_page"=>4,"fields"=>"ids"]);',
		'$ids = []; foreach ((array)$att as $id) { $id = (int)$id; if ($id>0) $ids[] = $id; }',
		'$ids = array_slice($ids, 0, 4);',
		'$gallery = !empty($ids) ? "[gallery ids=\\"" . implode(",", $ids) . "\\"]" : "";',
		'$did_wysiwyg = false;',
		'$did_flex = false;',
		'foreach ($fields as $name => $field) {',
		'  if (!$did_wysiwyg && is_array($field) && ($field["type"] ?? "") === "wysiwyg") {',
		'    $val = trim($marker . "\\n\\n" . $gallery);',
		'    $key = (string)($field["key"] ?? $name);',
		'    update_field($key, $val, $post_id);',
		'    $did_wysiwyg = true;',
		'    continue;',
		'  }',
		'  if (!$did_flex && is_array($field) && ($field["type"] ?? "") === "flexible_content") {',
		'    $layouts = $field["layouts"] ?? [];',
		'    if (!is_array($layouts) || empty($layouts)) { continue; }',
		'    $layout = $layouts[0];',
		'    $layout_name = is_array($layout) ? (string)($layout["name"] ?? "") : "";',
		'    if ($layout_name === "") { continue; }',
		'    $sub_fields = is_array($layout) ? ($layout["sub_fields"] ?? []) : [];',
		'    $row = ["acf_fc_layout" => $layout_name];',
		'    if (is_array($sub_fields) && !empty($sub_fields)) {',
		'      $sub = $sub_fields[0];',
		'      $sub_name = is_array($sub) ? (string)($sub["name"] ?? "") : "";',
		'      if ($sub_name !== "") { $row[$sub_name] = $marker; }',
		'    }',
		'    $key = (string)($field["key"] ?? $name);',
		'    update_field($key, [$row], $post_id);',
		'    $did_flex = true;',
		'    continue;',
		'  }',
		'  if ($did_wysiwyg && $did_flex) { break; }',
		'}',
	].join( ' ' );

	safe( () => wp( { wpPath, args: [ 'eval', php ] } ) );
}

function mutateSourceForType( env, type ) {
	const wpPath = env.sourceWpPath;
	const marker = `AIE FINAL ${ env.tag } (${ type })`;

	if ( type === 'post' ) {
		const postId = Number(
			wp( {
				wpPath,
				args: [
					'post',
					'list',
					'--post_type=post',
					'--posts_per_page=1',
					'--format=ids',
				],
			} ).split( /\s+/ )[ 0 ] || 0
		);
		if ( ! postId ) return { skipped: true, reason: 'No posts found' };
		appendPostContent( { wpPath, postId, marker } );
		updatePostMeta( { wpPath, postId, key: 'text_field', value: marker } );
		updatePostMeta( {
			wpPath,
			postId,
			key: 'repeater_0_text',
			value: marker,
		} );
		setFeaturedImageToNextAttachment( { wpPath, postId } );
		mutateAcfFieldsBestEffort( { wpPath, postId, marker } );
		return { postId };
	}

	if ( type === 'page' ) {
		const idsRaw = wp( {
			wpPath,
			args: [
				'post',
				'list',
				'--post_type=page',
				'--posts_per_page=3',
				'--format=ids',
			],
		} );
		const ids = idsRaw
			.split( /\s+/ )
			.map( ( x ) => Number( x ) )
			.filter( ( n ) => Number.isFinite( n ) && n > 0 );
		if ( ! ids.length ) return { skipped: true, reason: 'No pages found' };
		for ( const id of ids ) {
			appendPostContent( { wpPath, postId: id, marker } );
			setFeaturedImageToNextAttachment( { wpPath, postId: id } );
			mutateAcfFieldsBestEffort( { wpPath, postId: id, marker } );
		}
		return { pageIds: ids };
	}

	if ( type === 'woo_product' ) {
		const postId = Number(
			wp( {
				wpPath,
				args: [
					'post',
					'list',
					'--post_type=product',
					'--posts_per_page=1',
					'--format=ids',
				],
			} ).split( /\s+/ )[ 0 ] || 0
		);
		if ( ! postId ) return { skipped: true, reason: 'No products found' };
		appendPostContent( { wpPath, postId, marker } );
		updatePostMeta( {
			wpPath,
			postId,
			key: '_aie_final_marker',
			value: marker,
		} );
		setFeaturedImageToNextAttachment( { wpPath, postId } );
		mutateAcfFieldsBestEffort( { wpPath, postId, marker } );
		return { productId: postId };
	}

	if ( type === 'woo_order' ) {
		let postId = Number(
			wp( {
				wpPath,
				args: [
					'post',
					'list',
					'--post_type=shop_order',
					'--posts_per_page=1',
					'--format=ids',
				],
			} ).split( /\s+/ )[ 0 ] || 0
		);

		// If there are no orders, create a minimal one so we can export/import/compare.
		if ( ! postId ) {
			const php = [
				'if (!function_exists("wc_create_order")) { echo ""; return; }',
				'$order = wc_create_order();',
				'if (is_wp_error($order) || !$order) { echo ""; return; }',
				'$products = function_exists("wc_get_products") ? wc_get_products(["limit"=>1,"status"=>"publish"]) : [];',
				'if (is_array($products) && !empty($products)) { $order->add_product($products[0], 1); }',
				'$order->set_customer_note(' +
					JSON.stringify( String( marker ) ) +
					');',
				'$order->calculate_totals();',
				'$order->save();',
				'echo (int) $order->get_id();',
			].join( ' ' );
			const created = safe( () =>
				wp( { wpPath, args: [ 'eval', php ] } )
			);
			postId = created.ok
				? Number( String( created.value || '' ).trim() || 0 )
				: 0;
		}

		if ( ! postId )
			return {
				skipped: true,
				reason: 'No orders found and could not create one',
			};
		updatePostMeta( {
			wpPath,
			postId,
			key: '_customer_note',
			value: marker,
		} );
		updatePostMeta( {
			wpPath,
			postId,
			key: '_aie_final_marker',
			value: marker,
		} );
		return { orderId: postId };
	}

	if ( type === 'woo_coupon' ) {
		const postId = Number(
			wp( {
				wpPath,
				args: [
					'post',
					'list',
					'--post_type=shop_coupon',
					'--posts_per_page=1',
					'--format=ids',
				],
			} ).split( /\s+/ )[ 0 ] || 0
		);
		let couponId = postId;
		if ( ! couponId ) {
			const created = safe( () =>
				wp( {
					wpPath,
					args: [
						'post',
						'create',
						'--post_type=shop_coupon',
						'--post_status=publish',
						`--post_title=AIE-COUPON-${ env.tag
							.replace( /[^A-Za-z0-9]+/g, '' )
							.slice( 0, 18 ) }`,
						'--porcelain',
					],
				} )
			);
			couponId = created.ok
				? Number( String( created.value || '' ).trim() || 0 )
				: 0;
		}
		if ( ! couponId ) return { skipped: true, reason: 'No coupons found' };
		appendPostContent( { wpPath, postId: couponId, marker } );
		updatePostMeta( {
			wpPath,
			postId: couponId,
			key: '_aie_final_marker',
			value: marker,
		} );
		mutateAcfFieldsBestEffort( { wpPath, postId: couponId, marker } );
		return { couponId };
	}

	if ( type === 'media' ) {
		const attachmentId = Number(
			wp( {
				wpPath,
				args: [
					'post',
					'list',
					'--post_type=attachment',
					'--posts_per_page=1',
					'--format=ids',
				],
			} ).split( /\s+/ )[ 0 ] || 0
		);
		if ( ! attachmentId )
			return { skipped: true, reason: 'No attachments found' };
		wp( {
			wpPath,
			args: [
				'post',
				'update',
				String( attachmentId ),
				`--post_title=${ marker }`,
			],
		} );
		wp( {
			wpPath,
			args: [
				'post',
				'meta',
				'update',
				String( attachmentId ),
				'_wp_attachment_image_alt',
				marker,
			],
		} );
		return { attachmentId };
	}

	if ( type === 'menu' ) {
		// Best-effort: update the first menu item's title in the first menu.
		const menuName = wp( {
			wpPath,
			args: [ 'menu', 'list', '--fields=name', '--format=csv' ],
		} )
			.split( /\r?\n/ )
			.slice( 1 )
			.map( ( x ) => x.trim() )
			.filter( Boolean )[ 0 ];
		if ( ! menuName ) return { skipped: true, reason: 'No menus found' };

		const itemIds = wp( {
			wpPath,
			args: [
				'menu',
				'item',
				'list',
				menuName,
				'--fields=db_id',
				'--format=csv',
			],
		} )
			.split( /\r?\n/ )
			.slice( 1 )
			.map( ( x ) => Number( x.trim() ) )
			.filter( ( n ) => Number.isFinite( n ) && n > 0 );
		const firstItem = itemIds[ 0 ];
		if ( ! firstItem )
			return { skipped: true, reason: 'Menu has no items' };

		wp( {
			wpPath,
			args: [
				'menu',
				'item',
				'update',
				String( firstItem ),
				`--title=${ marker }`,
			],
		} );
		return { menuName, menuItemId: firstItem };
	}

	if ( type === 'user' ) {
		const userId = Number(
			wp( {
				wpPath,
				args: [
					'user',
					'list',
					'--role=administrator',
					'--number=1',
					'--field=ID',
				],
			} ).split( /\s+/ )[ 0 ] || 0
		);
		if ( ! userId )
			return { skipped: true, reason: 'No admin users found' };
		wp( {
			wpPath,
			args: [
				'user',
				'meta',
				'update',
				String( userId ),
				'first_name',
				marker,
			],
		} );
		return { userId };
	}

	if ( type === 'comment' ) {
		const commentId = Number(
			wp( {
				wpPath,
				args: [ 'comment', 'list', '--number=1', '--field=comment_ID' ],
			} ).split( /\s+/ )[ 0 ] || 0
		);
		if ( ! commentId )
			return { skipped: true, reason: 'No comments found' };
		wp( {
			wpPath,
			args: [
				'comment',
				'update',
				String( commentId ),
				`--comment_content=${ marker }`,
			],
		} );
		return { commentId };
	}

	if ( type === 'taxonomy' ) {
		const taxonomy = env.taxonomy;
		const termId = Number(
			wp( {
				wpPath,
				args: [
					'term',
					'list',
					taxonomy,
					'--number=1',
					'--field=term_id',
				],
			} ).split( /\s+/ )[ 0 ] || 0
		);
		if ( ! termId )
			return {
				skipped: true,
				reason: `No terms found in taxonomy=${ taxonomy }`,
			};
		wp( {
			wpPath,
			args: [
				'term',
				'update',
				taxonomy,
				String( termId ),
				`--description=${ marker }`,
			],
		} );
		return { taxonomy, termId };
	}

	if ( type === 'woo_attribute' ) {
		// Woo attributes: use WooCommerce APIs to update + clear caches.
		const safeTag = env.tag.replace( /'/g, '' );
		const res = wp( {
			wpPath,
			args: [
				'eval',
				[
					'if (!function_exists("wc_get_attribute_taxonomies") || !function_exists("wc_update_attribute")) { echo ""; return; }',
					'$attrs = wc_get_attribute_taxonomies();',
					'if (!is_array($attrs) || empty($attrs)) { echo ""; return; }',
					'$a = reset($attrs);',
					'if (!$a) { echo ""; return; }',
					'$id = (int) ($a->attribute_id ?? 0);',
					'$slug = (string) ($a->attribute_name ?? "");',
					'$type = (string) ($a->attribute_type ?? "select");',
					'$orderby = (string) ($a->attribute_orderby ?? "menu_order");',
					'$has_archives = (int) ($a->attribute_public ?? 0);',
					'$label = (string) ($a->attribute_label ?? "");',
					'$new_label = $label . " - ' + safeTag + '";',
					'wc_update_attribute($id, ["name" => $new_label, "slug" => $slug, "type" => $type, "order_by" => $orderby, "has_archives" => $has_archives]);',
					'if (function_exists("wc_delete_attribute_transients")) { wc_delete_attribute_transients(); }',
					'echo (int) $id;',
				].join( ' ' ),
			],
		} );
		const attributeId = Number( String( res || '' ).trim() || 0 );
		if ( ! attributeId )
			return { skipped: true, reason: 'No Woo attributes found' };
		return { attributeId };
	}

	if ( type === 'database_table' ) {
		const patterns = env.dbTablePatterns
			.split( ',' )
			.map( ( x ) => x.trim() )
			.filter( Boolean );
		const jsPatterns = patterns
			.map( ( p ) => p.replace( /'/g, '' ) )
			.join( ',' );

		const res = wp( {
			wpPath,
			args: [
				'eval',
				[
					'global $wpdb;',
					'$patterns = array_filter(array_map("trim", explode(",", "' +
						jsPatterns +
						'")));',
					'$tables = $wpdb->get_col("SHOW TABLES");',
					'$pick = "";',
					'foreach ($tables as $t) {',
					'  $ok = empty($patterns);',
					'  foreach ($patterns as $p) { if ($p !== "" && stripos($t, $p) !== false) { $ok = true; break; } }',
					'  if (!$ok) continue;',
					'  $cnt = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t}");',
					'  if ($cnt > 0) { $pick = $t; break; }',
					'}',
					'if ($pick === "") { echo ""; return; }',
					'$row = $wpdb->get_row("SELECT * FROM {$pick} LIMIT 1", ARRAY_A);',
					'if (!is_array($row) || empty($row)) { echo $pick; return; }',
					'$cols = array_keys($row);',
					'$whereCol = $cols[0];',
					'$whereVal = $row[$whereCol];',
					'$setCol = null;',
					'foreach ($cols as $c) { if ($c !== $whereCol) { $setCol = $c; break; } }',
					'if ($setCol) { $wpdb->update($pick, [$setCol => "AIE FINAL ' +
						env.tag.replace( /'/g, '' ) +
						'"], [$whereCol => $whereVal]); }',
					'echo $pick;',
				].join( ' ' ),
			],
		} );
		const tableName = String( res || '' ).trim();
		if ( ! tableName )
			return {
				skipped: true,
				reason: `No non-empty table matched patterns=${ env.dbTablePatterns }`,
			};
		return { tableName };
	}

	if ( type === 'custom_post_types' ) {
		// Mutate one item of the chosen CPT (default: product).
		const postType = env.customPostType;
		const postId = Number(
			wp( {
				wpPath,
				args: [
					'post',
					'list',
					`--post_type=${ postType }`,
					'--posts_per_page=1',
					'--format=ids',
				],
			} ).split( /\s+/ )[ 0 ] || 0
		);
		if ( ! postId )
			return {
				skipped: true,
				reason: `No posts found for post_type=${ postType }`,
			};
		appendPostContent( { wpPath, postId, marker } );
		updatePostMeta( {
			wpPath,
			postId,
			key: '_aie_final_marker',
			value: marker,
		} );
		mutateAcfFieldsBestEffort( { wpPath, postId, marker } );
		return { postType, postId };
	}

	return { skipped: true, reason: `Unknown type=${ type }` };
}

function runVisualMulti( env, type, extraEnv = {} ) {
	const nodeEnv = {
		...process.env,
		AIE_CONTENT_TYPE: type,
		AIE_CUSTOM_POST_TYPE: env.customPostType,
		AIE_TAXONOMY: env.taxonomy,
		AIE_DB_TABLE_PATTERNS: env.dbTablePatterns,
		// Default import options for the regression
		AIE_IF_EXISTS: process.env.AIE_IF_EXISTS || 'update',
		AIE_IF_NOT_EXISTS: process.env.AIE_IF_NOT_EXISTS || 'create',
		AIE_AUTO_IMPORT_MEDIA: process.env.AIE_AUTO_IMPORT_MEDIA || 'true',
		AIE_MEDIA_DUPLICATE_MODE:
			process.env.AIE_MEDIA_DUPLICATE_MODE || 'skip',
		...extraEnv,
	};

	const result = spawnSync(
		'node',
		[ 'scripts/aie-import-export-visual-multi.js' ],
		{
			cwd: process.cwd(),
			env: nodeEnv,
			stdio: 'inherit',
		}
	);
	if ( result.status !== 0 ) {
		throw new Error(
			`visual-multi failed for type=${ type } (exit=${ result.status })`
		);
	}
}

function getVisualVariants( type ) {
	const mediaHeavyTypes = new Set( [
		'post',
		'page',
		'custom_post_types',
		'media',
		'woo_product',
	] );

	return [
		{
			name: 'baseline',
			extraEnv: {},
		},
		{
			name: 'repeat-update',
			extraEnv: mediaHeavyTypes.has( type )
				? { AIE_MEDIA_DUPLICATE_MODE: 'replace' }
				: {},
		},
	];
}

function getComparePostTypeForType( env, type ) {
	if ( type === 'post' ) return 'post';
	if ( type === 'page' ) return 'page';
	if ( type === 'custom_post_types' ) return env.customPostType;
	if ( type === 'woo_product' ) return 'product';
	if ( type === 'woo_order' ) return 'shop_order';
	if ( type === 'woo_coupon' ) return 'shop_coupon';
	return '';
}

function getSourceIdsForNonPostType( { env, type, mutateResult } ) {
	const wpPath = env.sourceWpPath;

	if ( type === 'media' ) {
		if ( mutateResult && mutateResult.attachmentId )
			return [ Number( mutateResult.attachmentId ) ].filter( Boolean );
		const idsRaw = safe( () =>
			wp( {
				wpPath,
				args: [
					'post',
					'list',
					'--post_type=attachment',
					'--posts_per_page=3',
					'--format=ids',
				],
			} )
		);
		if ( idsRaw.ok && idsRaw.value ) {
			return String( idsRaw.value )
				.trim()
				.split( /\s+/ )
				.map( ( x ) => Number( x ) )
				.filter( ( n ) => Number.isFinite( n ) && n > 0 )
				.slice( 0, 3 );
		}
	}

	if ( type === 'user' ) {
		if ( mutateResult && mutateResult.userId )
			return [ Number( mutateResult.userId ) ].filter( Boolean );
		const idsRaw = safe( () =>
			wp( {
				wpPath,
				args: [ 'user', 'list', '--number=3', '--field=ID' ],
			} )
		);
		if ( idsRaw.ok && idsRaw.value ) {
			return String( idsRaw.value )
				.trim()
				.split( /\s+/ )
				.map( ( x ) => Number( x ) )
				.filter( ( n ) => Number.isFinite( n ) && n > 0 )
				.slice( 0, 3 );
		}
	}

	if ( type === 'comment' ) {
		if ( mutateResult && mutateResult.commentId )
			return [ Number( mutateResult.commentId ) ].filter( Boolean );
		const idsRaw = safe( () =>
			wp( {
				wpPath,
				args: [ 'comment', 'list', '--number=3', '--field=comment_ID' ],
			} )
		);
		if ( idsRaw.ok && idsRaw.value ) {
			return String( idsRaw.value )
				.trim()
				.split( /\s+/ )
				.map( ( x ) => Number( x ) )
				.filter( ( n ) => Number.isFinite( n ) && n > 0 )
				.slice( 0, 3 );
		}
	}

	if ( type === 'taxonomy' ) {
		if ( mutateResult && mutateResult.termId )
			return [ Number( mutateResult.termId ) ].filter( Boolean );
		const taxonomy = env.taxonomy || 'category';
		const idsRaw = safe( () =>
			wp( {
				wpPath,
				args: [
					'term',
					'list',
					taxonomy,
					'--number=3',
					'--field=term_id',
				],
			} )
		);
		if ( idsRaw.ok && idsRaw.value ) {
			return String( idsRaw.value )
				.trim()
				.split( /\s+/ )
				.map( ( x ) => Number( x ) )
				.filter( ( n ) => Number.isFinite( n ) && n > 0 )
				.slice( 0, 3 );
		}
	}

	return [];
}

async function main() {
	const env = loadEnv();

	console.log( `[final] Types: ${ env.types.join( ', ' ) }` );
	console.log( `[final] Tag: ${ env.tag }` );
	console.log( `[final] Browser comparison limit: ${ env.compareLimit }` );
	console.log( `[final] Source WP path: ${ env.sourceWpPath }` );
	console.log( `[final] Target WP path: ${ env.targetWpPath }` );

	const results = [];

	for ( const type of env.types ) {
		console.log( `\n[final] === ${ type } ===` );

		const mut = safe( () => mutateSourceForType( env, type ) );
		if ( ! mut.ok ) {
			console.log(
				`[final] mutate failed (${ type }): ${ String(
					mut.error && mut.error.message
						? mut.error.message
						: mut.error
				) }`
			);
		} else if ( mut.value && mut.value.skipped ) {
			console.log(
				`[final] mutate skipped (${ type }): ${
					mut.value.reason || 'n/a'
				}`
			);
		} else {
			console.log(
				`[final] mutate ok (${ type }): ${ JSON.stringify(
					mut.value || {}
				) }`
			);
		}

		const extraEnv = {};
		if (
			type === 'database_table' &&
			mut.ok &&
			mut.value &&
			mut.value.tableName
		) {
			extraEnv.AIE_DB_TABLE = mut.value.tableName;
		}

		// WooCommerce orders may be stored in HPOS tables (not as posts), so prefer the
		// explicit orderId we just mutated/created.
		if (
			type === 'woo_order' &&
			mut.ok &&
			mut.value &&
			mut.value.orderId
		) {
			extraEnv.AIE_SOURCE_POST_IDS = String( mut.value.orderId );
			console.log(
				`[final] compare IDs (${ type }): ${ extraEnv.AIE_SOURCE_POST_IDS }`
			);
		}

		// Ensure we compare items of the correct type (visual-multi defaults are Pages).
		const comparePostType = getComparePostTypeForType( env, type );
		if ( comparePostType && ! extraEnv.AIE_SOURCE_POST_IDS ) {
			const idsRaw = safe( () =>
				wp( {
					wpPath: env.sourceWpPath,
					// Use post_status=any so we can pick items like WooCommerce orders (wc-*) too.
					args: [
						'post',
						'list',
						`--post_type=${ comparePostType }`,
						'--post_status=any',
						`--posts_per_page=${ env.compareLimit }`,
						'--orderby=rand',
						'--format=ids',
					],
				} )
			);
			if ( idsRaw.ok && idsRaw.value ) {
				extraEnv.AIE_SOURCE_POST_IDS = String( idsRaw.value )
					.trim()
					.split( /\s+/ )
					.slice( 0, env.compareLimit )
					.join( ',' );
				console.log(
					`[final] compare IDs (${ type }): ${ extraEnv.AIE_SOURCE_POST_IDS }`
				);
			}
		} else {
			const ids = getSourceIdsForNonPostType( {
				env,
				type,
				mutateResult: mut.ok ? mut.value : null,
			} );
			if ( ids.length ) {
				extraEnv.AIE_SOURCE_POST_IDS = ids
					.slice( 0, env.compareLimit )
					.join( ',' );
				console.log(
					`[final] compare IDs (${ type }): ${ extraEnv.AIE_SOURCE_POST_IDS }`
				);
			}
		}

		for ( const variant of getVisualVariants( type ) ) {
			console.log(
				`[final] restore target DB before ${ type }/${ variant.name }`
			);
			restoreTargetDb( env );
			const mergedEnv = { ...extraEnv, ...variant.extraEnv };
			console.log(
				`[final] visual variant (${ type }/${
					variant.name
				}): ${ JSON.stringify( mergedEnv ) }`
			);
			const visual = safe( () => runVisualMulti( env, type, mergedEnv ) );
			if ( ! visual.ok ) {
				console.log(
					`[final] visual FAILED (${ type }/${
						variant.name
					}): ${ String(
						visual.error && visual.error.message
							? visual.error.message
							: visual.error
					) }`
				);
				results.push( {
					type,
					variant: variant.name,
					ok: false,
					error: String(
						visual.error && visual.error.message
							? visual.error.message
							: visual.error
					),
				} );
				continue;
			}
			results.push( { type, variant: variant.name, ok: true } );
		}
	}

	console.log( '\n[final] Done.' );
	const failed = results.filter( ( r ) => ! r.ok );
	if ( failed.length ) {
		console.log(
			`[final] FAILURES: ${ failed
				.map(
					( f ) =>
						`${ f.type }${ f.variant ? `/${ f.variant }` : '' }`
				)
				.join( ', ' ) }`
		);
		process.exitCode = 2;
	} else {
		console.log( '[final] All types passed.' );
	}
}

main().catch( ( err ) => {
	console.error( err );
	process.exitCode = 1;
} );
