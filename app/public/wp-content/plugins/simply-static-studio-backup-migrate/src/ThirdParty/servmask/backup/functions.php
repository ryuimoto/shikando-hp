<?php

namespace Simply_Static\Backup\ThirdParty\servmask\backup;

/**
 * Get WordPress plugins directory
 *
 * @return string
 */
function ai1wm_get_plugins_dir() {
    return WP_PLUGIN_DIR;
}

/**
 * Get WordPress themes directories
 *
 * @return array
 */
function ai1wm_get_themes_dirs() {
    return \Simply_Static\Backup\Helper::get_themes_dirs();
}

/**
 * Get WordPress uploads directory
 *
 * @return string
 */
function ai1wm_get_uploads_dir() {
    $upload_dir = wp_upload_dir();
    return $upload_dir['basedir'];
}

/**
 * Get WordPress uploads URL
 *
 * @return string
 */
function ai1wm_get_uploads_url() {
    $upload_dir = wp_upload_dir();
    return $upload_dir['baseurl'];
}