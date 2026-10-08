---
title: "Lieferantenkataloge"
topic: supplier-catalogs.overview
version: 3
keywords:
    - DATANORM
    - BMEcat
    - Preisliste importieren
    - Großhändler
    - Katalogimport
    - Einkaufspreise aktualisieren
    - Bezugsquelle
    - Rabattgruppen
    - OCI
    - IDS-Connect
    - Open Masterdata
    - Großhandelsshop
audience: []
modules:
    - module.lager
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

**DATANORM im Detail:** Unterstützt werden Version 4 und 5 — neben
Artikeldateien (DATANORM.nnn) auch Rabattgruppen (DATANORM.RAB),
Warengruppen (DATANORM.WRG) und Preisdateien (DATPREIS.nnn).
Listenpreise (Preiskennzeichen 1) werden über die Rabattgruppe zum
Netto-Einkaufspreis gerechnet; Änderungsdateien lassen den Bestand
unangetastet (Verarbeitungsmodus im Import-Dialog wählbar). Bei
kundenindividuellen Preisdateien wird der K-Kontrollsatz gegen die an
der Quelle hinterlegte Kundennummer geprüft. Der Zeichensatz ist
üblicherweise CP850. Umgekehrt exportiert die Artikelliste den eigenen
Stamm als DATANORM-Katalog oder DATPREIS-Preisdatei (auch je
B2B-Katalogzugang mit Kundenpreisen).

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

**Shop-Absprung (OCI oder IDS-Connect):** Quellen mit hinterlegtem
Shop-Zugang erlauben den direkten Absprung in den Lieferanten-Webshop.
Das Protokoll wählen Sie an der Quelle; IDS-Connect, wie es der Elektro-
und SHK-Großhandel anbietet, braucht zusätzlich Ihre Kundennummer beim
Großhändler. Der dort gefüllte Warenkorb kommt als Bestellentwurf für
das gewählte Ziel-Lager zurück. Übernommen werden Positionen, deren
Lieferanten-Artikelnummer einem Artikel zugeordnet ist; Hinweise des
Shops (etwa Lieferzeiten oder gesperrte Artikel) erscheinen als Meldung.
Meldet der Shop den Warenkorb als bereits bestellt, bestellen Sie ihn
nicht ein zweites Mal. Bei IDS-Quellen öffnet das Shop-Symbol in der
Artikelliste die Artikelseite direkt im Shop.

**Open Masterdata:** Eine Quelle im Format „Open Masterdata“ liest keine
Datei, sondern fragt den Webservice des Großhändlers je Artikel ab —
über Großhandelsnummer, GTIN oder Hersteller und Herstellernummer. Die
Abfrage zeigt Preis, Verfügbarkeit, Bilder und Dokumente; „In den
Katalog übernehmen“ legt den Katalogartikel an, der dann wie jeder
andere verknüpft oder in den Artikelstamm übernommen wird. Mit
Abrufintervall fragt workDiary Preise und Verfügbarkeit der geführten
Artikel regelmäßig nach, „Preise aktualisieren“ sofort. Token-Adresse,
Produkt-Adresse, Client-ID und Anmeldedaten vergibt der Großhändler; ob
die Kundennummer zur Anmeldung gehört, steht in dessen Zugangsbrief.

Lesen ist mit Lager-Leserechten möglich; Anlegen, Importieren und
Verknüpfen erfordern Lager-Buchungsrechte.
