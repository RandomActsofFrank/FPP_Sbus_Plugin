<?php
/*
 * FrSky SBUS Plugin - Shared helpers (safe headers, logging)
 * Callers must define FPP_SBUS_PLUGIN_ROOT before including, e.g.:
 *   if (!defined('FPP_SBUS_PLUGIN_ROOT')) define('FPP_SBUS_PLUGIN_ROOT', __DIR__);
 *   require_once __DIR__ . '/plugin_common.inc';
 */

if (!function_exists('fpp_sbus_log')) {
    /**
     * Append a line to fpp_sbus.log in the FPP log folder (or plugin dir if not writable).
     * @param string $message
     * @param array $context Optional key-value data (e.g. ['type' => 'playlists', 'host' => '127.0.0.1'])
     */
    function fpp_sbus_log($message, $context = []) {
        static $logFile = null;
        if ($logFile === null) {
            $fppLogDirs = array('/home/fpp/logs', '/var/log/fpp', '/opt/fpp/logs');
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
