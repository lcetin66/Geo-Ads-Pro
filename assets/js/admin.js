/* Plugin Name: die1-Geo Ads Pro - admin.js */
/* Date: 20260610 */
/* Author: Levent Cetin - 3CCS.com */

jQuery(function($){
    var i18n = window.GAP_ADMIN_I18N || {};

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
                            '<div style="font-size:12px;color:#646970;margin-top:2px;">' + $('<span>').text(file.name).html() + '</div>'
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
    // Banner List – Delete via JS (fixes duplicate hidden field bug)
    // =========================================================================
    $(document).on('click', '.gap-delete-banner-btn', function(e){
        e.preventDefault();
        var $btn = $(this);
        var confirmMsg = $btn.data('confirm') || i18n.areYouSure;
        if (!confirm(confirmMsg)) return;

        var form = $('<form method="post"></form>');
        form.append($('<input type="hidden" name="gap_delete_banner_id">').val($btn.data('id')));
        form.append($('<input type="hidden" name="gap_delete_banner_region">').val($btn.data('region')));
        form.append($('<input type="hidden" name="gap_delete_nonce">').val($btn.data('nonce')));
        $('body').append(form);
        form.submit();
    });

    // =========================================================================
    // Banner List – Sortable Table Columns
    // =========================================================================
    var $table = $('#gap-banner-table');
    if ($table.length) {
        var sortState = { key: null, asc: true };

        $table.on('click', '.gap-sortable', function(){
            var $th = $(this);
            var key = $th.data('sort-key');
            var type = $th.data('sort-type');

            if (sortState.key === key) {
                sortState.asc = !sortState.asc;
            } else {
                sortState.key = key;
                sortState.asc = true;
            }

            $table.find('.gap-sortable').removeClass('gap-sort-asc gap-sort-desc');
            $th.addClass(sortState.asc ? 'gap-sort-asc' : 'gap-sort-desc');

            var $tbody = $table.find('tbody');
            var $rows = $tbody.find('tr.gap-banner-main').get();

            $rows.sort(function(a, b){
                var va, vb;
                if (type === 'number') {
                    va = parseFloat($(a).data(key)) || 0;
                    vb = parseFloat($(b).data(key)) || 0;
                } else {
                    va = ($(a).data(key) || '').toString().toLowerCase();
                    vb = ($(b).data(key) || '').toString().toLowerCase();
                }
                if (va < vb) return sortState.asc ? -1 : 1;
                if (va > vb) return sortState.asc ? 1 : -1;
                return 0;
            });

            $.each($rows, function(i, row){ $tbody.append(row); });
        });
    }

    // =========================================================================
    // Region Picker – Custom Dropdown with Checkboxes
    // =========================================================================
    var $picker = $('#gap_region_picker');
    if ($picker.length) {
        var $toggle = $('#gap_region_picker_toggle');
        var $dropdown = $('#gap_region_picker_dropdown');

        $toggle.on('click', function(e){
            e.stopPropagation();
            $picker.toggleClass('is-open');
        });

        $dropdown.on('click', '.gap-region-item-name', function(e){
            e.stopPropagation();
            var region = $(this).closest('.gap-region-picker-item').data('region');
            $('#gap_selected_region_input').val(region);
            $picker.removeClass('is-open');
            $('#gap_select_region_form').submit();
        });

        $(document).on('click', function(e){
            if (!$(e.target).closest('#gap_region_picker').length) {
                $picker.removeClass('is-open');
            }
        });

        // Delete button – open modal
        $('#gap_delete_regions_btn').on('click', function(){
            var checked = [];
            $dropdown.find('.gap-region-check:checked').each(function(){
                checked.push($(this).val());
            });

            if (checked.length === 0) {
                alert($picker.data('no-selection') || i18n.selectAtLeastOneRegion);
                return;
            }

            $('#gap_regions_to_delete').val(checked.join(','));

            var msg = $('#gap_delete_region_modal').data('msg') || '';
            msg = msg.replace('{regions}', checked.join(', '));
            $('#gap_modal_message').text(msg);

            $('#gap_delete_region_modal').fadeIn(200);
        });

        // Modal cancel
        $(document).on('click', '.gap-modal-cancel', function(){
            $('#gap_delete_region_modal').fadeOut(200);
        });

        // Close modal on overlay click
        $('#gap_delete_region_modal').on('click', function(e){
            if ($(e.target).is('.gap-modal-overlay')) {
                $(this).fadeOut(200);
            }
        });
    }

    // =========================================================================
    // City/Region Autocomplete (Photon API - OpenStreetMap)
    // =========================================================================
    var regionInput = $('input[name="gap_region_name"]');
    if (regionInput.length) {
        var dropdown = $('<ul class="gap-city-dropdown"></ul>').insertAfter(regionInput);
        var searchTimer = null;

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
                            var props = feature.properties;
                            var coords = feature.geometry.coordinates;
                            var city   = props.name || '';
                            var state  = props.state || '';
                            var country = props.country || '';
                            var label  = [city, state, country].filter(Boolean).join(', ');

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

        dropdown.on('click', 'li', function(){
            var item = $(this);
            var cityName = item.data('city');
            var lat      = item.data('lat');
            var lon      = item.data('lon');

            regionInput.val(cityName);
            dropdown.hide().empty();

            var form = regionInput.closest('form');

            if (!form.find('input[name="gap_region_lat"]').length) {
                form.append('<input type="hidden" name="gap_region_lat" value="">');
                form.append('<input type="hidden" name="gap_region_lon" value="">');
            }

            form.find('input[name="gap_region_lat"]').val(lat);
            form.find('input[name="gap_region_lon"]').val(lon);

            var info = form.find('.gap-city-coords-info');
            if (!info.length) {
                info = $('<p class="gap-city-coords-info description"></p>').insertAfter(
                    form.find('input[name="gap_region_radius_km"]')
                );
            }
            info.text('📍 ' + cityName + ': ' + lat.toFixed(4) + ', ' + lon.toFixed(4));
        });

        $(document).on('click', function(e){
            if (!$(e.target).closest('input[name="gap_region_name"], .gap-city-dropdown').length) {
                dropdown.hide().empty();
            }
        });
    }

    // =========================================================================
    // Copy shortcode to clipboard
    // =========================================================================
    $(document).on('click', '.gap-copy-shortcode', function(e){
        e.preventDefault();
        e.stopPropagation();
        var text = $(this).attr('data-shortcode');
        var $btn = $(this);

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function(){
                $btn.find('.dashicons').removeClass('dashicons-clipboard').addClass('dashicons-yes');
                setTimeout(function(){ $btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-clipboard'); }, 1500);
            });
        } else {
            var tmp = document.createElement('textarea');
            tmp.value = text;
            tmp.style.position = 'fixed';
            tmp.style.left = '-9999px';
            document.body.appendChild(tmp);
            tmp.select();
            document.execCommand('copy');
            document.body.removeChild(tmp);
            $btn.find('.dashicons').removeClass('dashicons-clipboard').addClass('dashicons-yes');
            setTimeout(function(){ $btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-clipboard'); }, 1500);
        }
    });
});
