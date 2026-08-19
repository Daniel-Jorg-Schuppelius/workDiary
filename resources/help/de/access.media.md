---
title: "Zutrittsmedien"
topic: access.media
version: 1
audience: []
related:
    - assets.fleet
---

**Transponder, Karten und Codes** als verwalteter Bestand — die Erweiterung
der physischen Schlüsselübergabe. Jedes Medium hat jederzeit **genau einen
Status** (Im Lager / Ausgegeben / Verloren / Gesperrt / Ausgemustert) und
einen belegten Verbleib.

## Grundsätze

- **Die Mediennummer wird nur gehasht gespeichert** — sichtbar bleiben die
  letzten vier Stellen. Der Klartext ist nur im Anlagemoment bekannt.
- **Inhaber ist Nutzer ODER externe Person** (Name + Firma) — ein
  Reinigungsdienst hat kein Mitarbeiterkonto.
- **workDiary steuert keine Zutrittsanlage.** Der Verwaltungsstand hier und
  der Anlagenstand dort werden über die Sperr-Aufgabe zusammengehalten.

## Verlust und Sperrung

Eine Verlustmeldung setzt den Status auf **Verloren** und erzeugt zwingend
eine **Sperr-Aufgabe** („Medium …1234 in Anlage X sperren", fällig in zwei
Tagen). Erst wer die Sperrung in der Anlage durchgeführt hat, bestätigt sie —
dann wird das Medium **Gesperrt** und die Aufgabe erledigt. Verloren und
gesperrt sind bewusst getrennte Zustände: Genau diese Lücke soll sichtbar
sein, denn in ihr ist das Medium ein Risiko.

## Ausgabe und Rückgabe

Jede Übergabe (Ausgabe/Rückgabe) landet in der **Historie** des Mediums —
mit Inhaber, Zeitpunkt, erwarteter Rückgabe und Zustand. Ein ausgegebenes
Medium kann nicht ausgemustert werden — erst zurücknehmen.
