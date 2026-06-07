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

    public function render($atts) {

        $atts = shortcode_atts([
            'mode'   => 'global',
            'region' => ''
        ], $atts);

        $mode   = sanitize_text_field($atts['mode']);
        $region = sanitize_text_field($atts['region']);

        // AJAX çağrısı yerine direkt backend banner seçimi
        $result = GAP()->banner_service->get_banner_html($mode, $region, '');

        if (!empty($result['html'])) {
            return $result['html'];
        }

        return '';
    }
}
