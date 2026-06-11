=== die1-Geo Ads Pro ===
Contributors: leventcetin
Author: Levent Cetin - 3CCS.com
Tags: ads, geo ads, banner, advertising, widget, shortcode, rest api
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.9
License: MIT
License URI: https://opensource.org/licenses/MIT

die1-Geo Ads Pro, bölge bazlı banner yönetimi, şehir → bölge eşleştirme, A/B test, analytics, widget, shortcode ve REST API desteği sunan profesyonel bir WordPress reklam eklentisidir.

== Description ==

die1-Geo Ads Pro, reklamlarınızı coğrafi konuma göre hedeflemenizi sağlar.  
Bölge bazlı banner yönetimi, şehir eşleştirme, tıklama ve gösterim takibi, A/B test sistemi ve gelişmiş analytics özellikleri içerir.

=== Features ===
* Bölge oluşturma ve yönetim
* Banner yükleme, seçme ve silme
* Banner boyutlarını otomatik algılama
* Korumalı analytics.json.php dosyasında tıklama ve gösterim takibi
* Şehir bazlı gösterim/tıklama takibi
* Local targeting için city-map veya en yakın radius tanımlı region eşleşmesi seçimi
* Kısa süreli duplicate impression engelleme
* Public AJAX ve REST endpointleri için nonce/rate limit sertleştirmesi
* Click tracking için kısa süreli IP+banner throttle
* CTR hesaplama
* Random veya sequential banner rotasyonu
* A/B test sistemi ve opsiyonel en yüksek CTR optimizasyonu
* Analytics dashboard (Chart.js)
* Aylık CSV raporu oluşturma ve müşteri e-postalarına gruplanmış şekilde otomatik gönderme
* Rapor maillerine ilgili banner görselleri de eklenir
* Rapor maillerinde HTML download linki bulunur
* Otomatik rapor geçmişi ve son gönderim tarihi analytics ekranında gösterilir
* Şehir eşleştirme ekleme, güncelleme ve silme
* Widget desteği
* Shortcode desteği
* REST API endpoint’leri
* Güvenli redirect sistemi
* JSON dosyaları için .htaccess koruması
* Admin tarafında CSRF + capability kontrolü
* Varsayılan olarak veriyi koruyan uninstall davranışı
* TR, DE ve EN dil dosyaları için hazır i18n yapısı
* Çoklu dosya destekli drag & drop banner yükleme alanı

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
* Local targeting yöntemi city-map seçilirse IP -> şehir -> city-map eşleşmesi kullanılır. Radius seçilirse region merkezi isimden otomatik çözülür; kullanıcı hiçbir radius içinde değilse en yakın region kullanılır.
* Varsayılan bölge, local/global çözüm bulunamadığında fallback olarak kullanılır.
* Sequential rotation, aynı bölge ve boyut grubu içinde sırayla banner seçer.
* A/B otomatik optimizasyon aktifse en yüksek CTR değerine sahip varyant tercih edilir.
* Aylık raporlar etkinse WP-Cron, her benzersiz müşteri e-postasına önceki ayın tek bir gruplanmış CSV raporunu gönderir, ilgili banner görsellerini ekler ve HTML download linki oluşturur.
* Analytics ekranında otomatik rapor geçmişi, son gönderim tarihi ve müşteri grubu bazlı log görünür.
* Uninstall sırasında veri varsayılan olarak korunur; tam temizlik için ayardan ayrıca etkinleştirmek gerekir.

=== Versioning ===
die1-Geo Ads Pro hem release hem de schema versiyonu takip eder.

* GAP_VERSION: Aktif plugin sürümü
* GAP_SCHEMA_VERSION: Aktif runtime veri/schema sürümü
* gap_version: WordPress options içindeki kurulu plugin sürümü
* gap_schema_version: WordPress options içindeki kurulu schema sürümü
* gap_upgraded_at: Son başarılı upgrade zamanı
* gap_auto_monthly_reports: Aylık CSV raporlarının gruplanmış otomatik e-posta gönderimi

gap_maybe_upgrade() activation ve erken plugins_loaded sırasında çalışır; upload korumalarını hazırlar, legacy .json verileri korumalı .json.php dosyalarına taşır, varsayılan option değerlerini tamamlar ve sürüm/schema durumunu kaydeder.

=== Languages ===
Eklenti geo-ads-pro text domain kullanır ve languages klasöründen çeviri yükler.

* geo-ads-pro.pot: Ana çeviri şablonu
* geo-ads-pro-tr_TR.po / .mo: Türkçe
* geo-ads-pro-de_DE.po / .mo: Almanca
* geo-ads-pro-en_US.po / .mo: İngilizce

Yeni arayüz metinleri __(), esc_html__(), esc_html_e() veya esc_attr__() ile çevrilebilir hale getirilmelidir.

=== Installation ===
1. GitHub reposundaki dist/die1-geo-ads-pro-1.0.9.zip dosyasını indirin
2. WordPress Admin -> Plugins -> Add New -> Upload Plugin ekranını açın
3. die1-geo-ads-pro-1.0.9.zip dosyasını seçip Install Now ile yükleyin
4. Eklentiyi etkinleştirin
5. die1-Geo Ads Pro menüsünden bölgeleri ve banner’ları yönetin

=== Changelog ===
= 1.0.9 =
* Proje ve plugin adı die1-Geo Ads Pro olarak güncellendi.
* Ana admin menüsü Banner List sayfasıyla açılacak şekilde yeniden düzenlendi.
* Bölge yönetimi ve banner yükleme akışı Regions & Upload sayfasına taşındı.
* Banner List ekranına region, boyut, click ve view sütunlarında sıralama eklendi.
* Banner URL, link target, müşteri e-postası, shortcode kopyalama ve silme işlemleri merkezi listeden yönetilebilir hale getirildi.
* Çoklu bölge silme akışına bannerları Media Library içine arşivleme seçeneği eklendi.
* Analytics ekranına banner performans grafiğinin yanında şehir/region bazlı pasta grafik eklendi.
* Shortcode ile basılan bannerlar için frontend impression tracking eklendi.
* Local mode için şehir bilgisi boşsa server-side IP çözümleme fallback’i eklendi.
* Shortcode işleme, redirect URL doğrulama, IP doğrulama, rapor indirme, widget HTML, banner dosya yolu ve admin inputlarında güvenlik sertleştirmeleri yapıldı.

= 1.0.8 =
* Widget görünüm renkleri düzeltildi: Hintergrundfarbe, Textfarbe, Link-Farbe ve Active Links Color gerçek renk seçici ve hex değeriyle çalışır.
* Widgetten Hintergrundfarbe 2 ve Hintergrundbild alanları kaldırıldı.
* die1-Geo Ads Pro admin sayfaları için yetki kontrolü dayanıklı hale getirildi.
* Local targeting için city-map/radius radio seçimi ve region radius alanı eklendi; region merkezi isimden otomatik çözülür.
* Asset/schema sürümü 1.0.8 / 2026060701 olarak güncellendi.

= 1.0.7 =
* Admin ekranındaki manuel Stadt/City -> Region formu kaldırıldı
* Yerine hangi bannerın hangi region altında olduğunu gösteren Banner -> Region Assignment tablosu eklendi
* Bölge seçilmediğinde banner upload alanının nerede açılacağını gösteren bilgilendirme paneli eklendi
* Yeni bölge eklendikten sonra bölge otomatik seçiliyor ve drag & drop upload alanı hemen görünüyor
* Drag & drop görünürlük metinleri TR/DE/EN dil dosyalarına eklendi
* Banner kayıtlarına müşteri e-postası alanı eklendi
* Analytics veri modeline şehir bazlı tıklama/gösterim takibi eklendi
* Analytics sayfasına manuel CSV indirme, e-posta gönderme ve otomatik aylık rapor desteği eklendi
* Otomatik aylık raporlar aynı müşteri e-postası altındaki banner’ları tek gruplanmış CSV halinde gönderir
* Otomatik rapor geçmişi ve son gönderim tarihi analytics ekranında gösterilmeye başladı
* Rapor maillerine ilgili banner görselleri eklenmeye başladı
* Rapor maillerine HTML download linki eklendi
* Settings ekranına local targeting yöntemi için city-map/radius radio seçimi eklendi
* Region ekranına radius modu için latitude, longitude ve radius km alanları eklendi
* Asset/schema sürümü 1.0.7 / 2026060607 olarak güncellendi

= 1.0.6 =
* Analytics ekranındaki Chart.js CDN bağımlılığı kaldırıldı
* Analytics grafiği native canvas ile çalışacak hale getirildi
* Analytics tablosu eklendi; JS kapalı ya da grafik çizilemezse veriler yine görünür
* Analytics asset enqueue hook eşleşmesi daha dayanıklı hale getirildi
* Asset/schema sürümü 1.0.6 / 2026060606 olarak güncellendi

= 1.0.5 =
* Admin banner yükleme alanı drag & drop destekli hale getirildi
* Çoklu banner dosyası yükleme desteği eklendi
* Seçilen dosyalar upload öncesi listelenir
* Drag & drop metinleri TR/DE/EN dil dosyalarına eklendi
* Asset/schema sürümü 1.0.5 / 2026060605 olarak güncellendi

= 1.0.4 =
* Text Domain ve Domain Path plugin header bilgileri tamamlandı
* Admin, settings, analytics, A/B test ve widget metinleri gettext fonksiyonlarına bağlandı
* TR, DE ve EN için PO/MO dil dosyaları eklendi
* Ana POT şablonu eklendi
* Asset/schema sürümü 1.0.4 / 2026060604 olarak güncellendi

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
