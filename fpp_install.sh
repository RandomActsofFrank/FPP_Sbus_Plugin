#!/bin/bash
# FPP looks for scripts/fpp_install.sh first, then this file. Delegate to scripts version.
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
exec "$SCRIPT_DIR/scripts/fpp_install.sh" "$@"
