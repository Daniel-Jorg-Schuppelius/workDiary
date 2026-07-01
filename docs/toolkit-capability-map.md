# Toolkit Capability-Map

Referenz der **öffentlichen API-Oberfläche** der eigenen Toolkits. Zweck: vor
dem Schreiben eines Helfers/Parsers/Formatters/Validators hier nachsehen, ob es
die Funktion schon gibt — statt app-lokal zu duplizieren (vgl. `CLAUDE.md` →
„Toolkit-first" und `docs/toolkit-konsolidierung-2026-06.md`).

> Diese Map ist eine **Landkarte, kein Vertrag**: Signaturen können sich
> zwischen Releases ändern. Vor der Nutzung die Klasse kurz gegenlesen
> (`vendor/<paket>/src/...`). Stand der Kartierung: 2026-06-30.

Installierte Versionen siehe `docs/toolkit-konsolidierung-2026-06.md`.
`php-financial-formats` ist **bewusst nicht** in `composer.lock`
(`ComposerLockHygieneTest`) — nicht ungefragt hinzufügen.

---

## 1. php-common-toolkit — `CommonToolkit\` (`dschuppelius/php-common-toolkit`)

Generische, fachneutrale Bausteine. **Erste Anlaufstelle** für String/Zahl/
Datum/Validierung/Dateisystem/CSV-XML-XLSX.

### Daten-Helfer (`CommonToolkit\Helper\Data\…`)

- **`StringHelper`** — Encoding/Transliteration/Case/Suche/Kürzen:
  `isNullOrEmpty`, `convertEncoding`, `convertToUtf8`, `stripBom`,
  `detectLegacyEncoding`, `toAscii`, `truncate`, `containsKeyword(…, SearchMode)`,
  `slugify`, `camelToSnake`/`snakeToCamel`/`kebabToCamel`/`camelToKebab`,
  `mask`/`maskEmail`, `normalizeWhitespace`, `splitFixedWidth`,
  `htmlEntitiesToText`/`textToHtmlEntities`, `parseToTypedValue`.
- **`NumberHelper`** — Formatierung/Parsing/BC-Math:
  `formatBytes`/`parseByteString`, `toGermanFormat`/`toUSFormat`,
  `normalizeDecimal` (⚠ gibt `float` — verletzt Decimal-String-Regel, s. u.),
  `percentage`, `detectNumberFormat`, `formatCurrency`, `toWords`,
  BC-Math: `addPrecise`/`subtractPrecise`/`multiplyPrecise`/`dividePrecise`/
  `sumPrecise`/`modPrecise`/`powPrecise`/`sqrtPrecise`.
- **`DateHelper`** — Parsing/Berechnung/Feiertage/Arbeitstage:
  `isDate`, `parseFlexible`, `parseDateTime(…, CountryCode)`, `germanToIso`/
  `isoToGerman`, `fromExcelSerial`/`excelCellToGerman`, `formatDate`,
  `addWorkingDays`/`getWorkingDays`/`isWorkingDay`/`getNextWorkingDay`,
  `getGermanHolidays`/`isGermanHoliday`/`getEasterDate`, `startOf…`/`endOf…`,
  `getQuarter`, `getAge`, `humanDiff`.
- **`Validator`** — Finanzdaten: `isIBAN`/`isRealIBAN`/`isMaskedIBAN`, `isBIC`,
  `isBankCode`, `isAccountNumber`, `isVatId`/`isValidVatId`, `isAmount`, `isDate`.
- **`BankHelper`** — IBAN/BIC/BLZ: `validateIBAN`, `checkIBAN`, `extractIBANs`,
  `getBankNameByIBAN`/`getBankNameByBLZ`, `getBICByIBAN`, `getCountryFromIBAN`.
- **`CurrencyHelper`** — `format`, `parse`, `detectCurrencyFormat`, `isCurrency`,
  `equals` (⚠ `normalizeAmount` liefert DE-Format-String, kein Punkt-Dezimal).
- **`VatNumberHelper`** — `isVatId`, `validateVatId`, `extractVatIds`,
  `formatVatId`, `getCountryFromVatId`.
- **`EmailHelper`** — `isValid`, `isDisposable`, `extractFromText`, `normalize`,
  `getDomain`/`getLocalPart`.
- **`PhoneNumberHelper`** — `parse`, `format`, `isValid` (länderabhängig).
- **`PostalCodeHelper`** — `isValid`, `format`, `getCountryFromPostalCode`.
- **`CreditCardHelper`** — `isValid` (Luhn), `getType`, `mask` (⚠ validiert nur,
  erzeugt keine Prüfziffer).
- **`TaxNumberHelper`**, **`CompanyIdHelper`** (HRB/GLN/DUNS),
  **`CreditorIdHelper`** (SEPA), **`UnitConversionHelper`** (Zeit/Gewicht/Volumen/
  Länge/Fläche/Geschwindigkeit/Druck/Energie/…).
- **`CryptoHelper`** — ⚠ `secureHash` liefert gesalzenes base64-Array, **kein**
  deterministisches Hex (für Hash-Ketten/Blindindex ungeeignet — s. Konsoldoc).
- **`JsonHelper`** — `isValid`, `decode`/`encode` (⚠ `JSON_THROW_ON_ERROR`),
  `prettyPrint`/`minify`, `extractPath`, `merge`, `maskSensitiveData`.
- **`XmlHelper`** — `safeLoadString` (XXE-gehärtet, **bevorzugt** vor
  `simplexml_load_string`), `isValid`, `escapeSpecialChars`, `extractCDATA`.
- **`IPHelper`**, **`WebLinkHelper`**, **`SecurityHelper`** (sanitize/Token).

### Dateisystem (`CommonToolkit\Helper\FileSystem\…`)

- **`File`** — `read`/`write`/`append`/`copy`/`delete`, `size`, `mimeType`,
  `detectEncoding`, `md5`/`sha256`, `readLines` (Generator).
- **`Folder`** — `create`/`delete`/`copy`/`move`/`get`/`size`, `isAbsolutePath`.
- **`Files`** — `find`, `glob`, `filter`.
- **FileTypes**: `CsvFile` (`detectDelimiter`/`detectEncoding`), `JsonFile`,
  `XmlFile` (`validateXSD`), `ZipFile` (`create`/`extract`/`addFile`),
  `PdfFile` (`getMetadata`/`getPageCount`/`encrypt`/`decrypt`).

### Parser/Builder (Document-Modell)

- **CSV**: `Parsers\CSVDocumentParser` (`fromString`/`fromFile`/`fromFileRange`/
  `streamRows`/`processBatches`/`detectDelimiter`), `Builders\CSVDocumentBuilder`.
  Entities `CSV\Document` (`toString`/`toFile`), `HeaderLine`/`DataLine`.
  Zeilen-Helfer: `Helper\Data\CSV\StringHelper` (`detectDelimiter`,
  `splitCsvByLogicalLine`, `parseLineToFields`, `stripExcelTextPrefix`; **NEU,
  release-pending**: `encodeField`/`encodeLine` — RFC-4180-Ausgabe-Encoder mit
  optionalem `forceEnclosure`, Pendant zum App-`CsvFacade::line`).
- **XML**: `Parsers\XmlDocumentParser`, `Builders\XmlDocumentBuilder`,
  Entities `XML\Document`/`Element`.
- **XLSX**: `Parsers\XLSXDocumentParser`, `Builders\XLSXDocumentBuilder`,
  Entities `XLSX\Document`/`Sheet`/`Cell`.
- **HTML**: `Parsers\HTMLDocumentParser`, `Builders\HTMLDocumentBuilder`.

### Plattform/Shell

- **`Helper\Platform`** — `isWindows`/`isLinux`/`isMac`, `getOS`, `getEnv`.
- **`Helper\Shell`** — `execute`, `isCommandAvailable`, `escape`.
- **`Helper\Java`** — `execute`, `isAvailable`.

### Enums (Auswahl, type-safe statt String-Konstanten)

`CurrencyCode` (120+), `CountryCode` (250+), `LanguageCode`, `SubdivisionCode`,
`Weekday`, `Month`, `CaseType`, `SearchMode`, `DateTimeFormat`/`-Group`,
`MetricPrefix` + 15+ Einheiten-Enums (`LengthUnit`/`WeightUnit`/`VolumeUnit`/…).

---

## 2. php-financial-formats — `CommonToolkit\FinancialFormats\` (`daniel-jorg-schuppelius/php-financial-formats`)

Bankformate & Buchhaltungs-Austauschformate. ⚠ **Optional, nicht in
`composer.lock`** — nur nutzen, wenn bewusst beauftragt.

- **Parser** (`Parsers\…`): `ISO20022\CamtParser` (CAMT.052/053/054/055/056/029,
  `parse`/`parseFile`/`parseCamt053`/`parseCamt052All`/`countStatements`/
  `getStatementSummary`), `ISO20022\PainParser` (pain.001–018),
  `Swift\Mt940DocumentParser` (`fromFile`/`fromFileMultiple`/`parse` + OFX),
  `Swift\Mt10xParser`, `DATEV\DatevDocumentParser` (V700 CSV),
  `DATEV\BankTransactionParser` (DATEV ASCII), `QIF\QifDocumentParser`,
  `QXF\QxfDocumentParser`, `Lexoffice\…`, `Addison\…`, `Sage\…`.
- **Validator**: `Helper\Data\CamtValidator` (`validate`/`detectType`),
  `Helper\Data\PainValidator` (XSD gegen ISO-20022-Schemata).
- **Helper**: `BankStatementSummarizer` (`fromMt940`/`fromCamt`/`fromCamt053`/
  `fromBankTransaction` → `BankStatementSummary`, BC-Math),
  `Mt940ProfileDetector` (`detect`).
- **Generator**: `Camt053Generator`/`Camt052Generator`/`Camt054Generator`,
  `Swift\SwiftMessageGenerator`, `DATEV\DatevDocumentGenerator`.
- **Converter** (Format↔Format, ~18): `Camt053ToBankTransactionConverter`,
  `Mt940ToBankTransactionConverter`, `BankTransactionToMt940Converter`,
  `BankTransactionToCamt053Converter`, `QifToQxfConverter`, … (DATEV/QXF/OFX/
  Addison/Lexoffice/Sage).
- **Builder**: `DATEV\BankTransactionBuilder` (Auto-Formatting/Feldbreiten),
  `Mt\Mt940DocumentBuilder`, CAMT/QIF/QXF/OFX-Builder.
- **Entities**: CAMT `Type53/52/54\Document`+`Transaction`, `Balance`
  (`getSignedAmount`), `PartyIdentification`; DATEV `BankTransaction`/`Document`;
  Swift `Mt9xx\…`; `Statement\BankStatementSummary` (readonly VO).
- **Enums**: `CamtType`/`CamtVersion`/`BalanceType`/`EntryStatus`, `PainType`/
  `PaymentMethod`, `Mt940Profile`/`Mt940OutputFormat`, DATEV-Headerfelder.

---

## 3. php-erechnung-toolkit — `ERechnungToolkit\` (`daniel-jorg-schuppelius/php-erechnung-toolkit`)

E-Rechnung & strukturierte Geschäftsdokumente (XRechnung, ZUGFeRD/Factur-X,
Order-X, XBestellung, openTRANS, UGL, Despatch Advice).

- **Builders** (Fluent): `ERechnungDocumentBuilder` (`create`/`xrechnung`/
  `zugferd(…, ERechnungProfile)`/`creditNote`/`withSeller`/`withBuyer`/`addLine`/
  `addDiscount`/`withPaymentMeansCode`/`build(): Document`),
  `OrderBuilder` (`xbestellung`/…/`build(): Order`), `DespatchAdviceBuilder`.
- **Parsers**: `ERechnungParser` (`parse`/`parseFile` UBL+CII), `OrderParser`,
  `OrderXParser` (Order-X CII), `OpenTransOrderParser`, `UglParser`/
  `UglInvoiceParser`, `DespatchAdviceParser`, `ZugferdPdfParser`
  (`isZugferdPdf`/`extractXml`/`parseFile` — XML aus PDF/A-3).
- **Generators**: `ERechnungGenerator` (`generateUbl`/`generateCii`),
  `OrderGenerator`, `OrderXGenerator`, `OpenTransOrderGenerator`, `UglGenerator`,
  `ZugferdPdfGenerator` (`generate`/`generateToFile` PDF/A-3, braucht pdf-toolkit),
  `InvoiceHtmlGenerator`.
- **Validators**: `UblSchemaValidator` (XSD), `KositValidator` (Geschäftsregeln
  EN16931/XRechnung/Peppol via KoSIT-JAR → `ValidationResult`).
- **Entities**: `Document`/`Order`/`DespatchAdvice`/`UglInvoice`, `Party`,
  `PostalAddress`, `InvoiceLine`/`OrderLine`, `AllowanceCharge` (statische
  `discount()`/`shipping()`/…), `TaxTotal`/`TaxSubtotal`, `MonetaryTotal`,
  `PaymentTerms` (`net30()`/`withSkonto()`/`sepaDirectDebit()`).
- **Enums**: `ERechnungProfile` (MINIMUM…XRECHNUNG_EXTENSION), `OrderProfile`,
  `OrderXProfile`, `InvoiceType`, `TaxCategory` (UNTDID 5305), `PaymentMeansCode`,
  `UnitCode` (UN/ECE), `AllowanceChargeReasonCode`.

---

## 4. php-pdf-toolkit — `PDFToolkit\` (`daniel-jorg-schuppelius/php-pdf-toolkit`)

PDF-Erstellung (HTML/Text→PDF), Textextraktion (inkl. OCR), Manipulation.

- **Registries** (Singleton, Auto-Priorisierung — **bevorzugter Einstieg**):
  `Registries\PDFWriterRegistry` (`getInstance`, `createPdf`/`createPdfString`,
  `htmlToPdf`/`textToPdf`/`fileToPdf`, `getByType(PDFWriterType)`),
  `Registries\PDFReaderRegistry` (`extractText`, `getTextAlternatives`).
- **Entities**: `PDFContent` (`fromHtml`/`fromText`/`fromFile`/`fromDocument`/
  `fromBuilder`), `PDFDocument` (`hasText`/`getTextOrDefault`/`renderAsHtml`),
  `PageSize` (`fromMm`/`fromFormat`/`detectFormat`/`isLandscape`).
- **Writer**: `DompdfWriter`, `TcpdfWriter`, `WkhtmltopdfWriter`,
  `ZugferdWriter` (PDF/A-3 + XML-Einbettung, Levels MINIMUM…EXTENDED).
- **Reader**: `PDFToTextReader`, `PDFBoxReader`, `TesseractReader`,
  `OcrMyPDFReader`, `ZugferdReader`.
- **Helper**: `PDFHelper` (`isValidPdf`/`getMetadata`/`getPageCount`/
  `hasEmbeddedText`/`isLikelyScanned`/`getPageSize`/`detectFormat`/
  `renderPageToImage`/`renderPageToBase64`), `PDFMergeHelper` (`merge`),
  `PDFSplitHelper` (`extractPages`/`splitToPages`/`splitByPageCount`),
  `PDFTextProvider` (`rawText`/`layoutText`/`ocrText`/`rowAligned` lazy+cached),
  `PDFCropHelper`.
- **Enums**: `PDFWriterType` (Dompdf/Zugferd/Tcpdf/Wkhtmltopdf),
  `PDFReaderType`, `PaperFormat` (A0–A8/Letter/Legal/…), `PDFTextVariant`.

> Hinweis: workDiary nutzt aktuell zusätzlich `barryvdh/laravel-dompdf` direkt.
> Bei neuer PDF-Erzeugung prüfen, ob `PDFWriterRegistry` passt (s. Audit).

---

## 5. datev-php-sdk — `Datev\` (`daniel-jorg-schuppelius/datev-php-sdk`)

DATEV Desktop API (localhost). ⚠ **Kein** eingebauter EXTF/CSV-Export —
EXTF-Parsing/-Serialisierung bleibt App-Verantwortung (bzw. financial-formats).

- **Client**: `API\Desktop\Client` (Bearer/Auth, baseUrl 127.0.0.1:58452).
- **Accounting**: `Endpoints\Accounting\AccountPostingsEndpoint`
  (`setClientId`/`setFiscalYearId`/`search`/`getById`),
  `AccountingRecordsEndpoint`, `PostingProposalsCashRegisterBatchEndpoint`.
  Entities `AccountPosting`, `Records\Record`, `Debitors\Debitor`,
  `Creditors\Creditor`.
- **ClientMasterData** (60+ Endpoints): `ClientsEndpoint`, `AddresseesEndpoint`,
  `BanksEndpoint`, `EmployeesEndpoint`, `TaxAuthoritiesEndpoint`, …
- **DocumentManagement** (50+): `DocumentsEndpoint`, `DocumentFilesEndpoint`,
  `FoldersEndpoint`.
- **Weitere Module**: Payroll, OrderManagement, Law, PublicSector, IAM,
  Diagnostics.
- **Enums**: `DebitCredit`, `TaxationMethod`, `PersonType`, `LegalFormType`,
  `PaymentMethod`, `Country`, …
- **Common-IDs/VO**: `ClientID`/`NaturalPersonID`/`OrganizationID`, `EuVatID`,
  `EmailAddress`.

---

## 6. lexoffice-php-sdk — `Lexoffice\` (`daniel-jorg-schuppelius/lexoffice-php-sdk`)

Lexoffice/lexware REST-API.

- **Client**: `API\Client` (`__construct(apiKey, baseUrl, logger, sleepAfterRequest)`).
- **Endpoints**: `ContactsEndpoint` (`create`/`get`/`update`/`search`),
  `VouchersEndpoint`/`VoucherListEndpoint`, `ArticlesEndpoint` (CRUD),
  `Documents\InvoicesEndpoint` (`create(…, finalize)`/`pursue`/`render`),
  `Documents\{CreditNotes,Quotations,DeliveryNotes,OrderConfirmations,
  DownPaymentInvoices,Dunnings,RecurringTemplates}Endpoint`,
  `PaymentsEndpoint`/`PaymentConditionsEndpoint`, `FilesEndpoint`
  (`upload`/`download`), `Countries`/`PostingCategories`/`PrintLayouts`/
  `ProfileEndpoint`, `EventSubscriptionsEndpoint` (Webhooks).
- **Entities**: `Contacts\Contact`/`Company`/`Person`/`Address(es)`/`Roles`,
  `Vouchers\Voucher`/`VoucherItem`, `Documents\…\{Invoice,CreditNote,Quotation,…}`
  + shared `LineItem`/`TaxConditions`/`TotalPrice`/`PaymentConditions`,
  `Articles\Article`/`Price`, `Payments\Payment`, `Profile\Profile`,
  `Files\File`.
- **Enums**: `VoucherStatus`/`VoucherType`, `PaymentStatus`/`PaymentItemType`,
  `TaxType`/`TaxClassification`, `ArticleType`, `EventType`, `ExecutionInterval`,
  `CountryCode`, `Language`.
- **Contracts**: `ClassicEndpointInterface`, `DocumentEndpointInterface`
  (`pursue`/`render`), `SearchableEndpointInterface`, `ListableEndpointInterface`.

---

## Bekannte Nicht-Migrierbarkeiten (aus dem Konsolidierungs-Review)

- `NumberHelper::normalizeDecimal` → **float** (verletzt Decimal-String-Regel).
- `CurrencyHelper::normalizeAmount` → DE-Format-String, kein Punkt-Dezimal.
- `CryptoHelper::secureHash` → gesalzen/base64, kein deterministisches Hex
  (Hash-Ketten/Blindindex/ReleaseVerifier brauchen Hex).
- `CreditCardHelper` Luhn **validiert nur**, erzeugt keine Prüfziffer
  (SerialNumberGenerator braucht Erzeugung).

Diese sind bewusst app-lokal — nicht erneut als Migration vorschlagen, sondern
ggf. als **Toolkit-Erweiterung (Klasse C)** behandeln.
