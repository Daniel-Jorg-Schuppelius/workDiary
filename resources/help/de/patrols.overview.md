---
title: "Wächterrundgänge"
topic: patrols.overview
version: 1
audience: []
related:
    - dispatch.overview
---

Ein **Rundgang** ist eine geordnete Liste von **Kontrollpunkten** mit
Soll-Fenstern relativ zum Start („Punkt 3: +20 min ± 10"). Der **Scan belegt
Punkt und Zeit** — der belastbare Nachweis gegenüber Auftraggebern
(Bewachung, Facility, Winterdienst).

## Tokens

Jeder Kontrollpunkt bekommt einen **Token** (auf den Tag gedruckt/als QR).
Gespeichert wird nur der Hash; der Klartext erscheint genau einmal — beim
Anlegen. **Ein verlorener Tag** wird über „Token neu ausgeben" ersetzt: neuer
Token, gleiche Route, der alte ist sofort wertlos.

## Durchführung

Rundgang starten → Tokens scannen (Kamera-Scanner tippt als Tastatur, oder
von Hand eingeben) → abschließen. Je Route läuft höchstens ein Rundgang
zugleich; Doppelscans zählen einmal.

## Abweichungen

Verpasste Punkte oder Scans außerhalb des Fensters werden **gezeigt, nie
geglättet** — und der Abschluss verlangt dann eine **Begründung**. Zusätzlich
entsteht ein **offener Punkt** an der Leitstelle (fällig am Folgetag) — die
Eskalation läuft über das vorhandene System, kein eigener Kanal.

Die Soll-Zeiten sind **Nachweis-, keine Leistungsdruck-Metrik**: Es gibt
bewusst keine Positionsdaten am Scan und keine personenbezogene
Dauerauswertung.
