#!/bin/bash
# FPP_Sbus_Plugin uninstall script

# Stop daemon if running
PLUGINDIR="${FPPDIR}/plugins/FPP_Sbus_Plugin"
PIDFILE="${PLUGINDIR}/sbus_daemon.pid"
if [ -f "$PIDFILE" ]; then
    kill $(cat "$PIDFILE") 2>/dev/null
    rm -f "$PIDFILE"
fi

# Remove systemd unit if we installed it
FPP_SBUS_SERVICE_NAME="fpp-sbus-plugin.service"
if [ -f "/etc/systemd/system/${FPP_SBUS_SERVICE_NAME}" ]; then
    if [ "$(id -u)" = "0" ]; then
        systemctl disable "$FPP_SBUS_SERVICE_NAME" 2>/dev/null || true
        rm -f "/etc/systemd/system/${FPP_SBUS_SERVICE_NAME}"
        systemctl daemon-reload 2>/dev/null || true
    else
        sudo systemctl disable "$FPP_SBUS_SERVICE_NAME" 2>/dev/null || true
        sudo rm -f "/etc/systemd/system/${FPP_SBUS_SERVICE_NAME}"
        sudo systemctl daemon-reload 2>/dev/null || true
    fi
fi

echo "FrSky SBUS plugin (FPP_Sbus_Plugin) uninstalled."
