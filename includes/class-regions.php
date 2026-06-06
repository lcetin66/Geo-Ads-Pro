<?php
// Plugin Name: Geo Ads Pro - class-regions.php
// 06062026
// Author: Levent Cetin - 3CCS.com

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_Regions {

    private $file;
    private $data = [];

    public function __construct() {
        $this->file = gap_protected_json_path('regions');
        $this->load();
    }

    private function load() {
        $this->data = gap_read_json_file($this->file);
    }

    private function save() {
        gap_write_json_file($this->file, $this->data);
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

    public function delete_banner($region, $banner_id) {
        $region = (string) $region;
        if (isset($this->data[$region])) {
            $banners = $this->data[$region]['banners'] ?? [];
            $this->data[$region]['banners'] = array_values(array_filter($banners, fn($b) => $b['id'] != $banner_id));
            $this->save();
        }
    }

    public function delete_region($region) {
        $region = (string) $region;
        if (isset($this->data[$region])) {
            unset($this->data[$region]);
            $this->save();
        }
    }
}
