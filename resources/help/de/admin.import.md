---
title: "CSV-Import"
topic: admin.import
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.tenants
---

Der Import-Wizard bringt Stammdaten per CSV nach WorkDiary – mit
Analyse vor dem Schreiben und vollständigem Fehlerbericht.

Typischer Ablauf:

1. **Entität wählen**: z. B. Kunden, Benutzer, Projekte, Teams,
   Lieferanten, Materialien.
2. **CSV hochladen** → die **Preflight-Analyse** prüft Struktur und
   Inhalte, ohne etwas zu schreiben.
3. **Vorschau prüfen**: erkannte Zeilen, Warnungen und Fehler.
4. **Bestätigen**: der Import läuft als Hintergrund-Job.
5. **Fehler-CSV herunterladen**: alle abgewiesenen Zeilen mit
   Begründung – korrigieren und erneut importieren.

Wichtig zu wissen:

- Vor der Bestätigung wird **nichts geschrieben** – Preflight und
  Vorschau sind gefahrlos.
- Die Import-Historie zeigt alle Läufe mit Status und lässt sich nach
  Entität und Zustand filtern.
- Fehlerhafte Zeilen brechen den Lauf nicht ab; sie landen im
  Fehlerbericht.

Tipps:

- Erst kleine Testdatei importieren, dann den Vollbestand.
- Reihenfolge beachten: erst Kunden/Teams, dann abhängige Daten wie
  Projekte.
