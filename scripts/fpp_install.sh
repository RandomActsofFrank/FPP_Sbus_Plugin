#!/bin/bash
# FPP_Sbus_Plugin install script (verbose for FPP install window)
# FPP runs this with FPPDIR=... SRCDIR=... as arguments; export them so we can use them.

for arg in "$@"; do
    case "$arg" in
        FPPDIR=*|SRCDIR=*) export "$arg" ;;
    esac
done

echo "=== FrSky SBUS Plugin install ==="

if [ -z "$FPPDIR" ]; then
    echo "Warning: FPPDIR not set. Using default /home/fpp/media if it exists."
    [ -d /home/fpp/media ] && export FPPDIR=/home/fpp/media
    [ -d /opt/fpp ] && [ -z "$FPPDIR" ] && export FPPDIR=/opt/fpp
fi

if [ -n "$FPPDIR" ] && [ -f "${FPPDIR}/scripts/common" ]; then
    . "${FPPDIR}/scripts/common"
fi

if [ -z "$PLUGINDIR" ]; then
    if [ -n "$FPPDIR" ]; then
        PLUGINDIR="${FPPDIR}/plugins/FPP_Sbus_Plugin"
    else
        PLUGINDIR="$(cd "$(dirname "$0")/.." && pwd)"
    fi
fi
echo "Plugin directory: $PLUGINDIR"

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
# Use PLUGINDIR (set from FPPDIR or from this script's path) so we don't require FPPDIR.
echo "Installing systemd service (fpp-sbus-plugin.service)..."
FPP_SBUS_SERVICE_NAME="fpp-sbus-plugin.service"
FPP_SBUS_SERVICE_FILE="/etc/systemd/system/${FPP_SBUS_SERVICE_NAME}"
if [ -n "$PLUGINDIR" ] && [ -f "${PLUGINDIR}/scripts/sbus_fpp_daemon.py" ] && [ -d "$(dirname "$FPP_SBUS_SERVICE_FILE")" ]; then
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
    echo "  Skipped (PLUGINDIR=$PLUGINDIR; daemon script or /etc/systemd/system missing?)."
fi

echo "=== FrSky SBUS plugin install complete ==="
echo "Configure via Plugin menu -> SBUS - Configuration (plugin dir: FPP_Sbus_Plugin)"
