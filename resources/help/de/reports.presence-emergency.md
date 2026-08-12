---
title: "Notfall-Anwesenheitsliste"
topic: reports.presence-emergency
version: 1
audience: []
related:
    - reports.overview
---

Die Notfall-Anwesenheitsliste zeigt zeitpunktbezogen, wer im Gebäude,
außer Haus oder abwesend ist — gedacht für Evakuierungen, Brandfälle
und andere Notlagen. Ohne Zeitangabe gilt der aktuelle Moment; über den
Zeitpunkt-Filter lässt sich die Lage rückwirkend rekonstruieren, soweit
die Datenlage es erlaubt.

Die Gruppen entstehen aus vorhandenen Daten: „Im Gebäude" aus offenen
Anwesenheitsstempeln, „Außer Haus" aus laufenden Kundeneinsätzen und
Zeitbuchungen, „Abwesend" aus genehmigtem Urlaub und aktiven
Krankmeldungen. Personen ohne jedes Signal stehen unter „Ohne Meldung" —
ihr Status ist unbekannt und muss vor Ort geklärt werden.

Der Standortfilter ordnet über Stempelterminals zu. Personen, die ohne
Terminal gestempelt haben (z. B. im Browser), erscheinen gesondert als
„Anwesend ohne Standortzuordnung" und werden nie ausgeblendet — im
Zweifel gehört eine Person lieber zu viel auf die Liste als eine zu
wenig.

Die Liste ist eine Ableitung, keine eigene Datenquelle: Widersprüche
werden angezeigt, nie automatisch korrigiert. Jeder Abruf wird
protokolliert; der Zugriff erfordert eine eigene Berechtigung. Für den
Aushang stehen Druckansicht und PDF-Export bereit — Datenstand und
Erstellungszeitpunkt sind auf jedem Ausdruck sichtbar.
