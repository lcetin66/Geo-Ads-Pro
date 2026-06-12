<?php
/* Plugin Name: die1-Geo Ads Pro - settings.php */
/* Date: 20260606 */
/* Author: Levent Cetin - 3CCS.com */

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_Settings {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_page() {

        add_submenu_page(
            'geo-ads-pro-banners',
            __('Settings', 'geo-ads-pro'),
            __('Settings', 'geo-ads-pro'),
            'manage_options',
            'geo-ads-pro-settings',
            [$this, 'render_page']
        );
    }

    public function register_settings() {

        register_setting('gap_settings_group', 'gap_enable_local_mode', ['sanitize_callback' => 'absint']);
        register_setting('gap_settings_group', 'gap_default_region', ['sanitize_callback' => ['Geo_Ads_Pro_Regions', 'normalize_region_input']]);
        register_setting('gap_settings_group', 'gap_rotation_mode', ['sanitize_callback' => [$this, 'sanitize_rotation_mode']]);
        register_setting('gap_settings_group', 'gap_abtest_auto', ['sanitize_callback' => 'absint']);
        register_setting('gap_settings_group', 'gap_delete_data_on_uninstall', ['sanitize_callback' => 'absint']);
    }

    public function sanitize_rotation_mode($mode) {
        return in_array($mode, ['random', 'sequential'], true) ? $mode : 'random';
    }

    public function render_page() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'geo-ads-pro'));
        }

        // Analytics sıfırlama işlemi
        if (isset($_POST['gap_reset_analytics'])) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gap_reset_analytics_nonce')) {
                wp_die(esc_html__('Invalid request.', 'geo-ads-pro'));
            }
            GAP()->analytics->reset();
            delete_option('gap_rotation_state');
            echo '<div class="updated"><p>' . esc_html__('Analytics data reset.', 'geo-ads-pro') . '</p></div>';
        }

        $regions = GAP()->regions->get_all();
        ?>

        <div class="wrap gap-settings-page">
            <h1><?php esc_html_e('die1-Geo Ads Pro – Settings', 'geo-ads-pro'); ?></h1>

            <table class="widefat striped" style="max-width: 760px; margin: 16px 0;">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('Plugin Version', 'geo-ads-pro'); ?></th>
                        <td><?php echo esc_html(defined('GAP_VERSION') ? GAP_VERSION : get_option('gap_version', '')); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Schema Version', 'geo-ads-pro'); ?></th>
                        <td><?php echo esc_html(get_option('gap_schema_version', '0')); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Last Upgrade', 'geo-ads-pro'); ?></th>
                        <td><?php echo esc_html(get_option('gap_upgraded_at', '-')); ?></td>
                    </tr>
                </tbody>
            </table>

            <form method="post" action="options.php">
                <?php settings_fields('gap_settings_group'); ?>
                <?php do_settings_sections('gap_settings_group'); ?>

                <table class="form-table">

                    <tr>
                        <th scope="row"><?php esc_html_e('Local Mode (IP Based)', 'geo-ads-pro'); ?></th>
                        <td>
                            <input type="checkbox" name="gap_enable_local_mode"
                                   value="1" <?php checked(get_option('gap_enable_local_mode'), 1); ?>>
                            <label><?php esc_html_e('Enable IP-based city → region mapping', 'geo-ads-pro'); ?></label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Default Region', 'geo-ads-pro'); ?></th>
                        <td>
                            <select name="gap_default_region">
                                <option value=""><?php esc_html_e('Select', 'geo-ads-pro'); ?></option>
                                <?php foreach ($regions as $r => $data): ?>
                                    <option value="<?php echo esc_attr($r); ?>"
                                        <?php selected(get_option('gap_default_region'), $r); ?>>
                                        <?php echo esc_html(Geo_Ads_Pro_Regions::display_name($r)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Banner Rotation Mode', 'geo-ads-pro'); ?></th>
                        <td>
                            <select name="gap_rotation_mode">
                                <option value="random" <?php selected(get_option('gap_rotation_mode'), 'random'); ?>><?php esc_html_e('Random', 'geo-ads-pro'); ?></option>
                                <option value="sequential" <?php selected(get_option('gap_rotation_mode'), 'sequential'); ?>><?php esc_html_e('Sequential', 'geo-ads-pro'); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('A/B Test Auto Optimization', 'geo-ads-pro'); ?></th>
                        <td>
                            <input type="checkbox" name="gap_abtest_auto"
                                   value="1" <?php checked(get_option('gap_abtest_auto'), 1); ?>>
                            <label><?php esc_html_e('Automatically select the banner with the highest CTR', 'geo-ads-pro'); ?></label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Uninstall Cleanup', 'geo-ads-pro'); ?></th>
                        <td>
                            <input type="checkbox" name="gap_delete_data_on_uninstall"
                                   value="1" <?php checked(get_option('gap_delete_data_on_uninstall'), 1); ?>>
                            <label><?php esc_html_e('Remove banner files, JSON data, and settings when the plugin is deleted', 'geo-ads-pro'); ?></label>
                        </td>
                    </tr>

                </table>

                <hr>

                <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'geo-ads-pro'); ?></button>
            </form>

            <hr>

            <form method="post">
                <?php wp_nonce_field('gap_reset_analytics_nonce'); ?>
                <button name="gap_reset_analytics" class="button button-secondary"
                        onclick="return confirm('<?php echo esc_js(__('Reset analytics data?', 'geo-ads-pro')); ?>')">
                    <?php esc_html_e('Reset Analytics Data', 'geo-ads-pro'); ?>
                </button>
            </form>

        </div>

        <?php
    }
}
