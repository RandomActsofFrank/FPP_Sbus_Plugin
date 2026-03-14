#!/bin/sh
# Stop SBUS daemon (callable from config page)
# If systemd service is running, stop it first so it does not restart.

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGINDIR="$(dirname "$SCRIPT_DIR")"
PIDFILE="${PLUGINDIR}/sbus_daemon.pid"

if command -v systemctl >/dev/null 2>&1 && [ -f /etc/systemd/system/fpp-sbus-plugin.service ]; then
    if [ "$(id -u)" = "0" ]; then
        systemctl stop fpp-sbus-plugin.service 2>/dev/null
    else
        sudo systemctl stop fpp-sbus-plugin.service 2>/dev/null
    fi
    rm -f "$PIDFILE"
    echo "Daemon stopped"
    exit 0
fi

if [ -f "$PIDFILE" ]; then
    PID=$(cat "$PIDFILE" 2>/dev/null)
    if [ -n "$PID" ] && kill -0 "$PID" 2>/dev/null; then
        kill "$PID" 2>/dev/null
        rm -f "$PIDFILE"
        echo "Daemon stopped (PID $PID)"
    else
        rm -f "$PIDFILE"
        echo "PID file stale, cleaned up"
    fi
else
    echo "Daemon not running"
fi
