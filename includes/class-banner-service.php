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

    public function resolve_region($mode, $region, $city, $latitude = null, $longitude = null) {
        $mode = in_array($mode, ['global', 'local'], true) ? $mode : 'global';
        $region = sanitize_text_field($region);
        $city = sanitize_text_field($city);
        $latitude = is_numeric($latitude) ? floatval($latitude) : null;
        $longitude = is_numeric($longitude) ? floatval($longitude) : null;
        $regions = $this->regions->get_all();

        if ($mode === 'local' && get_option('gap_enable_local_mode')) {
            $targeting_method = get_option('gap_local_targeting_method', 'city_map');

            if ($targeting_method === 'radius') {
                $radius_region = $this->region_by_radius($regions, $latitude, $longitude);
                if ($radius_region !== '') {
                    $region = $radius_region;
                }
            } else {
                $mapped_region = $this->citymap->city_to_region($city);
                if ($mapped_region !== '') {
                    $region = $mapped_region;
                } elseif ($city !== '' && isset($regions[$city])) {
                    $region = $city;
                }
            }
        }

        if ($region === '') {
            $region = sanitize_text_field(get_option('gap_default_region', ''));
        }

        return isset($regions[$region]) ? $region : '';
    }

    private function region_by_radius($regions, $latitude, $longitude) {
        if ($latitude === null || $longitude === null) {
            return '';
        }

        $best_region = '';
        $best_distance = null;
        $best_inside_region = '';
        $best_inside_distance = null;

        foreach ($regions as $region => $data) {
            $region_latitude = $data['latitude'] ?? null;
            $region_longitude = $data['longitude'] ?? null;
            $radius_km = floatval($data['radius_km'] ?? 0);

            if ((!is_numeric($region_latitude) || !is_numeric($region_longitude)) && $radius_km > 0) {
                $coords = gap_geocode_region_center($region);
                if ($coords) {
                    $region_latitude = $coords['latitude'];
                    $region_longitude = $coords['longitude'];
                    $this->regions->update_region_targeting($region, [
                        'latitude'  => $region_latitude,
                        'longitude' => $region_longitude,
                        'radius_km' => $radius_km,
                    ]);
                }
            }

            if (!is_numeric($region_latitude) || !is_numeric($region_longitude) || $radius_km <= 0) {
                continue;
            }

            $distance = $this->distance_km($latitude, $longitude, floatval($region_latitude), floatval($region_longitude));
            if ($best_distance === null || $distance < $best_distance) {
                $best_region = (string) $region;
                $best_distance = $distance;
            }

            if ($distance <= $radius_km && ($best_inside_distance === null || $distance < $best_inside_distance)) {
                $best_inside_region = (string) $region;
                $best_inside_distance = $distance;
            }
        }

        return $best_inside_region !== '' ? $best_inside_region : $best_region;
    }

    private function distance_km($lat1, $lon1, $lat2, $lon2) {
        $earth_radius_km = 6371;
        $dlat = deg2rad($lat2 - $lat1);
        $dlon = deg2rad($lon2 - $lon1);

        $a = sin($dlat / 2) * sin($dlat / 2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dlon / 2) * sin($dlon / 2);

        return $earth_radius_km * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    public function get_banner($mode, $region, $city, $latitude = null, $longitude = null) {
        $region = $this->resolve_region($mode, $region, $city, $latitude, $longitude);
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

    public function get_banner_html($mode, $region, $city, $latitude = null, $longitude = null) {
        $result = $this->get_banner($mode, $region, $city, $latitude, $longitude);
        $banner = $result['banner'];

        if (!$banner) {
            return ['html' => '', 'region' => $result['region']];
        }

        $src = $this->banner_image_url($result['region'], $banner['file']);
        $click_args = [
            'gap_click' => intval($banner['id']),
        ];
        if ($city !== '') {
            $click_args['gap_city'] = $city;
        }
        $click_url = add_query_arg($click_args, home_url('/'));
        $html = '<a href="' . esc_url($click_url) . '" target="_blank" rel="noopener noreferrer">'
              . '<img class="gap-banner" data-banner-id="' . intval($banner['id']) . '" '
              . 'src="' . esc_url($src) . '" width="' . intval($banner['width']) . '" height="' . intval($banner['height']) . '" alt="">'
              . '</a>';

        return [
            'html' => $html,
            'region' => $result['region'],
            'click_url' => $click_url,
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
