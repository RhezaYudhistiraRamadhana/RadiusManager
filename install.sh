#!/bin/bash
# ============================================================
# CENDANA — Auto Installer for Ubuntu/Debian
# Central Evaluasi Network, Direktori Akun, dan Navigasi Autentikasi
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
echo "   CENDANA Installer"
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
mkdir -p "$WEB_ROOT/storage/logs"
cp -r . "$WEB_ROOT/"
chown -R www-data:www-data "$WEB_ROOT"
chmod -R 755 "$WEB_ROOT"
chmod -R 775 "$WEB_ROOT/storage/logs"

# ── Write config ──────────────────────────────────────────────────────────
info "Writing config.php..."
HASHED=$(php -r "echo password_hash('$APP_PASS', PASSWORD_DEFAULT);")
API_KEY=$(php -r "echo bin2hex(random_bytes(16));")

cat > "$WEB_ROOT/config.php" << CONF
<?php
// ─── Database Configuration ───────────────────────────────────────────────
define('DB_HOST',     '$DB_HOST');
define('DB_NAME',     '$DB_NAME');
define('DB_USER',     '$DB_USER');
define('DB_PASS',     '$DB_PASS');
define('DB_PORT',     '3306');

// ─── App Configuration ────────────────────────────────────────────────────
define('APP_NAME',       'CENDANA');
define('APP_FULL_NAME',  'Central Evaluasi Network, Direktori Akun, dan Navigasi Autentikasi');
define('APP_TAGLINE',    'FreeRADIUS Management & Access Control System');
define('APP_VERSION',    '1.9.4');
define('APP_ADMIN',      'admin');
define('APP_PASS',       '$HASHED');
define('ROWS_PER_PAGE',  20);
define('EXPIRY_WARN_DAYS', 7);
define('API_KEY',        '$API_KEY');

// ─── Session lifetime (seconds) ──────────────────────────────────────────
define('SESSION_LIFETIME', 3600);

// ─── Environment & Error Logging ──────────────────────────────────────────
define('APP_ENV',  'production');
define('LOG_FILE', __DIR__ . '/storage/logs/app.log');

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors',     '1');
    ini_set('error_log',      LOG_FILE);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// ─── SMTP & Email Settings (Production Mail Relay) ────────────────────────
define('MAIL_FROM',       'noreply@your-domain.edu');
define('MAIL_FROM_NAME',  APP_NAME . ' Security');
define('SMTP_HOST',       '');
define('SMTP_PORT',       587);
define('SMTP_USER',       '');
define('SMTP_PASS',       '');
define('SMTP_SECURE',     'tls');
define('DEV_MODE',        false);

// ─── Load DB Connection & Mail Helpers ────────────────────────────────────
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mail.php';
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

# ── HTTPS / SSL Configuration (Optional) ──────────────────────────────────
read -rp "Configure HTTPS? (y/n) [n]: " DO_SSL
PROTO="http"
if [[ "$DO_SSL" == "y" || "$DO_SSL" == "Y" ]]; then
    read -rp "Use Let's Encrypt (l) or self-signed certificate (s)? [s]: " SSL_TYPE
    if [[ "${SSL_TYPE:-s}" == "l" || "${SSL_TYPE:-s}" == "L" ]]; then
        info "Installing Certbot and generating Let's Encrypt certificate..."
        apt-get install -y certbot python3-certbot-apache
        read -rp "Domain name (e.g. radius.polman.ac.id): " DOMAIN
        certbot --apache -d "$DOMAIN" --non-interactive --agree-tos \
                -m "admin@$DOMAIN"
        PROTO="https"
    else
        info "Generating self-signed SSL certificate..."
        mkdir -p /etc/ssl/private /etc/ssl/certs
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout /etc/ssl/private/radiusmanager.key \
            -out /etc/ssl/certs/radiusmanager.crt \
            -subj "/CN=CENDANA/O=CENDANA"
        a2enmod ssl
        a2ensite default-ssl
        PROTO="https"
    fi
    systemctl reload apache2
fi

echo ""
echo "============================================"
echo -e "${GREEN}   Installation Complete!${NC}"
echo "============================================"
echo ""
echo "  URL:      ${PROTO}://$(hostname -I | awk '{print $1}')/radiusmanager"
echo "  Username: admin"
echo "  Password: $APP_PASS"
echo ""
echo "  Run install.sql on your FreeRADIUS DB if"
echo "  indexes were not applied automatically."
echo ""
