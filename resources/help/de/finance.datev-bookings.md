---
title: "DATEV-Buchungsstapel"
topic: finance.datev-bookings
version: 1
audience: []
related:
    - finance.transfers
    - finance.reconciliation
    - roles.buchhaltung
    - glossary.core
---

Der **DATEV-Buchungsstapel** übergibt gestellte Rechnungen, Gutschriften und –
optional – freigegebene Spesen eines abgeschlossenen Zeitraums als prüfbare
DATEV-Datei (Format V700) an die Steuerberatung oder Buchhaltung.

Grundprinzip: WorkDiary erzeugt **keine** Buchhaltung, sondern einen sauberen
Übergabe-Stapel. Geführt eine externe Faktura-Software (DATEV oder Lexoffice)
die Rechnungen, gehören diese **nicht** in den lokalen Buchungsstapel – solche
Rechnungen werden automatisch ausgeschlossen und in der Prüfansicht
ausgewiesen.

## Vorbereitung

Vor dem ersten Export hinterlegt die Verwaltung die **Buchhaltungs-
Konfiguration** der Organisation:

- Berater- und Mandantennummer,
- Kontenrahmen (SKR03 oder SKR04) und Sachkontenlänge,
- Standard-Erlöskonto sowie ein eigenes Konto für steuerfreie/0 %-Umsätze,
- die Basis des Debitoren-Nummernkreises,
- die Zuordnung der Steuersätze (19 %, 7 %, 0 %) zu den DATEV-Buchungs-
  schlüsseln,
- das Festschreibekennzeichen (GoBD) und den Zeichensatz (üblich
  ISO-8859-1).

Eine **Debitorennummer** kann je Kunde gepflegt werden. Fehlt sie, wird sie
deterministisch aus der konfigurierten Nummernkreis-Basis und der Kundennummer
abgeleitet.

## Ablauf

1. **Stapel anlegen:** Zeitraum wählen (und optional freigegebene Spesen
   einbeziehen). Es entsteht ein **Entwurf** mit den buchungsreifen Belegen.
2. **Prüfen:** Die Vorschau zeigt je Beleg den Buchungssatz – Soll-/Haben-
   Kennzeichen, Debitoren- und Erlöskonto, Buchungsschlüssel, Belegnummer und
   Bruttobetrag – samt Summe. Fehlende Stammdaten oder Buchungsschlüssel
   erscheinen als **Warnung** beziehungsweise blockierender **Fehler**.
3. **Finalisieren:** Erst die Finalisierung erzeugt die DATEV-Datei, hält eine
   Prüfsumme (SHA-256) fest und markiert die enthaltenen Belege als übergeben.
   Ein finalisierter Stapel ist **unveränderlich**; dieselbe Rechnung kann
   nicht ein zweites Mal übergeben werden.
4. **Herunterladen:** Die erzeugte CSV-Datei lässt sich für die Kanzlei
   herunterladen.

## Hinweise

- Berücksichtigt werden gestellte und bezahlte Rechnungen mit Belegdatum im
  Zeitraum; Gutschriften werden als umgekehrte Buchung gebildet.
- Belege (PDF/Fotos) sind im MVP nicht Teil des Stapels; sie verbleiben als
  Anlage am Vorgang und werden der Kanzlei separat bereitgestellt.

## Berechtigungen

- **Stapel anlegen, finalisieren und herunterladen:** die Rolle *Buchhaltung*
  (und Administratoren).
- **Buchhaltungs-Konfiguration und Debitorennummern pflegen:**
  Administratoren.
