<?php
/**
 * 士観道 (Shikando) child theme functions.
 *
 * @package Shikando
 */

// Enqueue Google Fonts for Japanese typography.
function shikando_enqueue_fonts() {
	$google_fonts_url = 'https://fonts.googleapis.com/css2?'
		. 'family=Noto+Serif+JP:wght@400;500;700'
		. '&family=Noto+Sans+JP:wght@300;400;500;700'
		. '&family=Shippori+Mincho:wght@400;500;700'
		. '&display=swap';
	wp_enqueue_style( 'shikando-google-fonts', $google_fonts_url, array(), null );
}
add_action( 'wp_enqueue_scripts', 'shikando_enqueue_fonts' );
add_action( 'enqueue_block_editor_assets', 'shikando_enqueue_fonts' );

// Enqueue custom stylesheet.
function shikando_enqueue_styles() {
	wp_enqueue_style(
		'shikando-custom',
		get_stylesheet_directory_uri() . '/assets/css/shikando-custom.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'shikando_enqueue_styles' );

// Enqueue editor styles.
function shikando_editor_styles() {
	add_editor_style( 'assets/css/editor-style.css' );
	add_editor_style(
		'https://fonts.googleapis.com/css2?'
		. 'family=Noto+Serif+JP:wght@400;500;700'
		. '&family=Noto+Sans+JP:wght@300;400;500;700'
		. '&family=Shippori+Mincho:wght@400;500;700'
		. '&display=swap'
	);
}
add_action( 'after_setup_theme', 'shikando_editor_styles' );

// Register custom block pattern category.
function shikando_pattern_categories() {
	register_block_pattern_category( 'shikando', array(
		'label'       => '士観道',
		'description' => '士観道オリジナルのブロックパターン',
	) );
}
add_action( 'init', 'shikando_pattern_categories' );

// Register custom block styles.
function shikando_block_styles() {
	register_block_style( 'core/separator', array(
		'name'         => 'gold-line',
		'label'        => '金色ライン',
		'inline_style' => '
			.wp-block-separator.is-style-gold-line {
				border-color: var(--wp--preset--color--accent-1) !important;
				border-width: 0 0 2px 0;
			}
		',
	) );

	register_block_style( 'core/group', array(
		'name'         => 'washi-card',
		'label'        => '和紙カード',
		'inline_style' => '
			.wp-block-group.is-style-washi-card {
				background-color: rgba(245, 240, 232, 0.95);
				border: 1px solid var(--wp--preset--color--accent-1);
				border-radius: 2px;
				padding: var(--wp--preset--spacing--50);
				box-shadow: 0 2px 20px rgba(26, 26, 26, 0.06);
			}
		',
	) );

	register_block_style( 'core/button', array(
		'name'         => 'gold-outline',
		'label'        => '金枠ボタン',
		'inline_style' => '
			.wp-block-button.is-style-gold-outline .wp-block-button__link {
				background: transparent;
				color: var(--wp--preset--color--accent-1);
				border: 1px solid var(--wp--preset--color--accent-1);
			}
			.wp-block-button.is-style-gold-outline .wp-block-button__link:hover {
				background: var(--wp--preset--color--accent-1);
				color: var(--wp--preset--color--accent-2);
			}
		',
	) );
}
add_action( 'init', 'shikando_block_styles' );
