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

    private function info_icon($text) {
        return '<span tabindex="0" title="' . esc_attr($text) . '" style="display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;margin-left:6px;border-radius:50%;background:#2271b1;color:#fff;font-size:11px;font-weight:700;line-height:1;cursor:help;">i</span>';
    }

    public function form($instance) {

        $title         = isset($instance['title']) ? esc_attr($instance['title']) : '';
        $ad_title      = isset($instance['ad_title']) ? esc_attr($instance['ad_title']) : '';
        $link_title    = isset($instance['link_title']) ? esc_attr($instance['link_title']) : '';
        $show_ad_only  = !empty($instance['show_ad_only']);
        $new_window    = !empty($instance['new_window']);
        $nofollow      = !empty($instance['nofollow']);
        $image_url     = isset($instance['image_url']) ? esc_url($instance['image_url']) : '';
        $image_width   = isset($instance['image_width']) ? absint($instance['image_width']) : '';
        $image_height  = isset($instance['image_height']) ? absint($instance['image_height']) : '';
        $padding_top   = isset($instance['padding_top']) ? absint($instance['padding_top']) : '';
        $padding_right = isset($instance['padding_right']) ? absint($instance['padding_right']) : '';
        $padding_bottom = isset($instance['padding_bottom']) ? absint($instance['padding_bottom']) : '';
        $padding_left  = isset($instance['padding_left']) ? absint($instance['padding_left']) : '';
        $image_alt     = isset($instance['image_alt']) ? esc_attr($instance['image_alt']) : '';
        $ad_url        = isset($instance['ad_url']) ? esc_url($instance['ad_url']) : '';
        $ad_code       = isset($instance['ad_code']) ? esc_textarea($instance['ad_code']) : '';
        ?>

        <p style="margin:0 0 14px;">
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>" style="display:block; font-weight:600; margin:0 0 4px;">
                <?php esc_html_e('Title:', 'geo-ads-pro'); ?>
            </label>
            <input class="widefat"
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                   value="<?php echo $title; ?>">
        </p>

        <div style="margin:14px 0 0; padding:12px; border:1px solid #dcdcde; border-radius:6px; background:#f8f9fa;">
            <p style="margin:0 0 14px; padding:8px 10px; border-left:4px solid #2271b1; background:#fff;">
                <strong><?php esc_html_e('Hinweis:', 'geo-ads-pro'); ?></strong>
                <?php esc_html_e('Wenn Bildpfad und Code leer sind, läuft das automatische Geo Ads Pro Banner-System weiter.', 'geo-ads-pro'); ?>
            </p>

        <p style="margin:0 0 14px;">
            <label for="<?php echo esc_attr($this->get_field_id('ad_title')); ?>" style="display:block; font-weight:600; margin:0 0 4px;">
                <?php esc_html_e('Anzeigentitel', 'geo-ads-pro'); ?>
                <?php echo $this->info_icon(__('Optionaler sichtbarer Hinweis über der Anzeige, z. B. Werbung.', 'geo-ads-pro')); ?>
            </label>
            <input class="widefat"
                   id="<?php echo esc_attr($this->get_field_id('ad_title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('ad_title')); ?>"
                   value="<?php echo $ad_title; ?>">
            <span style="display:block; margin-top:4px;">
                <?php esc_html_e('Ein Titel für die Anzeige, z. B. Werbung - lassen Sie dieses Feld leer, um die Kennzeichnung zu deaktivieren.', 'geo-ads-pro'); ?>
            </span>
        </p>

        <p style="margin:0 0 14px;">
            <label for="<?php echo esc_attr($this->get_field_id('link_title')); ?>" style="display:block; font-weight:600; margin:0 0 4px;">
                <?php esc_html_e('Link Anzeigentitel', 'geo-ads-pro'); ?>
                <?php echo $this->info_icon(__('Optionaler title-Text für den Anzeigenlink.', 'geo-ads-pro')); ?>
            </label>
            <input class="widefat"
                   id="<?php echo esc_attr($this->get_field_id('link_title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('link_title')); ?>"
                   value="<?php echo $link_title; ?>">
        </p>

        <p style="margin:0 0 12px;">
            <label>
                <input type="checkbox"
                       name="<?php echo esc_attr($this->get_field_name('show_ad_only')); ?>"
                       value="1" <?php checked($show_ad_only); ?>>
                <?php esc_html_e('Nur Werbeanzeige anzeigen?', 'geo-ads-pro'); ?>
                <?php echo $this->info_icon(__('Blendet Widget-Titel und Anzeigenkennzeichnung aus.', 'geo-ads-pro')); ?>
            </label>
        </p>

        <p style="margin:0 0 12px;">
            <label>
                <input type="checkbox"
                       name="<?php echo esc_attr($this->get_field_name('new_window')); ?>"
                       value="1" <?php checked($new_window); ?>>
                <?php esc_html_e('Links in einem neuen Fenster öffnen?', 'geo-ads-pro'); ?>
                <?php echo $this->info_icon(__('Öffnet die Anzeigen-URL in einem neuen Browserfenster oder Tab.', 'geo-ads-pro')); ?>
            </label>
        </p>

        <p style="margin:0 0 14px;">
            <label>
                <input type="checkbox"
                       name="<?php echo esc_attr($this->get_field_name('nofollow')); ?>"
                       value="1" <?php checked($nofollow); ?>>
                <?php esc_html_e('Nofollow? (Link nicht folgen)', 'geo-ads-pro'); ?>
                <?php echo $this->info_icon(__('Fügt rel="nofollow" zum Anzeigenlink hinzu.', 'geo-ads-pro')); ?>
            </label>
        </p>

        <p style="margin:0 0 14px;">
            <label for="<?php echo esc_attr($this->get_field_id('image_url')); ?>" style="display:block; font-weight:600; margin:0 0 4px;">
                <?php esc_html_e('Bildpfad:', 'geo-ads-pro'); ?>
                <?php echo $this->info_icon(__('URL zum Anzeigenbild. Wird genutzt, wenn kein Code eingetragen ist.', 'geo-ads-pro')); ?>
            </label>
            <input class="widefat"
                   id="<?php echo esc_attr($this->get_field_id('image_url')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('image_url')); ?>"
                   value="<?php echo $image_url; ?>"
                   placeholder="https://">
        </p>

        <p style="margin:0 0 14px; display:flex; align-items:center; gap:10px;">
            <label for="<?php echo esc_attr($this->get_field_id('image_width')); ?>" style="min-width:180px; font-weight:600;">
                <?php esc_html_e('Bild Breite hinzufügen', 'geo-ads-pro'); ?>
                <?php echo $this->info_icon(__('Optionale Bildbreite in Pixeln.', 'geo-ads-pro')); ?>
            </label>
            <input type="number"
                   min="0"
                   step="1"
                   id="<?php echo esc_attr($this->get_field_id('image_width')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('image_width')); ?>"
                   value="<?php echo esc_attr($image_width); ?>"
                   style="width:90px;">
        </p>

        <p style="margin:0 0 14px; display:flex; align-items:center; gap:10px;">
            <label for="<?php echo esc_attr($this->get_field_id('image_height')); ?>" style="min-width:180px; font-weight:600;">
                <?php esc_html_e('Bildhöhe hinzufügen', 'geo-ads-pro'); ?>
                <?php echo $this->info_icon(__('Optionale Bildhöhe in Pixeln.', 'geo-ads-pro')); ?>
            </label>
            <input type="number"
                   min="0"
                   step="1"
                   id="<?php echo esc_attr($this->get_field_id('image_height')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('image_height')); ?>"
                   value="<?php echo esc_attr($image_height); ?>"
                   style="width:90px;">
        </p>

        <div style="margin:0 0 14px;">
            <div style="font-weight:600; margin:0 0 8px;">
                <?php esc_html_e('Padding', 'geo-ads-pro'); ?>
                <?php echo $this->info_icon(__('Innenabstand der manuellen Anzeige in Pixeln.', 'geo-ads-pro')); ?>
            </div>
            <div style="display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:8px;">
                <label style="display:block; font-weight:600;">
                    <?php esc_html_e('Oben', 'geo-ads-pro'); ?>
                    <input type="number"
                           min="0"
                           step="1"
                           name="<?php echo esc_attr($this->get_field_name('padding_top')); ?>"
                           value="<?php echo esc_attr($padding_top); ?>"
                           style="width:100%; margin-top:4px;">
                </label>
                <label style="display:block; font-weight:600;">
                    <?php esc_html_e('Rechts', 'geo-ads-pro'); ?>
                    <input type="number"
                           min="0"
                           step="1"
                           name="<?php echo esc_attr($this->get_field_name('padding_right')); ?>"
                           value="<?php echo esc_attr($padding_right); ?>"
                           style="width:100%; margin-top:4px;">
                </label>
                <label style="display:block; font-weight:600;">
                    <?php esc_html_e('Unten', 'geo-ads-pro'); ?>
                    <input type="number"
                           min="0"
                           step="1"
                           name="<?php echo esc_attr($this->get_field_name('padding_bottom')); ?>"
                           value="<?php echo esc_attr($padding_bottom); ?>"
                           style="width:100%; margin-top:4px;">
                </label>
                <label style="display:block; font-weight:600;">
                    <?php esc_html_e('Links', 'geo-ads-pro'); ?>
                    <input type="number"
                           min="0"
                           step="1"
                           name="<?php echo esc_attr($this->get_field_name('padding_left')); ?>"
                           value="<?php echo esc_attr($padding_left); ?>"
                           style="width:100%; margin-top:4px;">
                </label>
            </div>
        </div>

        <p style="margin:0 0 14px;">
            <label for="<?php echo esc_attr($this->get_field_id('image_alt')); ?>" style="display:block; font-weight:600; margin:0 0 4px;">
                <?php esc_html_e('Alternativer Text für das Bild', 'geo-ads-pro'); ?>
                <?php echo $this->info_icon(__('Beschreibt das Anzeigenbild für Barrierefreiheit und SEO.', 'geo-ads-pro')); ?>
            </label>
            <input class="widefat"
                   id="<?php echo esc_attr($this->get_field_id('image_alt')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('image_alt')); ?>"
                   value="<?php echo $image_alt; ?>">
        </p>

        <p style="margin:0 0 14px;">
            <label for="<?php echo esc_attr($this->get_field_id('ad_url')); ?>" style="display:block; font-weight:600; margin:0 0 4px;">
                <?php esc_html_e('Anzeigen-URL', 'geo-ads-pro'); ?>
                <?php echo $this->info_icon(__('Zieladresse, die beim Klick auf das Anzeigenbild geöffnet wird.', 'geo-ads-pro')); ?>
            </label>
            <input class="widefat"
                   id="<?php echo esc_attr($this->get_field_id('ad_url')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('ad_url')); ?>"
                   value="<?php echo $ad_url; ?>"
                   placeholder="https://">
        </p>

        <p style="margin:0;">
            <label for="<?php echo esc_attr($this->get_field_id('ad_code')); ?>" style="display:block; font-weight:600; margin:0 0 4px;">
                <?php esc_html_e('- ODER - Code:', 'geo-ads-pro'); ?>
                <?php echo $this->info_icon(__('HTML- oder Embed-Code. Wenn Code eingetragen ist, hat er Vorrang vor dem Bildpfad.', 'geo-ads-pro')); ?>
            </label>
            <textarea class="widefat"
                      rows="8"
                      id="<?php echo esc_attr($this->get_field_id('ad_code')); ?>"
                      name="<?php echo esc_attr($this->get_field_name('ad_code')); ?>"><?php echo $ad_code; ?></textarea>
        </p>
        </div>

        <?php
    }

    public function update($new, $old) {

        $instance = [];

        $instance['title'] = sanitize_text_field($new['title'] ?? '');
        $instance['ad_title'] = sanitize_text_field($new['ad_title'] ?? '');
        $instance['link_title'] = sanitize_text_field($new['link_title'] ?? '');
        $instance['show_ad_only'] = !empty($new['show_ad_only']) ? 1 : 0;
        $instance['new_window'] = !empty($new['new_window']) ? 1 : 0;
        $instance['nofollow'] = !empty($new['nofollow']) ? 1 : 0;
        $instance['image_url'] = esc_url_raw($new['image_url'] ?? '');
        $instance['image_width'] = absint($new['image_width'] ?? 0);
        $instance['image_height'] = absint($new['image_height'] ?? 0);
        $instance['padding_top'] = absint($new['padding_top'] ?? 0);
        $instance['padding_right'] = absint($new['padding_right'] ?? 0);
        $instance['padding_bottom'] = absint($new['padding_bottom'] ?? 0);
        $instance['padding_left'] = absint($new['padding_left'] ?? 0);
        $instance['image_alt'] = sanitize_text_field($new['image_alt'] ?? '');
        $instance['ad_url'] = esc_url_raw($new['ad_url'] ?? '');
        $instance['ad_code'] = wp_kses_post($new['ad_code'] ?? '');

        $default_mode   = get_option('gap_enable_local_mode') ? 'local' : 'global';
        $default_region = sanitize_text_field(get_option('gap_default_region', ''));

        $legacy_mode   = $old['mode'] ?? $default_mode;
        $legacy_region = $old['region'] ?? $default_region;

        $instance['mode'] = in_array($new['mode'] ?? $legacy_mode, ['global', 'local'], true)
            ? ($new['mode'] ?? $legacy_mode)
            : $default_mode;

        $instance['region'] = sanitize_text_field($new['region'] ?? $legacy_region);

        return $instance;
    }

    private function has_manual_ad($instance) {
        return !empty($instance['ad_code']) || !empty($instance['image_url']);
    }

    private function render_manual_ad($instance) {
        $ad_title     = sanitize_text_field($instance['ad_title'] ?? '');
        $link_title   = sanitize_text_field($instance['link_title'] ?? '');
        $show_ad_only = !empty($instance['show_ad_only']);
        $new_window   = !empty($instance['new_window']);
        $nofollow     = !empty($instance['nofollow']);
        $image_url    = esc_url($instance['image_url'] ?? '');
        $image_width  = absint($instance['image_width'] ?? 0);
        $image_height = absint($instance['image_height'] ?? 0);
        $padding_top = absint($instance['padding_top'] ?? 0);
        $padding_right = absint($instance['padding_right'] ?? 0);
        $padding_bottom = absint($instance['padding_bottom'] ?? 0);
        $padding_left = absint($instance['padding_left'] ?? 0);
        $image_alt    = esc_attr($instance['image_alt'] ?? '');
        $ad_url       = esc_url($instance['ad_url'] ?? '');
        $ad_code      = wp_kses_post($instance['ad_code'] ?? '');

        $rel = [];
        if ($new_window) {
            $rel[] = 'noopener';
            $rel[] = 'noreferrer';
        }
        if ($nofollow) {
            $rel[] = 'nofollow';
        }

        $extra_style = '';
        if ($padding_top > 0 || $padding_right > 0 || $padding_bottom > 0 || $padding_left > 0) {
            $extra_style .= sprintf(
                'padding:%dpx %dpx %dpx %dpx;',
                $padding_top,
                $padding_right,
                $padding_bottom,
                $padding_left
            );
        }

        echo '<div class="geo-ads-pro-widget gap-manual-ad"' . ($extra_style !== '' ? ' style="' . esc_attr($extra_style) . '"' : '') . '>';

        if (!$show_ad_only && $ad_title !== '') {
            echo '<div class="gap-manual-ad-title">' . esc_html($ad_title) . '</div>';
        }

        if ($ad_code !== '') {
            echo '<div class="gap-manual-ad-code">' . $ad_code . '</div>';
            echo '</div>';
            return;
        }

        if ($image_url === '') {
            echo '</div>';
            return;
        }

        $image_attrs = [
            'src' => $image_url,
            'alt' => $image_alt,
        ];

        if ($image_width > 0) {
            $image_attrs['width'] = $image_width;
        }

        if ($image_height > 0) {
            $image_attrs['height'] = $image_height;
        }

        $image_html = '<img';
        foreach ($image_attrs as $name => $value) {
            $image_html .= ' ' . $name . '="' . esc_attr($value) . '"';
        }
        $image_html .= '>';

        if ($ad_url !== '') {
            echo '<a class="gap-manual-ad-link" href="' . $ad_url . '"'
                . ($new_window ? ' target="_blank"' : '')
                . (!empty($rel) ? ' rel="' . esc_attr(implode(' ', $rel)) . '"' : '')
                . ($link_title !== '' ? ' title="' . esc_attr($link_title) . '"' : '')
                . '>' . $image_html . '</a>';
        } else {
            echo $image_html;
        }

        echo '</div>';
    }

    public function widget($args, $instance) {

        $title      = isset($instance['title']) ? esc_html($instance['title']) : '';
        $mode       = isset($instance['mode']) ? esc_attr($instance['mode']) : (get_option('gap_enable_local_mode') ? 'local' : 'global');
        $region     = isset($instance['region']) ? esc_attr($instance['region']) : esc_attr(get_option('gap_default_region', ''));

        echo $args['before_widget'];

        if (empty($instance['show_ad_only']) && !empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }

        if ($this->has_manual_ad($instance)) {
            $this->render_manual_ad($instance);
            echo $args['after_widget'];
            return;
        }

        $widget_id = esc_attr($this->id);

        echo '<div class="geo-ads-pro-widget"
                 data-widget-id="' . $widget_id . '"
                 data-mode="' . $mode . '"
                 data-region="' . $region . '">
                <div class="gap-widget-content"></div>
              </div>';

        echo $args['after_widget'];
    }
}
