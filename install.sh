#!/bin/bash
# ============================================================
# RadiusManager — Auto Installer for Ubuntu/Debian
# Usage: sudo bash install.sh
# ============================================================
set -e

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
info()    { echo -e "${GREEN}[INFO]${NC} $1"; }
warning() { echo -e "${YELLOW}[WARN]${NC} $1"; }
error()   { echo -e "${RED}[ERR]${NC}  $1"; exit 1; }

[ "$EUID" -ne 0 ] && error "Please run as root: sudo bash install.sh"

# ── Collect config ────────────────────────────────────────────────────────
echo ""
echo "============================================"
echo "   RadiusManager Installer"
echo "============================================"
echo ""

read -rp "FreeRADIUS DB host     [localhost]: " DB_HOST;  DB_HOST=${DB_HOST:-localhost}
read -rp "FreeRADIUS DB name     [radius]:    " DB_NAME;  DB_NAME=${DB_NAME:-radius}
read -rp "FreeRADIUS DB user     [radius]:    " DB_USER;  DB_USER=${DB_USER:-radius}
read -rsp "FreeRADIUS DB password:             " DB_PASS;  echo ""
read -rp "Admin password         [admin123]:  " APP_PASS; APP_PASS=${APP_PASS:-admin123}
read -rp "Web root path          [/var/www/html/radiusmanager]: " WEB_ROOT
WEB_ROOT=${WEB_ROOT:-/var/www/html/radiusmanager}

echo ""
info "Installing dependencies..."
apt-get update -qq
apt-get install -y -qq apache2 php php-mysql php-mbstring libapache2-mod-php

# ── Deploy files ──────────────────────────────────────────────────────────
info "Deploying files to $WEB_ROOT..."
mkdir -p "$WEB_ROOT"
cp -r . "$WEB_ROOT/"
chown -R www-data:www-data "$WEB_ROOT"
chmod -R 755 "$WEB_ROOT"

# ── Write config ──────────────────────────────────────────────────────────
info "Writing config.php..."
HASHED=$(php -r "echo password_hash('$APP_PASS', PASSWORD_DEFAULT);")

cat > "$WEB_ROOT/config.php" << CONF
<?php
define('DB_HOST',          '$DB_HOST');
define('DB_NAME',          '$DB_NAME');
define('DB_USER',          '$DB_USER');
define('DB_PASS',          '$DB_PASS');
define('DB_PORT',          '3306');
define('APP_NAME',         'RadiusManager');
define('APP_VERSION',      '1.0.0');
define('APP_ADMIN',        'admin');
define('APP_PASS',         '$HASHED');
define('ROWS_PER_PAGE',    20);
define('SESSION_LIFETIME', 3600);

require_once __DIR__ . '/includes/db.php';
CONF

# ── Apache vhost ──────────────────────────────────────────────────────────
info "Configuring Apache..."
cat > /etc/apache2/conf-available/radiusmanager.conf << VHOST
Alias /radiusmanager $WEB_ROOT
<Directory $WEB_ROOT>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
VHOST

a2enconf radiusmanager
a2enmod rewrite
systemctl reload apache2

# ── Apply DB indexes ──────────────────────────────────────────────────────
info "Applying database indexes..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$WEB_ROOT/install.sql" 2>/dev/null && \
    info "Indexes applied." || warning "Could not apply indexes. Run install.sql manually."

# ── PHP settings ──────────────────────────────────────────────────────────
info "Tuning PHP settings..."
PHP_INI=$(php -r "echo php_ini_loaded_file();")
sed -i 's/^memory_limit.*/memory_limit = 256M/'         "$PHP_INI" 2>/dev/null || true
sed -i 's/^max_execution_time.*/max_execution_time = 300/' "$PHP_INI" 2>/dev/null || true
systemctl reload apache2

echo ""
echo "============================================"
echo -e "${GREEN}   Installation Complete!${NC}"
echo "============================================"
echo ""
echo "  URL:      http://$(hostname -I | awk '{print $1}')/radiusmanager"
echo "  Username: admin"
echo "  Password: $APP_PASS"
echo ""
echo "  Run install.sql on your FreeRADIUS DB if"
echo "  indexes were not applied automatically."
echo ""
