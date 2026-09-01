---
title: "Dokumente verwalten"
topic: documents.manage
version: 1
audience: []
modules:
    - module.documents
related:
    - forms.fill
    - knowledge.articles
    - glossary.core
---

Das Dokumentenmodul verwaltet Verträge, Zertifikate, Prüfberichte,
Anleitungen und mehr als **versionierte Dateien** mit Metadaten,
Gültigkeit und Bezug zu Kunde, Projekt, Auftrag oder Asset.

Typischer Ablauf:

1. **Dokument hochladen**: Titel, Dokumenttyp (z. B. „Vertrag",
   „Zertifikat", „Prüfbericht", „Bedienungsanleitung"), optional
   Gültigkeit (von/bis) und Bezugsobjekt. Die Datei wird Version 1.
2. **Neue Version hochladen**, wenn sich das Dokument ändert – die
   Versionsnummer zählt hoch, alte Versionen bleiben unverändert
   erhalten (mit Versionshinweis).
3. **Download** wahlweise der aktuellen oder einer älteren Version.
4. **Archivieren**, wenn das Dokument nicht mehr aktiv gebraucht wird.

Wichtige Status: „Entwurf", „Aktiv", „Archiviert" – **„Abgelaufen"**
wird automatisch aus dem „Gültig bis"-Datum berechnet und nicht
gespeichert. Ablaufende Dokumente können über Benachrichtigungsregeln
gemeldet werden.

Berechtigungen: Sichtbare Dokumente dürfen von berechtigten
Mitarbeitenden gelesen und angelegt werden. Bearbeiten darf der
Erfasser oder eine Person mit erweiterten Dokumentrechten.

Risiken: **Löschen entfernt das Dokument mit allen Versionen**
(Soft-Delete, nur mit Löschberechtigung). Versionen selbst sind
unveränderlich – Korrekturen erfolgen immer über eine neue Version.
