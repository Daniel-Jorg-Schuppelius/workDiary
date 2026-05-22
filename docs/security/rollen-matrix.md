# Rollen- und Rechte-Matrix

Status: Aktiv • Quelle der Wahrheit: `database/seeders/PermissionsSeeder.php::defaultRoleMatrix()`

Diese Matrix dokumentiert die seedbaren Standardprofile aus MVP-003 (Feature
[019 — Rollen-, Rechte- und Produktprofile](../features/019-rollen-rechte-produktprofile.md)).
Sie wird beim Anlegen jeder Organisation automatisch per
`PermissionsSeeder::seedOrganization()` (ausgelöst vom `OrganizationObserver`)
auf die jeweilige Organisation angewendet. Bestehende Organisationen können das
Mapping durch erneutes Ausführen des Seeders nachholen — Spatie-Roles und
-Permissions werden idempotent per `firstOrCreate` / `syncPermissions`
angelegt; Organisations-spezifische Anpassungen über die Admin-UI bleiben
erhalten (`syncPermissions` ersetzt die Permission-Liste der jeweiligen Rolle).

## Profile auf einen Blick

| Profil               | Einsatzbereich                                   | Lese-Umfang                | Schreib-Umfang                                    |
| -------------------- | ------------------------------------------------ | -------------------------- | ------------------------------------------------- |
| `admin`              | Kundenadmin pro Organisation                     | alles                      | alles                                             |
| `geschaeftsfuehrung` | Management-Sicht / Reporting                     | alles (read-only)          | keine                                             |
| `teamleitung`        | Operative Personal-/Zeit-/Planungsführung        | Team-/Projektbereich       | Personalplanung, Zeitfreigabe, Diary, Dienstpläne |
| `buchhaltung`        | Rechnungsstellung & Kontrolling                  | Kunden/Projekte/Rechnungen | Kunden, Rechnungen, Stundenzettel-Lock            |
| `user`               | Standard-Mitarbeiter Innendienst                 | eigene Daten + Projekte    | eigene Zeit, Diary, Urlaub, Touren-Logs           |
| `aussendienst`       | Mobile Erfassung (Touren, Spesen)                | eigene Daten + Projekte    | eigene Zeit, Diary, Touren, Spesen, Fahrzeug-Log  |
| `callcenter`         | Anrufannahme / Tagebuch-Erfassung                | Tagebuch + Kundenstamm     | Diary (auch für andere)                           |
| `support`            | Anbieter-Support / Helpdesk                      | nahezu alles + Audit       | **keine** (strikt read-only)                      |
| `training_manager`   | Schulungsverwaltung (Bestandsrolle, EventPolicy) | Räume/Events               | Räume/Events                                      |
| `kunde`              | Customer-Self-Service-Portal (eigener Guard)     | nur eigene Kunden-Daten    | **keine** (read-only)                             |

## Detail-Matrix

Legende: ✓ = Permission ist Bestandteil des Profils. Permissions sind nach
Themengebiet gruppiert; nicht aufgeführte Permissions sind in keinem der
nicht-`admin`-Profile enthalten und werden bei Bedarf manuell zugewiesen.

### Plattform / Organisation

| Permission          | admin | geschaeftsfuehrung | teamleitung | buchhaltung | user | aussendienst | callcenter | support |
| ------------------- | :---: | :----------------: | :---------: | :---------: | :--: | :----------: | :--------: | :-----: |
| `organization.view` |   ✓   |         ✓          |      ✓      |      ✓      |  ✓   |      ✓       |     ✓      |    ✓    |
| `access.audit.view` |   ✓   |         ✓          |      ✓      |             |      |              |            |    ✓    |
| `audit-log.view`    |   ✓   |         ✓          |             |      ✓      |      |              |            |    ✓    |

### Benutzerverwaltung

| Permission          | admin | geschaeftsfuehrung | teamleitung | buchhaltung | user | aussendienst | callcenter | support |
| ------------------- | :---: | :----------------: | :---------: | :---------: | :--: | :----------: | :--------: | :-----: |
| `user.viewAny`      |   ✓   |         ✓          |      ✓      |      ✓      |      |              |            |    ✓    |
| `user.view`         |   ✓   |         ✓          |      ✓      |      ✓      |      |              |            |    ✓    |
| `user.rates.manage` |   ✓   |                    |             |      ✓      |      |              |            |         |
| `user.flex.manage`  |   ✓   |                    |      ✓      |      ✓      |      |              |            |         |

### Kunden

| Permission         | admin | geschaeftsfuehrung | teamleitung | buchhaltung | user | aussendienst | callcenter | support |
| ------------------ | :---: | :----------------: | :---------: | :---------: | :--: | :----------: | :--------: | :-----: |
| `customer.viewAny` |   ✓   |         ✓          |      ✓      |      ✓      |      |      ✓       |     ✓      |    ✓    |
| `customer.view`    |   ✓   |         ✓          |      ✓      |      ✓      |      |      ✓       |     ✓      |    ✓    |
| `customer.create`  |   ✓   |                    |             |      ✓      |      |              |            |         |
| `customer.update`  |   ✓   |                    |             |      ✓      |      |              |            |         |
| `customer.delete`  |   ✓   |                    |             |      ✓      |      |              |            |         |
| `customer.export`  |   ✓   |                    |             |      ✓      |      |              |            |         |

### Projekte

| Permission               | admin | geschaeftsfuehrung | teamleitung | buchhaltung | user | aussendienst | callcenter | support |
| ------------------------ | :---: | :----------------: | :---------: | :---------: | :--: | :----------: | :--------: | :-----: |
| `project.viewAny`        |   ✓   |         ✓          |      ✓      |      ✓      |  ✓   |      ✓       |            |    ✓    |
| `project.view`           |   ✓   |         ✓          |      ✓      |      ✓      |  ✓   |      ✓       |            |    ✓    |
| `project.create`         |   ✓   |                    |      ✓      |             |      |              |            |         |
| `project.update`         |   ✓   |                    |      ✓      |             |      |              |            |         |
| `project.archive`        |   ✓   |                    |      ✓      |             |      |              |            |         |
| `project.billing.manage` |   ✓   |                    |             |      ✓      |      |              |            |         |
| `task.manage`            |   ✓   |                    |      ✓      |             |  ✓   |      ✓       |            |         |
| `milestone.manage`       |   ✓   |                    |      ✓      |             |  ✓   |              |            |         |

### Zeit & Stundenzettel

| Permission                    | admin | geschaeftsfuehrung | teamleitung | buchhaltung | user | aussendienst | callcenter | support |
| ----------------------------- | :---: | :----------------: | :---------: | :---------: | :--: | :----------: | :--------: | :-----: |
| `timeEntry.viewAny`           |   ✓   |         ✓          |      ✓      |      ✓      |      |              |            |    ✓    |
| `timeEntry.viewOwn`           |   ✓   |         ✓          |             |             |  ✓   |      ✓       |            |         |
| `timeEntry.create`            |   ✓   |                    |             |             |  ✓   |      ✓       |            |         |
| `timeEntry.update`            |   ✓   |                    |             |             |  ✓   |      ✓       |            |         |
| `timeEntry.delete`            |   ✓   |                    |             |             |  ✓   |      ✓       |            |         |
| `timeEntry.create-for-others` |   ✓   |                    |      ✓      |             |      |              |            |         |
| `timeEntry.approve`           |   ✓   |                    |      ✓      |      ✓      |      |              |            |         |
| `timesheet.viewAny`           |   ✓   |         ✓          |      ✓      |      ✓      |      |              |            |    ✓    |
| `timesheet.create`            |   ✓   |                    |             |             |  ✓   |      ✓       |            |         |
| `timesheet.update`            |   ✓   |                    |             |             |  ✓   |      ✓       |            |         |
| `timesheet.sign`              |   ✓   |                    |      ✓      |      ✓      |  ✓   |      ✓       |            |         |
| `timesheet.lock`              |   ✓   |                    |      ✓      |      ✓      |      |              |            |         |
| `timesheet.unlock`            |   ✓   |                    |      ✓      |      ✓      |      |              |            |         |
| `timesheet.export`            |   ✓   |                    |             |      ✓      |      |              |            |         |

### Rechnungen

| Permission        | admin | geschaeftsfuehrung | teamleitung | buchhaltung | user | aussendienst | callcenter | support |
| ----------------- | :---: | :----------------: | :---------: | :---------: | :--: | :----------: | :--------: | :-----: |
| `invoice.viewAny` |   ✓   |         ✓          |             |      ✓      |      |              |            |    ✓    |
| `invoice.view`    |   ✓   |         ✓          |             |      ✓      |      |              |            |    ✓    |
| `invoice.create`  |   ✓   |                    |             |      ✓      |      |              |            |         |
| `invoice.update`  |   ✓   |                    |             |      ✓      |      |              |            |         |
| `invoice.issue`   |   ✓   |                    |             |      ✓      |      |              |            |         |
| `invoice.pay`     |   ✓   |                    |             |      ✓      |      |              |            |         |

### Tagebuch (Diary)

| Permission                | admin | geschaeftsfuehrung | teamleitung | buchhaltung | user | aussendienst | callcenter | support |
| ------------------------- | :---: | :----------------: | :---------: | :---------: | :--: | :----------: | :--------: | :-----: |
| `diary.viewAny`           |   ✓   |         ✓          |      ✓      |             |      |              |     ✓      |    ✓    |
| `diary.viewOwn`           |   ✓   |         ✓          |             |             |  ✓   |      ✓       |            |         |
| `diary.create`            |   ✓   |                    |      ✓      |             |  ✓   |      ✓       |     ✓      |         |
| `diary.update`            |   ✓   |                    |      ✓      |             |  ✓   |      ✓       |     ✓      |         |
| `diary.delete`            |   ✓   |                    |             |             |  ✓   |      ✓       |            |         |
| `diary.create-for-others` |   ✓   |                    |      ✓      |             |      |              |     ✓      |         |
| `diary.export`            |   ✓   |                    |      ✓      |             |      |              |            |         |

### Dienstplan / Schichten

| Permission                    | admin | geschaeftsfuehrung | teamleitung | buchhaltung | user | aussendienst | callcenter | support |
| ----------------------------- | :---: | :----------------: | :---------: | :---------: | :--: | :----------: | :--------: | :-----: |
| `dutyPlan.viewAny`            |   ✓   |         ✓          |      ✓      |             |      |              |            |    ✓    |
| `dutyPlan.create`             |   ✓   |                    |      ✓      |             |      |              |            |         |
| `dutyPlan.update`             |   ✓   |                    |      ✓      |             |      |              |            |         |
| `dutyPlan.publish`            |   ✓   |                    |      ✓      |             |      |              |            |         |
| `shift.manage`                |   ✓   |                    |      ✓      |             |      |              |            |         |
| `scheduled-shift.manage`      |   ✓   |                    |      ✓      |             |      |              |            |         |
| `coverage-requirement.manage` |   ✓   |                    |      ✓      |             |      |              |            |         |
| `on-call-shift.manage`        |   ✓   |                    |      ✓      |             |      |              |            |         |
| `emergency-assignment.manage` |   ✓   |                    |      ✓      |             |      |              |            |         |
| `shift-type.manage`           |   ✓   |                    |      ✓      |             |      |              |            |         |

### Abwesenheiten / Anwesenheit / Flex

| Permission             | admin | geschaeftsfuehrung | teamleitung | buchhaltung | user | aussendienst | callcenter | support |
| ---------------------- | :---: | :----------------: | :---------: | :---------: | :--: | :----------: | :--------: | :-----: |
| `vacation.viewAny`     |   ✓   |         ✓          |      ✓      |             |      |              |            |    ✓    |
| `vacation.request`     |   ✓   |                    |             |             |  ✓   |      ✓       |            |         |
| `vacation.approve`     |   ✓   |                    |      ✓      |             |      |              |            |         |
| `vacation.cancel`      |   ✓   |                    |      ✓      |             |      |              |            |         |
| `sick-leave.viewAny`   |   ✓   |         ✓          |      ✓      |             |      |              |            |    ✓    |
| `sick-leave.manage`    |   ✓   |                    |      ✓      |             |      |              |            |         |
| `attendance.viewAny`   |   ✓   |         ✓          |      ✓      |             |      |              |            |    ✓    |
| `attendance.manage`    |   ✓   |                    |      ✓      |             |  ✓   |      ✓       |            |         |
| `work-schedule.manage` |   ✓   |                    |      ✓      |             |      |              |            |         |
| `flex.view`            |   ✓   |         ✓          |      ✓      |             |  ✓   |      ✓       |            |    ✓    |
| `flex.manage`          |   ✓   |                    |      ✓      |             |      |              |            |         |

### Außendienst (Touren / Fahrzeuge / Spesen)

| Permission           | admin | geschaeftsfuehrung | teamleitung | buchhaltung | user | aussendienst | callcenter | support |
| -------------------- | :---: | :----------------: | :---------: | :---------: | :--: | :----------: | :--------: | :-----: |
| `tour.viewAny`       |   ✓   |         ✓          |      ✓      |             |  ✓   |      ✓       |            |    ✓    |
| `tour.manage`        |   ✓   |                    |      ✓      |             |      |              |            |         |
| `travel-log.viewAny` |   ✓   |         ✓          |      ✓      |             |  ✓   |      ✓       |            |    ✓    |
| `travel-log.manage`  |   ✓   |                    |             |             |  ✓   |      ✓       |            |         |
| `vehicle.viewAny`    |   ✓   |         ✓          |             |             |      |      ✓       |            |    ✓    |
| `energy-log.manage`  |   ✓   |                    |             |             |      |      ✓       |            |         |

### Reports

| Permission      | admin | geschaeftsfuehrung | teamleitung | buchhaltung | user | aussendienst | callcenter | support |
| --------------- | :---: | :----------------: | :---------: | :---------: | :--: | :----------: | :--------: | :-----: |
| `report.view`   |   ✓   |         ✓          |      ✓      |      ✓      |      |              |            |    ✓    |
| `report.export` |   ✓   |         ✓          |             |      ✓      |      |              |            |         |

### Customer-Portal

Die Rolle `kunde` wird ausschließlich Portal-Accounts (Tabelle `users` mit
`customer_id IS NOT NULL`) zugewiesen und ist im internen `web`-Guard
deaktiviert. Authentifizierung läuft über den dedizierten `customer`-Guard
samt eigenem User-Provider (siehe
[Customer-Portal-Guard](./customer-portal-guard.md)). Die Permissions sind
auf reine Lese-Sichten auf Daten des **eigenen** Kunden beschränkt;
Datenscoping erfolgt zusätzlich in jedem Controller per `customer_id`.

| Permission                      | admin | kunde |
| ------------------------------- | :---: | :---: |
| `customerPortal.access`         |   ✓   |   ✓   |
| `customerPortal.diary.view`     |   ✓   |   ✓   |
| `customerPortal.timeEntry.view` |   ✓   |   ✓   |
| `customerPortal.invoice.view`   |   ✓   |   ✓   |

## Erweiterung & Anpassung

- **Pro Organisation überschreibbar.** Die Admin-UI unter `admin.access.roles`
  erlaubt es Kunden-Admins, Rollen ihrer Organisation feingranular anzupassen.
  Globale Plattform-Rollen (`team_id = NULL`) sind dort read-only und werden
  ausschließlich vom Seeder verwaltet.
- **Neue Rollen.** Eine neue Rolle hinzufügen erfolgt in drei Schritten:
    1. Enum-Case in `app/Enums/User/UserRole.php`,
    2. Permission-Liste in `PermissionsSeeder::defaultRoleMatrix()`,
    3. Label in `lang/de/user.php` + `lang/en/user.php`.
- **Audit-Trail.** Rechteänderungen über die UI werden im `AuditLog` der
  Organisation protokolliert (siehe `RoleController::store/update/destroy`).
- **Idempotenz.** Der Seeder kann gefahrlos mehrfach ausgeführt werden;
  `firstOrCreate` für Roles/Permissions und `syncPermissions` für die
  Zuordnung garantieren stabiles Verhalten.

## Offene Punkte

- Rolle `kunde` (Customer-Self-Service-Portal) ist mit MVP-003 Folge-Issue #56
  ausgeliefert; Details siehe [Customer-Portal-Guard](./customer-portal-guard.md).
- Plattform-Rollen `systemadmin` / `kundenadmin` aus Feature 019 sind als
  `admin` (global vs. org-scoped) bereits über die `team_id`-Trennung des
  Spatie-Setups abgebildet.
