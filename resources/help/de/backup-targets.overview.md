---
title: "Cloud-Backupziele"
topic: backup-targets.overview
version: 2
keywords:
    - Datensicherung
    - Offsite-Backup
    - Sicherung in die Cloud
    - Dropbox
    - OneDrive
    - Google Drive
    - verschlüsseltes Backup
    - 3-2-1-Regel
    - Wiederherstellung
    - Restore-Test
    - Master-Key
    - Backup-Aufbewahrung
    - Nextcloud
    - WebDAV
    - S3
    - Seafile
audience: []
related:
    - admin.integrations
---

WorkDiary sichert die gesamte Installation verschlüsselt in Dropbox, OneDrive, Google Drive, Nextcloud, auf einen WebDAV-Server, in S3-kompatiblen Speicher oder nach Seafile (Offsite-Kopie der 3-2-1-Strategie). Der Klartext verlässt die Installation nie — hochgeladen werden ausschließlich verschlüsselte Teile mit signiertem Commit-Manifest.

**Verbindungen:** Nur der Plattform-Betreiber verwaltet Backupziele; Dropbox, OneDrive und Google Drive werden je mit einem eigenen Konto per OAuth angebunden (getrennt vom Dokumenteingang, eigene Schreib-Scopes), Nextcloud, WebDAV, S3 und Seafile mit eigenen Zugangsdaten. Beim Verbinden schreibt, liest und löscht WorkDiary einmal eine Probedatei; erst dann ist das Ziel aktiv. Fehlt die nötige Berechtigung, ist das Ziel sichtbar blockiert.

**Seafile:** Server-URL (nur HTTPS), Konto-Token eines eigenen Seafile-Benutzers und Name oder ID einer bestehenden, unverschlüsselten Bibliothek. WorkDiary schreibt nur in einen eigenen Ordner darin und prüft jede hochgeladene Datei auf ihre Größe. Den Konto-Token liefert die Seafile-API: POST an /api2/auth-token/ mit Benutzername und Passwort, bei Zwei-Faktor-Anmeldung zusätzlich mit dem Kopf X-Seafile-OTP und dem aktuellen Code.

**Schlüssel:** BACKUP_MASTER_KEY (ENV, offline sichern!) ist der einzige reguläre Entschlüsselungsweg; optional entschlüsselt ein Recovery-Schlüsselpaar im Notfall. Ohne Recovery-Key warnt die Seite dauerhaft — Verlust des Master-Keys macht alle Backups wertlos.

**Betrieb:** Der nächtliche Lauf erstellt Snapshot (DB-Dump + Dateien), verschlüsselt, lädt Teile wiederaufnehmbar hoch und wendet die Retention an (7 täglich / 4 wöchentlich / 12 monatlich; Legal Hold schützt einzelne Generationen). Wöchentlich prüft eine Stichproben-Verifikation Signatur und Hashes; der Restore-Test stellt isoliert wieder her und protokolliert RPO/RTO — bis zum ersten grünen Test gilt „gesichert, Wiederherstellung nicht bestätigt“.
