<?php
/*
 * FrSky SBUS Plugin - API endpoints (optional)
 * Can expose status/config via JSON for external tools.
 */
header('Content-Type: application/json');

$pluginDir = dirname(__DIR__);
$configFile = $pluginDir . '/sbus_config.json';

if (!file_exists($configFile)) {
    echo json_encode(['error' => 'Config not found']);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);
$pidFile = $pluginDir . '/sbus_daemon.pid';
$running = false;
if (file_exists($pidFile)) {
    $pid = trim(file_get_contents($pidFile));
    $running = $pid && @file_exists("/proc/$pid");
}

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
