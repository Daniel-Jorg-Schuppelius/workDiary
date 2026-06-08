# Sicherheitshärtung 2026-06 (Defense-in-Depth)

Aufbauend auf [`audit-2026-06.md`](audit-2026-06.md). Vier zusätzliche Bausteine
für die vertrauenswürdige Speicherung von Firmendaten.

## 1) Zwei-Faktor-Authentifizierung (TOTP)

- Paket `pragmarx/google2fa-qrcode` (rein lokal, keine externen Calls).
- `users.two_factor_secret` / `two_factor_recovery_codes` (beide `encrypted`),
  `two_factor_confirmed_at`; `organizations.two_factor_required`.
- **Service** `App\Services\Auth\TwoFactorService` (Secret, Verifikation ±1
  Zeitfenster, QR-SVG, 8 Recovery-Codes).
- **Login-Challenge:** `LoginController` parkt nach erfolgreicher Passwortprüfung
  die Identität in der Session (`auth.2fa.id`) und loggt erst nach gültigem TOTP-/
  Recovery-Code voll ein (`TwoFactorChallengeController`). Funktioniert für Neu-
  System **und** Legacy-Login (Challenge sitzt hinter der Credential-Prüfung).
- **Selbstverwaltung:** `TwoFactorController` + `account/two-factor` (aktivieren →
  QR scannen → bestätigen → Recovery-Codes; deaktivieren/Codes neu via gültigem Code).
- **Org-Pflicht:** Middleware `RequireTwoFactorSetup` erzwingt die Einrichtung,
  wenn `organizations.two_factor_required` gesetzt ist (Toggle im Org-Formular).
- Recovery-Codes sind einmalig; Deaktivieren ist bei Org-Pflicht gesperrt.
- Tests: `tests/Feature/TwoFactorTest.php` (6).

## 2) Verschlüsselung sensibler Daten at-rest

`encrypted`-Cast (AES-256, an `APP_KEY` gebunden) auf:

- `users`: `tax_identification_number`, `social_security_number`
- `contact_bank_accounts`: `account_holder`, `iban`, `bic`
- `contact_addresses`: `street`, `supplement`

Spalten auf `text` erweitert (Ciphertext länger; SQLite übersprungen, da typ-egal).
Bestandsdaten: `php artisan security:encrypt-existing` (liest roh, idempotent,
**vorher Backup**). Felder werden nirgends in `WHERE`/`UNIQUE`/Berechnungen genutzt.

**Chat-Nachrichten (`messages.body`): bewusst NICHT app-verschlüsselt** (Variante A),
da die Volltextsuche `WHERE body LIKE` benötigt. Stattdessen At-rest-Schutz auf
**Infrastruktur-Ebene** (verschlüsseltes Volume / DB-at-rest, z. B. LUKS) vorsehen.

> **KRITISCH – Key-Management:** Alle verschlüsselten Werte hängen am `APP_KEY`.
> `APP_KEY` getrennt vom DB-Backup sichern. Verlust = Datenverlust. Rotation über
> `APP_PREVIOUS_KEYS` möglich. Tests: `tests/Feature/PiiEncryptionTest.php` (3).

## 3) Admins ohne Einblick in private Chats

`ChannelPolicy`/`MessagePolicy` ohne pauschalen `HasAdminBypass`: private Kanäle und
Direktnachrichten sind auch für Plattform-Admins ohne Mitgliedschaft tabu. Admin-
Moderation nur in **öffentlichen** Kanälen (`adminMayModerate()`). Nachrichten-Inhalte
darf weiterhin nur der Autor bearbeiten. Test: `ChatTest::test_admin_cannot_access_private_channel…`.

## 4) CSP-Härtung Stufe 1 (Nonce)

- Pro-Request-Nonce über `Vite::useCspNonce()`; Blade-Direktive `@cspNonce` an allen
  52 Inline-`<script>`-Tags (37 Views). `@vite`-Tags erhalten den Nonce automatisch.
- **Flag** `config('security.csp_script_nonce')` / `CSP_SCRIPT_NONCE` (Default **aus**):
  - aus → `script-src 'self' 'unsafe-inline' 'unsafe-eval'` (unverändert).
  - an → `script-src 'self' 'nonce-…' 'unsafe-eval'` (kein `unsafe-inline` mehr).
- `'unsafe-eval'` bleibt: Alpine.js (Standard-Build) benötigt es. Dessen Entfernung
  (Alpine-CSP-Build) ist **Stufe 2** und separat zu planen.
- **Rollout:** Nach einem Browser-Smoke-Test aller Seiten (keine CSP-Konsolenfehler)
  `CSP_SCRIPT_NONCE=true` setzen. Tests: `tests/Feature/CspNonceTest.php` (2).

## 5) 2FA fürs Customer-Portal

Der `customer`-Guard nutzt dasselbe `User`-Modell → 2FA-Spalten vorhanden.
Gespiegelt auf den Portal-Guard: Login-Challenge (`CustomerPortal\TwoFactorChallengeController`,
Session `auth.customer.2fa.id`), Selbstverwaltung (`CustomerPortal\TwoFactorController`,
`customer-portal/two-factor`), Org-Pflicht via `two-factor.setup:customer`-Middleware,
Nav-Link „Sicherheit". Tests: `tests/Feature/CustomerPortal/CustomerTwoFactorTest.php` (3).

## 6) CSP Stufe 2 (`unsafe-eval` entfernen) — Refactoring fertig, Build-Switch zurückgestellt

Der gesamte Alpine-Code ist **CSP-konform refactored** (Voraussetzung für den
`@alpinejs/csp`-Build, der kein `eval`/`new Function` nutzt). Alle Alpine-Logik liegt
in registrierten **`Alpine.data`-Komponenten** (`resources/js/alpine/components.js`,
22 Stück); Direktiven nutzen ausschließlich Property-/Getter-Zugriffe und Methoden­
aufrufe. Konkret umgestellt:

- Inline-Objektliterale (`x-data="{…}"`) → registrierte Komponenten; Config über
  `data-config`/`data-*`-JSON statt Objekt-Argumenten.
- Ternaries/Operatoren in `:class`/`x-show`/`:style`/`x-text` → Getter/Methoden
  (`tabClass()`, `isMode()`, `chipStyle()`, `dayMinutesLabel()` …).
- Bracket-Zugriff `days[iso]` → Punkt-Keys `days.dN` (CSP erlaubt nur Dot-Notation).
- Template-Literals (`` `…${x}…` ``) → String-Bauende Getter (`barStyle`, `dotStyle`).
- Bestehende `window.*`-Widgets (`tagPicker`, `facilityPicker`, `wsForm`, `ganttBar`,
  `signaturePad`, `projectTabs`) → `Alpine.data`; die alten JS-Dateien entfernt.

**Build-Switch zurückgestellt:** `resources/js/app.js` importiert weiter den
**Standard-`alpinejs`-Build**. Der Wechsel auf `@alpinejs/csp` wurde getestet, aber
zurückgenommen — im CSP-Build „zerfließen" Dialoge (strengerer Evaluator, nur im
Browser verifizierbar). Der gesamte volle Testlauf (1604) ist mit dem Standard-Build
grün. Der Switch ist ein Einzeiler in app.js; **erst nach Browser-Smoke-Test** aller
interaktiven Seiten aktivieren — dann auch `'unsafe-eval'` in
`SecurityHeaders::scriptSrc()` (Flag-Zweig) entfernen.

Verifikation des Refactorings: grep über alle Views = **0** Inline-Operatoren/
Backticks/Bracket-Zugriffe/Arrow-Functions in Alpine-Direktiven; `php -l` über alle
kompilierten Views = 0 (neue) Fehler; Build grün.

> **Vor Produktiv-Aktivierung:** Browser-Smoke-Test aller interaktiven Seiten
> (Dialoge, Tabs, Picker, Gantt-Drag, Stoppuhr), da der CSP-Build Laufzeit-Semantik
> ändert und sich nicht rein automatisiert (ohne Browser) verifizieren lässt.

## Verbleibend / optional

- Adressen vollständig verschlüsseln (PLZ/Ort bleiben aktuell für Filter klar).
