---
title: "Cloud-Backupziele"
topic: backup-targets.overview
version: 1
audience: []
related:
    - admin.integrations
---

WorkDiary sichert die gesamte Installation verschlüsselt in Dropbox, OneDrive oder Google Drive (Offsite-Kopie der 3-2-1-Strategie). Der Klartext verlässt die Installation nie — hochgeladen werden ausschließlich verschlüsselte Teile mit signiertem Commit-Manifest.

**Verbindungen:** Nur der Plattform-Betreiber verwaltet Backupziele; je Provider wird ein eigenes Konto per OAuth angebunden (getrennt vom Dokumenteingang, eigene Schreib-Scopes). Fehlt die nötige Berechtigung, ist das Ziel sichtbar blockiert.

**Schlüssel:** BACKUP_MASTER_KEY (ENV, offline sichern!) ist der einzige reguläre Entschlüsselungsweg; optional entschlüsselt ein Recovery-Schlüsselpaar im Notfall. Ohne Recovery-Key warnt die Seite dauerhaft — Verlust des Master-Keys macht alle Backups wertlos.

**Betrieb:** Der nächtliche Lauf erstellt Snapshot (DB-Dump + Dateien), verschlüsselt, lädt Teile wiederaufnehmbar hoch und wendet die Retention an (7 täglich / 4 wöchentlich / 12 monatlich; Legal Hold schützt einzelne Generationen). Wöchentlich prüft eine Stichproben-Verifikation Signatur und Hashes; der Restore-Test stellt isoliert wieder her und protokolliert RPO/RTO — bis zum ersten grünen Test gilt „gesichert, Wiederherstellung nicht bestätigt“.
