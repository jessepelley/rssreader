<?php
$FAVICON_DIR = '/volume3/web/jjjp.ca/news/data/favicons/';

if (!isset($_GET['url']) || empty($_GET['url'])) {
    http_response_code(400);
    exit('Missing url parameter.');
}

$feedWebsite = $_GET['url']; // website from feed table

$files = glob($FAVICON_DIR . '*.txt');
$faviconFile = '';

foreach ($files as $txtFile) {
    $url = trim(file_get_contents($txtFile));
    if ($url === $feedWebsite) {
        $icoFile = substr($txtFile, 0, -4) . '.ico';
        if (file_exists($icoFile)) {
            $faviconFile = $icoFile;
            break;
        }
    }
}

if (!$faviconFile) {
    http_response_code(404);
    exit('Favicon not found.');
}

header('Content-Type: image/x-icon');
readfile($faviconFile);
exit;
