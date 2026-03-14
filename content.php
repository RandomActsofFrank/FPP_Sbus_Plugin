<?php
/*
 * FrSky SBUS Plugin - Configuration Page
 * Map SBUS channel values to FPP effects/playlists
 */

$plugin = 'FPP_Sbus_Plugin';
$pluginDir = dirname(__DIR__);
$configFile = $pluginDir . '/sbus_config.json';
$defaultConfig = array(
    'enabled' => 0,
    'serialPort' => '/dev/ttyAMA0',
    'baudRate' => 100000,
    'fppHost' => '127.0.0.1',
    'rules' => array()
);

// Load config
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
    if (!$config) $config = $defaultConfig;
} else {
    $config = $defaultConfig;
}

// Handle form submission
if (isset($_POST['save'])) {
    $config['enabled'] = isset($_POST['enabled']) ? 1 : 0;
    $sp = $_POST['serialPort'] ?? '';
    $custom = trim($_POST['serialPortCustom'] ?? '');
    $config['serialPort'] = ($sp === 'custom' && $custom !== '') ? $custom : trim($sp ?: '/dev/ttyAMA0');
    $config['baudRate'] = intval($_POST['baudRate'] ?: 100000);
    $config['fppHost'] = trim($_POST['fppHost'] ?: '127.0.0.1');
    $config['rules'] = array();

    // Parse rules
    if (!empty($_POST['rules'])) {
        $rulesData = json_decode($_POST['rules'], true);
        if (is_array($rulesData)) {
            foreach ($rulesData as $r) {
                if (!empty($r['channel']) && !empty($r['command']) && isset($r['minVal']) && isset($r['maxVal'])) {
                    $config['rules'][] = array(
                        'channel' => intval($r['channel']),
                        'minVal' => intval($r['minVal']),
                        'maxVal' => intval($r['maxVal']),
                        'command' => trim($r['command']),
                        'commandType' => trim($r['commandType'] ?? 'api')
                    );
                }
            }
        }
    }

    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    echo "<div class='alert alert-success'>Configuration saved. Restart FPP or the SBUS daemon for changes to take effect.</div>";
}

// Build rules JSON for editing
$rulesJson = json_encode($config['rules'], JSON_PRETTY_PRINT);

// Discover available serial ports
$serialPorts = array('/dev/ttyAMA0', '/dev/ttyS0', '/dev/ttyUSB0', '/dev/ttyUSB1', '/dev/ttyUSB2');
?>

<div class="container">
<h2>FrSky SBUS Plugin Configuration</h2>

<p>Configure SBUS reception from your FrSky RC receiver and map servo channel values to FPP commands (effects, playlists, etc.).</p>
<p><strong>Note:</strong> SBUS uses inverted serial logic. You need an <a href="https://electronicspost.com/explain-the-logic-not-gate-or-inverter-and-its-operation-with-truth-table/" target="_blank">inverter circuit</a> (e.g., transistor + resistors) between the receiver and Raspberry Pi unless your hardware inverts the signal.</p>

<div class="panel panel-info" style="margin-bottom:16px;">
<div class="panel-heading"><strong>How SBUS reading works (no SBUS code in PHP)</strong></div>
<div class="panel-body small">
<p>A <strong>Python daemon</strong> does all SBUS reading; this config page only displays the result.</p>
<ol>
<li><strong>Daemon:</strong> <code>scripts/sbus_fpp_daemon.py</code> runs in the background (started when FPP starts, if the plugin is enabled). It opens the serial port (e.g. /dev/ttyAMA0) at 100000 baud, 8E2.</li>
<li><strong>Serial → SBUS:</strong> The daemon reads raw bytes, finds 25-byte SBUS packets (header 0x0F, footer 0x00), and decodes the 16 channels (11 bits each) plus ch17/ch18, failsafe, and frame_lost. The protocol is implemented in <code>parse_sbus_packet()</code> in that script.</li>
<li><strong>Status file:</strong> After each valid packet, the daemon writes <code>sbus_status.json</code> in the plugin directory with last_packet time, channels[1–16], and flags.</li>
<li><strong>Status page:</strong> <strong>Status &rarr; SBUS Status</strong> reads <code>sbus_status.json</code> when you open it and shows receiver/channel data (no JavaScript polling).</li>
</ol>
<p>So the only code that reads SBUS from the receiver is <strong>scripts/sbus_fpp_daemon.py</strong>. Rule triggering (FPP API calls) also runs in that daemon.</p>
</div>
</div>

<h3>Daemon</h3>
<div class="panel panel-default">
    <div class="panel-body">
        <p class="text-muted small">Receiver and channel status: use <strong>Status &rarr; SBUS Status</strong> in the menu (no JavaScript status checks on this page).</p>
        <div style="margin-top:8px;">
            <button type="button" class="btn btn-primary btn-sm" id="btnRestartDaemon">Restart Daemon</button>
            <button type="button" class="btn btn-warning btn-sm" id="btnStopDaemon">Stop Daemon</button>
            <button type="button" class="btn btn-danger btn-sm" id="btnUninstall">Clean Up / Uninstall</button>
            <span id="daemonActionResult" class="text-muted small" style="margin-left:10px;"></span>
        </div>
        <p class="text-muted small" style="margin-top:8px;">If the buttons fail: <a href="plugin.php?plugin=<?php echo htmlspecialchars($plugin); ?>&page=actions.php&action=restart" target="_blank">Restart</a> | <a href="plugin.php?plugin=<?php echo htmlspecialchars($plugin); ?>&page=actions.php&action=stop" target="_blank">Stop</a> (open in new tab to run).</p>
    </div>
</div>

<form method="post">
<table class="table table-striped table-bordered">
<tr><th>Setting</th><th>Value</th></tr>
<tr>
    <td>Enable SBUS</td>
    <td><input type="checkbox" name="enabled" value="1" <?php echo $config['enabled'] ? 'checked' : ''; ?>></td>
</tr>
<tr>
    <td>Serial Port</td>
    <td>
        <?php
        $sp = $config['serialPort'];
        $isCustom = !in_array($sp, $serialPorts);
        ?>
        <select name="serialPort" id="serialPortSelect">
            <?php foreach ($serialPorts as $port): ?>
            <option value="<?php echo htmlspecialchars($port); ?>" <?php echo ($sp === $port) ? 'selected' : ''; ?>><?php echo htmlspecialchars($port); ?></option>
            <?php endforeach; ?>
            <option value="custom" <?php echo $isCustom ? 'selected' : ''; ?>>Custom...</option>
        </select>
        <input type="text" name="serialPortCustom" id="serialPortCustom" placeholder="/dev/ttyXXX" value="<?php echo $isCustom ? htmlspecialchars($sp) : ''; ?>" style="<?php echo $isCustom ? '' : 'display:none;'; ?>">
    </td>
</tr>
<tr>
    <td>Baud Rate</td>
    <td><input type="number" name="baudRate" value="<?php echo htmlspecialchars($config['baudRate']); ?>" required> (SBUS = 100000)</td>
</tr>
<tr>
    <td>FPP Host</td>
    <td><input type="text" name="fppHost" value="<?php echo htmlspecialchars($config['fppHost']); ?>"> (127.0.0.1 for local)</td>
</tr>
</table>

<h3>Channel → Effect Rules</h3>
<p>When an SBUS channel value falls within min-max, the command is sent. SBUS channels use values 172–1811 (FrSky 0–100%).</p>
<p><strong>Command types:</strong> <code>api</code> = FPP REST API (e.g. <code>Start Playlist/My Playlist</code> or <code>Start Effect/Effect Name</code>)</p>

<table class="table table-bordered" id="rulesTable">
<thead><tr><th>Channel</th><th>Min Value</th><th>Max Value</th><th>FPP Command</th><th>Action</th></tr></thead>
<tbody id="rulesBody">
</tbody>
</table>
<button type="button" class="btn btn-primary" onclick="addRule()">Add Rule</button>

<input type="hidden" name="rules" id="rulesInput">

<p style="margin-top:20px;"><button type="submit" name="save" class="btn btn-success">Save Configuration</button></p>
</form>
</div>

<script>
var rules = <?php echo isset($rulesJson) && $rulesJson !== '' ? $rulesJson : '[]'; ?>;
if (!Array.isArray(rules)) rules = [];

function appendRuleRow(i) {
    var tbody = document.getElementById('rulesBody');
    if (!tbody) return;
    var r = rules[i];
    if (!r) return;
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><input type="number" min="1" max="16" class="rule-channel" data-i="' + i + '" value="' + (r.channel || 1) + '"></td>' +
        '<td><input type="number" min="172" max="1811" class="rule-min" data-i="' + i + '" value="' + (r.minVal ?? 172) + '"></td>' +
        '<td><input type="number" min="172" max="1811" class="rule-max" data-i="' + i + '" value="' + (r.maxVal ?? 1811) + '"></td>' +
        '<td><input type="text" class="rule-cmd" data-i="' + i + '" placeholder="Start Playlist/MyPlaylist" value="' + (r.command || '') + '" style="width:100%"></td>' +
        '<td><button type="button" class="btn btn-danger btn-sm" onclick="removeRule(' + i + ')">Remove</button></td>';
    tbody.appendChild(tr);
}

function addRule(ch, minV, maxV, cmd) {
    var i = rules.length;
    rules.push({ channel: ch || 1, minVal: minV ?? 172, maxVal: maxV ?? 1811, command: cmd || '', commandType: 'api' });
    appendRuleRow(i);
}

function removeRule(i) {
    rules.splice(i, 1);
    renderRules();
}

function renderRules() {
    var tbody = document.getElementById('rulesBody');
    if (!tbody) return;
    tbody.innerHTML = '';
    for (var i = 0; i < rules.length; i++) {
        appendRuleRow(i);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (rules.length === 0) addRule();
    else renderRules();

    function doDaemonAction(action, btnId) {
        var btn = document.getElementById(btnId);
        var resultEl = document.getElementById('daemonActionResult');
        if (btn) btn.disabled = true;
        if (resultEl) resultEl.textContent = 'Running…';
        var url = 'plugin.php?plugin=<?php echo htmlspecialchars($plugin); ?>&page=actions.php&action=' + encodeURIComponent(action);
        fetch(url).then(function(r) { return r.text(); }).then(function(text) {
            var data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                var m = text.match(/\{[\s\S]*?\}(?=\s*$|\s*<)/);
                data = m ? JSON.parse(m[0]) : { ok: false, message: 'Response was not JSON. Check if page loaded correctly.' };
            }
            if (resultEl) resultEl.textContent = data.message || (data.ok ? 'Done' : 'Failed');
        }).catch(function(err) {
            if (resultEl) resultEl.textContent = 'Request failed. Try the link below or run the script via SSH.';
        }).finally(function() {
            if (btn) btn.disabled = false;
            if (resultEl) setTimeout(function() { resultEl.textContent = ''; }, 8000);
        });
    }
    var btnRestart = document.getElementById('btnRestartDaemon');
    var btnStop = document.getElementById('btnStopDaemon');
    var btnUninstall = document.getElementById('btnUninstall');
    if (btnRestart) btnRestart.addEventListener('click', function() { doDaemonAction('restart', 'btnRestartDaemon'); });
    if (btnStop) btnStop.addEventListener('click', function() { doDaemonAction('stop', 'btnStopDaemon'); });
    if (btnUninstall) btnUninstall.addEventListener('click', function() {
        if (confirm('Stop daemon and clear plugin state? Use FPP Plugin Manager to fully remove the plugin.')) {
            doDaemonAction('uninstall', 'btnUninstall');
        }
    });


    var form = document.querySelector('form');
    if (form) form.addEventListener('submit', function() {
        var rows = document.querySelectorAll('#rulesBody tr');
        var out = [];
        rows.forEach(function(row, i) {
            var ch = row.querySelector('.rule-channel');
            var minV = row.querySelector('.rule-min');
            var maxV = row.querySelector('.rule-max');
            var cmd = row.querySelector('.rule-cmd');
            if (ch && minV && maxV && cmd && cmd.value.trim()) {
                out.push({
                    channel: parseInt(ch.value) || 1,
                    minVal: parseInt(minV.value) || 172,
                    maxVal: parseInt(maxV.value) || 1811,
                    command: cmd.value.trim(),
                    commandType: 'api'
                });
            }
        });
        var rulesInput = document.getElementById('rulesInput');
        if (rulesInput) rulesInput.value = JSON.stringify(out);
    });

    var sel = document.getElementById('serialPortSelect');
    var customInput = document.getElementById('serialPortCustom');
    function toggleCustom() {
        customInput.style.display = (sel.value === 'custom') ? 'inline' : 'none';
    }
    sel.addEventListener('change', toggleCustom);
    toggleCustom();
});
</script>
