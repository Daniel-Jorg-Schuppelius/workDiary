---
title: "Lernplattform"
topic: learning.overview
version: 3
audience: []
related:
    - training.overview
    - safety.overview
    - learning.standards
    - learning.subtitles
---

Die Lernplattform beantwortet, **wie gelernt und geprüft wird**. *Was* wer
bis wann schuldet, bleibt im Trainingsmanagement — beide greifen ineinander,
ohne sich zu doppeln.

## Kurse aufbauen

Ein Kurs besteht aus Abschnitten und Lerneinheiten. Eine Einheit ist
entweder Inhalt, eine Prüfung, eine Aufgabe, ein Präsenztermin oder ein
Fremdinhalt. Inhalte werden aus Blöcken gebaut (Text, Überschrift, Hinweis,
Checkliste, Bild, Galerie, Datei, Video, Audio, Einbettung, Code, Akkordeon,
Tabelle, Wissensartikel, Prozedur, Verständnisfrage, Trenner) — freies HTML gibt
es bewusst nicht.

**Jeder Block ist auch ohne Sehen, Hören oder Maus nutzbar.** Bilder und jedes
Galeriebild brauchen einen Alternativtext, Audio ein Transkript, und
Tabellenspalten brauchen Köpfe. Akkordeon und Lösung der Verständnisfrage
klappen per Tastatur auf. Eine Verständnisfrage wird nicht bewertet: richtige
Antworten beginnen im Editor mit `*`. Der Prozedurblock zeigt die gültige
Version einer Prozedur; gestartet wird sie an einem Tagebucheintrag.

**Einbettungen brauchen einen freigegebenen Host.** Die Sicherheitsrichtlinie
der Anwendung blockiert fremde Seiten sonst still im Kurs; deshalb lehnt der
Editor einen nicht freigegebenen Host sofort sichtbar ab. Erlaubte Hosts
werden in den Einstellungen gepflegt.

Ein Kurs kann **Voraussetzungen** haben (alle oder eine genügt): sie sperren den
Start, nicht die Zuweisung — Pflicht-Einschreibungen sind ausgenommen. Eine
**Prüfung ohne Kurs** ist ein Kurs der Art „Prüfung“ mit genau einer
Prüfungseinheit; wer besteht, bekommt den hinterlegten Zielkurs angerechnet —
mit demselben Rückfluss in Zertifikat, Unterweisungsnachweis und Qualifikation.

Mit **Feste Reihenfolge** gibt ein Kurs jede Einheit erst frei, wenn die vorherige
abgeschlossen ist; gesperrte Einheiten tragen den Hinweis „Nach der vorherigen
Einheit“. Ein Freigabedatum der Einheit gilt zusätzlich.

Wer von LearnDash kommt, übernimmt das **Export-ZIP** (Kurskatalog → „LearnDash-Import“): Kurse, Lektionen, Themen und Prüfungen entstehen als Entwürfe, Fragen landen im Katalog mit ihrer Kategorie. Bilder und Medien werden nicht kopiert (Platzhalter zum Nachpflegen), Lektionsvideos nur von freigegebenen Hosts. Abgeschlossene Kurse werden für Personen mit passender E-Mail als Einschreibung „importiert“ vermerkt — ohne Zertifikat und ohne Unterweisungsnachweis, denn ein importierter Abschluss ist kein eigener Nachweis. Der Probelauf zeigt vorher, was entstünde.

## Freigabe friert den Inhalt ein

Mit der Freigabe entsteht eine Kursversion mit einem Abbild des gesamten
Inhalts. Laufende Teilnahmen bleiben auf ihrer Version — der Stoff ändert
sich nicht unter jemandem, der schon mittendrin ist. Nach der Freigabe ist
der Inhalt gesperrt; Korrekturen laufen über eine Folgeversion.

Hängt der Kurs an einem Schulungskurs des Trainingsmanagements, schreibt die
Freigabe dort die Kursversion mit. So trägt der spätere Nachweis dieselbe
Versionsnummer.

Kursoptionen steuern den Ablauf: Ein **Freischaltplan** (Tage ab Einschreibung
und/oder festes Datum — es gilt das spätere) sperrt eine Einheit bis zum Tag,
und zwar an jeder Abschlussstelle — Player, Portal, externer Zugang und
Offline-Sync —, nicht nur in der Anzeige. Eine **Mindestverweildauer** zählt ab
dem ersten Öffnen der Einheit oder über die Lernzeit. **Vorschau-Einheiten**
sind im Portal ohne Einschreibung lesbar (nur Text). **Kategorien** aus den
Einstellungen ordnen den Katalog, **Schlagwörter** bilden die Querachse und
bleiben auch nach der Freigabe pflegbar; **Verfügbarkeitsfenster** und
**Teilnehmergrenze** gelten für die Selbsteinschreibung — die Verwaltung darf
weiterhin zuweisen, Pflicht-Einschreibungen umgehen die Grenze. Aufgaben
tragen **Dateiregeln** (Endungen, Anzahl, Größe — nie lockerer als das System)
und auf Wunsch eine **Auto-Freigabe** mit vollen Punkten, die sich mit dem
Vier-Augen-Prinzip ausschließt.

## Lernzeit ist Arbeitszeit

Unterweisungen müssen **während der Arbeitszeit** stattfinden (§ 12 Abs. 1
ArbSchG). Deshalb trägt jeder Kurs eine Zeitpolitik:

- **Nur während der Arbeitszeit** (Vorgabe für Pflichtkurse): Der Start
  außerhalb wird abgelehnt.
- **Zählt immer als Arbeitszeit**: für angeordnete Fortbildung.
- **Außerhalb nur mit Freigabe**.
- **Freiwillig, unbezahlt**: nur für echte Zusatzangebote — bei Kursen mit
  Pflichtbezug gesperrt.

Lernzeit **innerhalb** der Arbeitszeit wird nicht doppelt gezählt; die Zeit
ist über die Anwesenheit bereits erfasst. Lernzeit **außerhalb** erzeugt eine
Anwesenheitsspanne, damit Ruhezeit, Höchstarbeitszeit und Nachtarbeit
geprüft werden.

## Prüfungen

Ein Versuch friert die gestellten Fragen ein. Wird eine Frage später
geändert, bleibt ein altes Ergebnis erklärbar — genau das fragt ein Prüfer
nach einem Vorfall. Versuche werden nie gelöscht; eine Korrektur tritt neben
den ursprünglichen Wert, statt ihn zu ersetzen.

Aufsätze bewertet ein Mensch. Die KI schlägt Kurse und Fragen vor und
beantwortet Lernerfragen im Kurskontext — **bewerten und entscheiden darf
sie nicht**.

Fragen liegen im **Fragenkatalog** der Organisation (Menü „Lernen“ →
„Fragenkatalog“) mit Kategorie und Kurzname; eine Prüfung zeigt auf
Katalogfragen, dieselbe Frage darf in mehreren Prüfungen stehen. Zusätzlich zur
festen Liste ziehen **Ziehregeln** je Versuch eine Anzahl zufälliger Fragen aus
einer Kategorie („5 aus Brandschutz“). Eine Frage aus der Prüfung zu entfernen
lässt sie im Katalog; gelöscht wird nur, was nirgends verwendet wird — und ein
abgelegter Versuch behält immer seine eigene Kopie der Fragen.

Der Prüfungsablauf ist je Prüfung einstellbar: alle Fragen auf einer Seite
oder eine Frage je Seite, Zurück und Überspringen erlauben, Pflichtbeantwortung,
Ergebnistexte je Prozentbereich und ein Tipp je Frage. Antworten werden beim
Ändern zwischengespeichert — nach einem Verbindungsabbruch geht nichts verloren;
nach Ablauf des Zeitlimits zählt nur, was rechtzeitig gespeichert war. Die
Fragenübersicht zeigt beantwortete und gemerkte Fragen.

Prüfende sehen die **Prüfungsakte** eines Versuchs (Fragen der eingefrorenen
Kopie, gegebene Antworten, Punkte, Korrekturen) — jede Einsicht wird
protokolliert. Je Prüfung gibt es eine **Statistik** (Versuche, Bestehensquote,
Zeitbedarf, Fehlerquote je Frage — Quoten erst ab der Mindestgruppe). Aus der
Teilnehmerliste lässt sich ein **weiterer Versuch freigeben**, trotz
Versuchsgrenze oder Sperrfrist, genau einmal und mit Begründung.

Das **Notenbuch** je Kurs zeigt Lernende × Bestandteile. Ohne Komponenten addiert es die Punkte aus Prüfungen und Aufgaben; legt die Betreuung **Komponenten** fest (Prüfung, Aufgabe, manuelle Note), kann sie Gewichte vergeben — alle zusammen 100 oder gar keine. Manuelle Noten sind additiv: eine Korrektur ist ein neuer Eintrag, der jüngste zählt. Das **Zeugnis** (PDF) und der CSV-Export kommen aus derselben Rechnung; solange etwas offen ist, trägt das Zeugnis den Vermerk „vorläufig“.

Feinheiten je Frage: Antwortoptionen können **eigene Punkte** tragen („Label {3}“, auch negativ) — dann zählt die gewählte Option statt alles-oder-nichts; eine **Selbsteinschätzung** ist eine Skala ohne richtige Antwort, die gewählte Stufe ist der Wert; ein **Aufsatz** nimmt Text, eine Datei oder beides an — die Datei liegt in der Bewertung bereit. Je Prüfung lässt sich das Bestehen zusätzlich **in Punkten** verlangen und die Teilmenge je Versuch **in Prozent** der verfügbaren Fragen festlegen.

## Nachweise

Ein bestandener Kurs wirkt an genau einer Stelle nach außen: Zertifikat mit
Prüfcode, Unterweisungsnachweis im Arbeitsschutz-Register, erfülltes
Schulungs-Soll und verlängerte Qualifikation. Es entsteht keine zweite
Nachweiswelt.

Zertifikate lassen sich über einen Link prüfen. Die Prüfseite zeigt Kurs,
Datum, Gültigkeit und Aussteller — den Namen nur abgekürzt.

Lerndaten gehören der Person: Die **Betroffenenauskunft** (Datenschutz-Modul)
nennt Einschreibungen, Prüfungsversuche, Zertifikate, Lernzeit und Buchungen
als Zähler mit Zeitraum — Fragetexte und Antworten nicht. Das **Löschkonzept**
schlägt abgeschlossene Einschreibungen ohne Zertifikat nach der Frist des
Rechtsraums zum Löschen vor (Versuche und Lernzeit gehen mit); Zertifikate
bleiben wegen ihrer Nachweisfunktion länger und werden danach auf Initialen
gekürzt — der Prüflink antwortet weiter.

## Kompetenzen

Die **Kompetenzmatrix** (Lernen → Kompetenzen) zeigt je Person die
erreichte Stufe jeder Kompetenz. Stufen entstehen auf zwei Wegen: Ein Kurs mit
hinterlegter Kompetenz belegt beim Abschluss seine Stufe — eine Wiederholung
stuft nie herab, und bei Kursen mit Gültigkeitsdauer gilt die Stufe nur
befristet. Eine **Einschätzung** durch die Lernverwaltung darf eine Stufe
dagegen auch senken.

Je Rolle lässt sich eine **Soll-Stufe** festlegen. Liegt eine Person darunter,
markiert die Matrix die Lücke; abgelaufene Stufen zählen dabei nicht.
Kompetenzen sperren nichts — Sperren bleiben bei der Qualifikation.

## Wer lernt

Neben Mitarbeitenden können Kunden über das Portal und externe Beteiligte
ohne Benutzerkonto lernen. Externe erhalten einen befristeten Einmal-Link;
ihr Nachweis ist derselbe wie intern.

Die Teilnehmer eines Kurses verwaltet die Kursakte unter „Teilnehmer“:
Personen der Organisation oder externe Personen einschreiben, Fälligkeit und
Zugang mit Begründung ändern, stornieren (Pflicht-Einschreibungen nie) und den
Einstiegslink für Externe erzeugen — ein neuer Link entwertet den alten. Bei
einer Buchungszusage geht der Link von selbst hinaus.

In den **Einstellungen der Lernplattform** (Kurskatalog, Verwaltungsrecht)
liegen der Schalter für Punkte und Bestenliste, die erlaubten Einbettungs-Hosts
und die **Trainer-Sicht**: eingeschaltet sehen Personen mit Autoren- oder
Bewertungsrecht nur Kurse, die ihnen gehören oder an denen sie als Trainer
stehen — Kurskatalog, Bewertungscockpit, Prüfungsstatistik und Kursanalyse
folgen derselben Regel. Die Verwaltung sieht weiterhin alles.

Im Player hält jede lernende Person **private Notizen** zur Einheit oder zum
Kurs fest — sichtbar nur für sie selbst, auch nicht für die Administration,
nicht in der Tätigkeitsrecherche; „Meine Schulungen" sammelt sie. Eine
**Frage an den Trainer** geht an die verantwortliche Person und die Trainer
des Kurses: mit Helpdesk-Modul als Ticket, sonst per E-Mail — beide werden
zusätzlich benachrichtigt. Zwei Dashboard-Kacheln (standardmäßig ausgeblendet)
zeigen die eigenen offenen Schulungen und den Bewertungs-Rückstand; die
Tätigkeitsrecherche findet freigegebene Kurse — Lernende ihre eigenen,
Autoren alle.

Der **Kurskatalog** lässt sich als Liste oder als Kacheln zeigen (die Wahl bleibt je Person gespeichert) und trägt einen **Sternewert** aus dem Kursfeedback — erst ab fünf Antworten, damit sich nichts auf Einzelne zurückrechnen lässt. Das Portal zeigt zusätzlich den **Preis** aus dem verknüpften Artikel. Im Player blendet der **Fokusmodus** die Seitenleiste aus; „Duplizieren“ legt aus einem Kurs einen neuen Entwurf an — Lehrmaterial ja, Einschreibungen und Nachweise nein.

## Auswertung und Mitbestimmung

Die Kursanalyse zeigt Quoten und Auffälligkeiten, keine Personenprofile.
Quoten erscheinen erst ab fünf Einschreibungen, damit sich nicht auf
Einzelne zurückrechnen lässt. Punkte, Abzeichen und Bestenliste sind im
Auslieferungszustand aus; die Bestenliste zeigt zusätzlich nur, wer
ausdrücklich zustimmt.

Benachrichtigungen kommen über die Org-Regeln: Zuweisung, bald fällig,
überfällig (mit Eskalation), Abgabe eingegangen, Bewertung vorhanden,
Zertifikat, Nachrücken von der Warteliste, Buchungsentscheidung und
Lernzeit-Freigabe. Die KI hat drei Eingänge — Gliederungsentwurf und
Fragenentwurf im Editor, Tutor im Player — und schlägt nur vor; übernommen
und bewertet wird von Hand. Punkte und Abzeichen erscheinen unter „Meine
Schulungen“, die Bestenliste zeigt nur Personen mit eigenem Opt-in.
