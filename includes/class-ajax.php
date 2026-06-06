<?php
// Date: 20260606
// Author: Levent Cetin - 3CCS.com

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_Ajax {

    private $regions;
    private $citymap;
    private $analytics;
    private $banner_service;

    public function __construct($regions, $citymap, $analytics, $banner_service) {
        $this->regions = $regions;
        $this->citymap = $citymap;
        $this->analytics = $analytics;
        $this->banner_service = $banner_service;

        add_action('wp_ajax_gap_get_banner', [$this, 'get_banner']);
        add_action('wp_ajax_nopriv_gap_get_banner', [$this, 'get_banner']);

        add_action('wp_ajax_gap_track_impression', [$this, 'track_impression']);
        add_action('wp_ajax_nopriv_gap_track_impression', [$this, 'track_impression']);

        add_action('init', [$this, 'handle_click_redirect']);
    }

    /**
     * Banner HTML ve bölge bilgisini üretir (AJAX ve Kısa kod için ortak)
     */
    public function generate_banner_html($mode, $region, $city) {
        return $this->banner_service->get_banner_html($mode, $region, $city);
    }

    /**
     * Banner GET (rotasyon + HTML)
     */
    public function get_banner() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gap_public_nonce')) {
            wp_send_json(['html' => '', 'region' => '']);
        }

        if (!gap_rate_limit('ajax_get_banner', 120, MINUTE_IN_SECONDS)) {
            wp_send_json(['html' => '', 'region' => '']);
        }

        $mode   = sanitize_text_field($_POST['mode'] ?? 'global');
        $region = sanitize_text_field($_POST['region'] ?? '');
        $city   = sanitize_text_field($_POST['city'] ?? '');

        $result = $this->generate_banner_html($mode, $region, $city);
        wp_send_json($result);
    }

    /**
     * Impression Tracking
     */
    public function track_impression() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gap_public_nonce')) {
            wp_send_json(['ok' => false]);
        }

        if (!gap_rate_limit('ajax_track_impression', 120, MINUTE_IN_SECONDS)) {
            wp_send_json(['ok' => false, 'rate_limited' => true]);
        }

        $banner_id = intval($_POST['banner_id'] ?? 0);
        $region    = sanitize_text_field($_POST['region'] ?? '');

        if (!$banner_id || !$region) wp_send_json(['ok' => false]);

        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = 'gap_imp_' . md5($ip . '|' . $banner_id);
        if (get_transient($key)) {
            wp_send_json(['ok' => true, 'skipped' => true]);
        }

        set_transient($key, 1, MINUTE_IN_SECONDS);
        $this->analytics->record_impression($banner_id, $region);

        wp_send_json(['ok' => true]);
    }

    /**
     * Click Tracking + Redirect
     */
    public function handle_click_redirect() {

        if (!isset($_GET['gap_click'])) return;

        $banner_id = intval($_GET['gap_click']);

        $found = $this->banner_service->find_banner($banner_id);

        if ($found) {
            $banner = $found['banner'];
            $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $key = 'gap_click_' . md5($ip . '|' . $banner_id);
            if (!get_transient($key) && gap_rate_limit('click_redirect', 60, MINUTE_IN_SECONDS)) {
                set_transient($key, 1, MINUTE_IN_SECONDS);
                $this->analytics->record_click($banner_id, $found['region']);
            }

            $target = gap_validate_click_url($banner['url'] ?? '');
            wp_redirect($target ?: home_url('/'));
            exit;
        }

        // Banner bulunamazsa
        wp_redirect(home_url('/'));
        exit;
    }
}
