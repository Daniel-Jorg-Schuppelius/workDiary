---
title: "Sammlungen"
topic: knowledge.collections
version: 6
audience: []
related:
    - knowledge.articles
    - communication.notes
    - ideas.overview
    - documents.manage
    - learning.overview
---

Eine **Sammlung** ordnet Inhalte über Modulgrenzen hinweg: Notizen,
Ideenlandkarten, Wissensartikel, Dokumente, Lernkurse und Lernpfade können
gemeinsam in einer Sammlung liegen. Die Zugehörigkeit zu Kunde, Auftrag oder
Projekt bleibt dabei führend — die Sammlung ist die zusätzliche, frei wählbare
Ordnung für alles, was sich keinem einzelnen Vorgang zuordnen lässt.

Typischer Ablauf:

1. Im Einstieg **Wissen** über **Sammlungen verwalten** eine Sammlung
   anlegen, bei Bedarf als Untersammlung einer anderen. Sammlungen lassen sich
   bis zu fünf Ebenen tief schachteln und später umhängen.
2. Auf der Detailseite eines Inhalts **Zur Sammlung hinzufügen** wählen.
   Ein Inhalt darf in mehreren Sammlungen liegen; es entsteht keine Kopie.
3. Nicht mehr benötigte Sammlungen **archivieren** statt löschen — die
   Zuordnungen bleiben erhalten und lassen sich wiederherstellen.

**Eine Sammlung gibt keinen Zugriff.** Jede Person sieht darin nur, was sie
auch sonst sehen darf: vertrauliche Notizen anderer, nicht geteilte
Ideenkarten, vertrauliche Dokumente und Lerninhalte ohne das Modul der
Lernplattform bleiben verborgen — auch ihre Anzahl wird nicht angezeigt.
**Private** Sammlungen sieht nur, wer sie angelegt hat.

## Einstieg „Wissen“

Die Seite **Wissen** zeigt Notizen, Ideenlandkarten, Wissensartikel, Dokumente,
Lernkurse und Lernpfade in einer Liste — wahlweise als Kacheln. Links steht der
Sammlungsbaum (eine Sammlung schließt ihre Untersammlungen ein), oben filtern
Titel, Art und Kunde, die Schlagwort-Badges grenzen weiter ein.

**Wissen** ist die einzige Tür zum Bereich: Notizen, Wissensarchiv,
Ideenlandkarten und Dokumente hängen als Reiter darüber, die Sammlungen sind
der Verwaltungsmodus. Jeder Reiter behält seine eigenen Spalten und Aktionen —
Fristen und Freigabe stehen also weiter bei den Dokumenten, die Freigabe von
Artikeln weiter im Wissensarchiv.

Der Wechsel nimmt den Filter mit: Wer einen Kunden gewählt hat und auf
**Dokumente** geht, sieht dessen Dokumente. Übernommen wird, was der Reiter
auch auswerten kann — ein Wissensartikel gehört keinem Kunden, dort bleibt
die Auswahl also außen vor.

Mehrere Inhalte markieren und **Hinzufügen** legt sie auf einmal in eine
Sammlung — das geht genauso in der Trefferliste der **Suche** für Notizen,
Wissensartikel und Lernkurse.

## Notiz in Wissensartikel überführen

Im Lesedialog einer Notiz legt **In Wissensartikel überführen** einen Entwurf im
Wissensarchiv an: Betreff wird Titel, Text wird Problembeschreibung, die
Schlagwörter wandern mit. Der Artikel zeigt unter „Hier erwähnt in“ die Notiz
als Herkunft; ein zweiter Klick öffnet den vorhandenen Artikel, statt einen
neuen anzulegen. Vertrauliche Notizen lassen sich nicht überführen.

## Übernahme aus Obsidian und OneNote

Administratoren übernehmen vorhandene Notizen **einmalig oder auf Anstoß** —
nur lesend, ohne Rückschreiben und ohne laufenden Abgleich. Im Einstieg
**Wissen** stehen dafür zwei Knöpfe:

- **Obsidian übernehmen** liest einen Obsidian-Tresor über eine vorhandene
  Ordner-Anbindung des Cloud-Dokumenteingangs (Nextcloud, OneDrive, Dropbox,
  Google Drive). Den Pfad des Tresors geben Sie relativ zum Stammordner der
  Anbindung an. Unterordner werden Sammlungen, Schlagwörter aus dem YAML-Kopf
  und `#schlagwörter` im Text wandern mit, `[[Wikilinks]]` werden Verweise.
  `.obsidian/` und `.trash/` bleiben außen vor.
- **OneNote übernehmen** erscheint erst, wenn die Organisation in den
  Einstellungen des Microsoft-365-Plugins **OneNote-Übernahme erlauben**
  eingeschaltet und im Microsoft-365-Panel **OneNote verbunden** hat. Die
  Verbindung fragt dafür den zusätzlichen, nur lesenden Bereich Notes.Read
  an. Notizbuch wird Sammlung, Abschnittsgruppen und Abschnitte werden
  Untersammlungen, jede Seite eine Notiz oder ein Artikel; der Seiteninhalt wird
  als Text übernommen.

Übernommen wird wahlweise als Notiz oder als Wissensartikel-Entwurf. Jeder
übernommene Inhalt zeigt seine Herkunft („Übernommen aus …“). Ein weiterer Lauf
überspringt, was schon da ist, und übernimmt nur Neues — höchstens 300 neue
Inhalte je Lauf.

## Verweise und Rückverweise

Auf den Detailseiten dieser Inhalte zeigt die Karte **Verweise**, worauf ein
Inhalt verweist und wo er erwähnt wird:

- **Verweis setzen** verbindet den Inhalt mit einer Notiz, einer Ideenkarte,
  einem Wissensartikel, einem Dokument, einem Lernkurs oder einem Lernpfad.
  Die Auswahl lässt sich über das Suchfeld eingrenzen.
- **Hier erwähnt in** listet, gruppiert nach Art, alles, was auf die Seite
  zeigt — auch die Verknüpfungen aus der Wissensbasis und die aus
  Ideenknoten überführten oder verknüpften Ziele. Auch Kunden, Projekte und
  Aufträge zeigen diese Liste, sobald etwas auf sie verweist.
- **Verweis lösen** entfernt nur von Hand gesetzte Verweise; Verknüpfungen
  der Wissensbasis und der Ideenlandkarten pflegen Sie dort.

Wie bei Sammlungen gibt ein Verweis keinen Zugriff: Eine Quelle erscheint
nur, wenn Sie sie auch sonst öffnen dürfen.

Zum Anlegen und Befüllen von Sammlungen und zum Setzen von Verweisen braucht
es das Recht „Sammlungen und Verweise pflegen“; zum Ansehen der Sammlungen
„Sammlungen sehen“.
