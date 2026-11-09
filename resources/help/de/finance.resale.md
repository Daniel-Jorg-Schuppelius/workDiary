---
title: "Abos & Lizenzen"
topic: finance.resale
version: 13
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
    - finance.resale.accounts
    - finance.resale.reconcile
    - finance.resale.board
    - finance.resale.subscription
    - finance.resale.periods
    - finance.resale.plan
    - finance.resale.reports
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

**Mengen und Deckung:** Das Register lässt nirgends mehr zuordnen, als da
ist. Einer Periode ordnen Sie höchstens zu, was sie noch offen hat — auch
beim Verschieben eines Bezugs. Abtreten, aufteilen und übernehmen lassen
sich nur Lizenzen, die im Zeitraum weder abgetreten noch schon über
Rechnungen an den Halter berechnet sind; eine Abtretung an den eigenen
Halter lehnt das Register ab und zeigt Altfälle mit „Abtretung
zurücknehmen“. Eine Position einer Serienvorlage nimmt höchstens so viele
Lizenzen auf, wie sie berechnet („Vorlagenposition überbucht“ meldet
Altfälle). Deckt eine Vorlage nur einen Teil eines Abos, enthält der
Rechnungsvorschlag den Rest („4 von 9 Lizenzen, 5 über Serienvorlage“); der
automatische Serienlauf lässt ihn aus. Eine Rechnungsposition trägt
höchstens so viele Stücklizenzen, wie sie Stück hat.

**Generische Liste:** Neben den Anbieter-Exporten (Telekom, Quality
Hosting) nimmt der Import jede CSV- oder XLSX-Liste, deren Spalten am Namen
erkennbar sind — deutsch oder englisch: Kennung, Firma, Produkt, Menge,
Beginn, Ende, Intervall, Laufzeit, Einkaufspreis, Verkaufspreis, Anbieter,
Bestellnummer. Pflicht sind Firma, Produkt und Beginn. Ohne Kennung entsteht
sie aus Firma, Produkt und Beginn, sodass ein erneuter Import dieselben Abos
aktualisiert statt zu verdoppeln. Der Anbieter kommt aus der Spalte oder
aus dem Dialog; eine CSV-Vorlage liegt im Import-Dialog.

**Posteingang:** Importierte Abos, deren Firma das Register noch keinem
Halter zuordnen kann, landen im Posteingang. Je Firma entscheiden Sie einmal:
Kunde, Endkunde eines Partners (Fremdkunde) oder eigener Bestand — der
Vorschlag kommt aus dem Namensvergleich mit Kunden und Fremdkunden und aus
den Rechnungstexten: Steht die Firma im Text von Rechnungen an einen Partner,
schlägt der Posteingang diesen Partner vor, und ein Klick öffnet die Zuordnung
als Endkunde. Auch beendete Abos bleiben hier, solange eine fällige Periode
offen ist — ohne Halter fände sonst niemand ihre Rechnungen. „Halter zuordnen“ neben
„Noch nicht zugeordnet“ (Lizenzkonto, Abo, Perioden) öffnet dieselbe Zuordnung. Die
Entscheidung wird gemerkt, der nächste Import ordnet dieselbe Firma sofort
zu. Zeilen, die der Import nicht verarbeiten konnte (unlesbares Datum, Menge
ohne Zahl, doppelte Kennung), stehen als Befunde am Import: die Anzahl in
der Meldung, die Einzelheiten aufklappbar in der Liste.

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
Vertrag gelten der Firma. (2) Eingangsbelege aus dem Belegspiegel (Lexoffice, sevDesk, Akaunting, Invoice Ninja und weitere Programme; ohne Entwurf, Storno und gelöschte Belege) pro rata:
für Sammelrechnungen ohne Positionen (Telekom) nennen Sie den Anteil des
Anbieters und den Leistungsmonat, der Betrag wird auf alle Perioden des
Monats verteilt, gewichtet mit ihrem monatlichen Soll-Einkauf. (3) Domain-
Buchungen aus der Domainverwaltung automatisch. Beim PDF-Import prüft das
Register die Summe: weicht die Summe der Positionen von der Belegsumme ab
(etwa weil eine Seite nicht gelesen wurde), wird trotzdem importiert und
der Unterschied als Hinweis gezeigt. Die Einkaufsseite filtert nach
Anbieter, Quelle, Zeitraum und Suchbegriff; eine Zuteilung lösen Sie immer
als Ganzes je Beleg.

**Produkt-Einstufung:** Die Liste führt alle Artikel des Artikelkatalogs —
die aktiven Artikel des Artikelstamms und die Artikel angebundener
Buchhaltungsprogramme wie Lexoffice, mit ihrer Quelle. Auch easybill,
sevDesk, Invoice Ninja, Akaunting und InvoicePlane liefern ihre Artikel:
der Belegabruf liest sie vor den Belegen, und gespiegelte Positionen tragen
dann den Artikel der Quelle statt nur seiner Kennung. Welche davon
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
Domains im Subuser-Konto rechnet das Domainportal selbst ab: es stellt dem
Subuser Monatsrechnungen, die das Buchungsjournal den Perioden zuordnet.
Diese Domains brauchen keine Serienvorlage und kommen in keinen
Rechnungsentwurf des Registers; Mappe und Abo-Seite nennen den Subuser.

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
Die Kacheln über den Listen führen zu dem, was sie zählen: Auf Lizenzkonten,
Abgleich, Lizenzbestand und in einer Charge filtern sie die Liste, in der
Arbeitsmappe eines Empfängers die Abgleichstafel auf die betroffenen Produkte;
dort hebt ein zweiter Klick auf die aktive Kachel den Filter wieder auf.

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
zurück, zeigt der Überblick das an und der Wochen-Digest erinnert. Ein Anbieter
ohne laufende Abos, etwa nach einem Wechsel, steht dort als „keine laufenden
Abos“ und gilt nicht als veraltet.

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
