---
title: "CSV-Import"
topic: admin.import
version: 2
audience:
    - admin
schema: process
related:
    - admin.handbook
    - admin.tenants
    - contacts.manage
---

## Zweck und Hintergrund

Der Import-Wizard bringt Stammdaten per CSV nach WorkDiary — mit
Analyse **vor** dem Schreiben und vollständigem Fehlerbericht. Er ist
der schnellste Weg, einen Altbestand (Kunden, Benutzer, Projekte,
Teams, Lieferanten, Materialien) strukturiert zu übernehmen, ohne die
Datenqualität dem Zufall zu überlassen.

## Voraussetzungen

- Administrationsrechte.
- Eine CSV-Datei je Entität; Spaltenzuordnung erfolgt im Wizard.
- Bei abhängigen Daten: die richtige **Reihenfolge** (erst
  Kunden/Teams, dann Projekte & Co.).

## Empfohlener Ablauf

1. **Entität wählen** (z. B. Kunden, Benutzer, Projekte, Teams,
   Lieferanten, Materialien).
2. **CSV hochladen** — die **Preflight-Analyse** prüft Struktur und
   Inhalte, ohne etwas zu schreiben.
3. **Vorschau prüfen:** erkannte Zeilen, Warnungen, Fehler.
4. **Bestätigen** — der Import läuft als Hintergrund-Job.
5. **Fehler-CSV herunterladen:** alle abgewiesenen Zeilen mit
   Begründung; korrigieren und erneut importieren.

![Import-Assistent mit Entitätswahl, Mustervorlage und Vorprüfung](media/administration/import-assistent.png)
*Der Import-Assistent: Entität wählen, Mustervorlage laden, Datei hochladen — die Vorprüfung schreibt nichts.*

## Beispiel aus der Praxis

Beim Umstieg importiert ein Betrieb zuerst eine Testdatei mit zehn
Kunden, prüft Vorschau und Feldzuordnung, und lädt dann den
Vollbestand mit 800 Zeilen. Zwölf Zeilen landen mit Begründung im
Fehlerbericht, werden korrigiert und im zweiten Lauf übernommen.

## Typische Fehler

- **Ohne Testdatei direkt den Vollbestand laden** — Zuordnungsfehler
  multiplizieren sich unnötig.
- **Reihenfolge missachten:** Projekte vor ihren Kunden scheitern an
  fehlenden Bezügen.
- **Fehlerbericht ignorieren:** Fehlerhafte Zeilen brechen den Lauf
  nicht ab — sie fehlen aber im Bestand, bis sie nachimportiert sind.

## Auswirkungen und nächste Schritte

Vor der Bestätigung wird **nichts** geschrieben — Preflight und
Vorschau sind gefahrlos. Die Import-Historie zeigt alle Läufe mit
Status und lässt sich nach Entität und Zustand filtern. Als Nächstes:
importierte Stammdaten stichprobenartig prüfen und Dubletten über die
Zusammenführung bereinigen.
