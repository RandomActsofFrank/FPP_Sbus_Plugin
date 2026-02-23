<?php
/*
 * FrSky SBUS Plugin - Status Page
 */
$pluginDir = dirname(__DIR__);
$configFile = $pluginDir . '/sbus_config.json';
$pidFile = $pluginDir . '/sbus_daemon.pid';
$logFile = $pluginDir . '/sbus_daemon.log';

$enabled = false;
$running = false;
$config = null;

if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
    $enabled = !empty($config['enabled']);
}

if (file_exists($pidFile)) {
    $pid = trim(file_get_contents($pidFile));
    $running = $pid && file_exists("/proc/$pid");
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

<?php if (file_exists($logFile) && is_readable($logFile)): ?>
<h3>Recent Log</h3>
<pre style="max-height:200px;overflow:auto;background:#f5f5f5;padding:10px;"><?php echo htmlspecialchars(file_get_contents($logFile, false, null, -2048)); ?></pre>
<?php endif; ?>

<p><a href="plugin.php?plugin=FPP_Sbus_Plugin&page=content.php" class="btn btn-primary">Configure</a></p>
</div>
