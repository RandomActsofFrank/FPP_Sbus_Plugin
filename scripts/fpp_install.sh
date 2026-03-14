#!/bin/bash
# FPP_Sbus_Plugin install script (verbose for FPP install window)

echo "=== FrSky SBUS Plugin install ==="

. ${FPPDIR}/scripts/common

echo "Plugin directory: ${FPPDIR}/plugins/FPP_Sbus_Plugin"

# Ensure python3 is available
echo "Checking for python3..."
if ! command -v python3 &> /dev/null; then
    echo "Warning: python3 not found. SBUS plugin requires Python 3."
else
    echo "  python3 found: $(command -v python3)"
fi

# Install pyserial (serial module) if not present
echo "Checking for pyserial (serial module)..."
if command -v python3 &> /dev/null && ! python3 -c "import serial" 2>/dev/null; then
    echo "  pyserial not found. Installing..."
    if command -v apt-get &> /dev/null; then
        echo "  Running apt-get install python3-serial..."
        if [ "$(id -u)" = "0" ]; then
            apt-get update -qq && apt-get install -y python3-serial 2>/dev/null || true
        else
            sudo apt-get update -qq && sudo apt-get install -y python3-serial 2>/dev/null || true
        fi
    fi
    if ! python3 -c "import serial" 2>/dev/null; then
        echo "  Trying pip3 install pyserial..."
        pip3 install --user pyserial 2>/dev/null || pip3 install pyserial 2>/dev/null || true
    fi
    if ! python3 -c "import serial" 2>/dev/null; then
        echo "Warning: Could not install pyserial. Install manually: sudo apt-get install python3-serial"
    else
        echo "  pyserial installed OK."
    fi
else
    echo "  pyserial already installed."
fi

# Create default config if missing
PLUGINDIR="${FPPDIR}/plugins/FPP_Sbus_Plugin"
CONFIG="${PLUGINDIR}/sbus_config.json"
echo "Checking config file..."
if [ ! -f "$CONFIG" ]; then
    echo '{"enabled":0,"serialPort":"/dev/ttyAMA0","baudRate":100000,"fppHost":"127.0.0.1","rules":[]}' > "$CONFIG"
    echo "  Created default config at $CONFIG"
else
    echo "  Config already exists."
fi

# Make scripts executable (including postStart so FPP can run it when fppd starts)
echo "Making scripts executable..."
for script in sbus_fpp_daemon.py stop_daemon.sh restart_daemon.sh postStart.sh postStop.sh preStart.sh preStop.sh; do
    [ -f "${PLUGINDIR}/scripts/${script}" ] && chmod +x "${PLUGINDIR}/scripts/${script}" 2>/dev/null && echo "  ${script}" || true
done
echo "  Done."

# Install systemd unit: run SBUS daemon as a service so it starts at boot (after fppd).
echo "Installing systemd service (fpp-sbus-plugin.service)..."
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
        if mv "/tmp/${FPP_SBUS_SERVICE_NAME}.tmp" "$FPP_SBUS_SERVICE_FILE" 2>/dev/null && systemctl daemon-reload && systemctl enable "$FPP_SBUS_SERVICE_NAME" 2>/dev/null; then
            echo "  Service installed and enabled for boot."
        else
            echo "  Service install failed or not enabled."
        fi
    else
        if sudo mv "/tmp/${FPP_SBUS_SERVICE_NAME}.tmp" "$FPP_SBUS_SERVICE_FILE" 2>/dev/null && sudo systemctl daemon-reload && sudo systemctl enable "$FPP_SBUS_SERVICE_NAME" 2>/dev/null; then
            echo "  Service installed and enabled for boot."
        else
            echo "  Service install failed or not enabled (may need sudo)."
        fi
    fi
    rm -f "/tmp/${FPP_SBUS_SERVICE_NAME}.tmp" 2>/dev/null
else
    echo "  Skipped (FPPDIR not set or systemd dir not writable)."
fi

echo "=== FrSky SBUS plugin install complete ==="
echo "Configure via Plugin menu -> SBUS - Configuration (plugin dir: FPP_Sbus_Plugin)"
