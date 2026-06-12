<?php
// Date: 20260606
// Author: Levent Cetin - 3CCS.com

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_ABTest {

    private $regions;

    public function __construct($regions) {
        $this->regions = $regions;

        add_action('admin_menu', [$this, 'register_page'], 20);
    }

    public function register_page() {

        add_submenu_page(
            'geo-ads-pro-banners',
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
            <h1><?php esc_html_e('die1-Geo Ads Pro – A/B Test System', 'geo-ads-pro'); ?></h1>

            <div style="background:#f0f6fc;border-left:4px solid #2271b1;padding:12px 16px;margin:12px 0 20px;border-radius:3px;max-width:800px;">
                <p style="margin:0 0 8px;font-weight:600;font-size:14px;"><?php esc_html_e('What is the A/B Test System?', 'geo-ads-pro'); ?></p>
                <p style="margin:0 0 6px;"><?php esc_html_e('The A/B Test System automatically compares banners of the same size within the same region. It measures which banner gets more clicks and declares the best-performing one as the "Winner".', 'geo-ads-pro'); ?></p>
                <p style="margin:0;color:#555;"><?php esc_html_e('CTR (Click-Through Rate) = Number of Clicks ÷ Number of Impressions × 100. Example: If a banner was shown 1,000 times and clicked 20 times, the CTR is 2%. The higher the CTR, the more effective the banner.', 'geo-ads-pro'); ?></p>
            </div>

            <table class="widefat">
                <thead>
                    <tr>
                        <th>
                            <?php esc_html_e('Region', 'geo-ads-pro'); ?>
                            <br><small style="font-weight:normal;color:#777;"><?php esc_html_e('The geographic area where the banner is displayed.', 'geo-ads-pro'); ?></small>
                        </th>
                        <th>
                            <?php esc_html_e('Size', 'geo-ads-pro'); ?>
                            <br><small style="font-weight:normal;color:#777;"><?php esc_html_e('Banner dimensions (width × height in pixels). Banners with the same size are compared against each other.', 'geo-ads-pro'); ?></small>
                        </th>
                        <th>
                            <?php esc_html_e('Variants', 'geo-ads-pro'); ?>
                            <br><small style="font-weight:normal;color:#777;"><?php esc_html_e('All competing banners in this group. Each banner\'s ID and CTR (click rate) is shown.', 'geo-ads-pro'); ?></small>
                        </th>
                        <th>
                            <?php esc_html_e('Winner', 'geo-ads-pro'); ?>
                            <br><small style="font-weight:normal;color:#777;"><?php esc_html_e('The banner with the highest CTR – the most effective banner in this group.', 'geo-ads-pro'); ?></small>
                        </th>
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
                            <td><?php echo esc_html(Geo_Ads_Pro_Regions::display_name($region)); ?></td>
                            <td><?php echo esc_html($size); ?></td>
                            <td>
                                <?php foreach ($banners as $b): ?>
                                    <div>
                                        <?php $stats = GAP()->analytics->get_banner_stats($b['id']); ?>
                                        <?php $has_stats = !empty($stats['region']) || !empty($stats['impressions']) || !empty($stats['clicks']); ?>
                                        <?php $imp = $has_stats ? ($stats['impressions'] ?? 0) : ($b['impressions'] ?? 0); ?>
                                        <?php $clk = $has_stats ? ($stats['clicks'] ?? 0) : ($b['clicks'] ?? 0); ?>
                                        <?php esc_html_e('ID', 'geo-ads-pro'); ?>: <?php echo intval($b['id']); ?> —
                                        <?php esc_html_e('CTR', 'geo-ads-pro'); ?>: <?php echo !empty($imp)
                                            ? round($clk / $imp * 100, 2) . '%'
                                            : '0%'; ?>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <?php if ($winner): ?>
                                    <strong><?php esc_html_e('ID', 'geo-ads-pro'); ?> <?php echo intval($winner['id']); ?></strong>
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
