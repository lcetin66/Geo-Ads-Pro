/* Geo Ads Pro – Rotation Page JS */

jQuery(function($){
    var dragged = null;
    var selectedBanners = [];

    // Başlangıçta boş dropzone mesajını göster
    $('#gap_rotation_dropzone_empty').addClass('is-visible');

    // Banner grid'de drag başlat
    $(document).on('dragstart', '.gap-rotation-banner-item', function(e){
        dragged = $(this);
        e.originalEvent.dataTransfer.effectAllowed = 'copy';
        e.originalEvent.dataTransfer.setData('text/plain', '');
    });

    // Dropzone hover / active state
    var dz = $('#gap_rotation_dropzone');
    dz.on('dragover dragenter', function(e){
        e.preventDefault();
        $(this).addClass('gap-rotation-dropzone-active');
    }).on('dragleave drop', function(e){
        e.preventDefault();
        $(this).removeClass('gap-rotation-dropzone-active');
    });

    // Dropzone'ya banner bırak
    dz.on('drop', function(){
        if (!dragged) return;
        var id   = dragged.data('id');
        var file = dragged.find('img').attr('src') || '';

        if (selectedBanners.indexOf(id) === -1) {
            selectedBanners.push({ id: id, img: file });
            renderDropzoneItems();
        }

        // Sol taraftaki kartı gizle
        dragged.hide();
        dragged = null;
    });

    // Seçili banner'ları render et
    function renderDropzoneItems() {
        var $items = $('#gap_rotation_dropzone_items');
        $items.empty();

        if (selectedBanners.length === 0) {
            $('#gap_rotation_dropzone_empty').addClass('is-visible');
            return;
        }

        $('#gap_rotation_dropzone_empty').removeClass('is-visible');

        $.each(selectedBanners, function(i, b){
            var html = '<div class="gap-rotation-drop-item">'
                     + '  <img src="' + b.img + '" alt=""> '
                     + '  <span>Banner #' + b.id + '</span> '
                     + '  <span class="gap-remove-banner" data-id="' + b.id + '">✕</span> '
                     + '</div>';
            $items.append(html);

            // Sol tarafa geri dönmek için data-store ekle
            $('.gap-rotation-banner-item[data-id="' + b.id + '"]').show();
        });
    }

    // Dropzone'dan banner kaldır
    $(document).on('click', '.gap-remove-banner', function(){
        var id = $(this).data('id');
        selectedBanners = selectedBanners.filter(function(b){ return b.id !== id; });
        renderDropzoneItems();
        $('.gap-rotation-banner-item[data-id="' + id + '"]').show();
    });

    // Bölge filtresi
    $('#gap_rotation_region_filter').on('change', function(){
        var val = $(this).val();
        if (val === '') {
            $('.gap-rotation-banner-item').show();
        } else {
            $('.gap-rotation-banner-item').hide().filter('[data-region="' + val + '"]').show();
        }
    });

    // "Rotate oluştur" butonu
    $('#gap_create_rotation_btn').on('click', function(e){
        e.preventDefault();

        console.log('🔵 gap_create_rotation_btn clicked');
        console.log('   selectedBanners:', selectedBanners);

        if (selectedBanners.length < 2) {
            alert(__('Please select at least 2 banners.', 'geo-ads-pro'));
            return;
        }

        var name = $('#gap_group_name_input').val().trim();
        console.log('   group name:', name);

        if (!name) {
            alert(__('Please enter a group name.', 'geo-ads-pro'));
            return;
        }

        // Nonce alanını bul
        var nonceInput = $('input[name="_wpnonce"]');
        var nonceVal = nonceInput.val();
        console.log('   nonce found:', nonceVal ? 'YES' : 'NO', '- selector matched:', nonceInput.length);

        if (nonceInput.length === 0) {
            console.error('❌ _wpnonce input not found in DOM');
            alert('❌ Security field missing. Please reload.');
            return;
        }

        if (!nonceVal) {
            console.error('❌ nonce value is empty');
            alert('❌ Security nonce has no value. Please reload.');
            return;
        }

        // Form oluştur ve submit et
        var $form = $('<form method="post" style="display:none;"></form>');
        $form.append('<input type="hidden" name="gap_save_rotation_group" value="1">');
        $form.append('<input type="hidden" name="_wpnonce" value="' + nonceVal + '">');
        $form.append('<input type="hidden" name="gap_group_name" value="' + name.replace(/"/g, '&quot;') + '">');
        $form.append('<input type="hidden" name="gap_group_region" value="mixed">');
        $form.append('<input type="hidden" name="gap_group_id" value="">');

        $.each(selectedBanners, function(i, b){
            console.log('   adding banner:', b.id);
            $form.append('<input type="hidden" name="gap_group_banners[]" value="' + b.id + '">');
        });

        console.log('✅ Form created with', $form.children().length, 'fields. Submitting...');
        $('body').append($form);
        setTimeout(function(){
            $form.submit();
        }, 100);
    });

    // Shortcode kopyalama desteği
    $('#gap_shortcode_input').on('focus', function(){
        this.select();
    });
});
