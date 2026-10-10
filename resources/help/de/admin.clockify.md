---
title: "Clockify-Import"
topic: admin.clockify
version: 3
keywords:
    - Clockify
    - Zeiten importieren
    - Detailed Report
    - Clockify CSV
    - Clockify API
    - Wechsel von Clockify
    - Zeiten übertragen
    - Fernwartung nach Clockify
    - Benutzerzuordnung
    - Korrekturen zurückschreiben
    - stündlicher Import
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - admin.kimai
    - admin.toggl
    - admin.scheduler
    - admin.organization-settings
---

Die Seite **Clockify-Import** übernimmt Zeiteinträge aus Clockify in
WorkDiary – als hochgeladenen Detailed Report (CSV) oder direkt über die
Clockify-API. Auf Wunsch überträgt sie in WorkDiary erfasste Zeiten nach
Clockify, etwa Fernwartungssitzungen, und schreibt Korrekturen an importierten
Zeiten zurück. Sie finden die Seite im Systemmenü (Zahnrad **System** in der
Kopfzeile) unter **Plugins** → **Clockify-Import**, sobald das Plugin aktiv
ist.

## Voraussetzungen

- Das Plugin ist für Ihre Organisation aktiviert: **System** → **Plugins** →
  **Plugins**, beim Eintrag Clockify **Aktivieren**. Aktivierung und
  Einstellungen gelten nur für die aktuelle Organisation.
- Seite und Einstellungen stehen Administratoren offen. Die
  **Zuordnungs-Inbox**, in der Sie offene Fälle lösen, steht zusätzlich der
  Buchhaltung offen.
- Für den CSV-Weg genügt ein Detailed Report aus Clockify.
- Für den API-Weg brauchen Sie einen API-Key (in Clockify unter Profil →
  Advanced → API). Im kostenlosen Clockify-Tarif sind nur 30 API-Anfragen pro
  Stunde erlaubt; dort ist der CSV-Weg der empfohlene.

## Einrichtung

Die Zugangsdaten hinterlegen Sie auf der Seite **Plugins** über
**Konfigurieren** beim Eintrag Clockify:

1. **Clockify API-Key**: der Schlüssel aus Clockify. Er wird verschlüsselt
   gespeichert; ein leeres Feld behält beim Speichern den bisherigen Wert.
2. **Workspace-ID**: optional. Leer nimmt WorkDiary den Standard-Workspace des
   API-Keys.
3. **API-Basis-URL** und **Reports-API-Basis-URL**: nur ändern, wenn Ihr Konto
   auf einer regionalen Clockify-Instanz liegt; der Hilfetext im Dialog nennt
   ein Beispiel.
4. **Sync-Zeitfenster (Tage)**: wie weit ein API-Import ohne Zeitraum –
   auch der stündliche – zurückblickt und wie weit die stündliche Übertragung
   reicht (Standard 30 Tage).
5. **Abrechenbar übernehmen**: an übernimmt das Abrechenbar-Kennzeichen aus
   Clockify; aus markiert importierte Zeiten nie als abrechenbar.
6. **Einbenutzer-Modus** und **Zeiten buchen für Benutzer**: nur für
   Einzelarbeitsplätze, siehe unten. Den Benutzer wählen Sie aus der Liste.
7. Optional **Zeit-Übertragung aktivieren**, **Korrekturen
   zurückschreiben** und **Webhook-Secret** (siehe „Webhook“).
8. **Speichern**. Mit **Verbindung testen** im Dialog prüfen Sie den Zugang.
   Ohne API-Key meldet das Plugin den CSV-Modus – das ist kein Fehler.

## Zeiten importieren

**CSV hochladen:** Exportieren Sie in Clockify den Detailed Report als CSV
(Clockify → Reports → Detailed → Export → CSV), wählen Sie die Datei auf der
Seite **Clockify-Import** aus und klicken Sie auf **Importieren**. WorkDiary
erkennt die Spalten an der Kopfzeile – Project, Client, Description, Task,
Email, Tags, Billable, Start Date, Start Time, End Date, End Time sowie Duration
(h) oder Duration (decimal). Nicht benötigte Spalten dürfen fehlen; Start Date
und entweder eine Endzeit oder eine Dauer sind Pflicht. Komma und Semikolon
gehen beide, die Datei darf höchstens 20 MB groß sein.

**Direkt aus der Clockify-API importieren:** Wählen Sie optional einen
Zeitraum (**Von**, **Bis**) und klicken Sie auf **Von API importieren**.
WorkDiary holt die Zeiteinträge aller Benutzer des Workspace; ohne Zeitraum
die letzten Tage gemäß Sync-Zeitfenster. Laufende Einträge ohne Ende
überspringt der Import.

**Stündlicher Import:** Ist ein API-Key hinterlegt, läuft der API-Import
zusätzlich jede Stunde von selbst – über das Sync-Zeitfenster und mit
Löschabgleich (siehe unten). Den Takt ändern Sie unter **Geplante Aufgaben**
beim Eintrag „Clockify-Import“. Jeder Lauf verbraucht Anfragen aus dem
Kontingent Ihres Clockify-Tarifs. Ohne API-Key gibt es keinen automatischen
Import; CSV-Dateien laden Sie immer hier hoch.

Meldet die Clockify-API einen Fehler, zeigt die Seite ihn an; es wird dann
nichts importiert. Nach einem Import auf dieser Seite meldet sie, wie viele
Einträge angelegt, übersprungen und offen in der Inbox sind, und wie viele
keinem Benutzer zugeordnet werden konnten.

## Kunden, Projekte und Personen zuordnen

- **Projekte:** WorkDiary legt beim Import keine Kunden oder Projekte an. Ein
  Eintrag wird gebucht, wenn sich sein Projekt findet: über eine gemerkte
  Zuordnung, sonst über den gleichen Projektnamen beim passenden Kunden. Ist
  in den Organisationseinstellungen **Zeiten anhand von Schlüsselwörtern dem
  Projekt zuordnen** eingeschaltet, hilft zuletzt ein eindeutiger
  Schlüsselworttreffer.
- **Zuordnungs-Inbox:** Alles andere sammelt sich dort, gruppiert nach
  Client, Projekt und Task. Die Karte **Zuordnungs-Inbox** auf der Seite zeigt
  die Zahl offener Gruppen, **Zur Inbox** führt hin. Dort wählen Sie Kunde,
  optional Endkunde, und Projekt und buchen die Gruppe. Die Zuordnung wird
  gemerkt; Folgeimporte buchen dann ohne Rückfrage.
- **Personen:** Jede Zeit gehört der Person, die sie in Clockify erfasst hat.
  WorkDiary vergleicht deren E-Mail-Adresse (Spalte Email bzw. Angabe der API)
  mit der E-Mail-Adresse der aktiven Benutzer. Ohne Treffer entsteht ein Fall
  „Unbekannter Benutzer“ bzw. „Eintrag ohne Benutzersignal“ in der Inbox,
  statt dass die Zeit still beim Hauptbenutzer landet. Wählen Sie dort den
  Benutzer; die Wahl wird gemerkt.
- **Einbenutzer-Modus:** Nur wenn er eingeschaltet ist, bucht der Import
  Einträge ohne zuordenbare Person auf den Standard-Benutzer. Das ist der
  Benutzer aus **Zeiten buchen für Benutzer**, sonst der Inhaber der
  Organisation bzw. der erste Benutzer.

## Erneuter Import und Änderungen

- Bereits importierte Einträge legt ein erneuter Import nicht doppelt an.
- Beim API-Import erkennt WorkDiary jeden Eintrag an seiner Clockify-Kennung.
  Hat sich ein bekannter Eintrag in Clockify geändert (Beginn, Ende, Dauer,
  Beschreibung), übernimmt WorkDiary die Änderung. Ist die Zeit hier schon
  abgerechnet bzw. exportiert, ändert WorkDiary nichts; der Fall steht zur
  Kenntnisnahme in der Inbox.
- Fehlt bei einem API-Import ein früher importierter oder übertragener
  Eintrag im abgefragten Zeitraum, gilt er als in Clockify gelöscht. WorkDiary
  löscht die Zeit dann ebenfalls, sofern sie nicht abgerechnet ist; sonst
  entsteht ein Fall in der Inbox.
- Beim CSV-Weg erkennt WorkDiary einen Eintrag an Zeit, Client, Projekt, Task,
  Beschreibung und E-Mail. Wurde er in Clockify geändert, entsteht beim
  erneuten Hochladen ein zusätzlicher Eintrag. CSV-Importe lösen keine
  Löschungen aus.
- Tags aus Clockify werden ergänzt, nie entfernt.

## Zeiten nach Clockify übertragen

Mit **Zeit-Übertragung aktivieren** spiegelt WorkDiary in WorkDiary erfasste
Arbeitszeiten mit Beginn und Ende nach Clockify:

- Übertragen werden nur Zeiten in Projekten, die einem Clockify-Projekt
  zugeordnet sind. Als zugeordnet gilt ein Projekt, sobald Sie eine
  Clockify-Gruppe in der Zuordnungs-Inbox darauf gebucht haben und in Clockify
  ein Projekt mit demselben Client und Namen existiert. Projekte, die der
  Import allein über den Namen gefunden hat, zählen nicht.
- Angelegt wird immer für den Inhaber des API-Keys – Clockify erlaubt es nicht
  anders.
- Neue Zeiten gehen gleich nach dem Erfassen hinaus. Zusätzlich holt ein
  stündlicher Lauf nach, was im Sync-Zeitfenster noch fehlt. Mit **Nach
  Clockify übertragen** im Bereich **Zeiten nach Clockify übertragen** starten
  Sie die Übertragung von Hand, optional für einen Zeitraum.
- Anders als eine Rückbuchung bleibt die Zeit in WorkDiary abrechenbar. Sie
  verhält sich danach wie eine importierte: Änderungen und Löschungen werden
  in beide Richtungen abgeglichen.
- Bereits übertragene und aus Clockify importierte Zeiten werden übersprungen.

## Korrekturen zurückschreiben

Mit **Korrekturen zurückschreiben** überträgt WorkDiary Änderungen an über die
API importierten oder übertragenen Zeiten (Beschreibung, Beginn, Ende, Dauer,
Abrechenbar) und deren Löschung nach Clockify. Vorher vergleicht WorkDiary den
aktuellen Stand in Clockify: Wurde der Eintrag dort inzwischen geändert,
überschreibt WorkDiary nichts, sondern legt einen Konflikt in der Inbox an.
Abgerechnete und per CSV importierte Zeiten werden nie zurückgeschrieben.

## Webhook

Mit einem kostenpflichtigen Clockify-Tarif kann Clockify WorkDiary über neue
und geänderte Einträge benachrichtigen; der Import startet dann von selbst:

1. Die Seite **Clockify-Import** nennt im Abschnitt **Webhook (optional)** die
   Adresse, die Clockify aufrufen soll.
2. Legen Sie in Clockify unter Workspace-Einstellungen → Webhooks einen
   Webhook auf diese Adresse an.
3. Tragen Sie dessen Signatur-Token in den Plugin-Einstellungen unter
   **Webhook-Secret** ein und hinterlegen Sie dort auch die **Workspace-ID**.

Viele Ereignisse kurz hintereinander lösen nur einen Import aus. Ohne
Webhook-Secret bleibt der Webhook aus. Der stündliche Abruf bleibt die
verlässliche Quelle: Was ein ausgefallener Webhook verpasst, holt er nach.

## Typische Fehler

- **Kein API-Key hinterlegt** statt des Importbereichs: Der API-Key fehlt in
  den Plugin-Einstellungen.
- Die Meldung nennt den Free-Plan mit 30 Anfragen pro Stunde: Das
  Kontingent ist erschöpft. Importieren Sie per CSV oder warten Sie; eine
  unterbrochene Übertragung setzt der nächste Lauf fort.
- „Clockify: kein Workspace ermittelbar“: Tragen Sie die **Workspace-ID** ein.
- „Kein Projekt ist einem Clockify-Projekt zugeordnet“: Buchen Sie zuerst
  Clockify-Gruppen in der Inbox auf die gewünschten Projekte.
- Viele Fälle „Unbekannter Benutzer“: Die E-Mail-Adressen in Clockify weichen
  von denen in WorkDiary ab. Ordnen Sie jede Person einmal in der Inbox zu.
- Falsche Daten beim CSV-Import: Datumsangaben im Schrägstrich-Format, bei
  denen Tag und Monat beide höchstens 12 sind, liest WorkDiary als
  Monat/Tag. Stellen Sie in Clockify ein eindeutiges Datumsformat ein.
- Häufen sich Fehler, deaktiviert WorkDiary das Plugin automatisch; nach der
  Behebung setzen Sie es auf der Seite **Plugins** mit **Reset &
  Reaktivieren** zurück.
