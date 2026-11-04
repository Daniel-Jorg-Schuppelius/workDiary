---
title: "Fernwartung"
topic: admin.remote-support
version: 4
keywords:
    - AnyDesk
    - TeamViewer
    - RustDesk
    - Splashtop
    - pcvisit
    - API-Token
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

Die Fernwartung übernimmt Sitzungsberichte aus AnyDesk, TeamViewer,
RustDesk, Splashtop und pcvisit und überführt sie in Zeiteinträge.
Sitzungen werden über die Geräte-ID des Anbieters einem Gerät (Asset,
z. B. Arbeitsplatz, Server, Notebook) zugeordnet. Über **Sitzungen
importieren** lesen Sie Sitzungsexporte (AnyDesk, RustDesk Server Pro)
zusätzlich über den zentralen Import ein; die Kopfzeile verrät den
Anbieter, eine Spalte **Anbieter** überstimmt sie.

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

Anbieter einrichten (Plugin-Einstellungen der Organisation, je Anbieter
ein Schalter):

- **AnyDesk**: Lizenz-ID und API-Passwort der Lizenz; Geräte-ID ist die
  AnyDesk-ID des Rechners.
- **TeamViewer**: Script-Token mit Berechtigung für Verbindungsberichte;
  Geräte-ID ist die TeamViewer-ID.
- **RustDesk**: Der kostenlose RustDesk-Server speichert keine
  Verbindungen. Sitzungen liefert nur ein API-Server, den die Clients
  unter „API-Server“ eingetragen haben. Bei **Server Pro** tragen Sie die
  Adresse der Web-Konsole und ein API-Token ein (Einstellungen → Tokens →
  Erstellen, Berechtigung „Audit Log“); beim **Open-Source-API-Server**
  (rustdesk-api) die Adresse mit Port sowie Benutzername und Passwort
  eines Administrators, die Serverzeitzone nur, wenn der Server nicht in
  der Zone der Organisation läuft. Läuft der Server im eigenen Netz,
  schalten Sie **Private Netzadressen erlauben** ein. Geräte-ID ist die
  RustDesk-ID des Rechners (ohne Leerzeichen). Noch laufende Verbindungen
  werden erst nach ihrem Ende übernommen.
- **Splashtop**: API-Token aus der Web-Konsole (Kontoeinstellungen → Web
  API Tokens; nur Team-Owner und Super-Admins, die Open API gehört zu
  Splashtop Enterprise). Wählen Sie die Region Ihres Kontos; die Team-ID
  ermittelt das System aus dem Token. Sitzungsarten sind kommagetrennt
  wählbar (Vorgabe: unbeaufsichtigt und SOS). Geräte-ID ist die
  Computer-UUID aus Splashtop; die Inbox zeigt den Rechnernamen dazu.
  Andere Tarife nutzen den CSV-Export der Logs-Seite über den zentralen
  Import.
- **pcvisit**: Nutzer-API-Token aus der pcvisit Web App
  (Kontoeinstellungen) und ein Integrator-Token, den pcvisit an den
  Softwarehersteller vergibt (Betreiber-Einstellung; je Organisation nur
  bei eigener Vereinbarung eintragen). Übernommen werden abgeschlossene
  Sitzungen mit der in pcvisit erfassten Dauer; Geräte-ID ist die
  Computer-ID aus pcvisit, der Endkunde steht in der Notiz.

Fällt ein Anbieter aus, laufen die anderen weiter; die Meldung erscheint
im Plugin-Status und im Importergebnis.

Sicherheit und Risiken:

- API-Zugangsdaten der Anbieter liegen in den Plugin-Einstellungen
  der Organisation. Das System liest Sitzungsberichte – es vergibt
  keinen direkten Fernzugriff.
- Mehrkundengeräte erfordern sorgfältige Zuordnung je Sitzung, um
  kundenübergreifende Fehlbuchungen zu vermeiden.
- **Verworfene Verbindungen und Sitzungen werden nicht gebucht**; die
  Seite bietet keinen Weg, sie zurückzuholen.

Berechtigung: Die Seite ist Administratoren vorbehalten.
