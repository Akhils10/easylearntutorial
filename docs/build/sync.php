<?php


// -----------------
// Run this file to crawl the channel and generate the `out` file. Then run `build-tree.php` to parse `out`
// and create the site tree merged with defaults.
// -----------------


if (php_sapi_name() !== 'cli') {
    exit();
}


require_once __DIR__ . '/vendor/autoload.php';

$key = file_get_contents(__DIR__ . '/../../../../.chaves/gdc');
$channelId = file_get_contents(__DIR__ . '/../../../../.chaves/chanId');

$client = new Google_Client();
$client->setDeveloperKey($key);
$youtube = new Google_Service_YouTube($client);

$lists = [];
$total = 0;
$next = null;

do {
    $params = ['channelId' => $channelId, 'maxResults' => 50];
    if ($next) {
        $params['pageToken'] = $next;
    }
    $channel = $youtube->playlists->listPlaylists('id,snippet', $params);
    /** @var array $items */
    $items = $channel->getItems();
    /** @var Google_Service_YouTube_PageInfo $pageInfo */
    $pageInfo = $channel->getPageInfo();
    $total = $pageInfo->getTotalResults();
    $next = $channel->getNextPageToken();

    $playlist = array_map(function(/** @var Google_Service_YouTube_Playlist $item */ $item) use ($youtube) {
        $snip = $item->getSnippet();
        /** @var Google_Service_YouTube_Thumbnail $thumb */
        $thumb = $snip->getThumbnails()->getDefault();

        $items = $youtube->playlistItems->listPlaylistItems('id,contentDetails', [
            'playlistId' => $item->getId(),
            'maxResults' => 50
        ]);
        /** @var Google_Service_YouTube_PageInfo $pageInfo */
        $pageInfo = $items->getPageInfo();
        $total = $pageInfo->getTotalResults();
        $next = $items->getNextPageToken();
        $data = [];

        while (count($data) < $total) {
            $data = array_merge($data, Eltcom\getItems($items, $youtube));
            if ($next) {
                echo '---', PHP_EOL;
                print_r([
                    'data' => $data,
                    'total' => $total,
                    'next' => $next,
                    'id' => $item->getId(),
                ]);
                throw new Exception('Should fetch next page');
            }
        }

        return [
            'id' => $item->getId(),
            'title' => $snip->getTitle(),
            'description' => $snip->getDescription(),
            'thumbnail' => $thumb->getUrl(),
            'publishedAt' => $snip->getPublishedAt(),
            'items' => $data,
        ];
    }, $items);

    $lists = array_merge($lists, $playlist);
} while (count($lists) < $total);


echo json_encode($lists);
