<?php
$DB_FILE = '/volume3/web/jjjp.ca/news/data/users/Jesse/db.sqlite';
$ITEMS_PER_PAGE = 20;

$last_date = isset($_GET['last_date']) ? (int)$_GET['last_date'] : 0;
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
if (!$last_date || !$last_id) exit;

try {
    $db = new PDO('sqlite:' . $DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    exit('DB error');
}

// Fetch feeds
$feedsStmt = $db->query("SELECT * FROM feed");
$feeds = [];
while ($row = $feedsStmt->fetch(PDO::FETCH_ASSOC)) {
    $feeds[$row['id']] = $row;
}

// Fetch next batch using date/id cursor
$stmt = $db->prepare("
    SELECT *
    FROM entry
    WHERE (date < :last_date OR (date = :last_date AND id < :last_id))
    ORDER BY date DESC, id DESC
    LIMIT :limit
");
$stmt->bindValue(':last_date', $last_date, PDO::PARAM_INT);
$stmt->bindValue(':last_id', $last_id, PDO::PARAM_INT);
$stmt->bindValue(':limit', $ITEMS_PER_PAGE, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$items) exit;

// new last item for next batch
$last_item = end($items);
$last_date_new = $last_item['date'];
$last_id_new = $last_item['id'];

foreach ($items as $item):
    $feed = $feeds[$item['id_feed']] ?? null;
?>
<div class="mb-3">
    <div class="headline fw-semibold" data-target="a<?= (int)$item['id'] ?>">
        <?php if ($feed && !empty($feed['website'])):
            $faviconUrl = 'favicon.php?url=' . urlencode($feed['website']);
        ?>
        <img class="favicon" src="<?= htmlspecialchars($faviconUrl) ?>" alt="">
        <?php endif; ?>
        <span class="headline-text">
            <?php if ($feed): ?><span class="feed-name"><?= htmlspecialchars($feed['name']) ?> —</span><?php endif; ?>
            <?= htmlspecialchars($item['title']) ?>
        </span>
    </div>
    <div class="article border rounded p-3 mt-2" id="a<?= (int)$item['id'] ?>">
        <?php
        $content = preg_replace('/<img(.*?)>/', '<img$1 class="img-fluid">', $item['content']);
        echo $content;

        if (!empty($item['attributes'])) {
            $attr = json_decode($item['attributes'], true);
            if (!empty($attr['enclosures'])) {
                foreach ($attr['enclosures'] as $enclosure) {
                    if (!empty($enclosure['url'])) {
                        echo '<p><img src="' . htmlspecialchars($enclosure['url']) . '" class="img-fluid"></p>';
                    }
                }
            }
        }
        ?>
        <p><a href="<?= htmlspecialchars($item['link']) ?>" target="_blank" rel="noreferrer">Read original</a></p>
    </div>
</div>
<?php endforeach; ?>

<div id="load-more" data-last-date="<?= $last_date_new ?>" data-last-id="<?= $last_id_new ?>"></div>
