<?php
/*
 * FrSky SBUS Plugin - Daemon control actions
 * Handles Stop, Restart, Uninstall (cleanup) from config page.
 */
if (!defined('FPP_SBUS_PLUGIN_ROOT')) define('FPP_SBUS_PLUGIN_ROOT', __DIR__);
require_once __DIR__ . '/plugin_common.inc';
if (ob_get_level()) ob_end_clean();
fpp_sbus_json_header();
if (!headers_sent()) header('Cache-Control: no-store');

$pluginDir = fpp_sbus_plugin_dir(__DIR__);
$action = $_REQUEST['action'] ?? '';
fpp_sbus_log('actions.php', ['action' => $action]);

$allowed = ['stop', 'restart', 'uninstall'];
if (!in_array($action, $allowed)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid action']);
    exit;
}

$stopScript = $pluginDir . '/scripts/stop_daemon.sh';
$restartScript = $pluginDir . '/scripts/restart_daemon.sh';

$output = [];
$code = -1;

if ($action === 'stop' || $action === 'uninstall') {
    if (is_readable($stopScript)) {
        exec('sh ' . escapeshellarg($stopScript) . ' 2>&1', $output, $code);
    }
    if ($action === 'uninstall') {
        @unlink($pluginDir . '/sbus_daemon.pid');
        @unlink($pluginDir . '/sbus_status.json');
        $output[] = 'Plugin state cleared. Use FPP Plugin Manager to fully uninstall.';
    }
} elseif ($action === 'restart') {
    if (is_readable($restartScript)) {
        exec('sh ' . escapeshellarg($restartScript) . ' 2>&1', $output, $code);
    } else {
        fpp_sbus_log('actions.php restart script not found', ['path' => $restartScript]);
    }
}

fpp_sbus_log('actions.php result', ['action' => $action, 'code' => $code]);
echo json_encode([
    'ok' => ($code === 0 || $code === -1),
    'message' => implode("\n", $output),
    'action' => $action
]);
