# Inventar, Dienstmittel und Assets

## Status

In Progress — Stammdaten, Verknüpfungen, Wartungspläne sowie der
Ausgabe-/Rückgabe-Workflow (Checkout) und der Defekt-/Sperrstatus sind
umgesetzt (MVP-035, 036, 038 + Feature 009). Verfügbarkeit und Sperre
werden aus den Tabellen `asset_assignments` (offene Zuweisung = ausgegeben)
und `asset_defects` (offener blockierender Defekt = gesperrt) abgeleitet;
der vorhandene `Asset.status` wird zusätzlich auf die bestehenden Werte
`loanOut`/`blocked` gespiegelt (keine neuen Enum-Werte). Überfällige
Rückgaben meldet der Fristen-Scanner via `asset.returnOverdue`.
Offen: Foto-/Anhang-Verknüpfung direkt am Defekt, Prüfintervall-Eskalation,
auswertbare Wiederholdefekt-Statistik.
Konzept: [Asset-Stammdaten](../asset-stammdaten.md),
[Asset-Verknüpfungen](../asset-verknuepfungen.md),
[Defekt-/Sperrstatus](../asset-sperrstatus.md).

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
