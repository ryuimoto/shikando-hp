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
      <option value="対面セッション">対面セッション</option>
      <option value="電話セッション - お試し鑑定">電話セッション - お試し鑑定（15分）</option>
      <option value="電話セッション - スタンダード鑑定">電話セッション - スタンダード鑑定（30分）</option>
      <option value="電話セッション - じっくり鑑定">電話セッション - じっくり鑑定（60分）</option>
      <option value="電話セッション - 年間運勢鑑定">電話セッション - 年間運勢鑑定（90分）</option>
      <option value="チャットセッション - ワンポイント鑑定">チャットセッション - ワンポイント鑑定</option>
      <option value="チャットセッション - 総合鑑定">チャットセッション - 総合鑑定</option>
      <option value="チャットセッション - 命式詳細鑑定">チャットセッション - 命式詳細鑑定</option>
      <option value="チャットセッション - 相性鑑定">チャットセッション - 相性鑑定</option>
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

  // 8. 予約ページを生成
  console.log('  予約ページを生成中...');
  await generateReservationPages();

  console.log('');
  console.log('  ================================');
  console.log('  [完了] 静的サイト書き出し成功!');
  console.log(`  出力先: ${OUTPUT}`);
  console.log('');
  console.log('  次のステップ:');
  console.log('    cd deploy && npx vercel');
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
     '陰陽五行・八字は、生まれた年・月・日・時の八つの元素と五行の調和から、その人の持って生まれた運命を読み解く東洋占術です。さらにタローデパリのカードリーディングを通じて、今この瞬間のメッセージをお届けします。'],
    ['四柱推命は単なる占いではなく', '陰陽五行・八字は単なる占術ではなく'],
    ['四柱推命の研究歴', '陰陽五行・八字の研究歴'],
    ['四柱推命の知識や', '占術の知識や'],
    ['四柱推命入門', '占術入門'],
    ['四柱推命を専門とし', '陰陽五行・八字とタローデパリを専門とし'],
    // 占い → セッション/対話 の置換（WP DB内の旧テキスト対応）
    ['占い師紹介', 'プロフィール'],
    ['占い師プロフィール', 'プロフィール'],
    ['占い師について', 'プロフィール'],
    ['占い師', '道士'],
    ['占いメニュー', 'セッションメニュー'],
    ['本格オンライン占い', '本格オンラインセッション'],
    ['電話占い', '電話セッション'],
    ['チャット占い', 'チャットセッション'],
    ['占いへの想い', 'セッションへの想い'],
    ['占いの想い', 'セッションの想い'],
    ['占いとは', 'セッションとは'],
    ['占い鑑定（電話占い・チャット占い）', 'セッション（対面セッション・電話セッション・チャットセッション）'],
    ['占い鑑定', 'セッション'],
    ['占いコラム', 'コラム'],
    ['単なる占術ではなく', '単なる占術ではなく'],
    ['鑑定方法: 電話占い / チャット占い', 'セッション方法: 対面セッション / 電話セッション / チャットセッション'],
    ['鑑定方法', 'セッション方法'],
    ['鑑定実績：○○件以上', ''],
    // プロフィール「私の歩み」セクション挿入
    ['<h3 class="wp-block-heading has-large-font-size" style="letter-spacing:0.1em">セッションへの想い</h3>',
     `<h3 class="wp-block-heading has-large-font-size" style="letter-spacing:0.1em">私の歩み</h3>

<p class="has-medium-font-size" style="line-height:2.2">50歳という節目、私は大切な幼馴染の頼みを受け、迷うことなく会社の代表を引き受けました。しかし、それは想像を絶する困難の始まりでした。</p>

<p class="has-medium-font-size" style="line-height:2.2">名前貸しの代表として過ごした2年後、私を待っていたのは毎月届く督促状と、逃れられない裁判の日々。暗闇の中を彷徨っていた私を救ってくれたのは、ある方との奇跡的な出会いでした。その助けがあり、今の私があります。</p>

<p class="has-text-align-center has-accent-1-color has-text-color" style="font-size:1.3em;line-height:2.2">「今度は私が、誰かの力になりたい」</p>

<p class="has-medium-font-size" style="line-height:2.2">この実体験から、対話を通じて困り事に寄り添うため、占術の門を叩き、カウンセラーとしての道を歩み始めました。そこで導いてくださった3人の最高の師との出会いが、今の私の土台となっています。</p>

<p class="has-medium-font-size" style="line-height:2.2">人生の折り返し地点を過ぎた今、私が精一杯できること。それが「士観道」です。<br>今、この言葉を目にしてくださっているあなたへ。<br>それは、ご自身を見つめ直すための大切なタイミングかもしれません。一歩踏み出す勇気に、私は全力で寄り添います。このご縁に、心から感謝いたします。</p>

<h3 class="wp-block-heading has-large-font-size" style="letter-spacing:0.1em">セッションへの想い</h3>`],
    // 経歴・資格の更新
    ['陰陽五行・八字の研究歴：○年', '陰陽五行・タローデパリの研究歴：7年'],
    ['タローデパリの研究歴：○年', ''],
    ['資格・認定：○○', '資格・認定：タローデパリ認定リーダー'],
    // 経歴・資格セクションの後に修了証画像を挿入
    ['<li>資格・認定：タローデパリ認定リーダー</li>\n</ul>',
     `<li>資格・認定：タローデパリ認定リーダー</li>\n</ul>\n\n<figure class="wp-block-image size-large shikando-certificate"><img src="/wp-content/themes/shikando/assets/images/taro-de-paris-certificate.jpg" alt="タローデパリ認定リーダー 修了証" style="max-width:500px;width:100%;height:auto;border-radius:2px;box-shadow:0 2px 12px rgba(26,26,26,0.10)"></figure>`],
    // CTA ボタンのリンクを予約ページに変更
    ['href="/contact/">ご予約・お問い合わせ</a>', 'href="/reservation/">ご予約はこちら</a>'],
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

/**
 * 予約ページ・成功ページ・キャンセルページを生成
 */
async function generateReservationPages() {
  // 既存ページをテンプレートとして利用（services ページからhead/header/footerを取得）
  let template;
  try {
    template = await readFile(join(OUTPUT, 'services/index.html'), 'utf-8');
  } catch {
    console.log('    SKIP (テンプレートページが見つかりません)');
    return;
  }

  // head部分を抽出（<head>〜</head>）
  const headMatch = template.match(/<head[\s\S]*?<\/head>/);
  // header部分を抽出（<header〜最初の</header>）
  const headerMatch = template.match(/<header[\s\S]*?<\/header>/);
  // footer部分を抽出（最後の<footer〜</footer>）
  const footerMatches = template.match(/<footer[\s\S]*<\/footer>/);

  if (!headMatch || !headerMatch || !footerMatches) {
    console.log('    SKIP (テンプレートの解析に失敗)');
    return;
  }

  let head = headMatch[0];
  const header = headerMatch[0];
  const footer = footerMatches[0];

  // 予約ページ用にheadを加工
  head = head.replace(/<title>.*?<\/title>/, '<title>ご予約 &#8211; 士観道（しかんどう）</title>');
  head = head.replace('</head>',
    '  <link rel="stylesheet" href="/assets/css/reservation.css">\n</head>');

  const buildPage = (title, bodyContent, extraScripts = '') => `<!DOCTYPE html>
<html lang="ja">
${head}
<body class="page-template-default page">
${header}
<main class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--70)">
<div class="wp-block-group" style="max-width:960px;margin:0 auto;padding-left:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30)">
<h1 class="wp-block-heading has-text-align-center has-x-large-font-size" style="letter-spacing:0.15em;margin-bottom:0.5em">${title}</h1>
${bodyContent}
</div>
</main>
${footer}
${extraScripts}
</body>
</html>`;

  // ===== 予約ページ =====
  const reservationHtml = buildPage('ご予約', RESERVATION_BODY, '<script src="/assets/js/reservation.js"></script>');
  const reservationDir = join(OUTPUT, 'reservation');
  await mkdir(reservationDir, { recursive: true });
  await writeFile(join(reservationDir, 'index.html'), reservationHtml, 'utf-8');
  console.log('    /reservation/ ... OK');

  // ===== 成功ページ =====
  const successHead = head.replace(
    /<title>.*?<\/title>/,
    '<title>ご予約完了 &#8211; 士観道（しかんどう）</title>'
  );
  const successHtml = `<!DOCTYPE html>
<html lang="ja">
${successHead}
<body class="page-template-default page">
${header}
<main class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--70)">
<div class="wp-block-group" style="max-width:720px;margin:0 auto;padding-left:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);text-align:center">
<h1 class="wp-block-heading has-text-align-center has-x-large-font-size" style="letter-spacing:0.15em;margin-bottom:0.5em">ご予約ありがとうございます</h1>
<p class="has-medium-font-size" style="line-height:2.2">ご予約が完了いたしました。<br>確認メールをお送りしておりますのでご確認ください。</p>
<p class="has-medium-font-size" style="line-height:2.2">セッション当日を楽しみにお待ちしております。</p>
<div style="margin-top:2rem">
<a href="/" class="wp-block-button__link wp-element-button" style="padding:0.8em 2.5em;font-size:1rem">トップページへ戻る</a>
</div>
</div>
</main>
${footer}
</body>
</html>`;
  const successDir = join(OUTPUT, 'reservation', 'success');
  await mkdir(successDir, { recursive: true });
  await writeFile(join(successDir, 'index.html'), successHtml, 'utf-8');
  console.log('    /reservation/success/ ... OK');

  // ===== キャンセルページ =====
  const cancelHead = head.replace(
    /<title>.*?<\/title>/,
    '<title>予約キャンセル &#8211; 士観道（しかんどう）</title>'
  );
  const cancelHtml = `<!DOCTYPE html>
<html lang="ja">
${cancelHead}
<body class="page-template-default page">
${header}
<main class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--70)">
<div class="wp-block-group" style="max-width:720px;margin:0 auto;padding-left:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--30);text-align:center">
<h1 class="wp-block-heading has-text-align-center has-x-large-font-size" style="letter-spacing:0.15em;margin-bottom:0.5em">決済がキャンセルされました</h1>
<p class="has-medium-font-size" style="line-height:2.2">決済が完了しませんでした。<br>もう一度お試しいただくか、お問い合わせください。</p>
<div style="margin-top:2rem;display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
<a href="/reservation/" class="wp-block-button__link wp-element-button" style="padding:0.8em 2.5em;font-size:1rem">予約ページに戻る</a>
<a href="/contact/" class="wp-block-button__link wp-element-button" style="padding:0.8em 2.5em;font-size:1rem;background:transparent;color:var(--wp--preset--color--contrast);border:1px solid currentColor">お問い合わせ</a>
</div>
</div>
</main>
${footer}
</body>
</html>`;
  const cancelDir = join(OUTPUT, 'reservation', 'cancel');
  await mkdir(cancelDir, { recursive: true });
  await writeFile(join(cancelDir, 'index.html'), cancelHtml, 'utf-8');
  console.log('    /reservation/cancel/ ... OK');
}

// 予約ページの本文HTML
const RESERVATION_BODY = `
<div class="reservation-container">
  <!-- ステップインジケーター -->
  <div class="reservation-steps">
    <div class="step active">
      <span class="step-number">1</span>
      <span class="step-label">コース</span>
    </div>
    <span class="step-arrow">▶</span>
    <div class="step">
      <span class="step-number">2</span>
      <span class="step-label">日付</span>
    </div>
    <span class="step-arrow">▶</span>
    <div class="step">
      <span class="step-number">3</span>
      <span class="step-label">時間</span>
    </div>
    <span class="step-arrow">▶</span>
    <div class="step">
      <span class="step-number">4</span>
      <span class="step-label">情報</span>
    </div>
    <span class="step-arrow">▶</span>
    <div class="step">
      <span class="step-number">5</span>
      <span class="step-label">確認</span>
    </div>
  </div>

  <!-- ステップ1: コース選択 -->
  <div class="step-content active" id="step-1">
    <h2 class="step-title">コースを選択してください</h2>

    <h3 class="course-group-title">電話セッション</h3>
    <div class="course-cards">
      <div class="course-card" data-course="phone-trial">
        <div>
          <div class="course-name">お試し鑑定</div>
          <div class="course-detail">30分 ・ 初回限定</div>
        </div>
        <div class="course-price free">無料</div>
      </div>
      <div class="course-card" data-course="phone-standard">
        <div>
          <div class="course-name">スタンダード鑑定</div>
          <div class="course-detail">30分</div>
        </div>
        <div class="course-price">¥3,000</div>
      </div>
      <div class="course-card" data-course="phone-deep-60">
        <div>
          <div class="course-name">じっくり鑑定</div>
          <div class="course-detail">60分</div>
        </div>
        <div class="course-price">¥6,000</div>
      </div>
      <div class="course-card" data-course="phone-deep-90">
        <div>
          <div class="course-name">じっくり鑑定</div>
          <div class="course-detail">90分</div>
        </div>
        <div class="course-price">¥9,000</div>
      </div>
    </div>

    <h3 class="course-group-title">チャットセッション</h3>
    <div class="course-cards">
      <div class="course-card" data-course="chat-onepoint-free">
        <div>
          <div class="course-name">ワンポイント鑑定</div>
          <div class="course-detail">質問1つ ・ 初回限定</div>
        </div>
        <div class="course-price free">無料</div>
      </div>
      <div class="course-card" data-course="chat-onepoint">
        <div>
          <div class="course-name">ワンポイント鑑定</div>
          <div class="course-detail">質問1つ</div>
        </div>
        <div class="course-price">¥1,000</div>
      </div>
      <div class="course-card" data-course="chat-threepoint">
        <div>
          <div class="course-name">スリーポイント鑑定</div>
          <div class="course-detail">質問3つまで</div>
        </div>
        <div class="course-price">¥3,000</div>
      </div>
    </div>

    <div class="step-nav">
      <span></span>
      <button class="btn-next" id="step1-next" data-action="next" disabled>次へ</button>
    </div>
  </div>

  <!-- ステップ2: 日付選択 -->
  <div class="step-content" id="step-2">
    <h2 class="step-title">日付を選択してください</h2>
    <div class="calendar-wrapper">
      <div class="calendar-header">
        <button class="calendar-prev" aria-label="前月">◀</button>
        <span class="month-title"></span>
        <button class="calendar-next" aria-label="次月">▶</button>
      </div>
      <div class="calendar-grid"></div>
    </div>
    <div class="step-nav">
      <button class="btn-back" data-action="back">戻る</button>
      <button class="btn-next" id="step2-next" data-action="next" disabled>次へ</button>
    </div>
  </div>

  <!-- ステップ3: 時間選択 -->
  <div class="step-content" id="step-3">
    <h2 class="step-title">時間を選択してください</h2>
    <div class="time-slots-container">
      <div class="time-slots-loading">日付を選択すると空き時間が表示されます</div>
    </div>
    <div class="step-nav">
      <button class="btn-back" data-action="back">戻る</button>
      <button class="btn-next" id="step3-next" data-action="next" disabled>次へ</button>
    </div>
  </div>

  <!-- ステップ4: お客様情報入力 -->
  <div class="step-content" id="step-4">
    <h2 class="step-title">お客様情報を入力してください</h2>
    <form id="reservation-form" class="reservation-form" onsubmit="return false">
      <div class="form-group">
        <label>お名前<span class="required">*</span></label>
        <input type="text" name="name" required placeholder="例: 山田 太郎" autocomplete="name">
      </div>
      <div class="form-group">
        <label>メールアドレス<span class="required">*</span></label>
        <input type="email" name="email" required placeholder="例: example@email.com" autocomplete="email">
      </div>
      <div class="form-group">
        <label>電話番号<span class="required">*</span></label>
        <input type="tel" name="phone" required placeholder="例: 090-1234-5678" autocomplete="tel">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>生年月日（西暦）<span class="required">*</span></label>
          <input type="date" name="birthdate" required>
        </div>
        <div class="form-group">
          <label>出生時刻<span class="required">*</span></label>
          <input type="time" name="birthtime" required>
          <span class="form-hint">不明な場合はおおよその時刻をご入力ください</span>
        </div>
      </div>
      <div class="form-group">
        <label>ご相談内容</label>
        <textarea name="message" rows="4" placeholder="ご相談されたい内容をお書きください"></textarea>
      </div>
    </form>
    <div class="step-nav">
      <button class="btn-back" data-action="back">戻る</button>
      <button class="btn-next" id="step4-next" data-action="next" disabled>確認画面へ</button>
    </div>
  </div>

  <!-- ステップ5: 確認・送信 -->
  <div class="step-content" id="step-5">
    <h2 class="step-title">ご予約内容の確認</h2>
    <div class="confirm-body"></div>
    <button class="btn-submit" id="btn-submit" data-action="submit">予約・決済に進む</button>
    <div class="step-nav" style="margin-top:1rem">
      <button class="btn-back" data-action="back">修正する</button>
    </div>
  </div>
</div>
`;

// ===== 実行 =====
main().catch(err => {
  console.error('');
  console.error('  [エラー] ' + err.message);
  console.error('');
  process.exit(1);
});
