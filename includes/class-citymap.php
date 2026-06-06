<?php
// Plugin Name: Geo Ads Pro - class-citymap.php
// 06062026
// Author: Levent Cetin - 3CCS.com

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_CityMap {

    private $file;
    private $data = [];

    public function __construct() {
        $this->file = gap_upload_base_dir() . '/city-map.json';
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

    public function map_city($city, $region) {
        $city   = (string) $city;
        $region = (string) $region;
        $this->data[$city] = $region;
        $this->save();
    }

    public function city_to_region($city) {
        $city = (string) $city;

        if (isset($this->data[$city])) return $this->data[$city];

        foreach ($this->data as $c => $r) {
            if (mb_strtolower($c) === mb_strtolower($city)) return $r;
        }

        return '';
    }
}
