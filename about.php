<?php
/*
 * FrSky SBUS Plugin - About
 */
?>
<div class="container">
<h2>FrSky SBUS Plugin for FPP</h2>
<p>This plugin allows FPP (Falcon Player) to receive FrSky SBUS signals from RC transmitters and trigger effects, playlists, or other FPP commands based on servo channel values.</p>

<h3>Features</h3>
<ul>
<li>Read SBUS packets from Raspberry Pi serial port (UART)</li>
<li>Map any of 16 SBUS channels to FPP API commands</li>
<li>Trigger commands when channel value falls within a min-max range</li>
<li>Works with FrSky receivers (X8R, X4R, etc.) via SBUS output</li>
</ul>

<h3>Requirements</h3>
<ul>
<li>Raspberry Pi with serial port (ttyAMA0 or USB-serial adapter)</li>
<li><strong>SBUS connection</strong> – Use the receiver’s <strong>SBUS</strong> port (not S.Port). Either an inverter circuit between receiver and Pi, or uninverted SBUS from receivers that support it (e.g. X4R-SB, XSR). X8R needs an external inverter. See README.</li>
<li>Python 3 and pyserial</li>
</ul>

<h3>References</h3>
<ul>
<li><a href="https://github.com/RandomActsofFrank/fpp-plugin-Template" target="_blank">FPP Plugin Template</a></li>
<li><a href="https://github.com/Carbon225/raspberry-sbus" target="_blank">Carbon225/raspberry-sbus</a> – C++ SBUS library for Raspberry Pi</li>
<li><a href="https://github.com/1arthur1/PiSBUS" target="_blank">1arthur1/PiSBUS</a> – Arduino-style SBUS library for Raspberry Pi</li>
<li><a href="https://github.com/bolderflight/SBUS" target="_blank">bolderflight/SBUS</a> – Original SBUS protocol reference</li>
<li><a href="https://github.com/jcheger/arduino-frskysp" target="_blank">jcheger/arduino-frskysp</a> – FrSky Smart Port protocol (sensor/telemetry only; does not carry RC channels)</li>
</ul>
</div>
