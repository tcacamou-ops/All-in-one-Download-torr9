jQuery(document).ready(function ($) {
    const $document = $(document); // Cache document lookup

    // init events listeners
    $document.on('click', '#submit-tr4ker-credentials', submit_tr4ker_credentials);

    function submit_tr4ker_credentials(e) {
        e.preventDefault();
        allI1d.requestWPApi(
            allI1d_tr4ker.api.routes.credentials,
            {
                tr4ker_api_key: $('#tr4ker_api_key').val(),
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
