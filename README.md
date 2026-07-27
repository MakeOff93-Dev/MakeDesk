# Twitch ModDesk

Ein deutschsprachiges Admin- und Moderationspanel für Twitch-Teams. Das Projekt läuft ohne PHP-Framework, verwendet PDO und speichert sämtliche dauerhaften Anwendungsdaten sowie Web-Sitzungen in MySQL.

## Enthaltene Funktionen

- Geschützter Login mit den vorkonfigurierten Rollen Owner, Administrator, Moderator und Nur Lesen
- Persönliches Benutzerkonto mit Namen, E-Mail, MySQL-Profilbild und sicherer Passwortänderung
- Kontoweise Zwei-Faktor-Authentifizierung mit TOTP und einmaligen Wiederherstellungscodes
- Vollständig granulare Einzelrechte für Seiten, Aktionen, Integrationen und Zusatzmodule
- Optionaler Google-reCAPTCHA-v2-Schutz für den klassischen Login
- Zusätzlicher Twitch-Login mit kontoweiser Verknüpfung
- Ideen-Board mit Status, Priorität, Zuständigkeit und Wunschtermin
- Teamnotizen mit Sichtbarkeit, Tags und Verknüpfung zu Twitch-Usern oder Ideen
- Geteilte Links und Ressourcen
- Twitch OAuth Authorization Code Flow mit CSRF-State
- Verschlüsselte Access- und Refresh-Tokens in MySQL
- Frei wählbarer Zielkanal: Kanalinhaber- oder Moderator-Konto verbinden
- Twitch-User-Suche und lokaler Profil-Cache
- Moderator- und Ban-Synchronisierung
- BanSync für mehrere Zielkanäle mit einem verbundenen Modkonto
- Kanalweiser Banlog mit Erfolgen, Teilfehlern und Twitch-Fehlermeldungen
- Verwarnung, Timeout, Ban, Unban, Shield Mode und Chat-Löschung
- Moderatorrollen hinzufügen/entfernen (Kanalinhaber-Token erforderlich)
- Blockierte Chatbegriffe verwalten
- Interne Moderationsfälle mit Schweregrad, Status und Zuständigkeit
- Zentrales Audit-Protokoll und internes Aktionsjournal
- Browserbasierter, nach erfolgreicher Einrichtung gesperrter PHP-Installer
- Zentrale Einstellungsseite für Twitch, Discord und SMTP
- Discord-Bot mit mehreren Servern, mehreren Channels je Server und mehreren Ziel-Channels pro Ereignis
- Übernahme erreichbarer Discord-Channels direkt über den gespeicherten Bot
- Discord-Benachrichtigungen für BanSync, Mod-Aktionen, Ideen und Mod-Fälle
- Discord Studio mit Live-Versand, visueller Vorschau, Emojis, Farben, Bildern, Icons, Feldern und Footer
- Wiederverwendbare Discord-Nachrichtenvorlagen in MySQL
- SMTP-Testversand mit STARTTLS, direktem SSL/TLS, AUTH LOGIN oder AUTH PLAIN
- Zustellprotokoll für Discord und E-Mail
- Ruhiges Dunkelgrau-/Rot-Design mit einheitlichen, tiefen Panel-Widgets
- Design-Editor für Logo, Farben, Header, Versionsanzeige, Footer und Navigation
- Änderbare Seitentitel und Zusatztexte je Seite
- Umschaltbare URL-Rewrites für klassische Query-URLs oder saubere Pfade
- News- und Ankündigungsmodul mit Entwürfen, Veröffentlichungszeitpunkt und Discord-Ausgabe
- Modulverwaltung zum Aktivieren, Deaktivieren und Bearbeiten eingebauter Funktionen
- Owner-geschützter Import vertrauenswürdiger PHP-Zusatzmodule als ZIP
- Panel-Migrator für ausstehende Datenbankmigrationen
- GitHub-Release-Prüfung mit Updatehinweis, Changelog und Ein-Klick-Installation
- Versions-Popup mit lokalem und verfügbarem Changelog sowie Versand an Discord
- Owner-geschützter ZIP-Update-Importer mit Paketprüfung, Dateisicherung und Datenbankmigration
- Immer bedienbare Update-Dateiauswahl mit PHP-/ZIP-/Limit-Vorprüfung und sichtbarem Dateinamen
- Automatische Vollbackups von MySQL, `.env` und Projektdateien vor Updates und Migrationen
- Backup-Zentrale mit Download, Wiederherstellung und Update-Rollback
- Datenbankgestützte Rollen mit gruppierten, einzeln schaltbaren Berechtigungen
- Passwortbestätigung für Updates, Migrationen, Rollbacks, Rollen- und Moduländerungen
- Sicherheitsbereich mit Systemprüfungen, Ereignissen, IP-Sperren und aktiven Sitzungen
- Direkter Apache-/XAMPP-Einstieg im Projektordner ohne `/public` in der sichtbaren App-Adresse
- Responsive Oberfläche für Desktop, Tablet und Smartphone

## Installation auf einem Windows-Laptop

Geeignet sind beispielsweise XAMPP oder WampServer mit PHP 8.2+ und MySQL/MariaDB.

1. ZIP entpacken, beispielsweise nach `C:\xampp\htdocs\twitch-moddesk`.
2. In XAMPP Apache und MySQL starten.
3. Im Browser öffnen:

   ```text
   http://localhost/twitch-moddesk/install.php
   ```

4. Der Installer prüft PHP, Erweiterungen und Schreibrechte. Für eine normale lokale XAMPP-Installation sind die MySQL-Werte meist:

   ```text
   Host: 127.0.0.1
   Port: 3306
   Benutzer: root
   Passwort: leer, sofern nicht selbst gesetzt
   ```

5. Owner-Zugang anlegen. Twitch, Discord und SMTP können sofort oder später unter „Einstellungen“ eingetragen werden.
6. Nach erfolgreicher Installation wird der Installer automatisch gesperrt und leitet zum Login unter `http://localhost/twitch-moddesk/` weiter.

Wenn noch keine `.env` existiert, öffnet das ModDesk den Installer beim ersten Aufruf automatisch. Die Dateien `index.php` und `install.php` im Projektstamm ermöglichen unter Apache/XAMPP die saubere Adresse ohne `/public`. Die mitgelieferte `.htaccess` sperrt Anwendungs-, Datenbank-, Backup- und Konfigurationsdateien.

Für einen öffentlich erreichbaren Produktivserver bleibt ein Webserver-Document-Root direkt auf `public/` die sicherste Konfiguration. Dort funktioniert ModDesk weiterhin ohne Änderungen und ebenfalls ohne `/public` in der sichtbaren URL.

## Schnellstart mit Docker

Voraussetzungen: Docker mit Compose-Unterstützung.

1. Konfiguration anlegen:

   ```bash
   cp .env.example .env
   ```

2. In `.env` mindestens `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD` und `APP_KEY` ändern. Einen APP_KEY kannst du so erzeugen:

   ```bash
   php bin/generate-key.php
   ```

3. Container starten:

   ```bash
   docker compose up -d --build
   ```

4. Den ersten Owner-Zugang interaktiv erstellen:

   ```bash
   docker compose exec app php bin/install.php
   ```

5. `http://localhost:8080` öffnen und anmelden.

## Twitch-App einrichten

1. In der [Twitch Developer Console](https://dev.twitch.tv/console/apps) eine Anwendung registrieren.
2. Als OAuth Redirect URL exakt die Adresse aus `TWITCH_REDIRECT_URI` eintragen. Lokal ist das standardmäßig:

   ```text
   http://localhost:8080/?page=twitch-callback
   ```

3. Client-ID, neu erzeugtes Client-Secret und Redirect-URI unter „Einstellungen → Twitch-API“ eintragen. Das Client-Secret wird verschlüsselt in MySQL gespeichert. Die entsprechenden `.env`-Felder bleiben als Fallback nutzbar.

4. Bei HTTPS zusätzlich setzen:

   ```dotenv
   APP_URL=https://deine-domain.de
   SESSION_SECURE=true
   ```

5. Im Panel unter „Twitch-Zentrale“ auswählen, ob ein Kanalinhaber- oder Moderator-Konto verbunden wird. Der Moderator-Modus fordert bewusst weniger OAuth-Rechte an.

Für BanSync muss das verbundene Konto auf jedem Zielkanal Broadcaster oder Moderator sein. Die Kanäle selbst müssen weder Passwörter noch eigene OAuth-Tokens an das ModDesk weitergeben.

Das Panel verwendet den serverseitigen Authorization Code Flow. Twitch verlangt bei Anwendungen mit OAuth-Sitzung außerdem eine regelmäßige Token-Validierung. Richte deshalb stündlich diesen Cronjob ein:

```cron
0 * * * * cd /pfad/zu/twitch-moddesk && /usr/bin/php bin/validate-twitch.php
```

Bei Docker kann ein externer Cronjob folgenden Befehl aufrufen:

```bash
docker compose exec -T app php bin/validate-twitch.php
```

## Benutzerkonto, Zwei-Faktor, reCAPTCHA und Twitch-Login

Jeder angemeldete Benutzer besitzt unter „Mein Benutzerkonto“ einen eigenen Bereich. Dort lassen sich Benutzername, Anzeigename, E-Mail, Profilbild und Passwort ändern. Profil- und Passwortänderungen verlangen das aktuelle Passwort; nach einer Passwortänderung beendet ModDesk alle weiteren Sitzungen. Avatare werden als geprüfte PNG-, JPG- oder WebP-Datei bis 3 MB in MySQL gespeichert.

Unter „Mein Benutzerkonto → Zwei-Faktor-Schutz“ kann jeder Benutzer einen zeitbasierten Authenticator-Code einrichten. Der angezeigte Einrichtungsschlüssel wird manuell in einer TOTP-kompatiblen Authenticator-App eingetragen und anschließend mit einem sechsstelligen Code bestätigt. Der Schlüssel wird mit `APP_KEY` verschlüsselt in MySQL gespeichert. Danach erzeugt ModDesk zehn einmalig verwendbare Wiederherstellungscodes; serverseitig werden nur deren Passwort-Hashes gespeichert. Bei aktivem Schutz muss sowohl nach einer klassischen Passwortanmeldung als auch nach einer Twitch-Anmeldung ein Authenticator- oder Wiederherstellungscode bestätigt werden.

Für Google reCAPTCHA v2:

1. Einen Schlüssel vom Typ „Ich bin kein Roboter“-Checkbox für den Hostnamen der Installation anlegen.
2. Unter „Einstellungen → Authentifizierung“ Site-Key und Secret-Key eintragen.
3. reCAPTCHA aktivieren und speichern. Der Secret-Key wird verschlüsselt gespeichert; die Prüfung erfolgt serverseitig bei jedem klassischen Passwort-Login.

Für die Twitch-Anmeldung wird dieselbe Twitch-Anwendung verwendet, aber eine eigene Callback-Adresse. Die unter „Einstellungen → Authentifizierung“ angezeigte Login Redirect-URI muss zusätzlich und exakt in der Twitch Developer Console registriert werden, beispielsweise:

```text
http://localhost/twitch-moddesk/?page=twitch-login-callback
```

Danach Twitch-Login aktivieren. Jeder Benutzer verbindet sein persönliches Twitch-Konto nach Bestätigung des aktuellen Passworts einmal unter „Mein Benutzerkonto“. Ein Twitch-Konto kann nur einem aktiven ModDesk-Zugang zugeordnet werden. Der klassische Login bleibt parallel verfügbar; ModDesk verwendet den angeforderten Scope `user:read:email` nur für die Identitätsabfrage und widerruft den kurzzeitig erhaltenen Twitch-Token unmittelbar danach, statt ihn in der Datenbank zu speichern.

## Rollen und Einzelrechte

Die Rollen Owner, Administrator, Moderator und Nur Lesen sind vorinstalliert. Owner besitzt unveränderlichen Vollzugriff. Alle anderen Rollen – auch selbst angelegte – können unter „Rollen & Rechte“ pro Seite und Aktion konfiguriert werden. Dazu gehören getrennte Rechte für Anzeigen, Erstellen, Bearbeiten, Archivieren, Senden, Konfigurieren, Installieren, Rollback, Migrationen, Backups, Sitzungen und IP-Sperren.

Mit „Alle einschalten/ausschalten“ lassen sich sämtliche Rechte einer Rolle setzen; dieselben Schalter stehen auch je Kategorie bereit. Technische Alt-Rechte aus früheren Versionen erscheinen nicht im Editor, werden intern aber so lange mitgeführt, wie alte Handler sie für abwärtskompatible Updates benötigen. Hochgeladene Zusatzmodule erzeugen automatisch je ein Recht zum Ansehen und Ausführen.

## Kanalinhaber oder Moderator verbinden?

| Funktion | Verbundenes Moderator-Konto | Verbundenes Kanalinhaber-Konto |
| --- | ---: | ---: |
| User suchen | Ja | Ja |
| Verwarnen, Timeout, Ban, Unban | Ja, wenn Twitch-Mod | Ja |
| BanSync über mehrere Kanäle | Ja, auf allen moderierten Kanälen | Ja, eigener und zusätzlich moderierte Kanäle |
| Shield Mode, Chat löschen, blockierte Begriffe | Ja, wenn Twitch-Mod | Ja |
| Moderatorliste synchronisieren | Nein | Ja |
| Moderatorrolle hinzufügen/entfernen | Nein | Ja |

Nach dem Verbinden lässt sich der tatsächlich betreute Kanal unabhängig vom verbundenen Konto festlegen. Damit kann ein Hauptmoderator sein eigenes Twitch-Konto verbinden und beispielsweise den Kanal seines Streamers auswählen.

## Discord-Bot einrichten

1. Im [Discord Developer Portal](https://discord.com/developers/applications) eine Anwendung und darin einen Bot anlegen.
2. Application-ID und Bot-Token unter „Einstellungen → Discord“ eintragen. Der Token wird verschlüsselt gespeichert und später nicht mehr angezeigt.
3. Über den im ModDesk erzeugten Einladungslink den Bot zum gewünschten Server hinzufügen. Der Link fordert `View Channel`, `Send Messages`, `Embed Links` und `Send Messages in Threads` an.
4. In Discord den Entwicklermodus aktivieren und die Server-ID kopieren.
5. Im ModDesk den Server hinzufügen und „Channels von Discord abrufen“ wählen. Alternativ können Channelname und Channel-ID manuell eingetragen werden.
6. Pro Channel die gewünschten Ereignisse markieren. Dasselbe Ereignis darf gleichzeitig an mehrere Channels und Server gesendet werden.
7. Einen Test-Channel auswählen und „Speichern & testen“ ausführen.

Unterstützte Routen:

- BanSync-Ergebnis
- Twitch-Modaktion
- neue Idee
- neuer Moderationsfall
- veröffentlichte News
- Versions-Changelog

Der Bot sendet ausschließlich über die Discord REST API und liest keine Discord-Nachrichten. Ein Message-Content-Intent ist daher nicht erforderlich. Fehler und erfolgreiche Zustellungen werden in MySQL protokolliert und beeinflussen die eigentliche Moderationsaktion nicht.

## Discord Studio und Live-Nachrichten

Unter „Discord Studio“ können Admins und Owner eine Nachricht gestalten und direkt mit dem gespeicherten Bot versenden. Der Editor unterstützt:

- normalen Nachrichtentext und Discord-Markdown
- Unicode-Emoji-Auswahl
- Embed-Titel, Beschreibung, Farbe und Titel-Link
- Autorname mit Link und Icon
- Thumbnail und großes Bild über öffentliche HTTPS-Adressen
- bis zu 25 Felder, wahlweise nebeneinander
- Footer mit Icon und optionalen Zeitstempel
- Speichern, Bearbeiten und Löschen wiederverwendbarer Vorlagen
- Auswahl bereits eingerichteter Ereignis-Channels oder freie Channel-ID

Erwähnungen werden beim Versand absichtlich nicht automatisch aufgelöst. So löst ein versehentlich eingetragener Text wie `@everyone` keine Massenbenachrichtigung aus. Für normale Textkanäle benötigt der Bot `View Channel`, `Send Messages` und `Embed Links`; für Threads zusätzlich `Send Messages in Threads`.

## Design- und Inhaltseditor

Das Grunddesign verwendet eine ruhige dunkle Oberfläche mit roten Akzenten, neutralen Grautönen, konsistenten Karten und abgestimmten Widgets. Der „Design-Editor“ speichert alle Einstellungen in MySQL. Einstellbar sind App-Name, eigenes Rasterlogo, sieben Grundfarben, Headerzeile, Versionsanzeige, Footertext, Beschriftung/Symbol/Reihenfolge/Sichtbarkeit aller Menüpunkte sowie Titel und Zusatztext der einzelnen Seiten.

Logo-Uploads sind auf geprüfte PNG-, JPG- und WebP-Dateien bis 2 MB begrenzt. Frei eingegebenes HTML, JavaScript oder CSS wird nicht ausgeführt; dadurch kann das Design angepasst werden, ohne die Schutzmechanismen des Panels zu umgehen. Das Logo wird als Binärdatensatz in MySQL gespeichert und bleibt dadurch gemeinsam mit dem restlichen ModDesk-Datenbestand erhalten.

## URL-Rewrites

Unter „Einstellungen → Allgemein“ kann zwischen URLs wie `/?page=news` und sauberen Pfaden wie `/news` gewechselt werden. Für saubere Pfade benötigt Apache `mod_rewrite` und `AllowOverride All`; die passenden `.htaccess`-Regeln liegen sowohl im Projektstamm als auch unter `public/` bei. Wird nginx verwendet, muss dessen Fallback auf `index.php` entsprechend eingerichtet werden.

Die öffentliche App-URL muss weiterhin den vollständigen Installationspfad enthalten, beispielsweise `http://localhost/twitch-moddesk`. Nach einem Wechsel der Domain oder des Pfads muss auch die bei Twitch registrierte OAuth Redirect-URI geprüft werden.

## Module und Zusatzmodule

Entsprechend berechtigte Rollen verwalten unter „Module“ die eingebauten Bereiche News, Ideen, Notizen, Links, Twitch, BanSync, Moderationsfälle, Discord, Team, Design und Audit. Ein deaktiviertes Modul verschwindet aus Navigation und Panel-Routen; seine MySQL-Daten werden nicht gelöscht. Über „Bearbeiten“ gelangt man zu den jeweiligen normalen Einstellungen.

Zusatzmodule werden als ZIP mit einer `module.json` im Paketstamm oder in genau einem gemeinsamen Unterordner hochgeladen. Ein minimales Manifest sieht so aus:

```json
{
  "key": "stream-plan",
  "name": "Stream-Plan",
  "description": "Interne Streamplanung",
  "version": "1.0.0",
  "entry": "page.php",
  "navigation": {"label": "Stream-Plan", "icon": "◫", "order": 210},
  "settings": [
    {"key": "calendar_url", "label": "Kalender-URL", "type": "url", "default": ""}
  ]
}
```

Unterstützte Einstellungstypen sind `text`, `url`, `number`, `boolean`, `select` und `password`. Optionale SQL-Dateien unter `migrations/*.sql` werden nach Dateiname einmalig ausgeführt. Die Einstiegsdatei erhält die Variablen `$moduleKey`, `$moduleSettings` und die normalen ModDesk-Helfer. Statische CSS-, JavaScript- und Bilddateien werden mit `module_asset_url($moduleKey, 'assets/datei.css')` eingebunden.

Für eigene Formularaktionen enthält das Formular `csrf_field()` sowie die Felder `action=module-action`, `module_key` und einen frei wählbaren, kleingeschriebenen `module_action`-Namen. Bei diesem POST wird dieselbe Einstiegsdatei mit `$isModulePost = true` und `$moduleAction` aufgerufen; sie kann validieren, in MySQL speichern, Meldungen setzen und selbst weiterleiten. Ohne eigene Weiterleitung kehrt ModDesk zur Modulseite zurück.

Ein PHP-Modul läuft mit denselben Serverrechten wie ModDesk und kann Datenbank sowie entschlüsselte Einstellungen erreichen. Deshalb dürfen ausschließlich selbst erstellte oder vollständig vertrauenswürdige Modul-ZIPs installiert werden.

Jedes installierte Zusatzmodul legt die beiden Einzelrechte „ansehen“ und „verwenden“ an. Bei bereits vorhandenen Modulen übernimmt Migration `007_profiles_auth_permissions.sql` zunächst den bisherigen Zugriff aller Rollen; anschließend können diese Rechte im Rollen-Editor einzeln abgeschaltet werden.

## SMTP einrichten

Unter „Einstellungen → SMTP-Server“ werden Host, Port, Verschlüsselung, Anmeldemethode, Benutzer, Passwort und Absender hinterlegt. Unterstützt werden:

- STARTTLS, üblicherweise Port 587
- direktes SSL/TLS, üblicherweise Port 465
- unverschlüsselte Verbindung für ausdrücklich dafür vorgesehene lokale Testserver
- AUTH LOGIN, AUTH PLAIN oder ein Server ohne Anmeldung

Mit „Speichern & testen“ wird sofort eine Testmail versendet. Zertifikate werden geprüft; selbstsignierte Zertifikate werden aus Sicherheitsgründen nicht automatisch akzeptiert.
Bei Konten mit Zwei-Faktor-Anmeldung wird häufig ein App-Passwort benötigt. Reine OAuth2-SMTP-Konten werden von dieser Version noch nicht unterstützt.

## BanSync einrichten

1. Unter „Twitch-Zentrale“ dein eigenes Twitch-Konto als Moderator verbinden.
2. Unter „BanSync“ deinen Kanal und den zweiten Kanal, beispielsweise `dragoras07`, hinzufügen.
3. Auf beiden Kanälen muss das verbundene Konto Moderator oder Broadcaster sein.
4. „Mod-Rechte prüfen“ ausführen. Falls der Scope `user:read:moderated_channels` fehlt, das Twitch-Konto einmal neu verbinden.
5. Twitch-Benutzernamen, Aktion, Begründung und Zielkanäle auswählen und die Sicherheitsabfrage bestätigen.

Jeder Kanal wird einzeln angesprochen. Schlägt ein Kanal fehl, laufen die übrigen ausgewählten Kanäle weiter. Das Ergebnis wird je Kanal mit HTTP-Status und Twitch-Fehlermeldung gespeichert; erfolgreiche Bans werden bei einem späteren Fehler nicht automatisch aufgehoben.

## Upgrade von Version 1.6 auf 1.7

Das vollständige 1.7-ZIP unter „Einstellungen → Updates“ auswählen und installieren. Der Updater erstellt vorher automatisch ein Vollbackup, behält `.env`, MySQL-Daten, Profile, Einstellungen, Branding und eigene Module und führt anschließend `008_two_factor_redesign.sql` aus.

Die Migration ergänzt ausschließlich die Zwei-Faktor-Tabellen. Bei den Designwerten werden nur Farben ersetzt, die noch exakt den alten lila Standardwerten entsprechen; eigene Farbanpassungen bleiben unverändert. Bei einem manuellen Upgrade die Dateien über die vorhandene Installation kopieren, `.env`, `storage/` und eigene `modules/` behalten und danach im Panel alle offenen Migrationen ausführen. Alternativ:

```bat
C:\xampp\php\php.exe bin\migrate.php
```

## Upgrade von Version 1.5.1 auf 1.6

Das vollständige 1.6-ZIP unter „Einstellungen → Updates“ auswählen und installieren. Der Updater erstellt vorher automatisch ein Vollbackup, behält `.env`, MySQL-Daten, Profil- und Brandingdaten sowie eigene Module und führt anschließend `007_profiles_auth_permissions.sql` aus.

Bei einer manuellen Aktualisierung die neuen Dateien über die vorhandene Installation kopieren, `.env`, `storage/` und eigene `modules/` behalten und danach als Owner „Einstellungen → System“ öffnen. Dort Migration `007_profiles_auth_permissions.sql` ausführen. Alternativ:

```bat
C:\xampp\php\php.exe bin\migrate.php
```

Die Migration ist wiederholbar, solange sie nach einem Verbindungsabbruch noch nicht als abgeschlossen eingetragen wurde. Sie übernimmt die bisherigen Fähigkeiten von Administrator, Moderator, Nur Lesen und eigenen Rollen in die neuen Einzelrechte, ohne Benutzer oder Einstellungen zu ersetzen.

## Upgrade von Version 1.3 oder 1.4 auf 1.5

Version 1.5 behebt den blockierten Datei-Button im Update-Importer. In Version 1.4 war das `<input type="file">` vollständig `disabled`, sobald entweder `ZipArchive` fehlte oder die Update-Migration nicht bereit war. Ein deaktiviertes Browserfeld lässt sich nicht anklicken. Ab 1.5 bleibt die Dateiauswahl immer aktiv; fehlende Voraussetzungen erscheinen separat und stoppen erst die Installation mit einer verständlichen Meldung.

Wenn der Datei-Button in deiner installierten 1.3- oder 1.4-Version nicht anklickbar ist, kann genau diese Version das 1.5-ZIP nicht selbst auswählen. Nutze einmalig einen dieser Wege:

1. In `C:\xampp\php\php.ini` die Zeile `;extension=zip` zu `extension=zip` ändern, Apache neu starten und die offene Migration in ModDesk ausführen. Danach das 1.5-ZIP normal hochladen.
2. Alternativ die Dateien aus dem 1.5-ZIP einmal manuell über die vorhandenen Dateien kopieren. Die vorhandene `.env`, `storage/` und eigene `modules/` dabei behalten. Anschließend im Panel unter „Einstellungen → System“ alle offenen Migrationen ausführen. Von 1.3 sind dies `005_modules_news_discord_github.sql` und `006_security_backups_roles.sql`, von 1.4 nur Migration `006`.

Für größere Pakete in `php.ini` mindestens diese Werte verwenden und Apache danach neu starten:

```ini
file_uploads=On
upload_max_filesize=64M
post_max_size=70M
extension=zip
```

Der neue Migrator legt vor `006_security_backups_roles.sql` automatisch ein Vollbackup an. Nach dem Upgrade stehen „Backups“, „Sicherheit“ und „Rollen & Rechte“ als neue Bereiche bereit.

## Upgrade von Version 1.3 auf 1.4

Am einfachsten wird das vollständige 1.4-ZIP in Version 1.3 unter „Einstellungen → Update-Importer“ hochgeladen. Der Importer sichert die zu ersetzenden Dateien, behält `.env`, MySQL-Daten und `storage/` bei und führt anschließend automatisch `005_modules_news_discord_github.sql` aus.

Wenn die Dateien manuell ersetzt werden, `.env` und `storage/` nicht überschreiben. Danach als Owner „Einstellungen → System → Migrationen im Panel ausführen“ öffnen. Alternativ funktioniert weiterhin:

```bash
php bin/migrate.php
```

Die Migration übernimmt vorhandene Discord-Routen in die neue Mehrserver-/Mehrchannel-Struktur. Bestehende Benutzer, Twitch-Verbindungen, Banlogs, Ideen, Notizen, Links, Fälle, Branding, SMTP- und Discord-Zugangsdaten bleiben erhalten. Der Installer wird für ein Upgrade nicht erneut ausgeführt.

## Upgrade von Version 1.2 auf 1.3

Nach dem Ersetzen der Projektdateien die neue Datenbankmigration ausführen:

```bash
php bin/migrate.php
```

Bei Docker:

```bash
docker compose exec app php bin/migrate.php
```

Unter Windows/XAMPP kann die Migration aus dem Projektordner so gestartet werden:

```bat
C:\xampp\php\php.exe bin\migrate.php
```

Bestehende Ideen, Notizen, Nutzer, Moderationsfälle, Twitch-Verbindungen und Discord-Routen bleiben erhalten. Die Migration `004_branding_discord_studio_updates.sql` ergänzt die Logoablage, Discord-Vorlagen und den Updateverlauf.

Für dieses erste Upgrade auf 1.3 ist der einmalige Konsolenbefehl erforderlich. Ab Version 1.3 können spätere vollständige ModDesk-Pakete unter „Einstellungen → Update-Importer“ hochgeladen werden.

## ZIP-Update-Importer

Der Importer ist nur für Rollen mit den Rechten „Updates ansehen“ und „Updates installieren“ verfügbar und akzeptiert ausschließlich ein vollständiges ModDesk-ZIP mit `moddesk-update.json`, passender Produktkennung und einer höheren Versionsnummer. Vor dem Austausch werden das Paket und alle überschriebenen Dateien unter `storage/update-backups` gesichert. `.env`, MySQL-Daten, `storage/`, hochgeladene Zusatzmodule und damit auch das gespeicherte Logo bleiben unverändert; anschließend werden neue Datenbankmigrationen automatisch ausgeführt.

Falls XAMPP „ZIP fehlt“ meldet, in `C:\xampp\php\php.ini` die Zeile `extension=zip` aktivieren und Apache neu starten. Der Projektordner muss für den Apache-Benutzer beschreibbar sein.

Update-ZIPs können PHP-Code ersetzen und müssen deshalb aus einer vertrauenswürdigen Quelle stammen. Der aktuelle Importer prüft Struktur, Version, Pfade, symbolische Links, Größenlimits und Produkttyp, enthält aber noch keine kryptografische Herausgebersignatur.

Bei Docker-Deployments sollte das Container-Image weiterhin regulär neu gebaut werden; ein direkt im laufenden Container importiertes Update geht beim Neuaufbau des Containers verloren.

Für ein Upgrade wird der Installer nicht erneut ausgeführt.

## GitHub-Updates

Unter „Einstellungen → GitHub“ wird das Repository als `owner/repository` hinterlegt. ModDesk prüft den neuesten veröffentlichten GitHub-Release und sucht darin exakt nach dem konfigurierten ZIP-Asset, standardmäßig `twitch-moddesk.zip`. Der Release-Tag muss wie `v1.4.1` oder `1.4.1` aufgebaut und die Paketversion höher als die installierte Version sein.

Für öffentliche Repositories ist kein Token nötig. Für private Repositories kann ein Fine-grained Token mit lesendem Zugriff hinterlegt werden; es wird verschlüsselt in MySQL gespeichert. Nach Ablauf des eingestellten Intervalls erfolgt beim Aufruf des Owner-Dashboards eine Prüfung. Ein gefundenes Update erscheint als Hinweis sowie im Versions-Popup und kann nach erneuter Paketprüfung mit einem Klick installiert werden.

Automatische Updates reagieren bewusst auf veröffentlichte Releases mit vollständigem ZIP-Asset, nicht auf jeden Commit oder Branch-Push. Dadurch besitzt jede installierbare Version einen eindeutigen Tag, Changelog und reproduzierbares Paket. Vor dem Dateiaustausch legt ModDesk eine Sicherung unter `storage/update-backups` an und behält Installation, `.env`, Datenbank, Uploads sowie Zusatzmodule bei.

## Installation ohne Docker

Benötigt werden:

- PHP 8.2 oder neuer
- MySQL 8 oder kompatibles MariaDB
- PHP-Erweiterungen `pdo_mysql`, `curl`, `mbstring` und `openssl`
- für den Web-Updater zusätzlich die PHP-Erweiterung `zip`
- Apache/XAMPP mit aktiviertem `.htaccess` oder nginx mit Document Root auf `public/`

Vorgehen:

1. Unter Apache/XAMPP direkt `install.php` im Projektstamm öffnen; der Installer erzeugt `.env`, Datenbanktabellen und Owner.
2. Alternativ `.env.example` nach `.env` kopieren, eine UTF-8-Datenbank anlegen und `php bin/install.php` ausführen.
3. Für einen Produktivserver den Document Root vorzugsweise auf den Ordner `public/` setzen. Bei XAMPP schützt die mitgelieferte Root-`.htaccess` den Projektstamm.
4. Für den Produktivbetrieb HTTPS aktivieren und `SESSION_SECURE=true` setzen.

## Datenhaltung

MySQL enthält unter anderem:

- lokale Teamzugänge und datenbankbasierte Sessions
- Benutzerprofile, geprüfte Profilbilder und verknüpfte Twitch-Identitäten
- Rollen, granulare Einzelrechte und rollenspezifische Zuweisungen
- Ideen, Notizen, Links und Moderationsfälle
- verschlüsselte Twitch-Tokens und ausgewählten Zielkanal
- Twitch-Profilcache, Moderator- und Ban-Status
- Moderationsaktionen, API-Ergebnisse und Audit-Ereignisse
- BanSync-Zielkanäle, Sammelvorgänge und Einzelergebnisse je Kanal
- verschlüsselte Twitch-, Discord- und SMTP-Geheimnisse
- Discord-Ereignisrouten und Integrations-Zustellprotokolle
- Discord-Server, Channels und Mehrfachzuordnungen je Ereignis
- Discord-Nachrichtenvorlagen
- News, Modulstatus, Modulkonfigurationen und ausgeführte Modulmigrationen
- Branding-, Menü-, Seiten- und Farbanpassungen
- Verlauf eingespielter Systemupdates und zwischengespeicherter GitHub-Release-Status

Datenbankpasswort und APP_KEY bleiben in `.env` und gehören niemals in Git. Twitch Client-Secret, Discord Bot-Token und SMTP-Passwort werden mit dem APP_KEY verschlüsselt in MySQL gespeichert.

## Sicherheitshinweise

- Vor dem öffentlichen Betrieb alle Beispielpasswörter und `APP_KEY` ändern.
- Nur über HTTPS betreiben.
- Produktiv möglichst nur `public/` als Document Root freigeben; bei XAMPP muss `AllowOverride All` aktiv sein, damit die Root-`.htaccess` greift.
- Datenbank und Backups verschlüsseln und den Zugriff auf das notwendige Team begrenzen.
- Update-ZIPs nur aus vertrauenswürdiger Quelle hochladen und Sicherungsordner regelmäßig außerhalb des Webservers sichern.
- Moderationsaktionen werden unmittelbar bei Twitch ausgeführt. Rechte deshalb sparsam vergeben.
- Bot-Token niemals in Discord-Nachrichten, Screenshots oder Support-Chats veröffentlichen; bei Verdacht im Developer Portal sofort erneuern.
- Das Panel speichert keine Chatverläufe. Dafür wäre eine zusätzliche EventSub-Anbindung mit eigener Aufbewahrungs- und Datenschutzregel nötig.

## Offizielle Twitch-Dokumentation

- [OAuth Access Tokens](https://dev.twitch.tv/docs/authentication/getting-tokens-oauth/)
- [Token-Validierung](https://dev.twitch.tv/docs/authentication/validate-tokens/)
- [API-Referenz](https://dev.twitch.tv/docs/api/reference/)
- [Berechtigungen und Scopes](https://dev.twitch.tv/docs/authentication/scopes/)

## Offizielle Discord-Dokumentation

- [Create Message](https://docs.discord.com/developers/resources/message#create-message)
- [OAuth2 Bot Scope](https://docs.discord.com/developers/topics/oauth2#shared-resources-oauth2-scopes)
- [Rate Limits](https://docs.discord.com/developers/topics/rate-limits)

## Offizielle GitHub-Dokumentation

- [REST API – Releases](https://docs.github.com/en/rest/releases/releases)
- [REST API – Release Assets](https://docs.github.com/en/rest/releases/assets)
- [Authentifizierung der REST API](https://docs.github.com/en/rest/authentication/authenticating-to-the-rest-api)
