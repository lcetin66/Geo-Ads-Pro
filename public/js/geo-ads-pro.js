// Date: 20260606
/* Author: Levent Cetin - 3CCS.com */

jQuery(function($){

    let userCity = '';

    $.get('https://ipapi.co/json/', function(res){
        if (res && res.city) userCity = res.city;
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
                mode: mode,
                region: region,
                city: userCity
            }, function(res){

                if (res && res.html) {
                    widget.html(res.html);

                    // Impression tracking
                    let img = widget.find('.gap-banner');
                    let bannerId = img.data('banner-id');
                    let resolvedRegion = res.region || region;

                    if (bannerId && resolvedRegion) {
                        $.post(GAP_AJAX.url, {
                            action: 'gap_track_impression',
                            banner_id: bannerId,
                            region: resolvedRegion
                        });
                    }
                }
            });

        });
    }

});
