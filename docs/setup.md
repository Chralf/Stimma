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

## Klart!

Din LAMP-stack är nu installerad och säkrad med Let's Encrypt. Av säkerhetsskäl bör du ta bort info.php-filen när du är klar med testningen:

```bash
sudo rm /var/www/html/info.php
```


