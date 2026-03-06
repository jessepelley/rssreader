<?php
// -------- CORS --------
// Set first — ensures the header is present on every response (200, 403, 500, etc.).
// Without it, Safari throws a generic TypeError and the browser shows no console detail.
$allowed_origin = 'https://news.jjjp.ca';
header("Access-Control-Allow-Origin: $allowed_origin");

// Handle browser preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

// Reject requests from other origins
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== $allowed_origin) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// -------- CONFIGURATION --------
$DB_FILE   = '/volume3/web/jjjp.ca/news/data/users/Jesse/db.sqlite';
$DAYS_BACK = 1;
$PAGE_SIZE = 20;

// -------- DATABASE --------
try {
    $db = new PDO('sqlite:' . $DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// -------- FEEDS LOOKUP --------
$feeds = [];
foreach ($db->query('SELECT * FROM feed') as $row) {
    $feeds[(int)$row['id']] = ['name' => $row['name'], 'website' => $row['website']];
}

// -------- ATTACH FEED INFO TO ENTRIES --------
function attachFeed(array &$entries, array $feeds): void {
    foreach ($entries as &$entry) {
        $feed = $feeds[(int)$entry['id_feed']] ?? null;
        $entry['feed_name']    = $feed['name']    ?? '';
        $entry['feed_website'] = $feed['website'] ?? '';
    }
    unset($entry);
}

// -------- ROUTING --------
$action = $_GET['action'] ?? 'feed';

if ($action === 'feed') {
    $since = time() - ($DAYS_BACK * 86400);
    $stmt  = $db->prepare('SELECT * FROM entry WHERE date >= :since ORDER BY date DESC');
    $stmt->execute([':since' => $since]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    attachFeed($entries, $feeds);
    echo json_encode(['entries' => $entries], JSON_INVALID_UTF8_SUBSTITUTE);

} elseif ($action === 'more') {
    $last_date = filter_input(INPUT_GET, 'last_date', FILTER_VALIDATE_INT);
    $last_id   = filter_input(INPUT_GET, 'last_id',   FILTER_VALIDATE_INT);

    if (!$last_date || !$last_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing or invalid parameters: last_date and last_id required']);
        exit;
    }

    $stmt = $db->prepare('
        SELECT * FROM entry
        WHERE (date < :last_date OR (date = :last_date AND id < :last_id))
        ORDER BY date DESC, id DESC
        LIMIT :lim
    ');
    $stmt->bindValue(':last_date', $last_date, PDO::PARAM_INT);
    $stmt->bindValue(':last_id',   $last_id,   PDO::PARAM_INT);
    $stmt->bindValue(':lim',       $PAGE_SIZE, PDO::PARAM_INT);
    $stmt->execute();
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    attachFeed($entries, $feeds);
    echo json_encode(['entries' => $entries], JSON_INVALID_UTF8_SUBSTITUTE);

} elseif ($action === 'search') {
    $raw = trim($_GET['q'] ?? '');
    if (strlen($raw) < 2) { echo json_encode(['entries' => []]); exit; }
    $q    = '%' . $raw . '%';
    $stmt = $db->prepare('
        SELECT e.* FROM entry e
        INNER JOIN feed f ON e.id_feed = f.id
        WHERE e.title LIKE :q OR e.content LIKE :q OR f.name LIKE :q
        ORDER BY e.date DESC
        LIMIT 50
    ');
    $stmt->execute([':q' => $q]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    attachFeed($entries, $feeds);
    echo json_encode(['entries' => $entries], JSON_INVALID_UTF8_SUBSTITUTE);

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
}
