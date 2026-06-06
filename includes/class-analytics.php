<?php
// Date: 20260606
// Author: Levent Cetin - 3CCS.com

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_Analytics {

    private $regions;
    private $file;
    private $data = [];

    public function __construct($regions) {
        $this->regions = $regions;
        $this->file = gap_protected_json_path('analytics');
        $this->load();

        add_action('admin_menu', [$this, 'register_page']);
    }

    private function load() {
        $this->data = gap_read_json_file($this->file);
    }

    private function save() {
        gap_write_json_file($this->file, $this->data);
    }

    public function record_impression($banner_id, $region) {
        $this->increment($banner_id, $region, 'impressions');
    }

    public function record_click($banner_id, $region) {
        $this->increment($banner_id, $region, 'clicks');
    }

    private function increment($banner_id, $region, $field) {
        $banner_id = (string) intval($banner_id);
        $region = sanitize_text_field($region);

        if ($banner_id === '0' || $region === '') {
            return;
        }

        if (!isset($this->data[$banner_id])) {
            $this->data[$banner_id] = [
                'region' => $region,
                'impressions' => 0,
                'clicks' => 0,
            ];
        }

        $this->data[$banner_id]['region'] = $region;
        $this->data[$banner_id][$field] = intval($this->data[$banner_id][$field] ?? 0) + 1;
        $this->save();
    }

    public function get_banner_stats($banner_id) {
        $banner_id = (string) intval($banner_id);
        return $this->data[$banner_id] ?? [
            'region' => '',
            'impressions' => 0,
            'clicks' => 0,
        ];
    }

    public function get_regions_with_stats() {
        $regions = $this->regions->get_all();

        foreach ($regions as $region => &$data) {
            if (empty($data['banners']) || !is_array($data['banners'])) {
                continue;
            }

            foreach ($data['banners'] as &$banner) {
                $banner_id = (string) intval($banner['id']);
                $stats = $this->data[$banner_id] ?? null;
                $banner['impressions'] = intval($stats['impressions'] ?? ($banner['impressions'] ?? 0));
                $banner['clicks'] = intval($stats['clicks'] ?? ($banner['clicks'] ?? 0));
            }
            unset($banner);
        }
        unset($data);

        return $regions;
    }

    public function reset() {
        $this->data = [];
        $this->save();

        foreach ($this->regions->get_all() as $region => $data) {
            $banners = $data['banners'] ?? [];
            foreach ($banners as &$banner) {
                unset($banner['impressions'], $banner['clicks']);
            }
            $this->regions->update_banners($region, $banners);
        }
    }

    public function delete_banner($banner_id) {
        $banner_id = (string) intval($banner_id);
        if (isset($this->data[$banner_id])) {
            unset($this->data[$banner_id]);
            $this->save();
        }
    }

    public function delete_region($region) {
        $region = sanitize_text_field($region);
        foreach ($this->data as $banner_id => $stats) {
            if (($stats['region'] ?? '') === $region) {
                unset($this->data[$banner_id]);
            }
        }
        $this->save();
    }

    public function register_page() {

        add_submenu_page(
            'geo-ads-pro',
            __('Analytics', 'geo-ads-pro'),
            __('Analytics', 'geo-ads-pro'),
            'manage_options',
            'geo-ads-pro-analytics',
            [$this, 'render_page']
        );
    }

    public function render_page() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'geo-ads-pro'));
        }

        $regions = $this->get_regions_with_stats();
        $rows = [];

        foreach ($regions as $region => $data) {
            foreach (($data['banners'] ?? []) as $banner) {
                $impressions = intval($banner['impressions'] ?? 0);
                $clicks = intval($banner['clicks'] ?? 0);
                $rows[] = [
                    'region' => $region,
                    'id' => intval($banner['id']),
                    'file' => $banner['file'] ?? '',
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
                ];
            }
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Geo Ads Pro – Analytics', 'geo-ads-pro'); ?></h1>

            <p><?php esc_html_e('This screen displays click and impression statistics.', 'geo-ads-pro'); ?></p>

            <canvas id="gapChart" width="800" height="400"></canvas>

            <table class="widefat striped gap-analytics-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Region', 'geo-ads-pro'); ?></th>
                        <th>ID</th>
                        <th><?php esc_html_e('File', 'geo-ads-pro'); ?></th>
                        <th><?php esc_html_e('Impressions', 'geo-ads-pro'); ?></th>
                        <th><?php esc_html_e('Clicks', 'geo-ads-pro'); ?></th>
                        <th>CTR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row['region']); ?></td>
                                <td><?php echo esc_html($row['id']); ?></td>
                                <td><?php echo esc_html($row['file']); ?></td>
                                <td><?php echo esc_html($row['impressions']); ?></td>
                                <td><?php echo esc_html($row['clicks']); ?></td>
                                <td><?php echo esc_html($row['ctr']); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6"><?php esc_html_e('No analytics data yet.', 'geo-ads-pro'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <script>
                window.GAP_ANALYTICS = <?php echo wp_json_encode($regions, JSON_UNESCAPED_UNICODE); ?>;
            </script>
        </div>
        <?php
    }
}
