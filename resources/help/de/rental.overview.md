---
title: "Geräte- & Maschinenverleih"
topic: rental.overview
version: 3
keywords:
    - Vermieter
    - Ferienwohnung
    - Mietverhältnis
    - Vermietung
    - Mietgeräte
    - Mietpark
    - Werkzeugverleih
    - Baumaschinenvermietung
    - Mietvertrag
    - Kaution
    - Gerät reservieren
    - Rückgabe
    - Übergabeprotokoll
    - Verfügbarkeitskalender
    - Mietpreisliste
    - Telematik
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

**Einsatzort und Geofence:** Kommen Gerätepositionen per Import (etwa aus
einem Telematik-Export), prüft WorkDiary während eines Verleihs, ob das Gerät
am Einsatzort ist: am Standort des Verleihs mit dem in den Einstellungen
festgelegten Radius, sonst in den Geofences des Kunden. Verlässt es ihn,
werden die verantwortliche Person und die Teamleitung benachrichtigt.

## Wohnungen und Ferienwohnungen

Das Branchenprofil **Vermieter** empfiehlt das Verleihmodul. Unter **Wohnungen & Belegung** legen Sie Wohnungen für Dauervermietung, Ferienvermietung oder beides an. Die Belegungsübersicht nutzt den globalen Header-Zeitraum und zeigt auch freie Wohnungen.

Dauervermietungen enthalten Mieter, Mietbeginn, optional den letzten Miettag, Kaltmiete, Nebenkosten und Kaution. Ein bestätigtes Mietverhältnis blockiert die Wohnung bis einschließlich Mietende; ohne Enddatum unbefristet. Beim Auszug setzen Sie das Ende. Stornieren hebt die gesamte Belegung auf.

Ferienanfragen entscheiden Sie unter **Verleih-Anfragen**. Die Annahme einer Wohnungsanfrage reserviert sofort verbindlich. Die Standard-Preisliste liefert den Preis pro Nacht als Tagessatz und gegebenenfalls die Endreinigung. Gästezahl, Mindestaufenthalt, Anreise- und Abreisezeiten werden geprüft.

Für das Kundenportal müssen die Wohnung und der Bereich **Verleih-Anfragen** für den Kunden freigegeben sein. Monatsmieten und Kautionen der Dauervermietung sind im MVP Verwaltungsdaten; eine automatische Mietabrechnung oder Nebenkostenabrechnung erfolgt nicht.

Das Profil bringt außerdem drei Abläufe mit (**Wohnungsübergabe**, **Wohnungsabnahme**, **Gästewechsel Ferienwohnung**), das **Wohnungsübergabeprotokoll** mit Zählerständen und Schlüsseln, Vertragsvorlagen für den Wohnraummietvertrag mit und ohne Indexmiete sowie für Mietbedingungen einer Ferienwohnung mit Fristen wie der jährlichen Betriebskostenabrechnung, und eigene Felder der Wohnung (Wohnfläche, Zimmer, Etage, Haustiere, Energieausweis). Ein angefragtes Mietverhältnis können Sie bestätigen, ablehnen oder stornieren, ein bestätigtes nur noch stornieren. Stornierte und abgelehnte Mietverhältnisse bleiben unverändert.
