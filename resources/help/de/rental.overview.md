---
title: "Geräte- & Maschinenverleih"
topic: rental.overview
version: 1
audience: []
modules:
    - module.rental
related:
    - claims.overview
---

Das Modul führt den Verleih von Geräten, Maschinen und Werkzeugen als
nachvollziehbare Verleihakten — von Reservierung und Übergabe über
Laufzeit und Rücknahme bis zu Kaution und Abrechnung.

**Gerätepool:** Ein Verleihprofil macht ein Asset leihfähig und trägt
Gerätegruppe, Pufferzeiten (Transport/Rüsten/Reinigung), Zubehör und die
Standard-Preisliste. Das Asset bleibt im Asset-Modul führend.

**Verfügbarkeit:** Der Kalender zeigt Reservierungen, Verleihzeiträume,
Wartungs-, Reinigungs- und Transportfenster. Doppelbuchungen, gesperrte
oder prüfüberfällige Geräte werden vor Reservierung und Übergabe sichtbar
verhindert (gemeinsames Sperrmodell).

**Verleihakte:** Jeder Vorgang erhält eine eigene Nummer (VER-…), Kunde,
Zeitraum, Übergabe-/Rückgabeort und Verantwortliche. Die angewendete
Preislisten-Version wird als Snapshot eingefroren — spätere Preisänderungen
bewerten alte Fälle nicht um.

**Übergabe & Rücknahme:** Getrennte Protokolle dokumentieren Zustand,
Zubehör, Zählerstand/Betriebsstunden, Fotos und Unterschrift. Die
Rücknahme trägt die Folgeentscheidung: Reinigung, Reparatur/Sperre oder
kontrollierte Übergabe an die Reklamation.

**Abrechnung:** Mietpositionen entstehen aus dem Konditionen-Snapshot oder
manuell, werden freigegeben und lokal fakturiert oder an das führende
Fakturasystem übergeben. Die Kaution läuft als eigener Finanzvorgang —
Einbehalt braucht eine Pflichtbegründung.
