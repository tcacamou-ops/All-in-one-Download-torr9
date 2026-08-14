<?php
namespace AllI1D\Tr4ker\Filters;

use AllI1D\Tr4ker\Models\Tr4kerApiClient;
use AllI1D\Actions\Logs;
use AllI1D\Helpers\Crypto;
use AllI1D\Models\Repositories\FeedCatalogRepository;

class Tr4kerTvShows {

    /** @var Tr4kerFeedFetcher */
    private $feed_fetcher;

    public function __construct(Tr4kerFeedFetcher $feed_fetcher) {
        $this->feed_fetcher = $feed_fetcher;
    }

    public function process_tv_show($tvshow) {
        $items = FeedCatalogRepository::get_instance()->search($tvshow['title'], 'tvshow', 'tr4ker');

        $matched_torrent = $this->find_match($items, $tvshow);

        if (null === $matched_torrent) {
            if (!empty($tvshow['general_search_done'])) {
                do_action('alli1d_log', 'Tr4ker API - Skipped (general search already done, no catalog match)', Logs::DEBUG, Logs::SERIES_LOG);
                return $tvshow;
            }

            $items = $this->feed_fetcher->get(
                [
                    'context'      => 'cron',
                    'type'         => 'tvshow',
                    'title'        => $tvshow['title'],
                    'audio_format' => $tvshow['audio_format'],
                    'saison'       => $tvshow['saison'],
                    'episode'      => $tvshow['episode'],
                ]
            );

            if (empty($items)) {
                do_action('alli1d_log', 'Tr4ker API - No response', Logs::DEBUG, Logs::SERIES_LOG);
                return $tvshow;
            }
            do_action('alli1d_log', 'Tr4ker API - ' .count($items). ' results', Logs::DEBUG, Logs::SERIES_LOG);

            $matched_torrent = $this->find_match($items, $tvshow);

            if (null === $matched_torrent) {
                do_action('alli1d_log', 'Tr4ker API - No matching torrent title', Logs::DEBUG, Logs::SERIES_LOG);
                return $tvshow;
            }
        }

        $upload_dir = wp_upload_dir();
        $tr4ker_dir = $upload_dir['basedir'] . '/tr4ker';
        // Create the tr4ker folder if it does not exist
        if (!file_exists($tr4ker_dir)) {
            mkdir($tr4ker_dir, 0755, true);
        }
        $file_name = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', implode('-', [$tvshow['title'],$tvshow['audio_format'],$tvshow['saison'],$tvshow['episode']]))) . '.torrent';
        // Full path to the torrent file
        $file_path = $tr4ker_dir . '/' . $file_name;
        $apiClient = new Tr4kerApiClient(
            Crypto::decrypt( get_option('alli1d_tr4ker_api_key', '') )
        );
        $file_content = $apiClient->downloadTorrent($matched_torrent['id']);
        if (null !== $file_content && !Tr4kerApiClient::isValidTorrentContent($file_content)) {
            do_action('alli1d_log', 'Tr4ker API - Downloaded content is not a valid torrent file', Logs::ERROR, Logs::SERIES_LOG);
            $file_content = null;
        }
        if (null !== $file_content ) {
            file_put_contents($file_path, $file_content);
            $tvshow['found'] = true;
            $tvshow['results'][] = [
                'type'=> 'torrent',
                'path' => $file_path,
            ];
            do_action('alli1d_log', 'Tr4ker API - Torrent found : ' . $file_name, Logs::DEBUG, Logs::SERIES_LOG);
        } else {
            do_action('alli1d_log', 'Tr4ker API - Failed to download torrent', Logs::ERROR, Logs::SERIES_LOG);
        }
        return $tvshow;
    }

    /**
     * Find the first candidate in $items whose title matches $tvshow, using
     * the same title-matching rules regardless of whether $items came from
     * the local catalog or a live Tr4ker API fetch.
     *
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed>             $tvshow
     * @return array<string, mixed>|null
     */
    private function find_match(array $items, array $tvshow): ?array {
        foreach ($items as $item) {
            $is_match = apply_filters('alli1d_torrent_matches_title', true, [
                'torrent_name' => $item['title'],
                'title'        => $tvshow['title'],
                'year'         => null,
                'saison'       => $tvshow['saison'],
                'episode'      => $tvshow['episode'],
            ]);
            if (!$is_match) {
                // TorrentTitleMatcher already logs the real rejection reason.
                continue;
            }

            $quality_ok = apply_filters('alli1d_torrent_matches_quality', true, [
                'torrent_quality' => $item['quality'] ?? null,
                'preference'      => $tvshow['quality'] ?? 'any',
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