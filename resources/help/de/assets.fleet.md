---
title: "Assets & Fuhrpark"
topic: assets.fleet
version: 1
audience: []
related:
    - documents.manage
    - travel-expenses.manage
    - reports.overview
---

Assets und Fahrzeuge bilden betriebliche Objekte mit Status,
Zuständigkeit, Dokumenten und Wartungsinformationen ab. Tank- und
Ladelogs ergänzen den Verbrauchsverlauf.

Erfasse Stammdaten und eindeutige Kennungen, ordne Standort oder
Verantwortliche zu und pflege Wartungsintervalle sowie relevante
Dokumente. Statusänderungen sollten den tatsächlichen Lebenszyklus
widerspiegeln.

Vor Löschung oder Ausmusterung prüfen, ob offene Wartungen, Vorgänge,
Fahrten oder Dokumente verknüpft sind. Kritische Historie sollte
archiviert und nicht durch Überschreiben verloren gehen.

## Ausgabe und Rückgabe

Über das Panel „Ausgabe / Rückgabe" auf der Asset-Detailseite wird ein
Gerät an eine Person oder ein Team ausgegeben, optional mit
Auftragsbezug und erwarteter Rückgabe. Pro Asset gibt es höchstens eine
offene Zuweisung; ein bereits ausgegebenes oder wegen Defekt gesperrtes
Asset kann nicht erneut ausgegeben werden. Bei der Rückgabe wird das
Asset wieder verfügbar. Überschreitet eine Ausgabe die erwartete
Rückgabe, erscheint ein Überfällig-Hinweis und der Fristen-Scanner
benachrichtigt die ausleihende Person bzw. die Teamleitung.

## Defekte und Sperren

Im Panel „Defekte / Sperren" lassen sich Mängel mit Schweregrad
erfassen. Ist „Asset sperren" gesetzt, blockiert der offene Defekt jede
weitere Ausgabe, bis er erledigt oder ausgebucht wird. Für das Erledigen
oder Ausbuchen ist eine Lösungsnotiz erforderlich.

## Objektakte (Lebenszyklus)

Die „Objektakte" bündelt den gesamten Lebenszyklus eines Assets in einer
zusammenhängenden, druckbaren Ansicht: Stammdaten, Standort und Raum,
abgeleiteter Lebenszyklus-Status (in Betrieb, ersetzt oder stillgelegt),
Inbetriebnahme, Außerbetriebnahme und Garantie. Darunter erscheinen
Wartungen, Ausgaben und Rückgaben, Defekte und Sperren, verknüpfte
Aufträge, Protokolle, Materialeinsätze, offene Punkte und Anhänge sowie
der vollständige Lebenszyklus-Verlauf.

Die Akte ist über den Button „Objektakte" auf der Asset-Detailseite
erreichbar und lässt sich über die Druckfunktion des Browsers als
Dokument ausgeben (Anhängen von „?print=1" öffnet den Druckdialog
direkt). Der Lebenszyklus-Status wird aus Status, Außerbetriebnahme und
Garantie abgeleitet – es gibt keine separate Pflege.
