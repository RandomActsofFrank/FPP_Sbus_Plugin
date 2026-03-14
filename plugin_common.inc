<?php
/*
 * FrSky SBUS Plugin - Shared helpers (safe headers, logging)
 * Include from plugin PHP pages: require_once __DIR__ . '/plugin_common.inc';
 */

if (!function_exists('fpp_sbus_log')) {
    /**
     * Append a line to fpp_sbus.log for debugging.
     * @param string $message
     * @param array $context Optional key-value data (e.g. ['type' => 'playlists', 'host' => '127.0.0.1'])
     */
    function fpp_sbus_log($message, $context = []) {
        static $logFile = null;
        if ($logFile === null) {
            $logFile = (function_exists('dirname') && defined('__DIR__')) ? __DIR__ . '/fpp_sbus.log' : '';
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
