---
title: "Fernwartung"
topic: admin.remote-support
version: 1
audience:
    - admin
related:
    - admin.support
    - admin.plugins
    - assets.fleet
---

Die Fernwartung übernimmt Sitzungsberichte aus AnyDesk und
TeamViewer und überführt sie in Zeiteinträge. Sitzungen werden über
die Geräte-ID (AnyDesk-/TeamViewer-ID) einem Asset (Arbeitsplatz,
Server, Notebook) zugeordnet.

Posteingang (offene Anfragen):

- Hier sammeln sich Sitzungen, deren Geräte-ID noch keinem Asset der
  Organisation zugeordnet ist. Sie warten auf eine Entscheidung.
- Einträge sind nach Anbieter und Geräte-ID gruppiert (mit Anzahl,
  Dauer und Zeitraum).

Aktionen:

- **Bestehendem Gerät zuordnen**: verknüpft die Geräte-ID mit einem
  vorhandenen Asset; offene Sitzungen werden sofort als Zeiteinträge
  gebucht.
- **Neues Gerät anlegen**: legt ein neues Asset (Kategorie, Kunde)
  an und ordnet die Geräte-ID in einem Schritt zu.
- **Sitzungen zuweisen (geteiltes Gerät)**: bei Geräten mehrerer
  Kunden lassen sich einzelne Sitzungen gezielt einem bestimmten
  Kunden/Projekt zuordnen – verhindert Fehlbuchungen.
- **Verwerfen**: lehnt eine ganze Geräte-ID-Gruppe ab; die Sitzungen
  werden nicht gebucht.
- **Einzelsitzung verwerfen**: verwirft ausgewählte Sitzungen eines
  geteilten Geräts.

Sicherheit und Risiken:

- API-Zugangsdaten der Anbieter liegen in den Plugin-Einstellungen
  der Organisation. Das System liest Sitzungsberichte – es vergibt
  keinen direkten Fernzugriff.
- Geteilte Geräte erfordern sorgfältige Zuordnung je Sitzung, um
  kundenübergreifende Fehlbuchungen zu vermeiden.
- **Verworfene Sitzungen sind endgültig entfernt** und werden nicht
  in Zeiteinträge überführt – nicht rückholbar.

Berechtigung: Der Posteingang ist Administratoren vorbehalten.
