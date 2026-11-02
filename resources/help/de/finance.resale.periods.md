---
title: "Abrechnungsperioden"
topic: finance.resale.periods
version: 1
keywords:
    - Perioden
    - Vorschläge berechnen
    - Rechnungsentwurf
    - Sammelaktionen
    - Verzicht
    - strittig
    - Nachberechnung
    - Vorauszahlung
    - Stichtag
    - Serienlauf
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.plan
    - finance.resale.board
---

Die **Periodenseite** führt die fälligen Abrechnungsperioden aller Abos: Hier
entscheiden Sie je Periode (bestätigen, zuordnen, verzichten, strittig
stellen), starten den Vorschlagslauf, legen Rechnungsentwürfe an und öffnen
den Vorlagen-Vorschlag. Status-Kacheln und der Filter „Nur Probleme“ grenzen
die Liste ein.

**Perioden:** Die Periodenseite zeigt die fälligen Perioden aller Abos mit
Status-Kacheln (offen, berechnet, teilweise, verzichtet, strittig).
„Vorschläge berechnen" gleicht die offenen Perioden mit den Lizenzpositionen
der gespiegelten Rechnungen ab und legt Vorschläge an; Sie bestätigen sie,
ordnen von Hand eine Position zu (nur Rechnungen desselben Empfängers, nur
freie Lizenzmonate) oder verzichten mit Grund („Kulanz").
Bestätigen braucht einen offenen Vorschlag, Verzichten oder strittig stellen
eine fällige, noch nicht entschiedene Periode — Einzel- und Sammelaktion
prüfen dasselbe.
Im Zuordnen-Dialog stehen oben die Rechnungen im Zeitfenster der Periode,
darunter alle übrigen des Empfängers; das Suchfeld findet jede an ihrer Nummer,
und eine vergebene nennt, wo sie hängt. Auch Abo- und Periodenliste suchen nach
Rechnungsnummer. Entschiedene
Perioden fasst die Planung nicht mehr an; „Zurücknehmen" öffnet sie wieder.
Wird eine zugeordnete Rechnung später in Lexoffice storniert, setzt der
nächste Lauf den Bezug auf null Monate, vermerkt den Storno und öffnet die
Periode wieder, damit die Ersatzrechnung zugeordnet werden kann. Domain-Abos
deckt der Lauf über Positionen, die den Domainnamen nennen (etwa „.de-Domain
inkl. Verwaltung (lastkraft-logistik.de)“), unabhängig von der Produkt-Einstufung
des Artikels. Domains, die ein Subreseller über das Anbieterportal abrechnet,
deckt das Buchungsjournal des Domain-Plugins: je Subuser und Monat ein Beleg
„Kundennummer-MM-JJJJ“ (die Kundennummer tragen Sie auf der Seite des Subusers
ein), Empfänger ist dessen Kunde.

**Sichere und unsichere Vorschläge:** Der Vorschlagslauf (stündlich und
nach jedem Import) bestätigt sichere Treffer selbst — ein Beleg, dessen
Rechnungsdatum höchstens 14 Tage vor bis 45 Tage nach dem Periodenbeginn
liegt und der die Periode mit passender Lizenzzahl voll deckt; die
Bestätigung steht im Protokoll als „automatisch bestätigt“. Alles andere
bleibt Vorschlag und trägt „unsicher“ mit dem Grund im Tooltip. Vorschläge gelten ohne
Bestätigung: Die Periodenseite zeigt standardmäßig „Nur Probleme“ (offen,
teilweise, strittig), unbestätigte Vorschläge erreichen Sie als Stichprobe
über den Filter und den Stand im Überblick. Ein Klick auf die
Belegnummer öffnet den Beleg: Positionen, Leistungszeitraum, Rechnungstext,
Verbrauch je Position und der Vergleich zur Periode (Datum im Fenster,
Leistung, Artikel) — so sehen Sie vor der Bestätigung, ob die Rechnung zum
Zeitraum gehört.

**Sammelaktionen:** Auf der Periodenseite wählen Sie Zeilen über die
Kästchen und bestätigen alle Vorschläge auf einmal, verzichten mit einem
Grund für alle („Verzichten / strittig …“) oder lassen je
Rechnungsempfänger der Auswahl einen Entwurf anlegen. Entschiedene und
bevorstehende Perioden überspringt die Aktion und nennt die Zahl; jede
Periode bekommt denselben Protokolleintrag wie bei der Einzelaktion. Es
gibt keine Vorauswahl.

**Rechnungsentwurf:** Aus allen offenen Perioden eines Rechnungsempfängers
entsteht per Klick ein Entwurf — bei Lexoffice-Rechnungshoheit als Entwurf
in Lexoffice (nichts wird abgeschlossen; Sie prüfen und stellen dort aus),
bei lokaler Rechnungshoheit als lokaler Rechnungsentwurf mit Positionen und
vorgeschlagenen Bezügen. Eine Position je Abo und Zeitraum, Endkunde in der
Beschreibung, Menge in Monaten bei Monatsartikeln. Die Perioden merken sich
den Entwurf: ein zweiter Klick legt keinen zweiten an, sondern nennt den
ausstehenden mit Nummer und Datum; erst wenn der Entwurf zur Rechnung wurde
oder die Periode entschieden ist, sind sie wieder frei; bis dahin zeigen sie
„Im Entwurf“ statt „fehlt“ oder „Offen“. Der Dialog listet
nur Empfänger mit offenen Perioden und Verkaufspreis und nennt darunter, was
schon im Entwurf steht. Das Anlegen braucht das Recht *Rechnungsentwürfe aus
Perioden erzeugen*.
Zwei gleichzeitige Klicks (Doppelklick, zweites Browserfenster) legen
ebenfalls nur einen Entwurf an — der zweite meldet, dass gerade ein Entwurf
entsteht.

**Nachberechnung und Vorausplanung:** Der Rechnungsvorschlag bündelt
zusammenhängende offene Perioden eines Abos zu einer Position — bei
Monatsartikeln immer, bei Jahresabos mit einer Lizenz („3 Jahr“ für drei
offene Jahre); teilweise gedeckte Perioden und Lücken beginnen eine neue
Position. So lassen sich alte Jahre in einem Entwurf nachberechnen. Für
Vorauszahlungen hat der Dialog einen Stichtag (nur fällige, +1, +2, +3
Jahre): die Planung legt die Perioden bis zum Stichtag an, und der tägliche
Lauf behält Perioden mit Entwurfsstempel. Bei externer Rechnungshoheit kann
der Serienlauf monatlich am Ersten je Empfänger eine Übergabe an sein
Buchhaltungsprogramm anlegen (Schalter und Vorlauf im Dialog „Abrechnung“);
Abos mit laufender Serienvorlage lässt er aus.
