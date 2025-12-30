<?php
// Load configuration
require_once 'config.php';

// Session configuration
ini_set('session.cookie_lifetime', SESSION_LIFETIME);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();

// Authentication check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Session timeout check
if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
}

$_SESSION['last_activity'] = time();

$zettelsDir = ZETTELS_DIR;

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

// Sort by created_at
uasort($zettels, function($a, $b) {
    return strtotime($a['created_at']) - strtotime($b['created_at']);
});

// Handle export request
if (isset($_GET['download'])) {
    // Verify CSRF token
    if (!isset($_GET['token']) || !hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
        die("CSRF token validation failed.");
    }
    
    $exportType = $_GET['download'];
    $selectedIds = isset($_GET['ids']) ? explode(',', $_GET['ids']) : array_keys($zettels);
    
    // Filter selected zettels
    $exportZettels = [];
    foreach ($selectedIds as $id) {
        $id = trim($id);
        if (isset($zettels[$id])) {
            $exportZettels[$id] = $zettels[$id];
        }
    }
    
    if (empty($exportZettels)) {
        die("No zettels selected for export.");
    }
    
    if ($exportType === 'single') {
        // Export as single markdown file
        exportAsSingleMarkdown($exportZettels, $zettels);
    } elseif ($exportType === 'zip') {
        // Export as ZIP with individual markdown files
        exportAsZip($exportZettels, $zettels);
    } elseif ($exportType === 'json') {
        // Export as JSON backup
        exportAsJSON($exportZettels);
    }
    exit;
}

function convertInternalLinks($content, $allZettels, $format = 'markdown') {
    // Convert [[id]] to proper markdown links
    return preg_replace_callback('/\[\[([a-zA-Z0-9]+)\]\]/', function($matches) use ($allZettels, $format) {
        $id = $matches[1];
        if (isset($allZettels[$id])) {
            $title = $allZettels[$id]['title'];
            if ($format === 'markdown') {
                // Create markdown link: [Title](id.md) for individual files
                // or [Title](#id) for single file
                return '[' . $title . '](#' . $id . ')';
            }
        }
        return '[[' . $id . ']]';
    }, $content);
}

function exportAsSingleMarkdown($exportZettels, $allZettels) {
    $markdown = "# Zettelkasten Export\n\n";
    $markdown .= "**Exported:** " . date('Y-m-d H:i:s') . "\n";
    $markdown .= "**Total Notes:** " . count($exportZettels) . "\n\n";
    $markdown .= "---\n\n";
    
    // Table of contents
    $markdown .= "## Table of Contents\n\n";
    foreach ($exportZettels as $id => $zettel) {
        $markdown .= "- [" . $zettel['title'] . "](#" . $id . ")\n";
    }
    $markdown .= "\n---\n\n";
    
    // Export each zettel
    foreach ($exportZettels as $id => $zettel) {
        $markdown .= "<a name=\"" . $id . "\"></a>\n\n";
        $markdown .= "## " . $zettel['title'] . "\n\n";
        $markdown .= "**ID:** `" . $zettel['id'] . "`  \n";
        $markdown .= "**Created:** " . $zettel['created_at'] . "  \n";
        $markdown .= "**Updated:** " . $zettel['updated_at'] . "\n\n";
        
        if (!empty($zettel['tags'])) {
            $markdown .= "**Tags:** ";
            foreach ($zettel['tags'] as $tag) {
                $markdown .= "#" . $tag . " ";
            }
            $markdown .= "\n\n";
        }
        
        // Convert internal links
        $content = convertInternalLinks($zettel['content'], $allZettels, 'markdown');
        $markdown .= $content . "\n\n";
        
        if (!empty($zettel['links'])) {
            $markdown .= "**Links to:**\n";
            foreach ($zettel['links'] as $linkId) {
                if (isset($allZettels[$linkId])) {
                    $markdown .= "- [" . $allZettels[$linkId]['title'] . "](#" . $linkId . ")\n";
                } else {
                    $markdown .= "- `" . $linkId . "` (not found)\n";
                }
            }
            $markdown .= "\n";
        }
        
        $markdown .= "---\n\n";
    }
    
    // Send file
    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="zettelkasten-export-' . date('Y-m-d') . '.md"');
    header('Content-Length: ' . strlen($markdown));
    echo $markdown;
}

function exportAsZip($exportZettels, $allZettels) {
    // Create temporary directory
    $tempDir = sys_get_temp_dir() . '/zettelkasten_' . uniqid();
    mkdir($tempDir, 0755, true);
    
    // Create index file
    $indexMd = "# Zettelkasten Export\n\n";
    $indexMd .= "**Exported:** " . date('Y-m-d H:i:s') . "\n";
    $indexMd .= "**Total Notes:** " . count($exportZettels) . "\n\n";
    $indexMd .= "## All Notes\n\n";
    
    foreach ($exportZettels as $id => $zettel) {
        $indexMd .= "- [" . $zettel['title'] . "](" . $id . ".md)\n";
    }
    
    file_put_contents($tempDir . '/INDEX.md', $indexMd);
    
    // Create individual markdown files
    foreach ($exportZettels as $id => $zettel) {
        $markdown = "# " . $zettel['title'] . "\n\n";
        $markdown .= "**ID:** `" . $zettel['id'] . "`  \n";
        $markdown .= "**Created:** " . $zettel['created_at'] . "  \n";
        $markdown .= "**Updated:** " . $zettel['updated_at'] . "\n\n";
        
        if (!empty($zettel['tags'])) {
            $markdown .= "**Tags:** ";
            foreach ($zettel['tags'] as $tag) {
                $markdown .= "#" . $tag . " ";
            }
            $markdown .= "\n\n";
        }
        
        $markdown .= "---\n\n";
        
        // Convert internal links to reference other .md files
        $content = preg_replace_callback('/\[\[([a-zA-Z0-9]+)\]\]/', function($matches) use ($allZettels) {
            $linkId = $matches[1];
            if (isset($allZettels[$linkId])) {
                $title = $allZettels[$linkId]['title'];
                return '[' . $title . '](' . $linkId . '.md)';
            }
            return '[[' . $linkId . ']]';
        }, $zettel['content']);
        
        $markdown .= $content . "\n\n";
        
        if (!empty($zettel['links'])) {
            $markdown .= "---\n\n";
            $markdown .= "**Links to:**\n\n";
            foreach ($zettel['links'] as $linkId) {
                if (isset($allZettels[$linkId])) {
                    $markdown .= "- [" . $allZettels[$linkId]['title'] . "](" . $linkId . ".md)\n";
                } else {
                    $markdown .= "- `" . $linkId . "` (not found)\n";
                }
            }
            $markdown .= "\n";
        }
        
        // Sanitize filename
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $id);
        file_put_contents($tempDir . '/' . $filename . '.md', $markdown);
    }
    
    // Create ZIP file
    $zipFile = sys_get_temp_dir() . '/zettelkasten_export_' . uniqid() . '.zip';
    $zip = new ZipArchive();
    
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        // Add all markdown files
        $files = glob($tempDir . '/*.md');
        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();
        
        // Send ZIP file
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="zettelkasten-export-' . date('Y-m-d') . '.zip"');
        header('Content-Length: ' . filesize($zipFile));
        readfile($zipFile);
        
        // Clean up
        unlink($zipFile);
    }
    
    // Clean up temp directory
    array_map('unlink', glob($tempDir . '/*'));
    rmdir($tempDir);
}

function exportAsJSON($exportZettels) {
    $export = [
        'exported_at' => date('Y-m-d H:i:s'),
        'total_notes' => count($exportZettels),
        'zettels' => array_values($exportZettels)
    ];
    
    $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="zettelkasten-backup-' . date('Y-m-d') . '.json"');
    header('Content-Length: ' . strlen($json));
    echo $json;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Zettels - Zettelkasten</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .export-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .export-options {
            background: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .export-format {
            margin: 20px 0;
            padding: 20px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            transition: border-color 0.3s;
        }
        
        .export-format:hover {
            border-color: #3498db;
        }
        
        .export-format h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        
        .export-format p {
            color: #7f8c8d;
            margin: 10px 0;
        }
        
        .zettel-list {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .zettel-checkbox {
            display: flex;
            align-items: center;
            padding: 10px;
            margin: 5px 0;
            background: #f8f9fa;
            border-radius: 4px;
            transition: background 0.2s;
        }
        
        .zettel-checkbox:hover {
            background: #e9ecef;
        }
        
        .zettel-checkbox input[type="checkbox"] {
            margin-right: 10px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .zettel-checkbox label {
            cursor: pointer;
            flex: 1;
            font-weight: 500;
        }
        
        .zettel-checkbox .zettel-id {
            color: #7f8c8d;
            font-size: 0.85em;
            margin-left: 10px;
        }
        
        .selection-controls {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 600;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .export-actions {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn-export {
            background: #27ae60;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }
        
        .btn-export:hover {
            background: #229954;
        }
        
        .btn-export.disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }
        
        .stats {
            margin: 15px 0;
            padding: 10px;
            background: #e8f5e9;
            border-radius: 4px;
            color: #2e7d32;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="export-container">
        <h1>📥 Export Zettels</h1>
        <a href="index.php" class="back-link">← Back to Zettelkasten</a>
        
        <div class="export-options">
            <h2>Choose Export Format</h2>
            
            <div class="export-format">
                <h3>📄 Single Markdown File</h3>
                <p>Export all selected notes as one combined markdown file with a table of contents. Internal links work as anchors within the document.</p>
            </div>
            
            <div class="export-format">
                <h3>📦 ZIP Archive (Individual Files)</h3>
                <p>Export each note as a separate markdown file, packaged in a ZIP archive. Includes an INDEX.md file. Internal links reference other .md files.</p>
            </div>
            
            <div class="export-format">
                <h3>💾 JSON Backup</h3>
                <p>Export as structured JSON data for backup or programmatic use. Preserves all metadata and can be re-imported.</p>
            </div>
        </div>
        
        <div class="zettel-list">
            <h2>Select Zettels to Export</h2>
            
            <div class="selection-controls">
                <button class="btn-secondary" onclick="selectAll()">Select All</button>
                <button class="btn-secondary" onclick="selectNone()">Select None</button>
                <button class="btn-secondary" onclick="invertSelection()">Invert Selection</button>
                <div class="stats" id="selectionStats">
                    <span id="selectedCount"><?= count($zettels) ?></span> of <?= count($zettels) ?> notes selected
                </div>
            </div>
            
            <form id="exportForm" method="GET">
                <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="download" id="downloadType" value="">
                <input type="hidden" name="ids" id="selectedIds" value="">
                
                <?php foreach ($zettels as $id => $zettel): ?>
                <div class="zettel-checkbox">
                    <input type="checkbox" 
                           id="zettel_<?= htmlspecialchars($id) ?>" 
                           value="<?= htmlspecialchars($id) ?>" 
                           class="zettel-select"
                           checked
                           onchange="updateSelectionCount()">
                    <label for="zettel_<?= htmlspecialchars($id) ?>">
                        <?= htmlspecialchars($zettel['title']) ?>
                        <span class="zettel-id">(<?= htmlspecialchars($id) ?>)</span>
                    </label>
                </div>
                <?php endforeach; ?>
            </form>
            
            <div class="export-actions">
                <button class="btn-export" onclick="exportAs('single')">
                    📄 Export as Single Markdown
                </button>
                <button class="btn-export" onclick="exportAs('zip')">
                    📦 Export as ZIP
                </button>
                <button class="btn-export" onclick="exportAs('json')">
                    💾 Export as JSON
                </button>
            </div>
        </div>
    </div>

    <script>
        function selectAll() {
            document.querySelectorAll('.zettel-select').forEach(cb => cb.checked = true);
            updateSelectionCount();
        }
        
        function selectNone() {
            document.querySelectorAll('.zettel-select').forEach(cb => cb.checked = false);
            updateSelectionCount();
        }
        
        function invertSelection() {
            document.querySelectorAll('.zettel-select').forEach(cb => cb.checked = !cb.checked);
            updateSelectionCount();
        }
        
        function updateSelectionCount() {
            const total = document.querySelectorAll('.zettel-select').length;
            const selected = document.querySelectorAll('.zettel-select:checked').length;
            document.getElementById('selectedCount').textContent = selected;
            
            // Enable/disable export buttons
            const buttons = document.querySelectorAll('.btn-export');
            buttons.forEach(btn => {
                if (selected === 0) {
                    btn.classList.add('disabled');
                    btn.disabled = true;
                } else {
                    btn.classList.remove('disabled');
                    btn.disabled = false;
                }
            });
        }
        
        function exportAs(type) {
            const selected = Array.from(document.querySelectorAll('.zettel-select:checked'))
                .map(cb => cb.value);
            
            if (selected.length === 0) {
                alert('Please select at least one zettel to export.');
                return;
            }
            
            document.getElementById('downloadType').value = type;
            document.getElementById('selectedIds').value = selected.join(',');
            document.getElementById('exportForm').submit();
        }
        
        // Initialize selection count
        updateSelectionCount();
    </script>
</body>
</html>
