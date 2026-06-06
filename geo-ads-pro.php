<?php
/**
 * Plugin Name: Geo Ads Pro
 * Description: Bölge bazlı banner yönetimi, şehir → bölge eşleştirme ve widget gösterimi.
 * Version: 1.0.0
 * Author: Levent Cetin - 3CCS.com
 * Text Domain: geo-ads-pro
 */

if (!defined('ABSPATH')) exit;

define('GAP_PLUGIN_FILE', __FILE__);
define('GAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GAP_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once GAP_PLUGIN_DIR . 'includes/helpers.php';
require_once GAP_PLUGIN_DIR . 'includes/class-regions.php';
require_once GAP_PLUGIN_DIR . 'includes/class-citymap.php';
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

    private static $instance = null;

    public static function instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {

        $this->regions        = new Geo_Ads_Pro_Regions();
        $this->citymap        = new Geo_Ads_Pro_CityMap();
        $this->admin          = new Geo_Ads_Pro_Admin($this->regions, $this->citymap);
        $this->widget_manager = new Geo_Ads_Pro_Widget_Manager($this->regions, $this->citymap);
        $this->ajax           = new Geo_Ads_Pro_Ajax($this->regions, $this->citymap);
        $this->analytics      = new Geo_Ads_Pro_Analytics($this->regions);
        $this->abtest         = new Geo_Ads_Pro_ABTest($this->regions);
        $this->shortcode      = new Geo_Ads_Pro_Shortcode($this->regions, $this->citymap);
        $this->rest           = new Geo_Ads_Pro_REST($this->regions, $this->citymap);
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
            '1.0.0'
        );

        wp_enqueue_script(
            'geo-ads-pro-public',
            GAP_PLUGIN_URL . 'public/js/geo-ads-pro.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('geo-ads-pro-public', 'GAP_AJAX', [
            'url' => admin_url('admin-ajax.php')
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
            '1.0.0'
        );

        // Modern UI redesign CSS
        wp_enqueue_style(
            'geo-ads-pro-admin-ui',
            GAP_PLUGIN_URL . 'assets/css/admin-ui.css',
            [],
            '1.0.0'
        );

        // Analytics sayfası için özel CSS + JS
        if ($hook === 'geo-ads-pro_page_geo-ads-pro-analytics') {

            wp_enqueue_style(
                'geo-ads-pro-analytics',
                GAP_PLUGIN_URL . 'assets/css/analytics.css',
                [],
                '1.0.0'
            );

            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js',
                [],
                '4.4.0',
                true
            );

            wp_enqueue_script(
                'geo-ads-pro-analytics',
                GAP_PLUGIN_URL . 'assets/js/analytics.js',
                ['jquery', 'chart-js'],
                '1.0.0',
                true
            );
        }

        // Genel admin JS
        wp_enqueue_script(
            'geo-ads-pro-admin',
            GAP_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            '1.0.0',
            true
        );
    }


}

function GAP() { return Geo_Ads_Pro::instance(); }
GAP();
