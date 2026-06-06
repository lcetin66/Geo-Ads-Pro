# Geo Ads Pro

<p align="center">
  <img src="https://img.shields.io/badge/WordPress-5.0%2B-blue.svg" alt="WordPress Version">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-blue.svg" alt="PHP Version">
  <img src="https://img.shields.io/badge/License-MIT-green.svg" alt="License">
  <a href="https://github.com/lcetin66/Geo-Ads-Pro/issues">
    <img src="https://img.shields.io/github/issues/lcetin66/Geo-Ads-Pro" alt="Issues">
  </a>
  <a href="https://github.com/lcetin66/Geo-Ads-Pro/stargazers">
    <img src="https://img.shields.io/github/stars/lcetin66/Geo-Ads-Pro?style=social" alt="Stars">
  </a>
</p>

<p align="center">
  <a href="#-features"><strong>Explore Features</strong></a> •
  <a href="#-directory-structure"><strong>Directory Structure</strong></a> •
  <a href="#-installation"><strong>Installation</strong></a> •
  <a href="#-usage"><strong>Usage</strong></a>
</p>

---

Geo Ads Pro is a professional GEO-targeted banner and advertisement management plugin for WordPress. It allows you to display area-specific banner advertisements, map cities to regions, perform A/B testing, view interactive analytics, and utilize widgets, shortcodes, and a REST API endpoint.

---

## 🚀 Features

### 🎯 Ad Management
- **Region Creation:** Easily organize banners by region.
- **Banner Uploader:** Upload and manage image banners.
- **Auto-Dimension Detection:** Automatically detects banner width and height.
- **Selective Display:** Easily toggle active/inactive banners.
- **Click Tracking URLs:** Assign custom redirection links to each banner.
- **Smart Banner Rotation:** Supports random and sequential rotation for selected banners of the same size.
- **Delete Management:** Remove regions, banners, and city mappings from the admin panel.

### 🌍 GEO Targeting
- **City-to-Region Mapping:** Map individual cities to broader regions.
- **Local (IP-based) Mode:** Auto-detects visitor cities and displays localized banners when local mode is enabled.
- **Global fallback mode:** Serves fallback regional banners globally.
- **Secure Backend IP Lookup:** Reliable server-side visitor city mapping.

### 📊 Analytics
- **Click Tracking:** Records every time a banner is clicked.
- **Impression Tracking:** Counts views for individual banners.
- **Separate Analytics Store:** Keeps tracking counters in a protected `analytics.json.php` file instead of mutating banner configuration on every view.
- **Impression Throttling:** Prevents rapid duplicate impression writes per visitor/banner for a short interval.
- **CTR Calculation:** Automatically calculates Click-Through Rates.
- **Visual Analytics:** Interactive charts representing performance across regions (Chart.js integration).

### 🧪 A/B Testing System
- **Variant Grouping:** Banners of the same size are automatically grouped as variants.
- **Performance Evaluation:** Monitors and displays the CTR performance of each variant.
- **Winner Declaration:** Identifies the highest-performing variant.
- **Auto Optimization:** Optional setting can prefer the highest-CTR banner in a variant group.

### 🧩 Integrations
- **Widget Support:** Custom WordPress Widget for drag-and-drop integration.
- **Shortcode Support:** Embed banners anywhere using:
  ```text
  [geo_ads_pro mode="global" region="NRW"]
  ```
- **REST API Support:** Fetch banner details programmatically via:
  ```text
  GET /wp-json/geo-ads-pro/v1/banner?mode=global&region=NRW
  ```

### 🔐 Security
- **Anti-CSRF Protection:** Nonces used for all administrative settings.
- **XSS Prevention:** Strict sanitization and output escaping.
- **Upload Restrictions:** Restricts uploader to authorized image formats.
- **Access Control:** `.htaccess` rules generated to deny direct HTTP access to JSON files.
- **Secure Redirection:** Safe redirect endpoint validation.
- **Public Endpoint Throttling:** AJAX, REST, impression, and click flows include nonce/rate-limit/throttle protections where appropriate.
- **Protected Data Files:** JSON runtime data is written as executable `.json.php` files with an immediate `exit` guard.
- **Safe Uninstall:** Upload data is preserved by default unless cleanup is explicitly enabled in settings.
- **Multilingual Ready:** Ships with a `geo-ads-pro` text domain plus TR, DE, and EN translation files.

---

## 📁 Directory Structure

```text
geo-ads-pro/
├── geo-ads-pro.php
├── README.md
├── readme.txt
├── LICENSE.md
├── assets/
│   ├── css/
│   │   ├── admin-ui.css
│   │   ├── admin.css
│   │   └── analytics.css
│   └── js/
│       ├── admin.js
│       └── analytics.js
├── includes/
│   ├── helpers.php
│   ├── class-abtest.php
│   ├── class-admin.php
│   ├── class-ajax.php
│   ├── class-analytics.php
│   ├── class-banner-service.php
│   ├── class-citymap.php
│   ├── class-regions.php
│   ├── class-rest.php
│   ├── class-settings.php
│   ├── class-shortcode.php
│   └── class-widget.php
├── languages/
│   ├── geo-ads-pro.pot
│   ├── geo-ads-pro-tr_TR.po
│   ├── geo-ads-pro-tr_TR.mo
│   ├── geo-ads-pro-de_DE.po
│   ├── geo-ads-pro-de_DE.mo
│   ├── geo-ads-pro-en_US.po
│   └── geo-ads-pro-en_US.mo
└── public/
    ├── css/
    │   └── geo-ads-pro.css
    └── js/
        └── geo-ads-pro.js
```

---

## 🔧 Requirements
- **WordPress:** 5.0+
- **PHP:** 7.4+
- **Writable Directories:** File permissions allowing JSON file storage in the uploads directory.

---

## 🧠 Runtime Data

Geo Ads Pro stores runtime data under the WordPress uploads directory:

```text
wp-content/uploads/geo-ads-pro/
├── regions.json.php
├── city-map.json.php
├── analytics.json.php
└── <region-folder>/
    └── banner-image files
```

`regions.json.php` stores region and banner configuration, `city-map.json.php` stores city-to-region mappings, and `analytics.json.php` stores click/impression counters. These files contain a PHP `exit` guard before the JSON payload so direct web requests cannot read the data on PHP-enabled servers. Legacy `.json` files are migrated automatically. The shared banner selection logic lives in `includes/class-banner-service.php` and is used by AJAX, shortcode, widget, and REST rendering.

---

## ⚙️ Settings Notes

- **Local Mode:** Local city mapping is only applied when `gap_enable_local_mode` is enabled.
- **Default Region:** Used as fallback when no explicit or local region resolves.
- **Rotation Mode:** `random` picks a random selected banner; `sequential` rotates selected banners in order per region/size group.
- **A/B Auto Optimization:** When enabled, the highest-CTR banner in the selected size group is preferred.
- **Uninstall Cleanup:** Data is preserved by default. Enable uninstall cleanup only when banner files and JSON data should be deleted with the plugin.

---

## 🧭 Versioning

Geo Ads Pro tracks both release and schema versions:

- `GAP_VERSION`: Current plugin release version.
- `GAP_SCHEMA_VERSION`: Current runtime data/schema version.
- `gap_version`: Installed plugin version stored in WordPress options.
- `gap_schema_version`: Installed schema version stored in WordPress options.
- `gap_upgraded_at`: Last successful upgrade timestamp.

`gap_maybe_upgrade()` runs on activation and early `plugins_loaded`. It prepares upload protection files, migrates legacy `.json` runtime data to protected `.json.php` files, ensures default options exist, and records the current version/schema state.

---

## 🌐 Languages

Geo Ads Pro uses the `geo-ads-pro` text domain and loads translation files from `/languages`.

- `geo-ads-pro.pot`: Translation template.
- `geo-ads-pro-tr_TR.po/.mo`: Turkish.
- `geo-ads-pro-de_DE.po/.mo`: German.
- `geo-ads-pro-en_US.po/.mo`: English.

New user-facing PHP strings should use WordPress gettext helpers such as `__()`, `esc_html__()`, `esc_html_e()`, and `esc_attr__()` with the `geo-ads-pro` text domain.

---

## 🔒 Security Notes

- Admin mutations require `manage_options` plus WordPress nonces.
- Public AJAX banner and impression endpoints require the localized frontend nonce.
- REST banner requests are public by design but IP-throttled.
- Click and impression counters are throttled per visitor/banner to reduce counter manipulation.
- Upload runtime data is protected by `.htaccess`, `web.config`, `index.php`, and `.json.php` PHP exit guards.

---

## 📜 License
This project is licensed under the MIT License. See [LICENSE.md](file:///Users/lventctn/Developer/Geo-Ads-Pro/LICENSE.md) for details.
