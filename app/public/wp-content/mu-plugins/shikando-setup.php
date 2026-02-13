<?php
/**
 * 士観道 初期セットアップ（Must-Use Plugin）
 *
 * WordPress管理画面に「士観道セットアップ」メニューを追加し、
 * ワンクリックで初期設定・ページ作成・テーマ有効化を行います。
 *
 * セットアップ完了後、このファイルは削除してください。
 *
 * @package Shikando
 */

// 管理画面メニュー追加
add_action( 'admin_menu', function () {
	add_management_page(
		'士観道セットアップ',
		'士観道セットアップ',
		'manage_options',
		'shikando-setup',
		'shikando_setup_page'
	);
} );

// 管理画面に通知表示
add_action( 'admin_notices', function () {
	$current_theme = wp_get_theme();
	if ( 'shikando' !== $current_theme->get_stylesheet() ) {
		echo '<div class="notice notice-warning"><p>';
		echo '<strong>士観道テーマ:</strong> 初期セットアップがまだ完了していません。';
		echo ' <a href="' . admin_url( 'tools.php?page=shikando-setup' ) . '">セットアップを実行する</a>';
		echo '</p></div>';
	}
} );

function shikando_setup_page() {
	// セットアップ実行
	if ( isset( $_POST['shikando_run_setup'] ) && check_admin_referer( 'shikando_setup_nonce' ) ) {
		$results = shikando_run_setup();
		echo '<div class="wrap"><h1>士観道セットアップ完了</h1>';
		echo '<div class="notice notice-success"><p>セットアップが完了しました。</p></div>';
		echo '<ul style="list-style:disc;padding-left:20px;">';
		foreach ( $results as $result ) {
			echo '<li>' . esc_html( $result ) . '</li>';
		}
		echo '</ul>';
		echo '<p><strong>このファイル（mu-plugins/shikando-setup.php）は削除して構いません。</strong></p>';
		echo '<p><a href="' . home_url() . '" class="button button-primary">サイトを確認する</a> ';
		echo '<a href="' . admin_url() . '" class="button">管理画面へ</a></p>';
		echo '</div>';
		return;
	}

	// セットアップ画面
	echo '<div class="wrap"><h1>士観道 初期セットアップ</h1>';
	echo '<p>以下の設定を自動で行います:</p>';
	echo '<ul style="list-style:disc;padding-left:20px;">';
	echo '<li>テーマ「士観道」を有効化</li>';
	echo '<li>サイトタイトル・キャッチフレーズの設定</li>';
	echo '<li>タイムゾーン・日付形式の設定</li>';
	echo '<li>パーマリンク構造の変更</li>';
	echo '<li>固定ページの作成（トップ、プロフィール、サービス・料金、ブログ、お問い合わせ）</li>';
	echo '<li>フロントページ・投稿ページの設定</li>';
	echo '</ul>';
	echo '<form method="post">';
	wp_nonce_field( 'shikando_setup_nonce' );
	echo '<p><input type="submit" name="shikando_run_setup" value="セットアップを実行" class="button button-primary button-hero"></p>';
	echo '</form></div>';
}

function shikando_run_setup() {
	$results = array();

	// 1. テーマ有効化
	switch_theme( 'shikando' );
	$results[] = 'テーマ「士観道」を有効化しました';

	// 2. サイト設定
	update_option( 'blogname', '士観道（しかんどう）' );
	update_option( 'blogdescription', '陰陽五行・八字 / タローデパリによる本格オンラインセッション' );
	update_option( 'timezone_string', 'Asia/Tokyo' );
	update_option( 'date_format', 'Y年n月j日' );
	update_option( 'time_format', 'H:i' );
	update_option( 'WPLANG', 'ja' );
	$results[] = 'サイトタイトル・タイムゾーン等を設定しました';

	// 3. パーマリンク
	update_option( 'permalink_structure', '/%postname%/' );
	flush_rewrite_rules();
	$results[] = 'パーマリンク構造を /%postname%/ に設定しました';

	// 4. 固定ページ作成
	$pages = array(
		array(
			'title'   => 'ホーム',
			'slug'    => 'home',
			'content' => '',
		),
		array(
			'title'   => 'プロフィール',
			'slug'    => 'profile',
			'content' => shikando_profile_content(),
		),
		array(
			'title'   => 'サービス・料金',
			'slug'    => 'services',
			'content' => shikando_services_content(),
		),
		array(
			'title'   => 'ブログ',
			'slug'    => 'blog',
			'content' => '',
		),
		array(
			'title'   => 'ご予約・お問い合わせ',
			'slug'    => 'contact',
			'content' => shikando_contact_content(),
		),
		array(
			'title'   => 'プライバシーポリシー',
			'slug'    => 'privacy-policy',
			'content' => shikando_privacy_content(),
		),
		array(
			'title'   => '特定商取引法に基づく表記',
			'slug'    => 'tokushoho',
			'content' => shikando_tokushoho_content(),
		),
	);

	$home_page_id = 0;
	$blog_page_id = 0;

	foreach ( $pages as $page_data ) {
		$existing = get_page_by_path( $page_data['slug'] );
		if ( $existing ) {
			$results[] = 'ページ「' . $page_data['title'] . '」は既に存在します（スキップ）';
			if ( 'home' === $page_data['slug'] ) {
				$home_page_id = $existing->ID;
			}
			if ( 'blog' === $page_data['slug'] ) {
				$blog_page_id = $existing->ID;
			}
			continue;
		}

		$page_id = wp_insert_post( array(
			'post_title'   => $page_data['title'],
			'post_name'    => $page_data['slug'],
			'post_content' => $page_data['content'],
			'post_status'  => 'publish',
			'post_type'    => 'page',
		) );

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			$results[] = 'ページ「' . $page_data['title'] . '」を作成しました';
			if ( 'home' === $page_data['slug'] ) {
				$home_page_id = $page_id;
			}
			if ( 'blog' === $page_data['slug'] ) {
				$blog_page_id = $page_id;
			}
		}
	}

	// 5. フロントページ設定
	if ( $home_page_id ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home_page_id );
		$results[] = 'フロントページを「ホーム」に設定しました';
	}
	if ( $blog_page_id ) {
		update_option( 'page_for_posts', $blog_page_id );
		$results[] = '投稿ページを「ブログ」に設定しました';
	}

	// 6. デフォルトの「Hello world!」投稿と「Sample Page」を削除
	$hello_world = get_page_by_title( 'Hello world!', OBJECT, 'post' );
	if ( $hello_world ) {
		wp_delete_post( $hello_world->ID, true );
		$results[] = 'デフォルト投稿「Hello world!」を削除しました';
	}
	$sample_page = get_page_by_title( 'Sample Page', OBJECT, 'page' );
	if ( $sample_page ) {
		wp_delete_post( $sample_page->ID, true );
		$results[] = 'デフォルトページ「Sample Page」を削除しました';
	}

	// 7. カテゴリ作成
	$categories = array(
		'占術入門' => 'senjutsu-intro',
		'運勢・運気'   => 'unsei',
		'コラム'       => 'column',
		'お知らせ'     => 'news',
	);

	foreach ( $categories as $name => $slug ) {
		if ( ! term_exists( $slug, 'category' ) ) {
			wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
			$results[] = 'カテゴリ「' . $name . '」を作成しました';
		}
	}

	// デフォルトカテゴリ「未分類」を「お知らせ」に変更
	$news_term = get_term_by( 'slug', 'news', 'category' );
	if ( $news_term ) {
		update_option( 'default_category', $news_term->term_id );
	}

	return $results;
}

// --- ページコンテンツ生成関数 ---

function shikando_profile_content() {
	return '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)">

<!-- wp:heading {"level":2,"style":{"typography":{"letterSpacing":"0.1em"}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size" style="letter-spacing:0.1em">プロフィール</h2>
<!-- /wp:heading -->

<!-- wp:separator {"className":"is-style-gold-line","style":{"spacing":{"margin":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|50"}}}} -->
<hr class="wp-block-separator is-style-gold-line" style="margin-top:var(--wp--preset--spacing--30);margin-bottom:var(--wp--preset--spacing--50)"/>
<!-- /wp:separator -->

<!-- wp:paragraph {"style":{"typography":{"lineHeight":"2.2"}},"fontSize":"medium"} -->
<p class="has-medium-font-size" style="line-height:2.2">士観道（しかんどう）のプロフィールをここに記載します。陰陽五行・八字とタローデパリを専門とし、長年の研鑽を重ねてまいりました。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"style":{"typography":{"letterSpacing":"0.1em"}},"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size" style="letter-spacing:0.1em">経歴・資格</h3>
<!-- /wp:heading -->

<!-- wp:list {"style":{"typography":{"lineHeight":"2.2"}},"fontSize":"medium"} -->
<ul class="has-medium-font-size" style="line-height:2.2">
<li>陰陽五行・タローデパリの研究歴：7年</li>
<li>資格・認定：タローデパリ認定講師</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":3,"style":{"typography":{"letterSpacing":"0.1em"}},"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size" style="letter-spacing:0.1em">私の歩み</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"lineHeight":"2.2"}},"fontSize":"medium"} -->
<p class="has-medium-font-size" style="line-height:2.2">50歳という節目、私は大切な幼馴染の頼みを受け、迷うことなく会社の代表を引き受けました。しかし、それは想像を絶する困難の始まりでした。</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"lineHeight":"2.2"}},"fontSize":"medium"} -->
<p class="has-medium-font-size" style="line-height:2.2">名前貸しの代表として過ごした2年後、私を待っていたのは毎月届く督促状と、逃れられない裁判の日々。暗闇の中を彷徨っていた私を救ってくれたのは、ある方との奇跡的な出会いでした。その助けがあり、今の私があります。</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"center","style":{"typography":{"lineHeight":"2.2","fontSize":"1.3em"}},"textColor":"accent-1"} -->
<p class="has-text-align-center has-accent-1-color has-text-color" style="font-size:1.3em;line-height:2.2">「今度は私が、誰かの力になりたい」</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"lineHeight":"2.2"}},"fontSize":"medium"} -->
<p class="has-medium-font-size" style="line-height:2.2">この実体験から、対話を通じて困り事に寄り添うため、占術の門を叩き、カウンセラーとしての道を歩み始めました。そこで導いてくださった3人の最高の師との出会いが、今の私の土台となっています。</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"lineHeight":"2.2"}},"fontSize":"medium"} -->
<p class="has-medium-font-size" style="line-height:2.2">人生の折り返し地点を過ぎた今、私が精一杯できること。それが「士観道」です。<br>今、この言葉を目にしてくださっているあなたへ。<br>それは、ご自身を見つめ直すための大切なタイミングかもしれません。一歩踏み出す勇気に、私は全力で寄り添います。このご縁に、心から感謝いたします。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"style":{"typography":{"letterSpacing":"0.1em"}},"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size" style="letter-spacing:0.1em">セッションへの想い</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"lineHeight":"2.2"}},"fontSize":"medium"} -->
<p class="has-medium-font-size" style="line-height:2.2">陰陽五行・八字は単なる占術ではなく、自分自身を深く理解するための道具です。生まれ持った命式を知ることで、自分の強み・弱みを理解し、より良い人生の選択ができるようになります。タローデパリのカードリーディングでは、今この瞬間に必要なメッセージを受け取ることができます。</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"lineHeight":"2.2"}},"fontSize":"medium"} -->
<p class="has-medium-font-size" style="line-height:2.2">士観道では、一人ひとりに寄り添い、わかりやすく丁寧な鑑定を心がけています。お悩みの大小に関わらず、どうぞお気軽にご相談ください。</p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->

<!-- wp:pattern {"slug":"shikando/cta-reservation"} /-->';
}

function shikando_services_content() {
	return '<!-- wp:pattern {"slug":"shikando/pricing-table"} /-->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)">

<!-- wp:heading {"level":2,"style":{"typography":{"letterSpacing":"0.1em"}},"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size" style="letter-spacing:0.1em">鑑定の流れ</h2>
<!-- /wp:heading -->

<!-- wp:separator {"className":"is-style-gold-line","style":{"spacing":{"margin":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|50"}}}} -->
<hr class="wp-block-separator is-style-gold-line" style="margin-top:var(--wp--preset--spacing--30);margin-bottom:var(--wp--preset--spacing--50)"/>
<!-- /wp:separator -->

<!-- wp:list {"ordered":true,"style":{"typography":{"lineHeight":"2.5"}},"fontSize":"medium"} -->
<ol class="has-medium-font-size" style="line-height:2.5">
<li><strong>お問い合わせ・ご予約</strong> — お問い合わせフォームよりご希望のコースと日時をお知らせください。</li>
<li><strong>生年月日のご提供</strong> — 鑑定に必要な情報（生年月日・出生時刻・ご相談内容）をお送りください。</li>
<li><strong>鑑定実施</strong> — ご予約日時に電話またはチャットにて鑑定いたします。</li>
<li><strong>アフターフォロー</strong> — 鑑定後のご質問にもお答えいたします。</li>
</ol>
<!-- /wp:list -->

</div>
<!-- /wp:group -->

<!-- wp:pattern {"slug":"shikando/cta-reservation"} /-->';
}

function shikando_contact_content() {
	return '<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)">

<!-- wp:paragraph {"style":{"typography":{"lineHeight":"2"}},"fontSize":"medium"} -->
<p class="has-medium-font-size" style="line-height:2">ご予約・お問い合わせは下記フォームよりお気軽にどうぞ。<br>通常24時間以内にご返信いたします。</p>
<!-- /wp:paragraph -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"left":{"color":"var:preset|color|accent-1","width":"3px"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="border-left-color:var(--wp--preset--color--accent-1);border-left-width:3px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">

<!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size">営業情報</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size">営業時間: 10:00 - 22:00<br>定休日: 不定休<br>セッション方法: 対面セッション / 電話セッション / チャットセッション</p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->

<!-- wp:heading {"level":3,"style":{"typography":{"letterSpacing":"0.1em"},"spacing":{"margin":{"top":"var:preset|spacing|50"}}},"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size" style="letter-spacing:0.1em;margin-top:var(--wp--preset--spacing--50)">お問い合わせフォーム</h3>
<!-- /wp:heading -->

<!-- wp:separator {"className":"is-style-gold-line","style":{"spacing":{"margin":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|40"}}}} -->
<hr class="wp-block-separator is-style-gold-line" style="margin-top:var(--wp--preset--spacing--30);margin-bottom:var(--wp--preset--spacing--40)"/>
<!-- /wp:separator -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size">※ Contact Form 7 プラグインをインストール後、ここにフォームのショートコードを挿入してください。<br>例: [contact-form-7 id="xxx" title="お問い合わせ"]</p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->';
}

function shikando_privacy_content() {
	return '<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)">

<!-- wp:paragraph {"style":{"typography":{"lineHeight":"2.2"}},"fontSize":"medium"} -->
<p class="has-medium-font-size" style="line-height:2.2">士観道（以下「当サイト」）は、お客様の個人情報の保護を重要と考え、以下のプライバシーポリシーに従い、個人情報の適切な取扱いと保護に努めます。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size">個人情報の収集</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"lineHeight":"2.2"}},"fontSize":"medium"} -->
<p class="has-medium-font-size" style="line-height:2.2">当サイトでは、お問い合わせやご予約の際に、お名前、メールアドレス、生年月日等の個人情報をお伺いすることがあります。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size">個人情報の利用目的</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"lineHeight":"2.2"}},"fontSize":"medium"} -->
<p class="has-medium-font-size" style="line-height:2.2">収集した個人情報は、鑑定サービスの提供、お問い合わせへの回答、サービス改善の目的にのみ利用いたします。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size">個人情報の第三者提供</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"lineHeight":"2.2"}},"fontSize":"medium"} -->
<p class="has-medium-font-size" style="line-height:2.2">法令に基づく場合を除き、お客様の同意なく個人情報を第三者に提供することはありません。</p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->';
}

function shikando_tokushoho_content() {
	return '<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)">

<!-- wp:table {"hasFixedLayout":true,"fontSize":"medium"} -->
<figure class="wp-block-table has-custom-font-size has-medium-font-size">
<table class="has-fixed-layout">
<tbody>
<tr><td><strong>事業者名</strong></td><td>士観道</td></tr>
<tr><td><strong>代表者</strong></td><td>（お名前を記載）</td></tr>
<tr><td><strong>所在地</strong></td><td>（住所を記載）</td></tr>
<tr><td><strong>電話番号</strong></td><td>（電話番号を記載）</td></tr>
<tr><td><strong>メールアドレス</strong></td><td>（メールアドレスを記載）</td></tr>
<tr><td><strong>サービス内容</strong></td><td>陰陽五行・八字 / タローデパリによるセッション（対面セッション・電話セッション・チャットセッション）</td></tr>
<tr><td><strong>料金</strong></td><td>サービス・料金ページをご確認ください</td></tr>
<tr><td><strong>お支払い方法</strong></td><td>（お支払い方法を記載）</td></tr>
<tr><td><strong>キャンセルポリシー</strong></td><td>鑑定前日までのキャンセルは無料。当日キャンセルは料金の50%をいただきます。</td></tr>
</tbody>
</table>
</figure>
<!-- /wp:table -->

</div>
<!-- /wp:group -->';
}
