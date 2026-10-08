---
title: "Zuschlagsregeln"
topic: admin.surcharge-rules
version: 3
keywords:
    - Nachtzuschlag
    - Sonntagszuschlag
    - Feiertagszuschlag
    - Wochenendzuschlag
    - SFN-Zuschläge
    - Zeitzuschläge
    - Lohnart
    - Lohnexport
    - DATEV Lohn
    - Lexware
    - Schichtzuschlag
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.lohn
related:
    - exports.payroll
    - finance.transfers
    - admin.handbook
    - glossary.core
---

Zuschlagsregeln definieren Nacht-, Wochenend-, Feiertags- und
benutzerdefinierte Zeitfenster-Zuschläge sowie Zuschläge für
Bereitschaft, Rufbereitschaft und Überstunden. Beim Zeitexport werden
die Zeiten danach ausgewertet und je Lohnart als eigene Zeilen
ausgewiesen.

Typischer Ablauf:

1. **Anlegen** öffnet den Dialog **Zuschlagsregel anlegen**. Unter
   **Grunddaten** tragen Sie **Code** (eindeutig, z. B. „night“),
   **Bezeichnung** (z. B. „Nachtzuschlag“), **Art** und **Zuschlag (%)**
   (0–999,99) ein.
2. **Art** wählen: **Nacht** (Zeitfenster, auch über Mitternacht, z. B.
   22:00–06:00), **Samstag**, **Sonntag**, **Feiertag** (gesetzliche
   Feiertage automatisch), **Benutzerdefiniert** (freies Zeitfenster),
   **Bereitschaft**, **Rufbereitschaft** oder **Überstunden**. Für Nacht
   und Benutzerdefiniert legen Sie das **Zeitfenster** mit **Fenster
   von** und **Fenster bis** fest.
3. Unter **Lohnübergabe** optional die **Lohnart** für DATEV/Lexware
   (z. B. „2010“) und die **Priorität** hinterlegen. Mit **Steuerfrei
   bis (%)** und **Lohnart steuerpflichtiger Anteil** teilen Sie einen
   Zuschlag über der steuerfreien Grenze auf zwei Lohnarten auf.
4. Unter **Gültigkeit** optional **Gültig ab**/**Gültig bis** setzen und
   **Regel ist aktiv** einschalten; unter **Bedingungen** begrenzen Sie
   die Regel auf **Teams**, **Standorte** oder **Schichttypen**.

Wichtige Regeln:

- Bei überlappenden Regeln der Arten Nacht, Samstag, Sonntag, Feiertag
  und Benutzerdefiniert gewinnt der **höchste Prozentsatz** – es wird
  nicht addiert. Bei Gleichstand entscheidet die Priorität.
- **Bereitschaft** wertet die Stunden eingetragener Bereitschaften aus,
  **Rufbereitschaft** die Zeiteinträge der Art Bereitschaft und
  **Überstunden** die Stunden über dem Monatssoll. Diese drei Arten
  brauchen kein Zeitfenster, werden nicht mit den übrigen Zuschlägen
  verrechnet und erscheinen als eigene Zeilen. Gültigkeit und
  Bedingungen wertet der Export für sie derzeit nicht aus.
- Bedingungen schränken eine Regel ein: leer = gilt für alle; mehrere
  Bedingungen sind UND-verknüpft, innerhalb einer Liste genügt ein
  Treffer. Der Standort wird über Terminal-Stempel erkannt — ohne
  ermittelbaren Kontext greift eine bedingte Regel nicht. Standorte
  können eine eigene Feiertags-Region tragen (Feiertagszuschlag am
  Einsatzort).
- Änderungen wirken auf **künftige Exporte**; bereits erzeugte
  Exporte bleiben unverändert (Korrektur über Re-Export). Historische
  Zeiträume bewertet nur eine auditierte Neuberechnung durch den
  Betrieb neu — nie eine stille Regeländerung.

Berechtigungen: Mit **Zuschlagsregeln sehen** sehen Sie die Liste;
anlegen, bearbeiten und löschen dürfen nur Personen mit dem Recht
**Zuschlagsregeln verwalten**.
