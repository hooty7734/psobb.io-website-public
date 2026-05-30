#!/bin/bash
# PSOBB.io Companion App - Secure Permissions Setup
# Configures secure POSIX group read-access on the Newserv players directory.
# Since Newserv runs as root, this script sets the group owner to www-data and 
# applies the setgid bit so any newly created files inherit the www-data group.

set -e

PLAYERS_DIR="/opt/newserv/system/players"
WEB_USER="www-data"

echo "=== PSOBB.io Permissions Setup ==="

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "ERROR: Please run this script as root (sudo ./setup_permissions.sh)."
    exit 1
fi

# Verify players directory exists
if [ ! -d "$PLAYERS_DIR" ]; then
    echo "ERROR: Newserv players directory not found at $PLAYERS_DIR."
    echo "Please ensure Newserv is installed and the players directory exists."
    exit 1
fi

echo "Target Directory: $PLAYERS_DIR"
echo "Web Server User:  $WEB_USER"

# 1. Change group ownership of the players folder and files to www-data
echo "Configuring ownership to root:$WEB_USER..."
chown -R root:$WEB_USER "$PLAYERS_DIR"

# 2. Set directory permissions: 
# - Owner (root): read/write/execute (7)
# - Group (www-data): read/execute (5)
# - Others: none (0)
# - SetGID bit (2) so any new file created in this directory inherits the group owner (www-data)
echo "Applying setgid bit and directory permissions (2750)..."
chmod 2750 "$PLAYERS_DIR"

# 3. Set file permissions for all existing character and bank files
# - Owner (root): read/write (6)
# - Group (www-data): read (4)
# - Others: none (0)
echo "Applying read-only permissions for existing player files (640)..."
find "$PLAYERS_DIR" -type f -exec chmod 640 {} +

echo ""
echo "=== Setup Completed Successfully! ==="
echo "PHP running under $WEB_USER can now safely read offline character profiles."
echo ""
echo "IMPORTANT FOR SYSTEMD SERVICE:"
echo "To ensure newly created files are group-readable, make sure Newserv's"
echo "process umask is set to 027 (so new files are written as 640 instead of 600)."
echo "If Newserv is managed via systemd, add this line to your service file under the [Service] section:"
echo "  UMask=027"
echo ""
