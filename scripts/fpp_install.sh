#!/bin/bash
# FPP_Sbus_Plugin install script

. ${FPPDIR}/scripts/common

# Ensure python3 and pyserial are available
if ! command -v python3 &> /dev/null; then
    echo "Warning: python3 not found. SBUS plugin requires Python 3."
fi

# Create default config if missing
PLUGINDIR="${FPPDIR}/plugins/FPP_Sbus_Plugin"
CONFIG="${PLUGINDIR}/sbus_config.json"
if [ ! -f "$CONFIG" ]; then
    echo '{"enabled":0,"serialPort":"/dev/ttyAMA0","baudRate":100000,"fppHost":"127.0.0.1","rules":[]}' > "$CONFIG"
fi

# Make daemon executable
chmod +x "${PLUGINDIR}/scripts/sbus_fpp_daemon.py" 2>/dev/null || true

echo "FrSky SBUS plugin installed. Configure via Plugin menu -> SBUS - Configuration (plugin dir: FPP_Sbus_Plugin)"
