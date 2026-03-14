<?php
/*
 * FrSky SBUS Plugin - Status JSON (receiver/daemon state)
 * Used by config page only. Named to avoid conflicting with FPP core api/file endpoints.
 */
if (!defined('FPP_SBUS_PLUGIN_ROOT')) define('FPP_SBUS_PLUGIN_ROOT', __DIR__);
require_once __DIR__ . '/plugin_common.inc';
fpp_sbus_json_header();
fpp_sbus_log('sbus_status.php requested');

$pluginDir = dirname(__DIR__);
$configFile = $pluginDir . '/sbus_config.json';

$config = [];
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true) ?: [];
}
$pidFile = $pluginDir . '/sbus_daemon.pid';
$running = false;
if (file_exists($pidFile)) {
    $pid = trim(file_get_contents($pidFile));
    $running = $pid && @file_exists("/proc/$pid");
}
fpp_sbus_log('status', ['running' => $running, 'enabled' => !empty($config['enabled'])]);

$out = [
    'enabled' => !empty($config['enabled']),
    'running' => $running,
    'serialPort' => $config['serialPort'] ?? null,
    'fppHost' => $config['fppHost'] ?? null,
    'rulesCount' => count($config['rules'] ?? [])
];

// Receiver status (connection + channel data)
$statusFile = $pluginDir . '/sbus_status.json';
if (file_exists($statusFile)) {
    $status = @json_decode(file_get_contents($statusFile), true);
    if ($status) {
        $lastPacket = $status['last_packet'] ?? 0;
        $timeSince = (microtime(true) - $lastPacket);
        $out['receiver'] = [
            'connected' => ($timeSince < 0.5),
            'lastPacket' => $lastPacket,
            'channels' => $status['channels'] ?? array_fill(0, 16, 0),
            'ch17' => !empty($status['ch17']),
            'ch18' => !empty($status['ch18']),
            'failsafe' => !empty($status['failsafe']),
            'frameLost' => !empty($status['frame_lost'])
        ];
    }
}

echo json_encode($out);
