#!/usr/bin/env python3
"""
FrSky SBUS to FPP Daemon
Reads SBUS packets from serial port, parses channel values,
and triggers FPP API commands when servo values match configured rules.

SBUS protocol: 25 bytes, 100000 baud, 8E2
- Byte 0: 0x0F header
- Bytes 1-22: 16 channels, 11 bits each
- Byte 23: ch17, ch18, frame_lost, failsafe
- Byte 24: 0x00 end byte

Based on protocol from: https://github.com/bolderflight/SBUS
"""

import sys
import json
import time
import urllib.request
import urllib.error
import urllib.parse
import serial
import os
import signal

SBUS_HEADER = 0x0F
SBUS_FOOTER = 0x00
SBUS_PACKET_SIZE = 25
SBUS_BAUD = 100000
SBUS_NUM_CHANNELS = 16

# FrSky typical range
SBUS_MIN = 172
SBUS_MAX = 1811
SBUS_MID = 992


def parse_sbus_packet(data):
    """Parse 25-byte SBUS packet into channel values and flags."""
    if len(data) != SBUS_PACKET_SIZE or data[0] != SBUS_HEADER or data[24] != SBUS_FOOTER:
        return None

    channels = [0] * SBUS_NUM_CHANNELS
    # 16 channels * 11 bits = 176 bits = 22 bytes
    channels[0]  = ((data[1]      | data[2]  << 8)                     & 0x07FF)
    channels[1]  = ((data[2]  >> 3 | data[3]  << 5)                    & 0x07FF)
    channels[2]  = ((data[3]  >> 6 | data[4]  << 2 | data[5] << 10)    & 0x07FF)
    channels[3]  = ((data[5]  >> 1 | data[6]  << 7)                    & 0x07FF)
    channels[4]  = ((data[6]  >> 4 | data[7]  << 4)                    & 0x07FF)
    channels[5]  = ((data[7]  >> 7 | data[8]  << 1 | data[9] << 9)     & 0x07FF)
    channels[6]  = ((data[9]  >> 2 | data[10] << 6)                    & 0x07FF)
    channels[7]  = ((data[10] >> 5 | data[11] << 3)                    & 0x07FF)
    channels[8]  = ((data[11] >> 8 | data[12] << 0)                    & 0x07FF)
    channels[9]  = ((data[12] >> 1 | data[13] << 7)                    & 0x07FF)
    channels[10] = ((data[13] >> 4 | data[14] << 4)                    & 0x07FF)
    channels[11] = ((data[14] >> 7 | data[15] << 1 | data[16] << 9)    & 0x07FF)
    channels[12] = ((data[16] >> 2 | data[17] << 6)                    & 0x07FF)
    channels[13] = ((data[17] >> 5 | data[18] << 3)                    & 0x07FF)
    channels[14] = ((data[18] >> 8 | data[19] << 0)                    & 0x07FF)
    channels[15] = ((data[19] >> 1 | data[20] << 7)                    & 0x07FF)

    byte23 = data[23]
    ch17 = bool(byte23 & 0x80)
    ch18 = bool(byte23 & 0x40)
    frame_lost = bool(byte23 & 0x20)
    failsafe = bool(byte23 & 0x10)

    return {
        'channels': channels,
        'ch17': ch17, 'ch18': ch18,
        'frame_lost': frame_lost, 'failsafe': failsafe
    }


def call_fpp_api(host, command):
    """Send FPP API command via HTTP."""
    url = f"http://{host}/api/command/{command}"
    try:
        req = urllib.request.Request(url)
        with urllib.request.urlopen(req, timeout=5) as resp:
            return resp.status == 200
    except Exception as e:
        print(f"FPP API error: {e}", file=sys.stderr)
        return False


def main():
    plugin_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    config_path = os.path.join(plugin_dir, 'sbus_config.json')

    if not os.path.exists(config_path):
        print("Config not found:", config_path, file=sys.stderr)
        sys.exit(1)

    with open(config_path, 'r') as f:
        config = json.load(f)

    if not config.get('enabled'):
        print("SBUS plugin is disabled in config.")
        sys.exit(0)

    port = config.get('serialPort', '/dev/ttyAMA0')
    baud = config.get('baudRate', SBUS_BAUD)
    fpp_host = config.get('fppHost', '127.0.0.1')
    rules = config.get('rules', [])

    if not rules:
        print("No channel rules configured.")
        sys.exit(0)

    # Track last triggered rule to avoid spamming
    last_triggered = {}  # rule_idx -> last trigger time
    trigger_cooldown = 0.5  # seconds

    try:
        ser = serial.Serial(
            port=port,
            baudrate=baud,
            bytesize=serial.EIGHTBITS,
            parity=serial.PARITY_EVEN,
            stopbits=serial.STOPBITS_TWO,
            timeout=0.01
        )
    except Exception as e:
        print(f"Serial open error: {e}", file=sys.stderr)
        sys.exit(1)

    def shutdown(signum=None, frame=None):
        ser.close()
        sys.exit(0)

    signal.signal(signal.SIGTERM, shutdown)
    signal.signal(signal.SIGINT, shutdown)

    buf = bytearray()
    print(f"SBUS daemon started on {port}, FPP host {fpp_host}", file=sys.stderr)

    while True:
        try:
            chunk = ser.read(256)
            if chunk:
                buf.extend(chunk)
                while len(buf) >= SBUS_PACKET_SIZE:
                    # Find header
                    idx = buf.find(bytes([SBUS_HEADER]))
                    if idx < 0:
                        buf.clear()
                        break
                    buf = buf[idx:]
                    if len(buf) < SBUS_PACKET_SIZE:
                        break
                    packet = bytes(buf[:SBUS_PACKET_SIZE])
                    buf = buf[SBUS_PACKET_SIZE:]
                    parsed = parse_sbus_packet(packet)
                    if parsed and not parsed['failsafe']:
                        now = time.time()
                        for ri, rule in enumerate(rules):
                            ch = int(rule.get('channel', 1)) - 1
                            if ch < 0 or ch >= SBUS_NUM_CHANNELS:
                                continue
                            val = parsed['channels'][ch]
                            minv = int(rule.get('minVal', SBUS_MIN))
                            maxv = int(rule.get('maxVal', SBUS_MAX))
                            cmd = rule.get('command', '').strip()
                            if not cmd:
                                continue
                            if minv <= val <= maxv:
                                key = ri
                                if key not in last_triggered or (now - last_triggered[key]) >= trigger_cooldown:
                                    last_triggered[key] = now
                                    # URL-encode command (spaces -> %20, slashes preserved)
                                    cmd_encoded = urllib.parse.quote(cmd, safe='/')
                                    call_fpp_api(fpp_host, cmd_encoded)
            else:
                time.sleep(0.001)
        except serial.SerialException as e:
            print(f"Serial error: {e}", file=sys.stderr)
            time.sleep(1)
        except Exception as e:
            print(f"Error: {e}", file=sys.stderr)
            time.sleep(0.1)


if __name__ == '__main__':
    main()
