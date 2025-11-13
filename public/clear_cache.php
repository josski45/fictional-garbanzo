<?php
/**
 * Clear PHP OpCache
 */

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OpCache cleared successfully!\n";
} else {
    echo "⚠️ OpCache not available\n";
}

if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    echo "✅ APCu cache cleared successfully!\n";
} else {
    echo "⚠️ APCu not available\n";
}

echo "\n✅ All caches cleared!\n";
echo "Please test your bot again.\n";
