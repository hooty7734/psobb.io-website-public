#!/bin/bash

# Setup permissions and upgrade database for psobb.io-website on a Linux VPS
# Must be run with sudo

# ============================================================================
# Configuration
# ============================================================================
WEB_USER="www-data"
WEB_GROUP="www-data"
# ============================================================================

if [ "$EUID" -ne 0 ]; then
  echo "Please run as root (e.g., sudo ./setup_permissions.sh)"
  exit 1
fi

echo "Ensuring necessary directories exist..."
mkdir -p uploads/mods
mkdir -p db

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

echo "Permissions setup and database upgrade complete!"
