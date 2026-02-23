#!/bin/bash
# FPP_Sbus_Plugin uninstall script

# Stop daemon if running
PLUGINDIR="${FPPDIR}/plugins/FPP_Sbus_Plugin"
PIDFILE="${PLUGINDIR}/sbus_daemon.pid"
if [ -f "$PIDFILE" ]; then
    kill $(cat "$PIDFILE") 2>/dev/null
    rm -f "$PIDFILE"
fi

echo "FrSky SBUS plugin (FPP_Sbus_Plugin) uninstalled."
