#!/bin/bash

# Setup permissions and upgrade database for psobb.io-website on a Linux VPS
# Also configures secure POSIX group read-access on the Newserv players directory.
# Must be run with sudo

# ============================================================================
# Configuration
# ============================================================================
WEB_USER="www-data"
WEB_GROUP="www-data"
PLAYERS_DIR="/opt/newserv/system/players"
# ============================================================================

set -e

if [ "$EUID" -ne 0 ]; then
  echo "Please run as root (e.g., sudo ./setup_permissions.sh)"
  exit 1
fi

echo "=== PSOBB.io Permissions Setup ==="

# ---- Website Directory Setup ----

echo "Ensuring necessary directories exist..."
mkdir -p uploads/mods
mkdir -p uploads/mod_images
mkdir -p db
mkdir -p scratch

echo "Setting global ownership to $WEB_USER:$WEB_GROUP..."
chown -R $WEB_USER:$WEB_GROUP .

echo "Applying database schema upgrades..."
if command -v php >/dev/null 2>&1; then
    # Run the DB upgrade script as the web user to ensure proper ownership of website.db
    sudo -u $WEB_USER php db/init_db.php
else
    echo "Warning: php command not found. Cannot run db/init_db.php automatically."
fi

echo "Setting default directory permissions to 755..."
find . -type d -exec chmod 755 {} \;

echo "Setting default file permissions to 644..."
find . -type f -exec chmod 644 {} \;

echo "Setting write permissions for uploads directories..."
chmod -R 775 uploads

echo "Setting write permissions for scratch (cache) directory..."
chmod 775 scratch

echo "Setting write permissions for SQLite database directory..."
# SQLite needs write access to the directory containing the database
chmod 775 db

if [ -f "db/website.db" ]; then
    echo "Setting write permissions for website.db..."
    chmod 664 db/website.db
fi

# Keep scripts executable
chmod 744 setup_permissions.sh
if [ -f "install-deck.sh" ]; then
    chmod 755 install-deck.sh
fi

# ---- Newserv Players Directory Setup ----

if [ -d "$PLAYERS_DIR" ]; then
    echo ""
    echo "--- Newserv Players Directory ---"
    echo "Target Directory: $PLAYERS_DIR"

    # Change group ownership of the players folder and files to www-data
    echo "Configuring ownership to root:$WEB_USER..."
    chown -R root:$WEB_USER "$PLAYERS_DIR"

    # Set directory permissions:
    # - Owner (root): read/write/execute (7)
    # - Group (www-data): read/execute (5)
    # - Others: none (0)
    # - SetGID bit (2) so any new file created inherits the group owner (www-data)
    echo "Applying setgid bit and directory permissions (2750)..."
    chmod 2750 "$PLAYERS_DIR"

    # Set file permissions for all existing character and bank files
    # - Owner (root): read/write (6)
    # - Group (www-data): read (4)
    # - Others: none (0)
    echo "Applying read-only permissions for existing player files (640)..."
    find "$PLAYERS_DIR" -type f -exec chmod 640 {} +
else
    echo ""
    echo "Note: Newserv players directory not found at $PLAYERS_DIR (skipping)."
fi

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
