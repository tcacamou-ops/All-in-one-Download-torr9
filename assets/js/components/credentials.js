jQuery(document).ready(function ($) {
    const $document = $(document); // Cache document lookup

    // init events listeners
    $document.on('click', '#submit-torr9-credentials', submit_torr9_credentials);

    function submit_torr9_credentials(e) {
        e.preventDefault();
        allI1d.requestWPApi(
            allI1d_torr9.api.routes.credentials,
            {
                torr9_api_key: $('#torr9_api_key').val(),
                torr9_full_token: $('#torr9_full_token').val(),
            },
            function (response, data) {
                allI1d.showToast('Saved', 'success');
                setTimeout(function () { location.reload(); }, 1000);
            },
            'POST',
            function (request, error) {
                var message = (request.responseJSON && request.responseJSON.message)
                    ? request.responseJSON.message
                    : error;
                allI1d.showToast(message, 'error');
            }
        );
    }
});
