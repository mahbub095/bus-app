<?php
/**
 * One-time OPcache reset helper.
 * DELETE THIS FILE after use — it should not remain in production.
 */
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache reset successfully.\n";
} else {
    echo "OPcache is not enabled.\n";
}
// Also attempt to clear APC if present
if (function_exists('apc_clear_cache')) {
    apc_clear_cache();
    echo "APC cache cleared.\n";
}
echo "Done. Delete this file now.\n";
