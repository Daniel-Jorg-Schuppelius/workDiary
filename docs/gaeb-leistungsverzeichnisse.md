# GAEB-Leistungsverzeichnisse importieren und exportieren

Bedienhandbuch für den Bau-Datenaustausch: Ausschreibungen einlesen, Angebote
kalkulieren, Aufmaße übernehmen und Dateien wieder herausgeben.

> **Modul erforderlich.** Alle Funktionen hängen am Modul **`module.bau`**
> (Plan Pro/Enterprise). Ohne freigeschaltetes Modul sind die Menüpunkte nicht
> sichtbar und die Routen antworten mit **423**.

## Was verarbeitet wird

Die Anwendung liest und schreibt alle drei GAEB-Generationen. Welche Familie
eine Datei hat, wird **am Inhalt** erkannt — die Endung darf abweichen, Dateien
aus Vergabeportalen sind häufig umbenannt.

| Familie         | Übliche Endungen | Lesen | Schreiben |
| --------------- | ---------------- | :---: | :-------: |
| GAEB DA XML 3.1–3.3 | `.X81`, `.X83`, `.X84`, `.X86`, `.X31` | ✓ | ✓ |
| GAEB 2000       | `.p81`, `.p83`, `.p84`, `.p86`         | ✓ | ✓ |
| GAEB 90         | `.d81`, `.d83`, `.d84`, `.d86`         | ✓ | ✓ |
| DA11 (Aufmaß)   | `.d11`                                 | ✓ | ✓ |

Auch die Zeichensätze älterer Dateien (CP850, CP1252) werden erkannt und
umgewandelt — Umlaute und „ß" kommen unverfälscht an.

## Ausschreibung einlesen

**Bau → Leistungsverzeichnisse → Importieren** (`/bill-of-quantities/import`).

1. Datei hochladen. Format und **Austauschphase** werden automatisch bestimmt
   und angezeigt (z. B. „X83 — Angebotsaufforderung").
2. Kunde bzw. Projekt zuordnen.
3. Import bestätigen.

Übernommen werden Gliederung (Lose, Titel, Positionen), Ordnungszahlen,
Langtexte, Mengen und Einheiten, Bieterergänzungen, Einheitspreisanteile sowie
**Katalogzuordnungen** — darunter die Kostengruppen nach DIN 276.

Das **Herkunftsformat** wird mitgespeichert. Beim späteren Export ist es die
Voreinstellung, damit die Antwortdatei in derselben Generation zurückgeht, in der
die Ausschreibung kam.

### Wiederholter Import derselben Ausschreibung

Wird eine Datei zu einem bestehenden LV erneut eingelesen (z. B. nach einer
Bieteranfrage), meldet die Anwendung Abweichungen statt stillschweigend zu
überschreiben. Positionen werden über die Ordnungszahl zugeordnet.

## Kalkulieren und ausgeben

Im LV werden Einheitspreise erfasst; Gesamtbeträge und die Angebotssumme
berechnet die Anwendung nach GAEB-Regeln (Rundung je Position, danach Summe).

**Export** über die LV-Ansicht → *Exportieren*. Vor dem Schreiben läuft ein
**Preflight**, der die Datei gegen die Anforderungen der Zielphase prüft:

| Prüfung                        | Bedeutung                                                     |
| ------------------------------ | ------------------------------------------------------------- |
| Summenabgleich                 | Positionssummen ≠ Angebotssumme → Rechenfehler oder Lücke      |
| Offene Bieterergänzungen       | Textlücken, die der Bieter füllen muss, sind noch leer         |
| Fehlende Anschrift             | Bieterangaben unvollständig (aus den Organisationsstammdaten)  |
| Fehlende Preise (X84/X86)      | Angebotsphasen ohne Einheitspreis sind unvollständig           |

Meldungen aus dem Preflight verhindern den Export nicht in jedem Fall — sie
benennen, was die Vergabestelle beanstanden wird.

### Formatwechsel kostet Felder

Wird in eine ältere Generation geschrieben, geht verloren, was diese nicht
kennt. Die Anwendung **protokolliert** das und zeigt es vor dem Download an:

| Ziel        | Fällt weg                                                    |
| ----------- | ------------------------------------------------------------ |
| GAEB 2000   | BIM-GUIDs, Teile der Katalogzuordnungen                      |
| GAEB 90     | Katalogzuordnungen (inkl. DIN 276), Teilmengen, längere Texte |

Wenn die Vergabestelle DA XML akzeptiert, ist DA XML die verlustfreie Wahl.

## Aufmaß (Mengenermittlung)

Aufmaßdateien nach **REB-VB 23.003** (Phase `X31`, GAEB-90-Endung `.d11`) werden
wie eine Ausschreibung hochgeladen und den Positionen des bestehenden LV
zugeordnet; ebenso lassen sie sich wieder ausgeben. Die Anwendung **rechnet die
Formelsätze nach** — Rechteck, Dreieck, Trapez, Zylinder- und Kegelsektoren,
Pyramidenstümpfe, Polygonzüge, Vieleckflächen aus Koordinaten, Querprofile und
stationierte Trapezprofile sowie freie Rechenausdrücke.

Winkel stehen in **Gon**, Werte in Millimetern; beides wird umgerechnet. Zeilen
mit Adressverweis bleiben für spätere Zeilen als Zwischenergebnis verfügbar.

Der Formelkatalog der REB ist **vollständig** umgesetzt und gegen die
offizielle BVBS-Prüfdatei abgeglichen — einschließlich der Querprofile und
stationierten Trapezprofile aus dem Straßenbau. Sollte eine Datei dennoch eine
unbekannte Formel enthalten, erscheint sie als Hinweis am Aufmaß und geht
**nicht** stillschweigend in die Menge ein: Ein falsch geratener Wert wäre in
der Abrechnung teurer als eine offene Zeile.

Rechenzeilen dürfen sich auf Zwischenergebnisse **anderer Positionen** beziehen.
Der Import rechnet deshalb das ganze Verzeichnis in Dateireihenfolge durch, statt
jede Position für sich zu betrachten.

Die ermittelte Menge wird als Baufortschritt der Position gebucht und ist damit
Grundlage für Abschlags- und Schlussrechnung.

Aufmaße lassen sich in beiden Fassungen ausgeben: als **X31** (DA XML) oder als
**DA11**-Datei für Partner, die noch mit der GAEB-90-Generation arbeiten. Die
DA11 begrenzt die Ordnungszahl auf neun Stellen — ist die Gliederung des LV
länger, meldet der Export das, statt die Nummer stillschweigend zu kürzen (eine
gekürzte Ordnungszahl zeigt auf die falsche Position).

## Kostengruppen nach DIN 276

Positionen tragen Katalogzuordnungen — Kostengruppe (DIN 276, Ausgaben 1981 bis
2018), Leistungsbereich, Bauteil, Kostenträger, BIM-Kennung. Eine Position kann
über **Teilmengen** auf mehrere Kostengruppen aufgeteilt sein (z. B. 60 % auf
KG 331, 40 % auf KG 333).

Diese Zuordnungen kommen beim Import mit, bleiben beim Export erhalten (außer
in GAEB 90, s. o.) und sind die Grundlage der Kostenverfolgung.

## Fehlerbilder

| Symptom | Ursache / Abhilfe |
| --- | --- |
| „Format nicht erkannt" | Datei ist keine GAEB-Datei oder liegt gezippt vor — Archiv zuerst entpacken. |
| Umlaute verstümmelt | Datei wurde von einem Fremdsystem bereits falsch umkodiert; Original neu anfordern. |
| Mengen um Faktor 1000 daneben | Aufmaßwerte stehen in Millimetern — Prüfen, ob die Fremdsoftware die Einheit korrekt gesetzt hat. |
| Export wird von der Vergabestelle abgelehnt | Preflight-Meldungen abarbeiten; im Zweifel DA XML statt GAEB 90 senden. |
| Menüpunkt fehlt | Modul `module.bau` nicht freigeschaltet (Plan Pro/Enterprise). |

## Technischer Hintergrund

Formatlogik, Formelkatalog und Schema-Validierung liegen im Paket
`daniel-jorg-schuppelius/php-erechnung-toolkit` — dessen Handbuch
(`docs/GAEB/README.md`) beschreibt Formatfamilien, Phasen und die
REB-Formelvorschriften im Detail. Die Anwendung steuert Zuordnung, Workflow,
Berechtigungen und Persistenz bei.
