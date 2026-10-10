---
title: "SLA, Verträge & Service-Level"
topic: sla.overview
version: 4
keywords:
    - Service Level Agreement
    - Reaktionszeit
    - Lösungszeit
    - Servicevertrag
    - SLA-Verletzung
    - Fristüberschreitung
    - Eskalation
    - Ticket überfällig
    - Einhaltungsquote
    - SLA-Bericht
    - Antwortzeit
audience: []
related:
    - glossary.core
---

SLA-Verträge (Service Level Agreements) hinterlegen je Kunde oder als
**Standardvertrag** für alle Kunden die vereinbarten Reaktions- und
Lösungsfristen je Priorität (**Fristen je Priorität**), optional mit
**Geschäftszeiten** – ohne sie laufen die Fristen in Kalenderzeit. Sie
finden die Verträge unter **Service Desk** → **SLA-Verträge**. Aus diesen
Sollwerten leitet WorkDiary den SLA-Status eines Service-Tickets ab und
dokumentiert Überschreitungen revisionssicher.

## SLA-Status am Ticket

Jedes Service-Ticket mit hinterlegter SLA-Frist zeigt seinen Lösungs-Status
als Badge:

- **SLA im Plan**: ausreichend Restzeit bis zur Lösungsfrist.
- **SLA gefährdet**: die Restzeit liegt bei höchstens 20 % der
  Gesamtfrist.
- **SLA verletzt**: die Frist ist überschritten (oder das Ticket wurde
  zu spät bestätigt bzw. gelöst).
- **SLA erfüllt**: das Ticket wurde rechtzeitig gelöst.

Tickets ohne Frist zeigen „Kein SLA“. Die Reaktionsfrist wird analog
ausgewertet und bei der ersten Bestätigung geprüft.

## Verletzungsregister & Erkennung

Überschrittene Fristen werden in einem Verletzungsregister festgehalten –
je Ticket und Typ („Reaktionszeit“ bzw. „Lösungszeit“) genau einmal.
Erkannt werden sie:

1. bei der automatischen Prüfung offener Tickets, die standardmäßig alle
   fünf Minuten läuft,
2. bei Statusübergängen, wenn die erste Reaktion oder die Lösung zu spät
   erfolgt.

Jede Verletzung lässt sich in der **Verletzungsliste** des SLA-Reports
mit einer **Ursache** versehen und **Quittieren**; dafür ist das Recht
**SLA-Verletzungen quittieren** nötig.

## Eskalation

Die automatische Prüfung benachrichtigt bei gefährdeten und verletzten
Tickets die zugewiesene Person. Bleibt das Ereignis unerledigt, eskaliert
WorkDiary nach den **Benachrichtigungsregeln** der Organisation an die
dort eingestellte Eskalationsrolle (standardmäßig die Teamleitung).
Zusätzlich arbeitet WorkDiary die im SLA-Vertrag unter **Eskalation**
hinterlegten Stufen ab.

## SLA-Report

Die **SLA-Auswertung** (**Auswertungen** → **Projekte & Kunden** → **SLA**)
zeigt im gewählten Zeitraum die **Tickets mit SLA**, die
**Einhaltungsquote** und die **Verletzungen** – gegliedert **Nach Typ**,
**Nach Priorität**, **Nach Kunde** und **Nach Ursache** – sowie eine
**Verletzungsliste** mit Sprung zum Ticket und die
**Inklusivzeit-Kontingente**. Der Report ist als PDF, CSV und Excel
exportierbar. Einsehen darf ihn, wer das Recht **SLA-Status & -Report
einsehen** hat; exportieren zusätzlich nur mit dem Recht **Auswertungen
exportieren** – ohne das Recht fehlen die Exportknöpfe. Administratoren
dürfen beides immer.
