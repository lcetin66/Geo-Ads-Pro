<?php
// Date: 20260606
// Author: Levent Cetin - 3CCS.com

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_ABTest {

    private $regions;

    public function __construct($regions) {
        $this->regions = $regions;

        add_action('admin_menu', [$this, 'register_page']);
    }

    public function register_page() {

        add_submenu_page(
            'geo-ads-pro',
            __('A/B Test', 'geo-ads-pro'),
            __('A/B Test', 'geo-ads-pro'),
            'manage_options',
            'geo-ads-pro-abtest',
            [$this, 'render_page']
        );
    }

    public function render_page() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'geo-ads-pro'));
        }

        $regions = $this->regions->get_all();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Geo Ads Pro – A/B Test System', 'geo-ads-pro'); ?></h1>

            <p><?php esc_html_e('Banners with the same size are automatically grouped as variants.', 'geo-ads-pro'); ?></p>

            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Region', 'geo-ads-pro'); ?></th>
                        <th><?php esc_html_e('Size', 'geo-ads-pro'); ?></th>
                        <th><?php esc_html_e('Variants', 'geo-ads-pro'); ?></th>
                        <th><?php esc_html_e('Winner', 'geo-ads-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($regions as $region => $data): ?>

                    <?php
                    // Boyut bazlı grupla
                    $groups = [];
                    foreach ($data['banners'] as $b) {
                        $key = intval($b['width']) . 'x' . intval($b['height']);
                        $groups[$key][] = $b;
                    }
                    ?>

                    <?php foreach ($groups as $size => $banners): ?>

                        <?php
                        // Kazanan varyant (CTR)
                        $winner = null;
                        $best_ctr = 0;

                        foreach ($banners as $b) {
                            $stats = GAP()->analytics->get_banner_stats($b['id']);
                            $has_stats = !empty($stats['region']) || !empty($stats['impressions']) || !empty($stats['clicks']);
                            $imp = $has_stats ? ($stats['impressions'] ?? 0) : ($b['impressions'] ?? 0);
                            $clk = $has_stats ? ($stats['clicks'] ?? 0) : ($b['clicks'] ?? 0);
                            $ctr = ($imp > 0) ? ($clk / $imp) : 0;

                            if ($ctr > $best_ctr) {
                                $best_ctr = $ctr;
                                $winner = $b;
                            }
                        }
                        ?>

                        <tr>
                            <td><?php echo esc_html($region); ?></td>
                            <td><?php echo esc_html($size); ?></td>
                            <td>
                                <?php foreach ($banners as $b): ?>
                                    <div>
                                        <?php $stats = GAP()->analytics->get_banner_stats($b['id']); ?>
                                        <?php $has_stats = !empty($stats['region']) || !empty($stats['impressions']) || !empty($stats['clicks']); ?>
                                        <?php $imp = $has_stats ? ($stats['impressions'] ?? 0) : ($b['impressions'] ?? 0); ?>
                                        <?php $clk = $has_stats ? ($stats['clicks'] ?? 0) : ($b['clicks'] ?? 0); ?>
                                        ID: <?php echo intval($b['id']); ?> —
                                        CTR: <?php echo !empty($imp)
                                            ? round($clk / $imp * 100, 2) . '%'
                                            : '0%'; ?>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <?php if ($winner): ?>
                                    <strong>ID <?php echo intval($winner['id']); ?></strong>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
