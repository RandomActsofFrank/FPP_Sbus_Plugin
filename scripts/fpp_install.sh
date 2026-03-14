#!/bin/bash
# FPP_Sbus_Plugin install script

. ${FPPDIR}/scripts/common

# Ensure python3 is available
if ! command -v python3 &> /dev/null; then
    echo "Warning: python3 not found. SBUS plugin requires Python 3."
fi

# Install pyserial (serial module) if not present
if command -v python3 &> /dev/null && ! python3 -c "import serial" 2>/dev/null; then
    echo "Installing pyserial (python3-serial) for SBUS daemon..."
    if command -v apt-get &> /dev/null; then
        if [ "$(id -u)" = "0" ]; then
            apt-get update -qq && apt-get install -y python3-serial 2>/dev/null || true
        else
            sudo apt-get update -qq && sudo apt-get install -y python3-serial 2>/dev/null || true
        fi
    fi
    if ! python3 -c "import serial" 2>/dev/null; then
        pip3 install --user pyserial 2>/dev/null || pip3 install pyserial 2>/dev/null || true
    fi
    if ! python3 -c "import serial" 2>/dev/null; then
        echo "Warning: Could not install pyserial. Install manually: sudo apt-get install python3-serial"
    else
        echo "pyserial installed."
    fi
fi

# Create default config if missing
PLUGINDIR="${FPPDIR}/plugins/FPP_Sbus_Plugin"
CONFIG="${PLUGINDIR}/sbus_config.json"
if [ ! -f "$CONFIG" ]; then
    echo '{"enabled":0,"serialPort":"/dev/ttyAMA0","baudRate":100000,"fppHost":"127.0.0.1","rules":[]}' > "$CONFIG"
fi

# Make scripts executable
chmod +x "${PLUGINDIR}/scripts/sbus_fpp_daemon.py" 2>/dev/null || true
chmod +x "${PLUGINDIR}/scripts/stop_daemon.sh" 2>/dev/null || true
chmod +x "${PLUGINDIR}/scripts/restart_daemon.sh" 2>/dev/null || true

echo "FrSky SBUS plugin installed. Configure via Plugin menu -> SBUS - Configuration (plugin dir: FPP_Sbus_Plugin)"
