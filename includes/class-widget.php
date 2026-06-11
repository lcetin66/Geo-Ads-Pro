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

    private function info_icon($text, $key = '') {
        $id = 'gap-tip-' . ($key ? $key : uniqid());
        return sprintf(
            '<span class="gap-tooltip-wrap"><span class="gap-tooltip-icon" data-gap-tip="%s" tabindex="0" role="button" aria-label="%s">i</span><span id="%s" class="gap-tooltip-box"><div class="gap-tooltip-box-inner"><span class="gap-tooltip-box-label">%s</span>%s</div></span></span>',
            esc_attr($id),
            __('Hilfe', 'geo-ads-pro'),
            $id,
            esc_html__('Hilfe', 'geo-ads-pro'),
            esc_html($text)
        );
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
                <?php echo $this->info_icon(__('Optionaler sichtbarer Hinweis über der Anzeige, z. B. Werbung.', 'geo-ads-pro'), 'ad_title'); ?>
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
                <?php echo $this->info_icon(__('Optionaler title-Text für den Anzeigenlink.', 'geo-ads-pro'), 'link_title'); ?>
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
                <?php echo $this->info_icon(__('Blendet Widget-Titel und Anzeigenkennzeichnung aus.', 'geo-ads-pro'), 'show_ad_only'); ?>
            </label>
        </p>

        <p style="margin:0 0 12px;">
            <label>
                <input type="checkbox"
                       name="<?php echo esc_attr($this->get_field_name('new_window')); ?>"
                       value="1" <?php checked($new_window); ?>>
                <?php esc_html_e('Links in einem neuen Fenster öffnen?', 'geo-ads-pro'); ?>
                <?php echo $this->info_icon(__('Öffnet die Anzeigen-URL in einem neuen Browserfenster oder Tab.', 'geo-ads-pro'), 'new_window'); ?>
            </label>
        </p>

        <p style="margin:0 0 14px;">
            <label>
                <input type="checkbox"
                       name="<?php echo esc_attr($this->get_field_name('nofollow')); ?>"
                       value="1" <?php checked($nofollow); ?>>
                <?php esc_html_e('Nofollow? (Link nicht folgen)', 'geo-ads-pro'); ?>
                <?php echo $this->info_icon(__('Fügt rel="nofollow" zum Anzeigenlink hinzu.', 'geo-ads-pro'), 'nofollow'); ?>
            </label>
        </p>

        <p style="margin:0 0 14px;">
            <label for="<?php echo esc_attr($this->get_field_id('image_url')); ?>" style="display:block; font-weight:600; margin:0 0 4px;">
                <?php esc_html_e('Bildpfad:', 'geo-ads-pro'); ?>
                <?php echo $this->info_icon(__('URL zum Anzeigenbild. Wird genutzt, wenn kein Code eingetragen ist.', 'geo-ads-pro'), 'image_url'); ?>
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
                <?php echo $this->info_icon(__('Optionale Bildbreite in Pixeln.', 'geo-ads-pro'), 'image_width'); ?>
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
                <?php echo $this->info_icon(__('Optionale Bildhöhe in Pixeln.', 'geo-ads-pro'), 'image_height'); ?>
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
                <?php echo $this->info_icon(__('Innenabstand der manuellen Anzeige in Pixeln.', 'geo-ads-pro'), 'padding'); ?>
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
                <?php echo $this->info_icon(__('Beschreibt das Anzeigenbild für Barrierefreiheit und SEO.', 'geo-ads-pro'), 'image_alt'); ?>
            </label>
            <input class="widefat"
                   id="<?php echo esc_attr($this->get_field_id('image_alt')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('image_alt')); ?>"
                   value="<?php echo $image_alt; ?>">
        </p>

        <p style="margin:0 0 14px;">
            <label for="<?php echo esc_attr($this->get_field_id('ad_url')); ?>" style="display:block; font-weight:600; margin:0 0 4px;">
                <?php esc_html_e('Anzeigen-URL', 'geo-ads-pro'); ?>
                <?php echo $this->info_icon(__('Zieladresse, die beim Klick auf das Anzeigenbild geöffnet wird.', 'geo-ads-pro'), 'ad_url'); ?>
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
                <?php echo $this->info_icon(__('HTML- oder Embed-Code. Wenn Code eingetragen ist, hat er Vorrang vor dem Bildpfad.', 'geo-ads-pro'), 'ad_code'); ?>
            </label>
            <textarea class="widefat"
                      rows="8"
                      id="<?php echo esc_attr($this->get_field_id('ad_code')); ?>"
                      name="<?php echo esc_attr($this->get_field_name('ad_code')); ?>"><?php echo $ad_code; ?></textarea>
        </p>
        </div>

        <style>
.gap-tooltip-wrap { position: relative; display: inline-block; vertical-align: middle; }

.gap-tooltip-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 16px; height: 16px; border-radius: 50%;
    background: #2271b1; color: #fff; font-size: 11px; font-weight: 700; line-height: 1;
    cursor: pointer; user-select: none; margin-left: 6px;
    transition: all .2s ease; box-shadow: 0 0 0 0 rgba(34,113,177,.5);
}
.gap-tooltip-icon:hover {
    background: #135e96; transform: scale(1.25);
    box-shadow: 0 0 0 4px rgba(34,113,177,.25), 0 0 12px rgba(34,113,177,.35);
}
.gap-tooltip-icon.active {
    background: #d63638; box-shadow: 0 0 0 4px rgba(214,54,56,.2);
    animation: gap-icon-pulse .6s ease;
}
@keyframes gap-icon-pulse {
    0%   { transform: scale(1.25); }
    50%  { transform: scale(.9); }
    100% { transform: scale(1); }
}

.gap-tooltip-box {
    display: none; position: absolute; z-index: 99999; left: -40px; bottom: calc(100% + 8px);
    min-width: 260px; max-width: 340px; padding: 0;
    background: #fff; border: 1px solid #ddd; border-radius: 8px;
    box-shadow: 0 8px 30px rgba(0,0,0,.18);
    opacity: 0; transform: translateY(6px) scale(.97); pointer-events: none;
    transition: opacity .2s ease, transform .2s ease;
    overflow: hidden;
}
.gap-tooltip-box.show {
    opacity: 1; transform: translateY(0) scale(1); pointer-events: auto;
}
.gap-tooltip-box-inner { padding: 14px 18px; font-size: 13.5px; line-height: 1.6; color: #444; }
.gap-tooltip-box-label {
    display: block; margin-bottom: 6px; font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .6px; color: #2271b1;
}
.gap-tooltip-box::after {
    content: ""; position: absolute; top: 100%; left: 24px;
    border: 8px solid transparent; border-top-color: #fff;
}
</style>
<script>
(function(){var active=null;function open(box,icon){if(active&&active!==box)closeActive();box.style.display="block";requestAnimationFrame(function(){box.classList.add("show")});icon.classList.add("active");active=box}function closeAll(){document.querySelectorAll(".gap-tooltip-box.show").forEach(function(b){b.classList.remove("show");setTimeout(function(){b.style.display="none"},200)});document.querySelectorAll(".gap-tooltip-icon.active").forEach(function(i){i.classList.remove("active")});active=null}document.addEventListener("click",function(e){var i=e.target.closest(".gap-tooltip-icon");if(i){var b=document.getElementById(i.getAttribute("data-gap-tip"));if(b)open(b,i);return}if(!e.target.closest(".gap-tooltip-wrap"))closeActive()});function closeActive(){closeAll()}document.addEventListener("keydown",function(e){if(e.key==="Escape")closeActive()})})();
</script>

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
        // Sanitize ad code: allow safe HTML (for Google Adsense etc) but preserve shortcode brackets
        // wp_kses_post strips <script> OK but we also need to allow <ins>, <div>, <span>, etc.
        $allowed = array(
            'a'         => array('href' => true, 'title' => true, 'target' => true, 'rel' => true),
            'img'       => array('src' => true, 'alt' => true, 'width' => true, 'height' => true),
            'div'       => array(),
            'span'      => array(),
            'p'         => array(),
            'br'        => array(),
            'ins'       => array('class' => true, 'style' => true, 'data-ad-slot' => true, 'data-full-width-responsive' => true),
            // <script> tags removed — XSS risk. Use external src for ad scripts.
            'iframe'    => array(
                'src'         => true,
                'width'       => true,
                'height'      => true,
                'frameborder' => true,
                'allow'       => true,
                'loading'     => true,
                'sandbox'     => true, // Restrict iframe capabilities
            ),
        );
        $instance['ad_code'] = wp_kses($new['ad_code'] ?? '', $allowed);

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
            // Process shortcodes in user-submitted ad code
            $ad_code = do_shortcode($ad_code);
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
