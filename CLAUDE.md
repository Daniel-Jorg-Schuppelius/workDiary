# CLAUDE.md — workDiary

Projektweite Arbeitsanweisungen für Claude Code. Diese Datei wird bei jeder
Session geladen.

## Toolkit-first (verbindlich)

Dieses Projekt besitzt eigene, gepflegte Toolkits. **Bevor** ein Helfer,
Service, Parser, Formatter, Validator, Konverter oder Generator app-lokal
geschrieben wird, gilt:

1. **Erst die Toolkits prüfen.** Nachschlagen in
   [docs/toolkit-capability-map.md](docs/toolkit-capability-map.md)
   (öffentliche API-Oberfläche aller sechs Toolkits). Bei Unsicherheit die
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
| php-erechnung-toolkit | `ERechnungToolkit\` | XRechnung, ZUGFeRD/Factur-X, Order-X, XBestellung, openTRANS, UGL, Despatch Advice (Build/Parse/Generate/Validate) |
| php-pdf-toolkit | `PDFToolkit\` | PDF erzeugen (HTML/Text→PDF), Textextraktion + OCR, Merge/Split, ZUGFeRD-PDF/A-3 |
| datev-php-sdk | `Datev\` | DATEV Desktop API: Buchungsstapel, Stammdaten, Belege (kein EXTF-CSV-Export) |
| lexoffice-php-sdk | `Lexoffice\` | Lexoffice REST-API: Kontakte, Belege, Rechnungen, Artikel, Webhooks |

### Bewusst app-lokal (nicht erneut vorschlagen)

`NumberHelper::normalizeDecimal` (gibt float — Decimal-String-Regel),
`CurrencyHelper::normalizeAmount` (DE-Format), `CryptoHelper::secureHash`
(gesalzen/base64 statt Hex für Hash-Ketten), `CreditCardHelper`-Luhn (validiert
nur, erzeugt keine Prüfziffer). Begründungen:
[docs/toolkit-konsolidierung-2026-06.md](docs/toolkit-konsolidierung-2026-06.md).

## Verweise

- Toolkit-API-Referenz: [docs/toolkit-capability-map.md](docs/toolkit-capability-map.md)
- Migrationslog & A–F-Klassifikation: [docs/toolkit-konsolidierung-2026-06.md](docs/toolkit-konsolidierung-2026-06.md)
- Audit-Befunde (offene Migrations-/Erweiterungskandidaten): [docs/toolkit-audit-2026-06.md](docs/toolkit-audit-2026-06.md)
