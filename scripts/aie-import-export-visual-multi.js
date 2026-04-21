/**
 * Manual E2E (Playwright): export+import a post type, then do a visual compare
 * for a few selected items (screenshots on source vs target edit screens).
 *
 * Defaults to Pages (post_type=page) and compares a few well-known sample pages.
 *
 * Usage:
 *   node scripts/aie-import-export-visual-multi.js
 *
 * Env (defaults read from .env.e2e if present):
 *   AIE_SOURCE_URL, AIE_SOURCE_ADMIN_USER, AIE_SOURCE_ADMIN_PASSWORD
 *   AIE_TARGET_URL, AIE_TARGET_ADMIN_USER, AIE_TARGET_ADMIN_PASSWORD
 *   AIE_HEADLESS=true|false
 *   AIE_CONTENT_TYPE=page|custom_post_types|media|menu|user|comment|taxonomy|woo_product|woo_order|woo_coupon|woo_attribute|database_table
 *   AIE_CUSTOM_POST_TYPE=product|portfolio|...
 *   AIE_TAXONOMY=category|post_tag|product_cat|...
 *   AIE_TAXONOMY_POST_TYPE=post|page|product|...
 *   AIE_SOURCE_POST_IDS=2,146,701
 *   AIE_SOURCE_MENU_NAMES="Main Menu,Footer"
 *   AIE_DB_TABLE=wp_some_table
 *   AIE_DB_TABLE_PATTERNS=otbo,mask
 *   AIE_IF_EXISTS=update|skip|create
 *   AIE_IF_NOT_EXISTS=create|skip
 *   AIE_AUTO_IMPORT_MEDIA=true|false
 *   AIE_MEDIA_DUPLICATE_MODE=skip|create|replace
 */

const fs = require('fs');
const os = require('os');
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

  const idsRaw = String(get('AIE_SOURCE_POST_IDS', '2,146,701'));
  const sourceIds = idsRaw
    .split(',')
    .map((x) => Number(String(x).trim()))
    .filter((n) => Number.isFinite(n) && n > 0);

  const menuNamesRaw = String(get('AIE_SOURCE_MENU_NAMES', ''));
  const sourceMenuNames = menuNamesRaw
    .split(',')
    .map((x) => String(x).trim())
    .filter(Boolean);

  const dbTable = String(get('AIE_DB_TABLE', '')).trim();
  const dbTablePatternsRaw = String(get('AIE_DB_TABLE_PATTERNS', 'otbo,mask'));
  const dbTablePatterns = dbTablePatternsRaw
    .split(',')
    .map((x) => String(x).trim())
    .filter(Boolean);

  const ifExists = String(get('AIE_IF_EXISTS', 'update')).trim() || 'update';
  const ifNotExists = String(get('AIE_IF_NOT_EXISTS', 'create')).trim() || 'create';
  const autoImportMedia = String(get('AIE_AUTO_IMPORT_MEDIA', 'true')).toLowerCase() !== 'false';
  const mediaDuplicateMode = String(get('AIE_MEDIA_DUPLICATE_MODE', 'skip')).trim() || 'skip';

  const sourceWpPathDefault = path.resolve(process.cwd(), '../../..');
  const targetWpPathGuess = (() => {
    const marker = `${path.sep}Local Sites${path.sep}aie${path.sep}`;
    if (sourceWpPathDefault.includes(marker)) {
      return sourceWpPathDefault.replace(marker, `${path.sep}Local Sites${path.sep}aie2${path.sep}`);
    }
    return '';
  })();

  return {
    sourceUrl: get('AIE_SOURCE_URL', 'http://aie.local'),
    sourceUser: get('AIE_SOURCE_ADMIN_USER', 'admin'),
    sourcePass: get('AIE_SOURCE_ADMIN_PASSWORD', 'admin'),
    targetUrl: get('AIE_TARGET_URL', 'http://aie2.local'),
    targetUser: get('AIE_TARGET_ADMIN_USER', 'admin'),
    targetPass: get('AIE_TARGET_ADMIN_PASSWORD', 'admin'),
    headless,
    contentType: get('AIE_CONTENT_TYPE', 'page'),
    customPostType: get('AIE_CUSTOM_POST_TYPE', ''),
    taxonomy: get('AIE_TAXONOMY', 'category'),
    taxonomyPostType: get('AIE_TAXONOMY_POST_TYPE', 'post'),
    sourceIds,
    sourceMenuNames,
    dbTable,
    dbTablePatterns,
    sourceWpPath: get('AIE_SOURCE_WP_PATH', sourceWpPathDefault),
    targetWpPath: get('AIE_TARGET_WP_PATH', targetWpPathGuess || sourceWpPathDefault),
    ifExists,
    ifNotExists,
    autoImportMedia,
    mediaDuplicateMode,
  };
}

function slugify(str) {
  return String(str || '')
    .toLowerCase()
    .replace(/['"]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '')
    .slice(0, 60);
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

async function gotoAdminPage(page, opts, adminPathWithQuery) {
  await ensureLoggedIn(page, opts);
  await page.goto(`${opts.baseUrl}${adminPathWithQuery}`, { waitUntil: 'domcontentloaded' });
  if (await page.locator('form#loginform').count()) {
    await ensureLoggedIn(page, opts);
    await page.goto(`${opts.baseUrl}${adminPathWithQuery}`, { waitUntil: 'domcontentloaded' });
  }
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
  const next = page.locator('.aie-step.active .aie-next-step');
  await next.waitFor({ state: 'visible', timeout: 30_000 });
  await page.waitForFunction(() => {
    const btn = document.querySelector('.aie-step.active .aie-next-step');
    return btn && !btn.disabled;
  });
  await next.click();
}

async function selectUniqueField(page, preferredValues) {
  const uniqueSelect = page.locator('#aie-unique-field');
  await uniqueSelect.waitFor({ state: 'visible', timeout: 30_000 });

  // Wait until the select is populated (more than just placeholder).
  await page.waitForFunction(() => {
    const el = document.querySelector('#aie-unique-field');
    return el && el.options && el.options.length > 1;
  });

  const existing = await uniqueSelect.evaluate((el) => Array.from(el.options).map((o) => o.value));
  const chosen = (preferredValues || []).find((v) => existing.includes(v));
  const fallback = existing.find((v) => v && v.trim() !== '');

  if (!chosen && !fallback) {
    throw new Error('Could not select a unique field: #aie-unique-field has no usable options');
  }
  await uniqueSelect.selectOption({ value: chosen || fallback });
}

async function selectContentTypeOnStep1(page, contentType) {
  if (!contentType) return;
  const input = page.locator(`.aie-step-1.active input[name="content_type"][value="${contentType}"]`).first();
  if (!(await input.count())) return;

  if (await input.isDisabled()) {
    throw new Error(`Content type "${contentType}" is disabled in the UI (premium locked?)`);
  }

  // Inputs are usually visually hidden; click the visible label card instead.
  const label = page.locator('.aie-step-1.active label.aie-content-type', { has: input }).first();
  if (await label.count()) {
    await label.click({ force: true });
    return;
  }

  // Fallback: set checked via DOM.
  await page.evaluate((ct) => {
    const el = document.querySelector(`.aie-step-1.active input[name="content_type"][value="${ct}"]`);
    if (!el) return;
    el.checked = true;
    el.dispatchEvent(new Event('change', { bubbles: true }));
    el.dispatchEvent(new Event('click', { bubbles: true }));
  }, contentType);
}

function getComparePostType(env) {
  if (env.contentType === 'custom_post_types') return env.customPostType;
  if (env.contentType === 'woo_product' || env.contentType === 'product') return 'product';
  if (env.contentType === 'woo_order' || env.contentType === 'order') return 'shop_order';
  if (env.contentType === 'woo_coupon' || env.contentType === 'coupon') return 'shop_coupon';
  return env.contentType;
}

async function ensureExportPostTypeSelected(page, postType) {
  if (!postType) throw new Error('AIE_CUSTOM_POST_TYPE is required for AIE_CONTENT_TYPE=custom_post_types');

  // Export Step 2: there should be (or we can create) a post type selector filter row.
  const existingSelector = page.locator('.aie-step-2.active select.aie-post-type-selector');
  if (!(await existingSelector.count())) {
    const addFilter = page.locator('.aie-step-2.active .aie-add-filter');
    if (await addFilter.count()) {
      await addFilter.click();
      await page.waitForTimeout(200);
    }

    // Try to pick the special "_post_type" filter which turns into a selector.
    const fieldSelect = page.locator('.aie-step-2.active .aie-filter-row:last-child select.aie-filter-field').first();
    if (await fieldSelect.count()) {
      await fieldSelect.selectOption({ value: '_post_type' });
    }
  }

  const selector = page.locator('.aie-step-2.active select.aie-post-type-selector').first();
  await selector.waitFor({ state: 'visible', timeout: 30_000 });
  await page.waitForFunction(
    (pt) => {
      const el = document.querySelector('.aie-step-2.active select.aie-post-type-selector');
      if (!el) return false;
      return Array.from(el.options).some((o) => o.value === pt);
    },
    postType,
    { timeout: 30_000 }
  );
  await selector.selectOption({ value: postType });

  // Wait until Next is enabled (count + post type selection).
  await page.waitForFunction(() => {
    const btn = document.querySelector('.aie-step-2.active .aie-next-step');
    return btn && !btn.disabled;
  });
}

async function ensureExportTaxonomySelected(page, taxonomy) {
  if (!taxonomy) throw new Error('AIE_TAXONOMY is required for AIE_CONTENT_TYPE=taxonomy');

  // Export Step 2: there should be (or we can create) a taxonomy selector filter row.
  const existingSelector = page.locator('.aie-step-2.active select.aie-taxonomy-selector');
  if (!(await existingSelector.count())) {
    const addFilter = page.locator('.aie-step-2.active .aie-add-filter');
    if (await addFilter.count()) {
      await addFilter.click();
      await page.waitForTimeout(200);
    }

    const fieldSelect = page.locator('.aie-step-2.active .aie-filter-row:last-child select.aie-filter-field').first();
    if (await fieldSelect.count()) {
      await fieldSelect.selectOption({ value: '_taxonomy' });
    }
  }

  const selector = page.locator('.aie-step-2.active select.aie-taxonomy-selector').first();
  await selector.waitFor({ state: 'visible', timeout: 30_000 });
  await page.waitForFunction(
    (tx) => {
      const el = document.querySelector('.aie-step-2.active select.aie-taxonomy-selector');
      if (!el) return false;
      return Array.from(el.options).some((o) => o.value === tx);
    },
    taxonomy,
    { timeout: 30_000 }
  );
  await selector.selectOption({ value: taxonomy });

  // Wait until Next is enabled (taxonomy selection is required for counts).
  await page.waitForFunction(() => {
    const btn = document.querySelector('.aie-step-2.active .aie-next-step');
    return btn && !btn.disabled;
  });
}

function parseRowCountFromTableLabel(label) {
  const txt = String(label || '');
  const m = txt.match(/\(([\d,.\s]+)\s*rows\)/i);
  if (!m) return null;
  const n = parseInt(String(m[1]).replace(/[^\d]/g, ''), 10);
  return Number.isFinite(n) ? n : null;
}

function pickDatabaseTableOption(options, { preferredTable, patterns } = {}) {
  const cleanPreferred = String(preferredTable || '').trim();
  const patternList = Array.isArray(patterns) ? patterns : [];

  const candidates = options
    .map((o) => ({
      value: String(o.value || ''),
      text: String(o.text || ''),
      count: parseRowCountFromTableLabel(o.text),
    }))
    .filter((o) => o.value && typeof o.count === 'number' && o.count > 0);

  if (!candidates.length) return null;

  if (cleanPreferred) {
    const exact = candidates.find((o) => o.value === cleanPreferred);
    if (exact) return exact;
  }

  const isCoreLike = (name) => /_(posts|postmeta|terms|term_taxonomy|term_relationships|termmeta|options|users|usermeta|comments|commentmeta|links)$/i.test(String(name || ''));
  const isInternalLike = (name) =>
    /_(actionscheduler_|wc_|woocommerce_)/i.test(String(name || '')) || /_aioseo_|_yoast_/i.test(String(name || ''));

  const hasPattern = (o) => {
    const hay = `${o.value} ${o.text}`.toLowerCase();
    return patternList.some((p) => {
      const needle = String(p || '').trim().toLowerCase();
      return needle && hay.includes(needle);
    });
  };

  const score = (o) => {
    let s = 0;
    if (hasPattern(o)) s += 100;
    if (isCoreLike(o.value)) s -= 80;
    if (isInternalLike(o.value)) s -= 30;
    // Prefer smaller tables to keep the E2E run fast.
    const n = Math.max(1, Number(o.count || 1));
    const sizePenalty = Math.min(40, Math.log10(n) * 15); // 0..40
    s += 40 - sizePenalty;
    return s;
  };

  const ranked = [...candidates].sort((a, b) => score(b) - score(a));
  return ranked[0];
}

async function ensureExportDatabaseTableSelected(page, { preferredTable, patterns } = {}) {
  const selector = page.locator('.aie-step-2.active #aie-table-name').first();
  await selector.waitFor({ state: 'visible', timeout: 30_000 });

  // Wait until options are populated and dropdown is enabled.
  await page.waitForFunction(() => {
    const el = document.querySelector('.aie-step-2.active #aie-table-name');
    return el && !el.disabled && el.options && el.options.length > 1;
  });

  const options = await selector.evaluate((el) =>
    Array.from(el.options).map((o) => ({ value: o.value, text: o.textContent || '' }))
  );
  const chosen = pickDatabaseTableOption(options, { preferredTable, patterns });
  if (!chosen) {
    throw new Error('Could not find a non-empty database table to export (all tables show 0 rows?)');
  }

  await selector.selectOption({ value: chosen.value });

  // Wait until count is refreshed and Next is enabled (count > 0).
  await page.waitForFunction(() => {
    const countEl = document.querySelector('.aie-step-2.active .aie-count-value');
    const btn = document.querySelector('.aie-step-2.active .aie-next-step');
    if (!countEl || !btn) return false;
    const n = parseInt(String(countEl.textContent || '').replace(/[^\d]/g, ''), 10);
    return Number.isFinite(n) && n > 0 && !btn.disabled;
  });

  return chosen.value;
}

async function ensureImportDatabaseTableSelected(page, { preferredTable, patterns } = {}) {
  const selector = page.locator('.aie-step-4.active #aie-import-table-name').first();
  await selector.waitFor({ state: 'visible', timeout: 30_000 });

  await page.waitForFunction(() => {
    const el = document.querySelector('.aie-step-4.active #aie-import-table-name');
    return el && !el.disabled && el.options && el.options.length > 1;
  });

  const options = await selector.evaluate((el) =>
    Array.from(el.options).map((o) => ({ value: o.value, text: o.textContent || '' }))
  );
  const preferred = String(preferredTable || '').trim();
  if (preferred && options.some((o) => String(o.value) === preferred)) {
    // On import we must prefer the exact table from the export, even if it's empty on target.
    await selector.selectOption({ value: preferred });
  } else {
    const chosen = pickDatabaseTableOption(options, { preferredTable: '', patterns }) || null;
    if (!chosen) {
      throw new Error('Could not find a usable database table to import into (dropdown has no valid options)');
    }
    await selector.selectOption({ value: chosen.value });
  }

  // Wait for table columns to render in mapping target panel.
  await page.waitForFunction(() => document.querySelectorAll('.aie-step-4.active .aie-target-field').length > 0, null, {
    timeout: 30_000,
  });

  return await selector.inputValue();
}

async function exportAllItems(page, source, contentType) {
  await gotoAdminPage(page, source, '/wp-admin/admin.php?page=rsl-ie-export');

  // Step 1
  await page.waitForSelector('.aie-step-1.active', { timeout: 30_000 });
  await selectContentTypeOnStep1(page, contentType);
  await clickNextStep(page);

  // Step 2
  await page.waitForSelector('.aie-step-2.active', { timeout: 30_000 });
  const meta = {};
  if (contentType === 'custom_post_types') {
    // Note: selector is required; it enables Next step.
    await ensureExportPostTypeSelected(page, source.customPostType);
  } else if (contentType === 'taxonomy') {
    await ensureExportTaxonomySelected(page, source.taxonomy);
  } else if (contentType === 'database_table') {
    meta.tableName = await ensureExportDatabaseTableSelected(page, {
      preferredTable: source.dbTable,
      patterns: source.dbTablePatterns,
    });
  }
  await clickNextStep(page);

  // Step 3: add all fields in all visible categories
  await page.waitForSelector('.aie-step-3.active', { timeout: 30_000 });
  // Wait until available fields are rendered for the selected content type.
  await page.waitForFunction(() => document.querySelectorAll('.aie-step-3.active .aie-field-item').length > 0, null, {
    timeout: 30_000,
  });

  // Click "Add all" for every category (even if collapsed/hidden).
  await page.evaluate(() => {
    document.querySelectorAll('.aie-step-3.active .aie-add-all-fields').forEach((btn) => {
      try {
        btn.click();
      } catch {}
    });
  });

  // Wait until at least one column is added.
  await page.waitForFunction(() => {
    const el = document.querySelector('.aie-step-3.active .aie-columns-count');
    const n = el ? Number(String(el.textContent || '').trim()) : 0;
    return Number.isFinite(n) && n > 0;
  });

  const step3Next = page.locator('.aie-step-3.active .aie-next-step');
  await page.waitForFunction(() => {
    const btn = document.querySelector('.aie-step-3.active .aie-next-step');
    return btn && !btn.disabled;
  });
  await step3Next.click();

  // Step 4: start export
  await page.waitForSelector('.aie-step-4.active', { timeout: 30_000 });
  await page.locator('.aie-start-export').click();

  // Step 5: download
  await page.waitForSelector('.aie-step-5.active', { timeout: 30_000 });
  const completeCard = page.locator('.aie-export-complete-card');
  await completeCard.waitFor({ state: 'visible', timeout: 5 * 60_000 });

  const [download] = await Promise.all([page.waitForEvent('download', { timeout: 60_000 }), page.locator('.aie-download-file').click()]);
  return { download, meta };
}

async function extractMediaDetails(page, baseUrl) {
  return await page.evaluate((baseUrlInner) => {
    const readValue = (selector) => {
      const el = document.querySelector(selector);
      if (!el) return '';
      if ('value' in el) return String(el.value || '');
      return String(el.textContent || '');
    };

    const title = readValue('#title').trim();
    const altText = readValue('#attachment_alt').trim();
    const caption = readValue('#excerpt').trim();
    const description = readValue('#content').trim();

    const fileUrl =
      readValue('#attachment_url').trim() ||
      (document.querySelector('.misc-pub-attachment a') && document.querySelector('.misc-pub-attachment a').href) ||
      '';

    const normalize = (s) => String(s || '').split(baseUrlInner).join('__BASE__');

    const filename = (() => {
      try {
        const u = new URL(fileUrl);
        return u.pathname.split('/').pop() || '';
      } catch {
        const parts = String(fileUrl || '').split('/');
        return parts[parts.length - 1] || '';
      }
    })();

    return {
      title: normalize(title),
      filename: normalize(filename),
      altText: normalize(altText),
      caption: normalize(caption),
      description: normalize(description),
      // Keep raw URL so Playwright can navigate to it; exclude it from comparisons elsewhere.
      fileUrl,
    };
  }, baseUrl);
}

async function importItems(page, target, csvPath, contentType, importMeta = {}) {
  await gotoAdminPage(page, target, '/wp-admin/admin.php?page=rsl-ie-import');

  // Step 1
  await page.waitForSelector('.aie-step-1.active', { timeout: 30_000 });
  await selectContentTypeOnStep1(page, contentType);
  await page.locator('.aie-step-1.active .aie-next-step').click();
  await handleBackupModalIfPresent(page);

  // Step 2 upload
  await page.waitForSelector('.aie-step-2.active', { timeout: 30_000 });
  await page.setInputFiles('#aie-file-input', csvPath);
  await page.waitForFunction(() => {
    const btn = document.querySelector('.aie-step-2.active .aie-next-step');
    return btn && !btn.disabled;
  });
  await page.locator('.aie-step-2.active .aie-next-step').click();

  // Step 3 preview
  await page.waitForSelector('.aie-step-3.active', { timeout: 30_000 });
  await clickNextStep(page);

  // Step 4 mapping: auto map
  await page.waitForSelector('.aie-step-4.active', { timeout: 30_000 });

  if (contentType === 'custom_post_types') {
    const postTypeSelect = page.locator('.aie-step-4.active #aie-custom-post-type').first();
    await postTypeSelect.waitFor({ state: 'visible', timeout: 30_000 });
    await page.waitForFunction(
      (pt) => {
        const el = document.querySelector('.aie-step-4.active #aie-custom-post-type');
        if (!el) return false;
        return Array.from(el.options).some((o) => o.value === pt);
      },
      target.customPostType,
      { timeout: 30_000 }
    );
    await postTypeSelect.selectOption({ value: target.customPostType });
    await page.waitForTimeout(300);
  }

  if (contentType === 'database_table') {
    await ensureImportDatabaseTableSelected(page, {
      preferredTable: importMeta.tableName || target.dbTable,
      patterns: target.dbTablePatterns,
    });
    await page.waitForTimeout(300);
  }

  await page.locator('.aie-auto-map').click();
  await page.waitForTimeout(250);
  await clickNextStep(page);

  // Step 5 options
  await page.waitForSelector('.aie-step-5.active', { timeout: 30_000 });

  // If Match Found
  if (target.ifExists) {
    const ifExistsRadio = page.locator(`.aie-step-5.active input[name="if_exists"][value="${target.ifExists}"]`).first();
    if (await ifExistsRadio.count()) {
      await ifExistsRadio.check({ force: true });
    }
  }

  // If No Match Found
  if (target.ifNotExists) {
    const ifNotExistsRadio = page
      .locator(`.aie-step-5.active input[name="if_not_exists"][value="${target.ifNotExists}"]`)
      .first();
    if (await ifNotExistsRadio.count()) {
      await ifNotExistsRadio.check({ force: true });
    }
  }

  if (contentType === 'media') {
    await selectUniqueField(page, ['file_url', 'url', 'filename', 'file_name', 'file']);
  } else if (contentType === 'menu') {
    await selectUniqueField(page, ['name', 'slug']);
  } else if (contentType === 'user') {
    await selectUniqueField(page, ['user_login', 'user_email', 'ID']);
  } else if (contentType === 'comment' || contentType === 'comments') {
    await selectUniqueField(page, ['comment_ID', 'comment_author_email', 'comment_date_gmt']);
  } else if (contentType === 'taxonomy' || contentType === 'taxonomy_terms') {
    await selectUniqueField(page, ['slug', 'name', 'term_id']);
  } else if (contentType === 'woo_product' || contentType === 'product') {
    await selectUniqueField(page, ['sku', '_sku', 'post_title']);
  } else if (contentType === 'woo_order' || contentType === 'order') {
    await selectUniqueField(page, ['order_key', 'order_number', 'ID']);
  } else if (contentType === 'woo_coupon' || contentType === 'coupon') {
    await selectUniqueField(page, ['post_title', 'code', 'ID']);
  } else if (contentType === 'woo_attribute' || contentType === 'attribute') {
    await selectUniqueField(page, ['attribute_name', 'attribute_label', 'name', 'slug', 'ID']);
  } else if (contentType === 'database_table') {
    await selectUniqueField(page, importMeta.uniqueFieldPreferred || []);
  } else {
    await selectUniqueField(page, ['post_title', 'post_name', 'ID']);
  }

  const mediaCheckbox = page.locator('#aie-auto-import-media');
  if (await mediaCheckbox.count()) {
    if (target.autoImportMedia) {
      await mediaCheckbox.check({ force: true });
    } else {
      await mediaCheckbox.uncheck({ force: true }).catch(() => {});
    }
  }

  if (target.autoImportMedia && target.mediaDuplicateMode) {
    const mediaModeRadio = page
      .locator(`.aie-step-5.active input[name="media_duplicate_mode"][value="${target.mediaDuplicateMode}"]`)
      .first();
    if (await mediaModeRadio.count()) {
      await mediaModeRadio.check({ force: true });
    }
  }

  await page.locator('.aie-start-import').click();
  await handleBackupModalIfPresent(page);

  // Step 6 complete
  await page.waitForSelector('.aie-step-6.active', { timeout: 30_000 });
  await page.locator('.aie-import-complete-card').waitFor({ state: 'visible', timeout: 10 * 60_000 });
}

async function getPostTitleFromEdit(page) {
  const classicTitle = page.locator('#title');
  if (await classicTitle.count()) {
    const value = await classicTitle.inputValue();
    if (value) return value.trim();
  }
  const blockTitle = page.locator('h1.editor-post-title__input');
  if (await blockTitle.count()) {
    const text = await blockTitle.innerText();
    if (text) return text.trim();
  }
  const tabTitle = await page.title();
  const m = tabTitle.match(/[“"](.*?)[”"]/);
  if (m && m[1]) return m[1].trim();
  return '';
}

async function getPermalinkFromEditorStore(page) {
  try {
    await page.waitForFunction(
      () => Boolean(window.wp?.data?.select?.('core/editor')?.getPermalink),
      { timeout: 15_000 }
    );
  } catch {}

  const fromStore = await page.evaluate(() => {
    try {
      const url = window.wp?.data?.select?.('core/editor')?.getPermalink?.();
      return typeof url === 'string' && url.length ? url : null;
    } catch {
      return null;
    }
  });

  if (fromStore) return fromStore;

  // Fallback: admin bar "View" link (works for many classic / custom edit screens).
  const adminBarView = page.locator('#wp-admin-bar-view a').first();
  if (await adminBarView.count()) {
    const href = await adminBarView.getAttribute('href');
    if (href) return href;
  }

  return null;
}

async function openEditByTitle(page, site, postType, title) {
  const isMedia = postType === 'media' || postType === 'attachment';
  const tries = [title];
  if (isMedia && title.includes('.')) {
    const base = title.replace(/\.[^/.]+$/, '');
    if (base && base !== title) tries.push(base);
  }

  for (const t of tries) {
    const searchUrl = isMedia
      ? `${site.baseUrl}/wp-admin/upload.php?mode=list&s=${encodeURIComponent(t)}`
      : `${site.baseUrl}/wp-admin/edit.php?post_type=${encodeURIComponent(postType)}&s=${encodeURIComponent(t)}`;
    await page.goto(searchUrl, { waitUntil: 'domcontentloaded' });

    const link = isMedia
      ? page.locator('#the-list td.title a', { hasText: t }).first()
      : page.locator('a.row-title', { hasText: t }).first();
    if (await link.count()) {
      await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), link.click()]);
      return;
    }
  }

  const first = isMedia ? page.locator('#the-list td.title a').first() : page.locator('a.row-title').first();
  if (!(await first.count())) throw new Error(`Could not find target item by title: ${title}`);
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), first.click()]);
}

async function extractEditorContentBestEffort(page, baseUrl) {
  // Give Gutenberg a moment to hydrate its data stores after navigation.
  try {
    await page.waitForFunction(
      () => Boolean(window.wp?.data?.select?.('core/editor')?.getEditedPostContent),
      { timeout: 15_000 }
    );
  } catch {}

  return await page.evaluate((baseUrlInner) => {
    try {
      const wp = window.wp;
      const content = wp?.data?.select?.('core/editor')?.getEditedPostContent?.();
      if (typeof content === 'string') return content.split(baseUrlInner).join('__BASE__');
    } catch {}

    const textarea = document.querySelector('#content');
    if (textarea && typeof textarea.value === 'string') {
      return textarea.value.split(baseUrlInner).join('__BASE__');
    }

    return null;
  }, baseUrl);
}

async function screenshotSegments(page, outDir, prefix) {
  // Try to normalize Gutenberg scroll (it uses nested scroll containers).
  await page.evaluate(() => {
    try {
      const scroller =
        document.querySelector('.interface-interface-skeleton__content') ||
        document.querySelector('.edit-post-layout__content') ||
        document.querySelector('.edit-post-visual-editor');
      if (scroller && typeof scroller.scrollTo === 'function') {
        scroller.scrollTo(0, 0);
      }
    } catch {}
    try {
      window.scrollTo(0, 0);
    } catch {}
  });
  await page.waitForTimeout(500);

  const top = path.join(outDir, `${prefix}-top.png`);
  await page.screenshot({ path: top });

  // Prefer a stable editor root to avoid relying on the window scroll container
  // (Gutenberg uses nested scroll areas).
  const editorShot = path.join(outDir, `${prefix}-editor.png`);
  const canvasFrame = page.locator('iframe[name="editor-canvas"]').first();
  if (await canvasFrame.count()) {
    try {
      // Ensure the editor area is visible before screenshotting the iframe.
      await page.evaluate(() => {
        const scroller =
          document.querySelector('.interface-interface-skeleton__content') ||
          document.querySelector('.edit-post-layout__content') ||
          document.querySelector('.edit-post-visual-editor');
        if (scroller && typeof scroller.scrollTo === 'function') scroller.scrollTo(0, 0);
      });
      await page.waitForTimeout(500);
      await canvasFrame.scrollIntoViewIfNeeded();
      await page.waitForTimeout(300);
      await canvasFrame.screenshot({ path: editorShot });
    } catch {}
  } else {
    const editor = page.locator('.edit-post-visual-editor, .block-editor-writing-flow, .editor-styles-wrapper, #postdivrich, #poststuff').first();
    if (await editor.count()) {
      try {
        await editor.scrollIntoViewIfNeeded();
        await page.waitForTimeout(300);
        await editor.screenshot({ path: editorShot });
      } catch {}
    }
  }

  const acfBox = page.locator('.acf-postbox').first();
  const acfShot = path.join(outDir, `${prefix}-acf.png`);
  if (await acfBox.count()) {
    try {
      await acfBox.scrollIntoViewIfNeeded();
      await page.waitForTimeout(300);
      await acfBox.screenshot({ path: acfShot });
    } catch {}
  }

  return { top, editor: editorShot, acf: acfShot };
}

async function screenshotFrontend(page, url, outPath) {
  if (!url) return null;
  await page.goto(url, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1000);
  await page.screenshot({ path: outPath, fullPage: true });
  return outPath;
}

function runWpEvalJson({ wpPath, url }, phpCode) {
  const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'aie-wp-eval-'));
  const phpPath = path.join(tmpDir, 'eval.php');
  fs.writeFileSync(phpPath, `<?php\n${phpCode}\n`);

  const args = ['eval-file', phpPath, `--path=${wpPath}`];
  if (url) args.push(`--url=${url}`);
  args.push('--quiet');

  const out = execFileSync('wp', args, { encoding: 'utf8' }).trim();
  try {
    return JSON.parse(out);
  } catch (e) {
    const preview = out.slice(0, 500);
    throw new Error(`wp eval JSON parse failed: ${String(e && e.message ? e.message : e)}\nOutput: ${preview}`);
  }
}

function getPostContentViaWpCli({ wpPath, url }, postId) {
  const args = ['post', 'get', String(postId), '--field=post_content', `--path=${wpPath}`];
  if (url) args.push(`--url=${url}`);
  return execFileSync('wp', args, { encoding: 'utf8' }).trim();
}

function dumpMenusViaWpCli({ wpPath, url }) {
  const php = `
global $wpdb;
$home = home_url();
$menus = wp_get_nav_menus(['hide_empty' => false]);
$out = [];
foreach ($menus as $m) {
  $items = wp_get_nav_menu_items($m->term_id);
  if (!is_array($items)) { $items = []; }

  $children = [];
  foreach ($items as $it) {
    $p = (int) $it->menu_item_parent;
    if (!isset($children[$p])) { $children[$p] = []; }
    $children[$p][] = $it;
  }
  foreach ($children as $pid => $list) {
    usort($children[$pid], function($a, $b) { return (int)$a->menu_order <=> (int)$b->menu_order; });
  }

  $flat = [];
  $walk = function($parentId, $depth, $path) use (&$walk, &$children, &$flat, $home) {
    if (empty($children[$parentId])) { return; }
    foreach ($children[$parentId] as $it) {
      $title = (string) $it->title;
      $type = (string) $it->type;
      $object = (string) $it->object;
      $objectId = (int) $it->object_id;
      $url = (string) $it->url;
      $resolved = $url;
      $invalid = false;

      if ($type === 'post_type') {
        $post = get_post($objectId);
        if (!$post) { $invalid = true; }
        else { $resolved = (string) get_permalink($objectId); }
      } elseif ($type === 'taxonomy') {
        $term = get_term($objectId, $object);
        if (!$term || is_wp_error($term)) { $invalid = true; }
        else {
          $termUrl = get_term_link($term);
          if (is_wp_error($termUrl)) { $invalid = true; }
          else { $resolved = (string) $termUrl; }
        }
      }

      $itemPath = $path ? ($path . ' > ' . $title) : $title;
      $flat[] = [
        'depth' => (int) $depth,
        'path' => (string) $itemPath,
        'title' => (string) $title,
        'type' => (string) $type,
        'object' => (string) $object,
        'url' => (string) $url,
        'resolved_url' => (string) $resolved,
        'invalid' => (bool) $invalid,
      ];
      $walk((int) $it->ID, $depth + 1, $itemPath);
    }
  };
  $walk(0, 0, '');

  $out[] = [
    'term_id' => (int) $m->term_id,
    'name' => (string) $m->name,
    'slug' => (string) $m->slug,
    'items' => $flat,
  ];
}
echo wp_json_encode(['home_url' => $home, 'menus' => $out], JSON_UNESCAPED_SLASHES);
`;

  return runWpEvalJson({ wpPath, url }, php);
}

function normalizeMenuDump(dump, baseUrl) {
  const normalize = (s) => String(s || '').split(baseUrl).join('__BASE__');
  const normMenus = (dump.menus || []).map((m) => ({
    name: m.name,
    slug: m.slug,
    items: (m.items || []).map((it) => ({
      depth: it.depth,
      path: it.path,
      title: it.title,
      type: it.type,
      object: it.object,
      url: normalize(it.url),
      resolved_url: normalize(it.resolved_url),
      invalid: Boolean(it.invalid),
    })),
  }));
  return { home_url: normalize(dump.home_url), menus: normMenus };
}

function buildMenuCompareHtml({ source, target, compared }) {
  const esc = (s) =>
    String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;')
      .replace(/'/g, '&#039;');

  const row = (cells, cls = '') => `<tr class="${cls}">${cells.map((c) => `<td>${c}</td>`).join('')}</tr>`;

  let html = `<!doctype html><html><head><meta charset="utf-8"/><title>AIE Menu Compare</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:24px;}
h1{margin:0 0 8px 0;}
.muted{color:#666;margin:0 0 16px 0;}
table{border-collapse:collapse;width:100%;margin:16px 0;}
th,td{border:1px solid #ddd;padding:8px;vertical-align:top;font-size:12px;}
th{background:#f7f7f7;text-align:left;}
.bad{background:#fff1f1;}
.ok{background:#f2fff1;}
.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;white-space:pre-wrap;word-break:break-word;}
</style></head><body>`;

  html += `<h1>Menu import/export comparison</h1>`;
  html += `<p class="muted">Source: ${esc(source.baseUrl)} | Target: ${esc(target.baseUrl)}</p>`;

  for (const c of compared) {
    html += `<h2>${esc(c.menuName)}</h2>`;
    html += `<p class="muted">structureEqual=${esc(String(c.structureEqual))}, hasInvalidItems=${esc(String(c.hasInvalidItems))}</p>`;
    html += `<table><thead><tr><th style="width:50%">Source</th><th style="width:50%">Target</th></tr></thead><tbody>`;

    const max = Math.max(c.sourceItems.length, c.targetItems.length);
    for (let i = 0; i < max; i++) {
      const s = c.sourceItems[i] || null;
      const t = c.targetItems[i] || null;
      const sTxt = s
        ? `${'  '.repeat(s.depth)}- ${s.title} [${s.type}:${s.object}]\\n  url=${s.resolved_url}\\n  invalid=${String(s.invalid)}`
        : '(missing)';
      const tTxt = t
        ? `${'  '.repeat(t.depth)}- ${t.title} [${t.type}:${t.object}]\\n  url=${t.resolved_url}\\n  invalid=${String(t.invalid)}`
        : '(missing)';
      const mismatch =
        !s ||
        !t ||
        s.depth !== t.depth ||
        s.title !== t.title ||
        s.type !== t.type ||
        s.object !== t.object ||
        s.resolved_url !== t.resolved_url ||
        s.invalid !== t.invalid;
      html += row([`<div class="mono">${esc(sTxt)}</div>`, `<div class="mono">${esc(tTxt)}</div>`], mismatch ? 'bad' : 'ok');
    }
    html += `</tbody></table>`;
  }

  html += `</body></html>`;
  return html;
}

async function screenshotUserProfile(page, outDir, prefix) {
  await page.evaluate(() => {
    try {
      window.scrollTo(0, 0);
    } catch {}
  });
  await page.waitForTimeout(300);

  const top = path.join(outDir, `${prefix}-top.png`);
  await page.screenshot({ path: top, fullPage: false });

  const profile = page.locator('#your-profile, #profile-page, form#your-profile').first();
  const profileShot = path.join(outDir, `${prefix}-profile.png`);
  if (await profile.count()) {
    try {
      await profile.scrollIntoViewIfNeeded();
      await page.waitForTimeout(200);
      await profile.screenshot({ path: profileShot });
    } catch {}
  }

  const acfBox = page.locator('.acf-postbox').first();
  const acfShot = path.join(outDir, `${prefix}-acf.png`);
  if (await acfBox.count()) {
    try {
      await acfBox.scrollIntoViewIfNeeded();
      await page.waitForTimeout(200);
      await acfBox.screenshot({ path: acfShot });
    } catch {}
  }

  return { top, profile: profileShot, acf: acfShot };
}

async function extractUserDetails(page, baseUrl) {
  return await page.evaluate((baseUrlInner) => {
    const read = (selector) => {
      const el = document.querySelector(selector);
      if (!el) return '';
      if ('value' in el) return String(el.value || '');
      return String(el.textContent || '');
    };

    const readSelected = (selector) => {
      const el = document.querySelector(selector);
      if (!el || el.tagName !== 'SELECT') return '';
      const opt = el.options && el.selectedIndex >= 0 ? el.options[el.selectedIndex] : null;
      return opt ? String(opt.value || '') : '';
    };

    const normalize = (s) => String(s || '').split(baseUrlInner).join('__BASE__');

    // Core profile fields (wp-admin/user-edit.php).
    let user_login = read('#user_login').trim();
    if (!user_login) user_login = read('input[name="user_login"]').trim();
    if (!user_login) user_login = read('.user-user-login-wrap td, tr.user-user-login-wrap td').trim();

    let user_email = read('#email').trim();
    if (!user_email) user_email = read('input[name="email"]').trim();
    const first_name = read('#first_name').trim();
    const last_name = read('#last_name').trim();
    const nickname = read('#nickname').trim();
    const display_name = readSelected('#display_name') || read('#display_name').trim();
    const user_url = read('#url').trim();
    const description = read('#description').trim();
    const role = readSelected('#role');
    const locale = readSelected('#locale');
    const admin_color = readSelected('#admin_color');
    const rich_editing =
      readSelected('#rich_editing') ||
      (document.querySelector('input[name="rich_editing"]:checked')?.value
        ? String(document.querySelector('input[name="rich_editing"]:checked')?.value || '')
        : read('input[name="rich_editing"]:checked'));

    // ACF UI snapshot (best-effort): label -> visible value text.
    const acf = (() => {
      const box = document.querySelector('.acf-postbox');
      if (!box) return null;
      const fields = Array.from(box.querySelectorAll('.acf-field')).map((field) => {
        const label = (field.querySelector('.acf-label label')?.textContent || '').trim();
        const type = field.getAttribute('data-type') || '';
        const name = field.getAttribute('data-name') || '';

        // Prefer visible selections for complex fields, fallback to first input value.
        let value = '';
        if (type === 'image' || type === 'file') {
          const link = field.querySelector('.file-wrap a');
          const img = field.querySelector('img');
          value = (link && link.getAttribute('href')) || (img && img.getAttribute('src')) || '';
        } else if (type === 'gallery') {
          const imgs = Array.from(field.querySelectorAll('.acf-gallery .thumbnail img, .acf-gallery img')).map((img) =>
            img.getAttribute('src')
          );
          value = imgs.filter(Boolean).join(',');
        } else if (type === 'relationship' || type === 'post_object' || type === 'page_link') {
          const items = Array.from(field.querySelectorAll('.values .acf-rel-item, .values li, .choices .acf-rel-item'))
            .map((el) => (el.textContent || '').trim())
            .filter(Boolean);
          value = items.join('|');
        } else {
          const input = field.querySelector('input,textarea,select');
          if (input && 'value' in input) value = String(input.value || '');
        }

        return { label, name, type, value: normalize(value) };
      });
      return fields;
    })();

    return {
      user_login,
      user_email,
      first_name,
      last_name,
      nickname,
      display_name,
      // Do not normalize `user_url`: it is a user-entered external field and can legitimately
      // point to either site or a third-party domain. Normalizing by baseUrl causes false
      // mismatches when both sites store the same non-local URL.
      user_url,
      description,
      role,
      locale,
      admin_color,
      rich_editing,
      acf,
    };
  }, baseUrl);
}

async function screenshotCommentEdit(page, outDir, prefix) {
  await page.evaluate(() => {
    try {
      window.scrollTo(0, 0);
    } catch {}
  });
  await page.waitForTimeout(300);

  const top = path.join(outDir, `${prefix}-top.png`);
  await page.screenshot({ path: top });

  const content = page.locator('#poststuff, #commentform, form#commentform').first();
  const contentShot = path.join(outDir, `${prefix}-comment.png`);
  if (await content.count()) {
    try {
      await content.scrollIntoViewIfNeeded();
      await page.waitForTimeout(200);
      await content.screenshot({ path: contentShot });
    } catch {}
  }

  return { top, comment: contentShot };
}

function resolveUserIdByLoginOrEmail(site, login, email) {
  const safeLogin = String(login || '').trim();
  const safeEmail = String(email || '').trim();
  const php = `
$login = ${JSON.stringify(safeLogin)};
$email = ${JSON.stringify(safeEmail)};
$u = null;
if ($login !== '') { $u = get_user_by('login', $login); }
if (!$u && $email !== '') { $u = get_user_by('email', $email); }
echo wp_json_encode(['id' => $u ? (int)$u->ID : 0], JSON_UNESCAPED_SLASHES);
`;
  const out = runWpEvalJson({ wpPath: site.wpPath, url: site.baseUrl }, php);
  return Number(out && out.id ? out.id : 0);
}

function resolveCommentIdBySourceId(site, sourceCommentId) {
  const id = Number(sourceCommentId || 0);
  const php = `
global $wpdb;
$source = (string) ${JSON.stringify(String(id))};
$meta_key = '_aie_source_comment_id';
$cid = 0;
if ($source !== '' && $source !== '0') {
  $cid = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key = %s AND meta_value = %s ORDER BY comment_id DESC LIMIT 1",
    $meta_key,
    $source
  ));
}
echo wp_json_encode(['id' => $cid], JSON_UNESCAPED_SLASHES);
`;
  const out = runWpEvalJson({ wpPath: site.wpPath, url: site.baseUrl }, php);
  return Number(out && out.id ? out.id : 0);
}

function resolveSourceCommentIdByTargetId(site, targetCommentId) {
  const id = Number(targetCommentId || 0);
  const php = `
$cid = (int) ${JSON.stringify(id)};
$source = 0;
if ($cid > 0) {
  $v = get_comment_meta($cid, '_aie_source_comment_id', true);
  if ($v !== '' && $v !== null) { $source = (int) $v; }
}
echo wp_json_encode(['id' => $source], JSON_UNESCAPED_SLASHES);
`;
  const out = runWpEvalJson({ wpPath: site.wpPath, url: site.baseUrl }, php);
  return Number(out && out.id ? out.id : 0);
}

function dumpCommentViaWpCli(site, commentId) {
  const id = Number(commentId || 0);
  const php = `
$cid = (int) ${JSON.stringify(id)};
$c = get_comment($cid);
if (!$c) {
  echo wp_json_encode(['error' => 'not_found', 'id' => $cid], JSON_UNESCAPED_SLASHES);
  return;
}
$postId = (int) $c->comment_post_ID;
$post = $postId ? get_post($postId) : null;
$permalink = $postId ? get_permalink($postId) : '';
$slug = $post ? get_page_uri((int)$post->ID) : '';
$out = [
  'comment_ID' => (int) $c->comment_ID,
  'comment_post_ID' => (int) $c->comment_post_ID,
  'post_permalink' => (string) $permalink,
  'post_type' => $post ? (string) $post->post_type : '',
  'post_slug' => (string) $slug,
  'comment_author' => (string) $c->comment_author,
  'comment_author_email' => (string) $c->comment_author_email,
  'comment_author_url' => (string) $c->comment_author_url,
  'comment_author_IP' => (string) $c->comment_author_IP,
  'comment_date' => (string) $c->comment_date,
  'comment_date_gmt' => (string) $c->comment_date_gmt,
  'comment_approved' => (string) $c->comment_approved,
  'comment_agent' => (string) $c->comment_agent,
  'comment_type' => (string) $c->comment_type,
  'comment_parent' => (int) $c->comment_parent,
  'user_id' => (int) $c->user_id,
  'comment_content' => (string) $c->comment_content,
];
echo wp_json_encode($out, JSON_UNESCAPED_SLASHES);
`;
  const out = runWpEvalJson({ wpPath: site.wpPath, url: site.baseUrl }, php);
  return out;
}

async function screenshotTermEdit(page, outDir, prefix) {
  await page.evaluate(() => {
    try {
      window.scrollTo(0, 0);
    } catch {}
  });
  await page.waitForTimeout(300);

  const top = path.join(outDir, `${prefix}-top.png`);
  await page.screenshot({ path: top });

  const form = page.locator('#edittag, #addtag, form#edittag').first();
  const formShot = path.join(outDir, `${prefix}-term.png`);
  if (await form.count()) {
    try {
      await form.scrollIntoViewIfNeeded();
      await page.waitForTimeout(200);
      await form.screenshot({ path: formShot });
    } catch {}
  }

  const acfBox = page.locator('.acf-postbox, .acf-field').first();
  const acfShot = path.join(outDir, `${prefix}-acf.png`);
  if (await acfBox.count()) {
    try {
      await acfBox.scrollIntoViewIfNeeded();
      await page.waitForTimeout(200);
      await page.screenshot({ path: acfShot });
    } catch {}
  }

  return { top, term: formShot, acf: acfShot };
}

async function extractTermDetails(page, baseUrl) {
  return await page.evaluate((baseUrlInner) => {
    const read = (selector) => {
      const el = document.querySelector(selector);
      if (!el) return '';
      if ('value' in el) return String(el.value || '');
      return String(el.textContent || '');
    };
    const normalize = (s) => String(s || '').split(baseUrlInner).join('__BASE__');

    const name = read('#name').trim();
    const slug = read('#slug').trim();
    const description = read('#description').trim();

    // ACF UI snapshot (best-effort): label -> visible value text.
    const acf = (() => {
      const root = document.querySelector('#edittag') || document.querySelector('#addtag') || document.body;
      const fields = Array.from(root.querySelectorAll('.acf-field')).map((field) => {
        const label = (field.querySelector('.acf-label label')?.textContent || '').trim();
        const type = field.getAttribute('data-type') || '';
        const nameAttr = field.getAttribute('data-name') || '';

        let value = '';
        if (type === 'image' || type === 'file') {
          const link = field.querySelector('.file-wrap a');
          const img = field.querySelector('img');
          value = (link && link.getAttribute('href')) || (img && img.getAttribute('src')) || '';
        } else if (type === 'gallery') {
          const imgs = Array.from(field.querySelectorAll('.acf-gallery .thumbnail img, .acf-gallery img')).map((img) =>
            img.getAttribute('src')
          );
          value = imgs.filter(Boolean).join(',');
        } else if (type === 'relationship' || type === 'post_object' || type === 'page_link') {
          const items = Array.from(field.querySelectorAll('.values .acf-rel-item, .values li, .choices .acf-rel-item'))
            .map((el) => (el.textContent || '').trim())
            .filter(Boolean);
          value = items.join('|');
        } else {
          const input = field.querySelector('input,textarea,select');
          if (input && 'value' in input) value = String(input.value || '');
        }

        return { label, name: nameAttr, type, value: normalize(value) };
      });
      return fields.length ? fields : null;
    })();

    return { name, slug, description, acf };
  }, baseUrl);
}

function resolveTermIdBySlug(site, taxonomy, slug) {
  const safeTax = String(taxonomy || '').trim();
  const safeSlug = String(slug || '').trim();
  const php = `
$tax = ${JSON.stringify(safeTax)};
$slug = ${JSON.stringify(safeSlug)};
$t = null;
if ($tax !== '' && $slug !== '') { $t = get_term_by('slug', $slug, $tax); }
echo wp_json_encode(['id' => $t ? (int)$t->term_id : 0], JSON_UNESCAPED_SLASHES);
`;
  const out = runWpEvalJson({ wpPath: site.wpPath, url: site.baseUrl }, php);
  return Number(out && out.id ? out.id : 0);
}

function dumpTermViaWpCli(site, taxonomy, termId) {
  const id = Number(termId || 0);
  const safeTax = String(taxonomy || '').trim();
  const php = `
$tid = (int) ${JSON.stringify(id)};
$tax = ${JSON.stringify(safeTax)};
$t = get_term($tid, $tax);
if (!$t || is_wp_error($t)) {
  echo wp_json_encode(['error' => 'not_found', 'id' => $tid, 'taxonomy' => $tax], JSON_UNESCAPED_SLASHES);
  return;
}
$parent = $t->parent ? get_term((int)$t->parent, $t->taxonomy) : null;
$out = [
  'taxonomy' => (string) $t->taxonomy,
  'name' => (string) $t->name,
  'slug' => (string) $t->slug,
  'description' => (string) $t->description,
  'parent_slug' => ($parent && !is_wp_error($parent)) ? (string) $parent->slug : '',
  'parent_name' => ($parent && !is_wp_error($parent)) ? (string) $parent->name : '',
];
echo wp_json_encode($out, JSON_UNESCAPED_SLASHES);
`;
  return runWpEvalJson({ wpPath: site.wpPath, url: site.baseUrl }, php);
}

function resolveProductIdBySku(site, sku) {
  const safeSku = String(sku || '').trim();
  const php = `
$sku = ${JSON.stringify(safeSku)};
$id = 0;
if ($sku !== '') {
  // Prefer a direct postmeta query so the check doesn't depend on WooCommerce
  // lookup tables being up-to-date (imports that write postmeta directly can
  // leave wc_product_meta_lookup stale).
  $q = get_posts([
    'post_type' => 'product',
    'post_status' => 'any',
    'fields' => 'ids',
    'posts_per_page' => 1,
    'orderby' => 'ID',
    'order' => 'DESC',
    'meta_key' => '_sku',
    'meta_value' => $sku,
  ]);
  if (!empty($q)) {
    $id = (int) $q[0];
  } elseif (function_exists('wc_get_product_id_by_sku')) {
    $id = (int) wc_get_product_id_by_sku($sku);
  }
}
echo wp_json_encode(['id' => $id], JSON_UNESCAPED_SLASHES);
`;
  const out = runWpEvalJson({ wpPath: site.wpPath, url: site.baseUrl }, php);
  return Number(out && out.id ? out.id : 0);
}

function dumpProductViaWpCli(site, productId) {
  const id = Number(productId || 0);
  const php = `
$pid = (int) ${JSON.stringify(id)};
$post = get_post($pid);
if (!$post || $post->post_type !== 'product') {
  echo wp_json_encode(['error' => 'not_found', 'id' => $pid], JSON_UNESCAPED_SLASHES);
  return;
}
$p = function_exists('wc_get_product') ? wc_get_product($pid) : null;

$thumb = get_post_thumbnail_id($pid);
$thumbUrl = $thumb ? (string) wp_get_attachment_url($thumb) : '';
$thumbFile = $thumbUrl ? wp_basename(parse_url($thumbUrl, PHP_URL_PATH) ?: $thumbUrl) : '';

$galleryUrls = [];
if ($p && method_exists($p, 'get_gallery_image_ids')) {
  $ids = $p->get_gallery_image_ids();
  if (is_array($ids)) {
    foreach ($ids as $aid) {
      $u = $aid ? (string) wp_get_attachment_url((int)$aid) : '';
      if ($u !== '') { $galleryUrls[] = $u; }
    }
  }
}
$galleryFiles = array_map(function($u) {
  $path = parse_url((string)$u, PHP_URL_PATH);
  return $path ? wp_basename($path) : wp_basename((string)$u);
}, $galleryUrls);

$terms = function($tax) use ($pid) {
  $t = wp_get_post_terms($pid, $tax, ['fields' => 'slugs']);
  if (is_wp_error($t) || !is_array($t)) return [];
  $t = array_values(array_filter(array_map('strval', $t)));
  sort($t);
  return $t;
};

$out = [
  'post_title' => (string) $post->post_title,
  'post_name' => (string) $post->post_name,
  'post_status' => (string) $post->post_status,
  'post_content' => (string) $post->post_content,
  'post_excerpt' => (string) $post->post_excerpt,
  'sku' => $p && method_exists($p,'get_sku') ? (string) $p->get_sku() : '',
  'regular_price' => $p && method_exists($p,'get_regular_price') ? (string) $p->get_regular_price() : '',
  'sale_price' => $p && method_exists($p,'get_sale_price') ? (string) $p->get_sale_price() : '',
  'price' => $p && method_exists($p,'get_price') ? (string) $p->get_price() : '',
  'tax_status' => $p && method_exists($p,'get_tax_status') ? (string) $p->get_tax_status() : '',
  'tax_class' => $p && method_exists($p,'get_tax_class') ? (string) $p->get_tax_class() : '',
  'stock_quantity' => $p && method_exists($p,'get_stock_quantity') ? (string) ($p->get_stock_quantity() ?? '') : '',
  'stock_status' => $p && method_exists($p,'get_stock_status') ? (string) $p->get_stock_status() : '',
  'manage_stock' => $p && method_exists($p,'get_manage_stock') ? (bool) $p->get_manage_stock() : false,
  'backorders' => $p && method_exists($p,'get_backorders') ? (string) $p->get_backorders() : '',
  'downloadable' => $p && method_exists($p,'get_downloadable') ? (bool) $p->get_downloadable() : false,
  'virtual' => $p && method_exists($p,'get_virtual') ? (bool) $p->get_virtual() : false,
  'weight' => $p && method_exists($p,'get_weight') ? (string) $p->get_weight() : '',
  'length' => $p && method_exists($p,'get_length') ? (string) $p->get_length() : '',
  'width' => $p && method_exists($p,'get_width') ? (string) $p->get_width() : '',
  'height' => $p && method_exists($p,'get_height') ? (string) $p->get_height() : '',
  'shipping_class' => $p && method_exists($p,'get_shipping_class') ? (string) $p->get_shipping_class() : '',
  'featured' => $p && method_exists($p,'get_featured') ? (bool) $p->get_featured() : false,
  'catalog_visibility' => $p && method_exists($p,'get_catalog_visibility') ? (string) $p->get_catalog_visibility() : '',
  'total_sales' => $p && method_exists($p,'get_total_sales') ? (string) $p->get_total_sales() : '',
  'average_rating' => $p && method_exists($p,'get_average_rating') ? (string) $p->get_average_rating() : '',
  'review_count' => $p && method_exists($p,'get_review_count') ? (string) $p->get_review_count() : '',
  'product_type' => $terms('product_type'),
  'product_cat' => $terms('product_cat'),
  'product_tag' => $terms('product_tag'),
  'featured_image_filename' => (string) $thumbFile,
  'gallery_filenames' => $galleryFiles,
];

// Optional ACF snapshot (portable for many field types).
if (function_exists('get_fields')) {
  $acf = get_fields($pid);
  if (is_array($acf)) { $out['acf'] = $acf; }
}

echo wp_json_encode($out, JSON_UNESCAPED_SLASHES);
`;
  return runWpEvalJson({ wpPath: site.wpPath, url: site.baseUrl }, php);
}

function listCouponsViaWpCli(site, limit = 20) {
  const safeLimit = Number(limit || 20);
  const php = `
$limit = max(1, (int) ${JSON.stringify(safeLimit)});
$ids = get_posts([
  'post_type' => 'shop_coupon',
  'post_status' => 'any',
  'fields' => 'ids',
  'posts_per_page' => $limit,
  'orderby' => 'ID',
  'order' => 'ASC',
]);
$out = [];
foreach ($ids as $cid) {
  $c = class_exists('WC_Coupon') ? new WC_Coupon((int)$cid) : null;
  $out[] = [
    'id' => (int) $cid,
    'code' => $c && method_exists($c,'get_code') ? (string) $c->get_code() : (string) get_the_title((int)$cid),
    'product_ids_count' => $c && method_exists($c,'get_product_ids') ? count((array)$c->get_product_ids()) : 0,
    'product_cat_count' => $c && method_exists($c,'get_product_categories') ? count((array)$c->get_product_categories()) : 0,
    'emails_count' => $c && method_exists($c,'get_email_restrictions') ? count((array)$c->get_email_restrictions()) : 0,
  ];
}
echo wp_json_encode(['coupons' => $out], JSON_UNESCAPED_SLASHES);
`;
  const out = runWpEvalJson({ wpPath: site.wpPath, url: site.baseUrl }, php);
  return (out && Array.isArray(out.coupons)) ? out.coupons : [];
}

function resolveCouponIdByCode(site, code) {
  const safeCode = String(code || '').trim();
  const php = `
$code = ${JSON.stringify(safeCode)};
$id = 0;
if ($code !== '') {
  if (function_exists('wc_get_coupon_id_by_code')) {
    $id = (int) wc_get_coupon_id_by_code($code);
  }
  if (!$id) {
    $p = function_exists('get_page_by_title') ? get_page_by_title($code, OBJECT, 'shop_coupon') : null;
    if ($p && !is_wp_error($p)) { $id = (int) $p->ID; }
  }
  if (!$id) {
    $q = get_posts([
      'post_type' => 'shop_coupon',
      'post_status' => 'any',
      'fields' => 'ids',
      'posts_per_page' => 1,
      's' => $code,
    ]);
    if (!empty($q)) { $id = (int) $q[0]; }
  }
}
echo wp_json_encode(['id' => $id], JSON_UNESCAPED_SLASHES);
`;
  const out = runWpEvalJson({ wpPath: site.wpPath, url: site.baseUrl }, php);
  return Number(out && out.id ? out.id : 0);
}

function dumpCouponViaWpCli(site, couponId) {
  const id = Number(couponId || 0);
  const php = `
$cid = (int) ${JSON.stringify(id)};
$post = get_post($cid);
if (!$post || $post->post_type !== 'shop_coupon') {
  echo wp_json_encode(['error' => 'not_found', 'id' => $cid], JSON_UNESCAPED_SLASHES);
  return;
}
if (!class_exists('WC_Coupon')) {
  echo wp_json_encode(['error' => 'woocommerce_not_active'], JSON_UNESCAPED_SLASHES);
  return;
}
$c = new WC_Coupon($cid);

$dt = function($d) {
  if (!$d) return '';
  if (is_string($d)) return (string) $d;
  if (is_object($d) && method_exists($d, 'getTimestamp')) {
    return gmdate('Y-m-d H:i:s', $d->getTimestamp());
  }
  return '';
};

$productRef = function($pid) {
  $pid = (int) $pid;
  if ($pid <= 0) return '';
  $p = function_exists('wc_get_product') ? wc_get_product($pid) : null;
  if ($p) {
    $sku = (string) $p->get_sku();
    if ($sku !== '') return 'sku:' . $sku;
  }
  $slug = get_post_field('post_name', $pid);
  if ($slug) return 'slug:' . (string) $slug;
  return 'id:' . (string) $pid;
};

$catRef = function($tid) {
  $tid = (int) $tid;
  if ($tid <= 0) return '';
  $t = get_term($tid, 'product_cat');
  if ($t && !is_wp_error($t) && !empty($t->slug)) return 'slug:' . (string) $t->slug;
  return 'id:' . (string) $tid;
};

$arr = function($v) {
  if (!is_array($v)) return [];
  $v = array_values(array_filter(array_map('strval', $v)));
  sort($v);
  return $v;
};

$arrPortable = function($ids, $fn) {
  $out = [];
  if (is_array($ids)) {
    foreach ($ids as $x) {
      $r = $fn($x);
      if ($r !== '') $out[] = (string) $r;
    }
  }
  $out = array_values(array_unique($out));
  sort($out);
  return $out;
};

$out = [
  'code' => (string) $c->get_code(),
  'description' => (string) $c->get_description(),
  'status' => (string) $c->get_status(),
  'discount_type' => (string) $c->get_discount_type(),
  'amount' => (string) $c->get_amount(),
  'date_expires' => $dt($c->get_date_expires()),
  'usage_count' => (string) $c->get_usage_count(),
  'usage_limit' => (string) $c->get_usage_limit(),
  'usage_limit_per_user' => (string) $c->get_usage_limit_per_user(),
  'limit_usage_to_x_items' => (string) $c->get_limit_usage_to_x_items(),
  'individual_use' => (bool) $c->get_individual_use(),
  'free_shipping' => (bool) $c->get_free_shipping(),
  'exclude_sale_items' => (bool) $c->get_exclude_sale_items(),
  'minimum_amount' => (string) $c->get_minimum_amount(),
  'maximum_amount' => (string) $c->get_maximum_amount(),
  'allowed_emails' => $arr($c->get_email_restrictions()),
  'product_ids' => $arrPortable($c->get_product_ids(), $productRef),
  'excluded_product_ids' => $arrPortable($c->get_excluded_product_ids(), $productRef),
  'product_categories' => $arrPortable($c->get_product_categories(), $catRef),
  'excluded_product_categories' => $arrPortable($c->get_excluded_product_categories(), $catRef),
];

echo wp_json_encode($out, JSON_UNESCAPED_SLASHES);
`;
  return runWpEvalJson({ wpPath: site.wpPath, url: site.baseUrl }, php);
}

function listWooAttributesViaWpCli(site, limit = 50) {
  const safeLimit = Number(limit || 50);
  const php = `
$limit = max(1, (int) ${JSON.stringify(safeLimit)});
if (!function_exists('wc_get_attribute_taxonomies')) {
  echo wp_json_encode(['error' => 'woocommerce_not_active', 'attributes' => []], JSON_UNESCAPED_SLASHES);
  return;
}
$attrs = wc_get_attribute_taxonomies();
if (!is_array($attrs)) { $attrs = []; }
$out = [];
foreach ($attrs as $a) {
  $name = isset($a->attribute_name) ? (string) $a->attribute_name : '';
  $tax = $name !== '' && function_exists('wc_attribute_taxonomy_name') ? (string) wc_attribute_taxonomy_name($name) : '';
  $termCount = ($tax !== '' && taxonomy_exists($tax)) ? (int) wp_count_terms(['taxonomy' => $tax, 'hide_empty' => false]) : 0;
  $out[] = [
    'attribute_id' => (int) ($a->attribute_id ?? 0),
    'attribute_name' => $name,
    'attribute_label' => (string) ($a->attribute_label ?? ''),
    'attribute_type' => (string) ($a->attribute_type ?? ''),
    'attribute_orderby' => (string) ($a->attribute_orderby ?? ''),
    'attribute_public' => (int) ($a->attribute_public ?? 0),
    'taxonomy' => $tax,
    'term_count' => $termCount,
  ];
}
usort($out, function($x,$y){ return ($y['term_count'] ?? 0) <=> ($x['term_count'] ?? 0); });
$out = array_slice($out, 0, $limit);
echo wp_json_encode(['attributes' => $out], JSON_UNESCAPED_SLASHES);
`;
  const out = runWpEvalJson({ wpPath: site.wpPath, url: site.baseUrl }, php);
  return out && Array.isArray(out.attributes) ? out.attributes : [];
}

function dumpWooAttributeViaWpCli(site, attributeName) {
  const safeName = String(attributeName || '').trim();
  const php = `
$name = ${JSON.stringify(safeName)};
if (!function_exists('wc_get_attribute_taxonomies')) {
  echo wp_json_encode(['error' => 'woocommerce_not_active'], JSON_UNESCAPED_SLASHES);
  return;
}
$attrs = wc_get_attribute_taxonomies();
if (!is_array($attrs)) { $attrs = []; }
$attr = null;
foreach ($attrs as $a) {
  if (!isset($a->attribute_name)) continue;
  if ((string)$a->attribute_name === $name) { $attr = $a; break; }
}
if (!$attr) {
  echo wp_json_encode(['error' => 'not_found', 'attribute_name' => $name], JSON_UNESCAPED_SLASHES);
  return;
}
$tax = function_exists('wc_attribute_taxonomy_name') ? (string) wc_attribute_taxonomy_name($name) : ('pa_' . $name);

$termMetaPortable = function($term_id) {
  $meta = get_term_meta((int)$term_id);
  if (!is_array($meta) || empty($meta)) return (object)[];

  $convert = function($v) use (&$convert) {
    $v = maybe_unserialize($v);
    if (is_array($v)) {
      foreach ($v as $kk => $vv) { $v[$kk] = $convert($vv); }
      return $v;
    }
    if (is_object($v)) {
      if ($v instanceof stdClass) return $convert((array)$v);
      return $v;
    }
    if (is_numeric($v)) {
      $id = (int)$v;
      if ($id > 0 && get_post_type($id) === 'attachment') {
        $url = wp_get_attachment_url($id);
        if ($url) {
          $path = parse_url($url, PHP_URL_PATH);
          $base = $path ? basename($path) : basename($url);
          if ($base) return 'file:' . $base;
        }
        $file = get_post_meta($id, '_wp_attached_file', true);
        if ($file) return 'file:' . basename((string)$file);
      }
    }
    return $v;
  };

  $out = [];
  foreach ($meta as $k => $vals) {
    if (!is_array($vals)) continue;
    $val = (count($vals) === 1) ? $vals[0] : $vals;
    $out[$k] = $convert($val);
  }
  ksort($out);
  return $out;
};

$terms = [];
if (taxonomy_exists($tax)) {
  $rawTerms = get_terms(['taxonomy' => $tax, 'hide_empty' => false]);
  if (!is_wp_error($rawTerms) && is_array($rawTerms)) {
    foreach ($rawTerms as $t) {
      $terms[] = [
        'name' => (string) $t->name,
        'slug' => (string) $t->slug,
        'description' => (string) $t->description,
        'meta' => $termMetaPortable((int)$t->term_id),
      ];
    }
  }
}
usort($terms, function($a,$b){ return strcmp(($a['slug'] ?? '') . '|' . ($a['name'] ?? ''), ($b['slug'] ?? '') . '|' . ($b['name'] ?? '')); });

$out = [
  'attribute_name' => (string) ($attr->attribute_name ?? ''),
  'attribute_label' => (string) ($attr->attribute_label ?? ''),
  'attribute_type' => (string) ($attr->attribute_type ?? ''),
  'attribute_orderby' => (string) ($attr->attribute_orderby ?? ''),
  'attribute_public' => (int) ($attr->attribute_public ?? 0),
  'taxonomy' => (string) $tax,
  'terms' => $terms,
];
echo wp_json_encode($out, JSON_UNESCAPED_SLASHES);
`;
  return runWpEvalJson({ wpPath: site.wpPath, url: site.baseUrl }, php);
}

function getDbTableSchemaViaWpCli(site, tableName) {
  const safe = String(tableName || '').trim();
  const php = `
$table = ${JSON.stringify(safe)};
global $wpdb;
if ($table === '') {
  echo wp_json_encode(['error' => 'missing_table'], JSON_UNESCAPED_SLASHES);
  return;
}
$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
if (!$exists) {
  echo wp_json_encode(['error' => 'not_found', 'table' => $table], JSON_UNESCAPED_SLASHES);
  return;
}
$cols = $wpdb->get_results(
  $wpdb->prepare(
    "SELECT COLUMN_NAME as name, DATA_TYPE as type, COLUMN_KEY as col_key
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
     ORDER BY ORDINAL_POSITION",
    DB_NAME,
    $table
  )
);
$columns = [];
$primary = [];
if (is_array($cols)) {
  foreach ($cols as $c) {
    $name = (string) ($c->name ?? '');
    $key = (string) ($c->col_key ?? '');
    if ($name === '') continue;
    $columns[] = $name;
    if ($key === 'PRI') $primary[] = $name;
  }
}
echo wp_json_encode(['table' => $table, 'columns' => $columns, 'primary' => $primary], JSON_UNESCAPED_SLASHES);
`;
  return runWpEvalJson({ wpPath: site.wpPath, url: site.baseUrl }, php);
}

function dumpDbTableViaWpCli(site, tableName, { sampleLimit = 25, chunkSize = 500 } = {}) {
  const safe = String(tableName || '').trim();
  const php = `
$table = ${JSON.stringify(safe)};
$sampleLimit = max(0, (int) ${JSON.stringify(Number(sampleLimit || 25))});
$chunk = max(1, (int) ${JSON.stringify(Number(chunkSize || 500))});
global $wpdb;
$bt = chr(96); // backtick for SQL identifiers

if ($table === '') {
  echo wp_json_encode(['error' => 'missing_table'], JSON_UNESCAPED_SLASHES);
  return;
}

$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
if (!$exists) {
  echo wp_json_encode(['error' => 'not_found', 'table' => $table], JSON_UNESCAPED_SLASHES);
  return;
}

// Columns + primary key(s)
$cols = $wpdb->get_results(
  $wpdb->prepare(
    "SELECT COLUMN_NAME as name, COLUMN_KEY as col_key
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
     ORDER BY ORDINAL_POSITION",
    DB_NAME,
    $table
  )
);
$columns = [];
$primary = [];
if (is_array($cols)) {
  foreach ($cols as $c) {
    $name = (string) ($c->name ?? '');
    if ($name === '') continue;
    $columns[] = $name;
    if ((string) ($c->col_key ?? '') === 'PRI') $primary[] = $name;
  }
}

$count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$bt}{$table}{$bt}");

// Stable ordering.
$orderCols = !empty($primary) ? $primary : ( !empty($columns) ? [ $columns[0] ] : [] );
$orderBy = '';
if (!empty($orderCols)) {
  $orderBy = ' ORDER BY ' . implode(', ', array_map(function($c) use ($bt){ return $bt . str_replace($bt,'', $c) . $bt; }, $orderCols));
}

$ctx = hash_init('sha256');
$offset = 0;
while ($offset < $count) {
  $sql = "SELECT * FROM {$bt}{$table}{$bt}{$orderBy} LIMIT {$chunk} OFFSET {$offset}";
  $rows = $wpdb->get_results($sql, ARRAY_A);
  if (empty($rows)) break;
  foreach ($rows as $r) {
    if (is_array($r)) { ksort($r); }
    hash_update($ctx, wp_json_encode($r, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    hash_update($ctx, \"\\n\");
  }
  $offset += $chunk;
}
$hash = hash_final($ctx);

$sample = [];
if ($sampleLimit > 0) {
  $sql = "SELECT * FROM {$bt}{$table}{$bt}{$orderBy} LIMIT {$sampleLimit}";
  $rows = $wpdb->get_results($sql, ARRAY_A);
  if (is_array($rows)) {
    foreach ($rows as $r) {
      if (is_array($r)) { ksort($r); }
      $sample[] = $r;
    }
  }
}

echo wp_json_encode([
  'table' => $table,
  'count' => $count,
  'columns' => $columns,
  'primary' => $primary,
  'order_by' => $orderCols,
  'hash' => $hash,
  'sample' => $sample,
], JSON_UNESCAPED_SLASHES);
`;
  return runWpEvalJson({ wpPath: site.wpPath, url: site.baseUrl }, php);
}

function dumpOrderViaWpCli(site, orderId) {
  const id = Number(orderId || 0);
  const php = `
$oid = (int) ${JSON.stringify(id)};
if (!function_exists('wc_get_order')) {
  echo wp_json_encode(['error' => 'woocommerce_not_active'], JSON_UNESCAPED_SLASHES);
  return;
}
$order = wc_get_order($oid);
if (!$order || is_wp_error($order)) {
  echo wp_json_encode(['error' => 'not_found', 'id' => $oid], JSON_UNESCAPED_SLASHES);
  return;
}

$dt = function($d) {
  if (!$d) return '';
  if (is_string($d)) return (string) $d;
  if (is_object($d) && method_exists($d, 'getTimestamp')) {
    return gmdate('Y-m-d H:i:s', $d->getTimestamp());
  }
  return '';
};

$addr = function($prefix) use ($order) {
  $fields = ['first_name','last_name','company','address_1','address_2','city','state','postcode','country','email','phone'];
  $out = [];
  foreach ($fields as $f) {
    $getter = 'get_' . $prefix . '_' . $f;
    $out[$f] = method_exists($order, $getter) ? (string) $order->$getter() : '';
  }
  return $out;
};

$line_items = [];
foreach ($order->get_items('line_item') as $it) {
  $product = $it->get_product();
  $sku = $product ? (string) $product->get_sku() : '';
  $line_items[] = [
    'sku' => $sku,
    'name' => (string) $it->get_name(),
    'quantity' => (int) $it->get_quantity(),
    'subtotal' => (string) $it->get_subtotal(),
    'total' => (string) $it->get_total(),
    'subtotal_tax' => (string) $it->get_subtotal_tax(),
    'total_tax' => (string) $it->get_total_tax(),
  ];
}
usort($line_items, function($a,$b){
  return strcmp(($a['sku'] ?? '') . '|' . ($a['name'] ?? ''), ($b['sku'] ?? '') . '|' . ($b['name'] ?? ''));
});

$shipping_lines = [];
foreach ($order->get_items('shipping') as $it) {
  $shipping_lines[] = [
    'method_id' => (string) $it->get_method_id(),
    'method_title' => (string) $it->get_method_title(),
    'total' => (string) $it->get_total(),
    'total_tax' => (string) $it->get_total_tax(),
  ];
}
usort($shipping_lines, function($a,$b){
  return strcmp(($a['method_id'] ?? '') . '|' . ($a['method_title'] ?? ''), ($b['method_id'] ?? '') . '|' . ($b['method_title'] ?? ''));
});

$fee_lines = [];
foreach ($order->get_items('fee') as $it) {
  $fee_lines[] = [
    'name' => (string) $it->get_name(),
    'total' => (string) $it->get_total(),
    'total_tax' => (string) $it->get_total_tax(),
  ];
}
usort($fee_lines, function($a,$b){ return strcmp(($a['name'] ?? ''), ($b['name'] ?? '')); });

$coupon_lines = [];
foreach ($order->get_items('coupon') as $it) {
  $coupon_lines[] = [
    'code' => (string) $it->get_code(),
    'discount' => (string) $it->get_discount(),
    'discount_tax' => (string) $it->get_discount_tax(),
  ];
}
usort($coupon_lines, function($a,$b){ return strcmp(($a['code'] ?? ''), ($b['code'] ?? '')); });

$notes = [];
if (function_exists('wc_get_order_notes')) {
  $order_notes = wc_get_order_notes(['order_id' => $oid]);
  if (is_array($order_notes)) {
    foreach ($order_notes as $n) {
      $notes[] = [
        'content' => (string) ($n->content ?? ''),
        'type' => (string) ($n->type ?? ''),
      ];
    }
  }
}

$metaOrderNumber = (string) $order->get_meta('_order_number', true);
// Portable order number (meta when present; falls back to WC order number).
$portableOrderNumber = $metaOrderNumber !== '' ? $metaOrderNumber : (string) $order->get_order_number();
usort($notes, function($a,$b){
  return strcmp(($a['type'] ?? '') . '|' . ($a['content'] ?? ''), ($b['type'] ?? '') . '|' . ($b['content'] ?? ''));
});

$out = [
  'order_number' => $portableOrderNumber,
  'order_key' => (string) $order->get_order_key(),
  'status' => (string) $order->get_status(),
  'currency' => (string) $order->get_currency(),
  'date_created_gmt' => $dt($order->get_date_created()),
  'date_modified_gmt' => $dt($order->get_date_modified()),
  'date_paid_gmt' => $dt($order->get_date_paid()),
  'date_completed_gmt' => $dt($order->get_date_completed()),
  'customer_id' => (int) $order->get_customer_id(),
  'customer_note' => (string) $order->get_customer_note(),
  'payment_method' => (string) $order->get_payment_method(),
  'payment_method_title' => (string) $order->get_payment_method_title(),
  'transaction_id' => (string) $order->get_transaction_id(),
  'totals' => [
    'discount_total' => (string) $order->get_discount_total(),
    'discount_tax' => (string) $order->get_discount_tax(),
    'shipping_total' => (string) $order->get_shipping_total(),
    'shipping_tax' => (string) $order->get_shipping_tax(),
    'cart_tax' => (string) $order->get_cart_tax(),
    'total_tax' => (string) $order->get_total_tax(),
    'total' => (string) $order->get_total(),
    'subtotal' => (string) $order->get_subtotal(),
  ],
  'billing' => $addr('billing'),
  'shipping' => $addr('shipping'),
  'line_items' => $line_items,
  'shipping_lines' => $shipping_lines,
  'fee_lines' => $fee_lines,
  'coupon_lines' => $coupon_lines,
  'notes' => $notes,
  'meta_order_number' => $portableOrderNumber,
];

echo wp_json_encode($out, JSON_UNESCAPED_SLASHES);
  `;
  return runWpEvalJson({ wpPath: site.wpPath, url: site.baseUrl }, php);
}

function resolveOrderIdByOrderKey(site, orderKey) {
  const key = String(orderKey || '').trim();
  if (!key) return 0;
  const php = `
$key = ${JSON.stringify(key)};
if (!function_exists('wc_get_orders')) { echo wp_json_encode(['id'=>0], JSON_UNESCAPED_SLASHES); return; }
$ids = wc_get_orders(['limit'=>1, 'return'=>'ids', 'order_key'=>$key]);
$id = (!empty($ids) && is_array($ids)) ? (int) $ids[0] : 0;
echo wp_json_encode(['id'=>$id], JSON_UNESCAPED_SLASHES);
  `;
  const out = runWpEvalJson({ wpPath: site.wpPath, url: site.baseUrl }, php);
  return Number(out && out.id ? out.id : 0);
}

function resolveOrderIdByOrderNumber(site, orderNumber) {
  const num = String(orderNumber || '').trim();
  if (!num) return 0;
  const php = `
$num = ${JSON.stringify(num)};
if (!function_exists('wc_get_orders')) { echo wp_json_encode(['id'=>0], JSON_UNESCAPED_SLASHES); return; }
$orders = wc_get_orders(['meta_key'=>'_order_number','meta_value'=>$num,'limit'=>1]);
if (!empty($orders)) { echo wp_json_encode(['id'=>(int)$orders[0]->get_id()], JSON_UNESCAPED_SLASHES); return; }
if (is_numeric($num)) { $o = wc_get_order((int)$num); if ($o) { echo wp_json_encode(['id'=>(int)$o->get_id()], JSON_UNESCAPED_SLASHES); return; } }
echo wp_json_encode(['id'=>0], JSON_UNESCAPED_SLASHES);
  `;
  const out = runWpEvalJson({ wpPath: site.wpPath, url: site.baseUrl }, php);
  return Number(out && out.id ? out.id : 0);
}

async function gotoOrderEdit(page, site, orderId) {
  const id = Number(orderId || 0);
  if (!id) throw new Error('gotoOrderEdit requires orderId');

  // Try classic post.php screen first (works when orders are stored as posts).
  await gotoAdminPage(page, site, `/wp-admin/post.php?post=${id}&action=edit`);
  const isOrderData = await page.locator('#order_data, .woocommerce-order-data, .wc-order-data').count();
  if (isOrderData) return;

  // HPOS screen (WooCommerce > Orders).
  await gotoAdminPage(page, site, `/wp-admin/admin.php?page=wc-orders&action=edit&id=${id}`);
}

async function main() {
  const env = loadEnv();
  if (env.contentType === 'custom_post_types' && !env.customPostType) {
    throw new Error('Set AIE_CUSTOM_POST_TYPE when using AIE_CONTENT_TYPE=custom_post_types');
  }
  const artifactsDir = path.resolve(process.cwd(), 'e2e', 'artifacts', `aie-visual-${env.contentType}`);
  fs.mkdirSync(artifactsDir, { recursive: true });

  const browser = await chromium.launch({ headless: env.headless });
  const context = await browser.newContext({ acceptDownloads: true, viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  const frontPage = await context.newPage();

  const source = {
    baseUrl: env.sourceUrl,
    username: env.sourceUser,
    password: env.sourcePass,
    customPostType: env.customPostType,
    taxonomy: env.taxonomy,
    taxonomyPostType: env.taxonomyPostType,
  };
  const target = {
    baseUrl: env.targetUrl,
    username: env.targetUser,
    password: env.targetPass,
    customPostType: env.customPostType,
    taxonomy: env.taxonomy,
    taxonomyPostType: env.taxonomyPostType,
  };

  try {
    const exported = await exportAllItems(page, source, env.contentType);
    const download = exported.download;
    const exportMeta = exported.meta || {};

    const exportPath = path.join(artifactsDir, download.suggestedFilename() || `export-${Date.now()}.csv`);
    await download.saveAs(exportPath);
    console.log(`[export] Saved: ${exportPath}`);

    const importMeta = { ...exportMeta };
    if (env.contentType === 'database_table' && importMeta.tableName) {
      const schema = getDbTableSchemaViaWpCli({ wpPath: env.sourceWpPath, baseUrl: env.sourceUrl }, importMeta.tableName);
      const primary = schema && Array.isArray(schema.primary) ? schema.primary.filter(Boolean) : [];
      const columns = schema && Array.isArray(schema.columns) ? schema.columns.filter(Boolean) : [];
      const preferred = primary.length ? [primary[0]] : columns.length ? [columns[0]] : [];
      importMeta.uniqueFieldPreferred = preferred;
    }

    await importItems(page, target, exportPath, env.contentType, importMeta);
    console.log('[import] Completed');

    const results = [];

    if (env.contentType === 'menu') {
      const sourceDumpRaw = dumpMenusViaWpCli({ wpPath: env.sourceWpPath, url: env.sourceUrl });
      const targetDumpRaw = dumpMenusViaWpCli({ wpPath: env.targetWpPath, url: env.targetUrl });

      const sourceDump = normalizeMenuDump(sourceDumpRaw, env.sourceUrl);
      const targetDump = normalizeMenuDump(targetDumpRaw, env.targetUrl);

      const pickMenus = () => {
        if (env.sourceMenuNames && env.sourceMenuNames.length) return env.sourceMenuNames;
        return (sourceDump.menus || []).slice(0, 2).map((m) => m.name);
      };
      const menuNames = pickMenus().filter(Boolean);
      if (!menuNames.length) throw new Error('No menus found on source site');

      const compared = [];

      for (const name of menuNames) {
        const safe = slugify(name || 'menu');
        const dir = path.join(artifactsDir, `menu-${safe || 'menu'}`);
        fs.mkdirSync(dir, { recursive: true });

        const sourceMenu = (sourceDump.menus || []).find((m) => String(m.name).toLowerCase() === String(name).toLowerCase());
        const targetMenu = (targetDump.menus || []).find((m) => String(m.name).toLowerCase() === String(name).toLowerCase());

        const sourceItems = sourceMenu ? sourceMenu.items || [] : [];
        const targetItems = targetMenu ? targetMenu.items || [] : [];

        const hasInvalidItems = Boolean(sourceItems.some((it) => it.invalid) || targetItems.some((it) => it.invalid));

        const normalizeCompare = (items) =>
          items.map((it) => ({
            depth: it.depth,
            title: it.title,
            type: it.type,
            object: it.object,
            resolved_url: it.resolved_url,
            invalid: it.invalid,
          }));

        const structureEqual = JSON.stringify(normalizeCompare(sourceItems)) === JSON.stringify(normalizeCompare(targetItems));

        const itemResult = {
          menuName: name,
          structureEqual,
          hasInvalidItems,
          source: sourceMenu || null,
          target: targetMenu || null,
        };
        results.push(itemResult);
        fs.writeFileSync(path.join(dir, 'result.json'), JSON.stringify(itemResult, null, 2));

        compared.push({ menuName: name, structureEqual, hasInvalidItems, sourceItems, targetItems });
        console.log(`[compare] menu "${name}" structureEqual=${String(structureEqual)} invalid=${String(hasInvalidItems)}`);
      }

      const reportHtml = buildMenuCompareHtml({ source, target, compared });
      const reportPath = path.join(artifactsDir, 'menu-compare.html');
      fs.writeFileSync(reportPath, reportHtml);

      const reportShot = path.join(artifactsDir, 'menu-compare.png');
      await page.goto(`file://${reportPath}`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(300);
      await page.screenshot({ path: reportShot, fullPage: true });

      fs.writeFileSync(
        path.join(artifactsDir, 'summary.json'),
        JSON.stringify({ contentType: env.contentType, report: { html: reportPath, screenshot: reportShot }, results }, null, 2)
      );
      console.log(`[done] Summary: ${path.join(artifactsDir, 'summary.json')}`);
      return;
    }

    if (env.contentType === 'user') {
      for (const sourceUserId of env.sourceIds) {
        await gotoAdminPage(page, source, `/wp-admin/user-edit.php?user_id=${sourceUserId}`);

        const sourceDetails = await extractUserDetails(page, env.sourceUrl);
        const safe = slugify(sourceDetails.user_login || String(sourceUserId));
        const dir = path.join(artifactsDir, `${String(sourceUserId).padStart(4, '0')}-${safe || 'user'}`);
        fs.mkdirSync(dir, { recursive: true });

        const sourceShots = await screenshotUserProfile(page, dir, 'source');

        const targetUserId = resolveUserIdByLoginOrEmail(
          { wpPath: env.targetWpPath, baseUrl: env.targetUrl },
          sourceDetails.user_login,
          sourceDetails.user_email
        );
        if (!targetUserId) {
          throw new Error(`Could not find imported user on target for login=${sourceDetails.user_login} email=${sourceDetails.user_email}`);
        }

        await gotoAdminPage(page, target, `/wp-admin/user-edit.php?user_id=${targetUserId}`);
        const targetDetails = await extractUserDetails(page, env.targetUrl);
        const targetShots = await screenshotUserProfile(page, dir, 'target');

        const contentEqual = JSON.stringify(sourceDetails) === JSON.stringify(targetDetails);
        const itemResult = {
          sourceUserId,
          targetUserId,
          user_login: sourceDetails.user_login,
          contentEqual,
          screenshots: { source: sourceShots, target: targetShots },
          source: sourceDetails,
          target: targetDetails,
        };
        results.push(itemResult);
        fs.writeFileSync(path.join(dir, 'result.json'), JSON.stringify(itemResult, null, 2));

        console.log(`[compare] user ${sourceUserId} "${sourceDetails.user_login}" contentEqual=${String(contentEqual)}`);
      }

      fs.writeFileSync(path.join(artifactsDir, 'summary.json'), JSON.stringify({ contentType: env.contentType, results }, null, 2));
      console.log(`[done] Summary: ${path.join(artifactsDir, 'summary.json')}`);
      return;
    }

    if (env.contentType === 'comment' || env.contentType === 'comments') {
      const sourceSite = { wpPath: env.sourceWpPath, baseUrl: env.sourceUrl };
      const targetSite = { wpPath: env.targetWpPath, baseUrl: env.targetUrl };

      for (const sourceCommentId of env.sourceIds) {
        const safe = slugify(`comment-${sourceCommentId}`);
        const dir = path.join(artifactsDir, `${String(sourceCommentId).padStart(4, '0')}-${safe || 'comment'}`);
        fs.mkdirSync(dir, { recursive: true });

        await gotoAdminPage(page, source, `/wp-admin/comment.php?action=editcomment&c=${sourceCommentId}`);
        const sourceShots = await screenshotCommentEdit(page, dir, 'source');
        const sourceDump = dumpCommentViaWpCli(sourceSite, sourceCommentId);

        const targetCommentId = resolveCommentIdBySourceId(targetSite, sourceCommentId);
        if (!targetCommentId) {
          throw new Error(`Could not find imported comment on target for source comment_ID=${sourceCommentId} (missing _aie_source_comment_id meta)`);
        }

        await gotoAdminPage(page, target, `/wp-admin/comment.php?action=editcomment&c=${targetCommentId}`);
        const targetShots = await screenshotCommentEdit(page, dir, 'target');
        const targetDump = dumpCommentViaWpCli(targetSite, targetCommentId);

        const normalizeDump = (dump, baseUrl) => {
          const normalize = (s) => String(s || '').split(baseUrl).join('__BASE__');
          return {
            post_permalink: normalize(dump.post_permalink),
            post_type: dump.post_type,
            post_slug: dump.post_slug,
            comment_author: dump.comment_author,
            comment_author_email: dump.comment_author_email,
            comment_author_url: normalize(dump.comment_author_url),
            comment_author_IP: dump.comment_author_IP,
            comment_date: dump.comment_date,
            comment_date_gmt: dump.comment_date_gmt,
            comment_approved: dump.comment_approved,
            comment_agent: dump.comment_agent,
            comment_type: dump.comment_type,
            comment_content: normalize(dump.comment_content),
          };
        };

        const targetParentSourceId = targetDump.comment_parent
          ? resolveSourceCommentIdByTargetId(targetSite, targetDump.comment_parent)
          : 0;

        const normalizedSource = {
          ...normalizeDump(sourceDump, env.sourceUrl),
          comment_parent_source_id: sourceDump.comment_parent || 0,
        };
        const normalizedTarget = {
          ...normalizeDump(targetDump, env.targetUrl),
          comment_parent_source_id: targetParentSourceId || 0,
        };

        const contentEqual = JSON.stringify(normalizedSource) === JSON.stringify(normalizedTarget);

        const itemResult = {
          sourceCommentId,
          targetCommentId,
          contentEqual,
          screenshots: { source: sourceShots, target: targetShots },
          source: normalizedSource,
          target: normalizedTarget,
        };
        results.push(itemResult);
        fs.writeFileSync(path.join(dir, 'result.json'), JSON.stringify(itemResult, null, 2));
        console.log(`[compare] comment ${sourceCommentId} -> ${targetCommentId} contentEqual=${String(contentEqual)}`);
      }

      fs.writeFileSync(path.join(artifactsDir, 'summary.json'), JSON.stringify({ contentType: env.contentType, results }, null, 2));
      console.log(`[done] Summary: ${path.join(artifactsDir, 'summary.json')}`);
      return;
    }

    if (env.contentType === 'taxonomy') {
      const taxonomy = env.taxonomy || 'category';
      const postType = env.taxonomyPostType || 'post';
      const sourceSite = { wpPath: env.sourceWpPath, baseUrl: env.sourceUrl };
      const targetSite = { wpPath: env.targetWpPath, baseUrl: env.targetUrl };

      for (const sourceTermId of env.sourceIds) {
        const safe = slugify(`term-${taxonomy}-${sourceTermId}`);
        const dir = path.join(artifactsDir, `${String(sourceTermId).padStart(4, '0')}-${safe || 'term'}`);
        fs.mkdirSync(dir, { recursive: true });

        await gotoAdminPage(page, source, `/wp-admin/term.php?taxonomy=${encodeURIComponent(taxonomy)}&tag_ID=${sourceTermId}&post_type=${encodeURIComponent(postType)}`);
        const sourceShots = await screenshotTermEdit(page, dir, 'source');
        const sourceDetails = await extractTermDetails(page, env.sourceUrl);
        const sourceDump = dumpTermViaWpCli(sourceSite, taxonomy, sourceTermId);

        if (sourceDump && sourceDump.error) {
          throw new Error(`Could not dump source term ${sourceTermId} in taxonomy=${taxonomy}: ${sourceDump.error}`);
        }

        const targetTermId = resolveTermIdBySlug(targetSite, taxonomy, sourceDump.slug);
        if (!targetTermId) {
          throw new Error(`Could not find imported term on target for taxonomy=${taxonomy} slug=${sourceDump.slug}`);
        }

        await gotoAdminPage(page, target, `/wp-admin/term.php?taxonomy=${encodeURIComponent(taxonomy)}&tag_ID=${targetTermId}&post_type=${encodeURIComponent(postType)}`);
        const targetShots = await screenshotTermEdit(page, dir, 'target');
        const targetDetails = await extractTermDetails(page, env.targetUrl);
        const targetDump = dumpTermViaWpCli(targetSite, taxonomy, targetTermId);

        const normalizeDump = (dump, baseUrl) => {
          const normalize = (s) => String(s || '').split(baseUrl).join('__BASE__');
          return {
            taxonomy: dump.taxonomy,
            name: dump.name,
            slug: dump.slug,
            description: normalize(dump.description),
            parent_slug: dump.parent_slug,
            parent_name: dump.parent_name,
          };
        };

        const normalizedSource = { ...normalizeDump(sourceDump, env.sourceUrl), acf: sourceDetails.acf };
        const normalizedTarget = { ...normalizeDump(targetDump, env.targetUrl), acf: targetDetails.acf };

        const contentEqual = JSON.stringify(normalizedSource) === JSON.stringify(normalizedTarget);
        const itemResult = {
          taxonomy,
          sourceTermId,
          targetTermId,
          slug: sourceDump.slug,
          contentEqual,
          screenshots: { source: sourceShots, target: targetShots },
          source: normalizedSource,
          target: normalizedTarget,
        };

        results.push(itemResult);
        fs.writeFileSync(path.join(dir, 'result.json'), JSON.stringify(itemResult, null, 2));
        console.log(`[compare] taxonomy ${taxonomy} term ${sourceTermId} -> ${targetTermId} "${sourceDump.slug}" contentEqual=${String(contentEqual)}`);
      }

      fs.writeFileSync(path.join(artifactsDir, 'summary.json'), JSON.stringify({ contentType: env.contentType, taxonomy, results }, null, 2));
      console.log(`[done] Summary: ${path.join(artifactsDir, 'summary.json')}`);
      return;
    }

    if (env.contentType === 'woo_attribute' || env.contentType === 'attribute') {
      const sourceSite = { wpPath: env.sourceWpPath, baseUrl: env.sourceUrl };
      const targetSite = { wpPath: env.targetWpPath, baseUrl: env.targetUrl };

      const attrs = listWooAttributesViaWpCli(sourceSite, 50);
      if (!attrs.length) throw new Error('No WooCommerce attributes found on source site');

      const picked = [...attrs].sort((a, b) => (b.term_count || 0) - (a.term_count || 0)).slice(0, Math.min(3, attrs.length));

      for (const a of picked) {
        const name = String(a.attribute_name || '').trim();
        if (!name) continue;
        const dir = path.join(artifactsDir, `attr-${slugify(name) || name}`);
        fs.mkdirSync(dir, { recursive: true });

        // Source screenshots
        await gotoAdminPage(page, source, `/wp-admin/edit.php?post_type=product&page=product_attributes&edit=${encodeURIComponent(String(a.attribute_id || ''))}`);
        const sourceAttrShot = path.join(dir, 'source-attributes.png');
        await page.screenshot({ path: sourceAttrShot, fullPage: true });

        const sourceDumpRaw = dumpWooAttributeViaWpCli(sourceSite, name);
        if (sourceDumpRaw && sourceDumpRaw.error) {
          throw new Error(`Could not dump source attribute "${name}": ${sourceDumpRaw.error}`);
        }

        const tax = String(sourceDumpRaw.taxonomy || '');
        if (tax) {
          await gotoAdminPage(page, source, `/wp-admin/edit-tags.php?taxonomy=${encodeURIComponent(tax)}&post_type=product`);
          await page.screenshot({ path: path.join(dir, 'source-terms.png'), fullPage: true });
        }

        // Target resolve
        const targetAttrs = listWooAttributesViaWpCli(targetSite, 200);
        const targetAttr = targetAttrs.find((x) => String(x.attribute_name || '') === name);
        if (!targetAttr) {
          throw new Error(`Could not find imported attribute on target for attribute_name="${name}"`);
        }

        await gotoAdminPage(page, target, `/wp-admin/edit.php?post_type=product&page=product_attributes&edit=${encodeURIComponent(String(targetAttr.attribute_id || ''))}`);
        const targetAttrShot = path.join(dir, 'target-attributes.png');
        await page.screenshot({ path: targetAttrShot, fullPage: true });

        const targetDumpRaw = dumpWooAttributeViaWpCli(targetSite, name);
        if (targetDumpRaw && targetDumpRaw.error) {
          throw new Error(`Could not dump target attribute "${name}": ${targetDumpRaw.error}`);
        }

        if (tax) {
          await gotoAdminPage(page, target, `/wp-admin/edit-tags.php?taxonomy=${encodeURIComponent(tax)}&post_type=product`);
          await page.screenshot({ path: path.join(dir, 'target-terms.png'), fullPage: true });
        }

        const normalize = (dump) => {
          const copy = JSON.parse(JSON.stringify(dump || {}));
          const terms = Array.isArray(copy.terms) ? copy.terms : [];
          for (const t of terms) {
            if (t && t.meta && typeof t.meta === 'object' && !Array.isArray(t.meta)) {
              const keys = Object.keys(t.meta).sort();
              const next = {};
              for (const k of keys) next[k] = t.meta[k];
              t.meta = next;
            }
          }
          terms.sort((x, y) => String(x.slug || '').localeCompare(String(y.slug || '')) || String(x.name || '').localeCompare(String(y.name || '')));
          copy.terms = terms;
          return {
            attribute_name: copy.attribute_name,
            attribute_label: copy.attribute_label,
            attribute_type: copy.attribute_type,
            attribute_orderby: copy.attribute_orderby,
            attribute_public: Number(copy.attribute_public || 0),
            taxonomy: copy.taxonomy,
            terms: copy.terms,
          };
        };

        const sourceDump = normalize(sourceDumpRaw);
        const targetDump = normalize(targetDumpRaw);
        const contentEqual = JSON.stringify(sourceDump) === JSON.stringify(targetDump);

        const itemResult = {
          attribute_name: name,
          source_attribute_id: Number(a.attribute_id || 0),
          target_attribute_id: Number(targetAttr.attribute_id || 0),
          term_count: Number(a.term_count || 0),
          contentEqual,
          screenshots: {
            source: { attributes: sourceAttrShot, terms: tax ? path.join(dir, 'source-terms.png') : null },
            target: { attributes: targetAttrShot, terms: tax ? path.join(dir, 'target-terms.png') : null },
          },
          source: sourceDump,
          target: targetDump,
        };

        results.push(itemResult);
        fs.writeFileSync(path.join(dir, 'result.json'), JSON.stringify(itemResult, null, 2));
        console.log(`[compare] attribute "${name}" ${String(itemResult.source_attribute_id)} -> ${String(itemResult.target_attribute_id)} contentEqual=${String(contentEqual)}`);
      }

      fs.writeFileSync(path.join(artifactsDir, 'summary.json'), JSON.stringify({ contentType: env.contentType, results }, null, 2));
      console.log(`[done] Summary: ${path.join(artifactsDir, 'summary.json')}`);
      return;
    }

    if (env.contentType === 'database_table') {
      const tableName = importMeta.tableName || env.dbTable || '';
      if (!tableName) throw new Error('database_table comparison requires a selected tableName');

      const sourceSite = { wpPath: env.sourceWpPath, baseUrl: env.sourceUrl };
      const targetSite = { wpPath: env.targetWpPath, baseUrl: env.targetUrl };

      const sourceDumpRaw = dumpDbTableViaWpCli(sourceSite, tableName, { sampleLimit: 25 });
      const targetDumpRaw = dumpDbTableViaWpCli(targetSite, tableName, { sampleLimit: 25 });

      if (sourceDumpRaw && sourceDumpRaw.error) throw new Error(`Could not dump source table "${tableName}": ${sourceDumpRaw.error}`);
      if (targetDumpRaw && targetDumpRaw.error) throw new Error(`Could not dump target table "${tableName}": ${targetDumpRaw.error}`);

      const normalize = (dump) => ({
        table: dump.table,
        count: Number(dump.count || 0),
        columns: Array.isArray(dump.columns) ? dump.columns : [],
        primary: Array.isArray(dump.primary) ? dump.primary : [],
        order_by: Array.isArray(dump.order_by) ? dump.order_by : [],
        hash: String(dump.hash || ''),
        sample: Array.isArray(dump.sample) ? dump.sample : [],
      });

      const sourceDump = normalize(sourceDumpRaw);
      const targetDump = normalize(targetDumpRaw);

      const contentEqual = sourceDump.hash && sourceDump.hash === targetDump.hash && sourceDump.count === targetDump.count;
      const itemResult = {
        tableName,
        contentEqual,
        source: sourceDump,
        target: targetDump,
        exportPath,
      };

      results.push(itemResult);
      fs.writeFileSync(path.join(artifactsDir, 'summary.json'), JSON.stringify({ contentType: env.contentType, results }, null, 2));
      console.log(`[compare] database_table "${tableName}" contentEqual=${String(contentEqual)} rows=${String(sourceDump.count)}`);
      console.log(`[done] Summary: ${path.join(artifactsDir, 'summary.json')}`);
      return;
    }

    if (env.contentType === 'woo_coupon' || env.contentType === 'coupon') {
      const sourceSite = { wpPath: env.sourceWpPath, baseUrl: env.sourceUrl };
      const targetSite = { wpPath: env.targetWpPath, baseUrl: env.targetUrl };

      const candidates = listCouponsViaWpCli(sourceSite, 25);
      if (!candidates.length) throw new Error('No coupons found on source site');

      const score = (c) =>
        (c.product_ids_count ? 2 : 0) + (c.product_cat_count ? 2 : 0) + (c.emails_count ? 1 : 0);
      const picked = [...candidates]
        .sort((a, b) => score(b) - score(a))
        .slice(0, Math.min(3, candidates.length));

      for (const c of picked) {
        const sourceCouponId = Number(c.id || 0);
        const dir = path.join(artifactsDir, `${String(sourceCouponId).padStart(4, '0')}-coupon`);
        fs.mkdirSync(dir, { recursive: true });

        await gotoAdminPage(page, source, `/wp-admin/post.php?post=${sourceCouponId}&action=edit`);
        const sourceShots = await screenshotSegments(page, dir, 'source');
        const sourceDumpRaw = dumpCouponViaWpCli(sourceSite, sourceCouponId);
        if (sourceDumpRaw && sourceDumpRaw.error) {
          throw new Error(`Could not dump source coupon ${sourceCouponId}: ${sourceDumpRaw.error}`);
        }

        const targetCouponId = resolveCouponIdByCode(targetSite, sourceDumpRaw.code);
        if (!targetCouponId) {
          throw new Error(`Could not find imported coupon on target for code="${String(sourceDumpRaw.code || '')}"`);
        }

        await gotoAdminPage(page, target, `/wp-admin/post.php?post=${targetCouponId}&action=edit`);
        const targetShots = await screenshotSegments(page, dir, 'target');
        const targetDumpRaw = dumpCouponViaWpCli(targetSite, targetCouponId);
        if (targetDumpRaw && targetDumpRaw.error) {
          throw new Error(`Could not dump target coupon ${targetCouponId}: ${targetDumpRaw.error}`);
        }

        const normalize = (dump, baseUrl) => {
          const norm = (s) => String(s || '').split(baseUrl).join('__BASE__');
          const copy = JSON.parse(JSON.stringify(dump || {}));
          if (copy.description) copy.description = norm(copy.description);
          return copy;
        };

        const sourceDump = normalize(sourceDumpRaw, env.sourceUrl);
        const targetDump = normalize(targetDumpRaw, env.targetUrl);

        const contentEqual = JSON.stringify(sourceDump) === JSON.stringify(targetDump);
        const itemResult = {
          sourceCouponId,
          targetCouponId,
          code: sourceDump.code,
          contentEqual,
          screenshots: { source: sourceShots, target: targetShots },
          source: sourceDump,
          target: targetDump,
        };

        results.push(itemResult);
        fs.writeFileSync(path.join(dir, 'result.json'), JSON.stringify(itemResult, null, 2));
        console.log(`[compare] coupon ${sourceCouponId} -> ${targetCouponId} code="${String(sourceDump.code || '')}" contentEqual=${String(contentEqual)}`);
      }

      fs.writeFileSync(path.join(artifactsDir, 'summary.json'), JSON.stringify({ contentType: env.contentType, results }, null, 2));
      console.log(`[done] Summary: ${path.join(artifactsDir, 'summary.json')}`);
      return;
    }

    if (env.contentType === 'woo_order' || env.contentType === 'order') {
      const sourceSite = { wpPath: env.sourceWpPath, baseUrl: env.sourceUrl };
      const targetSite = { wpPath: env.targetWpPath, baseUrl: env.targetUrl };

      for (const sourceOrderId of env.sourceIds) {
        const dir = path.join(artifactsDir, `${String(sourceOrderId).padStart(4, '0')}-order`);
        fs.mkdirSync(dir, { recursive: true });

        await gotoOrderEdit(page, source, sourceOrderId);
        const sourceShots = await screenshotSegments(page, dir, 'source');
        const sourceDumpRaw = dumpOrderViaWpCli(sourceSite, sourceOrderId);
        if (sourceDumpRaw && sourceDumpRaw.error) {
          throw new Error(`Could not dump source order ${sourceOrderId}: ${sourceDumpRaw.error}`);
        }

        let targetOrderId = 0;
        if (sourceDumpRaw.order_key) {
          targetOrderId = resolveOrderIdByOrderKey(targetSite, sourceDumpRaw.order_key);
        }
        if (!targetOrderId && sourceDumpRaw.order_number) {
          targetOrderId = resolveOrderIdByOrderNumber(targetSite, sourceDumpRaw.order_number);
        }
        if (!targetOrderId) {
          throw new Error(
            `Could not find imported order on target for order_key=${String(sourceDumpRaw.order_key || '')} order_number=${String(
              sourceDumpRaw.order_number || ''
            )}`
          );
        }

        await gotoOrderEdit(page, target, targetOrderId);
        const targetShots = await screenshotSegments(page, dir, 'target');
        const targetDumpRaw = dumpOrderViaWpCli(targetSite, targetOrderId);
        if (targetDumpRaw && targetDumpRaw.error) {
          throw new Error(`Could not dump target order ${targetOrderId}: ${targetDumpRaw.error}`);
        }

        const normalize = (dump, baseUrl) => {
          const norm = (s) => String(s || '').split(baseUrl).join('__BASE__');
          const copy = JSON.parse(JSON.stringify(dump || {}));
          if (copy.customer_note) copy.customer_note = norm(copy.customer_note);
          if (Array.isArray(copy.notes)) {
            copy.notes = copy.notes.map((n) => ({ ...n, content: norm(n.content) }));
          }
          return copy;
        };

        const sourceDump = normalize(sourceDumpRaw, env.sourceUrl);
        const targetDump = normalize(targetDumpRaw, env.targetUrl);

        const contentEqual = JSON.stringify(sourceDump) === JSON.stringify(targetDump);
        const itemResult = {
          sourceOrderId,
          targetOrderId,
          order_number: sourceDump.order_number,
          order_key: sourceDump.order_key,
          contentEqual,
          screenshots: { source: sourceShots, target: targetShots },
          source: sourceDump,
          target: targetDump,
        };

        results.push(itemResult);
        fs.writeFileSync(path.join(dir, 'result.json'), JSON.stringify(itemResult, null, 2));
        console.log(
          `[compare] order ${sourceOrderId} -> ${targetOrderId} order_number="${String(sourceDump.order_number || '')}" contentEqual=${String(
            contentEqual
          )}`
        );
      }

      fs.writeFileSync(path.join(artifactsDir, 'summary.json'), JSON.stringify({ contentType: env.contentType, results }, null, 2));
      console.log(`[done] Summary: ${path.join(artifactsDir, 'summary.json')}`);
      return;
    }

    if (env.contentType === 'woo_product' || env.contentType === 'product') {
      const sourceSite = { wpPath: env.sourceWpPath, baseUrl: env.sourceUrl };
      const targetSite = { wpPath: env.targetWpPath, baseUrl: env.targetUrl };

      for (const sourceProductId of env.sourceIds) {
        const dir = path.join(artifactsDir, `${String(sourceProductId).padStart(4, '0')}-product`);
        fs.mkdirSync(dir, { recursive: true });

        await gotoAdminPage(page, source, `/wp-admin/post.php?post=${sourceProductId}&action=edit`);
        const sourceShots = await screenshotSegments(page, dir, 'source');
        const sourceDumpRaw = dumpProductViaWpCli(sourceSite, sourceProductId);
        if (sourceDumpRaw && sourceDumpRaw.error) {
          throw new Error(`Could not dump source product ${sourceProductId}: ${sourceDumpRaw.error}`);
        }

        const targetProductId = sourceDumpRaw.sku ? resolveProductIdBySku(targetSite, sourceDumpRaw.sku) : 0;
        if (!targetProductId) {
          // Fallback: find by title in UI.
          const comparePostType = 'product';
          await openEditByTitle(page, target, comparePostType, sourceDumpRaw.post_title);
        } else {
          await gotoAdminPage(page, target, `/wp-admin/post.php?post=${targetProductId}&action=edit`);
        }

        const targetShots = await screenshotSegments(page, dir, 'target');
        const finalTargetId = targetProductId || Number(new URL(page.url()).searchParams.get('post') || 0);
        const targetDumpRaw = dumpProductViaWpCli(targetSite, finalTargetId);

        const normalize = (dump, baseUrl) => {
          const norm = (s) => String(s || '').split(baseUrl).join('__BASE__');
          const copy = { ...dump };
          // Slugs can legitimately diverge across sites due to uniqueness constraints
          // (e.g. an existing product already uses the desired post_name). Treat it
          // as non-load-bearing for import correctness and visual parity.
          delete copy.post_name;
          if (typeof copy.post_content === 'string') copy.post_content = norm(copy.post_content);
          if (typeof copy.post_excerpt === 'string') copy.post_excerpt = norm(copy.post_excerpt);
          // Don't compare raw ACF arrays with IDs across sites (best-effort only).
          // Keep only if it's scalar/portable; otherwise null it out to avoid false negatives.
          if (copy.acf && typeof copy.acf === 'object') {
            // Leave as-is; in practice demo products usually don't have ACF.
          }
          return copy;
        };

        const sourceDump = normalize(sourceDumpRaw, env.sourceUrl);
        const targetDump = normalize(targetDumpRaw, env.targetUrl);

        const contentEqual = JSON.stringify(sourceDump) === JSON.stringify(targetDump);
        const itemResult = {
          sourceProductId,
          targetProductId: finalTargetId,
          sku: sourceDump.sku,
          contentEqual,
          screenshots: { source: sourceShots, target: targetShots },
          source: sourceDump,
          target: targetDump,
        };
        results.push(itemResult);
        fs.writeFileSync(path.join(dir, 'result.json'), JSON.stringify(itemResult, null, 2));
        console.log(`[compare] product ${sourceProductId} -> ${finalTargetId} sku="${String(sourceDump.sku || '')}" contentEqual=${String(contentEqual)}`);
      }

      fs.writeFileSync(path.join(artifactsDir, 'summary.json'), JSON.stringify({ contentType: env.contentType, results }, null, 2));
      console.log(`[done] Summary: ${path.join(artifactsDir, 'summary.json')}`);
      return;
    }

    for (const sourceId of env.sourceIds) {
      await gotoAdminPage(page, source, `/wp-admin/post.php?post=${sourceId}&action=edit`);
      const title = await getPostTitleFromEdit(page);
      const comparePostType = getComparePostType(env);

      let sourceContent = null;
      let sourcePermalink = null;
      if (env.contentType === 'media') {
        const details = await extractMediaDetails(page, env.sourceUrl);
        sourceContent = JSON.stringify({ ...details, fileUrl: undefined }, null, 0);
        sourcePermalink = details.fileUrl || (await getPermalinkFromEditorStore(page));
      } else {
        sourcePermalink = await getPermalinkFromEditorStore(page);
        sourceContent = await extractEditorContentBestEffort(page, env.sourceUrl);
      }

      const safe = slugify(title || String(sourceId));
      const dir = path.join(artifactsDir, `${String(sourceId).padStart(4, '0')}-${safe || 'item'}`);
      fs.mkdirSync(dir, { recursive: true });

      const sourceShots = await screenshotSegments(page, dir, 'source');
      const sourceFront = await screenshotFrontend(frontPage, sourcePermalink, path.join(dir, 'source-front.png'));

      const isMedia = comparePostType === 'media' || comparePostType === 'attachment';
      await gotoAdminPage(
        page,
        target,
        isMedia ? `/wp-admin/upload.php` : `/wp-admin/edit.php?post_type=${encodeURIComponent(comparePostType)}`
      );
      await openEditByTitle(page, target, comparePostType, title);

      let targetContent = null;
      let targetPermalink = null;
      if (env.contentType === 'media') {
        const details = await extractMediaDetails(page, env.targetUrl);
        targetContent = JSON.stringify({ ...details, fileUrl: undefined }, null, 0);
        targetPermalink = details.fileUrl || (await getPermalinkFromEditorStore(page));
      } else {
        targetContent = await extractEditorContentBestEffort(page, env.targetUrl);
        targetPermalink = await getPermalinkFromEditorStore(page);
      }

      const targetShots = await screenshotSegments(page, dir, 'target');
      const targetFront = await screenshotFrontend(frontPage, targetPermalink, path.join(dir, 'target-front.png'));

      // Gutenberg can re-serialize blocks differently across sites depending on which blocks/plugins are active,
      // causing false negatives when comparing `getEditedPostContent()`. Prefer DB-saved post_content for posts/pages.
      let contentEqual = sourceContent !== null && targetContent !== null ? sourceContent === targetContent : null;
      let compareMode = 'editor';
      if (env.contentType !== 'media') {
        const targetId = Number(new URL(page.url()).searchParams.get('post') || 0);
        if (targetId > 0) {
          const sourceDb = getPostContentViaWpCli({ wpPath: env.sourceWpPath, url: env.sourceUrl }, sourceId)
            .split(env.sourceUrl)
            .join('__BASE__');
          const targetDb = getPostContentViaWpCli({ wpPath: env.targetWpPath, url: env.targetUrl }, targetId)
            .split(env.targetUrl)
            .join('__BASE__');
          contentEqual = sourceDb === targetDb;
          compareMode = 'db';
        }
      }

      const itemResult = {
        sourceId,
        title,
        contentEqual,
        compareMode,
        permalinks: { source: sourcePermalink, target: targetPermalink },
        screenshots: { source: sourceShots, target: targetShots, frontend: { source: sourceFront, target: targetFront } },
      };
      results.push(itemResult);
      fs.writeFileSync(path.join(dir, 'result.json'), JSON.stringify(itemResult, null, 2));

      console.log(`[compare] ${sourceId} "${title}" contentEqual=${String(contentEqual)}`);
    }

    fs.writeFileSync(path.join(artifactsDir, 'summary.json'), JSON.stringify({ contentType: env.contentType, results }, null, 2));
    console.log(`[done] Summary: ${path.join(artifactsDir, 'summary.json')}`);
  } finally {
    await browser.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exitCode = 1;
});
