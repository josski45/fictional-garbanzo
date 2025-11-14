<?php
/**
 * Test Autoloader Script
 * Test apakah autoloader bisa load class dengan benar
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Autoloader...</h2>\n";

// Load autoloader
$autoloadPath = __DIR__ . '/../autoload.php';
echo "<p>1. Loading autoloader from: $autoloadPath</p>\n";

if (!file_exists($autoloadPath)) {
    die("<p style='color:red'>❌ Autoloader file not found!</p>");
}

require_once $autoloadPath;
echo "<p style='color:green'>✅ Autoloader loaded</p>\n";

// Test Logger class dengan berbagai cara
echo "<h3>Test 1: class_exists dengan backslash</h3>\n";
if (class_exists('\JosskiTools\Utils\Logger')) {
    echo "<p style='color:green'>✅ Logger class exists (with backslash)</p>\n";
} else {
    echo "<p style='color:red'>❌ Logger class NOT found (with backslash)</p>\n";
}

echo "<h3>Test 2: class_exists tanpa backslash</h3>\n";
if (class_exists('JosskiTools\Utils\Logger')) {
    echo "<p style='color:green'>✅ Logger class exists (without backslash)</p>\n";
} else {
    echo "<p style='color:red'>❌ Logger class NOT found (without backslash)</p>\n";
}

echo "<h3>Test 3: Direct class usage</h3>\n";
try {
    \JosskiTools\Utils\Logger::init(__DIR__ . '/../logs');
    echo "<p style='color:green'>✅ Logger::init() called successfully</p>\n";
} catch (Error $e) {
    echo "<p style='color:red'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "<h3>Test 4: Check file path</h3>\n";
$loggerFile = __DIR__ . '/../src/utils/Logger.php';
echo "<p>Logger.php path: $loggerFile</p>\n";
if (file_exists($loggerFile)) {
    echo "<p style='color:green'>✅ Logger.php file exists</p>\n";
    
    // Baca namespace dari file
    $content = file_get_contents($loggerFile);
    if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
        echo "<p>Namespace in file: <code>{$matches[1]}</code></p>\n";
    }
    if (preg_match('/class\s+(\w+)/', $content, $matches)) {
        echo "<p>Class name in file: <code>{$matches[1]}</code></p>\n";
    }
} else {
    echo "<p style='color:red'>❌ Logger.php file NOT found!</p>\n";
}

echo "<h3>Test 5: Debug autoloader</h3>\n";
echo "<pre>\n";
echo "Base dir: " . __DIR__ . "/../src/\n";
echo "Expected file for JosskiTools\\Utils\\Logger:\n";
echo "  " . __DIR__ . "/../src/Utils/Logger.php\n";
echo "\nFile exists checks:\n";
echo "  src/utils/Logger.php: " . (file_exists(__DIR__ . '/../src/utils/Logger.php') ? 'YES' : 'NO') . "\n";
echo "  src/Utils/Logger.php: " . (file_exists(__DIR__ . '/../src/Utils/Logger.php') ? 'YES' : 'NO') . "\n";
echo "</pre>\n";

echo "<h3>Done!</h3>\n";
