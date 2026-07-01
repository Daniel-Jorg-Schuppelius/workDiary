# Toolkit-Audit — offene Migrations-/Erweiterungskandidaten

Stand: 2026-06-30. Ergänzt `docs/toolkit-konsolidierung-2026-06.md` um **neue**
Befunde aus einem breiten Sweep über `app/` (6 parallele Audit-Durchläufe gegen
`docs/toolkit-capability-map.md`). Klassen wie im Konsolidierungs-Doc:
B = lokales Duplikat → migrieren · C = fachneutral, fehlt im Toolkit → ergänzen ·
D = Geschäftsregel (belassen) · E = optional/guarded · F = unklar/riskant.

**Leitplanke unverändert:** Namensähnlichkeit ≠ Migration. Vor Ersatz Verhalten/
Locale/Rundung/Encoding/Fehlersemantik vergleichen; Geld/Mengen bleiben
Decimal-String/bcmath (eine float-API ist kein gültiger Ersatz).

---

## Zweiter Audit — Datei-/CSV-Operationen (2026-07-01)

Gezielter Sweep über rohe Datei-Ops (`file_get_contents`/`file_put_contents`/
`fopen`/`fwrite`/`unlink`/`copy`/`mkdir`/`glob`/`scandir`) und CSV
(`fputcsv`/`str_getcsv`/manuelle Serializer). **Zusätzliche Leitplanke:** Toolkit
`File`/`Folder` arbeiten nur auf lokalem FS und werfen Exceptions — alles über
Laravel `Storage`/Flysystem (FTP/SFTP) ist **kein** Ersatz (Klasse D); Streaming
nach `php://output` ≠ String-Builder; `payload_hash`-sensible Exporte nur nach
Round-Trip-Beleg.

**Ergebnis:** Der Großteil ist bewusst Klasse D — Storage/Flysystem-nah oder
toleranter `@`-Best-Effort-Cleanup (`OrganizationLifecycle`-Purge,
`DatabaseHealth`-Marker, `SystemHealthCommand`-Probe), wo die Toolkit-Exception-
Semantik die dokumentierte Fehler-Toleranz bräche. Der CSV-Lesepfad ist komplett
Toolkit; größte App-interne Dublette ist die inline-Kopie von `CsvFacade::line`
in 16 Reporting-Controllern (10 nutzen schon ein Trait) — App-Hygiene, kein
Toolkit-Thema.

### Umgesetzt (workDiary-seitig, installiertes Toolkit) ✅ 2026-07-01

- **`Console/Commands/Release/ManifestCommand.php`** + **`Console/Commands/Isms/
  SbomGenerateCommand.php`** — `is_dir`+`mkdir(recursive)` → `Folder::create`,
  `file_put_contents` → `File::write`, in try/catch mit erhaltener FAILURE-Meldung.
- **`Plugins/Toggl/Sources/TogglWorkspaceReader.php`** — 2× `file_get_contents`
  → `File::read` (CSV-Jahresdateien, workspace-JSON).
- **`Support/Translations.php`** — `file_get_contents`→`File::read`,
  `file_put_contents`→`File::write` (3×), `mkdir`→`Folder::create` (`lang:sync`).
- Verifikation: `File::read` = reines `file_get_contents` (kein Encoding-Umbau),
  `File::write` funktioniert für neue Pfade, `Folder::create` idempotent. Tests
  grün (TranslationParity/ReleaseManifest/SbomCommand/TogglExportImport, 21),
  Pint + PHPStan L8.

### JSON: encode flächendeckend auf JsonHelper ✅ erledigt (2026-07-01)

Eigener Audit (encode/decode). `JsonHelper::encode($data, $flags)` =
`json_encode($data, JSON_THROW_ON_ERROR | $flags)` → **byte-identisch bei
gleichen Flags**, wirft statt `false`. **15 `json_encode`-Stellen migriert**
(Translations, Diagnostics, WebhookDispatch, BillingTransfer, DedupsAndStages,
IntegrationResolver + hash-/GoBD-kritisch: HashChained-Kette, ReleaseManifest-
Signaturbasis, SbomGenerator, ManifestCommand, ComponentsController,
ExportAuditLog, WhistleblowingExport, AuditPackage, RegisterExport). Der
`(string)`-Cast entfällt; bei AuditPackage/RegisterExport fiel der tote
`=== false`→throw-Block weg. Der throw-statt-`false` behebt latente
`(string)false === ""`-Bugs in Hash-/Dedupe-Keys. Direkt an der Aufrufstelle,
**keine Fassade**. 77 Tests grün (Hash-Kette/Release/SBOM/Auditpaket/
Whistleblowing/Register/Übersetzungen), Pint + PHPStan L8.

**Decode bewusst überwiegend belassen:** `JsonHelper::decode` wirft **und loggt**
bei ungültigem JSON. Nur `DiagnosticsService:457` migriert (hatte schon
`try/catch`). NICHT angefasst: `HashChained:131` (nutzt `json_last_error()` als
stille JSON-Sonde → würde bei jedem Nicht-JSON-Wert werfen+loggen, GoBD),
Google-Timeline-/CSAF-Upload/Sanctum-Spalte (tolerieren Müll bewusst), 4×
WebAuthn/2FA-`response()->json(json_decode(...))`-Roundtrips. **Klasse C: keine.**

### Toolkit erweitert (release-pending) ✅ 2026-07-01

- **`CommonToolkit\Helper\Data\CSV\StringHelper::encodeField()` +
  `encodeLine()`** (im Repo `php-common-toolkit`, mit 9 Tests grün, 200er
  CSV-Suite ohne Regression, Pint/PHPStan sauber). Schließt die **Wurzel-Lücke**,
  warum CSV-*Ausgabe* nicht am Toolkit hängt: das Toolkit hatte nur Parser +
  round-trip-Serializer (`FieldAbstract::toString` quotet nur geparste Felder),
  aber **keinen sicheren Ausgabe-Encoder** für frei erzeugte Werte. Der neue
  Encoder ist byte-gleich zu `App\Support\Toolkit\CsvFacade::line` (bedingtes
  RFC-4180-Quoting + Enclosure-Verdopplung) und deckt via `forceEnclosure`-Flag
  auch das Always-Quote von `TimeExport`-`GenericCsvProfile` ab.
  **Publish bleibt beim User** (Tag+Push+Packagist+`composer update`). Danach
  können `CsvFacade::line` daran delegieren und die `fputcsv`/`php://output`-
  Exporte den Toolkit-Encoder nutzen (statt PHP-`fputcsv` mit abweichendem
  `,`/`\`-Quoting).

### Bewusst NICHT erweitert / migriert

- `File::write(lock: true)` (LOCK_EX) wäre für die `.env`-/Lock-Writer nötig,
  aber die sind ohnehin F/D (Symlink-`getRealPath` + `@`-Best-Effort) → ohne
  sicheren Konsumenten spekulativ, daher nicht ergänzt.
- Streaming-CSV nach `php://output` in ~8 Controllern bleibt Framework-Glue
  (`response()->streamDownload`); der wiederverwendbare Teil ist genau der neue
  `encodeLine`-Encoder — kein eigener Toolkit-Streaming-Writer nötig.
- `fputcsv`-Stellen (`ProcessingActivity`, `ExportAuditLog`) sind **nicht**
  byte-gleich zu `CsvFacade`/dem Encoder (PHP-`fputcsv`: `,`-Delimiter + `\`-
  Escape) → nur nach Round-Trip-Abnahme; `ExportAuditLog` zusätzlich GoBD.
- 16 Reporting-Controller mit inline-`CsvFacade::line`-Kopie → App-interne
  Dedup auf das `WritesReportCsv`-Trait (byte-identisch, kein `payload_hash`-
  Risiko), aber App-Hygiene statt Toolkit — separater Sweep bei Bedarf.

## Priorisierte Befunde (Klasse B — echte Gewinne)

### P1 · ZIP-Entpackung in Toggl-Import → `ZipFile::extract` ✅ erledigt (2026-06-30)

- **`app/Plugins/Toggl/Http/Controllers/TogglController.php`** — manuelles
  `new \ZipArchive` + händischer Zip-Slip-Check (`str_contains('..')`) + `extractTo`
  ersetzt durch `ZipFile::isZipFile` + `ZipFile::extract(…, deleteSourceFile: false)`
  in try/catch (Fehlertexte + `rrmdir`-Cleanup erhalten). `rrmdir` bewusst
  **belassen** (toleranter Cleanup-Helfer; `Folder::delete` wirft — andere
  Fehlersemantik, auch in `pruneOldImports` genutzt). Tests grün
  (`TogglExportImportTest`, 10/10), Pint + PHPStan L8 grün.
- Ziel: `CommonToolkit\Helper\FileSystem\FileTypes\ZipFile::extract`.
- **Gewinn (Sicherheit):** Toolkit prüft Zip-Slip per normalisiertem realpath-
  Containment — **stärker** als der String-Match auf `..`. Reiner Extract **ohne
  AES** → unterscheidet sich von den bewusst zurückgestellten AES-ZIP-Stellen.
- **Risiko:** Toolkit **wirft** statt `back()->withErrors()` → in try/catch
  kapseln, Fehlertexte + `rrmdir`-Cleanup erhalten. Validierung (`mimes:zip`,
  200 MB) bleibt vorgelagert. Konfidenz: hoch.
- Mitnahme: **`TogglController.php:634-646` (`rrmdir`)** → `Folder::delete(recursive)`.

### P2 · Lexoffice-Plugin aufs SDK — ⛔ NICHT migriert (nach Verifikation verworfen, 2026-07-01)

> **Neubewertung nach Tiefenanalyse:** Ursprünglich als „größter Hebel"
> eingestuft. Verifikation widerlegt das: Das SDK transportiert über
> **`GuzzleHttp\Client` direkt** (php-api-toolkit `ClientAbstract`), während
> **10+ Lexoffice-Tests** über `Http::fake()` (Laravel-HTTP) mocken. Eine
> Migration macht diese Fakes wirkungslos → echte Netzaufrufe im Test; sie
> erzwingt das Umschreiben aller Tests auf Guzzle-MockHandler. Hinzu kommen
> bewusste Design-Entscheidungen: Custom-Retry/Rate-Limit (429 + `Retry-After`),
> Binär-PDF-Download im Zweischritt, Dual-Path-Dateiauflösung, ExternalReference-
> Payload-Snapshots, `finalize`/`precedingSalesVoucherId`-Query-Flags,
> Optimistic-Locking-`version` bei Artikeln. Nutzen einer Migration (typisierte
> Entities) steht in keinem Verhältnis zum Risiko an einer **buchhaltungs-
> kritischen** Integration ([[project_invoice_sovereignty]]).
> **Entscheidung: belassen (Klasse D/F).** Die SDK-Endpoints existieren in 1.0.2
> zwar alle (der „kein Articles-Endpoint"-Kommentar in `LexofficeArticleSync` ist
> veraltet), aber das ist nicht der Blocker. Falls je gewünscht: nur als
> *koordinierter* Slice mit gleichzeitiger Umstellung der Tests auf SDK-/Guzzle-
> Mocking — bewusst zurückgestellt, siehe [[project_lexoffice_sdk_http_fake]].

#### Ursprüngliche Befundtabelle (verworfen, zur Nachvollziehbarkeit)

Nur der **Kontakt-Schreibpfad** nutzt das `lexoffice-php-sdk`
(`LexofficeService::createContact` → `ContactsEndpoint`, Klasse A). Alle übrigen
Pfade umgehen vorhandene SDK-Endpoints zugunsten roher `Http::`-Aufrufe mit
handgemappten Array-Payloads:

| Datei:Zeile | Roh-HTTP gegen | Ziel-SDK-Endpoint |
| --- | --- | --- |
| `Plugins/Lexoffice/LexofficeInvoiceService.php:59,109,131,143` | `/invoices`, `/document`, `/files` | `Documents\InvoicesEndpoint::create(…,finalize)`/`render` + `FilesEndpoint::download` |
| `Services/Finance/Targets/LexofficeTarget.php:93,275` | `/invoices` (Entwurf), `/contacts` | `InvoicesEndpoint::create(…,finalize:false)`, `ContactsEndpoint::search` |
| `Plugins/Lexoffice/LexofficeArticleSync.php:60,155,163,298` | `/articles` (CRUD) | `ArticlesEndpoint` + `Articles\Article` |
| `Plugins/Lexoffice/LexofficeVoucherSync.php:281`, `LexofficeVoucherFileService.php:72,129,152,170` | `/voucherlist`, `/vouchers`, `/files` | `VoucherListEndpoint`, `VouchersEndpoint`, `FilesEndpoint::download` |
| `Plugins/Lexoffice/LexofficeContactSync.php:77` | `/contacts` (Pull) | `ContactsEndpoint::search`/`get` |
| `Plugins/Lexoffice/LexofficeDunningService.php:53,69`, `LexofficeOrderDocumentService.php:66,111`, `LexofficeDeliveryNoteService.php:62,111` | `/dunnings`, `/order-confirmations`, `/delivery-notes` | `DunningsEndpoint`/`OrderConfirmationsEndpoint`/`DeliveryNotesEndpoint` (`pursue`) |

- **Gewinn:** beseitigt doppelte Mapping-Logik, bringt typsichere Entities, eine
  Auth-/Rate-Limit-Stelle. Echte Driftquelle.
- **Vorbedingung (pro Endpoint):** gegen installierte **SDK-Version 1.0.2**
  gegenlesen, ob Datei-Download (rohe Bytes + Content-Type), `render`
  (`/document→documentFileId→/files`-Zweischritt) und `pursue`
  (`precedingSalesVoucherId`) abgedeckt sind. Wenn nicht → **Klasse C** (SDK
  ergänzen) statt B. `?finalize`-Semantik (true bei Invoice, false bei
  Finance-Entwurf) muss erhalten bleiben.
- **Hinweis:** float-Rundung (`round(...,2)`) in `LexofficeTarget`/Mapper ist
  bestehende Semantik — nicht durch die Migration verschlimmbessern.
- (ursprüngliche Konfidenzangabe entfällt — siehe Neubewertung oben.)

### P3 · Public-IP-Prüfung → `IPHelper::isPublicIP` ✅ erledigt (2026-07-01)

- **`app/Support/UrlSafety.php`** — der private Leaf-Check `isPublicIp`
  (`filter_var(…, NO_PRIV_RANGE|NO_RES_RANGE)`) wurde entfernt; die zwei
  Aufrufstellen rufen jetzt `IPHelper::isPublicIP` direkt (byte-identische
  Filter-Flags + vorgelagertes `isValidIP`). Die SSRF-/DNS-Rebinding-
  Orchestrierung (`resolveHost`, `isPubliclyRoutableHttpUrl`) bleibt bewusst
  app-lokal (`WebLinkHelper::validateUrl` prüft **nicht** die Privat/Reserviert-
  Sperre). Tests grün (`UrlSafetyTest`), Pint + PHPStan L8 grün.

### P4 · CSV-Anzeigeformat → `NumberHelper::toGermanFormat` ✅ erledigt (2026-07-01)

- **`app/Support/CsvNumber.php`** (Klasse + `CsvNumberTest` entfernt) — die fünf
  Reporting-Controller rufen `NumberHelper::toGermanFormat($v, 2,
  withThousandsSeparator: true)` direkt (byte-identisch zum bisherigen
  `number_format($float, 2, ',', '.')`). Die null/''-Guard-Semantik war an keiner
  Aufrufstelle in Gebrauch (alle übergeben Floats via `is_float`/`(float)`). Tests
  grün (Reporting-Suite inkl. CSV/XLSX, 139), Pint + PHPStan L8 grün.
- **Risiko:** Edge-Differenz — `CsvNumber` gibt für `null`/`''` einen leeren
  String, `toGermanFormat('')` gibt `'0,00'`. Null/''-Guard beibehalten.
  Konfidenz: mittel. Mehrere Reporting-Controller betroffen.

## Mit Vorsicht (Klasse B/E — Verhaltensbruch möglich)

- **`TimeExport/Profiles/GenericCsvProfile.php:48-93`** — eigener CSV-Serializer
  (quotet **immer**, BOM-Literal) → `CsvFacade::line` (quotet **bedingt**). Die
  Byte-Ausgabe ändert sich ⇒ revisionsrelevanter **`payload_hash` (SHA-256)
  bricht**, Export gilt als „geändert". Erst Quoting-Strategie angleichen + Round-
  Trip-Test, sonst bewusst belassen (D). `DatevLodasProfile` ist **bewusst
  quotefrei** → eher D.
- **`app/Support/XlsxExport.php`** — PhpSpreadsheet-Wrapper (Streaming nach
  `php://output`, AutoSize, DE-Zahlenformat). `XLSXDocumentBuilder`/-Generator
  bietet **kein** Streaming/AutoSize → kein verlustfreier Ersatz. Belassen oder
  Toolkit erweitern (C). Konfidenz: niedrig.

## Klasse C — fachneutrale Lücken im Toolkit (ergänzen statt duplizieren)

- `NumberHelper::normalizeDecimalString(): string` — string-rückgebende Dezimal-
  Normalisierung (löst die bewusst belassene float-Lücke aus dem Konsoldoc).
- `ColorHelper` (WCAG-Luminanz/Kontrast) — `app/Support/Color.php:48-79`
  (`relativeLuminance`/`contrastRatio`) ist fachneutrale Farbmathematik, fehlt im
  common-toolkit. Nur bei erwarteter Mehrfachnutzung.
- `BankHelper::normalizeIBAN(): ?string` — `app/Support/Iban.php:22-29`
  (Whitespace-Strip + Uppercase) hat kein exponiertes Pendant (nur intern in
  `checkIBAN`). ⚠ Der **Blindindex-Hash** (`hash('sha256')`) bleibt app-lokal
  (deterministisches Hex), nur die Normalisierung wäre ein Toolkit-Kandidat —
  Format **exakt** identisch halten, sonst bricht der Index.
- BOM-**Prepend**-Helfer — `StringHelper::stripBom` entfernt nur. Zwei Kopien des
  Literals `"\xEF\xBB\xBF"` (`GenericCsvProfile.php:78`, `ExportRunner.php:33`).
  Mind. App-Konstante, ggf. Toolkit-API.
- MIME→Extension-Umkehrhelfer — `LexofficeVoucherFileService.php:195-204`
  (`extensionFor`). `File::mimeType` ist nur Datei→MIME. Kandidat, kein 1:1.

## Verifiziert sauber / bewusst belassen (Klasse A/D/F — kein Handlungsbedarf)

- **E-Rechnungs-/Bestellformat-Stack komplett Klasse A:** XRechnung/ZUGFeRD
  (`ERechnungDocumentBuilder`/`ZugferdPdfGenerator`), XBestellung/Order-X/
  openTRANS/UGL (`OrderBuilder`/`Order`), Despatch Advice/UGL-Parser, DATEV
  (`DatevDocumentGenerator/-Parser` via Guard). **Keine** openTRANS-Dublette.
- **XML-Sicherheit:** kein ungehärtetes `simplexml`/`DOMDocument`-**Lesen** in
  `app/`. Einzige verbleibende DOM-Nutzung = schreibender `GaebDaXmlExporter`
  (kein XXE-Vektor, Klasse D — GAEB hat kein Toolkit-Pendant).
- **Feiertage:** `HolidayService` nutzt Yasumi (16 Bundesländer + AT + Custom-DB-
  Tabelle) — `DateHelper::getGermanHolidays` ist flach/bundesweit, **strikt
  weniger mächtig** → Migration wäre Regression. Belassen.
- **PDF-Sicht-Renderer** (Manufacturing/Protocol/Procurement/Timesheet via
  barryvdh) — bewusst barryvdh; `PDFWriterRegistry`-Wechsel bringt ohne PDF/A-3/
  XML-Bedarf keinen Mehrwert. Belassen.
- **Decimal-`str_replace`**-Stellen (Inventory/Manufacturing/Procurement),
  `Article/UnitConverter`, bcmath-Lagerbewertung, `SerialNumberGenerator`-Luhn,
  `RecurrenceGenerator` (RRULE), `EnvelopeCrypto`/`Iban::hash`, Carbon-TZ-
  Arithmetik (`WeekViewService`/`Tz`/`CarbonFmt`) — Geschäftsregeln/Krypto ohne
  verhaltensgleiches Toolkit-Pendant.
- **Offen zu klären (F):** `Manufacturing/ParameterResolver.php:108` —
  `strtotime(...) === false` als Datumsvalidierung. `DateHelper::isDate` wäre
  strenger, würde aber relative Ausdrücke ablehnen → erst Eingabe-Erwartung
  klären, nicht blind migrieren.

## Status / Reihenfolge

1. **P1 ZIP** ✅ erledigt (2026-06-30).
2. **P3 Public-IP** + **P4 CsvNumber** ✅ erledigt (2026-07-01).
3. **P2 Lexoffice-SDK** ⛔ nach Verifikation verworfen (Test-Transport-Kopplung +
   bewusste Architektur — s. o.). Nur als koordinierter Slice mit Test-Umstellung
   denkbar, bewusst zurückgestellt.
4. **Offen — Klasse-C-Toolkit-Erweiterungen** (eigene Toolkit-Repos, brauchen
   Release + `composer update`; nicht in workDiary allein machbar):
   `NumberHelper::normalizeDecimalString(): string`, `BankHelper::normalizeIBAN()`,
   BOM-Prepend-Helfer, optional `ColorHelper`, mime→ext. Bewusst nicht autonom
   ausgerollt (Paket-Releases sind außenwirksam).
5. **Offen — mit Vorsicht:** `GenericCsvProfile`/`XlsxExport` nur nach Round-Trip-/
   `payload_hash`-Verifikation.
