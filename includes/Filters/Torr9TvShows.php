<?php
namespace AllI1D\Torr9\Filters;

use AllI1D\Torr9\Models\Torr9ApiClient;
use AllI1D\Actions\Logs;

class Torr9TvShows {


    public function __construct() {
    }

    public function process_tv_show($tvshow) {
        $apiClient = new Torr9ApiClient(
            get_option('alli1d_torr9_api_key', ''),
            get_option('alli1d_torr9_full_token', '')
        );
        $params = [
            'q'=> $tvshow['title'],
            'type'=>'tvshow',
            'saison'=>$tvshow['saison'],
            'episode'=>$tvshow['episode'],
        ];
        if ($tvshow['audio_format'] === 'VF') {
            $params['lang'] = 'VFF,TRUEFRENCH,FRENCH';
        }
		
		$response = $apiClient->listTorrents($params);
		if ($response === null || count($response) === 0 || !isset($response['torrents']) || count($response['torrents']) === 0) {
            do_action('alli1d_log', 'Torr9 API - No response', Logs::DEBUG, Logs::SERIES_LOG);
			return $tvshow;
		}
		do_action('alli1d_log', 'Torr9 API - ' .count($response['torrents']). ' results', Logs::DEBUG, Logs::SERIES_LOG);
		
        $upload_dir = wp_upload_dir();
        $torr9_dir = $upload_dir['basedir'] . '/torr9';
        // Create the torr9 folder if it does not exist
        if (!file_exists($torr9_dir)) {
            mkdir($torr9_dir, 0755, true);
        }
        $file_name = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', implode('-', [$tvshow['title'],$tvshow['audio_format'],$tvshow['saison'],$tvshow['episode']]))) . '.torrent';
        // Full path to the torrent file
        $file_path = $torr9_dir . '/' . $file_name;
        $file_content = $apiClient->downloadTorrent($response['torrents'][0]['id']);
        if (null !== $file_content ) {
            file_put_contents($file_path, $file_content);
            $tvshow['found'] = true;
            $tvshow['results'][] = [
                'type'=> 'torrent',
                'path' => $file_path,
            ];
            do_action('alli1d_log', 'Torr9 API - Torrent found : ' . $file_name, Logs::DEBUG, Logs::SERIES_LOG);
        } else {
            do_action('alli1d_log', 'Torr9 API - Failed to download torrent', Logs::ERROR, Logs::SERIES_LOG);
        }
        return $tvshow;
    }
}