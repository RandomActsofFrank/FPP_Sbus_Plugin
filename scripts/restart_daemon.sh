#!/bin/sh
# Stop and start SBUS daemon (callable from config page)
# If systemd service fpp-sbus-plugin.service is installed, use it; otherwise start daemon manually.

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGINDIR="$(dirname "$SCRIPT_DIR")"
DAEMON="${PLUGINDIR}/scripts/sbus_fpp_daemon.py"
PIDFILE="${PLUGINDIR}/sbus_daemon.pid"
CONFIG="${PLUGINDIR}/sbus_config.json"

# If systemd manages the daemon, restart via systemd (avoids double-start)
if command -v systemctl >/dev/null 2>&1; then
    if systemctl is-enabled fpp-sbus-plugin.service >/dev/null 2>&1 || [ -f /etc/systemd/system/fpp-sbus-plugin.service ]; then
        if [ "$(id -u)" = "0" ]; then
            systemctl restart fpp-sbus-plugin.service 2>/dev/null && echo "Daemon restarted (systemd)" && exit 0
        else
            sudo systemctl restart fpp-sbus-plugin.service 2>/dev/null && echo "Daemon restarted (systemd)" && exit 0
        fi
    fi
fi

# Stop if running (manual or legacy)
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
    nohup python3 "$DAEMON" >> "${PLUGINDIR}/sbus_daemon.log" 2>&1 </dev/null &
    echo $! > "$PIDFILE"
    echo "Daemon restarted"
else
    echo "Daemon script not found"
fi
