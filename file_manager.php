<?php
/**
 * Simple File Manager
 * Allows upload and download of files
 * Run with: php -S 0.0.0.0:80 file_manager.php
 */

// Start session for authentication
session_start();

// Configuration
$UPLOAD_DIR = __DIR__ . '/uploads';
$MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB
$ALLOWED_EXTENSIONS = []; // Empty array = allow all extensions
$PASSWORD = 'changeme'; // Change this to your desired password

// Create uploads directory if it doesn't exist
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
}

// Authentication functions
function isAuthenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function requireAuth() {
    if (!isAuthenticated()) {
        http_response_code(403);
        die('Access denied. Please log in.');
    }
}

// Handle login
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (isset($_POST['password']) && $_POST['password'] === $PASSWORD) {
        $_SESSION['authenticated'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $login_error = 'Incorrect password. Please try again.';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle file download
if (isset($_GET['download']) && !empty($_GET['download'])) {
    requireAuth();
    $filename = basename($_GET['download']);
    $filepath = $UPLOAD_DIR . '/' . $filename;
    
    if (file_exists($filepath) && is_file($filepath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . htmlspecialchars($filename) . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        http_response_code(404);
        die('File not found');
    }
}

// Handle file upload
$upload_message = '';
$upload_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    requireAuth();
    if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $filename = basename($_FILES['file']['name']);
        $filepath = $UPLOAD_DIR . '/' . $filename;
        
        // Check file size
        if ($_FILES['file']['size'] > $MAX_FILE_SIZE) {
            $upload_error = 'File size exceeds maximum allowed size (' . ($MAX_FILE_SIZE / 1024 / 1024) . 'MB)';
        } else {
            // Check extension if restrictions are set
            if (!empty($ALLOWED_EXTENSIONS)) {
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (!in_array($ext, $ALLOWED_EXTENSIONS)) {
                    $upload_error = 'File type not allowed. Allowed extensions: ' . implode(', ', $ALLOWED_EXTENSIONS);
                }
            }
            
            // Move uploaded file
            if (empty($upload_error)) {
                if (move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
                    $upload_message = 'File "' . htmlspecialchars($filename) . '" uploaded successfully!';
                } else {
                    $upload_error = 'Failed to save file. Please check directory permissions.';
                }
            }
        }
    } else {
        // Provide detailed error messages
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        
        $error_code = $_FILES['file']['error'];
        $upload_error = 'Upload error: ' . (isset($error_messages[$error_code]) 
            ? $error_messages[$error_code] 
            : 'Unknown error (code: ' . $error_code . ')');
        
        // Additional diagnostic info
        $upload_error .= '<br><small>PHP Limits: upload_max_filesize=' . ini_get('upload_max_filesize') . 
                        ', post_max_size=' . ini_get('post_max_size') . 
                        ', max_execution_time=' . ini_get('max_execution_time') . 's</small>';
    }
}

// Handle file deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    requireAuth();
    $filename = basename($_GET['delete']);
    $filepath = $UPLOAD_DIR . '/' . $filename;
    
    if (file_exists($filepath) && is_file($filepath)) {
        if (unlink($filepath)) {
            $upload_message = 'File "' . htmlspecialchars($filename) . '" deleted successfully!';
        } else {
            $upload_error = 'Failed to delete file.';
        }
    }
}

// Get list of files (only if authenticated)
$files = [];
if (isAuthenticated() && is_dir($UPLOAD_DIR)) {
    $items = scandir($UPLOAD_DIR);
    foreach ($items as $item) {
        if ($item !== '.' && $item !== '..') {
            $item_path = $UPLOAD_DIR . '/' . $item;
            if (is_file($item_path)) {
                $files[] = [
                    'name' => $item,
                    'size' => filesize($item_path),
                    'modified' => filemtime($item_path)
                ];
            }
        }
    }
    
    // Sort by modification time (newest first)
    usort($files, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
}

// Format file size
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .content {
            padding: 30px;
        }
        
        .upload-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .upload-section h2 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px dashed #667eea;
            border-radius: 6px;
            background: white;
            cursor: pointer;
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .files-section h2 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .files-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        .files-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        
        .files-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .files-table tr:hover {
            background: #f8f9fa;
        }
        
        .file-name {
            font-weight: 500;
            color: #667eea;
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-download {
            background: #28a745;
            padding: 6px 15px;
            font-size: 14px;
            text-decoration: none;
            color: white;
            border-radius: 4px;
            display: inline-block;
        }
        
        .btn-download:hover {
            background: #218838;
            transform: none;
        }
        
        .btn-delete {
            background: #dc3545;
            padding: 6px 15px;
            font-size: 14px;
            text-decoration: none;
            color: white;
            border-radius: 4px;
            display: inline-block;
        }
        
        .btn-delete:hover {
            background: #c82333;
            transform: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .empty-state::before {
            content: "📁";
            font-size: 48px;
            display: block;
            margin-bottom: 15px;
        }
        
        .login-section {
            max-width: 400px;
            margin: 0 auto;
            padding: 40px 0;
        }
        
        .login-section h2 {
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .logout-section {
            text-align: right;
            margin-bottom: 20px;
        }
        
        .btn-logout {
            background: #6c757d;
            color: white;
            padding: 8px 20px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            display: inline-block;
        }
        
        .btn-logout:hover {
            background: #5a6268;
            transform: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📁 File Manager</h1>
            <p>Upload and download files</p>
        </div>
        
        <div class="content">
            <?php if (!isAuthenticated()): ?>
                <!-- Login Form -->
                <div class="login-section">
                    <h2>🔒 Login Required</h2>
                    
                    <?php if ($login_error): ?>
                        <div class="message error"><?php echo $login_error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <input type="password" name="password" placeholder="Enter password" required autofocus>
                        </div>
                        <button type="submit" name="login">Login</button>
                    </form>
                </div>
            <?php else: ?>
                <!-- Logout Button -->
                <div class="logout-section">
                    <a href="?logout=1" class="btn-logout">Logout</a>
                </div>
                
                <!-- File Manager Content -->
            <!-- Upload Section -->
            <div class="upload-section">
                <h2>Upload File</h2>
                
                <?php if ($upload_message): ?>
                    <div class="message success"><?php echo $upload_message; ?></div>
                <?php endif; ?>
                
                <?php if ($upload_error): ?>
                    <div class="message error"><?php echo $upload_error; ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <input type="file" name="file" id="file" required>
                    </div>
                    <button type="submit">Upload File</button>
                </form>
            </div>
            
            <!-- PHP Configuration Diagnostics -->
            <div class="upload-section" style="background: #fff3cd; border: 1px solid #ffc107;">
                <h2>📊 PHP Upload Configuration</h2>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px; font-weight: 600;">upload_max_filesize:</td>
                        <td style="padding: 8px;"><?php echo ini_get('upload_max_filesize'); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; font-weight: 600;">post_max_size:</td>
                        <td style="padding: 8px;"><?php echo ini_get('post_max_size'); ?> 
                            <small style="color: #666;">(must be ≥ upload_max_filesize)</small></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; font-weight: 600;">max_execution_time:</td>
                        <td style="padding: 8px;"><?php echo ini_get('max_execution_time'); ?> seconds</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; font-weight: 600;">memory_limit:</td>
                        <td style="padding: 8px;"><?php echo ini_get('memory_limit'); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; font-weight: 600;">Application Limit:</td>
                        <td style="padding: 8px;"><?php echo formatBytes($MAX_FILE_SIZE); ?></td>
                    </tr>
                </table>
                <p style="margin-top: 15px; font-size: 14px; color: #856404;">
                    <strong>Note:</strong> If your file is 2.6MB and upload fails, check that <code>upload_max_filesize</code> and <code>post_max_size</code> are both at least 3M or higher.
                    To fix, edit your <code>php.ini</code> file or create a <code>.htaccess</code> file with:
                    <code style="display: block; margin-top: 5px; padding: 5px; background: white; border-radius: 4px;">php_value upload_max_filesize 10M<br>php_value post_max_size 10M</code>
                </p>
            </div>
            
            <!-- Files List Section -->
            <div class="files-section">
                <h2>Files (<?php echo count($files); ?>)</h2>
                
                <?php if (empty($files)): ?>
                    <div class="empty-state">
                        No files uploaded yet. Upload your first file above!
                    </div>
                <?php else: ?>
                    <table class="files-table">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Size</th>
                                <th>Modified</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $file): ?>
                                <tr>
                                    <td class="file-name"><?php echo htmlspecialchars($file['name']); ?></td>
                                    <td><?php echo formatBytes($file['size']); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', $file['modified']); ?></td>
                                    <td>
                                        <div class="file-actions">
                                            <a href="?download=<?php echo urlencode($file['name']); ?>" class="btn-download">Download</a>
                                            <a href="?delete=<?php echo urlencode($file['name']); ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this file?')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

