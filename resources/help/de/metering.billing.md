---
title: "Zählerstands-Faktura"
topic: metering.billing
version: 1
keywords:
    - Verbrauchsabrechnung
    - nutzungsbasierte Abrechnung
    - Pay per Use
    - Kopienabrechnung
    - Klickpreis
    - Kopierer abrechnen
    - Freikontingent
    - Staffelpreise
    - Grundgebühr
    - Betriebsstunden abrechnen
    - Zählerstand abrechnen
    - kWh abrechnen
audience: []
related:
    - invoices.manage
    - assets.fleet
    - contacts.manage
---

Die Zählerstands-Faktura rechnet verbrauchsabhängige Verträge ab —
Kopien am Multifunktionsgerät, Betriebsstunden, kWh.

**Vereinbarung:** Je Zähler wird festgelegt, welcher Kunde abgerechnet
wird, in welchem Turnus, mit welcher Grundgebühr, welchem Freikontingent
und welchem Preis je Einheit. Staffelpreise sind möglich; die Staffel gilt
für den Verbrauch der Periode.

**Verbrauch:** Gerechnet wird die Differenz zwischen dem letzten Stand
*vor* der Periode und dem letzten Stand *in* der Periode — nicht die Summe
der erfassten Verbräuche, die auch die Zeit davor enthielte. Fehlt ein
Stand, wird die Periode nicht abgerechnet, statt zu schätzen.

**Lauf:** Der monatliche Lauf erzeugt Rechnungsentwürfe, keine versendeten
Rechnungen — die Freigabe bleibt beim Menschen. Jede Periode wird nur einmal
abgerechnet; ein zweiter Lauf erzeugt keine Dublette.

## Staffel, Status und Projekt

**Mengenstaffel:** Im Dialog der Vereinbarung stehen Zeilen mit **ab Menge**
und **Preis je Einheit**. Die erste Stufe beginnt bei 0; leere Zeilen werden
ignoriert. Mit Staffel gilt je Stufe ihr Preis statt des Einheitspreises, und
gerechnet wird stufenweise auf den Verbrauch nach Abzug der Freimenge — eine
Einheit mehr macht die Rechnung nie billiger. Die Liste zeigt dann „Staffel“
statt des Einheitspreises.

**Status:** *Aktiv* wird abgerechnet, *Pausiert* und *Beendet* überspringt der
Lauf; **Jetzt abrechnen** steht nur bei aktiven Vereinbarungen. Nach einer
Pause rechnet der Lauf ab dem Datum **Nächste Abrechnung** nach — wer die
Pausenzeit nicht berechnen will, zieht das Datum vor dem Reaktivieren vor.

**Projekt:** Ein gewähltes Projekt wird den Rechnungsentwürfen des Laufs
zugeordnet.
