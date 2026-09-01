---
title: "Leasing & Asset-Verträge"
topic: asset-finance.overview
version: 1
audience: []
modules:
    - module.asset_finance
related:
    - investments.overview
    - rental.overview
---

Das Modul führt Leasing-, Mietkauf-, Finanzierungs- und Nutzungsverträge
für Maschinen, Fahrzeuge und IT als operative Leasingakten — mit
Konditionen, Fristen, Nutzungslimits und Soll-Ist-Sicht.

**Leasingakte:** Jeder Vertrag erhält eine eigene Nummer (LEA-…),
Vertragspartner, Laufzeit, Assets, Kostenstelle und Verantwortliche.
Konditionen (Rate, Sonderzahlung, Restwert, Kaufoption) sind vertrauliche
Finanzdaten und brauchen ein eigenes Recht.

**Aktivierung:** Beim Aktivieren werden die Konditionen als Snapshot
eingefroren und der Soll-Ratenplan über die Laufzeit erzeugt. Spätere
Änderungen sind auditpflichtig.

**Fristenkalender:** Kündigung, Verlängerung, Kaufoption, Rückgabe,
Endprüfung, Versicherung und Dokumentablauf mit Vorwarnzeit — fällige
Fristen erzeugen Benachrichtigungen und Eskalationen.

**Ist-Werte nur als Referenz:** Eingangsrechnungen werden Ratenzeilen
zugeordnet, Zählerstände speisen Nutzungslimits — WorkDiary bucht nichts.
Bilanzierung (HGB/IFRS 16) und steuerliche Zurechnung bleiben beim
Rechnungswesen; die Zielgruppe ist B2B (Verbraucherschutz nach CCD II,
ab 20.11.2026, betrifft Verbraucherverträge und wird hier nicht geprüft).

**Rückgabe/Ende:** Der Ende-Prozess dokumentiert Zustand, Kilometer/
Betriebsstunden, Schäden und die Entscheidung — Rückgabe, Kauf,
Verlängerung oder Ersatzinvestition — mit Kostenfolge und DMS-Nachweisen.
