<?php
// -------- CONFIGURATION --------
$DB_FILE = '/volume3/web/jjjp.ca/news/data/users/Jesse/db.sqlite';
$DAYS_BACK = 1; // fetch items from the last X days
$BASE_URL = 'https://jjjp.ca/news/p'; // your FreshRSS base URL

// -------- DATABASE CONNECTION --------
try {
    $db = new PDO('sqlite:' . $DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Failed to connect to FreshRSS database: ' . $e->getMessage());
}

// -------- FETCH FEEDS --------
$feedsStmt = $db->query("SELECT * FROM feed");
$feeds = [];
while ($row = $feedsStmt->fetch(PDO::FETCH_ASSOC)) {
    $feeds[$row['id']] = $row;
}

// -------- FETCH RECENT ITEMS --------
$since_time = time() - ($DAYS_BACK * 86400);
$itemsStmt = $db->prepare("
    SELECT *
    FROM entry
    WHERE date >= :since
    ORDER BY date DESC
");
$itemsStmt->execute([':since' => $since_time]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>RSS Reader</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Standard favicon -->
<link rel="icon" type="image/png" sizes="32x32" href="/reader/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/reader/favicon-16x16.png">
<link rel="shortcut icon" href="/reader/favicon.ico">

<!-- Apple touch icon -->
<link rel="apple-touch-icon" sizes="180x180" href="/reader/apple-touch-icon.png">

<!-- Android / Chrome manifest -->
<link rel="manifest" href="/reader/site.webmanifest">

<style>
/* Headline row */
.headline {
    cursor: pointer;
    display: flex;
    align-items: flex-start;   /* align favicon to first text line */
    gap: 6px;
    line-height: 1.3;
}

/* Favicon */
.favicon {
    width: 16px;
    height: 16px;
    margin-top: 2px;           /* optical alignment */
    flex-shrink: 0;
}

/* Text container for ellipsis */
.headline-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
    width: 100%;
}

/* Feed name (desktop) */
.feed-name {
    margin-right: 4px;
}

/* Article body */
.article {
    display: none;
}

.article img figure {
    max-width: 100%;
    height: auto;
}

.article-date {
    font-size: 0.85rem;
    color: #666;
}

.article-content img, .article-content {
    margin-bottom: 1rem;
    margin-top: 1rem;
}

/* Mobile behavior: hide feed name */
@media (max-width: 840px) {
    .feed-name {
        display: none;
    }
}

.article-content p {
    line-height: 1.6;
}

.article-content p q,

.article-content .inline-quote-block {
    background-color: #cce5ff; /* light contrast */
    font-weight: 450;
    border-radius: 0.3rem;
}

.greyed-text {
    color: #999;
}

.article-content .greyed-text a:link,
.article-content .greyed-text a:visited,
.article-content .greyed-text a:hover,
.article-content .greyed-text a:active {
    color: #0d6efd;
    text-decoration: underline;
}

</style>
</head>

<body class="bg-white">

<nav class="navbar navbar-light border-bottom mb-3 position-sticky" style="top: 0; background-color: #fff; z-index: 1000;">
<div class="container d-flex justify-content-between align-items-center">
    <a class="navbar-brand" href="/reader/">RSS Reader</a>
    <a class="nav-link px-2 text-body-secondary" href="https://jjjp.ca/">Home</a>
</div>
</nav>


<div class="container">

<?php if (!$items): ?>
    <p>No recent items found.</p>
<?php else: ?>
<?php
$lastTitle = null; // track last title to skip consecutive duplicates
foreach ($items as $item):
    if ($item['title'] === $lastTitle) continue;
    $lastTitle = $item['title'];

    $item['title'] = str_replace("- Reuters", "", $item['title']);
    $item['title'] = str_replace("- AP News", "", $item['title']);

    $feed = $feeds[$item['id_feed']] ?? null;

    $title = trim($item['title']);
    // echo "Title before check: " . htmlspecialchars($title) . "<br>";
    if (empty($title) || preg_match('#^https?://#', $title)) {
        $title = trim(strip_tags($item['content']));
        $title = mb_substr($title, 0, 100, 'UTF-8'); // first 100 characters
        $title .= '...'; // optional
    $item['title'] = $title;
    }

// foreach ($items as $item):
    
//     if ($item['title']  === $lastTitle) continue;
//     $lastTitle = $item['title'];

//     $feed = $feeds[$item['id_feed']] ?? null;
// ?>
<div class="mb-3">
    <div class="headline fw-semibold" data-target="a<?= (int)$item['id'] ?>">
        <?php if ($feed && !empty($feed['website'])):
            $faviconUrl = 'favicon.php?url=' . urlencode($feed['website']);

            if ($feed['name'] === 'Reuters') {
                $faviconUrl = "16b6fed2.ico";
            }
            if ($feed['name'] === 'Japan Times') {
                $faviconUrl = "japantimes.ico";
            }
            if ($feed['name'] === 'Associated Press') {
                $faviconUrl = "apnews.ico";
            }
        ?>
            <img class="favicon" src="<?= htmlspecialchars($faviconUrl) ?>" alt="">
        <?php endif; ?>

        <span class="headline-text">
            <?php if ($feed): ?>
                <span class="feed-name"><?= htmlspecialchars($feed['name']) ?> —</span>
            <?php endif; ?>
            <?= htmlspecialchars(html_entity_decode($item['title'])) ?>
        </span>
    </div>

    <div class="article border rounded p-3 mt-2" id="a<?= (int)$item['id'] ?>">

    <div class="headline-full fw-semibold">
        <?php if ($feed && !empty($feed['website'])):
            $faviconUrl = 'favicon.php?url=' . urlencode($feed['website']);
            if ($feed['name'] === 'Reuters') {
                $faviconUrl = "16b6fed2.ico";
            }
            if ($feed['name'] === 'Japan Times') {
                $faviconUrl = "japantimes.ico";
            }
            if ($feed['name'] === 'Associated Press') {
                $faviconUrl = "apnews.ico";
            }
        ?>
            <img class="favicon" src="<?= htmlspecialchars($faviconUrl) ?>" alt="">
        <?php endif; ?>

        <a class="headline-text-full text-decoration-none" href="<?= htmlspecialchars($item['link']) ?>" target="_blank" rel="noreferrer">
            <?php if ($feed): ?>
                <span class="feed-name-full"><?= htmlspecialchars($feed['name']) ?> —</span>
            <?php endif; ?><span class="headline-only">
            <?= htmlspecialchars(html_entity_decode($item['title'])) ?></span>
        </a>
    </div>

    <?php if ($item['date']): ?>
        <div class="article-date">
            <?= date('M j, Y H:i', (int)$item['date']) ?>
        </div>
        <?php endif; ?>
            

    <div class="article-content">
        <?php
        // Main content
        $content = $item['content'];
        $content = preg_replace('/<img(.*?)>/', '<img$1 class="img-fluid rounded">', $content);
        $content = preg_replace('/[\s\x{00A0}\x{C2A0}]*Reuters/u', '', $content);
        $content = preg_replace('/[\s\x{00A0}\x{C2A0}]*AP News/u', '', $content);
        
        if ($feed['name'] === 'Fox News') {
            // Remove "CLICK HERE" promotional text
            $content = preg_replace('/<p>.*?CLICK HERE.*?<\/p>/i', '', $content);
            
            // For Fox News: grey out non-quote text, leave quotes normal
            // First, protect anchor tag contents
            $parts = preg_split('/(<a\b[^>]*>.*?<\/a>|<[^>]+>)/s', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
            
            foreach ($parts as &$part) {
                // Skip HTML tags and complete anchor elements
                if (strpos($part, '<') === 0) continue;
                
                // Split text into quote and non-quote segments
                $segments = preg_split('/([""][^"""]+?[""])/u', $part, -1, PREG_SPLIT_DELIM_CAPTURE);
                
                foreach ($segments as $i => &$segment) {
                    // Odd indices are the captured quotes
                    if ($i % 2 === 0) {
                        // Even indices are non-quote text - wrap in grey class
                        if (trim($segment) !== '') {
                            $segment = '<span class="greyed-text">' . $segment . '</span>';
                        }
                    }
                    // Leave quotes (odd indices) untouched
                }
                $part = implode('', $segments);
            }
            $content = implode('', $parts);
        }
        
        echo $content;

        // Enclosures (images not inline in content)
        if (!empty($item['attributes'])) {
            $attr = json_decode($item['attributes'], true);
            if (!empty($attr['enclosures'])) {
                foreach ($attr['enclosures'] as $enclosure) {
                    if (!empty($enclosure['url'])) {
                        echo '<p><img src="' . htmlspecialchars($enclosure['url']) . '" class="img-fluid rounded"></p>';
                    }
                }
            }
        }
        ?>
    </div>

        <div class="text-end mt-2">

        <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard(this)" title="Copy link">
            Copy
        </button>
        <button class="btn btn-sm btn-outline-secondary" onclick="shareArticle(this)" title="Share">
            Share
        </button>

        <button class="btn btn-sm btn-outline-secondary"
            onclick="searchOtherSources(this)"
            title="Find other coverage">
        Search
        </button>

        <button class="btn btn-sm btn-outline-primary me-1" href="#" target="_blank" onclick="openArticle(this)" title="Open article">
            Open
        </button>
        </div>

    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<footer class="py-4 px-4 text-center text-body-secondary small">
  <p class="mb-0">
  <?= count($items) ?> items loaded in <span id="loadTime"></span> seconds.
  </p>
</footer>

<script>
document.querySelectorAll('.headline').forEach(function (headline) {
    headline.addEventListener('click', function () {
        var targetId = headline.getAttribute('data-target');
        var article = document.getElementById(targetId);

        if (!article) return;

        // Close all others
        document.querySelectorAll('.article').forEach(function (a) {
            if (a !== article) a.style.display = 'none';
        });

        // Toggle current
        var wasHidden = article.style.display !== 'block';
        article.style.display = wasHidden ? 'block' : 'none';

        if (wasHidden) {
            // Get the navbar height
            var navbar = document.querySelector('.navbar');
            var navbarHeight = navbar ? navbar.offsetHeight : 0;
            
            // Scroll to the article with offset
            setTimeout(function() {
                var articlePosition = article.getBoundingClientRect().top + window.pageYOffset;
                var offsetPosition = articlePosition - navbarHeight - 10; // 10px extra padding
                
                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }, 100); // Small delay to ensure display change has taken effect
        }
    });
});
</script>

<script>
  window.addEventListener('load', () => {
    document.getElementById('loadTime').textContent = (performance.now() / 1000).toFixed(3);
  });

  function copyToClipboard(button) {
    const article = button.closest('.article');
    const url = article.querySelector('.headline-text-full').href;
    
    navigator.clipboard.writeText(url).then(() => {
        const originalText = button.textContent;
        button.textContent = 'Copied!';
        setTimeout(() => button.textContent = originalText, 2000);
    }).catch(err => {
        console.error('Failed to copy:', err);
    });
}

function shareArticle(button) {
    const article = button.closest('.article');
    const url = article.querySelector('.headline-text-full').href;
    const title = article.querySelector('.headline-only').textContent.trim();
    
    if (navigator.share) {
        navigator.share({
            title: title,
            text: `"${title}"`,
            url: url
        }).catch(err => console.log('Share cancelled or failed', err));
    } else {
        // Fallback to copy
        copyToClipboard(button.previousElementSibling);
    }
}

function openArticle(button) {
    const article = button.closest('.article');
    const url = article.querySelector('.headline-text-full').href;
    window.open(url, '_blank');
}
function saveToInstapaper(button) {
    const article = button.closest('.article');
    const url = article.querySelector('.headline-text-full').href;

    // Instapaper URL scheme
    const instapaperURL = 'instapaper://save?url=' + encodeURIComponent(url);

    // Attempt to open Instapaper app
    window.location.href = instapaperURL;
}

function searchOtherSources(button) {
    const article = button.closest('.article');
    const headline = article.querySelector('.headline-only')?.textContent.trim();
    const source = article.querySelector('.source-name')?.textContent.trim();

    if (!headline) return;

    let query = headline;

    if (source) {
        const domain = source.toLowerCase().replace(/\s+/g, '') + '.com';
        query += ' -site:' + domain;
    }

    const url = 'https://www.google.com/search?q=' + encodeURIComponent(query);
    //const url = 'https://www.google.com/search?q=' + encodeURIComponent(query) + ' -site:foxnews.com';

    
    window.open(url, '_blank');
}

</script>

</body>
</html>
