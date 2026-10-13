---
title: "Nummernformate"
topic: admin.number-formats
version: 5
keywords:
    - Nummernkreis
    - Rechnungsnummer
    - Belegnummer
    - Kundennummer
    - Artikelnummer
    - fortlaufende Nummer
    - Nummernvergabe
    - Nummerierung
    - Präfix
    - Startnummer
    - Jahreszähler
    - Zähler zurücksetzen
    - Nummernvergabe Buchhaltung
    - Kundennummer aus Lexoffice
    - Entwurfsnummer
    - Trennzeichen
    - Fakturierungsweg
    - Angebotsnummer vom Programm
    - Rechnungsnummer vom Programm
    - Rechnung an Lexoffice
    - Rechnung an sevDesk
    - Rechnung an easybill
audience:
    - admin
    - teamleitung
related:
    - invoices.manage
    - admin.classifications
    - glossary.core
---

Nummernformate legen die fortlaufenden Nummernkreise je Organisation fest,
zum Beispiel für Service-Tickets, Assets, Artikel, Fertigungsaufträge,
Seriennummern, Bestellungen, Kunden, Lieferanten, Rechnungen und
Gutschriften. Für jeden Bereich wird ein eigenes Format gepflegt.

Je Format konfigurieren Sie Präfix und Präfix-Trennzeichen, optional die
Aufnahme der Jahreszahl mit Jahres-Trennzeichen, die Stellenzahl der
Auffüllung, den Startwert und ob der Zähler jährlich zurückgesetzt wird.
Eine Vorschau zeigt die jeweils nächste erzeugte Nummer.

Die Einstellungen gelten für die aktuelle Organisation; ist noch keine
Organisation zugeordnet, wird zunächst dorthin verwiesen. Ändern Sie Format
oder Startwert mit Bedacht, da bereits vergebene Nummern erhalten bleiben.

## Speichern und Trenner für alle Kreise

Alle Änderungen der Seite speichern Sie gemeinsam mit **Speichern** in der
Kopfzeile. Kreise, an denen Sie nichts ändern, bleiben auf dem Standard
(„Default“). Bei einem Eingabefehler nennt die Meldung den Kreis, und Ihre
Eingaben bleiben erhalten.

Über der Tabelle stehen die Felder **Trenner nach Präfix** und **Trenner
nach Jahr** für alle Kreise. **Für alle Kreise übernehmen** setzt beide
Trenner in jedem Kreis, den WorkDiary vergibt, und speichert die Seite. Ein
leeres Feld bedeutet: kein Trenner. Vergebene Nummern bleiben unverändert.

## Wer vergibt Kunden- und Lieferantennummern?

In der Spalte **Vergibt** wählen Sie für **Kunde** und **Lieferant**, ob
WorkDiary die Nummer vergibt oder ein angebundenes Buchhaltungsprogramm.
Zur Wahl stehen aktive Programme, die Nummern vergeben können: Lexoffice
(Kunde und Lieferant), sevDesk und easybill (Kunde). orgaMAX vergibt keine
Nummern für WorkDiary.

Vergibt ein Programm, gilt:

- Ein neuer Kunde bekommt zunächst eine Entwurfsnummer (`ENTWURF-…`). Beim
  Anlegen im Formular wird er sofort an das Programm übertragen und erhält
  dessen Nummer. Kunden aus Importen oder fehlgeschlagene Übertragungen holt
  ein stündlicher Lauf nach.
- Das Feld **Kundennummer** ist für diese Kunden gesperrt.
- Die Spalte **Nächste Nummer** zeigt die zuletzt übernommene Nummer, wie
  viele Kontakte noch auf ihre Nummer warten und bei sevDesk die nächste
  Nummer des Programms.
- Gehört die Nummer des Programms schon einem anderen Kunden, bleibt die
  bisherige Nummer, und die **Zuordnungs-Inbox** zeigt den Fall. Nummerieren
  Sie den anderen Kunden um, übernimmt der nächste Abgleich die Nummer.
- Bestehende Kunden behalten ihre Nummer.
- Führt die Buchhaltung die Stammdaten, überträgt WorkDiary nichts; die
  Nummer kommt, sobald der Kontaktabgleich den Kunden verknüpft.

Vergibt WorkDiary, prüft jede Übertragung, ob die Nummer im Programm schon
einem anderen Kontakt gehört, und bricht dann mit einer Meldung ab, statt
den fremden Kontakt zu überschreiben.

## Rechnung, Gutschrift und Storno

In der Spalte **Vergibt** wählen Sie auch für **Rechnung**, **Gutschrift**
und **Storno**, wer die Nummer vergibt: WorkDiary oder ein angebundenes
Programm – Lexoffice und easybill für alle drei, sevDesk für Rechnung und
Storno. Vergibt ein Programm, trägt ein Entwurf eine Entwurfsnummer;
**Stellen** überträgt den Beleg und übernimmt die Nummer des Programms,
Gutschrift und Storno mit Bezug auf die übertragene Rechnung. Abschlags- und
Schlussrechnungen legen Sie dann im Programm an.

Steht der **Fakturierungsweg** in den Einstellungen der Organisation auf
einem Buchhaltungsprogramm, führt dieses die Rechnungen ganz; die Spalte
**Vergibt** nennt es. Rechnen einzelne Kunden über einen anderen Weg ab,
steht darunter, für wie viele Kunden.

## Gesperrte Kreise

Vergibt ein Programm die Nummern eines Kreises, legt es auch das Format fest.
Präfix, Trenner, Jahr, Stellenzahl, Jahres-Reset und Start sind dann
gesperrt; die gespeicherten Werte gelten wieder, sobald WorkDiary vergibt.
Rechnet ein Kunde trotz externem Fakturierungsweg in WorkDiary ab, bleiben
Rechnung, Gutschrift und Storno offen, weil seine Rechnungen lokale Nummern
bekommen. Die Auswahl **Vergibt** bleibt änderbar; die Felder werden nach dem
Speichern frei.

## Angebot, Auftragsbestätigung und Lieferschein

Für **Angebot**, **Auftragsbestätigung** und **Lieferschein** wählen Sie in
der Spalte **Vergibt** Lexoffice oder easybill. Die Nummer entsteht dann beim
Versand des Angebots, beim Bestätigen der Auftragsbestätigung und beim
Ausliefern des Lieferscheins, wenn WorkDiary den Beleg an das Programm
überträgt.
