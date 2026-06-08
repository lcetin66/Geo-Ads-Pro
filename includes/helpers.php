<?php
/* Plugin Name: Geo Ads Pro - helpers.php */
/* Date: 20260606 */
/* Author: Levent Cetin - 3CCS.com */

if (!defined('ABSPATH')) exit;

function gap_upload_base_dir() {
    $upload = wp_upload_dir();
    $dir = trailingslashit($upload['basedir']) . 'geo-ads-pro';

    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
    }

    // JSON ve PHP erişimini kısıtla
    $ht = $dir . '/.htaccess';
    $rules = "Options -Indexes\n<FilesMatch \"\\.(json|php|bak)$\">\nRequire all denied\n</FilesMatch>\n<IfModule !mod_authz_core.c>\n<FilesMatch \"\\.(json|php|bak)$\">\nDeny from all\n</FilesMatch>\n</IfModule>\n";
    @file_put_contents($ht, $rules);

    $index = $dir . '/index.php';
    if (!file_exists($index)) {
        @file_put_contents($index, "<?php\n/* Date: 20260606 */\n/* Author: Levent Cetin - 3CCS.com */\nexit;\n");
    }

    $web_config = $dir . '/web.config';
    if (!file_exists($web_config)) {
        $config = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n    <security>\n      <requestFiltering>\n        <fileExtensions>\n          <add fileExtension=\".json\" allowed=\"false\" />\n          <add fileExtension=\".php\" allowed=\"false\" />\n          <add fileExtension=\".bak\" allowed=\"false\" />\n        </fileExtensions>\n      </requestFiltering>\n    </security>\n  </system.webServer>\n</configuration>\n";
        @file_put_contents($web_config, $config);
    }

    return $dir;
}

function gap_upload_base_url() {
    $upload = wp_upload_dir();
    return trailingslashit($upload['baseurl']) . 'geo-ads-pro';
}

function gap_region_folder_name($region) {
    return sanitize_file_name((string) $region);
}

function gap_protected_json_path($name) {
    $name = sanitize_file_name((string) $name);
    $dir = gap_upload_base_dir();
    $protected = trailingslashit($dir) . $name . '.json.php';
    $legacy = trailingslashit($dir) . $name . '.json';

    if (!file_exists($protected)) {
        $data = [];
        if (file_exists($legacy)) {
            $legacy_json = @file_get_contents($legacy);
            $decoded = $legacy_json ? json_decode($legacy_json, true) : [];
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        gap_write_json_file($protected, $data);

        if (file_exists($legacy)) {
            @unlink($legacy);
        }
    }

    return $protected;
}

function gap_read_json_file($file) {
    $contents = @file_get_contents($file);
    if (!$contents) {
        return [];
    }

    $contents = preg_replace('/^<\?php exit; \?>\s*/', '', $contents);
    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : [];
}

function gap_write_json_file($file, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return @file_put_contents($file, "<?php exit; ?>\n" . $json, LOCK_EX);
}

function gap_validate_click_url($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    $url = esc_url_raw($url, ['http', 'https']);
    if (!$url) {
        return '';
    }

    $parts = wp_parse_url($url);
    if (empty($parts['scheme']) || !in_array($parts['scheme'], ['http', 'https'], true)) {
        return '';
    }

    return $url;
}

function gap_rate_limit($bucket, $limit, $window) {
    $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $key = 'gap_rate_' . md5($bucket . '|' . $ip);
    $count = intval(get_transient($key));

    if ($count >= $limit) {
        return false;
    }

    set_transient($key, $count + 1, $window);
    return true;
}
