<?php
// Date: 20260606
/**
 * Plugin Name: Geo Ads Pro
 * Description: Region-based banner management, city → region mapping, and widget display.
 * Version: 1.0.7
 * Author: Levent Cetin - 3CCS.com
 * Text Domain: geo-ads-pro
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

define('GAP_PLUGIN_FILE', __FILE__);
define('GAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GAP_VERSION', '1.0.7');
define('GAP_SCHEMA_VERSION', '2026060607');

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
            'nonce' => wp_create_nonce('gap_public_nonce')
        ]);
    }

    public function enqueue_admin_assets($hook) {

        // Sadece Geo Ads Pro admin sayfalarında çalışsın
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
}

function gap_maybe_upgrade($force = false) {
    $installed_schema = (string) get_option('gap_schema_version', '0');
    $installed_version = (string) get_option('gap_version', '0.0.0');

    if (!$force && $installed_schema === GAP_SCHEMA_VERSION && $installed_version === GAP_VERSION) {
        return;
    }

    gap_upload_base_dir();
    add_option('gap_enable_local_mode', 0);
    add_option('gap_rotation_mode', 'random');
    add_option('gap_abtest_auto', 0);
    add_option('gap_delete_data_on_uninstall', 0);

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

register_activation_hook(__FILE__, 'gap_activate');
add_action('plugins_loaded', 'gap_maybe_upgrade', 1);

GAP();
