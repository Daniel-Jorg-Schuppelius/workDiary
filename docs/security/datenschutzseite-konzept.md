# Datenschutzseite für Org-Admins — Konzept

Status: Konzept (MVP-005, Issue #5) • Quelle:
[Feature 016 — Datenschutz, Sicherheit und Datenlebenszyklus](../features/016-datenschutz-dsgvo-datenlebenszyklus.md)
• Ergänzt: [Supportzugriff-Grundsätze](supportzugriff-grundsaetze.md),
[Rollen-Matrix](rollen-matrix.md).

Dieses Dokument beschreibt das Zielbild der **Datenschutzseite für Org-Admins**:
eine zentrale Übersicht, die ein Kundenadmin aufrufen kann, um den
Datenschutz- und Sicherheitszustand seiner Organisation einzusehen, zu
exportieren und administrative Entscheidungen (Löschung, Tokenwiderruf,
Supportfreigabe) anzustoßen. Es legt Routen, Sektionen, Datenquellen,
Berechtigungen und Akzeptanzkriterien für die spätere Umsetzung verbindlich
fest.

Dies ist ein **Konzept-Dokument**, kein Implementierungs-Ticket — die
Umsetzung verteilt sich auf mehrere Folge-MVPs (siehe Abschnitt 10).

## 1. Ziel und Adressat

| Aspekt              | Festlegung                                                                                |
| ------------------- | ----------------------------------------------------------------------------------------- |
| Primärer Adressat   | **Org-Admin** (Rolle `admin` mit `team_id = org.id`)                                      |
| Sekundärer Adressat | **Geschäftsführung** (Rolle `geschaeftsfuehrung`, read-only)                              |
| Plattform-Support   | sieht die Seite, kann sie nicht für andere Orgs konsolidieren                             |
| Endnutzer (`user`)  | kein Zugriff                                                                              |
| Zweck               | DSGVO-Transparenzpflichten erfüllen, Vertrauen schaffen, administrative Kontrolle bündeln |

Die Datenschutzseite ist **ein zusammenführender Lesepunkt**, kein neuer
Datenspeicher. Sie aggregiert ausschließlich Informationen, die an anderer
Stelle bereits modelliert sind (`AuditLog`, `Organization`, `User`,
`personal_access_tokens`, `sessions`, Config-Werte).

## 2. Zugriff und Berechtigungen

### 2.1 Routen (vorgeschlagen)

| Methode | Pfad                            | Name                               | Permission                  |
| ------- | ------------------------------- | ---------------------------------- | --------------------------- |
| GET     | `/admin/privacy`                | `admin.privacy.index`              | `privacy.view`              |
| GET     | `/admin/privacy/sessions`       | `admin.privacy.sessions.index`     | `privacy.sessions.view`     |
| DELETE  | `/admin/privacy/sessions/{id}`  | `admin.privacy.sessions.destroy`   | `privacy.sessions.revoke`   |
| GET     | `/admin/privacy/tokens`         | `admin.privacy.tokens.index`       | `privacy.tokens.view`       |
| DELETE  | `/admin/privacy/tokens/{id}`    | `admin.privacy.tokens.destroy`     | `privacy.tokens.revoke`     |
| GET     | `/admin/privacy/integrations`   | `admin.privacy.integrations.index` | `privacy.integrations.view` |
| GET     | `/admin/privacy/exports`        | `admin.privacy.exports.index`      | `privacy.exports.view`      |
| GET     | `/admin/privacy/support-access` | `admin.privacy.support.index`      | `privacy.support.view`      |
| GET     | `/admin/privacy/report.pdf`     | `admin.privacy.report`             | `privacy.report.export`     |

### 2.2 Neue Permissions (Folge-MVP)

Vorgeschlagene Erweiterung in `App\Enums\User\Permission` und Aufnahme in
`PermissionsSeeder::defaultRoleMatrix()`:

| Permission                  | admin | geschaeftsfuehrung | support |
| --------------------------- | :---: | :----------------: | :-----: |
| `privacy.view`              |   ✓   |         ✓          |    ✓    |
| `privacy.sessions.view`     |   ✓   |         ✓          |    ✓    |
| `privacy.sessions.revoke`   |   ✓   |                    |         |
| `privacy.tokens.view`       |   ✓   |         ✓          |    ✓    |
| `privacy.tokens.revoke`     |   ✓   |                    |         |
| `privacy.integrations.view` |   ✓   |         ✓          |    ✓    |
| `privacy.exports.view`      |   ✓   |         ✓          |    ✓    |
| `privacy.support.view`      |   ✓   |         ✓          |    ✓    |
| `privacy.report.export`     |   ✓   |         ✓          |         |

Plattform-Support sieht die Seite **read-only** für die Org, in deren Kontext
er sich gerade befindet (siehe
[Supportzugriff-Grundsätze §3](supportzugriff-grundsaetze.md)). Er darf
**keine** Tokens/Sessions widerrufen — das ist Sache des Kundenadmins.

## 3. Sektionen der Seite

### 3.1 Kopfbereich: Datenschutz-Status auf einen Blick

- Organisationsname, Betriebsmodus (SaaS / Private Cloud / On-Premise — aus
  Config ableitbar).
- Anzahl aktiver Nutzer, Anzahl aktiver Sessions, Anzahl aktiver
  API-Tokens.
- Letzter Mandantenexport (Datum / Auslöser).
- Aktive Supportfreigaben (Anzahl, nächster Ablauf) — siehe
  [Supportzugriff-Grundsätze §5](supportzugriff-grundsaetze.md).
- AVV-/DPA-Status (Link auf Dokument, Stand des Abschlusses).

### 3.2 Datenkategorien & Aufbewahrung

Tabellarische Darstellung der personenbezogenen Datenkategorien.
Quelle: statisch ausgelieferte Konfiguration (`config/privacy.php`,
noch anzulegen) plus dynamischer Datensatz-Count pro Org.

| Kategorie                  | Modelle (Beispiel)                     | Sensibilitätsstufe | Aufbewahrung (Vorschlag)       | Löschpfad                          |
| -------------------------- | -------------------------------------- | ------------------ | ------------------------------ | ---------------------------------- |
| Mitarbeitende              | `User`, `UserGroup`                    | hoch               | bis Vertragsende + 0           | Org-Admin → User-Löschung          |
| Arbeitszeit                | `Timesheet`, `Attendance`              | hoch               | 10 Jahre (GoBD)                | gesperrt nach Lock, nicht löschbar |
| Lohnabwesenheiten          | `SickLeave`, `Vacation`                | besonders sensibel | gemäß Tarif/Gesetz             | Org-Admin nach Frist               |
| Tagebuch                   | `DiaryEntry`, `Comment`                | mittel             | 5 Jahre (konfigurierbar)       | Org-Admin                          |
| Touren / Standorte         | `TravelLog`, `Tour`                    | hoch               | 2 Jahre (Vorschlag)            | automatischer Löschlauf            |
| Spesen / Reisekosten       | `Expense`, `PerDiemTrip`, `PerDiemDay` | hoch               | 10 Jahre (GoBD)                | gesperrt, archiviert               |
| Kundenstamm                | `Customer`, Customer-Contacts          | mittel             | bis Geschäftsbeziehung + Frist | Org-Admin                          |
| Anhänge (Dokumente, Fotos) | `Attachment`                           | je nach Inhalt     | mit übergeordnetem Datum       | gemeinsam mit Owner-Record         |
| Unterschriften             | `Attachment` (Signatur-Pfad)           | besonders sensibel | wie Auftrag                    | gemeinsam mit Auftrag              |
| Qualifikationen            | `Qualification`                        | hoch               | bis Vertragsende               | Org-Admin                          |
| Push-Subscriptions         | `PushSubscription`                     | gering             | bei Abmeldung                  | automatisch bei Logout/Cleanup     |
| Audit-Protokoll            | `AuditLog`                             | hoch               | 24 Monate (Vorschlag)          | systemseitiger Rotations-Job       |

Die endgültigen Fristen werden in einem Folge-MVP normativ in
`config/privacy.php` festgeschrieben.

### 3.3 Aktive Sessions

Quelle: Tabelle `sessions` (Laravel) gefiltert auf Nutzer der Organisation
(`user_id IN (Org-Mitglieder)`).

Anzeige je Eintrag:

- Nutzer (Name, Rolle).
- IP-Adresse, User-Agent (gekürzt / lesbar dargestellt).
- Letzte Aktivität (`last_activity`).
- Geografische Region (nur Land, optional — keine Geolocation-Auflösung
  ohne Konfigurationsflag).

Aktionen:

- **Session widerrufen**: löscht `sessions`-Zeile und schreibt
  `session.revoked` in den `AuditLog`
  (`changes = { revoked_user_id, by_user_id }`).

### 3.4 API-Tokens (Sanctum)

Quelle: `personal_access_tokens` (Tabelle aus
`2026_04_30_053546_create_personal_access_tokens_table.php`), gefiltert auf
Org-Nutzer.

Anzeige je Token:

- Name, zugehöriger Nutzer.
- Erstellungs-, Ablauf- und letztes Nutzungsdatum.
- Abilities (Scopes).

Aktionen:

- **Token widerrufen**: löscht Token, schreibt `api-token.revoked`
  (`changes = { token_name, owner_user_id, by_user_id }`).

### 3.5 Externe Integrationen / Datenflüsse

Quelle: Konfiguration zur Laufzeit. Die Seite zeigt nur **konfigurierte
und aktive** Integrationen, jeweils mit folgender Information:

| Integration                                             | Quelle                                   | Daten, die abfließen                            |
| ------------------------------------------------------- | ---------------------------------------- | ----------------------------------------------- |
| Mail-Versand (SMTP / Mailgun / Postmark / Resend / SES) | `config/mail.php`, `config/services.php` | Empfänger-Mailadresse, Betreff, Body            |
| Web-Push                                                | `config/webpush.php` (VAPID)             | Endpoint-URL (Browser-Hersteller), Push-Payload |
| Geocoding (Nominatim)                                   | `App\Services\NominatimGeocoder`         | Adress-Strings                                  |
| Slack (sofern konfiguriert)                             | `config/services.php`                    | Benachrichtigungstexte                          |
| AWS S3 (sofern als Storage aktiv)                       | `config/filesystems.php`                 | Anhangsbytes, Pfadnamen                         |

Jede Zeile trägt einen klaren **Aktiv-Status** (aktiv / inaktiv / nicht
konfiguriert) und einen Link zur Dokumentation des jeweiligen Anbieters.

**Datenfluss-Negativaussage** (statisch eingeblendet):

- WorkDiary nutzt **keine** Tracking-, Analytics- oder Werbe-Dienste.
- Es findet **keine** produktübergreifende Auswertung von Kundendaten
  statt (siehe Feature 016, _Produktversprechen_).

### 3.6 Letzte Exporte

Quelle: `AuditLog` gefiltert auf Events `tenant.export.requested`,
`tenant.export.completed`, sowie die bestehenden CSV/PDF-Exporte
(`/diary/export.*`, `/customers/export`, `/travel-logs/export`, …) sofern
sie das `audit.exported`-Event schreiben.

Anzeige je Eintrag:

- Datum, Auslöser, Scope, Größe (falls bekannt), Format.

Aktionen:

- Neuen Mandantenexport anstoßen (führt auf
  `OrganizationController::export` mit Audit).

### 3.7 Letzte Supportzugriffe

Quelle: `AuditLog` gefiltert auf Event-Präfix `support.*` (siehe
[Supportzugriff-Grundsätze §4.2](supportzugriff-grundsaetze.md#42-supportzugriff-selbst)).

Anzeige je Eintrag:

- Zeitpunkt, Support-Identität, Ticket-Referenz, Scope, Dauer.
- Bei Impersonation: Ziel-User.

Aktionen:

- Aktive Supportfreigaben sofort widerrufen (`support.access.revoked`
  mit `reason = revoked_by_admin`).

### 3.8 DSGVO-Aktionen

| Aktion                             | Umsetzung                                                 |
| ---------------------------------- | --------------------------------------------------------- |
| **Auskunft** (Art. 15)             | Mandantenexport ZIP über `OrganizationController::export` |
| **Berichtigung** (Art. 16)         | über vorhandene CRUD-Pfade je Datensatz                   |
| **Löschung** (Art. 17)             | per Folge-MVP konkretisiert (Tenant-Delete-Workflow)      |
| **Einschränkung** (Art. 18)        | `archived`-Pfad pro Auditable-Model                       |
| **Datenübertragbarkeit** (Art. 20) | Mandantenexport, Format dokumentiert in Export-ADR        |
| **Widerspruch** (Art. 21)          | über Org-Admin, dokumentiert im AuditLog                  |

Jede DSGVO-Aktion erzeugt einen Eintrag im `AuditLog`
(`tenant.export.*`, `tenant.delete.*`) und wird in Abschnitt 3.6 sichtbar.

### 3.9 Datenschutzbericht (PDF)

Ein Org-Admin kann einen **stichtagsbezogenen Datenschutzbericht** als PDF
exportieren. Inhalt: Sektionen 3.1 bis 3.8 plus Aufbewahrungs-Snapshot.
Der Bericht enthält **keine** personenbezogenen Detaildaten, nur
aggregierte Zählungen, Konfigurations- und Audit-Statistiken — geeignet
für interne Datenschutzbeauftragte und Behörden.

Der Export selbst schreibt einen Eintrag `audit.exported` mit
`changes = { filter: "privacy_report", row_count: ... }`.

## 4. Datenquellen und Audit-Events

Pflicht-Events, die die Datenschutzseite nutzt (ergänzend zum Katalog
in [Supportzugriff-Grundsätze §4](supportzugriff-grundsaetze.md#4-auditpunkte-verbindlich)):

| Event                     | Auslöser                                    | `changes`-Inhalt                            |
| ------------------------- | ------------------------------------------- | ------------------------------------------- |
| `session.revoked`         | Org-Admin widerruft Session                 | `{ revoked_user_id, by_user_id }`           |
| `api-token.created`       | neuer Sanctum-Token erzeugt                 | `{ token_name, owner_user_id, abilities }`  |
| `api-token.revoked`       | Token gelöscht                              | `{ token_name, owner_user_id, by_user_id }` |
| `privacy.report.exported` | Datenschutzbericht generiert                | `{ filter, generated_at }`                  |
| `integration.changed`     | externe Integration aktiviert / deaktiviert | `{ integration, from, to }`                 |

## 5. Sicherheits- und Trennungs-Pflichten

- **Strikte Mandantenisolation.** Alle Abfragen MÜSSEN über den
  `OrganizationScope` laufen oder explizit gegen `organization_id` filtern
  (vgl. [tenant-audit-2026.md](tenant-audit-2026.md)).
- **Keine Klartext-Token.** Die Datenschutzseite zeigt API-Tokens
  niemals im Klartext (Sanctum hasht ohnehin).
- **Read-only-Default.** Jede Aktion (Widerruf, Export) erfordert eine
  separate Permission und schreibt einen Audit-Eintrag.
- **Kein Cross-Org-Zugriff für Plattform-Support.** Auch der Support
  sieht die Datenschutzseite nur für eine Org pro Session, niemals
  aggregiert.
- **Keine personenbezogenen Detaildaten im PDF-Bericht.** Aggregation
  ist Pflicht (Anonymisierung gemäß Feature 041 / MVP-045).

## 6. UI-Hinweise

- Einstiegspunkt: Org-Admin-Menü, Sektion „Sicherheit & Datenschutz".
- Erste Bildschirmseite ist die Übersicht (3.1). Detailsektionen
  jeweils als eigene Unterseite (deep-linkbar).
- Sprache: ausschließlich Deutsch (alle Permissions, Audit-Labels,
  Aufbewahrungs-Beschreibungen liegen in `lang/de/`).
- Hinweis-Banner, wenn Plattform-Support gerade aktiv ist (visuelle
  Bestätigung gemäß Supportzugriff-Grundsätze §5.3).

## 7. Akzeptanzkriterien (für Umsetzungs-MVP)

1. Ein Org-Admin erreicht die Datenschutzseite über `/admin/privacy`
   ohne weitere Klicks aus dem Org-Admin-Menü.
2. Alle in Abschnitt 3 genannten Sektionen sind erreichbar.
3. Plattform-Support sieht die Seite read-only, sieht keinen
   „Widerrufen"-Knopf für Sessions/Tokens.
4. Geschäftsführung sieht die Seite read-only inkl. PDF-Export.
5. Endnutzer (`user`-Rolle) erhält 403 auf jeder Route der Seite.
6. Jede schreibende Aktion (Session-/Token-Widerruf, Support-Freigabe
   widerrufen, Bericht-Export) erzeugt einen `AuditLog`-Eintrag mit dem
   in §4 spezifizierten `event` und Inhalt.
7. Der PDF-Bericht enthält keine personenbezogenen Detaildaten.
8. Alle Permissions aus §2.2 sind im Enum, im `PermissionsSeeder` und in
   `lang/de/access.php` vorhanden.
9. Tests prüfen Sichtbarkeit pro Rolle (admin / geschaeftsfuehrung /
   support / user) und Idempotenz der Permission-Seeds.

## 8. Out of scope (gehört in spätere MVPs)

- Automatischer Löschlauf mit Review-Modus (siehe Feature 016, „Später").
- Feldverschlüsselung sensibler Daten (Feature 016, „Später").
- Regionale Datenhaltung für SaaS.
- Verbraucher-/Endkunden-Datenschutzportal (Customer-Portal — eigenes
  Folge-MVP).
- Anpassbare Aufbewahrungsfristen pro Org (initial: globale Defaults
  aus `config/privacy.php`).

## 9. Abhängigkeiten

- [Supportzugriff-Grundsätze](supportzugriff-grundsaetze.md) (MVP-004) —
  liefert das Audit-Event-Schema und die Trennung Plattform-Support ↔
  Org-Admin.
- [Rollen-Matrix](rollen-matrix.md) (MVP-003) — wird um die neuen
  `privacy.*`-Permissions erweitert.
- [Tenant-Audit 2026](tenant-audit-2026.md) — sichert die Mandanten-
  isolation der Abfragen.
- [Export-Autorisierungs-ADR](adr-export-authorization.md) — Rahmen für
  PDF/ZIP-Exporte.

## 10. Folge-Tickets (Implementierung)

Die Umsetzung wird in mindestens drei Folge-MVPs zerlegt:

1. **Konfiguration `config/privacy.php` + Permissions + Routen-Grundgerüst**
   (`privacy.*`, leere Sektionen, Test der Sichtbarkeitsmatrix).
2. **Sektionen 3.1 – 3.6** (Übersicht, Datenkategorien, Sessions, Tokens,
   Integrationen, Exporte).
3. **Sektionen 3.7 – 3.9** (Supportzugriffe, DSGVO-Aktionen,
   Datenschutzbericht-PDF).

Eng verzahnt mit:

- **#43** [MVP-044] Diagnose-Seite — teilt sich UI-Sektion „Integrationen
  / Systemzustand" mit der Datenschutzseite; Inhalte bleiben getrennt
  (Datenschutzseite = Daten/Personenbezug, Diagnose-Seite =
  Systemzustand).
- **#44** [MVP-045] Supportbericht ohne fachliche Kundendaten —
  liefert den Aggregationsmechanismus, den der PDF-Bericht (3.9)
  wiederverwendet.

## 11. Änderungsverfahren

Änderungen an diesem Konzept erfordern:

1. Pull Request mit Diff auf dieses Dokument.
2. Synchronisation mit `docs/security/rollen-matrix.md`, falls neue
   Permissions hinzukommen.
3. Synchronisation mit
   [Supportzugriff-Grundsätze §4](supportzugriff-grundsaetze.md#4-auditpunkte-verbindlich),
   falls neue Audit-Events eingeführt werden.
4. Status-Aktualisierung des Quell-Features 016, sobald ein Folge-MVP
   in Umsetzung geht.
