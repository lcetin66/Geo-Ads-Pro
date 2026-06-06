<?php
// Plugin Name: Geo Ads Pro - class-regions.php
// 06062026
// Author: Levent Cetin - 3CCS.com

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_Regions {

    private $file;
    private $data = [];

    public function __construct() {
        $this->file = gap_upload_base_dir() . '/regions.json';
        if (!file_exists($this->file)) file_put_contents($this->file, json_encode([]));
        $this->load();
    }

    private function load() {
        $json = @file_get_contents($this->file);
        $this->data = $json ? json_decode($json, true) : [];
        if (!is_array($this->data)) $this->data = [];
    }

    private function save() {
        @file_put_contents($this->file, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function get_all() {
        return $this->data;
    }

    public function get_region($region) {
        $region = (string) $region;
        return $this->data[$region] ?? ['banners' => []];
    }

    public function add_region($region) {
        $region = (string) $region;
        if (!isset($this->data[$region])) {
            $this->data[$region] = ['banners' => []];
            $this->save();
        }
    }

    public function add_banner($region, $banner) {
        $region = (string) $region;
        if (!isset($this->data[$region])) $this->data[$region] = ['banners' => []];
        $this->data[$region]['banners'][] = $banner;
        $this->save();
    }

    public function update_banners($region, $banners) {
        $region = (string) $region;
        if (!isset($this->data[$region])) $this->data[$region] = ['banners' => []];
        $this->data[$region]['banners'] = array_values($banners);
        $this->save();
    }
}
