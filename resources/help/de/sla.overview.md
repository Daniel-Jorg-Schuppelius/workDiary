---
title: "SLA, Verträge & Service-Level"
topic: sla.overview
version: 1
audience: []
related:
    - glossary.core
---

SLA-Verträge (Service Level Agreements) hinterlegen je Kunde oder als
Standard die vereinbarten Reaktions- und Lösungsfristen je Priorität. Aus
diesen Sollwerten leitet WorkDiary den SLA-Status eines Service-Tickets ab
und dokumentiert Überschreitungen revisionssicher.

## SLA-Status am Ticket

Jedes Service-Ticket mit hinterlegter SLA-Frist zeigt seinen Lösungs-Status
als Badge:

- **SLA im Plan**: ausreichend Restzeit bis zur Lösungsfrist.
- **SLA gefährdet**: die Restzeit liegt unter 20 % der Gesamtfrist.
- **SLA verletzt**: die Frist ist überschritten (oder das Ticket wurde
  zu spät bestätigt/gelöst).

Die Reaktionsfrist wird analog ausgewertet und bei der ersten Bestätigung
geprüft.

## Verletzungsregister & Erkennung

Überschrittene Fristen werden in einem Verletzungsregister festgehalten –
je Ticket und Typ (Reaktion bzw. Lösung) genau einmal. Erkannt werden sie:

1. beim nächtlichen Scan offener Tickets (`tickets:scan-sla-breaches`),
2. bei Statusübergängen, wenn die erste Reaktion oder die Lösung zu spät
   erfolgt.

Jede Verletzung lässt sich quittieren und mit einer Ursache versehen.

## Eskalation

Der Fristen-Scanner benachrichtigt bei gefährdeten und verletzten Tickets
den Ticket-Verantwortlichen sowie – als Eskalation – die Teamleitung. Die
Schwellen und Empfänger folgen den Benachrichtigungsregeln der Organisation.

## SLA-Report

Die SLA-Auswertung (Auswertungen → SLA) zeigt im gewählten Zeitraum die
Einhaltungsquote, die Verletzungen je Typ, Priorität und Kunde sowie eine
Ursachen-Gruppierung und eine Verletzungsliste mit Sprung zum Ticket. Der
Report ist als CSV und PDF exportierbar.
