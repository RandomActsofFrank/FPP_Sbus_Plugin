#!/bin/bash
# fpp-plugin-SBUS uninstall script

# Stop daemon if running
PLUGINDIR="${FPPDIR}/plugins/fpp-plugin-SBUS"
PIDFILE="${PLUGINDIR}/sbus_daemon.pid"
if [ -f "$PIDFILE" ]; then
    kill $(cat "$PIDFILE") 2>/dev/null
    rm -f "$PIDFILE"
fi

echo "FrSky SBUS plugin uninstalled."
