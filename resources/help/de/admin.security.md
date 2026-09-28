---
title: "Sicherheit & Härtung"
topic: admin.security
version: 2
audience:
    - admin
related:
    - admin.handbook
    - admin.backups
    - isms.software
---

Die wichtigsten Sicherheitswerkzeuge für den Betrieb:

**Sicherheitsübersicht**: Die Admin-Seite „Sicherheit"
(`/admin/security`) bündelt read-only den sicherheitsrelevanten
Zustand: aktive Sitzungen, API-Tokens (nur Metadaten – niemals der
Token-Wert), aktive externe Integrationen, die letzten Daten-/
Zeit-Exporte, die letzten Supportzugriffe (Audit-Ereignisse mit
Präfix `support.`) sowie 2FA-Abdeckung und at-rest-Verschlüsselungs-
Status. Die Seite zeigt nur an und verändert keine Sicherheitsobjekte;
die automatisierten Lösch- und Aufbewahrungsläufe sind nicht Teil
dieser Übersicht.

**Zwei-Faktor-Authentifizierung**: Nutzer können mehrere Methoden
parallel hinterlegen – **TOTP** (Authenticator-App), **E-Mail-Code**
und **WebAuthn** (FIDO2-Sicherheitsschlüssel/Passkey). Empfehlen Sie
mindestens zwei Methoden, damit der Verlust eines Faktors nicht zur
Aussperrung führt.

**Verschlüsselung von Bestandsdaten**:
`php artisan security:encrypt-existing` (mit `--dry-run` zum Testen)
verschlüsselt vorhandene sensible Felder (u. a. Steuer-/
Sozialversicherungsnummern, IBAN/BIC, Adressen). Der Lauf ist
idempotent und überspringt bereits verschlüsselte Werte.
**Achtung**: Die Verschlüsselung hängt am **APP_KEY** – vor dem Lauf
ein Backup ziehen und den Schlüssel separat sichern; ohne APP_KEY
sind die Daten unwiederbringlich.

**Audit-Kette prüfen**: `php artisan audit:verify` validiert die
SHA-256-Hash-Ketten der revisionssicheren Audit-Protokolle und endet
mit Exit-Code 1 bei einem Bruch – ideal für Cron/CI. Halten Sie den Befehl
dauerhaft grün.

**Systemzustand**: `php artisan system:health` prüft Datenbank,
Migrationen, Storage, Queue, APP_KEY, Mail und Lizenz, ohne Daten zu
ändern.

**Komponenten & SBOM**: Die Komponentenübersicht der Administration
zeigt App-, PHP-, Laravel- und DB-Version, Module und Plugins und
erzeugt eine **SBOM** (CycloneDX 1.5) aus den Lock-Dateien – als
Download für Audits. Zugriff haben nur globale Admins.

## Temporäre IP-Sperre und SIEM-Export

Ohne fail2ban auf dem Server kann WorkDiary Adressen nach wiederholten
Fehlversuchen selbst vorübergehend sperren (Umgebungsvariable
`SECURITY_IP_BAN`, Standard aus): 15 Minuten, bei Wiederholung eine Stunde,
dann 24 Stunden. Angemeldete Sitzungen und private Netze sind nie betroffen;
aktive Sperren sehen und lösen Sie unter „Angriffserkennung“. Da sich viele
Nutzer eine Adresse teilen können (Mobilfunk, Firmennetz), bleibt fail2ban
die erste Wahl. Für ein SIEM schreibt WorkDiary jedes Sicherheitsereignis
zusätzlich im Format CEF oder JSON in eine eigene Datei oder per Syslog
(`SECURITY_SIEM_FORMAT`, `SECURITY_SIEM_TARGET`).

## Konto nach einer Übernahme sichern

Bestätigt sich, dass jemand ein Konto übernommen hat, reicht Abmelden nicht:
Wer das Passwort kennt, meldet sich wieder an. „Konto sichern“ in der
Sitzungsverwaltung (für Mitglieder Ihrer Organisation) und „Kontoübernahme
bestätigen“ an einem Sicherheitsereignis (Plattform-Administration) beenden
alle Sitzungen und API-Tokens, machen das Passwort und alle Passkeys ungültig
und senden der Person einen Link zum Festlegen eines neuen Passworts.
App-basierte Zwei-Faktor-Methoden bleiben erhalten. Der Vorgang erscheint als
Sicherheitsereignis und im Prüfprotokoll. Ihr eigenes Konto sichern Sie auf
Ihrer Seite zur Zwei-Faktor-Authentifizierung.
