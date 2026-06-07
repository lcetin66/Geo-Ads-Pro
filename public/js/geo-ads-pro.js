// Date: 20260606
/* Author: Levent Cetin - 3CCS.com */

jQuery(function($){

    let userCity = '';
    let userLatitude = '';
    let userLongitude = '';

    $.get('https://ipapi.co/json/', function(res){
        if (res && res.city) userCity = res.city;
        if (res && typeof res.latitude !== 'undefined') userLatitude = res.latitude;
        if (res && typeof res.longitude !== 'undefined') userLongitude = res.longitude;
        initGeoAdsWidgets();
    }).fail(function(){
        initGeoAdsWidgets();
    });

    function initGeoAdsWidgets() {

        $('.geo-ads-pro-widget').each(function(){

            let widget = $(this);

            let mode   = widget.data('mode');
            let region = widget.data('region');

            $.post(GAP_AJAX.url, {
                action: 'gap_get_banner',
                nonce: GAP_AJAX.nonce,
                mode: mode,
                region: region,
                city: userCity,
                latitude: userLatitude,
                longitude: userLongitude
            }, function(res){

                if (res && res.html) {
                    let content = widget.find('.gap-widget-content');
                    let clickUrl = res.click_url || (res.banner && res.banner.id ? (window.location.origin + '/?gap_click=' + res.banner.id) : '');

                    if (content.length) {
                        content.html(res.html);

                        if (clickUrl) {
                            let captionText = (typeof GAP_AJAX !== 'undefined' && GAP_AJAX.view_ad) ? GAP_AJAX.view_ad : 'View ad';
                            content.append(
                                '<div class="gap-widget-caption">' +
                                    '<a href="' + clickUrl + '" target="_blank" rel="noopener noreferrer">' + captionText + '</a>' +
                                '</div>'
                            );
                        }
                    } else {
                        widget.html(res.html);
                    }

                    // Impression tracking
                    let img = widget.find('.gap-banner');
                    let bannerId = img.data('banner-id');
                    let resolvedRegion = res.region || region;

                    if (bannerId && resolvedRegion) {
                        $.post(GAP_AJAX.url, {
                            action: 'gap_track_impression',
                            nonce: GAP_AJAX.nonce,
                            banner_id: bannerId,
                            region: resolvedRegion,
                            city: userCity
                        });
                    }
                } else {
                    let content = widget.find('.gap-widget-content');
                    if (content.length) {
                        let emptyText = (typeof GAP_AJAX !== 'undefined' && GAP_AJAX.no_banner) ? GAP_AJAX.no_banner : '';
                        content.html('<div class="gap-widget-empty">' + emptyText + '</div>');
                    }
                }
            });

        });
    }

});
