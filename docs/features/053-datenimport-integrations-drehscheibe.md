# Datenimport & Integrations-Drehscheibe (Zuordnung statt Datenmüll)

## Status

**Phase 0 in Umsetzung** — `MVP-103`. Ziel: eine **einheitliche
Zuordnungs- und Staging-Schicht** für alle Datenimporte (CSV-Wizard *und*
Plugin-Syncs), damit die App als Schnittstelle zwischen Fremdsystemen dienen
kann, ohne mit Dubletten/„Datenmüll" zugeschüttet zu werden.

**Getroffene Richtungsentscheidungen (2026-06-29):**

- **Voll-Konsolidierung jetzt:** gemeinsames Fundament *und* sofortige Migration
  aller bestehenden Integrationen (Toggl, Lexoffice, OpenProject, RemoteSupport)
  auf die gemeinsame Schicht.
- **Inbox-First als Grundregel:** Importe legen niemals blind an. Unzuordenbares
  landet immer in der Zuordnungs-Inbox; Anlegen ist bewusstes Opt-in pro
  Importlauf/Entität.
- **Konzept zuerst:** dieses Dokument vor der Implementierung.

---

## 1. Ausgangslage (Ist-Analyse)

Heute existieren **drei voneinander getrennte Import-Welten** mit drei
verschiedenen Zuordnungs- und Staging-Mechaniken:

### 1.1 CSV-Wizard (MVP-049)

`admin.imports.*` → [ImportController](../../app/Http/Controllers/Admin/ImportController.php)
→ [CsvPreflightAnalyzer](../../app/Services/Import/CsvPreflightAnalyzer.php)
→ [ProcessCsvImportJob](../../app/Jobs/ProcessCsvImportJob.php), getrieben von
[EntitySpec](../../app/Services/Import/EntitySpec.php) +
[EntitySpecRegistry](../../app/Services/Import/EntitySpecRegistry.php).

Specs: Customer, Project, User, Material, Vehicle, ScheduledShift, RemoteSession.

- **Dedup nur über natürliche Schlüssel** (`number`, `email`). Ohne passenden
  Schlüssel wird **blind angelegt** → Hauptquelle für Reimport-Dubletten.
- **Nutzt das Zuordnungs-Register (`ExternalReference`) nicht.** Eine aus einem
  Fremdsystem stammende Liste hat keine stabile Herkunfts-ID-Bindung.

### 1.2 Plugin-Syncs (ExternalReference-basiert)

| Plugin | Capability | Entitäten | Match-Strategie | Staging bei „unmatched" |
|---|---|---|---|---|
| Toggl | TimeImport | Client→Customer, Project→Project, Entry→TimeEntry | [matchCustomer](../../app/Plugins/Toggl/TogglImportService.php#L112): ExtRef → Name exact | `toggl_pending_entries` |
| OpenProject | TimeImport | Project, WorkPackage→Task, Entry | ExtRef → Name exact | `openproject_pending_entries` |
| RemoteSupport | TimeImport | Session→TimeEntry (Gerät→Asset→Kunde) | Geräte-ID (ExtRef auf Asset) | `remote_pending_sessions` |
| Lexoffice | ContactSync, TimeExport | Contact↔Customer/Supplier, Voucher/Invoice/… | [findLocalMatch](../../app/Plugins/Lexoffice/LexofficeContactSync.php#L238): VAT→E-Mail→Firma+PLZ→Firma→Name | `pending_external_conflicts` (Feldkonflikt) |

**Befund:** Das *Mapping-Fundament* ist bereits generisch —
[ExternalReference](../../app/Models/ExternalReference.php) mit Unique-Index
`(plugin_id, external_type, referenceable_type, referenceable_id)` und
`external_id`-Idempotenz wird von allen genutzt. **Darüber ist alles
dupliziert:**

- **4 Match-Implementierungen** ohne gemeinsame Schnittstelle.
- **4 Staging-Mechaniken** mit nahezu identischem Schema (s. u.).
- **Match-Policy** (auto/manuell) nur bei Lexoffice
  ([LexofficeMatchPolicy](../../app/Plugins/Lexoffice/LexofficeMatchPolicy.php)).

Die drei Time-Pending-Tabellen sind strukturgleich (Beleg für Konsolidierung):

```
organization_id | <idempotenz-key> | <quell-deskriptoren> |
status(open|imported|dismissed) | time_entry_id | resolved_by | resolved_at
```

`pending_external_conflicts` ist verwandt, aber ein **anderer Fall-Typ**:
„bereits zugeordnet, aber Feldwerte weichen ab" (mit `local_snapshot`,
`remote_snapshot`, `diff_fields`).

### 1.3 Spezial-Importer

Procurement-Kataloge (DATANORM/BMEcat, Hash-Dedup über `external_no`) und
GAEB-XML (MVP-049/050) — eigene, fachlich komplexe Pipelines. Bleiben eigenständig,
docken aber bei Bedarf an das Zuordnungs-Register an (Abschnitt 6).

### 1.4 Lückende Entitäten (Datenmüll-Risiko)

Kein Importer/keine Dedup für **Supplier**, **Article** (außer Katalog),
**Invoice**. Supplier-Anlage heute ohne Dublettenschutz.

---

## 2. Zielbild: eine Integrations-Drehscheibe

Drei Bausteine — zwei davon teilweise vorhanden, einer neu:

### Baustein A — Zuordnungs-Register

Bleibt `ExternalReference` (Wahrheit „lokaler Datensatz ↔ Fremd-ID je System").
**Neu:** eine übergreifende **Verwaltungs-/Such-UI** „Zuordnungen": pro lokalem
Datensatz alle Fremd-IDs aller Systeme sehen, umbiegen, lösen; verwaiste
Referenzen finden.

### Baustein B — Universelle Zuordnungs-Inbox

Eine **einzige** entitäts- und quellenagnostische Tabelle
`integration_inbox_items`, die ersetzt:
`toggl_pending_entries`, `openproject_pending_entries`,
`remote_pending_sessions` **und** `pending_external_conflicts`.

Drei Fall-Typen (`case_type`):

- `unmatched` — eingegangen, noch keinem lokalen Datensatz zugeordnet.
- `conflict` — zugeordnet, aber Feldwerte weichen ab (heute Lexoffice).
- `ambiguous` — mehrere lokale Kandidaten, Mensch muss wählen.

### Baustein C — Gemeinsamer Matcher + Policy + Resolver

- `EntityMatcher` (verallgemeinert aus `CustomerDuplicateFinder` +
  `findLocalMatch`): liefert je Ziel-Entität gerankte Kandidaten über eine
  konfigurierbare Strategie-Kette.
- `ImportMatchPolicy` (verallgemeinert aus `LexofficeMatchPolicy`).
- `IntegrationResolver` — **der eine Engpass**, durch den jeder Import läuft und
  der die „kein-Müll"-Regel erzwingt.

### Die Grundregel (Inbox-First)

```
resolve(remote):
  1. ExternalReference(external_id) vorhanden? → LINK (+ ggf. conflict-Item bei Feld-Diff)
  2. sonst EntityMatcher → eindeutiger Treffer? → LINK + ExternalReference setzen
  3. sonst mehrere Kandidaten? → AMBIGUOUS-Inbox-Item
  4. sonst Policy == AutoLinkAndCreate (Opt-in)? → CREATE + ExternalReference
  5. sonst → UNMATCHED-Inbox-Item   (NIE blind anlegen)
```

`AutoLinkExactOnly` ist Default. Blind-Anlegen gibt es nur als bewusstes
Opt-in (`AutoLinkAndCreate`) je Importlauf/Entität.

---

## 3. Datenmodell

### 3.1 `integration_inbox_items` (neu)

```php
$table->id();
$table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
$table->string('plugin_id', 64);            // toggl | lexoffice | openproject | remote-support | csv-import
$table->string('source', 16)->nullable();   // api | csv | manual
$table->string('target_type', 191);         // Ziel-Morph: App\Models\Customer | Supplier | Project | TimeEntry ...
$table->string('external_type', 64);        // client | project | entry | contact | session ...
$table->string('external_id', 191)->nullable();
$table->string('dedupe_key', 191);          // Idempotenz: plugin:external_type:external_id | hash(csv-row)
$table->string('case_type', 16);            // unmatched | conflict | ambiguous
$table->string('status', 24)->default('open'); // open | resolved_linked | resolved_created |
                                                // resolved_local | resolved_remote | dismissed
// Kandidat / Ergebnis (polymorph, ohne FK wegen Multi-Target)
$table->string('referenceable_type', 191)->nullable(); // bei conflict/ambiguous: (Haupt-)Kandidat
$table->unsignedBigInteger('referenceable_id')->nullable();
$table->json('candidate_ids')->nullable();             // ambiguous: [{id, score, reasons[]}]
$table->string('resolved_to_type', 191)->nullable();   // Ergebnis-Datensatz
$table->unsignedBigInteger('resolved_to_id')->nullable();
// Daten
$table->json('remote_snapshot');            // Roh-Eingang (inkl. zeit-spezifischer Felder)
$table->json('local_snapshot')->nullable(); // conflict
$table->json('diff_fields')->nullable();    // conflict
// Denormalisierte Anzeige (Listen ohne JSON-Parsing)
$table->string('display_title')->nullable();
$table->string('display_subtitle')->nullable();
$table->timestamp('occurred_at')->nullable(); // z. B. Zeiteintrag-Zeitpunkt (Gruppierung/Sortierung)
$table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
$table->timestamp('resolved_at')->nullable();
$table->timestamps();

$table->unique(['organization_id', 'plugin_id', 'dedupe_key'], 'iii_dedupe_unique');
$table->index(['organization_id', 'status', 'target_type'], 'iii_status_target_idx');
$table->index(['plugin_id', 'external_type', 'external_id'], 'iii_external_idx');
```

> Index-Namen kurz & explizit ([[feedback_mysql_index_name_limit]]).

### 3.2 Interfaces / Services (neu, in `app/Services/Integration/`)

```php
// Eine Strategie-Kette je Ziel-Entität. CustomerDuplicateFinder wird darauf umgebaut.
interface MatchProfile {
    public function targetType(): string;            // Customer::class
    /** @return list<MatchStrategy>  geordnet, first-confident gewinnt */
    public function strategies(): array;             // ExactField('vat_id'), ExactField('email'),
                                                     // CompositeField(['company','address_zip']), Fuzzy('name', 0.86)
    /** Roh-Remote → lokales Schema (für Anlage/Conflict-Diff). */
    public function mapRemote(array $remote): array;
    public function displayFor(array $remote): array; // ['title'=>…, 'subtitle'=>…]
}

interface EntityMatcher {
    public function match(Organization $org, MatchProfile $profile, array $remote): MatchResult;
}
// MatchResult: ->exact(): ?Model; ->candidates(): array; ->confidence(): 'exact'|'likely'|'fuzzy'|null

enum ImportMatchPolicy: string {
    case AutoLinkExactOnly = 'auto_link';   // Default — Rest in die Inbox
    case AutoLinkAndCreate = 'auto_create'; // Opt-in — ohne Treffer anlegen
    case ManualReview      = 'manual';      // alles in die Inbox
}

// Der Engpass. Jeder Importer ruft NUR das hier auf.
final class IntegrationResolver {
    public function resolve(
        Organization $org, string $pluginId, MatchProfile $profile,
        string $externalType, ?string $externalId, array $remote,
        ImportMatchPolicy $policy, ?ConflictFieldPolicy $onConflict = null,
    ): ResolveOutcome; // Linked|Created|Staged(unmatched)|Conflict|Ambiguous
}
```

`ConflictFieldPolicy` übernimmt die heutige Lexoffice-Semantik
(`remote_wins | local_wins | manual`) generisch.

---

## 4. Migrationspfad pro Integration (Voll-Konsolidierung)

Jeder Importer ersetzt seine eigene Match-/Pending-Logik durch
`IntegrationResolver` + `integration_inbox_items`.

### 4.1 Toggl
- `matchCustomer/matchProject` → `EntityMatcher` mit `CustomerMatchProfile` /
  `ProjectMatchProfile`.
- `recordPending()` → `IntegrationResolver` (case `unmatched`).
- Mapping-UI `admin.toggl.mappings` bleibt als plugin-spezifische Sicht, liest
  aber `ExternalReference` (unverändert) bzw. die Inbox.
- **Backfill:** `toggl_pending_entries(open)` → `integration_inbox_items`
  (`target_type=TimeEntry`, `remote_snapshot` aus Spalten).

### 4.2 OpenProject
- Analog Toggl; zusätzlich `WorkPackage→Task` über `TaskMatchProfile`.
- **Backfill** `openproject_pending_entries(open)`.

### 4.3 RemoteSupport
- Geräte-ID→Asset bleibt (ExternalReference auf Asset); unbekannte Geräte →
  `unmatched`-Inbox (`target_type=TimeEntry`, Kandidat = Asset-Vorschlag).
- **Backfill** `remote_pending_sessions(open)`.

### 4.4 Lexoffice
- `findLocalMatch` → `EntityMatcher` (Customer/Supplier-Profile).
- `recordConflict` → `IntegrationResolver` (case `conflict`).
- Konflikt-Inbox `admin.lexoffice.conflicts` wird Spezial-Filter der neuen
  Inbox-UI (oder leitet dorthin um).
- **Backfill** `pending_external_conflicts(open)` → `case_type=conflict`.

### 4.5 CSV-Wizard
- `EntitySpec::upsert()` ruft künftig `IntegrationResolver` statt direktem
  Upsert. Natürliche Schlüssel (`number`) werden zu einer **Match-Strategie**
  (`ExactField('number')`), nicht mehr zur einzigen Logik.
- Neuer optionaler **Herkunfts-/Fremd-ID-Modus**: Spalte „Fremd-ID" im CSV →
  stabile `ExternalReference` → reimport-fest.
- Import-Formular bekommt die Policy-Auswahl (Default `AutoLinkExactOnly`).

### 4.6 Alt-Tabellen
Nach Code-Cutover + Backfill werden `toggl_pending_entries`,
`openproject_pending_entries`, `remote_pending_sessions`,
`pending_external_conflicts` in einer **separaten, späteren Migration**
entfernt (nicht im selben Release wie der Cutover — Rollback-Fenster).

---

## 5. Phasen & Akzeptanzkriterien

**Phase 0 — Fundament**
- `integration_inbox_items` + Modell; `EntityMatcher`, `MatchProfile`,
  `ImportMatchPolicy`, `IntegrationResolver`.
- `CustomerDuplicateFinder` ([[project_customer_merge]]) wird auf `MatchProfile`
  umgestellt (keine Doppellogik).
- ✅ Resolver-Unit-Tests: Link / Create(Opt-in) / Staged / Conflict / Ambiguous.

**Phase 1 — Inbox-UI + Zuordnungs-Register-UI** ✅ (2026-06-29, 8 Tests)
- Inbox-Seite `admin.integration.inbox` mit Filtern (Status, Fall-Typ, Quelle,
  Entität); Aktionen je Item: *Zuordnen* (Kandidat-Button oder Auswahl
  bestehender), *Neu anlegen*, Konflikt *Remote übernehmen*/*Lokal behalten*,
  *Verwerfen* — über `InboxActionService` + `MatchProfileRegistry`.
- „Zuordnungen"-Verwaltung `admin.integration.mappings` über `ExternalReference`
  (Liste + Verknüpfung lösen).
- Menü-Eintrag mit Badge (offene Einträge), gated `canManageBilling`.
- Offen für Phase 2: bestehende Lexoffice-Konflikte/Toggl-Pendings in die neue
  Inbox migrieren (Backfill).

**Phase 2 — Plugins migrieren (slice-weise)**
- **Entscheidung (2026-06-29):** Zeit-Import-Trias (Toggl/OpenProject/
  RemoteSupport) wird NICHT per-Eintrag in die Inbox gezwungen (das würde die
  Gruppen-Zuordnung als UX zerstören) — stattdessen wird die universelle Inbox
  später um **Gruppierung + Doppel-Referenz (Client→Kunde & Projekt→Projekt) +
  Buchungs-Callback** erweitert. Reihenfolge daher: **Lexoffice zuerst** (passt
  sauber), dann die Zeit-Trias über die erweiterte Inbox.
- ✅ **Lexoffice (2026-06-29):** Konflikte UND zuvor still gezählte ambiguous-
  Fälle schreibt `LexofficeContactSync` jetzt in `integration_inbox_items`
  (Match-/Apply-Kern unberührt). Alte Route `admin.lexoffice.conflicts` leitet
  auf die universelle Inbox um; Backfill-Migration übernimmt offene
  `pending_external_conflicts(lexoffice)`. `pending_external_conflicts` bleibt
  bestehen (Inventory-Outbox nutzt es weiter). 7 Lexoffice-Tests grün.
- ✅ **Toggl (2026-06-29, voller Port, 18 Tests):** Universelle Inbox um
  `group_key` + `InboxGroupBooker`-Mechanik (Registry + `TogglGroupBooker`)
  erweitert. Unmatchbare Toggl-Einträge landen als gruppierte Inbox-Items
  (`recordPending` → `integration_inbox_items`); Auflösung in der Inbox via
  Gruppen-Karte (Kunde/Projekt existierend-oder-neu + Fuzzy-Vorschlag → buchen),
  `bookInboxGroup` materialisiert TimeEntries + merkt client/project-Refs. Alte
  Toggl-Pending-UI/-Routen (`pending.assign`/`dismiss`) entfernt, Toggl-Import-
  Seite verlinkt auf die Inbox. Backfill offener `toggl_pending_entries`.
  `toggl_pending_entries`-Tabelle bleibt vorerst (Drop später).
- ✅ **OpenProject (2026-06-30, voller Port, 38 Tests):** `InboxGroupBooker` zu
  einem generischen Interface gemacht (`groups`/`rules`/`book(input)`/`dismiss`
  + Trait `ResolvesInboxTargets`); jede Gruppe trägt einen `form`-Diskriminator
  (`customer_project` für Toggl, `project` für OpenProject), die Inbox-View
  rendert das passende Formular. `OpenProjectImportService` analog Toggl
  umgebaut (recordPending → Inbox, `bookInboxGroup`/`dismissInboxGroup`/
  `openInboxGroups`), `OpenProjectGroupBooker` + Registry-Eintrag, alte
  Pending-UI/-Routen entfernt, Deep-Link, Backfill.
- ⏳ Offen: RemoteSupport (Geräte→Asset→Kunde-Standardprojekt — andere Form),
  CSV-Wizard.

**Phase 3 — Lücken + CSV-Härtung**
- ✅ **CSV-Kundenimport-Dedup (2026-06-30):** `CustomerSpec::upsert` dedupliziert
  nach der Kundennummer zusätzlich feldübergreifend über den gemeinsamen
  `EntityMatcher` (EXACT: USt-IdNr./Lexoffice-Nr.) — Reimport mit abweichender
  Nummer erzeugt keine Dublette mehr (bestehende Nummer bleibt erhalten).
  `SupplierMatchProfile` ergänzt + in `MatchProfileRegistry` registriert.
- ✅ **SupplierSpec (2026-06-30):** CSV-Lieferantenimport ergänzt (war eine
  Lücke) — `ImportEntity::Suppliers`, `SupplierSpec` (Dedup nach Nummer +
  feldübergreifend via `SupplierMatchProfile`), in `EntitySpecRegistry`
  registriert, i18n in 5 Sprachen. Tests: 3 SupplierSpec + ImportController grün.
- ✅ **ArticleSpec (2026-06-30):** CSV-Artikelstammimport (Basis-Artikel) —
  `Permission::ArticleImport`, `ImportEntity::Articles`, `ArticleSpec`
  (Enum-Validierung Typ/Status, Dedup nach Nummer → GTIN via `EntityMatcher`/
  `ArticleMatchProfile`, GTIN-Treffer erhält bestehende Nummer), registriert +
  i18n. Tests: 4 ArticleSpec + ImportController grün.
- ⏳ Bewusst NICHT gebaut: `InvoiceSpec` — widerspricht der Rechnungshoheit
  (DATEV/Lexoffice führen Rechnungen).
- ✅ **CSV-Wizard Inbox-First (2026-06-30):** opt-in pro Lauf
  (`import_runs.match_policy` = `auto_create` Default | `inbox_first`).
  Marker-Interface `InboxFirstSpec` + Trait `DedupsAndStages` (Match/Apply/Stage);
  Customer/Supplier/Article-Spec implementieren `upsertOrStage` (Treffer →
  Update, sonst → `integration_inbox_items` statt Blind-Anlage). Job routet bei
  `inbox_first` + `InboxFirstSpec` auf `upsertOrStage`; Default-Pfad unverändert.
  Policy-Auswahl in der Import-Maske. Tests: Default-Pipeline + staged grün.
- ✅ **CSV-Fremd-ID-Modus (2026-06-30):** optionale Spalte `external_id`
  (Aliase fremd-id/externe-id/quell-id) bindet die Zeile als `ExternalReference`
  (plugin `csv-import`) → Reimport trifft denselben Datensatz, auch bei
  geänderter Nummer/Name. Dabei `DedupsAndStages` zu `resolveImport()` vereinigt
  (ExtRef → Nummer → Matcher → anlegen/Inbox); die drei Specs delegieren nur noch.
  Gestagte Inbox-Items tragen die Fremd-ID → beim „Neu anlegen" wird die Bindung
  geschrieben. Tests: Refactor + Fremd-ID-Reimport grün.
- ✅ **RemoteSupport (2026-06-30):** Hauptfall (unbekanntes Gerät) in der
  universellen Inbox — `RemoteSupportGroupBooker` liest `openPendingGroups()` und
  bucht über das bewährte `assignPending()` (Form-Typ `asset`: Bindung an
  bestehendes Asset), **ohne Storage-Umbau/Backfill**. Asset-Neuanlage und der
  Mehrkundengeräte-/Shared-Flow bleiben in der RemoteSupport-UI (Deep-Link).
  Generischer Inbox-Controller liefert Asset-Optionen; Inbox-View hat einen
  `asset`-Form-Zweig. Test: Booker bindet Asset + bucht.

**Damit ist der geplante Scope von MVP-103 abgeschlossen** (bewusste Ausnahmen:
`InvoiceSpec` — Rechnungshoheit; RemoteSupport-Shared/Neuanlage — eigene RS-UI).

**Phase 4 — Aufräumen**
- Alt-Tabellen droppen; Doku/Plugin-Doctor aktualisieren.

---

## 6. Risiken & Kompatibilität

- **Laufende Syncs:** Cutover je Plugin hinter dem Resolver; Backfill vor
  UI-Umstellung. Alt-Tabellen erst nach Stabilisierung droppen.
- **Multi-Target-Polymorphie:** `integration_inbox_items` nutzt bewusst
  morph-Spalten **ohne** FK (mehrere Ziel-Entitäten) — referentielle Integrität
  über Resolver + Tests statt DB-Constraint.
- **Audit/GoBD:** Resolver-Aktionen (Link/Create/Merge) sind über die
  bestehenden `Auditable`-Modelle abgedeckt; Inbox-Entscheidungen tragen
  `resolved_by/at`.
- **Performance:** Fuzzy-Match ist O(n²) je Org-Entität — für große Bestände
  Kandidaten-Vorfilter (Trigramm/Präfix) als spätere Optimierung vorsehen.

---

## 7. Entscheidungen (2026-06-29, getroffen)

1. **Inbox-UI-Ort:** zentrales `admin.integration.inbox` mit Filtern (Plugin,
   Entität, Fall-Typ, Status) **+ kontextuelle Deep-Links** aus den jeweiligen
   Bereichen.
2. **Retention:** `open`-Items werden **nie** automatisch gelöscht;
   `dismissed`/`resolved_*` sind nach **90 Tagen** bereinigbar (späterer
   Cleanup-Command, nicht Phase 0).
3. **Fremd-ID im CSV:** **optionale** Spalte — nur genutzt, wenn die Quelle eine
   stabile ID liefert; nie verpflichtend.
4. **Procurement/GAEB:** bleiben **vorerst eigenständig**; Andocken ans
   Zuordnungs-Register optional zu einem späteren Zeitpunkt.
