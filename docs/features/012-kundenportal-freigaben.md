# Kundenportal und Freigaben

## Status

In Progress

## Ziel

Kunden oder externe Ansprechpartner sollen ausgewählte Aufträge, Protokolle,
Abnahmen, offene Punkte, Termine und Dokumente sicher einsehen, bestätigen oder
kommentieren können. Der Zugriff soll kontrolliert, nachvollziehbar und auf den
jeweiligen Kontext begrenzt sein.

## Warum

Abnahme, Rückfrage und Nachweis passieren nicht nur intern. Wenn Kunden
Protokolle, Fotos oder offene Punkte direkt bestätigen können, sinken
Abrechnungsstreitigkeiten und Nachfragen. Gleichzeitig entsteht ein sauberer
Kommunikations- und Freigabenachweis.

## MVP

- Signierte Links für einzelne Protokolle, Stundenzettel oder Abnahmen.
- Kundenansicht mit Auftrag, Fotos, Zeiten, Material, offenen Punkten und PDF.
- Kommentar- oder Rückfragefunktion mit Benachrichtigung.
- Freigabe, Ablehnung oder Abnahme mit Unterschrift und Zeitstempel.
- Ablaufdatum und Widerruf für externe Links.
- Audit-Log für externe Zugriffe und Entscheidungen.

## Akzeptanzkriterien

- Externe sehen nur freigegebene Inhalte.
- Jede Kundenentscheidung wird mit Kontext protokolliert.
- Abgelehnte Abnahmen erzeugen offene Punkte oder Rückfragen.
- Freigaben können für Rechnung und Archiv genutzt werden.

## Abhängigkeiten

- Dokumentation und Abnahmeprotokolle
- Aufzeichnung und Zeiterfassung als Kernprodukt
- `Customer`
- `Timesheet`
- `Attachment`
- PDF-Ausgabe
- Benachrichtigungen
- Audit

## GitHub Issues

- TBD
