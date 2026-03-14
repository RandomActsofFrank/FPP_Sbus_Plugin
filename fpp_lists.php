<?php
/*
 * FrSky SBUS Plugin - Proxy FPP file/playlist lists for dropdowns (e.g. for AJAX refresh).
 * Returns JSON: { "items": ["name1", "name2", ...] } or { "error": "message" }
 * Primary list data is now inlined in content.php on page load; this endpoint still used if needed.
 */
if (!defined('FPP_SBUS_PLUGIN_ROOT')) define('FPP_SBUS_PLUGIN_ROOT', __DIR__);
require_once __DIR__ . '/plugin_common.inc';
require_once __DIR__ . '/fpp_lists_functions.inc.php';
fpp_sbus_json_header();

$pluginDir = __DIR__;
$configFile = $pluginDir . '/sbus_config.json';
$type = trim((string)($_GET['type'] ?? ''));

$allowed = array('playlists', 'sequences', 'effects', 'media');
if (!in_array($type, $allowed, true)) {
    fpp_sbus_log('fpp_lists.php invalid type', ['type' => $type]);
    echo json_encode(array('error' => 'Invalid type. Use playlists, sequences, effects, or media.'));
    exit;
}
fpp_sbus_log('fpp_lists.php', ['type' => $type]);

$items = fpp_sbus_get_list($configFile, $type, $pluginDir);
fpp_sbus_log('fpp_lists.php result', ['type' => $type, 'count' => count($items)]);
echo json_encode(array('items' => $items));
