<?php
// Include the Composer autoloader.
require_once 'vendor/autoload.php';
use AllI1D\Torr9\Models\Torr9ApiClient;

echo "Torr9 listing test:\n";
$apiKey = 'aKeyThatIsNotRealButLooksLikeOne';
$token = 'aTokenThatIsNotRealButLooksLikeOne';

$client = new Torr9ApiClient($apiKey, $token);
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