---
title: "Abos & Lizenzen"
topic: finance.resale
version: 2
audience: []
modules:
    - module.reselling
related:
    - roles.buchhaltung
    - glossary.core
---

Das **Reselling-Register** führt jede weiterverkaufte wiederkehrende Leistung
als Abo: Microsoft-365-Lizenzen, Domains, Hosting, Postfächer, Backups oder
Sonstiges — anbieterneutral in einer Liste.

**Halter:** Jedes Abo hat genau einen Halter. Ein **Kunde** bekommt die
Rechnung selbst. Ein **Fremdkunde** ist der Endkunde eines Partners; die
Rechnung geht an den Partner, der sie weiterreicht. **Eigener Bestand**
(interne Lizenzen, eigene Domains) wird nie berechnet. Abos ohne Halter
warten auf die Zuordnung und zählen in der Kennzahl „Ohne Halter".

**Laufzeit und Perioden:** Aus Beginn, Abrechnungsintervall (jährlich oder
monatlich) und Ende plant das Register die erwarteten Abrechnungsperioden —
bis 90 Tage in die Zukunft, damit die nächste Verlängerung sichtbar ist. Ein
Abo ohne Ende verlängert sich automatisch. Ein Rest am Laufzeitende, der
kürzer als ein Monat (jährlich) bzw. fünf Tage (monatlich) ist, ist ein
Ausrichtungsstummel und keine Periode. Perioden mit einer Entscheidung
(berechnet, teilweise, verzichtet, strittig) bleiben bei jeder Neuplanung
erhalten; offene Perioden folgen Änderungen an Menge, Preis und Ende.

**Preise:** Einkauf und Verkauf je Stück und Intervall, netto. Der Artikel
liefert Produkt und Verkaufspreis für Rechnungen. Soll-Verkauf je Periode =
Menge × Verkaufspreis.

**Status:** Aktiv, Gekündigt (Ende bekannt, bis dahin werden Perioden
geplant), Abgelöst (Nachfolger bei einem anderen Anbieter) und Beendet.
Beendete und abgelöste Abos bekommen keine neuen Perioden.

**Löschen:** Ein Abo mit entschiedenen Perioden lässt sich nicht löschen —
setze es auf „beendet". Rechte: Sehen mit *Reselling-Register sehen*,
Pflegen mit *Reselling-Register pflegen*.

**Abgleich je Rechnungsempfänger:** Wenn Perioden offen bleiben und unklar
ist, ob eine Rechnung fehlt oder nur die Zuordnung, hilft der Abgleich
(Schaltfläche in der Abo-Liste, auf der Periodenseite und am Kunden). Er
stellt je Empfänger — Kunde samt seiner Endkunden — die fälligen Perioden
aller Abos den Lizenzpositionen seiner Rechnungen gegenüber, in
Lizenzmonaten je Produkt: *Soll* aus den Perioden, *Abgerechnet* aus den
Positionen. Der Befund sagt, was zu tun ist: „nur nicht zugeordnet" (freie
Positionen reichen — zuordnen), „nie abgerechnet" (mehr Perioden als
Positionen — Rechnung nachholen über den Entwurf oder Periode verzichten)
oder „ohne Periode" (mehr Positionen als Perioden — Abo fehlt im Register
oder Doppelabrechnung). Je offener Periode stehen die Positionen desselben
Produkts mit Abstand zum Periodenbeginn: freie mit Zuordnung, bereits
vergebene mit ihrem Halter zur Kontrolle. Bezugsdatum ist der
**Leistungszeitraum** der Rechnung, sonst das Rechnungsdatum; Lizenzen und
Monate stehen getrennt („5 × 12 Mon." = fünf Lizenzen für ein Jahr). Dazu
drei Fallen, die je Abo unsichtbar wären: **Rechnung an einen anderen
Kunden** (das Anbieter-Konto ist nicht der Kunde, oder der Endkunde wird
direkt statt über den Partner berechnet; erkannt am gemeinsamen
Namensbestandteil) — die Lösung ist „Halter → Kunde": das Abo wechselt zu
diesem Kunden und der Vorschlagslauf greift sofort. **Stornierte**
Rechnungen nahe am Periodenbeginn zeigen, warum eine Periode leer ist. Abos
aus dem **Posteingang**, deren Firma im Rechnungstext an diesen Empfänger
steht, warten auf ihren Halter. Trifft eine freie Position keine Periode
ihres Produkts mehr, fehlt der Vertrag im Register (der Anbieter-Export kennt
ihn nicht): die Produktzeile sagt „Position ohne Abo ab …", und „Abo aus
Position anlegen" öffnet den Abo-Dialog mit Artikel, Menge, Beginn und Preis
aus der Rechnung. Die Zuordnung darf eine Periode eines anderen Abos
desselben Empfängers treffen — nie eine Periode eines fremden Kunden.

**Generische Liste:** Neben den Anbieter-Exporten (Telekom, Quality
Hosting) nimmt der Import jede CSV- oder XLSX-Liste, deren Spalten am Namen
erkennbar sind — deutsch oder englisch: Kennung, Firma, Produkt, Menge,
Beginn, Ende, Intervall, Laufzeit, Einkaufspreis, Verkaufspreis, Anbieter,
Bestellnummer. Pflicht sind Firma, Produkt und Beginn. Ohne Kennung entsteht
sie aus Firma, Produkt und Beginn, sodass ein erneuter Import dieselben Abos
aktualisiert statt zu verdoppeln. Der Anbieter kommt aus der Spalte oder
aus dem Dialog; eine CSV-Vorlage liegt im Import-Dialog.

**Lizenzen abtreten:** Sitzen zwei Firmen im selben Haus und nutzt die
zweite einen Teil der Lizenzen eines Vertrags, trittst du diese Lizenzen am
Vertrag ab („Lizenzen abtreten": Halter, Menge, Zeitraum, Verkaufspreis).
Es entsteht ein eigenes Abo für den anderen Halter mit eigenen
Abrechnungsperioden; der Vertrag plant seine Perioden mit dem Rest. Jeder
Halter bekommt seine eigenen Rechnungen zugeordnet. Löst ein Nachfolger den
Vertrag ab (Import), läuft die Abtretung dort weiter. Ein Vertrag mit
Abtretungen lässt sich erst löschen, wenn die Abtretungen weg sind. Auch
ein Halterwechsel im Zeitverlauf — eine Firma wird aufgespalten, die neue
übernimmt die Verträge — ist eine Abtretung: alle Lizenzen für den alten
Zeitraum an den früheren Halter; der Abgleich bietet das an einer Rechnung
des anderen Kunden als „Periode an … abtreten" vorbelegt an.

**Posteingang:** Importierte Abos, deren Firma das Register noch keinem
Halter zuordnen kann, landen im Posteingang. Je Firma entscheidest du einmal:
Kunde, Endkunde eines Partners (Fremdkunde) oder eigener Bestand — der
Vorschlag kommt aus dem Namensvergleich mit Kunden und Fremdkunden. Die
Entscheidung wird gemerkt, der nächste Import ordnet dieselbe Firma sofort
zu. Zeilen, die der Import nicht verarbeiten konnte (unlesbares Datum, Menge
ohne Zahl, doppelte Kennung), stehen als Befunde am Import: die Anzahl in
der Meldung, die Einzelheiten aufklappbar in der Liste.

**Perioden:** Die Periodenseite zeigt die fälligen Perioden aller Abos mit
Status-Kacheln (offen, berechnet, teilweise, verzichtet, strittig).
„Vorschläge berechnen" gleicht die offenen Perioden mit den Lizenzpositionen
der gespiegelten Rechnungen ab und legt Vorschläge an; du bestätigst sie,
ordnest von Hand eine Position zu (nur Rechnungen desselben Empfängers, nur
freie Lizenzmonate) oder verzichtest mit Grund („Kulanz"). Entschiedene
Perioden fasst die Planung nicht mehr an; „Zurücknehmen" öffnet sie wieder.
Wird eine zugeordnete Rechnung später in Lexoffice storniert, setzt der
nächste Lauf den Bezug auf null Monate, vermerkt den Storno und öffnet die
Periode wieder, damit die Ersatzrechnung zugeordnet werden kann.

**Rechnungsentwurf:** Aus allen offenen Perioden eines Rechnungsempfängers
entsteht per Klick ein Entwurf — bei Lexoffice-Rechnungshoheit als Entwurf
in Lexoffice (nichts wird abgeschlossen; du prüfst und stellst dort aus),
bei lokaler Rechnungshoheit als lokaler Rechnungsentwurf mit Positionen und
vorgeschlagenen Bezügen. Eine Position je Abo und Zeitraum, Endkunde in der
Beschreibung, Menge in Monaten bei Monatsartikeln. Die Perioden merken sich
den Entwurf: ein zweiter Klick legt keinen zweiten an, sondern nennt den
ausstehenden mit Nummer und Datum; erst wenn der Entwurf zur Rechnung wurde
oder die Periode entschieden ist, sind sie wieder frei. Der Dialog listet
nur Empfänger mit offenen Perioden und Verkaufspreis und nennt darunter, was
schon im Entwurf steht. Das Anlegen braucht das Recht *Rechnungsentwürfe aus
Perioden erzeugen*.

**Einkaufsbelege:** Der Ist-Einkauf je Abo und Periode kommt aus drei
Quellen: (1) Anbieterrechnungen und Gutschriften als PDF (Quality Hosting,
deutsches und englisches Layout) — jede Position nennt Vertrag, Endkunde und
Laufzeit, der Betrag geht exakt an die Periode; Gutschriftpositionen ohne
Vertrag gelten der Firma. (2) Eingangsbelege aus dem Belegspiegel pro rata:
für Sammelrechnungen ohne Positionen (Telekom) nennst du den Anteil des
Anbieters und den Leistungsmonat, der Betrag wird auf alle Perioden des
Monats verteilt, gewichtet mit ihrem monatlichen Soll-Einkauf. (3) Domain-
Buchungen aus der Domainverwaltung automatisch. Beim PDF-Import prüft das
Register die Summe: weicht die Summe der Positionen von der Belegsumme ab
(etwa weil eine Seite nicht gelesen wurde), wird trotzdem importiert und
der Unterschied als Hinweis gezeigt. Die Einkaufsseite filtert nach
Anbieter, Quelle, Zeitraum und Suchbegriff; eine Zuteilung löst du immer
als Ganzes je Beleg.

**Margenbericht:** Je Produkt und je Rechnungsempfänger stehen die
fälligen Perioden des Zeitraums mit Soll-Verkauf (Menge × Verkaufspreis),
Berechnet (Nettobeträge der Rechnungsbezüge, Vorschläge eingeschlossen),
Soll-Einkauf (Anbieterpreis × Menge) und Ist-Einkauf aus den
Einkaufsbelegen. Marge = Berechnet − Einkauf; der Ist-Einkauf zählt, sobald
jede Periode der Zeile einen hat, sonst der Soll-Einkauf. Beträge werden nie
über Währungen hinweg summiert — bei mehreren Währungen gibt es je Währung
eine Zeile und einen Hinweis. Export als CSV, XLSX oder PDF; der
Rechnungsvorschlag (offene Perioden mit offenen Lizenzmonaten und Betrag)
als CSV oder XLSX.

**Preisprüfung:** Je Produkt Einkauf laut Vertrag, Katalogpreis und UVP aus
der zuletzt importierten Preisliste gegen die Verkaufspreise der Abos
(Minimum, Median, Maximum). Hinweise: „Verkauf unter Einkauf", „Verkauf
unter UVP", „Vertrag teurer als Katalog", „Kein Verkaufspreis".

**Produkt-Einstufung:** Welche Lexoffice-Artikel Abo-Produkte sind, erkennt
das Register am Namen. Je Artikel kannst du übersteuern: „Abo-Produkt"
erzwingt die Erkennung, „Nie Abo-Position" hält Dienstleistungen mit einem
Produktnamen im Text (Wartung an Exchange) aus Vorschlägen, Rechnungslisten
und „Positionen ohne Abo" heraus.

**Domains:** Jede Domain aus der Domainverwaltung wird täglich zu einem Abo
„Domain" mit Jahresintervall ab Registrierung, Einkauf = Verlängerungspreis
und Halter aus der Domainverwaltung, solange das Register keinen entschieden
hat. Der Verkaufspreis je Endung kommt aus dem Preiskatalog (Anbieter
Domain-Reselling, Produkt z. B. „.de"), der Artikel aus dem Lexoffice-Artikel
zur Endung; manuell gepflegte Preise, Artikel und Halter überlebt jeder
Lauf. Verschwundene Domains enden am Stichtag; bleibt die Domainliste eines
Laufs leer, wird nichts beendet. Domain-Abos und ihre Einkaufsbelege lassen
sich nicht von Hand anlegen — sie kommen nur über den Sync.

**Verlängerungen und Abos ohne Rechnung:** Der Bericht „Verlängerungen"
zeigt, welche Abos sich im Zeitraum verlängern oder enden (Kacheln 30, 60,
90 Tage): Verlängerung ist der Beginn der nächsten geplanten Periode, bei
gekündigten Abos zählt das Ende. „Ohne Rechnung" listet Abos, deren älteste
offene fällige Periode länger als N Tage zurückliegt (Vorgabe 60), mit
offenen Perioden und offenem Betrag. Beide als CSV oder XLSX.

**Dashboard-Kachel:** Die Kachel „Offene Abo-Perioden" (Gruppe Finanzen,
standardmäßig aus) zeigt offene Perioden mit offenem Betrag, unbestätigte
Vorschläge und Abos ohne Halter und führt auf die jeweilige Seite.

**Importbefunde:** Jeder Import (Telekom, Quality Hosting, Preisliste,
generische Liste) protokolliert Zähler und Befunde je Zeile. Datumswerte
müssen als Datum vorliegen (Excel-Datumszellen werden gelesen; „3.2026"
oder „2026" allein nicht), Mengen als Zahl, Kennungen innerhalb einer Datei
eindeutig — andernfalls wird die Zeile übersprungen und der Grund genannt.

**Aufbewahrung der Importdateien:** Hochgeladene Importdateien (sie können
Endkundennamen enthalten) bleiben 90 Tage im Ablageordner und werden dann
vom Zeitplan gelöscht; der Importdatensatz mit seinen Zählern bleibt. Von
Hand: `resale:prune-imports` (mit --days lässt sich die Frist ändern).

**Reparatur der Rechnungsbezüge:** Wurde der Belegspiegel früher mit
neuen Positions-IDs neu aufgebaut, zeigen bestätigte Bezüge ins Leere
(Bezug ohne Positionstext, Periode gilt als ungedeckt). Der Befehl
`lexoffice:repair-resale-links` hängt solche Bezüge über Rechnungsnummer und
Lizenzposition wieder an; was nicht eindeutig ist, wird nur gelistet
(--dry-run zeigt vorab, was passieren würde).
