# CSV-Import (MVP-Minimal)

Status: Aktiv (MVP-049, Issue #48) • Quelle:
[Feature 020 — Import / Migration / Onboarding](features/020-import-migration-onboarding.md).

## 1. Zweck

Org-Admin soll **Kunden, Projekte, Nutzer und Materialien** per CSV
in den Mandanten laden — ohne externe Tools, idempotent, mit klaren
Fehlerberichten.

## 2. Unterstützte Entitäten und Felder (MVP)

### 2.1 `customers.csv`

| Spalte         | Pflicht | Notizen                              |
| -------------- | ------- | ------------------------------------ |
| external_ref   | nein    | Stabiler Fremd-Schlüssel (Idempotenz) |
| name           | ja      |                                      |
| email          | nein    | RFC-5322                              |
| phone          | nein    |                                      |
| address_line   | nein    |                                      |
| postal_code    | nein    |                                      |
| city           | nein    |                                      |
| country        | nein    | ISO-3166-1 alpha-2                   |
| tax_id         | nein    |                                      |
| notes          | nein    |                                      |

### 2.2 `projects.csv`

| Spalte             | Pflicht | Notizen                          |
| ------------------ | ------- | -------------------------------- |
| external_ref       | nein    |                                  |
| customer_external_ref | ja   | muss in `customers` existieren   |
| name               | ja      |                                  |
| description        | nein    |                                  |
| start_date         | nein    | YYYY-MM-DD                       |
| end_date           | nein    | YYYY-MM-DD                       |
| state              | nein    | active, paused, closed (default active) |

### 2.3 `users.csv`

| Spalte         | Pflicht | Notizen                              |
| -------------- | ------- | ------------------------------------ |
| email          | ja      | unique innerhalb Plattform           |
| name           | ja      |                                      |
| role           | ja      | org-rolle (admin, operator, …)        |
| send_invite    | nein    | bool, default false                  |

### 2.4 `materials.csv`

| Spalte         | Pflicht | Notizen                              |
| -------------- | ------- | ------------------------------------ |
| external_ref   | nein    |                                      |
| code           | ja      | unique pro Org                       |
| name           | ja      |                                      |
| unit           | ja      | Stk, m, kg, h, l, …                  |
| default_price  | nein    | dezimal, Punkt als Separator         |
| vat_rate       | nein    | Prozent ganzzahlig                   |
| notes          | nein    |                                      |

## 3. Format-Spezifikation

- UTF-8 (mit oder ohne BOM).
- Trenner: `;` ODER `,` — Auto-Detektion über Header-Zeile.
- Zeichenkette in `"…"` bei Trenner-Konflikt; Escaping `""` für `"`.
- Erste Zeile = Header mit den oben definierten Spalten-Codes.
- Maximal 5 MB / 50 000 Zeilen pro Datei.

## 4. Prozess

`POST /admin/import/{entity}` — Datei-Upload (Permission
`org.import.{entity}`):

1. **Vorprüfung** (`CsvPreflightAnalyzer`):
   - Header-Validierung,
   - Pflichtfelder-Check,
   - Format-Checks (E-Mail, Datum, Zahlen, ISO-Land),
   - Eindeutigkeit innerhalb Datei,
   - Vorschau erste 20 Zeilen.
2. **Bestätigung**: User sieht Vorschau + Statistik („402 neue, 18
   Aktualisierungen, 5 Fehler"). Erst nach Bestätigung →
3. **Ausführung** (`CsvImportJob`, Queue):
   - In Chunks à 500.
   - Upsert nach `external_ref` (falls vorhanden) sonst nach
     fachlichem Schlüssel (`email`, `code`).
   - Datenbank-Transaktion pro Chunk.
   - Fehler-Zeilen: Datei `errors_{import_id}.csv` mit
     Original-Inhalt + Spalte `_error`.
4. **Report** (`import_runs`-Tabelle): Zeilen, Erfolg, Warn,
   Fehler, Dauer, Aufrufer, Hash der Eingabedatei.

## 5. Datenmodell

### 5.1 `import_runs`

| Feld           | Typ           | Notizen                          |
| -------------- | ------------- | -------------------------------- |
| id             | uuid          |                                  |
| organization_id| uuid          |                                  |
| entity         | enum          | customers, projects, users, materials |
| state          | enum          | preflight, awaitingApproval, running, succeeded, partial, failed |
| input_filename | string        |                                  |
| input_hash     | string        | SHA-256                          |
| rows_total     | int           |                                  |
| rows_created   | int           |                                  |
| rows_updated   | int           |                                  |
| rows_failed    | int           |                                  |
| started_at     | datetime null |                                  |
| finished_at    | datetime null |                                  |
| created_by     | uuid          |                                  |
| created_at     | datetime      |                                  |

### 5.2 `import_run_errors`

| Feld         | Typ      | Notizen                |
| ------------ | -------- | ---------------------- |
| id           | uuid     |                        |
| import_run_id| uuid     |                        |
| row_number   | int      | 1-basiert ohne Header   |
| field        | string null |                     |
| code         | string   | required, format, unique, fkMissing, … |
| message      | string   | i18n                   |

## 6. Idempotenz

- Mit `external_ref`: Upsert (vorhandene werden aktualisiert, neue
  angelegt; nicht gelistete bleiben unverändert).
- Ohne `external_ref`: Match nach `email`/`code` — wenn vorhanden,
  Update; sonst Insert.
- Nie wird ein bestehender Datensatz gelöscht.

## 7. Permissions

| Permission                  |
| --------------------------- |
| `org.import.customers`      |
| `org.import.projects`       |
| `org.import.users`          |
| `org.import.materials`      |

Plus `org.import.viewReports` für `import_runs`-Liste.

## 8. Audit

`import.preflightFailed`, `import.confirmed`, `import.started`,
`import.finished` (mit Counts), `import.partial`.

## 9. Akzeptanzkriterien

1. Endpoints + Job für jede der 4 Entitäten.
2. Preflight + Vorschau + Bestätigungsschritt.
3. Idempotenz §6 mit Tests (Insert, Update, No-Op).
4. Fehler-Datei downloadbar; UI listet `import_runs`.
5. Performance: 10 000 Zeilen Kunden < 60 s im Test.
6. Audit-Events §8.
7. Deutsche Fehlermeldungen + Codes für jede Validierung.

## 10. Out-of-scope (MVP-049)

- Excel-Import (.xlsx) — nur CSV.
- Skript-/Mapping-Editor für freie Spalten-Zuordnung.
- Import von Aufträgen/Zeiterfassung/Protokollen.

## 11. Folge

- MVP-050 Demo-Mandant nutzt Import-Pipeline für Beispieldaten.
