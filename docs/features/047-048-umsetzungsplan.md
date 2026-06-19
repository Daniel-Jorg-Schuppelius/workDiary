# Umsetzungsplan: Fertigungsaufträge (047) & Lagerwirtschaft (048)

Stand 2026-06-16. Detailplan für die als „Planned" geschnittenen MVP-060 bis
MVP-074. **Noch kein Code** — dieser Plan ist zur Abnahme gedacht.

## Leitlinien (aus dem Bestand abgeleitet)

- **Erweitern statt neu bauen.** Wir nutzen die vorhandenen Bausteine:
  `Material`/`MaterialUsage`, den vollständigen Procedure-Kern
  (`ProcedureTemplateVersion`, `ProcedureRun`, `ProcedureStepRun`,
  `ProcedureDeviation`), `Numbering\NumberAuthority` + `NumberSequenceService`,
  `ExternalReference`/`LexofficeArticle`, das Plugin-System (`Plugins\Contracts`)
  und — als Vorlage für die Datenführerschaft — `Finance\BillingModeResolver`
  (+ `BillingMode`-Enum, `BillingModeLockedException`).
- **Datenführerschaft je Datenbereich** wie in Feature 008: pro Organisation
  genau ein führendes System für `inventory`. Muster identisch zu `BillingMode`.
- **Faktura-Übergabe** baut auf dem bestehenden `FacturationTarget`-Vertrag
  (Feature 045, `Services\Finance\Targets\*`) auf — MVP-074 ist ein neuer
  Lieferweg, kein neuer Kern.
- **Konventionen** (verbindlich, aus Projektgedächtnis):
  Sqid in URLs/Formularen (`HasSqid` + `DecodesSqidInputs`), Formulare als
  Modal-Dialoge (`x-modal _form_dialog`), kurze explizite Index-/Unique-Namen
  in Migrationen (64-Zeichen-MySQL-Limit; SQLite-Dev verdeckt es), Geld/Mengen
  als **Decimal** (nie float), `Auditable` + Hash-Kette wo nachweisrelevant,
  `BelongsToOrganization`-Scope für alle neuen Entitäten, i18n-Parität
  de/en/fr/it/es, PHPStan Level 8 grün, Tests pro Slice.

## Phasenüberblick

| Phase | MVPs | Inhalt | Abhängigkeit |
| --- | --- | --- | --- |
| P1 | 060 | Kanonischer Artikelstamm (Fundament) | — |
| P2 | 061, 062, 065 | Stücklisten/Rezepturen/Parameter + Fertigungsauftrag + Rückmeldungen | P1 |
| P3 | 063, 064 | Mobile Prozedurausführung + serverseitige Warte-/Trockenschritte | P2, Procedure-Kern |
| P4 | 066, 067, 068, 069, 070 | Lokaler Lagerkern: Führerschaft, Journal, Reservierung, Bewegungen, Inventur, Bewertung | P1 |
| P5 | 071, 074 | Fertigung ↔ Lager verbinden; Auslieferung + Faktura-Übergabe | P2, P4, Feature 045 |
| P6 | 072, 073 | Externe Provider: Outbox/Idempotenz/Kompensation + JTL-Wawi-Pilot | P4 |

Jede Phase ist eine eigenständige, getestete Slice und wird einzeln abgenommen.
P1 ist Voraussetzung für alles Weitere.

---

## Phase 1 — MVP-060: Kanonischer Artikelstamm

### Neue Tabellen

| Tabelle | Zweck / Kernspalten |
| --- | --- |
| `articles` | org_id, `number` (SKU), `gtin?`, name, description, `type` (raw/consumable/merchandise/semifinished/finished/service), `base_unit`, Flags (stockable/purchasable/sellable/manufacturable/batch_required/serial_required/shelf_life_required), `status` (draft/active/retired), `tax_class?`, `default_procedure_template_version_id?`, `default_purchase_price?`/`default_sale_price?`/`currency`, created_by |
| `article_option_definitions` | article_id, `code`, name, position, active |
| `article_option_values` | option_definition_id, `code`, label, position, active |
| `article_variants` | article_id, `sku`, `gtin?`, `status`, `is_default`, Preis-Overrides (nullable), created_by — **die bestands-/fertigungsführende Einheit** |
| `article_variant_option_values` | variant_id, option_value_id — Pivot; **Kombination je Artikel eindeutig** |
| `article_units` | article_id, `code`, label, `factor_to_base` (decimal), `kind` (base/purchase/sale/packaging), active — historisierte Umrechnung |
| `external_article_mappings` | org_id, plugin_id, external_id, article_id?/variant_id?, `external_parent_id?`, external_number, unit, `sync_status`, last_synced_at |

Decimal-Präzision projektkonform (Menge i.d.R. `decimal(15,4)`, Geld `decimal(13,2)`).
Unique: `(org_id, number)` für SKU; eindeutige Optionskombination je Artikel
über deterministischen Hash der sortierten option_value_ids (Spalte
`option_signature` + Unique `(article_id, option_signature)`), kurze Indexnamen.

### Modelle / Services

- `Article`, `ArticleVariant`, `ArticleOptionDefinition`, `ArticleOptionValue`,
  `ArticleUnit`, `ExternalArticleMapping` — alle mit `HasSqid`,
  `BelongsToOrganization`, `Auditable`.
- `Services\Article\ArticleNumberAuthority` (dünn): nutzt `NumberAuthority`/
  `NumberSequenceService` um eine SKU-Sequenz `article` zu ergänzen — **kein
  paralleler Generator**. GTIN getrennter Nummernbereich.
- `Services\Article\VariantResolver`: erzeugt/validiert die eindeutige
  Optionskombination, berechnet `option_signature`.
- `Services\Article\UnitConverter`: Decimal-Umrechnung Basiseinheit ↔ Einheit,
  Dimensionswechsel nur mit explizitem Faktor (Liter↔kg sonst verboten).
- Lebenszyklus: Stilllegen statt Löschen; löschbar nur referenzlose Entwürfe
  (Guard-Service + Policy).

### Integration mit Bestand

- `Material` bleibt zunächst **unangetastet** (additiv). Eine spätere,
  separate Daten-Migration (`articles:import-materials`, eigener Schritt am
  Ende von P1) legt je `Material` einen `Article` (type raw/consumable) + ggf.
  Default-Variante an und verknüpft `MaterialUsage` optional über ein nullable
  `article_variant_id`; **historische Snapshots in `MaterialUsage` bleiben**.
- `LexofficeArticle` bleibt externer Cache; neue `external_article_mappings`
  ergänzen die Variantenzuordnung (Lexoffice: jede Variante = eigenständiger
  Artikel, siehe 048).

### UI / Routen / Tests

- Admin-CRUD für Artikel + Varianten + Optionen + Einheiten als **Modal-Dialoge**,
  Sqid-Routen, Plan-/Modul-Gating (neues Modul `inventory`/`articles` prüfen).
- Policy `ArticlePolicy` (+ Variante) mit Permissions; Mandantengrenze getestet.
- i18n de/en/fr/it/es; PHPStan grün; Feature-Tests: Anlage, eindeutige
  Kombination, Stilllegen-statt-Löschen, SKU-Hoheit, Einheiten-Umrechnung,
  Cross-Org-Isolation, Sqid-Binding.

### Risiken P1

- **Material-Überführung** ist heikel (Abrechnung/Nachkalkulation hängen an
  `MaterialUsage`). Mitigation: additiv + nullable FK + Snapshots unangetastet;
  Migration als letzter, separat getesteter Schritt.
- Eindeutige Optionskombination bei nachträglicher Optionsänderung → neue
  Variante statt Mutation (Akzeptanzkriterium).

---

## Phase 2 — MVP-061/062/065: Stücklisten, Auftrag, Rückmeldungen

### Neue Tabellen

| Tabelle | Zweck |
| --- | --- |
| `procedure_material_requirements` | version_id, `position_code`, article/variant ref, `quantity_kind` (fixed/per_unit/ratio), quantity (decimal), unit, `rounding`, `waste_surcharge?`, `ratio_part?`, `is_tool`, active |
| `article_variant_bom_overrides` | variant_id, position_code, Aktion (add/disable/replace/override_qty), Werte — Vererbung über stabile `position_code` |
| `procedure_parameter_definitions` | version_id, `code`, `type` (number/text/choice/measure/date), constraints (json), position — versioniert, typisiert |
| `manufacturing_orders` | org, `number`, article_id, variant_id, target_qty, unit, `status`, priority, planned_start, due_at, customer_id?/project_id?/diary_entry_id?, responsible_user_id?/team?, `procurement_mode`, **eingefroren**: procedure_template_version_id, `bom_snapshot`, `variant_snapshot`, `parameter_snapshot`, procedure_run_id? |
| `manufacturing_order_materials` | order_id, article/variant + Bezeichnungssnapshot, target_qty, unit-Snapshot, reserved_qty, consumed_qty, cost-Snapshot, calc_reason, rounding |
| `manufacturing_order_reports` | order_id, produced_qty, good_qty, scrap_qty, rework_qty, Verbrauchsbezug, user, timestamp |

### Services

- `Services\Manufacturing\BomResolver`: löst Basis-BOM + Varianten-Overrides zu
  einer vollständigen, **eingefrorenen** Stückliste auf (Snapshot beim Freigeben).
- `Services\Manufacturing\MaterialDemandCalculator`: **deterministisch, Decimal**,
  Rezepturlogik (Verhältnis 1:3 → Anteile), Rundung, Verschnitt.
- `Services\Manufacturing\ManufacturingOrderStateMachine`: Entwurf → Freigegeben
  → In Arbeit → (Wartet/Blockiert) → Abgeschlossen / Abgebrochen; abgeschlossen
  = unveränderlich.
- Rückmeldungen aggregieren Offen-/Gut-/Ausschuss-/Nacharbeitsmenge je `report`.

### UI / Tests

- Auftrags-CRUD (Dialoge), Schritt „Freigeben" friert Version+BOM+Variante+
  Parameter ein. Fertigungsnachweis-PDF (Muster vorhandener PDF-Toolkit/Protokolle).
- Tests: deterministische Bedarfsberechnung, Snapshot-Unveränderlichkeit,
  Varianten-Override-Auflösung, Statusmaschine, getrennte Soll/Ist/Gut/Ausschuss.

### Risiken P2

- Snapshot-Vollständigkeit (Akzeptanzkriterium): BOM + Variante + Parameter
  müssen beim Freigeben vollständig eingefroren werden.

---

## Phase 3 — MVP-063/064: Ausführung + Warte-/Trockenschritte

- Erweitert den **vorhandenen** Procedure-Kern: mobile Schritt-für-Schritt-
  Ansicht (Material-/Mess-/Foto-/Datei-/Bestätigungsschritte existieren bereits).
- Neuer Schritttyp **Wartezeit**: `wait_until` (serverseitiger Timestamp) am
  `ProcedureStepRun`; Folgeschritte bis Fristablauf blockiert — **unabhängig vom
  Browser** (serverseitig geprüft, nicht per Neuladen umgehbar).
- Vorzeitige Fortsetzung nur als berechtigte, auditierte `ProcedureDeviation`.
- Tests: Blockade nicht über Reload/anderen Client umgehbar; Deviation auditiert.

---

## Phase 4 — MVP-066–070: Lokaler Lagerkern

### Datenführerschaft (066)

- `Enums\Inventory\InventoryMode` (local/external/read_only) + per-Org-Konfig
  (analog `BillingMode`; Ablage in `organizations.settings` bzw. dedizierte
  Settings-Ebene). `InventoryProviderResolver` (Vorbild `BillingModeResolver`).
- **Provider-Vertrag** `Contracts\Inventory\InventoryProvider` mit Capability-
  Matrix (lesen/reservieren/buchen/…); UI bietet nur deklarierte Fähigkeiten an.
  `LocalInventoryProvider` als erste Implementierung.

### Lagerkern (067–070)

| Tabelle | Zweck |
| --- | --- |
| `warehouses` | org, name, active, blocked, optional Standort/Fahrzeug/Team |
| `warehouse_locations` | warehouse_id, optionale Bereiche/Plätze |
| `stock_movements` | **append-only Journal**: org, variant_id, warehouse_id, location_id?, `stock_state` (physical/reserved/blocked/qc/damaged/scrap), `ownership_type` (own/customer/consignment/supplier/project) + owner ref, qty_base (decimal), original_qty/unit, `movement_type`, occurred_at, actor, source ref, **idempotency_key** (unique), cost-Snapshot |
| `stock_levels` | abgeleiteter Snapshot je (variant, warehouse, state, ownership) für Performance; Wahrheit bleibt das Journal |
| `stock_reservations` | variant, warehouse, qty, ownership, Quelle (Auftrag), Status, Priorität |
| `stock_counts` / `stock_count_lines` | stichtagsbezogene Inventur |

- Verfügbar = physisch − Reservierung − gesperrt − QS (server-seitige Formel).
- Bewegungen **append-only**: Korrektur nur per referenzierter Gegenbuchung;
  negative Bestände default gesperrt (rollenbasierte, auditierte Freigabe).
- Eigentumsarten dürfen nicht still vermischt/gegeneinander verbraucht werden.
- Bewertung (070): gleitender Durchschnitt, Kostensnapshot je Bewegung.
- Inventur (069): einfrieren → zählen → Differenz → Freigabe → Korrekturbuchung.

### Berechtigungen / Tests

- Getrennte Permissions (sehen / buchen / reservieren / Ersatz genehmigen /
  negativ freigeben / Korrektur / Inventur zählen / Differenz freigeben /
  extern re-transfer / Provider konfigurieren). Optional Vier-Augen für hohe
  Differenzen/negative Bestände/Korrekturen.
- Tests: Journal-Append-only, Verfügbarkeitsformel, Reservierung transaktional,
  keine stille Eigentums-Vermischung, Inventurdifferenz-Stichtag, Bewertung,
  Cross-Org.

---

## Phase 5 — MVP-071/074: Fertigung ↔ Lager + Auslieferung/Faktura

- 071: Auftrag freigeben → Bedarf-Snapshot → Verfügbarkeit prüfen → reservieren
  → starten → Ist-Verbrauch/Ausschuss buchen → Restreservierung frei →
  Fertigerzeugnis einlagern. Reservierung ≠ Verbrauch (getrennt).
- 074: **Auslieferung** bucht Variantenbestand ab und übergibt als konkrete
  Variante an das führende Fakturasystem — über den **vorhandenen
  `FacturationTarget`-Vertrag** (Lexoffice: flacher Artikel/Positionssnapshot;
  WorkDiary-Faktura: bestehende E-Rechnungsregeln). Fehlgeschlagene Faktura
  darf erfolgte Lagerbuchung nicht verbergen — beide Status getrennt sichtbar.

---

## Phase 6 — MVP-072/073: Externe Provider

- 072: persistierte **Outbox** (`inventory_outbox`: pending/processing/confirmed/
  failed/compensationRequired), stabile Idempotenz-ID, Retry über Queue,
  Konfliktübersicht, Kompensationsbuchung statt DB-Rollback.
- 073: optionales `JtlWawiInventoryProvider`-Plugin gegen den Provider-Vertrag,
  Vater-/Kind-Artikel-Mapping, Pilot an unterstützter Kundenschnittstelle.

---

## Querschnitt: Tests, Gating, Risiken

- **Plan-/Modul-Gating:** neues Modul (z. B. `inventory`) in `config/plans.php`,
  `EnforcePlanModules`, Menüfilter — analog bestehender Module.
- **Audit:** bewertungs-/bestandsrelevante Bewegungen revisionssicher.
- **Testumgebung-Hinweis:** Die Feature-Suite ist in der WSL-/SQLite-Dev-
  Umgebung sehr langsam (RefreshDatabase-Migrationen). Neue Slices mit
  gezielten, kleinen Testdateien fahren; vollständige Suite in CI.
- **Größtes Gesamtrisiko:** die Material→Artikel-Überführung (P1) berührt
  Abrechnung/Nachkalkulation. Daher additiv, FK nullable, Snapshots unangetastet,
  Migration als eigener verifizierter Schritt.

## Empfohlene Reihenfolge

P1 zuerst und allein abnehmen (Fundament). Danach wahlweise P2 (Fertigungspfad)
oder P4 (Lagerpfad) — beide hängen nur an P1, nicht voneinander. P5 braucht P2+P4.
P6 ist optionaler Ausbau.

## GitHub Issues

- TBD — je MVP ein Issue, verlinkt auf 047/048 und diese Plandatei.
