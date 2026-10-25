#!/bin/bash

# ============================================================
# ACCSESS Loyihasi - AWS EC2 Server Setup Script
# Ubuntu 20.04, 22.04, 24.04, 26.04 qo'llab-quvvatlanadi
# Foydalanish: sudo bash setup_server.sh [your-domain.com] [email]
# ============================================================

DOMAIN=${1:-"elbekroxmonov.uz"}
EMAIL=${2:-"elbekroxmonov@gmail.com"}
APP_DIR="/var/www/acsess"

echo "======================================================"
echo " ACCSESS Server Setup Boshlandi"
echo " Domen: $DOMAIN"
echo " Email: $EMAIL"
echo "======================================================"

# 0. Buzilgan yoki mos kelmaydigan PPA larni tozalash
echo ">>> [1/10] PPA larni tozalash va tizimni tayyorlash..."
rm -f /etc/apt/sources.list.d/*ondrej* /etc/apt/sources.list.d/*php* 2>/dev/null || true
apt install -y software-properties-common curl git unzip gnupg
add-apt-repository universe -y 2>/dev/null || true
apt update -y

# 1. Nginx, Certbot va Tesseract o'rnatish
echo ">>> [2/10] Nginx, Certbot va Tesseract OCR o'rnatish..."
apt install -y \
    nginx \
    certbot \
    python3-certbot-nginx \
    tesseract-ocr \
    tesseract-ocr-uzb \
    tesseract-ocr-rus \
    tesseract-ocr-eng

# 2. PHP va PHP-FPM o'rnatish (standart yoki versiyalangan)
echo ">>> [3/10] PHP va modullarni o'rnatish..."
apt install -y \
    php-fpm \
    php-mysql \
    php-mongodb \
    php-gd \
    php-curl \
    php-mbstring \
    php-xml \
    php-zip \
    php-intl \
    php-bcmath 2>/dev/null || \
apt install -y \
    php8.2-fpm \
    php8.2-mysql \
    php8.2-mongodb \
    php8.2-gd \
    php8.2-curl \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-zip \
    php8.2-intl \
    php8.2-bcmath 2>/dev/null || true

# PHP FPM xizmatini ishga tushirish
systemctl start php*-fpm 2>/dev/null || true
systemctl enable php*-fpm 2>/dev/null || true

# PHP FPM soketini aniqlash
PHP_SOCK=$(ls /run/php/php*-fpm.sock 2>/dev/null | head -n 1)
if [ -z "$PHP_SOCK" ]; then
    PHP_SOCK="/var/run/php/php-fpm.sock"
fi
echo ">>> Aniqlangan PHP-FPM soketi: $PHP_SOCK"

# 3. PHP.ini sozlamalari
echo ">>> [4/10] PHP konfiguratsiyasi..."
for ini in $(ls /etc/php/*/fpm/php.ini 2>/dev/null); do
    sed -i 's/upload_max_filesize = .*/upload_max_filesize = 100M/' $ini
    sed -i 's/post_max_size = .*/post_max_size = 100M/' $ini
    sed -i 's/max_execution_time = .*/max_execution_time = 120/' $ini
    sed -i 's/memory_limit = .*/memory_limit = 256M/' $ini
done

# 4. Loyiha papkalarini yaratish
echo ">>> [5/10] Loyiha papkalarini yaratish ($APP_DIR)..."
mkdir -p $APP_DIR
mkdir -p $APP_DIR/uploads/complaints
mkdir -p $APP_DIR/img/captures
mkdir -p $APP_DIR/cache
mkdir -p $APP_DIR/tmp

chown -R www-data:www-data $APP_DIR
chmod -R 755 $APP_DIR
chmod -R 777 $APP_DIR/uploads 2>/dev/null || true
chmod -R 777 $APP_DIR/img/captures 2>/dev/null || true
chmod -R 777 $APP_DIR/cache 2>/dev/null || true
chmod -R 777 $APP_DIR/tmp 2>/dev/null || true

# 5. Nginx - HTTP konfiguratsiyasi
echo ">>> [6/10] Nginx HTTP konfiguratsiyasi..."
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
        fastcgi_pass unix:$PHP_SOCK;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(env|git|htaccess) { deny all; return 404; }
    location ~ ^/(tmp|debug_|test_|audit_|list_|check_|port_) { deny all; return 404; }

    client_max_body_size 100M;
}
EOF

rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/acsess /etc/nginx/sites-enabled/
nginx -t && systemctl restart nginx

# 6. MongoDB va mongosh o'rnatish
echo ">>> [7/10] MongoDB o'rnatish va ishga tushirish..."
curl -fsSL https://www.mongodb.org/static/pgp/server-7.0.asc | gpg -o /usr/share/keyrings/mongodb-server-7.0.gpg --dearmor --yes 2>/dev/null || true
echo "deb [ arch=amd64,arm64 signed-by=/usr/share/keyrings/mongodb-server-7.0.gpg ] https://repo.mongodb.org/apt/ubuntu jammy/mongodb-org/7.0 multiverse" | tee /etc/apt/sources.list.d/mongodb-org-7.0.list
apt update -y || true
apt install -y mongodb-org php-mongodb || true
systemctl enable mongod 2>/dev/null || true
systemctl start mongod 2>/dev/null || true

# 7. SSL sertifikat olish (certbot)
echo ">>> [8/10] SSL sertifikat olish ($DOMAIN)..."
CERT_DOMAINS="-d $DOMAIN"
if host www.$DOMAIN >/dev/null 2>&1 || ping -c 1 -W 2 www.$DOMAIN >/dev/null 2>&1; then
    CERT_DOMAINS="$CERT_DOMAINS -d www.$DOMAIN"
fi

certbot --nginx $CERT_DOMAINS \
    --non-interactive --agree-tos --email $EMAIL \
    --redirect --keep-until-expiring || certbot --nginx -d $DOMAIN --non-interactive --agree-tos --email $EMAIL --redirect --keep-until-expiring || true

# 8. Nginx - To'liq HTTPS + Permissions-Policy
echo ">>> [9/10] Nginx HTTPS konfiguratsiyasi + Permissions-Policy..."
if [ -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]; then
cat > /etc/nginx/sites-available/acsess << EOF
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name $DOMAIN www.$DOMAIN;
    root $APP_DIR;
    index index.php index.html;
    charset utf-8;

    ssl_certificate     /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header Permissions-Policy "camera=*, microphone=*" always;

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
        fastcgi_pass unix:$PHP_SOCK;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(env|git|htaccess) { deny all; return 404; }
    location ~ ^/(tmp|debug_|test_|audit_|list_|check_|port_) { deny all; return 404; }

    client_max_body_size 100M;
}
EOF
fi

nginx -t && systemctl reload nginx
echo ">>> Nginx sozlandi!"

# 9. Xizmatlarni qayta ishga tushirish
echo ">>> [10/10] Xizmatlarni ishga tushirish..."
systemctl restart nginx
systemctl restart php*-fpm 2>/dev/null || true
systemctl enable nginx

echo ""
echo "======================================================"
echo " Server tayyorlandi!"
echo " Papka: $APP_DIR"
echo " Domen: https://$DOMAIN"
echo "======================================================"
