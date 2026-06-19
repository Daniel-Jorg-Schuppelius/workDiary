# Ausbauplan: Fertigung (047) & Lagerwirtschaft (048) — Post-MVP

Stand 2026-06-16. Der MVP-Schnitt (MVP-060 bis 071 + 074, Fehlmaterial,
Varianten-Overrides, Warte-/Trockenschritte, Fertigungs-UI) ist umgesetzt und
getestet (siehe [Umsetzungsplan](./047-048-umsetzungsplan.md)). Dieser Plan
deckt die in 047/048 bewusst als **„Später"** markierten Ausbaustufen plus die
externen Provider (MVP-072/073) ab. **Noch kein Code** — zur Abnahme.

## Vorhandene Grundlage (worauf alles aufsetzt)

- Artikelstamm: `articles`/`article_variants` (Flags `batch_required`,
  `serial_required`, `shelf_life_required` existieren bereits!), `article_units`,
  `external_article_mappings` (Provider-/Vater-/Kindreferenz vorgesehen).
- Lagerkern: append-only `stock_movements` (mit `idempotency_key`,
  Kostensnapshot-Feldern), abgeleitete Salden, `stock_reservations`,
  `stock_level_settings`, `stock_counts`/`stock_count_lines`, `stock_valuations`
  (gleitender Durchschnitt), `stock_deliveries`.
- Provider-Vertrag `Contracts\Inventory\InventoryProvider` + Capability-Matrix +
  `InventoryProviderResolver` (Modus local/external/read_only) — **der externe
  Pfad ist im Kern bereits vorgesehen, nur noch nicht implementiert**.
- Fertigung: `manufacturing_orders`/`_materials`/`_reports`, BomResolver,
  MaterialDemandCalculator, ManufacturingInventoryService, ProcedureRun-Link,
  Warte-Schritttyp; `procurement_requests`/`material_substitutes`.

## Phasen

| Phase | Thema | Hängt an | Aufwand |
| --- | --- | --- | --- |
| E1 | Externe Bestandsprovider (072 Outbox + 073 JTL-Wawi) | Provider-Vertrag, ExternalArticleMapping | L |
| E2 | Chargen-/Serien-/MHD-Rückverfolgung | stock_movements, Artikel-Flags | L |
| E3 | Bewertung FIFO / chargenbezogen | ValuationService, stock_valuations | M |
| E4 | Beschaffung: Bestellungen + Wareneingang + Vorschläge | procurement_requests, suppliers | L |
| E5 | Scanner-/Barcode-/Etiketten-Workflow | GTIN/SKU, stock_movements | M |
| E6 | Mobile/zyklische Inventur | StocktakeService | M |
| E7 | Fertigungsausbau (MRP, Lose, Kapazität, Fremdfertigung, SPC, Nachkalkulation) | Fertigungskern | XL |

E1 zuerst (schaltet die externe Datenführerschaft scharf, die der Kern schon
kennt). E2/E3 bilden ein zusammenhängendes Rückverfolgbarkeits-/Bewertungspaket.

---

## E1 — Externe Bestandsprovider (MVP-072 + 073)

> **MVP-072 umgesetzt (2026-06-16):** `inventory_outbox` (Idempotenz über
> unique (org, idempotency_key)), Enum `OutboxStatus`, Modell
> `InventoryOutboxEntry`, `InventoryOutboxService` (enqueue/enqueueForMovement +
> Statusübergänge), Vertrag `ExternalInventoryDispatcher` +
> `ExternalInventoryDispatcherResolver` (Singleton, Plugin-Registry),
> `InventoryOutboxDeliveryJob` (Retry/Backoff → bei endgültigem Fehlschlag
> `compensation_required` + `PendingExternalConflict`), i18n `outbox.status`
> (5 Sprachen). 7 Tests grün.
> **MVP-073 scaffold (2026-06-16):** `JtlWawiInventoryProvider` (Capabilities +
> ExternalArticleMapping-Auflösung) und `JtlWawiDispatcher` gegen die Verträge;
> API-Aufrufe werfen bewusst „Pilot ausstehend" (kein stiller Verlust → Outbox
> kompensiert). Nicht auto-registriert.
>
> **Schreibpfad + Rückkanal verdrahtet (2026-06-19):** `ExternalStockMirror`
> spiegelt lokal gebuchte Bewegungen bei `inventory_mode=external` in die Outbox
> (in `DeliveryService` eingehängt); `InventoryOutboxService::confirmByKey()` ist
> die idempotente Inbound-Bestätigung (Webhook-Rückkanal). **Extern blockiert
> (nicht codeseitig lösbar):** die echte JTL-Wawi-API + Pilot brauchen
> Fremdsystem-Zugang/Anmeldedaten.

**MVP-072: Persistierte Outbox + Idempotenz + Kompensation.**
- Neue Tabelle `inventory_outbox` (org, provider/plugin_id, operation,
  payload-json, idempotency_key, status `pending/processing/confirmed/failed/
  compensationRequired`, attempts, last_error, confirmed_at). Queue-Job zustellt;
  Retry mit Backoff; Webhooks-Eingang bzw. geplanter Abgleich.
- `InventoryProvider` um Schreib-Outbox-Variante erweitern: lokale Bewegung +
  Outbox-Eintrag transaktional; externe Bestätigung getrennt protokolliert
  (Muster wie WebhookDeliveryJob/SSRF-Guard bereits im Repo).
- Konflikte in eine zentrale Sync-/Konfliktübersicht (vorhandenes
  `PendingExternalConflict`-Muster nutzen). Kompensation = fachliche
  Gegenbuchung (append-only), kein DB-Rollback.

**MVP-073: JTL-Wawi-Plugin.**
- `JtlWawiInventoryProvider` implements `InventoryProvider`; Vater-/Kindartikel
  über `external_article_mappings` (external_parent_id schon vorgesehen);
  Bestände/Buchungen gegen den Kindartikel.
- Plugin im bestehenden Plugin-System (PluginManager/Contracts), Capability-
  Deklaration, Healthcheck pro Org, Pilot an einer unterstützten Kundenschnittstelle.

**Risiko:** API-Abbildung JTL (Vererbung) ist im Pilot zu verifizieren; Outbox-
Idempotenz strikt testen (keine Doppelbuchung).

## E2 — Chargen-/Serien-/MHD-Rückverfolgung

> **Seriennummern-Teil umgesetzt (2026-06-16):** Datenmodell `stock_serials`,
> Enums `SerialStatus`/`SerialSource`, `SerialNumberGenerator` (Luhn) über
> Nummernkreis `serial`, `SerialService` (Lebenslauf, Dublettensperre, Versand,
> Sperre, Provenienz/`wasShippedTo`, Geräte-Pass-`lookup`), Auto-Generierung in
> `receiveFinishedGood` und Serien-Versand in `DeliveryService`, UI
> `SerialController` (Liste/Pass/Verifikation/Sperren) + Routen `serials.*` +
> i18n (5 Sprachen). 11 Tests grün.
>
> **Chargen/MHD umgesetzt (2026-06-19):** `stock_lots` (eindeutig je
> Org+Variante+lot_no, mfg_date/best_before/supplier_ref/status), Bewertungs-
> schichten um `stock_lot_id`+`best_before` erweitert, `StockLot`-Modell,
> `LotService` (register/receiveIntoLot/block/expiringUntil = MHD-Überwachung).
> FEFO siehe E3.
>
> **Restpunkte geschlossen (2026-06-19):** `stock_movements` tragen jetzt
> `stock_lot_id`+`stock_serial_id` (StockPosting/Ledger) inkl.
> `LotService::onHand()`; `SerialService::captureForReceipt()` mit org-weiter
> Sperrlistenprüfung; per-Artikel-Seriennummernschema (`articles.serial_scheme`,
> `SerialNumberGenerator::generateFor()`); öffentlicher Geräte-Pass
> (`PublicSerialController`, opt-in pro Org, rate-limitiert, ohne PII). **E2
> vollständig.**

### Datenmodell
- Neue Entitäten `stock_lots` (Charge/Los: variant, lot_no, mfg_date,
  best_before, supplier_ref) und `stock_serials` (serial_no, variant,
  **status**, lot_ref?, current_warehouse, owner/customer_ref, source:
  eingekauft|eigengefertigt, mfg_order_ref?, delivery_ref?). `stock_movements`
  + `stock_reservations` + `stock_count_lines` + `stock_deliveries` um
  `stock_lot_id`/`stock_serial_id` erweitern.
- Der lokale Provider blockiert heute schon chargen-/seriennummernpflichtige
  Artikel (Flags vorhanden) — diese Sperre wird durch echten Lot-/Serial-Workflow
  ersetzt: Pflichterfassung bei Eingang/Entnahme/Auslieferung; jede Bewegung
  trägt Lot/Serial.
- MHD: Verfall überwacht (Meldebestand-Muster), Entnahme FEFO-fähig (siehe E3).

### Seriennummern-Statusmaschine (Einzelstück-Lebenslauf)
Jede Serie hat genau einen Lebenslauf, lückenlos auditiert:
`erzeugt/eingebucht → auf Lager → reserviert → ausgeliefert → (zurückgenommen) →
gesperrt/verschrottet`. Eine Serial-Nr. existiert **genau einmal je Org+Artikel**
(harte Dublettensperre); bereits verwendete Nummern werden nie recycelt.

### Seriennummern-Generator für Eigenfertigung
- Für **eigenproduzierte** Geräte vergibt WorkDiary die Seriennummer selbst —
  über die bestehende Nummernhoheit (`NumberAuthority`/`NumberSequenceService`,
  neuer Scope `serial`, lokal geführt), je Artikel/Variante konfigurierbares
  Schema (Präfix + laufende Nummer + optional Jahr/Werk + **Prüfziffer**, z. B.
  Luhn, für scannbare/tippsichere Nummern).
- Vergabe automatisch beim **Gutmelden/Einlagern des Fertigerzeugnisses**
  (`receiveFinishedGood` in MVP-071 erzeugt je Stück eine Serie). Eingekaufte
  Geräte: Seriennummer wird beim Wareneingang **erfasst** (nicht generiert) —
  beide Quellen sind über `stock_serials.source` unterscheidbar.

### Versand & Betrugsprävention
- **Versandnachweis:** Die Auslieferung (`stock_deliveries`, MVP-074) hält fest,
  **welche konkreten Serien** an **welchen Kunden** gingen — eindeutiger Beleg,
  was geliefert wurde.
- **Betrugsbremse durch lückenlose Historie:** Rückläufer/Garantie/Reklamation
  werden gegen die Serial-Historie geprüft — Garantie nur, wenn die Serie
  tatsächlich von uns an diesen Kunden ausgeliefert wurde (verhindert
  Garantiebetrug mit fremden/grau importierten Geräten). Doppel-Reklamation
  derselben Serie wird erkannt.
- **Sperrliste/Blocklist:** verlorene/gestohlene/zurückgerufene Serien sperren
  (Status `gesperrt`); gesperrte Serien können nicht ausgeliefert/aktiviert
  werden. Statuswechsel sind auditiert (Hash-Kette).
- **Wareneingangsprüfung:** beim Eingang gegen Sperrliste + erwartete
  Serien-Range prüfen (Schutz gegen untergeschobene/duplizierte Geräte).

**Risiko:** Migration bestehender (lot-/serienloser) Bestände; Pflichtprüfung
darf Altbestand nicht blockieren (Übergangsregel/Stichtag). Generator-Schema je
Artikel früh festlegen — vergebene Nummern sind unveränderlich.

## E3 — Bewertung: FIFO / chargenbezogen

> **FIFO umgesetzt (2026-06-16):** Enum `ValuationMethod` (moving_average/fifo),
> `stock_valuation_layers` (Zugangsschichten qty_remaining/unit_cost/acquired_at),
> `FifoValuationService` (Abgang verbraucht älteste Schicht zuerst, exakter
> Kostensnapshot an die Bewegung), Strategie-Vertrag `InventoryValuationStrategy`
> (auch von `ValuationService` implementiert), `ValuationMethodResolver`
> (org.settings) + `InventoryValuationManager` (Verfahrensauswahl je Org), i18n
> `valuation.method` (5 Sprachen). 12 Tests grün.
>
> **Restpunkte geschlossen (2026-06-19):** FEFO + chargenbezogene Schichten (E2);
> per-Artikel-Verfahren (`articles.valuation_method`, `ValuationMethodResolver::
> methodForVariant` / `InventoryValuationManager::forVariant`); Umstellung über
> `inventory:init-valuation-layers` (`ValuationBackfillService`); Verdrahtung in
> den Standard-Buchungspfad (`DeliveryService` bucht den Abgang COGS-bewertet über
> das aktive Verfahren). **E3 vollständig.**

- `ValuationService` um Verfahren erweitern (Strategie: moving_average | fifo |
  lot). FIFO: `stock_valuation_layers` (Zugangsschichten qty/cost/date);
  Abgang verbraucht Schichten FIFO/FEFO und schreibt den Schicht-Kostensnapshot
  an die Bewegung. Verfahren je Org/Artikel konfigurierbar.
- Historische Kosten bleiben unverändert (bestehender Snapshot-Grundsatz).

## E4 — Beschaffung: Bestellungen + Wareneingang gegen Bestellung + Vorschläge

> **Kern umgesetzt (2026-06-19):** Enum `PurchaseOrderStatus` (Statusmaschine),
> NumberScope `purchase_order` (Präfix BE-), `article_supplies` (Bezugsquelle je
> Artikel/Lieferant: supplier_sku/MOQ/pack_size/lead_time/Preis/is_preferred),
> `purchase_orders` + `purchase_order_lines`. `PurchaseOrderService`
> (createDraft/addLine/submit/transition), `GoodsReceiptService` (Wareneingang
> **gegen** die Bestellzeile, bewertet über den Valuation-Manager, Teil-/
> Überlieferung, Status-Ableitung), `ProcurementSuggestionService` (Meldebestand
> + offene `procurement_requests` → bevorzugte Bezugsquelle, Rundung auf
> MOQ/Verpackung, je Lieferant eine Entwurfsbestellung, markiert Anforderungen
> als bestellt). i18n `procurement` (5 Sprachen). 6 Tests grün.
>
> **UI umgesetzt (2026-06-19):** `PurchaseOrderController` (index/create-Dialog/
> store/show/addLine/submit/receive/cancel + Bestellvorschläge mit
> Lager-Auswahl & „Bestellungen erzeugen"), Routen `purchase-orders.*` →
> module.lager, Blades index/_form_dialog/show/suggestions, Nav „Bestellungen",
> i18n procurement ui/action/field/flash (5 Sprachen), PurchaseOrderLine mit
> Sqid. 4 Controller-Tests grün.
>
> **Restpunkte geschlossen (2026-06-19):** Bestellbezug an der `Receipt`-Bewegung
> (Valuation-`receipt(?Model $source)`, GoodsReceiptService reicht die
> Bestellzeile durch → source_type/source_id). Lieferavis als read-only
> **„Erwartete Wareneingänge"-Übersicht** (`PurchaseOrderController.incoming`,
> offene Bestellzeilen bestellter Aufträge, Route `purchase-orders.incoming`,
> i18n).
>
> **ASN-Beleg umgesetzt (2026-06-19):** `purchase_order_advices` (+ `_lines`),
> Enum `AdviceStatus`, `AdviceService` (announce/receive/cancel — receive bucht
> den Wareneingang gegen die Bestellzeilen), UI an der Bestelldetailseite (Avis
> erfassen je offener Zeile + Liste mit „Wareneingang buchen"/Stornieren),
> i18n. 2 Tests. **E4 vollständig.**

- `purchase_orders` + `purchase_order_lines` (Lieferant aus `suppliers`,
  Bezugsquellen-Stammdaten je Artikel: Lieferantenartikelnr., MOQ, Lieferzeit,
  Verpackungseinheit — strukturiert wie in 048 vorgesehen).
- Wareneingang bucht **gegen** eine Bestellzeile (Mengenabgleich, Teil-/
  Überlieferung) → ergänzt den lokalen `Receipt` um Bestellbezug.
- Automatische Bestellvorschläge aus `procurement_requests` + Meldebestand
  (`stock_level_settings`) + bevorzugter Bezugsquelle. `ProcurementStatus`
  (open→ordered→closed) wird damit durchgängig.

## E5 — Scanner-/Barcode-/Etiketten-Workflow

> **Kern umgesetzt (2026-06-19):** `BarcodeResolver` löst einen Scan nach
> Spezifität auf (Seriennummer → Charge → Varianten-GTIN/SKU → Artikel-GTIN,
> org-gescoped) und liefert `BarcodeMatch` (Typ + Variante/Serie/Charge/Artikel).
> `ScanActionService` bucht mobil per Scan (Enum `ScanAction`
> Eingang/Entnahme/Umlagerung; Verfügbarkeitsprüfung). `LabelService` baut die
> Etikettendaten (Code/Codetyp/Beschriftung) für Variante/Charge/Serie. i18n
> `inventory.scan` (5 Sprachen). 4 Tests grün.
>
> **UI umgesetzt (2026-06-19):** mobile Scan-/Buchungs-UI (`ScanController` +
> Blade `inventory/scan`, Code auflösen + Eingang/Entnahme/Umlagerung buchen,
> Routen `inventory.scan(.book)`, Nav „Scannen"); Etikettendruck als PDF
> (`LabelController` über Barryvdh DomPDF, Blade `inventory/labels/label`, Routen
> `inventory.labels.variant/serial/lot`). i18n `inventory.scan` ui-Keys
> (5 Sprachen). Tests grün.
>
> **QR + Vorlage ergänzt (2026-06-19):** Etikett enthält einen scannbaren QR-Code
> (bacon/bacon-qr-code). Leichtgewichtige **Etiketten-Vorlage je Organisation**
> über `settings.label` (Papiergröße + QR an/aus), vom `LabelController` honoriert.
> **Etiketten-Layout-Designer umgesetzt (2026-06-19):** `label_templates`
> (Papiergröße/Ausrichtung/QR/Felder/Standard), `LabelTemplate`-Modell,
> `LabelTemplateController` (CRUD + Standardvorlage), `LabelController` wählt
> Vorlage (?template oder Standard) und rendert das Etikett dynamisch nach den
> gewählten Feldern; Nav „Etikettenvorlagen", i18n (5 Sprachen). Test grün.
> **E5 vollständig.**

- GTIN/SKU sind bereits an Artikel/Varianten — Barcode-Auflösung (GTIN/SKU →
  Variante) als Lookup-Service; mobile Buchung per Scan (Eingang/Entnahme/
  Umlagerung/Inventur). Etikettendruck (Variante/Lot/Serial) über das vorhandene
  PDF-Toolkit; Etikettenvorlagen als Form-/Vorlagensystem (Feature 032) nutzen.

## E6 — Mobile & zyklische Inventur

> **Umgesetzt (2026-06-19):** Enum `StockCountType` (full/cycle) + Spalte
> `stock_counts.count_type`. `StocktakeService.openCycle(warehouse, variantIds)`
> friert nur die Buckets einer Teilmenge ein; `recordByScan(count, code, qty)`
> erfasst Scan-gestützt (über {@see BarcodeResolver}) in die passende Zeile.
> `CycleCountPlanner` (ABC-Analyse nach Bestandswert, kumulativ 80/95 %;
> `classify`/`dueVariants`). Zählung/Prüfung/Differenzfreigabe bleiben getrennt
> (unverändert). 3 Tests grün.
>
> **UI umgesetzt (2026-06-19):** `StocktakeController.openCycle` (zyklische
> Inventur je ABC-Klasse, Route `inventory.counts.cycle`) + `recordScan`
> (Scan-Erfassung in laufende Inventur, Route `inventory.counts.scan`); Blades
> counts/index (Zyklus-Auswahl A/B/C) + counts/show (Scan-Feld); i18n
> `count_ui.cycle*` (5 Sprachen). Tests grün.
>
> **Terminierung umgesetzt (2026-06-19):** Command `inventory:cycle-counts
> {--class=A} {--org=}` eröffnet je Lager eine Zählung der fälligen ABC-Klasse
> (für den Scheduler; bindet currentOrganization je Org wie der Plugin-Healthcheck).
> Test grün. **E6 vollständig.**

- `StocktakeService` um mobile Erfassung (Scan-gestützt, E5) und **zyklische
  Inventur** (Stichproben-/ABC-Zyklen statt Stichtags-Vollzählung) erweitern;
  Zählung/Prüfung/Differenzfreigabe bleiben getrennt zuweisbar (schon angelegt).

## E7 — Fertigungsausbau

> **Teil-umgesetzt (2026-06-19):** `MrpService.explode(article, variant, qty,
> ?warehouse)` löst die Stückliste mehrstufig über Halbfabrikate auf
> (Sekundärbedarf, make/buy, Nettoverrechnung gegen Lagerbestand, Zyklus-/
> Tiefenschutz) — Basis für Fertigungsvorschläge. `ManufacturingQualityService`
> aggregiert die Rückmeldungen zu Yield/Ausschuss-/Nacharbeitsquote je
> Auftrag/Artikel (SPC-Basis). 4 Tests grün. **(Inzwischen alle umgesetzt — siehe
> Notizen unten:** Los-Split/-Merge, Maschinen-/Kapazität, Fremdfertigung,
> Mess-Steptyp-SPC, Nachkalkulation inkl. **Lohnkosten** über die an den
> Fertigungsauftrag gebundene Zeiterfassung (`time_entries.manufacturing_order_id`).**)**
>
> **MRP/SPC-UI umgesetzt (2026-06-19):** `ManufacturingPlanningController` +
> Blade `manufacturing/planning` (Artikel+Menge → mehrstufige Bedarfsauflösung
> mit Stufen-Einrückung/make-buy/Brutto-Netto + Qualitätskennzahlen), Route
> `manufacturing-planning.index` → module.lager, Link aus der Fertigungsliste,
> i18n `manufacturing.planning` (5 Sprachen). 2 Tests grün.
>
> **Weitere E7-Bausteine umgesetzt (2026-06-19):**
> - **Los-Split/-Merge:** `LotSplitService.split/merge` über die Bewertungs-
>   schichten (kostenerhaltend; Quell-Charge wird „merged"), `stock_lots`-Status
>   MERGED, `manufacturing_order_reports.stock_lot_id`. 3 Tests.
> - **Fremdfertigung:** Enum `ProcurementMode` (in_house/purchase/subcontract,
>   ManufacturingOrder-Cast), `SubcontractService.commission` legt einen
>   Lieferantenauftrag (E4) über das Erzeugnis an + verknüpft
>   (`subcontract_purchase_order_id`); providedMaterials = Beistellmaterial.
>   i18n `manufacturing.procurement_mode` (5 Sprachen). 1 Test.
> - **Maschinen-/Kapazität:** `work_centers` (Tageskapazität + Rüstzeit),
>   `manufacturing_orders.work_center_id/planned_minutes`, `CapacityService`
>   (assign + Tageslast inkl. Rüstzeit je Auftrag + Überlast). 1 Test.
> - **Nachkalkulation:** `ManufacturingCostingService.costing` (Plan- vs.
>   Ist-Materialkosten + Stückkosten je Gutmenge; Kostenbasis Snapshot→Variante→
>   Artikel). 1 Test.
> - **Echte Ist-Kosten (2026-06-19):** `InventoryValuationStrategy.unitCost()`
>   (Durchschnitt bzw. nächste FIFO/FEFO-Schicht); `ManufacturingInventoryService.
>   consume()` erfasst den Ist-Stückkostenwert beim Verbrauch in
>   `manufacturing_order_materials.actual_cost` (ohne Eingriff in die
>   Buchungspfade → regressionsfrei); `ManufacturingCostingService` nutzt die
>   erfassten Ist-Kosten (Fallback Stammkosten). 7 Tests grün inkl. Consume-
>   Regression.
> - **Mess-Steptyp-SPC (2026-06-19):** `Services\Procedure\SpcService.analyzeStep`
>   aggregiert die Messwerte der `Messreihe`-Schrittausführungen
>   (`value_json.values`) zu n/Mittel/Min/Max/Standardabweichung sowie – bei
>   hinterlegten Spezifikationsgrenzen (`config.lsl/usl`) – Cp/Cpk und Anzahl
>   außerhalb der Toleranz. 2 Tests grün.
> - **E7-UIs (2026-06-19):** Fremdfertigungs-Button am Auftrag
>   (`ManufacturingOrderController.subcontract`); Kapazitätsboard
>   (`WorkCenterController` index/create/store) + Arbeitsplatz-Zuweisung am Auftrag
>   (`assignWorkCenter`); SPC-Karte auf der Planungsseite; Chargen-UI
>   (`LotController` index/split/merge). Routen `work-centers.*`/`inventory.lots*`,
>   Nav „Kapazität"/„Chargen", i18n `manufacturing.capacity` + `inventory.lot`
>   (5 Sprachen). 5 UI-Tests grün. **Damit ist E7 vollständig (Service + UI);
>   offen bleibt allein die JTL-Live-API (extern blockiert, später).**

- **Mehrstufige Materialbedarfsplanung (MRP):** Stücklisten-Auflösung über
  Halbfabrikate (Artikel type `semifinished` existiert) → abhängige
  Sekundärbedarfe + Fertigungsvorschläge; baut auf BomResolver auf.
- **Los-Splits & -Zusammenführung:** `manufacturing_order_reports` um
  Los-/Chargenreferenz (E2) erweitern; Split/Merge als eigene, auditierte
  Vorgänge.
- **Maschinenbelegung / Kapazität / Rüstzeiten:** Ressourcen-/Arbeitsplatz-
  Entität + Terminierung (an Feature 028 Disposition anbinden).
- **Fremdfertigung / Lieferantenaufträge:** Beschaffungsart `Fremdfertigung`
  (vorgesehen) → Lieferantenauftrag (E4) mit Beistellmaterial.
- **Qualitätskennzahlen / SPC:** Aggregation aus `manufacturing_order_reports`
  (Gut/Ausschuss/Nacharbeit) + Mess-Schritten der Prozedur (Messreihe-Steptyp).
- **Automatische Nachkalkulation je Produkt/Variante/Arbeitsplan-Version:**
  Ist-Verbrauch (Material-Kostensnapshots) + Arbeitszeit (Zeiterfassung) →
  Nachkalkulation (Feature 014) je Auftrag/Variante.

---

## Querschnitt

- Alle neuen Entitäten: `BelongsToOrganization`, Sqid-Routen, Audit wo
  nachweisrelevant, Decimal/bcmath, 5-Sprachen-i18n, Tests, Modul-Gating
  `module.lager`.
- Reihenfolge-Empfehlung: **E1 → E2 → E3** (externe Führung + Rückverfolgbarkeit
  + Bewertung als zusammenhängender Block), dann **E4/E5/E6** (Beschaffung &
  mobile Erfassung), zuletzt **E7** (Fertigungs-Ausbau, größter Brocken).

## GitHub Issues

- TBD — je Phase/Unterpunkt ein Issue, verlinkt auf 047/048 und diese Plandatei.
