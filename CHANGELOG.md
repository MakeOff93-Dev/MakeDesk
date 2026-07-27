# Twitch ModDesk – Changelog

## 1.5.1 – MariaDB-Kompatibilitätsfix

- Die in MariaDB reservierte Spaltenbezeichnung `sensitive` wird in Migration `006_security_backups_roles.sql` und der Rollenabfrage korrekt maskiert.
- Eine nach dem bisherigen SQL-Fehler abgebrochene Migration `006` kann anschließend gefahrlos erneut ausgeführt werden; bereits angelegte Tabellen bleiben erhalten.

## 1.5.0 – Sichere Updates, Backups und Rollen

- Der bisher deaktivierte Datei-Button im Update-Importer wurde grundlegend korrigiert: Die Dateiauswahl bleibt unabhängig von ZIP-Erweiterung und Migrationsstatus bedienbar.
- Neue Upload-Vorprüfung für PHP-Dateiuploads, ZIP-Erweiterung, Datenbankstatus, Schreibrechte sowie `upload_max_filesize` und `post_max_size`.
- Dateiname und Größe des ausgewählten Update- oder Modul-ZIPs werden direkt im Formular angezeigt.
- Automatisches Vollbackup von MySQL, `.env` und Projektdateien vor jedem Update und jeder Migration.
- Eigener Backup-Bereich zum Erstellen, Herunterladen, Wiederherstellen und Löschen von Sicherungen.
- Ein-Klick-Rollback für Updates, die bereits mit dem neuen Vollbackup-System installiert wurden.
- Der Panel-Migrator zeigt jede Migration samt Prüfsumme und Status und kann die nächste Migration einzeln oder alle offenen Migrationen in Reihenfolge ausführen.
- Rollen und Berechtigungen werden aus MySQL geladen; eigene Rollen können mit detaillierten Rechten erstellt und bearbeitet werden.
- Der geschützte Hauptadministrator kann weder deaktiviert noch herabgestuft werden.
- Updates, Rollbacks, Migrationen, Modulinstallationen, Rollenänderungen und Wiederherstellungen verlangen das aktuelle Passwort.
- Neuer Sicherheitsbereich mit Systemprüfungen, Sicherheitsereignissen, IP-Sperren und Verwaltung aktiver Sitzungen.
- Automatische temporäre IP-Sperre nach fünf fehlgeschlagenen Loginversuchen innerhalb von 15 Minuten.
- Audit- und Sicherheitsprotokolle maskieren sensible Schlüssel und zeigen im Produktivbetrieb keine internen Datenbankfehler.

## 1.4.0 – Module, GitHub-Updates und Mehrserver-Discord

- Datenbankmigrationen können Owner direkt im Panel prüfen und ausführen.
- Der aktive Twitch-Kanal lässt sich oben rechts über ein Dropdown wechseln.
- Neues News- und Ankündigungsmodul mit Entwürfen, angehefteten Beiträgen und Discord-Ereignis.
- Twitch, BanSync, Discord, News, Inhalte, Team, Design und Audit sind einzeln aktivierbare Panel-Module.
- Owner können vertrauenswürdige Zusatzmodule mit `module.json` als geprüftes ZIP installieren, konfigurieren, aktualisieren und deaktivieren.
- Discord unterstützt mehrere Server und beliebig viele Channels je Server sowie mehrere Ziel-Channels pro Ereignis.
- Text-, Ankündigungs- und Thread-Channels können über den Bot direkt vom Discord-Server übernommen werden.
- URL-Rewrites sind in den Einstellungen zwischen klassischen Query-URLs und sauberen Pfaden umschaltbar.
- GitHub Releases können automatisch geprüft und als Update-Hinweis im ModDesk angezeigt werden.
- Ein neuer Release lässt sich mit einem Klick herunterladen, prüfen, sichern, migrieren und installieren; `.env`, MySQL-Daten, Branding und Zusatzmodule bleiben erhalten.
- Ein Klick auf die Versionsanzeige öffnet die Änderungen der installierten und einer verfügbaren Version.
- Changelogs können aus dem Versionsdialog direkt in einen verwalteten Discord-Channel gepostet werden.

## 1.3.0 – Design und Discord Studio

- Design-Editor für Logo, Farben, Header, Footer, Navigation und Seiteninhalte.
- Discord Studio mit Embed-Vorschau, Icons, Bildern, Feldern, Footer und Vorlagen.
- Direkter Einstieg im Projektstamm ohne sichtbares `/public`.
- Owner-geschützter ZIP-Update-Importer mit Dateisicherung und automatischen Migrationen.

## 1.2.0 – Integrationen

- Discord-Bot und ereignisabhängige Channel-Routen.
- SMTP-Konfiguration und Testversand.
- Twitch BanSync über mehrere moderierte Kanäle mit kanalweisem Banlog.
