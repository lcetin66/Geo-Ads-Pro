<?php
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
            'A/B Test',
            'A/B Test',
            'manage_options',
            'geo-ads-pro-abtest',
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
            <h1>Geo Ads Pro – A/B Test Sistemi</h1>

            <p>Aynı boyuttaki banner’lar otomatik varyant olarak gruplanır.</p>

            <table class="widefat">
                <thead>
                    <tr>
                        <th>Bölge</th>
                        <th>Boyut</th>
                        <th>Varyantlar</th>
                        <th>Kazanan</th>
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
                            $imp = $b['impressions'] ?? 0;
                            $clk = $b['clicks'] ?? 0;
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
                                        ID: <?php echo intval($b['id']); ?> —
                                        CTR: <?php echo isset($b['impressions']) && $b['impressions'] > 0
                                            ? round(($b['clicks'] ?? 0) / $b['impressions'] * 100, 2) . '%'
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
