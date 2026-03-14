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
import os
import signal

try:
    import serial
except ImportError:
    print("Missing 'serial' module (pyserial). Install with:", file=sys.stderr)
    print("  sudo apt-get install python3-serial", file=sys.stderr)
    print("or: pip3 install pyserial", file=sys.stderr)
    sys.exit(1)

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


def write_status(status_file, parsed):
    """Write receiver status and channel data to JSON file for UI display."""
    try:
        data = {
            'last_packet': time.time(),
            'channels': parsed['channels'],
            'ch17': parsed['ch17'],
            'ch18': parsed['ch18'],
            'failsafe': parsed['failsafe'],
            'frame_lost': parsed['frame_lost'],
        }
        with open(status_file, 'w') as f:
            json.dump(data, f, indent=0)
    except Exception as e:
        print(f"Status write error: {e}", file=sys.stderr)


def write_no_signal_status(status_file):
    """Write minimal status when daemon is running but no SBUS packets received (so UI shows 'No signal')."""
    try:
        data = {
            'last_packet': 0.0,
            'channels': [0] * 16,
            'ch17': False,
            'ch18': False,
            'failsafe': False,
            'frame_lost': True,
        }
        with open(status_file, 'w') as f:
            json.dump(data, f, indent=0)
    except Exception as e:
        print(f"Status write error: {e}", file=sys.stderr)


def _fpp_start_url(host, command):
    """Build FPP REST URL for Start Playlist/Sequence/Effect/Media; else None (use /api/command/).
    FPP API accepts + for spaces in path (quote_plus)."""
    command = urllib.parse.unquote(command)
    if '/' not in command:
        return None
    parts = command.split('/', 1)
    ctype = (parts[0] or '').strip()
    name = (parts[1] or '').strip()
    if not name:
        return None
    base = f"http://{host}/api/"
    if ctype == 'Start Playlist':
        encoded = urllib.parse.quote_plus(name)
        return f"{base}playlist/{encoded}/start"
    if ctype == 'Start Sequence':
        seq_name = name
        if not seq_name.lower().endswith('.fseq'):
            seq_name += '.fseq'
        encoded = urllib.parse.quote_plus(seq_name)
        return f"{base}sequence/{encoded}/start"
    if ctype == 'Start Effect':
        encoded = urllib.parse.quote_plus(name)
        return f"{base}effect/{encoded}/start"
    if ctype == 'Start Media':
        # Media can be started via the same playlist start endpoint by passing the media filename.
        encoded = urllib.parse.quote_plus(name)
        return f"{base}playlist/{encoded}/start"
    return None


def call_fpp_api(host, command):
    """Send FPP API command via HTTP. Uses REST endpoints for Start Playlist/Sequence/Effect/Media."""
    url = _fpp_start_url(host, command)
    if url is None:
        # + for spaces, keep / unencoded for command path
        url = f"http://{host}/api/command/{urllib.parse.quote(command.replace(' ', '+'), safe='/')}"
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

    # Create default config if missing (same defaults as content.php) so daemon/config page work before first save
    if not os.path.exists(config_path):
        default_config = {
            'enabled': 0,
            'serialPort': '/dev/ttyAMA0',
            'baudRate': 100000,
            'fppHost': '127.0.0.1',
            'rules': []
        }
        try:
            with open(config_path, 'w') as f:
                json.dump(default_config, f, indent=2)
            print("Created default config at", config_path, file=sys.stderr)
        except Exception as e:
            print("Config not found and could not create default:", config_path, e, file=sys.stderr)
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

    status_file = os.path.join(plugin_dir, 'sbus_status.json')
    heartbeat_file = os.path.join(plugin_dir, 'sbus_heartbeat.json')
    pid_file = os.path.join(plugin_dir, 'sbus_daemon.pid')
    HEARTBEAT_INTERVAL = 15  # seconds
    last_heartbeat_time = 0.0
    CONNECTED_TIMEOUT = 0.5  # seconds without packet = disconnected

    # Same log path order as plugin_common.inc so heartbeat appears in fpp_sbus.log
    _log_dirs = ['/home/fpp/media/logs', '/home/fpp/logs', '/var/log/fpp', '/opt/fpp/logs', plugin_dir]
    _log_file = None
    for _d in _log_dirs:
        if os.path.isdir(_d) and os.access(_d, os.W_OK):
            _log_file = os.path.join(_d, 'fpp_sbus.log')
            break
    if not _log_file:
        _log_file = os.path.join(plugin_dir, 'fpp_sbus.log')

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

    try:
        with open(pid_file, 'w') as f:
            f.write(str(os.getpid()))
    except Exception as e:
        print(f"PID file write error: {e}", file=sys.stderr)

    def shutdown(signum=None, frame=None):
        try:
            ser.close()
        except Exception:
            pass
        try:
            if os.path.exists(pid_file):
                os.remove(pid_file)
        except Exception:
            pass
        sys.exit(0)

    signal.signal(signal.SIGTERM, shutdown)
    signal.signal(signal.SIGINT, shutdown)

    buf = bytearray()
    last_packet_time = 0.0  # when we last got a valid SBUS packet (0 = none yet)
    NO_SIGNAL_THRESHOLD = 30  # seconds without packet -> log "no SBUS signal" and write minimal status
    print(f"SBUS daemon started on {port}, FPP host {fpp_host}", file=sys.stderr)

    def write_heartbeat(no_sbus_signal=False):
        try:
            with open(heartbeat_file, 'w') as f:
                json.dump({'last_heartbeat': time.time(), 'pid': os.getpid()}, f)
        except Exception as e:
            print(f"Heartbeat write error: {e}", file=sys.stderr)
        try:
            with open(_log_file, 'a') as f:
                if no_sbus_signal:
                    f.write(time.strftime('%Y-%m-%d %H:%M:%S', time.localtime()) + ' sbus daemon heartbeat (no SBUS signal)\n')
                else:
                    f.write(time.strftime('%Y-%m-%d %H:%M:%S', time.localtime()) + ' sbus daemon heartbeat\n')
        except Exception:
            pass

    while True:
        try:
            now = time.time()
            if now - last_heartbeat_time >= HEARTBEAT_INTERVAL:
                last_heartbeat_time = now
                no_signal = (last_packet_time == 0.0) or (now - last_packet_time >= NO_SIGNAL_THRESHOLD)
                write_heartbeat(no_sbus_signal=no_signal)
                if no_signal:
                    write_no_signal_status(status_file)
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
                    if parsed:
                        last_packet_time = time.time()
                        write_status(status_file, parsed)
                        if not parsed['failsafe'] and rules:
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
                                        call_fpp_api(fpp_host, cmd)
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
