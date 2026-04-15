#!/bin/bash

# ============================================================
# ACCSESS Loyihasi - AWS EC2 Ubuntu 22.04 Server Setup Script
# Foydalanish: sudo bash setup_server.sh [your-domain.com]
# ============================================================

set -e

DOMAIN=${1:-"yourdomain.com"}
EMAIL=${2:-"admin@${DOMAIN}"}
APP_DIR="/var/www/acsess"
PHP_VERSION="8.2"

echo "======================================================"
echo " ACCSESS Server Setup Boshlandi"
echo " Domen: $DOMAIN"
echo " Email: $EMAIL"
echo "======================================================"

# 1. Tizimni yangilash
echo ">>> [1/10] Tizimni yangilash..."
apt update && apt upgrade -y

# 2. Kerakli kutubxonalarni o'rnatish
echo ">>> [2/10] PHP $PHP_VERSION, Nginx, MySQL, Certbot o'rnatish..."
apt install -y software-properties-common curl git unzip
add-apt-repository ppa:ondrej/php -y
apt update

apt install -y \
    nginx \
    certbot \
    python3-certbot-nginx \
    mysql-server \
    php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-mysql \
    php${PHP_VERSION}-gd \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-intl \
    php${PHP_VERSION}-bcmath

# 3. Tesseract OCR o'rnatish
echo ">>> [3/10] Tesseract OCR o'rnatish (uz, ru, en)..."
apt install -y tesseract-ocr tesseract-ocr-uzb tesseract-ocr-rus tesseract-ocr-eng

# 4. PHP.ini sozlamalari
echo ">>> [4/10] PHP konfiguratsiyasi..."
PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 100M/' $PHP_INI
sed -i 's/post_max_size = .*/post_max_size = 100M/' $PHP_INI
sed -i 's/max_execution_time = .*/max_execution_time = 120/' $PHP_INI
sed -i 's/memory_limit = .*/memory_limit = 256M/' $PHP_INI

# 5. Loyiha papkasini tayyorlash
echo ">>> [5/10] Papkalarni yaratish..."
mkdir -p $APP_DIR
mkdir -p $APP_DIR/uploads/complaints
mkdir -p $APP_DIR/img/captures
mkdir -p $APP_DIR/cache
mkdir -p $APP_DIR/tmp

# Ruxsatlar
chown -R www-data:www-data $APP_DIR
chmod -R 755 $APP_DIR
chmod -R 777 $APP_DIR/uploads
chmod -R 777 $APP_DIR/img/captures
chmod -R 777 $APP_DIR/cache
chmod -R 777 $APP_DIR/tmp

# 6. Nginx - Faza 1: HTTP only (certbot ACME challenge uchun)
echo ">>> [6/10] Nginx HTTP konfiguratsiyasi ($DOMAIN)..."
cat > /etc/nginx/sites-available/acsess << EOF
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    root $APP_DIR;
    index index.php index.html;
    charset utf-8;

    access_log /var/log/nginx/acsess_access.log;
    error_log  /var/log/nginx/acsess_error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(env|git|htaccess) { deny all; return 404; }
    location ~ ^/(tmp|debug_|test_|audit_) { deny all; return 404; }

    client_max_body_size 100M;
}
EOF

# Eski default config'ni o'chirish
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/acsess /etc/nginx/sites-enabled/
nginx -t && systemctl start nginx

# 7. MySQL sozlash
echo ">>> [7/10] MySQL bazasini yaratish..."
DB_NAME="acsess4"
DB_USER="acsess_user"
DB_PASS=$(openssl rand -base64 24 | tr -d '/+=' | head -c 20)

mysql -u root << SQLEOF
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQLEOF

echo ""
echo "======================================================"
echo " MySQL Ma'lumotlari (SAQLANG!)"
echo " DB_NAME: $DB_NAME"
echo " DB_USER: $DB_USER"
echo " DB_PASS: $DB_PASS"
echo "======================================================"
echo ""

# 8. SSL sertifikat olish (certbot)
echo ">>> [8/10] SSL sertifikat olish ($DOMAIN)..."
certbot --nginx -d $DOMAIN -d www.$DOMAIN \
    --non-interactive --agree-tos --email $EMAIL \
    --redirect --keep-until-expiring
echo ">>> SSL sertifikat muvaffaqiyatli olindi!"

# 9. Nginx - Faza 2: To'liq HTTPS + Permissions-Policy (mikrofon/kamera uchun)
echo ">>> [9/10] Nginx HTTPS konfiguratsiyasi + Permissions-Policy..."
cat > /etc/nginx/sites-available/acsess << EOF
# HTTP → HTTPS yo'naltirish (mikrofon/kamera HTTPS talab qiladi)
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    return 301 https://\$host\$request_uri;
}

# HTTPS asosiy server bloki
server {
    listen 443 ssl http2;
    server_name $DOMAIN www.$DOMAIN;
    root $APP_DIR;
    index index.php index.html;
    charset utf-8;

    # SSL sertifikatlar (Let's Encrypt / certbot)
    ssl_certificate     /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Mikrofon va kamera ruxsati (getUserMedia ishlashi uchun SHART)
    add_header Permissions-Policy "camera=*, microphone=*" always;
    add_header Feature-Policy "camera *; microphone *" always;

    # Logs
    access_log /var/log/nginx/acsess_access.log;
    error_log  /var/log/nginx/acsess_error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(env|git|htaccess) { deny all; return 404; }
    location ~ ^/(tmp|debug_|test_|audit_|list_|check_|port_) { deny all; return 404; }

    client_max_body_size 100M;
}
EOF

nginx -t && systemctl reload nginx
echo ">>> Nginx HTTPS + Permissions-Policy sozlandi!"

# 10. Xizmatlarni qayta ishga tushirish
echo ">>> [10/10] Xizmatlarni ishga tushirish..."
systemctl restart php${PHP_VERSION}-fpm
systemctl restart nginx
systemctl enable nginx
systemctl enable php${PHP_VERSION}-fpm
systemctl enable mysql

echo ""
echo "======================================================"
echo " O'rnatish YAKUNLANDI!"
echo ""
echo " Keyingi qadamlar:"
echo " 1. Git orqali loyihani yukling:"
echo "    cd $APP_DIR"
echo "    git clone https://github.com/Elbekjon95/access2.git ."
echo ""
echo " 2. .env faylini sozlang:"
echo "    cp .env.production.example .env"
echo "    nano .env"
echo "    # DB_PASS=$DB_PASS ni kiriting"
echo ""
echo " 3. Ma'lumotlar bazasini import qiling:"
echo "    mysql -u $DB_USER -p'$DB_PASS' $DB_NAME < database.sql"
echo ""
echo " Sayt manzili: https://$DOMAIN"
echo " SSL: Let's Encrypt (avtomatik yangilanadi)"
echo " Mikrofon/Kamera: HTTPS orqali ishlaydi ✓"
echo ""
echo " PHP: $(php -v | head -n 1)"
echo " Nginx: $(nginx -v 2>&1)"
echo " Tesseract: $(tesseract --version 2>&1 | head -n 1)"
echo "======================================================"
