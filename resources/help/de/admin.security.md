---
title: "Sicherheit & Härtung"
topic: admin.security
version: 1
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
und **WebAuthn** (FIDO2-Sicherheitsschlüssel/Passkey). Empfiehl
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
mit Exit-Code 1 bei einem Bruch – ideal für Cron/CI. Halte den Befehl
dauerhaft grün.

**Systemzustand**: `php artisan system:health` prüft Datenbank,
Migrationen, Storage, Queue, APP_KEY, Mail und Lizenz, ohne Daten zu
ändern.

**Komponenten & SBOM**: Die Komponentenübersicht der Administration
zeigt App-, PHP-, Laravel- und DB-Version, Module und Plugins und
erzeugt eine **SBOM** (CycloneDX 1.5) aus den Lock-Dateien – als
Download für Audits. Zugriff haben nur globale Admins.
