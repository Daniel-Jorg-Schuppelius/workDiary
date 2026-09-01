---
title: "Faktura-Übergabe"
topic: finance.transfers
version: 1
audience: []
modules:
    - module.finance
related:
    - exports.payroll
    - admin.surcharge-rules
    - roles.buchhaltung
    - glossary.core
---

Die Faktura-Übergabe überträgt abrechenbare **Zeiten** und
**Materialien** an das führende Fakturierungssystem.

Grundprinzip Rechnungshoheit: **Die Rechnung entsteht im führenden
externen Programm** (DATEV oder Lexoffice) – WorkDiary liefert nur
geprüfte Positionen samt Übergabenachweis zu. Eine lokale Rechnung in
WorkDiary gibt es nur, wenn keine externe Faktura-Software im Einsatz
ist. Pro Organisation/Kunde gilt genau ein Fakturierungsweg.

Typischer Ablauf:

1. **Übergabe anlegen** („Entwurf"): Kanal wählen –
   „Leistungen/Zeit" oder „Produkte/Material" (getrennt) – und Ziel:
   „Lexoffice" (Rechnungsentwurf via API), „DATEV" (derzeit als
   Datei-Paket) oder „Datei-Export" (CSV).
2. Positionen prüfen und **bestätigen** („Bestätigt").
3. **Ausführen** → Status **„Übergeben"** (final). Bei
   „Fehlgeschlagen" ist eine Wiederholung aus „Bestätigt" möglich.
4. „Entwurf"/„Bestätigt" lassen sich **verwerfen** – die enthaltenen
   Positionen werden wieder freigegeben.

Risiken und unumkehrbare Aktionen:

- **„Übergeben" ist final** – die enthaltenen Positionen sind gegen
  Änderungen gesperrt.
- Korrekturen laufen über nachvollziehbare **Storno-/
  Differenzübergaben**, nie über stilles Zurücksetzen.

Berechtigungen: Zeit- und Materialübergaben sind getrennt geschützt.
Nur Personen mit der jeweiligen Faktura-Berechtigung können den
entsprechenden Kanal ausführen.
