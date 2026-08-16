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

## Verweise

Die gesamte Entwicklungs-/Architekturdoku liegt im Schwester-Repo
**WorkDiary-Architecture** (`../WorkDiary-Architecture/`, im Workspace
eingebunden): Feature-/MVP-Doku unter `features/`, Security-Doku unter
`security/`, Querschnittsdoku auf Root-Ebene.

- Toolkit-API-Referenz: [toolkit-capability-map.md](../WorkDiary-Architecture/toolkit-capability-map.md)
- Migrationslog & A–F-Klassifikation: [toolkit-konsolidierung-2026-06.md](../WorkDiary-Architecture/toolkit-konsolidierung-2026-06.md)
- Audit-Befunde (offene Migrations-/Erweiterungskandidaten): [toolkit-audit-2026-06.md](../WorkDiary-Architecture/toolkit-audit-2026-06.md)
