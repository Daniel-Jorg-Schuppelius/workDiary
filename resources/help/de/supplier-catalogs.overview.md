---
title: "Lieferantenkataloge"
topic: supplier-catalogs.overview
version: 1
audience: []
related:
    - articles.master
    - procurement.orders
---

Lieferantenkataloge halten die Preislisten Ihrer Lieferanten im System —
getrennt vom eigenen Artikelstamm, aber mit ihm verknüpfbar.

**Katalogquellen:** Je Lieferant werden eine oder mehrere Quellen
angelegt. Unterstützte Formate sind DATANORM, BMEcat und CSV mit frei
zuordenbarem Spalten-Mapping (Artikelnummer, Bezeichnung, Einkaufspreis,
Währung, GTIN, Herstellernummer, Warengruppe, Verfügbarkeit, Lieferzeit).
Dateien kommen per Upload oder automatischem Remote-Abruf in wählbarem
Intervall herein; eine hochgeladene shopinfo.xml füllt Mapping,
Zeichensatz und Trennzeichen vor. Das Mapping wird an der Quelle
gespeichert und bei späteren Abrufen wiederverwendet.

**Import:** Jeder Lauf fasst zusammen, wie viele Katalogartikel neu
angelegt, aktualisiert, im Preis geändert oder als ausgelaufen markiert
wurden. Katalogartikel führen neben dem Einkaufspreis auch Staffelpreise.

**Verknüpfung (Bezugsquellen):** Katalogartikel werden manuell oder per
GTIN/EAN-Vorschlag mit eigenen Artikeln (auch Varianten) verknüpft. Erst
diese Verknüpfung stellt die Bezugsquelle her — der Artikelstamm selbst
bleibt vom Import unberührt. Verknüpfungen lassen sich jederzeit wieder
lösen.

**Preisabgleich mit Freigabe:** Ändert ein Import den Einkaufspreis eines
verknüpften Artikels, entsteht eine Kalkulationswarnung, die geprüft und
quittiert wird. Aus den Margenregeln berechnet das System
Verkaufspreisvorschläge direkt am Katalogartikel. Die Übernahme in den
Artikel erfolgt nie automatisch: Im Direktmodus übernimmt sie der
Bearbeiter ausdrücklich, im Vier-Augen-Modus entsteht stattdessen ein
Freigabe-Antrag, den eine zweite Person genehmigen oder ablehnen muss.

**OCI-Punchout:** Quellen mit hinterlegtem Shop-Zugang erlauben den
direkten Absprung in den Lieferanten-Webshop. Der dort gefüllte
Warenkorb kommt über einen zeitlich begrenzten, signierten Rücksprung
zurück und wird dem gewählten Ziel-Lager zugeordnet — als Grundlage für
die weitere Beschaffung.

Lesen ist mit Lager-Leserechten möglich; Anlegen, Importieren und
Verknüpfen erfordern Lager-Buchungsrechte.
