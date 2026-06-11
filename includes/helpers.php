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

    // Güvenlik: Sadece https izinli — http ile açık linklere izin verilmez (phishing/SEO riski)
    $url = esc_url_raw($url, ['https']);
    if (!$url) {
        return '';
    }

    $parts = wp_parse_url($url);
    if (empty($parts['scheme']) || !in_array($parts['scheme'], ['https'], true)) {
        return '';
    }

    // Kendi domainine yönlendirme engelle — open redirect önleme
    $home_parse = wp_parse_url(home_url());
    if (!empty($parts['host']) && !empty($home_parse['host'])) {
        if (strcasecmp($parts['host'], $home_parse['host']) === 0 || strcasecmp($parts['host'], 'www.' . $home_parse['host']) === 0) {
            return ''; // Kendi domaine yönlendirme engellendi (SEO spam koruması)
        }
    }

    return $url;
}

function gap_rate_limit($bucket, $limit, $window) {
    // Güvenlik: IP adresi FILTER_VALIDATE_IP ile doğrulanmalı — sanitize_text_field IP için uygun değil
    $raw_ip = wp_unslash($_SERVER['REMOTE_ADDR'] ?? '');
    $ip     = filter_var($raw_ip, FILTER_VALIDATE_IP) ? $raw_ip : '0.0.0.0';
    $key    = 'gap_rate_' . md5($bucket . '|' . $ip);
    $count = intval(get_transient($key));

    if ($count >= $limit) {
        return false;
    }

    set_transient($key, $count + 1, $window);
    return true;
}

/**
 * Geocode a region name to lat/lon coordinates.
 */
function gap_geocode_region_center($region) {
    // Build a local lookup of well-known German/Turkish regions
    $known = [
        'NRW'                 => ['latitude' => 51.434,  'longitude' => 7.128],
        'Nordrhein-Westfalen' => ['latitude' => 51.434,  'longitude' => 7.128],
        'Bayern'              => ['latitude' => 48.790,  'longitude' => 11.496],
        'Bavaria'             => ['latitude' => 48.790,  'longitude' => 11.496],
        'Berlin'              => ['latitude' => 52.520,  'longitude' => 13.405],
        'Hamburg'             => ['latitude' => 53.551,  'longitude' => 9.993],
        'Hessen'              => ['latitude' => 50.652,  'longitude' => 9.166],
        'Istanbul'            => ['latitude' => 41.008,  'longitude' => 28.978],
        'Antalya'             => ['latitude' => 36.897,  'longitude' => 30.713],
        'Izmir'               => ['latitude' => 38.419,  'longitude' => 27.128],
        // Almanya şehirleri
        'Stuttgart'           => ['latitude' => 48.775,  'longitude' => 9.182],
        'Stuttgard'           => ['latitude' => 48.775,  'longitude' => 9.182],
        'Düsseldorf'          => ['latitude' => 51.227,  'longitude' => 6.773],
        'Dusseldorf'          => ['latitude' => 51.227,  'longitude' => 6.773],
        'Duisburg'            => ['latitude' => 51.435,  'longitude' => 6.762],
        'München'             => ['latitude' => 48.137,  'longitude' => 11.576],
        'Munich'              => ['latitude' => 48.137,  'longitude' => 11.576],
        'Köln'                => ['latitude' => 50.938,  'longitude' => 6.960],
        'Cologne'             => ['latitude' => 50.938,  'longitude' => 6.960],
        'Frankfurt'           => ['latitude' => 50.110,  'longitude' => 8.682],
        'Dortmund'            => ['latitude' => 51.514,  'longitude' => 7.468],
        'Essen'               => ['latitude' => 51.456,  'longitude' => 7.012],
        'Leipzig'             => ['latitude' => 51.340,  'longitude' => 12.374],
        'Bremen'              => ['latitude' => 53.075,  'longitude' => 8.808],
        'Dresden'             => ['latitude' => 51.050,  'longitude' => 13.738],
        'Hannover'            => ['latitude' => 52.374,  'longitude' => 9.738],
        'Nürnberg'            => ['latitude' => 49.452,  'longitude' => 11.077],
        'Nuremberg'           => ['latitude' => 49.452,  'longitude' => 11.077],
        'Bochum'              => ['latitude' => 51.482,  'longitude' => 7.216],
        'Wuppertal'           => ['latitude' => 51.256,  'longitude' => 7.150],
        'Bielefeld'           => ['latitude' => 52.021,  'longitude' => 8.532],
        'Bonn'                => ['latitude' => 50.735,  'longitude' => 7.099],
        'Mannheim'            => ['latitude' => 49.487,  'longitude' => 8.466],
        'Karlsruhe'           => ['latitude' => 49.006,  'longitude' => 8.404],
        'Gelsenkirchen'       => ['latitude' => 51.517,  'longitude' => 7.085],
        'Münster'             => ['latitude' => 51.960,  'longitude' => 7.626],
        'Augsburg'            => ['latitude' => 48.370,  'longitude' => 10.898],
        'Aachen'              => ['latitude' => 50.776,  'longitude' => 6.084],
    ];

    $region = trim($region);
    if (! empty($known[$region])) {
        return $known[$region];
    }

    // Fallback: try WordPress geocoder via wp_remote_get to a free API (Almanya ile sınırlı)
    $result = wp_remote_get(
        'https://nominatim.openstreetmap.org/search?format=json&q=' . rawurlencode($region) . '&countrycodes=de&limit=1',
        ['timeout' => 5, 'headers' => ['User-Agent' => 'GeoAdsPro/1.0']]
    );

    if (is_wp_error($result)) {
        return ['latitude' => 0, 'longitude' => 0];
    }

    $body = wp_remote_retrieve_body($result);
    $data = json_decode($body, true);
    if (! empty($data[0]['lat']) && ! empty($data[0]['lon'])) {
        return [
            'latitude'  => (float) $data[0]['lat'],
            'longitude' => (float) $data[0]['lon'],
        ];
    }

    return ['latitude' => 0, 'longitude' => 0];
}

/**
 * Resolve city name from visitor IP address (server-side).
 * Uses ip-api.com with 24h transient cache per IP.
 */
function gap_resolve_city_from_ip($ip = '') {
    if ($ip === '') {
        $raw_ip = wp_unslash($_SERVER['REMOTE_ADDR'] ?? '');
        $ip = filter_var($raw_ip, FILTER_VALIDATE_IP) ? $raw_ip : '';
    }

    if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
        return '';
    }

    $cache_key = 'gap_ip_city_' . md5($ip);
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    $response = wp_remote_get(
        'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,city,regionName,country',
        ['timeout' => 3, 'blocking' => true]
    );

    if (is_wp_error($response)) {
        set_transient($cache_key, '', HOUR_IN_SECONDS);
        return '';
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
        set_transient($cache_key, '', HOUR_IN_SECONDS);
        return '';
    }

    $city = sanitize_text_field($data['city'] ?? '');
    set_transient($cache_key, $city, DAY_IN_SECONDS);

    return $city;
}

/**
 * Sanitize customer email for storage.
 */
function gap_sanitize_customer_email($email) {
    if ($email === '') {
        return '';
    }
    $email = sanitize_email($email);
    if (is_wp_error($email)) {
        return '';
    }
    return strtolower($email);
}
