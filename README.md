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

### SBUS Signal Inversion

SBUS uses **inverted** UART logic (idle high). Standard UART is idle low. You need an inverter between the FrSky receiver SBUS output and the Raspberry Pi RX pin.

**Simple inverter circuit** (transistor + 2 resistors):
- 10kΩ from receiver SBUS → transistor base
- Transistor collector → Pi RX (GPIO15 / ttyAMA0)
- 10kΩ pull-up from Pi RX to 3.3V
- Emitter to GND

References: [Carbon225/raspberry-sbus](https://github.com/Carbon225/raspberry-sbus), [PiSBUS](https://github.com/1arthur1/PiSBUS).

### Raspberry Pi UART Setup

To use the built-in UART on Pi 3/4 (often used by Bluetooth):

1. Disable Bluetooth: `sudo systemctl disable hciuart`
2. Add `dtoverlay=disable-bt` to `/boot/config.txt`
3. Reboot
4. Use `/dev/ttyAMA0` in the plugin config

For Pi 4/5 with additional UARTs, add `dtoverlay=uart2` (or uart3, uart4, uart5) to `/boot/config.txt`.

## Installation

1. Install the plugin via FPP's Plugin Manager or copy the `fpp-plugin-SBUS` folder to `${FPPDIR}/plugins/`
2. Run the plugin install (or enable via Plugin Manager)
3. Configure via **Plugin menu → SBUS - Configuration**

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

- [FPP Plugin Template](https://github.com/FalconChristmas/fpp-plugin-Template)
- [Carbon225/raspberry-sbus](https://github.com/Carbon225/raspberry-sbus) – C++ SBUS library for Raspberry Pi
- [1arthur1/PiSBUS](https://github.com/1arthur1/PiSBUS) – Arduino-style SBUS library for Raspberry Pi
- [bolderflight/SBUS](https://github.com/bolderflight/SBUS) – Original SBUS protocol reference
