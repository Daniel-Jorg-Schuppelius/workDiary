---
title: "Disposition und Konfliktwarnungen"
topic: dispatch.overview
version: 1
audience: []
related:
    - diary-entries.edit
    - planning.shifts
    - assets.fleet
---

Die Disposition steuert, **wer welchen Auftrag wann** erledigt — ergänzend zur
fachlichen Auftrags-Statusmaschine. Jeder Auftrag trägt einen
**Dispositionsstatus**:

- **Ungeplant**: weder terminiert noch zugewiesen.
- **Geplant**: terminiert oder einem Mitarbeiter zugewiesen.
- **Bestätigt**: die Zuweisung wurde verbindlich bestätigt.
- **Unterwegs**: der Einsatz läuft.
- **Erledigt**: der Auftrag ist abgeschlossen.

## Konfliktwarnungen vor der Bestätigung

Vor der Terminbestätigung prüft WorkDiary die geplante Zuweisung gegen die
bestehenden Arbeitszeit- und Verfügbarkeitsregeln (Überschneidung mit anderen
Schichten oder Aufträgen, Ruhezeit, Tages-/Wochenhöchstarbeitszeit, Urlaub und
Abwesenheit). Es gibt zwei Schweregrade:

- **Harte Konflikte** verhindern die Bestätigung. Sie lassen sich nur mit einer
  **dokumentierten Begründung** bewusst übersteuern; die Übersteuerung wird
  revisionssicher protokolliert.
- **Warnungen** sind Hinweise und blockieren nicht.

## Fahrzeug-Reservierung

Am Auftrag kann ein Fahrzeug für ein Zeitfenster reserviert werden. Ist das
Fahrzeug im gewünschten Zeitraum bereits reserviert, verhindert das System die
Doppelreservierung. Die Reservierungen je Fahrzeug lassen sich in der
Reservierungsliste einsehen und wieder aufheben.
