#!/bin/sh
# Start SBUS daemon when FPP starts (or run this script manually / from cron @reboot)
# Plugin dir is derived from this script's path so it works even when FPPDIR is not set.

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGINDIR="$(dirname "$SCRIPT_DIR")"
DAEMON="${PLUGINDIR}/scripts/sbus_fpp_daemon.py"
PIDFILE="${PLUGINDIR}/sbus_daemon.pid"
CONFIG="${PLUGINDIR}/sbus_config.json"

# Check if enabled and config exists
if [ ! -f "$CONFIG" ]; then
    exit 0
fi

# Quick JSON check for enabled
if ! grep -q '"enabled"\s*:\s*1' "$CONFIG" 2>/dev/null; then
    exit 0
fi

# If systemd unit is installed, do NOT start here - the unit starts at boot (After=fppd).
# Starting here would cause two instances: postStart + systemd both start the daemon.
if [ -f /etc/systemd/system/fpp-sbus-plugin.service ]; then
    exit 0
fi
# No systemd unit: start daemon manually (e.g. plugin installed but install script didn't add the unit)
# Must run as user fpp only (daemon refuses to run as root)
if [ -x "$DAEMON" ] || [ -f "$DAEMON" ]; then
    if [ "$(id -u)" = "0" ] && ! id fpp >/dev/null 2>&1; then
        : # root and no fpp user: skip start (daemon would exit anyway)
    elif id fpp >/dev/null 2>&1; then
        nohup sudo -u fpp python3 "$DAEMON" >> "${PLUGINDIR}/sbus_daemon.log" 2>&1 </dev/null &
    else
        nohup python3 "$DAEMON" >> "${PLUGINDIR}/sbus_daemon.log" 2>&1 </dev/null &
    fi
fi
