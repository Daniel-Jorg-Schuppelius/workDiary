---
title: "Prozedur ausführen"
topic: procedures.run
version: 2
keywords:
    - Checkliste abarbeiten
    - Arbeitsanweisung ausführen
    - Ablauf starten
    - Pflichtschritte
    - Abweichung erfassen
    - Gegenzeichnung
    - Vier-Augen-Prüfung
    - Backup-Nachweis
    - Schritt bestätigen
audience: []
related:
    - protocols.create
---

Eine Prozedur (Arbeitsanweisung mit Pflichtschritten) wird auf einem
**Auftrag** oder **Asset** gestartet. Während des Laufs gilt:

- **Pflichtschritte** müssen in der definierten Reihenfolge bearbeitet
  werden.
- **Backup-Schritte** verlangen den Nachweis (Hash + Größe oder externer
  Ablage-Link), bevor Sie weiterklicken können.
- **Vier-Augen-Schritte** brauchen die Gegenzeichnung einer **zweiten Person**
  mit passender Rolle (siehe unten).
- **Abweichungen** werden mit Begründung und Folgeaktion erfasst – ein
  Überspringen ohne Begründung ist nicht möglich.

Beim Abschluss der Prozedur wird die verwendete Versionsnummer der
Vorlage fixiert. Spätere Vorlagenänderungen wirken **nicht** rückwirkend.

## Vier-Augen-Schritte

Die Leiste **Vier-Augen-Freigabe** am Schritt zeigt den Stand: noch nicht
angefordert, angefordert, übernommen oder gegengezeichnet.

1. Wer den Schritt ausführt, klickt **Zweite Person anfordern**. Die
   Teamleitung erhält eine Benachrichtigung mit Link auf den Lauf.
2. Eine andere berechtigte Person öffnet den Lauf und klickt **Als zweite
   Person gegenzeichnen**. Wer angefordert hat, kann nicht selbst
   gegenzeichnen.
3. Erst danach lässt sich der Schritt erledigen.

Eine Gegenzeichnung lässt sich mit Begründung **widerrufen** (Recht
„Vier-Augen-Freigabe widerrufen“, in der Regel Leitung oder Administration). Der
Schritt fällt dann auf „offen“ zurück, die Anforderung bleibt bestehen und
eine andere Person kann gegenzeichnen. Anforderung, Gegenzeichnung und
Widerruf stehen mit Person und Zeitpunkt im Verlauf des Laufs.
