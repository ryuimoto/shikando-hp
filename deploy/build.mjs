#!/usr/bin/env node

/**
 * 士観道 HP - 静的サイト書き出しスクリプト
 *
 * Local by FlywheelのWordPressサイトを静的HTMLに変換し、
 * Vercelデプロイ用に出力します。
 *
 * 前提条件:
 *   - Local by Flywheelでサイトが起動していること
 *   - 士観道セットアップが実行済みであること
 *
 * 使い方:
 *   node build.mjs
 *
 * @package Shikando
 */

import { mkdir, writeFile, rm, readFile, copyFile } from 'fs/promises';
import { existsSync, readdirSync, statSync } from 'fs';
import { join, dirname, relative, extname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const PROJECT_ROOT = dirname(__dirname);
const WP_ROOT = join(PROJECT_ROOT, 'app', 'public');
const OUTPUT = join(__dirname, 'public');

// ===== 設定 =====
const SITE_DOMAIN = 'shikando-hp.local';
const SITE_URL = `http://${SITE_DOMAIN}`;
const FALLBACK_URL = 'http://localhost:10033';

// 書き出し対象ページ
const PAGES = [
  { path: '/', file: 'index.html' },
  { path: '/profile/', file: 'profile/index.html' },
  { path: '/services/', file: 'services/index.html' },
  { path: '/blog/', file: 'blog/index.html' },
  { path: '/contact/', file: 'contact/index.html' },
  { path: '/privacy-policy/', file: 'privacy-policy/index.html' },
  { path: '/tokushoho/', file: 'tokushoho/index.html' },
];

// Formspree お問い合わせフォーム HTML
const FORMSPREE_FORM = `
<form action="https://formspree.io/f/mdalkevr" method="POST" class="shikando-contact-form">
  <div class="form-field">
    <label for="name">お名前 <span class="required">*</span></label>
    <input type="text" id="name" name="name" required placeholder="例: 山田 太郎">
  </div>
  <div class="form-field">
    <label for="email">メールアドレス <span class="required">*</span></label>
    <input type="email" id="email" name="email" required placeholder="例: example@email.com">
  </div>
  <div class="form-field">
    <label for="phone">電話番号</label>
    <input type="tel" id="phone" name="phone" placeholder="例: 090-1234-5678">
  </div>
  <div class="form-field">
    <label for="birthdate">生年月日</label>
    <input type="date" id="birthdate" name="birthdate">
  </div>
  <div class="form-field">
    <label for="service">ご希望のサービス</label>
    <select id="service" name="service">
      <option value="">選択してください</option>
      <option value="電話占い - お試し鑑定">電話占い - お試し鑑定（15分）</option>
      <option value="電話占い - スタンダード鑑定">電話占い - スタンダード鑑定（30分）</option>
      <option value="電話占い - じっくり鑑定">電話占い - じっくり鑑定（60分）</option>
      <option value="電話占い - 年間運勢鑑定">電話占い - 年間運勢鑑定（90分）</option>
      <option value="チャット占い - ワンポイント鑑定">チャット占い - ワンポイント鑑定</option>
      <option value="チャット占い - 総合鑑定">チャット占い - 総合鑑定</option>
      <option value="チャット占い - 命式詳細鑑定">チャット占い - 命式詳細鑑定</option>
      <option value="チャット占い - 相性鑑定">チャット占い - 相性鑑定</option>
    </select>
  </div>
  <div class="form-field">
    <label for="message">ご相談内容 <span class="required">*</span></label>
    <textarea id="message" name="message" rows="6" required placeholder="ご相談内容をお書きください"></textarea>
  </div>
  <div class="form-submit">
    <button type="submit">送信する</button>
  </div>
</form>`;

// Formspree フォーム用CSS
const FORMSPREE_CSS = `
<style>
.shikando-contact-form {
  max-width: 600px;
}
.shikando-contact-form .form-field {
  margin-bottom: 1.5em;
}
.shikando-contact-form label {
  display: block;
  margin-bottom: 0.4em;
  font-weight: 500;
  font-size: 0.95em;
}
.shikando-contact-form .required {
  color: #C5A572;
}
.shikando-contact-form input[type="text"],
.shikando-contact-form input[type="email"],
.shikando-contact-form input[type="tel"],
.shikando-contact-form input[type="date"],
.shikando-contact-form select,
.shikando-contact-form textarea {
  width: 100%;
  padding: 0.7em 0.9em;
  border: 1px solid #ccc;
  border-radius: 2px;
  font-size: 1em;
  font-family: inherit;
  background: #fff;
  transition: border-color 0.3s;
  box-sizing: border-box;
}
.shikando-contact-form input:focus,
.shikando-contact-form select:focus,
.shikando-contact-form textarea:focus {
  border-color: #C5A572;
  outline: none;
}
.shikando-contact-form .form-submit {
  margin-top: 2em;
}
.shikando-contact-form button[type="submit"] {
  background: #C5A572;
  color: #0D1B2A;
  border: none;
  padding: 0.8em 2.5em;
  font-size: 1em;
  font-weight: 600;
  border-radius: 2px;
  cursor: pointer;
  transition: opacity 0.3s;
  letter-spacing: 0.1em;
}
.shikando-contact-form button[type="submit"]:hover {
  opacity: 0.85;
}
</style>`;

// ===== メイン処理 =====
async function main() {
  console.log('');
  console.log('  士観道 HP - 静的サイト書き出し');
  console.log('  ================================');
  console.log('');

  // 1. サイト接続確認
  const baseUrl = await detectBaseUrl();
  console.log(`  [OK] サイト接続: ${baseUrl}`);
  console.log('');

  // 2. 出力ディレクトリ初期化
  if (existsSync(OUTPUT)) {
    await rm(OUTPUT, { recursive: true });
  }
  await mkdir(OUTPUT, { recursive: true });

  // 3. 各ページを取得・処理
  console.log('  ページを取得中...');
  const allAssetUrls = new Set();

  for (const page of PAGES) {
    process.stdout.write(`    ${page.path} ... `);
    try {
      let html = await fetchText(baseUrl + page.path);

      // アセットURL収集
      collectAssetUrls(html, baseUrl, allAssetUrls);

      // URL書き換え
      html = rewriteUrls(html, baseUrl);

      // お問い合わせページのフォーム置き換え
      if (page.path === '/contact/') {
        html = replaceContactForm(html);
      }

      // コンテンツ置換（WP DB内の旧テキストを修正）
      html = replaceContent(html);

      // WordPress不要要素を除去
      html = cleanHtml(html);

      // 保存
      const outPath = join(OUTPUT, page.file);
      await mkdir(dirname(outPath), { recursive: true });
      await writeFile(outPath, html, 'utf-8');
      console.log('OK');
    } catch (err) {
      console.log(`SKIP (${err.message})`);
    }
  }

  // 4. アセットダウンロード
  console.log('');
  console.log(`  アセットをダウンロード中 (${allAssetUrls.size}件)...`);
  let downloaded = 0;
  let skipped = 0;

  for (const assetUrl of allAssetUrls) {
    try {
      await downloadAsset(assetUrl, baseUrl);
      downloaded++;
    } catch {
      skipped++;
    }
  }
  console.log(`    完了: ${downloaded}件 ダウンロード / ${skipped}件 スキップ`);

  // 5. CSS内のアセットも取得
  console.log('');
  console.log('  CSS内のアセットを処理中...');
  await processCssAssets(baseUrl);

  // 6. テーマの静的アセットをコピー
  console.log('  テーマアセットをコピー中...');
  await copyThemeStaticAssets();

  // 7. robots.txt 生成
  await writeFile(join(OUTPUT, 'robots.txt'), 'User-agent: *\nAllow: /\n', 'utf-8');

  // 8. vercel.json を public/ にコピー
  const vercelJsonSrc = join(__dirname, 'vercel.json');
  if (existsSync(vercelJsonSrc)) {
    await copyFile(vercelJsonSrc, join(OUTPUT, 'vercel.json'));
  }

  console.log('');
  console.log('  ================================');
  console.log('  [完了] 静的サイト書き出し成功!');
  console.log(`  出力先: ${OUTPUT}`);
  console.log('');
  console.log('  次のステップ:');
  console.log('    cd deploy/public && npx vercel');
  console.log('');
}

// ===== ユーティリティ =====

async function detectBaseUrl() {
  for (const url of [SITE_URL, FALLBACK_URL]) {
    try {
      const res = await fetch(url, { redirect: 'follow', signal: AbortSignal.timeout(5000) });
      if (res.ok) return url;
    } catch {}
  }
  throw new Error(
    'WordPress サイトに接続できません。\n' +
    'Local by Flywheel でサイトが起動していることを確認してください。'
  );
}

async function fetchText(url) {
  const res = await fetch(url, { redirect: 'follow', signal: AbortSignal.timeout(10000) });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.text();
}

async function fetchBinary(url) {
  const res = await fetch(url, { redirect: 'follow', signal: AbortSignal.timeout(10000) });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return Buffer.from(await res.arrayBuffer());
}

/**
 * HTML内のローカルアセットURLを収集
 */
function collectAssetUrls(html, baseUrl, urlSet) {
  // link[href], script[src], img[src], img[srcset]
  const patterns = [
    /(?:href|src)=["']([^"']+)["']/g,
    /srcset=["']([^"']+)["']/g,
  ];

  for (const pattern of patterns) {
    let match;
    while ((match = pattern.exec(html)) !== null) {
      const urls = match[1].split(',').map(s => s.trim().split(/\s+/)[0]);
      for (const url of urls) {
        if (isLocalAsset(url, baseUrl)) {
          urlSet.add(normalizeUrl(url, baseUrl));
        }
      }
    }
  }
}

/**
 * ローカルアセットかどうか判定
 */
function isLocalAsset(url, baseUrl) {
  if (!url) return false;
  // 外部CDN (Google Fonts, etc.) は除外
  if (url.startsWith('https://') || url.startsWith('//')) return false;
  // data: URIは除外
  if (url.startsWith('data:')) return false;
  // ローカルドメインの絶対URL
  if (url.startsWith(baseUrl)) return true;
  if (url.startsWith(`http://${SITE_DOMAIN}`)) return true;
  // 相対パス（/wp-content/, /wp-includes/）
  if (url.startsWith('/wp-content/') || url.startsWith('/wp-includes/')) return true;
  return false;
}

/**
 * URLを正規化（絶対パスに統一）
 */
function normalizeUrl(url, baseUrl) {
  if (url.startsWith('http')) {
    return url.replace(/^https?:\/\/[^/]+/, baseUrl);
  }
  return baseUrl + url;
}

/**
 * HTML内のURLを書き換え
 */
function rewriteUrls(html, baseUrl) {
  // 絶対URL → 相対パス（ドメインのみの場合は "/" に変換）
  html = html.replaceAll(baseUrl + '/', '/');
  html = html.replaceAll(baseUrl, '/');
  html = html.replaceAll(`http://${SITE_DOMAIN}/`, '/');
  html = html.replaceAll(`http://${SITE_DOMAIN}`, '/');
  html = html.replaceAll(`//${SITE_DOMAIN}/`, '/');
  html = html.replaceAll(`//${SITE_DOMAIN}`, '/');

  // クエリ文字列の除去 (キャッシュバスター)
  html = html.replace(/(\.css|\.js)\?ver=[^"'&\s]+/g, '$1');
  html = html.replace(/(\.css|\.js)\?[0-9.]+/g, '$1');

  return html;
}

/**
 * お問い合わせフォームを Formspree に置き換え
 */
function replaceContactForm(html) {
  // CF7ショートコードのプレースホルダーを検出して置き換え
  // パターン1: CF7のレンダリング済みフォーム
  html = html.replace(
    /<div[^>]*class="[^"]*wpcf7[^"]*"[^>]*>[\s\S]*?<\/div>\s*<\/div>/g,
    FORMSPREE_FORM
  );

  // パターン2: CF7未インストール時のプレースホルダーテキスト
  html = html.replace(
    /<p[^>]*>※\s*Contact Form 7[\s\S]*?<\/p>/g,
    FORMSPREE_FORM
  );

  // FormspreeのCSSを</head>の前に挿入
  html = html.replace('</head>', FORMSPREE_CSS + '\n</head>');

  return html;
}

/**
 * コンテンツ置換（WP DB内の旧テキストを更新）
 */
function replaceContent(html) {
  // 占術名の変更: 四柱推命 → 陰陽五行・八字 / タローデパリ
  const replacements = [
    ['四柱推命・八字による', '陰陽五行・八字 / タローデパリによる'],
    ['四柱推命・八字', '陰陽五行・八字 / タローデパリ'],
    ['四柱推命に基づいた', '陰陽五行・八字やタローデパリに基づいた'],
    ['四柱推命（しちゅうすいめい）は、生まれた年・月・日・時の四つの柱から、その人の持って生まれた運命を読み解く東洋占術の最高峰です。',
     '陰陽五行・八字は、生まれた年・月・日・時の四つの柱と五行の調和から、その人の持って生まれた運命を読み解く東洋占術です。さらにタローデパリのカードリーディングを通じて、今この瞬間のメッセージをお届けします。'],
    ['四柱推命は単なる占いではなく', '陰陽五行・八字は単なる占いではなく'],
    ['四柱推命の研究歴', '陰陽五行・八字の研究歴'],
    ['四柱推命の知識や', '占術の知識や'],
    ['四柱推命入門', '占術入門'],
    ['四柱推命を専門とし', '陰陽五行・八字とタローデパリを専門とし'],
  ];

  for (const [from, to] of replacements) {
    html = html.replaceAll(from, to);
  }

  return html;
}

/**
 * WordPress不要要素を除去
 */
function cleanHtml(html) {
  // 管理バー関連
  html = html.replace(/<link[^>]*id=['"]admin-bar[^>]*>/g, '');
  html = html.replace(/<style[^>]*id=['"]admin-bar[^>]*>[\s\S]*?<\/style>/g, '');

  // wp-emoji (boundary-safe: don't cross </script> or </style> boundaries)
  html = html.replace(/<script[^>]*>(?:(?!<\/script>)[\s\S])*?wp-emoji(?:(?!<\/script>)[\s\S])*?<\/script>/g, '');
  html = html.replace(/<style[^>]*>(?:(?!<\/style>)[\s\S])*?wp-emoji(?:(?!<\/style>)[\s\S])*?<\/style>/g, '');

  // REST API / oEmbed / wp-json リンク
  html = html.replace(/<link[^>]*rel=['"]https:\/\/api\.w\.org\/['"][^>]*>/g, '');
  html = html.replace(/<link[^>]*type=['"]application\/json\+oembed['"][^>]*>/g, '');
  html = html.replace(/<link[^>]*type=['"]text\/xml\+oembed['"][^>]*>/g, '');

  // RSD / wlwmanifest
  html = html.replace(/<link[^>]*rel=['"]EditURI['"][^>]*>/g, '');
  html = html.replace(/<link[^>]*rel=['"]wlwmanifest['"][^>]*>/g, '');

  // WordPress generator
  html = html.replace(/<meta[^>]*name=['"]generator['"][^>]*>/g, '');

  // wp-embed script
  html = html.replace(/<script[^>]*wp-embed[^>]*><\/script>/g, '');

  // shortlink
  html = html.replace(/<link[^>]*rel=['"]shortlink['"][^>]*>/g, '');

  // 空行を整理
  html = html.replace(/\n{3,}/g, '\n\n');

  return html;
}

/**
 * アセットをダウンロードして保存
 */
async function downloadAsset(url, baseUrl) {
  const urlPath = url.replace(baseUrl, '').split('?')[0];
  if (!urlPath || urlPath === '/') return;

  const outPath = join(OUTPUT, urlPath);

  // 既にダウンロード済みならスキップ
  if (existsSync(outPath)) return;

  const data = await fetchBinary(url);
  await mkdir(dirname(outPath), { recursive: true });
  await writeFile(outPath, data);
}

/**
 * ダウンロード済みCSS内の url() 参照を処理
 */
async function processCssAssets(baseUrl) {
  const cssFiles = findFiles(OUTPUT, '.css');

  for (const cssFile of cssFiles) {
    let css = await readFile(cssFile, 'utf-8');
    const urlPattern = /url\(["']?([^"')]+)["']?\)/g;
    let match;

    while ((match = urlPattern.exec(css)) !== null) {
      const ref = match[1];
      if (ref.startsWith('data:') || ref.startsWith('http') || ref.startsWith('//')) continue;

      // 相対パスを解決
      const cssDir = dirname(cssFile);
      const assetPath = join(cssDir, ref.split('?')[0]);

      if (!existsSync(assetPath)) {
        // CSSファイルのディレクトリからの相対パスをURLに変換
        const relFromOutput = relative(OUTPUT, cssDir);
        const assetUrlPath = '/' + join(relFromOutput, ref.split('?')[0]).replace(/\\/g, '/');
        const fullUrl = baseUrl + assetUrlPath;

        try {
          await downloadAsset(fullUrl, baseUrl);
        } catch {}
      }
    }

    // CSS内のローカルURL書き換え
    css = css.replaceAll(baseUrl, '');
    css = css.replaceAll(`http://${SITE_DOMAIN}`, '');
    await writeFile(cssFile, css, 'utf-8');
  }
}

/**
 * テーマの静的アセット（CSS/画像）を直接コピー
 */
async function copyThemeStaticAssets() {
  const themeDir = join(WP_ROOT, 'wp-content', 'themes', 'shikando', 'assets');
  const outThemeDir = join(OUTPUT, 'wp-content', 'themes', 'shikando', 'assets');

  if (existsSync(themeDir)) {
    await copyDir(themeDir, outThemeDir);
  }
}

/**
 * ディレクトリを再帰的にコピー
 */
async function copyDir(src, dest) {
  await mkdir(dest, { recursive: true });
  const entries = readdirSync(src, { withFileTypes: true });

  for (const entry of entries) {
    const srcPath = join(src, entry.name);
    const destPath = join(dest, entry.name);

    if (entry.isDirectory()) {
      await copyDir(srcPath, destPath);
    } else {
      // PHP以外のファイルのみコピー
      if (!entry.name.endsWith('.php')) {
        await mkdir(dirname(destPath), { recursive: true });
        await copyFile(srcPath, destPath);
      }
    }
  }
}

/**
 * ディレクトリ内の特定拡張子ファイルを再帰検索
 */
function findFiles(dir, ext) {
  const results = [];
  if (!existsSync(dir)) return results;

  const entries = readdirSync(dir, { withFileTypes: true });
  for (const entry of entries) {
    const fullPath = join(dir, entry.name);
    if (entry.isDirectory()) {
      results.push(...findFiles(fullPath, ext));
    } else if (entry.name.endsWith(ext)) {
      results.push(fullPath);
    }
  }
  return results;
}

// ===== 実行 =====
main().catch(err => {
  console.error('');
  console.error('  [エラー] ' + err.message);
  console.error('');
  process.exit(1);
});
