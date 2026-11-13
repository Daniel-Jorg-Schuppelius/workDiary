---
title: "Wissensseiten"
topic: knowledge.pages
version: 1
keywords:
    - Wissensdatenbank
    - Wiki
    - Handbuch
    - Arbeitsanweisung
    - Pflichtlektüre
    - Lesebestätigung
    - Einarbeitung
    - Glossar
    - Suchlücke
    - Notfallhandbuch
    - Bildanleitung
    - Schwärzen
    - OneNote
    - Fassung
audience: []
modules:
    - module.knowledge
related:
    - knowledge.collections
    - knowledge.articles
    - work-queue.overview
---

Die **Wissensseiten** halten fest, wie Ihre Organisation arbeitet:
Arbeitsanweisungen, Anleitungen, Entscheidungen und das Notfallhandbuch. Sie
finden sie unter **Tagesgeschäft** → **Wissen & Doku** → **Wissen**, Reiter
**Wissensseiten**.

## Aufbau wie ein Notizbuch

- **Bereiche** sind die Notizbücher (etwa „Handbuch“ oder „IT“). Ein Bereich
  kann **eingeschränkt** sein: dann lesen ihn nur die gewählten Rollen, Teams
  und Personen.
- **Rubriken** gliedern einen Bereich, **Seiten** stehen darin, **Unterseiten**
  darunter (bis zu drei Ebenen).
- Text schreiben Sie in Markdown – mit Tabellen, Bildern aus den Anhängen
  (als Bildadresse `anhang:Dateiname.png`), **Hinweiskästen** (`:::hinweis`, `:::tipp`,
  `:::achtung`) und **Aufklappabschnitten** (`:::details Titel`), jeweils bis
  zu einer Zeile `:::`. Eingegebenes HTML wird als Text gezeigt.

## Bearbeiten, Fassungen und Freigabe

1. **Bearbeiten** öffnet den Text; jede gespeicherte Änderung ist eine neue
   **Fassung**. Hat jemand die Seite inzwischen gespeichert, meldet das
   Speichern einen Konflikt – nichts wird still überschrieben.
2. Die **verantwortliche Person** gibt die Fassung **frei**. Bis dahin sehen
   Leserinnen und Leser weiter die zuletzt freigegebene Fassung.
3. Unter **Fassungen** vergleichen Sie zwei Fassungen Zeile für Zeile und
   holen eine ältere als neue Fassung zurück.

## Aktuell halten

Unter **Verantwortung und Einordnung** legen Sie die verantwortliche Person,
ein **Prüfintervall** und ein **Gültigkeitsdatum** fest. **Stimmt, aktuell**
bestätigt die Seite und setzt das nächste Prüfdatum. Fällige Prüfungen,
endende Gültigkeit und wartende Freigaben stehen im **Arbeitsvorrat**; nach
Ablauf der Gültigkeit gilt die Seite als **veraltet**.

## Pflichtlektüre

Unter **Pflichtlektüre** ordnen Sie eine Seite allen Mitgliedern, einer Rolle,
einem Team oder einer Person zu – auf Wunsch als Teil der
**Einarbeitungsliste**. Wer sie lesen muss, bestätigt mit **Gelesen und
verstanden**; eine neu freigegebene Fassung verlangt eine neue Bestätigung.
Die Übersicht zeigt, wer welche Fassung bestätigt hat. Ihre eigene Lektüre
finden Sie unter **Meine Lektüre** und im Arbeitsvorrat.

## Finden

Die Suche durchsucht Titel und Text – und das **Glossar**: ein Kürzel oder
Synonym findet auch den Begriff („USt-VA“ findet „Umsatzsteuer-Voranmeldung“).
Suchbegriffe ohne Treffer landen als **Suchlücken** bei der Verwaltung,
gezählt ohne Ihren Namen. Seiten erscheinen auch in der globalen Suche. Die
Markierung **geändert** zeigt Seiten, die sich seit Ihrem letzten Besuch
geändert haben.

## Verknüpfen

Mit **Verknüpfen** hängen Sie eine Seite an Kunde, Projekt, Asset, Auftrag,
Ticket oder Prozedur. Am Kunden, Projekt und Asset steht sie dann im Cockpit,
an Auftrag, Ticket und Prozedur in der Karte **Wissensseiten**. Wie andere
Wissensinhalte lassen sich Seiten in **Sammlungen** legen.

## Bildanleitungen

Eine **Bildanleitung** besteht aus Schritten mit Bildschirmfoto und Text. Im
Schritt setzen Sie **Pfeil**, **Nummer**, **Rahmen** und **Text** als
Markierung. **Schwärzen** schreibt die gewählten Bereiche ins Bild; das
ungeschwärzte Original wird dabei verworfen und ist auch in älteren Fassungen
nicht mehr abrufbar. **Abspielen** zeigt die Anleitung Schritt für Schritt.

## Notfallhandbuch und PDF

Seiten mit dem Kennzeichen **Notfallhandbuch** bilden das Notfallhandbuch:
**Notfallhandbuch (PDF)** gibt alle freigegebenen Notfallseiten mit Fassung,
Freigabe und Verantwortung aus – damit es auch bei Ausfall der App vorliegt.
Jede Seite lässt sich einzeln **als PDF** ausgeben.

## Papierkorb und Import

Gelöschte Seiten liegen 30 Tage im **Papierkorb** und lassen sich
wiederherstellen; danach werden sie endgültig gelöscht. Endgültig löschen
dürfen nur Berechtigte. **Markdown importieren** übernimmt Dateien als
Entwürfe; Obsidian-Ordner und OneNote-Notizbücher übernehmen Sie über den
Import im Einstieg **Wissen** mit dem Ziel **Wissensseite**.
