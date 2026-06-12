# Rollen, Rechte und Produktprofile

## Status

Done — MVP umgesetzt mit MVP-003 (Issue #3, Branch `main`).

## Ziel

WorkDiary soll produktseitig verständliche Rollenprofile bereitstellen:
Geschäftsführung, Teamleitung, Buchhaltung, Außendienst, Innendienst, Kunde,
Support, Systemadmin und Kundenadmin. Die technische Rechteverwaltung soll für
Kunden einfacher administrierbar und sicher voreingestellt sein.

## Warum

Komplexe Rechte sind nur verkaufbar, wenn Kunden sie verstehen. Besonders bei
Arbeitszeiten, Krankheit, Abrechnung, Protokollen, Kundendaten und
Supportzugriffen braucht es sichere Standardrollen.

## MVP

- Vordefinierte Rollenprofile mit klarer Beschreibung.
- Rechte-Matrix als Admin-Ansicht (siehe `docs/security/rollen-matrix.md`).
- Trennung von Systemadmin (Plattform-Admin, `team_id = NULL`), Kundenadmin
  (`admin` pro Organisation) und Support.
- Sensible Datenbereiche (Rechnungen, Audit, Mitarbeiter-Stammdaten) mit
  expliziten Berechtigungen.
- Rollenprüfung für Kundenportal und externe Links (Folge-Issue, siehe unten).

## Profile (Stand MVP-003)

Die vollständige Permission-zu-Rolle-Matrix wird in
[`docs/security/rollen-matrix.md`](../security/rollen-matrix.md) gepflegt und
ist als _Single Source of Truth_ via `PermissionsSeeder::defaultRoleMatrix()`
codeverbindlich verankert.

| Profil                    | Slug                 | Kurzbeschreibung                                                          |
| ------------------------- | -------------------- | ------------------------------------------------------------------------- |
| Systemadmin               | `admin` (global)     | Plattform-Admin (`team_id = NULL`), umgeht Policies via `HasAdminBypass`. |
| Kundenadmin               | `admin` (org-scoped) | Voller Zugriff innerhalb der eigenen Organisation.                        |
| Geschäftsführung          | `geschaeftsfuehrung` | Read-only-Sicht inkl. Reporting + Audit, keine Schreibrechte.             |
| Teamleitung               | `teamleitung`        | Personal-, Zeit-, Dienstplan- und Diary-Führung; keine Finanzfunktionen.  |
| Buchhaltung               | `buchhaltung`        | Kunden-/Rechnungs-/Stundenzettel-Workflow, Export, Tarife.                |
| Innendienst (Mitarbeiter) | `user`               | Eigene Zeit/Diary/Urlaub; Projekte read-only.                             |
| Außendienst               | `aussendienst`       | Mobile Erfassung (eigene Zeit, Diary, Touren, Spesen, Fahrzeug-Log).      |
| Callcenter                | `callcenter`         | Diary für andere erfassen, Kundenstamm einsehen.                          |
| Support                   | `support`            | Anbieter-Support, **strikt read-only** + Auditzugriff.                    |
| Kunde (Customer-Portal)   | `kunde`              | Self-Service-Sicht für externe Auftraggeber (read-only), eigener Guard.   |

Der Kunden-Guard ist separat dokumentiert: siehe
[Customer-Portal-Guard](../security/customer-portal-guard.md).

## Akzeptanzkriterien

- ✅ Neue Mandanten starten mit sicheren Standardrollen
  (`OrganizationObserver` → `PermissionsSeeder::seedOrganization`).
- ✅ Kunden können nachvollziehen, welche Rolle welche Daten sehen darf
  (Admin-UI `admin.access.roles` + `docs/security/rollen-matrix.md`).
- ✅ Supportzugriff ist getrennt von fachlichen Kundenrollen (`support`-Rolle
  ohne Schreibrechte, Tests in `tests/Feature/Access/RoleProfilesTest.php`).
- ✅ Rechteänderungen werden protokolliert (`AuditLog` mit Events
  `role.created` / `role.updated` / `role.deleted` aus `RoleController`,
  Test: `tests/Feature/Access/RoleControllerAuditTest.php`).

## Abhängigkeiten

- Mandantenfähigkeit und Betriebsmodelle (Feature 015)
- Datenschutz, Sicherheit und Datenlebenszyklus
- Audit
- Kundenportal: Rolle `kunde` + dedizierter `customer`-Guard, siehe
  [Customer-Portal-Guard](../security/customer-portal-guard.md).

## Offene Folge-Arbeiten

- Kundenportal-Rollenprüfung und externe Links sind weiterhin als Folgearbeit
  dokumentiert.

## GitHub Issues

- #3 — MVP-003: Rollenprofile dokumentieren und seedbar machen (umgesetzt)
- #56 — Rolle `kunde` + Customer-Portal-Guard (umgesetzt)
