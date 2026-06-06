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
- **Smart Banner Rotation:** Automatically rotates banners of the same size.

### 🌍 GEO Targeting
- **City-to-Region Mapping:** Map individual cities to broader regions.
- **Local (IP-based) Mode:** Auto-detects visitor cities and displays localized banners.
- **Global fallback mode:** Serves fallback regional banners globally.
- **Secure Backend IP Lookup:** Reliable server-side visitor city mapping.

### 📊 Analytics
- **Click Tracking:** Records every time a banner is clicked.
- **Impression Tracking:** Counts views for individual banners.
- **CTR Calculation:** Automatically calculates Click-Through Rates.
- **Visual Analytics:** Interactive charts representing performance across regions (Chart.js integration).

### 🧪 A/B Testing System
- **Variant Grouping:** Banners of the same size are automatically grouped as variants.
- **Performance Evaluation:** Monitors and displays the CTR performance of each variant.
- **Winner Declaration:** Identifies the highest-performing variant.

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
│   ├── class-citymap.php
│   ├── class-regions.php
│   ├── class-rest.php
│   ├── class-settings.php
│   ├── class-shortcode.php
│   └── class-widget.php
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

## 📜 License
This project is licensed under the MIT License. See [LICENSE.md](file:///Users/lventctn/Developer/Geo-Ads-Pro/LICENSE.md) for details.
