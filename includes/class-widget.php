<?php
// Plugin Name: Geo Ads Pro - class-regions.php
// 06062026
// Author: Levent Cetin - 3CCS.com

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_Widget_Manager {

    private $regions;
    private $citymap;

    public function __construct($regions, $citymap) {
        $this->regions = $regions;
        $this->citymap = $citymap;

        add_action('widgets_init', [$this, 'register_widget']);
    }

    public function register_widget() {
        register_widget('Geo_Ads_Pro_Widget');
    }
}

class Geo_Ads_Pro_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'geo_ads_pro_widget',
            __('Geo Ads Pro Widget', 'geo-ads-pro'),
            ['description' => __('Displays region-based banners.', 'geo-ads-pro')]
        );
    }

    public function form($instance) {

        $title  = isset($instance['title'])  ? esc_attr($instance['title'])  : '';
        $mode   = isset($instance['mode'])   ? esc_attr($instance['mode'])   : 'global';
        $region = isset($instance['region']) ? esc_attr($instance['region']) : '';

        $regions = GAP()->regions->get_all();
        ?>

        <p>
            <label><?php esc_html_e('Title:', 'geo-ads-pro'); ?></label>
            <input class="widefat"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                   value="<?php echo $title; ?>">
        </p>

        <p>
            <label><?php esc_html_e('Location Mode:', 'geo-ads-pro'); ?></label>
            <select name="<?php echo esc_attr($this->get_field_name('mode')); ?>">
                <option value="global" <?php selected($mode, 'global'); ?>>Global</option>
                <option value="local"  <?php selected($mode, 'local');  ?>><?php esc_html_e('Local (IP)', 'geo-ads-pro'); ?></option>
            </select>
        </p>

        <p>
            <label><?php esc_html_e('Region in Global Mode:', 'geo-ads-pro'); ?></label>
            <select name="<?php echo esc_attr($this->get_field_name('region')); ?>">
                <option value=""><?php esc_html_e('Select', 'geo-ads-pro'); ?></option>
                <?php foreach ($regions as $r => $data): ?>
                    <option value="<?php echo esc_attr($r); ?>" <?php selected($region, $r); ?>>
                        <?php echo esc_html($r); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <?php
    }

    public function update($new, $old) {

        $instance = [];

        $instance['title']  = sanitize_text_field($new['title'] ?? '');
        $instance['mode']   = in_array($new['mode'], ['global', 'local'], true)
                                ? $new['mode']
                                : 'global';
        $instance['region'] = sanitize_text_field($new['region'] ?? '');

        return $instance;
    }

    public function widget($args, $instance) {

        $title  = isset($instance['title'])  ? esc_html($instance['title'])  : '';
        $mode   = isset($instance['mode'])   ? esc_attr($instance['mode'])   : 'global';
        $region = isset($instance['region']) ? esc_attr($instance['region']) : '';

        echo $args['before_widget'];

        if (!empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }

        $widget_id = esc_attr($this->id);

        echo '<div class="geo-ads-pro-widget"
                 data-widget-id="' . $widget_id . '"
                 data-mode="' . $mode . '"
                 data-region="' . $region . '"></div>';

        echo $args['after_widget'];
    }
}
