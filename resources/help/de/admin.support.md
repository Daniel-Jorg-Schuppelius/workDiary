---
title: "Supportbericht & Fehlerdiagnose"
topic: admin.support
version: 1
audience:
    - admin
related:
    - admin.security
    - admin.backups
    - admin.handbook
---

Der **Supportbericht** bündelt den technischen Zustand deiner
Installation, damit der Support ein Problem analysieren kann — **ohne
dass Kundendaten das Haus verlassen**.

So ist der Bericht aufgebaut:

- **Versionen & Build**: App-Version, Build-Hash, PHP-, Laravel- und
  Datenbank-Version sowie die aktiven Module und Plugins.
- **Health-Status**: das Ergebnis von `php artisan system:health`
  (Datenbank, Migrationen, Storage, Queue, APP_KEY, Mail, Lizenz,
  Backup) als kompakter Statusblock.
- **Plugin-Fehler (7 Tage)**: nur Plugin-ID, Phase und Anzahl — keine
  Fehlertexte, keine Payloads.
- **Betrieb**: Queue-Stand und letzte Backup-Heartbeats (nur Counts und
  Metadaten wie Größe/Zeitpunkt).
- **Stammdaten-Counts**: Anzahl Datensätze je Tabelle — niemals
  Inhalte.
- **Konfigurations-Flags**: welche Module/Features aktiv sind,
  Mailtransport-Typ, Queue-Treiber. Secrets (APP_KEY, Passwörter,
  Tokens) werden grundsätzlich redaktiert.

**Datensparsamkeit ist das Kernversprechen.** Der Bericht enthält
ausschließlich explizit freigegebene, technische Felder (Whitelist).
Kundennamen, personenbezogene Daten, Klartext-Zugangsdaten und Secrets
tauchen nie auf.

So erzeugst du den Bericht:

- **Admin-Seite** „Supportbericht": ZIP-Bundle (optional mit Passwort),
  reine JSON-Datei oder Vorschau im Browser.
- **Kommandozeile** (On-Premise/CI): `php artisan support:report` gibt
  den Bericht auf STDOUT aus, `--output=pfad.json` schreibt ihn in eine
  Datei.

Jede Erzeugung wird im Audit-Log protokolliert
(`support.reportGenerated`, `support.reportDownloaded`).
