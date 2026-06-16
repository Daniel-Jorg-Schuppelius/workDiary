# Refactoring-Backlog

Ergebnis eines strukturierten Code-Assessments der Hauptapp (Stand 2026-06-15).
Ziele: God-Klassen/Struktur, Typsicherheit/PHPStan, Duplikate/Dead Code, Testabdeckung.

## Ausgangslage

Die Codebasis ist diszipliniert: PHPStan **Level 8 mit leerer Baseline**, nur 1
`@deprecated` und 1 TODO in ~162k Zeilen, 438 Testdateien mit breiter Abdeckung.
„Refactoring" bedeutet hier **gezielte Eingriffe, kein Rewrite**. Größte Hebel:
God-Klassen/Duplikate und ein Test-Sicherheitsnetz für wenige riskante,
ungetestete Klassen. „Billiger" Typsicherheits-Headroom (mixed/array) ist
praktisch ausgereizt.

Legende Aufwand: **S** klein · **M** mittel · **L** groß. Risiko: niedrig/mittel/hoch.

---

## Welle 0 — Test-Sicherheitsnetz (P1, vor jedem Umbau)

Geld-/Recht-/sicherheitsrelevante Klassen, die heute **komplett ungetestet** sind.

| # | Ziel | Was absichern | Prio |
|---|------|---------------|------|
| 0.1 | `Services/Licensing/LicenseSeal.php` | Tamper-/Integritätsprüfung, beschädigte/fehlende/unleserliche Seal-Datei, Strukturvalidierung | P1 |
| 0.2 | `Services/Finance/Targets/{LexofficeTarget,FileTarget,FacturationTargetRegistry}.php` | Contact-Resolve-Fallback, HTTP-4xx/5xx, Payload-Struktur, CSV-BOM/Escaping, unbekanntes Target | P1 |
| 0.3 | `Services/Whistleblowing/Scanning/ClamAvScanDriver.php` | EICAR-Erkennung, Scanner-Crash/Fail-Safe, unerwartete Exit-Codes | P1 |
| 0.4 | `Services/Whistleblowing/WhistleblowingPermissions.php` | Rollen-Seeding vollständig, Trennung Meldestelle↔Admin, Org-/Team-Isolation | P1 |
| 0.5 | `Services/Isms/RegisterExportService.php` | Status-Filter „nur freigegeben", Org-Scope, CSV/JSON-Kanonik | P1 |
| 0.6 | Policies (99 von ~102 ohne Unit-Test) | Cross-Org-Isolation, `HasAdminBypass`, Ownership; v.a. `AuditLogPolicy` (immer false), `Finance/BankAccountPolicy`, `Privacy/ProcessingActivityPolicy`, `Isms/IsmsAuditPolicy` | P1 |

## Welle 1 — Quick Wins (geringes Risiko: Duplikate + Config)

| # | Befund | Vorschlag | Aufwand | Risiko |
|---|--------|-----------|---------|--------|
| 1.1 | 43× Copy-Paste Sqid-Decode-Fallback (`InvoiceController::store` 3×) | `Sqid::decodeOrAbort(Model::class, $raw)` + ausrollen | M | niedrig |
| 1.2 | 12× wortgleicher `currentOrganization()`/`ensureOrganizationScoped()` | Trait `Concerns\ResolvesCurrentOrganization` | S | niedrig |
| 1.3 | 10+ Controller mit handkopiertem CSV-Streaming | Trait `StreamsCsv` analog `Reporting/Concerns/WritesReportCsv` | M | niedrig |
| 1.4 | `phpstan-strict-rules` installiert, aber nicht eingebunden | In `phpstan.neon` aktivieren + Fixes | S | niedrig |
| 1.5 | `HasSqid::getKey()`-Cast erzeugt 127 Level-9-Fehler | Key typsicher beziehen | S | niedrig |
| 1.6 | `RateCalculator`/`BrandingService`-Methoden nur intern genutzt | Sichtbarkeit → `private` | S | niedrig |
| 1.7 | Verwaiste Blade-Komponenten (zu verifizieren) | Nach Grep-Verifikation entfernen | S | niedrig-mittel |

## Welle 2 — God-Klassen aufteilen (struktureller Kern)

| # | Datei | Vorschlag | Aufwand | Risiko |
|---|-------|-----------|---------|--------|
| 2.1 | `Plugins/Toggl/Http/Controllers/TogglController.php` (768) | ZIP-Handling→`TogglExportArchiveService`, Optionen→`TogglOptionBuilder`; Controller auf Request→Service reduzieren | L | mittel |
| 2.2 | `Services/Isms/AuditService.php` (608) | 4 Aggregat-Services (Audit/Finding/CorrectiveAction/Review) + `AssignsSequentialNo`-Concern | L | mittel |
| 2.3 | `Models/User.php` (595) | Concerns: `HasEffectivePermissions`, `InteractsWithTwoFactor`, `HasPreferences` | M | mittel |
| 2.4 | `Models/Project.php` (574) | `booted()`-Seiteneffekte → `ProjectObserver`; `effective*` → `ProjectRateResolver` | M | mittel |
| 2.5 | `Http/Controllers/Admin/ClassificationRequirementController.php` (716) | `validatePayload`→FormRequest; Options/Presets→`ClassificationRequirementMetadata`; Filter→Scope | M | niedrig |
| 2.6 | `Services/Install/InstallationManager.php` (671) | Org/Admin-Anlage→`OrganizationProvisioner`; Env-Writer in kleine Configuratoren; Manager als Fassade | L | mittel |
| 2.7 | `Services/Demo/DemoSeederService.php` (817) | `blueprint()`-Branchendaten → `DemoBlueprintProvider`/Config | S | niedrig |
| 2.8 | `Http/Controllers/AssetController.php` (608) | `show()`-Aggregation→`AssetDetailAssembler`; Timeline→`AssetTimelinePresenter` | M | mittel |

## Welle 3 — Konsistenz vereinheitlichen

| # | Befund | Empfehlung | Aufwand | Risiko |
|---|--------|------------|---------|--------|
| 3.1 | 383 inline `$request->validate` neben 59 FormRequests | Hotspot-Controller (ProtocolController 9, InternalCaseController 8, OpenIssueController 7…) auf FormRequests ziehen | M | niedrig |
| 3.2 | Auth gemischt: `Gate::authorize` (175) / `$this->authorize` (12) / `abort_unless` (79); 53 Controller mischen | Auf `Gate::authorize` standardisieren; `$this->authorize` entfernen; `abort_unless` nur für Nicht-Policy-Checks | L | mittel |
| 3.3 | Roh-`{id}`-Routen trotz Sqid-Konvention (admin/privacy, api-tokens) | Auf Sqid/Model-Binding umstellen (Enumeration-Risiko) | M | mittel |
| 3.4 | 293 `argument.type`-Abweichungen (Level 9) | Gezielt abarbeiten (Hotspots: `AdvisoryImportService`, `ManagesUserContactDetails`, `ProtocolService`) | M | niedrig |

## Bewusste Nicht-Befunde

- ICS-Public-Feed ist **bewusst org-agnostisch** (per Test dokumentiert) — kein Bug.
- Routen/Controller-Abdeckung sehr sauber (keine toten Resource-Methoden).
- Legacy-Redirect-Routen (`shifts.index`→`duties.index` etc.) sind gewollte Backward-Compat-Aliase.
- Voller Sprung auf PHPStan Level 9 lohnt nicht (~2215 Fehler, ~70 % bloße Cast-Warnungen).
