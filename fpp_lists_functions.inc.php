<?php
/*
 * Shared logic for fetching FPP playlists/sequences/effects/media.
 * Used by fpp_lists.php (API) and content.php (inline lists on page load).
 * Returns array of item names for dropdowns.
 */
if (!function_exists('fpp_sbus_extract_names')) {
    function fpp_sbus_extract_names($data, $type = '') {
        $out = array();
        if (!is_array($data)) return $out;
        if (array_keys($data) === range(0, count($data) - 1) && !empty($data)) {
            foreach ($data as $f) {
                if (is_string($f)) $out[] = $f;
                elseif (is_array($f) && (isset($f['name']) || isset($f['filename']))) $out[] = isset($f['name']) ? $f['name'] : $f['filename'];
            }
            return array_values(array_filter($out));
        }
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
                $out[] = is_string($f) ? $f : (is_array($f) && (isset($f['name']) || isset($f['filename'])) ? (isset($f['name']) ? $f['name'] : $f['filename']) : '');
            }
            return array_values(array_filter($out));
        }
        return $out;
    }
}

/**
 * Get list of names for a given type (playlists, sequences, effects, media).
 * @param string $configFile Full path to sbus_config.json
 * @param string $type One of: playlists, sequences, effects, media
 * @param string $pluginDir Directory containing plugin (for filesystem fallback)
 * @return array List of item names (strings)
 */
function fpp_sbus_get_list($configFile, $type, $pluginDir) {
    $allowed = array('playlists', 'sequences', 'effects', 'media');
    if (!in_array($type, $allowed, true)) return array();

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
    $ctx = stream_context_create(array('http' => array('timeout' => 5)));
    foreach ($hostsToTry as $host) {
        $base = 'http://' . $host . '/api/';
        $urls = array($base . $type);
        if ($type === 'playlists') $urls[] = $base . 'playlist';
        foreach ($urls as $url) {
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw !== false) {
                $data = json_decode($raw, true);
                $items = fpp_sbus_extract_names($data, $type);
                if (!empty($items)) break 2;
            }
        }
    }

    if (empty($items)) {
        $mediaBases = array('/home/fpp/media', '/var/www/media', '/opt/fpp/media', $pluginDir . '/media');
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
                if (!empty($items)) break 2;
            }
        }
        $items = array_unique($items);
        sort($items);
    }

    return array_values(array_map('strval', array_filter($items, function($v) { return $v !== ''; })));
}
