/* Plugin Name: Geo Ads Pro - admin.js */
/* Date: 20260609 */
/* Author: Levent Cetin - 3CCS.com */

jQuery(function($){

    // =========================================================================
    // Dropzone
    // =========================================================================
    $('.gap-dropzone').each(function(){
        const dropzone = $(this);
        const input = dropzone.find('.gap-dropzone-input');
        const form = dropzone.closest('.gap-upload-form');
        const list = form.find('.gap-upload-file-list');

        function renderFiles(files) {
            list.empty();
            dropzone.find('.gap-dropzone-preview').remove();
            dropzone.find('.gap-dropzone-icon, .gap-dropzone-title, .gap-dropzone-text').hide();

            Array.from(files || []).forEach(function(file){
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const preview = $('<div class="gap-dropzone-preview"></div>');
                        preview.append(
                            '<img src="' + e.target.result + '" style="max-width:100%;max-height:200px;border-radius:4px;margin:4px;">' +
                            '<div style="font-size:12px;color:#646970;margin-top:2px;">' + file.name + '</div>'
                        );
                        dropzone.append(preview);
                    };
                    reader.readAsDataURL(file);
                } else {
                    $('<li/>').text(file.name).appendTo(list);
                }
            });
        }

        dropzone.on('click keydown', function(event){
            if (event.type === 'click' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                input.trigger('click');
            }
        });

        input.on('click', function(event){ event.stopPropagation(); });
        input.on('change', function(){ renderFiles(this.files); });

        dropzone.on('dragenter dragover', function(event){
            event.preventDefault();
            event.stopPropagation();
            dropzone.addClass('is-dragover');
        });

        dropzone.on('dragleave dragend drop', function(event){
            event.preventDefault();
            event.stopPropagation();
            dropzone.removeClass('is-dragover');
        });

        dropzone.on('drop', function(event){
            const files = event.originalEvent.dataTransfer.files;
            if (!files || !files.length) return;
            input[0].files = files;
            renderFiles(files);
        });
    });

    // =========================================================================
    // Şehir / Bölge Autocomplete (Photon API - OpenStreetMap tabanlı)
    // =========================================================================
    const regionInput = $('input[name="gap_region_name"]');
    if (!regionInput.length) return;

    // Autocomplete dropdown container
    const dropdown = $('<ul class="gap-city-dropdown"></ul>').insertAfter(regionInput);
    let searchTimer = null;

    function searchCities(query) {
        if (query.length < 2) { dropdown.hide().empty(); return; }

        clearTimeout(searchTimer);
        searchTimer = setTimeout(function(){
            $.getJSON(
                'https://photon.komoot.io/api/?q=' + encodeURIComponent(query) + '&lang=de&limit=6&layer=city&layer=district',
                function(data) {
                    dropdown.empty();

                    if (!data.features || !data.features.length) {
                        dropdown.hide();
                        return;
                    }

                    data.features.forEach(function(feature){
                        const props = feature.properties;
                        const coords = feature.geometry.coordinates; // [lon, lat]
                        const city   = props.name || '';
                        const state  = props.state || '';
                        const country = props.country || '';
                        const label  = [city, state, country].filter(Boolean).join(', ');

                        $('<li></li>')
                            .text(label)
                            .data('city', city)
                            .data('lat', coords[1])
                            .data('lon', coords[0])
                            .appendTo(dropdown);
                    });

                    dropdown.show();
                }
            );
        }, 300);
    }

    regionInput.on('input', function(){
        searchCities($(this).val());
    });

    regionInput.on('keydown', function(e){
        if (e.key === 'Escape') { dropdown.hide().empty(); }
    });

    // Bir şehir seçilince
    dropdown.on('click', 'li', function(){
        const item = $(this);
        const cityName = item.data('city');
        const lat      = item.data('lat');
        const lon      = item.data('lon');

        regionInput.val(cityName);
        dropdown.hide().empty();

        // Radius input alanını bul ve koordinat bilgisini gizli alanlara yaz
        const form = regionInput.closest('form');

        // Gizli koordinat alanları yoksa oluştur
        if (!form.find('input[name="gap_region_lat"]').length) {
            form.append('<input type="hidden" name="gap_region_lat" value="">');
            form.append('<input type="hidden" name="gap_region_lon" value="">');
        }

        form.find('input[name="gap_region_lat"]').val(lat);
        form.find('input[name="gap_region_lon"]').val(lon);

        // Koordinatı kullanıcıya göster
        let info = form.find('.gap-city-coords-info');
        if (!info.length) {
            info = $('<p class="gap-city-coords-info description"></p>').insertAfter(
                form.find('input[name="gap_region_radius_km"]')
            );
        }
        info.text('📍 ' + cityName + ': ' + lat.toFixed(4) + ', ' + lon.toFixed(4));
    });

    // Dışarı tıklayınca kapat
    $(document).on('click', function(e){
        if (!$(e.target).closest('input[name="gap_region_name"], .gap-city-dropdown').length) {
            dropdown.hide().empty();
        }
    });
});
