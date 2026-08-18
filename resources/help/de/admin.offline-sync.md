---
title: "Offline-Synchronisierung"
topic: admin.offline-sync
version: 1
audience: []
related:
    - admin.metrics
---

Wer unterwegs ohne Netz arbeitet, erfasst in eine **Geräte-Outbox**; sobald
die Verbindung zurück ist, überträgt das Gerät die Befehle. Diese Seite zeigt
**jeden übertragenen Befehl mit seinem Ergebnis** — die Antwort auf die Frage,
welche Daten offline entstanden sind und ob sie angekommen sind.

## Die vier Ergebnisse

- **Übernommen** — der Befehl ist im Bestand. Der Normalfall.
- **Wiederholung** — dasselbe Gerät hat denselben Befehl doppelt gesendet
  (typisch nach Verbindungsabbruch mitten in der Übertragung). Kein Fehler:
  Der Befehl wurde beim ersten Mal übernommen, die Wiederholung erkannt und
  verworfen.
- **Konflikt** — der Bestand hat sich zwischenzeitlich geändert; der Befehl
  wurde **nicht** angewandt.
- **Abgewiesen** — der Befehl war ungültig (etwa ein Stempelbefehl in einem
  unzulässigen Zustand); die Fehlerspalte nennt den Grund.

**Konflikt und Abgewiesen sind der Grund für diese Seite:** Diese Erfassungen
sind *nicht* im Bestand gelandet. Die Zähler im Ergebnisfilter zählen immer
den Gesamtbestand — ein gesetzter Filter verdeckt sie nicht.

## Die zwei Zeitstempel

**Erfasst (offline)** ist die Gerätezeit der Erfassung, **Übertragen** der
Eingang auf dem Server. Die Spanne dazwischen ist die Offline-Latenz — ein
Tag Abstand ist bei Außendienst normal, eine Woche ein Hinweis, dass ein
Gerät nicht synchronisiert.
