<?php
// Plugin Name: Geo Ads Pro - class-admin.php
// 06062026
// Author: Levent Cetin - 3CCS.com

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_Admin {

    private $regions;
    private $citymap;

    public function __construct($regions, $citymap) {
        $this->regions = $regions;
        $this->citymap = $citymap;

        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        add_menu_page(
            'Geo Ads Pro',
            'Geo Ads Pro',
            'manage_options',
            'geo-ads-pro',
            [$this, 'render_page'],
            'dashicons-location-alt',
            60
        );
    }

    public function render_page() {

        if (!current_user_can('manage_options')) {
            wp_die('Yetkin yok.');
        }

        $regions  = $this->regions->get_all();
        $city_map = $this->citymap->get_all();

        $base_dir = gap_upload_base_dir();
        $base_url = gap_upload_base_url();

        // Bölge ekleme
        if (isset($_POST['gap_add_region'])) {

            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gap_add_region_nonce')) {
                wp_die('Geçersiz istek.');
            }

            $region = sanitize_text_field($_POST['gap_region_name'] ?? '');

            if ($region !== '') {
                $this->regions->add_region($region);
                $region_dir = $base_dir . '/' . $region;
                if (!file_exists($region_dir)) wp_mkdir_p($region_dir);
                echo '<div class="updated"><p>Bölge eklendi: ' . esc_html($region) . '</p></div>';
                $regions = $this->regions->get_all();
            }
        }

        // Banner yükleme
        if (isset($_POST['gap_upload_banner']) && !empty($_POST['gap_selected_region'])) {

            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gap_upload_banner_nonce')) {
                wp_die('Geçersiz istek.');
            }

            $region = sanitize_text_field($_POST['gap_selected_region'] ?? '');
            if ($region === '') {
                echo '<div class="error"><p>Bölge seçilmedi.</p></div>';
            } else {

                $region_dir = $base_dir . '/' . $region;
                if (!file_exists($region_dir)) wp_mkdir_p($region_dir);

                if (!empty($_FILES['gap_banner_file']['name'])) {

                    $file = $_FILES['gap_banner_file'];

                    $uploaded = wp_handle_upload($file, [
                        'test_form' => false,
                        'mimes'     => [
                            'jpg|jpeg' => 'image/jpeg',
                            'png'      => 'image/png',
                            'gif'      => 'image/gif',
                            'webp'     => 'image/webp',
                        ]
                    ]);

                    if (!isset($uploaded['error']) && !empty($uploaded['file'])) {

                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        if (!in_array($uploaded['type'], $allowed_types, true)) {
                            @unlink($uploaded['file']);
                            echo '<div class="error"><p>Geçersiz dosya türü.</p></div>';
                        } else {

                            $filename = basename($uploaded['file']);
                            $new_path = $region_dir . '/' . $filename;

                            @rename($uploaded['file'], $new_path);

                            $size = @getimagesize($new_path);
                            $w = $size ? (int) $size[0] : 0;
                            $h = $size ? (int) $size[1] : 0;

                            $id = time() . rand(1000, 9999);

                            $this->regions->add_banner($region, [
                                'id'       => $id,
                                'file'     => $filename,
                                'width'    => $w,
                                'height'   => $h,
                                'selected' => false,
                                'url'      => ''
                            ]);

                            echo '<div class="updated"><p>Banner yüklendi.</p></div>';
                            $regions = $this->regions->get_all();
                        }
                    } else {
                        echo '<div class="error"><p>Yükleme hatası.</p></div>';
                    }
                }
            }
        }

        // Banner seçim + URL kaydetme
        if (isset($_POST['gap_save_selection']) && !empty($_POST['gap_selected_region'])) {

            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gap_save_selection_nonce')) {
                wp_die('Geçersiz istek.');
            }

            $region = sanitize_text_field($_POST['gap_selected_region'] ?? '');
            $selected_ids = isset($_POST['gap_banner_select']) && is_array($_POST['gap_banner_select'])
                ? array_map('sanitize_text_field', $_POST['gap_banner_select'])
                : [];
            $urls = isset($_POST['gap_banner_url']) && is_array($_POST['gap_banner_url'])
                ? $_POST['gap_banner_url']
                : [];

            $region_data = $this->regions->get_region($region);
            $banners = $region_data['banners'] ?? [];

            foreach ($banners as &$banner) {
                $id = (string) $banner['id'];
                $banner['selected'] = in_array($id, $selected_ids, true);
                if (isset($urls[$id])) {
                    $banner['url'] = esc_url_raw($urls[$id]);
                }
            }

            $this->regions->update_banners($region, $banners);
            echo '<div class="updated"><p>Seçimler ve URL’ler kaydedildi.</p></div>';
            $regions = $this->regions->get_all();
        }

        // Şehir → bölge eşleştirme
        if (isset($_POST['gap_add_city_map'])) {

            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gap_add_city_map_nonce')) {
                wp_die('Geçersiz istek.');
            }

            $city = sanitize_text_field($_POST['gap_city_name'] ?? '');
            $region_for_city = sanitize_text_field($_POST['gap_city_region'] ?? '');

            if ($city !== '' && $region_for_city !== '') {
                $this->citymap->map_city($city, $region_for_city);
                echo '<div class="updated"><p>Şehir eşleştirildi: ' . esc_html($city) . ' → ' . esc_html($region_for_city) . '</p></div>';
                $city_map = $this->citymap->get_all();
            }
        }

        $selected_region = sanitize_text_field($_POST['gap_selected_region'] ?? '');

        ?>
        <div class="wrap">
            <h1>Geo Ads Pro – Bölgeler & Banner Yönetimi</h1>

            <h2>Bölge Ekle</h2>
            <form method="post">
                <?php wp_nonce_field('gap_add_region_nonce'); ?>
                <input type="text" name="gap_region_name" placeholder="Örn: NRW, Istanbul, Bayern" required>
                <button class="button button-primary" name="gap_add_region">Ekle</button>
            </form>

            <hr>

            <h2>Bölge Seç</h2>
            <form method="post" enctype="multipart/form-data">
                <select name="gap_selected_region" onchange="this.form.submit()">
                    <option value="">Bölge seçin</option>
                    <?php foreach ($regions as $region => $data): ?>
                        <option value="<?php echo esc_attr($region); ?>" <?php selected($selected_region, $region); ?>>
                            <?php echo esc_html($region); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php
            if ($selected_region !== ''):

                $region = $selected_region;
                $region_dir = $base_dir . '/' . $region;
                $region_url = $base_url . '/' . $region;
                $region_data = $this->regions->get_region($region);
                $banners = $region_data['banners'] ?? [];
            ?>

            <hr>

            <h2><?php echo esc_html($region); ?> Bölgesi – Banner Yükle</h2>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('gap_upload_banner_nonce'); ?>
                <input type="hidden" name="gap_selected_region" value="<?php echo esc_attr($region); ?>">
                <input type="file" name="gap_banner_file" required>
                <button class="button button-primary" name="gap_upload_banner">Yükle</button>
            </form>

            <hr>

            <h2>Banner Listesi</h2>

            <form method="post">
                <?php wp_nonce_field('gap_save_selection_nonce'); ?>
                <input type="hidden" name="gap_selected_region" value="<?php echo esc_attr($region); ?>">

                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Önizleme</th>
                            <th>Boyut</th>
                            <th>Dosya</th>
                            <th>ID</th>
                            <th>Seç</th>
                            <th>Tıklama URL</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($banners)): ?>
                        <?php foreach ($banners as $banner): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo esc_url($region_url . '/' . $banner['file']); ?>" width="120" alt="">
                                </td>
                                <td><?php echo esc_html((int)$banner['width'] . 'x' . (int)$banner['height']); ?></td>
                                <td><?php echo esc_html($banner['file']); ?></td>
                                <td><?php echo esc_html($banner['id']); ?></td>
                                <td>
                                    <input type="checkbox"
                                           name="gap_banner_select[]"
                                           value="<?php echo esc_attr($banner['id']); ?>"
                                           <?php checked(!empty($banner['selected'])); ?>>
                                </td>
                                <td>
                                    <input type="text"
                                           class="widefat"
                                           name="gap_banner_url[<?php echo esc_attr($banner['id']); ?>]"
                                           value="<?php echo esc_attr($banner['url'] ?? ''); ?>"
                                           placeholder="https://...">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6">Bu bölge için henüz banner yok.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <br>
                <button class="button button-primary" name="gap_save_selection">Seçimleri Kaydet</button>
            </form>

            <?php endif; ?>

            <hr>
            <h2>Şehir → Bölge Eşleştirme</h2>

            <form method="post">
                <?php wp_nonce_field('gap_add_city_map_nonce'); ?>
                <input type="text" name="gap_city_name" placeholder="Şehir adı (örn: Düsseldorf)" required>

                <select name="gap_city_region" required>
                    <option value="">Bölge seçin</option>
                    <?php foreach ($regions as $r => $data): ?>
                        <option value="<?php echo esc_attr($r); ?>"><?php echo esc_html($r); ?></option>
                    <?php endforeach; ?>
                </select>

                <button class="button button-primary" name="gap_add_city_map">Ekle</button>
            </form>

            <h3>Mevcut Eşleştirmeler</h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Şehir</th>
                        <th>Bölge</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($city_map)): ?>
                        <?php foreach ($city_map as $city => $region_name): ?>
                            <tr>
                                <td><?php echo esc_html($city); ?></td>
                                <td><?php echo esc_html($region_name); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2">Henüz eşleştirme yok.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

        </div>
        <?php
    }
}
