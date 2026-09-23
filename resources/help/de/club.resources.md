---
title: "Sportstätten und Ressourcen"
topic: club.resources
version: 1
audience: []
modules:
    - module.club
related:
    - club.events
    - club.matches
---

Sportstätten und Ressourcen bilden Hallen, Teilflächen (Hälfte, Drittel),
Tische, Plätze, Bahnen und Stände, Boote und Geräte als **Baum** ab: Eine
Teilfläche hängt unter ihrer Halle, ein Tisch unter der Halle oder einer
Hälfte. Die Konfliktprüfung ist gemeinsam — eine Belegung der ganzen Halle
sperrt alle Teilflächen und Tische darunter, verschiedene freie Teilflächen
sind parallel nutzbar. Es gibt keine isolierten Kalender je Sportart.

**Räume und Assets:** Eine Ressource kann an einen vorhandenen Raum gebunden
sein; dann teilen sich Termine mit diesem Raum und Belegungen der Ressource
(samt Teilflächen) denselben Kalender. Boote und Geräte können an ein Asset
gebunden sein: Sperren des Assets (Wartung, Defekt, Prüfung) verhindern die
Belegung — es gibt keine zweite Verfügbarkeit für dasselbe Objekt und keine
Pflicht, einen Mietvertrag anzulegen.

**Einheiten und Puffer:** Eine Ressource mit mehreren Einheiten (z. B. vier
Bahnen) lässt sich teilweise belegen; die Menge je Termin wird gegen die
Einheiten geprüft. Auf- und Abbaupuffer verlängern das belegte Fenster.

**Belegen:** Ressourcen werden am Termin oder Spieltag belegt — mit Menge,
wahlweise eigenem Zeitfenster, Puffern und der nutzenden Person. Die
Belegung prüft Kapazität, Halle/Teilflächen, Raumkalender, Sperrzeiten und
Asset-Sperren in einer Transaktion; zwei gleichzeitige Buchungen werden nie
beide bestätigt. Auswärtsspielorte sind Ortsangaben und belegen nichts.

**Verschieben und absagen:** Wird ein Termin verschoben, wandern seine
Belegungen mit — bei einem Konflikt bleibt alles beim Alten (alte Zeit, alte
Belegung). Eine Absage gibt alle Belegungen frei.

**Sperrzeiten:** Witterung, Wartung oder Fremdbelegung werden als Sperrzeit
mit Grund erfasst. Bestehende Belegungen werden zur Neuplanung **markiert**,
nicht gelöscht; neue Belegungen im Zeitraum sind gesperrt. Das Aufheben der
Sperre gibt markierte Belegungen wieder frei.

**Freigaben:** Boote, Geräte und ähnliche Ressourcen können eine Einweisungs-
oder Eignungsfreigabe je Mitglied verlangen, befristbar und von der Leitung
erteilt. Ohne gültige Freigabe kann die Person nicht als Nutzende eingetragen
werden; am Termin werden angemeldete Teilnehmer ohne Freigabe angezeigt.
