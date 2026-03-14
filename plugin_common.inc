<?php
/*
 * FrSky SBUS Plugin - Shared helpers (safe headers, logging)
 * Compatible with PHP 8.2.x (e.g. 8.2.7). Avoids passing null to trim/pathinfo/htmlspecialchars.
 * Callers must define FPP_SBUS_PLUGIN_ROOT before including, e.g.:
 *   if (!defined('FPP_SBUS_PLUGIN_ROOT')) define('FPP_SBUS_PLUGIN_ROOT', __DIR__);
 *   require_once __DIR__ . '/plugin_common.inc';
 */

if (!function_exists('fpp_sbus_plugin_dir')) {
    /**
     * Resolve plugin directory so PHP and daemon use the same path (pid/heartbeat/config).
     * When FPP runs postStart, the daemon uses FPPDIR/plugins/FPP_Sbus_Plugin. The web server
     * may serve from a different __DIR__. Prefer known FPP paths so we find daemon-written files.
     * @param string $currentDir Usually __DIR__ of the including script
     * @return string Plugin root path (with trailing slash stripped)
     */
    function fpp_sbus_plugin_dir($currentDir) {
        $candidates = array(
            '/home/fpp/media/plugins/FPP_Sbus_Plugin',
            '/home/fpp/plugins/FPP_Sbus_Plugin',
            '/opt/fpp/media/plugins/FPP_Sbus_Plugin',
            '/opt/fpp/plugins/FPP_Sbus_Plugin',
            '/var/www/html/plugins/FPP_Sbus_Plugin',
            $currentDir,
        );
        foreach ($candidates as $dir) {
            $dir = rtrim(str_replace('\\', '/', $dir), '/');
            if ($dir === '') continue;
            if (file_exists($dir . '/sbus_config.json') || file_exists($dir . '/scripts/sbus_fpp_daemon.py')) {
                return $dir;
            }
        }
        return $currentDir;
    }
}

if (!function_exists('fpp_sbus_log')) {
    /**
     * Append a line to fpp_sbus.log in the FPP log folder (or plugin dir if not writable).
     * @param string $message
     * @param array $context Optional key-value data (e.g. ['type' => 'playlists', 'host' => '127.0.0.1'])
     */
    function fpp_sbus_log($message, $context = []) {
        static $logFile = null;
        if ($logFile === null) {
            $fppLogDirs = array('/home/fpp/media/logs', '/home/fpp/logs', '/var/log/fpp', '/opt/fpp/logs');
            $logFile = '';
            foreach ($fppLogDirs as $dir) {
                if (is_dir($dir) && is_writable($dir)) {
                    $logFile = $dir . '/fpp_sbus.log';
                    break;
                }
            }
            if ($logFile === '') {
                $base = defined('FPP_SBUS_PLUGIN_ROOT') ? FPP_SBUS_PLUGIN_ROOT : __DIR__;
                $logFile = $base . '/fpp_sbus.log';
            }
        }
        if ($logFile === '' || $logFile === false) return;
        $ts = date('Y-m-d H:i:s');
        $line = $ts . ' ' . $message;
        if (!empty($context)) {
            $line .= ' ' . json_encode($context);
        }
        $line .= "\n";
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('fpp_sbus_json_header')) {
    /**
     * Send JSON content-type header only if headers not already sent (avoids PHP warning when FPP wraps plugin output).
     */
    function fpp_sbus_json_header() {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
    }
}
