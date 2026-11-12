---
title: "Terminbuchung (Calendly) verbinden"
topic: admin.calendly
version: 2
keywords:
    - Online-Terminbuchung
    - Terminbuchungsseite
    - Buchungsseite
    - Terminvereinbarung
    - Terminplaner
    - Kundentermine
    - Online-Termine
    - Termin-Eingang
    - Termin abgesagt
    - Termin verschoben
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
und ändert keine Verfügbarkeiten. Verfügbarkeiten pflegen Sie weiterhin dort,
wo die Buchungsseite verwaltet wird.

**Ohne Kundentreffer:** Eine Buchung, deren Adresse keinem Kunden eindeutig
zugeordnet werden kann, erscheint im **Integrations-Eingang** als Fall mit Ziel
„Kunde“: zuordnen (Vorschläge nach Name), neu anlegen oder verwerfen. Alternativ
ordnen Sie die Anfrage im Termin-Eingang einem Kunden oder Lead zu.

**Gastgeber zuordnen:** Calendly-Gastgeber werden über ihre E-Mail-Adresse
erkannt. Weicht die Calendly-Adresse ab, ordnen Sie sie unter **Calendly-Nutzer
zuordnen** einem Benutzer zu; die Termine gehen dann an diesen Benutzer.

**Rechte:** Verbinden, Webhook, Abgleich, Gastgeber-Zuordnung und Buchungslinks
sind Sache der Organisationsverwaltung. Terminwünsche bestätigen und ablehnen
darf, wer die Disposition führt — auf dieser Seite wie im Termin-Eingang.

**Datenführerschaft:** Buchungen und Absagen aus Calendly gehören zum Bereich Kalender. Führt ein anderes System den Kalender, legt WorkDiary keinen Terminwunsch an und ändert keinen; der Fall steht im Integrations-Eingang zum einmaligen Übernehmen.
