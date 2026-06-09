<?php
/* Plugin Name: Geo Ads Pro - shortcode.php */
/* Date: 20260606 */
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
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        return '';
    }

    private function get_visitor_city_from_ip($ip) {
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
            'group_id'  => 0
        ], $atts);

        $mode       = sanitize_text_field($atts['mode']);
        $region     = sanitize_text_field($atts['region']);
        $banner_id  = intval($atts['banner_id']);
        $group_id   = intval($atts['group_id']);

        // Local mode: ziyaretçinin IP'sinden şehri tespit et
        $city = '';
        if ($mode === 'local' && get_option('gap_enable_local_mode')) {
            $ip = $this->get_visitor_ip();
            $city = $this->get_visitor_city_from_ip($ip);

            if ($city !== '') {
                // Ziyaretçinin şehrine göre bölgesini tespit et (sadece radius/citymap ile, fallback yok)
                $visitor_region = GAP()->banner_service->resolve_region_strict($city);

                // Hiçbir bölgeye eşleşmediyse veya başka bölgeye eşleştiyse boş dön
                if ($visitor_region !== $region) {
                    return '';
                }
            } else {
                // IP tespiti başarısız — local modda banner gösterme
                return '';
            }
        }

        // AJAX çağrısı yerine direkt backend banner seçimi
        $result = GAP()->banner_service->get_banner_html($mode, $region, $city, $banner_id, $group_id);

        if (!empty($result['html'])) {
            return $result['html'];
        }

        // Debug: Eğer HTML boşsa neden olduğunu göster (geliştirme ortamında)
        if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
            $debug_info = sprintf(
                '<!-- GAP DEBUG: mode=%s, region=%s, banner_id=%d, found=%s -->',
                esc_attr($mode),
                esc_attr($region),
                $banner_id,
                !empty($result) ? 'true' : 'false'
            );
            return $debug_info;
        }

        return '';
    }
}
