<?php
/**
 * View Logs - Real-time log viewer
 */

// Password protection
$password = 'joss2024'; // Ganti dengan password Anda
$inputPass = $_GET['pass'] ?? '';

if ($inputPass !== $password) {
    die('Access denied. Usage: view_logs.php?pass=YOUR_PASSWORD');
}

$action = $_GET['action'] ?? 'view';
$logFile = $_GET['file'] ?? 'webhook.log';

// Available log files
$logFiles = [
    'webhook.log' => 'Webhook Log',
    'webhook_raw.log' => 'Raw Webhook Input',
    'php_errors.log' => 'PHP Errors',
    'error.log' => 'Error Log',
    'telegram_api.log' => 'Telegram API Calls',
    'user_activity.log' => 'User Activity',
];

$logPath = __DIR__ . '/../logs/' . basename($logFile);

?>
<!DOCTYPE html>
<html>
<head>
    <title>📋 View Logs</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        .header { background: #2d2d30; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        h1 { color: #4ec9b0; margin-bottom: 10px; }
        .nav { margin-top: 15px; }
        .nav a { display: inline-block; padding: 8px 15px; background: #007acc; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px; margin-bottom: 10px; font-size: 14px; }
        .nav a:hover { background: #005a9e; }
        .nav a.active { background: #4ec9b0; }
        .log-container { background: #1e1e1e; border: 1px solid #3e3e42; border-radius: 8px; padding: 20px; overflow-x: auto; max-height: 80vh; overflow-y: auto; }
        .log-line { padding: 5px 0; border-bottom: 1px solid #2d2d30; font-size: 13px; line-height: 1.6; }
        .log-line:hover { background: #2d2d30; }
        .timestamp { color: #608b4e; }
        .error { color: #f48771; font-weight: bold; }
        .info { color: #4fc1ff; }
        .success { color: #4ec9b0; }
        .empty { color: #858585; font-style: italic; padding: 20px; text-align: center; }
        .actions { margin-bottom: 15px; }
        .actions button { padding: 10px 20px; background: #007acc; color: white; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px; }
        .actions button:hover { background: #005a9e; }
        .clear-btn { background: #f48771 !important; }
        .clear-btn:hover { background: #d16969 !important; }
        .stats { background: #2d2d30; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .stats span { display: inline-block; margin-right: 20px; }
    </style>
</head>
<body>

<div class="header">
    <h1>📋 Log Viewer</h1>
    <div class="nav">
        <?php foreach ($logFiles as $file => $name): ?>
            <a href="?pass=<?= urlencode($password) ?>&file=<?= urlencode($file) ?>" 
               class="<?= $logFile === $file ? 'active' : '' ?>">
                <?= htmlspecialchars($name) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($action === 'clear' && isset($_GET['confirm'])): ?>
    <?php
    if (file_exists($logPath)) {
        file_put_contents($logPath, '');
        echo "<div style='background: #4ec9b0; color: #1e1e1e; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
        echo "✅ Log file cleared: " . htmlspecialchars($logFile);
        echo "</div>";
    }
    ?>
<?php endif; ?>

<div class="actions">
    <button onclick="location.reload()">🔄 Refresh</button>
    <button onclick="if(confirm('Clear this log file?')) location.href='?pass=<?= urlencode($password) ?>&file=<?= urlencode($logFile) ?>&action=clear&confirm=1'" class="clear-btn">🗑️ Clear Log</button>
    <button onclick="window.open('?pass=<?= urlencode($password) ?>&file=<?= urlencode($logFile) ?>&action=download', '_blank')">💾 Download</button>
</div>

<?php if ($action === 'download'): ?>
    <?php
    if (file_exists($logPath)) {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $logFile . '"');
        readfile($logPath);
        exit;
    }
    ?>
<?php endif; ?>

<?php if (file_exists($logPath)): ?>
    <?php
    $content = file_get_contents($logPath);
    $lines = explode("\n", $content);
    $lines = array_filter($lines);
    $totalLines = count($lines);
    $fileSize = filesize($logPath);
    $lastModified = date('Y-m-d H:i:s', filemtime($logPath));
    ?>
    
    <div class="stats">
        <span class="info">📄 File: <?= htmlspecialchars($logFile) ?></span>
        <span class="success">📊 Lines: <?= $totalLines ?></span>
        <span class="timestamp">📦 Size: <?= number_format($fileSize / 1024, 2) ?> KB</span>
        <span class="timestamp">🕒 Modified: <?= $lastModified ?></span>
    </div>
    
    <div class="log-container">
        <?php if ($totalLines > 0): ?>
            <?php
            // Show last 500 lines
            $displayLines = array_slice($lines, -500);
            foreach ($displayLines as $line):
                $line = htmlspecialchars($line);
                $class = '';
                
                if (stripos($line, 'error') !== false || stripos($line, 'fail') !== false) {
                    $class = 'error';
                } elseif (stripos($line, 'success') !== false || stripos($line, '✅') !== false) {
                    $class = 'success';
                } elseif (stripos($line, 'info') !== false || stripos($line, 'command') !== false) {
                    $class = 'info';
                }
                
                // Highlight timestamp
                $line = preg_replace('/\[(.*?)\]/', '<span class="timestamp">[$1]</span>', $line);
            ?>
                <div class="log-line <?= $class ?>"><?= $line ?></div>
            <?php endforeach; ?>
            
            <?php if ($totalLines > 500): ?>
                <div style="text-align: center; padding: 20px; color: #858585;">
                    Showing last 500 of <?= $totalLines ?> lines. Download full log for complete history.
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty">No log entries found. File is empty.</div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="log-container">
        <div class="empty">Log file not found: <?= htmlspecialchars($logPath) ?></div>
    </div>
<?php endif; ?>

<script>
    // Auto-scroll to bottom
    window.addEventListener('load', function() {
        const container = document.querySelector('.log-container');
        container.scrollTop = container.scrollHeight;
    });
    
    // Auto-refresh every 5 seconds
    setTimeout(function() {
        location.reload();
    }, 5000);
</script>

</body>
</html>
