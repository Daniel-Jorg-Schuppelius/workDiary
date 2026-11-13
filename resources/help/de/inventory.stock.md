---
title: "Lagerbestände & Scannen"
topic: inventory.stock
version: 7
keywords:
    - Warenbestand
    - Bestand buchen
    - Wareneingang
    - Entnahme buchen
    - Umlagerung
    - Reservierung
    - Meldebestand
    - Mindestbestand
    - Charge sperren
    - Chargenverwaltung
    - FEFO
    - Barcode scannen
    - Bestandswert
audience: []
modules:
    - module.lager
related:
    - warehouses.manage
    - inventory.counts
    - inventory.labels
    - articles.master
---

Die Bestandsübersicht zeigt je gewähltem Lagerort die Bestände der
Varianten: verfügbare, physische und reservierte Menge, gleitender
Durchschnittspreis und Bestandswert sowie der Meldebestand. Aktive
Reservierungen und Varianten unter Meldebestand werden gesondert
ausgewiesen.

Mit Buchungsrecht erfassen Sie manuelle Bewegungen (Eingang, Entnahme,
Reservierung, Freigabe) inklusive Eigentumsart und können Mindest- und
Meldebestände je Variante und Lagerort setzen. Entnahmen ins Negative
sind nur möglich, wenn Sie sie ausdrücklich zulassen.

Chargen (Lots) führen Sie in der Chargenliste mit Restbestand; dort lassen
sich Lose teilen und zusammenführen. Die Scan-Ansicht löst einen Code
(Seriennummer, Charge, GTIN oder SKU) auf und bucht direkt eine Aktion
(Eingang, Entnahme, Umlagerung). Alle Bewegungen schreiben in das
fortlaufende Bewegungsjournal und sind nicht rückgängig zu machen;
Korrekturen erfolgen über Gegenbuchungen.

Eine Charge lässt sich in der Chargenliste **sperren** und wieder
**freigeben** — jeweils mit Begründung, beides steht im Audit-Protokoll. Der
Bestand einer gesperrten Charge bleibt im Lager, die Kommissionierliste
schlägt sie aber nicht mehr vor; teilen und zusammenführen geht erst nach der
Freigabe. Beim Zusammenführen wandert der Bestand der Quellcharge über
Gegenbuchungen (Umlagerung Abgang/Zugang) zur Zielcharge; die zusammengeführte
Charge ist danach abgeschlossen und nimmt keinen Zugang mehr an.

**Entnahme je Charge.** Hat das Lager Chargenbestand, bietet das
Buchungsformular die Auswahl „Charge (Entnahme)“: Leer gelassen wird
automatisch nach FEFO entnommen (frühestes Mindesthaltbarkeitsdatum zuerst,
wie auf der Kommissionierliste), sonst genau die gewählte Charge. Für
Zugang, Reservierung und Freigabe hat die Auswahl keine Wirkung.
Chargenpflichtige Artikel lassen sich so entnehmen, solange ihre Chargen die
Menge decken — Bestand ohne Charge wird für sie nicht entnommen; der Zugang
läuft weiter über den Wareneingang. Eine Umlagerung per Scan nimmt die
Chargen mit ins Ziel-Lager; ein gescannter Chargencode bucht genau diese
Charge.

**Sperre im Bestand.** Das Sperren einer Charge bucht ihren Bestand in den
Zustand „gesperrt“: Er bleibt im Lager, zählt aber nicht mehr als verfügbar
und erscheint in der Bestandsübersicht in der Spalte „Gesperrt“. Eine
gesperrte Charge nimmt keinen Wareneingang an; Rückbuchungen und
Wiedereinlagerungen in sie bleiben gesperrt. Die Freigabe bucht den
gesperrten Bestand zurück. Auch das Teilen einer Charge ist eine Buchung:
Die abgeteilte Menge wandert im Bewegungsjournal auf die neue Charge.

**Altbestand bereinigen.** Bis Oktober 2026 trugen Entnahmen keine Charge;
der Bestand einer Charge kann deshalb über dem liegen, was von ihr noch da
ist. Ihr Administrator prüft das mit dem Befehl `inventory:lots:repair`:
ohne Zusatz ein Probelauf mit einer Tabelle je Charge (Buchsaldo, Restmenge
der Bewertungsschichten, Differenz); mit `--apply` bucht er bei FIFO- oder
FEFO-Bewertung die Differenz nach „ohne Charge“ um, der Gesamtbestand bleibt
gleich. Bei gleitendem Durchschnitt meldet er die Charge nur, gesperrte
Chargen erst nach der Freigabe.

## Einheiten, Lagerjournal und Einstellungen

**Erfassen in einer Einheit des Artikels.** Hat ein Artikel weitere Einheiten mit Umrechnungsfaktor (etwa 1 Rolle = 100 m), bieten Buchungsformular und Scan-Ansicht neben der Menge die Auswahl **Einheit**. Gebucht wird immer in der Basiseinheit; die eingegebene Menge und die Einheit bleiben an der Bewegung stehen. Gerundet wird kaufmännisch auf vier Nachkommastellen. Eine Menge, die in der Basiseinheit null ergibt, und eine am Artikel nicht gepflegte Einheit werden abgewiesen.

**Lagerjournal.** Der Reiter **Lagerjournal** listet alle Bewegungen mit Zeitpunkt, Lagerort, Bewegungsart, Menge in Basiseinheit und der Spalte **Erfasst als** (Eingabemenge und Einheit). Suche, Lagerort und der Zeitraum oben grenzen die Liste ein.

**Mindesthaltbarkeit.** Trägt ein Artikel die Eigenschaft „Mindesthaltbarkeit erforderlich“, nimmt das Lager seinen Zugang nur über den Wareneingang der Bestellung mit Charge und Mindesthaltbarkeitsdatum an. Manueller Zugang und Scan-Eingang werden abgewiesen. Eine schon bekannte Charge bringt ihr Datum mit.

**Lager-Einstellungen.** Mit dem Recht zur Lagerkonfiguration öffnen Sie in der Bestandsübersicht die **Lager-Einstellungen**. Dort wählen Sie das **Bewertungsverfahren** der Organisation (gleitender Durchschnitt, FIFO oder FEFO) und den **Reservierungszeitpunkt** der Fertigung (bei Freigabe oder beim Start). Beim Wechsel zu FIFO oder FEFO erhält vorhandener Bestand eine Anfangsschicht zum bisherigen Durchschnittspreis. Zurück zum gleitenden Durchschnitt geht es erst, wenn kein Bestand mehr in Schichten liegt, weil der Durchschnitt in dieser Zeit nicht fortgeschrieben wurde.

## Rechte, Reservierungen und Artikelnummern

**Getrennte Rechte.** Buchen, Inventur zählen, Inventurdifferenzen freigeben, Reservierungen ändern und externe Buchungen kompensieren sind eigene Rechte. Wer bisher buchen durfte, behält Zählen, Reservieren und Kompensieren; Ihr Administrator kann die Rechte je Rolle trennen.

**Reservierung ändern.** Mit dem Recht für Reservierungen öffnet das Stiftsymbol einer aktiven Reservierung einen Dialog mit reservierter, entnommener und offener Menge. Dort setzen Sie die Priorität (kleinere Zahl = höhere Priorität) und geben eine Teilmenge frei, etwa um sie einem anderen Auftrag zuzuteilen.

**Vier-Augen-Freigabe.** In den Lager-Einstellungen lässt sich die Vier-Augen-Freigabe für Inventurdifferenzen einschalten, mit einem Grenzwert für den Bestandswert der Differenz je Zeile. Ab dem Grenzwert gibt nicht frei, wer gezählt hat. Grenzwert und Freigebende stehen im Audit-Protokoll.

**SKU und GTIN.** Die Lager-Einstellungen zeigen, wer Artikelnummer, Varianten-SKU und GTIN führt. Gewählt wird das auf der Seite „Nummernkreise“ beim Kreis „Artikel“. Führt eine Warenwirtschaft die Nummern, sind die Felder im Artikelstamm gesperrt und kommen mit dem Abgleich.

**Negativbestand mit vier Augen:** Verlangt die Organisation in den Lager-Einstellungen eine Vier-Augen-Freigabe für Entnahmen in den Negativbestand, wird eine Entnahme mit „Negativen Bestand zulassen“ zum Antrag im Genehmigungs-Eingang. Gebucht wird erst, wenn eine andere Person mit dem Recht „Negative Bestände freigeben“ zustimmt; Antragsteller und Freigebende stehen im Audit-Protokoll.

**Materialverbrauch am Stundenzettel:** Material, das mit einem lagergeführten Artikel verknüpft ist, bucht beim Erfassen am Stundenzettel oder auf „Heute“ den Abgang aus dem Lager mit der Kennzeichnung „Standard“ (bei mehreren das erste nach Name). Die Bewegung steht im Lagerjournal mit der erfassten Menge und Einheit; eine Änderung bucht die Differenz, das Löschen bucht zurück. Es gelten die Regeln dieser Seite: Negativbestand nur mit Recht und gegebenenfalls vier Augen, Chargen nach Mindesthaltbarkeit, Seriennummern nur über das Lager. Material, das Sie bisher zusätzlich hier entnommen haben, entnehmen Sie künftig nur noch am Stundenzettel — sonst zählt der Abgang doppelt.

**Reservierungen umverteilen:** Im Dialog „Reservierung ändern“ geben Sie offene Menge an eine andere Reservierung desselben Artikels, Lagers und Eigentums ab, etwa an einen Auftrag mit höherer Priorität. Eine jüngere Reservierung verdrängt eine ältere nie still; die Umverteilung steht im Audit-Protokoll, und die Materialposition eines Fertigungsauftrags folgt ihr.

**Bestandswert je Verfahren:** Durchschnittspreis und Bestandswert der
Übersicht folgen dem Bewertungsverfahren des Artikels: beim gleitenden
Durchschnitt aus dem Durchschnittsbuch, bei FIFO und FEFO aus den offenen
Bewertungsschichten (Wert der Restmengen, Durchschnitt = Wert ÷ Menge). Dieselben
Werte nutzt die ABC-Einteilung der zyklischen Inventur.
