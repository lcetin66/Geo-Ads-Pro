<?php
// Date: 20260606
// Author: Levent Cetin - 3CCS.com

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_REST {

    private $regions;
    private $citymap;
    private $banner_service;

    public function __construct($regions, $citymap, $banner_service) {
        $this->regions = $regions;
        $this->citymap = $citymap;
        $this->banner_service = $banner_service;

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
        if (!gap_rate_limit('rest_banner', 120, MINUTE_IN_SECONDS)) {
            return new WP_Error('gap_rate_limited', 'Rate limit exceeded.', ['status' => 429]);
        }

        $mode   = sanitize_text_field($request->get_param('mode') ?? 'global');
        $region = sanitize_text_field($request->get_param('region') ?? '');
        $city   = sanitize_text_field($request->get_param('city') ?? '');

        $result = $this->banner_service->get_banner($mode, $region, $city);
        $banner = $result['banner'];

        if (!$banner) {
            return ['html' => ''];
        }

        $src = $this->banner_service->banner_image_url($result['region'], $banner['file']);

        return [
            'id'         => $banner['id'],
            'image'      => $src,
            'width'      => intval($banner['width']),
            'height'     => intval($banner['height']),
            'click_url'  => home_url('/?gap_click=' . intval($banner['id'])),
            'region'     => $result['region']
        ];
    }
}
