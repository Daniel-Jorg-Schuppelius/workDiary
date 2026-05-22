# Kategorien pro Organisation

Status: Aktiv (MVP-031, Issue #31) • Quelle:
[Feature 024 — Klassifikationen / Tags / Datenqualität](features/024-klassifikationen-tags-datenqualitaet.md).
• Aufbauend auf: [Kernklassifikationen](kernklassifikationen.md) (MVP-030).

## 1. Zweck

Org-Admins sollen eigene Klassifikationswerte je Domäne pflegen,
Plattform-Defaults überschreiben oder Plattform-Werte org-lokal
deaktivieren, **ohne** historische Daten zu zerstören.

## 2. Funktionsumfang

### 2.1 Pflege-UI (`/settings/classifications`)

- Sidebar: Liste aller Domains (z. B. `entry_type`, `defect_type`).
- Hauptbereich: Tabelle mit `code`, `label`, `sort_order`, `active`,
  Spalte „Quelle" (Platform / Org / Override).
- Aktionen pro Zeile:
  - **Neu** (Org-spezifisch): legt Datensatz mit
    `organization_id = aktuelle Org` an.
  - **Override**: dupliziert Plattform-Default in Org-Scope, Label
    und Sort sind editierbar.
  - **Deaktivieren**: setzt `active = false` (Plattform-Default per
    leerem Override deaktivierbar — siehe §3).
  - **Reaktivieren**: setzt `active = true`.

### 2.2 Re-Order

Drag&Drop ändert `sort_order` in 10er-Schritten. Sortieränderungen
sind auditiert (`classification.sortChanged`).

## 3. Plattform-Default org-lokal deaktivieren

Mechanik: Org-Admin erzeugt einen Override mit gleichem `domain` +
`code`, `active = false`. Der Resolver (siehe
[Kernklassifikationen §4](kernklassifikationen.md)) liefert den Wert
dadurch in dieser Org nicht mehr aus.

Historische Datensätze, die diesen Code referenzieren, bleiben
gültig — Reports zeigen den Wert mit Pill „deaktiviert" an.

## 4. Validierung

- `code`: `[a-z][a-z0-9_]{1,58}`, eindeutig je `(org, domain)`.
- `label`: 1..180 Zeichen, Pflicht.
- `color_hex`: optional, Format `#RRGGBB`.
- `icon`: optional, muss Material-Symbols-Outlined-Name sein
  (Whitelist-Datei `config/icons.php`).
- Schutz vor versehentlichem Löschen: `DELETE` nur, wenn keine
  Referenz aus Domain-Tabellen existiert; sonst HTTP 409
  `error.code = "classification.referencedByEntities"`.

## 5. Import

`POST /api/orgs/{org}/classifications/import` akzeptiert CSV mit
Spalten `domain,code,label,sort_order,color_hex,icon`. Idempotent:
existierender Code → Update, neuer Code → Insert. Maximal 1000 Zeilen
pro Import; größere via Job (siehe CSV-Import-MVP-049).

## 6. Permissions

| Permission                            | Wer                          |
| ------------------------------------- | ---------------------------- |
| `classification.org.view`             | Org-Mitglieder.              |
| `classification.org.manage`           | Org-Admin.                   |
| `classification.org.deactivateDefault`| Org-Admin (gesonderte Perm). |
| `classification.org.import`           | Org-Admin.                   |

## 7. Audit-Events

`classification.created`, `classification.updated`,
`classification.deactivated`, `classification.reactivated`,
`classification.sortChanged`, `classification.imported`.

## 8. Akzeptanzkriterien

1. UI listet Domains und Werte, unterscheidet Platform/Org/Override.
2. Override und org-lokales Deaktivieren funktionieren ohne
   Historie-Bruch.
3. CSV-Import idempotent + Audit-Event.
4. Drag&Drop-Sort speichert `sort_order` und auditiert.
5. Löschschutz §4 mit Test.

## 9. Out-of-scope (MVP-031)

- Bulk-Edit von Labels.
- Übersetzungs-UI für `label_i18n` (i18n-Pflege-Werkzeug folgt).
- Verschachtelte Klassifikationen (z. B. Kategorie-Baum).

## 10. Folge

- MVP-032 Pflicht pro Auftragstyp.
