<?php
/* Plugin Name: die1-Geo Ads Pro - class-citymap.php */
/* Date: 20260606 */
/* Author: Levent Cetin - 3CCS.com */

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_CityMap {

    private $file;
    private $data = [];

    public function __construct() {
        $this->file = gap_protected_json_path('city-map');
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

    public function map_city($city, $region) {
        // Enjeksiyon koruması: şehir ve bölge adları sanitization'lanmalı
        $city   = sanitize_text_field((string) $city);
        $region = sanitize_text_field((string) $region);
        $this->data[$city] = $region;
        $this->save();
    }

    public function delete_city($city) {
        // Enjeksiyon koruması
        $city = sanitize_text_field((string) $city);
        if (isset($this->data[$city])) {
            unset($this->data[$city]);
            $this->save();
        }
    }

    public function city_to_region($city) {
        $city = sanitize_text_field((string) $city);

        if (isset($this->data[$city])) return $this->data[$city];

        foreach ($this->data as $c => $r) {
            if (mb_strtolower($c) === mb_strtolower($city)) return $r;
        }

        return '';
    }

    public function remove_region_mappings($region) {
        // Enjeksiyon koruması
        $region = sanitize_text_field((string) $region);
        foreach ($this->data as $city => $r) {
            if ($r === $region) {
                unset($this->data[$city]);
            }
        }
        $this->save();
    }
}
