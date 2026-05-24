# Lizenzstatus und Feature-Flags in Admin-Oberfläche

Status: Aktiv (MVP-047, Issue #46) • Quelle:
[Feature 021 — Tarife / Lizenzportal / Abrechnung](features/021-tarife-lizenzportal-abrechnung.md).
• Verwandt: [Diagnose-Seite §3.2](diagnose-seite.md) (MVP-044).

## 1. Zweck

Org-Admin und Plattform-Admin sollen sofort sehen:

- Welche **Edition** (Free / Pro / Enterprise) ist aktiv?
- Wie lange ist die **Lizenz** gültig?
- Welche **Features** sind freigeschaltet, welche gesperrt?
- Welche Limits (Nutzer, Aufträge, Storage) gelten und wie weit sind
  sie ausgeschöpft?

## 2. Route und Aufbau

`GET /admin/license` — Permission `platform.license.view`.

Sektionen:

1. **Lizenz-Karte**: Edition, Aussteller, Ablauf, Lizenz-ID,
   Hardware-Bindung (sofern vorhanden), Status-Badge.
2. **Limit-Karten**: Pro Limit eine Karte mit aktueller Auslastung,
   Schwellwert-Anzeige, Trend.
3. **Feature-Flag-Tabelle**: Code, Bezeichnung, Quelle
   (`license|env|orgOverride`), Status (`on|off|grace`),
   ggf. Restlaufzeit.
4. **Lizenz-Aktionen**: „Lizenz erneuern" (Link), „Lizenz hochladen"
   (Datei-Upload), „Online-Aktivierung" (sofern verfügbar).

## 3. Datenmodell

### 3.1 `licenses`

| Feld             | Typ         | Notizen                     |
| ---------------- | ----------- | --------------------------- |
| id               | uuid        | PK                          |
| edition          | enum        | free, pro, enterprise       |
| issued_at        | datetime    |                             |
| valid_until      | datetime    | NULL = unbefristet          |
| customer_ref     | string      | Lizenz-Kunde (anonymisiert) |
| hardware_hash    | string null | Optionale Hardware-Bindung  |
| max_users        | int         |                             |
| max_orgs         | int         |                             |
| storage_quota_gb | int         |                             |
| signature        | text        | Signiertes Lizenz-Payload   |
| installed_at     | datetime    |                             |

### 3.2 `feature_flags`

| Feld            | Typ           | Notizen                                    |
| --------------- | ------------- | ------------------------------------------ |
| id              | uuid          |                                            |
| code            | string        | z. B. `protocols.signed`, `reports.export` |
| source          | enum          | license, env, orgOverride                  |
| state           | enum          | on, off, grace                             |
| grace_until     | datetime null | Falls grace, Ablauf                        |
| organization_id | uuid null     | NULL = global                              |
| reason          | string null   | Begründung Override                        |
| created_by      | uuid null     |                                            |
| created_at      | datetime      |                                            |

`FeatureFlagResolver::isEnabled(code, org)` mit Cache 60 s.
Auflösungsreihenfolge: `orgOverride` (für org) → `env` → `license`.

## 4. Lizenz-Validierung

`LicenseValidator::verify(Payload, Signature)`:

- ECDSA-P256 Signaturprüfung gegen eingebauten Public Key.
- Konsistenz: `valid_until > now()`, Hardware-Hash passt (sofern
  gesetzt), Limits ≥ aktuelle Auslastung.
- Ergebnis: `LicenseStatus { valid, warnings[], errors[] }`.

Status-Logik:

- `valid` — Lizenz gültig, Limits OK.
- `expiringSoon` — Ablauf < 30 Tage.
- `expired` — Ablauf überschritten, aber innerhalb 14-Tage-Grace.
- `gracePeriod` — Grace aktiv: Lese-Schreiben funktioniert, aber
  rote Banner-Warnung.
- `blocked` — Grace abgelaufen: Schreiboperationen gesperrt
  (Lesezugriff bleibt).
- `invalid` — Signatur fehlerhaft.

## 5. Limit-Enforcement

`LimitGuard::ensureCanCreate(modelClass, org)` wird in Create-
Aktionen aufgerufen:

| Limit            | Geprüft in                                          |
| ---------------- | --------------------------------------------------- |
| max_users        | `UserController@store`, `OrgMemberInvite`           |
| max_orgs         | `OrganizationCreateAction`                          |
| storage_quota_gb | `AttachmentStoreAction`, `ExportArtifactCleanupJob` |

Bei Limit-Überschreitung: `LimitExceededException` mit klarer
deutscher Meldung „Nutzerlimit ({n}/{max}) der aktuellen Lizenz
erreicht. Bitte Lizenz erweitern."

## 6. Feature-Gates im Code

Twig-/Blade-Helfer `@feature('code')` + Middleware
`requires-feature:code`. Bei deaktiviertem Feature:

- UI: Karte wird ausgegraut + Tooltip „Nicht in dieser Lizenz
  enthalten. Edition: Pro erforderlich."
- Backend: HTTP 423 Locked mit JSON `{error:'feature_disabled',
code:'protocols.signed'}`.

## 7. Permissions

| Permission                      | Wer                        |
| ------------------------------- | -------------------------- |
| `platform.license.view`         | Plattform-Admin, Org-Admin |
| `platform.license.install`      | Plattform-Admin            |
| `platform.featureFlag.override` | Plattform-Admin            |

## 8. Audit

- `license.installed`, `license.expired`,
  `license.gracePeriodEntered`, `license.blocked`,
  `featureFlag.overridden`.
- `limit.exceeded` mit `limit`, `current`, `max`.

## 9. Akzeptanzkriterien

1. UI mit drei Sektionen (Lizenz-Karte, Limits, Feature-Flags) + Aktionen.
   — erledigt: `app/Http/Controllers/Admin/LicenseAdminController.php` und
   `resources/views/admin/license/index.blade.php` rendern Lizenz-Karte
   (Lizenznehmer, Lizenz-ID, Ausstellung, Ablauf inkl. „n Tage verbleibend",
   Domain-Bindung), Limits-Karten (Nutzer/Organisationen mit
   Auslastungsbalken und Schwellen-Färbung ok/warn/critical) sowie
   Feature-Flags-Tabelle aus `LicensePayload->features`. Aktionen-Slot
   verlinkt auf die bestehende Aktivierungs-Seite `license.show`.
2. `LicenseValidator` mit ECDSA-Signaturprüfung, Tests für jeden Status-Code §4.
   — bereits vorhanden: `app/Services/Licensing/LicenseService.php` +
   `LicenseSeal` mit ECDSA-P256. Status-Mapping läuft im
   `LicenseAdminController::badgeTone`.
3. `LimitGuard` mit Tests für jeden Limit-Typ §5. — **out-of-scope** dieses
   Iterationsschritts. UI zeigt die Auslastung; Enforcement (Block bei
   Überschreitung) bleibt für einen späteren Schritt.
4. `@feature` / `requires-feature` Middleware mit Tests. — **out-of-scope**
   dieses Iterationsschritts. Feature-Flags sind im UI sichtbar, eine
   `FeatureFlagResolver`-Klasse + `requires-feature`-Middleware folgen
   separat.
5. Diagnose-Seite (MVP-044) zeigt Lizenz-Sektion mit gleichem Status.
   — erledigt: `DiagnosticsService::checkLicense()` verwendet denselben
   `LicenseService->current()` und mappt `LicenseStatus` auf
   `DiagnosticStatus`.
6. Audit-Events §8. — teilweise erledigt: `license.installed` wird beim
   erfolgreichen Install im bestehenden `LicenseController::store()`
   geschrieben (mit `license_id_sha256`, Status, Lizenznehmer, Ablauf).
   `license.expired` / `license.gracePeriodEntered` / `license.blocked` /
   `featureFlag.overridden` / `limit.exceeded` setzen den später folgenden
   `LimitGuard` und `FeatureFlagResolver` voraus.

## 10. Out-of-scope (MVP-047)

- Online-Selbstbestellung im Lizenzportal (eigenes Feature 021
  später).
- Pro-Org-Sub-Lizenzen.
- Floating-User-Lizenzen.

## 11. Folge

- MVP-048 Onboarding-Checkliste verwendet `feature_flags` zur
  Schritt-Aktivierung.
