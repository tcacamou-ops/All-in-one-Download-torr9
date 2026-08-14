<?php
namespace AllI1D\Tr4ker\Filters;

use AllI1D\Tr4ker\Models\Tr4kerApiClient;
use AllI1D\Helpers\Crypto;

class Tr4kerFeedFetcher {

    /**
     * Fetch a set of search criteria directly from the Tr4ker API.
     *
     * @param array $criteria See Tr4kerApiClient::fetchFeed().
     * @return array|null
     */
    public function get(array $criteria): ?array
    {
        $api_key = Crypto::decrypt(get_option('alli1d_tr4ker_api_key', ''));
        if ('' === $api_key) {
            return null;
        }

        $client = new Tr4kerApiClient($api_key);
        return $client->fetchFeed($criteria);
    }
}
