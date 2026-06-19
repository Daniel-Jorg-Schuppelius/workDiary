# Prozeduren, Arbeitsanweisungen und Checklisten

## Status

In Progress — Backend-Kern (MVP-025..029) umgesetzt und getestet; **Vorlagen-
Designer-UI, PDF-/Druckansicht eines Laufs, sichtbare automatische Zuordnung am
Auftrag und bedingte Schritte (wenn-dann, als Vorlagen-Metadaten) ergänzt**.
Offen: Auswertung bedingter Schritte im Execution-Kern sowie die ausführende
Schritt-für-Schritt-Lauf-UI. Konzipiert in MVP-025 bis MVP-029:
[Prozedurvorlagen](../prozedurvorlagen.md),
[Pflicht & Reihenfolge](../prozedur-pflicht.md),
[Backup-Nachweis](../prozedur-backup.md),
[Vier-Augen / Freigeber](../prozedur-vier-augen.md),
[Abweichungen & Folgeaktion](../prozedur-abweichungen.md). Der operative Einsatz
als versionierter Arbeitsplan für Mengen-, Material- und Ergebnisaufträge wird
in [Fertigungs-, Montage- und Arbeitsaufträge](./047-fertigungs-montage-arbeitsauftraege.md)
weitergeführt.

## Ziel

WorkDiary soll Unternehmen ermöglichen, verbindliche Prozeduren für bestimmte
Tätigkeiten festzulegen. Mitarbeitende sollen diese Abläufe im Auftrag oder
Protokoll Schritt für Schritt durchgehen, bestätigen und dokumentieren können.
Wenn ein Schritt eine zweite Person, eine bestimmte Qualifikation, ein
Konfigurationsbackup, ein Foto, eine Messung oder eine Freigabe erfordert, muss
das im Protokoll sichtbar und nachweisbar sein.

## Warum

Viele Tätigkeiten sind nur sicher und sauber, wenn definierte Abläufe eingehalten
werden. Beispiel: Vor einem Update muss ein Konfigurationsbackup angelegt,
geprüft und dem Auftrag zugeordnet werden. Nach dem Update müssen Version,
Funktionstest, Rückfallplan und Abnahme bestätigt werden. Ohne verbindliche
Prozeduren bleibt unklar, ob kritische Schritte wirklich erledigt wurden.

## Anwendungsfälle

- Software-, Firmware- oder Anlagenupdate mit Pflicht-Backup vor Änderung.
- Wartung mit Sicherheits-, Prüf- und Messschritten.
- Inbetriebnahme mit Vorprüfung, Durchführung, Test und Abnahme.
- Austausch eines Bauteils mit Seriennummern-, Material- und Fotopflicht.
- Tätigkeiten mit Vier-Augen-Prinzip.
- Arbeiten, die nur mit bestimmter Qualifikation oder Unterweisung erlaubt sind.
- Übergaben zwischen Mitarbeitenden oder Schichten.
- Notfall- oder Rückfallprozeduren.
- Arbeitspläne für Fertigung oder Montage mit Rezeptur, Materialbedarf,
  Wartezeiten und Anleitungsbildern.

## MVP

- Prozedurvorlagen pro Organisation, Auftragstyp, Produkt, Asset oder Tätigkeit.
- Schritte mit Typ: Bestätigung, Text, Zahl, Foto, Datei, Messwert,
  Unterschrift, Auswahl, Material, Dienstmittel, Backup, Freigabe.
- Pflichtschritte und optionale Schritte.
- Reihenfolge und Sperren: ein späterer Schritt darf erst nach vorherigem
  Pflichtschritt erfolgen.
- Rollen- und Qualifikationsanforderungen pro Schritt.
- Zweite Person erforderlich: Prüfer, Freigeber, Helfer oder Abnehmer.
- Abweichung begründen, wenn ein Schritt nicht wie vorgesehen ausgeführt wird.
- Prozedurstand im Auftrag: offen, in Bearbeitung, blockiert, abgeschlossen.
- PDF-/Exportdarstellung der durchlaufenen Prozedur.

Prozeduren definieren dabei den Ablauf. Konkrete Sollmengen, Termine,
Materialbedarfs-Snapshots, Gutmengen und Ausschuss gehören in einen
Fertigungs-/Montageauftrag und nicht in die allgemeine Prozedurvorlage.

## Akzeptanzkriterien

- Ein Auftrag kann eine passende Prozedur automatisch oder manuell erhalten.
- Kritische Schritte können nicht unbemerkt übersprungen werden.
- Ein Protokoll zeigt, wer welchen Schritt wann bestätigt oder abgelehnt hat.
- Wenn eine zweite Person nötig ist, erscheint diese Anforderung sichtbar im
  Protokoll und im Auftrag.
- Backup-, Foto-, Mess- oder Dateipflichten können technisch nachgewiesen
  werden, nicht nur als Freitext.
- Abweichungen bleiben nachvollziehbar und fließen in Auswertungen ein.
- Prozedurvorlagen sind versioniert, damit alte Aufträge ihren damaligen
  Ablauf behalten.

## Datenstruktur

Für belastbare Prozeduren braucht es strukturierte Daten:

- Prozedurname, Version, Gültigkeit und Anwendungsbereich.
- Schritt-ID, Reihenfolge, Pflichtstatus und erwarteter Eingabetyp.
- erforderliche Rolle, Qualifikation oder zweite Person.
- Ergebnis: erledigt, nicht anwendbar, fehlgeschlagen, abgewichen, blockiert.
- Nachweis: Datei, Foto, Messwert, Backup, Kommentar, Unterschrift.
- Zeitstempel, ausführende Person und prüfende Person.
- Abweichungsgrund und Folgeaktion.

## Später

- Prozedur-Designer mit Bedingungen: wenn Ergebnis X, dann Zusatzschritte Y.
- Automatische Prozedurzuordnung anhand von Produkt, Asset, Auftragstyp oder SLA.
- Wiederverwendbare Schrittbibliothek.
- Risiko- und Kritikalitätsstufen pro Prozedur.
- Automatische Folgeaufträge bei fehlgeschlagenen Schritten.
- Auswertung der häufigsten Abweichungen und blockierten Prozeduren.
- Import von Arbeitsanweisungen aus bestehenden Dokumenten.
- Wiederverwendbare Arbeitspläne mit Stücklisten, Rezepturen,
  Anleitungsmedien und serverseitigen Wartezeiten gemäß Feature 047.

## Abhängigkeiten

- Dokumentation und Abnahmeprotokolle
- Qualität, Sicherheit und Arbeitsschutz
- Wissensbasis und Problemhistorie
- Klassifikationen, Tags und Datenqualität
- Inventar, Dienstmittel und Assets
- Qualifikationen
- Audit
- Anhänge und Storage
- Fertigungs-, Montage- und Arbeitsaufträge

## GitHub Issues

- TBD
