# ADR: Standardmuster für Mandanten-Authorization bei Export-Endpunkten

**Status:** akzeptiert (Mai 2026, MVP-002)
**Bezug:** [Issue #2](https://github.com/Daniel-Jorg-Schuppelius/workDiary/issues/2),
[tenant-audit-2026.md](./tenant-audit-2026.md)

## Kontext

WorkDiary bietet zahlreiche Export-Endpunkte (CSV, PDF, ICS, JSON) und
mehrere Reporting-Module, die jeweils Eloquent-Queries gegen die Datenbank
absetzen und das Ergebnis als Download/PDF zurückgeben. Im Audit (MVP-001)
und in der Tenant-Testsuite (MVP-002) wurde festgestellt:

- Eloquent-Queries werden automatisch über den `OrganizationScope` gefiltert,
  sobald `currentOrganization` an den Container gebunden ist.
- Im Web-Stack setzt [`SetOrganizationContext`](../../app/Http/Middleware/SetOrganizationContext.php)
  die Bindung anhand des eingeloggten Users.
- Im API-Stack (`auth:sanctum`) wurde dieselbe Middleware im Rahmen MVP-002
  ergänzt (siehe [`bootstrap/app.php`](../../bootstrap/app.php)), sonst lief
  der Scope bei API-Requests als No-Op.

## Entscheidung

Jeder neue Export-Endpunkt nutzt das folgende mehrschichtige Standardmuster:

1. **Routing** unter einer der Standard-Middleware-Gruppen
   (`web` mit `auth` oder `api` mit `auth:sanctum`). Beide Stacks binden
   `currentOrganization` automatisch.
2. **Authorization über Policy** (`Gate::authorize(...)` oder
   `$this->authorize(...)`) vor dem Build-Up der Query. Standard-Abilities
   sind `viewAny`, `view`, `export` (eigene Ability, wo passend).
3. **Datenbeschaffung ausschließlich über Eloquent** (`Model::query()...`),
   damit der Global Scope greift. **Keine** Raw-SQL-Statements oder
   `DB::table(...)`-Aufrufe, ohne dass die `organization_id`-Klausel
   manuell ergänzt wird.
4. Bei aggregierten Reports (z. B. SUMs über mehrere Modelle) gilt das
   gleiche Prinzip pro Sub-Query. Joins gegen `users`, `customers`,
   `projects` etc. erben den Scope automatisch.
5. **Keine** Aufrufe von `withoutGlobalScopes()` in Export-Pfaden. Falls
   trotzdem nötig (z. B. Cross-Tenant-Reporting für Plattform-Admins):
    - im Code-Kommentar `// TENANT-BYPASS: <Grund>` ergänzen,
    - im Audit-Dokument (`tenant-audit-2026.md`) registrieren,
    - per Permission absichern (`platform.reports.cross_tenant`).
6. **Test-Pflicht:** jeder Export bekommt eine Regressionsprüfung in
   [`tests/Feature/Tenant/ExportTenantTest.php`](../../tests/Feature/Tenant/ExportTenantTest.php).
   Der Test legt Org-B-Daten an, loggt sich als Org-A-User ein, ruft den
   Export auf und stellt sicher, dass weder ID noch eindeutiger
   Identifier-String aus Org B im Response-Body vorkommt.

## Konsequenzen

### Vorteile

- Defense-in-Depth: Scope + Policy + Tests.
- Konsistent für CSV, PDF und JSON-Exports.
- Neue Exports müssen sich nicht ums Tenant-Filtering kümmern, solange
  Eloquent verwendet wird.

### Aufwand

- Pro neuem Export ein Tenant-Regressionstest (~10 Zeilen).
- Beim Hinzufügen von Reports muss die Liste in `tenant-audit-2026.md`
  gepflegt werden.

## Folgeentscheidungen

- 18 vorhandene Report-PDFs: stichprobenartige Tests in MVP-002, vollständige
  Abdeckung erfolgt zusammen mit dem allgemeinen Reports-Refactor.
- Bei zukünftigen externen API-Webhooks: separates ADR, da Webhooks ggf.
  ohne `auth:sanctum` laufen und eigene Mandantenbindung benötigen.
