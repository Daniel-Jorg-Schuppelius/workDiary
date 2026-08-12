---
title: "Freie Zeit-Dimensionen"
topic: admin.time-dimensions
version: 1
audience: []
related:
    - reports.overview
---

Freie Zeit-Dimensionen erweitern die Zeitaufteilung um eigene
Zuordnungsziele, die es als WorkDiary-Modell nicht gibt — etwa
ERP-Aufträge, Anlagen-Nummern oder interne Verrechnungsobjekte.
Vorhandene Stammdaten (Projekte, Kostenstellen, Standorte, Fahrzeuge,
Tätigkeiten) werden dagegen immer direkt referenziert und nie als
Dimension gespiegelt.

Ein Dimensionstyp bündelt zusammengehörige Werte unter einem Namen und
Code. Deaktivierte Typen verschwinden aus dem Aufteilen-Dialog;
bestehende Aufteilungen und Auswertungen bleiben unverändert. Werte
können einen Gültigkeitszeitraum tragen — außerhalb davon werden sie im
Dialog nicht mehr angeboten.

Die externe ID je Wert ist der Anker für eine spätere automatische
Synchronisation aus einem Fremdsystem (z. B. ERP-Kostenträger). Bis
dahin werden Werte hier manuell gepflegt; Anlage, Umschalten und
Löschen werden auditiert.

In der Auswertung „Zeitaufteilung nach Dimension" bildet jeder
Dimensionstyp eine eigene Gruppe mit seinen Werten.
