#!/bin/bash

# ============================================================================
# PSOBB Website — Server Setup Script
# ============================================================================
# Configures file permissions, initializes the database, and installs cron jobs.
# Must be run as root from the project directory.
#
# Usage:
#   cd /var/www/html/psobb-website
#   sudo ./setup_permissions.sh
# ============================================================================

WEB_USER="www-data"
WEB_GROUP="www-data"

# ============================================================================
# 1. Pre-flight checks
# ============================================================================

if [ "$EUID" -ne 0 ]; then
    echo "Error: Please run as root (e.g., sudo ./setup_permissions.sh)"
    exit 1
fi

echo ""
echo "=== PSOBB Website Setup ==="
echo ""

# ============================================================================
# 1.5 PHP module check
# ============================================================================

if command -v php >/dev/null 2>&1; then
    echo "[0/7] Checking required PHP modules..."
    REQUIRED_MODULES="sqlite3 curl json mbstring gd"
    MISSING=""
    for mod in $REQUIRED_MODULES; do
        if ! php -m 2>/dev/null | grep -qi "^${mod}$"; then
            MISSING="$MISSING $mod"
        fi
    done
    if [ -n "$MISSING" ]; then
        echo "  !! Missing PHP modules:$MISSING"
        echo "  !! Install with: sudo apt install$(echo $MISSING | sed 's/ / php-/g; s/^/ php-/')"
        echo "  !! Continuing setup, but the site will not work until these are installed."
        echo ""
    else
        echo "  -> All required PHP modules are installed."
    fi
else
    echo "[0/7] PHP CLI not found — skipping module check."
    echo "  !! Install PHP: sudo apt install php php-sqlite3 php-curl php-json php-mbstring php-gd"
    echo ""
fi

# ============================================================================
# 2. Create required directories
# ============================================================================

echo "[1/7] Creating required directories..."
mkdir -p uploads/mods
mkdir -p db

# ============================================================================
# 3. Environment configuration
# ============================================================================

echo "[2/7] Checking environment configuration..."
if [ ! -f ".env" ]; then
    cp .env.example .env
    echo "  -> Created .env from .env.example"
    echo "  !! IMPORTANT: Edit .env with your actual API keys before going live!"
else
    echo "  -> .env already exists, skipping."
fi

# ============================================================================
# 4. File ownership and permissions
# ============================================================================

echo "[3/7] Setting file ownership to $WEB_USER:$WEB_GROUP..."
chown -R $WEB_USER:$WEB_GROUP .

echo "[4/7] Setting file permissions..."
# Directories: 755 (rwxr-xr-x)
find . -type d -exec chmod 755 {} \;
# Files: 644 (rw-r--r--)
find . -type f -exec chmod 644 {} \;

# Secure .env (readable only by owner)
if [ -f ".env" ]; then
    chmod 600 .env
fi

# Writable directories for PHP
chmod 775 db
chmod -R 775 uploads

# Database file (if already exists)
if [ -f "db/website.db" ]; then
    chmod 664 db/website.db
fi

# Keep scripts executable
chmod 744 setup_permissions.sh
chmod 744 promote_admin.php

# ============================================================================
# 5. Database initialization
# ============================================================================

echo "[5/7] Initializing database schema..."
if command -v php >/dev/null 2>&1; then
    sudo -u $WEB_USER php db/init_db.php
else
    echo "  !! Warning: PHP CLI not found. Run 'php db/init_db.php' manually."
fi

# ============================================================================
# 6. Cron job installation
# ============================================================================

echo "[6/7] Installing cron jobs for $WEB_USER..."

PROJECT_DIR="$(pwd)"

# Mission cron: runs every minute (script internally loops for 55s with a lock file)
CRON_MISSIONS="* * * * * /usr/bin/php ${PROJECT_DIR}/api/cron_missions.php >> /dev/null 2>&1"

# Community event cron: runs once per hour
CRON_COMMUNITY="0 * * * * /usr/bin/php ${PROJECT_DIR}/api/cron_community.php >> /dev/null 2>&1"

# Streak alert cron: runs once daily at 11 PM server time
CRON_STREAK="0 23 * * * /usr/bin/php ${PROJECT_DIR}/api/cron_streak_alert.php >> /dev/null 2>&1"

# Idempotent install: remove old entries, then append fresh ones
(
    sudo -u $WEB_USER crontab -l 2>/dev/null \
        | grep -v "cron_missions.php" \
        | grep -v "cron_community.php" \
        | grep -v "cron_streak_alert.php"
    echo "$CRON_MISSIONS"
    echo "$CRON_COMMUNITY"
    echo "$CRON_STREAK"
) | sudo -u $WEB_USER crontab -

echo "  -> Installed 3 cron jobs. Verify with: sudo -u $WEB_USER crontab -l"

# ============================================================================
# 7. Done
# ============================================================================

echo "[7/7] Validating setup..."

ERRORS=0

if [ ! -f ".env" ]; then
    echo "  ✗ .env file missing"
    ERRORS=$((ERRORS + 1))
fi

if [ ! -d "db" ]; then
    echo "  ✗ db/ directory missing"
    ERRORS=$((ERRORS + 1))
else
    echo "  ✓ db/ directory exists"
fi

if [ ! -d "uploads/mods" ]; then
    echo "  ✗ uploads/mods/ directory missing"
    ERRORS=$((ERRORS + 1))
else
    echo "  ✓ uploads/mods/ directory exists"
fi

if [ -f "db/website.db" ]; then
    echo "  ✓ database initialized"
else
    echo "  ✗ database not created (check PHP installation)"
    ERRORS=$((ERRORS + 1))
fi

if [ -f "api/config.php" ]; then
    echo "  ✓ config.php present"
fi

if [ -f ".htaccess" ]; then
    echo "  ✓ .htaccess present"
fi

echo ""
if [ $ERRORS -eq 0 ]; then
    echo "=== Setup complete! ==="
    echo ""
    echo "Next steps:"
    echo "  1. Edit .env with your API keys and secrets"
    echo "  2. Point your Apache/Nginx web root to: ${PROJECT_DIR}"
    echo "  3. Ensure mod_rewrite is enabled (Apache: a2enmod rewrite)"
    echo "  4. Create your first admin: php promote_admin.php <username>"
    echo ""
else
    echo "=== Setup completed with $ERRORS warning(s). See above. ==="
fi
