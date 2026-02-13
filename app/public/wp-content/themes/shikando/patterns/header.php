<?php
/**
 * Title: Header
 * Slug: shikando/header
 * Categories: header
 * Block Types: core/template-part/header
 * Description: 士観道サイトヘッダー
 *
 * @package Shikando
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"0","bottom":"0"}}},"backgroundColor":"accent-2","textColor":"base","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull has-base-color has-accent-2-background-color has-text-color has-background" style="padding-top:0;padding-bottom:0">
	<!-- wp:group {"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
		<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
			<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"left"},"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
			<div class="wp-block-group">
				<!-- wp:site-title {"level":0,"style":{"typography":{"fontFamily":"var:preset|font-family|shippori-mincho","fontWeight":"700","letterSpacing":"0.15em"}},"fontSize":"large"} /-->
			</div>
			<!-- /wp:group -->
			<!-- wp:navigation {"textColor":"base","overlayBackgroundColor":"accent-2","overlayTextColor":"base","style":{"typography":{"letterSpacing":"0.08em","fontWeight":"400"}},"fontSize":"small","layout":{"type":"flex","justifyContent":"right","flexWrap":"wrap"}} -->
				<!-- wp:navigation-link {"label":"ホーム","url":"/"} /-->
				<!-- wp:navigation-link {"label":"プロフィール","url":"/profile/"} /-->
				<!-- wp:navigation-link {"label":"サービス・料金","url":"/services/"} /-->
				<!-- wp:navigation-link {"label":"ブログ","url":"/blog/"} /-->
				<!-- wp:navigation-link {"label":"ご予約・お問い合わせ","url":"/contact/"} /-->
			<!-- /wp:navigation -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
	<!-- wp:separator {"align":"full","style":{"spacing":{"margin":{"top":"0","bottom":"0"}}},"className":"is-style-gold-line"} -->
	<hr class="wp-block-separator alignfull is-style-gold-line" style="margin-top:0;margin-bottom:0"/>
	<!-- /wp:separator -->
</div>
<!-- /wp:group -->
