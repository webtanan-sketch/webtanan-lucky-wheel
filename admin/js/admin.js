(function ($) {
    'use strict';
    $(function () {
        $('.wtlw-settings-form').on('change', 'select[name*="[type]"]', function () {
            var row = $(this).closest('.wtlw-section-row');
            row.toggleClass('wtlw-is-extra', 'extra_attempts' === $(this).val());
        });
    });
}(jQuery));
