<?php
namespace AllI1D\Tr4ker\Filters;

use AllI1D\Tr4ker\Models\Tr4kerApiClient;
use AllI1D\Helpers\Crypto;

class Status {

    public function __construct() {
    }

    public static function process_status($status) {
        $apiClient = new Tr4kerApiClient(
            Crypto::decrypt( get_option('alli1d_tr4ker_api_key', '') )
        );
        $is_connected = $apiClient->testConnection();

        if ($is_connected) {
            $retour = ['status' => 'connected', 'success' => 'Connection to Tr4ker API successful'];
        } else {
            $retour = [
                'error' => 'Failed to connect to Tr4ker API. Please check your API key.',
                'API connection' => 'failure',
            ];
        }
        $retour['settings_url'] = admin_url('admin.php?page=all-in-one-download-torr9');


        $status['Tr4ker'] = $retour;
        return $status;
    }
}
