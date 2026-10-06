---
title: "Lagerbestände & Scannen"
topic: inventory.stock
version: 2
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
