---
title: "Kimai-Import"
topic: admin.kimai
version: 3
keywords:
    - Kimai
    - Zeiten importieren
    - Timesheets übernehmen
    - Kimai CSV
    - Kimai API
    - Wechsel von Kimai
    - Zeiten zurückbuchen
    - Rückbuchung
    - Benutzerzuordnung
    - Korrekturen zurückschreiben
    - stündlicher Import
    - selbst gehostetes Kimai
    - Private Adressen erlauben
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - admin.clockify
    - admin.toggl
    - finance.open-times
    - admin.organization-settings
    - admin.scheduler
---

Die Seite **Kimai-Import** übernimmt Zeiteinträge aus der Zeiterfassung Kimai
in WorkDiary – als hochgeladenen CSV-Export oder direkt über die Kimai-API.
Auf Wunsch bucht sie in WorkDiary erfasste Zeiten als Kimai-Timesheets zurück
und schreibt Korrekturen an importierten Zeiten nach Kimai zurück. Sie finden
die Seite im Systemmenü (Zahnrad **System** in der Kopfzeile) unter
**Plugins** → **Kimai-Import**, sobald das Plugin aktiv ist.

## Voraussetzungen

- Das Plugin ist für Ihre Organisation aktiviert: **System** → **Plugins** →
  **Plugins**, beim Eintrag Kimai **Aktivieren**. Aktivierung und
  Einstellungen gelten nur für die aktuelle Organisation.
- Seite und Einstellungen stehen Administratoren offen. Die
  **Zuordnungs-Inbox**, in der Sie offene Fälle lösen, steht zusätzlich der
  Buchhaltung offen.
- Für den CSV-Weg genügt ein Timesheet-Export aus Kimai.
- Für den API-Weg brauchen Sie die Adresse einer Kimai-2-Instanz und das
  API-Token eines Kimai-Benutzers (in Kimai unter Profil → API-Zugang). Sollen
  die Zeiten aller Personen kommen, braucht dieser Benutzer in Kimai das Recht
  view_other_timesheet.
- Die Kimai-Instanz muss öffentlich erreichbar sein, oder Sie geben eine
  selbst gehostete Instanz im eigenen Netz mit **Private Adressen erlauben**
  frei (siehe Einrichtung).

## Einrichtung

Die Zugangsdaten hinterlegen Sie auf der Seite **Plugins** über
**Konfigurieren** beim Eintrag Kimai:

1. **Kimai-Basis-URL**: die Adresse, unter der Sie Kimai im Browser öffnen –
   ohne /api am Ende.
2. **Private Adressen erlauben**: nur für eine selbst gehostete Instanz im
   eigenen Netz (zum Beispiel 192.168.x.x). Ohne diesen Schalter lehnt
   WorkDiary interne Adressen ab. Die Änderung wird protokolliert. Hat der
   Betreiber Ihrer Installation diese Freigabe gesperrt, bleibt der Schalter
   ohne Wirkung.
3. **Kimai API-Token**: das Token aus Kimai. Es wird verschlüsselt
   gespeichert; ein leeres Feld behält beim Speichern den bisherigen Wert.
4. **Zeiten aller Benutzer abrufen**: an (Standard), wenn der Token-Benutzer
   fremde Zeiten lesen darf; sonst kommen nur seine eigenen Zeiten.
5. **Sync-Zeitfenster (Tage)**: wie weit ein API-Import ohne Zeitraum
   zurückblickt (Standard 30 Tage), auch der stündliche.
6. **Abrechenbar übernehmen**: an übernimmt das Abrechenbar-Kennzeichen aus
   Kimai; aus markiert importierte Zeiten nie als abrechenbar.
7. **Einbenutzer-Modus** und **Zeiten buchen für Benutzer**: nur für
   Einzelarbeitsplätze, siehe unten. Den Benutzer wählen Sie aus der Liste.
8. Für die Rückbuchung **Rückbuchung aktivieren**, **Kimai-Activity-ID für
   Rückbuchungen** und optional **Sofort-Rückbuchung neuer Zeiten**; für die
   Rückrichtung bei Korrekturen **Korrekturen zurückschreiben**.
9. **Speichern**. Mit **Verbindung testen** im Dialog prüfen Sie den Zugang.
   Ohne Token meldet das Plugin den CSV-Modus – das ist kein Fehler.

## Zeiten importieren

**CSV hochladen:** Exportieren Sie die Zeiten in Kimai als CSV (Kimai → Zeiten
→ Export → CSV), wählen Sie die Datei auf der Seite **Kimai-Import** aus und
klicken Sie auf **Importieren**. WorkDiary erkennt die Spalten an der
Kopfzeile, auf Deutsch oder Englisch – etwa Datum, Von, Bis oder Dauer, Kunde,
Projekt, Tätigkeit, Beschreibung, Abrechenbar, Schlagworte und E-Mail. Komma
und Semikolon als Trennzeichen gehen beide, die Datei darf höchstens 20 MB
groß sein. Die Uhrzeiten gelten als Ortszeit Ihrer Organisation.

**Direkt aus der Kimai-API importieren:** Wählen Sie optional einen Zeitraum
(**Von**, **Bis**) und klicken Sie auf **Von API importieren**. Ohne Zeitraum
fragt WorkDiary die letzten Tage gemäß Sync-Zeitfenster ab. Laufende
Timesheets ohne Ende überspringt der Import.

**Stündlicher Import:** Sind Basis-URL und API-Token hinterlegt, läuft der
API-Import zusätzlich jede Stunde von selbst – über das Sync-Zeitfenster und
mit Löschabgleich (siehe unten). Den Takt ändern Sie unter **Geplante
Aufgaben** beim Eintrag „Kimai-Import“. Ohne API-Zugang gibt es keinen
automatischen Import; CSV-Dateien laden Sie immer hier hoch.

Nach einem Import auf dieser Seite meldet sie, wie viele Einträge angelegt,
übersprungen und offen in der Inbox sind, und wie viele keinem Benutzer
zugeordnet werden konnten.

## Kunden, Projekte und Personen zuordnen

- **Projekte:** WorkDiary legt beim Import keine Kunden oder Projekte an. Ein
  Eintrag wird gebucht, wenn sich sein Projekt findet: über eine gemerkte
  Zuordnung, beim API-Import über die Projektnummer aus Kimai, sonst über den
  gleichen Projektnamen beim passenden Kunden. Ist in den
  Organisationseinstellungen **Zeiten anhand von Schlüsselwörtern dem Projekt
  zuordnen** eingeschaltet, hilft zuletzt ein eindeutiger
  Schlüsselworttreffer.
- **Zuordnungs-Inbox:** Alles andere sammelt sich dort, gruppiert nach Kunde,
  Projekt und Tätigkeit. Die Karte **Zuordnungs-Inbox** auf der Seite zeigt
  die Zahl offener Gruppen, **Zur Inbox** führt hin. Dort wählen Sie Kunde,
  optional Endkunde, und Projekt und buchen die Gruppe. Die Zuordnung wird
  gemerkt; Folgeimporte buchen dann ohne Rückfrage.
- **Personen:** Jede Zeit gehört der Person, die sie in Kimai erfasst hat.
  Beim CSV-Import zählt die Spalte E-Mail, beim API-Import die
  E-Mail-Adresse des Kimai-Benutzers (fehlt sie, sein Benutzername). Beides
  vergleicht WorkDiary mit der E-Mail-Adresse der aktiven Benutzer; eine in
  der Inbox für einen Benutzernamen gemerkte Wahl gilt weiter. Ohne Treffer entsteht ein Fall „Unbekannter Benutzer“
  bzw. „Eintrag ohne Benutzersignal“ in der Inbox, statt dass die Zeit still
  beim Hauptbenutzer landet. Wählen Sie dort den Benutzer; die Wahl wird
  gemerkt.
- **Einbenutzer-Modus:** Nur wenn er eingeschaltet ist, bucht der Import
  Einträge ohne zuordenbare Person auf den Standard-Benutzer. Das ist der
  Benutzer aus **Zeiten buchen für Benutzer**, sonst der Inhaber der
  Organisation bzw. der erste Benutzer.

## Erneuter Import und Änderungen

- Bereits importierte Einträge legt ein erneuter Import nicht doppelt an.
- Beim API-Import erkennt WorkDiary jeden Eintrag an seiner Kimai-Nummer.
  Hat sich ein bekannter Eintrag in Kimai geändert (Beginn, Ende, Dauer,
  Beschreibung), übernimmt WorkDiary die Änderung. Ist die Zeit hier schon
  abgerechnet bzw. exportiert, ändert WorkDiary nichts; der Fall steht zur
  Kenntnisnahme in der Inbox.
- Fehlt bei einem API-Import mit **Zeiten aller Benutzer abrufen** ein früher
  importierter Eintrag im abgefragten Zeitraum, gilt er als in Kimai gelöscht.
  WorkDiary löscht die Zeit dann ebenfalls, sofern sie nicht abgerechnet ist;
  sonst entsteht ein Fall in der Inbox.
- Beim CSV-Weg erkennt WorkDiary einen Eintrag an Zeit, Kunde, Projekt,
  Tätigkeit, Beschreibung und E-Mail. Wurde er in Kimai geändert, entsteht
  beim erneuten Hochladen ein zusätzlicher Eintrag. CSV-Importe lösen keine
  Löschungen aus. Für laufende Abgleiche eignet sich der API-Weg.
- Schlagworte aus Kimai werden ergänzt, nie entfernt.

## Zeiten nach Kimai zurückbuchen

Der Bereich **Zeiten nach Kimai zurückbuchen** erscheint, sobald ein
API-Zugang hinterlegt und **Rückbuchung aktivieren** eingeschaltet ist.

- Zurückgebucht werden in WorkDiary erfasste Zeiten mit Beginn und Ende, die
  noch nicht exportiert sind und deren Projekt einem Kimai-Projekt zugeordnet
  ist. Diese Zuordnung entsteht beim API-Import – für automatisch gefundene
  Projekte und für API-Gruppen, die Sie in der Inbox buchen. Ein CSV-Import
  liefert sie nicht.
- Aus Kimai importierte Zeiten bucht WorkDiary nie zurück.
- Kimai verlangt je Timesheet eine Tätigkeit: Die **Kimai-Activity-ID für
  Rückbuchungen** – die Nummer der Tätigkeit in Kimai – gilt für alle
  zurückgebuchten Zeiten. Beschreibung und Abrechenbar-Kennzeichen gehen mit.
- **Nach Kimai exportieren** startet die Rückbuchung nach einer Rückfrage,
  optional für einen Zeitraum. Die Meldung nennt gebuchte, übersprungene und
  fehlgeschlagene Einträge.
- Eine zurückgebuchte Zeit gilt in WorkDiary als exportiert: Sie erscheint
  nicht mehr unter **Offene Zeiten** und wird hier nicht mehr abgerechnet.
  Mit **Sofort-Rückbuchung neuer Zeiten** geschieht das gleich beim Erfassen,
  ohne Gelegenheit zur Korrektur.

## Korrekturen zurückschreiben

Mit **Korrekturen zurückschreiben** überträgt WorkDiary Änderungen an über die
API importierten Zeiten (Beschreibung, Beginn, Ende, Dauer, Abrechenbar) und
deren Löschung nach Kimai. Vorher vergleicht WorkDiary den aktuellen Stand in
Kimai: Wurde der Eintrag dort inzwischen geändert, überschreibt WorkDiary
nichts, sondern legt einen Konflikt in der Inbox an. Abgerechnete und per CSV
importierte Zeiten werden nie zurückgeschrieben. Die Übertragung läuft im
Hintergrund und wird bei Fehlern wiederholt.

## Typische Fehler

- **Kein API-Zugang hinterlegt** statt des Importbereichs: Basis-URL oder
  Token fehlen in den Plugin-Einstellungen.
- „Keine Kimai-Activity-ID hinterlegt – Rückbuchung nicht möglich.“: Tragen
  Sie die Nummer einer Kimai-Tätigkeit ein.
- „Kein Projekt ist einem Kimai-Projekt zugeordnet“: Führen Sie zuerst einen
  API-Import aus oder buchen Sie die API-Gruppen in der Inbox.
- Viele Fälle „Unbekannter Benutzer“: Die E-Mail-Adressen in Kimai oder die
  E-Mail-Spalte passen nicht zu den E-Mail-Adressen in WorkDiary. Ordnen Sie
  jede Person einmal in der Inbox zu.
- Es kommen nur die Zeiten des Token-Benutzers: Ihm fehlt in Kimai das Recht
  view_other_timesheet, oder **Zeiten aller Benutzer abrufen** ist aus.
- Der CSV-Import legt nichts an: Die Kopfzeile braucht mindestens Datum sowie
  Bis oder Dauer.
- Die API ist nicht erreichbar: Die Seite zeigt den Fehler an, importiert
  wird nichts. Tragen Sie die Adresse ohne /api ein, prüfen Sie das Token und
  nutzen Sie **Verbindung testen**. Liegt die Instanz im internen Netz,
  meldet WorkDiary eine private Adresse; schalten Sie **Private Adressen
  erlauben** ein. Hat der Betreiber diese Freigabe gesperrt, braucht die
  Instanz eine öffentlich erreichbare Adresse. Häufen sich Fehler,
  deaktiviert WorkDiary das Plugin automatisch; nach der Behebung setzen Sie
  es auf der Seite **Plugins** mit **Reset & Reaktivieren** zurück.
