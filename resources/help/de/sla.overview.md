---
title: "SLA, Verträge & Service-Level"
topic: sla.overview
version: 9
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

Die Stufen pflegen Sie im Vertrag unter **Eskalationsstufen**: je Stufe die
Minuten nach Ablauf der Lösungsfrist und den Empfänger – den Bearbeiter des
Tickets, eine Rolle oder eine Person.

## Welcher Vertrag gilt

Für ein neues Ticket gilt der erste passende Vertrag in dieser Reihenfolge:
Vertrag des Assets, Vertrag des Projekts, Vertrag des Kunden, **Standard-SLA**
der Queue, **Standardvertrag** der Organisation. Der Vertrag des Assets ist die
engste Zusage – er gilt für genau dieses Objekt und wird im Formular des Assets
gewählt (Thema „Assets & Fuhrpark“). Er greift nur, wenn er aktiv ist und dem
Kunden des Tickets gehört oder keinen Kunden hat; sonst gilt die übrige
Reihenfolge. Ein Queue-Wechsel lässt ihn stehen. Nennt der Vertrag keine
Geschäftszeiten, gelten die der Queue; die Feiertagsregion der Queue ersetzt
die der Organisation. Der Vertragsstand wird am Ticket eingefroren – spätere
Änderungen am Vertrag deuten bestehende Tickets nicht um.

## Pausen

Unter **SLA-Pause bei** legt der Vertrag fest, in welchen Wartezuständen die
Uhr anhält. Beim Fortsetzen – auch durch eine Antwort des Kunden – verschieben
sich die offenen Fristen um die Dauer der Pause.

## OLA

Ein Vertrag mit **OLA (internes Ziel)** und einem Team ist kein
Kundenversprechen: Er wird nie als SLA eines Tickets gezogen und kann weder
Standardvertrag sein noch an Kunde oder Projekt hängen. Tickets in den Queues
des Teams zeigen das interne Ziel zusätzlich an der SLA-Uhr.

Überschreitet ein offenes Ticket das interne Ziel, erhält die Teamleitung des
OLA-Teams einmal eine Benachrichtigung (ohne sie die zugewiesene Person);
danach greift die Eskalation der Benachrichtigungsregel **Internes Ziel (OLA)
überschritten**. Ein Verletzungsregister führt WorkDiary für OLA nicht.

## Vertragspflichtige Wartungen

Die Vertragsseite listet unter **Vertragspflichtige Wartungen** die
Wartungspläne, die an diesen Vertrag gebunden sind – mit Asset, nächster
Fälligkeit und der Aktion **Bei Fälligkeit**. Pläne mit
**Vertragspflicht** tragen das gleichnamige Kennzeichen. Die Bindung
setzen Sie im Dialog des Wartungsplans am Asset (Thema „Assets &
Fuhrpark“). Mit der Aktion **Service-Ticket anlegen** entsteht je
Fälligkeit ein Ticket, für das dieser Vertrag gilt.

## Inklusivzeit-Kontingente

Am SLA-Vertrag legen Sie mit **Kontingent hinzufügen** je Zeitraum (Monat,
Quartal, Jahr) die Inklusivstunden fest, dazu die **Warnschwelle** in Prozent,
ab der die Fristenüberwachung meldet, und als Nachweis die **Pauschale** und den
**Mehrstundensatz**. Verbraucht werden die abrechenbaren Personenzeiten des
Vertragskunden im Zeitraum. Die Vertragsseite und der SLA-Report zeigen
Verbrauch, Pauschale und den Betrag der Mehrstunden (Stunden über dem
Kontingent × Satz). workDiary stellt keine Rechnung daraus – berechnet wird im
Abrechnungsprogramm. Bearbeiten und Löschen setzt das Recht zur Pflege der
SLA-Verträge voraus.

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

## Materialvorschlag

Ist dem Ticket ein Gerät mit Stückliste zugeordnet, zeigt es die **fälligen Verschleißteile** (aus Wechselintervall und Einbaudatum) als Vorschlag für den Einsatz — was wirklich getauscht wird, entscheidet der Monteur. Dieselbe Karte erscheint am Auftrag mit Gerät.

**Vertrauliche Tickets im Bericht:** Bei vertraulichen Tickets, die Sie nicht
öffnen dürfen, zeigt die Liste der SLA-Verletzungen weder Ticket noch Kunden; in
der Auswertung je Kunde zählen sie unter „Vertrauliches Ticket“.
