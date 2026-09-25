# CLAUDE.md — workDiary

Projektweite Arbeitsanweisungen für Claude Code. Diese Datei wird bei jeder
Session geladen.

## Toolkit-first (verbindlich)

Dieses Projekt besitzt eigene, gepflegte Toolkits. **Bevor** ein Helfer,
Service, Parser, Formatter, Validator, Konverter oder Generator app-lokal
geschrieben wird, gilt:

1. **Erst die Toolkits prüfen.** Nachschlagen in der Capability-Map im
   Schwester-Repo: [toolkit-capability-map.md](../WorkDiary-Architecture/toolkit-capability-map.md)
   (öffentliche API-Oberfläche aller Toolkits). Bei Unsicherheit die
   Zielklasse unter `vendor/<paket>/src/...` gegenlesen.
2. **Existiert die Funktion → direkt nutzen** (an der Aufrufstelle, **keine
   dünnen Wrapper/Fassaden** um Einzelaufrufe).
3. **Existiert sie *fast* → Toolkit erweitern** statt app-lokal duplizieren
   (Klasse C). Mit dem Nutzer abstimmen, da Toolkit-Release nötig.
4. **App-lokal nur**, wenn die Logik fachlich workDiary-spezifisch ist
   (Geschäftsregel, Klasse D) oder ein belegter Verhaltensunterschied gegen die
   Toolkit-Variante spricht (Klasse F).

**Leitplanke:** Namensähnlichkeit rechtfertigt keine Migration. Vor dem Ersatz
Verhalten, Fehlersemantik, Locale, Zeitzone, Rundung, Encoding und
Rückwärtskompatibilität vergleichen. Nach einer Migration: betroffene Tests +
`composer test`/PHPStan/Pint grün halten.

**Gate:** `tests/Unit/Architecture/ToolkitFirstRuleTest.php` meldet rohe Aufrufe
(`json_encode`, `number_format`, Dateisystem-Funktionen, `ZipArchive`, `proc_open`/`exec`,
`finfo`, `wordwrap`, `rtrim(rtrim(` …) in `app/`. Ausnahme nur mit Begründung in die
Allowlist der Regel — Geschäftsregel oder belegter Unterschied, nicht Bequemlichkeit.

### Die Toolkits (Details: capability-map)

| Toolkit | Namespace | Zuständig für |
| --- | --- | --- |
| php-common-toolkit | `CommonToolkit\` | String/Zahl/Datum/Validierung, IBAN/BIC/USt-ID, Dateisystem, CSV/XML/XLSX/HTML-Parser, Enums (Currency/Country/Units) |
| php-financial-formats | `CommonToolkit\FinancialFormats\` | CAMT/PAIN (ISO 20022), MT940/SWIFT, DATEV-ASCII, OFX/QIF/QXF, Bank-Format-Konverter ⚠ *nicht in composer.lock* |
| php-erechnung-toolkit | `ERechnungToolkit\` | XRechnung, ZUGFeRD/Factur-X, Order-X, XBestellung, openTRANS, UGL, Despatch Advice, DATANORM 4/5 (Build/Parse/Generate/Validate) |
| php-pdf-toolkit | `PDFToolkit\` | PDF erzeugen (HTML/Text→PDF), Textextraktion + OCR, Merge/Split, ZUGFeRD-PDF/A-3 |
| datev-php-sdk | `Datev\` | DATEV Desktop API: Buchungsstapel, Stammdaten, Belege (kein EXTF-CSV-Export) |
| lexoffice-php-sdk | `Lexoffice\` | Lexoffice REST-API: Kontakte, Belege, Rechnungen, Artikel, Webhooks |
| orgamax-php-sdk | `Orgamax\` | orgaMAX-Buchhaltung REST-API: Kunden/Lieferanten/Artikel, Aufträge, Rechnungen (Zahlung/Lock/Versand/PDF), Dateien, To-dos — Basis des OrgaMax-Plugins |
| php-api-toolkit | `APIToolkit\` | HTTP-/API-Client-Fundament (Basis der SDKs): `ClientAbstract` mit Retry/Backoff/Retry-After und injizierbarem Guzzle, Auth inkl. OAuth2 (PKCE/Revocation), `CursorPaginator`, typisierte HTTP-Exceptions — Plugins beziehen ihre Clients über `App\Plugins\Support\PluginHttpFactory` (`client()`/`sdkClient()`/`clientCredentialsGrant()`); die frühere `PluginHttp`-Klasse existiert nicht mehr |
| php-error-toolkit | `ERRORToolkit\` | Logging-Fundament aller Toolkits: `LoggerRegistry` (+ Laravel-Bridge: auto-discovertes ServiceProvider leitet Toolkit-Logs in den Laravel-Log-Channel, ENV `ERROR_TOOLKIT_LOG_CHANNEL`), `ErrorLog`-Trait, Datei-/Konsolen-Logger, FileSystem-Exceptions |
| php-translation-toolkit | `TranslationToolkit\` | Maschinelle Übersetzung: `TranslationService` (Cache→Provider→Usage-Listener), Provider DeepL/Azure Translator/LibreTranslate inkl. Glossar-Erzwingung, `TranslationRegistry`. Die Adapter unter `app/Services/Ai/Providers/` (Basis `AbstractTranslationAdapter`) verbinden nur noch `AiProviderConnection`/`TranslateRequest` mit dem Toolkit — **Übersetzungsprotokolle gehören ins Toolkit**, app-seitig bleiben Geschäftsregeln (DeepL-Free-Sperre, Gedächtnis-Glossar, Budget, Fehler-Redaktion) |
| php-elearning-toolkit | `ELearningToolkit\` | E-Learning-Standards ohne Laravel: SCORM 1.2/2004 (Manifest, sicheres Entpacken, Abschlussregel), xAPI 1.0.3 (Statement-Validierung), cmi5 (Kursstruktur, Start-URL, LaunchData, Statement-Regeln, LMS-Statements), LTI 1.3 (JWT, Login, Launch-Prüfung, Deep Linking) — reguläre Abhängigkeit (`^0.1.2`); Betrieb (Inhalts-Host, LRS, Schlüsselablage) bleibt unter `App\Services\Learning` |

### Bewusst app-lokal (nicht erneut vorschlagen)

`NumberHelper::normalizeDecimal` (gibt float — Decimal-String-Regel),
`CurrencyHelper::normalizeAmount` (DE-Format), `CryptoHelper::secureHash`
(gesalzen/base64 statt Hex für Hash-Ketten), `CreditCardHelper`-Luhn (validiert
nur, erzeugt keine Prüfziffer). Begründungen:
[toolkit-konsolidierung-2026-06.md](../WorkDiary-Architecture/toolkit-konsolidierung-2026-06.md).

### Shell-Kommandos: CommandBuilder escaped selbst — NICHT caller-seitig escapen

Der `ConfigToolkit\CommandBuilder` escaped die eingesetzten Platzhalterwerte
**selbst** (config-toolkit ≥ 0.5). Aufrufer (`PdfFile`, `TifFile`,
`OfficeHelper`, `ImageCropHelper`, `MediaHelper`, …) übergeben daher **Rohwerte**.
**Kein** caller-seitiges `escapeshellarg()` an Einzelwert-Platzhaltern (z. B.
`[OUTPUT]`) ergänzen — das ist redundant. Ein Static-Audit meldet die scheinbare
„Asymmetrie" (`TifFile::merge`: `[INPUT]` escaped, `[OUTPUT]` roh) fälschlich als
Bug: **ist keiner.** `[OUTPUT]` (Einzelwert) bleibt roh, der CommandBuilder
escaped ihn einmal; `[INPUT]` beim Multi-Datei-Merge muss pro Datei vor-escaped
werden (sonst würden alle Dateien zu *einem* Token verklebt) — `CommandBuilder::isShellQuotedSequence()`
erkennt die vor-escapte Sequenz und reicht sie mehrteilig durch. Also: bei
Shell-Command-Findings zuerst prüfen, ob der Wert über den CommandBuilder läuft;
wenn ja, Rohwerte übergeben, kein caller-Escaping (Ausnahme: bewusstes
Multi-Wert-Vor-Escaping).

## Morph-Map: Typwerte sind Aliase, nie Klassennamen (MVP-860)

`Relation::enforceMorphMap` ist aktiv; `config/morph-map.php` ist generiert
(`php artisan morph-map:generate`, nach jedem neuen Modell; `--check` im Gate).
Zwei Schlüsselarten, `App\Support\MorphMap`:

- **Alias** (Tabellenname) für alle `*_type`-Spalten: schreiben mit
  `$model->getMorphClass()` oder `MorphMap::alias(X::class)`, vergleichen mit
  `MorphMap::is($row->x_type, X::class)`, nachschlagen in klassen-geschlüsselten
  Maps/Registries/`match` erst nach `MorphMap::classFor($row->x_type)`.
- **Stabiler Schlüssel** (`MorphMap::stableKey(X::class)`, bisheriger
  Klassenname) für die hash-verketteten Tabellen `audit_logs` und
  `audit_redactions` sowie die Sqid-Alphabete — nie `getMorphClass()` beim
  Lesen oder Schreiben von Audit-Zeilen.

Nie `X::class`, `$x::class`, `get_class()` oder `=== X::class` an einer
Typspalte (Gate `RawMorphClassLiteralRuleTest`, auch qualifizierte Spalten,
Operator- und Ternärform). Werte von Clients (Formulare, Offline-Sync) dürfen
Alias oder Klassenname sein → mit `classFor()` auflösen, Alias speichern.
Enum-Casts (`'kind_type' => KindEnum::class`) sind keine Morph-Werte. Zieht
eine Klasse um, bleibt ihr `legacy`-Schlüssel und nur der Wert wird nachgeführt.

## Modul-Manifeste: eine Quelle je Modul (MVP-861)

Jedes Modul hat ein Manifest unter `app/Modules/Manifests/<Name>Manifest.php`
(Basis `App\Modules\Manifest`, Register `App\Modules\ModuleRegistry`). Das
Manifest nennt Code, Art (`ModuleKind::Platform|Core|Feature`), Lizenzcode,
Label und Beschreibung, Domänenordner, **Tabellen**, Routenmuster fürs
Modul-Gate, Rechtegruppen, Navigationsschlüssel, `requires()` und Plugins.
`config/plans.php` enthält nur noch Tarife, Presets und Purge-Regeln.

- **Neue Tabelle, neuer Ordner, neue Rechtegruppe, neues Lizenzmodul →
  Manifest ergänzen.** `php artisan modules:check` (im `composer qa`) und das
  Gate `ModuleManifestCoverageRuleTest` verlangen genau eine Zuordnung.
- Routen-Gate: `routePatterns()` des Manifests (spezifischstes Muster
  gewinnt); Sidebar-Gating: `navigation()`; Modulkatalog und Gate-Meldung:
  `label()`/`description()` über `ModuleCatalog`.
- Ein Lizenzcode kann mehrere Manifeste tragen (Lager: Inventory,
  Manufacturing, Procurement); genau eines ist Eigentümer (`ownsLicense()`).
- Betrieb: `modules:cache` nach dem Deploy (steht in deploy.sh), `modules:clear`
  beim Entwickeln nach neuen Manifesten.
- **Modulregister:** nach jeder Manifest-Änderung `php artisan modules:doc`
  (schreibt `../WorkDiary-Architecture/modul-register.md`); `modules:check`
  meldet ein veraltetes Register, sobald das Architektur-Repo daneben liegt.

## Domänenordner: keine Klasse direkt im Schichtordner (MVP-862)

Modelle, Services, Controller, Policies, Requests und Factories liegen in
Domänenordnern, die das Manifest des Moduls in `folders()` nennt
(`app/Models/Time/TimeEntry.php`, `app/Policies/Time/TimeEntryPolicy.php`,
`database/factories/Time/TimeEntryFactory.php`). Einzige Wurzeldateien sind
die Basisklassen `Controller`, `BaseFormRequest` und `PermissionPolicy`.
Gate `DomainFolderRuleTest`; Plugin-Modelle unter `app/Models/Plugins/<Name>`.
Wer eine Klasse verschiebt: `use`-Zeilen und Nachbarn im alten Namespace
nachziehen, `morph-map:generate` laufen lassen (Legacy-Schlüssel bleiben,
Werte wandern), Views bleiben wo sie sind.

## Modulgrenzen: Contracts, Events, Erweiterungspunkte (MVP-863)

`App\Modules\BoundaryRules` (Matrix nach `ModuleKind`): Plattform → nur
Plattform, Kern → Plattform/Kern, Feature → Plattform/Kern direkt, Feature →
Feature nur mit `requires()` (transitiv, Lizenzfamilie zählt). Ausgenommen:
Modelle, `…\Contracts\…`, `…\Dto\…`, `…\Exceptions\…`, `App\Events\…`;
konkrete Plugins werden nicht gemessen, `app/Plugins/Support` ist Plattform.
`php artisan modules:deps [--all|--baseline]` zeigt Kanten und Verstöße;
Gate `ModuleBoundaryRuleTest` gegen
`tests/Unit/Architecture/baselines/module-boundaries.php` — seit Phase 111
Welle 4 leer; ein neuer Verstoß wird gelöst, nicht eingetragen.

Über eine Modulgrenze führen drei Wege, alle im Manifest:

- **Erweiterungspunkt** — Plattform/Kern definiert das Interface und liest
  `ModuleRegistry::extensions(Interface::class)`; das Modul nennt seine Klassen
  in `extensions()`. Bestehend: `DeadlineScan`, `EntitySpec`, `SearchSource`,
  `DemoBlock`, `SyncCommandHandler`, `MailableDocumentProvider`,
  `ProfileInstallStep`, `AllocationTargetHandler`, `InboxGroupBooker`,
  `VehicleReservationGuard`, `ProjectEconomicsDimension`, `RoomBlockingSource`,
  `CloudIntakeHandler`, `RetentionPolicyProvider`, `MailIntakeHandler`,
  `OffboardingStep`, `NavigationCondition`, `PluginCapabilitySource`,
  `MirrorPdfRenderer`, `RuleAction`, `RuleTrigger` (Automationsregeln), `EarlyWarningSource`. Neue Scans, Specs, Quellen,
  Demo-Blöcke, Löschbereiche usw. **nie** in eine feste Liste, sondern ins
  Manifest des Moduls.
- **Contract mit Null-Bindung** — der Aufrufer braucht eine Antwort:
  Interface im aufrufenden Modul unter `Services/<Modul>/Contracts`,
  Null-Implementierung daneben, `contracts()` im definierenden und
  `bindings()` im bindenden Manifest (genau eins). Null antwortet neutral oder
  wirft `ModuleUnavailableException::for($code)`.
- **Domain-Event** — es soll nur etwas geschehen: Event unter
  `App\Events\<Domäne>` (Vergangenheitsform), Listener unter
  `App\Listeners\<Domäne>` in `listeners()`; Fachmodul-Listener erben
  `ModuleListener`; Konsistenz-Events synchron, Folgeprozesse
  `ShouldDispatchAfterCommit`; Benachrichtigung über `NotifiesUsers` +
  `NotificationBridge`. Observer nutzen nur ihr Modul oder die Plattform
  (Gate `ObserverModuleRuleTest`), Gate `EventNamingRuleTest`.

`modules:check` prüft die Verdrahtung (Interface erfüllt, Contract genau
einmal definiert/gebunden, Listener mit `handle()`).

## Journale: ein Baustein für alle Ereignisketten (MVP-864)

Ereignisjournale (`*Event` auf `*_events`) erben von
`App\Models\Journal\JournalEntry` (append-only) bzw.
`HashChainedJournalEntry` (GoBD-Kette, Kanonik bleibt in `hashPayload()`).
Vertrag: `event`, `actor_user_id`, `payload`, `occurred_at`; abweichende
Spalten nennt das Journal in `$journalColumns` (`metadata`, `meta`,
`event_type`, `user_id`, `created_at` ist Standard für `occurred_at`).
Der Träger nutzt `HasJournal` (`journal()`, `record($event, $payload, $actor,
$at, $extra)`), trägerlose Journale `X::log(null, …)`. **Nie** `XEvent::create(`
oder `->journal()->create(` (Gate `JournalContractRuleTest`). Anzeige über
`<x-journal :entries="$x->journal">`, Labels über `label()` (Enum →
`journal.<modul>.<event>` in `lang/*/journal.php` → Modulpräfix → lesbarer
Schlüssel). Neues Journal: Modell erben, `subject()`, Träger `HasJournal`,
Labels ×5.

## Belegpositionen: ein Vertrag, ein Rechner (MVP-865)

Positionen von Rechnung, Angebot, Rechnungsplan, Kostenermittlung, Übergabe
(Quellnachweis und Rechnungssicht) und LV tragen `App\Models\Contracts\DocumentLine`
über den Trait `App\Models\Concerns\IsDocumentLine`; ihr Kopf trägt
`HasDocumentLines` (`lines()`, `documentCurrency()`, `documentTotals()`).
Abweichende Spalten nennt das Modell in `lineColumns()`
(`'tax_rate' => 'vat_rate'`, `'net_amount' => 'total_price'`, `null` = Feld
fehlt). Gelesen wird über den Vertrag (`netAmount()`, `taxRate()`,
`lineQuantity()`), nie über rohe Spalten quer durch die Belegarten.

- **Menge × Einzelpreis rechnet nur `App\Services\Billing\DocumentTotalsCalculator::lineNet()`**
  (HalfUp auf der Preisskala, dann Währungsskala; Positionsrabatt Prozent vor
  Betrag). Kein `round($q * $p, 2)`, kein `$price->times($qty)` im Code —
  Gate `DocumentLineContractRuleTest`, Ausnahme nur der Rechner selbst.
- **Belegsummen** liefert `documentTotals()` des Kopfes über
  `DocumentTotalsCalculator::totals($lines, DocumentTotalsContext)`:
  Zeilennettos in Währungspräzision je Steuersatz, Belegrabatt anteilig,
  Steuer pro Satz gerundet, Reverse Charge ohne Steuer. Neue Belegart: Kopf
  implementiert `HasDocumentLines` und baut seinen Kontext (Währung,
  Satz-Rückfall, Rabatt) selbst — keine zweite Summenrechnung.
- **Neue Positionstabelle** (`quantity` + `unit_price` + `tax_rate|vat_rate`)
  ⇒ Modell mit `DocumentLine`, `IsDocumentLine`, `HasSqid`, `Auditable`,
  `HasFactory` + Factory, `entity-types` ×5; Quellposten (wie
  `material_usages`) nur mit Grund in der Allowlist des Gates.
- Gespeicherte Beträge gelten vor der Rechnung (`InvoiceItem.amount` setzt
  der Observer über `calculatedNetAmount()`, LV-`total_price` ist die
  Bieterangabe); Rechnungspositionen ausgestellter Rechnungen bleiben
  unveränderlich. Skonto bleibt an der Rechnung. Eingefrorene Summen unter
  `tests/Fixtures/totals` — Änderungen an Rundung oder Rabattlogik brechen
  sie absichtlich.

## Feldschema: ein Typkatalog für Erfassung (MVP-866)

Formulare, Checklisten und Kundenportal erfassen über `App\Services\Fields`:
`FieldType` (14 Typen) ist der einzige Erfassungs-Typkatalog; `FieldSchema`
(Definitionen, `fromRows()` für Dialogzeilen, `fromArray()` für Bestand),
`FieldValidator` (Laravel-Regeln je Typ, Pflichtanhänge), `FieldValues`
(typtreue Werte, `display()`), `FieldDocument` (Schema + Werte in einer
JSON-Spalte, Cast `FieldDocumentCast`). Blade: `<x-field-input :field>` und
`<x-field-display :field :values>` — die einzigen Stellen, die auf den Typ
verzweigen (Gate `FieldSchemaRuleTest`).

- **Kein neues Typ-Enum** mit `text/number/date/choice/photo/…`; Fachtypen
  registriert ein Modul als `Contracts\FieldExtension` in `extensions()` seines
  Manifests und referenziert sie in `FieldDefinition::extension`.
- **Neue `checklist`-Spalte ⇒ `FieldDocumentCast`**; neue Schema-/Werte-
  Spalten (`fields`, `value_json`, `values`) nur mit Grund in der Gate-
  Allowlist. Kein `@switch($field['type'])` / `match ($field->type)` in
  Views oder Diensten — Komponente bzw. Validator nutzen.
- Formularvorlagen speichern `FieldSchema::fromRows()->toArray()`; alte
  Typnamen `select`/`checkbox` liest `FieldType::fromStored()` weiter.
- **Fach-Enums mit eigenen Speicherwerten** (`ProtocolItemType`,
  `ProcedureStepType`, `SurveyQuestionType`, MVP-867) implementieren
  `App\Enums\Fields\Contracts\FieldTyped` (`fieldType()`, `fieldExtension()`)
  statt eigene Regeln zu tragen; Adapter lesen die Fachform
  (`Protocol\Fields\ProtocolItemFields` liest `value_json` nur — der
  Protokoll-Hash bleibt, Fixture `tests/Fixtures/protocols`;
  `Procedure\Fields\ProcedureStepFields`, `SurveyQuestion::fieldDefinition()`).
  Fachtypen (Mangel, Messreihe, Anhänge, Signatur) sind `FieldExtension`s im
  Protokoll-Manifest. Datumsfelder prüfen strikt (`DateHelper::isDateTime`,
  kein „tomorrow"), Eingabenamen ohne Präfix über `FieldValidator::rules($schema, '')`.

## Eigene Felder je Organisation (MVP-868)

Kunde, Auftrag, Asset, Artikel und Projekt tragen `HasCustomFields`
(Vertrag `App\Models\Contracts\CustomFieldSubject`); das Schema je Träger
liegt in `custom_field_definitions` (Org × Morph-Alias, `FieldSchemaCast`,
Version, aktiv), die Werte in `custom_field_values` (ein Satz je Datensatz,
`FieldValuesCast`). Alles läuft über `App\Services\Fields\CustomFieldService`
(`schemaFor()`, `rules()`, `saveDefinition()`, `sync()`, `texts()`,
`exportColumns()/exportRow()`).

- **Neuer Träger**: Klasse in `CustomFieldService::SUBJECTS`, Modell mit
  `HasCustomFields implements CustomFieldSubject`, Controller mit
  `SavesCustomFields` (`validatedCustomFields()` vor dem Speichern,
  `syncCustomFields()` danach), Formular `<x-custom-fields-group :model :subject>`,
  Detailseite `<x-custom-fields-card :subject>`; Exporte hängen
  `exportColumns()/exportRow()` an, Suchquellen `customFieldTexts()`.
- Feldlisten bearbeitet `x-field-schema-editor` (auch Formularvorlagen);
  Upload-/Signaturtypen gibt es hier nicht. Deaktivieren statt löschen,
  Recht `organization.customFields.manage`.
- Branchenprofile: Schlüssel `custom_fields` (Alias → Zeilen) ergänzt nur
  fehlende Schlüssel (`CustomFieldInstallStep`); eigene Felder werden nie
  überschrieben.

## Kontaktdaten: Primärkontakt inline, Anschrift als Satellit (MVP-869)

Jede Partei (Kunde, Lieferant, Fremdkunde, Lead, Bewerbung, Vereinsmitglied,
Erziehungsberechtigte) trägt `HasContactAndBankDetails` und implementiert
`App\Models\Contracts\ContactDetailsHolder`. E-Mail, Telefon und
Ansprechpartner stehen inline; Anschriften und Bankverbindungen liegen in
`contact_addresses`/`contact_bank_accounts`. Nur Kunde und Lieferant haben
eine Projektion (`address_*`, `bank_*`, `ContactDetailsProjectionObserver`).

- **Keine Spalten `street`/`zip`/`postal_code`/`city`** an Parteien (Gate
  `ContactColumnsRuleTest`, Allowlist mit Grund: Einsatzort, Objekt, Absender).
- Formular: `<x-contact-address-fields :subject>` mit Eingaben
  `address_street`/`address_zip`/`address_city`, Regeln
  `ContactSatelliteFields::addressRules()`; Controller
  `WritesContactDetails` (`pullContactDetails()` vor, `writeContactDetails()`
  nach dem Speichern); Dienste `ContactDetailsWriter::pullInline()` +
  `writeInline()` (Teil-Update). Anzeige `postalAddressLines()`.
- Anonymisierung löscht den Satelliten ausdrücklich (`addresses()->delete()`),
  Auskunftsabschnitte geben die Anschrift aus; harte Löschung der Partei
  räumt die Satelliten selbst ab.
- Konventionen für Spaltennamen und Kontaktdaten:
  [datenbank-konventionen.md](../WorkDiary-Architecture/datenbank-konventionen.md).

## Spaltennamen (MVP-870)

Neue Spalten folgen [datenbank-konventionen.md](../WorkDiary-Architecture/datenbank-konventionen.md):
`created_by`/`updated_by`, `<rolle>_user_id`, `note`, `is_*`, `valid_from`/
`valid_until`, Fachart `kind`, `<name>_type` nur mit `<name>_id`,
`*_amount` + `currency`. Gate `ColumnNamingRuleTest` prüft Migrationen nach
`2027_02_24_140000` (verboten: `created_by_user_id`, `updated_by_user_id`,
`notes`, `active`, `valid_to`, `type`, loses `*_type`); der Bestand bleibt.

## Listen-Sortierung über den Index-Parser (MVP-871)

`sort`/`dir` liest ein Controller nur über `App\Support\SortableQuery::resolve()`
(bzw. `apply()` oder `ParsesIndexQuery`): Whitelist, ungültiger Schlüssel setzt
Schlüssel **und** Richtung auf den Default, `dir` unabhängig von der
Schreibweise. Nie `$request->query('sort')`/`string('dir')` direkt (Gate
`IndexQueryRuleTest`). `direction` ist in der App ein Fachfilter
(Zahlungs-/Bürgschaftsrichtung, Verschieben), `input('sort')` ein Positionsfeld.

## Statusvertrag: Übergänge nur im Enum (MVP-872)

Status-Enums mit Lebenszyklus implementieren `HasStatusTransitions` und
nutzen `HasTransitions`; die Tabelle steht in `allowedTransitions()`. Dienste
prüfen mit `$status->canTransitionTo()` oder den Guard-Traits
`AssertsStatusTransition` (RuntimeException) bzw.
`AssertsValidatedTransition` (ValidationException mit Meldungsschlüssel).
Keine `TRANSITIONS`-Konstante, keine Methode `canTransition`/`transitionTo`/
`assertTransition` außerhalb `app/Enums` (Gate `EnumTransitionContractTest`,
Regel 3). Gleicher Status zählt nicht als Übergang — wer ihn zulassen will,
prüft `$from !== $to` selbst. Status-Spalten casten auf ein Enum
(`StatusEnumCastRuleTest`, Baseline nur schrumpfen).

## Verweise

Die gesamte Entwicklungs-/Architekturdoku liegt im Schwester-Repo
**WorkDiary-Architecture** (`../WorkDiary-Architecture/`, im Workspace
eingebunden): Feature-/MVP-Doku unter `features/`, Security-Doku unter
`security/`, Querschnittsdoku auf Root-Ebene.

- Toolkit-API-Referenz: [toolkit-capability-map.md](../WorkDiary-Architecture/toolkit-capability-map.md)
- Migrationslog & A–F-Klassifikation: [toolkit-konsolidierung-2026-06.md](../WorkDiary-Architecture/toolkit-konsolidierung-2026-06.md)
- Audit-Befunde (offene Migrations-/Erweiterungskandidaten): [toolkit-audit-2026-06.md](../WorkDiary-Architecture/toolkit-audit-2026-06.md)
- Audit 2026-09 (Eigenbau statt Toolkit, Gate, Toolkit-Kandidaten): [toolkit-audit-2026-09.md](../WorkDiary-Architecture/toolkit-audit-2026-09.md)
