---
title: "Cloud-Dokumenteingang"
topic: cloud-intake.overview
version: 2
keywords:
    - Paperless-ngx
    - Seafile
    - Dropbox
    - OneDrive
    - SharePoint
    - Google Drive
    - Ordner überwachen
    - Dokumentenimport
    - Cloud-Ordner
    - Ordnerregel
    - automatischer Import
    - Cloud-Speicher anbinden
    - Belege aus der Cloud
audience: []
related:
    - documents.manage
    - admin.integrations
---

WorkDiary übernimmt Dokumente LESEND aus überwachten Ordnern in Dropbox, OneDrive/SharePoint, Google Drive und Nextcloud sowie aus Paperless-ngx und Seafile und routet sie per Ordnerregel in den Rechnungseingang oder das DMS.

**Verbindungen:** Je Provider wird ein Konto per OAuth angebunden und bestätigt; danach werden Container (Drive/Bibliothek) und Stammordner gewählt. Aktiv importiert wird erst, wenn mindestens eine gültige Regel existiert.

**Paperless-ngx:** Basis-URL und API-Token stehen in den Plugin-Einstellungen; „Paperless-ngx verbinden“ prüft das Konto. Als Container wählen Sie alle Dokumente oder einen Tag, Korrespondenten bzw. Dokumenttyp. Der Pfad eines Dokuments lautet Dokumenttyp/Korrespondent/Dateiname (ohne Zuordnung „-“), als Stammordner genügt „/“. Übernommen wird die Originaldatei — so bleiben eingebettete E-Rechnungsdaten erhalten; die Herkunft verlinkt am Beleg zurück ins Paperless-Original. Nachträglich getaggte Dokumente erkennt der nächste Lauf am Abgleich der Dokumentliste. Der Token liest mit den Rechten seines Benutzers: nötig ist Leserecht auf Dokumente, Tags, Korrespondenten, Dokumenttypen und UI-Einstellungen; übernommen wird nur, was dieser Benutzer sehen darf.

**Seafile:** Adresse und API-Token stehen in den Plugin-Einstellungen; „Seafile verbinden“ prüft das Konto. Als Container wählen Sie eine Bibliothek, darin den Stammordner; verschlüsselte Bibliotheken stehen nicht zur Wahl. Hat sich unter dem Stammordner seit dem letzten Lauf nichts geändert, liest WorkDiary keinen Ordner. Eine Datei erkennt WorkDiary an ihrem Inhalt: Umbenennen oder Verschieben erzeugt keinen neuen Beleg, eine inhaltlich geänderte Datei schon. Die Herkunft verlinkt am Beleg auf die Datei in Seafile. Den Konto-Token liefert die Seafile-API: POST an /api2/auth-token/ mit Benutzername und Passwort, bei Zwei-Faktor-Anmeldung zusätzlich mit dem Kopf X-Seafile-OTP und dem aktuellen Code.

**Regeln:** Pfadmuster mit * und ** sowie Variablen wie {customer_number} ordnen Dateien vorhandenen Kunden, Projekten, Aufträgen, Assets oder Verträgen zu — nie per Auto-Anlage. Unsichere Treffer landen in der Integrations-Inbox.

**Sicherheit:** Nur Lese-Scopes, Tokens verschlüsselt, Quelldateien bleiben unverändert; Webhooks sind reine Aufwecksignale, maßgeblich ist der wiederanlaufbare Delta-Lauf.
