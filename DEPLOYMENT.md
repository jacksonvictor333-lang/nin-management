# Kuweka NIN Management kwenye seva (HTTPS)

Kamera ya simu (kupiga picha ya mwombaji) na ulinzi wa password vinahitaji **HTTPS**.
Mwongozo huu una sehemu mbili: ofisini kwa XAMPP, na seva halisi ya mtandaoni.

---

## A. Ofisini (XAMPP, mtandao wa Wi-Fi)

Apache ya XAMPP tayari inasikiliza `https://` (port 443) kwa cheti cha kujitengenezea
(`C:\xampp\apache\conf\ssl.crt\server.crt`), ambacho kinatambua `localhost` na IP za kompyuta.

1. Anzisha upya Apache kwenye **XAMPP Control Panel** (Stop → Start) baada ya kubadilisha cheti.
2. Fungua kwenye simu: `https://<IP ya kompyuta>/nin-management/public`
   (IP inaonekana kwa `ipconfig` → *IPv4 Address* ya Wi-Fi).
3. Simu itaonya "connection not private" kwa sababu cheti si cha mamlaka rasmi:
   chagua **Advanced → Proceed**. Baada ya hapo kamera inafanya kazi.
4. IP ya kompyuta ikibadilika (mtandao mpya), badilisha kwenye `.env`:
   ```
   CARD_VERIFY_URL=http://<IP mpya>/nin-management/public
   ```
   kisha `php artisan config:clear`. Tengeneza cheti kipya chenye IP hiyo:
   ```
   cd C:\xampp\apache\conf
   openssl req -x509 -newkey rsa:2048 -nodes -days 825 -keyout ssl.key/server.key -out ssl.crt/server.crt ^
     -subj "/CN=NIN Management (local)" -addext "subjectAltName=DNS:localhost,IP:127.0.0.1,IP:<IP mpya>"
   ```
   Kidokezo: weka IP ya kudumu (DHCP reservation) kwenye router ili isibadilike.

---

## B. Seva halisi (Ubuntu + Nginx au Apache)

### 1. Mahitaji
- PHP 8.2+ na extensions: `pdo_mysql mbstring openssl tokenizer xml ctype fileinfo zip zlib gd`
- MySQL 8 / MariaDB 10.6+
- Composer 2
- Domain (mf. `nin.mfano.go.tz`) inayoelekea IP ya seva

### 2. Msimbo
```bash
git clone https://github.com/jacksonvictor333-lang/nin-management.git /var/www/nin-management
cd /var/www/nin-management
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
php artisan storage:link
chown -R www-data:www-data storage bootstrap/cache
```

### 3. `.env` ya production
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nin.mfano.go.tz
APP_TIMEZONE=Africa/Dar_es_Salaam
FORCE_HTTPS=true
CARD_VERIFY_URL=https://nin.mfano.go.tz

SESSION_LIFETIME=30
SESSION_SECURE_COOKIE=true

DB_DATABASE=nin_management
DB_USERNAME=nin
DB_PASSWORD=<password imara>

MAIL_MAILER=smtp            # kwa viungo vya kuweka upya password
SMS_DRIVER=beem             # au africastalking
SMS_API_KEY=...
SMS_API_SECRET=...
SMS_SENDER_ID=...
SMS_OFFICE_NAME="ofisi ya usajili ya Wilaya"
```
> **Muhimu:** ukihamisha database kutoka XAMPP, tumia `APP_KEY` ileile.
> APP_KEY mpya inabatilisha QR za kadi zote zilizochapishwa na siri za 2FA.

### 4. Database
```bash
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```
Kuhamisha data iliyopo: tengeneza backup kwenye **Menyu → Backups**, kisha rejesha
`database.sql` kwenye MySQL ya seva na nakili `files/` kwenda `storage/app/public/`.
Nyaraka za waombaji ziko `storage/app/private/documents/` — zinakili pia.

### 5. Nginx + cheti cha bure (Let's Encrypt)
```nginx
server {
    server_name nin.mfano.go.tz;
    root /var/www/nin-management/public;
    index index.php;
    client_max_body_size 30M;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
    location ~ /\.(?!well-known) { deny all; }
}
```
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d nin.mfano.go.tz      # inaweka HTTPS na kuongeza muda wa cheti yenyewe
```

### 6. Kazi za ratiba (backup ya kila siku saa 7 usiku)
```bash
crontab -u www-data -e
* * * * * cd /var/www/nin-management && php artisan schedule:run >> /dev/null 2>&1
```
Nakili backup (`storage/app/backups`) kwenda mahali pengine (diski nyingine au cloud) kila wiki.

### 7. Ukaguzi wa mwisho
- `https://nin.mfano.go.tz/login` inafunguka bila onyo la cheti
- Skani QR ya kadi kwa simu → ukurasa wa "Valid card"
- Piga picha kwa kamera kwenye fomu ya usajili
- `php artisan test` (kwenye mashine ya maendeleo) — tests zote zipite
