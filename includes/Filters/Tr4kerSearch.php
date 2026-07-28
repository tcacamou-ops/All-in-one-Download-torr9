<?php
namespace AllI1D\Tr4ker\Filters;

use AllI1D\Tr4ker\Models\Tr4kerApiClient;
use AllI1D\Helpers\Crypto;
use Throwable;

class Tr4kerSearch {

    public function __construct() {
    }

    public function search($results, $criteria) {
        $api_key = Crypto::decrypt( get_option('alli1d_tr4ker_api_key', '') );
        if (empty($api_key)) {
            $results['errors']['tr4ker'] = 'missing_credentials';
            return $results;
        }

        try {
            $apiClient = new Tr4kerApiClient($api_key);
            $items = $apiClient->searchTorrents($criteria);
            $results['items'] = array_merge($results['items'], $items);
        } catch (Throwable $e) {
            error_log('Tr4ker API search failed: ' . $e->getMessage());
            $results['errors']['tr4ker'] = 'search_failed';
        }

        return $results;
    }
}
