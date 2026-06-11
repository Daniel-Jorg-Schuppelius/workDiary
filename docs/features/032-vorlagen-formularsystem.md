# Vorlagen- und Formularsystem

## Status

Done — MVP umgesetzt (2026-06-10): Formularvorlagen mit 6 Feldtypen, Ausfüllen am Auftrag mit fields_snapshot (Versionssicherheit), Druckansicht, Modul-Gating (module.forms, Pro+). Offen: Foto-/Datei-/Unterschrift-Felder, Bedingungslogik, PDF-Engine.

## Ziel

WorkDiary soll ein wiederverwendbares Vorlagen- und Formularsystem bieten:
Protokolle, Checklisten, Prozeduren, PDFs, E-Mail-Texte, Kundenberichte und
interne Formulare sollen konfigurierbar und versionierbar sein.

## Warum

Viele Funktionen brauchen ähnliche Bausteine. Ein generisches Vorlagensystem
verhindert Sonderlösungen für jeden Protokoll- oder Formularfall.

## MVP

- Formularfelder: Text, Zahl, Datum, Auswahl, Checkbox, Foto, Datei, Signatur.
- Vorlagenversionen mit Gültigkeitszeitraum.
- Zuordnung zu Auftragstyp, Kunde, Produkt, Objekt oder Mandant.
- Pflichtfelder und einfache Bedingungen.
- PDF-Ausgabe auf Basis einer Vorlage.

## Akzeptanzkriterien

- Admins können einfache Vorlagen ohne Code pflegen.
- Alte Aufträge behalten die damals gültige Vorlage.
- Formulare erzeugen strukturierte Daten für Auswertungen.
- Pflichtfelder und Bedingungen sind nachvollziehbar.

## Abhängigkeiten

- Dokumentation und Abnahmeprotokolle
- Prozeduren, Arbeitsanweisungen und Checklisten
- Dokumentenmanagement
- Klassifikationen, Tags und Datenqualität

## GitHub Issues

- TBD
