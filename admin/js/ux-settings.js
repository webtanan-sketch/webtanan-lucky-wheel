(function ($) {
    'use strict';

    $(function () {
        var frame;
        var input = $('#wtlw-hub-logo-id');
        var preview = $('#wtlw-hub-logo-preview');

        $('#wtlw-select-hub-logo').on('click', function (event) {
            event.preventDefault();

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: 'انتخاب لوگوی مرکز گردونه',
                button: { text: 'استفاده از این تصویر' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                input.val(attachment.id);
                preview.html(
                    $('<img>', {
                        src: attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url,
                        alt: ''
                    }).css({
                        width: '76px',
                        height: '76px',
                        objectFit: 'cover',
                        borderRadius: '50%',
                        border: '1px solid #cbd5e1'
                    })
                );
            });

            frame.open();
        });

        $('#wtlw-remove-hub-logo').on('click', function (event) {
            event.preventDefault();
            input.val('');
            preview.html($('<span>', {
                class: 'description',
                text: 'در حال حاضر لوگویی انتخاب نشده است و متن «شانس» نمایش داده می‌شود.'
            }));
        });
    });
}(jQuery));
