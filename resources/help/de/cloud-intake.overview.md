---
title: "Cloud-Dokumenteingang"
topic: cloud-intake.overview
version: 1
audience: []
related:
    - documents.manage
    - admin.integrations
---

WorkDiary übernimmt Dokumente LESEND aus überwachten Ordnern in Dropbox, OneDrive/SharePoint und Google Drive und routet sie per Ordnerregel in den Rechnungseingang oder das DMS.

**Verbindungen:** Je Provider wird ein Konto per OAuth angebunden und bestätigt; danach werden Container (Drive/Bibliothek) und Stammordner gewählt. Aktiv importiert wird erst, wenn mindestens eine gültige Regel existiert.

**Regeln:** Pfadmuster mit * und ** sowie Variablen wie {customer_number} ordnen Dateien vorhandenen Kunden, Projekten, Aufträgen, Assets oder Verträgen zu — nie per Auto-Anlage. Unsichere Treffer landen in der Integrations-Inbox.

**Sicherheit:** Nur Lese-Scopes, Tokens verschlüsselt, Quelldateien bleiben unverändert; Webhooks sind reine Aufwecksignale, maßgeblich ist der wiederanlaufbare Delta-Lauf.
