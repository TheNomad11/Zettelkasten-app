<?php
// Zettelkasten Web App in a single PHP file
require_once 'Parsedown.php';

// Start session for CSRF protection
session_start();

// Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error: $errstr in $errfile on line $errline");
    die("An error occurred. Please try again later.");
});

$parsedown = new Parsedown();
$parsedown->setSafeMode(true); // Disable HTML in Markdown

// Directory to store Zettels
$zettelsDir = 'zettels';
if (!file_exists($zettelsDir)) {
    mkdir($zettelsDir, 0755, true);
}

// Load all Zettels
$zettels = [];
$files = glob($zettelsDir . '/*.txt');
foreach ($files as $file) {
    $content = file_get_contents($file);
    $zettel = json_decode($content, true);
    if ($zettel && !empty($zettel['id'])) {
        $zettels[$zettel['id']] = $zettel;
    }
}

// Sort Zettels by creation date in descending order
$zettels_sorted = array_values($zettels);
usort($zettels_sorted, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$zettels_by_id = [];
foreach ($zettels_sorted as $z) {
    $zettels_by_id[$z['id']] = $z;
}
$zettels = $zettels_by_id;

// All tags for tag cloud and autocomplete
$allTags = [];
foreach ($zettels as $zettel) {
    if (!empty($zettel['tags']) && is_array($zettel['tags'])) {
        $allTags = array_merge($allTags, $zettel['tags']);
    }
}
$allTags = array_unique(array_filter($allTags));

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    if (isset($_POST['create'])) {
        // Validate and sanitize inputs
        $title = trim($_POST['title']);
        if (empty($title) || strlen($title) > 255) {
            die("Invalid title.");
        }

        $content = trim($_POST['content']);
        if (empty($content)) {
            die("Content cannot be empty.");
        }

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

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } elseif (isset($_POST['edit'])) {
        $id = $_POST['id'];
        if (!preg_match('/^[a-zA-Z0-9]+$/', $id)) {
            die("Invalid Zettel ID.");
        }

        if (isset($zettels[$id])) {
            $title = trim($_POST['title']);
            if (empty($title) || strlen($title) > 255) {
                die("Invalid title.");
            }

            $content = trim($_POST['content']);
            if (empty($content)) {
                die("Content cannot be empty.");
            }

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
                'created_at' => $zettels[$id]['created_at'],
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $file = fopen($zettelsDir . '/' . $id . '.txt', 'w');
            if (flock($file, LOCK_EX)) {
                fwrite($file, json_encode($zettel, JSON_PRETTY_PRINT));
                flock($file, LOCK_UN);
            } else {
                die("Could not lock file for writing.");
            }
            fclose($file);
        }

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } elseif (isset($_POST['delete'])) {
        $id = $_POST['id'];
        if (!preg_match('/^[a-zA-Z0-9]+$/', $id)) {
            die("Invalid Zettel ID.");
        }

        if (file_exists($zettelsDir . '/' . $id . '.txt')) {
            unlink($zettelsDir . '/' . $id . '.txt');
        }

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Search and tag filtering
$searchResults = [];
if (isset($_GET['search'])) {
    $term = strtolower(trim($_GET['search']));
    $searchResults = [];

    foreach ($zettels as $id => $z) {
        $score = 0;

        if (strpos(strtolower($z['title']), $term) !== false) {
            $score += 3;
        }
        if (strpos(strtolower($z['content']), $term) !== false) {
            $score += 2;
        }
        if (!empty($z['tags'])) {
            foreach ($z['tags'] as $tag) {
                if (strpos(strtolower($tag), $term) !== false) {
                    $score += 1;
                    break;
                }
            }
        }

        if ($score > 0) {
            $z['relevance_score'] = $score;
            $searchResults[$id] = $z;
        }
    }

    $searchResults_sorted = array_values($searchResults);
    usort($searchResults_sorted, function($a, $b) {
        return $b['relevance_score'] - $a['relevance_score'];
    });
    $searchResults_by_id = [];
    foreach ($searchResults_sorted as $z) {
        $searchResults_by_id[$z['id']] = $z;
    }
    $searchResults = $searchResults_by_id;
} elseif (isset($_GET['tag'])) {
    $tag = strtolower(trim($_GET['tag']));
    foreach ($zettels as $id => $z) {
        if (!empty($z['tags']) && in_array($tag, array_map('strtolower', $z['tags']))) {
            $searchResults[$id] = $z;
        }
    }

    $tagResults_sorted = array_values($searchResults);
    usort($tagResults_sorted, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $tagResults_by_id = [];
    foreach ($tagResults_sorted as $z) {
        $tagResults_by_id[$z['id']] = $z;
    }
    $searchResults = $tagResults_by_id;
}

function findRelatedZettels($current, $allZettels, $limit = 5) {
    $related = [];
    foreach ($allZettels as $id => $zettel) {
        if ($id == $current['id']) {
            continue;
        }
        $sharedTags = array_intersect($current['tags'] ?? [], $zettel['tags'] ?? []);
        $score = count($sharedTags);
        if ($score > 0) {
            $related[$id] = [
                'title' => $zettel['title'],
                'score' => $score
            ];
        }
    }
    uasort($related, function($a, $b) {
        if ($a['score'] === $b['score']) {
            return strcasecmp($a['title'], $b['title']);
        }
        return $b['score'] <=> $a['score'];
    });
    return array_slice($related, 0, $limit);
}

function findSimilarZettels($current, $all, $limit = 5) {
    $scores = [];
    foreach ($all as $id => $z) {
        if ($id === $current['id']) {
            continue;
        }
        $score = count(array_intersect($current['tags'] ?? [], $z['tags'] ?? [])) * 2;
        $cw = array_map('strtolower', preg_split('/\s+/', $current['content']));
        $zw = array_map('strtolower', preg_split('/\s+/', $z['content']));
        $score += count(array_intersect($cw, $zw));
        if ($score > 0) {
            $scores[$id] = $score;
        }
    }
    arsort($scores);
    return array_slice($scores, 0, $limit, true);
}

function findBacklinks($id, $all) {
    $back = [];
    foreach ($all as $zid => $z) {
        if (!empty($z['links']) && in_array($id, $z['links'])) {
            $back[$zid] = $z;
        }
    }
    return $back;
}

// Pagination and "show" logic
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));

if (isset($_GET['show'])) {
    $showId = $_GET['show'];
    $sourceZettels = isset($zettels[$showId]) ? [$showId => $zettels[$showId]] : [];
    $displayZettels = $sourceZettels;
    $totalPages = 1;
} else {
    $sourceZettels = !empty($searchResults) ? $searchResults : $zettels;
    $displayZettels = array_slice($sourceZettels, ($page - 1) * $perPage, $perPage, true);
    $totalPages = ceil(count($sourceZettels) / $perPage);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zettelkasten</title>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0 auto;
            padding: 20px;
            max-width: 900px;
            font-size: 18px;
        }
        h1 { font-size: 28px; color: #333; }
        h2 { font-size: 24px; color: #333; }
        h3 { font-size: 22px; color: #333; }
        form {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        textarea, input[type=text] {
            width: 100%;
            padding: 8px;
            margin: 5px 0 15px;
            font-size: 16px;
        }
        textarea { height: 120px; }
        button {
            background: #333;
            color: #fff;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            font-size: 16px;
            border-radius: 4px;
        }
        .zettel {
            border: 1px solid #ddd;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            background: #fff;
        }
        .zettel-content { margin: 15px 0; }
        .zettel-content a { color: #0066cc; text-decoration: none; }
        .zettel-content a:hover { text-decoration: underline; }
        .tags a, .links a, .backlinks a, .related a, .similar a {
            color: #0066cc;
            text-decoration: none;
            margin-right: 5px;
        }
        .tags a:hover, .links a:hover, .backlinks a:hover, .related a:hover, .similar a:hover {
            text-decoration: underline;
        }
        .edit-form {
            display: none;
            background: #f0f0f0;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .edit-form.active { display: block; }
        .timestamps { font-size: 14px; color: #666; }
        .pagination {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 4px;
            text-decoration: none;
            color: #333;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .pagination a:hover { background: #ddd; }
        .pagination .active {
            background: #333;
            color: #fff;
            border: 1px solid #333;
        }
        .tag-cloud a {
            font-size: 14px;
            margin: 2px;
            display: inline-block;
            text-decoration: none;
            color: #0066cc;
        }
        .tag-cloud a:hover { text-decoration: underline; }
        .actions form {
            display: inline;
            background: transparent !important;
            padding: 0;
            margin: 0;
        }
        .actions button { margin-left: 8px; }
    </style>
</head>
<body>
    <h1>Zettelkasten</h1>
    <!-- Search -->
    <form method="GET">
        <input type="text" name="search" placeholder="Search Zettels..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
        <button type="submit">Search</button>
    </form>
    <!-- Create Zettel -->
    <h2>Create New Zettel</h2>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="text" name="title" placeholder="Title" required>
        <textarea name="content" placeholder="Content (Markdown supported)" required></textarea>
        <input type="text" name="tags" id="tags" placeholder="Tags (comma-separated)">
        <input type="text" name="links" id="links" placeholder="Links to other Zettels (comma-separated IDs)">
        <button type="submit" name="create">Create Zettel</button>
    </form>
    <!-- Display Zettels -->
    <h2><?= isset($_GET['search']) ? 'Search Results for: ' . htmlspecialchars($_GET['search']) : (isset($_GET['tag']) ? 'Tag: ' . htmlspecialchars($_GET['tag']) : 'All Zettels') ?></h2>
    <?php if (isset($_GET['search']) && empty($displayZettels)): ?>
        <p>No Zettels found for your search.</p>
    <?php elseif (empty($displayZettels)): ?>
        <p>No Zettels found.</p>
    <?php else: ?>
        <?php foreach ($displayZettels as $id => $z):
            $backlinks = findBacklinks($id, $zettels);
            $related = findRelatedZettels($z, $zettels);
            $similar = findSimilarZettels($z, $zettels);
        ?>
            <div class="zettel" id="<?= $id ?>">
                <h3><?= htmlspecialchars($z['title']) ?></h3>
                <div class="zettel-content"><?= $parsedown->text($z['content']) ?></div>
                <div class="tags"><strong>Tags:</strong>
                    <?php foreach ($z['tags'] ?? [] as $tag): ?>
                        <a href="?tag=<?= urlencode($tag) ?>"><?= htmlspecialchars($tag) ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="timestamps"><strong>Created:</strong> <?= htmlspecialchars($z['created_at']) ?> | <strong>Updated:</strong> <?= htmlspecialchars($z['updated_at']) ?></div>
                <div class="actions">
                    <button onclick="toggleEditForm('<?= $id ?>')">Edit</button>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button type="submit" name="delete" onclick="return confirm('Delete this Zettel?')">Delete</button>
                    </form>
                </div>
                <div class="edit-form" id="edit-form-<?= $id ?>">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="text" name="title" value="<?= htmlspecialchars($z['title']) ?>" required>
                        <textarea name="content" required><?= htmlspecialchars($z['content']) ?></textarea>
                        <input type="text" name="tags" id="tags-edit-<?= $id ?>" value="<?= htmlspecialchars(implode(', ', $z['tags'] ?? [])) ?>">
                        <input type="text" name="links" id="links-edit-<?= $id ?>" value="<?= htmlspecialchars(implode(', ', $z['links'] ?? [])) ?>">
                        <button type="submit" name="edit">Save Changes</button>
                        <button type="button" onclick="toggleEditForm('<?= $id ?>')">Cancel</button>
                    </form>
                </div>
                <?php if (!empty($z['links'])): ?>
                    <div class="links"><strong>Links:</strong>
                        <?php foreach ($z['links'] as $linkId): if (isset($zettels[$linkId])): ?>
                            <a href="#<?= $linkId ?>" onclick="scrollToZettel('<?= $linkId ?>', event)"><?= htmlspecialchars($zettels[$linkId]['title']) ?></a>
                        <?php endif; endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($backlinks)): ?>
                    <div class="backlinks"><strong>Backlinks:</strong>
                        <ul>
                            <?php foreach ($backlinks as $bid => $b): ?>
                                <li><a href="#<?= $bid ?>" onclick="scrollToZettel('<?= $bid ?>', event)"><?= htmlspecialchars($b['title'] ?? 'Untitled') ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if (!empty($related)): ?>
                    <div class="related"><strong>Related:</strong>
                        <ul>
                            <?php foreach ($related as $rid => $r): ?>
                                <li><a href="#<?= $rid ?>" onclick="scrollToZettel('<?= $rid ?>', event)"><?= htmlspecialchars($r['title'] ?? 'Untitled') ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if (!empty($similar)): ?>
                    <div class="similar"><strong>Similar:</strong>
                        <ul>
                            <?php foreach ($similar as $sid => $score): if (isset($zettels[$sid])): $sz = $zettels[$sid]; ?>
                                <li><a href="#<?= $sid ?>" onclick="scrollToZettel('<?= $sid ?>', event)"><?= htmlspecialchars($sz['title'] ?? 'Untitled') ?></a></li>
                            <?php endif; endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Prev</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <!-- All tags / tag cloud -->
    <h2>All Tags</h2>
    <div class="tag-cloud">
        <?php foreach ($allTags as $tag): ?>
            <a href="?tag=<?= urlencode($tag) ?>"><?= htmlspecialchars($tag) ?></a>
        <?php endforeach; ?>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script>
        function toggleEditForm(id) {
            document.getElementById('edit-form-' + id).classList.toggle('active');
        }
        function scrollToZettel(id, e) {
            e.preventDefault();
            const el = document.getElementById(id);
            if (el) {
                window.scrollTo({
                    top: el.offsetTop - 20,
                    behavior: 'smooth'
                });
            } else {
                window.location.href = "?show=" + encodeURIComponent(id);
            }
        }
        <?php if (isset($_GET['show'])): ?>
        $(document).ready(function() {
            const el = document.getElementById("<?= htmlspecialchars($_GET['show']) ?>");
            if (el) {
                window.scrollTo({
                    top: el.offsetTop - 20,
                    behavior: 'smooth'
                });
            }
        });
        <?php endif; ?>
        // Autocomplete for tags
        const allTags = <?= json_encode($allTags) ?>;
        function setupTagAutocomplete(selector) {
            const $el = $(selector);
            if (!$el.length) {
                return;
            }
            $el.autocomplete({
                source: function(req, res) {
                    const val = $el.val();
                    const lastComma = val.lastIndexOf(',');
                    const cur = lastComma === -1 ? val : val.substring(lastComma + 1).trim();
                    const existing = val.split(',').map(t => t.trim().toLowerCase()).filter(Boolean);
                    res(allTags.filter(tag => tag.toLowerCase().includes(cur.toLowerCase()) && !existing.includes(tag.toLowerCase())));
                },
                minLength: 0,
                appendTo: $el.parent(),
                focus: function(e, ui) {
                    e.preventDefault();
                },
                select: function(e, ui) {
                    const val = $el.val();
                    const lastComma = val.lastIndexOf(',');
                    const before = lastComma === -1 ? '' : val.substring(0, lastComma + 1) + ' ';
                    $el.val((before + ui.item.value + ', ').replace(/\s+,/g, ', '));
                    return false;
                }
            }).on('focus', function() {
                $el.autocomplete('search', '');
            });
        }
        // Autocomplete for links
        const allZettels = <?= json_encode(array_values(array_map(function($z) {
            return ['id' => $z['id'], 'title' => $z['title']];
        }, $zettels))) ?>;
        function setupLinkAutocomplete(selector) {
            const $el = $(selector);
            if (!$el.length) {
                return;
            }
            $el.autocomplete({
                source: function(req, res) {
                    const val = $el.val();
                    const lastComma = val.lastIndexOf(',');
                    const cur = lastComma === -1 ? val : val.substring(lastComma + 1).trim();
                    const results = allZettels.filter(z => z.title.toLowerCase().includes(cur.toLowerCase()) || z.id.toLowerCase().includes(cur.toLowerCase()))
                        .filter(z => !val.split(',').map(t => t.trim()).includes(z.id))
                        .map(z => ({
                            label: z.title + ' (' + z.id + ')',
                            value: z.id
                        }));
                    res(results);
                },
                minLength: 0,
                appendTo: $el.parent(),
                focus: function(e) {
                    e.preventDefault();
                },
                select: function(e, ui) {
                    const val = $el.val();
                    const lastComma = val.lastIndexOf(',');
                    const before = lastComma === -1 ? '' : val.substring(0, lastComma + 1) + ' ';
                    $el.val(before + ui.item.value + ', ');
                    return false;
                }
            }).on('focus', function() {
                $el.autocomplete('search', '');
            });
        }
        $(document).ready(function() {
            setupTagAutocomplete('#tags');
            setupLinkAutocomplete('#links');
            document.querySelectorAll('.edit-form').forEach(f => {
                const id = f.id.split('-')[2];
                setupTagAutocomplete('#tags-edit-' + id);
                setupLinkAutocomplete('#links-edit-' + id);
            });
        });
    </script>
</body>
</html>
