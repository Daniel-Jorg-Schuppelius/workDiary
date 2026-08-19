---
title: "Umfragen"
topic: sales.surveys
version: 1
audience: []
related:
    - contacts.manage
---

Eine schlanke **Umfrage-Engine** für NPS und freie Fragebögen — keine
Marketing-Automation. Fragetypen: **NPS (0–10)**, Skala (1–5), Auswahl,
Freitext. Die Teilnahme läuft über einen **Einmal-Link** (30 Tage gültig),
ohne Portal-Login.

## Drei Pflichtregeln

- **Ermüdungsschutz:** Je E-Mail-Adresse höchstens eine Einladung in 90
  Tagen — über **alle** Fragebögen hinweg. Beim automatischen Trigger wird
  still übersprungen, beim manuellen Versand mit Fehlermeldung abgelehnt.
- **Opt-out je Kunde:** Wer widersprochen hat, wird nicht mehr eingeladen.
- **Anonymität ist eine Speicher-Eigenschaft:** Bei anonymen Fragebögen
  trägt die Antwort keinen Einladungsbezug und die Einladung keinen
  Antwortzeitpunkt — ein Re-Identifikations-Join hat keine Felder. Deshalb
  lässt sich die Einstellung nach der ersten Einladung nicht mehr ändern.

## Auslöser

Manuell je Kunde — oder automatisch **nach Ticketabschluss** (am Fragebogen
aktivierbar). Ein gescheiterter Einladungsversuch verhindert nie den
Ticket-Statuswechsel.

## Auswertung

**NPS-Score** = %Promotoren (9–10) − %Detraktoren (0–6). Ohne Antworten gibt
es keinen Score — kein Wert heißt „nichts zu rechnen", nicht Null. Die
Ticket-CSAT (Bewertung im Portal) bleibt unabhängig davon bestehen.
