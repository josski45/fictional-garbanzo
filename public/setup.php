<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cek apakah sudah login
session_start();
$isLoggedIn = isset($_SESSION['setup_logged_in']) && $_SESSION['setup_logged_in'] === true;

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Password untuk akses setup (ganti ini!)
define('SETUP_PASSWORD', 'joss2024');

// Handle login
if (isset($_POST['login'])) {
    // Use hash_equals() for constant-time comparison to prevent timing attacks
    $providedPassword = $_POST['password'] ?? '';
    if (hash_equals(SETUP_PASSWORD, $providedPassword)) {
        $_SESSION['setup_logged_in'] = true;
        header('Location: setup.php');
        exit;
    } else {
        $loginError = 'Password salah!';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: setup.php');
    exit;
}

// Require login untuk akses
if (!$isLoggedIn) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>🔐 Login - JOSS Setup</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .login-box { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); max-width: 400px; width: 100%; }
            h1 { color: #2c3e50; margin-bottom: 30px; text-align: center; }
            input[type="password"] { width: 100%; padding: 15px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px; margin-bottom: 20px; }
            button { width: 100%; padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; font-weight: bold; }
            button:hover { opacity: 0.9; }
            .error { background: #fee; color: #c00; padding: 10px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>🔐 Login Setup</h1>
            <?php if (isset($loginError)): ?>
                <div class="error"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Masukkan password" required autofocus>
                <button type="submit" name="login">🔓 Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$action = $_GET['action'] ?? 'menu';
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>🔧 JOSS Setup - Bot Configuration</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); padding: 40px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 3px solid #3498db; }
        h1 { color: #2c3e50; font-size: 32px; }
        .logout-btn { background: #e74c3c; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 14px; }
        .logout-btn:hover { background: #c0392b; }
        h2 { color: #34495e; margin: 30px 0 15px 0; padding-bottom: 10px; border-bottom: 3px solid #3498db; }
        h3 { color: #555; margin: 20px 0 10px 0; font-size: 18px; }
        .menu { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin: 30px 0; }
        .menu-item { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 12px; text-decoration: none; display: block; transition: all 0.3s; }
        .menu-item:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        .menu-item h3 { color: white; margin: 0 0 10px 0; font-size: 20px; }
        .menu-item p { opacity: 0.9; font-size: 14px; line-height: 1.5; margin: 0; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #2c3e50; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px; }
        .form-group textarea { min-height: 120px; font-family: monospace; }
        .form-group small { display: block; margin-top: 5px; color: #7f8c8d; font-size: 13px; }
        .btn { display: inline-block; background: #3498db; color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; border: none; cursor: pointer; font-size: 16px; font-weight: 500; }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .back-btn { background: #95a5a6; margin-right: 10px; }
        .back-btn:hover { background: #7f8c8d; }
        .alert { padding: 15px; border-radius: 8px; margin: 20px 0; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 5px solid #3498db; }
        code { background: #ecf0f1; padding: 3px 8px; border-radius: 4px; font-family: 'Courier New', monospace; color: #e74c3c; }
        pre { background: #2c3e50; color: #ecf0f1; padding: 20px; border-radius: 8px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        th { background: #3498db; color: white; }
        .status-ok { color: #27ae60; font-weight: bold; }
        .status-error { color: #e74c3c; font-weight: bold; }
        .status-warn { color: #f39c12; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔧 JOSS Setup</h1>
        <a href="?logout" class="logout-btn">🚪 Logout</a>
    </div>

<?php

if ($message) {
    echo "<div class='alert alert-success'>✅ " . htmlspecialchars($message) . "</div>";
}
if ($error) {
    echo "<div class='alert alert-error'>❌ " . htmlspecialchars($error) . "</div>";
}

if ($action === 'menu') {
    ?>
    <p style="color: #7f8c8d; margin-bottom: 30px;">Panel konfigurasi dan deployment untuk JOSS Helper Bot</p>
    
    <div class="menu">
        <a href="?action=env" class="menu-item">
            <h3>📝 Edit .env File</h3>
            <p>Konfigurasi bot token, API keys, dan pengaturan dasar</p>
        </a>
        <a href="?action=webhook" class="menu-item">
            <h3>🔗 Setup Webhook</h3>
            <p>Atur dan verifikasi webhook URL untuk bot Telegram</p>
        </a>
        <a href="?action=deploy" class="menu-item">
            <h3>🚀 Deployment Config</h3>
            <p>Edit deploy.config.php untuk upload ke server</p>
        </a>
        <a href="?action=permissions" class="menu-item">
            <h3>🔐 Fix Permissions</h3>
            <p>Perbaiki permission folder logs, temp, sessions, dll</p>
        </a>
        <a href="?action=clear" class="menu-item">
            <h3>🗑️ Clear Cache</h3>
            <p>Hapus temporary files, logs, dan cache</p>
        </a>
        <a href="test.php" class="menu-item">
            <h3>🧪 Test Suite</h3>
            <p>Test bot, webhook, dan debug configuration</p>
        </a>
    </div>
    <?php

} elseif ($action === 'env') {
    $envPath = __DIR__ . '/../.env';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_env'])) {
        // CSRF token validation
        $providedToken = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'], $providedToken)) {
            $error = 'Invalid CSRF token!';
        } else {
            $content = $_POST['env_content'];

            // Validate .env content to prevent code injection
            // Check for PHP tags or suspicious patterns
            if (preg_match('/<\?php|<\?=|<script|eval\(|exec\(|system\(|passthru\(/i', $content)) {
                $error = 'Invalid content detected! .env file cannot contain PHP code or scripts.';
            } else {
                if (file_put_contents($envPath, $content)) {
                    header('Location: setup.php?action=menu&message=' . urlencode('File .env berhasil disimpan!'));
                    exit;
                } else {
                    $error = 'Gagal menyimpan file .env!';
                }
            }
        }
    }
    
    $envContent = file_exists($envPath) ? file_get_contents($envPath) : '';
    if (empty($envContent)) {
        $envContent = "# Telegram Bot Configuration
BOT_TOKEN=YOUR_BOT_TOKEN_HERE
WEBHOOK_URL=https://yourdomain.com/webhook.php

# Security
SECRET_KEY=JSK
DEFAULT_ENCRYPTION_KEY=Match&Ocean

# API Keys
FERDEV_API_KEY=

# Admin Configuration
ADMIN_IDS=123456789,987654321

# Directories
TEMP_DIR=temp
SESSIONS_DIR=sessions

# Limits
MAX_FILE_SIZE=52428800
";
    }
    ?>
    <h2>📝 Edit .env File</h2>
    
    <div class="info-box">
        <h3>ℹ️ Petunjuk:</h3>
        <ul style="margin-left: 20px; line-height: 1.8;">
            <li><strong>BOT_TOKEN:</strong> Dapatkan dari @BotFather di Telegram</li>
            <li><strong>WEBHOOK_URL:</strong> URL lengkap webhook.php Anda</li>
            <li><strong>ADMIN_IDS:</strong> User ID Telegram admin (pisahkan dengan koma)</li>
            <li><strong>FERDEV_API_KEY:</strong> API key untuk layanan Ferdev (opsional)</li>
        </ul>
    </div>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <div class="form-group">
            <label>📄 Isi File .env:</label>
            <textarea name="env_content" required><?= htmlspecialchars($envContent) ?></textarea>
            <small>Edit nilai sesuai kebutuhan, simpan untuk menerapkan perubahan</small>
        </div>

        <button type="submit" name="save_env" class="btn btn-success">💾 Simpan .env</button>
        <a href="?action=menu" class="btn back-btn">← Kembali</a>
    </form>
    <?php

} elseif ($action === 'webhook') {
    require_once __DIR__ . '/../src/utils/TelegramBot.php';
    
    $configPath = __DIR__ . '/../config/config.php';
    $config = file_exists($configPath) ? require $configPath : [];
    
    if (empty($config['bot_token']) || $config['bot_token'] === 'YOUR_BOT_TOKEN_HERE') {
        ?>
        <h2>🔗 Setup Webhook</h2>
        <div class="alert alert-error">
            ❌ Bot token belum dikonfigurasi! Silakan set <strong>BOT_TOKEN</strong> di file .env terlebih dahulu.
            <br><br>
            <a href="?action=env" class="btn">📝 Edit .env</a>
        </div>
        <a href="?action=menu" class="btn back-btn">← Kembali</a>
        <?php
    } else {
        $bot = new JosskiTools\Utils\TelegramBot($config['bot_token']);
        
        if (isset($_POST['set_webhook'])) {
            $webhookUrl = trim($_POST['webhook_url']);
            $result = $bot->setWebhook($webhookUrl);
            
            if ($result['ok']) {
                header('Location: setup.php?action=webhook&message=' . urlencode('Webhook berhasil diset!'));
                exit;
            } else {
                $error = 'Gagal set webhook: ' . ($result['description'] ?? 'Unknown error');
            }
        }
        
        if (isset($_POST['delete_webhook'])) {
            $result = $bot->deleteWebhook();
            if ($result['ok']) {
                header('Location: setup.php?action=webhook&message=' . urlencode('Webhook berhasil dihapus!'));
                exit;
            }
        }
        
        $webhookInfo = $bot->getWebhookInfo();
        $currentWebhook = $webhookInfo['result']['url'] ?? '';
        
        // Auto-detect webhook URL
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $scriptPath = dirname(dirname($_SERVER['PHP_SELF']));
        $suggestedUrl = $protocol . '://' . $host . $scriptPath . '/webhook.php';
        
        ?>
        <h2>🔗 Setup Webhook</h2>
        
        <?php if ($currentWebhook): ?>
        <div class="alert alert-success">
            ✅ <strong>Webhook Aktif:</strong><br>
            <code><?= htmlspecialchars($currentWebhook) ?></code>
        </div>
        
        <h3>📊 Status Webhook:</h3>
        <table>
            <tr>
                <th>Parameter</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>URL</td>
                <td><code><?= htmlspecialchars($currentWebhook) ?></code></td>
            </tr>
            <tr>
                <td>Pending Updates</td>
                <td><?= $webhookInfo['result']['pending_update_count'] ?? 0 ?></td>
            </tr>
            <?php if (isset($webhookInfo['result']['last_error_date'])): ?>
            <tr>
                <td>Last Error</td>
                <td style="color: #e74c3c;">
                    <?= date('Y-m-d H:i:s', $webhookInfo['result']['last_error_date']) ?><br>
                    <small><?= htmlspecialchars($webhookInfo['result']['last_error_message']) ?></small>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        
        <form method="POST" style="margin-top: 20px;" onsubmit="return confirm('Yakin ingin hapus webhook?')">
            <button type="submit" name="delete_webhook" class="btn btn-danger">🗑️ Hapus Webhook</button>
        </form>
        <?php else: ?>
        <div class="alert alert-warning">
            ⚠️ Webhook belum diset. Bot tidak akan menerima update otomatis.
        </div>
        <?php endif; ?>
        
        <h3>🔧 Set Webhook Baru:</h3>
        <form method="POST">
            <div class="form-group">
                <label>Webhook URL:</label>
                <input type="url" name="webhook_url" value="<?= htmlspecialchars($currentWebhook ?: $suggestedUrl) ?>" required>
                <small>
                    <strong>Saran:</strong> <code><?= htmlspecialchars($suggestedUrl) ?></code><br>
                    ⚠️ <strong>Penting:</strong> Gunakan HTTPS, port 80/88/443/8443, dan SSL valid!
                </small>
            </div>
            
            <button type="submit" name="set_webhook" class="btn btn-success">✅ Set Webhook</button>
            <a href="?action=menu" class="btn back-btn">← Kembali</a>
        </form>
        <?php
    }

} elseif ($action === 'deploy') {
    $deployConfigPath = __DIR__ . '/../deploy.config.php';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_deploy'])) {
        // CSRF token validation
        $providedToken = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'], $providedToken)) {
            $error = 'Invalid CSRF token!';
        } else {
            $content = $_POST['deploy_content'];

            // Validate PHP syntax by checking for obvious malicious patterns
            // Since this is a config file, we expect it to be valid PHP
            $suspiciousPatterns = [
                '/eval\s*\(/i',
                '/exec\s*\(/i',
                '/system\s*\(/i',
                '/passthru\s*\(/i',
                '/shell_exec\s*\(/i',
                '/`[^`]*`/',  // Backticks
                '/proc_open\s*\(/i',
                '/popen\s*\(/i',
                '/curl_exec\s*\(/i',
                '/curl_multi_exec\s*\(/i',
                '/parse_ini_file\s*\(/i',
                '/show_source\s*\(/i'
            ];

            $isSuspicious = false;
            foreach ($suspiciousPatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $isSuspicious = true;
                    break;
                }
            }

            if ($isSuspicious) {
                $error = 'Suspicious code patterns detected! Config file should only contain configuration arrays.';
            } else {
                if (file_put_contents($deployConfigPath, $content)) {
                    header('Location: setup.php?action=menu&message=' . urlencode('deploy.config.php berhasil disimpan!'));
                    exit;
                } else {
                    $error = 'Gagal menyimpan deploy.config.php!';
                }
            }
        }
    }
    
    $deployContent = file_exists($deployConfigPath) ? file_get_contents($deployConfigPath) : '';
    if (empty($deployContent)) {
        $deployContent = "<?php
return [
    // FTP/SFTP Configuration
    'connection' => [
        'type' => 'ftp', // 'ftp' atau 'sftp'
        'host' => 'ftp.yourdomain.com',
        'port' => 21, // 21 untuk FTP, 22 untuk SFTP
        'username' => 'your_username',
        'password' => 'your_password',
        'timeout' => 30,
    ],
    
    // Remote server path
    'remote_path' => '/public_html/bot/', // Path di server
    
    // Local project path
    'local_path' => __DIR__,
    
    // Files/folders to exclude from deployment
    'exclude' => [
        '.git',
        '.gitignore',
        '.env',
        'node_modules',
        'vendor',
        'tests',
        'README.md',
        'composer.json',
        'composer.lock',
        'deploy.config.php',
        'deploy.php',
        'logs/*',
        'temp/*',
        'sessions/*',
        'data/cache/*',
        'data/stats/*',
    ],
    
    // Backup settings
    'backup' => [
        'enabled' => true,
        'path' => __DIR__ . '/backups',
        'keep_last' => 5, // Simpan 5 backup terakhir
    ],
    
    // Post-deployment commands (optional)
    'post_deploy' => [
        // Uncomment jika ingin auto-set webhook setelah deploy
        // 'set_webhook' => true,
        // 'webhook_url' => 'https://yourdomain.com/webhook.php',
    ],
];
";
    }
    ?>
    <h2>🚀 Deployment Configuration</h2>
    
    <div class="info-box">
        <h3>ℹ️ Petunjuk:</h3>
        <ul style="margin-left: 20px; line-height: 1.8;">
            <li><strong>host:</strong> Alamat FTP/SFTP server Anda</li>
            <li><strong>username & password:</strong> Kredensial FTP/SFTP</li>
            <li><strong>remote_path:</strong> Path folder di server (contoh: /public_html/bot/)</li>
            <li><strong>exclude:</strong> File/folder yang tidak ikut di-upload</li>
            <li>Setelah save, gunakan <code>deploy.php</code> untuk upload ke server</li>
        </ul>
    </div>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <div class="form-group">
            <label>📄 Isi deploy.config.php:</label>
            <textarea name="deploy_content" required style="min-height: 400px;"><?= htmlspecialchars($deployContent) ?></textarea>
            <small>Edit konfigurasi deployment sesuai server Anda</small>
        </div>

        <button type="submit" name="save_deploy" class="btn btn-success">💾 Simpan Config</button>
        <a href="?action=menu" class="btn back-btn">← Kembali</a>
    </form>
    <?php

} elseif ($action === 'permissions') {
    $directories = [
        'logs' => __DIR__ . '/../logs',
        'temp' => __DIR__ . '/../temp',
        'sessions' => __DIR__ . '/../sessions',
        'data/cache' => __DIR__ . '/../data/cache',
        'data/stats' => __DIR__ . '/../data/stats',
    ];
    
    if (isset($_POST['fix_permissions'])) {
        $fixed = [];
        $errors = [];
        
        foreach ($directories as $name => $path) {
            if (!is_dir($path)) {
                if (mkdir($path, 0755, true)) {
                    $fixed[] = "Folder $name berhasil dibuat";
                } else {
                    $errors[] = "Gagal membuat folder $name";
                }
            }
            
            if (is_dir($path)) {
                if (chmod($path, 0755)) {
                    $fixed[] = "Permission $name berhasil diset (0755)";
                } else {
                    $errors[] = "Gagal set permission $name";
                }
            }
        }
        
        if ($errors) {
            $error = implode(', ', $errors);
        } else {
            header('Location: setup.php?action=menu&message=' . urlencode('Semua permissions berhasil diperbaiki!'));
            exit;
        }
    }
    ?>
    <h2>🔐 Fix Permissions</h2>
    
    <h3>📂 Status Direktori:</h3>
    <table>
        <tr>
            <th>Direktori</th>
            <th>Exists</th>
            <th>Writable</th>
            <th>Permission</th>
        </tr>
        <?php foreach ($directories as $name => $path): 
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            $perms = $exists ? substr(sprintf('%o', fileperms($path)), -4) : '-';
        ?>
        <tr>
            <td><code><?= htmlspecialchars($name) ?></code></td>
            <td><?= $exists ? "<span class='status-ok'>✅</span>" : "<span class='status-error'>❌</span>" ?></td>
            <td><?= $writable ? "<span class='status-ok'>✅</span>" : "<span class='status-error'>❌</span>" ?></td>
            <td><code><?= $perms ?></code></td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <form method="POST" style="margin-top: 20px;">
        <button type="submit" name="fix_permissions" class="btn btn-success">🔧 Perbaiki Semua</button>
        <a href="?action=menu" class="btn back-btn">← Kembali</a>
    </form>
    
    <div class="info-box" style="margin-top: 30px;">
        <h3>ℹ️ Catatan:</h3>
        <p>Jika ada error, coba jalankan manual via terminal:</p>
        <pre>chmod -R 755 logs temp sessions data/cache data/stats</pre>
    </div>
    <?php

} elseif ($action === 'clear') {
    if (isset($_POST['clear_confirmed'])) {
        $cleared = [];
        $clearTargets = $_POST['clear_targets'] ?? [];
        
        if (in_array('logs', $clearTargets)) {
            $logFiles = glob(__DIR__ . '/../logs/*.log');
            foreach ($logFiles as $file) {
                if (file_put_contents($file, '') !== false) {
                    $cleared[] = basename($file);
                }
            }
        }
        
        if (in_array('temp', $clearTargets)) {
            $tempFiles = glob(__DIR__ . '/../temp/*');
            foreach ($tempFiles as $file) {
                if (is_file($file) && unlink($file)) {
                    $cleared[] = 'temp/' . basename($file);
                }
            }
        }
        
        if (in_array('sessions', $clearTargets)) {
            $sessionFiles = glob(__DIR__ . '/../sessions/*');
            foreach ($sessionFiles as $file) {
                if (is_file($file) && unlink($file)) {
                    $cleared[] = 'sessions/' . basename($file);
                }
            }
        }
        
        if (in_array('cache', $clearTargets)) {
            $cacheFiles = glob(__DIR__ . '/../data/cache/*');
            foreach ($cacheFiles as $file) {
                if (is_file($file) && unlink($file)) {
                    $cleared[] = 'cache/' . basename($file);
                }
            }
        }
        
        $message = count($cleared) . ' file berhasil dibersihkan!';
        header('Location: setup.php?action=menu&message=' . urlencode($message));
        exit;
    }
    ?>
    <h2>🗑️ Clear Cache & Temporary Files</h2>
    
    <div class="alert alert-warning">
        ⚠️ <strong>Perhatian:</strong> Proses ini akan menghapus file-file temporary dan logs. Pastikan Anda yakin!
    </div>
    
    <form method="POST">
        <h3>📂 Pilih yang ingin dibersihkan:</h3>
        <div class="form-group">
            <label>
                <input type="checkbox" name="clear_targets[]" value="logs" checked>
                <strong>Logs</strong> - Kosongkan semua file log
            </label>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="clear_targets[]" value="temp" checked>
                <strong>Temp</strong> - Hapus file temporary
            </label>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="clear_targets[]" value="sessions">
                <strong>Sessions</strong> - Hapus session files (logout semua user)
            </label>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="clear_targets[]" value="cache">
                <strong>Cache</strong> - Hapus cached data
            </label>
        </div>
        
        <button type="submit" name="clear_confirmed" class="btn btn-danger" onclick="return confirm('Yakin ingin membersihkan file-file ini?')">🗑️ Bersihkan Sekarang</button>
        <a href="?action=menu" class="btn back-btn">← Kembali</a>
    </form>
    <?php
}

?>

</div>
</body>
</html>
