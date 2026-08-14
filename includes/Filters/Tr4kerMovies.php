<?php
namespace AllI1D\Tr4ker\Filters;

use AllI1D\Tr4ker\Models\Tr4kerApiClient;
use AllI1D\Actions\Logs;
use AllI1D\Helpers\Crypto;
use AllI1D\Models\Repositories\FeedCatalogRepository;

class Tr4kerMovies {

    /** @var Tr4kerFeedFetcher */
    private $feed_fetcher;

    public function __construct(Tr4kerFeedFetcher $feed_fetcher) {
        $this->feed_fetcher = $feed_fetcher;
    }

    public function process_movie($movie) {
        $items = FeedCatalogRepository::get_instance()->search($movie['title'], 'movie', 'tr4ker');

        $matched_torrent = $this->find_match($items, $movie);

        if (null === $matched_torrent) {
            if (!empty($movie['general_search_done'])) {
                do_action('alli1d_log', 'Tr4ker API - Skipped (general search already done, no catalog match)', Logs::DEBUG, Logs::FILMS_LOG);
                return $movie;
            }

            $items = $this->feed_fetcher->get(
                [
                    'context'      => 'cron',
                    'type'         => 'movie',
                    'title'        => $movie['title'],
                    'audio_format' => $movie['audio_format'],
                ]
            );

            if (empty($items)) {
                do_action('alli1d_log', 'Tr4ker API - No response', Logs::DEBUG, Logs::FILMS_LOG);
                return $movie;
            }
            do_action('alli1d_log', 'Tr4ker API - ' .count($items). ' results', Logs::DEBUG, Logs::FILMS_LOG);

            $matched_torrent = $this->find_match($items, $movie);

            if (null === $matched_torrent) {
                do_action('alli1d_log', 'Tr4ker API - No matching torrent title', Logs::DEBUG, Logs::FILMS_LOG);
                return $movie;
            }
        }

        $upload_dir = wp_upload_dir();
        $tr4ker_dir = $upload_dir['basedir'] . '/tr4ker';
        // Create the tr4ker folder if it does not exist
        if (!file_exists($tr4ker_dir)) {
            mkdir($tr4ker_dir, 0755, true);
        }
        $file_name = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', implode('-', [$movie['title'], $movie['audio_format']]))) . '.torrent';
        // Full path to the torrent file
        $file_path = $tr4ker_dir . '/' . $file_name;
        $apiClient = new Tr4kerApiClient(
            Crypto::decrypt( get_option('alli1d_tr4ker_api_key', '') )
        );
        $file_content = $apiClient->downloadTorrent($matched_torrent['id']);
        if (null !== $file_content && !Tr4kerApiClient::isValidTorrentContent($file_content)) {
            do_action('alli1d_log', 'Tr4ker API - Downloaded content is not a valid torrent file', Logs::ERROR, Logs::FILMS_LOG);
            $file_content = null;
        }
        if (null !== $file_content) {
            file_put_contents($file_path, $file_content);
            $movie['found'] = true;
            $movie['results'][] = [
                'type'=> 'torrent',
                'path' => $file_path,
            ];
            do_action('alli1d_log', 'Tr4ker API - Torrent found : ' . $file_name, Logs::DEBUG, Logs::FILMS_LOG);
        } else {
            do_action('alli1d_log', 'Tr4ker API - Failed to download torrent', Logs::ERROR, Logs::FILMS_LOG);
        }
        return $movie;
    }

    /**
     * Find the first candidate in $items whose title matches $movie, using
     * the same title-matching rules regardless of whether $items came from
     * the local catalog or a live Tr4ker API fetch.
     *
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed>             $movie
     * @return array<string, mixed>|null
     */
    private function find_match(array $items, array $movie): ?array {
        foreach ($items as $item) {
            $is_match = apply_filters('alli1d_torrent_matches_title', true, [
                'torrent_name' => $item['title'],
                'title'        => $movie['title'],
                'year'         => $movie['year'] ?? null,
                'saison'       => null,
                'episode'      => null,
            ]);
            if (!$is_match) {
                // TorrentTitleMatcher already logs the real rejection reason.
                continue;
            }

            $quality_ok = apply_filters('alli1d_torrent_matches_quality', true, [
                'torrent_quality' => $item['quality'] ?? null,
                'preference'      => $movie['quality'] ?? 'any',
            ]);
            if (!$quality_ok) {
                // TorrentTitleMatcher already logs the real rejection reason.
                continue;
            }

            return $item;
        }
        return null;
    }
}