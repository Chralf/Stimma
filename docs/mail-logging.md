# Mailloggning i Stimma

## Översikt

Stimma har ett omfattande mailloggning-system som spårar alla utgående e-postmeddelanden från systemet. Alla mailloggar sparas i den befintliga `logs`-tabellen utan att behöva skapa nya tabeller eller mappar.

## Vad som loggas

### E-posttyper som spåras:
- **Inloggningstoken** - E-postmeddelanden med inloggningslänkar
- **Påminnelser** - Automatiska påminnelser från cron-jobb
- **Systemmeddelanden** - Alla andra utgående e-postmeddelanden

### Logginformation inkluderar:
- Måladress och avsändaradress
- E-postämne
- Meddelandelängd
- Tidsstämpel
- Användarinformation
- Serverresponser vid fel
- Specifik feltyp (anslutning, autentisering, leverans, etc.)

## Loggtyper och status

### Lyckade e-postmeddelanden:
- `mail_send_success` - E-post skickat framgångsrikt
- `login_token_sent` - Inloggningstoken skickat
- `reminder_sent` - Påminnelse skickat
- `cron_mail_send_success` - Cron e-post skickat

### Misslyckade e-postmeddelanden:
- `mail_send_failed` - E-post misslyckades
- `login_token_failed` - Inloggningstoken misslyckades
- `reminder_failed` - Påminnelse misslyckades
- `cron_mail_send_failed` - Cron e-post misslyckades

### Feltyper som loggas:
- `connection_failed` - Kunde inte ansluta till SMTP-server
- `invalid_server_response` - Ogiltig serverhälsning
- `authentication_failed` - Autentisering misslyckades
- `from_command_failed` - FROM-kommando misslyckades
- `to_command_failed` - TO-kommando misslyckades
- `data_command_failed` - DATA-kommando misslyckades
- `delivery_failed` - Leverans misslyckades

## Teknisk implementation

### Filer som påverkas:
- `html/include/mail.php` - Huvudfunktionen för e-postutskick
- `html/include/auth.php` - Inloggningstoken-funktionen
- `html/cron/send_reminders.php` - Cron-jobb för påminnelser
- `html/admin/logs.php` - Admin-vy för att visa loggar

### Loggfunktion:
Alla mailloggar använder den befintliga `logActivity()` funktionen som sparar data i `logs`-tabellen med följande struktur:
- `email` - Måladress
- `message` - Detaljerat loggmeddelande
- `created_at` - Tidsstämpel
- Kontextdata sparas som JSON i meddelandet

## Admin-vy

### Förbättringar i logs-sidan:
- **Typ-kolumn** - Visar om det är E-post, Inloggning, AI, etc.
- **Status-kolumn** - Visar om e-post lyckades eller misslyckades med färgkodade badges
- **Detaljerad felinformation** - För misslyckade e-postmeddelanden

### Statusbadges:
- 🟢 **Skickat** - E-post levererat framgångsrikt
- 🟡 **Skickas** - E-post påbörjat
- 🔴 **Misslyckades** - E-post misslyckades

## Felsökning

### Vanliga problem:
1. **SMTP-anslutning misslyckas** - Kontrollera `MAIL_HOST` och `MAIL_PORT` i `.env`
2. **Autentisering misslyckas** - Kontrollera `MAIL_USERNAME` och `MAIL_PASSWORD`
3. **Leverans misslyckas** - Kontrollera mål-e-postadresser och serverinställningar

### Loggning för felsökning:
Alla fel loggas med detaljerad information inklusive:
- Serverresponser
- Feltyper
- Anslutningsdetaljer
- Tidsstämplar

## Säkerhet

### Dataskydd:
- E-postadresser loggas för spårning
- Meddelandeinnehåll loggas inte (endast längd)
- Känslig information som lösenord loggas aldrig

### GDPR-efterlevnad:
- Loggar kan rensas enligt dataskyddsregler
- Användare kan begära radering av sina loggar
- Loggar sparas endast så länge som nödvändigt

## Underhåll

### Loggrensning:
För att hålla loggarna hanterbara rekommenderas regelbunden rensning:

```sql
-- Rensa loggar äldre än 90 dagar
DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Rensa endast mailloggar äldre än 30 dagar
DELETE FROM logs WHERE message LIKE '%E-post%' AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

### Övervakning:
- Kontrollera regelbundet för misslyckade e-postmeddelanden
- Övervaka SMTP-serverstatus
- Följ upp användarfeedback om saknade e-postmeddelanden

## Konfiguration

### Miljövariabler för e-post:
```env
MAIL_HOST=your_smtp_server
MAIL_PORT=465
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Stimma"
```

### Loggningsinställningar:
Loggning aktiveras automatiskt när mailloggning är implementerat. Inga ytterligare inställningar krävs.
