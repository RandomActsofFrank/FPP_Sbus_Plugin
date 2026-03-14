#!/bin/sh
# Stop and start SBUS daemon (callable from config page)
# If systemd service fpp-sbus-plugin.service is installed, use it; otherwise start daemon manually.
# Uses full paths for systemctl/id so it works when run from PHP (minimal PATH).

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGINDIR="$(dirname "$SCRIPT_DIR")"
DAEMON="${PLUGINDIR}/scripts/sbus_fpp_daemon.py"
PIDFILE="${PLUGINDIR}/sbus_daemon.pid"
CONFIG="${PLUGINDIR}/sbus_config.json"
SYSTEMCTL="/usr/bin/systemctl"
ID="/usr/bin/id"
[ ! -x "$SYSTEMCTL" ] && SYSTEMCTL="systemctl"
[ ! -x "$ID" ] && ID="id"

# If systemd manages the daemon, restart via systemd (avoids double-start)
if [ -f /etc/systemd/system/fpp-sbus-plugin.service ]; then
    if [ "$($ID -u 2>/dev/null)" = "0" ]; then
        $SYSTEMCTL restart fpp-sbus-plugin.service 2>&1 && echo "Daemon restarted (systemd)" && exit 0
    else
        sudo $SYSTEMCTL restart fpp-sbus-plugin.service 2>&1 && echo "Daemon restarted (systemd)" && exit 0
    fi
    echo "systemctl restart failed; starting daemon manually."
fi

# Stop if running (manual or legacy); remove PID file so daemon can create it (and so fpp can if it was root-owned)
if [ -f "$PIDFILE" ]; then
    PID=$(cat "$PIDFILE" 2>/dev/null)
    [ -n "$PID" ] && kill "$PID" 2>/dev/null
fi
rm -f "$PIDFILE" 2>/dev/null

# Start if enabled and config exists
if [ ! -f "$CONFIG" ]; then
    echo "Config not found, daemon not started"
    exit 0
fi

if ! grep -q '"enabled"\s*:\s*1' "$CONFIG" 2>/dev/null; then
    echo "Plugin disabled, daemon not started"
    exit 0
fi

if [ ! -f "$DAEMON" ]; then
    echo "Daemon script not found"
    exit 1
fi

# Must run as user fpp only (daemon exits if run as root)
if [ "$($ID -u 2>/dev/null)" = "0" ] && ! $ID fpp >/dev/null 2>&1; then
    echo "Cannot start daemon: user fpp does not exist. Install the systemd service (run plugin install) or run as user fpp."
    exit 1
fi
if $ID fpp >/dev/null 2>&1; then
    nohup sudo -u fpp python3 "$DAEMON" >> "${PLUGINDIR}/sbus_daemon.log" 2>&1 </dev/null &
else
    nohup python3 "$DAEMON" >> "${PLUGINDIR}/sbus_daemon.log" 2>&1 </dev/null &
fi
echo "Daemon restarted"
