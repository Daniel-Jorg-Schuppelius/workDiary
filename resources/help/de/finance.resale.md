---
title: "Abos & Lizenzen"
topic: finance.resale
version: 10
keywords:
    - Import-Vorschau
    - Sammelaktionen
    - Abonnement
    - Subscription
    - wiederkehrende Leistungen
    - Lizenzweiterverkauf
    - Abo abrechnen
    - Abrechnungsperioden
    - Verlängerungen
    - Kündigungsfrist
    - Marge berechnen
    - Abos importieren
    - Hosting abrechnen
    - Lexoffice
    - Telekom
    - Quality Hosting
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
In der Abo-Liste filtern Sie „Nur ohne Halter“, wählen mehrere Abos aus und
ordnen sie mit „Halter zuordnen“ in einem Schritt einem Kunden, einem
Endkunden oder dem eigenen Bestand zu; ein einzelnes Abo geht über das Symbol
in der Zeile. Domains bekommen den Halter dabei auch in der Domainverwaltung,
und die Inbox führt Abos ohne Firmenname dorthin.

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
setzen Sie es auf „beendet". Rechte: Sehen mit *Reselling-Register sehen*,
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
zweite einen Teil der Lizenzen eines Vertrags, treten Sie diese Lizenzen am
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
Halter zuordnen kann, landen im Posteingang. Je Firma entscheiden Sie einmal:
Kunde, Endkunde eines Partners (Fremdkunde) oder eigener Bestand — der
Vorschlag kommt aus dem Namensvergleich mit Kunden und Fremdkunden. Die
Entscheidung wird gemerkt, der nächste Import ordnet dieselbe Firma sofort
zu. Zeilen, die der Import nicht verarbeiten konnte (unlesbares Datum, Menge
ohne Zahl, doppelte Kennung), stehen als Befunde am Import: die Anzahl in
der Meldung, die Einzelheiten aufklappbar in der Liste.

**Perioden:** Die Periodenseite zeigt die fälligen Perioden aller Abos mit
Status-Kacheln (offen, berechnet, teilweise, verzichtet, strittig).
„Vorschläge berechnen" gleicht die offenen Perioden mit den Lizenzpositionen
der gespiegelten Rechnungen ab und legt Vorschläge an; Sie bestätigen sie,
ordnen von Hand eine Position zu (nur Rechnungen desselben Empfängers, nur
freie Lizenzmonate) oder verzichten mit Grund („Kulanz"). Entschiedene
Perioden fasst die Planung nicht mehr an; „Zurücknehmen" öffnet sie wieder.
Wird eine zugeordnete Rechnung später in Lexoffice storniert, setzt der
nächste Lauf den Bezug auf null Monate, vermerkt den Storno und öffnet die
Periode wieder, damit die Ersatzrechnung zugeordnet werden kann. Domain-Abos
deckt der Lauf über Positionen, die den Domainnamen nennen (etwa „.de-Domain
inkl. Verwaltung (thieme-logistik.de)“), unabhängig von der Produkt-Einstufung
des Artikels.

**Rechnungsentwurf:** Aus allen offenen Perioden eines Rechnungsempfängers
entsteht per Klick ein Entwurf — bei Lexoffice-Rechnungshoheit als Entwurf
in Lexoffice (nichts wird abgeschlossen; Sie prüfen und stellen dort aus),
bei lokaler Rechnungshoheit als lokaler Rechnungsentwurf mit Positionen und
vorgeschlagenen Bezügen. Eine Position je Abo und Zeitraum, Endkunde in der
Beschreibung, Menge in Monaten bei Monatsartikeln. Die Perioden merken sich
den Entwurf: ein zweiter Klick legt keinen zweiten an, sondern nennt den
ausstehenden mit Nummer und Datum; erst wenn der Entwurf zur Rechnung wurde
oder die Periode entschieden ist, sind sie wieder frei. Der Dialog listet
nur Empfänger mit offenen Perioden und Verkaufspreis und nennt darunter, was
schon im Entwurf steht. Das Anlegen braucht das Recht *Rechnungsentwürfe aus
Perioden erzeugen*.

**Serienrechnung:** Bei lokaler Rechnungshoheit kann das Register die
Entwürfe täglich selbst anlegen. Der Schalter steht auf der Seite
*Produkt-Einstufung* (Recht *Reselling-Register pflegen*): aktiv = der nächste Lauf
legt je Rechnungsempfänger mit fälligen, noch nicht entworfenen Perioden
denselben Entwurf an wie der Klick — Positionen je Abo und Zeitraum,
Perioden als Vorschlag verknüpft und gestempelt. Der *Vorlauf* in Tagen
nimmt Perioden schon vor ihrem Beginn mit (0 = nur fällige). Empfänger
mit Lexoffice-/DATEV-Hoheit lässt der Lauf unberührt, Perioden ohne
Verkaufspreis werden übersprungen, eigener Bestand nie berechnet. Die
Entwürfe schließen Sie in der Rechnungsliste ab; der Wochen-Digest nennt,
wie viele Entwürfe der letzten sieben Tage noch offen sind. Einmalig oder
zur Probe: `php artisan resale:draft-local --organization=… --dry-run`
(`--force` übergeht den Schalter).

**Einkaufsbelege:** Der Ist-Einkauf je Abo und Periode kommt aus drei
Quellen: (1) Anbieterrechnungen und Gutschriften als PDF (Quality Hosting,
deutsches und englisches Layout) — jede Position nennt Vertrag, Endkunde und
Laufzeit, der Betrag geht exakt an die Periode; Gutschriftpositionen ohne
Vertrag gelten der Firma. (2) Eingangsbelege aus dem Belegspiegel pro rata:
für Sammelrechnungen ohne Positionen (Telekom) nennen Sie den Anteil des
Anbieters und den Leistungsmonat, der Betrag wird auf alle Perioden des
Monats verteilt, gewichtet mit ihrem monatlichen Soll-Einkauf. (3) Domain-
Buchungen aus der Domainverwaltung automatisch. Beim PDF-Import prüft das
Register die Summe: weicht die Summe der Positionen von der Belegsumme ab
(etwa weil eine Seite nicht gelesen wurde), wird trotzdem importiert und
der Unterschied als Hinweis gezeigt. Die Einkaufsseite filtert nach
Anbieter, Quelle, Zeitraum und Suchbegriff; eine Zuteilung lösen Sie immer
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

**Produkt-Einstufung:** Die Liste führt alle Artikel des Artikelkatalogs —
die aktiven Artikel des Artikelstamms und die Artikel angebundener
Buchhaltungsprogramme wie Lexoffice, mit ihrer Quelle. Welche davon
Abo-Produkte sind, erkennt das Register am Namen. Je Artikel können Sie
übersteuern: „Abo-Produkt" erzwingt die Erkennung, „Nie Abo-Position" hält
Dienstleistungen mit einem Produktnamen im Text (Wartung an Exchange) aus
Vorschlägen, Rechnungslisten und „Positionen ohne Abo" heraus. Die
Einstufung wirkt für jede Quelle gleich: sie entscheidet, welche
Rechnungspositionen der Belegspiegel als Lizenzpositionen führt, und die
Preisprüfung vergleicht den Verkaufspreis des Artikels mit den Abo-Preisen
(ein Monatspreis zählt beim Jahresabo zwölffach).

**Verträge:** Ein Abo kann einen Vertrag der Vertragsverwaltung als
Fristenrahmen tragen (Feld „Vertrag" im Abo-Dialog; nur Kundenverträge,
deren Partner der Rechnungsempfänger des Abos ist). Die Vertragsakte zeigt
ihre Abos im Panel „Abos & Lizenzen". Kein zweiter Fristenkatalog: der
tägliche Lauf trägt je Abo einen Termin in den Vertragskalender — bei
gekündigten Abos die Kündigungsfrist zum Ende, bei automatischer
Verlängerung eine Verlängerungswarnung vor der nächsten Periode, jeweils um
die Kündigungsfrist des Vertrags (ohne Angabe 30 Tage) vorgezogen, mit 14
Tagen Vorwarnung. Ändert sich das Ende, wandert der Termin mit; erledigte
Termine bleiben erledigt; beendete Abos oder Abos ohne Vertrag schließen
ihren offenen Termin.

**Domains:** Jede Domain aus der Domainverwaltung wird täglich zu einem Abo
„Domain" mit Jahresintervall ab Registrierung, Einkauf = Verlängerungspreis
und Halter aus der Domainverwaltung, solange das Register keinen entschieden
hat. Der Verkaufspreis je Endung kommt aus dem Preiskatalog (Anbieter
Domain-Reselling, Produkt z. B. „.de"), der Artikel aus dem Artikelkatalog
(Artikelstamm oder Buchhaltungsprogramm) zur Endung; manuell gepflegte Preise, Artikel und Halter überlebt jeder
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

**Lizenzkonto:** Ein Konto heißt nach seinem Halter; geht die Rechnung an
einen Partner, steht er dabei („über LDS Systems“), und Serienvorlagen des
Partners erscheinen nur in dem Endkunden-Konto, dem sie zugeordnet sind. Der
Reiter *Lizenzkonten* zeigt je Anbieterkonto (Kundennummer
beim Anbieter, Vorgänger eines Anbieterwechsels eingeschlossen), wie viele
Lizenzen je Produkt laufen, wer sie hält und welches Lizenzjahr berechnet ist
— als Raster Produkt × Halter × Jahr mit Rechnungsnummer oder „fehlt“. Halten
mehrere Firmen Lizenzen desselben Kontos, pflegen Sie die **Aufteilung** je
Produkt („Märkische Bunker 5 × Exchange ab 18.10.2025“): fremde Halter füllen
zuerst den Vertrag mit der größten Menge, dann nach Beginn, der Vertragshalter
behält den Rest. Die Abtretungen je Vertrag entstehen daraus, folgen jeder
Änderung und wandern beim Anbieterwechsel auf den Nachfolger; manuelle
Abtretungen bleiben als Ausnahme möglich. Der Zeitplan plant auch abgelöste
und beendete Verträge der letzten drei Jahre nach, damit eine spätere
Aufteilung ihr Soll noch ändert. **Prüffälle an Bezügen** zeigen Positionen,
die mehr Lizenzmonate decken, als sie haben (überbucht), und Bezüge, deren
Leistungszeitraum die Periode nicht trifft — mit „Auf … verschieben“ auf die
passende Periode desselben Abos oder „Bezug lösen“; der Bezugsdialog und die
Schnellzuordnung warnen vorab mit ⚠.

**Serienvorlagen (Plan):** Führt ein Buchhaltungsprogramm die Rechnungen,
entstehen wiederkehrende Rechnungen dort als Serienvorlagen. Das Register
spiegelt sie (Lexoffice: täglich und nach dem Belegsync, nur lesend) und zeigt
im Lizenzkonto je Vorlage Status, Rhythmus, nächste Ausführung und die
Lizenzpositionen mit den Abos, die sie berechnen. Die Zuordnung Abo ↔
Vorlagenposition schlägt der Lauf vor — zuerst über die Rechnung, die die
Vorlage zuletzt erzeugt hat und die einer Periode zugeordnet ist, sonst über
gleichen Empfänger, Artikel und Menge (bei mehreren Endkunden eines Partners
nur mit Nennung) — Sie bestätigen, ordnen von Hand zu
oder lösen. Perioden eines Abos mit laufender Vorlage lässt der
Rechnungsvorschlag aus. **Prüfregeln Bestand ↔ Plan** melden vor der Rechnung:
Abo ohne Vorlage, Vorlage ohne Abo („Abo aus Vorlage anlegen“ übernimmt
Artikel, Menge, Rhythmus und Preis), abweichende Menge, Vorlage läuft nach dem
Abo-Ende weiter, Vorlage pausiert oder beendet bei laufendem Abo, nächste
Ausführung mehr als sieben Tage neben dem Periodenbeginn, letzte Ausführung
fehlgeschlagen — in der Kachel, im Wochen-Digest und im Arbeitsvorrat. Jede
gespiegelte Rechnung trägt den Verweis auf ihre Vorlage.

**Überblick und Reiter:** Der Einstieg „Abos & Lizenzen“ ist eine
**Aufgabenliste in vier Gruppen** — Berechnen (fällige Leistung ohne
Rechnung, erst nach 21 Tagen Karenz und nie, solange eine aktive
Serienvorlage ihre Ausführung für die Periode noch vor sich hat; dazu
Prüffälle an Bezügen, Aufteilungen über dem Bestand, Stücklizenzen ohne
Rechnung), Zuordnen (Abos ohne Halter, Lizenzpositionen ohne Abo), Plan
prüfen (Befunde zu Serienvorlagen, also was die nächste Rechnung falsch
macht) und Anbieterstand (veraltete Importe, Verlängerungen in 30 Tagen).
Jede Zeile nennt Zähler und Betrag und springt auf die Fläche, auf der
entschieden wird. Daneben der **Stand**: der letzte Vorschlagslauf,
unbestätigte Vorschläge als Stichprobe, über Serienvorlage geplante und noch
in der Karenz liegende Perioden, je Anbieter der letzte Import mit Alter. Die
Reiter folgen dem Ablauf: Überblick · Lizenzkonten · Abgleich · Perioden ·
Inbox │ Abos · Lizenzbestand · Einkauf │ Berichte · Preise; Produkt-Einstufung
und Abrechnung liegen im ⋯-Menü.

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

**Produkt-Einstufung mit Filter:** Die Einstufung zeigt standardmäßig nur
Abo-Produkte und Artikel mit Abos oder Vorlagenpositionen; „Alle Artikel“
und „Ohne Einstufung“ sowie die Suche nach Name oder Nummer grenzen ein.
Führt ein Buchhaltungsprogramm die Rechnungen, steht je Artikel, in wie
vielen aktiven Serienvorlagen er vorkommt.

**Import mit Vorschau:** Nach dem Hochladen zeigt das Register zuerst, was
der Import ändern würde — je Datei neu, geändert (Menge, Preis, Status,
Beginn, Ende mit dem alten Wert), unverändert und die planbaren Abos des
Anbieters, die die Datei nicht mehr nennt (der Import beendet sie nicht).
Übernommen wird erst nach „Übernehmen“; „Verwerfen“ löscht die Dateien.
Ändert der Anbieter einen Preis, zieht der Import den Einkaufspreis des Abos
nach, solange er dem letzten Anbieterpreis entspricht — ein von Hand
gepflegter Preis bleibt — und schreibt eine Katalogzeile ab dem Stand der
Datei (Datum im Dateinamen wie „2026-10-26_Export.xlsx“, sonst der
Importtag). Liegt der letzte Import eines Anbieters länger als 30 Tage
zurück, zeigt der Überblick das an und der Wochen-Digest erinnert.

**Einkauf aus dem Rechnungseingang:** Ordnen Sie im Einkauf-Reiter unter
„Lieferanten zuordnen“ einem Lieferanten des Stamms den Anbieter zu (der
Name liefert einen Vorschlag, zugeordnet wird nie automatisch). Sobald eine
Eingangsrechnung dieses Lieferanten im Rechnungseingang zugeordnet ist,
übernimmt das Register sie als Einkauf: Quality Hosting positionsgenau über
den Rechnungsleser (Vertrag und Laufzeit je Position), Domainanbieter je
Domainname auf das Domain-Abo (nur, wenn das Buchungsjournal des Registrars
die Kosten nicht schon liefert), alle anderen pro rata auf die Perioden des
Rechnungsmonats. Jede Zeile trägt die Eingangsrechnung als Herkunft; ein
zweiter Lauf erkennt sie am Hash. Positionen, die zu keinem Abo passen,
bleiben als „ohne Abo“ stehen — der Filter „Nur ohne Abo“ zeigt sie, „Abo
zuordnen“ hängt sie an das richtige Abo und die Periode am Belegdatum.
Rückwirkend holt `resale:import-incoming --since=` zugeordnete Rechnungen
nach. Das Lizenzkonto zeigt je Produkt Soll- und Ist-Einkauf sowie die
Marge der letzten zwölf Monate; der Wochen-Digest meldet Abos, deren
Vertragspreis vom zuletzt importierten Katalog- oder Anbieterpreis abweicht.

**Rechnungsbezug an Stücklizenzen:** Beim Verkauf einer Lizenz aus dem
Lizenzbestand wählen Sie die Rechnungsposition aus dem Buchhaltungsprogramm,
die diese Lizenz berechnet; die Liste zeigt die Positionen des gewählten
Kunden rund um das Verkaufsdatum, passende (gleicher Artikel oder
Produktname) stehen vorn und werden vorgeschlagen. Ohne Position bleibt die
Rechnungsreferenz Freitext. Lizenzliste, Paketseite und Kundenakte zeigen zur
verknüpften Position die Belegnummer mit Sprung ins Buchhaltungsprogramm und
Belegbild. Ein Verkauf, der nach 30 Tagen weder Position noch Referenz trägt,
erscheint als Prüffall „Stücklizenzen ohne Rechnung“ im Überblick, im
Abgleich des Empfängers und über den Filter „Ohne Rechnung“ im
Lizenzbestand; nachtragen können Sie den Bezug über „Verkauf berichtigen“.
Im Kundenportal sehen Kunden ihre verkauften Lizenzen mit Rechnungsnummer,
nie den Schlüssel.
