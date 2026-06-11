// Date: 20260610
/* Author: Levent Cetin - 3CCS.com */

jQuery(function($){

    initGeoAdsWidgets();
    trackShortcodeBanners();

    function initGeoAdsWidgets() {

        $('.geo-ads-pro-widget').each(function(){

            var widget = $(this);
            var mode   = widget.data('mode');
            var region = widget.data('region');

            $.post(GAP_AJAX.url, {
                action: 'gap_get_banner',
                nonce: GAP_AJAX.nonce,
                mode: mode,
                region: region
            }, function(res){

                if (res && res.html) {
                    var content = widget.find('.gap-widget-content');
                    var clickUrl = res.click_url || (res.banner && res.banner.id ? (window.location.origin + '/?gap_click=' + res.banner.id) : '');

                    if (content.length) {
                        content.html(res.html);

                        if (clickUrl) {
                            var captionText = (typeof GAP_AJAX !== 'undefined' && GAP_AJAX.view_ad) ? GAP_AJAX.view_ad : 'View ad';
                            content.append(
                                '<div class="gap-widget-caption">' +
                                    '<a href="' + clickUrl + '" target="_blank" rel="noopener noreferrer">' + captionText + '</a>' +
                                '</div>'
                            );
                        }
                    } else {
                        widget.html(res.html);
                    }

                    var img = widget.find('.gap-banner');
                    var bannerId = img.data('banner-id');
                    var resolvedRegion = res.region || region;

                    if (bannerId && resolvedRegion) {
                        $.post(GAP_AJAX.url, {
                            action: 'gap_track_impression',
                            nonce: GAP_AJAX.nonce,
                            banner_id: bannerId,
                            region: resolvedRegion
                        });
                    }
                } else {
                    var content = widget.find('.gap-widget-content');
                    if (content.length) {
                        var emptyText = (typeof GAP_AJAX !== 'undefined' && GAP_AJAX.no_banner) ? GAP_AJAX.no_banner : '';
                        content.html('<div class="gap-widget-empty">' + emptyText + '</div>');
                    }
                }
            });

        });
    }

    function trackShortcodeBanners() {
        $('img.gap-banner').each(function(){
            var $img = $(this);
            if ($img.data('impression-tracked')) return;
            $img.data('impression-tracked', true);

            var bannerId = $img.data('banner-id');
            var region = $img.data('region') || '';

            if (bannerId) {
                $.post(GAP_AJAX.url, {
                    action: 'gap_track_impression',
                    nonce: GAP_AJAX.nonce,
                    banner_id: bannerId,
                    region: region
                });
            }
        });
    }

});
