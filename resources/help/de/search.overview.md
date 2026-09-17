---
title: "Suche und Tätigkeitsrecherche"
topic: search.overview
version: 3
audience: []
related: []
---

Die Suche beantwortet vor allem eine Frage: **Was wurde wann bei welchem Kunden
gemacht?** Sie durchsucht Zeiteinträge (auch Fernwartungs-Notizen und
Stundenzettel-Positionen), Aufträge mit Kommentaren, Stundenzettel-Notizen,
Tickets, Protokolle, offene Punkte, Kommunikationsnotizen und Wissensartikel —
jeweils mit Projekt, Endkunde und Kunde als Kontext. Darunter stehen die
Stammdaten wie Kunden, Projekte, Objekte, Spesen und Dokumente.

## So wird gesucht

- Alle Wörter müssen vorkommen, egal wo: „smtp exchange" findet den Zeiteintrag
  „Sendeconnector auf SMTP umgestellt" im Projekt „Exchange-Migration".
- Gesucht wird am Wortanfang: „exch" findet „Exchange" und „Exchangeserver".
- Wörter in Anführungszeichen („smtp relay") müssen direkt hintereinander stehen.
- Ein Minus schließt aus: „drucker -toner".
- Füllwörter wie „wann haben wir … gemacht" werden ignoriert.
- Kommt ein Wort nirgends vor, sucht die Suche ähnlich geschriebene Wörter mit
  und zeigt das an. „Ähnliche Schreibweisen" nimmt Varianten auch für bekannte
  Wörter mit — hilfreich bei Tippfehlern in den Notizen selbst.
- Synonyme pflegt die Administration unter System › Organisation ›
  Such-Synonyme.

## Übersicht und Filter

„Kunden & Endkunden" zeigt, bei wem es Treffer gibt und in welchem Zeitraum; ein
Klick filtert darauf. In der Filterleiste stehen Quelle, Zeitraum, Person, Kunde
(inklusive seiner Endkunden), Endkunde und Sortierung. Ohne Suchbegriff, aber
mit Kunde, Endkunde oder Projekt erscheinen die neuesten Tätigkeiten.

„Schlagwörter in den Treffern" zählt die Schlagwörter der gefundenen Einträge;
ein Klick grenzt auf eines ein, das Kreuz am Filter hebt es wieder auf. Wer
Sammlungen sehen darf, findet außerdem **Sammlung** in der Filterleiste — sie
schließt ihre Untersammlungen ein. Beide Filter reichen auch ohne Suchbegriff.
Gezählt werden nur Einträge, die Sie öffnen dürfen; Schlagwörter vertraulicher
Inhalte anderer erscheinen deshalb nicht.

## Einstiege

Kundenseite, Endkundenseite und Projekt haben ein eigenes Suchfeld bzw. einen
Knopf. Ein erkannter Anrufer öffnet die Suche direkt mit seinem Kundenfilter.
Mit aktivem KI-Modul fasst „KI-Antwort" die Treffer zusammen.

Die Trefferliste respektiert Modul- und Berechtigungsgrenzen — angezeigt wird
nur, was die eigene Rolle auch öffnen darf.
