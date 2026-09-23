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

## Verweise

Die gesamte Entwicklungs-/Architekturdoku liegt im Schwester-Repo
**WorkDiary-Architecture** (`../WorkDiary-Architecture/`, im Workspace
eingebunden): Feature-/MVP-Doku unter `features/`, Security-Doku unter
`security/`, Querschnittsdoku auf Root-Ebene.

- Toolkit-API-Referenz: [toolkit-capability-map.md](../WorkDiary-Architecture/toolkit-capability-map.md)
- Migrationslog & A–F-Klassifikation: [toolkit-konsolidierung-2026-06.md](../WorkDiary-Architecture/toolkit-konsolidierung-2026-06.md)
- Audit-Befunde (offene Migrations-/Erweiterungskandidaten): [toolkit-audit-2026-06.md](../WorkDiary-Architecture/toolkit-audit-2026-06.md)
- Audit 2026-09 (Eigenbau statt Toolkit, Gate, Toolkit-Kandidaten): [toolkit-audit-2026-09.md](../WorkDiary-Architecture/toolkit-audit-2026-09.md)
