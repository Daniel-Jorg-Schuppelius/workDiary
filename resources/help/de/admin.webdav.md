---
title: "WebDAV-Ablage"
topic: admin.webdav
version: 3
keywords:
    - WebDAV
    - Nextcloud
    - ownCloud
    - Dokumente spiegeln
    - Dateiablage
    - Rechnungen ablegen
    - Protokolle ablegen
    - App-Passwort
    - Ordnerregeln
    - Spiegelkonflikt
    - Private Adressen
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - documents.manage
    - invoices.manage
    - protocols.sign
    - backup-targets.overview
---

Die Seite **WebDAV** (Seitentitel **WebDAV-Ablage**) spiegelt freigegebene
Dokumente und auf Wunsch gestellte Rechnungen und unterschriebene Protokolle
als Dateien in eine externe WebDAV-Ablage, etwa Nextcloud oder ownCloud. Zu
jeder Datei hält WorkDiary einen Übergabenachweis (Prüfsumme, Zeitpunkt,
Ziel) fest. WorkDiary bleibt führend: Es gibt keinen Rückkanal, und Änderungen
an gespiegelten Dateien in der Ablage werden als Konflikt sichtbar statt still
übernommen. Sie finden die Seite im Systemmenü (Zahnrad **System** in der
Kopfzeile) unter **Plugins** → **WebDAV**, sobald das Plugin aktiv ist.

## Voraussetzungen

- Das Plugin ist für Ihre Organisation aktiviert: **System** → **Plugins** →
  **Plugins**, beim Eintrag WebDAV **Aktivieren**. Die Ablage selbst richten
  Sie nicht im Plugin-Dialog ein, sondern auf der Seite **WebDAV**.
- Die Seite steht Administratoren offen.
- Sie brauchen ein Konto in der Ablage mit Schreibrecht auf den Zielordner
  und ein App-Passwort (Nextcloud: Einstellungen → Sicherheit →
  App-Passwort).
- Die Ablage muss öffentlich erreichbar sein. Steht der Server im eigenen
  Netz, schalten Sie **Private/interne Adressen erlauben** ein (siehe unten).
- Je Organisation gibt es genau eine WebDAV-Ablage.

Als Ziel für Datensicherungen dient diese Seite nicht. Ein WebDAV-Backupziel
richten Sie unter **Cloud-Backupziele** ein.

## Ablage einrichten

Im Abschnitt **Ablage** füllen Sie aus:

- **Bezeichnung**: frei wählbarer Name.
- **Collection-URL**: der vollständige WebDAV-Ordner, in den WorkDiary
  schreibt, bei Nextcloud etwa …/remote.php/dav/files/BENUTZER/WorkDiary. Die
  Adresse muss mit http:// oder https:// beginnen; den Ordner legen Sie vorher
  in der Ablage an.
- **Benutzername** und **App-Passwort**: Das Passwort ist beim ersten
  Speichern Pflicht und wird verschlüsselt gespeichert; ein leeres Feld behält
  später das gespeicherte Passwort.
- **Standardordner**: Unterordner für Dokumente ohne eigene Ordnerregel
  (vorbelegt mit Dokumente).
- **Private/interne Adressen erlauben**: nur einschalten, wenn der
  WebDAV-Server in Ihrem eigenen Netz steht (zum Beispiel 192.168.x.x). Ohne
  diesen Schalter lehnt WorkDiary interne Adressen schon beim Speichern ab.
  Das Einschalten wird protokolliert. Hat der Betreiber Ihrer Installation
  diese Freigabe gesperrt, bleibt der Schalter ohne Wirkung.
- **Aktiv**: schaltet die Ablage ein oder aus.
- **Gespiegelte Inhalte**: **Dokumente (DMS)**, **Rechnungen (PDF)**,
  **Protokolle (PDF)**.
- **Dokumenttyp → Ordner**: je Dokumenttyp ein eigener Unterordner, siehe
  unten.

Mit **Speichern** übernehmen Sie die Angaben. Ist die Ablage aktiv, zeigt die
Seite ihren Zustand (etwa **Zustand ok**) und **Verbindung testen**.

## Was gespiegelt wird und wann

- **Dokumente:** Ein Dokument wird gespiegelt, sobald es den Status **Aktiv**
  mit einer Datei hat, und erneut bei jeder neuen Version. Reine Änderungen
  an den Angaben ohne neue Version lösen keinen Upload aus. Das gilt nur mit
  Haken bei **Dokumente (DMS)**; ist gar keine Quelle angehakt, gelten die
  Dokumente als gewählt.
- **Rechnungen (PDF):** Mit diesem Haken wird jede Rechnung beim Wechsel auf
  **Gestellt** einmal als PDF abgelegt.
- **Protokolle (PDF):** Mit diesem Haken wird jedes Protokoll beim
  Unterschreiben (Status **Unterschrieben**) als PDF abgelegt.
- Die Übertragung läuft im Hintergrund über eine Warteschlange und wird bei
  Verbindungsfehlern wiederholt. Unveränderte Inhalte lädt WorkDiary nicht
  erneut hoch.
- **Jetzt spiegeln** reiht alles aus den angehakten Quellen erneut ein:
  freigegebene Dokumente, gestellte Rechnungen und unterschriebene
  Protokolle – nützlich nach dem Einrichten, auch für Belege von davor.
  Bereits gespiegelte Inhalte lädt WorkDiary nicht erneut hoch.
- Einen zeitgesteuerten Lauf gibt es nicht; die Spiegelung folgt den
  Änderungen in WorkDiary.

## Ordner und Dateinamen

- Dokumente liegen im Ordner ihres Dokumenttyps aus **Dokumenttyp → Ordner**,
  sonst im **Standardordner** – beides relativ zur Collection-URL. Die Datei
  heißt document- mit der Dokumentnummer und der ursprünglichen Endung, etwa
  document-42.pdf.
- Die Auswahl nennt die Dokumenttypen mit ihrer Bezeichnung, etwa Vertrag
  oder Rechnung. Es gibt immer drei freie Zeilen; Zeilen ohne Typ oder ohne
  Unterordner verwirft WorkDiary.
- Rechnungen liegen unter invoices/Jahr/Rechnungsnummer.pdf, Protokolle unter
  protocols/Jahr/protocol-Nummer.pdf – direkt unter der Collection-URL, nicht
  im Standardordner.
- Fehlende Unterordner legt WorkDiary selbst an.

## Konflikte

Bevor WorkDiary eine neue Version hochlädt, prüft es, ob die Datei in der
Ablage seit der letzten Spiegelung verändert wurde. Wenn ja, überschreibt es
nichts, sondern legt einen Konflikt in der Zuordnungs-Inbox an: „Externe
Änderung erkannt – Spiegelung angehalten“. Dort wählen Sie:

- **Remote überschreiben**: Die Datei in der Ablage erhält den Stand aus
  WorkDiary; die externe Änderung geht verloren.
- **Als neue Version importieren**: Der Stand aus der Ablage wird zur neuen
  Version des Dokuments in WorkDiary.
- **Spiegelung trennen**: Dieses Dokument wird dauerhaft nicht mehr
  gespiegelt; die Ablage bleibt für alle anderen aktiv.

Bei Rechnungs- und Protokoll-PDFs gibt es nur **Remote überschreiben**:
Gestellte Rechnungen und unterschriebene Protokolle sind unveränderlich,
WorkDiary legt sein PDF erneut ab. Wollen Sie die geänderte Datei behalten,
wählen Sie **Verwerfen**.

Die Zuordnungs-Inbox steht Administratoren und der Buchhaltung offen.

## Trennen

**Trennen** schaltet die Ablage ab. Bereits gespiegelte Dateien bleiben in der
Ablage erhalten. Zum Wiedereinschalten setzen Sie **Aktiv** und speichern.

## Typische Fehler

- „Die Collection-URL muss mit http:// oder https:// beginnen.“: Tragen Sie die
  vollständige Adresse ein.
- „Für eine neue Ablage ist ein App-Passwort erforderlich.“: Beim ersten
  Speichern fehlt das Passwort.
- **Zustand fehlerhaft** mit „WebDAV-Ablage nicht erreichbar oder Zugangsdaten
  ungültig.“: Prüfen Sie Collection-URL, Benutzername und App-Passwort und ob
  der Ordner existiert. Ein WebDAV-Fehler mit RuntimeException deutet oft auf
  eine Adresse im internen Netz ohne Freigabe hin.
- „Die Collection-URL zeigt auf eine private/interne Adresse.“: Steht der
  Server im eigenen Netz, schalten Sie **Private/interne Adressen erlauben**
  ein. Hat der Betreiber diese Freigabe gesperrt, braucht die Ablage eine
  öffentlich erreichbare Adresse.
- „Keine aktive WebDAV-Ablage vorhanden.“ bei **Jetzt spiegeln**: Die Ablage
  ist aus oder unvollständig.
- Rechnungen oder Protokolle fehlen in der Ablage: Der passende Haken unter
  **Gespiegelte Inhalte** war beim Stellen bzw. Unterschreiben nicht gesetzt.
- Ein Dokument wird nicht mehr aktualisiert: Es steht ein offener Konflikt in
  der Inbox, oder seine Spiegelung wurde getrennt.
