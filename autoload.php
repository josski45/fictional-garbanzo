<?php
/**
 * Simple PSR-4 Autoloader
 * Digunakan jika vendor/autoload.php tidak ada
 * 
 * CATATAN: Di server Linux, folder name adalah case-sensitive!
 * Class JosskiTools\Utils\Logger harus ada di src/utils/Logger.php (lowercase 'utils')
 */

spl_autoload_register(function ($class) {
    // Namespace prefix
    $prefix = 'JosskiTools\\';

    // Base directory untuk namespace
    $baseDir = __DIR__ . '/src/';

    // Cek apakah class menggunakan namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Bukan namespace kita, skip
        return;
    }

    // Ambil relative class name (tanpa prefix)
    $relativeClass = substr($class, $len);

    // Pisahkan namespace menjadi array segment
    $segments = explode('\\', $relativeClass);
    if (empty($segments)) {
        return;
    }

    // Ambil nama class (segment paling akhir)
    $className = array_pop($segments);

    // Convert directory segments ke lowercase agar cocok dengan struktur folder (api, utils, dll)
    $directories = array_map(function ($segment) {
        return strtolower($segment);
    }, $segments);

    // Susun kembali path dengan directory lowercase + class name original
    $pathParts = array_merge($directories, [$className]);
    $normalizedPath = implode('/', $pathParts);
    $file = $baseDir . $normalizedPath . '.php';

    // Coba load dengan path normalized
    if (file_exists($file)) {
        require $file;
        return;
    }

    // Fallback: gunakan case original (PSR-4 standard) jika file tidak ditemukan
    $fallbackFile = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($fallbackFile)) {
        require $fallbackFile;
    }
});
