---
title: "Cloud-Dokumenteingang"
topic: cloud-intake.overview
version: 5
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

**Regeltest und Start:** Jede Regel braucht einen Testpfad, etwa einen echten Kundenordner. Beim Speichern prüft WorkDiary, ob der Pfad das Muster trifft, welche Variablen erkannt werden und welches Ziel entsteht; ohne bestandenen Test wird nicht gespeichert. Eine feste Zielreferenz ordnet alle Dateien einer Regel einem bestimmten Kunden, Projekt, Auftrag, Asset oder Vertrag zu. Aktive Regeln einer Verbindung dürfen sich nicht überschneiden: Gleiche Muster oder ein Testpfad, der zwei Regeln trifft, blockieren die Aktivierung. Den ersten Import beginnt eine Verbindung erst, wenn Sie die Vorschau angesehen und „Import starten“ bestätigt haben; ein neuer Stammordner verlangt eine neue Bestätigung.

**Datenführerschaft:** Führt laut Datenführerschaft ein anderes System die Dokumente, übernimmt die Verbindung keine Dateien ins DMS; je Datei entsteht ein Fall im Integrations-Eingang, ohne Übergabenachweis. **Einmalig übernehmen** lädt die Datei neu und legt sie nach den Regeln an. Regeln für Eingangsrechnungen, Bestellungen und Vergabepakete betrifft das nicht.

## Anmeldung abgelaufen, blockiert, abgeschaltet

Lehnt der Anbieter die Anmeldung ab (abgelaufen oder widerrufen), steht die Verbindung auf **Neu anmelden**. Fehlen bei der Anmeldung Leserechte, steht sie auf **Blockiert**; nach wiederholten Fehlern bis zur Abschaltschwelle auf **Deaktiviert**. In allen drei Fällen ruht der Import, und die Karte bietet **Neu anmelden** an. Erst die erneute Anmeldung macht die Verbindung wieder aktiv; Änderungen an Ordner oder Regeln tun das nicht.
