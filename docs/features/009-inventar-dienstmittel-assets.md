# Inventar, Dienstmittel und Assets

## Status

Proposed

## Ziel

WorkDiary soll Dienstmittel, Werkzeuge, Geräte, Maschinen, Fahrzeuge,
Leihgeräte, Produkte, Anlagen und sonstige Assets nachvollziehbar verwalten.
Für jeden Auftrag soll sichtbar sein, welche Mittel genutzt, ausgegeben,
zurückgegeben, beschädigt, gewartet oder ersetzt wurden.

## Warum

Wenn Dienstmittel Teil des Arbeitsnachweises sind, dürfen sie nicht nur als
Freitext oder Materialposition auftauchen. Firmen müssen wissen, wo sich Geräte
befinden, wer sie zuletzt genutzt hat, ob Prüfungen oder Wartungen fällig sind
und ob bestimmte Assets wiederkehrend Probleme verursachen.

## MVP

- Asset-Stammdaten: Typ, Hersteller, Modell, Seriennummer, Standort, Status.
- Ausgabe/Rückgabe an Mitarbeitende, Teams, Fahrzeuge oder Aufträge.
- Verknüpfung mit Protokollen, Fotos, Wartungen, Mängeln und Aufträgen.
- Wartungs- und Prüfintervalle mit Fälligkeitsstatus.
- Defektmeldung und Sperrstatus.
- Historie pro Asset: Nutzung, Wartung, Reparatur, Standortwechsel.

## Akzeptanzkriterien

- Ein Auftrag zeigt alle verwendeten Dienstmittel und Assets.
- Ein Asset zeigt seine komplette Nutzungshistorie.
- Fällige Prüfungen und gesperrte Assets werden vor Einsatz sichtbar.
- Defekte Assets können nicht unbemerkt weiter eingeplant werden.
- Wiederkehrende Asset-Probleme sind auswertbar.

## Abhängigkeiten

- Dokumentation und Abnahmeprotokolle
- Auswertungen und Entscheidungsgrundlagen
- `Vehicle`
- `MaterialUsage`
- `Attachment`
- `DiaryEntry`
- `Project`
- `Task`

## GitHub Issues

- TBD
