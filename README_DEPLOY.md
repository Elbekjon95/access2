# ACCSESS — Serverga 0 dan O'rnatish Qo'llanmasi
## AWS EC2 | Ubuntu 22.04 LTS | PHP 8.2 | MySQL 8 | Nginx | HTTPS

---

## 📋 Minimal Talablar

| Komponent | Tavsiya |
|-----------|---------|
| Bulut | AWS EC2 (yoki boshqa VPS) |
| Instance turi | `t2.small` yoki yuqori (min 1 GB RAM) |
| OS | Ubuntu 22.04 LTS |
| Ochiq portlar | 22 (SSH), 80 (HTTP), 443 (HTTPS) |
| Domen | DNS A-yozuvi serverga ko'rsatilgan bo'lishi shart |

> 🎤 **Nima uchun HTTPS majburiy?**
> Brauzerlar mikrofon va kamerani (`getUserMedia`) faqat HTTPS saytlarda ochadi.
> HTTP saytda `navigator.mediaDevices` umuman ishlamaydi!

---

## 🔑 1-QADAM: Serverga SSH orqali ulanish

```bash
# Windows (PowerShell) yoki macOS/Linux (Terminal):
ssh -i "kalit-faylingiz.pem" ubuntu@SERVER_IP
```

**AWS EC2 da yangi instance ochish:**
1. AWS Console → EC2 → Launch Instance
2. OS: `Ubuntu Server 22.04 LTS`
3. Instance type: `t2.small`
4. Key pair: yangi `.pem` fayl yarating va yuklab oling
5. Security Group: 22, 80, 443 portlarini oching
6. Launch

---

## 🛠️ 2-QADAM: Serverda muhitni avtomatik sozlash

Setup skripti quyidagilarni **avtomatik** o'rnatadi:
- Nginx, PHP 8.2-FPM, MySQL 8
- Tesseract OCR (uz, ru, en tillari)
- Let's Encrypt SSL sertifikati (HTTPS)
- Barcha papkalar va ruxsatlar

```bash
# Setup skriptini yuklab oling
wget https://raw.githubusercontent.com/Elbekjon95/access2/main/setup_server.sh

# Domeningiz va emailingizni kiriting (SSL uchun email kerak)
sudo bash setup_server.sh sizning-domen.uz admin@sizning-domen.uz
```

> ⚠️ **DIQQAT:** Skript oxirida chiqadigan `DB_PASS` ni **hoziroq** nusxa oling!
> Bu parol faqat bir marta ko'rinadi va `.env` faylga kiritiladi.

```
======================================================
 MySQL Ma'lumotlari (SAQLANG!)
 DB_NAME: acsess4
 DB_USER: acsess_user
 DB_PASS: xxxxxxxxxxxxxxxx   ← BU PAROLNI NUSXA OLING!
======================================================
```

---

## 📁 3-QADAM: GitHub dan loyihani yuklash

```bash
cd /var/www/acsess

# Loyihani clone qilish
sudo git clone https://github.com/Elbekjon95/access2.git .

# Ruxsatlarni tiklash (clone dan keyin shart)
sudo chown -R www-data:www-data /var/www/acsess
sudo chmod -R 755 /var/www/acsess
sudo chmod -R 777 /var/www/acsess/uploads
sudo chmod -R 777 /var/www/acsess/img/captures
sudo chmod -R 777 /var/www/acsess/cache
```

---

## ⚙️ 4-QADAM: .env faylini sozlash

```bash
cd /var/www/acsess

# Namuna fayldan nusxa oling
cp .env.production.example .env

# Tahrirlang
nano .env
```

**Quyidagi barcha qiymatlarni to'ldiring:**

```env
# === MA'LUMOTLAR BAZASI ===
DB_HOST=localhost
DB_NAME=acsess4
DB_USER=acsess_user
DB_PASS=SETUP_SKRIPTDAN_OLINGAN_PAROL

# === AI ENGINE ===
GEMINI_API_KEY=AIza...              # aistudio.google.com
GEMINI_MODEL=gemini-2.0-flash-exp
GEMINI_TTS_MODEL=gemini-2.0-flash-exp

# === OVOZ TANISH (STT/TTS) ===
UZBEKVOICE_API_KEY=...              # uzbekvoice.com
GROQ_API_KEY=gsk_...               # console.groq.com

# === REYS MA'LUMOTLARI ===
FLIGHT_API_URL=https://...

# === TELEGRAM (SHIKOYATLAR) ===
TELEGRAM_BOT_TOKEN=...             # @BotFather dan olingan token
TELEGRAM_CHAT_ID=...               # Guruh yoki kanal ID

# === EMAIL ===
COMPLAINT_EMAIL=admin@sizning-domen.uz

# === OB-HAVO ===
OPENWEATHER_API_KEY=...            # openweathermap.org/api
```

**Saqlash:** `Ctrl+O` → `Enter` → `Ctrl+X`

```bash
# .env faylni himoya qiling (faqat www-data o'qiy olsin)
sudo chmod 600 /var/www/acsess/.env
sudo chown www-data:www-data /var/www/acsess/.env
```

---

## 🗄️ 5-QADAM: Ma'lumotlar bazasini import qilish

```bash
# SQL faylni import qiling
mysql -u acsess_user -p'DB_PASS_SHUBU' acsess4 < /var/www/acsess/database.sql

# Import tekshirish — jadvallar bo'lishi kerak
mysql -u acsess_user -p'DB_PASS_SHUBU' -e "SHOW TABLES FROM acsess4;"

# Admin foydalanuvchi tekshirish
mysql -u acsess_user -p'DB_PASS_SHUBU' -e "SELECT id, username, role FROM acsess4.users;"
```

> **Default admin:** login `admin` | parol `admin123`
> 🔴 **Birinchi kirishda parolni o'zgartiring!**

---

## 🌐 6-QADAM: Nginx sozlash (setup_server.sh ishlatmagan bo'lsangiz)

> ✅ `setup_server.sh` ishlatgan bo'lsangiz — bu qadam **allaqachon bajarilgan**, o'tkazib yuboring.

```bash
# Nginx config faylini ko'chiring
sudo cp /var/www/acsess/nginx_acsess.conf /etc/nginx/sites-available/acsess

# Domeningizni kiriting
sudo sed -i 's/yourdomain.com/sizning-domen.uz/g' /etc/nginx/sites-available/acsess

# Yoqish
sudo ln -sf /etc/nginx/sites-available/acsess /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# SSL sertifikat olish
sudo certbot --nginx -d sizning-domen.uz -d www.sizning-domen.uz \
    --non-interactive --agree-tos --email admin@sizning-domen.uz --redirect

# Nginx tekshirish va qayta yuklash
sudo nginx -t && sudo systemctl reload nginx
```

---

## ✅ 7-QADAM: Hamma narsa ishlayotganini tekshirish

```bash
# PHP ishlayaptimi?
php -v

# Nginx statusini tekshiring
sudo systemctl status nginx

# MySQL statusini tekshiring
sudo systemctl status mysql

# PHP-FPM statusini tekshiring
sudo systemctl status php8.2-fpm

# Tesseract OCR ishlayaptimi?
tesseract --version

# SSL sertifikat tekshirish
sudo certbot certificates

# Sayt API ni test qiling (domen bilan)
curl -sk https://sizning-domen.uz/api/weather.php?city=Tashkent | head -c 200
```

Hammasi yashil ✅ bo'lsa — sayt tayyor!

---

## 🔄 8-QADAM: Yangilash (Githubdan serverga pull qilish)

Har safar lokal kompyuterdan GitHub ga push qilgandan so'ng, serverda:

```bash
cd /var/www/acsess

# config.php kabi lokal o'zgarishlar bo'lsa, stash qiling
sudo git stash

# Yangi kodni yuklab oling
sudo git pull origin main

# Ruxsatlarni tiklang
sudo chown -R www-data:www-data /var/www/acsess
sudo chmod 600 /var/www/acsess/.env

# PHP-FPM qayta yuklash (yangi PHP fayllar uchun)
sudo systemctl restart php8.2-fpm
```

> **Nima uchun `git stash`?**
> Server `.env` yoki `config.php` ni o'zgartirishi mumkin.
> `git stash` bu o'zgarishlarni vaqtincha saqlaydi, `git pull` to'siqsiz o'tadi.

---

## 🚨 Muammolarni bartaraf etish

### Sahifa 502 Bad Gateway ko'rsatsa:
```bash
sudo systemctl status php8.2-fpm
sudo journalctl -u php8.2-fpm -n 50
sudo systemctl restart php8.2-fpm
```

### Ma'lumotlar bazasiga ulanmasa:
```bash
# MySQL ishlayaptimi?
sudo systemctl status mysql

# Foydalanuvchi va bazani tekshiring
sudo mysql -u root -e "SELECT user, host FROM mysql.user; SHOW DATABASES;"

# .env dagi DB_PASS to'g'rimi?
cat /var/www/acsess/.env | grep DB_
```

### Ruxsat xatosi (403 / 500):
```bash
sudo chown -R www-data:www-data /var/www/acsess
sudo chmod -R 755 /var/www/acsess
sudo chmod -R 777 /var/www/acsess/uploads /var/www/acsess/img/captures /var/www/acsess/cache
sudo chmod 600 /var/www/acsess/.env
```

### Kamera/Mikrofon ishlamasa:
```bash
# HTTPS ishlayaptimi?
curl -I https://sizning-domen.uz | grep -i "strict-transport\|permissions-policy"

# Nginx config to'g'rimi?
sudo nginx -t
```

### Git pull xatosi (local changes):
```bash
cd /var/www/acsess
sudo git stash        # lokal o'zgarishlarni saqla
sudo git pull         # kodni yangilang
```

### Loglarni ko'rish:
```bash
# Nginx xatolari
sudo tail -f /var/log/nginx/acsess_error.log

# PHP xatolari
sudo tail -f /var/log/php8.2-fpm.log

# MySQL xatolari
sudo tail -f /var/log/mysql/error.log
```

---

## 🔐 Xavfsizlik checklisti

| ✅ | Narsa |
|----|-------|
| ☐ | Admin parolini o'zgartiring (`admin123` dan) |
| ☐ | `.env` fayl `chmod 600` qilingan |
| ☐ | `.env` `.gitignore` da mavjud |
| ☐ | `tmp/` papkaga brauzerdan kirish bloklangan |
| ☐ | SSL sertifikat faol (`certbot certificates`) |
| ☐ | `debug_*.php`, `test_*.php` fayllar bloklangan |

---

## 📞 Qo'llab-quvvatlash

**Telegram:** @elbekroxmonov
**GitHub:** https://github.com/Elbekjon95/access2
