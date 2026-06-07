<?php
// Date: 20260606
// Author: Levent Cetin - 3CCS.com

if (!defined('ABSPATH')) exit;

class Geo_Ads_Pro_Analytics {

    private $regions;
    private $file;
    private $data = [
        'summary' => [],
        'events'  => [],
    ];

    public function __construct($regions) {
        $this->regions = $regions;
        $this->file = gap_protected_json_path('analytics');
        $this->load();

        add_action('admin_menu', [$this, 'register_page'], 20);
        add_action('gap_monthly_report_cron', [$this, 'process_monthly_reports']);
        add_action('init', [$this, 'handle_report_download_request']);
    }

    private function normalize_data($raw) {
        $normalized = [
            'summary' => [],
            'events'  => [],
        ];

        if (!is_array($raw) || empty($raw)) {
            return $normalized;
        }

        if (isset($raw['summary']) || isset($raw['events'])) {
            $summary = isset($raw['summary']) && is_array($raw['summary']) ? $raw['summary'] : [];
            $events  = isset($raw['events']) && is_array($raw['events']) ? $raw['events'] : [];
            foreach ($summary as $banner_id => $stats) {
                if (!is_array($stats)) {
                    continue;
                }

                $id = (string) intval($banner_id);
                if ($id === '0') {
                    continue;
                }

                $normalized['summary'][$id] = [
                    'region'      => sanitize_text_field($stats['region'] ?? ''),
                    'impressions' => intval($stats['impressions'] ?? 0),
                    'clicks'      => intval($stats['clicks'] ?? 0),
                    'cities'      => isset($stats['cities']) && is_array($stats['cities']) ? $stats['cities'] : [],
                ];
            }

            foreach ($events as $event) {
                if (!is_array($event)) {
                    continue;
                }

                $normalized['events'][] = [
                    'ts'              => sanitize_text_field($event['ts'] ?? ''),
                    'type'            => sanitize_text_field($event['type'] ?? ''),
                    'banner_id'       => intval($event['banner_id'] ?? 0),
                    'region'          => sanitize_text_field($event['region'] ?? ''),
                    'city'            => sanitize_text_field($event['city'] ?? ''),
                    'customer_email'  => gap_sanitize_customer_email($event['customer_email'] ?? ''),
                    'destination_url' => gap_validate_click_url($event['destination_url'] ?? ''),
                    'file'            => sanitize_text_field($event['file'] ?? ''),
                ];
            }

            return $normalized;
        }

        foreach ($raw as $banner_id => $stats) {
            if (!is_array($stats)) {
                continue;
            }

            $id = (string) intval($banner_id);
            if ($id === '0') {
                continue;
            }

            $normalized['summary'][$id] = [
                'region'      => sanitize_text_field($stats['region'] ?? ''),
                'impressions' => intval($stats['impressions'] ?? 0),
                'clicks'      => intval($stats['clicks'] ?? 0),
                'cities'      => isset($stats['cities']) && is_array($stats['cities']) ? $stats['cities'] : [],
            ];
        }

        return $normalized;
    }

    private function load() {
        $this->data = $this->normalize_data(gap_read_json_file($this->file));
    }

    private function save() {
        gap_write_json_file($this->file, $this->data);
    }

    private function find_banner_meta($banner_id) {
        $banner_id = intval($banner_id);

        foreach ($this->regions->get_all() as $region => $data) {
            foreach (($data['banners'] ?? []) as $banner) {
                if (intval($banner['id'] ?? 0) === $banner_id) {
                    return [
                        'region' => $region,
                        'banner' => $banner,
                    ];
                }
            }
        }

        return null;
    }

    private function ensure_summary_entry($banner_id, $region) {
        $banner_id = (string) intval($banner_id);
        $region = sanitize_text_field($region);

        if ($banner_id === '0' || $region === '') {
            return false;
        }

        if (!isset($this->data['summary'][$banner_id])) {
            $this->data['summary'][$banner_id] = [
                'region'      => $region,
                'impressions' => 0,
                'clicks'      => 0,
                'cities'      => [],
            ];
        }

        if (!isset($this->data['summary'][$banner_id]['cities']) || !is_array($this->data['summary'][$banner_id]['cities'])) {
            $this->data['summary'][$banner_id]['cities'] = [];
        }

        return $banner_id;
    }

    private function increment_city_counter($banner_id, $city, $field) {
        $city = sanitize_text_field($city);
        if ($city === '') {
            return;
        }

        if (!isset($this->data['summary'][$banner_id]['cities'][$city])) {
            $this->data['summary'][$banner_id]['cities'][$city] = [
                'impressions' => 0,
                'clicks'      => 0,
            ];
        }

        $this->data['summary'][$banner_id]['cities'][$city][$field] = intval($this->data['summary'][$banner_id]['cities'][$city][$field] ?? 0) + 1;
    }

    private function append_event($type, $banner_id, $region, $city, $extra = []) {
        $meta = $this->find_banner_meta($banner_id);
        $banner = $meta['banner'] ?? [];

        $this->data['events'][] = array_merge([
            'ts'             => current_time('mysql'),
            'type'           => $type,
            'banner_id'      => intval($banner_id),
            'region'         => sanitize_text_field($region),
            'city'           => sanitize_text_field($city),
            'customer_email'  => gap_sanitize_customer_email($banner['customer_email'] ?? ''),
            'destination_url' => gap_validate_click_url($banner['url'] ?? ''),
            'file'           => basename((string) ($banner['file'] ?? '')),
        ], $extra);
    }

    public function record_impression($banner_id, $region, $city = '') {
        $banner_id = $this->ensure_summary_entry($banner_id, $region);
        if ($banner_id === false) {
            return;
        }

        $this->data['summary'][$banner_id]['region'] = sanitize_text_field($region);
        $this->data['summary'][$banner_id]['impressions'] = intval($this->data['summary'][$banner_id]['impressions'] ?? 0) + 1;
        $this->increment_city_counter($banner_id, $city, 'impressions');
        $this->append_event('impression', $banner_id, $region, $city);
        $this->save();
    }

    public function record_click($banner_id, $region, $city = '') {
        $banner_id = $this->ensure_summary_entry($banner_id, $region);
        if ($banner_id === false) {
            return;
        }

        $this->data['summary'][$banner_id]['region'] = sanitize_text_field($region);
        $this->data['summary'][$banner_id]['clicks'] = intval($this->data['summary'][$banner_id]['clicks'] ?? 0) + 1;
        $this->increment_city_counter($banner_id, $city, 'clicks');
        $this->append_event('click', $banner_id, $region, $city);
        $this->save();
    }

    public function get_banner_stats($banner_id) {
        $banner_id = (string) intval($banner_id);
        return $this->data['summary'][$banner_id] ?? [
            'region' => '',
            'impressions' => 0,
            'clicks' => 0,
            'cities' => [],
        ];
    }

    public function get_regions_with_stats() {
        $regions = $this->regions->get_all();

        foreach ($regions as $region => &$data) {
            if (empty($data['banners']) || !is_array($data['banners'])) {
                continue;
            }

            foreach ($data['banners'] as &$banner) {
                $banner_id = (string) intval($banner['id'] ?? 0);
                $stats = $this->data['summary'][$banner_id] ?? null;
                $banner['impressions'] = intval($stats['impressions'] ?? ($banner['impressions'] ?? 0));
                $banner['clicks'] = intval($stats['clicks'] ?? ($banner['clicks'] ?? 0));
                $banner['cities'] = isset($stats['cities']) && is_array($stats['cities']) ? $stats['cities'] : ($banner['cities'] ?? []);
            }
            unset($banner);
        }
        unset($data);

        return $regions;
    }

    public function reset() {
        $this->data = [
            'summary' => [],
            'events'  => [],
        ];
        $this->save();
        update_option('gap_monthly_report_history', [], false);
        update_option('gap_monthly_report_log', [], false);

        foreach ($this->regions->get_all() as $region => $data) {
            $banners = $data['banners'] ?? [];
            foreach ($banners as &$banner) {
                unset($banner['impressions'], $banner['clicks'], $banner['cities']);
            }
            $this->regions->update_banners($region, $banners);
        }
    }

    public function delete_banner($banner_id) {
        $banner_id = (string) intval($banner_id);
        if (isset($this->data['summary'][$banner_id])) {
            unset($this->data['summary'][$banner_id]);
        }

        if (!empty($this->data['events']) && is_array($this->data['events'])) {
            $this->data['events'] = array_values(array_filter($this->data['events'], function ($event) use ($banner_id) {
                return (string) intval($event['banner_id'] ?? 0) !== $banner_id;
            }));
        }

        $this->save();
    }

    public function delete_region($region) {
        $region = sanitize_text_field($region);

        foreach ($this->data['summary'] as $banner_id => $stats) {
            if (($stats['region'] ?? '') === $region) {
                unset($this->data['summary'][$banner_id]);
            }
        }

        if (!empty($this->data['events']) && is_array($this->data['events'])) {
            $this->data['events'] = array_values(array_filter($this->data['events'], function ($event) use ($region) {
                return sanitize_text_field($event['region'] ?? '') !== $region;
            }));
        }

        $this->save();
    }

    private function normalize_month($month) {
        $month = sanitize_text_field((string) $month);
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $month;
        }

        return current_time('Y-m');
    }

    private function event_month_matches($timestamp, $month) {
        $month = $this->normalize_month($month);
        $timestamp = (string) $timestamp;
        return $timestamp !== '' && substr($timestamp, 0, 7) === $month;
    }

    private function normalize_banner_filter($banner_ids = 0) {
        $filter = [];

        if (is_array($banner_ids)) {
            foreach ($banner_ids as $banner_id) {
                $banner_id = intval($banner_id);
                if ($banner_id > 0) {
                    $filter[(string) $banner_id] = $banner_id;
                }
            }
        } else {
            $banner_id = intval($banner_ids);
            if ($banner_id > 0) {
                $filter[(string) $banner_id] = $banner_id;
            }
        }

        return $filter;
    }

    private function build_monthly_rows($month, $banner_ids = 0) {
        $month = $this->normalize_month($month);
        $filter = $this->normalize_banner_filter($banner_ids);
        $rows = [];
        $index = [];

        foreach (($this->data['events'] ?? []) as $event) {
            if (!is_array($event)) {
                continue;
            }

            if (!$this->event_month_matches($event['ts'] ?? '', $month)) {
                continue;
            }

            $event_banner_id = intval($event['banner_id'] ?? 0);
            if ($event_banner_id <= 0) {
                continue;
            }

            if (!empty($filter) && !isset($filter[(string) $event_banner_id])) {
                continue;
            }

            $city = sanitize_text_field($event['city'] ?? '');
            $city_key = $city !== '' ? $city : __('Unknown', 'geo-ads-pro');
            $row_key = $event_banner_id . '|' . $city_key;

            if (!isset($index[$row_key])) {
                $meta = $this->find_banner_meta($event_banner_id);
                $banner = $meta['banner'] ?? [];
                $region = $meta['region'] ?? sanitize_text_field($event['region'] ?? '');

                $index[$row_key] = count($rows);
                $rows[] = [
                    'month'           => $month,
                    'banner_id'       => $event_banner_id,
                    'region'          => $region,
                    'file'            => basename((string) ($banner['file'] ?? ($event['file'] ?? ''))),
                    'customer_email'   => gap_sanitize_customer_email($banner['customer_email'] ?? ($event['customer_email'] ?? '')),
                    'destination_url'  => gap_validate_click_url($banner['url'] ?? ($event['destination_url'] ?? '')),
                    'city'            => $city_key,
                    'impressions'     => 0,
                    'clicks'          => 0,
                ];
            }

            $field = ($event['type'] ?? '') === 'click' ? 'clicks' : 'impressions';
            $rows[$index[$row_key]][$field] = intval($rows[$index[$row_key]][$field] ?? 0) + 1;
        }

        foreach ($rows as &$row) {
            $impressions = intval($row['impressions'] ?? 0);
            $clicks = intval($row['clicks'] ?? 0);
            $row['ctr'] = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;
        }
        unset($row);

        usort($rows, function ($a, $b) {
            if ($a['banner_id'] === $b['banner_id']) {
                return strcmp((string) $a['city'], (string) $b['city']);
            }

            return $a['banner_id'] <=> $b['banner_id'];
        });

        return $rows;
    }

    private function csv_escape($value) {
        $value = (string) $value;
        if ($value === '') {
            return '""';
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }

    private function csv_from_rows($rows, $month) {
        $month = $this->normalize_month($month);
        $headers = [
            'month',
            'banner_id',
            'region',
            'file',
            'customer_email',
            'destination_url',
            'city',
            'impressions',
            'clicks',
            'ctr',
        ];

        $lines = [implode(',', $headers)];
        foreach ($rows as $row) {
            $lines[] = implode(',', [
                $this->csv_escape($month),
                $this->csv_escape($row['banner_id'] ?? ''),
                $this->csv_escape($row['region'] ?? ''),
                $this->csv_escape($row['file'] ?? ''),
                $this->csv_escape($row['customer_email'] ?? ''),
                $this->csv_escape($row['destination_url'] ?? ''),
                $this->csv_escape($row['city'] ?? ''),
                $this->csv_escape($row['impressions'] ?? 0),
                $this->csv_escape($row['clicks'] ?? 0),
                $this->csv_escape($row['ctr'] ?? 0),
            ]);
        }

        return "\xEF\xBB\xBF" . implode("\n", $lines) . "\n";
    }

    public function generate_monthly_csv($month, $banner_ids = 0, $label = null) {
        $rows = $this->build_monthly_rows($month, $banner_ids);
        $month = $this->normalize_month($month);
        $filter = $this->normalize_banner_filter($banner_ids);
        if ($label === null || $label === '') {
            if (count($filter) === 1) {
                $single_banner_id = intval(reset($filter));
                $label = 'banner-' . $single_banner_id;
            } elseif (!empty($filter)) {
                $label = 'filtered-banners';
            } else {
                $label = 'all-banners';
            }
        }

        return [
            'month' => $month,
            'rows'  => $rows,
            'csv'   => $this->csv_from_rows($rows, $month),
            'filename' => 'geo-ads-pro-report-' . $month . '-' . $label . '.csv',
        ];
    }

    public function send_monthly_report($month, $recipient_email = '', $banner_ids = 0, $label = null) {
        $recipient_email = gap_sanitize_customer_email($recipient_email);
        $report = $this->generate_monthly_csv($month, $banner_ids, $label);

        if ($recipient_email === '') {
            $filter = $this->normalize_banner_filter($banner_ids);
            if (count($filter) === 1) {
                $banner_id = intval(reset($filter));
                $meta = $this->find_banner_meta($banner_id);
                $recipient_email = gap_sanitize_customer_email($meta['banner']['customer_email'] ?? '');
            }
        }

        if ($recipient_email === '') {
            return new WP_Error('gap_report_email_missing', __('A valid email address is required.', 'geo-ads-pro'));
        }

        $download_url = $this->create_monthly_report_download_url($month, $banner_ids, $label);
        $temp = wp_tempnam($report['filename']);
        if (!$temp) {
            return new WP_Error('gap_report_temp_failed', __('Could not create a temporary report file.', 'geo-ads-pro'));
        }

        file_put_contents($temp, $report['csv']);

        $attachments = [$temp];
        $banner_attachments = $this->get_banner_attachment_paths($banner_ids);
        if (!empty($banner_attachments)) {
            $attachments = array_merge($attachments, $banner_attachments);
        }

        $subject = sprintf(__('Geo Ads Pro Monthly Report (%s)', 'geo-ads-pro'), $report['month']);
        $attachment_count = count($attachments) - 1;
        $message = $this->build_monthly_report_email_html($report, $download_url, $banner_ids, $attachment_count);
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $sent = wp_mail($recipient_email, $subject, $message, $headers, $attachments);
        @unlink($temp);

        if (!$sent) {
            return new WP_Error('gap_report_mail_failed', __('The email could not be sent.', 'geo-ads-pro'));
        }

        return true;
    }

    private function create_monthly_report_download_url($month, $banner_ids = 0, $label = null) {
        $token = wp_generate_password(24, false, false);
        $payload = [
            'month'      => $this->normalize_month($month),
            'banner_ids' => array_values($this->normalize_banner_filter($banner_ids)),
            'label'      => $label,
        ];

        set_transient('gap_report_download_' . $token, $payload, 7 * DAY_IN_SECONDS);

        return add_query_arg('gap_report_download', rawurlencode($token), home_url('/'));
    }

    public function handle_report_download_request() {
        $token = sanitize_text_field($_GET['gap_report_download'] ?? '');
        if ($token === '') {
            return;
        }

        $payload = get_transient('gap_report_download_' . $token);
        if (!is_array($payload)) {
            wp_die(esc_html__('Report link expired or invalid.', 'geo-ads-pro'));
        }

        $report = $this->generate_monthly_csv(
            $payload['month'] ?? current_time('Y-m'),
            $payload['banner_ids'] ?? 0,
            $payload['label'] ?? null
        );

        delete_transient('gap_report_download_' . $token);

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $report['filename'] . '"');
        echo $report['csv'];
        exit;
    }

    private function build_monthly_report_email_html($report, $download_url, $banner_ids, $attachment_count) {
        $attachments_note = $attachment_count > 0
            ? sprintf(_n('%d banner image is attached with the report.', '%d banner images are attached with the report.', $attachment_count, 'geo-ads-pro'), $attachment_count)
            : __('No banner image attachments were available for this report.', 'geo-ads-pro');

        $banner_cards = $this->build_banner_preview_cards_html($banner_ids);

        $html  = '<div style="font-family:Arial,Helvetica,sans-serif;line-height:1.6;color:#1d2327;">';
        $html .= '<h2 style="margin:0 0 12px;">' . esc_html__('Geo Ads Pro Monthly Report', 'geo-ads-pro') . '</h2>';
        $html .= '<p>' . esc_html(sprintf(__('Your monthly CSV report for %s is ready.', 'geo-ads-pro'), $report['month'])) . '</p>';
        $html .= '<p>' . esc_html($attachments_note) . '</p>';
        $html .= '<p style="margin:18px 0;">'
              . '<a href="' . esc_url($download_url) . '" style="display:inline-block;padding:12px 18px;background:#2271b1;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">'
              . esc_html__('Download CSV', 'geo-ads-pro')
              . '</a>'
              . '</p>';

        if ($banner_cards !== '') {
            $html .= '<h3 style="margin:24px 0 12px;">' . esc_html__('Banner Previews', 'geo-ads-pro') . '</h3>';
            $html .= $banner_cards;
        }

        $html .= '<p style="margin-top:24px;">' . esc_html__('Best regards.', 'geo-ads-pro') . '</p>';
        $html .= '</div>';

        return $html;
    }

    private function build_banner_preview_cards_html($banner_ids) {
        $banner_ids = $this->normalize_banner_filter($banner_ids);
        if (empty($banner_ids)) {
            return '';
        }

        $cards = '';
        foreach ($banner_ids as $banner_id) {
            $meta = $this->find_banner_meta($banner_id);
            $region = $meta['region'] ?? '';
            $banner = $meta['banner'] ?? [];
            $file = basename((string) ($banner['file'] ?? ''));
            $image_url = ($region !== '' && $file !== '') ? $this->banner_image_url($region, $file) : '';

            $cards .= '<div style="border:1px solid #dcdcde;border-radius:8px;padding:14px;margin:0 0 14px;background:#fff;">';
            $cards .= '<div style="font-weight:700;margin-bottom:8px;">' . esc_html(sprintf(__('Banner #%d', 'geo-ads-pro'), $banner_id)) . '</div>';

            if ($image_url !== '') {
                $cards .= '<div style="margin-bottom:10px;"><img src="' . esc_url($image_url) . '" alt="" style="max-width:220px;height:auto;display:block;border-radius:6px;"></div>';
            }

            $cards .= '<div style="font-size:13px;color:#50575e;">';
            $cards .= esc_html(sprintf(__('Region: %s', 'geo-ads-pro'), $region !== '' ? $region : '-')) . '<br>';
            $cards .= esc_html(sprintf(__('File: %s', 'geo-ads-pro'), $file !== '' ? $file : '-'));
            $cards .= '</div>';
            $cards .= '</div>';
        }

        return $cards;
    }

    private function get_banner_attachment_paths($banner_ids = 0) {
        $banner_ids = $this->normalize_banner_filter($banner_ids);
        if (empty($banner_ids)) {
            return [];
        }

        $base_dir = realpath(gap_upload_base_dir());
        if (!$base_dir) {
            return [];
        }

        $paths = [];
        foreach ($banner_ids as $banner_id) {
            $meta = $this->find_banner_meta($banner_id);
            $banner = $meta['banner'] ?? [];
            $region = $meta['region'] ?? '';
            $file = basename((string) ($banner['file'] ?? ''));

            if ($region === '' || $file === '') {
                continue;
            }

            $path = trailingslashit(gap_upload_base_dir()) . gap_region_folder_name($region) . '/' . $file;
            $real = realpath($path);
            if (!$real || !is_file($real) || strpos($real, $base_dir . DIRECTORY_SEPARATOR) !== 0) {
                continue;
            }

            $paths[] = $real;
        }

        return array_values(array_unique($paths));
    }

    private function get_monthly_report_history() {
        $history = get_option('gap_monthly_report_history', []);
        return is_array($history) ? $history : [];
    }

    private function update_monthly_report_history(array $history) {
        update_option('gap_monthly_report_history', $history, false);
    }

    private function get_monthly_report_log() {
        $log = get_option('gap_monthly_report_log', []);
        return is_array($log) ? $log : [];
    }

    private function update_monthly_report_log(array $log) {
        update_option('gap_monthly_report_log', $log, false);
    }

    private function record_monthly_report_log($email, array $banner_ids, $month, $report) {
        $log = $this->get_monthly_report_log();
        $email = $this->normalize_report_email($email);
        $month = $this->normalize_month($month);
        $banner_ids = array_values(array_unique(array_filter(array_map('intval', $banner_ids))));
        sort($banner_ids);

        if ($email === '') {
            return;
        }

        $key = 'email:' . $email;
        $log[$key] = [
            'email'         => $email,
            'month'         => $month,
            'last_sent_at'  => current_time('mysql'),
            'banner_ids'    => $banner_ids,
            'banner_count'  => count($banner_ids),
            'filename'      => is_array($report) ? ($report['filename'] ?? '') : '',
        ];

        update_option('gap_monthly_report_log', $log, false);
    }

    private function get_monthly_report_log_rows() {
        $rows = [];

        foreach ($this->get_monthly_report_log() as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $banner_ids = isset($entry['banner_ids']) && is_array($entry['banner_ids']) ? array_values(array_map('intval', $entry['banner_ids'])) : [];
            $rows[] = [
                'email'        => gap_sanitize_customer_email($entry['email'] ?? ''),
                'month'        => sanitize_text_field($entry['month'] ?? ''),
                'last_sent_at' => sanitize_text_field($entry['last_sent_at'] ?? ''),
                'banner_count' => intval($entry['banner_count'] ?? count($banner_ids)),
                'banner_ids'   => $banner_ids,
                'filename'     => sanitize_text_field($entry['filename'] ?? ''),
            ];
        }

        usort($rows, function ($a, $b) {
            return strcmp((string) ($b['last_sent_at'] ?? ''), (string) ($a['last_sent_at'] ?? ''));
        });

        return $rows;
    }

    private function report_history_key_for_email($email) {
        $email = $this->normalize_report_email($email);
        return $email !== '' ? 'email:' . $email : '';
    }

    private function report_history_key_for_banner($banner_id) {
        $banner_id = intval($banner_id);
        return $banner_id > 0 ? 'banner:' . $banner_id : '';
    }

    private function normalize_report_email($email) {
        $email = gap_sanitize_customer_email($email);
        return $email !== '' ? strtolower($email) : '';
    }

    private function report_already_sent_for_group(array $history, $email, array $banner_ids, $month) {
        $month = $this->normalize_month($month);
        $email_key = $this->report_history_key_for_email($email);
        if ($email_key !== '' && ($history[$email_key] ?? '') === $month) {
            return true;
        }

        foreach ($banner_ids as $banner_id) {
            $banner_id = intval($banner_id);
            if ($banner_id <= 0) {
                continue;
            }

            $legacy_key = (string) $banner_id;
            $banner_key = $this->report_history_key_for_banner($banner_id);

            if (($history[$legacy_key] ?? '') === $month || ($history[$banner_key] ?? '') === $month) {
                return true;
            }
        }

        return false;
    }

    private function mark_report_sent_for_group(array &$history, $email, array $banner_ids, $month) {
        $month = $this->normalize_month($month);
        $email_key = $this->report_history_key_for_email($email);
        if ($email_key !== '') {
            $history[$email_key] = $month;
        }

        foreach ($banner_ids as $banner_id) {
            $banner_id = intval($banner_id);
            if ($banner_id <= 0) {
                continue;
            }

            $history[(string) $banner_id] = $month;
            $history[$this->report_history_key_for_banner($banner_id)] = $month;
        }
    }

    private function get_previous_month_label() {
        return wp_date('Y-m', strtotime('first day of previous month', current_time('timestamp')));
    }

    private function get_monthly_report_start_month() {
        $start_month = sanitize_text_field(get_option('gap_monthly_report_start_month', current_time('Y-m')));
        return preg_match('/^\d{4}-\d{2}$/', $start_month) ? $start_month : current_time('Y-m');
    }

    private function get_banners_grouped_by_customer_email() {
        $banner_map = [];

        foreach ($this->regions->get_all() as $region => $data) {
            foreach (($data['banners'] ?? []) as $banner) {
                $banner_id = intval($banner['id'] ?? 0);
                $email = $this->normalize_report_email($banner['customer_email'] ?? '');

                if ($banner_id > 0 && $email !== '') {
                    if (!isset($banner_map[$email])) {
                        $banner_map[$email] = [];
                    }
                    $banner_map[$email][] = $banner_id;
                }
            }
        }

        foreach ($banner_map as $email => $banner_ids) {
            $banner_ids = array_values(array_unique(array_map('intval', $banner_ids)));
            sort($banner_ids);
            $banner_map[$email] = $banner_ids;
        }

        return $banner_map;
    }

    public function process_monthly_reports() {
        if (!get_option('gap_auto_monthly_reports')) {
            return 0;
        }

        $target_month = $this->get_previous_month_label();
        $start_month = $this->get_monthly_report_start_month();
        if (strcmp($target_month, $start_month) < 0) {
            return 0;
        }

        $history = $this->get_monthly_report_history();
        $sent_count = 0;

        foreach ($this->get_banners_grouped_by_customer_email() as $email => $banner_ids) {
            if ($this->report_already_sent_for_group($history, $email, $banner_ids, $target_month)) {
                continue;
            }

            $result = $this->send_monthly_report($target_month, $email, $banner_ids, 'customer-' . substr(md5($email), 0, 8));
            if (!is_wp_error($result)) {
                $this->mark_report_sent_for_group($history, $email, $banner_ids, $target_month);
                $this->record_monthly_report_log($email, $banner_ids, $target_month, $result);
                $sent_count++;
            }
        }

        if ($sent_count > 0) {
            $this->update_monthly_report_history($history);
        }

        return $sent_count;
    }

    private function render_city_breakdown($cities) {
        if (empty($cities) || !is_array($cities)) {
            return '-';
        }

        uasort($cities, function ($a, $b) {
            $a_total = intval($a['impressions'] ?? 0) + intval($a['clicks'] ?? 0);
            $b_total = intval($b['impressions'] ?? 0) + intval($b['clicks'] ?? 0);
            return $b_total <=> $a_total;
        });

        $parts = [];
        foreach (array_slice($cities, 0, 3, true) as $city => $stats) {
            $parts[] = sprintf(
                '%s (%d/%d)',
                $city,
                intval($stats['impressions'] ?? 0),
                intval($stats['clicks'] ?? 0)
            );
        }

        return implode(', ', $parts);
    }

    public function register_page() {

        add_submenu_page(
            'geo-ads-pro',
            __('Analytics', 'geo-ads-pro'),
            __('Analytics', 'geo-ads-pro'),
            'manage_options',
            'geo-ads-pro-analytics',
            [$this, 'render_page']
        );
    }

    public function render_page() {

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'geo-ads-pro'));
        }

        $report_notice = '';
        $report_error = '';

        if (isset($_POST['gap_report_action'])) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'gap_monthly_report_nonce')) {
                wp_die(esc_html__('Invalid request.', 'geo-ads-pro'));
            }

            $action = sanitize_text_field($_POST['gap_report_action']);
            $month = sanitize_text_field($_POST['gap_report_month'] ?? current_time('Y-m'));
            $banner_id = intval($_POST['gap_report_banner_id'] ?? 0);
            $email = sanitize_email($_POST['gap_report_email'] ?? '');

            if ($action === 'download') {
                $report = $this->generate_monthly_csv($month, $banner_id);
                nocache_headers();
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . $report['filename'] . '"');
                echo $report['csv'];
                exit;
            }

            if ($action === 'email') {
                $result = $this->send_monthly_report($month, $email, $banner_id);
                if (is_wp_error($result)) {
                    $report_error = $result->get_error_message();
                } else {
                    $report_notice = __('Monthly report emailed successfully.', 'geo-ads-pro');
                }
            }
        }

        $regions = $this->get_regions_with_stats();
        $rows = [];
        $banner_options = [];
        $report_log_rows = $this->get_monthly_report_log_rows();

        foreach ($regions as $region => $data) {
            foreach (($data['banners'] ?? []) as $banner) {
                $impressions = intval($banner['impressions'] ?? 0);
                $clicks = intval($banner['clicks'] ?? 0);
                $rows[] = [
                    'region' => $region,
                    'id' => intval($banner['id']),
                    'file' => $banner['file'] ?? '',
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
                    'email' => $banner['customer_email'] ?? '',
                    'cities' => $this->render_city_breakdown($banner['cities'] ?? []),
                ];

                $banner_options[] = [
                    'id' => intval($banner['id']),
                    'label' => sprintf(
                        '%s / #%d / %s',
                        $region,
                        intval($banner['id']),
                        !empty($banner['customer_email']) ? $banner['customer_email'] : __('No email', 'geo-ads-pro')
                    ),
                ];
            }
        }

        $selected_month = isset($_POST['gap_report_month']) ? sanitize_text_field($_POST['gap_report_month']) : current_time('Y-m');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Geo Ads Pro – Analytics', 'geo-ads-pro'); ?></h1>

            <p><?php esc_html_e('This screen displays click, impression, city, and monthly report statistics.', 'geo-ads-pro'); ?></p>

            <?php if ($report_notice !== ''): ?>
                <div class="updated notice"><p><?php echo esc_html($report_notice); ?></p></div>
            <?php endif; ?>

            <?php if ($report_error !== ''): ?>
                <div class="error notice"><p><?php echo esc_html($report_error); ?></p></div>
            <?php endif; ?>

            <details style="margin: 16px 0 24px; padding: 14px 16px; border: 1px solid #dcdcde; border-radius: 8px; background:#fff;">
                <summary style="cursor:pointer; font-weight:600; margin-bottom: 10px;"><?php esc_html_e('Monthly Report', 'geo-ads-pro'); ?></summary>
                <form method="post" style="margin-top: 14px;">
                    <?php wp_nonce_field('gap_monthly_report_nonce'); ?>
                    <table class="form-table" style="max-width: 820px;">
                        <tr>
                            <th scope="row"><label for="gap_report_month"><?php esc_html_e('Month', 'geo-ads-pro'); ?></label></th>
                            <td><input type="month" id="gap_report_month" name="gap_report_month" value="<?php echo esc_attr($selected_month); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="gap_report_banner_id"><?php esc_html_e('Banner', 'geo-ads-pro'); ?></label></th>
                            <td>
                                <select id="gap_report_banner_id" name="gap_report_banner_id">
                                    <option value="0"><?php esc_html_e('All banners', 'geo-ads-pro'); ?></option>
                                    <?php foreach ($banner_options as $option): ?>
                                        <option value="<?php echo esc_attr($option['id']); ?>" <?php selected(intval($_POST['gap_report_banner_id'] ?? 0), $option['id']); ?>>
                                            <?php echo esc_html($option['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="gap_report_email"><?php esc_html_e('Email Override', 'geo-ads-pro'); ?></label></th>
                            <td>
                                <input type="email" id="gap_report_email" name="gap_report_email" placeholder="customer@example.com" value="<?php echo esc_attr(sanitize_email($_POST['gap_report_email'] ?? '')); ?>">
                                <p class="description"><?php esc_html_e('Leave empty to use the selected banner’s stored customer email.', 'geo-ads-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <p>
                        <button type="submit" class="button button-primary" name="gap_report_action" value="download"><?php esc_html_e('Download CSV', 'geo-ads-pro'); ?></button>
                        <button type="submit" class="button button-secondary" name="gap_report_action" value="email"><?php esc_html_e('Send by Email', 'geo-ads-pro'); ?></button>
                    </p>
                </form>
            </details>

            <details style="margin: 16px 0 24px; padding: 14px 16px; border: 1px solid #dcdcde; border-radius: 8px; background:#fff;">
                <summary style="cursor:pointer; font-weight:600; margin-bottom: 10px;"><?php esc_html_e('Automatic Report History', 'geo-ads-pro'); ?></summary>
                <div style="margin-top: 14px;">
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Customer Email', 'geo-ads-pro'); ?></th>
                                <th><?php esc_html_e('Month', 'geo-ads-pro'); ?></th>
                                <th><?php esc_html_e('Last Sent', 'geo-ads-pro'); ?></th>
                                <th><?php esc_html_e('Banners', 'geo-ads-pro'); ?></th>
                                <th><?php esc_html_e('Banner IDs', 'geo-ads-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($report_log_rows)): ?>
                                <?php foreach ($report_log_rows as $report_row): ?>
                                    <tr>
                                        <td><?php echo esc_html($report_row['email']); ?></td>
                                        <td><?php echo esc_html($report_row['month']); ?></td>
                                        <td><?php echo esc_html($report_row['last_sent_at']); ?></td>
                                        <td><?php echo esc_html($report_row['banner_count']); ?></td>
                                        <td><?php echo esc_html(implode(', ', array_map('intval', $report_row['banner_ids']))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5"><?php esc_html_e('No automatic monthly reports have been sent yet.', 'geo-ads-pro'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </details>

            <canvas id="gapChart" width="800" height="400"></canvas>

            <table class="widefat striped gap-analytics-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Region', 'geo-ads-pro'); ?></th>
                        <th>ID</th>
                        <th><?php esc_html_e('File', 'geo-ads-pro'); ?></th>
                        <th><?php esc_html_e('Customer Email', 'geo-ads-pro'); ?></th>
                        <th><?php esc_html_e('Impressions', 'geo-ads-pro'); ?></th>
                        <th><?php esc_html_e('Clicks', 'geo-ads-pro'); ?></th>
                        <th>CTR</th>
                        <th><?php esc_html_e('Top Cities', 'geo-ads-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row['region']); ?></td>
                                <td><?php echo esc_html($row['id']); ?></td>
                                <td><?php echo esc_html($row['file']); ?></td>
                                <td><?php echo esc_html($row['email']); ?></td>
                                <td><?php echo esc_html($row['impressions']); ?></td>
                                <td><?php echo esc_html($row['clicks']); ?></td>
                                <td><?php echo esc_html($row['ctr']); ?>%</td>
                                <td><?php echo esc_html($row['cities']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8"><?php esc_html_e('No analytics data yet.', 'geo-ads-pro'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <script>
                window.GAP_ANALYTICS = <?php echo wp_json_encode($regions, JSON_UNESCAPED_UNICODE); ?>;
            </script>
        </div>
        <?php
    }
}
