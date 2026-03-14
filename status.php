<?php
/*
 * FrSky SBUS Plugin - Status Page (server-side only, no JavaScript)
 */
if (!defined('FPP_SBUS_PLUGIN_ROOT')) define('FPP_SBUS_PLUGIN_ROOT', __DIR__);
require_once __DIR__ . '/plugin_common.inc';
$pluginDir = fpp_sbus_plugin_dir(__DIR__);
$configFile = $pluginDir . '/sbus_config.json';
$pidFile = $pluginDir . '/sbus_daemon.pid';
$logFile = $pluginDir . '/sbus_daemon.log';
$statusFile = $pluginDir . '/sbus_status.json';

$enabled = false;
$running = false;
$config = null;
$receiver = null;

if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
    $enabled = !empty($config['enabled']);
}

$heartbeatFile = $pluginDir . '/sbus_heartbeat.json';
if (file_exists($pidFile)) {
    $pid = trim((string)@file_get_contents($pidFile));
    $running = $pid !== '' && file_exists("/proc/$pid");
}
if (!$running && file_exists($heartbeatFile) && (@filemtime($heartbeatFile) > 0)) {
    $mtime = @filemtime($heartbeatFile);
    if ($mtime && (microtime(true) - $mtime) < 45) {
        $running = true;
    }
}

if (file_exists($statusFile)) {
    $status = @json_decode(file_get_contents($statusFile), true);
    if ($status) {
        $lastPacket = $status['last_packet'] ?? 0;
        $timeSince = (microtime(true) - $lastPacket);
        $receiver = [
            'connected' => ($timeSince < 0.5),
            'channels' => $status['channels'] ?? array_fill(0, 16, 0),
            'failsafe' => !empty($status['failsafe']),
            'frameLost' => !empty($status['frame_lost']),
            'ch17' => !empty($status['ch17']),
            'ch18' => !empty($status['ch18'])
        ];
    }
}
?>
<div class="container">
<h2>FrSky SBUS Plugin Status</h2>

<table class="table table-striped table-bordered">
<tr><th>Status</th><th>Value</th></tr>
<tr><td>Plugin Enabled</td><td><?php echo $enabled ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>'; ?></td></tr>
<tr><td>Daemon Running</td><td><?php echo $running ? '<span class="label label-success">Running</span>' : '<span class="label label-default">Stopped</span>'; ?></td></tr>
<?php if ($config): ?>
<tr><td>Serial Port</td><td><?php echo htmlspecialchars($config['serialPort'] ?? 'N/A'); ?></td></tr>
<tr><td>FPP Host</td><td><?php echo htmlspecialchars($config['fppHost'] ?? 'N/A'); ?></td></tr>
<tr><td>Rules Configured</td><td><?php echo count($config['rules'] ?? []); ?></td></tr>
<?php endif; ?>
</table>

<?php if ($receiver): ?>
<h3>Receiver</h3>
<p><?php echo $receiver['connected'] ? '<span class="label label-success">Connected</span>' : '<span class="label label-warning">No signal recently</span>'; ?>
<?php
$flags = [];
if ($receiver['failsafe']) $flags[] = 'Failsafe';
if ($receiver['frameLost']) $flags[] = 'Frame lost';
if ($receiver['ch17']) $flags[] = 'Ch17';
if ($receiver['ch18']) $flags[] = 'Ch18';
if (!empty($flags)) echo ' &ndash; ' . implode(', ', $flags);
?></p>
<table class="table table-condensed table-bordered" style="max-width:600px;">
<thead><tr><th>Ch</th><th>1</th><th>2</th><th>3</th><th>4</th><th>5</th><th>6</th><th>7</th><th>8</th><th>9</th><th>10</th><th>11</th><th>12</th><th>13</th><th>14</th><th>15</th><th>16</th></tr></thead>
<tbody><tr><td>Value</td><?php for ($i = 0; $i < 16; $i++) { echo '<td>' . (isset($receiver['channels'][$i]) ? (int)$receiver['channels'][$i] : '-') . '</td>'; } ?></tr></tbody>
</table>
<p class="text-muted small">Refreshes when you reload this page.</p>
<?php elseif ($running): ?>
<h3>Receiver</h3>
<p><span class="label label-default">Daemon running</span> No channel data yet. Connect receiver and reload.</p>
<?php endif; ?>

<?php if (file_exists($logFile) && is_readable($logFile)): ?>
<h3>Recent Log</h3>
<pre style="max-height:200px;overflow:auto;background:#f5f5f5;padding:10px;"><?php echo htmlspecialchars((string)@file_get_contents($logFile, false, null, -2048)); ?></pre>
<?php endif; ?>

<p><a href="plugin.php?plugin=FPP_Sbus_Plugin&page=content.php" class="btn btn-primary">Configure</a></p>
</div>
