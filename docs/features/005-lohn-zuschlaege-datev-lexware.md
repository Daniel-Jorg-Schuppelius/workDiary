# Lohn, Zuschläge und DATEV/Lexware

## Status

Proposed

## Ziel

Geprüfte Arbeitszeiten sollen nicht nur dokumentiert, sondern für Lohnabrechnung
und Buchhaltung verwendbar werden. WorkDiary soll Zuschläge, Kostenstellen und
Exportformate so vorbereiten, dass Steuerberatung und Lohnbüro möglichst wenig
manuell nacharbeiten müssen.

## Warum

Für viele Betriebe entscheidet nicht die schönste Zeiterfassung, sondern der
Monatsabschluss: Sind Nacht-, Sonn-, Feiertags-, Bereitschafts- und
Reisezuschläge korrekt? Können Daten an DATEV, Lexware oder andere Systeme
übergeben werden? Genau hier entsteht ein klarer Produktnutzen.

## MVP

- Zuschlagsregeln pro Organisation: Nacht, Sonntag, Feiertag, Bereitschaft,
  Rufbereitschaft, Überstunden.
- Zuschlagsberechnung auf Basis geprüfter Zeiten.
- Kostenstellen und Lohnarten-Mapping.
- Export für DATEV-kompatible Übergabe als CSV.
- Export für Lexware/Lexoffice-nahe Workflows, soweit fachlich passend.
- Prüfansicht vor Export mit Summen pro Mitarbeitendem.

## Akzeptanzkriterien

- Zuschläge werden reproduzierbar aus Zeitdaten berechnet.
- Admins können vor dem Export sehen, welche Regel welchen Betrag erzeugt hat.
- Exportierte Daten enthalten keine ungeprüften Monatsdaten.
- Lohnarten und Kostenstellen sind konfigurierbar, nicht hart codiert.

## Abhängigkeiten

- Zeiterfassungs-Monatsfreigabe
- `Attendance`
- `TimeEntry`
- `ScheduledShift`
- `OnCallShift`
- `Holiday`
- `WorkBalanceCalculator`
- Reports

## GitHub Issues

- TBD
