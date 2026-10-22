---
title: "Gutschriftsverfahren"
topic: invoices.self-billing
version: 1
keywords:
    - Gutschrift an Lieferanten
    - Self-Billing
    - Gutschriftsverfahren
    - § 14 UStG Gutschrift
    - Abrechnung Subunternehmer
    - Typcode 389
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.vertrieb
related:
    - invoices.manage
    - finance.incoming-invoices
---
Im **Gutschriftsverfahren** stellt Ihre Organisation als Leistungsempfängerin die Rechnung über eine Leistung des Lieferanten selbst aus (§ 14 Abs. 2 Satz 2 UStG) — etwa für Subunternehmer, Provisionen oder Ankäufe. Der Beleg heißt **Gutschrift** und ist von der kaufmännischen Gutschrift zu einer eigenen Rechnung getrennt.

## Voraussetzungen

- Am Lieferanten ist das Datum der **Vereinbarung über das Gutschriftsverfahren** eingetragen (Lieferant bearbeiten). Ohne Vereinbarung steht der Lieferant nicht zur Auswahl.
- Der Lieferant hat eine **USt-IdNr.** oder **Steuernummer** und eine vollständige Anschrift.
- Die Gutschrift hat mindestens eine Position und einen **Leistungszeitraum**.

Fehlt etwas, nennt die Detailseite die offenen Punkte; **Ausstellen** erscheint erst, wenn alles vorliegt.

## Ablauf

1. **Gutschrift anlegen**: Lieferant und Leistungszeitraum wählen.
2. **Position hinzufügen**: Beschreibung, Menge, Einheit, Einzelpreis und Steuersatz. Gerechnet wird wie bei Rechnung und Angebot.
3. **Ausstellen**: vergibt die Nummer aus dem eigenen Nummernkreis (Vorgabe `GS-Jahr-Nummer`, unter Nummernkreise änderbar) und schreibt Summen und Inhalt fest.

Mit dem Ausstellen entsteht die E-Rechnung (XRechnung, Typcode 389) und landet im **Rechnungseingang**, bereits dem Lieferanten zugeordnet. Von dort geht sie wie jede Eingangsrechnung an die Buchhaltung — an DATEV oder an das angebundene Programm. Das PDF trägt die Angabe „Gutschrift“, den Lieferanten als Leistenden und den Hinweis auf die Vereinbarung.

## Korrektur und Storno

Eine ausgestellte Gutschrift ist unveränderlich. **Stornieren** geht nur, solange die Buchhaltung sie nicht übernommen hat; der zugehörige Eingang wird dann abgelehnt. Danach korrigieren Sie mit einer neuen Gutschrift mit negativen Beträgen.

Berechtigung: Ansehen mit „Rechnungen auflisten“, Anlegen mit „Rechnung anlegen“, Ausstellen und Storno mit „Rechnung ausstellen“.
