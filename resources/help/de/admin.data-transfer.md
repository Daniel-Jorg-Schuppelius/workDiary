---
title: "Datentransfer"
topic: admin.data-transfer
version: 1
audience:
    - admin
related:
    - admin.import
    - admin.backups
    - admin.handbook
---

Der Datentransfer bündelt den Im- und Export von Datenbeständen unter
einer gemeinsamen Tab-Navigation: **Import**, **Export** und
**Verlauf**. Alle Läufe sind auf die aktuelle Organisation beschränkt.

Bereiche:

- **Import**: CSV-Import über den Import-Wizard. Details im Kapitel
  **Import**.
- **Export**: Auswahl einer Entität (z. B. Kunden) und eines Formats;
  optional einschränkbar über Filter (Status, Suchtext, Zeitraum,
  Benutzer). Der Export wird als Lauf erzeugt und steht anschließend
  zum Download bereit.
- **Verlauf**: Import- und Export-Läufe gemeinsam, mit Status und
  Kennzahlen (z. B. Zeilenzahl).

Exporte können später heruntergeladen oder gelöscht werden; beim
Löschen wird auch die hinterlegte Datei entfernt.

Zugriff hat, wer Admin ist oder mindestens ein Export-Recht für eine
Entität besitzt. Jeder Export wird im Audit-Log protokolliert.

Hinweis: Dieser Bereich dient der Datenübernahme/-übertragung. Für
vollständige System-Sicherungen siehe **Backups**.
