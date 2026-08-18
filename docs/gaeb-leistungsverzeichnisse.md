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

## Angebote vergleichen (Preisspiegel)

Wer Nachunternehmer anfragt, verschickt eine **X83** und bekommt mehrere **X84**
zurück. Importieren Sie jedes Angebot zum selben Leistungsverzeichnis; die
Ansicht **Preisspiegel** stellt sie dann nebeneinander.

Sie zeigt je Bieter die Angebotssumme mit Rang und den Abstand zum
nächstgünstigeren Angebot, je Position den günstigsten Preis und die Spanne
zwischen den Bietern. Eine große Spanne bei einer einzelnen Position ist oft
kein Preisproblem, sondern ein Textproblem — die Bieter haben die Leistung
unterschiedlich verstanden.

Zwei Dinge macht die Ansicht bewusst **nicht**:

- **Sie wertet nicht.** Liegt das günstigste Angebot mehr als zehn Prozent unter
  dem nächsten, wird das gekennzeichnet — die Vergabeordnung verlangt dann
  Aufklärung, nicht Ausschluss (§ 16d VOB/A, § 60 VgV).
- **Sie füllt keine Lücken.** Wer eine Position nicht angeboten hat, erscheint
  dort mit „—", nicht mit 0,00 €. Sonst gewänne er jede Position, die er
  ausgelassen hat.

## Kostengruppen nach DIN 276

Positionen tragen Katalogzuordnungen — Kostengruppe (DIN 276, Ausgaben 1981 bis
2018), Leistungsbereich, Bauteil, Kostenträger, BIM-Kennung. Eine Position kann
über **Teilmengen** auf mehrere Kostengruppen aufgeteilt sein (z. B. 60 % auf
KG 331, 40 % auf KG 333).

Diese Zuordnungen kommen beim Import mit, bleiben beim Export erhalten (außer
in GAEB 90, s. o.) und sind die Grundlage der Kostenverfolgung.

**Der Katalogstamm wird mitgeliefert:** DIN 276 in den Ausgaben **2018-12**
(dreistufig) und **2008-12** sowie die StLB-Leistungsbereiche — jeweils nur
Nummern und Kurzbezeichnungen in fünf Sprachen, kein Normtext (der ist
lizenzpflichtig).

**Beide DIN-Ausgaben stehen nebeneinander.** „310" heißt 2008 „Baugrube", 2018
„Baugrube, Erdbau"; die Ausgabe 2018 hat außerdem die 200er, 500er und 600/700
neu gegliedert. Ein laufendes Vorhaben rechnet weiter nach seiner Ausgabe ab.

### Zuordnen

**Leistungsverzeichnis → Zuordnen.** Der Filter **„Nur ohne Kostengruppe"** ist
der Arbeitsmodus. Jede Zeile zeigt die Herkunft der Zuordnung: *aus der Datei*
(darf beim Reimport überschrieben werden), *von Hand* (bleibt) oder *Vorschlag*
(von einer Regel gesetzt). Eine Nummer, die im Katalog nicht steht, wird
abgewiesen — die Auswertung summiert nach Nummern, eine falsche fiele sonst
niemandem auf.

Die Massenzuordnung über die Auswahl überschreibt auch von Hand Gesetztes; wer
sie auslöst, meint genau das.

**Aufgeteilte Positionen** erscheinen mit ihren **Teilmengen** als eigene Zeilen
darunter, jede mit eigenem Auswahlfeld. Das ist kein Beiwerk: In der Auswertung
schlägt die Zuordnung der Teilmenge die der Position — wer 300 m³ auf KG 310 und
150 m³ auf KG 320 verteilt, meint genau das.

### Zuordnungsregeln

**Bau → Zuordnungsregeln** hält fest, welche Leistung üblicherweise auf welche
Kostengruppe schlägt — über den **Leistungsbereich** (steht in der Datei,
Präfixvergleich: „013" trifft auch „013.2") oder über ein **Stichwort** im Text.
Der Regellauf **füllt nur Lücken**; greifen mehrere Regeln, gewinnt die mit dem
kleinsten Rang.

### Auswerten

**Leistungsverzeichnis → Kostengruppen** zeigt die Summen je Gruppe (erste bis
dritte Gliederungsebene umschaltbar), darunter die **Kostenverfolgung** mit
LV-Ansatz, Nachträgen, aufgemessener Leistung und Rest. Export als CSV oder
Excel.

Jede Summenzeile ist **anklickbar** und führt zu den Positionen dahinter —
gefiltert auf ihre Kostengruppe. Auf der zweiten Ebene sind das auch die
Untergruppen: „310" zeigt 311 und 312 mit.

Drei Regeln bestimmen die Verteilung:

1. **Teilmengen schlagen die Position** (300 m³ auf KG 310, 150 m³ auf KG 320).
2. **Der Abschnitt vererbt** an Positionen ohne eigene Zuordnung.
3. **„Ohne Zuordnung" steht immer in der Tabelle**, auch bei 0,00 € — dort
   landet auch der Rest unvollständiger Teilmengen.

Ein Aufmaß über der LV-Menge ergibt einen **negativen Rest**; er wird gezeigt,
nicht geglättet. Das **Budget** stammt aus der Kostenermittlung am Projekt
(s. u.); der **abgerechnete Stand** fehlt bewusst — er liegt im führenden
Faktura-System.

### Kostenermittlung (Budget und X51)

Die HOAI kennt vier Stufen — **Kostenschätzung, Kostenberechnung,
Kostenanschlag, Kostenfeststellung**. Sie lösen einander nicht ab: Ihr
Vergleich *ist* die Kostenkontrolle.

**Budget einlesen.** Eine fremde Kostenermittlung kommt als **X51** herein und
wird dem **Projekt** zugeordnet — nicht dem einzelnen Leistungsverzeichnis, denn
ermittelt wird das Bauvorhaben als Ganzes. In der Kostenverfolgung erscheint sie
danach als Budgetspalte. Hängt ein LV an keinem Projekt, bleibt die Spalte leer:
Ein fehlendes Budget ist kein Budget von null.

Verglichen wird gegen die **jüngste eingelesene** Ermittlung. Eine aus dem
eigenen Bestand erzeugte zählt dabei nicht als Budget — sonst verglichen sich die
eigenen Zahlen mit sich selbst.

**Kostenermittlung ausgeben.** Auf der Kostengruppen-Seite stehen zwei Ausgaben
bereit:

| Ausgabe | Grundlage |
| --- | --- |
| **Kostenanschlag** | LV-Ansatz + Nachträge — der Stand, zu dem vergeben wurde |
| **Kostenfeststellung** | die aufgemessene Leistung |

Kostenschätzung und Kostenberechnung erzeugt WorkDiary **nicht**: Sie stammen aus
der Planung, und die dafür nötigen Kennwerte liegen hier nicht vor. Gelesen
werden alle vier Stufen.

### Baukostenkataloge

**Bau → Baukostenkataloge** hält Kennwerte als Nachschlagewerk: was ein Bauteil
üblicherweise kostet. Sie kommen als **GAEB X50** herein und gehen ebenso wieder
hinaus.

**Der Kennwert ist eine Spanne**, keine Zahl: von, Mittel und bis stehen
nebeneinander. Der Mittelwert ist der Rechenwert; die Spanne daneben sagt, wie
sicher er ist. Fehlt der Mittelwert, wird die Mitte der Spanne genommen — fehlt
alles, bleibt das Feld leer statt auf null gesetzt.

Die **Nummernform reist mit**: X50.2 nummeriert vollständig, X50.1 in Teilen.
Der Export wählt dieselbe Form, in der der Katalog hereinkam — sonst liest die
Gegenseite andere Nummern.

### Ausgabe wechseln

**Zuordnen → Ausgabe wechseln** zeigt zuerst eine Vorschau. Umgestellt wird nur,
was eine eindeutige Entsprechung hat; alles andere bleibt stehen. Die Lücken
zeigen, wo jemand entscheiden muss — eine geratene Nummer wäre schlimmer als die
alte.

## Vergabeunterlagen als Paket einlesen

Vergabestellen liefern selten eine Einzeldatei, sondern ein **ZIP** mit
Leistungsverzeichnis, Bewerbungsbedingungen, Plänen und Vordrucken.

**Bau → Vergabeunterlagen** (`/bill-of-quantities/pakete`) nimmt das Paket
entgegen und zerlegt es:

- **GAEB-Dateien werden vorgeschlagen, nicht importiert.** Sie erscheinen in der
  Liste mit Format, Phase und Herkunftspaket; der Import startet auf Knopfdruck.
  Das ist Absicht: Ein Paket kann mehrere Lose enthalten, von denen nur eines
  bearbeitet wird.
- **Alle übrigen Dateien** gehen ins Dokumentenmanagement und hängen am
  Vergabevorgang. Wird beim Einlesen **kein Vorgang** ausgewählt, werden nur die
  GAEB-Dateien übernommen — ein Dokument ohne Bezug wäre im DMS nicht
  wiederzufinden.

Dieselbe Datei erscheint kein zweites Mal (Erkennung über den Inhalts-Hash).
Portale stellen Unterlagen bei jeder Berichtigung erneut bereit; ohne diese
Prüfung entstünde bei jedem Abruf ein neuer Vorschlag.

Der Weg steht auch dem **Cloud-Dokumenteingang** offen: Eine Ordnerregel mit dem
Ziel *Vergabeunterlagen (GAEB-Paket)* verarbeitet abgelegte Archive automatisch.
Da eine Ordnerregel keinen Vergabevorgang kennt, entstehen dort ausschließlich
GAEB-Vorschläge.

## Angebot abgeben

Aus der Vergabeakte führt **Abgabe vorbereiten** in den Assistenten. Er geht drei
Schritte: **prüfen, ausgeben, dokumentieren**.

Die Prüfung steht bewusst *vor* der Abgabe — Nachbessern ist im Vergaberecht die
Ausnahme, ein unvollständiges Angebot wird ausgeschlossen. Zwei Stufen:

| Stufe | Bedeutung | Beispiele |
| --- | --- | --- |
| **Sperre** | hält die Abgabe an | keine Go-Entscheidung, Akte bereits entschieden, offene Pflicht-Unterlagen |
| **Hinweis** | meldet, hält nicht auf | fehlende Verfahrensart, fehlende Bindefrist, fehlender Angebotswert, unbepreiste Positionen, **abgelaufene Abgabefrist** |

Dass die abgelaufene Frist **nicht sperrt**, ist Absicht: Die Einreichung wird
hier *dokumentiert*, oft am Tag nach der Abgabe. Wer einen bereits abgegebenen
Vorgang nicht mehr eintragen kann, hat keine vollständigere Akte, sondern eine
falsche.

Bei den Positionen zählt der Assistent nur, was **keinen Einheitspreis** trägt
und **nicht als „nicht angeboten" gekennzeichnet** ist. Genau diese
Unterscheidung trennt eine bewusste Nichtabgabe von einer Lücke, die zum
Ausschluss führt.

Schritt 2 verlinkt den GAEB-Export in der Phase **X84** (Angebotsabgabe) am
hängenden Leistungsverzeichnis. Schritt 3 schreibt einen versionierten Snapshot
mit SHA-256-Hash — der Nachweis, *was* eingereicht wurde.

## Submissionsergebnis festhalten

Bei einer Öffentlichen Ausschreibung werden die Angebotsendsummen im
**Eröffnungstermin verlesen**; oberschwellig nennt das Informationsschreiben
nach § 134 GWB den vorgesehenen Zuschlagsempfänger. Das ist die einzige
belastbare Quelle für den eigenen Preisabstand.

In der Vergabeakte hält der Block **Submissionsergebnis** diese Angaben fest.
Zwei Punkte zur Bedienung:

- **Das eigene Angebot gehört mit in die Liste** (Häkchen *Eigenes Angebot*).
  Ohne diese Zeile lässt sich kein Abstand ablesen.
- **Der Rang wird erfasst, nicht gerechnet.** Er kann von der Preisreihenfolge
  abweichen — Nebenangebote und Wertungspunkte verschieben ihn.

Der Bieter bleibt Freitext und wird nicht als Kunde oder Lieferant angelegt: Wer
im Eröffnungstermin verlesen wird, ist deswegen kein Geschäftspartner.

## Bekanntmachungs-Radar

**Vertrieb → Bekanntmachungs-Radar** durchsucht die öffentlichen
Bekanntmachungen des Bundes. Betrieb und Bedienung sind im Hilfethema
*Bekanntmachungs-Radar* beschrieben; für den Betrieb wichtig:

- Der Abruf läuft täglich um **05:15 Uhr** über `tenders:fetch-notices` und holt
  den **Vortag** — ein Veröffentlichungstag ist erst am Folgetag vollständig.
- Die Quelle (`oeffentlichevergabe.de`, OpenData unter CC0) braucht **keine
  Zugangsdaten**. Fällt der Abruf aus, lässt sich ein Tag nachholen:
  `php artisan tenders:fetch-notices --day=2026-08-17`.
- **Ohne Suchprofil sucht der Radar nichts.** Das ist kein Fehler, sondern die
  Voreinstellung — es gibt keinen sinnvollen Standardfilter.

## Fehlerbilder

| Symptom | Ursache / Abhilfe |
| --- | --- |
| „Format nicht erkannt" | Datei ist keine GAEB-Datei oder liegt gezippt vor — Archiv zuerst entpacken. |
| Umlaute verstümmelt | Datei wurde von einem Fremdsystem bereits falsch umkodiert; Original neu anfordern. |
| Mengen um Faktor 1000 daneben | Aufmaßwerte stehen in Millimetern — Prüfen, ob die Fremdsoftware die Einheit korrekt gesetzt hat. |
| Export wird von der Vergabestelle abgelehnt | Preflight-Meldungen abarbeiten; im Zweifel DA XML statt GAEB 90 senden. |
| Menüpunkt fehlt | Modul `module.bau` nicht freigeschaltet (Plan Pro/Enterprise). |
| Paket wird abgewiesen | Mehr als 500 Dateien im Archiv, oder das ZIP ist beschädigt. |
| Paket eingelesen, aber keine GAEB-Datei gefunden | Das Archiv enthält nur Dokumente — sichtbar an der Meldung „0 GAEB-Dateien erkannt". |
| Restdokumente fehlen nach dem Einlesen | Beim Einlesen war kein Vergabevorgang ausgewählt; nur GAEB wird dann übernommen. |
| Radar bleibt leer | Kein aktives Suchprofil, oder der Tagesabruf lief noch nicht (Cron 05:15 Uhr). |
| Kostengruppen-Auswertung zeigt alles unter „ohne Zuordnung" | Das LV führt keinen Kostengruppenkatalog im Kopf, oder die Positionen tragen keine Zuordnung. |
| Kostengruppe lässt sich nicht auswählen | Zum Katalogtyp der Datei ist kein Stamm hinterlegt — die Nummer lässt sich dann nur frei eintragen. |
| Regellauf ändert nichts | Er füllt nur Lücken; vorhandene Zuordnungen bleiben unangetastet. |
| Budgetspalte fehlt | Das LV hängt an keinem Projekt, oder für das Projekt wurde noch keine Kostenermittlung eingelesen. |
| Kostenschätzung lässt sich nicht ausgeben | Beabsichtigt — erzeugt werden nur Kostenanschlag und Kostenfeststellung. |

## Technischer Hintergrund

Formatlogik, Formelkatalog und Schema-Validierung liegen im Paket
`daniel-jorg-schuppelius/php-erechnung-toolkit` — dessen Handbuch
(`docs/GAEB/README.md`) beschreibt Formatfamilien, Phasen und die
REB-Formelvorschriften im Detail. Die Anwendung steuert Zuordnung, Workflow,
Berechtigungen und Persistenz bei.
