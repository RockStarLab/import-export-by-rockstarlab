/**
 * Manual E2E (Playwright): Content Sync checks
 *
 * What it covers:
 * - Ensures a connected site exists (source -> target)
 * - For each supported post type: fills visible ACF fields on source and pushes to target
 * - Single post sync on `/wp-admin/post.php?post=1&action=edit` (push + pull via mapping modal)
 * - Bulk sync from `/wp-admin/edit.php` including "no selection" browse modal flow
 * - Media dedup check: verifies attachment count by `_aie_file_hash` does not increase on repeat push/pull
 *
 * Usage:
 *   node scripts/aie-content-sync-check.js
 *
 * Env (defaults read from .env.e2e if present):
 *   AIE_SOURCE_URL, AIE_SOURCE_ADMIN_USER, AIE_SOURCE_ADMIN_PASSWORD
 *   AIE_TARGET_URL, AIE_TARGET_ADMIN_USER, AIE_TARGET_ADMIN_PASSWORD
 *   AIE_HEADLESS=true|false
 *   AIE_SOURCE_WP_PATH=/path/to/wp/root
 *   AIE_TARGET_WP_PATH=/path/to/wp/root
 *   AIE_LOCAL_PHP=/path/to/php (Local.app bundled PHP works well)
 *   AIE_WP_BIN=/path/to/wp (wp-cli wrapper)
 */

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');

let lastDialogMessage = '';

function parseDotEnv(contents) {
  const env = {};
  for (const line of contents.split(/\r?\n/)) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const idx = trimmed.indexOf('=');
    if (idx === -1) continue;
    const key = trimmed.slice(0, idx).trim();
    const value = trimmed.slice(idx + 1).trim();
    env[key] = value;
  }
  return env;
}

function loadEnv() {
  const envPath = path.resolve(process.cwd(), '.env.e2e');
  let fileEnv = {};
  if (fs.existsSync(envPath)) fileEnv = parseDotEnv(fs.readFileSync(envPath, 'utf8'));
  const get = (key, fallback) => process.env[key] ?? fileEnv[key] ?? fallback;

  const headlessRaw = String(get('AIE_HEADLESS', 'true')).toLowerCase();
  const headless = headlessRaw === '1' || headlessRaw === 'true' || headlessRaw === 'yes';

  const sourceWpPathDefault = path.resolve(process.cwd(), '../../..');
  const targetWpPathGuess = (() => {
    const marker = `${path.sep}Local Sites${path.sep}aie${path.sep}`;
    if (sourceWpPathDefault.includes(marker)) {
      return sourceWpPathDefault.replace(marker, `${path.sep}Local Sites${path.sep}aie2${path.sep}`);
    }
    return '';
  })();

  const localPhpDefault =
    '/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php';

  return {
    headless,
    source: {
      baseUrl: get('AIE_SOURCE_URL', 'http://aie.local'),
      username: get('AIE_SOURCE_ADMIN_USER', 'admin'),
      password: get('AIE_SOURCE_ADMIN_PASSWORD', 'admin'),
      wpPath: String(get('AIE_SOURCE_WP_PATH', sourceWpPathDefault)),
    },
    target: {
      baseUrl: get('AIE_TARGET_URL', 'http://aie2.local'),
      username: get('AIE_TARGET_ADMIN_USER', 'admin'),
      password: get('AIE_TARGET_ADMIN_PASSWORD', 'admin'),
      wpPath: String(get('AIE_TARGET_WP_PATH', targetWpPathGuess || sourceWpPathDefault)),
    },
    localPhp: String(get('AIE_LOCAL_PHP', localPhpDefault)),
    wpBin: String(get('AIE_WP_BIN', '/opt/homebrew/bin/wp')),
  };
}

function wp(env, args, { trim = true } = {}) {
  const out = execFileSync(env.localPhp, [env.wpBin, `--path=${env.wpPath}`, ...args], {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  });
  return trim ? String(out).trim() : String(out);
}

function wpEval(env, code) {
  return wp(env, ['eval', code], { trim: true });
}

function wpEvalJson(env, code) {
  const raw = wp(env, ['eval', code], { trim: true });
  try {
    return JSON.parse(raw || 'null');
  } catch {
    return null;
  }
}

function ensureSiteApiKey(env) {
  const code = `
$k = get_option('aie_site_api_key');
if (!$k) {
  $k = bin2hex(random_bytes(16));
  update_option('aie_site_api_key', $k);
}
echo $k;
`;
  return wpEval(env, code);
}

function pickOnePostId(env, postType, { status = 'publish' } = {}) {
  const code = `
$args = [
  'post_type' => ${JSON.stringify(postType)},
  'posts_per_page' => 1,
  'post_status' => ${JSON.stringify(status)},
  'orderby' => 'ID',
  'order' => 'ASC',
];
$posts = get_posts($args);
echo (!empty($posts)) ? $posts[0]->ID : '';
`;
  return wpEval(env, code);
}

function ensureFeaturedImage(env, postId) {
  const code = `
$pid = (int) ${JSON.stringify(String(postId))};
$att = get_posts(['post_type'=>'attachment','posts_per_page'=>1,'post_status'=>'inherit','orderby'=>'ID','order'=>'ASC']);
if (empty($att)) { echo ''; return; }
$attId = (int) $att[0]->ID;
set_post_thumbnail($pid, $attId);
echo $attId;
`;
  return wpEval(env, code);
}

function getAttachmentMd5(env, attachmentId) {
  const code = `
$id = (int) ${JSON.stringify(String(attachmentId))};
$path = get_attached_file($id);
if (!$path || !file_exists($path)) { echo ''; return; }
echo md5_file($path);
`;
  return wpEval(env, code);
}

function countAttachments(env) {
  const code = `
global $wpdb;
$n = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment'");
echo (string) $n;
`;
  return Number(wpEval(env, code) || '0');
}

function countAttachmentsByFileHash(env, fileHash) {
  const code = `
global $wpdb;
$h = ${JSON.stringify(String(fileHash || ''))};
if (!$h) { echo '0'; return; }
$n = (int) $wpdb->get_var(
  $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
     INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
     WHERE p.post_type = 'attachment'
       AND pm.meta_key = '_aie_file_hash'
       AND pm.meta_value = %s",
    $h
  )
);
echo (string) $n;
`;
  return Number(wpEval(env, code) || '0');
}

function findPostOnTargetByOriginalId(envTarget, sourcePostId, postType) {
  const code = `
$sid = (int) ${JSON.stringify(String(sourcePostId))};
$pt = ${JSON.stringify(String(postType))};
$posts = get_posts([
  'post_type' => $pt,
  'posts_per_page' => 1,
  'post_status' => 'any',
  'meta_query' => [[
    'key' => '_aie_original_post_id',
    'value' => $sid,
  ]],
]);
echo (!empty($posts)) ? (string) $posts[0]->ID : '';
`;
  return wpEval(envTarget, code);
}

function getPostMetaBulk(env, postId, metaKeys) {
  const keys = Array.from(new Set(metaKeys.filter(Boolean)));
  const code = `
$pid = (int) ${JSON.stringify(String(postId))};
$keys = json_decode(${JSON.stringify(JSON.stringify(keys))}, true);
$out = [];
foreach ($keys as $k) {
  $out[$k] = get_post_meta($pid, $k, true);
}
echo wp_json_encode($out);
`;
  const raw = wpEval(env, code);
  try {
    return JSON.parse(raw || '{}');
  } catch {
    return {};
  }
}

function fillAllAcfFields(env, postId, { prefix, attachmentIds, referencePostId } = {}) {
  const attIds = Array.isArray(attachmentIds) ? attachmentIds.map((x) => String(x)).filter(Boolean) : [];
  const code = `
$pid = (int) ${JSON.stringify(String(postId))};
$prefix = ${JSON.stringify(String(prefix || 'AIE_SYNC_'))};
$ref_post_id = (int) ${JSON.stringify(String(referencePostId || postId))};
$att_ids = json_decode(${JSON.stringify(JSON.stringify(attIds))}, true);
if (!is_array($att_ids)) { $att_ids = []; }

if (!function_exists('get_field_objects') || !function_exists('update_field')) {
  echo 'ok';
  return;
}

function aie_first_choice_value($choices) {
  if (!is_array($choices) || empty($choices)) return '';
  foreach ($choices as $k => $v) {
    if ($k !== '' && $k !== null) return (string) $k;
  }
  foreach ($choices as $k => $v) return (string) $v;
  return '';
}

function aie_first_attachment_id($att_ids) {
  if (!empty($att_ids)) return (int) $att_ids[0];
  $a = get_posts(['post_type'=>'attachment','posts_per_page'=>1,'post_status'=>'inherit','orderby'=>'ID','order'=>'ASC','fields'=>'ids']);
  return !empty($a) ? (int) $a[0] : 0;
}

function aie_attachment_ids($att_ids, $count = 2) {
  $out = [];
  foreach ($att_ids as $id) {
    $id = (int) $id;
    if ($id > 0) $out[] = $id;
    if (count($out) >= $count) break;
  }
  if (count($out) >= 1) return $out;
  $a = get_posts(['post_type'=>'attachment','posts_per_page'=>$count,'post_status'=>'inherit','orderby'=>'ID','order'=>'ASC','fields'=>'ids']);
  foreach ($a as $id) $out[] = (int) $id;
  return $out;
}

function aie_term_ids_for_taxonomy($taxonomy, $count = 1) {
  if (!$taxonomy || !taxonomy_exists($taxonomy)) return [];
  $terms = get_terms(['taxonomy'=>$taxonomy,'hide_empty'=>false,'number'=>$count]);
  if (!is_wp_error($terms) && !empty($terms)) {
    return array_map(fn($t) => (int) $t->term_id, $terms);
  }
  // If taxonomy has no terms and it's hierarchical, try inserting one.
  $created = wp_insert_term('AIE Sync Term', $taxonomy, ['slug' => 'aie-sync-term']);
  if (!is_wp_error($created) && !empty($created['term_id'])) return [ (int) $created['term_id'] ];
  return [];
}

function aie_user_id() {
  $u = get_users(['number'=>1,'orderby'=>'ID','order'=>'ASC','fields'=>'ids']);
  return !empty($u) ? (int) $u[0] : 0;
}

function aie_permalink_or_id($post_id, $return_format) {
  $return_format = (string) $return_format;
  if ($return_format === 'id') return (int) $post_id;
  if ($return_format === 'object') return get_post((int) $post_id);
  // default: url
  return get_permalink((int) $post_id);
}

function aie_gen_value($field, $ctx) {
  $type = isset($field['type']) ? (string) $field['type'] : '';
  $name = isset($field['name']) ? (string) $field['name'] : '';
  $stamp = isset($ctx['stamp']) ? (string) $ctx['stamp'] : '';
  $prefix = isset($ctx['prefix']) ? (string) $ctx['prefix'] : 'AIE_SYNC_';

  // Non-value containers
  if (in_array($type, ['tab','accordion','message'], true)) return null;

  $base = substr($prefix . $name . '_' . $stamp, 0, 180);
  $safe = preg_replace('/[^a-z0-9]+/i', '-', $base);
  $safe = strtolower(trim($safe, '-'));
  $safe = substr($safe, 0, 40);

  if (in_array($type, ['text','textarea','wysiwyg','password','email','url'], true)) {
    if ($type === 'email') return 'aie-sync+' . $safe . '@example.com';
    if ($type === 'url') return 'https://example.com/' . $safe;
    return $base;
  }

  if (in_array($type, ['number','range'], true)) return 42;
  if ($type === 'true_false') return 1;
  if ($type === 'date_picker') return gmdate('Y-m-d');
  if ($type === 'date_time_picker') return gmdate('Y-m-d H:i:s');
  if ($type === 'time_picker') return '12:34';
  if ($type === 'color_picker') return '#00ff00';
  if ($type === 'oembed') return 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

  if ($type === 'google_map') {
    return [
      'address' => 'Kyiv, Ukraine',
      'lat' => 50.4501,
      'lng' => 30.5234,
      'zoom' => 12,
    ];
  }

  if ($type === 'link') {
    return [
      'title' => 'AIE Sync Link',
      'url' => 'https://example.com/' . $safe,
      'target' => '_blank',
    ];
  }

  if (in_array($type, ['select','radio','button_group','icon_picker'], true)) {
    $choices = isset($field['choices']) ? $field['choices'] : [];
    $v = aie_first_choice_value($choices);
    if ($type === 'icon_picker') {
      // ACF icon_picker stores an array with { type, value } even when return_format=string.
      // Using a raw string can break rendering (field expects array offsets).
      return [
        'type'  => 'dashicons',
        'value' => $v !== '' ? $v : 'dashicons-admin-site',
      ];
    }
    return $v;
  }

  if ($type === 'checkbox') {
    $choices = isset($field['choices']) ? $field['choices'] : [];
    $v = aie_first_choice_value($choices);
    return $v !== '' ? [ $v ] : [];
  }

  if (in_array($type, ['image','file'], true)) {
    return aie_first_attachment_id($ctx['att_ids']);
  }

  if ($type === 'gallery') {
    return aie_attachment_ids($ctx['att_ids'], 2);
  }

  if ($type === 'taxonomy') {
    $tax = isset($field['taxonomy']) ? (string) $field['taxonomy'] : '';
    $multiple = !empty($field['multiple']);
    $ids = aie_term_ids_for_taxonomy($tax, $multiple ? 2 : 1);
    if ($multiple) return $ids;
    return !empty($ids) ? (int) $ids[0] : 0;
  }

  if ($type === 'user') return aie_user_id();

  if (in_array($type, ['post_object','relationship'], true)) {
    $allow_multiple = $type === 'relationship' || !empty($field['multiple']);
    if ($allow_multiple) return [ (int) $ctx['ref_post_id'] ];
    return (int) $ctx['ref_post_id'];
  }

  if ($type === 'page_link') {
    $return_format = isset($field['return_format']) ? $field['return_format'] : 'url';
    $multiple = !empty($field['multiple']);
    if ($multiple) return [ aie_permalink_or_id($ctx['ref_post_id'], $return_format) ];
    return aie_permalink_or_id($ctx['ref_post_id'], $return_format);
  }

  if ($type === 'group') {
    $out = [];
    if (!empty($field['sub_fields']) && is_array($field['sub_fields'])) {
      foreach ($field['sub_fields'] as $sf) {
        if (empty($sf['name'])) continue;
        $out[$sf['name']] = aie_gen_value($sf, $ctx);
      }
    }
    return $out;
  }

  if ($type === 'repeater') {
    $rows = [];
    $row = [];
    if (!empty($field['sub_fields']) && is_array($field['sub_fields'])) {
      foreach ($field['sub_fields'] as $sf) {
        if (empty($sf['name'])) continue;
        $row[$sf['name']] = aie_gen_value($sf, $ctx);
      }
    }
    $rows[] = $row;
    return $rows;
  }

  if ($type === 'flexible_content') {
    if (empty($field['layouts']) || !is_array($field['layouts'])) return [];
    $layout = $field['layouts'][0];
    if (empty($layout['name'])) return [];
    $row = [ 'acf_fc_layout' => $layout['name'] ];
    if (!empty($layout['sub_fields']) && is_array($layout['sub_fields'])) {
      foreach ($layout['sub_fields'] as $sf) {
        if (empty($sf['name'])) continue;
        $row[$sf['name']] = aie_gen_value($sf, $ctx);
      }
    }
    return [ $row ];
  }

  // Fallback: try setting a scalar string (some custom field types accept this).
  return $base;
}

$objs = get_field_objects($pid, false, false);
if (!$objs || !is_array($objs)) { echo 'ok'; return; }

$ctx = [
  'prefix' => $prefix,
  'stamp' => gmdate('YmdHis'),
  'att_ids' => $att_ids,
  'ref_post_id' => $ref_post_id,
];

foreach ($objs as $name => $field) {
  if (empty($field['key'])) continue;
  $val = aie_gen_value($field, $ctx);
  // Skip container-only fields (tab/message/accordion), which return null.
  if ($val === null) continue;
  update_field($field['key'], $val, $pid);
}

echo 'ok';
`;

  return wpEval(env, code);
}

function getAcfSnapshot(env, postId) {
  const code = `
$pid = (int) ${JSON.stringify(String(postId))};
if (!function_exists('get_field_objects')) { echo wp_json_encode([]); return; }

function aie_file_md5_for_attachment($id) {
  $id = (int) $id;
  if (!$id) return null;
  $path = get_attached_file($id);
  if (!$path || !file_exists($path)) return null;
  return md5_file($path);
}

function aie_norm_post_ref($id) {
  $id = (int) $id;
  if (!$id) return null;
  $p = get_post($id);
  if (!$p) return null;
  // Use slug-based normalization; the meaning of _aie_original_post_id differs
  // between source (stores remote id) and target (stores source id).
  return 'slug:' . $p->post_type . ':' . $p->post_name;
}

function aie_norm_term_slugs($taxonomy, $value) {
  if (!$taxonomy || !taxonomy_exists($taxonomy)) return [];
  $ids = [];
  if (is_array($value)) {
    foreach ($value as $v) {
      if (is_object($v) && isset($v->term_id)) $ids[] = (int) $v->term_id;
      elseif (is_array($v) && isset($v['term_id'])) $ids[] = (int) $v['term_id'];
      else $ids[] = (int) $v;
    }
  } else {
    if (is_object($value) && isset($value->term_id)) $ids[] = (int) $value->term_id;
    elseif (is_array($value) && isset($value['term_id'])) $ids[] = (int) $value['term_id'];
    else $ids[] = (int) $value;
  }
  $slugs = [];
  foreach ($ids as $id) {
    if (!$id) continue;
    $t = get_term($id, $taxonomy);
    if ($t && !is_wp_error($t) && !empty($t->slug)) $slugs[] = (string) $t->slug;
  }
  sort($slugs);
  return $slugs;
}

function aie_norm_value($field, $value) {
  $type = isset($field['type']) ? (string) $field['type'] : '';

  if (in_array($type, ['tab','accordion','message'], true)) return null;

  if ($type === 'icon_picker') {
    if (is_array($value) && isset($value['value'])) return (string) $value['value'];
    return is_scalar($value) ? (string) $value : $value;
  }

  if (in_array($type, ['text','textarea','wysiwyg','password','email','url','oembed','date_picker','date_time_picker','time_picker','color_picker','select','radio','button_group'], true)) {
    return is_scalar($value) ? (string) $value : $value;
  }
  if (in_array($type, ['number','range'], true)) return is_numeric($value) ? 0 + $value : $value;
  if ($type === 'true_false') return (bool) $value;

  if ($type === 'google_map') {
    if (!is_array($value)) return $value;
    return [
      'address' => isset($value['address']) ? (string) $value['address'] : '',
      'lat' => isset($value['lat']) ? 0 + $value['lat'] : null,
      'lng' => isset($value['lng']) ? 0 + $value['lng'] : null,
    ];
  }

  if ($type === 'link') {
    if (!is_array($value)) return $value;
    return [
      'title' => isset($value['title']) ? (string) $value['title'] : '',
      'url' => isset($value['url']) ? (string) $value['url'] : '',
      'target' => isset($value['target']) ? (string) $value['target'] : '',
    ];
  }

  if (in_array($type, ['image','file'], true)) {
    $id = 0;
    if (is_numeric($value)) $id = (int) $value;
    elseif (is_array($value) && isset($value['ID'])) $id = (int) $value['ID'];
    elseif (is_object($value) && isset($value->ID)) $id = (int) $value->ID;
    return aie_file_md5_for_attachment($id);
  }

  if ($type === 'gallery') {
    $hashes = [];
    if (is_array($value)) {
      foreach ($value as $v) {
        $id = 0;
        if (is_numeric($v)) $id = (int) $v;
        elseif (is_array($v) && isset($v['ID'])) $id = (int) $v['ID'];
        elseif (is_object($v) && isset($v->ID)) $id = (int) $v->ID;
        $h = aie_file_md5_for_attachment($id);
        if ($h) $hashes[] = $h;
      }
    }
    sort($hashes);
    return $hashes;
  }

  if ($type === 'taxonomy') {
    $tax = isset($field['taxonomy']) ? (string) $field['taxonomy'] : '';
    return aie_norm_term_slugs($tax, $value);
  }

  if (in_array($type, ['post_object','relationship'], true)) {
    if (is_array($value)) {
      $out = [];
      foreach ($value as $v) {
        $id = is_object($v) && isset($v->ID) ? (int) $v->ID : (int) $v;
        $out[] = aie_norm_post_ref($id);
      }
      $out = array_values(array_filter($out));
      sort($out);
      return $out;
    }
    $id = is_object($value) && isset($value->ID) ? (int) $value->ID : (int) $value;
    return aie_norm_post_ref($id);
  }

  if ($type === 'page_link') {
    if (is_array($value)) {
      $out = array_map('strval', $value);
      $out = array_map(
        function ( $v ) {
          $parts = is_string( $v ) ? wp_parse_url( $v ) : null;
          return ( $parts && isset( $parts['path'] ) ) ? (string) $parts['path'] : (string) $v;
        },
        $out
      );
      sort($out);
      return $out;
    }
    if ( is_scalar( $value ) ) {
      $v = (string) $value;
      $parts = wp_parse_url( $v );
      return ( $parts && isset( $parts['path'] ) ) ? (string) $parts['path'] : $v;
    }
    return $value;
  }

  if ($type === 'user') {
    $id = 0;
    if (is_numeric($value)) $id = (int) $value;
    elseif (is_object($value) && isset($value->ID)) $id = (int) $value->ID;
    $u = $id ? get_user_by('id', $id) : null;
    return $u ? (string) $u->user_login : null;
  }

  if ($type === 'checkbox') {
    $out = [];
    if (is_array($value)) $out = array_map('strval', $value);
    elseif ($value !== null && $value !== '') $out = [ (string) $value ];
    sort($out);
    return $out;
  }

  if ($type === 'group') {
    if (!is_array($value)) return $value;
    $out = [];
    foreach (($field['sub_fields'] ?? []) as $sf) {
      if (empty($sf['name'])) continue;
      $out[$sf['name']] = aie_norm_value($sf, $value[$sf['name']] ?? null);
    }
    ksort($out);
    return $out;
  }

  if ($type === 'repeater') {
    if (!is_array($value)) return $value;
    $rows = [];
    foreach ($value as $row) {
      if (!is_array($row)) continue;
      $outRow = [];
      foreach (($field['sub_fields'] ?? []) as $sf) {
        if (empty($sf['name'])) continue;
        $outRow[$sf['name']] = aie_norm_value($sf, $row[$sf['name']] ?? null);
      }
      ksort($outRow);
      $rows[] = $outRow;
    }
    return $rows;
  }

  if ($type === 'flexible_content') {
    if (!is_array($value)) return $value;
    $layoutMap = [];
    foreach (($field['layouts'] ?? []) as $l) {
      if (!empty($l['name'])) $layoutMap[$l['name']] = $l;
    }
    $rows = [];
    foreach ($value as $row) {
      if (!is_array($row)) continue;
      $layoutName = isset($row['acf_fc_layout']) ? (string) $row['acf_fc_layout'] : '';
      $layout = $layoutMap[$layoutName] ?? null;
      $outRow = [ 'acf_fc_layout' => $layoutName ];
      if ($layout && !empty($layout['sub_fields'])) {
        foreach ($layout['sub_fields'] as $sf) {
          if (empty($sf['name'])) continue;
          $outRow[$sf['name']] = aie_norm_value($sf, $row[$sf['name']] ?? null);
        }
      }
      ksort($outRow);
      $rows[] = $outRow;
    }
    return $rows;
  }

  return $value;
}

$objs = get_field_objects($pid, false, true);
if (!$objs || !is_array($objs)) { echo wp_json_encode([]); return; }
$out = [];
foreach ($objs as $name => $field) {
  if (empty($field['type'])) continue;
  $norm = aie_norm_value($field, $field['value'] ?? null);
  if ($norm === null) continue;
  $out[(string) $name] = [ 'type' => (string) $field['type'], 'value' => $norm ];
}
ksort($out);
echo wp_json_encode($out);
`;

  return wpEvalJson(env, code) || {};
}

function diffAcfSnapshots(expected, actual) {
  const mismatches = [];
  for (const [name, e] of Object.entries(expected || {})) {
    const a = actual ? actual[name] : undefined;
    const eVal = e?.value;
    const aVal = a?.value;
    if (a === undefined) {
      mismatches.push({ field: name, expected: e, actual: null });
      continue;
    }
    if (JSON.stringify(eVal) !== JSON.stringify(aVal)) {
      mismatches.push({ field: name, expected: e, actual: a });
    }
  }
  return mismatches;
}

async function ensureLoggedIn(page, { baseUrl, username, password }) {
  const isLogin = await page.locator('form#loginform').count();
  if (!isLogin) return;
  await page.fill('#user_login', username);
  await page.fill('#user_pass', password);
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.click('#wp-submit')]);
  await page.waitForSelector('#wpadminbar', { timeout: 30_000 });
}

async function gotoAdmin(page, env, pathWithQuery) {
  // Navigate straight to the target admin URL. If we're not logged in, WordPress
  // will show a login form (often on /wp-login.php with redirect_to).
  for (let attempt = 0; attempt < 3; attempt++) {
    try {
      await page.goto(`${env.baseUrl}${pathWithQuery}`, { waitUntil: 'domcontentloaded' });

      // Handle login if needed, then re-open the target URL in case WP redirected
      // us to /wp-admin/ instead of the original page.
      if (await page.locator('form#loginform').count()) {
        await ensureLoggedIn(page, env);
        await page.goto(`${env.baseUrl}${pathWithQuery}`, { waitUntil: 'domcontentloaded' });
      }
      return;
    } catch (e) {
      const msg = String(e && e.message ? e.message : e);
      if (attempt < 2 && msg.includes('interrupted by another navigation')) {
        await page.waitForLoadState('domcontentloaded').catch(() => null);
        continue;
      }
      throw e;
    }
  }
}

async function waitVisible(page, selector, timeout = 30_000) {
  const loc = page.locator(selector);
  await loc.waitFor({ state: 'visible', timeout });
  return loc;
}

function attachPageDebugging(page, { label } = {}) {
  const prefix = label ? `[${label}] ` : '';
  page.on('dialog', async (dialog) => {
    try {
      lastDialogMessage = `${dialog.type()}: ${dialog.message()}`;
      // eslint-disable-next-line no-console
      console.log(`${prefix}[dialog] ${lastDialogMessage}`);
      await dialog.accept();
    } catch {
      // ignore
    }
  });
  page.on('pageerror', (err) => {
    // eslint-disable-next-line no-console
    console.log(`${prefix}[pageerror] ${String(err && err.message ? err.message : err)}`);
  });
  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      // eslint-disable-next-line no-console
      console.log(`${prefix}[console:error] ${msg.text()}`);
    }
  });

  page.on('requestfailed', (req) => {
    try {
      const failure = req.failure();
      const text = failure && failure.errorText ? failure.errorText : 'unknown';
      // eslint-disable-next-line no-console
      console.log(`${prefix}[requestfailed] ${req.url()} (${text})`);
    } catch {
      // ignore
    }
  });

  page.on('response', (res) => {
    try {
      const status = res.status();
      if (status >= 500) {
        // eslint-disable-next-line no-console
        console.log(`${prefix}[http:${status}] ${res.url()}`);
      }
    } catch {
      // ignore
    }
  });
}

async function waitAdminReady(page) {
  // Prefer a broad, robust readiness check. In some WP admin screens the admin
  // bar can be present but temporarily hidden (nojs/nojq classes) during load.
  await page.locator('form#loginform').waitFor({ state: 'detached', timeout: 30_000 }).catch(() => null);
  await page.locator('#wpwrap').waitFor({ state: 'attached', timeout: 30_000 });
  await page.locator('#wpcontent, #wpbody').first().waitFor({ state: 'attached', timeout: 30_000 });
}

async function ensureConnectedSite(sourcePage, env, targetApiKey) {
  await gotoAdmin(sourcePage, env.source, '/wp-admin/admin.php?page=rsl-ie-content-sync');
  await waitVisible(sourcePage, '#rsl-ie-content-sync');

  // Wait for JS to load sites list at least once.
  await sourcePage.waitForTimeout(750);

  const targetUrl = env.target.baseUrl.replace(/\/+$/, '');
  const rows = sourcePage.locator('#aie-sites-list tr[data-site-id]');
  const rowCount = await rows.count();

  for (let i = 0; i < rowCount; i++) {
    const row = rows.nth(i);
    const urlText = ((await row.locator('.column-url').innerText().catch(() => '')) || '').trim();
    if (urlText.includes(targetUrl)) {
      return String(await row.getAttribute('data-site-id'));
    }
  }

  // Add new site
  await sourcePage.locator('#aie-add-site-btn').click();
  await waitVisible(sourcePage, '#aie-site-modal');
  await sourcePage.fill('#aie-site-name', 'aie2');
  await sourcePage.fill('#aie-site-url', env.target.baseUrl);
  await sourcePage.fill('#aie-site-api-key', targetApiKey);
  await sourcePage.locator('#aie-save-site-btn').click();

  // Wait until modal closes and row appears.
  await sourcePage.waitForFunction(() => {
    const m = document.querySelector('#aie-site-modal');
    if (!m) return true;
    const s = window.getComputedStyle(m);
    return s.display === 'none';
  });

  // Give loadSites() time to render.
  await sourcePage.waitForTimeout(1500);

  const rows2 = sourcePage.locator('#aie-sites-list tr[data-site-id]');
  const rowCount2 = await rows2.count();
  for (let i = 0; i < rowCount2; i++) {
    const row = rows2.nth(i);
    const urlText = ((await row.locator('.column-url').innerText().catch(() => '')) || '').trim();
    if (urlText.includes(targetUrl)) {
      return String(await row.getAttribute('data-site-id'));
    }
  }

  throw new Error('Could not create/find connected site row for target');
}

async function savePost(page) {
  // Classic editor
  const classic = page.locator('#publish');
  if (await classic.count()) {
    if (await classic.isVisible().catch(() => false)) {
      await classic.click();
      // Wait for the classic editor notice (best-effort).
      await Promise.race([
        page.locator('#message.updated, #message.notice-success').waitFor({ state: 'visible', timeout: 15_000 }).catch(() => null),
        page.waitForTimeout(1500),
      ]);
      return;
    }
  }

  // Gutenberg: prefer clicking Update if available, fallback to Cmd/Ctrl+S.
  const updateBtn = page
    .locator('button:has-text("Update"), button:has-text("Обновить")')
    .filter({ hasNot: page.locator('[aria-disabled="true"]') })
    .first();

  if (await updateBtn.count()) {
    if (await updateBtn.isVisible().catch(() => false)) {
      await updateBtn.click({ force: true }).catch(() => {});
      await page.waitForTimeout(1500);
      return;
    }
  }

  await page.keyboard.press('Meta+S').catch(() => {});
  await page.waitForTimeout(2000);
}

async function fillVisibleAcfFields(page, { prefix }) {
  // Collect visible field descriptors in the browser context.
  const descriptors = await page.evaluate(() => {
    const fields = Array.from(document.querySelectorAll('.acf-field'));
    const isVisible = (el) => {
      if (!el) return false;
      const style = window.getComputedStyle(el);
      if (!style) return false;
      if (style.display === 'none' || style.visibility === 'hidden') return false;
      // ACF may hide inputs via CSS; offsetParent is a decent heuristic.
      return !!(el.offsetParent || el.getClientRects().length);
    };
    return fields
      .filter((f) => isVisible(f))
      .map((f) => ({
        key: f.getAttribute('data-key') || '',
        name: f.getAttribute('data-name') || '',
        type: f.getAttribute('data-type') || '',
        label: (f.querySelector('.acf-label label')?.textContent || '').trim(),
      }))
      .filter((d) => d.key && d.name && d.type);
  });

  const filled = [];
  const skipped = [];

  for (const d of descriptors) {
    const root = page.locator(`.acf-field[data-key="${d.key}"]`);

    // Skip non-value fields
    const nonValueTypes = new Set(['tab', 'accordion', 'message']);
    if (nonValueTypes.has(d.type)) {
      skipped.push({ ...d, reason: 'non-value' });
      continue;
    }

    const base = `${prefix}${d.name}`.slice(0, 180);
    const isoDate = new Date().toISOString().slice(0, 10);
    const safeTag = String(base)
      .replace(/[^a-z0-9]+/gi, '-')
      .replace(/(^-|-$)/g, '')
      .slice(0, 40)
      .toLowerCase();

    try {
      if (['text', 'textarea', 'number', 'date_picker', 'date_time_picker', 'time_picker', 'email', 'url'].includes(d.type)) {
        let value = base;
        if (d.type === 'number') value = '42';
        if (d.type === 'date_picker') value = isoDate;
        if (d.type === 'date_time_picker') value = isoDate;
        if (d.type === 'time_picker') value = '12:34';
        if (d.type === 'email') value = `aie-sync+${safeTag}@example.com`;
        if (d.type === 'url') value = `https://example.com/${safeTag}`;

        const input = root.locator('input, textarea').first();
        await input.waitFor({ state: 'attached', timeout: 10_000 });
        await input.fill(value);
        filled.push({ ...d, value, metaKey: d.name });
        continue;
      }

      if (d.type === 'select') {
        const select = root.locator('select').first();
        await select.waitFor({ state: 'attached', timeout: 10_000 });
        // Choose first non-empty option if possible.
        const opt = await select.evaluate((el) => {
          const options = Array.from(el.options || []);
          const nonEmpty = options.find((o) => o.value && o.value !== '0');
          return nonEmpty ? nonEmpty.value : (options[0]?.value || '');
        });
        await select.selectOption(opt);
        filled.push({ ...d, value: opt, metaKey: d.name });
        continue;
      }

      if (d.type === 'true_false') {
        const cb = root.locator('input[type="checkbox"]').first();
        await cb.waitFor({ state: 'attached', timeout: 10_000 });
        await cb.check({ force: true });
        filled.push({ ...d, value: true, metaKey: d.name });
        continue;
      }

      if (d.type === 'radio' || d.type === 'button_group') {
        const radio = root.locator('input[type="radio"]').first();
        await radio.waitFor({ state: 'attached', timeout: 10_000 });
        await radio.check({ force: true });
        const v = await radio.getAttribute('value');
        filled.push({ ...d, value: v || '', metaKey: d.name });
        continue;
      }

      skipped.push({ ...d, reason: `unsupported-type:${d.type}` });
    } catch (e) {
      skipped.push({ ...d, reason: `fill-failed:${String(e && e.message ? e.message : e)}` });
    }
  }

  return { filled, skipped };
}

async function openSyncModal(page) {
  const startedAt = Date.now();
  const urlAtStart = page.url();

  // Ensure we're not mid-navigation; locator.waitFor can be flaky if the page is
  // repeatedly reloading (some screens auto-refresh after sync success).
  await page.waitForLoadState('domcontentloaded').catch(() => null);

  const btn = page.locator('#aie-sync-content-btn').first();
  for (let attempt = 0; attempt < 30; attempt++) {
    if (await btn.count()) break;
    await page.waitForTimeout(1000);
  }

  if (!(await btn.count())) {
    const waitedMs = Date.now() - startedAt;
    const where = page.url();
    const debugPath = path.resolve(process.cwd(), 'e2e', 'artifacts', `content-sync-missing-sync-btn-${Date.now()}.png`);
    await page.screenshot({ path: debugPath, fullPage: true }).catch(() => null);
    throw new Error(
      `Sync button (#aie-sync-content-btn) did not appear after ${waitedMs}ms (urlStart=${urlAtStart}, urlNow=${where}, screenshot=${debugPath})`
    );
  }

  await btn.scrollIntoViewIfNeeded().catch(() => null);
  await btn.click({ force: true });
  await waitVisible(page, '#aie-sync-modal');
}

async function selectSiteInSyncModal(page, siteId) {
  const sel = page.locator('#aie-sync-site-select');
  await sel.waitFor({ state: 'visible', timeout: 30_000 });
  await sel.selectOption(siteId);
}

async function waitForMappingModalReady(page) {
  await waitVisible(page, '#aie-mapping-modal');
  try {
    await waitVisible(page, '#aie-mapping-table-container', 90_000);
  } catch (e) {
    const loadingVisible = await page
      .locator('#aie-mapping-loading')
      .isVisible()
      .catch(() => false);
    const modalVisible = await page
      .locator('#aie-mapping-modal')
      .isVisible()
      .catch(() => false);
    throw new Error(
      `Mapping table did not appear (modalVisible=${modalVisible}, loadingVisible=${loadingVisible}, lastDialog=${lastDialogMessage || 'none'}): ${
        e && e.message ? e.message : String(e)
      }`
    );
  }
  await page.waitForFunction(() => {
    const btn = document.querySelector('#aie-mapping-confirm-btn');
    return btn && !btn.disabled;
  });
}

async function setMappingSelection(page, localId, remoteIdOrNull) {
  if (!remoteIdOrNull) return;
  const local = String(localId);
  const remote = String(remoteIdOrNull);
  await page.evaluate(
    ({ localId: lid, remoteId: rid }) => {
      const $ = window.jQuery;
      const sel = document.querySelector(`select.aie-remote-select[data-local-id="${lid}"]`);
      if (!sel) return;
      // If option doesn't exist, add it.
      if (!sel.querySelector(`option[value="${rid}"]`)) {
        const opt = new Option(`🔄 Update: ID ${rid}`, rid, false, false);
        sel.appendChild(opt);
      }
      sel.value = rid;
      if ($) $(sel).trigger('change');
      sel.dispatchEvent(new Event('change', { bubbles: true }));
    },
    { localId: local, remoteId: remote }
  );
}

async function confirmMappingAndWaitSuccess(page, { timeoutMs = 600_000 } = {}) {
  const confirmBtn = page.locator('#aie-mapping-confirm-btn');
  await confirmBtn.click();

  // Sync modal is shown again with progress and then a success/error notice.
  const success = page.locator('#aie-sync-result.notice-success');
  const error = page.locator('#aie-sync-result.notice-error');
  const anyResult = page.locator('#aie-sync-result');

  await Promise.race([
    success.waitFor({ state: 'visible', timeout: timeoutMs }),
    error.waitFor({ state: 'visible', timeout: timeoutMs }),
    anyResult.waitFor({ state: 'visible', timeout: timeoutMs }),
  ]);

  if (await error.isVisible().catch(() => false)) {
    const msg = (await error.innerText().catch(() => '')).trim();
    throw new Error(`Sync failed: ${msg || 'notice-error'}`);
  }
  if (!(await success.isVisible().catch(() => false))) {
    // If result became visible but didn't get a success/error class, surface it.
    const msg = (await anyResult.innerText().catch(() => '')).trim();
    throw new Error(`Sync result did not become success: ${msg || 'unknown result state'}`);
  }

  // Success auto-reloads the page after ~2s. Wait a bit longer to avoid the
  // scheduled reload interrupting the next navigation.
  const nav = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 25_000 }).catch(() => null);
  await page.waitForTimeout(3500);
  await nav;
  await page.waitForTimeout(250);
}

async function runSinglePostPushPull(sourcePage, env, siteId, postId, postType) {
  await gotoAdmin(sourcePage, env.source, `/wp-admin/post.php?post=${postId}&action=edit`);
  await waitAdminReady(sourcePage);

  const now = new Date();
  const stamp = `${now.toISOString()}-`;

  // Fill *all* ACF fields via ACF API (covers repeaters/flexible, media fields, etc).
  // Still opens the editor in the browser to exercise the "Sync This Post" UI.
  const attachmentIds = (() => {
    const id = ensureFeaturedImage(env.source, postId);
    return id ? [id] : [];
  })();
  fillAllAcfFields(env.source, postId, {
    prefix: `AIE_SYNC_${postType}_${stamp}_`,
    attachmentIds,
    referencePostId: postId,
  });

  // Ensure values are persisted.
  await savePost(sourcePage);

  const expectedSnapshot = getAcfSnapshot(env.source, postId);

  // Push (create or update remote)
  await openSyncModal(sourcePage);
  await selectSiteInSyncModal(sourcePage, siteId);
  await sourcePage.locator('#aie-sync-push-btn').click();
  await waitForMappingModalReady(sourcePage);

  const remoteExisting = findPostOnTargetByOriginalId(env.target, postId, postType);
  await setMappingSelection(sourcePage, postId, remoteExisting || null);
  await confirmMappingAndWaitSuccess(sourcePage);

  const remoteId = findPostOnTargetByOriginalId(env.target, postId, postType);
  if (!remoteId) throw new Error(`Push did not create/update target post for original ${postId} (${postType})`);

  // Verify ACF snapshot on target matches the source snapshot (normalized: attachments by md5, taxonomy by slug, etc).
  const actualSnapshot = getAcfSnapshot(env.target, remoteId);
  const mismatches = diffAcfSnapshots(expectedSnapshot, actualSnapshot);

  // Pull: mutate remote meta and pull back into local post via mapping.
  // Pick first simple scalar ACF field (if any) and change it remotely.
  const firstFieldName = Object.keys(expectedSnapshot || {}).find((k) => {
    const t = expectedSnapshot[k]?.type;
    const v = expectedSnapshot[k]?.value;
    return (
      ['text', 'textarea', 'email', 'url', 'number', 'range', 'true_false', 'color_picker', 'date_picker', 'date_time_picker', 'time_picker', 'wysiwyg'].includes(
        String(t)
      ) && (typeof v === 'string' || typeof v === 'number' || typeof v === 'boolean')
    );
  });
  if (firstFieldName) {
    const remoteNew = `AIE_SYNC_REMOTE_${postType}_${stamp}_${firstFieldName}`.slice(0, 180);
    wpEval(
      env.target,
      `if (function_exists('update_field')) { update_field(${JSON.stringify(firstFieldName)}, ${JSON.stringify(remoteNew)}, ${parseInt(
        remoteId,
        10
      )}); } else { update_post_meta(${parseInt(remoteId, 10)}, ${JSON.stringify(firstFieldName)}, ${JSON.stringify(remoteNew)}); } echo 'ok';`
    );

    await gotoAdmin(sourcePage, env.source, `/wp-admin/post.php?post=${postId}&action=edit`);
    await openSyncModal(sourcePage);
    await selectSiteInSyncModal(sourcePage, siteId);
    await sourcePage.locator('#aie-sync-pull-btn').click();
    await waitForMappingModalReady(sourcePage);
    await setMappingSelection(sourcePage, postId, remoteId);
    await confirmMappingAndWaitSuccess(sourcePage);

    const localAfterPull = getAcfSnapshot(env.source, postId);
    const got = localAfterPull[firstFieldName]?.value;
    if (String(got ?? '') !== String(remoteNew)) {
      mismatches.push({ field: firstFieldName, expected: remoteNew, actual: got, direction: 'pull' });
    }
  }

  return { remoteId, mismatches };
}

async function runSinglePostPushOnly(sourcePage, env, siteId, postId, postType) {
  await gotoAdmin(sourcePage, env.source, `/wp-admin/post.php?post=${postId}&action=edit`);
  await waitAdminReady(sourcePage);

  const now = new Date();
  const stamp = `${now.toISOString()}-`;

  const attachmentIds = (() => {
    const id = ensureFeaturedImage(env.source, postId);
    return id ? [id] : [];
  })();
  fillAllAcfFields(env.source, postId, {
    prefix: `AIE_SYNC_${postType}_${stamp}_`,
    attachmentIds,
    referencePostId: postId,
  });
  await savePost(sourcePage);
  const expectedSnapshot = getAcfSnapshot(env.source, postId);

  await openSyncModal(sourcePage);
  await selectSiteInSyncModal(sourcePage, siteId);
  await sourcePage.locator('#aie-sync-push-btn').click();
  await waitForMappingModalReady(sourcePage);

  const remoteExisting = findPostOnTargetByOriginalId(env.target, postId, postType);
  await setMappingSelection(sourcePage, postId, remoteExisting || null);
  await confirmMappingAndWaitSuccess(sourcePage);

  const remoteId = findPostOnTargetByOriginalId(env.target, postId, postType);
  if (!remoteId) throw new Error(`Push did not create/update target post for original ${postId} (${postType})`);

  const actualSnapshot = getAcfSnapshot(env.target, remoteId);
  const mismatches = diffAcfSnapshots(expectedSnapshot, actualSnapshot);

  return { remoteId, mismatches };
}

async function runBulkNoSelectionBrowseFlow(sourcePage, env, siteId) {
  await gotoAdmin(sourcePage, env.source, '/wp-admin/edit.php');
  await waitAdminReady(sourcePage);

  // Ensure no post checkboxes are selected.
  await sourcePage.evaluate(() => {
    document.querySelectorAll('tbody .check-column input[type="checkbox"]:checked').forEach((x) => (x.checked = false));
  });

  // Click Sync Content, should open Choose Site modal (and then Browse modal after selecting a site).
  await openSyncModal(sourcePage);
  await selectSiteInSyncModal(sourcePage, siteId);

  // Browse modal should appear automatically.
  await waitVisible(sourcePage, '#aie-browse-modal');
  await waitVisible(sourcePage, '#aie-browse-search');
  await waitVisible(sourcePage, '#aie-browse-pull-btn');

  // Close browse modal via X (closes everything).
  await sourcePage.locator('#aie-browse-modal .aie-modal-close').click();
  await sourcePage.waitForTimeout(500);
}

async function runBulkSyncPushUpdate(sourcePage, env, siteId, postId, remoteId) {
  await gotoAdmin(sourcePage, env.source, '/wp-admin/edit.php');
  await waitAdminReady(sourcePage);

  // Select a post checkbox.
  const cb = sourcePage.locator(`tbody .check-column input[type="checkbox"][value="${postId}"]`).first();
  await cb.waitFor({ state: 'attached', timeout: 30_000 });
  await cb.check({ force: true });

  await openSyncModal(sourcePage);
  await selectSiteInSyncModal(sourcePage, siteId);
  await sourcePage.locator('#aie-sync-push-btn').click();
  await waitForMappingModalReady(sourcePage);
  await setMappingSelection(sourcePage, postId, remoteId || null);
  await confirmMappingAndWaitSuccess(sourcePage);
}

async function main() {
  const env0 = loadEnv();
  const env = {
    ...env0,
    source: { ...env0.source, localPhp: env0.localPhp, wpBin: env0.wpBin },
    target: { ...env0.target, localPhp: env0.localPhp, wpBin: env0.wpBin },
  };

  // Ensure API key exists on target so source can connect.
  const targetKey = ensureSiteApiKey(env.target);

  const browser = await chromium.launch({ headless: env.headless });
  const sourceCtx = await browser.newContext();
  const sourcePage = await sourceCtx.newPage();
  attachPageDebugging(sourcePage, { label: 'source' });

  try {
    const siteId = await ensureConnectedSite(sourcePage, env, targetKey);
    console.log(`[content-sync] connected site_id=${siteId}`);

    // Prepare IDs per post type (source side).
    const ids = {
      post: '1',
      page: pickOnePostId(env.source, 'page') || '',
      portfolio: pickOnePostId(env.source, 'portfolio') || '',
      product: pickOnePostId(env.source, 'product') || '',
    };

    // Media baseline (source featured image for post=1).
    const featuredAtt = ensureFeaturedImage(env.source, ids.post);
    const imgHash = getAttachmentMd5(env.source, featuredAtt);
    const targetAttBefore = countAttachments(env.target);
    const sourceAttBefore = countAttachments(env.source);
    const targetHashBefore = countAttachmentsByFileHash(env.target, imgHash);
    const sourceHashBefore = countAttachmentsByFileHash(env.source, imgHash);

    // Single post push+pull (post=1)
    const single = await runSinglePostPushPull(sourcePage, env, siteId, ids.post, 'post');
    const targetAttAfterPushPull = countAttachments(env.target);
    const sourceAttAfterPull = countAttachments(env.source);
    const targetHashAfterPushPull = countAttachmentsByFileHash(env.target, imgHash);
    const sourceHashAfterPull = countAttachmentsByFileHash(env.source, imgHash);

    // Repeat push (update mapping) to ensure media doesn't duplicate on target.
    await gotoAdmin(sourcePage, env.source, `/wp-admin/post.php?post=${ids.post}&action=edit`);
    await openSyncModal(sourcePage);
    await selectSiteInSyncModal(sourcePage, siteId);
    await sourcePage.locator('#aie-sync-push-btn').click();
    await waitForMappingModalReady(sourcePage);
    await setMappingSelection(sourcePage, ids.post, single.remoteId);
    await confirmMappingAndWaitSuccess(sourcePage);
    const targetAttAfterRepeatPush = countAttachments(env.target);
    const targetHashAfterRepeatPush = countAttachmentsByFileHash(env.target, imgHash);

    // Bulk sync (no selection -> browse modal)
    await runBulkNoSelectionBrowseFlow(sourcePage, env, siteId);

    // Bulk push update for post=1 with explicit mapping
    await runBulkSyncPushUpdate(sourcePage, env, siteId, ids.post, single.remoteId);

    // Push other post types (ACF fill on source; verify exists remotely)
    const extraTypes = [
      { type: 'page', id: ids.page },
      { type: 'portfolio', id: ids.portfolio },
      { type: 'product', id: ids.product },
    ].filter((x) => x.id);

    const extraIssues = [];
    for (const t of extraTypes) {
      // Ensure featured image too (helps exercise media sync paths for CPTs).
      ensureFeaturedImage(env.source, t.id);
      const r = await runSinglePostPushOnly(sourcePage, env, siteId, t.id, t.type);
      if (r.mismatches.length) {
        console.log(`[${t.type}] mismatches:\n${JSON.stringify(r.mismatches, null, 2)}`);
        extraIssues.push({ area: `${t.type}=${t.id}`, mismatches: r.mismatches });
      } else {
        console.log(`[${t.type}] ok (remoteId=${r.remoteId})`);
      }
    }

    const issues = [];
    if (single.mismatches.length) issues.push({ area: 'post=1', mismatches: single.mismatches });
    if (extraIssues.length) issues.push(...extraIssues);
    // Target: initial push may add media; repeat push should not add more.
    if (targetAttAfterRepeatPush > targetAttAfterPushPull) {
      issues.push({
        area: 'media-dedup-target',
        before: targetAttAfterPushPull,
        after: targetAttAfterRepeatPush,
      });
    }
    // Hash-based dedup: the same file hash must not create multiple attachments.
    if (targetHashAfterRepeatPush > Math.max(1, targetHashBefore)) {
      issues.push({
        area: 'media-dedup-target-hash',
        hash: imgHash,
        before: targetHashBefore,
        after: targetHashAfterRepeatPush,
      });
    }
    // Source: pull back should not add media because image already exists locally.
    if (sourceAttAfterPull > sourceAttBefore) {
      issues.push({
        area: 'media-dedup-source',
        before: sourceAttBefore,
        after: sourceAttAfterPull,
      });
    }
    if (sourceHashAfterPull > Math.max(1, sourceHashBefore)) {
      issues.push({
        area: 'media-dedup-source-hash',
        hash: imgHash,
        before: sourceHashBefore,
        after: sourceHashAfterPull,
      });
    }

    console.log(
      `[media] md5=${imgHash} targetAttachments before=${targetAttBefore} afterPushPull=${targetAttAfterPushPull} afterRepeatPush=${targetAttAfterRepeatPush} hashCount before=${targetHashBefore} afterPushPull=${targetHashAfterPushPull} afterRepeatPush=${targetHashAfterRepeatPush}`
    );
    console.log(
      `[media] md5=${imgHash} sourceAttachments before=${sourceAttBefore} afterPull=${sourceAttAfterPull} hashCount before=${sourceHashBefore} afterPull=${sourceHashAfterPull}`
    );

    if (issues.length) {
      console.log('\nIssues found:', JSON.stringify(issues, null, 2));
      process.exitCode = 1;
    } else {
      console.log('\nSummary: all checks passed');
    }
  } finally {
    await sourceCtx.close();
    await browser.close();
  }
}

main().catch((err) => {
  // eslint-disable-next-line no-console
  console.error(err);
  process.exitCode = 1;
});
