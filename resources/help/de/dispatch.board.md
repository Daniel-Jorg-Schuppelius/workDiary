---
title: "Leitstelle: Board und Karte"
topic: dispatch.board
version: 1
audience: []
modules:
    - module.planung
related:
    - dispatch.overview
    - tours.manage
    - sla.overview
---

Die **Leitstelle** zeigt die offenen und geplanten Aufträge eines Zeitraums auf
einen Blick — als **Board** (Spalten) oder als **Karte**. Sie ist eine reine
Übersicht: alle Änderungen erfolgen weiterhin im jeweiligen Auftrag.

## Board

Das Board gruppiert die Aufträge des gewählten Zeitraums wahlweise:

- **Nach Status**: Spalten nach Dispositionsstatus (Ungeplant, Geplant,
  Bestätigt, Unterwegs, Erledigt).
- **Nach Mitarbeiter**: eine Bahn je zugewiesenem Mitarbeiter.

Jede Karte nennt Kunde, Zeitfenster und Mitarbeiter und markiert besondere
Lagen:

- **Konflikt**: für die aktuelle Zuweisung liegt ein **harter
  Dispositionskonflikt** vor (z. B. Doppelverplanung, Schichtüberschneidung).
- **SLA**: für den Kunden ist ein Service-Ticket **gefährdet** oder
  **verletzt** (SLA-Risiko).

Ein Klick auf eine Karte öffnet den Auftrag.

## Karte

Die Karte verortet die Aufträge über ihren eigenen Standort oder — falls keiner
hinterlegt ist — über den **Kundenstandort**. Die Marker-Farbe folgt dem
Dispositionsstatus; **SLA-gefährdete oder verletzte** Aufträge werden **rot**
hervorgehoben. Über die Filter lassen sich gezielt **nur SLA-Risiken** oder
**nur unbestätigte** Aufträge anzeigen.

## Bewusst nicht enthalten

Die Leitstelle ist reine Visualisierung. **Tourenoptimierung**,
**Echtzeit-Tracking** und **dauerhafte Standortüberwachung** sind aus
Datenschutzgründen kein Bestandteil dieser Ansicht.
