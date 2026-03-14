<?php
/*
 * FrSky SBUS Plugin - Proxy FPP file/playlist lists for dropdowns
 * Returns JSON: { "items": ["name1", "name2", ...] } or { "error": "message" }
 */
header('Content-Type: application/json');

$pluginDir = dirname(__DIR__);
$configFile = $pluginDir . '/sbus_config.json';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

$allowed = array('playlists', 'sequences', 'effects', 'media');
if (!in_array($type, $allowed, true)) {
    echo json_encode(array('error' => 'Invalid type. Use playlists, sequences, effects, or media.'));
    exit;
}

$config = array();
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true) ?: array();
}
$host = isset($config['fppHost']) ? trim($config['fppHost']) : '127.0.0.1';
$url = 'http://' . $host . '/api/files/' . $type;

$ctx = stream_context_create(array(
    'http' => array('timeout' => 5)
));
$raw = @file_get_contents($url, false, $ctx);
if ($raw === false) {
    echo json_encode(array('error' => 'Could not reach FPP at ' . $host . '. Check FPP Host in config.'));
    exit;
}

$data = json_decode($raw, true);
$items = array();

if (is_array($data)) {
    if (isset($data['files']) && is_array($data['files'])) {
        foreach ($data['files'] as $f) {
            if (is_string($f)) {
                $items[] = $f;
            } elseif (is_array($f) && isset($f['name'])) {
                $items[] = $f['name'];
            }
        }
    } elseif (isset($data['items']) && is_array($data['items'])) {
        $items = $data['items'];
    } elseif (array_keys($data) === array_keys(array_values($data))) {
        foreach ($data as $f) {
            $items[] = is_string($f) ? $f : (isset($f['name']) ? $f['name'] : '');
        }
        $items = array_filter($items);
    }
}

echo json_encode(array('items' => array_values($items)));
