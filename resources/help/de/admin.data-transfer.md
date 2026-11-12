---
title: "Datentransfer"
topic: admin.data-transfer
version: 3
keywords:
    - Datenexport
    - Daten exportieren
    - Daten importieren
    - CSV-Export
    - Excel-Export
    - Export herunterladen
    - Exportverlauf
    - Kunden exportieren
    - Datenübernahme
    - Massenexport
audience:
    - admin
    - buchhaltung
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

## Spalten mit eigenem Recht

Verrechnungs- und Kostensätze von Personen stehen nur mit dem Recht für Personal-
und Lohndaten im Export, die Sätze von Projekten nur mit dem Recht für die
Projektabrechnung. Fehlt das Recht, fehlen die Spalten in der Datei; eine Datei mit
diesen Spalten lädt nur herunter, wer das Recht hat. Beim Import übergeht workDiary
solche Spalten ohne das Recht und nennt sie im Prüfbericht; die übrigen Spalten
werden übernommen.
