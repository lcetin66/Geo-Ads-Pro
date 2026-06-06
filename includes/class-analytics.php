<?php
// Author: Levent Cetin - 3CCS.com

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_Analytics {

    private $regions;

    public function __construct($regions) {
        $this->regions = $regions;

        add_action('admin_menu', [$this, 'register_page']);
    }

    public function register_page() {

        add_submenu_page(
            'geo-ads-pro',
            'Analytics',
            'Analytics',
            'manage_options',
            'geo-ads-pro-analytics',
            [$this, 'render_page']
        );
    }

    public function render_page() {

        if (!current_user_can('manage_options')) {
            wp_die('Yetkin yok.');
        }

        $regions = $this->regions->get_all();

        ?>
        <div class="wrap">
            <h1>Geo Ads Pro – Analytics</h1>

            <p>Bu ekran tıklama ve gösterim istatistiklerini gösterir.</p>

            <canvas id="gapChart" width="800" height="400"></canvas>

            <script>
                window.GAP_ANALYTICS = <?php echo json_encode($regions, JSON_UNESCAPED_UNICODE); ?>;
            </script>
        </div>
        <?php
    }
}
