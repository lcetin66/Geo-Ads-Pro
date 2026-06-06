<?php
// Date: 20260606
// Author: Levent Cetin - 3CCS.com

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_Settings {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_page() {

        add_submenu_page(
            'geo-ads-pro',
            'Settings',
            'Settings',
            'manage_options',
            'geo-ads-pro-settings',
            [$this, 'render_page']
        );
    }

    public function register_settings() {

        register_setting('gap_settings_group', 'gap_enable_local_mode');
        register_setting('gap_settings_group', 'gap_default_region');
        register_setting('gap_settings_group', 'gap_rotation_mode');
        register_setting('gap_settings_group', 'gap_abtest_auto');
    }

    public function render_page() {

        if (!current_user_can('manage_options')) {
            wp_die('Yetkin yok.');
        }

        // Analytics sıfırlama işlemi
        if (isset($_POST['gap_reset_analytics'])) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gap_reset_analytics_nonce')) {
                wp_die('Geçersiz istek.');
            }
            $regions = GAP()->regions->get_all();
            foreach ($regions as $region => $data) {
                if (isset($data['banners']) && is_array($data['banners'])) {
                    foreach ($data['banners'] as &$b) {
                        $b['impressions'] = 0;
                        $b['clicks'] = 0;
                    }
                    GAP()->regions->update_banners($region, $data['banners']);
                }
            }
            echo '<div class="updated"><p>Analytics verileri sıfırlandı.</p></div>';
        }

        $regions = GAP()->regions->get_all();
        ?>

        <div class="wrap gap-settings-page">
            <h1>Geo Ads Pro – Settings</h1>

            <form method="post" action="options.php">
                <?php settings_fields('gap_settings_group'); ?>
                <?php do_settings_sections('gap_settings_group'); ?>

                <table class="form-table">

                    <tr>
                        <th scope="row">Local Mode (IP Tabanlı)</th>
                        <td>
                            <input type="checkbox" name="gap_enable_local_mode"
                                   value="1" <?php checked(get_option('gap_enable_local_mode'), 1); ?>>
                            <label>IP tabanlı şehir → bölge eşleştirmeyi aktif et</label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Varsayılan Bölge</th>
                        <td>
                            <select name="gap_default_region">
                                <option value="">Seçin</option>
                                <?php foreach ($regions as $r => $data): ?>
                                    <option value="<?php echo esc_attr($r); ?>"
                                        <?php selected(get_option('gap_default_region'), $r); ?>>
                                        <?php echo esc_html($r); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Banner Rotasyon Modu</th>
                        <td>
                            <select name="gap_rotation_mode">
                                <option value="random" <?php selected(get_option('gap_rotation_mode'), 'random'); ?>>Random</option>
                                <option value="sequential" <?php selected(get_option('gap_rotation_mode'), 'sequential'); ?>>Sequential</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">A/B Test Otomatik Optimizasyon</th>
                        <td>
                            <input type="checkbox" name="gap_abtest_auto"
                                   value="1" <?php checked(get_option('gap_abtest_auto'), 1); ?>>
                            <label>En yüksek CTR’a sahip banner’ı otomatik seç</label>
                        </td>
                    </tr>

                </table>

                <hr>

                <button type="submit" class="button button-primary">Ayarları Kaydet</button>
            </form>

            <hr>

            <form method="post">
                <?php wp_nonce_field('gap_reset_analytics_nonce'); ?>
                <button name="gap_reset_analytics" class="button button-secondary"
                        onclick="return confirm('Analytics verileri sıfırlansın mı?')">
                    Analytics Verilerini Sıfırla
                </button>
            </form>

        </div>

        <?php
    }
}
