---
title: "Artikelstamm"
topic: articles.master
version: 5
keywords:
    - Artikelstammdaten
    - Artikel anlegen
    - Artikelverwaltung
    - Produktkatalog
    - Materialstamm
    - Artikelnummer
    - Varianten
    - SKU
    - EAN
    - GTIN
    - Mengeneinheiten
    - Zolltarifnummer
audience: []
modules:
    - module.lager
related:
    - articles.lexoffice
    - materials.manage
    - inventory.stock
    - warehouses.manage
---

Der Artikelstamm ist das zentrale Verzeichnis aller Produkte, Materialien
und Leistungen des Mandanten. Er bildet die Basis für Lager,
Beschaffung, Fertigung und Verkauf.

Ein Artikel trägt Stammdaten wie Artikelnummer, Art, Status,
Basiseinheit, GTIN sowie Standard-Einkaufs- und -Verkaufspreis. Auf der
Detailansicht pflegen Sie darüber hinaus Optionen mit Optionswerten
(z. B. Größe, Farbe), zusätzliche Einheiten mit Umrechnungsfaktor zur
Basiseinheit und konkrete Varianten. Eine Variante entsteht aus einer
Kombination von Optionswerten und erhält automatisch eine SKU.

Anlegen und Bearbeiten laufen als Dialog. Artikel und Varianten lassen
sich stilllegen (Status „Retired"), statt sie zu löschen. Ein Löschen
ist nur möglich, solange keine abhängigen Daten bestehen; andernfalls
wird es blockiert. Legen Sie Varianten erst an, wenn Optionen und
Optionswerte vollständig sind, da sich Varianten daraus zusammensetzen.

Für Zollpapiere tragen Artikel unter „Zoll und Ausfuhr“ die Zolltarifnummer
(6 bis 11 Ziffern, Leerzeichen und Punkte werden entfernt), das Ursprungsland
als ISO-Code und das Nettogewicht je Einheit.

## Lager und Bewertung

Unter „Lager und Bewertung“ legen Sie im Artikeldialog ein eigenes **Bewertungsverfahren** fest; ohne Auswahl gilt das Verfahren der Organisation. Der Wechsel zu FIFO oder FEFO legt für vorhandenen Bestand eine Anfangsschicht an; zurück zum gleitenden Durchschnitt geht es erst, wenn kein Bestand des Artikels mehr in Schichten liegt.

Mit einem **Seriennummern-Präfix** zählt der Artikel eigene Seriennummern (Präfix und laufende Nummer, auf Wunsch mit Prüfziffer); ohne Präfix gilt der Nummernkreis der Organisation.

Die zusätzlichen **Einheiten** wirken in Lagerbuchung, Scan, Bestellung und Wareneingang: Die Menge lässt sich in der Einheit erfassen, gebucht wird in der Basiseinheit. Die Eigenschaft „Mindesthaltbarkeit erforderlich“ verlangt beim Wareneingang Charge und Mindesthaltbarkeitsdatum.

Führt eine Warenwirtschaft die Artikelnummern (Seite „Nummernkreise“, Kreis „Artikel“), sind Artikelnummer und GTIN im Dialog nur lesbar; neue Artikel und Varianten erhalten ihre Nummer mit dem Abgleich.

**Steuerklasse:** Im Artikelformular halten Sie die Steuerklasse fest (Regelsatz, ermäßigt, erhöht). Sie wird mit dem Artikel gespeichert, beim Import übernommen und bei Dubletten und Zusammenführungen verglichen; den Steuersatz eines Belegs setzt weiter die Position.

## Fertigung

Unter „Fertigung“ wählen Sie den **Standard-Arbeitsplan** des Artikels: eine
veröffentlichte Version einer aktiven Prozedurvorlage. Fertigungsaufträge und
die Fertigungsplanung stellen den Artikel danach her, solange ein Auftrag keine
eigene Version wählt; auch die Bedarfsplanung liest ihn. Der Abschnitt erscheint,
sobald es veröffentlichte Prozedurversionen gibt.
