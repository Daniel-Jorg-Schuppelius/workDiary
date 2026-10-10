---
title: "Personal: Urlaub, Krankheit, Qualifikation, Arbeitsschutz"
topic: reports.personnel
version: 6
keywords:
    - Fehlzeiten
    - Resturlaub
    - Urlaubsauswertung
    - Krankheitstage
    - Lohnfortzahlung
    - Fortsetzungserkrankung
    - AU-Bescheinigung
    - Qualifikationsmatrix
    - ablaufende Zertifikate
    - Arbeitsunfälle auswerten
    - Beinaheunfall
    - Schulungsbedarf
    - Frühwarnungen
audience:
    - admin
    - geschaeftsfuehrung
    - personalverwaltung
    - teamleitung
related:
    - reports.overview
    - absences.manage
    - reports.absence-calendar
    - time-accounts.flex
    - catalog.qualifications
    - safety.overview
    - learning.overview
---

Diese Auswertungen fassen personenbezogene Daten zusammen: Urlaub und
Gleitzeit, Krankheit und Lohnfortzahlung, Qualifikationen mit Ablaufdaten,
Sicherheitsereignisse sowie wiederkehrende Probleme und Schulungsbedarf. Sie
finden sie unter **Auswertungen** → **Team** (**Urlaub & Flex**,
**Krankheiten**, **Qualifikationen**, **Arbeitsschutz**) und unter
**Auswertungen** → **Projekte & Kunden** (**Probleme & Schulung**). Es handelt
sich um besonders schützenswerte Daten: Geben Sie Zahlen nur an Personen
weiter, die sie für ihre Aufgabe brauchen. Korrekturen nehmen Sie am
Urlaubsantrag, an der Krankmeldung, an der Qualifikation der Person oder am
Sicherheitsereignis vor.

## Urlaub & Flex

**Auswertungen** → **Team** → **Urlaub & Flex** zeigt Abwesenheiten und
Gleitzeit je Person für den Zeitraum aus der Kopfzeile. Gezählt werden
Werktage: Montag bis Freitag ohne Feiertage, auf den Zeitraum zugeschnitten.

Spalten je Person:

- **Urlaub**, **Sonder** und **Unbezahlt**: Werktage aus genehmigten Anträgen
  der jeweiligen Art.
- **Krank**: Werktage aus Krankmeldungen; stornierte Krankmeldungen zählen
  nicht.
- **Ausstehend**: Werktage aus noch nicht entschiedenen Anträgen, farbig
  hervorgehoben.
- **Anspruch** und **Rest** mit Jahreszahl: das Urlaubskonto des Jahres, in dem
  der Zeitraum endet. Der Anspruch umfasst Grundanspruch, Zusatzurlaub und
  nutzbaren Übertrag; der Rest zieht nur genehmigte Tage ab und wird rot, wenn
  er negativ ist. Ohne hinterlegten Anspruch steht „–“.
- **Flex Δ**: Veränderung des Gleitzeitsaldos in den Monaten des Zeitraums
  (Ist minus Soll laut Monatsständen des Arbeitszeitkontos).
- **Flex-Saldo**: der letzte Monatsstand bis zum Ende des Zeitraums.

Kacheln: **Mitarbeiter**, **Urlaub (Werktage)** mit den ausstehenden Tagen,
**Krank**, **Sonder / Unbezahlt** und **Flex-Änderung Σ**. Das Diagramm
**Abwesenheitstage je Monat nach Typ** stapelt Urlaub, Krank, Sonder und
Unbezahlt; je nach Länge des Zeitraums heißt es je Tag, je Woche oder je
Quartal. **Resturlaub je Mitarbeiter (Top 15)** zeigt die höchsten Reste.

Die Filter **Bereich** (**Nur eigene** oder **Gesamtes Team** für alle
Personen der Organisation), **Mitarbeiter**, **Team** und **Status** sehen
Administratoren und Personen mit dem Recht **Alle Urlaubsanträge sehen**; alle
anderen sehen nur ihre eigene Zeile. Mit **Status** zählen nur Anträge mit
**Ausstehend** oder **Genehmigt**. Export: **PDF** mit Diagramm, **CSV** und
**Excel**.

Die Spalte **Krank**, ihre Kachel und ihr Anteil im Diagramm zeigen die Werte anderer Personen nur, wenn Sie zusätzlich das Recht **Krankmeldungen einsehen** haben; sonst fehlen sie in Ansicht und Export.

## Krankheiten

**Auswertungen** → **Team** → **Krankheiten** öffnet den **Krankheits-Report**
für den Zeitraum aus der Kopfzeile. Stornierte Krankmeldungen zählen nicht.

Spalten je Person mit Krankmeldungen im Zeitraum:

- **Werktage** und **Kal.-Tage**: Krankheitstage im Zeitraum, einmal ohne
  Wochenenden und Feiertage, einmal als Kalendertage.
- **Fälle**: Zahl der Krankmeldungen; **Folge**: davon Folgebescheinigungen.
- **Mit AU**: Krankmeldungen mit hochgeladener Bescheinigung, im Verhältnis zu
  allen Fällen.
- **Lohnfortzahlung**: verbrauchte Tage im Verhältnis zum Anspruch als
  Balken – grün, ab 75 % orange, rot bei Ausschöpfung.
- **Status**: **Ausgeschöpft** mit dem Datum, an dem der Anspruch endet, sonst
  die verbleibenden freien Tage oder **OK**. Unter dem Namen steht **Kette
  seit** mit dem Beginn der laufenden Krankheitskette.

So rechnet die Lohnfortzahlung:

- Der Anspruch beträgt in der Standardeinstellung sechs Wochen, also 42
  Kalendertage Arbeitsunfähigkeit. Gezählt werden nur die Krankheitstage selbst,
  bei einer laufenden Krankheit bis heute – Arbeitstage zwischen zwei
  Krankmeldungen zählen nie mit.
- Krankmeldungen, die sich überschneiden, nahtlos aneinander anschließen oder
  als Folgebescheinigung verknüpft sind, bilden einen Krankheitsfall. Das gilt
  auch, wenn während einer laufenden Krankheit eine neue Krankheit hinzukommt.
- Eine neue Krankheit, die erst nach Arbeitstagen beginnt, startet mit vollem
  Anspruch.
- Bestätigt die Krankenkasse dieselbe Krankheit (Fortsetzungserkrankung), wird
  an der neuen Krankmeldung im Feld **Fortsetzungserkrankung von** die frühere
  Krankmeldung gewählt. Dann teilen sich die Fälle einen Anspruch. Für dieselbe
  Krankheit entsteht ein neuer Anspruch, wenn die Person in der
  Standardeinstellung sechs Monate lang nicht wegen dieser Krankheit
  arbeitsunfähig war oder seit Beginn der ersten Arbeitsunfähigkeit zwölf
  Monate vergangen sind.
- **Kette seit** nennt den Beginn des ersten Falls, der auf den laufenden
  Anspruch zählt.

Spalten **Lohnfortzahlung** und **Status** zeigen den Stand von heute,
unabhängig vom gewählten Zeitraum. Die Werte sind eine Orientierung, keine
arbeitsrechtliche Prüfung oder Rechtsberatung.

Kacheln: **Mitarbeiter**, **Werktage krank** mit den Kalendertagen,
**Krankheitsfälle** mit den Folgebescheinigungen, **Mit AU** und **Anspruch
ausgeschöpft**. Diagramme: **Kranktage je Monat** mit Medianlinie (je nach
Zeitraum je Tag, Woche oder Quartal) und die Heatmap **Kranktage je Mitarbeiter
und Monat**.

Filter wie bei **Urlaub & Flex**: **Bereich**, **Mitarbeiter** und **Team** –
hier für Administratoren und Personen mit dem Recht **Krankmeldungen
einsehen**; alle anderen sehen nur ihre eigene Zeile. Diese Seite bietet keinen
Export.

## Qualifikationen

**Auswertungen** → **Team** → **Qualifikationen** zeigt die
**Qualifikationsmatrix**: eine Zeile je Person mit mindestens einer aktiven
Qualifikation, eine Spalte je aktiver Qualifikation des Katalogs (Kürzel,
voller Name beim Überfahren). Inaktive Qualifikationen erscheinen weder in der
Matrix noch in Kacheln, Diagrammen und Export.

- Jede Zelle zeigt das Ablaufdatum oder ✓, wenn die Qualifikation ohne
  Ablaufdatum gilt.
- Farben: grün **gültig**, orange **läuft in 30 Tagen ab**, rot
  **abgelaufen**, grau **keine Zuweisung**. Die Legende steht unter der Matrix.
- Kacheln: **Mitarbeiter**, **Qualifikationen**, **Zuweisungen**, **Laufen ab
  (≤30 T.)** und **Abgelaufen**.
- Diagramme: **Träger je Qualifikation (Top 15)** und **Zuweisungen je
  Qualifikation nach Status** für die zwölf häufigsten Qualifikationen.

Stichtag ist immer heute; der Zeitraum der Kopfzeile ändert die Matrix nicht.
Die Zeilen aller Personen sehen Administratoren und Personen mit dem Recht
**Qualifikationen verwalten**; alle anderen sehen nur ihre eigene Zeile.
Die Filter **Mitarbeiter** und **Team** gibt es nur mit diesem Recht. Export: **PDF** im Querformat, **CSV** und
**Excel** mit einer Zeile je Person und dem Ablaufdatum bzw. einem
Gültigkeitsvermerk je Qualifikation. Qualifikationen pflegen Sie im Katalog und
an der Person, nicht in der Auswertung.

## Arbeitsschutz

**Auswertungen** → **Team** → **Arbeitsschutz** wertet alle
Sicherheitsereignisse aus, die im Zeitraum der Kopfzeile eingetreten sind.

- Kacheln: **Ereignisse gesamt**, **Offen** (alle nicht geschlossenen),
  **Geschlossen** und **Kritisch** (Schweregrad Kritisch).
- Diagramme: **Ereignisse je Monat** mit der zweiten Reihe **davon
  geschlossen** und **Ereignisse je Monat nach Status**, gestapelt nach
  **Gemeldet**, **In Untersuchung**, **Maßnahmen definiert** und
  **Geschlossen**; je nach Zeitraum je Tag, Woche oder Quartal.
- **Nach Art** zählt **Unfall**, **Beinaheunfall**, **Gefährdung** und
  **Mangel**, **Nach Schweregrad** zählt **Niedrig**, **Mittel**, **Hoch** und
  **Kritisch**.

Filter: **Mitarbeiter** und **Team** – sie beziehen sich auf die Person, die
das Ereignis gemeldet hat. Einen Export gibt es nicht; die einzelnen Ereignisse
bearbeiten Sie im Sicherheitsereignis-Register.

## Probleme & Schulung

**Auswertungen** → **Projekte & Kunden** → **Probleme & Schulung** öffnet die
**Management-Auswertung**. Sie zeigt den aktuellen Stand ohne Zeitraum, Filter
oder Export.

Die Karte **Wiederkehrende Probleme** sammelt die Frühwarnungen der Module, die
Ihre Organisation nutzt, gruppiert nach Art:

- **Nacharbeit je Kunde**: Kunden, deren Nacharbeitsanteil der letzten 90 Tage
  den Zielwert verfehlt. Ohne hinterlegten Zielwert (siehe „Zielwerte
  (Reports)“) entsteht keine Warnung.
- **Wiederkehrende Defekte**: Objekte mit wiederholten Defekten in den letzten
  zwölf Monaten.
- **Reklamationsmuster**: auffällig gehäufte Reklamationen.
- **Wiederkehrende Tickets**: Kunden oder Objekte mit vielen Tickets im
  Zeitfenster; Schwelle und Fenster sind Einstellungen der Organisation,
  standardmäßig drei Tickets in 90 Tagen.
- **Personal-Engpässe**: Teams, deren geplanter Bedarf in den nächsten vier
  Wochen die Kapazität übersteigt.

Jeder Eintrag nennt den Befund, ein Detail und eine Empfehlung; der Titel führt,
wo vorhanden, zum betroffenen Kunden, Objekt oder Bericht. Ohne Befund steht
**Keine Auffälligkeiten.**

Die Karte **Schulungsbedarf** listet je **Kompetenz** die **Personen mit
Lücke**, die **Ø Lücke (Stufen)** und **Passende Kurse** (freigegebene Kurse,
die die Kompetenz vermitteln). Grundlage sind die Kompetenzanforderungen je
Rolle der Lernplattform; abgelaufene Nachweise zählen nicht. Ohne Lernplattform
oder ohne Kompetenz-Soll bleibt die Tabelle leer.

## Wer was sieht

- **Urlaub & Flex**, **Krankheiten** und **Qualifikationen**: Ohne weiteres
  Recht sieht jede Person nur ihre eigenen Daten. Die Sicht auf alle Personen
  der Organisation haben Administratoren und, je Auswertung, wer das Recht der
  zugehörigen Liste hat: **Alle Urlaubsanträge sehen** für **Urlaub & Flex**,
  **Krankmeldungen einsehen** für **Krankheiten** und **Qualifikationen
  verwalten** für **Qualifikationen**.
- **Arbeitsschutz**: Menüeintrag und Seite nur mit dem Recht
  **Sicherheitsereignis-Register sehen** oder **Sicherheitsereignisse bearbeiten
  / schließen**; Administratoren sehen sie immer. In der Standardvergabe haben
  Teamleitung und Geschäftsführung das Leserecht.
- **Probleme & Schulung**: nur mit dem Recht **Auswertungen einsehen** oder als
  Administrator. In der Standardvergabe haben es unter anderem
  Geschäftsführung, Teamleitung und Personalverwaltung.
- **Urlaub & Flex**, **Krankheiten** und **Qualifikationen** setzen das
  Zusatzmodul Team-Auswertungen voraus; **Arbeitsschutz** und **Probleme &
  Schulung** stehen ohne das Modul zur Verfügung.
