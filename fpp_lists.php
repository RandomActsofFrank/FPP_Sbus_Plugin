<?php
/*
 * FrSky SBUS Plugin - Proxy FPP file/playlist lists for dropdowns
 * Returns JSON: { "items": ["name1", "name2", ...] } or { "error": "message" }
 * Tries FPP REST API first, then falls back to listing local media directories.
 */
if (!defined('FPP_SBUS_PLUGIN_ROOT')) define('FPP_SBUS_PLUGIN_ROOT', __DIR__);
require_once __DIR__ . '/plugin_common.inc';
fpp_sbus_json_header();

$pluginDir = dirname(__DIR__);
$configFile = $pluginDir . '/sbus_config.json';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

$allowed = array('playlists', 'sequences', 'effects', 'media');
if (!in_array($type, $allowed, true)) {
    fpp_sbus_log('fpp_lists.php invalid type', ['type' => $type]);
    echo json_encode(array('error' => 'Invalid type. Use playlists, sequences, effects, or media.'));
    exit;
}
fpp_sbus_log('fpp_lists.php', ['type' => $type]);

$config = array();
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true) ?: array();
}
$configHost = isset($config['fppHost']) ? trim($config['fppHost']) : '127.0.0.1';
$requestHost = isset($_SERVER['HTTP_HOST']) ? trim($_SERVER['HTTP_HOST']) : '';
if (strpos($requestHost, ':') !== false) {
    $requestHost = substr($requestHost, 0, strpos($requestHost, ':'));
}
$hostsToTry = array_unique(array_filter(array($configHost, $requestHost, '127.0.0.1', 'localhost')));

$items = array();

function extractNames($data, $type = '') {
    $out = array();
    if (!is_array($data)) return $out;
    if (isset($data['items']) && is_array($data['items'])) {
        foreach ($data['items'] as $f) {
            if (is_string($f)) $out[] = $f;
            elseif (is_array($f)) $out[] = isset($f['name']) ? $f['name'] : (isset($f['filename']) ? $f['filename'] : (isset($f['path']) ? basename($f['path'], '.' . pathinfo($f['path'], PATHINFO_EXTENSION)) : ''));
        }
        return array_values(array_filter($out));
    }
    if (isset($data['files']) && is_array($data['files'])) {
        foreach ($data['files'] as $f) {
            if (is_string($f)) $out[] = $f;
            elseif (is_array($f)) $out[] = isset($f['name']) ? $f['name'] : (isset($f['filename']) ? $f['filename'] : '');
        }
        return array_values(array_filter($out));
    }
    if ($type && isset($data[$type]) && is_array($data[$type])) {
        foreach ($data[$type] as $f) {
            $out[] = is_string($f) ? $f : (is_array($f) && isset($f['name']) ? $f['name'] : (is_array($f) && isset($f['filename']) ? $f['filename'] : ''));
        }
        return array_values(array_filter($out));
    }
    if (array_keys($data) === array_keys(array_values($data))) {
        foreach ($data as $f) {
            $out[] = is_string($f) ? $f : (is_array($f) && (isset($f['name']) || isset($f['filename'])) ? ($f['name'] ?? $f['filename']) : '');
        }
        return array_values(array_filter($out));
    }
    return $out;
}

$ctx = stream_context_create(array('http' => array('timeout' => 5)));
$listSource = '';
foreach ($hostsToTry as $host) {
    $base = 'http://' . $host . '/api/';
    $urls = array($base . $type);
    if ($type === 'playlists') {
        $urls[] = $base . 'playlist';
    }
    foreach ($urls as $url) {
        $raw = @file_get_contents($url, false, $ctx);
        fpp_sbus_log('fpp_lists try url', ['url' => $url, 'ok' => ($raw !== false), 'len' => ($raw !== false ? strlen($raw) : 0)]);
        if ($raw !== false) {
            $data = json_decode($raw, true);
            $items = extractNames($data, $type);
            if (!empty($items)) {
                $listSource = 'api:' . $url;
                break 2;
            }
            if (is_array($data) && isset($data['error'])) continue;
        }
    }
}

if (empty($items)) {
    $mediaBases = array('/home/fpp/media', '/var/www/media', '/opt/fpp/media', __DIR__ . '/../media', __DIR__ . '/../../media', $pluginDir . '/media');
    $subDirs = array('playlists' => array('playlists', 'playlist'), 'sequences' => array('sequences', 'sequence'), 'effects' => array('effects', 'effect'), 'media' => array('music', 'media'));
    $extMap = array('playlists' => array('.json'), 'sequences' => array('.fseq', '.eseq'), 'effects' => array('.eseq'), 'media' => array('.mp3', '.ogg', '.wav'));
    $subList = isset($subDirs[$type]) ? $subDirs[$type] : array($type);
    $exts = isset($extMap[$type]) ? $extMap[$type] : array();
    foreach ($mediaBases as $base) {
        foreach ($subList as $sub) {
            $dir = $base . '/' . $sub;
            if (!is_dir($dir)) continue;
            $files = @scandir($dir);
            if (!$files) continue;
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                $path = $dir . '/' . $f;
                if (is_dir($path)) continue;
                $ext = strrchr($f, '.');
                if ($ext && in_array(strtolower($ext), $exts, true)) {
                    $items[] = basename($f, $ext);
                }
            }
            if (!empty($items)) {
                $listSource = 'fs:' . $dir;
                break 2;
            }
        }
    }
    $items = array_unique($items);
    sort($items);
}

$items = array_values(array_map('strval', array_filter($items, function($v) { return $v !== ''; })));
fpp_sbus_log('fpp_lists.php result', ['type' => $type, 'count' => count($items), 'source' => isset($listSource) ? $listSource : 'none']);
echo json_encode(array('items' => $items));
