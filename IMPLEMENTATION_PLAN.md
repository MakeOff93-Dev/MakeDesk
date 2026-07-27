# Admin-Panel – Umsetzungsstand

Dieser Plan bildet die gewünschte Ausbaufolge für Twitch ModDesk ab. Version 1.7.0 ergänzt die stabile Basis um ein vollständig vereinheitlichtes Dunkelgrau-/Rot-Design und kontoweise Zwei-Faktor-Authentifizierung.

## Phase 1 – Kritische Fehler und Basis

- [x] Datei-auswählen-Fehler bei Update- und Modul-ZIPs behoben
- [x] Upload-Vorprüfung und verständliche PHP-Limitmeldungen
- [x] Updatepakete prüfen, installieren und protokollieren
- [x] Automatische Vollsicherung vor Updates und Migrationen
- [x] Manueller Backup-Download und Wiederherstellung
- [x] Rollback für Updates mit neuem Vollbackup
- [x] Datenbankgestützte Rollen mit einzeln schaltbaren Seiten- und Aktionsrechten
- [x] Vorkonfigurierte Rollen Owner, Administrator, Moderator und Nur Lesen
- [x] Persönliches Profil, Avatar, Name, E-Mail und sichere Passwortänderung
- [x] Hauptadministrator gegen Deaktivierung und Herabstufung geschützt
- [x] Passwortbestätigung für kritische Aktionen
- [x] CSRF-Schutz, Sicherheitsheader, verschlüsselte Geheimnisse und Login-Limits
- [x] Sicherheitsbereich mit Prüfungen, Ereignissen, IP-Sperren und Sitzungen

## Phase 2 – Systemverwaltung

- [x] Migrator im Panel
- [x] Einzelne oder alle offenen Migrationen in sicherer Reihenfolge
- [x] Status- und Fehlerhistorie für Migrationen
- [x] Audit-Log für administrative Änderungen
- [x] Benutzer- und Rollenverwaltung
- [~] URL-Rewrites: Standardrouting ist umschaltbar; frei definierbare, testbare Regeln folgen
- [~] Audit-Export und erweiterte Filter folgen

## Phase 3 – Module und Verbindungen

- [x] Aktivierbare Core-Module und geprüfter ZIP-Modulimport
- [x] Discord als Modul mit mehreren Servern, Channels und Ereignisrouten
- [x] Twitch als Modul mit OAuth, Zielkanalwahl und Modtools
- [x] GitHub-Release-Prüfung, Changelog und Ein-Klick-Update
- [x] Versionsmeldung im Panel und Changelog-Versand an Discord
- [~] Zentrale interne Moddesk-Inbox mit gelesen/ungelesen, Kategorien und Prioritäten folgt
- [x] Getrennte Ansichts- und Aktionsrechte für hochgeladene Zusatzmodule
- [~] Modulabhängigkeiten und ein erweitertes Berechtigungsmanifest folgen

## Phase 4 – Komfort und Design

- [x] News mit Entwurf, Veröffentlichung und Planung
- [x] Design-Editor für Branding, Farben, Header, Footer und Seitentexte
- [x] Menübeschriftung, Symbol, Reihenfolge und Sichtbarkeit
- [x] Responsive Bedienung für Smartphone, Tablet und Desktop
- [x] Einheitliches dunkles Rot-/Dunkelgrau-Design für Core, Module und neue Widgets
- [x] Discord Embed- und Nachrichten-Editor
- [~] Frei anlegbare Menüs, Untermenüs und rollenabhängige Menüregeln folgen
- [~] Konfigurierbare Dashboard-Widgets und Hellmodus folgen

## Noch geplante Erweiterungen

- Discord-Login
- Passwort-Reset über SMTP
- Eigene URL-Regeln mit 301/302-Test und Schleifenerkennung
- Zentrale Panel-Benachrichtigungen mit gelesen/ungelesen
- Datei-Integritätsbaseline und Wartungsmodus
- Aufbewahrungsregeln für alte Backups und Logexport

Alle noch offenen Punkte bauen auf den Sicherheits-, Backup-, Profil-, Zwei-Faktor- und Berechtigungsgrundlagen aus Version 1.7.0 auf.
