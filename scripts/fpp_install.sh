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

# Make scripts executable (including postStart so FPP can run it when fppd starts)
chmod +x "${PLUGINDIR}/scripts/sbus_fpp_daemon.py" 2>/dev/null || true
chmod +x "${PLUGINDIR}/scripts/stop_daemon.sh" 2>/dev/null || true
chmod +x "${PLUGINDIR}/scripts/restart_daemon.sh" 2>/dev/null || true
chmod +x "${PLUGINDIR}/scripts/postStart.sh" 2>/dev/null || true
chmod +x "${PLUGINDIR}/scripts/postStop.sh" 2>/dev/null || true
chmod +x "${PLUGINDIR}/scripts/preStart.sh" 2>/dev/null || true
chmod +x "${PLUGINDIR}/scripts/preStop.sh" 2>/dev/null || true

# Install systemd unit: run SBUS daemon as a service so it starts at boot (after fppd).
# Runs the Python daemon directly; it exits if plugin is disabled in config.
FPP_SBUS_SERVICE_NAME="fpp-sbus-plugin.service"
FPP_SBUS_SERVICE_FILE="/etc/systemd/system/${FPP_SBUS_SERVICE_NAME}"
if [ -n "$FPPDIR" ] && [ -d "$(dirname "$FPP_SBUS_SERVICE_FILE")" ] && [ -f "${PLUGINDIR}/scripts/sbus_fpp_daemon.py" ]; then
    PYTHON3="$(command -v python3 2>/dev/null || echo '/usr/bin/python3')"
    cat << EOF > "/tmp/${FPP_SBUS_SERVICE_NAME}.tmp"
[Unit]
Description=FPP SBUS plugin daemon
After=network.target fppd.service

[Service]
Type=simple
User=fpp
WorkingDirectory=${PLUGINDIR}
ExecStart=${PYTHON3} ${PLUGINDIR}/scripts/sbus_fpp_daemon.py
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF
    if [ "$(id -u)" = "0" ]; then
        mv "/tmp/${FPP_SBUS_SERVICE_NAME}.tmp" "$FPP_SBUS_SERVICE_FILE" 2>/dev/null && systemctl daemon-reload && systemctl enable "$FPP_SBUS_SERVICE_NAME" 2>/dev/null && echo "Systemd unit ${FPP_SBUS_SERVICE_NAME} installed and enabled." || true
    else
        sudo mv "/tmp/${FPP_SBUS_SERVICE_NAME}.tmp" "$FPP_SBUS_SERVICE_FILE" 2>/dev/null && sudo systemctl daemon-reload && sudo systemctl enable "$FPP_SBUS_SERVICE_NAME" 2>/dev/null && echo "Systemd unit ${FPP_SBUS_SERVICE_NAME} installed and enabled." || true
    fi
    rm -f "/tmp/${FPP_SBUS_SERVICE_NAME}.tmp" 2>/dev/null
fi

echo "FrSky SBUS plugin installed. Configure via Plugin menu -> SBUS - Configuration (plugin dir: FPP_Sbus_Plugin)"
