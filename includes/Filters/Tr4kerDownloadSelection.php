<?php
namespace AllI1D\Tr4ker\Filters;

use AllI1D\Tr4ker\Models\Tr4kerApiClient;
use AllI1D\Actions\Logs;
use AllI1D\Helpers\Crypto;
use Throwable;

class Tr4kerDownloadSelection {

    public function __construct() {
    }

    public function download($null_default, $result) {
        try {
            $apiClient = new Tr4kerApiClient(
                Crypto::decrypt( get_option('alli1d_tr4ker_api_key', '') )
            );

            $file_content = $apiClient->downloadTorrent($result['id']);
            if (null === $file_content) {
                do_action('alli1d_log', 'Tr4ker API - Failed to download torrent', Logs::ERROR, Logs::FILMS_LOG);
                return $null_default;
            }

            $upload_dir = wp_upload_dir();
            $tr4ker_dir = $upload_dir['basedir'] . '/tr4ker';
            if (!file_exists($tr4ker_dir)) {
                mkdir($tr4ker_dir, 0755, true);
            }
            $file_name = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', implode('-', [$result['title'], $result['language'] ?? '']))) . '.torrent';
            $file_path = $tr4ker_dir . '/' . $file_name;
            file_put_contents($file_path, $file_content);
            do_action('alli1d_log', 'Tr4ker API - Torrent found : ' . $file_name, Logs::DEBUG, Logs::FILMS_LOG);

            return [
                'type' => 'torrent',
                'path' => $file_path,
            ];
        } catch (Throwable $e) {
            error_log('Tr4ker API download selection failed: ' . $e->getMessage());
            return null;
        }
    }
}
