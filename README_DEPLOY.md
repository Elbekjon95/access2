# ACCSESS — AWS EC2 Deployment Qo'llanmasi
## Ubuntu 22.04 LTS | PHP 8.2 | MySQL 8 | Nginx

---

## 📋 Talablar

- AWS EC2: `t2.small` yoki undan yuqori (min. 1GB RAM)
- OS: Ubuntu 22.04 LTS
- Port: 80 (HTTP), 443 (HTTPS), 22 (SSH) ochiq bo'lishi kerak
- Domain: DNS serverga yo'naltirilgan bo'lishi kerak

---

## 🔑 1. Serverga ulanish

```bash
ssh -i "sizning-kalit.pem" ubuntu@SERVER_IP
```

---

## 🛠️ 2. Server muhitini sozlash

```bash
# setup_server.sh faylini yuklang
wget https://raw.githubusercontent.com/SIZNING-REPO/acsess4/main/setup_server.sh

# Ishga tushiring (domeningizni kiriting)
sudo bash setup_server.sh yourdomain.com
```

> ⚠️ **MUHIM:** Skript oxirida chiqadigan MySQL parolini saqlang!

---

## 📁 3. Loyiha fayllarini yuklash

### Variant A: Git orqali (TAVSIYA ETILADI)

```bash
cd /var/www/acsess

# GitHub'da token bilan (yoki SSH key bilan)
git clone https://github.com/SIZNING-REPO/acsess4.git .
```

### Variant B: SCP/SFTP orqali

```bash
# Lokal kompyuterdan (PowerShell/Terminal):
scp -i "kalit.pem" -r C:/OSPanel/home/acsess4/* ubuntu@SERVER_IP:/var/www/acsess/
```

---

## ⚙️ 4. Muhit sozlamalari (.env)

```bash
cd /var/www/acsess

# Namunadan nusxa oling
cp .env.production.example .env

# Tahrirlang
nano .env
```

**TO'LDIRISH KERAK:**
```
DB_PASS=setup_server.sh dan olingan parol
GEMINI_API_KEY=sizning_gemini_key
UZBEKVOICE_API_KEY=sizning_uzbekvoice_key
TELEGRAM_BOT_TOKEN=bot_token
TELEGRAM_CHAT_ID=chat_id
COMPLAINT_EMAIL=admin@sizning-domen.uz
```

---

## 🗄️ 5. Ma'lumotlar bazasini yaratish

```bash
# Database import
mysql -u acsess_user -p acsess4 < /var/www/acsess/database.sql

# Parolni kiriting (setup_server.sh dan olingan)
```

**Adminni tekshiring:**
```bash
mysql -u acsess_user -p -e "SELECT username, role FROM acsess4.users;"
```

---

## 🌐 6. Nginx va SSL sozlash

```bash
# Nginx konfiguratsiyasini o'rnatish
sudo cp /var/www/acsess/nginx_acsess.conf /etc/nginx/sites-available/acsess

# Domeningizni o'zgartiring
sudo nano /etc/nginx/sites-available/acsess
# "yourdomain.com" ni haqiqiy domeningizga almashtiring

# Yoqish
sudo ln -sf /etc/nginx/sites-available/acsess /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

### SSL (HTTPS) - Bepul Let's Encrypt

```bash
sudo apt install certbot python3-certbot-nginx -y
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Avtomatik yangilash tekshiring
sudo certbot renew --dry-run
```

---

## 🔒 7. Ruxsatlarni sozlash

```bash
sudo chown -R www-data:www-data /var/www/acsess
sudo chmod -R 755 /var/www/acsess
sudo chmod -R 777 /var/www/acsess/uploads
sudo chmod -R 777 /var/www/acsess/img/captures
sudo chmod -R 777 /var/www/acsess/cache
sudo chmod 600 /var/www/acsess/.env
```

---

## 🧪 8. Test qiling

```bash
# PHP ishlayaptimi?
php -v

# Nginx ishlayaptimi?
sudo systemctl status nginx

# MySQL ishlayaptimi?
sudo systemctl status mysql

# Tesseract ishlayaptimi?
tesseract --version

# API test qiling
curl -s http://yourdomain.com/api/weather.php?city=Tashkent | head -c 200
```

---

## 🔄 9. Yangilash (Update) jarayoni

```bash
cd /var/www/acsess

# Kodni yangilash
git pull origin main

# Ruxsatlarni qayta sozlash
sudo chown -R www-data:www-data .
sudo chmod 600 .env

# PHP-FPM qayta yuklash
sudo systemctl reload php8.2-fpm
```

---

## 🚨 Muammolarni bartaraf etish

**Sahifa 502 xatosini ko'rsatsa:**
```bash
sudo systemctl status php8.2-fpm
sudo journalctl -u php8.2-fpm -n 50
```

**Ma'lumotlar bazasiga ulanmasa:**
```bash
sudo mysql -u root
SHOW DATABASES;
SELECT user, host FROM mysql.user;
```

**Ruxsat xatosi (403/500):**
```bash
sudo chown -R www-data:www-data /var/www/acsess
ls -la /var/www/acsess/.env
```

**Nginx log:**
```bash
sudo tail -f /var/log/nginx/acsess_error.log
```

---

## 🔐 Xavfsizlik ma'lumotlari

| Narsa | Ma'lumot |
|-------|----------|
| Default admin | `admin` / `admin123` |
| **MUHIM** | Birinchi kirishingizda parolni o'zgartiring! |
| .env fayl | Git'ga kirmaydi (gitignore) |
| tmp/ papka | Brauzerdan kirish bloklangan |

---

**Loyiha tayyor!** Savollar bo'lsa, TG: @elbekroxmonov
