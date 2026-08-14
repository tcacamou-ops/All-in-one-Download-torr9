<?php
if (!defined('ABSPATH')) {
    exit;
}
// Include the Composer autoloader.
require_once 'vendor/autoload.php';
use AllI1D\Tr4ker\Models\Tr4kerApiClient;

echo "Tr4ker listing test:\n";
$apiKey = 'aKeyThatIsNotRealButLooksLikeOne';
$token = 'aTokenThatIsNotRealButLooksLikeOne';

$client = new Tr4kerApiClient($apiKey, $token);
// As it comes from the script
$searchParams = [
    'title' => 'cross',
    'type' => 'tvshow',
    'saison' => 2,
    'episode' => 4,
    'found' => false,
    'results' => [],
    'audio_format' => 'VF',
];
// Transform parameters for the API
$apiParams = [
    'q' => $searchParams['title'],
    'type' => $searchParams['type'],
    'saison' => $searchParams['saison'],
    'episode' => $searchParams['episode'],
    'lang' => $searchParams['audio_format'],
];


$result = $client->listTorrents($apiParams);
$torrentFileCContent = $client->downloadTorrent($result['torrents'][0]['id']);