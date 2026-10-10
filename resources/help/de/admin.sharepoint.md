---
title: "SharePoint-Ablage"
topic: admin.sharepoint
version: 2
keywords:
    - SharePoint
    - SharePoint Online
    - Dokumentbibliothek
    - Dokumente spiegeln
    - Ablage in SharePoint
    - Microsoft 365
    - Site auswählen
    - Spiegelung
    - Übergabenachweis
    - Rechnungen ablegen
    - Spiegelkonflikt
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.msgraph
    - documents.manage
    - admin.integration-inbox
    - cloud-intake.overview
---

Die Seite **SharePoint-Ablage** spiegelt freigegebene Dokumente aus WorkDiary
über Microsoft Graph in eine Dokumentbibliothek von SharePoint Online – auf
Wunsch auch die PDFs gestellter Rechnungen und signierter Protokolle. WorkDiary
bleibt führend: Aus SharePoint fließt nichts zurück, und Änderungen an
gespiegelten Dateien in SharePoint werden als Konflikt sichtbar, nie still
übernommen. Zu jeder Übertragung hält WorkDiary einen Übergabenachweis fest
(Prüfsumme, Zeitpunkt, Ziel).

Dateien aus SharePoint lesend nach WorkDiary zu holen, ist eine andere
Funktion: der **Cloud-Dokumenteingang**.

## Voraussetzungen

- Das Plugin **SharePoint** ist unter **Plugins** aktiviert. Danach erscheint
  im Systemmenü (Zahnrad-Symbol **System**) in der Gruppe **Plugins** der
  Eintrag **SharePoint-Ablage**.
- Es gibt eine App-Registrierung in Microsoft Entra ID mit Client-ID und
  Client-Secret. Entweder hat der Betreiber eine App für die gesamte
  Installation hinterlegt, oder Ihre Organisation nutzt ihre eigene App aus den
  Einstellungen des Plugins **Microsoft 365** (**Client-ID (eigene
  App-Registrierung)**, **Client-Secret**, **Tenant (Verzeichnis-ID)**). Eine
  eigene App muss Ihre WorkDiary-Adresse mit dem Pfad
  /admin/sharepoint/oauth/callback als Redirect-URI vom Typ „Web“ kennen.
  Fehlt die App, zeigt die Seite einen Hinweis statt der Schaltfläche zum
  Verbinden.
- Sie brauchen ein Microsoft-365-Konto mit Schreibrecht auf die
  Ziel-Bibliothek. Die Verbindung arbeitet mit den Rechten dieses Kontos.
  Hat der Betreiber den Zugriff auf einzeln freigegebene Sites beschränkt
  (Sites.Selected), muss ein Tenant-Administrator die gewünschte Site
  zusätzlich freigeben.
- Die Seite steht Administratoren Ihrer Organisation offen. Je Organisation
  gibt es eine SharePoint-Verbindung.

## Verbinden

1. Klicken Sie auf **Mit Microsoft 365 verbinden**. Es öffnet sich die
   Anmeldung bei Microsoft; melden Sie sich an und stimmen Sie den
   Berechtigungen zu.
2. Microsoft leitet Sie auf die Seite zurück. Die Meldung „Mit Microsoft 365
   verbunden. Jetzt Site + Bibliothek wählen.“ bestätigt die Verbindung.

Den Vorgang muss dieselbe Person in derselben Sitzung abschließen, die ihn
gestartet hat. Andernfalls erscheint „Der OAuth-Vorgang ist abgelaufen oder
ungültig“; starten Sie die Verbindung dann neu.

## Ziel wählen: Site und Dokumentbibliothek

1. Geben Sie im Abschnitt **Ziel: Site + Dokumentbibliothek** im Feld **Site
   suchen** den Namen oder ein Stichwort der Site ein und klicken Sie auf
   **Suchen**.
2. Klicken Sie in der Trefferliste auf die Site. Sie wird als **Gewählt**
   markiert, und WorkDiary lädt ihre Dokumentbibliotheken.
3. Wählen Sie unter **Dokumentbibliothek** die Bibliothek aus und klicken Sie
   auf **Speichern**. **Aktuelles Ziel** zeigt danach Site und Bibliothek.

WorkDiary prüft Site und Bibliothek beim Speichern bei Microsoft nach; eine
Bibliothek, die nicht zur gewählten Site gehört, wird abgelehnt.

## Ordnerregeln und gespiegelte Inhalte

Im Abschnitt **Ordnerregeln + Quellen** legen Sie fest, was wohin gespiegelt
wird:

- **Standardordner** (vorbelegt mit „Dokumente“): Unterordner der Bibliothek
  für alle Dokumente ohne eigene Regel.
- **Aktiv**: schaltet die Spiegelung ein oder aus.
- **Gespiegelte Inhalte**: **Dokumente (DMS)**, **Rechnungen (PDF)** und
  **Protokolle (PDF)**. Ohne Auswahl werden nur Dokumente gespiegelt.
- **Dokumenttyp → Ordner**: Wählen Sie je Zeile einen Dokumenttyp und tragen
  Sie einen Unterordner ein, relativ zur Bibliothek. Leere Zeilen bleiben
  unberücksichtigt; nach jedem Speichern stehen drei weitere freie Zeilen
  bereit. Die Typen erscheinen in der Liste mit ihrer Bezeichnung, etwa
  Vertrag oder Rechnung.

Klicken Sie anschließend auf **Speichern**.

So legt WorkDiary die Dateien ab:

- **Dokumente** im Ordner ihres Typs oder im Standardordner. Der Dateiname
  besteht aus „document-“, einer internen Nummer und der Dateiendung; eine neue
  Version ersetzt so dieselbe Datei.
- **Rechnungen** im Ordner invoices, darin je Jahr ein Unterordner; der
  Dateiname ist die Rechnungsnummer.
- **Protokolle** im Ordner protocols, darin je Jahr ein Unterordner.

Rechnungen und Protokolle folgen nicht den Ordnerregeln.

## Wann gespiegelt wird

- **Automatisch bei Ereignissen:** Erhält ein Dokument den Status **Aktiv**
  (freigegeben) oder eine neue Version, überträgt WorkDiary diese Version.
  Reine Änderungen an Metadaten lösen keine neue Übertragung aus. Wird eine
  Rechnung gestellt oder ein Protokoll signiert, folgt dessen PDF. Das alles
  gilt nur für die ausgewählten Inhalte; ohne Haken bei **Dokumente (DMS)**
  überträgt WorkDiary keine Dokumente.
- **Im Hintergrund mit Wiederholung:** Die Übertragung läuft über eine
  Warteschlange. Schlägt sie fehl, wird sie automatisch wiederholt; keine
  Datei wird doppelt geschrieben.
- **Jetzt spiegeln:** reiht alles aus den ausgewählten Inhalten ein –
  aktive Dokumente, gestellte Rechnungen und signierte Protokolle –, etwa nach
  der Ersteinrichtung, auch für Belege von davor. Unveränderte Dateien
  überspringt WorkDiary.

Einen festen Zeitplan gibt es nicht. Aus SharePoint liest WorkDiary nur, um zu
prüfen, ob eine gespiegelte Datei dort verändert wurde.

## Konflikte auflösen

Wurde eine gespiegelte Datei in SharePoint verändert, überschreibt WorkDiary
sie nicht. Stattdessen erscheint ein Eintrag in der **Zuordnungs-Inbox** mit
dem Hinweis „Externe Änderung erkannt — Spiegelung angehalten (kein
Überschreiben).“ Für Dokumente aus der Dokumentenverwaltung stehen drei
Aktionen bereit:

- **Remote überschreiben**: Der Stand aus WorkDiary ersetzt die Datei in
  SharePoint; die dortige Änderung geht verloren.
- **Als neue Version importieren**: Der Stand aus SharePoint wird als neue
  Version des Dokuments übernommen.
- **Spiegelung trennen**: Dieses eine Dokument wird nicht mehr gespiegelt; die
  Anbindung bleibt aktiv.

Bei Rechnungs- und Protokoll-PDFs gibt es nur **Remote überschreiben**:
Gestellte Rechnungen und signierte Protokolle sind unveränderlich, WorkDiary
legt sein PDF erneut ab. Wollen Sie die geänderte Datei behalten, wählen Sie
**Verwerfen**.

Die **Zuordnungs-Inbox** steht Personen offen, die die Abrechnung verwalten
dürfen.

## Trennen und erneut verbinden

**Trennen** entfernt die Zugangsschlüssel der Verbindung. Bereits gespiegelte
Dateien bleiben in SharePoint erhalten. Ziel und Ordnerregeln bleiben
gespeichert; nach erneutem **Mit Microsoft 365 verbinden** geht es mit
denselben Einstellungen weiter.

## Typische Fehlerbilder

- **Keine Schaltfläche zum Verbinden:** Die Seite meldet eine fehlende
  App-Registrierung. Hinterlegen Sie Client-ID und Client-Secret (siehe
  Voraussetzungen) oder wenden Sie sich an den Betreiber.
- **Anmeldung abgebrochen:** „Microsoft hat keinen Autorisierungscode
  geliefert“ – die Anmeldung wurde abgebrochen oder die Zustimmung verweigert.
  Verlangt Ihr Tenant die Zustimmung eines Administrators, muss ein
  Entra-Administrator der App die Berechtigung erteilen.
- **Keine Sites gefunden:** Prüfen Sie den Suchbegriff. Bei beschränktem
  Zugriff muss der Tenant-Administrator die Site freigeben.
- **Site oder Bibliothek abgelehnt:** „Die gewählte Site ist nicht erreichbar
  oder nicht freigegeben.“ bzw. „Keine Dokumentbibliotheken in dieser Site
  gefunden.“ – das verbundene Konto hat keinen Zugriff, oder die Site hat keine
  Bibliothek.
- **Status Inaktiv, Jetzt spiegeln fehlt:** Die Verbindung ist getrennt,
  **Aktiv** ist ausgeschaltet, es ist keine Bibliothek gewählt, oder die
  Verbindung wurde nach wiederholten Fehlern in Folge stillgelegt. Nach
  Behebung der Ursache setzen **Trennen** und erneutes Verbinden die
  Fehlerzählung zurück. Solange die Verbindung gestört ist, steht dazu eine
  Betriebsaufgabe in der Übersicht der Betriebsaufgaben.
- **Zustand prüfen:** Neben dem Seitentitel steht der zuletzt geprüfte Zustand;
  **Verbindung testen** prüft ihn sofort.
