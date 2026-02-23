#!/bin/sh
# Stop SBUS daemon when FPP stops

PLUGINDIR="${FPPDIR}/plugins/FPP_Sbus_Plugin"
PIDFILE="${PLUGINDIR}/sbus_daemon.pid"

if [ -f "$PIDFILE" ]; then
    kill $(cat "$PIDFILE") 2>/dev/null
    rm -f "$PIDFILE"
fi
