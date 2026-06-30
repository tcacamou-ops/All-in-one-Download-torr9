<?php
namespace AllI1D\Torr9\Models;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;

class Torr9ApiClient
{
    // @var Client
    private $client;
    private $baseUrl = 'https://api.torr9.net/api/v1';
    private $apiKey = '';
    private $token = '';
    private $defaultParams = [
        'page' => 1,
        'limit' => 100,
        'sortBy' => 'seeders',
        'order' => 'desc',
    ];

    /**
     * Torr9ApiClient constructor.
     * @param string $apiKey
     * @param string $token
     */
    public function __construct(string $apiKey, string $token)
    {
        $this->apiKey = $apiKey;
        $this->token = $token;
        $this->client = new Client();
    }

    /**
     * Test the connection to the Torr9 API
     * @return bool
     */
    public function testConnection()
    {
        try {
            $path = $this->baseUrl.'/torrents/search?' . $this->buildQueryString(['q' => 'test']);
            error_log('Testing Torr9 API connection with path: ' . $this->redact_url( $path ) );
            $headers = [
                'Authorization' => 'Bearer ' . $this->token,
            ];
            $response = $this->client->request('GET', $path, ['headers' => $headers]);
            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            error_log('Torr9 API connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test the connection to the Torr9 RSS API
     * @return bool
     */
    public function testRssConnection()
    {
        try {
            $path = sprintf("%s/rss/freeleech?passkey=%s", $this->baseUrl, $this->apiKey);
            error_log('Testing Torr9 RSS API connection with path: ' . $this->redact_url( $path ) );
            $response = $this->client->request('GET', $path);
            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            error_log('Torr9 RSS API connection test failed: ' . $this->redact_url( $e->getMessage() ));
            return false;
        }
    }

    /**
     * List torrents
     * @param array $params
     * @return array|null
     */
    public function listTorrents($params = [])
    {
        try {
            $path = $this->baseUrl.'/torrents/search?' . $this->buildQueryString($params);
            error_log('Requesting Torr9 API with path: ' . $this->redact_url( $path ) );
            $headers = [
                'Authorization' => 'Bearer ' . $this->token,
            ];
            $response = $this->client->request('GET', $path, ['headers' => $headers]);
            $body = json_decode($response->getBody()->getContents(), true);
            return $this->filter($body, $params); // Returns the raw response content
        } catch (RequestException $e) {
            error_log('Torr9 API request failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Download the .torrent file
     * @param string $torrent_id
     * @return string|null
     */
    public function downloadTorrent($torrent_id)
    {
        try {
            $path = sprintf("%s/rss/torrents/%s/download?passkey=%s", $this->baseUrl, $torrent_id, $this->apiKey);
            error_log('Requesting Torr9 API download with path: ' . $this->redact_url( $path ) );
            $response = $this->client->request('GET', $path);
            return $response->getBody()->getContents(); // Binary content of the .torrent file
        } catch (RequestException $e) {
            error_log('Torr9 API download request failed: ' . $this->redact_url( $e->getMessage() ));
            return null;
        }
    }

    private function redact_url( string $url ): string {
        return preg_replace(
            '/([?&](?:passkey|api_key|token|key)=)[^&]+/',
            '$1***',
            $url
        );
    }

    /**
     * Build the query string for the API request
     * @param array $params
     * @return string
     */
    private function buildQueryString($params)
    {
        $params = array_merge($this->defaultParams, $params);
        $params = $this->whatToQuery($params);
        return http_build_query($params);
    }

    /**
     * Determine what to query based on the provided parameters
     * @param array $params
     * @return array
     */
    private function whatToQuery($params)
    {
        if (isset($params['type'])) {
            if ($params['type'] === 'movie') {
                $params['category'] = 'movie'; // Category for movies
            } elseif ($params['type'] === 'tvshow') {
                $params['category'] = 'tv'; // Category for TV shows
                $params = $this->saisonEtEpisodes($params);
            }
            unset($params['type']);
        }
        return $params;
    }

    /**
     * Handle season and episode parameters for TV shows
     * @param array $params
     * @return array
     */
    private function saisonEtEpisodes($params)
    {
        if (isset($params['saison'])) {
            $saison = intval($params['saison']);
            $params['q'] .= " S".str_pad($saison, 2, '0', STR_PAD_LEFT);
            unset($params['saison']);
        }
        if (isset($params['episode'])) {
            if (intval($params['episode']) > 0) {
                $params['q'] .= "E".str_pad(intval($params['episode']), 2, '0', STR_PAD_LEFT);
            }
            unset($params['episode']);
        }
        return $params;
    }

    /**
     * Filter the API response based on language tags
     * @param array $response
     * @param array $params
     * @return array
     */
    private function filter($response, $params)
    {
        $lang = isset($params['lang']) ? $params['lang'] : null;
        // Filter torrents by language using tags
        if ($lang && isset($response['torrents']) && is_array($response['torrents'])) {
            $what = str_replace([' '], '.', strtolower($params['name']));
            $filtered = [];
            foreach ($response['torrents'] as $torrent) {
                if (isset($torrent['name']) && stripos($torrent['name'], $what) === false) {
                    continue; // Skip torrents that don't match the name
                }
                // Transform tags to lowercase delimited string
                $tagsString = '';
                if (isset($torrent['tags']) && is_array($torrent['tags'])) {
                    $tagsString = '|' . implode('|', array_map('strtolower', $torrent['tags'])) . '|';
                }
                
                // Filter by language
                if ($lang === 'VF') {
                    if (
                        strpos($tagsString, '|vf|') !== false || 
                        strpos($tagsString, '|multi|') !== false ||
                        strpos($tagsString, '|vff|') !== false ||
                        strpos($tagsString, '|vf2|') !== false ){
                        $filtered[] = $torrent;
                    }
                } elseif ($lang === 'VOSTFR') {
                    if (strpos($tagsString, '|vostfr|') !== false || strpos($tagsString, '|multi|') !== false) {
                        $filtered[] = $torrent;
                    }
                } else {
                    $filtered[] = $torrent;
                }
            }
            $response['torrents'] = $filtered;
            $response['count'] = count($filtered);
        }
        return $response;
    }
}