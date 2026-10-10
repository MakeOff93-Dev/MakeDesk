![Alternativtext](https://media.discordapp.net/attachments/1144919102683615242/1531377850522013857/ChatGPT_Image_27._Juli_2026_21_08_25.png?ex=6aca8d43&is=6ac93bc3&hm=b32271a615a9c06bd49955af390c8ff47fda11d1e4e4e5102575ed9229f93c15&=&format=webp&quality=lossless)
# ModDesk

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
