---
title: "Faktura-Übergabe"
topic: finance.transfers
version: 3
keywords:
    - Lexoffice-Übergabe
    - Zeiten an Lexoffice
    - DATEV-Übergabe
    - Rechnungsentwurf erstellen
    - Leistungen übertragen
    - Material abrechnen
    - Stunden fakturieren
    - Abrechnung übergeben
    - Fakturierung
    - Rechnungshoheit
    - Positionen übergeben
    - Faktura-Export
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
**Materialien** an das führende Fakturierungssystem. Sie finden sie im
Menü unter **Faktura-Übergabe**; die Seite **Übergabenachweise** listet
alle Übergaben.

Grundprinzip Rechnungshoheit: **Die Rechnung entsteht im führenden
externen Programm** (z. B. Lexoffice, orgaMAX, sevDesk, easybill oder
DATEV) – WorkDiary liefert nur geprüfte Positionen samt
Übergabenachweis zu. Eine lokale Rechnung in WorkDiary gibt es nur,
wenn keine externe Faktura-Software im Einsatz ist. Je Organisation
bzw. Kunde gilt genau ein **Fakturierungsweg**.

Typischer Ablauf:

1. **Übergabe vorbereiten** (Status **Entwurf**): **Kunde**,
   **Übergabekanal** – **Leistungen/Zeit** oder **Produkte/Material**,
   getrennt –, **Übergabeziel** und **Leistungszeitraum** wählen. Das
   Ziel wird aus dem Fakturierungsweg des Kunden vorbelegt:
   **Lexoffice** (Rechnungsentwurf), **orgaMAX (Auftrag)**, **sevDesk
   (Rechnungsentwurf)** oder **easybill (Rechnungsentwurf)**; daneben
   steht immer der **Datei-Export** zur Wahl. Führt DATEV, erfolgt die
   Übergabe als Datei-Paket (CSV) über den Datei-Export.
2. Positionen prüfen und **Übergabe bestätigen** (Status
   **Bestätigt**). Erst danach lassen sich Bezeichnung und
   Leistungstext bearbeiten sowie Positionen zusammenfassen oder
   entfernen.
3. **Übertragen** → Status **Übergeben** (final). Bei
   **Fehlgeschlagen** setzt **Erneut versuchen** die Übergabe auf
   **Bestätigt** zurück; danach übertragen Sie erneut.
4. Übergaben im Status **Entwurf** oder **Bestätigt** lassen sich mit
   **Übergabe verwerfen** verwerfen – die enthaltenen Positionen werden
   wieder freigegeben.

Risiken und unumkehrbare Aktionen:

- **„Übergeben“ ist final** – die enthaltenen Positionen sind gegen
  Änderungen gesperrt.
- Korrekturen laufen über nachvollziehbare Vorgänge, nie über stilles
  Zurücksetzen: **Übergabe stornieren** gibt die Quellen wieder frei,
  **Korrektur anlegen** erzeugt eine Korrektur-Übergabe mit denselben
  Zeiten. Ein beim Ziel angelegter Entwurf wird dabei nicht gelöscht –
  entfernen Sie ihn dort von Hand.

Berechtigungen: Zeit- und Materialübergaben sind getrennt geschützt
(**Faktura-Zeiten vorbereiten und übertragen** bzw. **Faktura-Material
vorbereiten und übertragen**). Die Liste sehen Personen mit
**Übergabenachweise einsehen**; Stornieren und Korrigieren erfordern
zusätzlich **Finanzkonfiguration verwalten**.
