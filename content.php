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
<li><strong>This page:</strong> Click <strong>Refresh status</strong> to fetch receiver and channel data from <code>sbus_status.php</code> (reads <code>sbus_status.json</code>).</li>
</ol>
<p>So the only code that reads SBUS from the receiver is <strong>scripts/sbus_fpp_daemon.py</strong>. Rule triggering (FPP API calls) also runs in that daemon.</p>
</div>
</div>

<h3>Receiver Status</h3>
<div id="receiverStatus" class="panel panel-default">
    <div class="panel-body">
        <p id="receiverStatusText">Click <strong>Refresh status</strong> to check receiver and channel data.</p>
        <button type="button" class="btn btn-sm btn-default" id="btnRefreshStatus" style="margin-bottom:8px;">Refresh status</button>
        <table class="table table-condensed table-bordered" id="channelTable" style="max-width:600px;margin-top:10px;display:none;">
            <thead><tr><th>Ch</th><th>1</th><th>2</th><th>3</th><th>4</th><th>5</th><th>6</th><th>7</th><th>8</th><th>9</th><th>10</th><th>11</th><th>12</th><th>13</th><th>14</th><th>15</th><th>16</th></tr></thead>
            <tbody><tr><td>Value</td><td id="ch1">-</td><td id="ch2">-</td><td id="ch3">-</td><td id="ch4">-</td><td id="ch5">-</td><td id="ch6">-</td><td id="ch7">-</td><td id="ch8">-</td><td id="ch9">-</td><td id="ch10">-</td><td id="ch11">-</td><td id="ch12">-</td><td id="ch13">-</td><td id="ch14">-</td><td id="ch15">-</td><td id="ch16">-</td></tr></tbody>
        </table>
        <p id="receiverFlags" class="text-muted small" style="margin-top:8px;"></p>
        <h4 style="margin-top:16px;">Daemon</h4>
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
<p>Choose a <strong>Command</strong> and then an <strong>Item</strong> (playlist, sequence, effect, or media). Use <strong>Custom</strong> for other FPP commands (e.g. <code>Stop</code>, <code>Volume Set/50</code>).</p>
<p><button type="button" class="btn btn-sm btn-default" id="btnRefreshLists">Refresh lists</button> <span id="listsStatus" class="text-muted small"></span></p>

<table class="table table-bordered" id="rulesTable">
<thead><tr><th>Channel</th><th>Min</th><th>Max</th><th>Command</th><th>Item</th><th>Action</th></tr></thead>
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

var FPP_CMD_TYPES = ['Start Playlist', 'Start Sequence', 'Start Effect', 'Custom'];
var FPP_TYPE_MAP = { 'Start Playlist': 'playlists', 'Start Sequence': 'sequences', 'Start Effect': 'effects' };
var fppLists = { playlists: [], sequences: [], effects: [] };
var fppListsBase = 'plugin.php?plugin=<?php echo htmlspecialchars($plugin); ?>&page=fpp_lists.php';

function parseCommand(cmd) {
    var c = (cmd || '').trim();
    if (!c) return { type: 'Start Playlist', item: '', isCustom: true };
    var idx = c.indexOf('/');
    if (idx < 0) return { type: 'Custom', item: c, isCustom: true };
    var type = c.substring(0, idx).trim();
    var item = c.substring(idx + 1).trim();
    if (FPP_CMD_TYPES.indexOf(type) >= 0 && type !== 'Custom') return { type: type, item: item, isCustom: false };
    return { type: 'Custom', item: c, isCustom: true };
}

function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
}

function fillItemDropdown(sel, type, selectedItem) {
    if (!sel) return;
    var list = type === 'Start Playlist' ? fppLists.playlists : type === 'Start Sequence' ? fppLists.sequences : type === 'Start Effect' ? fppLists.effects : [];
    var current = sel.value;
    sel.innerHTML = '<option value="">— Select —</option>';
    for (var j = 0; j < list.length; j++) {
        var opt = document.createElement('option');
        opt.value = list[j];
        opt.textContent = list[j];
        if (list[j] === selectedItem || list[j] === current) opt.selected = true;
        sel.appendChild(opt);
      }
    if (!sel.value && selectedItem) {
        var o = document.createElement('option');
        o.value = selectedItem;
        o.textContent = selectedItem;
        o.selected = true;
        sel.insertBefore(o, sel.firstChild.nextSibling);
    }
}

function appendRuleRow(i) {
    var tbody = document.getElementById('rulesBody');
    if (!tbody) return;
    var r = rules[i];
    if (!r) return;
    var parsed = parseCommand(r.command);
    var tr = document.createElement('tr');
    tr.setAttribute('data-i', i);
    var typeOpts = FPP_CMD_TYPES.map(function(t) {
        return '<option value="' + esc(t) + '"' + (parsed.type === t ? ' selected' : '') + '>' + esc(t) + '</option>';
    }).join('');
    var itemVal = parsed.isCustom ? '' : parsed.item;
    tr.innerHTML = '<td><input type="number" min="1" max="16" class="rule-channel" data-i="' + i + '" value="' + (r.channel || 1) + '"></td>' +
        '<td><input type="number" min="172" max="1811" class="rule-min" data-i="' + i + '" value="' + (r.minVal ?? 172) + '"></td>' +
        '<td><input type="number" min="172" max="1811" class="rule-max" data-i="' + i + '" value="' + (r.maxVal ?? 1811) + '"></td>' +
        '<td><select class="rule-cmd-type" data-i="' + i + '">' + typeOpts + '</select></td>' +
        '<td><select class="rule-item" data-i="' + i + '" style="min-width:140px;"></select><input type="text" class="rule-cmd-custom" data-i="' + i + '" placeholder="e.g. Stop" style="display:none;min-width:140px;" value="' + esc(parsed.isCustom ? parsed.item : '') + '"></td>' +
        '<td><button type="button" class="btn btn-danger btn-sm" onclick="removeRule(' + i + ')">Remove</button></td>';
    tbody.appendChild(tr);
    var typeSel = tr.querySelector('.rule-cmd-type');
    var itemSel = tr.querySelector('.rule-item');
    var customInp = tr.querySelector('.rule-cmd-custom');
    if (parsed.type === 'Custom') {
        itemSel.style.display = 'none';
        customInp.style.display = 'inline';
    } else {
        fillItemDropdown(itemSel, parsed.type, parsed.item);
    }
    typeSel.addEventListener('change', function() {
        var t = typeSel.value;
        if (t === 'Custom') {
            itemSel.style.display = 'none';
            customInp.style.display = 'inline';
            if (!customInp.value && itemSel.value) customInp.value = typeSel.options[typeSel.selectedIndex].text + '/' + itemSel.value;
        } else {
            customInp.style.display = 'none';
            itemSel.style.display = 'inline';
            itemSel.innerHTML = '<option value="">— Select —</option>';
            var fppType = FPP_TYPE_MAP[t];
            if (fppLists[fppType].length) {
                fillItemDropdown(itemSel, t, '');
            } else {
                fetch(fppListsBase + '&type=' + encodeURIComponent(fppType)).then(function(res) { return res.json(); }).then(function(data) {
                    if (data.items) {
                        fppLists[fppType] = data.items;
                        fillItemDropdown(itemSel, t, '');
                    }
                }).catch(function() {});
            }
        }
    });
}

function refillAllItemDropdowns() {
    var rows = document.querySelectorAll('#rulesBody tr');
    rows.forEach(function(row) {
        var typeSel = row.querySelector('.rule-cmd-type');
        var itemSel = row.querySelector('.rule-item');
        if (!typeSel || !itemSel || typeSel.value === 'Custom') return;
        if (itemSel.style.display === 'none') return;
        var current = itemSel.value;
        fillItemDropdown(itemSel, typeSel.value, current);
    });
}

function loadFppLists(done) {
    var statusEl = document.getElementById('listsStatus');
    if (statusEl) statusEl.textContent = 'Loading…';
    var types = ['playlists', 'sequences', 'effects'];
    var left = types.length;
    var errors = [];
    function check() {
        left--;
        if (left !== 0) return;
        refillAllItemDropdowns();
        if (statusEl) {
            var total = fppLists.playlists.length + fppLists.sequences.length + fppLists.effects.length;
            if (errors.length) statusEl.textContent = errors[0];
            else if (total === 0) statusEl.textContent = 'No items from FPP. Check FPP Host; use Refresh lists to retry.';
            else statusEl.textContent = 'Lists loaded.';
        }
        if (done) done();
    }
    types.forEach(function(t) {
        fetch(fppListsBase + '&type=' + encodeURIComponent(t)).then(function(r) { return r.json(); }).then(function(data) {
            if (data.error) errors.push(data.error);
            if (data.items && Array.isArray(data.items)) fppLists[t] = data.items;
            check();
        }).catch(function() {
            errors.push('Could not load ' + t + '.');
            check();
        });
    });
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

function updateReceiverStatus() {
    var apiUrl = 'plugin.php?plugin=<?php echo htmlspecialchars($plugin); ?>&page=sbus_status.php';
    var statusEl = document.getElementById('receiverStatusText');
    var tableEl = document.getElementById('channelTable');
    var flagsEl = document.getElementById('receiverFlags');
    var btn = document.getElementById('btnRefreshStatus');
    if (!statusEl) return;
    if (btn) btn.disabled = true;
    statusEl.textContent = 'Checking…';
    var timedOut = false;
    var timeoutId = setTimeout(function() { timedOut = true; }, 5000);
    var receiverDetected = false;
    fetch(apiUrl)
        .then(function(r) { return r.text(); })
        .then(function(text) {
            if (timedOut) return;
            clearTimeout(timeoutId);
            var data = {};
            try {
                var m = text.match(/\{[\s\S]*\}/);
                if (m) data = JSON.parse(m[0]);
            } catch (e) {}
            if (data.receiver && data.receiver.connected) {
                receiverDetected = true;
                statusEl.innerHTML = '<span class="label label-success">Receiver connected</span> Channel data below.';
                if (tableEl) tableEl.style.display = 'table';
                for (var i = 1; i <= 16; i++) {
                    var el = document.getElementById('ch' + i);
                    if (el && data.receiver.channels) el.textContent = data.receiver.channels[i - 1] !== undefined ? data.receiver.channels[i - 1] : '-';
                }
                var flags = [];
                if (data.receiver.failsafe) flags.push('Failsafe');
                if (data.receiver.frameLost) flags.push('Frame lost');
                if (data.receiver.ch17) flags.push('Ch17');
                if (data.receiver.ch18) flags.push('Ch18');
                if (flagsEl) flagsEl.textContent = flags.length ? 'Flags: ' + flags.join(', ') : '';
            } else {
                if (data.receiver) {
                    statusEl.innerHTML = '<span class="label label-warning">No signal</span> No valid SBUS packets recently.';
                } else if (!data.running) {
                    statusEl.innerHTML = '<span class="label label-default">Daemon not running</span> Enable SBUS and restart FPP.';
                } else {
                    statusEl.innerHTML = '<span class="label label-warning">Waiting for data</span> Daemon running. Connect receiver.';
                }
                if (tableEl) tableEl.style.display = 'none';
                if (flagsEl) flagsEl.textContent = '';
            }
        })
        .catch(function() {
            clearTimeout(timeoutId);
            if (!timedOut && statusEl) statusEl.innerHTML = '<span class="label label-default">Could not fetch status</span>';
        })
        .finally(function() {
            if (timedOut && statusEl) {
                statusEl.innerHTML = '<span class="label label-default">Request timed out</span>';
            }
            if (receiverDetected) {
                if (btn) btn.disabled = false;
            } else {
                if (btn) btn.disabled = false;
            }
        });
}

document.addEventListener('DOMContentLoaded', function() {
    loadFppLists(function() {
        if (rules.length === 0) addRule();
        else renderRules();
    });

    var btnRefreshLists = document.getElementById('btnRefreshLists');
    if (btnRefreshLists) btnRefreshLists.addEventListener('click', function() {
        loadFppLists();
    });

    var btnRefresh = document.getElementById('btnRefreshStatus');
    if (btnRefresh) btnRefresh.addEventListener('click', updateReceiverStatus);

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
            if (data.ok && document.getElementById('btnRefreshStatus')) updateReceiverStatus();
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
        rows.forEach(function(row) {
            var ch = row.querySelector('.rule-channel');
            var minV = row.querySelector('.rule-min');
            var maxV = row.querySelector('.rule-max');
            var typeSel = row.querySelector('.rule-cmd-type');
            var itemSel = row.querySelector('.rule-item');
            var customInp = row.querySelector('.rule-cmd-custom');
            if (!ch || !minV || !maxV || !typeSel) return;
            var cmd = '';
            if (typeSel.value === 'Custom' && customInp) {
                cmd = customInp.value.trim();
            } else if (itemSel && typeSel.value && itemSel.value) {
                cmd = typeSel.value + '/' + itemSel.value;
            }
            if (cmd) {
                out.push({
                    channel: parseInt(ch.value) || 1,
                    minVal: parseInt(minV.value) || 172,
                    maxVal: parseInt(maxV.value) || 1811,
                    command: cmd,
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
