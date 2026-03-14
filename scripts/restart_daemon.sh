#!/bin/sh
# Stop and start SBUS daemon (callable from config page)

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGINDIR="$(dirname "$SCRIPT_DIR")"
DAEMON="${PLUGINDIR}/scripts/sbus_fpp_daemon.py"
PIDFILE="${PLUGINDIR}/sbus_daemon.pid"
CONFIG="${PLUGINDIR}/sbus_config.json"

# Stop if running
if [ -f "$PIDFILE" ]; then
    PID=$(cat "$PIDFILE" 2>/dev/null)
    [ -n "$PID" ] && kill "$PID" 2>/dev/null
    rm -f "$PIDFILE"
fi

# Start if enabled and config exists
if [ ! -f "$CONFIG" ]; then
    echo "Config not found, daemon not started"
    exit 0
fi

if ! grep -q '"enabled"\s*:\s*1' "$CONFIG" 2>/dev/null; then
    echo "Plugin disabled, daemon not started"
    exit 0
fi

if [ -f "$DAEMON" ]; then
    python3 "$DAEMON" >> "${PLUGINDIR}/sbus_daemon.log" 2>&1 &
    echo $! > "$PIDFILE"
    echo "Daemon restarted"
else
    echo "Daemon script not found"
fi
