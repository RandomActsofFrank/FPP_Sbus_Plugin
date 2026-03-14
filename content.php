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

<h3>Receiver Status</h3>
<div id="receiverStatus" class="panel panel-default">
    <div class="panel-body">
        <p id="receiverStatusText">Checking…</p>
        <table class="table table-condensed table-bordered" id="channelTable" style="max-width:600px;margin-top:10px;display:none;">
            <thead><tr><th>Ch</th><th>1</th><th>2</th><th>3</th><th>4</th><th>5</th><th>6</th><th>7</th><th>8</th><th>9</th><th>10</th><th>11</th><th>12</th><th>13</th><th>14</th><th>15</th><th>16</th></tr></thead>
            <tbody><tr><td>Value</td><td id="ch1">-</td><td id="ch2">-</td><td id="ch3">-</td><td id="ch4">-</td><td id="ch5">-</td><td id="ch6">-</td><td id="ch7">-</td><td id="ch8">-</td><td id="ch9">-</td><td id="ch10">-</td><td id="ch11">-</td><td id="ch12">-</td><td id="ch13">-</td><td id="ch14">-</td><td id="ch15">-</td><td id="ch16">-</td></tr></tbody>
        </table>
        <p id="receiverFlags" class="text-muted small" style="margin-top:8px;"></p>
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
var rules = <?php echo $rulesJson ?: '[]'; ?>;

function addRule(ch, minV, maxV, cmd) {
    var tbody = document.getElementById('rulesBody');
    var i = rules.length;
    rules.push({ channel: ch || 1, minVal: minV ?? 172, maxVal: maxV ?? 1811, command: cmd || '', commandType: 'api' });
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><input type="number" min="1" max="16" class="rule-channel" data-i="' + i + '" value="' + (rules[i].channel || 1) + '"></td>' +
        '<td><input type="number" min="172" max="1811" class="rule-min" data-i="' + i + '" value="' + (rules[i].minVal ?? 172) + '"></td>' +
        '<td><input type="number" min="172" max="1811" class="rule-max" data-i="' + i + '" value="' + (rules[i].maxVal ?? 1811) + '"></td>' +
        '<td><input type="text" class="rule-cmd" data-i="' + i + '" placeholder="Start Playlist/MyPlaylist" value="' + (rules[i].command || '') + '" style="width:100%"></td>' +
        '<td><button type="button" class="btn btn-danger btn-sm" onclick="removeRule(' + i + ')">Remove</button></td>';
    tbody.appendChild(tr);
}

function removeRule(i) {
    rules.splice(i, 1);
    renderRules();
}

function renderRules() {
    var tbody = document.getElementById('rulesBody');
    tbody.innerHTML = '';
    for (var i = 0; i < rules.length; i++) {
        addRule(rules[i].channel, rules[i].minVal, rules[i].maxVal, rules[i].command);
    }
}

function updateReceiverStatus() {
    var apiUrl = 'plugin.php?plugin=<?php echo htmlspecialchars($plugin); ?>&page=api.php';
    fetch(apiUrl).then(function(r) { return r.json(); }).then(function(data) {
        var statusEl = document.getElementById('receiverStatusText');
        var tableEl = document.getElementById('channelTable');
        var flagsEl = document.getElementById('receiverFlags');
        if (data.receiver) {
            var r = data.receiver;
            if (r.connected) {
                statusEl.innerHTML = '<span class="label label-success">Receiver connected</span> Channel data updating.';
                tableEl.style.display = 'table';
                for (var i = 1; i <= 16; i++) {
                    var el = document.getElementById('ch' + i);
                    if (el) el.textContent = r.channels[i - 1] !== undefined ? r.channels[i - 1] : '-';
                }
                var flags = [];
                if (r.failsafe) flags.push('Failsafe');
                if (r.frameLost) flags.push('Frame lost');
                if (r.ch17) flags.push('Ch17');
                if (r.ch18) flags.push('Ch18');
                flagsEl.textContent = flags.length ? 'Flags: ' + flags.join(', ') : '';
            } else {
                statusEl.innerHTML = '<span class="label label-warning">No signal</span> No valid SBUS packets received recently. Check wiring, serial port, and that the transmitter is on.';
                tableEl.style.display = 'none';
                flagsEl.textContent = '';
            }
        } else {
            if (!data.running) {
                statusEl.innerHTML = '<span class="label label-default">Daemon not running</span> Enable SBUS and restart FPP, or ensure the daemon is running.';
            } else {
                statusEl.innerHTML = '<span class="label label-warning">Waiting for data</span> Daemon is running. Connect receiver and ensure SBUS output is wired correctly.';
            }
            tableEl.style.display = 'none';
            flagsEl.textContent = '';
        }
    }).catch(function() {
        document.getElementById('receiverStatusText').innerHTML = '<span class="label label-default">Could not fetch status</span>';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    if (rules.length === 0) addRule();
    else renderRules();

    updateReceiverStatus();
    setInterval(updateReceiverStatus, 1500);


    document.querySelector('form').addEventListener('submit', function() {
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
        document.getElementById('rulesInput').value = JSON.stringify(out);
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
