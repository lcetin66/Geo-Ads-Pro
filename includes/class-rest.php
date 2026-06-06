<?php
// Author: Levent Cetin - 3CCS.com

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_REST {

    private $regions;
    private $citymap;

    public function __construct($regions, $citymap) {
        $this->regions = $regions;
        $this->citymap = $citymap;

        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {

        register_rest_route('geo-ads-pro/v1', '/banner', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_banner'],
            'permission_callback' => '__return_true'
        ]);
    }

    public function get_banner($request) {

        $mode   = sanitize_text_field($request->get_param('mode') ?? 'global');
        $region = sanitize_text_field($request->get_param('region') ?? '');
        $city   = sanitize_text_field($request->get_param('city') ?? '');

        if ($mode === 'local') {
            $region = $this->citymap->city_to_region($city);
        }

        if (!$region) {
            return ['html' => ''];
        }

        $region_data = $this->regions->get_region($region);
        $banners = $region_data['banners'] ?? [];

        $banners = array_filter($banners, fn($b) => !empty($b['selected']));

        if (empty($banners)) {
            return ['html' => ''];
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

        return [
            'id'         => $banner['id'],
            'image'      => $src,
            'width'      => intval($banner['width']),
            'height'     => intval($banner['height']),
            'click_url'  => home_url('/?gap_click=' . intval($banner['id'])),
            'region'     => $region
        ];
    }
}
