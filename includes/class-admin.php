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

    private function region_exists($region, $regions = null) {
        $regions = is_array($regions) ? $regions : $this->regions->get_all();
        return $region !== '' && array_key_exists($region, $regions);
    }

    private function delete_region_directory($region, $base_dir) {
        $base_real = realpath($base_dir);
        $region_dir = trailingslashit($base_dir) . gap_region_folder_name($region);
        $region_real = realpath($region_dir);

        if (!$base_real || !$region_real || strpos($region_real, $base_real . DIRECTORY_SEPARATOR) !== 0) {
            return false;
        }

        $iterator = new RecursiveDirectoryIterator($region_real, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        }

        return @rmdir($region_real);
    }

    public function render_page() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'geo-ads-pro'));
        }

        $regions  = $this->regions->get_all();
        $city_map = $this->citymap->get_all();

        $base_dir = gap_upload_base_dir();
        $base_url = gap_upload_base_url();

        // Bölge silme
        if (isset($_POST['gap_delete_region'])) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gap_delete_region_nonce')) {
                wp_die(esc_html__('Invalid request.', 'geo-ads-pro'));
            }
            $region = sanitize_text_field($_POST['gap_selected_region'] ?? '');
            if ($this->region_exists($region, $regions)) {
                $this->delete_region_directory($region, $base_dir);
                $this->regions->delete_region($region);
                $this->citymap->remove_region_mappings($region);
                GAP()->analytics->delete_region($region);
                echo '<div class="updated"><p>' . esc_html(sprintf(__('Region deleted: %s', 'geo-ads-pro'), $region)) . '</p></div>';
                $regions = $this->regions->get_all();
                $city_map = $this->citymap->get_all();
                $_POST['gap_selected_region'] = '';
            } else {
                echo '<div class="error"><p>' . esc_html__('Region not found.', 'geo-ads-pro') . '</p></div>';
            }
        }

        // Banner silme
        if (isset($_POST['gap_delete_banner'])) {
            if (!isset($_POST['gap_delete_banner_nonce']) || !wp_verify_nonce($_POST['gap_delete_banner_nonce'], 'gap_delete_banner_nonce')) {
                wp_die(esc_html__('Invalid request.', 'geo-ads-pro'));
            }
            $region = sanitize_text_field($_POST['gap_selected_region'] ?? '');
            $banner_id = intval($_POST['gap_delete_banner_id'] ?? 0);
            
            if ($this->region_exists($region, $regions) && $banner_id > 0) {
                $region_data = $this->regions->get_region($region);
                $banners = $region_data['banners'] ?? [];
                foreach ($banners as $b) {
                    if ($b['id'] == $banner_id) {
                        $banner_file = trailingslashit($base_dir) . gap_region_folder_name($region) . '/' . basename($b['file']);
                        $banner_real = realpath($banner_file);
                        $region_real = realpath(trailingslashit($base_dir) . gap_region_folder_name($region));

                        if ($banner_real && $region_real && strpos($banner_real, $region_real . DIRECTORY_SEPARATOR) === 0) {
                            @unlink($banner_real);
                        }
                        break;
                    }
                }
                $this->regions->delete_banner($region, $banner_id);
                GAP()->analytics->delete_banner($banner_id);
                echo '<div class="updated"><p>' . esc_html__('Banner deleted.', 'geo-ads-pro') . '</p></div>';
                $regions = $this->regions->get_all();
            } else {
                echo '<div class="error"><p>' . esc_html__('Banner could not be deleted.', 'geo-ads-pro') . '</p></div>';
            }
        }

        // Bölge ekleme
        if (isset($_POST['gap_add_region'])) {

            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gap_add_region_nonce')) {
                wp_die(esc_html__('Invalid request.', 'geo-ads-pro'));
            }

            $region = sanitize_text_field($_POST['gap_region_name'] ?? '');

            if ($region !== '') {
                $this->regions->add_region($region);
                $region_dir = trailingslashit($base_dir) . gap_region_folder_name($region);
                if (!file_exists($region_dir)) wp_mkdir_p($region_dir);
                echo '<div class="updated"><p>' . esc_html(sprintf(__('Region added: %s', 'geo-ads-pro'), $region)) . '</p></div>';
                $regions = $this->regions->get_all();
            }
        }

        // Banner yükleme
        if (isset($_POST['gap_upload_banner']) && !empty($_POST['gap_selected_region'])) {

            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gap_upload_banner_nonce')) {
                wp_die(esc_html__('Invalid request.', 'geo-ads-pro'));
            }

            $region = sanitize_text_field($_POST['gap_selected_region'] ?? '');
            if (!$this->region_exists($region, $regions)) {
                echo '<div class="error"><p>' . esc_html__('No region selected.', 'geo-ads-pro') . '</p></div>';
            } else {

                $region_dir = trailingslashit($base_dir) . gap_region_folder_name($region);
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
                            echo '<div class="error"><p>' . esc_html__('Invalid file type.', 'geo-ads-pro') . '</p></div>';
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

                            echo '<div class="updated"><p>' . esc_html__('Banner uploaded.', 'geo-ads-pro') . '</p></div>';
                            $regions = $this->regions->get_all();
                        }
                    } else {
                        echo '<div class="error"><p>' . esc_html__('Upload error.', 'geo-ads-pro') . '</p></div>';
                    }
                }
            }
        }

        // Banner seçim + URL kaydetme
        if (isset($_POST['gap_save_selection']) && !empty($_POST['gap_selected_region'])) {

            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gap_save_selection_nonce')) {
                wp_die(esc_html__('Invalid request.', 'geo-ads-pro'));
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
                    $banner['url'] = gap_validate_click_url($urls[$id]);
                }
            }

            $this->regions->update_banners($region, $banners);
            echo '<div class="updated"><p>' . esc_html__('Selections and URLs saved.', 'geo-ads-pro') . '</p></div>';
            $regions = $this->regions->get_all();
        }

        // Şehir → bölge eşleştirme
        if (isset($_POST['gap_update_city_map'])) {

            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gap_update_city_map_nonce')) {
                wp_die(esc_html__('Invalid request.', 'geo-ads-pro'));
            }

            $city = sanitize_text_field($_POST['gap_city_name'] ?? '');
            $region_for_city = sanitize_text_field($_POST['gap_city_region'] ?? '');

            if ($city !== '' && $this->region_exists($region_for_city, $regions)) {
                $this->citymap->map_city($city, $region_for_city);
                echo '<div class="updated"><p>' . esc_html(sprintf(__('City mapping updated: %s', 'geo-ads-pro'), $city)) . '</p></div>';
                $city_map = $this->citymap->get_all();
            } else {
                echo '<div class="error"><p>' . esc_html__('City mapping could not be updated.', 'geo-ads-pro') . '</p></div>';
            }
        }

        if (isset($_POST['gap_delete_city_map'])) {

            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gap_delete_city_map_nonce')) {
                wp_die(esc_html__('Invalid request.', 'geo-ads-pro'));
            }

            $city = sanitize_text_field($_POST['gap_city_name'] ?? '');

            if ($city !== '') {
                $this->citymap->delete_city($city);
                echo '<div class="updated"><p>' . esc_html(sprintf(__('City mapping deleted: %s', 'geo-ads-pro'), $city)) . '</p></div>';
                $city_map = $this->citymap->get_all();
            }
        }

        if (isset($_POST['gap_add_city_map'])) {

            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gap_add_city_map_nonce')) {
                wp_die(esc_html__('Invalid request.', 'geo-ads-pro'));
            }

            $city = sanitize_text_field($_POST['gap_city_name'] ?? '');
            $region_for_city = sanitize_text_field($_POST['gap_city_region'] ?? '');

            if ($city !== '' && $this->region_exists($region_for_city, $regions)) {
                $this->citymap->map_city($city, $region_for_city);
                echo '<div class="updated"><p>' . esc_html(sprintf(__('City mapped: %1$s → %2$s', 'geo-ads-pro'), $city, $region_for_city)) . '</p></div>';
                $city_map = $this->citymap->get_all();
            }
        }

        $selected_region = sanitize_text_field($_POST['gap_selected_region'] ?? '');

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Geo Ads Pro – Regions & Banner Management', 'geo-ads-pro'); ?></h1>

            <h2><?php esc_html_e('Add Region', 'geo-ads-pro'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('gap_add_region_nonce'); ?>
                <input type="text" name="gap_region_name" placeholder="<?php echo esc_attr__('Example: NRW, Istanbul, Bayern', 'geo-ads-pro'); ?>" required>
                <button class="button button-primary" name="gap_add_region"><?php esc_html_e('Add', 'geo-ads-pro'); ?></button>
            </form>

            <hr>

            <h2><?php esc_html_e('Select Region', 'geo-ads-pro'); ?></h2>
            <form method="post" enctype="multipart/form-data" style="display:inline-block; margin-bottom:15px;">
                <select name="gap_selected_region" onchange="this.form.submit()">
                    <option value=""><?php esc_html_e('Select a region', 'geo-ads-pro'); ?></option>
                    <?php foreach ($regions as $region => $data): ?>
                        <option value="<?php echo esc_attr($region); ?>" <?php selected($selected_region, $region); ?>>
                            <?php echo esc_html($region); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if ($selected_region !== ''): ?>
                <form method="post" style="display:inline-block; margin-left: 10px;" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to delete this region and all its banners?', 'geo-ads-pro')); ?>')">
                    <?php wp_nonce_field('gap_delete_region_nonce'); ?>
                    <input type="hidden" name="gap_selected_region" value="<?php echo esc_attr($selected_region); ?>">
                    <button class="button button-link-delete" name="gap_delete_region" style="color: #bc0b0b; cursor: pointer;"><?php esc_html_e('Delete This Region', 'geo-ads-pro'); ?></button>
                </form>
            <?php endif; ?>

            <?php
            if ($selected_region !== ''):

                $region = $selected_region;
                $region_dir = trailingslashit($base_dir) . gap_region_folder_name($region);
                $region_url = trailingslashit($base_url) . rawurlencode(gap_region_folder_name($region));
                $region_data = $this->regions->get_region($region);
                $banners = $region_data['banners'] ?? [];
            ?>

            <hr>

            <h2><?php echo esc_html(sprintf(__('%s Region – Upload Banner', 'geo-ads-pro'), $region)); ?></h2>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('gap_upload_banner_nonce'); ?>
                <input type="hidden" name="gap_selected_region" value="<?php echo esc_attr($region); ?>">
                <input type="file" name="gap_banner_file" required>
                <button class="button button-primary" name="gap_upload_banner"><?php esc_html_e('Upload', 'geo-ads-pro'); ?></button>
            </form>

            <hr>

            <h2><?php esc_html_e('Banner List', 'geo-ads-pro'); ?></h2>

            <form method="post">
                <?php wp_nonce_field('gap_save_selection_nonce'); ?>
                <input type="hidden" name="gap_selected_region" value="<?php echo esc_attr($region); ?>">

                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Preview', 'geo-ads-pro'); ?></th>
                            <th><?php esc_html_e('Size', 'geo-ads-pro'); ?></th>
                            <th><?php esc_html_e('File', 'geo-ads-pro'); ?></th>
                            <th>ID</th>
                            <th><?php esc_html_e('Select', 'geo-ads-pro'); ?></th>
                            <th><?php esc_html_e('Click URL', 'geo-ads-pro'); ?></th>
                            <th><?php esc_html_e('Action', 'geo-ads-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($banners)): ?>
                        <?php foreach ($banners as $banner): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo esc_url($region_url . '/' . rawurlencode(basename($banner['file']))); ?>" width="120" alt="">
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
                                <td>
                                    <button type="submit"
                                            name="gap_delete_banner"
                                            value="1"
                                            class="button button-link-delete"
                                            style="color: #bc0b0b; cursor: pointer;"
                                            onclick="if(confirm('<?php echo esc_js(__('Are you sure you want to delete this banner?', 'geo-ads-pro')); ?>')) { jQuery('#gap_delete_banner_id').val('<?php echo esc_attr($banner['id']); ?>'); return true; } return false;">
                                        <?php esc_html_e('Delete', 'geo-ads-pro'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7"><?php esc_html_e('There are no banners for this region yet.', 'geo-ads-pro'); ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <input type="hidden" name="gap_delete_banner_id" id="gap_delete_banner_id" value="0">
                <input type="hidden" name="gap_delete_banner_nonce" value="<?php echo esc_attr(wp_create_nonce('gap_delete_banner_nonce')); ?>">

                <br>
                <button class="button button-primary" name="gap_save_selection"><?php esc_html_e('Save Selections', 'geo-ads-pro'); ?></button>
            </form>

            <?php endif; ?>

            <hr>
            <h2><?php esc_html_e('City → Region Mapping', 'geo-ads-pro'); ?></h2>

            <form method="post">
                <?php wp_nonce_field('gap_add_city_map_nonce'); ?>
                <input type="text" name="gap_city_name" placeholder="<?php echo esc_attr__('City name (example: Düsseldorf)', 'geo-ads-pro'); ?>" required>

                <select name="gap_city_region" required>
                    <option value=""><?php esc_html_e('Select a region', 'geo-ads-pro'); ?></option>
                    <?php foreach ($regions as $r => $data): ?>
                        <option value="<?php echo esc_attr($r); ?>"><?php echo esc_html($r); ?></option>
                    <?php endforeach; ?>
                </select>

                <button class="button button-primary" name="gap_add_city_map"><?php esc_html_e('Add', 'geo-ads-pro'); ?></button>
            </form>

            <h3><?php esc_html_e('Current Mappings', 'geo-ads-pro'); ?></h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('City', 'geo-ads-pro'); ?></th>
                        <th><?php esc_html_e('Region', 'geo-ads-pro'); ?></th>
                        <th><?php esc_html_e('Action', 'geo-ads-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($city_map)): ?>
                        <?php foreach ($city_map as $city => $region_name): ?>
                            <tr>
                                <td><?php echo esc_html($city); ?></td>
                                <td>
                                    <form method="post" style="display:flex; gap:8px; align-items:center;">
                                        <?php wp_nonce_field('gap_update_city_map_nonce'); ?>
                                        <input type="hidden" name="gap_city_name" value="<?php echo esc_attr($city); ?>">
                                        <select name="gap_city_region">
                                            <?php foreach ($regions as $r => $data): ?>
                                                <option value="<?php echo esc_attr($r); ?>" <?php selected($region_name, $r); ?>>
                                                    <?php echo esc_html($r); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="button" name="gap_update_city_map"><?php esc_html_e('Update', 'geo-ads-pro'); ?></button>
                                    </form>
                                </td>
                                <td>
                                    <form method="post" onsubmit="return confirm('<?php echo esc_js(__('Delete this city mapping?', 'geo-ads-pro')); ?>')">
                                        <?php wp_nonce_field('gap_delete_city_map_nonce'); ?>
                                        <input type="hidden" name="gap_city_name" value="<?php echo esc_attr($city); ?>">
                                        <button class="button button-link-delete" name="gap_delete_city_map" style="color:#bc0b0b;"><?php esc_html_e('Delete', 'geo-ads-pro'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3"><?php esc_html_e('There are no mappings yet.', 'geo-ads-pro'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

        </div>
        <?php
    }
}
