---
title: "Wächterrundgänge"
topic: patrols.overview
version: 2
keywords:
    - Wächterkontrollsystem
    - Kontrollgang
    - Streifengang
    - Kontrollpunkte scannen
    - Rundgangsnachweis
    - Wachdienst
    - Sicherheitsdienst
    - Revierdienst
    - Objektschutz
    - QR-Code scannen
    - Rundgang abbrechen
    - Token neu ausgeben
audience: []
modules:
    - module.planung
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

## Abbruch

Ein laufender Rundgang lässt sich **abbrechen** — nur mit **Begründung**. Er
zählt dann nicht als abgeschlossen; bestätigte Kontrollpunkte bleiben als
Nachweis stehen, und der Bericht weist den Abbruch mit Grund, Person und
Zeitpunkt aus. Offene Kontrollpunkte gehen wie eine Abweichung als **offener
Punkt** an die Leitstelle. Danach ist die Route wieder frei.

## Abweichungen

Verpasste Punkte oder Scans außerhalb des Fensters werden **gezeigt, nie
geglättet** — und der Abschluss verlangt dann eine **Begründung**. Zusätzlich
entsteht ein **offener Punkt** an der Leitstelle (fällig am Folgetag) — die
Eskalation läuft über das vorhandene System, kein eigener Kanal.

Die Soll-Zeiten sind **Nachweis-, keine Leistungsdruck-Metrik**: Es gibt
bewusst keine Positionsdaten am Scan und keine personenbezogene
Dauerauswertung.

## Routen und Kontrollpunkte pflegen

**Bearbeiten** ändert Bezeichnung, Objekt und die **Verantwortliche** der
Route. Eine Route lässt sich **deaktivieren** — ein laufender Rundgang darf zu
Ende gehen, neue lassen sich nicht starten. Kontrollpunkte lassen sich
bearbeiten (Ort, Soll, Toleranz; erfasste Scans behalten ihre Bewertung) und
**deaktivieren**: Ab dann werden sie nicht mehr erwartet und lassen sich nicht
mehr scannen. Frühere Rundgänge bleiben unverändert: Ein Lauf, der vor der
Deaktivierung begonnen hat, erwartet den Punkt weiter.

## Eskalation

Der offene Punkt entsteht **sofort bei der Abweichung** — mit dem ersten Scan
außerhalb des Fensters oder sobald ein fälliger Punkt übersprungen ist — und
geht an die Verantwortliche der Route, ohne Eintrag an die Teamleitung der
Person, die den Rundgang durchführt. Je Lauf gibt es einen offenen Punkt; er
wird bis zum Abschluss fortgeschrieben. Läuft ein Rundgang nach der Soll-Dauer
plus Toleranz des letzten Kontrollpunkts noch, meldet ihn der Fristenlauf
einmal als **nicht beendet**; die Leitstelle kann ihn dann mit Begründung
abbrechen.
