# Kernklassifikationen

Status: Aktiv (MVP-030, Issue #30) • Quelle:
[Feature 024 — Klassifikationen / Tags / Datenqualität](features/024-klassifikationen-tags-datenqualitaet.md).

## 1. Zweck

Kontrollierte Wertelisten („Klassifikationen") für die Domänen, die
in Reports gruppiert werden. Plattform-weit definiert, pro Organisation
erweiterbar (siehe [Kategorien pro Org](kategorien-org.md), MVP-031).

## 2. Kernklassifikationen (Plattform-Defaults)

| Code                | Domäne           | Beispielwerte                                      |
| ------------------- | ---------------- | -------------------------------------------------- |
| `entry_type`        | Auftragstyp      | service, maintenance, installation, repair, advice |
| `activity`          | Tätigkeit        | analysis, install, configure, repair, document     |
| `defect_type`       | Fehlerart        | hardware, software, wiring, mechanical, user, env  |
| `root_cause`        | Ursache          | wear, misuse, defect, configuration, external      |
| `result`            | Ergebnis         | resolved, workaround, openIssue, escalated         |
| `priority`          | Priorität        | low, normal, high, critical                        |
| `goodwill_reason`   | Kulanzgrund      | warranty, customerRelation, escalation, error      |
| `rework_reason`     | Nacharbeitsgrund | qualityIssue, missingPart, additionalScope         |
| `product_group`     | Produktgruppe    | router, switch, server, hvac, lighting             |
| `dienstmittel_type` | Dienstmittel-Typ | tool, vehicle, instrument, device                  |

## 3. Tabelle `classifications`

```sql
CREATE TABLE classifications (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    organization_id BIGINT NULL,                       -- NULL = Plattform-Default
    domain          VARCHAR(40) NOT NULL,              -- z. B. defect_type
    code            VARCHAR(60) NOT NULL,              -- z. B. hardware
    label           VARCHAR(180) NOT NULL,
    label_i18n      JSON NULL,                         -- {"de":"Hardware","en":"Hardware"}
    sort_order      INT NOT NULL DEFAULT 100,
    color_hex       CHAR(7) NULL,                      -- #aabbcc
    icon            VARCHAR(60) NULL,                  -- Material Symbol
    active          BOOLEAN NOT NULL DEFAULT 1,
    deprecated_at   TIMESTAMP NULL,                    -- statt löschen
    description     TEXT NULL,
    created_at      TIMESTAMP NOT NULL,
    updated_at      TIMESTAMP NOT NULL,
    UNIQUE KEY uniq_code (organization_id, domain, code),
    INDEX idx_lookup (organization_id, domain, active)
);
```

`organization_id IS NULL` = Plattform-Default, sichtbar für alle Orgs.
Eine Org darf Defaults nicht ändern, aber per identischem `code`
einen Org-Override anlegen (eigener Datensatz mit `organization_id =
<org>`), der den Default überschreibt (Lookup §4).

## 4. Lookup-Logik

`ClassificationResolver::list(org, domain)`:

1. Liste alle aktiven Datensätze für `(organization_id = org)`
   mit `domain`.
2. Liste alle aktiven Plattform-Defaults für `domain`, deren `code`
   nicht in Schritt 1 vorkommt.
3. Sortiert nach `sort_order`, dann `label`.

Pro Lookup 60s Cache (`classifications.v1.{org}.{domain}`).

## 5. Verwendung in Domänen

- `diary_entries.entry_type` → FK auf `classifications.id`
  (domain = entry_type).
- Tagging-Tabellen (siehe Feature 024) referenzieren
  `classifications.id`.
- Audit speichert beim `set`-Event den **Code** (string), nicht die
  ID, damit historische Reports stabil bleiben.

## 6. Versionierung / Deaktivierung

- Eine Klassifikation darf **nicht** gelöscht werden, wenn historische
  Datensätze sie referenzieren.
- `active = false` (Soft-Disable) blendet sie aus neuen Auswahlen aus,
  alte Datensätze behalten den Wert.
- `deprecated_at` zeigt das Disable-Datum.

## 7. Permissions

| Permission                       | Wer                                |
| -------------------------------- | ---------------------------------- |
| `classification.list`            | Alle eingeloggten Nutzer (lesend). |
| `classification.platform.manage` | Plattform-Admin (siehe MVP-051).   |
| `classification.org.manage`      | Org-Admin (siehe MVP-031).         |

## 8. Akzeptanzkriterien

1. Tabelle `classifications` mit Seed der Plattform-Defaults (§2)
   im Migrations-Seeder.
2. `ClassificationResolver` mit Org-Override-Logik + Cache.
3. Soft-Disable lässt historische Daten unangetastet.
4. Unique-Constraint auf `(organization_id, domain, code)`.
5. Audit-Events `classification.created/updated/deactivated`.

## 9. Folge

- MVP-031 Kategorien pro Org pflegen.
- MVP-032 Pflicht pro Auftragstyp.
- MVP-033/034 Branchenprofile bringen Default-Klassifikationen mit.
