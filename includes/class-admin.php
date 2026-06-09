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
            __('Upload / Edit Banners', 'geo-ads-pro'),
            'Geo Ads Pro',
            'manage_options',
            'geo-ads-pro-upload', // ← parent = upload sayfası, redundant giriş yok
            [$this, 'render_page'],
            'dashicons-location-alt',
            60
        );

        add_submenu_page(
            'geo-ads-pro-upload',
            __('Upload / Edit Banners', 'geo-ads-pro'),
            __('Upload / Edit Banners', 'geo-ads-pro'),
            'manage_options',
            'geo-ads-pro-upload',
            [$this, 'render_page']
        );

        add_submenu_page(
            'geo-ads-pro-upload',
            __('Rotation Einstellungen', 'geo-ads-pro'),
            __('Rotation Einstellungen', 'geo-ads-pro'),
            'manage_options',
            'geo-ads-pro-rotation',
            [$this, 'render_rotation_page']
        );
    }

    private function region_exists($region, $regions = null) {
        $regions = is_array($regions) ? $regions : $this->regions->get_all();
        return $region !== '' && array_key_exists($region, $regions);
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
            $group = [
                'id'         => sanitize_text_field($_POST['gap_group_id'] ?? ''),
                'name'       => sanitize_text_field($_POST['gap_group_name'] ?? ''),
                'banner_ids' => array_map('intval', $_POST['gap_group_banners'] ?? []),
                'region'     => sanitize_text_field($_POST['gap_group_region'] ?? ''),
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
            <h1><?php esc_html_e('Rotation Einstellungen', 'geo-ads-pro'); ?></h1>

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
            <h2><?php esc_html_e('Rotated Banners (Multi language)', 'geo-ads-pro'); ?></h2>

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
                                <td><?php echo esc_html($group['region'] ?? ''); ?></td>
                                <td><?php echo esc_html(count($group['banner_ids'] ?? [])); ?></td>
                                <td style="font-family:monospace; font-size:14px;">
                                    [geo_ads_pro mode="local" region="<?php echo esc_attr($group['region'] ?? ''); ?>" group_id="<?php echo esc_attr($group['id'] ?? ''); ?>"]
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
        $id = random_int(100000, 999999);

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

        $this->render_page_content($selected_region, $regions, $base_dir, $base_url);
    }

    // =========================================================================
    // Render sayfaları
    // =========================================================================

    private function render_page_content($selected_region, $regions, $base_dir, $base_url) {
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
                            <th><?php esc_html_e('Shortcode', 'geo-ads-pro'); ?></th>
                            <th><?php esc_html_e('Action', 'geo-ads-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($banners)): ?>
                        <?php foreach ($banners as $banner): ?>
                            <tr class="gap-banner-main">
                                <td><img src="<?php echo esc_url($region_url . '/' . rawurlencode(basename($banner['file']))); ?>" width="120" alt=""></td>
                                <td><?php echo esc_html((int)$banner['width'] . 'x' . (int)$banner['height']); ?></td>
                                <td><?php echo esc_html($banner['file']); ?></td>
                                <td><?php echo esc_html($banner['id']); ?></td>
                                <td><input type="checkbox" name="gap_banner_select[]" value="<?php echo esc_attr($banner['id']); ?>" <?php checked(!empty($banner['selected'])); ?>></td>
                                <td><input type="text" class="widefat" name="gap_banner_url[<?php echo esc_attr($banner['id']); ?>]" value="<?php echo esc_attr($banner['url'] ?? ''); ?>" placeholder="https://..."></td>
                                <td><input type="email" class="widefat" name="gap_banner_email[<?php echo esc_attr($banner['id']); ?>]" value="<?php echo esc_attr($banner['customer_email'] ?? ''); ?>" placeholder="customer@example.com"></td>
                                <td colspan="2"><button type="submit" name="gap_delete_banner" value="1" class="button button-link-delete" style="color: #bc0b0b; cursor: pointer;" onclick="if(confirm('<?php echo esc_js(__('Are you sure you want to delete this banner?', 'geo-ads-pro')); ?>')) { jQuery('#gap_delete_banner_id').val('<?php echo esc_attr($banner['id']); ?>'); return true; } return false;"><?php esc_html_e('Delete', 'geo-ads-pro'); ?></button></td>
                            </tr>
                            <tr class="gap-banner-shortcode">
                                <td colspan="9" style="font-family:monospace; font-size:13px; background:#f6f6f7; padding:6px 10px;">[geo_ads_pro mode="local" region="<?php echo esc_attr($region); ?>" banner_id="<?php echo esc_attr($banner['id']); ?>"]</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9"><?php esc_html_e('There are no banners for this region yet.', 'geo-ads-pro'); ?></td></tr>
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
                        <th><?php esc_html_e('Shortcode', 'geo-ads-pro'); ?></th>
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
                            <td><img src="<?php echo esc_url($overview_region_url . '/' . rawurlencode(basename($overview_banner['file']))); ?>" width="120" alt=""></td>
                            <td><?php echo esc_html($overview_region); ?></td>
                            <td><?php echo esc_html($overview_banner['file']); ?></td>
                            <td><?php echo esc_html((int) $overview_banner['width'] . 'x' . (int) $overview_banner['height']); ?></td>
                            <td><?php echo esc_html($overview_banner['id']); ?></td>
                            <td><?php echo !empty($overview_banner['selected']) ? esc_html__('Yes', 'geo-ads-pro') : esc_html__('No', 'geo-ads-pro'); ?></td>
                            <td><?php echo esc_html($overview_banner['url'] ?? ''); ?></td>
                            <td><?php echo esc_html($overview_banner['customer_email'] ?? ''); ?></td>
                            <td colspan="2"><form method="post" style="display:inline;"><input type="hidden" name="gap_selected_region" value="<?php echo esc_attr($overview_region); ?>"><button class="button" type="submit"><?php esc_html_e('Edit Region Banners', 'geo-ads-pro'); ?></button></form></td>
                        </tr>
                        <tr>
                            <td colspan="10" style="font-family:monospace; font-size:16px; background:#f0f0f1; padding:10px 14px; line-height:1.7;">[geo_ads_pro mode="local" region="<?php echo esc_attr($overview_region); ?>" banner_id="<?php echo esc_attr($overview_banner['id']); ?>"]</td>
                        </tr>
                    <?php
                        endforeach;
                    endforeach;
                    ?>
                    <?php if (!$has_banners): ?>
                        <tr><td colspan="10"><?php esc_html_e('There are no banners uploaded yet.', 'geo-ads-pro'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

        </div>
        <?php
    }
}
