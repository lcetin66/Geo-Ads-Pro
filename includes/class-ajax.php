<?php
// Author: Levent Cetin - 3CCS.com

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_Ajax {

    private $regions;
    private $citymap;

    public function __construct($regions, $citymap) {
        $this->regions = $regions;
        $this->citymap = $citymap;

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
        if (!in_array($mode, ['global', 'local'], true)) {
            return ['html' => '', 'region' => ''];
        }

        if ($mode === 'local') {
            $region = $this->citymap->city_to_region($city);
        }

        if (!$region) {
            return ['html' => '', 'region' => ''];
        }

        $region_data = $this->regions->get_region($region);
        $banners = $region_data['banners'] ?? [];

        $banners = array_filter($banners, fn($b) => !empty($b['selected']));

        if (empty($banners)) {
            return ['html' => '', 'region' => $region];
        }

        // Boyut bazlı grupla
        $groups = [];
        foreach ($banners as $b) {
            $key = intval($b['width']) . 'x' . intval($b['height']);
            $groups[$key][] = $b;
        }

        $first_group = reset($groups);
        $banner = $first_group[array_rand($first_group)];

        $base_url = gap_upload_base_url();
        $src = esc_url($base_url . '/' . $region . '/' . $banner['file']);

        // Güvenli redirect linki
        $click_url = home_url('/?gap_click=' . intval($banner['id']));

        // HTML
        $html = '<a href="' . esc_url($click_url) . '" target="_blank" rel="noopener noreferrer">'
              . '<img class="gap-banner" data-banner-id="' . intval($banner['id']) . '" '
              . 'src="' . $src . '" width="' . intval($banner['width']) . '" height="' . intval($banner['height']) . '" alt="">'
              . '</a>';

        return [
            'html'   => $html,
            'region' => $region
        ];
    }

    /**
     * Banner GET (rotasyon + HTML)
     */
    public function get_banner() {

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

        $banner_id = intval($_POST['banner_id'] ?? 0);
        $region    = sanitize_text_field($_POST['region'] ?? '');

        if (!$banner_id || !$region) wp_send_json(['ok' => false]);

        $region_data = $this->regions->get_region($region);
        $banners = $region_data['banners'] ?? [];

        foreach ($banners as &$b) {
            if ($b['id'] == $banner_id) {
                if (!isset($b['impressions'])) $b['impressions'] = 0;
                $b['impressions']++;
            }
        }

        $this->regions->update_banners($region, $banners);

        wp_send_json(['ok' => true]);
    }

    /**
     * Click Tracking + Redirect
     */
    public function handle_click_redirect() {

        if (!isset($_GET['gap_click'])) return;

        $banner_id = intval($_GET['gap_click']);

        // Tüm bölgelerde ara
        $regions = $this->regions->get_all();

        foreach ($regions as $region => $data) {
            foreach ($data['banners'] as &$b) {

                if ($b['id'] == $banner_id) {

                    // Click sayacı
                    if (!isset($b['clicks'])) $b['clicks'] = 0;
                    $b['clicks']++;

                    // Kaydet
                    $this->regions->update_banners($region, $data['banners']);

                    // URL yoksa ana sayfaya
                    if (empty($b['url'])) {
                        wp_redirect(home_url('/'));
                        exit;
                    }

                    // Güvenli yönlendirme
                    wp_redirect(esc_url_raw($b['url']));
                    exit;
                }
            }
        }

        // Banner bulunamazsa
        wp_redirect(home_url('/'));
        exit;
    }
}
