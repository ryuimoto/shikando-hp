<?php
/**
 * Title: Footer
 * Slug: shikando/footer
 * Categories: footer
 * Block Types: core/template-part/footer
 * Description: 士観道サイトフッター
 *
 * @package Shikando
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|50"}}},"backgroundColor":"accent-2","textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-base-color has-accent-2-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:separator {"align":"wide","className":"is-style-gold-line","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"}}}} -->
	<hr class="wp-block-separator alignwide is-style-gold-line" style="margin-bottom:var(--wp--preset--spacing--60)"/>
	<!-- /wp:separator -->

	<!-- wp:columns {"align":"wide"} -->
	<div class="wp-block-columns alignwide">
		<!-- wp:column {"width":"40%"} -->
		<div class="wp-block-column" style="flex-basis:40%">
			<!-- wp:heading {"level":2,"style":{"typography":{"fontFamily":"var:preset|font-family|shippori-mincho","letterSpacing":"0.15em"}},"fontSize":"x-large","textColor":"accent-1"} -->
			<h2 class="wp-block-heading has-accent-1-color has-text-color has-x-large-font-size" style="font-family:var(--wp--preset--font-family--shippori-mincho);letter-spacing:0.15em">士観道</h2>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"style":{"typography":{"lineHeight":"2"}},"fontSize":"small"} -->
			<p class="has-small-font-size" style="line-height:2">陰陽五行・八字 / タローデパリによる<br>本格オンラインセッション<br>対面セッション・電話セッション・チャットセッション</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"30%"} -->
		<div class="wp-block-column" style="flex-basis:30%">
			<!-- wp:heading {"level":3,"style":{"typography":{"fontWeight":"500","letterSpacing":"0.08em"}},"fontSize":"small","textColor":"accent-1"} -->
			<h3 class="wp-block-heading has-accent-1-color has-text-color has-small-font-size" style="font-weight:500;letter-spacing:0.08em">メニュー</h3>
			<!-- /wp:heading -->
			<!-- wp:navigation {"textColor":"base","overlayMenu":"never","style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"fontSize":"small","layout":{"type":"flex","orientation":"vertical"}} -->
				<!-- wp:navigation-link {"label":"ホーム","url":"/"} /-->
				<!-- wp:navigation-link {"label":"プロフィール","url":"/profile/"} /-->
				<!-- wp:navigation-link {"label":"サービス・料金","url":"/services/"} /-->
				<!-- wp:navigation-link {"label":"ブログ・コラム","url":"/blog/"} /-->
				<!-- wp:navigation-link {"label":"ご予約・お問い合わせ","url":"/contact/"} /-->
			<!-- /wp:navigation -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"30%"} -->
		<div class="wp-block-column" style="flex-basis:30%">
			<!-- wp:heading {"level":3,"style":{"typography":{"fontWeight":"500","letterSpacing":"0.08em"}},"fontSize":"small","textColor":"accent-1"} -->
			<h3 class="wp-block-heading has-accent-1-color has-text-color has-small-font-size" style="font-weight:500;letter-spacing:0.08em">お問い合わせ</h3>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"fontSize":"small"} -->
			<p class="has-small-font-size">営業時間: 10:00 - 22:00<br>定休日: 不定休</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->

	<!-- wp:spacer {"height":"var:preset|spacing|60"} -->
	<div style="height:var(--wp--preset--spacing--60)" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
	<div class="wp-block-group alignwide">
		<!-- wp:paragraph {"fontSize":"small","textColor":"accent-4"} -->
		<p class="has-accent-4-color has-text-color has-small-font-size">&copy; 2026 士観道（しかんどう）All Rights Reserved.</p>
		<!-- /wp:paragraph -->
		<!-- wp:paragraph {"fontSize":"small","textColor":"accent-4"} -->
		<p class="has-accent-4-color has-text-color has-small-font-size"><a href="/privacy-policy/">プライバシーポリシー</a> | <a href="/tokushoho/">特定商取引法に基づく表記</a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
