<?php
/* Plugin Name: Geo Ads Pro - banner-service.php */
/* Date: 20260606 */
/* Author: Levent Cetin - 3CCS.com */

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_Banner_Service {

    private $regions;
    private $citymap;
    private $analytics;

    public function __construct($regions, $citymap, $analytics) {
        $this->regions = $regions;
        $this->citymap = $citymap;
        $this->analytics = $analytics;
    }

    public function resolve_region($mode, $region, $city) {
        $mode = in_array($mode, ['global', 'local'], true) ? $mode : 'global';
        $region = sanitize_text_field($region);
        $city = sanitize_text_field($city);

        if ($mode === 'local' && get_option('gap_enable_local_mode') && $city !== '') {
            // 1. Önce city map'te ara
            $mapped_region = $this->citymap->city_to_region($city);
            if ($mapped_region !== '') {
                return $mapped_region;
            }

            // 2. City map'te yoksa radius matching ile ara (şehrin koordinatlarına bak)
            $targeting_method = get_option('gap_local_targeting_method', 'city_map');
            if ($targeting_method === 'radius_matching' || $targeting_method === 'city_map') {
                $city_coords = gap_geocode_region_center($city);
                if ($city_coords['latitude'] != 0 || $city_coords['longitude'] != 0) {
                    $best_region = '';
                    $best_dist = PHP_INT_MAX;
                    foreach ($this->regions->get_all() as $r_name => $r_data) {
                        $lat = floatval($r_data['latitude'] ?? 0);
                        $lon = floatval($r_data['longitude'] ?? 0);
                        $radius = floatval($r_data['radius_km'] ?? 0);
                        if ($lat == 0 && $lon == 0) continue;
                        $dist = $this->haversine_distance($city_coords['latitude'], $city_coords['longitude'], $lat, $lon);
                        if ($radius > 0 && $dist <= $radius && $dist < $best_dist) {
                            $best_dist = $dist;
                            $best_region = $r_name;
                        }
                    }
                    if ($best_region !== '') {
                        return $best_region;
                    }
                }
            }
        }

        if ($region === '') {
            $region = sanitize_text_field(get_option('gap_default_region', ''));
        }

        $regions = $this->regions->get_all();
        return isset($regions[$region]) ? $region : '';
    }

    /**
     * Şehre göre bölgeyi tespit eder — fallback YOK.
     * Sadece city map veya radius matching ile eşleşirse dönder, yoksa '' dön.
     */
    public function resolve_region_strict($city) {
        $city = sanitize_text_field($city);
        if ($city === '') return '';

        // 1. City map'te ara
        $mapped = $this->citymap->city_to_region($city);
        if ($mapped !== '') return $mapped;

        // 2. Radius matching
        $city_coords = gap_geocode_region_center($city);
        if ($city_coords['latitude'] == 0 && $city_coords['longitude'] == 0) return '';

        $best_region = '';
        $best_dist = PHP_INT_MAX;
        foreach ($this->regions->get_all() as $r_name => $r_data) {
            $lat    = floatval($r_data['latitude'] ?? 0);
            $lon    = floatval($r_data['longitude'] ?? 0);
            $radius = floatval($r_data['radius_km'] ?? 0);
            if ($lat == 0 && $lon == 0 || $radius == 0) continue;
            $dist = $this->haversine_distance($city_coords['latitude'], $city_coords['longitude'], $lat, $lon);
            if ($dist <= $radius && $dist < $best_dist) {
                $best_dist   = $dist;
                $best_region = $r_name;
            }
        }

        return $best_region;
    }

    private function haversine_distance($lat1, $lon1, $lat2, $lon2) {
        $R = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
        return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
    }

    public function get_banner($mode, $region, $city, $banner_id = 0, $group_id = '') {
        $group_id = (string) $group_id;

        $region = $this->resolve_region($mode, $region, $city);
        if ($region === '') {
            return ['banner' => null, 'region' => ''];
        }

        // Rotasyon grubu varsa — o gruptan banner seç
        if ($group_id !== '' && $group_id !== '0') {
            $group = $this->find_rotation_group($group_id);
            if ($group && !empty($group['banner_ids'])) {
                // Çoklu bölge desteği — ziyaretçinin bölgesi grubun bölgelerinden biriyse göster
                $group_regions = $group['regions'] ?? [];
                // Eski format uyumu: tek string "region" alanı varsa array'e çevir
                if (empty($group_regions) && !empty($group['region']) && $group['region'] !== 'mixed') {
                    $group_regions = [$group['region']];
                }

                // Bölge kontrolü: ziyaretçinin bölgesi seçili bölgelerden biri olmalı
                if (!empty($group_regions) && !in_array($region, $group_regions, true)) {
                    return ['banner' => null, 'region' => $region];
                }

                // Sadece ziyaretçinin bölgesindeki banner'ları göster
                $all_banners = [];
                $region_data = $this->regions->get_region($region);
                foreach ($region_data['banners'] ?? [] as $b) {
                    if (in_array(intval($b['id']), $group['banner_ids'], true)) {
                        $all_banners[] = ['banner' => $b, 'region' => $region];
                    }
                }

                if (!empty($all_banners)) {
                    $pick = $all_banners[array_rand($all_banners)];
                    return [
                        'banner' => $pick['banner'],
                        'region' => $pick['region'],
                    ];
                }
            }
            return ['banner' => null, 'region' => $region];
        }

        $region_data = $this->regions->get_region($region);
        $all_banners = $region_data['banners'] ?? [];

        // Belirli bir banner_id varsa onu kullan (selected durumuna bakılmaksızın)
        if ($banner_id > 0) {
            foreach ($all_banners as $b) {
                if (intval($b['id']) === $banner_id) {
                    return [
                        'banner' => $b,
                        'region' => $region,
                    ];
                }
            }
            return ['banner' => null, 'region' => $region];
        }

        // Rotasyon için sadece selected banner'ları kullan
        $banners = array_filter($all_banners, fn($b) => !empty($b['selected']));

        if (empty($banners)) {
            return ['banner' => null, 'region' => $region];
        }

        $size_groups = [];
        foreach ($banners as $banner) {
            $key = intval($banner['width']) . 'x' . intval($banner['height']);
            $size_groups[$key][] = $banner;
        }

        $size = array_key_first($size_groups);
        $group = array_values($size_groups[$size]);
        $banner = $this->pick_banner($region, $size, $group);

        return [
            'banner' => $banner,
            'region' => $region,
        ];
    }

    public function get_banner_html($mode, $region, $city, $banner_id = 0, $group_id = '') {
        $result = $this->get_banner($mode, $region, $city, $banner_id, $group_id);
        $banner = $result['banner'];

        if (!$banner) {
            return ['html' => '', 'region' => $result['region']];
        }

        $src = $this->banner_image_url($result['region'], $banner['file']);
        $click_url = home_url('/?gap_click=' . intval($banner['id']));
        $target = isset($banner['link_target']) && $banner['link_target'] === '_self' ? '_self' : '_blank';
        $html = '<a href="' . esc_url($click_url) . '" target="' . esc_attr($target) . '" rel="noopener noreferrer">'
              . '<img class="gap-banner" data-banner-id="' . intval($banner['id']) . '" data-region="' . esc_attr($result['region']) . '" '
              . 'src="' . esc_url($src) . '" width="' . intval($banner['width']) . '" height="' . intval($banner['height']) . '" alt="">'
              . '</a>';

        return [
            'html' => $html,
            'region' => $result['region'],
            'banner' => $banner,
        ];
    }

    public function find_banner($banner_id) {
        $banner_id = intval($banner_id);
        foreach ($this->regions->get_all() as $region => $data) {
            foreach (($data['banners'] ?? []) as $banner) {
                if (intval($banner['id']) === $banner_id) {
                    return [
                        'region' => $region,
                        'banner' => $banner,
                    ];
                }
            }
        }

        return null;
    }

    public function find_rotation_group($group_id) {
        $group_id = (string) $group_id;
        $groups = (array) get_option('gap_rotation_groups', []);
        foreach ($groups as $group) {
            if ((string) $group['id'] === $group_id) {
                return $group;
            }
        }
        return null;
    }

    public function get_all_rotation_groups() {
        return (array) get_option('gap_rotation_groups', []);
    }

    public function save_rotation_group($group) {
        $groups = (array) get_option('gap_rotation_groups', []);

        // Mevcut grubu güncelle veya yeni ekle
        $found = false;
        foreach ($groups as &$g) {
            if ((string) $g['id'] === (string) $group['id']) {
                $g = $group;
                $found = true;
                break;
            }
        }

        // Yeni grup için ID üret — güvenli, tahmin edilemez
        if (!$found && empty($group['id'])) {
            $group['id'] = 'rg_' . bin2hex(random_bytes(16));
        }

        $groups[] = $group;

        // Çiftleri temizle
        $unique = [];
        foreach ($groups as $g) {
            $unique[$g['id']] = $g;
        }
        update_option('gap_rotation_groups', array_values($unique), false);
    }

    public function delete_rotation_group($group_id) {
        $groups = (array) get_option('gap_rotation_groups', []);
        $groups = array_values(array_filter($groups, fn($g) => (string) $g['id'] !== (string) $group_id));
        update_option('gap_rotation_groups', $groups, false);
    }

    public function banner_image_url($region, $file) {
        // Güvenli: bölge adı ve dosya yolu uploads dizini dışına çıkamaz mı kontrol et
        $base_dir = trailingslashit(gap_upload_base_dir());
        $safe_region = basename(gap_region_folder_name((string) $region));
        $safe_file   = basename((string) $file);

        // Path traversal koruması
        $full_path = realpath($base_dir . $safe_region . '/' . $safe_file);
        $expected  = realpath($base_dir . $safe_region);
        if ($full_path === false || $expected === false || strpos($full_path, $expected) !== 0) {
            return ''; // Erişim reddedildi — path traversal denemesi
        }

        return trailingslashit(gap_upload_base_url()) . rawurlencode($safe_region) . '/' . rawurlencode($safe_file);
    }

    private function pick_banner($region, $size, $group) {
        if (get_option('gap_abtest_auto')) {
            return $this->pick_best_ctr_banner($group);
        }

        if (get_option('gap_rotation_mode', 'random') === 'sequential') {
            return $this->pick_sequential_banner($region, $size, $group);
        }

        if (empty($group)) {
            return null; // Boş array → hata önleme
        }
        return $group[array_rand($group)];
    }

    private function pick_best_ctr_banner($group) {
        if (empty($group)) {
            return null;
        }
        $winner = null;
        $best_ctr = -1;

        foreach ($group as $banner) {
            $stats = $this->analytics->get_banner_stats($banner['id']);
            $impressions = intval($stats['impressions'] ?? 0);
            $clicks = intval($stats['clicks'] ?? 0);
            $ctr = $impressions > 0 ? $clicks / $impressions : 0;

            if ($ctr > $best_ctr) {
                $best_ctr = $ctr;
                $winner = $banner;
            }
        }

        return $winner ?: $group[array_rand($group)];
    }

    private function pick_sequential_banner($region, $size, $group) {
        $state = get_option('gap_rotation_state', []);
        $key = md5($region . '|' . $size);
        $index = intval($state[$key] ?? 0);
        $banner = $group[$index % count($group)];

        $state[$key] = ($index + 1) % count($group);
        update_option('gap_rotation_state', $state, false);

        return $banner;
    }
}
