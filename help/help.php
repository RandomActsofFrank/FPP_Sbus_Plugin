<?php
?>
<div class="container">
<h2>FrSky SBUS Plugin Help</h2>

<h3>Setup</h3>
<ol>
<li>Connect your FrSky SBUS receiver to the Raspberry Pi via an <strong>inverter circuit</strong> (SBUS uses inverted serial logic).</li>
<li>Enable the plugin and set the correct serial port (e.g. /dev/ttyAMA0 for built-in UART, /dev/ttyUSB0 for USB-serial).</li>
<li>Add rules: choose an SBUS channel (1–16), set min/max value range (typically 172–1811 for FrSky), and the FPP command to run.</li>
</ol>

<h3>FPP Commands</h3>
<p>Commands use the FPP REST API format. Examples:</p>
<ul>
<li><code>Start Playlist/My Playlist</code> – Start a playlist</li>
<li><code>Start Effect/Fire</code> – Start an effect</li>
<li><code>Stop Playlist</code> – Stop current playlist</li>
<li><code>Volume Set/70</code> – Set volume</li>
</ul>
<p>See FPP's <code>/apihelp.php</code> on your FPP instance for the full command list.</p>

<h3>SBUS Channel Values</h3>
<p>FrSky typically uses 172 (low) to 1811 (high), with 992 at center. Use min/max to define a range that triggers the command (e.g. min=1700, max=1811 for "stick high").</p>

<h3>Raspberry Pi UART</h3>
<p>To use built-in UART on Pi 3/4:</p>
<ul>
<li>Disable Bluetooth: <code>sudo systemctl disable hciuart</code></li>
<li>Add <code>dtoverlay=disable-bt</code> to /boot/config.txt</li>
<li>Reboot and use /dev/ttyAMA0</li>
</ul>
</div>
