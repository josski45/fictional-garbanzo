<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/utils/SessionManager.php';

use JosskiTools\Utils\SessionManager;

$config = require __DIR__ . '/config/config.php';
$sessionManager = new SessionManager($config['directories']['sessions']);

echo "🧹 Cleaning up old files...\n\n";

$cleaned = 0;

// Clean session files
$sessionsCleaned = $sessionManager->cleanExpiredSessions();
echo "✅ Cleaned {$sessionsCleaned} expired sessions\n";

// Clean temp files
$tempFiles = glob($config['directories']['temp'] . '/*');
$now = time();
$maxAge = 24 * 60 * 60; // 24 hours

foreach ($tempFiles as $file) {
    if (is_file($file)) {
        if ($now - filemtime($file) > $maxAge) {
            unlink($file);
            $cleaned++;
        }
    }
}

echo "✅ Cleaned {$cleaned} old temp files\n";

// Clean downloads
$downloadFiles = glob($config['directories']['downloads'] . '/*');
$cleaned = 0;

foreach ($downloadFiles as $file) {
    if (is_file($file)) {
        if ($now - filemtime($file) > $maxAge) {
            unlink($file);
            $cleaned++;
        }
    }
}

echo "✅ Cleaned {$cleaned} old download files\n";

echo "\n🎉 Cleanup complete!\n";
