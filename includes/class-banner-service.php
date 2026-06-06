<?php
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

        if ($mode === 'local' && get_option('gap_enable_local_mode')) {
            $mapped_region = $this->citymap->city_to_region($city);
            if ($mapped_region !== '') {
                $region = $mapped_region;
            }
        }

        if ($region === '') {
            $region = sanitize_text_field(get_option('gap_default_region', ''));
        }

        $regions = $this->regions->get_all();
        return isset($regions[$region]) ? $region : '';
    }

    public function get_banner($mode, $region, $city) {
        $region = $this->resolve_region($mode, $region, $city);
        if ($region === '') {
            return ['banner' => null, 'region' => ''];
        }

        $region_data = $this->regions->get_region($region);
        $banners = array_filter($region_data['banners'] ?? [], fn($b) => !empty($b['selected']));
        if (empty($banners)) {
            return ['banner' => null, 'region' => $region];
        }

        $groups = [];
        foreach ($banners as $banner) {
            $key = intval($banner['width']) . 'x' . intval($banner['height']);
            $groups[$key][] = $banner;
        }

        $size = array_key_first($groups);
        $group = array_values($groups[$size]);
        $banner = $this->pick_banner($region, $size, $group);

        return [
            'banner' => $banner,
            'region' => $region,
        ];
    }

    public function get_banner_html($mode, $region, $city) {
        $result = $this->get_banner($mode, $region, $city);
        $banner = $result['banner'];

        if (!$banner) {
            return ['html' => '', 'region' => $result['region']];
        }

        $src = $this->banner_image_url($result['region'], $banner['file']);
        $click_url = home_url('/?gap_click=' . intval($banner['id']));
        $html = '<a href="' . esc_url($click_url) . '" target="_blank" rel="noopener noreferrer">'
              . '<img class="gap-banner" data-banner-id="' . intval($banner['id']) . '" '
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

    public function banner_image_url($region, $file) {
        return trailingslashit(gap_upload_base_url()) . rawurlencode(gap_region_folder_name($region)) . '/' . rawurlencode(basename($file));
    }

    private function pick_banner($region, $size, $group) {
        if (get_option('gap_abtest_auto')) {
            return $this->pick_best_ctr_banner($group);
        }

        if (get_option('gap_rotation_mode', 'random') === 'sequential') {
            return $this->pick_sequential_banner($region, $size, $group);
        }

        return $group[array_rand($group)];
    }

    private function pick_best_ctr_banner($group) {
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
