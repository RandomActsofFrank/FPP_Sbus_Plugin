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

if [ -x "$DAEMON" ] || [ -f "$DAEMON" ]; then
    nohup python3 "$DAEMON" >> "${PLUGINDIR}/sbus_daemon.log" 2>&1 </dev/null &
    echo $! > "$PIDFILE"
fi
