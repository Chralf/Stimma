# LAMP-stack Installation Guide för Ubuntu 24.04

Denna guide visar hur du installerar en komplett LAMP-stack (Linux, Apache, MariaDB, PHP) på Ubuntu 24.04 samt säkrar din webbplats med SSL/TLS-certifikat från Let's Encrypt.

## 1. Uppdatera systemet

Börja med att uppdatera ditt system:

```bash
sudo apt update
sudo apt upgrade -y
```

## 2. Installera Apache

```bash
sudo apt install apache2 -y
sudo systemctl enable apache2
sudo systemctl start apache2
```

Verifiera installationen genom att öppna http://localhost eller din servers IP-adress i en webbläsare.

## 3. Installera MariaDB

```bash
sudo apt install mariadb-server mariadb-client -y
sudo systemctl enable mariadb
sudo systemctl start mariadb
```

Säkra din MariaDB-installation:

```bash
sudo mysql_secure_installation
```

Följ anvisningarna för att:
- Sätta ett root-lösenord
- Ta bort anonyma användare
- Inaktivera fjärrinloggning som root
- Ta bort testdatabasen
- Ladda om privilegietabellerna

## 4. Installera PHP och nödvändiga moduler

```bash
sudo apt install php libapache2-mod-php php-mysql php-common php-cli php-json php-curl php-gd php-mbstring php-xml php-zip -y
```

## 5. Verifiera PHP-installationen

Skapa en testfil:

```bash
sudo nano /var/www/html/info.php
```

Lägg till följande innehåll:

```php
<?php
phpinfo();
?>
```

Spara filen (Ctrl+O, Enter och stäng med Ctrl+X).

Starta om Apache:

```bash
sudo systemctl restart apache2
```

Besök http://localhost/info.php eller http://din-server-ip/info.php för att verifiera PHP-installationen.

## 6. Konfigurera Apache att prioritera PHP-filer

```bash
sudo nano /etc/apache2/mods-enabled/dir.conf
```

Ändra innehållet till:

```
<IfModule mod_dir.c>
    DirectoryIndex index.php index.html index.cgi index.pl index.xhtml index.htm
</IfModule>
```

Spara och starta om Apache:

```bash
sudo systemctl restart apache2
```

## 7. Installera Let's Encrypt (Certbot)

För att säkra din webbplats med SSL/TLS-certifikat från Let's Encrypt behöver du Certbot:

```bash
sudo apt install certbot python3-certbot-apache -y
```

### Konfigurera domännamn

Innan du kan få ett certifikat behöver du ett domännamn som pekar till din server. Se till att din domän är korrekt konfigurerad med A- eller AAAA-poster som pekar till din servers IP-adress.

### Hämta och installera certifikat 

Kör följande kommando och följ instruktionerna:

```bash
sudo certbot --apache
```

Du kommer att bli ombedd att:
- Ange din e-postadress (för förnyelseaviseringar)
- Godkänna användarvillkoren
- Välja vilka domäner du vill aktivera HTTPS för
- Välja om du vill omdirigera HTTP-trafik till HTTPS (rekommenderas)

### Verifiera automatisk förnyelse

Certbot konfigurerar en timer som automatiskt förnyar dina certifikat innan de löper ut. Kontrollera att timern är aktiv:

```bash
sudo systemctl status certbot.timer
```

Du kan också testa förnyelseproceduren:

```bash
sudo certbot renew --dry-run
```

## 8. Installera Stimma

För att installera Stimma, följ dessa steg:

```bash
cd /var/www
sudo git clone https://github.com/Chralf/Stimma.git
cd Stimma
sudo ln -s /var/www/Stimma/html/* /var/www/html/
sudo chown -R www-data:www-data /var/www/Stimma
sudo git pull
git config --global --add safe.directory /var/www/Stimma
```

## 9. Installera phpMyAdmin (valfritt)

För att enklare hantera din databas kan du installera phpMyAdmin:

```bash
sudo apt-get install phpmyadmin
```

Följ installationsguiden och välj Apache2 som webbserver när du blir tillfrågad.

## 10. Konfigurera Stimma

```bash
cd html
cp env.example .env
vi .env  # Redigera miljövariabler
chmod 755 upload -R  # Sätt rättigheter för upload-mappen
```

I `.env`-filen behöver du konfigurera:
- Databasanslutning
- E-postinställningar
- Andra miljövariabler som krävs för din installation

## 11. Databaskonfiguration

1. Skapa en ny databas och användare i MariaDB:
```bash
sudo mysql -u root -p
CREATE DATABASE stimma;
CREATE USER 'stimma_user'@'localhost' IDENTIFIED BY 'ditt_lösenord';
GRANT ALL PRIVILEGES ON stimma.* TO 'stimma_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

2. Uppdatera databasuppgifterna i `.env`-filen med de nya användaruppgifterna

3. Importera databasschemat genom att följa instruktionerna i [setup_database.md](setup_database.md)

## 12. Slutkontroll

Verifiera att allt fungerar korrekt:

- Kontrollera att Apache2 körs: `sudo systemctl status apache2`
- Kontrollera att MariaDB körs: `sudo systemctl status mariadb`
- Verifiera att alla filrättigheter är korrekta
- Testa att komma åt webbplatsen via webbläsaren
- Kontrollera att SSL-certifikatet fungerar korrekt

## 13. Felsökning

Om du stöter på problem:

1. Kontrollera Apache2-felloggar: `sudo tail -f /var/log/apache2/error.log`
2. Verifiera PHP-konfigurationen: `php -i`
3. Kontrollera databasanslutningen
4. Verifiera filrättigheter och ägare
5. Kontrollera att alla nödvändiga PHP-moduler är installerade

## 14. Säkerhetsåtgärder

För att säkerställa en säker installation:

- Uppdatera systemet regelbundet: `sudo apt update && sudo apt upgrade`
- Använd starka lösenord för alla användare och databaser
- Begränsa åtkomst till admin-panelen
- Säkerhetskopiera databasen regelbundet
- Använd SSL/TLS för all trafik
- Konfigurera en brandvägg (UFW):
```bash
sudo apt install ufw
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

## 15. Underhåll

För att hålla din installation uppdaterad:

1. Uppdatera Stimma regelbundet:
```bash
cd /var/www/Stimma
sudo git pull
```

2. Uppdatera systemet regelbundet:
```bash
sudo apt update
sudo apt upgrade
```

3. Kontrollera SSL-certifikatets status:
```bash
sudo certbot renew --dry-run
```

## Klart!

Din LAMP-stack är nu installerad och säkrad med Let's Encrypt. Av säkerhetsskäl bör du ta bort info.php-filen när du är klar med testningen:

```bash
sudo rm /var/www/html/info.php
```


