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

        add_action('admin_menu', [$this, 'register_menu'], 9);
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

        add_submenu_page(
            'geo-ads-pro',
            __('Upload / Edit Banners', 'geo-ads-pro'),
            __('Upload / Edit Banners', 'geo-ads-pro'),
            'manage_options',
            'geo-ads-pro',
            [$this, 'render_page']
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

    private function normalize_uploaded_files($field) {
        if (empty($_FILES[$field]['name'])) {
            return [];
        }

        if (!is_array($_FILES[$field]['name'])) {
            return [$_FILES[$field]];
        }

        $files = [];
        foreach ($_FILES[$field]['name'] as $index => $name) {
            if ($name === '') {
                continue;
            }

            $files[] = [
                'name' => $name,
                'type' => $_FILES[$field]['type'][$index] ?? '',
                'tmp_name' => $_FILES[$field]['tmp_name'][$index] ?? '',
                'error' => $_FILES[$field]['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $_FILES[$field]['size'][$index] ?? 0,
            ];
        }

        return $files;
    }

    private function handle_banner_upload($file, $region, $region_dir, $customer_email = '') {
        $uploaded = wp_handle_upload($file, [
            'test_form' => false,
            'mimes'     => [
                'jpg|jpeg' => 'image/jpeg',
                'png'      => 'image/png',
                'gif'      => 'image/gif',
                'webp'     => 'image/webp',
            ]
        ]);

        if (isset($uploaded['error']) || empty($uploaded['file'])) {
            return new WP_Error('gap_upload_error', __('Upload error.', 'geo-ads-pro'));
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($uploaded['type'], $allowed_types, true)) {
            @unlink($uploaded['file']);
            return new WP_Error('gap_invalid_file_type', __('Invalid file type.', 'geo-ads-pro'));
        }

        $filename = basename($uploaded['file']);
        $new_path = trailingslashit($region_dir) . $filename;

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
            'url'      => '',
            'customer_email' => gap_sanitize_customer_email($customer_email)
        ]);

        return true;
    }

    public function render_page() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'geo-ads-pro'));
        }

        $regions = $this->regions->get_all();

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
                $coords = gap_geocode_region_center($region);
                $this->regions->add_region($region, [
                    'latitude'  => $coords['latitude'] ?? 0,
                    'longitude' => $coords['longitude'] ?? 0,
                    'radius_km' => $_POST['gap_region_radius_km'] ?? 0,
                ]);
                $region_dir = trailingslashit($base_dir) . gap_region_folder_name($region);
                if (!file_exists($region_dir)) wp_mkdir_p($region_dir);
                echo '<div class="updated"><p>' . esc_html(sprintf(__('Region added: %s', 'geo-ads-pro'), $region)) . '</p></div>';
                $regions = $this->regions->get_all();
                $_POST['gap_selected_region'] = $region;
            }
        }

        // Bölge radius ayarları
        if (isset($_POST['gap_save_region_targeting']) && !empty($_POST['gap_selected_region'])) {

            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gap_region_targeting_nonce')) {
                wp_die(esc_html__('Invalid request.', 'geo-ads-pro'));
            }

            $region = sanitize_text_field($_POST['gap_selected_region'] ?? '');
            if ($this->region_exists($region, $regions)) {
                $region_data = $this->regions->get_region($region);
                $coords = gap_geocode_region_center($region);
                $this->regions->update_region_targeting($region, [
                    'latitude'  => $coords['latitude'] ?? ($region_data['latitude'] ?? 0),
                    'longitude' => $coords['longitude'] ?? ($region_data['longitude'] ?? 0),
                    'radius_km' => $_POST['gap_region_radius_km'] ?? 0,
                ]);
                echo '<div class="updated"><p>' . esc_html__('Region targeting saved.', 'geo-ads-pro') . '</p></div>';
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

                $files = $this->normalize_uploaded_files('gap_banner_file');
                if (!empty($files)) {
                    $uploaded_count = 0;
                    $errors = [];
                    $customer_email = gap_sanitize_customer_email($_POST['gap_customer_email'] ?? '');

                    foreach ($files as $file) {
                        $result = $this->handle_banner_upload($file, $region, $region_dir, $customer_email);
                        if (is_wp_error($result)) {
                            $errors[] = $file['name'] . ': ' . $result->get_error_message();
                        } else {
                            $uploaded_count++;
                        }
                    }

                    if ($uploaded_count > 0) {
                        echo '<div class="updated"><p>' . esc_html(sprintf(_n('%d banner uploaded.', '%d banners uploaded.', $uploaded_count, 'geo-ads-pro'), $uploaded_count)) . '</p></div>';
                        $regions = $this->regions->get_all();
                    }

                    foreach ($errors as $error) {
                        echo '<div class="error"><p>' . esc_html($error) . '</p></div>';
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
            $emails = isset($_POST['gap_banner_email']) && is_array($_POST['gap_banner_email'])
                ? $_POST['gap_banner_email']
                : [];

            $region_data = $this->regions->get_region($region);
            $banners = $region_data['banners'] ?? [];

            foreach ($banners as &$banner) {
                $id = (string) $banner['id'];
                $banner['selected'] = in_array($id, $selected_ids, true);
                if (isset($urls[$id])) {
                    $banner['url'] = gap_validate_click_url($urls[$id]);
                }
                if (isset($emails[$id])) {
                    $banner['customer_email'] = gap_sanitize_customer_email($emails[$id]);
                }
            }

            $this->regions->update_banners($region, $banners);
            echo '<div class="updated"><p>' . esc_html__('Selections, URLs, and customer emails saved.', 'geo-ads-pro') . '</p></div>';
            $regions = $this->regions->get_all();
        }

        $selected_region = sanitize_text_field($_POST['gap_selected_region'] ?? '');

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Geo Ads Pro – Regions & Banner Management', 'geo-ads-pro'); ?></h1>

            <h2><?php esc_html_e('Add Region', 'geo-ads-pro'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('gap_add_region_nonce'); ?>
                <input type="text" name="gap_region_name" placeholder="<?php echo esc_attr__('Example: NRW, Istanbul, Bayern', 'geo-ads-pro'); ?>" required>
                <input type="number" min="0" step="0.1" name="gap_region_radius_km" placeholder="<?php echo esc_attr__('Radius km', 'geo-ads-pro'); ?>">
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

            <h2><?php esc_html_e('Region Radius Targeting', 'geo-ads-pro'); ?></h2>
            <form method="post" style="max-width: 760px;">
                <?php wp_nonce_field('gap_region_targeting_nonce'); ?>
                <input type="hidden" name="gap_selected_region" value="<?php echo esc_attr($region); ?>">
                <div style="display:grid; grid-template-columns:minmax(0, 1fr); gap:12px; margin-bottom:12px;">
                    <label style="display:block; font-weight:600;">
                        <?php esc_html_e('Radius km', 'geo-ads-pro'); ?>
                        <input class="widefat" type="number" min="0" step="0.1" name="gap_region_radius_km" value="<?php echo esc_attr($region_data['radius_km'] ?? ''); ?>">
                    </label>
                </div>
                <p class="description">
                    <?php
                    printf(
                        esc_html__('Used when Settings → Local Targeting Method is set to radius matching. Coordinates are resolved automatically from the region name. Current center: %1$s, %2$s', 'geo-ads-pro'),
                        esc_html($region_data['latitude'] ?? '-'),
                        esc_html($region_data['longitude'] ?? '-')
                    );
                    ?>
                </p>
                <button class="button button-secondary" name="gap_save_region_targeting"><?php esc_html_e('Save Region Targeting', 'geo-ads-pro'); ?></button>
            </form>

            <hr>

            <h2><?php echo esc_html(sprintf(__('%s Region – Upload Banner', 'geo-ads-pro'), $region)); ?></h2>

            <form method="post" enctype="multipart/form-data" class="gap-upload-form">
                <?php wp_nonce_field('gap_upload_banner_nonce'); ?>
                <input type="hidden" name="gap_selected_region" value="<?php echo esc_attr($region); ?>">
                <div style="margin: 0 0 12px 0;">
                    <label for="gap_customer_email" style="display:block; font-weight:600; margin-bottom:6px;">
                        <?php esc_html_e('Customer Email', 'geo-ads-pro'); ?>
                    </label>
                    <input type="email"
                           id="gap_customer_email"
                           name="gap_customer_email"
                           class="widefat"
                           placeholder="customer@example.com">
                    <p class="description" style="margin:6px 0 0;">
                        <?php esc_html_e('Monthly CSV reports can be sent to this email address.', 'geo-ads-pro'); ?>
                    </p>
                </div>
                <div class="gap-dropzone" tabindex="0" role="button" aria-label="<?php echo esc_attr__('Select or drop banner images', 'geo-ads-pro'); ?>">
                    <input class="gap-dropzone-input" type="file" name="gap_banner_file[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple required>
                    <div class="gap-dropzone-icon">+</div>
                    <div class="gap-dropzone-title"><?php esc_html_e('Drop banner images here', 'geo-ads-pro'); ?></div>
                    <div class="gap-dropzone-text"><?php esc_html_e('or click to choose JPG, PNG, GIF, or WebP files', 'geo-ads-pro'); ?></div>
                </div>
                <ul class="gap-upload-file-list" aria-live="polite"></ul>
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
                            <th><?php esc_html_e('Customer Email', 'geo-ads-pro'); ?></th>
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
                                    <input type="email"
                                           class="widefat"
                                           name="gap_banner_email[<?php echo esc_attr($banner['id']); ?>]"
                                           value="<?php echo esc_attr($banner['customer_email'] ?? ''); ?>"
                                           placeholder="customer@example.com">
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
                        <tr><td colspan="8"><?php esc_html_e('There are no banners for this region yet.', 'geo-ads-pro'); ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <input type="hidden" name="gap_delete_banner_id" id="gap_delete_banner_id" value="0">
                <input type="hidden" name="gap_delete_banner_nonce" value="<?php echo esc_attr(wp_create_nonce('gap_delete_banner_nonce')); ?>">

                <br>
                <button class="button button-primary" name="gap_save_selection"><?php esc_html_e('Save Selections', 'geo-ads-pro'); ?></button>
            </form>

            <?php else: ?>

            <hr>

            <h2><?php esc_html_e('Upload Banner', 'geo-ads-pro'); ?></h2>

            <div class="gap-dropzone gap-dropzone-disabled" aria-disabled="true">
                <div class="gap-dropzone-icon">+</div>
                <div class="gap-dropzone-title"><?php esc_html_e('Add or select a region to upload banners.', 'geo-ads-pro'); ?></div>
                <div class="gap-dropzone-text"><?php esc_html_e('The drag and drop upload area will appear here after a region is selected.', 'geo-ads-pro'); ?></div>
            </div>

            <?php endif; ?>

            <hr>
            <h2><?php esc_html_e('Banner → Region Assignment', 'geo-ads-pro'); ?></h2>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Preview', 'geo-ads-pro'); ?></th>
                        <th><?php esc_html_e('Region', 'geo-ads-pro'); ?></th>
                        <th><?php esc_html_e('File', 'geo-ads-pro'); ?></th>
                        <th><?php esc_html_e('Size', 'geo-ads-pro'); ?></th>
                        <th>ID</th>
                        <th><?php esc_html_e('Selected', 'geo-ads-pro'); ?></th>
                        <th><?php esc_html_e('Click URL', 'geo-ads-pro'); ?></th>
                        <th><?php esc_html_e('Customer Email', 'geo-ads-pro'); ?></th>
                        <th><?php esc_html_e('Action', 'geo-ads-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $has_banners = false;
                    foreach ($regions as $overview_region => $overview_data):
                        $overview_banners = $overview_data['banners'] ?? [];
                        $overview_region_url = trailingslashit($base_url) . rawurlencode(gap_region_folder_name($overview_region));
                        foreach ($overview_banners as $overview_banner):
                            $has_banners = true;
                    ?>
                        <tr>
                            <td>
                                <img src="<?php echo esc_url($overview_region_url . '/' . rawurlencode(basename($overview_banner['file']))); ?>" width="120" alt="">
                            </td>
                            <td><?php echo esc_html($overview_region); ?></td>
                            <td><?php echo esc_html($overview_banner['file']); ?></td>
                            <td><?php echo esc_html((int) $overview_banner['width'] . 'x' . (int) $overview_banner['height']); ?></td>
                            <td><?php echo esc_html($overview_banner['id']); ?></td>
                            <td><?php echo !empty($overview_banner['selected']) ? esc_html__('Yes', 'geo-ads-pro') : esc_html__('No', 'geo-ads-pro'); ?></td>
                            <td><?php echo esc_html($overview_banner['url'] ?? ''); ?></td>
                            <td><?php echo esc_html($overview_banner['customer_email'] ?? ''); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="gap_selected_region" value="<?php echo esc_attr($overview_region); ?>">
                                    <button class="button" type="submit"><?php esc_html_e('Edit Region Banners', 'geo-ads-pro'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php
                        endforeach;
                    endforeach;
                    ?>
                    <?php if (!$has_banners): ?>
                        <tr><td colspan="9"><?php esc_html_e('There are no banners uploaded yet.', 'geo-ads-pro'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

        </div>
        <?php
    }
}
