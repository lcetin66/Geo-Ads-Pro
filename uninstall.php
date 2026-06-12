<?php
// Date: 20260606
/**
 * die1-Geo Ads Pro Uninstall
 *
 * Runs when the plugin is deleted.
 * 
 * @package Geo_Ads_Pro
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$delete_data = (int) get_option('gap_delete_data_on_uninstall', 0);

if (!$delete_data) {
    return;
}

delete_option('gap_enable_local_mode');
delete_option('gap_default_region');
delete_option('gap_unlimited_region_initialized');
delete_option('gap_rotation_mode');
delete_option('gap_rotation_state');
delete_option('gap_abtest_auto');
delete_option('gap_delete_data_on_uninstall');
delete_option('gap_version');
delete_option('gap_schema_version');
delete_option('gap_upgraded_at');
delete_option('gap_upgrade_required');

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
