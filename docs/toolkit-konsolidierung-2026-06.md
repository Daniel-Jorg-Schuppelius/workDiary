# Toolkit-Konsolidierung — Bestandsaufnahme & Migrationslog

Feature 052 (`MVP-102`). Reproduzierbare Inventarisierung app-lokaler
Hilfslogik, die durch die eigenen Toolkits abgedeckt ist, mit Fundklasse,
Entscheidung und Status. Prüfumfang: `app/` (ohne `app/Legacy/`), `routes/`,
`config/`, `database/`, `scripts/`.

> Status: **Bestandsaufnahme abgeschlossen; sichere Klasse-B-Slices migriert.**
> Encoding/BOM (Slice 1), XML-Parsing (Slice 3) und CSV-Parsing migriert.
> Dezimal (Slice 2) und Hash (Teil Slice 4) sind nach Verhaltensvergleich
> bewusst **nicht** migriert (Klasse D — siehe Begründung). JSON/ZIP sind als
> Kandidaten mit Vorbedingung dokumentiert (zurückgestellt).
>
> Leitlinie aus dem Review: **keine dünnen Funktionsfassaden** — Toolkits werden
> direkt an der Aufrufstelle genutzt.

## Installierte Toolkit-Versionen (Composer)

| Paket | Version |
| --- | --- |
| dschuppelius/php-common-toolkit | 1.11.4 |
| dschuppelius/php-config-toolkit | 0.4.5.1 |
| dschuppelius/php-error-toolkit | 2.4.2 |
| daniel-jorg-schuppelius/php-api-toolkit | 2.2.4.2 |
| daniel-jorg-schuppelius/php-pdf-toolkit | 0.12.3 |
| daniel-jorg-schuppelius/php-erechnung-toolkit | 0.4.4 |
| daniel-jorg-schuppelius/datev-php-sdk | 0.4.6 |
| daniel-jorg-schuppelius/lexoffice-php-sdk | 1.0.2 |
| daniel-jorg-schuppelius/php-financial-formats | 1.5.5 (optional, nicht in committeter composer.lock) |

## Fundklassen

A = Toolkit bereits genutzt · B = lokales Duplikat → migrieren · C = fachneutral,
fehlt im Toolkit → dort ergänzen · D = WorkDiary-Geschäftsregel (belassen) ·
E = optional/installationsabhängig (Guard erhalten) · F = unklar/riskant.

**Leitplanke:** Namensähnlichkeit rechtfertigt keine Migration. Vor dem Ersatz
werden Verhalten, Fehlersemantik, Locale, Zeitzone, Rundung, Encoding und
Rückwärtskompatibilität verglichen.

## Bestätigte Befunde

### Encoding / BOM / String (Klasse B — Slice 1, migriert)

| Datei | Fund | Ziel-API | Status |
| --- | --- | --- | --- |
| Procurement/CatalogCsvImportService | `mb_convert_encoding` + UTF-8-BOM-`preg_replace` | `StringHelper::convertToUtf8` + `::stripBom` | ✅ migriert |
| Procurement/DatanormImportService | `mb_convert_encoding` | `StringHelper::convertToUtf8` | ✅ migriert |
| Services/Help/HelpTopicLoader | UTF-8-BOM-`preg_replace` | `StringHelper::stripBom` | ✅ migriert |
| Plugins/Toggl/Sources/TogglCsvParser | UTF-8-BOM-`preg_replace` | `StringHelper::stripBom` | ✅ migriert |
| Plugins/RemoteSupport/Import/RemoteSessionSpec | UTF-8-BOM-`preg_replace` | `StringHelper::stripBom` | ✅ migriert |

Verhalten geprüft: `convertToUtf8` ist verhaltensgleich/robuster (mb→iconv-
Fallback, Encoding-Namens-Normalisierung, gibt Eingabe bei Fehlschlag zurück);
`stripBom` ist ein Superset (entfernt UTF-8/16/32-BOM, für die UTF-8-Eingaben
äquivalent). Belegt durch bestehende Tests (Catalog/Datanorm/Help/Toggl/
RemoteSupport) — grün.

### CSV-Parsing (Klasse B — migriert)

| Datei | Fund | Ziel-API | Status |
| --- | --- | --- | --- |
| Procurement/CatalogCsvImportService | manuelles `preg_split` + zeilenweises `str_getcsv` | `CSVDocumentParser::fromString` (+ `Document::getColumnNames`/`getRows`) | ✅ migriert |

Gewinn: robustes, RFC-konformes Quoting inkl. eingebetteter Trennzeichen/
Zeilenumbrüche und Konsistenzprüfung statt zeilenweisem `str_getcsv` (das
mehrzeilige gequotete Felder nicht beherrscht). Verhalten: leere Eingabe → `[]`
(Guard vor dem Parser); Kopfzeile via `getColumnNames`, ohne Kopfzeile
synthetische `col0..colN`. Toolkit-CSV bleibt **nicht** in eine neue Fassade
gekapselt — der Parser wird direkt aufgerufen (`App\Support\Toolkit\CsvFacade`
ist Altbestand; Toggl nutzt sie bereits, Klasse A). Tests grün
(CatalogCsvImport, SupplierCatalogController).

DATANORM (`DatanormImportService`) ist ein satz-/festfeldbasiertes Format, kein
Standard-CSV → bleibt app-lokal (Klasse D).

### Dezimal-Normalisierung (Klasse D — nicht migriert, begründet)

~20 Aufrufstellen `str_replace(',', '.', trim($v))` (Inventory/Manufacturing/
Procurement) sowie `AbstractEntitySpec::decimal()` / `CatalogCsvImportService`.

> **Entscheidung: belassen.** `NumberHelper::normalizeDecimal()` gibt **`float`**
> zurück und verletzt damit die harte Projektregel „Mengen/Geld immer als
> Decimal-String (bcmath), nie float". `CurrencyHelper::normalizeAmount()` gibt
> zwar `string`, aber in **DE-Format** (Komma-Dezimal) und mit Währungs-Semantik
> (stripSymbols/US-DE-Erkennung) — liefert also keinen kanonischen
> Punkt-Dezimal-String. Es gibt kein verhaltensgleiches, string-rückgebendes
> Pendant; daher keine Migration. (Wenn gewünscht: Klasse C — ein
> `NumberHelper::normalizeDecimalString(): string` im Toolkit ergänzen.)

### XML-Parsing (Klasse B — migriert)

| Datei | Fund | Ziel-API | Status |
| --- | --- | --- | --- |
| Services/Gaeb/GaebDaXmlParser | `simplexml_load_string(..., LIBXML_NONET)` | `XmlHelper::safeLoadString` (DOCTYPE-Reject + Namespace-Stripping bleiben app-seitig) | ✅ migriert |
| Procurement/BMEcatImportService | ungehärtetes `simplexml_load_string` + xpath | `XmlHelper::safeLoadString` | ✅ migriert (XXE-Härtung gewonnen) |
| Procurement/ShopinfoParser | ungehärtetes `simplexml_load_string` | `XmlHelper::safeLoadString` | ✅ migriert (XXE-Härtung gewonnen) |
| Services/Gaeb/GaebDaXmlExporter | `DOMDocument`/`createElementNS` (GAEB-Generierung) | — | Klasse D: bewusste DOM-Konstruktion, kein passendes Toolkit-Pendant |

`safeLoadString` nutzt `LIBXML_NONET` (keine Entity-Substitution) — verhaltens-
gleich zur bisherigen GAEB-Härtung und ein Sicherheits-**Gewinn** für die zuvor
ungehärteten BMEcat/Shopinfo-Parser. Tests grün (Unit/Gaeb, BMEcat, Shopinfo).

### Hash / Krypto (Klasse D/F — nicht migriert, begründet)

> **Entscheidung: belassen.** `CryptoHelper::secureHash()` liefert ein
> **gesalzenes, base64-kodiertes Array** (`hash`/`salt`/`algorithm`, Zufalls-Salt)
> — kein deterministisches Hex-`sha256`. Die App braucht an
> `Models\Concerns\HashChained` (GoBD-Hash-Kette), `Support\Iban` (Blindindex),
> `ReleaseVerifier` etc. **deterministische, ungesalzene Hex-Digests**. Ein
> Ersatz würde Hash-Ketten/Blindindizes brechen → nicht migrieren. `sodium_*`
> (Whistleblowing/Release/License) ebenso belassen, solange keine
> format-/algorithmus-identische Toolkit-API belegt ist (sicherheitskritisch).

### JSON / ZIP (Klasse B/E — zurückgestellt, mit Vorbedingung)

- `json_encode(... PRETTY_PRINT|UNESCAPED_*)` (ISMS-SBOM, Privacy, Advisory) →
  `JsonHelper::encode`/`prettyPrint` sind verfügbar, **ändern aber die
  Fehlersemantik** (`JSON_THROW_ON_ERROR` + Exception statt `false`). Kosmetisch,
  niedrige Priorität → zurückgestellt.
- `ZipArchive` (OrganizationLifecycle, SupportReportPackager mit AES-256,
  Whistleblowing, ExportAuditLog) → `ZipFile` ist Kandidat, aber
  AES-Verschlüsselung/Streaming müssen vorher als äquivalent belegt werden
  (Export-/Compliance-kritisch) → zurückgestellt (Klasse E/B).

### Belassen (Klasse D/E)

- `Services/Article/UnitConverter` — artikelbezogene Faktoren, Geschäftsregel.
- `Services/Privacy/GermanFederalStateResolver` — bewusst reduzierte PLZ-Logik.
- bcmath-Kern der Lagerbewertung — Geschäftslogik (Decimal-Strings).
- `Services/Inventory/SerialNumberGenerator` Luhn-**Prüfziffer-Erzeugung** —
  `CreditCardHelper::validateLuhn` validiert nur, erzeugt keine Prüfziffer →
  kein direktes Äquivalent (F/D).
- HTTP über Laravel `Http::*` (kein roher curl) — kein Fund.

## Migrations-Slices (Status)

1. ✅ **Encoding/BOM/String** → `StringHelper::convertToUtf8`/`stripBom` (5 Stellen).
2. ⛔ **Dezimal-Normalisierung** → Klasse D (float bzw. Währungs-Semantik;
   verletzt Decimal-String-Regel). Optional Klasse C (Toolkit ergänzen).
3. ✅ **XML-Parsing** → `XmlHelper::safeLoadString` (GAEB/BMEcat/Shopinfo;
   Exporter bleibt DOM/Klasse D).
4a. ✅ **CSV-Parsing** → `CSVDocumentParser::fromString` (CatalogCsvImport).
4b. ⛔ **Hash/Krypto** → Klasse D/F (secureHash gesalzen/base64; Hash-Ketten
   brauchen deterministisches Hex).
4c. ⏳ **JSON/ZIP** → zurückgestellt (Fehlersemantik bzw. AES-Äquivalenz prüfen).

Jede ✅-Slice ist getestet, PHPStan L8 + Pint grün.

## Dauerhafte Absicherung

- PR-Checkliste: bei neuen Parsern/Validatoren/Formattern/Helfern zuerst die
  Toolkits prüfen (Reihenfolge gemäß Feature-Doku §„Verbindliche Toolkit-
  Reihenfolge").
- `composer.lock` bleibt frei von `php-financial-formats`
  (`ComposerLockHygieneTest`).
