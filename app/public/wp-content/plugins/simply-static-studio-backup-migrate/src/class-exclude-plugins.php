<?php

namespace Simply_Static\Backup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers filters to exclude specific plugins from the export.
 */
class Exclude_Plugins {

	public function __construct() {
		add_filter( 'sssbm_exclude_plugins_from_export', [ $this, 'exclude_core_plugins' ] );
	}

	/**
	 * Ensures core and known-incompatible plugins are excluded from export.
	 *
	 * @param array $exclude_filters Existing exclusion filters.
	 * @return array Modified exclusion filters.
	 */
	public function exclude_core_plugins( $exclude_filters ) {
		if ( ! is_array( $exclude_filters ) ) {
			$exclude_filters = array();
		}

		// Slugs to exclude (we will also exclude their absolute directories under WP_PLUGIN_DIR)
		$slugs = array(
			'simply-static',
			'simply-static-pro',
			// Caching/Optimization
			'flying-press',
			'autoptimize',
			'flush-opcache',
			'wp-fastest-cache',
			'wp-rocket',
			'w3-total-cache',
			'coming-soon',
			'wp-super-cache',
			'sg-security',
			'wp-original-media-path',
			'wordfence',
			'really-simple-ssl',
			'all-in-one-wp-security-and-firewall',
			'sucuri-scanner',
			'better-wp-security',
			'defender-security',
			'wp-defender',
			'malcare-security',
			'hide-my-wp',
			'wps-hide-login',
			'wp-2fa',
			'wpide',
			'litespeed-cache',
			'wp-cloudflare-page-cache',
			'wp-optimize',
			'admin-site-enhancements',
			'admin-site-enhancements-pro',

			// Jetpack suite & spam
			'akismet',
			'jetpack',
			'jetpack-boost',
			'jetpack-protect',
			'jetpack-search',

			// Hosting helper (Pressable)
			'pressable-onepress-login',

			// Authentication (UpdraftPlus Two Factor Authentication)
			'two-factor-authentication',

			// WebP / image optimization plugins (handled by the platform)
			'ewww-image-optimizer',
			'shortpixel-image-optimiser',
			'webp-converter-for-media',
			'webp-express',
			'imagify',
			'wp-smushit',
			'optimole-wp',
			'robin-image-optimizer',
			'shortpixel-adaptive-images',
			'optimus',
			'image-optimization',
			'plus-webp',
			'tiny-compress-images',
		);

		$plugins_to_exclude = $slugs;

		// Also add absolute plugin directory paths for robust matching by the iterator
		foreach ( $slugs as $slug ) {
			$plugins_to_exclude[] = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $slug;
		}

		foreach ( $plugins_to_exclude as $path ) {
			if ( ! in_array( $path, $exclude_filters, true ) ) {
				$exclude_filters[] = $path;
			}
		}

		return $exclude_filters;
	}
}
