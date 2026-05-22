# Supportzugriff — Grundsätze und Auditpunkte

Status: Aktiv • Umsetzung MVP-004 (Issue #4) • Quellen:
[Feature 016 — Datenschutz](../features/016-datenschutz-dsgvo-datenlebenszyklus.md),
[Feature 041 — Support & Fehlerdiagnose](../features/041-support-fehlerdiagnose-kundeninstallationen.md).

Dieses Dokument ist die verbindliche Grundsatzbeschreibung für jeden Zugriff,
den der WorkDiary-Anbieter (Plattform-Support / Helpdesk) auf eine konkrete
Kundeninstallation, eine SaaS-Organisation oder einen On-Premise-Mandanten
nimmt. Es legt fest, **was Support darf, was er nicht darf, und welche
Vorgänge zwingend in den `AuditLog` zu schreiben sind.**

Die Grundsätze gelten gleichermaßen für SaaS-Betrieb, Private-Cloud und
On-Premise-Installationen. Sie sind die Vertrauensgrundlage gegenüber Kunden:
*Kundendaten gehören dem Kunden — auch beim Support.*

## 1. Grundsätze

1. **Datenminimierung.** Support sieht nur die Daten, die zur Bearbeitung der
   konkreten Anfrage zwingend nötig sind. Es gibt keinen pauschalen
   Lese-Vollzugriff "auf Vorrat".
2. **Zweckbindung.** Jeder Supportzugriff erfolgt zu einem benannten,
   nachvollziehbaren Zweck (z. B. konkretes Ticket, Fehlerbild, Migration).
3. **Read-only by default.** Plattform-Support darf grundsätzlich **nur
   lesen**. Schreibzugriffe in Kundendaten erfordern eine explizite,
   protokollierte Freigabe des Kundenadmins.
4. **Zeitliche Begrenzung.** Supportzugriffe sind auf den notwendigen Zeitraum
   beschränkt. Dauerhafte Support-Rechte auf einzelnen Organisationen sind
   unzulässig (Soll-Konzept, siehe Abschnitt 5).
5. **Transparenz für Kunden.** Kunden können jederzeit nachvollziehen, wann,
   durch welche Support-Identität und in welchem Umfang Zugriff stattgefunden
   hat (`AuditLog`-Sicht für Kundenadmins).
6. **Trennung Plattform-Support ↔ Org-Admin.** Plattform-Support ist *keine*
   Rolle innerhalb der Kundenorganisation. Er wird über globale Rollen
   (`team_id = NULL`) abgebildet und nicht über die Org-spezifische Admin-UI
   bearbeitbar gemacht (siehe Abschnitt 3).
7. **Kein Datenverkauf, keine Querauswertung.** Supportkontakt erzeugt
   niemals einen Anlass, Kundendaten für andere Zwecke (Produktentwicklung,
   Statistik außerhalb des Tickets) zu nutzen — vgl. Produktversprechen in
   Feature 016.
8. **Datenschutz hat Vorrang vor Bequemlichkeit.** Im Zweifel verlangt
   Support einen anonymisierten Reproduktionsfall vom Kunden, statt direkt
   in Produktivdaten einzusehen.

## 2. Rollenmodell für Support

| Träger                       | Spatie-Rolle / Permission                     | Geltungsbereich   | Schreibrechte     |
| ---------------------------- | --------------------------------------------- | ----------------- | ----------------- |
| **Plattform-Support**        | Rolle `support` mit `team_id = NULL`          | Plattform-weit    | keine (read-only) |
| **Org-internes Helpdesk**    | Rolle `support` mit `team_id = org.id`        | nur diese Org     | keine (read-only) |
| **Privilegierte Wartung**    | Rolle `admin` (global) — Notfall, dokumentiert | Plattform-weit   | vollständig       |
| **Kundenadmin (Org)**        | Rolle `admin` mit `team_id = org.id`          | nur diese Org     | vollständig       |
| **Impersonation (geplant)**  | Permission `user.impersonate`                 | siehe Abschnitt 5 | siehe Abschnitt 5 |

Die Permission-Liste der Rolle `support` ist verbindlich in
[`rollen-matrix.md`](rollen-matrix.md) dokumentiert (Spalte `support`) und
durch `tests/Feature/Access/RoleProfilesTest.php` testgesichert.

**Verbindliche Eigenschaften der Rolle `support`:**

- Enthält **keinerlei** Permissions mit den Suffixen `.create`, `.update`,
  `.delete`, `.approve`, `.publish`, `.sign`, `.lock`, `.unlock`, `.issue`,
  `.pay`, `.manage`, `.import`, `.sync`, `.archive`, `.impersonate`,
  `.reset-password`.
- Enthält `audit-log.view` und `access.audit.view`, damit Support
  Vorgänge nachvollziehen kann, ohne Daten zu verändern.
- Wird **niemals** durch denselben User-Account gleichzeitig mit `admin`
  oder schreibenden Rollen kombiniert (organisatorische Regel).

## 3. Trennung Plattform-Support ↔ Org-Admin

Spatie-Teams (`permission.teams = true`) bilden den Mandantenkontext ab.
Plattform-globale Rollen werden mit `team_id = NULL` angelegt, Org-spezifische
mit `team_id = organization.id`.

`Admin\Access\RoleController` setzt die Trennung technisch durch:

```php
$teamForeign = config('permission.column_names.team_foreign_key');
abort_if($role->{$teamForeign} === null, 403);                      // globale Rollen sind read-only in der Org-UI
abort_unless((int) $role->{$teamForeign} === $organization->id, 403); // fremde Org-Rollen sind unsichtbar
```

Daraus folgen die Pflichten:

- **Plattform-Support darf seine eigene Rolle nicht über die Org-UI
  manipulieren.** Änderungen an der globalen `support`-Rolle erfolgen
  ausschließlich über Seeder + Code-Review.
- **Kundenadmins sehen Plattform-Support nicht in ihrer Mitgliederliste.**
  Zugriffe werden ausschließlich über den `AuditLog` sichtbar.
- **Cross-Org-Operationen sind unzulässig.** Plattform-Support darf keinen
  Bulk-Zugriff über mehrere Organisationen ausführen, der nicht aus einem
  einzelnen, dokumentierten Vorgang resultiert.

## 4. Auditpunkte (verbindlich)

Jeder der folgenden Vorgänge **muss** einen `AuditLog`-Eintrag erzeugen.
Schema der Tabelle: `organization_id`, `user_id`, `event`, `auditable_type`,
`auditable_id`, `changes` (JSON), `ip`, `user_agent`, `created_at`
(siehe Migration `2026_04_30_150000_create_audit_logs_table.php`).

### 4.1 Rollen- und Rechtevergabe

Bereits umgesetzt in `Admin\Access\RoleController` (MVP-003):

| Event           | Auslöser                                  | `changes`-Inhalt                                           |
| --------------- | ----------------------------------------- | ---------------------------------------------------------- |
| `role.created`  | POST `/admin/access/roles`                | `{ name, permissions: [...] }`                             |
| `role.updated`  | PUT `/admin/access/roles/{role}`          | `{ permissions: { added: [...], removed: [...] } }`        |
| `role.deleted`  | DELETE `/admin/access/roles/{role}`       | `{ name, permissions: [...] }`                             |

Pflicht, ergänzend zu definieren (siehe Folge-MVPs):

| Event                       | Auslöser                                          | `changes`-Inhalt                                  |
| --------------------------- | ------------------------------------------------- | ------------------------------------------------- |
| `user.role.assigned`        | Zuweisung einer Rolle an einen User               | `{ role, team_id }`                               |
| `user.role.revoked`         | Entzug einer Rolle                                | `{ role, team_id }`                               |
| `user.permission.granted`   | Einzelpermission direkt vergeben (Ausnahmefall)   | `{ permission, team_id, reason }`                 |
| `user.permission.revoked`   | Einzelpermission entzogen                         | `{ permission, team_id }`                         |

### 4.2 Supportzugriff selbst

Diese Events sind heute **noch nicht implementiert**, aber durch dieses
Dokument verbindlich für die Folge-MVPs festgelegt:

| Event                          | Auslöser                                                  | `changes`-Inhalt                                                  | Folge-MVP    |
| ------------------------------ | --------------------------------------------------------- | ----------------------------------------------------------------- | ------------ |
| `support.access.granted`       | Kundenadmin gibt Support-Session frei                     | `{ granted_by, granted_to, scope, expires_at, ticket_ref }`       | später (s.u.) |
| `support.access.revoked`       | Kundenadmin oder System (Ablauf) hebt Freigabe auf        | `{ revoked_by, reason }`                                          | später       |
| `support.session.started`      | Plattform-Support öffnet eine Org im Support-Kontext      | `{ session_id, ticket_ref, ip, user_agent }`                      | später       |
| `support.session.ended`        | Session-Logout oder Timeout                               | `{ session_id, duration_seconds }`                                | später       |
| `support.impersonation.start`  | Support tritt in einen User-Account ein (`user.impersonate`) | `{ session_id, target_user_id, ticket_ref }`                   | später       |
| `support.impersonation.stop`   | Impersonation wird beendet                                | `{ session_id, target_user_id, duration_seconds }`                | später       |
| `support.report.exported`      | Supportbericht heruntergeladen                            | `{ report_type, anonymized: true, fields_count }`                 | siehe #44     |

### 4.3 Datenexport, -löschung, -zugriff auf sensible Felder

Pflicht-Events, die unabhängig von einer Support-Session anfallen:

| Event                       | Auslöser                                            | `changes`-Inhalt                                       |
| --------------------------- | --------------------------------------------------- | ------------------------------------------------------ |
| `tenant.export.requested`   | Mandantenexport gestartet                           | `{ scope, requested_by, format }`                      |
| `tenant.export.completed`   | Mandantenexport abgeschlossen                       | `{ scope, byte_size, file_ref }`                       |
| `tenant.delete.requested`   | Mandanten-Löschung beantragt                        | `{ scope, requested_by, scheduled_for }`               |
| `tenant.delete.completed`   | Mandanten-Löschung ausgeführt                       | `{ scope, deleted_records }`                           |
| `attachment.viewed`         | Anhang aufgerufen (Sichtbarmachung Support-Pfad)    | `{ attachment_id, owner_org_id, mime, byte_size }`    |
| `audit.exported`            | AuditLog selbst exportiert                          | `{ filter, row_count }`                                |

`event`-Werte sind Konstanten (kein freier String). Vorgeschlagene
Code-Konstante: `AuditLog::EVENT_*` (Folge-Refactoring).

### 4.4 Was *nicht* in den `AuditLog` gehört

- Inhaltliche Kundendaten (Auftragstexte, Diary-Inhalte, Anhangs-Bytes,
  Personendaten, IBANs, Krankheitsdetails). Der `changes`-Block enthält
  ausschließlich Metadaten, keine personenbezogenen Nutzdaten.
- Passwörter, Reset-Token, API-Tokens, VAPID-Keys, Session-Cookies.
- Mail-Bodys, Push-Payloads, externe API-Antworten.

## 5. Soll-Konzept für temporäre Supportfreigabe

Dieser Abschnitt beschreibt das Zielbild, das in den Folge-MVPs umzusetzen
ist; **nicht** Bestandteil der aktuellen Implementierung von MVP-004.

### 5.1 Lifecycle

1. **Kundenadmin gibt frei** (`support.access.granted`): wählt Scope
   (Bereiche / Datenklassen), Zweck (Freitext / Ticket-Referenz),
   Ablaufzeitpunkt (max. konfigurierbare Obergrenze, z. B. 24 h /
   7 Tage).
2. **Support nutzt Zugriff** (`support.session.started` /
   `support.session.ended`): jede Session ist einer Freigabe zugeordnet.
3. **Automatischer Ablauf**: System widerruft Freigabe und schreibt
   `support.access.revoked` mit `reason = expired`.
4. **Manueller Widerruf**: Kundenadmin kann jederzeit beenden.

### 5.2 Erforderliche, noch nicht vorhandene Felder

- Tabelle `support_access_grants` (`organization_id`, `granted_by_user_id`,
  `granted_to_user_id`, `scope`, `purpose`, `expires_at`, `revoked_at`,
  `revoked_reason`).
- Optional: `support_sessions` (`grant_id`, `started_at`, `ended_at`,
  `ip`, `user_agent`).
- `User`-Spalte `support_account` (bool) zur Markierung von
  Plattform-Support-Accounts.

### 5.3 Impersonation

`Permission::UserImpersonate` ist seit MVP-001 im Enum vorhanden, aber nicht
implementiert. Die Implementierung **darf erst erfolgen, wenn**:

- Eine `support_access_grants`-Freigabe für den Ziel-User existiert,
  *oder* der Plattform-Support den globalen `admin`-Notfallzugriff nutzt
  (eskalationspflichtig, separater Audit-Event).
- `support.impersonation.start` zwingend vor jedem Step in den
  Imitations-Kontext geschrieben wird.
- Eine UI-Banner-Komponente die laufende Impersonation für den
  Plattform-Support sichtbar macht.

## 6. Sichtbarkeit für Kunden

- **Kundenadmin (`admin`-Rolle einer Org)**: sieht alle `AuditLog`-Einträge
  der eigenen Organisation, inklusive Support-Events
  (`support.*`), gefiltert über die bestehende `AuditLogController`-Sicht.
- **`audit-log.view`-Permission**: bereits in den Profilen `admin`,
  `geschaeftsfuehrung`, `buchhaltung`, `support` enthalten (siehe
  Rollen-Matrix).
- **Export**: jeder Export des AuditLogs erzeugt selbst einen Audit-Eintrag
  (`audit.exported`).

## 7. Geltung für lokale Installationen

On-Premise-Betreiber sind selbst Plattform-Support für ihre Installation.
Die in Abschnitt 4 definierten Events gelten unverändert; die
Trennung Plattform-Support ↔ Org-Admin entfällt nur dann, wenn die
Installation als Einzel-Mandant betrieben wird. Auch in diesem Fall
empfiehlt sich, einen dedizierten technischen Account mit Rolle `support`
für Wartungs- und Diagnose-Aufgaben zu verwenden, um Aktionen sauber
von operativen Admin-Aktionen zu trennen.

## 8. Folge-Tickets (Implementierung)

Dieses Dokument ist verbindlicher Rahmen; die technische Umsetzung
verteilt sich auf:

- **#43** [MVP-044] Diagnose-Seite (Version, Lizenz, Queue, Scheduler,
  Mail, Storage, Backupstatus).
- **#44** [MVP-045] Supportbericht ohne fachliche Kundendaten exportieren
  (referenziert `support.report.exported`).
- Neues Folge-Issue (separat anzulegen): *temporäre Supportfreigabe +
  Session- und Impersonation-Lifecycle* (Abschnitt 5).

## 9. Änderungsverfahren

Änderungen an diesem Dokument erfordern:

1. Pull Request mit Diff auf dieses Dokument.
2. Anpassung der Rollen-Matrix (`rollen-matrix.md`) und ggf.
   `PermissionsSeeder::defaultRoleMatrix()`, falls sich Permissions der
   Rolle `support` ändern.
3. Anpassung / Ergänzung der Tests in
   `tests/Feature/Access/RoleProfilesTest.php` und
   `tests/Feature/Access/RoleControllerAuditTest.php`.
4. Eintrag im Audit-Event-Katalog (Tabelle in Abschnitt 4), wenn ein neues
   `event` eingeführt wird.
