# Mandantensicherheits-Audit 2026 (MVP-001)

**Status:** v1 (erstmalige Bestandsaufnahme, Mai 2026)
**Bezug:** [Issue #1](https://github.com/Daniel-Jorg-Schuppelius/workDiary/issues/1),
[Feature 015 Mandantenfähigkeit](../features/015-mandantenfaehigkeit-betriebsmodelle.md),
[Feature 016 Datenschutz/DSGVO](../features/016-datenschutz-dsgvo-datenlebenszyklus.md)

## Zweck

Vollständige Bestandsaufnahme der Mandantengrenzen in workDiary. Erfasst alle
Eloquent-Modelle, dokumentiert legitime Bypässe des `OrganizationScope`,
benennt Public-Routes ohne automatische Mandantenbindung und definiert eine
Allow-List für globale Tabellen. Ist Grundlage für die regressionssichere
Test-Suite unter `tests/Feature/Tenant/`.

## Architekturüberblick

WorkDiary nutzt **single-database, shared-schema Multi-Tenancy** mit harter
Trennung über `organization_id`. Mandantenfähigkeit wird durch zwei zentrale
Bausteine gewährleistet:

- [`App\Models\Concerns\BelongsToOrganization`](../../app/Models/Concerns/BelongsToOrganization.php) —
  Trait, der bei `creating` automatisch `organization_id` befüllt und beim Boot
  den Global Scope registriert.
- [`App\Models\Scopes\OrganizationScope`](../../app/Models/Scopes/OrganizationScope.php) —
  Global Scope, der Queries auf `currentOrganization` einschränkt, sofern der
  Container die Bindung kennt.

Der Container-Service `currentOrganization` wird im Web-Stack durch Middleware
gesetzt; in Konsolen-/Queue-/Test-Kontexten muss er explizit gebunden werden
(siehe `tests/Concerns/WithOrganization.php`).

## Modell-Inventar

51 von 55 produktiven Modellen sind tenant-scoped (92,7 %). Modelle aus
`App\Models\Legacy` (Connection `legacy`) sind **explizit ausgenommen** und im
Legacy-Abschnitt dokumentiert.

### Tenant-scoped Kerngeschäftsmodelle

| Modell | `organization_id` Migration | Abgeleitet von | Policy |
| --- | --- | --- | --- |
| Customer | 2026_05_12 | — | ✅ |
| Project | 2026_05_12 | — | ✅ |
| Task | 2026_05_12 | projects | ✅ |
| Milestone | 2026_05_12 | — | ✅ |
| DiaryEntry | 2026_05_12 | — | ✅ |
| TimeEntry | 2026_05_12 | diary_entries | ✅ |
| Timesheet | 2026_05_12 | — | ✅ |
| Invoice | 2026_05_12 | — | ✅ |
| InvoiceItem | 2026_05_25_140200 | invoices | ✅ |
| Event | 2026_05_12 | — | ✅ |
| PerDiemTrip | 2026_05_12 | — | ✅ |

### Tenant-scoped Operativ-/Stammdaten-/Admin-Modelle

| Modell | Kategorie | Migration | Hinweis |
| --- | --- | --- | --- |
| Attachment | Operativ | 2026_05_25_140000 | Polymorph, Backfill via `booted()` |
| Comment | Operativ | 2026_05_25_140100 | Polymorph |
| EventParticipant | Operativ | (Pivot) | Pivot `event_user` |
| EventReminder | Operativ | 2026_05_25_140100 | abgeleitet von events |
| ExternalReference | Operativ | 2026_05_12 | Polymorph |
| RecurrenceRule | Operativ | 2026_05_12 | Polymorph |
| MaterialUsage | Operativ | 2026_05_25_140200 | abgeleitet von timesheets |
| PerDiemDay | Operativ | 2026_05_25_140200 | abgeleitet von per_diem_trips |
| Vacation | Operativ | 2026_05_12 | — |
| SickLeave | Operativ | 2026_05_12 | — |
| Attendance | Operativ | 2026_05_12 | — |
| TravelLog | Operativ | 2026_05_12 | — |
| Tour | Operativ | 2026_05_12 | — |
| OnCallShift | Operativ | 2026_05_12 | — |
| ScheduledShift | Operativ | 2026_05_12 | — |
| DutyPlan | Operativ | 2026_05_12 | — |
| EmergencyAssignment | Operativ | 2026_05_12 | — |
| EnergyLog | Operativ | 2026_05_12 | — |
| Expense | Operativ | 2026_05_12 | — |
| Tag | Operativ | 2026_05_12 | — |
| WorkSchedule | Operativ | 2026_05_12 | — |
| Qualification | Stammdaten | 2026_05_12 | — |
| EntryType | Stammdaten | 2026_05_12 | — |
| Vehicle | Stammdaten | 2026_05_12 | — |
| Material | Stammdaten | 2026_05_12 | — |
| ActivityCategory | Stammdaten | 2026_05_12 | — |
| EventCategory | Stammdaten | 2026_05_12 | — |
| ExpenseCategory | Stammdaten | 2026_05_12 | — |
| ShiftType | Stammdaten | 2026_05_12 | — |
| Room | Stammdaten | 2026_05_12 | — |
| Holiday | Stammdaten | 2026_05_12 | — |
| AutomationRule | Admin | 2026_05_12 | — |
| AutomationRuleRun | Admin | 2026_05_25_140200 | abgeleitet von automation_rules |
| AuditLog | Admin | 2026_05_12 | — |
| CoverageRequirement | Admin | 2026_05_12 | — |
| FlexEligibility | Admin | 2026_05_12 | — |
| FlexBalance | Admin | 2026_05_25_140100 | abgeleitet von users |
| LexofficeArticle | Admin | 2026_05_12 | — |
| ProjectBillingRule | Admin | 2026_05_12 | abgeleitet von projects |
| PushSubscription | Admin | 2026_05_25_140100 | abgeleitet von users |

### Allow-List: bewusst nicht tenant-scoped

Diese Modelle nutzen **keinen** `BelongsToOrganization`-Trait. Das ist
beabsichtigt und im jeweiligen Modell zu dokumentieren. Erweiterungen dieser
Liste benötigen ein eigenes Issue und ein Review im Audit-Dokument.

| Modell | Begründung |
| --- | --- |
| Organization | Root-Tenant, hat selbst keine übergeordnete Organisation. |
| User | Hat zwar `organization_id`, aber kein Trait — `Authenticatable`-Hierarchie und Org-Wechsel für Plattform-Admins erfordern manuelle Filter. Eigener Audit-Eintrag, eigenes Folge-Issue für eventuelle Trait-Umstellung. |
| UserGroup | Organisationsbezug existiert, Lifecycle wird in Admin-Controllern bewusst über `withoutGlobalScopes()` gesteuert. |
| OrganizationAuditLog | Nullable `organization_id`, **bewusst ohne FK**, damit Audit-Einträge eine gelöschte Organisation überleben. |
| PerDiemRate | Globale Referenzdaten (Tagessätze nach Land/Region) — gilt für alle Mandanten. |
| GeocodeCache | Globaler Cache geokodierter Adressen, mandantenübergreifend zulässig. |
| `App\Models\Legacy\*` | Liegen auf separater `legacy`-Connection und sind über Middleware `access.legacy`/`legacy.write` geschützt. Siehe Legacy-Abschnitt. |

### CI-Gate

Die Allow-List ist die maßgebliche Referenz für den Architektur-Test
`tests/Architecture/TenantTraitCoverageTest.php`. Neue Modelle in
`app/Models/` müssen entweder `BelongsToOrganization` nutzen **oder**
explizit in der Allow-List geführt werden.

## Bypass-Inventar (`withoutGlobalScopes()`)

Der globale `OrganizationScope` wird an folgenden Stellen bewusst umgangen.
Jede Stelle ist im Code mit einem `// TENANT-BYPASS:`-Kommentar markiert und
verantwortet einen der dokumentierten Gründe.

| Datei | Zeile | Grund | Schutzmaßnahme |
| --- | --- | --- | --- |
| `app/Models/EventParticipant.php` | 63 | Pivot-Backfill via `event_user`-Tabelle | Org-Konsistenzprüfung gegen Parent-Event |
| `app/Models/Customer.php` | 139 | Slug-Eindeutigkeitsprüfung beim Anlegen | Expliziter `where('organization_id', ...)`-Filter |
| `app/Models/Customer.php` | 156 | Berechnung der nächsten Kundennummer (`K-XXXX`) | Expliziter `where('organization_id', ...)`-Filter |
| `app/Models/AutomationRuleRun.php` | 50 | Queue-Worker lädt Regel nach ID | Org wird aus zugehöriger AutomationRule abgeleitet |
| `app/Models/Organization.php` | 116 | Slug-Eindeutigkeit der Organisation selbst | Globale Unique-Validation, nicht org-gebunden |
| `app/Models/UserGroup.php` | 79 | Slug-Eindeutigkeit innerhalb der Org | Expliziter `where('organization_id', ...)`-Filter |
| `app/Http/Controllers/OrganizationController.php` | 43 | Plattform-Admin listet alle Organisationen | Route hinter `auth` + `access.manage` Permission |
| `app/Http/Controllers/Admin/Access/MemberController.php` | 42 | Plattform-Admin verwaltet Mitglieder über Orgs hinweg | Permission `manage-members` + expliziter Org-Filter |
| `app/Http/Controllers/Admin/Access/AccessHubController.php` | 52 | Übersicht aller Nutzer im Plattform-Admin | Permission `manage-access` + expliziter Org-Filter |
| `app/Http/Controllers/OrgMemberController.php` | 36 | Listet Mitglieder einer ausgewählten Organisation | Expliziter Org-Filter |
| `app/Http/Controllers/Admin/Access/UserGroupController.php` | 90, 97, 159 | UserGroup-Pflege im Plattform-Admin | Permission + expliziter Org-Filter |
| `app/Http/Controllers/PublicSignatureController.php` | 42 | Token-Lookup für signierte Stundenzettel-URL | Token-Entropie + `magic_expires_at`; nach Auflösung wird `currentOrganization` an die Org des Timesheets gebunden |

## Public-Routes (ohne Auth-Middleware)

Diese Routen werden ohne `auth`-Middleware bedient und müssen ihre
Mandantenbindung selbst herstellen.

| Route | Controller | Mandanten-Schutz |
| --- | --- | --- |
| `GET /license` | `LicenseController::show` | Org-agnostisch (Instanz-Lizenz). |
| `GET /calendar/public.ics` | `IcsFeedController::public` | Liefert ausschließlich als „public" markierte Events der Org, deren Slug im Feed-Subpfad steckt. |
| `GET /calendar/feed/{token}.ics` | `IcsFeedController::personalSchedule` | Token enthält User-ID + Org-Bindung; Render greift auf `currentOrganization` via Token-Resolver. |
| `GET /sign/timesheet/{token}` | `PublicSignatureController::show` | Token → Timesheet → Organization wird beim Laden geprüft und gegen `withoutGlobalScopes()`-Bypass abgesichert. |
| `POST /sign/timesheet/{token}` | `PublicSignatureController::store` | Wie oben; zusätzlich Signatur-Audit-Log mit Tokenscope. |

## Attachment-/Storage-Pfade

`attachments.path` enthält aktuell **keinen** `organization_id`-Präfix. Die
Defense-in-Depth besteht aus drei Schichten:

1. Global Scope `OrganizationScope` auf `Attachment`.
2. Policy `AttachmentPolicy` mit explizitem Org-Check vor Download.
3. Signierte URLs mit kurzer Laufzeit für externe Anhänge.

Die Frage, ob `path` auf `orgs/{organization_id}/...` migriert werden soll,
ist im ADR [adr-attachment-paths.md](./adr-attachment-paths.md) entschieden.

## Legacy-Abgrenzung

Der Legacy-Bereich bleibt im aktuellen Audit **außen vor** und wird in einem
eigenen Ticket separat behandelt. Folgende Komponenten gelten als Legacy:

- Verzeichnis [`app/Legacy/`](../../app/Legacy/) (Auth, Console, Http,
  Models, Providers, Services, Support).
- Modelle [`App\Models\Legacy\LegacyDiaryEntry`](../../app/Models/LegacyDiaryEntry.php),
  [`LegacyArchiveDiaryEntry`](../../app/Models/LegacyArchiveDiaryEntry.php) und
  [`LegacyUser`](../../app/Models/LegacyUser.php) auf Connection `legacy`.
- Konfiguration [`config/legacy.php`](../../config/legacy.php) und
  `LEGACY_DB_*`-Umgebungsvariablen.
- Routen aus `routes/legacy.php` mit Middleware `access.legacy` und
  `legacy.write`.

**Schutzkonzept der Legacy-Welt** (nicht Teil dieses Audits, hier nur
verzeichnet): Die Trennung erfolgt über eine getrennte Datenbank-Connection
und dedizierte Middleware. Neue Audit-Tests dürfen Legacy-Tabellen nicht
manipulieren und müssen Legacy-Routen aus dem Geltungsbereich ausnehmen.

## Test-Coverage

| Test | Geltungsbereich |
| --- | --- |
| [`tests/Feature/Security/OrganizationIsolationTest.php`](../../tests/Feature/Security/OrganizationIsolationTest.php) | Signierte Attachment-URLs, Direkt-Lookups, Kind-Tabellen (Comment, EventReminder, FlexBalance). |
| `tests/Feature/Tenant/TenantBoundaryTest.php` *(NEU im Zuge MVP-001)* | Kerngeschäftsmodelle: cross-org Read/Update/Delete → 403/404. |
| `tests/Feature/Tenant/AttachmentTenantTest.php` *(NEU)* | Attachment-Download cross-org wird durch Policy/Scope verhindert. |
| `tests/Feature/Tenant/PublicRouteTenantTest.php` *(NEU)* | ICS-Feed, PublicSignature: Tokens aus Org A liefern keine Org-B-Daten. |
| `tests/Architecture/TenantTraitCoverageTest.php` *(NEU)* | Jedes neue Model in `app/Models/` ohne Trait muss in der Allow-List stehen. |

## Offene Punkte / Folge-Issues

1. **Attachment-Pfad-Migration** auf `orgs/{org_id}/...` — eigenes Issue,
   Risiko Storage-Move + Backfill, siehe ADR.
2. **`User`-Trait-Umstellung** — eigenes Issue, betrifft Authenticatable und
   Org-Wechsel-Logik.
3. **Webhooks / öffentliche API** — eigener Audit-Lauf, sobald Endpunkte
   bestehen.
4. **Separate Datenbanken pro Mandant** — Architektur-Entscheidung, kein MVP.
