<?php
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

// Security headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");

$error = '';
$success = '';

// CSRF token management (same as index.php)
if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_LIFETIME) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

// SECURITY: Define max file size (5MB)
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);

// SECURITY: Define max zettels per import to prevent DoS
define('MAX_ZETTELS_PER_IMPORT', 1000);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF token validation failed.");
    }

    $file = $_FILES['import_file'];
    
    // SECURITY: Validate file upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "File upload error: " . getUploadErrorMessage($file['error']);
    } 
    // SECURITY: Check file size
    elseif ($file['size'] > MAX_UPLOAD_SIZE) {
        $error = "File too large. Maximum size: " . (MAX_UPLOAD_SIZE / 1024 / 1024) . "MB";
    }
    // SECURITY: Check if file was actually uploaded (prevents local file inclusion)
    elseif (!is_uploaded_file($file['tmp_name'])) {
        $error = "Security error: Invalid file upload.";
        error_log("Possible file upload attack detected from IP: " . $_SERVER['REMOTE_ADDR']);
    }
    else {
        $tempPath = $file['tmp_name'];
        
        // SECURITY: Validate file extension strictly
        $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($fileType === 'json') {
            $result = importFromJSON($tempPath);
            if ($result['success']) {
                $success = $result['message'];
                // Invalidate tag cache after successful import
                @unlink(ZETTELS_DIR . '/.tag_cache.json');
            } else {
                $error = $result['message'];
            }
        } elseif ($fileType === 'md' || $fileType === 'markdown') {
            $error = "Markdown import is not yet implemented. Please use JSON format.";
        } else {
            $error = "Unsupported file format. Only JSON files are allowed.";
        }
        
        // SECURITY: Always delete the uploaded temp file
        @unlink($tempPath);
    }
}

function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "File exceeds maximum allowed size";
        case UPLOAD_ERR_PARTIAL:
            return "File was only partially uploaded";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Server error: Missing temporary folder";
        case UPLOAD_ERR_CANT_WRITE:
            return "Server error: Failed to write file";
        case UPLOAD_ERR_EXTENSION:
            return "Server error: Upload blocked by extension";
        default:
            return "Unknown upload error";
    }
}

function importFromJSON($filePath) {
    // SECURITY: Read file content with size limit
    $content = @file_get_contents($filePath, false, null, 0, MAX_UPLOAD_SIZE);
    if ($content === false) {
        return ['success' => false, 'message' => "Failed to read file."];
    }

    // SECURITY: Validate JSON before decoding
    $data = @json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => "Invalid JSON file: " . json_last_error_msg()];
    }

    // Validate structure
    if (!isset($data['zettels']) || !is_array($data['zettels'])) {
        return ['success' => false, 'message' => "Invalid JSON structure. Missing 'zettels' array."];
    }

    // SECURITY: Limit number of zettels to prevent DoS
    if (count($data['zettels']) > MAX_ZETTELS_PER_IMPORT) {
        return ['success' => false, 'message' => "Too many zettels. Maximum allowed: " . MAX_ZETTELS_PER_IMPORT];
    }

    $imported = 0;
    $skipped = 0;
    $errors = [];
    $zettelsDir = ZETTELS_DIR;

    foreach ($data['zettels'] as $index => $zettel) {
        // SECURITY: Validate required fields and types
        if (!is_array($zettel)) {
            $errors[] = "Entry $index: Invalid zettel format";
            $skipped++;
            continue;
        }

        if (empty($zettel['id']) || !is_string($zettel['id'])) {
            $errors[] = "Entry $index: Missing or invalid ID";
            $skipped++;
            continue;
        }

        // SECURITY: Strict ID validation (only alphanumeric)
        if (!preg_match('/^[a-zA-Z0-9]+$/', $zettel['id'])) {
            $errors[] = "Entry $index: Invalid ID format (ID: {$zettel['id']})";
            $skipped++;
            continue;
        }

        if (empty($zettel['title']) || !is_string($zettel['title'])) {
            $errors[] = "Entry $index: Missing or invalid title";
            $skipped++;
            continue;
        }

        if (empty($zettel['content']) || !is_string($zettel['content'])) {
            $errors[] = "Entry $index: Missing or invalid content";
            $skipped++;
            continue;
        }

        // SECURITY: Validate title and content length
        if (strlen($zettel['title']) > 255) {
            $errors[] = "Entry $index: Title too long (max 255 characters)";
            $skipped++;
            continue;
        }

        if (strlen($zettel['content']) > 100000) {
            $errors[] = "Entry $index: Content too long (max 100KB)";
            $skipped++;
            continue;
        }

        // SECURITY: Path traversal protection
        $filepath = realpath($zettelsDir) . '/' . basename($zettel['id']) . '.txt';
        if (strpos($filepath, realpath($zettelsDir)) !== 0) {
            $errors[] = "Entry $index: Security error - path traversal attempt";
            $skipped++;
            continue;
        }

        // Check if zettel already exists
        if (file_exists($filepath)) {
            $skipped++;
            continue;
        }

        // SECURITY: Sanitize and validate tags
        $tags = [];
        if (isset($zettel['tags'])) {
            if (!is_array($zettel['tags'])) {
                $errors[] = "Entry $index: Invalid tags format";
                $skipped++;
                continue;
            }
            foreach ($zettel['tags'] as $tag) {
                if (!is_string($tag)) continue;
                $clean_tag = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($tag));
                if (!empty($clean_tag) && strlen($clean_tag) <= 50) {
                    $tags[] = $clean_tag;
                }
            }
        }

        // SECURITY: Sanitize and validate links
        $links = [];
        if (isset($zettel['links'])) {
            if (!is_array($zettel['links'])) {
                $errors[] = "Entry $index: Invalid links format";
                $skipped++;
                continue;
            }
            foreach ($zettel['links'] as $link) {
                if (!is_string($link)) continue;
                $clean_link = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($link));
                if (!empty($clean_link) && preg_match('/^[a-zA-Z0-9]+$/', $clean_link)) {
                    $links[] = $clean_link;
                }
            }
        }

        // SECURITY: Decode HTML entities for proper UTF-8 (XSS protection happens during display)
        $title = html_entity_decode(trim($zettel['title']), ENT_QUOTES, 'UTF-8');
        $content = html_entity_decode(trim($zettel['content']), ENT_QUOTES, 'UTF-8');

        // Validate timestamps
        $created_at = isset($zettel['created_at']) && 
                      preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $zettel['created_at']) 
                      ? $zettel['created_at'] 
                      : date('Y-m-d H:i:s');
        
        $updated_at = isset($zettel['updated_at']) && 
                      preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $zettel['updated_at']) 
                      ? $zettel['updated_at'] 
                      : date('Y-m-d H:i:s');

        // Build clean zettel array
        $cleanZettel = [
            'id' => $zettel['id'],
            'title' => $title,
            'content' => $content,
            'tags' => $tags,
            'links' => $links,
            'created_at' => $created_at,
            'updated_at' => $updated_at
        ];

        // Write file with proper error handling
        $file = @fopen($filepath, 'w');
        if ($file === false) {
            $errors[] = "Entry $index: Failed to create file";
            $skipped++;
            continue;
        }

        if (flock($file, LOCK_EX)) {
            $json = json_encode($cleanZettel, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                $errors[] = "Entry $index: JSON encoding failed";
                $skipped++;
                flock($file, LOCK_UN);
                fclose($file);
                @unlink($filepath);
                continue;
            }
            
            fwrite($file, $json);
            flock($file, LOCK_UN);
            $imported++;
        } else {
            $errors[] = "Entry $index: Could not lock file";
            $skipped++;
        }
        fclose($file);
    }

    // Build result message
    $message = "Import completed: $imported zettels imported, $skipped skipped.";
    if (!empty($errors) && count($errors) <= 10) {
        $message .= "\n\nErrors:\n" . implode("\n", $errors);
    } elseif (!empty($errors)) {
        $message .= "\n\n" . count($errors) . " errors occurred during import.";
    }

    return ['success' => true, 'message' => $message];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Zettels - Zettelkasten</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .import-container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .import-option { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .import-option h3 { margin-top: 0; color: #2c3e50; }
        .import-option input[type="file"] { margin: 15px 0; }
        .btn-import { 
            background: #27ae60; 
            color: white; 
            padding: 12px 24px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 1em; 
            font-weight: 600;
        }
        .btn-import:hover { background: #229954; }
        .btn-import:disabled { background: #95a5a6; cursor: not-allowed; }
        .error { 
            background: #e74c3c; 
            color: white; 
            padding: 15px; 
            border-radius: 6px; 
            margin: 20px 0;
            white-space: pre-line;
        }
        .success { 
            background: #27ae60; 
            color: white; 
            padding: 15px; 
            border-radius: 6px; 
            margin: 20px 0;
            white-space: pre-line;
        }
        .info-box {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box ul { margin: 10px 0 0 20px; }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="import-container">
        <h1>Import Zettels</h1>
        <a href="index.php" class="back-link">← Back to Zettelkasten</a>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="info-box">
            <strong>📋 Import Guidelines:</strong>
            <ul>
                <li>Only JSON backup files are supported</li>
                <li>Maximum file size: 5MB</li>
                <li>Maximum <?= MAX_ZETTELS_PER_IMPORT ?> zettels per import</li>
                <li>Duplicate IDs will be skipped (not overwritten)</li>
                <li>Invalid entries will be skipped with error messages</li>
            </ul>
        </div>

        <div class="warning-box">
            <strong>⚠️ Important:</strong> This import will NOT overwrite existing zettels. 
            Zettels with duplicate IDs will be skipped. To replace existing zettels, 
            delete them first or use a different ID in your import file.
        </div>

        <div class="import-option">
            <h3>📥 Import from JSON Backup</h3>
            <p>Upload a JSON backup file previously exported from this Zettelkasten system.</p>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <input 
                    type="file" 
                    name="import_file" 
                    accept=".json,application/json" 
                    required
                    onchange="validateFileSize(this)"
                >
                
                <div style="margin-top: 15px;">
                    <button type="submit" class="btn-import" id="submitBtn">Import JSON File</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function validateFileSize(input) {
        const maxSize = <?= MAX_UPLOAD_SIZE ?>;
        const submitBtn = document.getElementById('submitBtn');
        
        if (input.files.length > 0) {
            const fileSize = input.files[0].size;
            if (fileSize > maxSize) {
                alert('File is too large. Maximum size is ' + (maxSize / 1024 / 1024) + 'MB');
                input.value = '';
                submitBtn.disabled = true;
                return false;
            }
            submitBtn.disabled = false;
        }
        return true;
    }

    // Prevent form resubmission on page refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    </script>
</body>
</html>
