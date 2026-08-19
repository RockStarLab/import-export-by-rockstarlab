/**
 * Manual visual compare (Playwright):
 * - Opens source post edit (post=1) on aie.local
 * - Opens the matching post by title on aie2.local
 * - Saves full-page screenshots + ACF metabox screenshots
 *
 * Usage:
 *   node scripts/aie-acf-visual-compare.js
 *
 * Env (defaults read from .env.e2e if present):
 *   AIE_SOURCE_URL, AIE_SOURCE_ADMIN_USER, AIE_SOURCE_ADMIN_PASSWORD
 *   AIE_TARGET_URL, AIE_TARGET_ADMIN_USER, AIE_TARGET_ADMIN_PASSWORD
 *   AIE_HEADLESS=true|false
 */

const fs = require('fs');
const path = require('path');
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
  };
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

async function openPostEditByTitle(page, site, title) {
  const searchUrl = `${site.baseUrl}/wp-admin/edit.php?post_type=post&s=${encodeURIComponent(title)}`;
  await page.goto(searchUrl, { waitUntil: 'domcontentloaded' });
  const rowTitle = page.locator('a.row-title', { hasText: title }).first();
  if (await rowTitle.count()) {
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), rowTitle.click()]);
    return;
  }
  const first = page.locator('a.row-title').first();
  if (!(await first.count())) throw new Error(`Could not find target post by title: ${title}`);
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), first.click()]);
}

async function screenshotPost(page, outDir, prefix) {
  await page.waitForTimeout(2000);

  // Gutenberg uses an internal scroll container, so fullPage screenshots often
  // only capture the viewport. Capture a few scrolled segments instead.
  const topPath = path.join(outDir, `${prefix}-top.png`);
  await page.screenshot({ path: topPath });

  const acfBox = page.locator('.acf-postbox').first();
  const acfPath = path.join(outDir, `${prefix}-acf-box.png`);
  if (await acfBox.count()) {
    try {
      await acfBox.scrollIntoViewIfNeeded();
      await acfBox.screenshot({ path: acfPath });
    } catch {
      // fallback: already saved full page
    }
  }

  // Repeater section (captures nested fields + media previews)
  const repeater = page.locator('.acf-field[data-type="repeater"]').first();
  const repeaterPath = path.join(outDir, `${prefix}-repeater.png`);
  if (await repeater.count()) {
    try {
      await repeater.scrollIntoViewIfNeeded();
      await page.waitForTimeout(500);
      await page.screenshot({ path: repeaterPath });
    } catch {
      // ignore
    }
  }

  // Post object / relationship / taxonomy / user section (best-effort)
  const relations = page.locator('.acf-field[data-type="post_object"], .acf-field[data-type="relationship"], .acf-field[data-type="taxonomy"], .acf-field[data-type="user"]').first();
  const relationsPath = path.join(outDir, `${prefix}-relations.png`);
  if (await relations.count()) {
    try {
      await relations.scrollIntoViewIfNeeded();
      await page.waitForTimeout(500);
      await page.screenshot({ path: relationsPath });
    } catch {
      // ignore
    }
  }

  return { topPath, acfPath, repeaterPath, relationsPath };
}

async function main() {
  const env = loadEnv();
  const artifactsDir = path.resolve(process.cwd(), 'e2e', 'artifacts', 'aie-acf-visual-compare');
  fs.mkdirSync(artifactsDir, { recursive: true });

  const browser = await chromium.launch({ headless: env.headless });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  const source = { baseUrl: env.sourceUrl, username: env.sourceUser, password: env.sourcePass };
  const target = { baseUrl: env.targetUrl, username: env.targetUser, password: env.targetPass };

  try {
    await gotoAdminPage(page, source, '/wp-admin/post.php?post=1&action=edit');
    const title = await getPostTitleFromEdit(page);
    if (!title) throw new Error('Could not read source post title.');

    const srcShots = await screenshotPost(page, artifactsDir, 'source');

    await gotoAdminPage(page, target, '/wp-admin/edit.php?post_type=post');
    await openPostEditByTitle(page, target, title);
    const tgtShots = await screenshotPost(page, artifactsDir, 'target');

    fs.writeFileSync(
      path.join(artifactsDir, 'summary.json'),
      JSON.stringify({ title, source: srcShots, target: tgtShots }, null, 2)
    );

    console.log(`[visual] Title: ${title}`);
    console.log(`[visual] Source top: ${srcShots.topPath}`);
    console.log(`[visual] Target top: ${tgtShots.topPath}`);
  } finally {
    await browser.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exitCode = 1;
});
