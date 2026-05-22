# Asset-Verknüpfungen

Status: Aktiv (MVP-036, Issue #36) • Quellen:
[Feature 009 — Inventar / Dienstmittel / Assets](features/009-inventar-dienstmittel-assets.md),
[Feature 027 — Produkt-/Objektakte](features/027-produkt-objektakte-lebenszyklus.md).
• Aufbauend auf: [Asset-Stammdaten](asset-stammdaten.md) (MVP-035).

## 1. Zweck

Ein Asset wird im Alltag mit **Aufträgen, Protokollen, Materialien
und Anhängen** verknüpft. Diese Verknüpfungen sind die Grundlage für
Timeline (MVP-037) und Auswertungen.

## 2. Verknüpfungstabellen

### 2.1 `asset_diary_entry`

```sql
CREATE TABLE asset_diary_entry (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    asset_id        BIGINT NOT NULL,
    diary_entry_id  BIGINT NOT NULL,
    role            VARCHAR(20) NOT NULL DEFAULT 'subject', -- subject|tool|reference
    created_at      TIMESTAMP NOT NULL,
    UNIQUE KEY uniq_link (asset_id, diary_entry_id, role),
    INDEX idx_entry (diary_entry_id)
);
```

`role`:

- `subject` — Asset ist Gegenstand des Auftrags (Wartung, Reparatur).
- `tool` — Asset wurde im Auftrag genutzt (Werkzeug, Fahrzeug).
- `reference` — Asset wird referenziert, aber nicht direkt bearbeitet.

### 2.2 `asset_protocol`

```sql
CREATE TABLE asset_protocol (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    asset_id        BIGINT NOT NULL,
    protocol_id     BIGINT NOT NULL,
    role            VARCHAR(20) NOT NULL DEFAULT 'subject',
    created_at      TIMESTAMP NOT NULL,
    UNIQUE KEY uniq_link (asset_id, protocol_id, role),
    INDEX idx_protocol (protocol_id)
);
```

### 2.3 Materialien

`material_usages` (existiert bereits) wird um optionalen
`asset_id` ergänzt: „Dieses Ersatzteil wurde an Asset X verbaut."

```sql
ALTER TABLE material_usages
  ADD COLUMN asset_id BIGINT NULL AFTER diary_entry_id,
  ADD INDEX idx_asset (asset_id, used_on);
```

### 2.4 Anhänge

Polymorphes `attachments` (existiert) wird zusätzlich für
`attachable_type = App\Models\Asset` genutzt. Die Asset-Detailseite
listet alle direkt am Asset hängenden Anhänge **plus** alle Anhänge
verlinkter Aufträge / Protokolle / Materialeinsätze in einem
„Dokumente"-Tab mit Filter nach Quelle.

## 3. Service `AssetLinkService`

- `linkDiaryEntry(asset, entry, role)`,
  `unlinkDiaryEntry(asset, entry, role)`.
- `linkProtocol(asset, protocol, role)`,
  `unlinkProtocol(asset, protocol, role)`.
- `setMaterialUsage(usage, asset)`.

Validierung: `subject`-Verknüpfung pro `(asset, entry)` /
`(asset, protocol)` einmalig; `tool`/`reference` mehrfach erlaubt
(aber jeweils unique pro `role`).

## 4. UI

### 4.1 Auftragsdetail

- Sektion „Betroffene Objekte" mit Asset-Liste (Pill: `role`).
- „+ Asset hinzufügen" öffnet Such-Dialog (Asset-Nummer, Name,
  Seriennummer).

### 4.2 Asset-Detail

- Tab „Aufträge" — verlinkte Diary Entries.
- Tab „Protokolle" — verlinkte Protokolle.
- Tab „Material" — Material-Einsätze mit `asset_id = self`.
- Tab „Dokumente" — siehe §2.4.

## 5. Permissions

| Permission          | Wer                                    |
| ------------------- | -------------------------------------- |
| `asset.link.create` | Wer Auftrag/Protokoll bearbeiten darf. |
| `asset.link.delete` | dito.                                  |

## 6. Audit-Events

`asset.linked` (mit `target_type`, `target_id`, `role`),
`asset.unlinked`, `material.assetAssigned`,
`material.assetUnassigned`.

## 7. Akzeptanzkriterien

1. Tabellen §2 inklusive `material_usages.asset_id`.
2. Service `AssetLinkService` mit Validierung der Eindeutigkeit.
3. UI-Tabs auf Asset-Seite zeigen verlinkte Objekte performant
   (Eager-Loading; N+1 vermeiden).
4. „Dokumente"-Tab fasst alle Quellen zusammen mit Filter.
5. Tests: Subject darf nur einmal verlinkt sein; Tool mehrfach;
   Unlink-Audit korrekt.

## 8. Out-of-scope (MVP-036)

- Bulk-Verknüpfung (mehrere Assets per Klick) — Folge.
- Asset-Baum-Sichten — Folge.

## 9. Folge

- MVP-037 Objekt-Timeline.
