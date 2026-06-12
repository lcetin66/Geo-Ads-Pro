<?php
// Plugin Name: die1-Geo Ads Pro - class-regions.php
// 06062026
// Author: Levent Cetin - 3CCS.com

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_Regions {

    const UNLIMITED_REGION = '__gap_unlimited__';

    private $file;
    private $data = [];

    public function __construct() {
        $this->file = gap_protected_json_path('regions');
        $this->load();
    }

    private function load() {
        $this->data = gap_read_json_file($this->file);
        $this->ensure_unlimited_region();
    }

    private function save() {
        gap_write_json_file($this->file, $this->data);
    }

    private function ensure_unlimited_region() {
        if (!is_array($this->data)) {
            $this->data = [];
        }

        if (!isset($this->data[self::UNLIMITED_REGION]) || !is_array($this->data[self::UNLIMITED_REGION])) {
            $this->data = [self::UNLIMITED_REGION => [
                'banners'    => [],
                'latitude'   => 0,
                'longitude'  => 0,
                'radius_km'  => 0,
                'is_default' => true,
            ]] + $this->data;
            $this->save();
        } else {
            $this->data[self::UNLIMITED_REGION] = array_merge([
                'banners'    => [],
                'latitude'   => 0,
                'longitude'  => 0,
                'radius_km'  => 0,
                'is_default' => true,
            ], $this->data[self::UNLIMITED_REGION]);
        }

        if (!get_option('gap_unlimited_region_initialized')) {
            update_option('gap_default_region', self::UNLIMITED_REGION, false);
            update_option('gap_unlimited_region_initialized', 1, false);
        }
    }

    public static function unlimited_region_key() {
        return self::UNLIMITED_REGION;
    }

    public static function is_unlimited_region($region) {
        return self::normalize_region_input($region) === self::UNLIMITED_REGION;
    }

    public static function normalize_region_input($region) {
        $region = trim((string) $region);
        $normalized = function_exists('remove_accents') ? remove_accents($region) : $region;
        $normalized = strtolower($normalized);

        $labels = [
            self::UNLIMITED_REGION,
            'unlimited',
            'unbegrenzt',
            'sinirsiz',
            'sınırsız',
            'unlimitet',
        ];

        if (in_array($normalized, $labels, true) || $region === self::UNLIMITED_REGION) {
            return self::UNLIMITED_REGION;
        }

        return $region;
    }

    public static function unlimited_region_label() {
        return __('Unlimited', 'geo-ads-pro');
    }

    public static function display_name($region) {
        return self::is_unlimited_region($region) ? self::unlimited_region_label() : (string) $region;
    }

    public function get_all() {
        return $this->data;
    }

    public function get_region($region) {
        $region = self::normalize_region_input($region);
        return $this->data[$region] ?? ['banners' => []];
    }

    public function add_region($region, $meta = []) {
        // Path traversal ve injection koruması
        $region = sanitize_text_field(self::normalize_region_input($region));
        if (self::is_unlimited_region($region) || $region === self::unlimited_region_label()) {
            return;
        }
        if (!isset($this->data[$region])) {
            $this->data[$region] = array_merge([
                'banners' => [],
            ], $this->sanitize_targeting_meta($meta));
            $this->save();
        }
    }

    private function sanitize_targeting_meta($meta) {
        return [
            'latitude'  => isset($meta['latitude']) ? floatval($meta['latitude']) : 0,
            'longitude' => isset($meta['longitude']) ? floatval($meta['longitude']) : 0,
            'radius_km' => isset($meta['radius_km']) ? max(0, floatval($meta['radius_km'])) : 0,
        ];
    }

    public function update_region_targeting($region, $meta) {
        $region = self::normalize_region_input($region);
        if (!isset($this->data[$region])) {
            return;
        }
        if (self::is_unlimited_region($region)) {
            $this->data[$region] = array_merge($this->data[$region], [
                'latitude'  => 0,
                'longitude' => 0,
                'radius_km' => 0,
            ]);
            if (!isset($this->data[$region]['banners']) || !is_array($this->data[$region]['banners'])) {
                $this->data[$region]['banners'] = [];
            }
            $this->save();
            return;
        }

        $this->data[$region] = array_merge($this->data[$region], $this->sanitize_targeting_meta($meta));
        if (!isset($this->data[$region]['banners']) || !is_array($this->data[$region]['banners'])) {
            $this->data[$region]['banners'] = [];
        }
        $this->save();
    }

    public function add_banner($region, $banner) {
        // Enjeksiyon koruması: bölge adı temizlenmeli
        $region = sanitize_text_field(self::normalize_region_input($region));
        if (!isset($this->data[$region])) $this->data[$region] = ['banners' => []];
        // Banner yapısı doğrulanmalı — enjeksiyon önleme
        if (is_array($banner)) {
            $banner = [
                'id'             => absint($banner['id'] ?? 0),
                'file'           => sanitize_file_name($banner['file'] ?? ''),
                'width'          => absint($banner['width'] ?? 0),
                'height'         => absint($banner['height'] ?? 0),
                'selected'       => !empty($banner['selected']),
                'url'            => esc_url_raw($banner['url'] ?? ''),
                'link_target'    => in_array($banner['link_target'] ?? '', ['_blank', '_self'], true) ? $banner['link_target'] : '_blank',
                'customer_email' => sanitize_email($banner['customer_email'] ?? ''),
            ];
        }
        $this->data[$region]['banners'][] = $banner;
        $this->save();
    }

    public function update_banners($region, $banners) {
        // Enjeksiyon koruması
        $region = sanitize_text_field(self::normalize_region_input($region));
        if (!isset($this->data[$region])) $this->data[$region] = ['banners' => []];

        // Her banner'ın yapısını doğrula — enjeksiyon önleme
        $validated = [];
        foreach ($banners as $banner) {
            if (!is_array($banner)) continue;
            $validated[] = [
                'id'             => absint($banner['id'] ?? 0),
                'file'           => sanitize_file_name($banner['file'] ?? ''),
                'width'          => absint($banner['width'] ?? 0),
                'height'         => absint($banner['height'] ?? 0),
                'selected'       => !empty($banner['selected']),
                'url'            => esc_url_raw($banner['url'] ?? ''),
                'link_target'    => in_array($banner['link_target'] ?? '', ['_blank', '_self'], true) ? $banner['link_target'] : '_blank',
                'customer_email' => sanitize_email($banner['customer_email'] ?? ''),
            ];
        }
        $this->data[$region]['banners'] = array_values($validated);
        $this->save();
    }

    public function delete_banner($region, $banner_id) {
        // Enjeksiyon koruması
        $region = sanitize_text_field(self::normalize_region_input($region));
        $banner_id = absint($banner_id);
        if (isset($this->data[$region])) {
            $banners = $this->data[$region]['banners'] ?? [];
            $this->data[$region]['banners'] = array_values(array_filter($banners, fn($b) => intval($b['id']) != $banner_id));
            $this->save();
        }
    }

    public function delete_region($region) {
        // Enjeksiyon koruması
        $region = sanitize_text_field(self::normalize_region_input($region));
        if (self::is_unlimited_region($region)) {
            return;
        }
        if (isset($this->data[$region])) {
            unset($this->data[$region]);
            $this->save();
        }
    }
}
