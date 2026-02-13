<?php
/**
 * Title: ホームページヒーロー
 * Slug: shikando/hero-home
 * Categories: shikando, banner, featured
 * Description: トップページのメインヒーローセクション
 *
 * @package Shikando
 */
?>
<!-- wp:cover {"dimRatio":80,"overlayColor":"accent-2","isUserOverlayColor":true,"minHeight":90,"minHeightUnit":"vh","contentPosition":"center center","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-cover alignfull" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--50);min-height:90vh">
	<span aria-hidden="true" class="wp-block-cover__background has-accent-2-background-color has-background-dim-80 has-background-dim"></span>
	<div class="wp-block-cover__inner-container">
		<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained","contentSize":"680px"}} -->
		<div class="wp-block-group">
			<!-- wp:paragraph {"align":"center","style":{"typography":{"letterSpacing":"0.3em","fontWeight":"400"}},"fontSize":"small","textColor":"accent-1"} -->
			<p class="has-text-align-center has-accent-1-color has-text-color has-small-font-size" style="font-weight:400;letter-spacing:0.3em">陰陽五行・八字 / タローデパリ</p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontFamily":"var:preset|font-family|shippori-mincho","letterSpacing":"0.2em","lineHeight":"1.6","fontWeight":"700"}},"fontSize":"xx-large","textColor":"base"} -->
			<h1 class="wp-block-heading has-text-align-center has-base-color has-text-color has-xx-large-font-size" style="font-family:var(--wp--preset--font-family--shippori-mincho);font-weight:700;letter-spacing:0.2em;line-height:1.6">士観道</h1>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"align":"center","style":{"typography":{"letterSpacing":"0.15em"}},"fontSize":"small","textColor":"base"} -->
			<p class="has-text-align-center has-base-color has-text-color has-small-font-size" style="letter-spacing:0.15em">しかんどう</p>
			<!-- /wp:paragraph -->

			<!-- wp:separator {"className":"is-style-gold-line","style":{"spacing":{"margin":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}}} -->
			<hr class="wp-block-separator is-style-gold-line" style="margin-top:var(--wp--preset--spacing--40);margin-bottom:var(--wp--preset--spacing--40)"/>
			<!-- /wp:separator -->

			<!-- wp:paragraph {"align":"center","style":{"typography":{"lineHeight":"2.2"}},"fontSize":"medium","textColor":"base"} -->
			<p class="has-text-align-center has-base-color has-text-color has-medium-font-size" style="line-height:2.2">あなたの運命の道筋を<br>古来の知恵で紐解きます</p>
			<!-- /wp:paragraph -->

			<!-- wp:paragraph {"align":"center","style":{"typography":{"lineHeight":"2"}},"fontSize":"small","textColor":"accent-4"} -->
			<p class="has-text-align-center has-accent-4-color has-text-color has-small-font-size" style="line-height:2">対面セッション・電話セッション・チャットセッションで<br>いつでもどこからでもご相談いただけます</p>
			<!-- /wp:paragraph -->

			<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}}} -->
			<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--50)">
				<!-- wp:button -->
				<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/contact/">無料相談を予約する</a></div>
				<!-- /wp:button -->
				<!-- wp:button {"className":"is-style-gold-outline"} -->
				<div class="wp-block-button is-style-gold-outline"><a class="wp-block-button__link wp-element-button" href="/services/">サービスを見る</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->
	</div>
</div>
<!-- /wp:cover -->
