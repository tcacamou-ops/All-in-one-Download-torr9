<?php
namespace AllI1D\Tr4ker\Models;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;

class Tr4kerApiClient
{
    // @var Client
    private $client;
    private $baseUrl = 'https://tr4ker.net/api/torznab';
    private $apiKey = '';
    private $defaultParams = [
        'limit' => 100,
    ];

    /**
     * Tr4kerApiClient constructor.
     * @param string $apiKey
     */
    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->client = new Client();
    }

    /**
     * Test the connection to the Tr4ker Torznab API
     * @return bool
     */
    public function testConnection()
    {
        try {
            $path = $this->baseUrl.'?'.$this->buildQueryString(['t' => 'search', 'q' => 'test', 'limit' => 1]);
            error_log('Testing Tr4ker API connection with path: ' . $this->redact_url( $path ) );
            $response = $this->client->request('GET', $path);
            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            error_log('Tr4ker API connection test failed: ' . $this->redact_url( $e->getMessage() ));
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
        $lang = $params['lang'] ?? null;
        unset($params['lang']);
        try {
            $path = $this->baseUrl.'?'.$this->buildQueryString($params);
            error_log('Requesting Tr4ker API with path: ' . $this->redact_url( $path ) );
            $response = $this->client->request('GET', $path);
            $torrents = $this->parseTorznabResponse($response->getBody()->getContents());
            return ['torrents' => $this->filter($torrents, $lang)];
        } catch (RequestException $e) {
            error_log('Tr4ker API request failed: ' . $this->redact_url( $e->getMessage() ));
            return null;
        }
    }

    /**
     * Download the .torrent file
     * @param string $download_url
     * @return string|null
     */
    public function downloadTorrent($download_url)
    {
        try {
            error_log('Requesting Tr4ker API download with path: ' . $this->redact_url( $download_url ) );
            $response = $this->client->request('GET', $download_url);
            return $response->getBody()->getContents(); // Binary content of the .torrent file
        } catch (RequestException $e) {
            error_log('Tr4ker API download request failed: ' . $this->redact_url( $e->getMessage() ));
            return null;
        }
    }

    private function redact_url( string $url ): string {
        return preg_replace(
            '/([?&]apikey=)[^&]+/',
            '$1***',
            $url
        );
    }

    /**
     * Build the query string for the Torznab API request
     * @param array $params
     * @return string
     */
    private function buildQueryString($params)
    {
        $params = array_merge($this->defaultParams, $params);
        $params = $this->whatToQuery($params);
        $params['t'] = $params['t'] ?? 'search';
        $params['apikey'] = $this->apiKey;
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
                $params['cat'] = 2000; // Movies category
            } elseif ($params['type'] === 'tvshow') {
                $params['cat'] = 5000; // TV category
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
     * Parse a Torznab RSS/XML response into a flat list of torrents
     * @param string $xml_content
     * @return array
     */
    private function parseTorznabResponse($xml_content)
    {
        $torrents = [];
        $xml = @simplexml_load_string($xml_content);
        if ($xml === false || !isset($xml->channel->item)) {
            return $torrents;
        }
        foreach ($xml->channel->item as $item) {
            $torznab_attrs = $item->children('http://torznab.com/schemas/2015/feed');
            $seeders = 0;
            foreach ($torznab_attrs->attr as $attr) {
                if ((string) $attr['name'] === 'seeders') {
                    $seeders = (int) $attr['value'];
                }
            }
            $torrents[] = [
                'id' => (string) ($item->enclosure['url'] ?? $item->link),
                'name' => (string) $item->title,
                'seeders' => $seeders,
            ];
        }
        return $torrents;
    }

    /**
     * Filter torrents by language keywords found in their title
     * @param array $torrents
     * @param string|null $lang comma-separated list of keywords (e.g. "VFF,TRUEFRENCH,FRENCH")
     * @return array
     */
    private function filter($torrents, $lang)
    {
        if (!$lang) {
            return $torrents;
        }
        $keywords = array_filter(array_map(static fn($k) => strtolower(trim($k)), explode(',', $lang)));
        if (empty($keywords)) {
            return $torrents;
        }
        return array_values(array_filter($torrents, function ($torrent) use ($keywords) {
            $title = strtolower($torrent['name']);
            foreach ($keywords as $keyword) {
                if (strpos($title, $keyword) !== false) {
                    return true;
                }
            }
            return false;
        }));
    }
}
