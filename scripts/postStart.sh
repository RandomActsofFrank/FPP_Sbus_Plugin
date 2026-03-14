#!/bin/sh
# Start SBUS daemon when FPP starts

PLUGINDIR="${FPPDIR}/plugins/FPP_Sbus_Plugin"
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
