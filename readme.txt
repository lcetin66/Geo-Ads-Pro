=== Geo Ads Pro ===
Contributors: leventcetin
Author: Levent Cetin - 3CCS.com
Tags: ads, geo ads, banner, advertising, widget, shortcode, rest api
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.3
License: MIT
License URI: https://opensource.org/licenses/MIT

Geo Ads Pro, bölge bazlı banner yönetimi, şehir → bölge eşleştirme, A/B test, analytics, widget, shortcode ve REST API desteği sunan profesyonel bir WordPress reklam eklentisidir.

== Description ==

Geo Ads Pro, reklamlarınızı coğrafi konuma göre hedeflemenizi sağlar.  
Bölge bazlı banner yönetimi, şehir eşleştirme, tıklama ve gösterim takibi, A/B test sistemi ve gelişmiş analytics özellikleri içerir.

=== Features ===
* Bölge oluşturma ve yönetim
* Banner yükleme, seçme ve silme
* Banner boyutlarını otomatik algılama
* Korumalı analytics.json.php dosyasında tıklama ve gösterim takibi
* Kısa süreli duplicate impression engelleme
* Public AJAX ve REST endpointleri için nonce/rate limit sertleştirmesi
* Click tracking için kısa süreli IP+banner throttle
* CTR hesaplama
* Random veya sequential banner rotasyonu
* A/B test sistemi ve opsiyonel en yüksek CTR optimizasyonu
* Analytics dashboard (Chart.js)
* Şehir eşleştirme ekleme, güncelleme ve silme
* Widget desteği
* Shortcode desteği
* REST API endpoint’leri
* Güvenli redirect sistemi
* JSON dosyaları için .htaccess koruması
* Admin tarafında CSRF + capability kontrolü
* Varsayılan olarak veriyi koruyan uninstall davranışı

=== Shortcode ===
[geo_ads_pro mode="global" region="NRW"]

=== REST API ===
GET /wp-json/geo-ads-pro/v1/banner

=== Data Storage ===
Eklenti verileri WordPress uploads dizinindeki geo-ads-pro klasöründe tutulur:

* regions.json.php: Bölge ve banner konfigürasyonu
* city-map.json.php: Şehir -> bölge eşleştirmeleri
* analytics.json.php: Click/impression sayaçları
* region klasörleri: Banner görselleri

Veri dosyaları PHP exit guard ile yazılır; doğrudan web isteği geldiğinde JSON içeriği döndürülmez. Eski .json dosyaları otomatik olarak korumalı .json.php dosyalarına taşınır. Banner seçim mantığı includes/class-banner-service.php içinde ortaklaştırılmıştır. AJAX, shortcode, widget ve REST çıktısı aynı seçim/fallback/rotasyon kurallarını kullanır.

=== Security Notes ===
* Admin işlemleri manage_options ve nonce kontrolü gerektirir.
* Public AJAX banner/impression endpointleri frontend nonce ister.
* REST banner endpointi public kalır fakat IP bazlı rate limit uygular.
* Click ve impression sayaçları IP+banner bazlı kısa süreli throttle kullanır.
* Upload veri klasöründe .htaccess, web.config, index.php ve .json.php exit guard korumaları bulunur.

=== Settings Notes ===
* Local mode sadece gap_enable_local_mode aktifken şehir eşleştirmesi uygular.
* Varsayılan bölge, local/global çözüm bulunamadığında fallback olarak kullanılır.
* Sequential rotation, aynı bölge ve boyut grubu içinde sırayla banner seçer.
* A/B otomatik optimizasyon aktifse en yüksek CTR değerine sahip varyant tercih edilir.
* Uninstall sırasında veri varsayılan olarak korunur; tam temizlik için ayardan ayrıca etkinleştirmek gerekir.

=== Versioning ===
Geo Ads Pro hem release hem de schema versiyonu takip eder.

* GAP_VERSION: Aktif plugin sürümü
* GAP_SCHEMA_VERSION: Aktif runtime veri/schema sürümü
* gap_version: WordPress options içindeki kurulu plugin sürümü
* gap_schema_version: WordPress options içindeki kurulu schema sürümü
* gap_upgraded_at: Son başarılı upgrade zamanı

gap_maybe_upgrade() activation ve erken plugins_loaded sırasında çalışır; upload korumalarını hazırlar, legacy .json verileri korumalı .json.php dosyalarına taşır, varsayılan option değerlerini tamamlar ve sürüm/schema durumunu kaydeder.

=== Installation ===
1. Eklentiyi yükleyin
2. Etkinleştirin
3. Geo Ads Pro menüsünden bölgeleri ve banner’ları yönetin

=== Changelog ===
= 1.0.3 =
* GAP_VERSION ve GAP_SCHEMA_VERSION sabitleri eklendi
* gap_maybe_upgrade() ile kontrollü activation/plugins_loaded upgrade akışı eklendi
* gap_version, gap_schema_version ve gap_upgraded_at option kayıtları eklendi
* Asset versiyonları GAP_VERSION üzerinden yönetilmeye başladı
* Settings ekranında plugin/schema sürüm bilgisi gösterildi

= 1.0.2 =
* Runtime JSON dosyaları korumalı .json.php formatına taşındı
* Eski .json dosyaları için otomatik migrasyon eklendi
* AJAX banner endpointine nonce ve rate limit eklendi
* REST banner endpointine rate limit eklendi
* Click tracking için IP+banner throttle eklendi
* Upload veri klasörüne index.php ve web.config korumaları eklendi

= 1.0.1 =
* Ortak banner seçim servisi eklendi
* Analytics sayaçları ayrı analytics.json dosyasına taşındı
* Sequential rotation ve local mode ayarları gerçek gösterim akışına bağlandı
* Şehir eşleştirme güncelleme/silme yönetimi eklendi
* Banner/bölge silme akışı, nonce ayrımı ve dosya yolu güvenliği iyileştirildi
* Uninstall veri koruma seçeneği eklendi

= 1.0.0 =
* İlk sürüm

=== License ===
MIT License
