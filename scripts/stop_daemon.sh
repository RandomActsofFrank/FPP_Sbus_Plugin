#!/bin/sh
# Stop SBUS daemon (callable from config page)

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGINDIR="$(dirname "$SCRIPT_DIR")"
PIDFILE="${PLUGINDIR}/sbus_daemon.pid"

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
