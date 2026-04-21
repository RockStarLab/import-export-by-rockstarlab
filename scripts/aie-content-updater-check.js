/**
 * Manual E2E (Playwright): run Content Updater for each available content type,
 * apply a known library snippet (uppercase) to a single record, and verify that
 * the resulting value matches the expected pipeline output.
 *
 * Usage:
 *   node scripts/aie-content-updater-check.js
 *
 * Env (defaults read from .env.e2e if present):
 *   AIE_SOURCE_URL, AIE_SOURCE_ADMIN_USER, AIE_SOURCE_ADMIN_PASSWORD
 *   AIE_HEADLESS=true|false
 *   AIE_SOURCE_WP_PATH=/path/to/wp/root
 *   AIE_LOCAL_PHP=/path/to/php (Local.app bundled PHP works well)
 *   AIE_WP_BIN=/path/to/wp (wp-cli phar wrapper)
 */

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');

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
  if (fs.existsSync(envPath)) {
    fileEnv = parseDotEnv(fs.readFileSync(envPath, 'utf8'));
  }
  const get = (key, fallback) => process.env[key] ?? fileEnv[key] ?? fallback;

  const headlessRaw = String(get('AIE_HEADLESS', 'true')).toLowerCase();
  const headless = headlessRaw === '1' || headlessRaw === 'true' || headlessRaw === 'yes';

  const wpPathDefault = path.resolve(process.cwd(), '../../..');
  const localPhpDefault =
    '/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php';

  return {
    baseUrl: get('AIE_SOURCE_URL', 'http://aie.local'),
    username: get('AIE_SOURCE_ADMIN_USER', 'admin'),
    password: get('AIE_SOURCE_ADMIN_PASSWORD', 'admin'),
    headless,
    wpPath: String(get('AIE_SOURCE_WP_PATH', wpPathDefault)),
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
  return wp(env, ['eval', code]);
}

function ensureUpdaterTestTable(env) {
  const code = `
global $wpdb;
$table = $wpdb->prefix . 'aie_updater_test';
$charset = $wpdb->get_charset_collate();
$wpdb->query("CREATE TABLE IF NOT EXISTS \`$table\` (id BIGINT UNSIGNED NOT NULL PRIMARY KEY, value VARCHAR(255) NOT NULL) $charset");
$wpdb->replace($table, ['id' => 1, 'value' => 'Hello world'], ['%d','%s']);
echo $table;
`;
  return wpEval(env, code);
}

function readDbTestValue(env) {
  const code = `
global $wpdb;
$table = $wpdb->prefix . 'aie_updater_test';
$v = $wpdb->get_var($wpdb->prepare("SELECT value FROM \`$table\` WHERE id=%d", 1));
echo is_string($v) ? $v : '';
`;
  return wpEval(env, code);
}

function pickOneId(env, kind) {
  switch (kind) {
    case 'post':
      return wp(env, [
        'post',
        'list',
        '--post_type=post',
        '--post_status=publish',
        '--ignore_sticky_posts=1',
        '--posts_per_page=1',
        '--format=ids',
      ]);
    case 'page':
      return wp(env, ['post', 'list', '--post_type=page', '--post_status=publish', '--posts_per_page=1', '--format=ids']);
    case 'portfolio':
      return wp(env, [
        'post',
        'list',
        '--post_type=portfolio',
        '--post_status=publish',
        '--posts_per_page=1',
        '--format=ids',
      ]);
	    case 'product':
	      return wp(env, [
	        'post',
	        'list',
	        '--post_type=product',
	        '--post_status=publish',
	        '--posts_per_page=1',
	        '--format=ids',
	      ]);
	    case 'media':
	      return wp(env, ['post', 'list', '--post_type=attachment', '--post_status=inherit', '--posts_per_page=1', '--format=ids']);
	    case 'woo_order':
	      return wpEval(
	        env,
	        `
if (!function_exists('wc_get_orders')) { echo ''; return; }
$ids = wc_get_orders(['limit'=>1,'orderby'=>'ID','order'=>'ASC','return'=>'ids']);
echo (!empty($ids)) ? (string) $ids[0] : '';
`
	      );
	    case 'woo_coupon':
	      return wp(env, ['post', 'list', '--post_type=shop_coupon', '--post_status=any', '--posts_per_page=1', '--format=ids']);
	    case 'menu_term':
	      return wp(env, ['term', 'list', 'nav_menu', '--number=1', '--format=ids']);
	    case 'comment':
	      return wp(env, ['comment', 'list', '--number=1', '--format=ids']);
    case 'user':
      return wp(env, ['user', 'list', '--number=1', '--format=ids']);
    case 'category_term':
      return wp(env, ['term', 'list', 'category', '--number=1', '--format=ids']);
    default:
      throw new Error(`Unknown kind: ${kind}`);
	}
}

function readFieldValue(env, { type, id, taxonomy, field }) {
  if (type === 'post') {
    const f = field || 'post_title';
    return wp(env, ['post', 'get', String(id), `--field=${f}`], { trim: false }).trim();
  }
  if (type === 'woo_order') {
    const orderId = String(id || '').trim();
    const f = String(field || 'billing_first_name');
    const code = `
$id = (int) ${JSON.stringify(orderId)};
$field = ${JSON.stringify(f)};
if (!$id || !function_exists('wc_get_order')) { echo ''; return; }
$o = wc_get_order($id);
if (!$o) { echo ''; return; }
switch ($field) {
  case 'order_key':
    echo (string) $o->get_order_key();
    return;
  case 'billing_first_name':
    echo (string) $o->get_billing_first_name();
    return;
  default:
    echo (string) $o->get_meta($field, true, 'edit');
    return;
}
`;
    return wpEval(env, code).trim();
  }
  if (type === 'user') return wp(env, ['user', 'get', String(id), '--field=display_name'], { trim: false }).trim();
  if (type === 'comment') {
    const f = field || 'comment_author';
    return wp(env, ['comment', 'get', String(id), `--field=${f}`], { trim: false }).trim();
  }
  if (type === 'term') {
    const f = field || 'name';
    return wp(env, ['term', 'get', String(taxonomy), String(id), `--field=${f}`], { trim: false }).trim();
  }
  if (type === 'db_test') return readDbTestValue(env);
  throw new Error(`Unknown readFieldValue type: ${type}`);
}

async function ensureLoggedIn(page, { baseUrl, username, password }) {
  await page.goto(`${baseUrl}/wp-admin/`, { waitUntil: 'domcontentloaded' });
  const isLogin = await page.locator('form#loginform').count();
  if (!isLogin) return;

  await page.fill('#user_login', username);
  await page.fill('#user_pass', password);
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.click('#wp-submit')]);
  await page.waitForSelector('#wpadminbar', { timeout: 30_000 });
}

async function gotoContentUpdater(page, env) {
  await ensureLoggedIn(page, env);
  await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=rsl-ie-content-updater`, { waitUntil: 'domcontentloaded' });
  if (await page.locator('form#loginform').count()) {
    await ensureLoggedIn(page, env);
    await page.goto(`${env.baseUrl}/wp-admin/admin.php?page=rsl-ie-content-updater`, { waitUntil: 'domcontentloaded' });
  }
  await page.waitForSelector('#rsl-ie-content-updater', { timeout: 30_000 });
}

async function handleBackupModalIfPresent(page) {
  const overlay = page.locator('.aie-backup-warning-overlay');
  if (!(await overlay.count())) return;
  await overlay.waitFor({ state: 'visible', timeout: 15_000 });

  const created = page.locator('#aie-backup-created');
  const dontShow = page.locator('#aie-backup-dont-show');
  if (await created.count()) await created.check({ force: true });
  if (await dontShow.count()) await dontShow.check({ force: true });
  await page.locator('.aie-backup-confirm').click();
  await overlay.waitFor({ state: 'detached', timeout: 15_000 });
}

async function clickNextStep(page) {
  const btn = page.locator('.aie-step.active .aie-updater-next-step');
  await btn.waitFor({ state: 'visible', timeout: 30_000 });
  await page.waitForFunction(() => {
    const el = document.querySelector('.aie-step.active .aie-updater-next-step');
    return el && !el.disabled;
  });
  await btn.click();
  // Step 1 -> 2 shows the backup warning modal; handle it if present.
  await handleBackupModalIfPresent(page);
}

async function waitForUpdaterStep(page, stepNumber) {
  const step = page.locator(`.aie-updater-step-${stepNumber}.active`);
  await step.waitFor({ state: 'attached', timeout: 30_000 });
  // Wait until the step is actually visible (active step uses display toggling).
  await page.waitForFunction(
    ({ sel }) => {
      const el = document.querySelector(sel);
      if (!el) return false;
      const style = window.getComputedStyle(el);
      return style && style.display !== 'none' && style.visibility !== 'hidden';
    },
    { sel: `.aie-updater-step-${stepNumber}.active` },
    { timeout: 30_000 }
  );
}

async function clickStartNewUpdate(page) {
  const btn = page.locator('.aie-start-new-update');
  if (!(await btn.count())) return;
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), btn.click()]);
}

async function addFilterRow(page) {
  await waitForUpdaterStep(page, 2);
  await page.locator('.aie-updater-step-2.active .aie-updater-add-filter').click();
  const rows = page.locator('.aie-updater-step-2.active #aie-updater-filters-list .aie-filter-row');
  const idx = (await rows.count()) - 1;
  return rows.nth(idx);
}

async function setFilterStandard(row, { field, condition, value }) {
  const fieldSelect = row.locator('select.aie-updater-filter-field');
  await fieldSelect.selectOption(String(field));
  const condSelect = row.locator('select.aie-updater-filter-condition');
  await condSelect.waitFor({ state: 'visible', timeout: 30_000 });
  await condSelect.selectOption(String(condition));
  const valueInput = row.locator('.aie-updater-filter-value');
  if (await valueInput.count()) {
    await valueInput.fill(String(value));
  }
}

async function setFilterPostTypeSelector(row, { field, postType }) {
  const fieldSelect = row.locator('select.aie-updater-filter-field');
  await fieldSelect.selectOption(String(field));
  const valueSelect = row.locator('select.aie-updater-filter-value');
  await valueSelect.waitFor({ state: 'visible', timeout: 30_000 });
  await valueSelect.selectOption(String(postType));
}

async function setFilterTaxonomySelector(row, { field, taxonomy }) {
  const fieldSelect = row.locator('select.aie-updater-filter-field');
  await fieldSelect.selectOption(String(field));
  const valueSelect = row.locator('select.aie-updater-filter-value');
  await valueSelect.waitFor({ state: 'visible', timeout: 30_000 });
  await valueSelect.selectOption(String(taxonomy));
}

async function refreshCountAndWaitFor(page, expectedText) {
  await waitForUpdaterStep(page, 2);
  await page.locator('.aie-updater-step-2.active .aie-updater-refresh-count').click();
  const count = page.locator('.aie-updater-step-2.active .aie-count-value');
  await count.waitFor({ state: 'visible', timeout: 30_000 });
  await page.waitForFunction(
    ({ sel, exp }) => {
      const el = document.querySelector(sel);
      return el && el.textContent && el.textContent.trim() === exp;
    },
    { sel: '.aie-updater-step-2.active .aie-count-value', exp: String(expectedText) },
    { timeout: 30_000 }
  );
}

async function selectFieldToUpdate(page, fieldKey) {
  await waitForUpdaterStep(page, 3);
  const itemSelector = `.aie-fields-library .aie-field-item[data-field="${fieldKey}"]`;

  // Wait until the static fields library has been rendered (spinner replaced).
  await page
    .locator('.aie-updater-step-3.active #aie-updater-fields-library .aie-fields-library-body')
    .waitFor({ state: 'attached', timeout: 30_000 });

  // Wait for the field item to exist (it can start out hidden if its category is collapsed).
  const item = page.locator(itemSelector).first();
  await item.waitFor({ state: 'attached', timeout: 30_000 });

  // Expand collapsed categories (some content types skip the first group, so
  // everything can start collapsed).
  const categories = page.locator('.aie-updater-step-3.active .aie-field-category');
  const n = await categories.count();
  for (let i = 0; i < n; i++) {
    const cat = categories.nth(i);
    if (!(await cat.isVisible())) continue;
    const cls = (await cat.getAttribute('class')) || '';
    if (cls.includes('aie-collapsed')) {
      await cat.locator('.aie-field-category-title').click({ force: true });
    }
  }

  // Wait for library to populate and field to be clickable.
  await item.waitFor({ state: 'visible', timeout: 30_000 });
  await item.click();
  await page.locator(`.aie-updater-selected-fields .aie-selected-field[data-field="${fieldKey}"]`).waitFor({
    state: 'visible',
    timeout: 30_000,
  });
}

async function assignUppercaseAndGetExpected(page, { fieldKey, currentValue }) {
  await waitForUpdaterStep(page, 4);
  const assignBtn = page.locator(`tr[data-field="${fieldKey}"] .aie-assign-functions`).first();
  await assignBtn.waitFor({ state: 'visible', timeout: 30_000 });
  await assignBtn.click();

  const modal = page.locator('#aie-updater-functions-modal');
  await modal.waitFor({ state: 'visible', timeout: 30_000 });

  const uppercaseAdd = modal.locator('.aie-add-function-btn[data-function-id="snippet_uppercase"]').first();
  await uppercaseAdd.waitFor({ state: 'visible', timeout: 30_000 });
  await uppercaseAdd.click();

  const previewInput = modal.locator('#aie-updater-preview-input');
  await previewInput.fill(String(currentValue));

  await modal.locator('.aie-test-updater-pipeline').click();

  const preview = modal.locator('#aie-updater-preview-result');
  await preview.waitFor({ state: 'visible', timeout: 30_000 });

  const lastValue = preview.locator('.aie-preview-step .aie-step-value').last();
  const expectedValue = (await lastValue.textContent()) || '';

  await modal.locator('.aie-save-updater-functions').click();
  await modal.waitFor({ state: 'hidden', timeout: 30_000 });

  return expectedValue.trim();
}

async function startUpdateAndWait(page) {
  await waitForUpdaterStep(page, 5);
  const startBtn = page.locator('.aie-start-update-btn');
  await startBtn.waitFor({ state: 'visible', timeout: 30_000 });
  await startBtn.click();
  await handleBackupModalIfPresent(page);

  const results = page.locator('#aie-updater-results');
  await results.waitFor({ state: 'visible', timeout: 120_000 });

  const errors = ((await results.locator('.aie-final-errors').textContent()) || '').trim();
  return { errors: Number(errors || '0') };
}

async function runOneCase(page, env, tc) {
  await gotoContentUpdater(page, env);

  // Step 1: select content type
  const typeLabel = page.locator(
    `label.aie-content-type:has(input[name="updater_content_type"][value="${tc.contentType}"])`
  );
  await typeLabel.waitFor({ state: 'attached', timeout: 30_000 });
  await typeLabel.scrollIntoViewIfNeeded();
  await typeLabel.click({ force: true });
  await clickNextStep(page);

  // Step 2: filters / table selection
  if (tc.contentType === 'database_table') {
    const tableSelect = page.locator('#aie-updater-table-name');
    await tableSelect.waitFor({ state: 'visible', timeout: 30_000 });
    await tableSelect.selectOption(tc.tableName);
    // Wait for columns to load (table info shown and count refreshed)
    await page.locator('.aie-table-info').waitFor({ state: 'visible', timeout: 30_000 });
  }

  for (const f of tc.filters) {
    const row = await addFilterRow(page);
    if (f.kind === 'post_type_selector') {
      await setFilterPostTypeSelector(row, { field: f.field, postType: f.value });
    } else if (f.kind === 'taxonomy_selector') {
      await setFilterTaxonomySelector(row, { field: f.field, taxonomy: f.value });
    } else {
      await setFilterStandard(row, { field: f.field, condition: f.condition, value: f.value });
    }
  }

  await refreshCountAndWaitFor(page, tc.expectedCountText || '1');
  await clickNextStep(page);

  // Step 3: fields
  await selectFieldToUpdate(page, tc.fieldKey);
  await clickNextStep(page);

  // Step 4: functions + expected output
  const expected = await assignUppercaseAndGetExpected(page, { fieldKey: tc.fieldKey, currentValue: tc.currentValue });
  await clickNextStep(page);

  // Step 5: run update
  const { errors } = await startUpdateAndWait(page);

  // Verify result
  const actual = readFieldValue(env, tc.verify.read);

  return {
    contentType: tc.contentType,
    fieldKey: tc.fieldKey,
    id: tc.verify.id,
    expected,
    actual,
    match: expected === actual,
    errors,
  };
}

async function main() {
  const env = loadEnv();

  ensureUpdaterTestTable(env);

	  const ids = {
	    postId: pickOneId(env, 'post'),
	    pageId: pickOneId(env, 'page'),
	    portfolioId: pickOneId(env, 'portfolio'),
	    productId: pickOneId(env, 'product'),
	    commentId: pickOneId(env, 'comment'),
	    userId: pickOneId(env, 'user'),
	    categoryTermId: pickOneId(env, 'category_term'),
	    dbTableName: `${wpEval(env, 'global $wpdb; echo $wpdb->prefix;')}aie_updater_test`,
	  };

	  const current = {
	    postTitle: readFieldValue(env, { type: 'post', id: ids.postId }),
	    pageTitle: readFieldValue(env, { type: 'post', id: ids.pageId }),
	    portfolioTitle: readFieldValue(env, { type: 'post', id: ids.portfolioId }),
	    productTitle: readFieldValue(env, { type: 'post', id: ids.productId }),
	    commentContent: readFieldValue(env, { type: 'comment', id: ids.commentId, field: 'comment_content' }),
	    userDisplayName: readFieldValue(env, { type: 'user', id: ids.userId }),
	    categoryName: readFieldValue(env, { type: 'term', taxonomy: 'category', id: ids.categoryTermId }),
	    dbValue: readFieldValue(env, { type: 'db_test' }),
	  };

	  const cases = [
    {
      contentType: 'post',
      fieldKey: 'post_title',
      currentValue: current.postTitle,
      filters: [
        { field: 'ID', condition: 'equals', value: ids.postId },
        { field: 'post_status', condition: 'equals', value: 'publish' },
      ],
      verify: { id: ids.postId, read: { type: 'post', id: ids.postId } },
    },
    {
      contentType: 'page',
      fieldKey: 'post_title',
      currentValue: current.pageTitle,
      filters: [{ field: 'ID', condition: 'equals', value: ids.pageId }],
      verify: { id: ids.pageId, read: { type: 'post', id: ids.pageId } },
    },
    {
      contentType: 'custom_post_types',
      fieldKey: 'post_title',
      currentValue: current.portfolioTitle,
      filters: [
        { kind: 'post_type_selector', field: '_post_type', value: 'portfolio' },
        { field: 'ID', condition: 'equals', value: ids.portfolioId },
      ],
      verify: { id: ids.portfolioId, read: { type: 'post', id: ids.portfolioId } },
    },
    {
      contentType: 'user',
      fieldKey: 'display_name',
      currentValue: current.userDisplayName,
      filters: [{ field: 'ID', condition: 'equals', value: ids.userId }],
      verify: { id: ids.userId, read: { type: 'user', id: ids.userId } },
    },
    {
      contentType: 'comment',
      fieldKey: 'comment_content',
      currentValue: current.commentContent,
      filters: [{ field: 'comment_ID', condition: 'equals', value: ids.commentId }],
      verify: { id: ids.commentId, read: { type: 'comment', id: ids.commentId, field: 'comment_content' } },
    },
    {
      contentType: 'taxonomy',
      fieldKey: 'name',
      currentValue: current.categoryName,
      filters: [
        { kind: 'taxonomy_selector', field: '_taxonomy', value: 'category' },
        { field: 'term_id', condition: 'equals', value: ids.categoryTermId },
      ],
      verify: { id: ids.categoryTermId, read: { type: 'term', taxonomy: 'category', id: ids.categoryTermId } },
    },
	    {
	      contentType: 'woo_product',
	      fieldKey: 'post_title',
	      currentValue: current.productTitle,
	      filters: [{ field: 'ID', condition: 'equals', value: ids.productId }],
	      verify: { id: ids.productId, read: { type: 'post', id: ids.productId } },
	    },
	    {
	      contentType: 'database_table',
	      tableName: ids.dbTableName,
	      fieldKey: 'value',
      currentValue: current.dbValue,
      filters: [{ field: 'id', condition: 'equals', value: '1' }],
      verify: { id: 1, read: { type: 'db_test' } },
    },
	  ];
	  const casesToRun = cases.filter((tc) => tc && tc.verify && tc.verify.id && String(tc.verify.id).trim() !== '');

	  const browser = await chromium.launch({ headless: env.headless });
	  const context = await browser.newContext();
	  const page = await context.newPage();

	  const results = [];
	  try {
	    for (const tc of casesToRun) {
	      // Keep each run isolated-ish by restarting from Step 1.
	      const r = await runOneCase(page, env, tc);
	      results.push(r);
      console.log(
        `[${r.contentType}] field=${r.fieldKey} id=${r.id} match=${String(r.match)} errors=${r.errors} expected="${r.expected}" actual="${r.actual}"`
      );
      await clickStartNewUpdate(page);
    }
  } finally {
    await context.close();
    await browser.close();
  }

  const failed = results.filter((r) => !r.match || r.errors > 0);
  console.log(`\nSummary: ${results.length} cases, ${failed.length} issues`);
  if (failed.length) {
    process.exitCode = 1;
  }
}

main().catch((err) => {
  // eslint-disable-next-line no-console
  console.error(err);
  process.exitCode = 1;
});
