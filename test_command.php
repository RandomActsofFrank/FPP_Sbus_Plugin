<?php
/*
 * FrSky SBUS Plugin - Test FPP command (proxy to FPP API)
 * GET/POST: command=Start Playlist/MyShow
 * Returns JSON: { "ok": true|false, "message": "..." }
 */
if (!defined('FPP_SBUS_PLUGIN_ROOT')) define('FPP_SBUS_PLUGIN_ROOT', __DIR__);
require_once __DIR__ . '/plugin_common.inc';
fpp_sbus_json_header();
if (!headers_sent()) header('Cache-Control: no-store');

$pluginDir = dirname(__DIR__); // plugin package root (e.g. FPP_Sbus_Plugin)
$configFile = $pluginDir . '/sbus_config.json';
$config = [];
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true) ?: [];
}
$host = isset($config['fppHost']) ? trim($config['fppHost']) : '127.0.0.1';
$cmd = isset($_REQUEST['command']) ? trim($_REQUEST['command']) : '';

if ($cmd === '') {
    fpp_sbus_log('test_command.php missing command');
    echo json_encode(['ok' => false, 'message' => 'No command specified.']);
    exit;
}

$url = 'http://' . $host . '/api/command/' . rawurlencode($cmd);
fpp_sbus_log('test_command.php', ['command' => $cmd, 'url' => $url]);

$ctx = stream_context_create(['http' => ['timeout' => 10]]);
$raw = @file_get_contents($url, false, $ctx);

if ($raw === false) {
    $err = error_get_last();
    fpp_sbus_log('test_command.php failed', ['command' => $cmd]);
    echo json_encode(['ok' => false, 'message' => 'Could not reach FPP at ' . $host . '. Check FPP Host.']);
    exit;
}

$code = (isset($http_response_header) && preg_match('#HTTP/\d\.\d\s+(\d+)#', $http_response_header[0], $m)) ? (int)$m[1] : 0;
fpp_sbus_log('test_command.php result', ['command' => $cmd, 'http_code' => $code]);
echo json_encode(['ok' => ($code >= 200 && $code < 300), 'message' => $code >= 200 && $code < 300 ? 'Command sent.' : 'FPP returned HTTP ' . $code]);
