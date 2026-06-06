<?php
// Plugin Name: Geo Ads Pro - helpers.php
// 06062026
// Author: Levent Cetin - 3CCS.com

if (!defined('ABSPATH')) exit;

function gap_upload_base_dir() {
    $upload = wp_upload_dir();
    $dir = trailingslashit($upload['basedir']) . 'geo-ads-pro';

    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
    }

    // JSON ve PHP erişimini kısıtla
    $ht = $dir . '/.htaccess';
    if (!file_exists($ht)) {
        $rules = "<FilesMatch \"\.(json|php)$\">\nDeny from all\n</FilesMatch>\n";
        @file_put_contents($ht, $rules);
    }

    return $dir;
}

function gap_upload_base_url() {
    $upload = wp_upload_dir();
    return trailingslashit($upload['baseurl']) . 'geo-ads-pro';
}
