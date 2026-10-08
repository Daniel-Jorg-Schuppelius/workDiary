---
title: "Fernwartung"
topic: admin.remote-support
version: 3
keywords:
    - AnyDesk
    - TeamViewer
    - Fernzugriff
    - Remote-Sitzung
    - Fernwartungssitzung buchen
    - Sitzungsbericht
    - Geräte-ID zuordnen
    - Support-Sitzung
    - Remote Desktop
    - Fernsupport
    - Sitzungen als Zeit buchen
audience:
    - admin
related:
    - admin.support
    - admin.plugins
    - assets.fleet
---

Die Fernwartung übernimmt Sitzungsberichte aus AnyDesk und TeamViewer
und überführt sie in Zeiteinträge. Sitzungen werden über die Geräte-ID
(AnyDesk-/TeamViewer-ID) einem Gerät (Asset, z. B. Arbeitsplatz,
Server, Notebook) zugeordnet. Über **Sitzungen importieren** lesen Sie
AnyDesk-Sitzungen zusätzlich über den zentralen Import ein.

Die Seite **Fernwartung – unzugeordnete Verbindungen** hat zwei Reiter;
das Suchfeld findet Geräte-ID, Alias, Gerät oder Notiz.

Reiter **Unzugeordnete Geräte**:

- Hier sammeln sich IDs, die in den Reports auftauchen, aber noch
  keinem Gerät der Organisation zugeordnet sind – mit Anzahl der
  Sitzungen, Dauer und Zeitraum.
- Gibt es einen **Vorschlag** (passender Kunde oder passendes Gerät),
  übernehmen Sie ihn mit **Übernehmen**.
- **Bestehendes Gerät**: unter **Gerät auswählen** ein vorhandenes Gerät
  wählen und **Zuordnen**; die gespeicherten Sitzungen werden sofort
  als Zeiteinträge gebucht.
- **Neues Gerät**: **Name**, **Kategorie**, **Kunde** und optional
  **Fremdkunde (Endkunde)** angeben und **Anlegen & zuordnen**.
- **Mehrkundengerät**: Dieses Kästchen in beiden Reitern kennzeichnet
  ein Gerät, das für mehrere Kunden genutzt wird. Seine Sitzungen
  werden dann nicht automatisch gebucht, sondern im zweiten Reiter je
  Kunde.
- **Verwerfen**: lehnt alle Verbindungen einer ID ab; sie werden nicht
  gebucht.

Reiter **Sitzungen zuordnen** (Mehrkundengeräte):

- Sitzungen markieren, **Kunde**, optional **Fremdkunde (Endkunde)** und
  **Projekt** wählen und **Markierte buchen** – so landen die Zeiten
  beim richtigen Kunden.
- **Markierte intern buchen** bucht Sitzungen ohne Kunden auf das
  interne Wartungsprojekt.
- **Markierte verwerfen** verwirft einzelne Sitzungen.

Sicherheit und Risiken:

- API-Zugangsdaten der Anbieter liegen in den Plugin-Einstellungen
  der Organisation. Das System liest Sitzungsberichte – es vergibt
  keinen direkten Fernzugriff.
- Mehrkundengeräte erfordern sorgfältige Zuordnung je Sitzung, um
  kundenübergreifende Fehlbuchungen zu vermeiden.
- **Verworfene Verbindungen und Sitzungen werden nicht gebucht**; die
  Seite bietet keinen Weg, sie zurückzuholen.

Berechtigung: Die Seite ist Administratoren vorbehalten.
