<?php
/* Plugin Name: Geo Ads Pro - shortcode.php */
/* Date: 20260609 */
/* Author: Levent Cetin - 3CCS.com */

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_Shortcode {

    private $regions;
    private $citymap;

    public function __construct($regions, $citymap) {
        $this->regions = $regions;
        $this->citymap = $citymap;

        add_shortcode('geo_ads_pro', [$this, 'render']);
    }

    private function get_visitor_ip() {
        // Önce public IP dene
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        // Fallback: private IP de olsa döndür (localhost/dev ortamı için)
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return trim($_SERVER['REMOTE_ADDR']);
        }
        return '';
    }

    private function get_visitor_city_from_ip($ip) {
        if (!$ip) return '';

        // Localhost/private IP kontrolü
        if (in_array($ip, ['127.0.0.1', '::1']) ||
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            // Private IP — external API ile gerçek IP'yi bul
            $ext = wp_remote_get('https://api.ipify.org?format=json', ['timeout' => 3]);
            if (!is_wp_error($ext)) {
                $data = json_decode(wp_remote_retrieve_body($ext), true);
                if (!empty($data['ip'])) {
                    $ip = $data['ip'];
                }
            }
        }

        if (!$ip) return '';

        // Önce transient cache'e bak
        $cache_key = 'gap_city_' . md5($ip);
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        // ip-api.com ücretsiz, kayıt gerekmez
        $response = wp_remote_get(
            'http://ip-api.com/json/' . $ip . '?fields=status,city',
            ['timeout' => 3]
        );

        $city = '';
        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($data['status']) && $data['status'] === 'success' && !empty($data['city'])) {
                $city = $data['city'];
            }
        }

        // 1 saatlik cache
        set_transient($cache_key, $city, HOUR_IN_SECONDS);
        return $city;
    }

    public function render($atts) {

        $atts = shortcode_atts([
            'mode'      => 'global',
            'region'    => '',
            'banner_id' => 0,
            'group_id'  => ''
        ], $atts);

        $mode       = sanitize_text_field($atts['mode']);
        $region     = sanitize_text_field($atts['region']);
        $banner_id  = intval($atts['banner_id']);
        $group_id   = sanitize_text_field($atts['group_id']);
        $has_group  = ($group_id !== '' && $group_id !== '0');

        // Local mode: ziyaretçinin IP'sinden şehri tespit et
        $city = '';
        $ip = '';
        $visitor_region = '';

        if ($mode === 'local' && get_option('gap_enable_local_mode')) {
            $ip = $this->get_visitor_ip();
            $city = $this->get_visitor_city_from_ip($ip);

            if ($city !== '') {
                $visitor_region = GAP()->banner_service->resolve_region_strict($city);

                if ($has_group) {
                    // Grup shortcode'u → bölge kontrolünü banner_service'e bırak
                    $region = $visitor_region;
                } else {
                    // Tekli banner: bölge eşleşmiyorsa boş dön
                    if ($visitor_region !== $region) {
                        return '';
                    }
                }
            } else {
                // IP'den şehir tespit edilemedi — fallback: default region kullan
                if ($has_group) {
                    $region = sanitize_text_field(get_option('gap_default_region', ''));
                } else {
                    return '';
                }
            }
        }

        // Global mode veya local mode sonrası: banner seçimi
        $result = GAP()->banner_service->get_banner_html($mode, $region, $city, $banner_id, $group_id);

        if (!empty($result['html'])) {
            return $result['html'];
        }

        return '';
    }
}
