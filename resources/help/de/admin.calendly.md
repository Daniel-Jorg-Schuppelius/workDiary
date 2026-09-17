---
title: "Terminbuchung (Calendly) verbinden"
topic: admin.calendly
version: 1
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - appointments.inbox
---

Die Anbindung holt Termine, die Kunden über eine Buchungsseite vereinbaren,
in die Terminverwaltung.

**Verbinden:** Die Verbindung wird einmal je Organisation hergestellt und gilt
danach für alle Buchungsseiten des verbundenen Kontos. Zugangsdaten werden
verschlüsselt abgelegt und nach dem Speichern nicht mehr im Klartext gezeigt.

**Eingehende Buchungen:** Neue Termine landen zuerst im **Termin-Eingang**,
nicht direkt im Kalender. Dort werden sie einem Kunden zugeordnet — eindeutige
Treffer automatisch, unklare Fälle bleiben zur Entscheidung liegen. Erst
danach entsteht ein Termin.

**Absagen und Verschiebungen** werden nachgeführt, sofern die Buchungsseite
sie meldet. Ein bereits übernommener Termin wird dabei nicht still gelöscht,
sondern als abgesagt gekennzeichnet.

**Grenzen:** Die Anbindung liest Buchungen; sie legt keine Buchungsseiten an
und ändert keine Verfügbarkeiten. Verfügbarkeiten pflegst du weiterhin dort,
wo die Buchungsseite verwaltet wird.
