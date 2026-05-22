# Asset-/Objekt-Stammdaten

Status: Aktiv (MVP-035, Issue #35) • Quellen:
[Feature 009 — Inventar / Dienstmittel / Assets](features/009-inventar-dienstmittel-assets.md),
[Feature 027 — Produkt-/Objektakte / Lebenszyklus](features/027-produkt-objektakte-lebenszyklus.md).
• Folge-MVPs:
[Asset-Verknüpfungen](asset-verknuepfungen.md) (MVP-036),
[Objekt-Timeline](objekt-timeline.md) (MVP-037),
[Defekt-/Sperrstatus](asset-sperrstatus.md) (MVP-038).

## 1. Zweck

Minimal-Modell für **Assets / Objekte**: Geräte, Maschinen, Anlagen,
Werkzeuge, Fahrzeuge, Produkte beim Kunden. Eine Entität für beide
Sichten („Inventar" und „Objektakte"); Unterschiede durch
`asset_class` und `ownership`-Flags.

## 2. Tabelle `assets`

```sql
CREATE TABLE assets (
    id                  BIGINT PRIMARY KEY AUTO_INCREMENT,
    organization_id     BIGINT NOT NULL,
    asset_no            VARCHAR(60) NOT NULL,            -- org-eindeutige Nummer
    asset_class         VARCHAR(40) NOT NULL,            -- siehe §3
    category_code       VARCHAR(60) NULL,                -- Klassifikation aus Branchenprofil
    name                VARCHAR(180) NOT NULL,
    manufacturer        VARCHAR(180) NULL,
    model               VARCHAR(180) NULL,
    serial_no           VARCHAR(180) NULL,
    inventory_no        VARCHAR(180) NULL,               -- z. B. interne Inventarnummer
    customer_id         BIGINT NULL,                     -- Eigentümer Kunde (Objektakte)
    owned_by            VARCHAR(20) NOT NULL,            -- org|customer|external
    location_text       VARCHAR(255) NULL,
    location_lat        DECIMAL(10,7) NULL,
    location_lng        DECIMAL(10,7) NULL,
    status              VARCHAR(20) NOT NULL,            -- siehe §4
    health              VARCHAR(20) NOT NULL DEFAULT 'ok', -- ok|degraded|critical
    commissioned_on     DATE NULL,
    decommissioned_on   DATE NULL,
    warranty_until      DATE NULL,
    next_maintenance_on DATE NULL,
    next_inspection_on  DATE NULL,
    notes               TEXT NULL,
    custom              JSON NULL,                       -- profilspezifische Felder
    created_at          TIMESTAMP NOT NULL,
    updated_at          TIMESTAMP NOT NULL,
    UNIQUE KEY uniq_asset_no (organization_id, asset_no),
    INDEX idx_serial (organization_id, serial_no),
    INDEX idx_customer (customer_id, status),
    INDEX idx_status (organization_id, status, health)
);
```

`asset_no` ist menschlich lesbar (z. B. `AS-2025-0001`), generiert
durch `AssetNumberGenerator` (org-Sequence). `serial_no` ist
**Hersteller-Seriennummer** (nicht eindeutig per Org).

## 3. `asset_class`

| Code           | Beispiele                               |
| -------------- | --------------------------------------- |
| `device`       | Geräte beim Kunden (Router, Heizung)    |
| `machine`      | Maschinen / Anlagen                     |
| `tool`         | Werkzeuge, Messinstrumente              |
| `vehicle`      | Fahrzeuge                               |
| `installation` | Festinstallation (Baugruppe, Verteiler) |
| `software`     | Software-Asset (Lizenz, SaaS)           |
| `other`        | Sonstige                                |

## 4. `status`

`active`, `inMaintenance`, `inRepair`, `blocked` (siehe MVP-038),
`reserved`, `loanOut`, `replaced`, `decommissioned`, `lost`.

Übergänge per `AssetStatusMachine` mit erlaubten Wechseln; jeder
Wechsel = Audit-Event `asset.statusChanged`.

## 5. Service `AssetService`

- `create`, `update`, `decommission`, `transferOwnership`,
  `move` (Standort), `scheduleMaintenance`.
- Validierung: `decommissioned_on` impliziert `status =
decommissioned`; `customer_id` darf bei `owned_by = org` leer sein,
  muss bei `owned_by = customer` gesetzt sein.

## 6. Standort-Auflösung

`location_lat/_lng` ist optional. Wenn vorhanden, Anzeige in Karte
auf Asset-Seite (Folge-MVP). `location_text` ist Freitext oder vom
Kunden-Standort übernommen (Snapshot, nicht synchronisiert).

## 7. Custom-Felder

`custom` JSON nimmt profilspezifische Werte auf (z. B. IP-Adresse für
IT-Asset, kW-Leistung für HVAC). Schema-Definition pro Branchenprofil
(`asset_custom_schema` in
`database/data/branchprofiles/*.php`). Validator
`AssetCustomFieldsValidator` prüft Pflichtfelder.

## 8. Permissions

| Permission                | Wer                              |
| ------------------------- | -------------------------------- |
| `asset.view`              | Mitarbeitende.                   |
| `asset.create`            | Mitarbeitende mit `assetEditor`. |
| `asset.update`            | dito.                            |
| `asset.decommission`      | Org-Admin.                       |
| `asset.transferOwnership` | Org-Admin.                       |

Customer-Portal: Kunde sieht nur Assets mit `owned_by = customer` und
`customer_id = eigene Kundennr.`, ohne `inventory_no`, ohne `custom`-
Felder, die als `internalOnly` markiert sind.

## 9. Audit-Events

`asset.created`, `asset.updated`, `asset.statusChanged`,
`asset.healthChanged`, `asset.moved`, `asset.decommissioned`,
`asset.ownershipTransferred`.

## 10. Akzeptanzkriterien

1. Tabelle §2, Sequence-basierte `asset_no`.
2. `AssetStatusMachine` mit Tests pro erlaubtem/unerlaubtem Übergang.
3. `owned_by`-Konsistenz erzwungen.
4. Custom-Felder-Schema pro Branchenprofil validiert.
5. Customer-Portal-Filter §8 in Policy-Test gedeckt.
6. Audit-Events §9.

## 11. Out-of-scope (MVP-035)

- Asset-Tree (Eltern-/Kind-Assets) — Folge.
- QR-/Barcode-Scan-Workflow — Folge.
- Multi-Standort-Historie — kommt mit Timeline MVP-037 indirekt.

## 12. Folge

- MVP-036 Verknüpfungen Auftrag/Protokoll/Material/Anhang.
- MVP-037 Objekt-Timeline.
- MVP-038 Defekt-/Sperrstatus.
