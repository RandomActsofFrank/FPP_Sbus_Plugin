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

echo json_encode([
    'enabled' => !empty($config['enabled']),
    'running' => $running,
    'serialPort' => $config['serialPort'] ?? null,
    'fppHost' => $config['fppHost'] ?? null,
    'rulesCount' => count($config['rules'] ?? [])
]);
