# FPP FrSky SBUS Plugin

Allows FPP (Falcon Player) to receive FrSky SBUS signals from RC transmitters and trigger effects, playlists, or other FPP API commands based on servo channel values.

## Features

- Read SBUS packets from Raspberry Pi serial port (UART)
- Map any of 16 SBUS channels to FPP commands
- Trigger commands when channel value falls within a configured min-max range
- Works with FrSky receivers (X8R, X4R, etc.) via SBUS output

## Requirements

- **Raspberry Pi** with serial port (built-in ttyAMA0 or USB-serial adapter)
- **SBUS inverter circuit** – FrSky SBUS uses inverted serial logic; you need an inverter between the receiver and Pi
- Python 3 and pyserial (`python3-serial` package)

## Wiring & Hardware

### Use the SBUS port, not S.Port (Smart Port)

This plugin needs **RC channel data** (the 16 servo values). On FrSky receivers:

- **SBUS** – Carries the 16 channels. Use the **SBUS** output on the receiver for this plugin.
- **S.Port (Smart Port)** – Cannot be used for this plugin. Smart Port is a **sensor/telemetry protocol**: the receiver polls up to 28 physical sensor IDs (e.g. altimeter, voltage, GPS, RPM, RSSI), and devices respond with telemetry values. It does **not** carry the 16 RC channel values. See the [FrSky Smart Port protocol (arduino-frskysp)](https://github.com/jcheger/arduino-frskysp) docs and [sensor ID list](https://www.ordinoscope.net/static/arduino-frskysp/docs/html/) — only telemetry logical IDs (altitude, VFAS, current, cells, temperature, RPM, GPS, etc.) are defined; there are no “channel 1–16” IDs. So S.Port cannot replace SBUS for FPP. Both SBUS and S.Port outputs on FrSky are **inverted** at the connector (S.Port is inverted serial at 57600 baud), so using S.Port would not remove the need for inversion anyway.

### SBUS signal inversion and how to avoid an external inverter

SBUS uses **inverted** UART logic (idle high). Standard Raspberry Pi UART expects non-inverted (idle low), so you need either an inverter between the receiver and the Pi or an **uninverted** SBUS signal.

**Option A – External inverter circuit** (works with any receiver, including X8R)

- Simple transistor + resistors between the receiver **SBUS** pin and the Pi RX pin.
- 10kΩ from receiver SBUS → transistor base; collector → Pi RX; 10kΩ pull-up from Pi RX to 3.3V; emitter to GND.
- References: [Carbon225/raspberry-sbus](https://github.com/Carbon225/raspberry-sbus), [PiSBUS](https://github.com/1arthur1/PiSBUS).

**Option B – Uninverted SBUS from the receiver (no inverter needed)**

- On some FrSky receivers you can get **uninverted** SBUS by soldering to a pad or pin on the PCB (before the inverter stage). Then connect that wire directly to the Pi RX pin—no inverter circuit needed.
- **X4R-SB, XSR, R-XSR**: Uninverted SBUS is available at a documented solder point (e.g. pin “A” / middle of a 3-pin group). See [Oscar Liang: Uninverted SBUS and Smart Port on FrSky receivers](https://oscarliang.com/uninverted-sbus-smart-port-frsky-receivers/).
- **X8R**: The board uses two stacked PCBs and the inverter is hard to access, so the solder-tap method is not practical. For X8R, use **Option A** (external inverter) with the SBUS port.

### Raspberry Pi UART Setup

To use the built-in UART on Pi 3/4 (often used by Bluetooth):

1. Disable Bluetooth: `sudo systemctl disable hciuart`
2. Add `dtoverlay=disable-bt` to `/boot/config.txt`
3. Reboot
4. Use `/dev/ttyAMA0` in the plugin config

For Pi 4/5 with additional UARTs, add `dtoverlay=uart2` (or uart3, uart4, uart5) to `/boot/config.txt`.

## Installation

### Why "Failed to fetch" / "could not read Username" happens

FPP's Plugin Manager uses a **central plugin list** ([fpp-pluginList](https://github.com/FalconChristmas/fpp-pluginList)). It looks up each plugin there, fetches its `pluginInfo.json`, and uses the **srcURL** from that file to clone. If **FPP_Sbus_Plugin** is not in that list (or is listed with the wrong URL), FPP may try to clone from the wrong repo and you get a fetch error—so the problem is **plugin list configuration**, not necessarily your network. See [PLUGIN_LIST_ENTRY.md](PLUGIN_LIST_ENTRY.md) for the exact entry to add to the list and how to install manually.

### Manual install (use this if “Failed to fetch” from Plugin Manager)

The Plugin Manager installs by cloning from GitHub. If that fails (e.g. repo not created yet or no network access), install manually:

1. **Copy the plugin to your FPP device**
   - Copy the entire `FPP_Sbus_Plugin` folder to the FPP plugins directory on your Pi.
   - Default path: `/home/fpp/media/plugins/`
   - Example from your computer (replace with your Pi’s IP/hostname):
     ```bash
     scp -r FPP_Sbus_Plugin fpp@YOUR_FPP_IP:/home/fpp/media/plugins/
     ```
   - Or use Samba/USB/other file copy so the folder ends up at `.../plugins/FPP_Sbus_Plugin/`.

2. **Run the install script on the FPP device**
   - SSH into the Pi: `ssh fpp@YOUR_FPP_IP`
   - Then:
     ```bash
     cd /home/fpp/media/plugins/FPP_Sbus_Plugin/scripts
     sudo ${FPPDIR}/scripts/install_plugin.sh FPP_Sbus_Plugin
     ```
   - Or: in the FPP web UI go to **Plugin Manager**, find **FrSky SBUS** in the list, and use **Install from Directory** if your FPP version supports it.

3. **Configure** via **Plugin menu → SBUS - Configuration**.

### Install from Plugin Manager (after repo is on GitHub)

1. Ensure the repo exists at https://github.com/RandomActsofFrank/FPP_Sbus_Plugin and is **public**.
2. Add the repo to FPP’s plugin list (or use “Install from Git URL” if available).
3. Install **FrSky SBUS**, then configure as above.

## Configuration

- **Enable SBUS** – Turn the daemon on/off
- **Serial Port** – e.g. `/dev/ttyAMA0` (built-in) or `/dev/ttyUSB0` (USB-serial)
- **Baud Rate** – 100000 (SBUS standard)
- **FPP Host** – `127.0.0.1` for local FPP

### Channel Rules

Each rule defines:
- **Channel** (1–16)
- **Min Value** / **Max Value** – SBUS range (FrSky typically 172–1811; center ~992)
- **FPP Command** – API command, e.g. `Start Playlist/My Playlist` or `Start Effect/Fire`

When the channel value is within min–max, the command is sent (with a short cooldown to avoid spam).

### Example Commands

- `Start Playlist/Show Time`
- `Start Effect/Fire`
- `Stop Playlist`
- `Volume Set/70`

See your FPP instance's `/apihelp.php` for the full command list.

## References

- [FPP Plugin Template](https://github.com/RandomActsofFrank/fpp-plugin-Template)
- [Carbon225/raspberry-sbus](https://github.com/Carbon225/raspberry-sbus) – C++ SBUS library for Raspberry Pi
- [1arthur1/PiSBUS](https://github.com/1arthur1/PiSBUS) – Arduino-style SBUS library for Raspberry Pi
- [bolderflight/SBUS](https://github.com/bolderflight/SBUS) – Original SBUS protocol reference
