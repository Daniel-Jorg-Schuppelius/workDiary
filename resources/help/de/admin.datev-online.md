---
title: "DATEV-Online verbinden"
topic: admin.datev-online
version: 1
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
---

Die Anbindung überträgt abgeschlossene Buchungsstapel und Belegbilder
direkt an DATEV Unternehmen online — ohne Datei-Download und Upload von
Hand.

**Voraussetzungen:** Eine App-Registrierung bei DATEV (Client-ID und
Client-Secret aus dem DATEV-Entwicklerportal) und ein DATEV-Benutzer mit
Zugriff auf den Mandanten. Tragen Sie die Zugangsdaten in den
Plugin-Einstellungen ein und die dort genannte Weiterleitungsadresse in
der DATEV-App-Registrierung. Solange DATEV den Produktivbetrieb nicht
freigegeben hat, bleibt „Sandbox verwenden“ eingeschaltet.

**Anmelden und Mandant wählen:** „Mit DATEV anmelden“ führt zur Anmeldung
bei DATEV und zurück. Danach wählen Sie den Mandanten
(Beraternummer-Mandantennummer) aus der Liste der für Sie freigegebenen
Mandanten.

**Buchungsstapel:** Abgeschlossene Stapel aus dem DATEV-Export lassen sich
mit „An DATEV übertragen“ als EXTF-Import übergeben. Berater- und
Mandantennummer im Stapel müssen zum verbundenen Mandanten passen. DATEV
verarbeitet den Import im Hintergrund; den Stand fragt der nächtliche Lauf
ab, sofort mit „Importstatus abfragen“. Ein fehlgeschlagener Import lässt
sich nach der Korrektur erneut übertragen.

**Belegbilder:** Eingeschaltet überträgt der nächtliche Lauf ausgestellte
Rechnungen als „Rechnungsausgang“ und eingegangene Rechnungen als
„Rechnungseingang“ — jeden Beleg genau einmal und erst ab dem
eingestellten Datum (vorgegeben: der Tag der Anmeldung). „Jetzt
übertragen“ startet den Lauf sofort.

**Trennen:** Die Verbindung lässt sich jederzeit trennen; bereits
übertragene Daten bleiben in DATEV.
