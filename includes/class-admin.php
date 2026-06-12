<?php
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
            __('Banner List', 'geo-ads-pro'),
            'die1-Geo Ads Pro',
            'manage_options',
            'geo-ads-pro-banners',
            [$this, 'render_banner_list_page'],
            'dashicons-location-alt',
            60
        );

        add_submenu_page(
            'geo-ads-pro-banners',
            __('Banner List', 'geo-ads-pro'),
            __('Banner List', 'geo-ads-pro'),
            'manage_options',
            'geo-ads-pro-banners',
            [$this, 'render_banner_list_page']
        );

        add_submenu_page(
            'geo-ads-pro-banners',
            __('Regions & Upload', 'geo-ads-pro'),
            __('Regions & Upload', 'geo-ads-pro'),
            'manage_options',
            'geo-ads-pro-upload',
            [$this, 'render_page']
        );

        add_submenu_page(
            'geo-ads-pro-banners',
            __('Rotation Settings', 'geo-ads-pro'),
            __('Rotation Settings', 'geo-ads-pro'),
            'manage_options',
            'geo-ads-pro-rotation',
            [$this, 'render_rotation_page']
        );
    }

    private function region_exists($region, $regions = null) {
        $regions = is_array($regions) ? $regions : $this->regions->get_all();
        $region = Geo_Ads_Pro_Regions::normalize_region_input($region);
        return $region !== '' && array_key_exists($region, $regions);
    }

    private function is_unlimited_region($region) {
        return Geo_Ads_Pro_Regions::is_unlimited_region($region);
    }

    private function region_display_name($region) {
        return Geo_Ads_Pro_Regions::display_name($region);
    }

    // =========================================================================
    // Rotation Groups (Rotierte Banner-Gruppen)
    // =========================================================================

    public function render_rotation_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'geo-ads-pro'));
        }

        $regions = $this->regions->get_all();
        $base_dir = gap_upload_base_dir();
        $base_url = gap_upload_base_url();

        // AJAX: Gruppe speichern
        if (isset($_POST['gap_save_rotation_group']) && !empty($_POST['_wpnonce'])) {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'gap_save_rotation_group_nonce')) {
                wp_die(esc_html__('Invalid request.', 'geo-ads-pro'));
            }
            // Bölgeleri virgülle ayrılmış string'den array'e çevir
            $region_raw = sanitize_text_field($_POST['gap_group_region'] ?? '');
            $region_list = array_filter(array_map('trim', explode(',', $region_raw)));

            $group = [
                'id'         => sanitize_text_field($_POST['gap_group_id'] ?? ''),
                'name'       => sanitize_text_field($_POST['gap_group_name'] ?? ''),
                'banner_ids' => array_map('intval', $_POST['gap_group_banners'] ?? []),
                'region'     => !empty($region_list) ? $region_list[0] : '',  // eski uyumluluk
                'regions'    => $region_list,  // çoklu bölge desteği
                'created_at' => !empty($group['id']) ? null : time(),
            ];
            if (empty($group['name'])) {
                $group['name'] = sprintf(__('Rotation Group %d', 'geo-ads-pro'), count(GAP()->banner_service->get_all_rotation_groups()) + 1);
            }
            GAP()->banner_service->save_rotation_group($group);
            echo '<div class="updated"><p>' . esc_html__('Rotation group saved.', 'geo-ads-pro') . '</p></div>';
        }

        // AJAX: Gruppe löschen
        if (isset($_POST['gap_delete_rotation_group']) && !empty($_POST['_wpnonce'])) {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'gap_delete_rotation_group_nonce')) {
                wp_die(esc_html__('Invalid request.', 'geo-ads-pro'));
            }
            $group_id = sanitize_text_field($_POST['gap_group_id'] ?? '');
            GAP()->banner_service->delete_rotation_group($group_id);
            echo '<div class="updated"><p>' . esc_html__('Rotation group deleted.', 'geo-ads-pro') . '</p></div>';
        }

        // Grup verilerini getir
        $all_groups = GAP()->banner_service->get_all_rotation_groups();
        ?>
        <?php wp_nonce_field('gap_save_rotation_group_nonce'); ?>
        <div class="wrap gap-rotation-page">
            <h1><?php esc_html_e('Rotation Settings', 'geo-ads-pro'); ?></h1>

            <h2><?php esc_html_e('New Rotation Group', 'geo-ads-pro'); ?></h2>

            <div class="gap-rotation-layout">
                <!-- Linke Spalte: Filter + Banner -->
                <div class="gap-rotation-left">
                    <label for="gap_rotation_region_filter" style="display:block; font-weight:600; margin-bottom:8px;">
                        <?php esc_html_e('Filter by Region', 'geo-ads-pro'); ?>
                    </label>
                    <select id="gap_rotation_region_filter" class="widefat" style="margin-bottom:12px;">
                        <option value=""><?php esc_html_e('All Banners', 'geo-ads-pro'); ?></option>
                        <?php foreach ($regions as $region_name => $region_data): ?>
                            <option value="<?php echo esc_attr($region_name); ?>"><?php echo esc_html($region_name); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div id="gap_rotation_banner_grid" class="gap-rotation-banner-grid">
                    <?php foreach ($regions as $region_name => $region_data):
                        $region_banners = $region_data['banners'] ?? [];
                        foreach ($region_banners as $banner): ?>
                            <div class="gap-rotation-banner-item"
                                 data-region="<?php echo esc_attr($region_name); ?>"
                                 data-id="<?php echo esc_attr($banner['id']); ?>"
                                 draggable="true">
                                <img src="<?php echo esc_url(trailingslashit($base_url) . rawurlencode(gap_region_folder_name($region_name)) . '/' . rawurlencode(basename($banner['file']))); ?>" alt="">
                                <span class="gap-rotation-banner-label"><?php echo esc_html(substr($banner['file'], 0, 20)); ?>…</span>
                            </div>
                        <?php endforeach; endforeach; ?>
                    </div>
                </div>

                <!-- Rechte Spalte: Dropzone + Gruppe -->
                <div class="gap-rotation-right">
                    <label style="display:block; font-weight:600; margin-bottom:8px;">
                        <?php esc_html_e('Rotation Group Members', 'geo-ads-pro'); ?>
                    </label>
                    <div id="gap_rotation_dropzone" class="gap-rotation-dropzone">
                        <div id="gap_rotation_dropzone_empty" style="text-align:center; padding:40px 20px; color:#999;">
                            <svg class="gap-dnd-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                <path d="M12 8v8"/>
                                <path d="M8 12l4 4 4-4"/>
                            </svg>
                            <?php esc_html_e('Drag banners here to add them to the rotation group.', 'geo-ads-pro'); ?>
                        </div>
                        <div id="gap_rotation_dropzone_items" class="gap-rotation-dropzone-items"></div>
                    </div>

                    <label style="display:block; font-weight:600; margin:16px 0 8px;">
                        <?php esc_html_e('Select Region', 'geo-ads-pro'); ?>
                    </label>
                    <div class="gap-region-multi-select">
                        <div class="gap-region-multi-toggle" id="gap_region_multi_toggle">
                            <span class="gap-region-multi-placeholder"><?php esc_html_e('Select regions…', 'geo-ads-pro'); ?></span>
                            <span class="gap-region-multi-arrow">▾</span>
                        </div>
                        <div class="gap-region-multi-dropdown" id="gap_region_multi_dropdown">
                            <?php foreach ($regions as $region_name => $region_data): ?>
                                <label class="gap-region-multi-option">
                                    <input type="checkbox" class="gap-region-checkbox" value="<?php echo esc_attr($region_name); ?>">
                                    <?php echo esc_html($region_name); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <label for="gap_group_name_input" style="display:block; font-weight:600; margin:16px 0 8px;">
                        <?php esc_html_e('Group Name', 'geo-ads-pro'); ?>
                    </label>
                    <input type="text" id="gap_group_name_input" class="widefat" placeholder="<?php esc_attr_e('e.g. Stuttgart Summer Rotation', 'geo-ads-pro'); ?>">

                    <button id="gap_create_rotation_btn" class="button button-primary" style="margin-top:12px;">
                        <?php esc_html_e('Create Rotate Group', 'geo-ads-pro'); ?>
                    </button>

                    <div id="gap_shortcode_output" style="margin-top:16px; display:none;">
                        <label style="display:block; font-weight:600; margin-bottom:8px;">
                            <?php esc_html_e('Shortcode', 'geo-ads-pro'); ?>
                        </label>
                        <input type="text" id="gap_shortcode_input" class="widefat" readonly onclick="this.select();">
                        <p class="description"><?php esc_html_e('Copy this shortcode and paste it on your page.', 'geo-ads-pro'); ?></p>
                    </div>
                </div>
            </div>

            <hr>

            <!-- Bestehende Rotationsgruppen -->
            <h2><?php esc_html_e('Rotated Banners', 'geo-ads-pro'); ?></h2>

            <?php if (!empty($all_groups)): ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Group Name', 'geo-ads-pro'); ?></th>
                            <th><?php esc_html_e('Region', 'geo-ads-pro'); ?></th>
                            <th><?php esc_html_e('Banner Count', 'geo-ads-pro'); ?></th>
                            <th><?php esc_html_e('Shortcode', 'geo-ads-pro'); ?></th>
                            <th><?php esc_html_e('Action', 'geo-ads-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_groups as $group): ?>
                            <tr>
                                <td><?php echo esc_html($group['name'] ?? ''); ?></td>
                                <td><?php
                                    $group_regions = $group['regions'] ?? [$group['region'] ?? ''];
                                    $group_regions = array_filter($group_regions);
                                    $group_regions = array_map([$this, 'region_display_name'], $group_regions);
                                    echo esc_html(implode(', ', $group_regions));
                                ?></td>
                                <td><?php echo esc_html(count($group['banner_ids'] ?? [])); ?></td>
                                <td style="font-family:monospace; font-size:14px;">
                                    [geo_ads_pro mode="local" group_id="<?php echo esc_attr($group['id'] ?? ''); ?>"]
                                </td>
                                <td>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js(__('Delete this rotation group?', 'geo-ads-pro')); ?>')">
                                        <?php wp_nonce_field('gap_delete_rotation_group_nonce'); ?>
                                        <input type="hidden" name="gap_group_id" value="<?php echo esc_attr($group['id']); ?>">
                                        <button class="button button-link-delete" name="gap_delete_rotation_group" style="color: #bc0b0b; cursor: pointer;"><?php esc_html_e('Delete', 'geo-ads-pro'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php esc_html_e('No rotation groups created yet.', 'geo-ads-pro'); ?></p>
            <?php endif; ?>
        </div>


        <!-- Styles and scripts are enqueued via geo-ads-pro.php enqueue_admin_assets() -->
        <?php
    }

    private function archive_banner_to_media($banner, $region, $base_dir) {
        $file_path = trailingslashit($base_dir) . gap_region_folder_name($region) . '/' . basename($banner['file']);
        if (!file_exists($file_path)) return;

        $upload_dir = wp_upload_dir();
        $new_filename = wp_unique_filename($upload_dir['path'], $banner['file']);
        $new_path = trailingslashit($upload_dir['path']) . $new_filename;

        if (!copy($file_path, $new_path)) return;

        $filetype = wp_check_filetype($new_filename);
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_file_name(pathinfo($new_filename, PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $new_path);
        if (!is_wp_error($attach_id)) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $meta = wp_generate_attachment_metadata($attach_id, $new_path);
            wp_update_attachment_metadata($attach_id, $meta);
        }
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

    private function handle_banner_upload($file, $region, $region_dir, $customer_email = '', $click_url = '', $link_target = '_blank') {
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
        $id = random_int(100000, 999999);

        $this->regions->add_banner($region, [
            'id'             => $id,
            'file'           => $filename,
            'width'          => $w,
            'height'         => $h,
            'selected'       => false,
            'url'            => gap_validate_click_url($click_url),
            'link_target'    => in_array($link_target, ['_blank', '_self'], true) ? $link_target : '_blank',
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

        // Multi-region archive or delete
        if (isset($_POST['gap_archive_regions']) || isset($_POST['gap_delete_regions_confirm'])) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gap_delete_region_nonce')) {
                wp_die(esc_html__('Invalid request.', 'geo-ads-pro'));
            }
            $archive = isset($_POST['gap_archive_regions']);
            $regions_to_delete = array_filter(array_map('sanitize_text_field', explode(',', $_POST['gap_regions_to_delete'] ?? '')));
            $deleted = [];

            foreach ($regions_to_delete as $r) {
                if (!$this->region_exists($r, $regions)) continue;
                if ($this->is_unlimited_region($r)) continue;

                if ($archive) {
                    $rd = $this->regions->get_region($r);
                    foreach ($rd['banners'] ?? [] as $b) {
                        $this->archive_banner_to_media($b, $r, $base_dir);
                    }
                }

                $this->delete_region_directory($r, $base_dir);
                $this->regions->delete_region($r);
                $this->citymap->remove_region_mappings($r);
                GAP()->analytics->delete_region($r);
                $deleted[] = $this->region_display_name($r);
            }

            if (!empty($deleted)) {
                $msg = $archive
                    ? sprintf(__('Region(s) deleted, banners archived to Media Library: %s', 'geo-ads-pro'), implode(', ', $deleted))
                    : sprintf(__('Region(s) and banners deleted: %s', 'geo-ads-pro'), implode(', ', $deleted));
                echo '<div class="updated"><p>' . esc_html($msg) . '</p></div>';
                $regions = $this->regions->get_all();
                $_POST['gap_selected_region'] = '';
            }
        }

        // Bölge silme
        if (isset($_POST['gap_delete_region'])) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gap_delete_region_nonce')) {
                wp_die(esc_html__('Invalid request.', 'geo-ads-pro'));
            }
            $region = sanitize_text_field($_POST['gap_selected_region'] ?? '');
            if ($this->region_exists($region, $regions)) {
                if ($this->is_unlimited_region($region)) {
                    echo '<div class="error"><p>' . esc_html__('The unlimited region is fixed and cannot be deleted.', 'geo-ads-pro') . '</p></div>';
                } else {
                $this->delete_region_directory($region, $base_dir);
                $this->regions->delete_region($region);
                $this->citymap->remove_region_mappings($region);
                GAP()->analytics->delete_region($region);
                echo '<div class="updated"><p>' . esc_html(sprintf(__('Region deleted: %s', 'geo-ads-pro'), $this->region_display_name($region))) . '</p></div>';
                $regions = $this->regions->get_all();
                $_POST['gap_selected_region'] = '';
                }
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
                // Autocomplete'den gelen koordinatları kullan, yoksa geocode et
                $lat = isset($_POST['gap_region_lat']) && is_numeric($_POST['gap_region_lat'])
                    ? floatval($_POST['gap_region_lat']) : null;
                $lon = isset($_POST['gap_region_lon']) && is_numeric($_POST['gap_region_lon'])
                    ? floatval($_POST['gap_region_lon']) : null;

                if ($lat === null || $lon === null) {
                    $coords = gap_geocode_region_center($region);
                    $lat = $coords['latitude'];
                    $lon = $coords['longitude'];
                }

                $this->regions->add_region($region, [
                    'latitude'  => $lat,
                    'longitude' => $lon,
                    'radius_km' => max(0, min(99999, floatval($_POST['gap_region_radius_km'] ?? '0'))),
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
                if ($this->is_unlimited_region($region)) {
                    $this->regions->update_region_targeting($region, []);
                } else {
                    $coords = gap_geocode_region_center($region);
                    $this->regions->update_region_targeting($region, [
                        'latitude'  => $coords['latitude'] ?? ($region_data['latitude'] ?? 0),
                        'longitude' => $coords['longitude'] ?? ($region_data['longitude'] ?? 0),
                        'radius_km' => max(0, min(99999, floatval($_POST['gap_region_radius_km'] ?? '0'))),
                    ]);
                }
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
                    $click_url = gap_validate_click_url($_POST['gap_click_url'] ?? '');
                    $link_target = sanitize_text_field($_POST['gap_link_target'] ?? '_blank');

                    foreach ($files as $file) {
                        $result = $this->handle_banner_upload($file, $region, $region_dir, $customer_email, $click_url, $link_target);
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
            // Banner IDs integer olmalı, URL/Email/link_target sanitize edilmeli
            $selected_ids = isset($_POST['gap_banner_select']) && is_array($_POST['gap_banner_select'])
                ? array_map('absint', $_POST['gap_banner_select'])
                : [];
            $urls = isset($_POST['gap_banner_url']) && is_array($_POST['gap_banner_url'])
                ? array_map('sanitize_text_field', $_POST['gap_banner_url'])
                : [];
            $emails = isset($_POST['gap_banner_email']) && is_array($_POST['gap_banner_email'])
                ? array_map('sanitize_email', $_POST['gap_banner_email'])
                : [];
            $link_targets = isset($_POST['gap_banner_link_target']) && is_array($_POST['gap_banner_link_target'])
                ? array_map('sanitize_text_field', $_POST['gap_banner_link_target'])
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
                if (isset($link_targets[$id])) {
                    $lt = sanitize_text_field($link_targets[$id]);
                    $banner['link_target'] = in_array($lt, ['_blank', '_self'], true) ? $lt : '_blank';
                }
            }

            $this->regions->update_banners($region, $banners);
            echo '<div class="updated"><p>' . esc_html__('Selections, URLs, and customer emails saved.', 'geo-ads-pro') . '</p></div>';
            $regions = $this->regions->get_all();
        }

        $selected_region = sanitize_text_field($_POST['gap_selected_region'] ?? ($_GET['gap_selected_region'] ?? ''));

        // Boş durumda ilk region'u otomatik seç
        if ($selected_region === '' && !empty($regions)) {
            $selected_region = Geo_Ads_Pro_Regions::unlimited_region_key();
        }

        $this->render_page_content($selected_region, $regions, $base_dir, $base_url);
    }

    // =========================================================================
    // Render sayfaları
    // =========================================================================

    private function render_page_content($selected_region, $regions, $base_dir, $base_url) {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('die1-Geo Ads Pro – Regions & Banner Management', 'geo-ads-pro'); ?></h1>

            <h2><?php esc_html_e('Add Region', 'geo-ads-pro'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('gap_add_region_nonce'); ?>
                <input type="text" name="gap_region_name" placeholder="<?php echo esc_attr__('Example: NRW, Istanbul, Bayern', 'geo-ads-pro'); ?>" required>
                <input type="number" min="0" step="0.1" name="gap_region_radius_km" placeholder="<?php echo esc_attr__('Radius km', 'geo-ads-pro'); ?>">
                <button class="button button-primary" name="gap_add_region"><?php esc_html_e('Add', 'geo-ads-pro'); ?></button>
            </form>

            <hr>

            <h2><?php esc_html_e('Select Region', 'geo-ads-pro'); ?></h2>
            <form method="post" enctype="multipart/form-data" id="gap_select_region_form" style="display:none;">
                <input type="hidden" name="gap_selected_region" id="gap_selected_region_input" value="<?php echo esc_attr($selected_region); ?>">
            </form>

            <div class="gap-region-picker-row">
                <div class="gap-region-picker" id="gap_region_picker" data-no-selection="<?php echo esc_attr(__('Please select at least one region to delete.', 'geo-ads-pro')); ?>">
                    <div class="gap-region-picker-toggle" id="gap_region_picker_toggle">
                        <span class="gap-region-picker-label"><?php echo esc_html($selected_region !== '' ? $this->region_display_name($selected_region) : __('Select a region', 'geo-ads-pro')); ?></span>
                        <span class="gap-region-picker-arrow">▾</span>
                    </div>
                    <div class="gap-region-picker-dropdown" id="gap_region_picker_dropdown">
                        <?php foreach ($regions as $region => $data): ?>
                            <div class="gap-region-picker-item<?php echo $selected_region === $region ? ' active' : ''; ?><?php echo $this->is_unlimited_region($region) ? ' is-fixed' : ''; ?>" data-region="<?php echo esc_attr($region); ?>">
                                <?php if (!$this->is_unlimited_region($region)): ?>
                                    <input type="checkbox" class="gap-region-check" value="<?php echo esc_attr($region); ?>" onclick="event.stopPropagation();">
                                <?php else: ?>
                                    <input type="checkbox" class="gap-region-check" value="<?php echo esc_attr($region); ?>" disabled aria-disabled="true">
                                <?php endif; ?>
                                <span class="gap-region-item-name"><?php echo esc_html($this->region_display_name($region)); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="button" class="gap-region-delete-btn" id="gap_delete_regions_btn"<?php disabled($this->is_unlimited_region($selected_region)); ?>>
                    <?php esc_html_e('Delete This Region', 'geo-ads-pro'); ?>
                </button>
            </div>

            <!-- Delete Confirmation Modal -->
            <div id="gap_delete_region_modal" class="gap-modal-overlay" style="display:none;" data-msg="<?php echo esc_attr(__('When you delete the selected region(s) ({regions}), all banners belonging to those regions will also be deleted automatically. Would you like to archive the banners to the Media Library instead?', 'geo-ads-pro')); ?>">
                <div class="gap-modal">
                    <div class="gap-modal-body">
                        <p id="gap_modal_message"></p>
                    </div>
                    <div class="gap-modal-footer">
                        <form method="post" id="gap_modal_form">
                            <?php wp_nonce_field('gap_delete_region_nonce'); ?>
                            <input type="hidden" name="gap_regions_to_delete" id="gap_regions_to_delete" value="">
                            <button type="submit" name="gap_archive_regions" class="button button-primary"><?php esc_html_e('Yes, archive', 'geo-ads-pro'); ?></button>
                            <button type="submit" name="gap_delete_regions_confirm" class="button" style="background:#dc3232;border-color:#dc3232;color:#fff;margin-left:8px;"><?php esc_html_e('No, delete', 'geo-ads-pro'); ?></button>
                            <button type="button" class="button gap-modal-cancel" style="margin-left:8px;"><?php esc_html_e('Cancel', 'geo-ads-pro'); ?></button>
                        </form>
                    </div>
                </div>
            </div>

            <?php
            if ($selected_region !== ''):

                $region = $selected_region;
                $region_dir = trailingslashit($base_dir) . gap_region_folder_name($region);
                $region_url = trailingslashit($base_url) . rawurlencode(gap_region_folder_name($region));
                $region_data = $this->regions->get_region($region);
                $banners = $region_data['banners'] ?? [];
                $is_unlimited_region = $this->is_unlimited_region($region);
            ?>

            <hr>

            <?php if ($is_unlimited_region): ?>
                <h2><?php esc_html_e('Region Radius Targeting', 'geo-ads-pro'); ?></h2>
                <p class="description">
                    <?php esc_html_e('The unlimited region is always available and does not use radius targeting.', 'geo-ads-pro'); ?>
                </p>
            <?php else: ?>
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
            <?php endif; ?>

            <hr>

            <h2><?php echo esc_html(sprintf(__('%s Region – Upload Banner', 'geo-ads-pro'), $this->region_display_name($region))); ?></h2>

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
                <div style="margin: 0 0 12px 0;">
                    <label for="gap_click_url" style="display:block; font-weight:600; margin-bottom:6px;">
                        <?php esc_html_e('Ad URL', 'geo-ads-pro'); ?>
                    </label>
                    <input type="url"
                           id="gap_click_url"
                           name="gap_click_url"
                           class="widefat"
                           placeholder="https://www.example.com">
                    <p class="description" style="margin:6px 0 0;">
                        <?php esc_html_e('The URL that the banner links to when clicked.', 'geo-ads-pro'); ?>
                    </p>
                </div>
                <div style="margin: 0 0 12px 0;">
                    <label style="display:block; font-weight:600; margin-bottom:6px;">
                        <?php esc_html_e('Open in', 'geo-ads-pro'); ?>
                    </label>
                    <label style="margin-right:16px;">
                        <input type="radio" name="gap_link_target" value="_blank" checked>
                        <?php esc_html_e('New tab', 'geo-ads-pro'); ?>
                    </label>
                    <label>
                        <input type="radio" name="gap_link_target" value="_self">
                        <?php esc_html_e('Same page', 'geo-ads-pro'); ?>
                    </label>
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

            <?php endif; ?>

        </div>
        <?php
    }

    // =========================================================================
    // Banner List Page
    // =========================================================================

    public function render_banner_list_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'geo-ads-pro'));
        }

        $regions = $this->regions->get_all();
        $base_dir = gap_upload_base_dir();
        $base_url = gap_upload_base_url();
        // Handle save
        if (isset($_POST['gap_save_banner_list']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'gap_banner_list_nonce')) {
            // Tüm array inputları sanitize et — injection koruması
            $urls = isset($_POST['gap_banner_url']) && is_array($_POST['gap_banner_url']) ? array_map('sanitize_text_field', $_POST['gap_banner_url']) : [];
            $emails = isset($_POST['gap_banner_email']) && is_array($_POST['gap_banner_email']) ? array_map('sanitize_email', $_POST['gap_banner_email']) : [];
            $link_targets = isset($_POST['gap_banner_link_target']) && is_array($_POST['gap_banner_link_target']) ? array_map('sanitize_text_field', $_POST['gap_banner_link_target']) : [];
            $banner_regions = isset($_POST['gap_banner_region']) && is_array($_POST['gap_banner_region']) ? array_map('sanitize_text_field', $_POST['gap_banner_region']) : [];

            foreach ($banner_regions as $bid => $rname) {
                $region_data = $this->regions->get_region($rname);
                $banners = $region_data['banners'] ?? [];
                foreach ($banners as &$b) {
                    if ((string) $b['id'] === (string) $bid) {
                        if (isset($urls[$bid])) $b['url'] = gap_validate_click_url($urls[$bid]);
                        if (isset($emails[$bid])) $b['customer_email'] = gap_sanitize_customer_email($emails[$bid]);
                        if (isset($link_targets[$bid])) {
                            $lt = sanitize_text_field($link_targets[$bid]);
                            $b['link_target'] = in_array($lt, ['_blank', '_self'], true) ? $lt : '_blank';
                        }
                    }
                }
                $this->regions->update_banners($rname, $banners);
            }
            $regions = $this->regions->get_all();
            echo '<div class="updated"><p>' . esc_html__('Banner list saved.', 'geo-ads-pro') . '</p></div>';
        }

        // Handle delete
        if (isset($_POST['gap_delete_banner_id']) && $_POST['gap_delete_banner_id'] && isset($_POST['gap_delete_nonce']) && wp_verify_nonce($_POST['gap_delete_nonce'], 'gap_delete_banner_list_nonce')) {
            $del_id = absint($_POST['gap_delete_banner_id']);
            $del_region = sanitize_text_field($_POST['gap_delete_banner_region'] ?? '');
            if ($del_region) {
                $region_data = $this->regions->get_region($del_region);
                $all_banners = $region_data['banners'] ?? [];
                foreach ($all_banners as $b) {
                    if ((int) $b['id'] === $del_id) {
                        $banner_file = trailingslashit($base_dir) . gap_region_folder_name($del_region) . '/' . basename($b['file']);
                        $banner_real = realpath($banner_file);
                        $region_real = realpath(trailingslashit($base_dir) . gap_region_folder_name($del_region));
                        if ($banner_real && $region_real && strpos($banner_real, $region_real . DIRECTORY_SEPARATOR) === 0) {
                            @unlink($banner_real);
                        }
                        break;
                    }
                }
                $banners = array_filter($all_banners, fn($b) => (int) $b['id'] !== $del_id);
                $this->regions->update_banners($del_region, array_values($banners));
                GAP()->analytics->delete_banner($del_id);
                $regions = $this->regions->get_all();
                echo '<div class="updated"><p>' . esc_html__('Banner deleted.', 'geo-ads-pro') . '</p></div>';
            }
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Banner List', 'geo-ads-pro'); ?></h1>

            <form method="post">
                <?php wp_nonce_field('gap_banner_list_nonce'); ?>

                <table class="widefat striped" id="gap-banner-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Preview', 'geo-ads-pro'); ?></th>
                            <th class="gap-sortable" data-sort-key="region" data-sort-type="string"><?php esc_html_e('Region', 'geo-ads-pro'); ?> <span class="gap-sort-icon">⇅</span></th>
                            <th class="gap-sortable" data-sort-key="size" data-sort-type="number"><?php esc_html_e('Size', 'geo-ads-pro'); ?> <span class="gap-sort-icon">⇅</span></th>
                            <th><?php esc_html_e('ID', 'geo-ads-pro'); ?></th>
                            <th><?php esc_html_e('Ad URL', 'geo-ads-pro'); ?></th>
                            <th><?php esc_html_e('Open in', 'geo-ads-pro'); ?></th>
                            <th><?php esc_html_e('Customer Email', 'geo-ads-pro'); ?></th>
                            <th class="gap-sortable" data-sort-key="clicks" data-sort-type="number"><?php esc_html_e('Clicks', 'geo-ads-pro'); ?> <span class="gap-sort-icon">⇅</span></th>
                            <th class="gap-sortable" data-sort-key="views" data-sort-type="number"><?php esc_html_e('Views', 'geo-ads-pro'); ?> <span class="gap-sort-icon">⇅</span></th>
                            <th><?php esc_html_e('Shortcode', 'geo-ads-pro'); ?></th>
                            <th><?php esc_html_e('Delete', 'geo-ads-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $has_banners = false;
                        foreach ($regions as $region_name => $region_data):
                            $banners = $region_data['banners'] ?? [];
                            $region_url = trailingslashit($base_url) . rawurlencode(gap_region_folder_name($region_name));
                            foreach ($banners as $banner):
                                $has_banners = true;
                                $stats = GAP()->analytics->get_banner_stats($banner['id']);
                                $shortcode = '[geo_ads_pro mode="local" region="' . esc_attr($region_name) . '" banner_id="' . esc_attr($banner['id']) . '"]';
                        ?>
                            <tr class="gap-banner-main" data-region="<?php echo esc_attr($region_name); ?>" data-size="<?php echo esc_attr((int) $banner['width'] * (int) $banner['height']); ?>" data-clicks="<?php echo esc_attr(intval($stats['clicks'] ?? 0)); ?>" data-views="<?php echo esc_attr(intval($stats['impressions'] ?? 0)); ?>">
                                <td><img src="<?php echo esc_url($region_url . '/' . rawurlencode(basename($banner['file']))); ?>" width="120" alt=""></td>
                                <td><?php echo esc_html($this->region_display_name($region_name)); ?></td>
                                <td><?php echo esc_html((int) $banner['width'] . 'x' . (int) $banner['height']); ?></td>
                                <td><?php echo esc_html($banner['id']); ?></td>
                                <td>
                                    <input type="text" class="widefat" name="gap_banner_url[<?php echo esc_attr($banner['id']); ?>]" value="<?php echo esc_attr($banner['url'] ?? ''); ?>" placeholder="https://...">
                                    <input type="hidden" name="gap_banner_region[<?php echo esc_attr($banner['id']); ?>]" value="<?php echo esc_attr($region_name); ?>">
                                </td>
                                <td>
                                    <select name="gap_banner_link_target[<?php echo esc_attr($banner['id']); ?>]">
                                        <option value="_blank" <?php selected(($banner['link_target'] ?? '_blank'), '_blank'); ?>><?php esc_html_e('New tab', 'geo-ads-pro'); ?></option>
                                        <option value="_self" <?php selected(($banner['link_target'] ?? '_blank'), '_self'); ?>><?php esc_html_e('Same page', 'geo-ads-pro'); ?></option>
                                    </select>
                                </td>
                                <td><input type="email" class="widefat" name="gap_banner_email[<?php echo esc_attr($banner['id']); ?>]" value="<?php echo esc_attr($banner['customer_email'] ?? ''); ?>" placeholder="customer@example.com"></td>
                                <td><?php echo intval($stats['clicks'] ?? 0); ?></td>
                                <td><?php echo intval($stats['impressions'] ?? 0); ?></td>
                                <td>
                                    <span style="position:relative;display:inline-block;">
                                        <input type="text" readonly value="<?php echo esc_attr($shortcode); ?>" onclick="this.select();document.execCommand('copy');var b=this.nextElementSibling;b.style.opacity=1;b.style.visibility='visible';setTimeout(function(){b.style.opacity=0;b.style.visibility='hidden';},1500);" style="font-family:monospace;font-size:11px;width:220px;cursor:pointer;background:#f6f7f7;" title="<?php esc_attr_e('Click to copy', 'geo-ads-pro'); ?>">
                                        <span style="position:absolute;bottom:100%;left:50%;transform:translateX(-50%);background:#1d2327;color:#fff;padding:4px 10px;border-radius:4px;font-size:12px;white-space:nowrap;opacity:0;visibility:hidden;transition:opacity .3s;pointer-events:none;"><?php esc_html_e('Copied!', 'geo-ads-pro'); ?></span>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="button button-small gap-delete-banner-btn" data-id="<?php echo esc_attr($banner['id']); ?>" data-region="<?php echo esc_attr($region_name); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('gap_delete_banner_list_nonce')); ?>" style="color:#bc0b0b;" title="<?php esc_attr_e('Delete', 'geo-ads-pro'); ?>">
                                        <span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;line-height:14px;"></span>
                                    </button>
                                </td>
                            </tr>
                        <?php
                            endforeach;
                        endforeach;
                        ?>
                        <?php if (!$has_banners): ?>
                            <tr><td colspan="11"><?php esc_html_e('There are no banners uploaded yet.', 'geo-ads-pro'); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($has_banners): ?>
                    <br>
                    <button class="button button-primary" name="gap_save_banner_list"><?php esc_html_e('Save Changes', 'geo-ads-pro'); ?></button>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }
}
