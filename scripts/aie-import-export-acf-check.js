/**
 * Manual E2E (Playwright): Export Blog Posts from aie.local and import into aie2.local,
 * then compare ACF field values between source post ID=1 and the imported post.
 *
 * Usage:
 *   export NVM_DIR="$HOME/.nvm"; . "$NVM_DIR/nvm.sh"; nvm use 20.12.2
 *   node scripts/aie-import-export-acf-check.js
 *
 * Env (defaults read from .env.e2e if present):
 *   AIE_SOURCE_URL, AIE_SOURCE_ADMIN_USER, AIE_SOURCE_ADMIN_PASSWORD
 *   AIE_TARGET_URL, AIE_TARGET_ADMIN_USER, AIE_TARGET_ADMIN_PASSWORD
 *   AIE_HEADLESS=true|false
 *   AIE_CONTENT_TYPE=post|page|product|...
 *   AIE_SOURCE_POST_ID=1
 *   AIE_IF_EXISTS=update|skip|create
 *   AIE_IF_NOT_EXISTS=create|skip
 *   AIE_AUTO_IMPORT_MEDIA=true|false
 *   AIE_MEDIA_DUPLICATE_MODE=skip|create|replace
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

  return {
    sourceUrl: get('AIE_SOURCE_URL', 'http://aie.local'),
    sourceUser: get('AIE_SOURCE_ADMIN_USER', 'admin'),
    sourcePass: get('AIE_SOURCE_ADMIN_PASSWORD', 'admin'),
    targetUrl: get('AIE_TARGET_URL', 'http://aie2.local'),
    targetUser: get('AIE_TARGET_ADMIN_USER', 'admin'),
    targetPass: get('AIE_TARGET_ADMIN_PASSWORD', 'admin'),
    headless,
    contentType: get('AIE_CONTENT_TYPE', 'post'),
    sourcePostId: Number(get('AIE_SOURCE_POST_ID', '1')),
    ifExists: String(get('AIE_IF_EXISTS', 'update')).trim() || 'update',
    ifNotExists: String(get('AIE_IF_NOT_EXISTS', 'create')).trim() || 'create',
    autoImportMedia: String(get('AIE_AUTO_IMPORT_MEDIA', 'true')).toLowerCase() !== 'false',
    mediaDuplicateMode: String(get('AIE_MEDIA_DUPLICATE_MODE', 'skip')).trim() || 'skip',
  };
}

function wpCli({ wpPath, args }) {
  return execFileSync('wp', [`--path=${wpPath}`, ...args], { encoding: 'utf8' }).trim();
}

async function ensureLoggedIn(page, { baseUrl, username, password }) {
  // Try admin page first; if redirected to login, perform login.
  await page.goto(`${baseUrl}/wp-admin/`, { waitUntil: 'domcontentloaded' });

  const isLogin = await page.locator('form#loginform').count();
  if (!isLogin) {
    // Already logged in.
    return;
  }

  await page.fill('#user_login', username);
  await page.fill('#user_pass', password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('#wp-submit'),
  ]);

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
  if (await overlay.count()) {
    await overlay.waitFor({ state: 'visible', timeout: 15_000 });

    const created = page.locator('#aie-backup-created');
    const dontShow = page.locator('#aie-backup-dont-show');
    if (await created.count()) await created.check({ force: true });
    if (await dontShow.count()) await dontShow.check({ force: true });

    await page.locator('.aie-backup-confirm').click();
    await overlay.waitFor({ state: 'detached', timeout: 15_000 });
  }
}

async function clickNextStep(page) {
  const next = page.locator('.aie-step.active .aie-next-step');
  await next.waitFor({ state: 'visible', timeout: 30_000 });
  await next.click();
}

async function selectContentTypeOnStep1(page, contentType) {
  if (!contentType) return;
  const input = page.locator(`.aie-step-1.active input[name="content_type"][value="${contentType}"]`).first();
  if (!(await input.count())) return;

  const label = page.locator('.aie-step-1.active label.aie-content-type', { has: input }).first();
  if (await label.count()) {
    await label.click({ force: true });
    return;
  }

  await page.evaluate((ct) => {
    const el = document.querySelector(`.aie-step-1.active input[name="content_type"][value="${ct}"]`);
    if (!el) return;
    el.checked = true;
    el.dispatchEvent(new Event('change', { bubbles: true }));
    el.dispatchEvent(new Event('click', { bubbles: true }));
  }, contentType);
}

async function exportAllItems(page, source, contentType) {
  await gotoAdminPage(page, source, '/wp-admin/admin.php?page=rsl-ie-export');

  // Step 1: content type
  await page.waitForSelector('.aie-step-1.active', { timeout: 30_000 });
  await selectContentTypeOnStep1(page, contentType);
  await clickNextStep(page); // to Step 2

  // Step 2: filters
  await page.waitForSelector('.aie-step-2.active', { timeout: 30_000 });
  await clickNextStep(page); // to Step 3

  // Step 3: select fields
  await page.waitForSelector('.aie-step-3.active', { timeout: 30_000 });

  // Wait for static categories to appear.
  await page.waitForTimeout(500);

  // Wait for ACF fields to load (best effort).
  const acfItems = page.locator('.aie-acf-fields-grid .aie-field-item');
  try {
    await acfItems.first().waitFor({ state: 'visible', timeout: 30_000 });
  } catch {
    // ACF might be missing or have no fields; continue anyway.
  }

  // Click "Add all" in every visible category.
  const addAllButtons = page.locator('.aie-step-3.active .aie-add-all-fields');
  const btnCount = await addAllButtons.count();
  for (let i = 0; i < btnCount; i++) {
    const btn = addAllButtons.nth(i);
    // Some categories are display:none (taxonomies/custom/yoast) when empty; skip hidden.
    if (!(await btn.isVisible())) continue;
    await btn.click();
    // Give JS a moment to add fields.
    await page.waitForTimeout(50);
  }

  // Ensure Next is enabled.
  const step3Next = page.locator('.aie-step-3.active .aie-next-step');
  await step3Next.waitFor({ state: 'visible', timeout: 30_000 });
  await step3Next.waitFor({ state: 'attached', timeout: 30_000 });
  await page.waitForFunction(() => {
    const btn = document.querySelector('.aie-step-3.active .aie-next-step');
    return btn && !btn.disabled;
  });
  await step3Next.click(); // to Step 4

  // Step 4: format - leave defaults, start export
  await page.waitForSelector('.aie-step-4.active', { timeout: 30_000 });
  await page.locator('.aie-start-export').click();

  // Step 5: progress -> wait for completion and download.
  await page.waitForSelector('.aie-step-5.active', { timeout: 30_000 });

  const completeCard = page.locator('.aie-export-complete-card');
  await completeCard.waitFor({ state: 'visible', timeout: 5 * 60_000 });

  const [download] = await Promise.all([
    page.waitForEvent('download', { timeout: 60_000 }),
    page.locator('.aie-download-file').click(),
  ]);

  return download;
}

async function importItems(page, target, csvPath, contentType) {
  await gotoAdminPage(page, target, '/wp-admin/admin.php?page=rsl-ie-import');

  // Step 1: content type
  await page.waitForSelector('.aie-step-1.active', { timeout: 30_000 });
  await selectContentTypeOnStep1(page, contentType);

  // Backup warning can appear when leaving step 1 (per JS).
  await page.locator('.aie-step-1.active .aie-next-step').click();
  await handleBackupModalIfPresent(page);

  // Step 2: upload file
  await page.waitForSelector('.aie-step-2.active', { timeout: 30_000 });
  await page.setInputFiles('#aie-file-input', csvPath);

  const next2 = page.locator('.aie-step-2.active .aie-next-step');
  await page.waitForFunction(() => {
    const btn = document.querySelector('.aie-step-2.active .aie-next-step');
    return btn && !btn.disabled;
  });
  await next2.click();

  // Step 3: preview
  await page.waitForSelector('.aie-step-3.active', { timeout: 30_000 });
  await clickNextStep(page);

  // Step 4: mapping - Auto map then next
  await page.waitForSelector('.aie-step-4.active', { timeout: 30_000 });
  await page.locator('.aie-auto-map').click();
  await page.waitForTimeout(250);
  await clickNextStep(page);

  // Step 5: import options
  await page.waitForSelector('.aie-step-5.active', { timeout: 30_000 });

  // Unique field: Title (value post_title)
  const uniqueSelect = page.locator('#aie-unique-field');
  await uniqueSelect.waitFor({ state: 'visible', timeout: 30_000 });
  // Prefer selecting by value; label may be translated.
  await uniqueSelect.selectOption({ value: 'post_title' });

  // If Match Found
  const ifExists = String(process.env.AIE_IF_EXISTS || 'update').trim() || 'update';
  const ifExistsRadio = page.locator(`.aie-step-5.active input[name="if_exists"][value="${ifExists}"]`);
  if (await ifExistsRadio.count()) await ifExistsRadio.check({ force: true });

  // If No Match Found
  const ifNotExists = String(process.env.AIE_IF_NOT_EXISTS || 'create').trim() || 'create';
  const ifNotExistsRadio = page.locator(`.aie-step-5.active input[name="if_not_exists"][value="${ifNotExists}"]`);
  if (await ifNotExistsRadio.count()) await ifNotExistsRadio.check({ force: true });

  // Auto import media + duplicate handling
  const autoImportMedia = String(process.env.AIE_AUTO_IMPORT_MEDIA || 'true').toLowerCase() !== 'false';
  const mediaCheckbox = page.locator('#aie-auto-import-media');
  if (await mediaCheckbox.count()) {
    if (autoImportMedia) {
      await mediaCheckbox.check({ force: true });
    } else {
      await mediaCheckbox.uncheck({ force: true }).catch(() => {});
    }
  }
  if (autoImportMedia) {
    const mediaDuplicateMode = String(process.env.AIE_MEDIA_DUPLICATE_MODE || 'skip').trim() || 'skip';
    const mediaModeRadio = page.locator(
      `.aie-step-5.active input[name="media_duplicate_mode"][value="${mediaDuplicateMode}"]`
    );
    if (await mediaModeRadio.count()) await mediaModeRadio.check({ force: true });
  }

  await page.locator('.aie-start-import').click();
  await handleBackupModalIfPresent(page); // Backup warning can also appear here depending on flow.

  // Step 6: progress
  await page.waitForSelector('.aie-step-6.active', { timeout: 30_000 });
  const importComplete = page.locator('.aie-import-complete-card');
  await importComplete.waitFor({ state: 'visible', timeout: 10 * 60_000 });
}

async function getPostTitleFromEdit(page) {
  // Classic editor
  const classicTitle = page.locator('#title');
  if (await classicTitle.count()) {
    const value = await classicTitle.inputValue();
    if (value) return value.trim();
  }
  // Block editor
  const blockTitle = page.locator('h1.editor-post-title__input');
  if (await blockTitle.count()) {
    const text = await blockTitle.innerText();
    if (text) return text.trim();
  }
  // WP admin tab title usually includes the post title even when the editor UI
  // is rendered inside an iframe (so the title element isn't in the main DOM).
  const tabTitle = await page.title();
  const m = tabTitle.match(/[“"](.*?)[”"]/);
  if (m && m[1]) return m[1].trim();
  return '';
}

async function openPostEditByTitle(page, site, title, postType) {
  const searchUrl = `${site.baseUrl}/wp-admin/edit.php?post_type=${encodeURIComponent(
    postType || 'post'
  )}&s=${encodeURIComponent(title)}`;
  await page.goto(searchUrl, { waitUntil: 'domcontentloaded' });

  // WP list table row-title links.
  const rowTitle = page.locator('a.row-title', { hasText: title }).first();
  if (await rowTitle.count()) {
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      rowTitle.click(),
    ]);
    return;
  }

  // Fallback: first result.
  const first = page.locator('a.row-title').first();
  if (!(await first.count())) {
    throw new Error(`Could not find post in target site by title: ${title}`);
  }
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    first.click(),
  ]);
}

async function extractAcfInputs(page) {
  // Wait for ACF container (best-effort; some sites might not have ACF fields on the post).
  // ACF fields are often rendered by JS after the page load.
  await page.waitForTimeout(1500);

  return await page.evaluate(() => {
    const root = document.querySelector('#poststuff') || document.body;
    const nodes = Array.from(
      root.querySelectorAll(
        'input[name^="acf["], textarea[name^="acf["], select[name^="acf["]'
      )
    );

    const out = {};

    const push = (name, value) => {
      const existing = out[name];
      if (existing === undefined) {
        out[name] = [value];
      } else if (Array.isArray(existing)) {
        existing.push(value);
      } else {
        out[name] = [existing, value];
      }
    };

    for (const el of nodes) {
      if (!el.name) continue;

      // Skip ACF template/clone fields and hidden templates (not user-visible values).
      if (el.closest('.acf-hidden') || el.closest('.acf-clone')) continue;

      // Skip ACF internal hidden values that are volatile.
      if (el.name === 'acf_nonce' || el.name === 'acf_post_id') continue;

      if (el.tagName === 'SELECT') {
        if (el.multiple) {
          out[el.name] = Array.from(el.selectedOptions).map((o) => o.value);
        } else {
          out[el.name] = el.value;
        }
        continue;
      }

      const type = (el.getAttribute('type') || '').toLowerCase();
      if (type === 'checkbox' || type === 'radio') {
        if (el.checked) push(el.name, el.value);
        continue;
      }

      // Don't let hidden "default" values clobber real checkbox arrays.
      if (type === 'hidden' && Array.isArray(out[el.name])) {
        continue;
      }

      out[el.name] = el.value;
    }

    // Normalize checkbox arrays: sort for stable comparison.
    for (const [k, v] of Object.entries(out)) {
      if (Array.isArray(v)) {
        out[k] = v.slice().sort();
      }
    }

    return out;
  });
}

function diffObjects(a, b) {
  const diffs = [];
  const keys = new Set([...Object.keys(a), ...Object.keys(b)]);
  for (const key of Array.from(keys).sort()) {
    const av = a[key];
    const bv = b[key];
    const as = JSON.stringify(av ?? null);
    const bs = JSON.stringify(bv ?? null);
    if (as !== bs) {
      diffs.push({ key, source: av ?? null, target: bv ?? null });
    }
  }
  return diffs;
}

async function main() {
  const env = loadEnv();

  const artifactsDir = path.resolve(process.cwd(), 'e2e', 'artifacts', 'aie-import-export-acf');
  fs.mkdirSync(artifactsDir, { recursive: true });

  const browser = await chromium.launch({ headless: env.headless });
  const context = await browser.newContext({
    acceptDownloads: true,
    viewport: { width: 1440, height: 900 },
  });

  const page = await context.newPage();

  const source = { baseUrl: env.sourceUrl, username: env.sourceUser, password: env.sourcePass };
  const target = { baseUrl: env.targetUrl, username: env.targetUser, password: env.targetPass };

  try {
    // 1) Export from source (all fields)
    const download = await exportAllItems(page, source, env.contentType);
    const suggested = download.suggestedFilename();
    const exportPath = path.join(artifactsDir, suggested || `export-${Date.now()}.csv`);
    await download.saveAs(exportPath);
    console.log(`[export] Saved: ${exportPath}`);

    // 2) Import into target
    await importItems(page, target, exportPath, env.contentType);
    console.log('[import] Completed');

    // Small behavior signal (WP-CLI): media ID should remain stable in skip mode.
    try {
      const imgId = wpCli({
        wpPath: '/Users/shaggywizard/Local Sites/aie2/app/public',
        args: ['post', 'meta', 'get', '1', 'repeater_0_image'],
      });
      console.log(`[wp] target repeater_0_image: ${imgId}`);
    } catch (e) {
      console.log(`[wp] failed: ${String(e && e.message ? e.message : e)}`);
    }

    // 3) Compare ACF values for source item vs imported item in target (by title)
    await gotoAdminPage(page, source, `/wp-admin/post.php?post=${env.sourcePostId}&action=edit`);
    const sourceTitle = await getPostTitleFromEdit(page);
    if (!sourceTitle) {
      throw new Error('Could not read source post title from edit screen.');
    }

    const sourceAcf = await extractAcfInputs(page);
    const sourceAcfShot = path.join(artifactsDir, 'source-acf.png');
    const sourceAcfEl = page.locator('.acf-postbox .acf-fields:visible').first();
    try {
      if (await sourceAcfEl.count()) {
        await sourceAcfEl.waitFor({ state: 'visible', timeout: 20_000 });
        await sourceAcfEl.screenshot({ path: sourceAcfShot });
      } else {
        await page.screenshot({ path: sourceAcfShot, fullPage: true });
      }
    } catch {
      await page.screenshot({ path: sourceAcfShot, fullPage: true });
    }
    console.log(`[compare] Source title: ${sourceTitle}`);
    console.log(`[compare] Source screenshot: ${sourceAcfShot}`);

    await gotoAdminPage(page, target, `/wp-admin/edit.php?post_type=${encodeURIComponent(env.contentType)}`);
    await openPostEditByTitle(page, target, sourceTitle, env.contentType);

    // Wait for ACF JS to render (it can lag in headless runs).
    const visibleAcf = page.locator('.acf-postbox .acf-fields:visible').first();
    try {
      await visibleAcf.waitFor({ state: 'visible', timeout: 30_000 });
    } catch {
      // Continue: extraction can still work even if we can't see the metabox yet.
    }

    const targetAcf = await extractAcfInputs(page);
    const targetAcfShot = path.join(artifactsDir, 'target-acf.png');
    const targetAcfEl = page.locator('.acf-postbox .acf-fields:visible').first();
    try {
      if (await targetAcfEl.count()) {
        await targetAcfEl.waitFor({ state: 'visible', timeout: 20_000 });
        await targetAcfEl.screenshot({ path: targetAcfShot });
      } else {
        await page.screenshot({ path: targetAcfShot, fullPage: true });
      }
    } catch {
      await page.screenshot({ path: targetAcfShot, fullPage: true });
    }
    console.log(`[compare] Target screenshot: ${targetAcfShot}`);

    const diffs = diffObjects(sourceAcf, targetAcf);
    const diffPath = path.join(artifactsDir, 'acf-diff.json');
    fs.writeFileSync(diffPath, JSON.stringify({ sourceTitle, diffs }, null, 2));
    console.log(`[compare] Diff saved: ${diffPath}`);

    if (diffs.length) {
      console.log(`[compare] MISMATCH (${diffs.length} differences). First 20:`);
      for (const d of diffs.slice(0, 20)) {
        console.log(`- ${d.key}: source=${JSON.stringify(d.source)} target=${JSON.stringify(d.target)}`);
      }
      process.exitCode = 2;
    } else {
      console.log('[compare] OK: ACF inputs identical.');
    }
  } finally {
    await browser.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exitCode = 1;
});
