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

$pluginDir = fpp_sbus_plugin_dir(__DIR__);
$configFile = $pluginDir . '/sbus_config.json';
$config = [];
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true) ?: [];
}
$configHost = trim((string)($config['fppHost'] ?? '')) ?: '127.0.0.1';
$requestHost = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
if (strpos($requestHost, ':') !== false) {
    $requestHost = substr($requestHost, 0, strpos($requestHost, ':'));
}
$host = $requestHost !== '' ? $requestHost : $configHost;
if ($host === '') $host = '127.0.0.1';
$cmd = trim((string)($_REQUEST['command'] ?? ''));

if ($cmd === '') {
    fpp_sbus_log('test_command.php missing command');
    echo json_encode(['ok' => false, 'message' => 'No command specified.']);
    exit;
}

// FPP API accepts + for spaces in path segments (urlencode gives +).
$url = null;
$idx = strpos($cmd, '/');
if ($idx !== false) {
    $type = trim(substr($cmd, 0, $idx));
    $name = trim(substr($cmd, $idx + 1));
    $base = 'http://' . $host . '/api/';
    if ($type === 'Start Playlist' && $name !== '') {
        $url = $base . 'playlist/' . urlencode($name) . '/start';
    } elseif ($type === 'Start Sequence' && $name !== '') {
        // Sequence start requires the .fseq extension in the path segment.
        $seqName = $name;
        if (strtolower(substr($seqName, -5)) !== '.fseq') {
            $seqName .= '.fseq';
        }
        $url = $base . 'sequence/' . urlencode($seqName) . '/start';
    } elseif ($type === 'Start Effect' && $name !== '') {
        $url = $base . 'effect/' . urlencode($name) . '/start';
    } elseif ($type === 'Start Media' && $name !== '') {
        // Media can be started via the same playlist start endpoint by passing the media filename.
        $url = $base . 'playlist/' . urlencode($name) . '/start';
    }
}
if ($url === null) {
    $url = 'http://' . $host . '/api/command/' . urlencode($cmd);
}

fpp_sbus_log('test_command.php', ['command' => $cmd, 'url' => $url]);

$ctx = stream_context_create(['http' => ['timeout' => 10]]);
$raw = @file_get_contents($url, false, $ctx);

if ($raw === false) {
    fpp_sbus_log('test_command.php failed', ['command' => $cmd]);
    echo json_encode(['ok' => false, 'message' => 'Could not reach FPP at ' . $host . '. Check FPP Host.']);
    exit;
}

$code = 0;
if (isset($http_response_header) && is_array($http_response_header) && !empty($http_response_header[0])) {
    if (preg_match('#HTTP/\d\.\d\s+(\d+)#', $http_response_header[0], $m)) {
        $code = (int)$m[1];
    }
}
if ($code === 0 && $raw !== false) {
    $code = 200;
}
$ok = ($code >= 200 && $code < 300);
fpp_sbus_log('test_command.php result', ['command' => $cmd, 'url' => $url, 'http_code' => $code, 'ok' => $ok]);
echo json_encode(['ok' => $ok, 'message' => $ok ? 'Command sent.' : 'FPP returned HTTP ' . $code]);
