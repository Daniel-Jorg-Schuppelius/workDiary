---
title: "DATEV-Buchungsstapel"
topic: finance.datev-bookings
version: 2
audience: []
modules:
    - module.finance
schema: process
related:
    - invoices.manage
    - finance.transfers
    - finance.reconciliation
    - roles.buchhaltung
---

## Zweck und Hintergrund

Der DATEV-Buchungsstapel übergibt gestellte Rechnungen, Gutschriften
und optional freigegebene Spesen eines abgeschlossenen Zeitraums als
prüfbare DATEV-Datei (Format V700) an die Steuerberatung. Grundprinzip:
WorkDiary erzeugt **keine** Buchhaltung, sondern einen sauberen
Übergabe-Stapel. Führt eine externe Faktura-Software (DATEV oder
Lexoffice) die Rechnungen, gehören diese **nicht** in den lokalen
Stapel — sie werden automatisch ausgeschlossen und in der Prüfansicht
ausgewiesen.

## Voraussetzungen

Die Verwaltung hinterlegt einmalig die Buchhaltungs-Konfiguration der
Organisation:

- Berater- und Mandantennummer,
- Kontenrahmen (SKR03 oder SKR04) und Sachkontenlänge,
- Standard-Erlöskonto sowie ein eigenes Konto für steuerfreie
  0-%-Umsätze,
- die Basis des Debitoren-Nummernkreises,
- die Zuordnung der Steuersätze (19 %, 7 %, 0 %) zu den
  DATEV-Buchungsschlüsseln,
- Festschreibekennzeichen (GoBD) und Zeichensatz (üblich ISO-8859-1).

Eine Debitorennummer kann je Kunde gepflegt werden; fehlt sie, wird
sie deterministisch aus Nummernkreis-Basis und Kundennummer
abgeleitet. Stapel anlegen, finalisieren und herunterladen darf die
Rolle **Buchhaltung** (und Administration); die Konfiguration pflegen
Administratoren.

## Empfohlener Ablauf

1. **Stapel anlegen:** Zeitraum wählen, optional freigegebene Spesen
   einbeziehen — es entsteht ein **Entwurf** mit den buchungsreifen
   Belegen.
2. **Prüfen:** Die Vorschau zeigt je Beleg den Buchungssatz —
   Soll-/Haben-Kennzeichen, Debitoren- und Erlöskonto,
   Buchungsschlüssel, Belegnummer, Bruttobetrag — samt Summe. Fehlende
   Stammdaten erscheinen als **Warnung**, fehlende Buchungsschlüssel
   als blockierender **Fehler**.
3. **Finalisieren:** Erst jetzt entsteht die DATEV-Datei; eine
   SHA-256-Prüfsumme wird festgehalten und die Belege gelten als
   übergeben. Ein finalisierter Stapel ist **unveränderlich**.
4. **Herunterladen** und der Kanzlei bereitstellen.

![DATEV-Buchungsstapel mit Kennzahlen, Konfiguration und Stapel-Anlage](media/buchhaltung/datev-stapel.png)
*Die Stapelübersicht: Kennzahlen, Konfiguration, EXTF-Stammdaten und „Stapel anlegen“.*

## Beispiel aus der Praxis

Anfang des Monats erzeugt die Buchhaltung den Stapel für den Vormonat:
Zwei Belege warnen wegen fehlender Debitorennummer — nach der Pflege
am Kunden verschwinden die Warnungen, der Stapel wird finalisiert und
die CSV samt Prüfsumme an die Kanzlei gegeben.

## Typische Fehler

- **Dieselbe Rechnung zweimal übergeben wollen:** Finalisierte Belege
  sind gesperrt — Korrekturen laufen über Gutschrift/Korrekturbeleg im
  nächsten Stapel.
- **Warnungen ignorieren:** Fehlende Stammdaten fallen sonst erst in
  der Kanzlei auf.
- **Belege im Stapel erwarten:** PDFs/Fotos sind nicht Teil des
  Stapels; sie bleiben am Vorgang und gehen separat an die Kanzlei.

## Auswirkungen und nächste Schritte

Berücksichtigt werden gestellte und bezahlte Rechnungen mit Belegdatum
im Zeitraum; Gutschriften werden als umgekehrte Buchung gebildet. Nach
der Übergabe: Zahlungsabgleich pflegen und den nächsten Zeitraum erst
nach dessen Abschluss exportieren.
