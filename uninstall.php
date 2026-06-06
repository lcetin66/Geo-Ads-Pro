<?php
/**
 * Geo Ads Pro Uninstall
 *
 * Runs when the plugin is deleted.
 * 
 * @package Geo_Ads_Pro
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options from database
delete_option('gap_enable_local_mode');
delete_option('gap_default_region');
delete_option('gap_rotation_mode');
delete_option('gap_abtest_auto');

// Delete uploads folder and all files inside
$upload = wp_upload_dir();
$dir = trailingslashit($upload['basedir']) . 'geo-ads-pro';

if (file_exists($dir)) {
    $iterator = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($files as $file) {
        if ($file->isDir()) {
            @rmdir($file->getRealPath());
        } else {
            @unlink($file->getRealPath());
        }
    }
    @rmdir($dir);
}
