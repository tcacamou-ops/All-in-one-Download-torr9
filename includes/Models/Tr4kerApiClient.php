<?php
namespace AllI1D\Tr4ker\Models;

use AllI1D\Services\TorrentMetadataParser;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;

class Tr4kerApiClient
{
    private const REQUEST_TIMEOUT = 10;
    private const ALLOWED_DOWNLOAD_HOST = 'tr4ker.net';

    /**
     * Maximum accepted size (in bytes) for a Torznab XML response body,
     * before it is handed to simplexml_load_string(). A legitimate Torznab
     * feed (up to `limit=100` items) stays well under this, so anything
     * larger is treated as suspicious/oversized and rejected outright.
     */
    private const MAX_TORZNAB_RESPONSE_SIZE = 5 * 1024 * 1024; // 5 MB

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
        $this->client = new Client(['timeout' => self::REQUEST_TIMEOUT]);
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
     * Keyword search for the guided-search modal, mapped to the common
     * provider result contract and capped to the top 10 by seeders.
     * @param array $criteria ['title'=>string, 'type'=>?string, 'saison'=>?int, 'episode'=>?int]
     * @return array
     */
    public function searchTorrents(array $criteria): array
    {
        $params = [
            'q' => $criteria['title'] ?? '',
        ];
        if (!empty($criteria['type'])) {
            $params['type'] = $criteria['type'];
        }
        if (($params['type'] ?? null) === 'tvshow') {
            if (!empty($criteria['saison'])) {
                $params['saison'] = $criteria['saison'];
            }
            if (!empty($criteria['episode'])) {
                $params['episode'] = $criteria['episode'];
            }
        }

        $response = $this->listTorrents($params);
        if ($response === null || empty($response['torrents'])) {
            return [];
        }

        $parser = new TorrentMetadataParser();
        $items = array_map(static function ($torrent) use ($parser) {
            return [
                'provider' => 'tr4ker',
                'title'    => $torrent['name'],
                'quality'  => $parser->extract_quality($torrent['name']),
                'language' => $parser->extract_language($torrent['name']),
                'id'       => $torrent['id'],
                'score'    => $torrent['seeders'],
                'extra'    => ['seeders' => $torrent['seeders']],
            ];
        }, $response['torrents']);

        usort($items, static function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($items, 0, 10);
    }

    /**
     * Fetch feed items, called directly by `Tr4kerFeedFetcher`.
     *
     * For the interactive search context, delegates to searchTorrents()
     * unchanged (sorted, capped to 10, no title/season/episode matching:
     * the user picks manually). For the cron context (movie/tv-show
     * processing), returns the full, unsorted, uncapped candidate list
     * mapped to the common provider contract, so the caller's own
     * title/season/episode matching loop sees every candidate — exactly as
     * when it iterated listTorrents() directly, before caching existed.
     *
     * @param array $criteria ['context'=>'cron'|'search', 'title'=>string, 'type'=>?string, 'saison'=>?int, 'episode'=>?int, 'audio_format'=>?string]
     * @return array|null Null on request failure (not cached); [] on empty result (cached as a real miss).
     */
    public function fetchFeed(array $criteria): ?array
    {
        if (($criteria['context'] ?? null) === 'search') {
            return $this->searchTorrents($criteria);
        }

        $params = [
            'q' => $criteria['title'] ?? '',
        ];
        if (!empty($criteria['type'])) {
            $params['type'] = $criteria['type'];
        }
        if (($params['type'] ?? null) === 'tvshow') {
            if (!empty($criteria['saison'])) {
                $params['saison'] = $criteria['saison'];
            }
            if (!empty($criteria['episode'])) {
                $params['episode'] = $criteria['episode'];
            }
        }
        if (($criteria['audio_format'] ?? null) === 'VF') {
            $params['lang'] = 'VFF,TRUEFRENCH,FRENCH';
        }

        $response = $this->listTorrents($params);
        if ($response === null) {
            return null;
        }

        $parser = new TorrentMetadataParser();
        return array_map(static function ($torrent) use ($parser) {
            return [
                'provider' => 'tr4ker',
                'title'    => $torrent['name'],
                'quality'  => $parser->extract_quality($torrent['name']),
                'language' => $parser->extract_language($torrent['name']),
                'id'       => $torrent['id'],
                'score'    => $torrent['seeders'],
                'extra'    => ['seeders' => $torrent['seeders']],
            ];
        }, $response['torrents']);
    }

    /**
     * Download the .torrent file
     * @param string $download_url
     * @return string|null
     */
    public function downloadTorrent($download_url)
    {
        if (!$this->isAllowedDownloadUrl($download_url)) {
            error_log('Tr4ker API download rejected: untrusted download URL host');
            return null;
        }
        try {
            error_log('Requesting Tr4ker API download with path: ' . $this->redact_url( $download_url ) );
            $response = $this->client->request('GET', $download_url, ['allow_redirects' => false]);
            return $response->getBody()->getContents(); // Binary content of the .torrent file
        } catch (RequestException $e) {
            error_log('Tr4ker API download request failed: ' . $this->redact_url( $e->getMessage() ));
            return null;
        }
    }

    /**
     * Ensure the download URL points to the expected Tr4ker host over HTTPS,
     * since it is otherwise fully controlled by the third-party Torznab
     * response (SSRF prevention).
     * @param string $download_url
     * @return bool
     */
    private function isAllowedDownloadUrl($download_url): bool
    {
        $host = parse_url($download_url, PHP_URL_HOST);
        $scheme = parse_url($download_url, PHP_URL_SCHEME);
        return $scheme === 'https' && $host === self::ALLOWED_DOWNLOAD_HOST;
    }

    /**
     * Check that downloaded content looks like a valid bencoded .torrent
     * file before it is written to disk (defense in depth).
     * @param string|null $content
     * @return bool
     */
    public static function isValidTorrentContent(?string $content): bool
    {
        if (null === $content || '' === $content) {
            return false;
        }
        if ($content[0] !== 'd') {
            return false;
        }
        $head = substr($content, 0, 512);
        return strpos($head, 'announce') !== false || strpos($head, 'info') !== false;
    }

    /**
     * Whitelist of query parameters allowed to appear in clear text in logs.
     * Everything else (including the API key, whatever it may be called) is
     * redacted by default rather than relying on a blacklist of known
     * sensitive parameter names.
     */
    private const LOGGABLE_QUERY_PARAMS = ['t', 'q', 'cat', 'limit'];

    private function redact_url( string $url ): string {
        return preg_replace_callback(
            '/\?(.+)$/',
            function ($matches) {
                parse_str($matches[1], $params);
                $safe = [];
                foreach ($params as $key => $value) {
                    $safe[$key] = in_array($key, self::LOGGABLE_QUERY_PARAMS, true)
                        ? $value
                        : '[REDACTED]';
                }
                return '?' . http_build_query($safe);
            },
            $url,
            1
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

        if (strlen($xml_content) > self::MAX_TORZNAB_RESPONSE_SIZE) {
            error_log('Tr4ker API response rejected: Torznab response exceeds maximum allowed size');
            return $torrents;
        }

        // Legitimate Torznab feeds never carry a DOCTYPE. Reject any that do
        // before parsing, to rule out entity-expansion (Billion Laughs) DoS
        // payloads regardless of libxml's own protections.
        if (stripos($xml_content, '<!doctype') !== false) {
            error_log('Tr4ker API response rejected: DOCTYPE declaration found in Torznab response');
            return $torrents;
        }

        $xml = @simplexml_load_string($xml_content, \SimpleXMLElement::class, LIBXML_NONET);
        if ($xml === false || !isset($xml->channel->item)) {
            return $torrents;
        }
        foreach ($xml->channel->item as $item) {
            $torznab_attrs = $item->children('http://torznab.com/schemas/2015/feed');
            $seeders = 0;
            foreach ($torznab_attrs->attr as $attr) {
                // `$attr['name']` (array access on the SimpleXMLElement itself)
                // does not resolve torznab:attr's own name/value attributes on
                // this libxml/PHP version — it silently returns empty strings.
                // ->attributes() reads them reliably.
                $attr_attributes = $attr->attributes();
                if ((string) $attr_attributes['name'] === 'seeders') {
                    $seeders = (int) $attr_attributes['value'];
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
