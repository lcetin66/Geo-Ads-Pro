<?php
/**
 * Plugin Name: die1-Geo Ads Pro
 * Description: Region-based banner management, city → region mapping, and widget display.
 * Version: 1.0.9
 * Author: Levent Cetin - 3CCS.com
 * Text Domain: geo-ads-pro
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

define('GAP_PLUGIN_FILE', __FILE__);
define('GAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GAP_VERSION', '1.0.9');
define('GAP_SCHEMA_VERSION', '2026060701');

require_once GAP_PLUGIN_DIR . 'includes/helpers.php';
require_once GAP_PLUGIN_DIR . 'includes/class-regions.php';
require_once GAP_PLUGIN_DIR . 'includes/class-citymap.php';
require_once GAP_PLUGIN_DIR . 'includes/class-banner-service.php';
require_once GAP_PLUGIN_DIR . 'includes/class-admin.php';
require_once GAP_PLUGIN_DIR . 'includes/class-widget.php';
require_once GAP_PLUGIN_DIR . 'includes/class-ajax.php';
require_once GAP_PLUGIN_DIR . 'includes/class-analytics.php';
require_once GAP_PLUGIN_DIR . 'includes/class-abtest.php';
require_once GAP_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once GAP_PLUGIN_DIR . 'includes/class-rest.php';
require_once GAP_PLUGIN_DIR . 'includes/class-settings.php';



class Geo_Ads_Pro {

    public $regions;
    public $citymap;
    public $admin;
    public $widget_manager;
    public $ajax;
    public $analytics;
    public $banner_service;

    private static $instance = null;

    public static function instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {

        $this->regions        = new Geo_Ads_Pro_Regions();
        $this->citymap        = new Geo_Ads_Pro_CityMap();
        $this->analytics      = new Geo_Ads_Pro_Analytics($this->regions);
        $this->banner_service = new Geo_Ads_Pro_Banner_Service($this->regions, $this->citymap, $this->analytics);
        $this->admin          = new Geo_Ads_Pro_Admin($this->regions, $this->citymap);
        $this->widget_manager = new Geo_Ads_Pro_Widget_Manager($this->regions, $this->citymap);
        $this->ajax           = new Geo_Ads_Pro_Ajax($this->regions, $this->citymap, $this->analytics, $this->banner_service);
        $this->abtest         = new Geo_Ads_Pro_ABTest($this->regions);
        $this->shortcode      = new Geo_Ads_Pro_Shortcode($this->regions, $this->citymap);
        $this->rest           = new Geo_Ads_Pro_REST($this->regions, $this->citymap, $this->banner_service);
        $this->settings       = new Geo_Ads_Pro_Settings();


        add_action('plugins_loaded', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function init() {
        load_plugin_textdomain('geo-ads-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function enqueue_public_assets() {
        wp_enqueue_style(
            'geo-ads-pro-public',
            GAP_PLUGIN_URL . 'public/css/geo-ads-pro.css',
            [],
            GAP_VERSION
        );

        wp_enqueue_script(
            'geo-ads-pro-public',
            GAP_PLUGIN_URL . 'public/js/geo-ads-pro.js',
            ['jquery'],
            GAP_VERSION,
            true
        );

        wp_localize_script('geo-ads-pro-public', 'GAP_AJAX', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gap_public_nonce'),
            'view_ad' => __('View ad', 'geo-ads-pro'),
            'no_banner' => __('No banner available.', 'geo-ads-pro')
        ]);
    }

    public function enqueue_admin_assets($hook) {

        // Sadece die1-Geo Ads Pro admin sayfalarında çalışsın
        if (strpos($hook, 'geo-ads-pro') === false) {
            return;
        }

        // Temel admin CSS
        wp_enqueue_style(
            'geo-ads-pro-admin',
            GAP_PLUGIN_URL . 'assets/css/admin.css',
            [],
            GAP_VERSION
        );

        // Modern UI redesign CSS
        wp_enqueue_style(
            'geo-ads-pro-admin-ui',
            GAP_PLUGIN_URL . 'assets/css/admin-ui.css',
            [],
            GAP_VERSION
        );

        // Analytics sayfası için özel CSS + JS
        if (strpos($hook, 'geo-ads-pro-analytics') !== false) {

            wp_enqueue_style(
                'geo-ads-pro-analytics',
                GAP_PLUGIN_URL . 'assets/css/analytics.css',
                [],
                GAP_VERSION
            );

            wp_enqueue_script(
                'geo-ads-pro-analytics',
                GAP_PLUGIN_URL . 'assets/js/analytics.js',
                ['jquery'],
                GAP_VERSION,
                true
            );
        }

        // Rotation sayfası için özel CSS + JS
        if (strpos($hook, 'geo-ads-pro-rotation') !== false || (strpos($hook, 'toplevel_page_geo-ads-pro-banners') !== false && isset($_GET['page']) && $_GET['page'] === 'geo-ads-pro-rotation')) {

            wp_enqueue_style(
                'geo-ads-pro-admin-rotation',
                GAP_PLUGIN_URL . 'assets/css/admin-rotation.css',
                ['geo-ads-pro-admin'],
                GAP_VERSION
            );

            wp_enqueue_script(
                'geo-ads-pro-admin-rotation',
                GAP_PLUGIN_URL . 'assets/js/rotation.js',
                ['jquery'],
                GAP_VERSION,
                true
            );
        }

        // Genel admin JS
        wp_enqueue_script(
            'geo-ads-pro-admin',
            GAP_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            GAP_VERSION,
            true
        );
    }


}

function GAP() { return Geo_Ads_Pro::instance(); }

function gap_activate() {
    gap_maybe_upgrade(true);
    gap_schedule_monthly_reports_cron();
}

function gap_deactivate() {
    gap_unschedule_monthly_reports_cron();
}

function gap_schedule_monthly_reports_cron() {
    if (!wp_next_scheduled('gap_monthly_report_cron')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'gap_monthly_report_cron');
    }
}

function gap_unschedule_monthly_reports_cron() {
    $timestamp = wp_next_scheduled('gap_monthly_report_cron');
    while ($timestamp) {
        wp_unschedule_event($timestamp, 'gap_monthly_report_cron');
        $timestamp = wp_next_scheduled('gap_monthly_report_cron');
    }
}

function gap_maybe_upgrade($force = false) {
    $installed_schema = (string) get_option('gap_schema_version', '0');
    $installed_version = (string) get_option('gap_version', '0.0.0');

    if (!$force && $installed_schema === GAP_SCHEMA_VERSION && $installed_version === GAP_VERSION) {
        return;
    }

    gap_upload_base_dir();
    add_option('gap_enable_local_mode', 0);
    add_option('gap_local_targeting_method', 'city_map');
    add_option('gap_rotation_mode', 'random');
    add_option('gap_abtest_auto', 0);
    add_option('gap_delete_data_on_uninstall', 0);
    add_option('gap_auto_monthly_reports', 0);
    add_option('gap_monthly_report_start_month', current_time('Y-m'), '', false);
    add_option('gap_monthly_report_history', [], '', false);
    add_option('gap_monthly_report_log', [], '', false);

    add_option('gap_rotation_groups', [], '', false);

    gap_protected_json_path('regions');
    gap_protected_json_path('city-map');
    gap_protected_json_path('analytics');

    if (version_compare($installed_version, '1.0.7', '<')) {
        delete_option('gap_upgrade_required');
    }

    update_option('gap_version', GAP_VERSION, false);
    update_option('gap_schema_version', GAP_SCHEMA_VERSION, false);
    update_option('gap_upgraded_at', current_time('mysql'), false);
}

function gap_redirect_legacy_admin_paths() {
    if (is_admin()) {
        return;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }

    $request_uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if ($request_uri === '' || strpos($request_uri, '/wp-admin/') === false || strpos($request_uri, 'admin.php') !== false) {
        return;
    }

    $path = wp_parse_url($request_uri, PHP_URL_PATH) ?: '';
    $slug = basename(rtrim($path, '/'));

    $pages = [
        'geo-ads-pro' => 'geo-ads-pro',
        'geo-ads-pro-analytics' => 'geo-ads-pro-analytics',
        'geo-ads-pro-abtest' => 'geo-ads-pro-abtest',
        'geo-ads-pro-settings' => 'geo-ads-pro-settings',
    ];

    if (!isset($pages[$slug])) {
        return;
    }

    wp_safe_redirect(admin_url('admin.php?page=' . $pages[$slug]));
    exit;
}

register_activation_hook(__FILE__, 'gap_activate');
register_deactivation_hook(__FILE__, 'gap_deactivate');
add_action('plugins_loaded', 'gap_schedule_monthly_reports_cron', 2);
add_action('plugins_loaded', 'gap_maybe_upgrade', 1);
add_action('init', 'gap_redirect_legacy_admin_paths', 0);

// Enable shortcodes in ALL theme custom code/text fields
add_filter('the_content', 'do_shortcode');
add_filter('the_excerpt', 'do_shortcode');
add_filter('term_description', 'do_shortcode');
add_filter('comment_text', 'do_shortcode');
add_filter('widget_description', 'do_shortcode');
add_filter('list_table_pages', 'do_shortcode');

// Enable shortcodes in ALL widget output (Text, Custom HTML, custom theme widgets)
add_filter('widget_text', 'do_shortcode');                    // Legacy Text widgets
add_filter('widget_custom_html_content', 'do_shortcode');     // WordPress 5.8+ Custom HTML widgets
add_filter('widget_content', 'do_shortcode');                 // Universal (WP 6.7+) catch-all

// Jannah / TieLabs ad code fields: ensure shortcode runs on theme's custom ad output
add_filter('TieLabs/custom_ad_code', function($code) {
    return do_shortcode($code);
}, 20);

// SECURITY: Add security headers for uploaded banner images
add_action('send_headers', function() {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
});

// Cover WordPress Gutenberg Custom HTML blocks explicitly — only our shortcode
add_filter('render_block', function($block_content, $block) {
    if ($block['blockName'] !== 'core/html') return $block_content;
    if (strpos($block_content, '[geo_ads_pro') === false) return $block_content;
    return do_shortcode($block_content);
}, 10, 2 );

// SECURITY: Do NOT hijack wp_kses_post — it strips dangerous HTML.
// Running do_shortcode on every kses output is a stored-XSS vector.
// (Removed the dangerous wp_kses_post hook that was processing arbitrary content.)

GAP();
