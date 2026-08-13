---
title: "Zeitkonten (Verwaltung)"
topic: admin.time-accounts
version: 1
audience: [admin]
related:
    - time-accounts.overview
---

Zusatz-Zeitkonten machen aus vorhandenen Zeitdaten führbare Konten:
Nachtdienst-Zähler, Freizeit-/Ansparkonten, Zulagen-Sammler. Gleitzeit und
Urlaub bleiben eigene Konten und werden hier nicht doppelt geführt.

Je Konto legen Sie Einheit (Minuten, Tage, Anzahl), optionale
Ampel-Schwellen und die Übertragsregel fest — kumulierend oder mit Kappung
beim Monatsabschluss. Bebuchungsregeln bestimmen deklarativ die Quelle:
Lohnart-Muster aus der Zeitregel-Engine, Anwesenheits-Netto,
Abwesenheitstage, ein Dienst-Zähler je Schichttyp oder Mengen aus
importierten externen Positionen; ein Faktor gewichtet (z. B. 1,25 für
„Nachtstunde zählt 1:1,25").

Der tägliche Lauf bebucht idempotent; das Journal ist unveränderlich —
Korrekturen laufen als Storno-Gegenbuchung, manuelle Sonderbuchungen
brauchen eine Begründung und werden auditiert. Optional erscheint der
Kontostand in der Terminal-Statusantwort.
