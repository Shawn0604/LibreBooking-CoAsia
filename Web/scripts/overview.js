$(function () {
    $('#resourceChips .chip').on('click', function () {
        var $chip = $(this);
        var resourceId = $chip.data('resource');

        $('#resourceChips .chip').removeClass('active');
        $chip.addClass('active');

        if (resourceId === 'all') {
            $('#page-overview .event').removeClass('chip-hidden');
            return;
        }

        $('#page-overview .event').each(function () {
            var $event = $(this);
            var matches = String($event.data('resource')) === String(resourceId);
            $event.toggleClass('chip-hidden', !matches);
        });
    });
});
