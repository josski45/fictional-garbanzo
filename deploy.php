<?php
/**
 * Auto Deploy Script
 * Membuat ZIP dan deploy ke server
 * Usage: php deploy.php
 */

// Load configuration
$config = require __DIR__ . '/deploy.config.php';

// Constants
define('DEPLOY_URL', $config['deploy_url']);
define('ZIP_NAME', $config['zip_name']);
define('TEMP_DIR', __DIR__ . '/temp_deploy');

// Extract config
$filesToInclude = $config['files'];
$emptyDirs = $config['empty_dirs'];
$excludeFolders = $config['exclude_folders'];

echo "═══════════════════════════════════════\n";
echo "   JOSSKI BOT - AUTO DEPLOY SCRIPT\n";
echo "═══════════════════════════════════════\n\n";

// Step 1: Create temp directory
echo "📁 Step 1: Creating temporary directory...\n";
if (is_dir(TEMP_DIR)) {
    deleteDirectory(TEMP_DIR);
}
mkdir(TEMP_DIR, 0777, true);
echo "✅ Temporary directory created\n\n";

// Step 2: Copy files to temp directory
echo "📋 Step 2: Copying files...\n";
$copiedCount = 0;
foreach ($filesToInclude as $file) {
    $sourcePath = __DIR__ . '/' . $file;
    $destPath = TEMP_DIR . '/' . $file;
    
    if (file_exists($sourcePath)) {
        // Create directory if not exists
        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }
        
        // Copy file
        if (copy($sourcePath, $destPath)) {
            $copiedCount++;
            echo "  ✓ {$file}\n";
        } else {
            echo "  ✗ Failed to copy {$file}\n";
        }
    } else {
        echo "  ⚠ File not found: {$file}\n";
    }
}
echo "\n✅ Copied {$copiedCount} files\n\n";

// Step 3: Create empty directories
echo "📂 Step 3: Creating empty directories...\n";
foreach ($emptyDirs as $dir) {
    $dirPath = TEMP_DIR . '/' . $dir;
    if (!is_dir($dirPath)) {
        mkdir($dirPath, 0777, true);
        // Create .htaccess to protect directory
        if ($config['create_htaccess']) {
            file_put_contents($dirPath . '/.htaccess', "Deny from all\n");
        }
        echo "  ✓ {$dir}\n";
    }
}
echo "✅ Empty directories created\n\n";

// Step 4: Create ZIP
echo "📦 Step 4: Creating ZIP archive...\n";
$zipPath = __DIR__ . '/' . ZIP_NAME;
if (file_exists($zipPath)) {
    unlink($zipPath);
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(TEMP_DIR),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    $fileCount = 0;
    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen(TEMP_DIR) + 1);
            
            $zip->addFile($filePath, $relativePath);
            $fileCount++;
        }
    }
    
    $zip->close();
    
    $zipSize = filesize($zipPath);
    $zipSizeMB = round($zipSize / 1024 / 1024, 2);
    
    echo "✅ ZIP created successfully\n";
    echo "   Files: {$fileCount}\n";
    echo "   Size: {$zipSizeMB} MB\n";
    echo "   Path: {$zipPath}\n\n";
} else {
    echo "❌ Failed to create ZIP\n";
    exit(1);
}

// Step 5: Clean up temp directory
echo "🧹 Step 5: Cleaning up...\n";
deleteDirectory(TEMP_DIR);
echo "✅ Temporary files removed\n\n";

// Step 6: Upload to server
echo "🚀 Step 6: Deploying to server...\n";
echo "   Target: " . DEPLOY_URL . "\n";

if (function_exists('curl_init')) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, DEPLOY_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'zip' => new CURLFile($zipPath, 'application/zip', ZIP_NAME)
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, $config['timeout']);
    
    echo "   Uploading...\n";
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        echo "❌ cURL Error: {$error}\n";
        echo "\n⚠️  ZIP file created but upload failed\n";
        echo "   You can manually upload: {$zipPath}\n";
        exit(1);
    }
    
    echo "\n📡 Server Response:\n";
    echo "   HTTP Code: {$httpCode}\n";
    echo "   Response:\n";
    echo "   " . str_replace("\n", "\n   ", trim($response)) . "\n\n";
    
    if ($httpCode >= 200 && $httpCode < 300) {
        echo "✅ Deploy successful!\n\n";
        
        // Optional: Delete ZIP after successful deploy
        if ($config['delete_zip_after_deploy']) {
            echo "🗑️  Cleaning up ZIP file...\n";
            if (unlink($zipPath)) {
                echo "✅ ZIP file deleted\n";
            }
        } else {
            echo "ℹ️  ZIP file kept at: {$zipPath}\n";
        }
    } else {
        echo "⚠️  Deploy may have issues (HTTP {$httpCode})\n";
        echo "   ZIP file saved at: {$zipPath}\n";
    }
} else {
    echo "❌ cURL not available\n";
    echo "⚠️  ZIP file created but cannot upload\n";
    echo "   Please upload manually: {$zipPath}\n";
    exit(1);
}

echo "\n═══════════════════════════════════════\n";
echo "   DEPLOY COMPLETE!\n";
echo "═══════════════════════════════════════\n";

/**
 * Helper function to delete directory recursively
 */
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    
    rmdir($dir);
}
