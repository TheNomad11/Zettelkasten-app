<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Sessions last for 30 days
ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30); // 30 days
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 30); // 30 days


// Ensure session is properly configured
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();

// Authentication check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    error_log("Authentication failed - redirecting to login");
    header('Location: login.php');
    exit;
}

// FIXED: Add session activity tracking (security best practice)
$_SESSION['last_activity'] = time();


// CRITICAL: Ultra-aggressive cache prevention to stop browser caching
// This prevents the back button from showing cached logged-in pages after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // Always modified
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
// CRITICAL: Ultra-aggressive cache prevention to stop browser caching
// This prevents the back button from showing cached logged-in pages after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // Always modified
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// FIXED: Generate CSRF token with expiration to prevent stale token issues
if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

// FIXED: Regenerate CSRF token after 6 hours to prevent cached token issues
if (time() - $_SESSION['csrf_token_time'] > 21600) {
    error_log("CSRF token expired, regenerating");
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}


// FIXED: Add debugging for POST requests
//if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//    error_log("=== POST REQUEST DEBUG ===");
//    error_log("Session ID: " . session_id());
//    error_log("Session logged_in: " . (isset($_SESSION['logged_in']) ? $_SESSION['logged_in'] : 'NOT SET'));
//    error_log("Session CSRF: " . (isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : 'NOT SET'));
//    error_log("POST CSRF: " . (isset($_POST['csrf_token']) ? $_POST['csrf_token'] : 'NOT SET'));
//    error_log("CSRF Match: " . ((isset($_POST['csrf_token']) && isset($_SESSION['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) ? 'YES' : 'NO'));
//}

//if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//    error_log("POST received: " . print_r($_POST, true));
// }

// Set once, top of file (toggle to true only when actively debugging)
define('APP_DEBUG', false);

// Minimal, safe diagnostics
if (APP_DEBUG && $_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST event: action=" .
        (isset($_POST['create']) ? 'create' :
        (isset($_POST['edit']) ? 'edit' :
        (isset($_POST['delete']) ? 'delete' : 'unknown'))) .
        "; csrf_match=" .
        ((isset($_POST['csrf_token'], $_SESSION['csrf_token']) &&
          hash_equals($_POST['csrf_token'], $_SESSION['csrf_token'])) ? 'YES' : 'NO')
    );
}


require_once 'Parsedown.php';
$parsedown = new Parsedown();
$parsedown->setSafeMode(false);
$parsedown->setBreaksEnabled(true);  // Enable automatic line breaks!

$zettelsDir = 'zettels';
if (!file_exists($zettelsDir)) {
    mkdir($zettelsDir, 0755, true);
}

// POST: create, edit, delete - PROCESS FIRST BEFORE LOADING ZETTELS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    if (isset($_POST['create'])) {
        $title = trim($_POST['title']);
        if (empty($title) || strlen($title) > 255) die("Invalid title.");
        
        $content = trim($_POST['content']);
        if (empty($content)) die("Content cannot be empty.");

        $tags = array_filter(array_map('trim', explode(',', $_POST['tags'])));
        $tags = array_map(function($tag) {
            return preg_replace('/[^a-zA-Z0-9_\-]/', '', $tag);
        }, $tags);

        $links = array_filter(array_map('trim', explode(',', $_POST['links'])));
        $links = array_map(function($link) {
            return preg_replace('/[^a-zA-Z0-9_\-]/', '', $link);
        }, $links);

        $id = uniqid();
        $time = date('Y-m-d H:i:s');
        $zettel = [
            'id' => $id,
            'title' => $title,
            'content' => $content,
            'tags' => $tags,
            'links' => $links,
            'created_at' => $time,
            'updated_at' => $time
        ];

        $file = fopen($zettelsDir . '/' . $id . '.txt', 'w');
        if (flock($file, LOCK_EX)) {
            fwrite($file, json_encode($zettel, JSON_PRETTY_PRINT));
            flock($file, LOCK_UN);
        } else {
            die("Could not lock file for writing.");
        }
        fclose($file);

// Invalidate tag cache
    @unlink($zettelsDir . '/.tag_cache.json');

        if (isset($_POST['bookmarklet_mode']) && $_POST['bookmarklet_mode'] === '1') {
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Saved!</title></head><body>';
            echo '<h2>✓ Saved to Zettelkasten!</h2>';
            echo '<p>You can close this window now.</p>';
            echo '<script>setTimeout(function(){ window.close(); }, 2000);</script>';
            echo '</body></html>';
            exit;
        }

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;

 } elseif (isset($_POST['edit'])) {
    $id = $_POST['id'];
    if (!preg_match('/^[a-zA-Z0-9]+$/', $id)) die("Invalid Zettel ID.");
    
    // Path traversal protection
    $filepath = realpath($zettelsDir) . '/' . basename($id) . '.txt';
    if (strpos($filepath, realpath($zettelsDir)) !== 0) die("Path traversal detected");
    
    if (file_exists($filepath)) {

            $currentContent = file_get_contents($filepath);
            $currentZettel = json_decode($currentContent, true);
            
            $title = trim($_POST['title']);
            if (empty($title) || strlen($title) > 255) die("Invalid title.");
            
            $content = trim($_POST['content']);
            if (empty($content)) die("Content cannot be empty.");

            $tags = array_filter(array_map('trim', explode(',', $_POST['tags'])));
            $tags = array_map(function($tag) {
                return preg_replace('/[^a-zA-Z0-9_\-]/', '', $tag);
            }, $tags);

            $links = array_filter(array_map('trim', explode(',', $_POST['links'])));
            $links = array_map(function($link) {
                return preg_replace('/[^a-zA-Z0-9_\-]/', '', $link);
            }, $links);

            $zettel = [
                'id' => $id,
                'title' => $title,
                'content' => $content,
                'tags' => $tags,
                'links' => $links,
                'created_at' => $currentZettel['created_at'],
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $file = fopen($filepath, 'w');
            if (flock($file, LOCK_EX)) {
                fwrite($file, json_encode($zettel, JSON_PRETTY_PRINT));
                flock($file, LOCK_UN);
            } else {
                die("Could not lock file for writing.");
            }
            fclose($file);
        }
// Invalidate tag cache
@unlink($zettelsDir . '/.tag_cache.json');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;

} elseif (isset($_POST['delete'])) {
    $id = $_POST['id'];
    if (!preg_match('/^[a-zA-Z0-9]+$/', $id)) die("Invalid Zettel ID.");
    
    // Path traversal protection
    $filepath = realpath($zettelsDir) . '/' . basename($id) . '.txt';
    if (strpos($filepath, realpath($zettelsDir)) !== 0) die("Path traversal detected");
    
    if (file_exists($filepath)) {
        unlink($filepath);
    }

// Invalidate tag cache
    @unlink($zettelsDir . '/.tag_cache.json');

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Load all Zettels (indexed by ID) - AFTER POST processing
$zettels = [];
$files = glob($zettelsDir . '/*.txt');
foreach ($files as $file) {
    $content = file_get_contents($file);
    $zettel = json_decode($content, true);
    if ($zettel && !empty($zettel['id'])) {
        $zettels[$zettel['id']] = $zettel;
    }
}

// Sort by created_at descending, preserve keys (ID)
uasort($zettels, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Tags for cloud/autocomplete with caching
$tagCacheFile = $zettelsDir . '/.tag_cache.json';
$tagCacheAge = file_exists($tagCacheFile) ? (time() - filemtime($tagCacheFile)) : 3600;

// Use cached tags if less than 1 hour old
if (file_exists($tagCacheFile) && $tagCacheAge < 3600) {
    $allTags = json_decode(file_get_contents($tagCacheFile), true) ?? [];
} else {
    // Build tags from all zettels
    $allTags = [];
    foreach ($zettels as $z) {
        if (!empty($z['tags']) && is_array($z['tags'])) {
            $allTags = array_merge($allTags, $z['tags']);
        }
    }
    $allTags = array_unique(array_filter($allTags));
    
    // Cache the tags for 1 hour
    file_put_contents($tagCacheFile, json_encode($allTags));
}

// Search/tag logic (always preserve keys!)
$searchResults = [];
if (isset($_GET['search'])) {
    $term = strtolower(trim($_GET['search']));
    foreach ($zettels as $id => $z) {
        $score = 0;
        if (strpos(strtolower($z['title']), $term) !== false) $score += 3;
        if (strpos(strtolower($z['content']), $term) !== false) $score += 2;
        if (!empty($z['tags'])) foreach ($z['tags'] as $tag) {
            if (strpos(strtolower($tag), $term) !== false) {
                $score += 1;
                break;
            }
        }
        if ($score > 0) {
            $z['relevance_score'] = $score;
            $searchResults[$id] = $z;
        }
    }
    uasort($searchResults, function($a, $b) {
        return $b['relevance_score'] - $a['relevance_score'];
    });
} elseif (isset($_GET['tag'])) {
    $tag = strtolower(trim($_GET['tag']));
    foreach ($zettels as $id => $z) {
        if (!empty($z['tags']) && in_array($tag, array_map('strtolower', $z['tags']))) {
            $searchResults[$id] = $z;
        }
    }
    uasort($searchResults, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
}

// Internal linkify: [[id]] -> anchor with zettel title
function linkifyInternalZettels($content, $zettels) {
    return preg_replace_callback('/\[\[([a-zA-Z0-9]+)\]\]/', function($matches) use ($zettels) {
        $id = $matches[1];
        if (isset($zettels[$id])) {
            $title = htmlspecialchars($zettels[$id]['title']);
            return '<a href="?show=' . $id . '" class="internal-link">' . $title . '</a>';
        } else {
            return '<span class="broken-link">' . $id . '</span>';
        }
    }, $content);
}

function findRelatedZettels($current, $allZettels, $limit = 5) {
    $related = [];
    foreach ($allZettels as $id => $zettel) {
        if ($id == $current['id']) continue;
        $sharedTags = array_intersect($current['tags'] ?? [], $zettel['tags'] ?? []);
        $score = count($sharedTags);
        if ($score > 0) $related[$id] = ['title' => $zettel['title'], 'score' => $score];
    }
    uasort($related, function($a, $b) {
        return $b['score'] <=> $a['score'] ?: strcasecmp($a['title'], $b['title']);
    });
    return array_slice($related, 0, $limit);
}

function findSimilarZettels($current, $all, $limit = 5) {
    $scores = [];
    foreach ($all as $id => $z) {
        if ($id === $current['id']) continue;
        $score = count(array_intersect($current['tags'] ?? [], $z['tags'] ?? [])) * 2;
        $cw = array_map('strtolower', preg_split('/\s+/', $current['content']));
        $zw = array_map('strtolower', preg_split('/\s+/', $z['content']));
        $score += count(array_intersect($cw, $zw));
        if ($score > 0) $scores[$id] = $score;
    }
    arsort($scores);
    return array_slice($scores, 0, $limit, true);
}

function findBacklinks($id, $all) {
    $back = [];
    foreach ($all as $zid => $z) {
        if (!empty($z['links']) && in_array($id, $z['links'])) $back[$zid] = $z;
    }
    return $back;
}

// --- PAGINATION & SEARCH LOGIC ---
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));

if (isset($_GET['show'])) {
    $showId = $_GET['show'];
    $sourceZettels = isset($zettels[$showId]) ? [$showId => $zettels[$showId]] : [];
    $displayZettels = $sourceZettels;
    $totalPages = 1;
} elseif (isset($_GET['search'])) {
    $sourceZettels = $searchResults;
    $sourceKeys = array_keys($sourceZettels);
    $slicedKeys = array_slice($sourceKeys, ($page-1)*$perPage, $perPage);
    $displayZettels = [];
    foreach ($slicedKeys as $key) $displayZettels[$key] = $sourceZettels[$key];
    $totalPages = max(1, ceil(count($sourceZettels) / $perPage));
} elseif (isset($_GET['tag'])) {
    $sourceZettels = $searchResults;
    $sourceKeys = array_keys($sourceZettels);
    $slicedKeys = array_slice($sourceKeys, ($page-1)*$perPage, $perPage);
    $displayZettels = [];
    foreach ($slicedKeys as $key) $displayZettels[$key] = $sourceZettels[$key];
    $totalPages = max(1, ceil(count($sourceZettels) / $perPage));
} else {
    $sourceZettels = !empty($searchResults) ? $searchResults : $zettels;
    $sourceKeys = array_keys($sourceZettels);
    $slicedKeys = array_slice($sourceKeys, ($page-1)*$perPage, $perPage);
    $displayZettels = [];
    foreach ($slicedKeys as $key) $displayZettels[$key] = $sourceZettels[$key];
    $totalPages = ceil(count($sourceZettels) / $perPage);
}

// Check if this is bookmarklet popup mode
$isBookmarkletMode = isset($_GET['bookmarklet']) && $_GET['bookmarklet'] === '1';
$prefillUrl = isset($_GET['url']) ? htmlspecialchars($_GET['url']) : '';
$prefillTitle = isset($_GET['pagetitle']) ? htmlspecialchars($_GET['pagetitle']) : '';

// Check if we're showing a single zettel
$isSingleView = isset($_GET['show']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zettelkasten</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="jquery-ui-autocomplete.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2c3e50">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="Zettelkasten">
    <link rel="apple-touch-icon" href="icon-192.png">
    
</head>

</head>
<body>
    <div class="container">
        
<?php if ($isBookmarkletMode): ?>
        <!-- BOOKMARKLET POPUP MODE -->
        <div class="popup-mode">
            <h2>📌 Save to Zettelkasten</h2>
            <form method="POST" class="create-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="bookmarklet_mode" value="1">
                
                <label for="title">Title *</label>
                <input type="text" id="title" name="title" value="<?= $prefillTitle ?>" required>
                
                <label for="content">Content / URL *</label>
                <textarea id="content" name="content" required><?= $prefillUrl ?></textarea>
                
                <label for="tags">Tags (comma-separated)</label>
                <input type="text" id="tags" name="tags" placeholder="web, article, research">
                
                <label for="links">Links to other Zettels (comma-separated IDs)</label>
                <input type="text" id="links" name="links" placeholder="abc123, def456">
                
                <button type="submit" name="create">💾 Save Zettel</button>
            </form>
        </div>
        
<?php else: ?>
        <!-- NORMAL MODE -->
        <h1>🗂️ <a href="index.php">Zettelkasten</a></h1>
        
        <div style="text-align: right; margin-bottom: 20px;">
            <span style="color: #7f8c8d; margin-right: 15px;">👤 <?= htmlspecialchars($_SESSION['username']) ?></span>
            <a href="logout.php" style="padding: 8px 16px; background: #e74c3c; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9em;">Logout</a>
        </div>
        
        <?php if ($isSingleView): ?>
            <!-- Back link for single zettel view -->
            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="back-link">← Back to All Zettels</a>
        <?php else: ?>
            <!-- BOOKMARKLET SECTION - Only show when not in single view -->
            <div class="bookmarklet-section">
                <h3>🔖 Quick Capture Bookmarklet</h3>
                <p>Drag this link to your bookmarks bar to quickly save web pages to your Zettelkasten:</p>
                <?php 
                $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . htmlspecialchars($_SERVER['HTTP_HOST']) . htmlspecialchars($_SERVER['PHP_SELF']);
                $bookmarkletCode = "javascript:(function(){var url=encodeURIComponent(window.location.href);var title=encodeURIComponent(document.title);window.open('" . $currentUrl . "?bookmarklet=1&url='+url+'&pagetitle='+title,'Zettelkasten','width=600,height=700,scrollbars=yes');})();";
                ?>
                <a href="<?= htmlspecialchars($bookmarkletCode) ?>" class="bookmarklet-link" onclick="alert('Drag this link to your bookmarks bar instead of clicking it!'); return false;">➕ Add to Zettelkasten</a>
                <div class="bookmarklet-instructions">
                    <strong>How to use:</strong> Drag the button above to your browser's bookmarks bar. When you're on a webpage you want to save, click the bookmarklet to open a popup with the URL and title pre-filled.
                </div>
            </div>
            
            <!-- Search Form - Only show when not in single view -->
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search Zettels..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button type="submit">🔍 Search</button>
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="padding: 10px 20px; background: #95a5a6; color: white; text-decoration: none; border-radius: 4px;">Clear</a>
            </form>
            
            <!-- Tag Cloud - Only show when not in single view -->
            <?php if (!empty($allTags)): ?>
            <div class="tag-cloud">
                <strong>Tags:</strong>
                <?php foreach ($allTags as $tag): ?>
                    <a href="?tag=<?= urlencode($tag) ?>">#<?= htmlspecialchars($tag) ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
<!-- Create New Zettel Button - Only show when not in single view -->
<div style="text-align: center; margin: 30px 0;">
    <button onclick="document.getElementById('createModal').style.display='block'" class="btn-create-new">
        ➕ Create New Zettel
    </button>
</div>

<!-- Create Form Modal (hidden by default) -->
<div id="createModal" class="create-form" style="display: none;">
    <span class="modal-close" onclick="document.getElementById('createModal').style.display='none'">&times;</span>
    <h2>Create New Zettel</h2>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        
        <label for="title">Title *</label>
        <input type="text" id="title" name="title" required>
        
        <label for="content">Content * (Markdown supported, use [[id]] for internal links)</label>
        <textarea id="content" name="content" rows="10" required></textarea>
        
        <label for="tags">Tags (comma-separated)</label>
        <input type="text" id="tags" name="tags" placeholder="philosophy, productivity, ideas">
        
        <label for="links">Links to other Zettels (comma-separated IDs)</label>
        <input type="text" id="links" name="links" placeholder="abc123, def456">
        
        <button type="submit" name="create">Create Zettel</button>
        <button type="button" onclick="document.getElementById('createModal').style.display='none'" style="background: #95a5a6; margin-left: 10px;">Cancel</button>
    </form>
</div>

<?php endif; ?>

<!-- Display Zettels -->

        
        <!-- Display Zettels -->
        <h2 id="zettel-content">
            <?php if (isset($_GET['search'])): ?>
                Search Results for "<?= htmlspecialchars($_GET['search']) ?>"
            <?php elseif (isset($_GET['tag'])): ?>
                Zettels tagged with #<?= htmlspecialchars($_GET['tag']) ?>
            <?php elseif (isset($_GET['show'])): ?>
                Zettel Details
            <?php else: ?>
                All Zettels
            <?php endif; ?>
        </h2>
        
        <?php if (empty($displayZettels)): ?>
            <div class="no-results">
                <?php if (isset($_GET['search']) || isset($_GET['tag'])): ?>
                    No Zettels found for your search.
                <?php else: ?>
                    No Zettels yet. Create your first one above!
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php foreach ($displayZettels as $id => $z): 
            $backlinks = findBacklinks($id, $zettels);
            $related = findRelatedZettels($z, $zettels);
            $similar = findSimilarZettels($z, $zettels);
            $linkedContent = linkifyInternalZettels($z['content'], $zettels);
        ?>
        <div class="zettel">
            <div class="zettel-header">
                <div>
                    <h3 class="zettel-title"><?= htmlspecialchars($z['title']) ?></h3>
                    <div class="zettel-meta">
                        ID: <code><?= htmlspecialchars($z['id']) ?></code> | 
                        Created: <?= htmlspecialchars($z['created_at']) ?> | 
                        Updated: <?= htmlspecialchars($z['updated_at']) ?>
                    </div>
                </div>
            </div>
            
            <div class="zettel-content">
                <?= $parsedown->text($linkedContent) ?>
            </div>
            
            <?php if (!empty($z['tags'])): ?>
            <div class="zettel-tags">
                <?php foreach ($z['tags'] as $tag): ?>
                    <span><a href="?tag=<?= urlencode($tag) ?>" style="color: inherit; text-decoration: none;">#<?= htmlspecialchars($tag) ?></a></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($z['links'])): ?>
            <div class="zettel-links">
                <strong>Links to:</strong>
                <?php foreach ($z['links'] as $link): ?>
                    <?php if (isset($zettels[$link])): ?>
                        <a href="?show=<?= htmlspecialchars($link) ?>"><?= htmlspecialchars($zettels[$link]['title']) ?></a>
                    <?php else: ?>
                        <span style="color: #e74c3c;"><?= htmlspecialchars($link) ?> (not found)</span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['show']) && $_GET['show'] == $id): ?>
                <!-- Show related, similar, backlinks only in detail view -->
                <?php if (!empty($backlinks)): ?>
                <div class="related-section">
                    <h4>🔗 Backlinks (Zettels linking here):</h4>
                    <ul>
                        <?php foreach ($backlinks as $bid => $bzettel): ?>
                            <li><a href="?show=<?= htmlspecialchars($bid) ?>"><?= htmlspecialchars($bzettel['title']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($related)): ?>
                <div class="related-section">
                    <h4>📚 Related Zettels (by tags):</h4>
                    <ul>
                        <?php foreach ($related as $rid => $rdata): ?>
                            <li><a href="?show=<?= htmlspecialchars($rid) ?>"><?= htmlspecialchars($rdata['title']) ?></a> (<?= intval($rdata['score']) ?> shared tags)</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($similar)): ?>
                <div class="related-section">
                    <h4>🔍 Similar Zettels (by content):</h4>
                    <ul>
                        <?php foreach ($similar as $sid => $score): ?>
                            <?php if (isset($zettels[$sid])): ?>
                                <li><a href="?show=<?= htmlspecialchars($sid) ?>"><?= htmlspecialchars($zettels[$sid]['title']) ?></a></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="zettel-actions">
                <?php if (!isset($_GET['show']) || $_GET['show'] != $id): ?>
                    <a href="?show=<?= htmlspecialchars($id) ?>" class="btn-view">View Details</a>
                <?php endif; ?>
                <button class="btn-edit" onclick="document.getElementById('edit-<?= htmlspecialchars($id) ?>').style.display='block'; this.style.display='none';">Edit</button>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this Zettel?');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                    <button type="submit" name="delete" class="btn-delete">Delete</button>
                </form>
            </div>
            
            <!-- Edit Form (hidden by default) -->
            <div id="edit-<?= htmlspecialchars($id) ?>" class="edit-form" style="display: none;">
                <h3>Edit Zettel</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                    
                    <label>Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($z['title']) ?>" required>
                    
                    <label>Content</label>
                    <textarea name="content" required><?= htmlspecialchars($z['content']) ?></textarea>
                    
                    <label>Tags (comma-separated)</label>
                    <input type="text" id="tags-edit-<?= htmlspecialchars($id) ?>" name="tags" value="<?= htmlspecialchars(implode(', ', $z['tags'])) ?>">
                    
                    <label>Links (comma-separated IDs)</label>
                    <input type="text" id="links-edit-<?= htmlspecialchars($id) ?>" name="links" value="<?= htmlspecialchars(implode(', ', $z['links'])) ?>">
                    
                    <button type="submit" name="edit" class="btn-save">Save Changes</button>
                    <button type="button" class="btn-cancel" onclick="document.getElementById('edit-<?= htmlspecialchars($id) ?>').style.display='none';">Cancel</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">← Previous</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
<?php endif; ?>
        
    </div>

<script>
// Tag autocomplete (comma-separated)
(function() {
    const allTags = <?= json_encode(array_values($allTags)) ?>;
    
    function split(val) {
        return val.split(/,\s*/);
    }
    
    function extractLast(term) {
        return split(term).pop();
    }
    
    // Setup autocomplete for tag inputs
    function setupTagAutocomplete(selector) {
        $(selector)
            .on("keydown", function(event) {
                if (event.keyCode === $.ui.keyCode.TAB && $(this).autocomplete("instance").menu.active) {
                    event.preventDefault();
                }
            })
            .autocomplete({
                minLength: 0,
                source: function(request, response) {
                    const term = extractLast(request.term);
                    const existing = split(request.term).map(t => t.trim().toLowerCase()).filter(Boolean);
                    
                    // Filter out already entered tags
                    const available = allTags.filter(tag => 
                        tag.toLowerCase().indexOf(term.toLowerCase()) > -1 &&
                        !existing.includes(tag.toLowerCase())
                    );
                    response(available);
                },
                focus: function() {
                    return false; // Prevent value inserted on focus
                },
                select: function(event, ui) {
                    const terms = split(this.value);
                    terms.pop(); // Remove the current input
                    terms.push(ui.item.value); // Add the selected item
                    terms.push(""); // Add placeholder to get the comma at the end
                    this.value = terms.join(", ");
                    return false;
                }
            });
    }
    
    // Apply to create form
    setupTagAutocomplete('#tags');
    
    // Apply to all edit forms
    <?php foreach ($displayZettels as $id => $z): ?>
    setupTagAutocomplete('#tags-edit-<?= htmlspecialchars($id) ?>');
    <?php endforeach; ?>
})();

// Zettel ID autocomplete for links
(function() {
    const allZettels = <?= json_encode(array_map(function($z) { 
        return ['id' => $z['id'], 'title' => $z['title']]; 
    }, $zettels)) ?>;
    
    function split(val) {
        return val.split(/,\s*/);
    }
    
    function extractLast(term) {
        return split(term).pop();
    }
    
    function setupLinkAutocomplete(selector) {
        $(selector)
            .on("keydown", function(event) {
                if (event.keyCode === $.ui.keyCode.TAB && $(this).autocomplete("instance").menu.active) {
                    event.preventDefault();
                }
            })
            .autocomplete({
                minLength: 0,
                source: function(request, response) {
                    const term = extractLast(request.term);
                    const existing = split(request.term).map(t => t.trim()).filter(Boolean);
                    
                    const results = allZettels
                        .filter(z => 
                            (z.title.toLowerCase().indexOf(term.toLowerCase()) > -1 || 
                             z.id.toLowerCase().indexOf(term.toLowerCase()) > -1) &&
                            !existing.includes(z.id)
                        )
                        .map(z => ({
                            label: z.title + ' (' + z.id + ')',
                            value: z.id
                        }));
                    
                    response(results);
                },
                focus: function() {
                    return false;
                },
                select: function(event, ui) {
                    const terms = split(this.value);
                    terms.pop();
                    terms.push(ui.item.value);
                    terms.push("");
                    this.value = terms.join(", ");
                    return false;
                }
            });
    }
    
    // Apply to create form
    setupLinkAutocomplete('#links');
    
    // Apply to all edit forms
    <?php foreach ($displayZettels as $id => $z): ?>
    setupLinkAutocomplete('#links-edit-<?= htmlspecialchars($id) ?>');
    <?php endforeach; ?>
})();

// Register service worker for PWA
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('sw.js')
    .then(reg => {
      reg.update();
      console.log('Service Worker registered');
    })
    .catch(err => console.log('Service Worker registration failed'));
}


// Modal functions
function openCreateModal() {
    document.getElementById('createModal').style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent background scroll
    // Focus on title field
    setTimeout(() => document.getElementById('title').focus(), 100);
}

function closeCreateModal() {
    document.getElementById('createModal').style.display = 'none';
    document.body.style.overflow = 'auto'; // Re-enable scroll
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('createModal');
    if (event.target == modal) {
        closeCreateModal();
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeCreateModal();
    }
});

</script>


<script>
// Prevent back button from showing cached pages after logout
(function() {
    window.history.forward();

    // Clear browser cache on page load
    window.onpageshow = function(event) {
        if (event.persisted) {
            // Page loaded from cache (back button)
            // Force reload from server
            window.location.reload();
        }
    };

    // Prevent caching
    window.onunload = function() {
        null;
    };
})();
</script>
</body>
</html>
