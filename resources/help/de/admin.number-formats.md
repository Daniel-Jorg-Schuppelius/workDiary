---
title: "Nummernformate"
topic: admin.number-formats
version: 2
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
- Gehört die Nummer des Programms schon einem anderen Kunden, bleibt die
  bisherige Nummer, und die **Zuordnungs-Inbox** zeigt den Fall. Nummerieren
  Sie den anderen Kunden um, übernimmt der nächste Abgleich die Nummer.
- Bestehende Kunden behalten ihre Nummer.
- Führt die Buchhaltung die Stammdaten, überträgt WorkDiary nichts; die
  Nummer kommt, sobald der Kontaktabgleich den Kunden verknüpft.

Vergibt WorkDiary, prüft jede Übertragung, ob die Nummer im Programm schon
einem anderen Kontakt gehört, und bricht dann mit einer Meldung ab, statt
den fremden Kontakt zu überschreiben.

Rechnungs-, Gutschrift- und Stornonummern steuern Sie in den Einstellungen
des Lexoffice-Plugins.
