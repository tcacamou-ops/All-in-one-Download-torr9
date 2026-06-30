<?php
namespace AllI1D\Torr9\Filters;

use AllI1D\Torr9\Models\Torr9ApiClient;
use AllI1D\Helpers\Crypto;

class Status {

    public function __construct() {
    }

    public static function process_status($status) {
        $apiClient = new Torr9ApiClient(
            Crypto::decrypt( get_option('alli1d_torr9_api_key', '') ),
            Crypto::decrypt( get_option('alli1d_torr9_full_token', '') )
        );
        $is_connected = $apiClient->testConnection();
        $is_rss_connected = $apiClient->testRssConnection();

        if ($is_connected && $is_rss_connected) {
            $retour = ['status' => 'connected', 'success' => 'Connection to Torr9 API successful'];
        } else {
            $retour = [
                'error' => 'Failed to connect to Torr9 API. Please check your API key and token.',
                'Full Token connection' => $is_connected ? 'success' : 'failure',
                'API connection' => $is_rss_connected ? 'success' : 'failure',
            ];
        }
        $retour['settings_url'] = admin_url('admin.php?page=all-in-one-download-torr9');

        
        $status['Torr9'] = $retour;
        return $status;
    }
}
