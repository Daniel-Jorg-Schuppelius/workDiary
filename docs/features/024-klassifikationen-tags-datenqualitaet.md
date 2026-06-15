# Klassifikationen, Tags und Datenqualität

## Status

In Progress — Kernklassifikationen, Org-Kategorien und Pflichtklassifikationen
umgesetzt (MVP-030 bis MVP-032). Tagging ist über die polymorphe `HasTags`-
Mechanik nun zusätzlich an Kunde, Asset und Protokoll angebunden (Auftrag/
Wissensartikel hatten es bereits). Datenqualitäts-Hinweise werden auf der
Auftrags-Detailseite aus den vorhandenen Pflichtklassifikationen abgeleitet
(`DataQualityInspector`, rein lesend, ohne neue Pflichtmechanik). Stillgelegte
Klassifikationen (`deprecated_at`) sind im Admin sichtbar und über den Resolver
nicht mehr neu wählbar, bleiben für historische Daten aber lesbar.

Offen: Tag-/Kategorie-Mapping für CSV-Import (kein generischer Objekt-Import
mit Tag-Spalten vorhanden); Datenqualitäts-Report/Widget „Objekte ohne
Pflichtklassifikation" (Zähler je Domäne); produktbezogenes Tagging (kein
eigenständiges Produkt-Modell).

Konzipiert in MVP-030 bis MVP-032:
[Kernklassifikationen](../kernklassifikationen.md),
[Kategorien pro Organisation](../kategorien-org.md),
[Pflichtklassifikationen](../pflichtklassifikationen.md).

## Ziel

WorkDiary soll kontrollierte Klassifikationen für Auswertungen bereitstellen:
Auftragstyp, Tätigkeit, Fehlerart, Ursache, Ergebnis, Produktgruppe,
Priorität, Kulanzgrund, Nacharbeitsgrund, Dienstmitteltyp und weitere
kundenspezifische Kategorien.

## Warum

Gute Auswertungen entstehen nicht aus Freitext. Wenn Mitarbeitende gleiche
Sachverhalte unterschiedlich benennen, werden Reports unbrauchbar. WorkDiary
muss strukturierte Kategorien ermöglichen, ohne die Erfassung unnötig schwer zu
machen.

## MVP

- Organisationsweite Klassifikationslisten.
- Pflicht- oder optionale Kategorien pro Auftragstyp.
- Tagging für Auftrag, Protokoll, Produkt, Asset und Kunde.
- Datenqualitäts-Hinweise bei fehlenden Pflichtinformationen.
- Mapping für Import und Auswertung.

## Akzeptanzkriterien

- Reports nutzen kontrollierte Kategorien statt nur Freitext.
- Admins können Kategorien pflegen und deaktivieren.
- Alte Kategorien bleiben für historische Daten nachvollziehbar.
- Erfassende sehen nur relevante Auswahlwerte.

## Abhängigkeiten

- Auswertungen und Entscheidungsgrundlagen
- Dokumentation und Abnahmeprotokolle
- Wissensbasis und Problemhistorie
- Tags

## GitHub Issues

- TBD
