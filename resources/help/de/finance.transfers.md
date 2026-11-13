---
title: "Faktura-Übergabe"
topic: finance.transfers
version: 7
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
    - zugehörige Belege
    - Belegkette
    - Rechnung beim Anbieter
    - Nachweise versenden
    - Versandakte
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

**Pauschalen:** Nennt die Abrechnungsregel des Projekts oder die
Standardleistung der Organisation die Einheit „Pauschale“, entsteht je Auftrag
eine Position mit Menge 1 zum Preis der Leistung; die Zeiten des Auftrags bleiben
die Quellen der Position. Alle anderen Einheiten, etwa Stunde, Tag oder Stk,
rechnen nach Dauer. Taktung und vereinbarte Stunden gelten nicht für Pauschalen.

## Zugehörige Belege

Unten auf der Seite listet **Zugehörige Belege** alle Belege, die mit
diesem zusammenhängen: Angebot und seine Versionen, Rechnungen, Abschläge und
Schlussrechnung, Gutschriften und Stornos, Lieferscheine, Fertigungsaufträge
und Übergaben. Hat ein Buchhaltungsprogramm einen Beleg übernommen, steht
sein Gegenstück dort mit der Nummer des Programms. Bezüge, die das Programm
selbst meldet, etwa ein in Lexoffice angelegter Abschlag, erscheinen ebenfalls.
Die Spalte **Bezug** sagt, was der Beleg für diesen ist; „über …“ nennt den
Beleg, über den der Zusammenhang besteht.

## Rechnung beim Anbieter: Nachweise und Versand durch WorkDiary

Führt Lexware Office, sevDesk, easybill oder orgaMAX die Rechnung, bleiben
Nummer und Inhalt dort. WorkDiary kann die festgeschriebene Rechnung trotzdem
mit Leistungsnachweisen ausstatten und zustellen, ohne eigene Rechnung.

1. Schreiben Sie den Entwurf im Fakturasystem fest.
2. Klicken Sie im Übergabenachweis in der Karte **Rechnung beim Anbieter**
   auf **Rechnung aus … abrufen**. WorkDiary holt Nummer, Stand und das
   Original und ordnet die Positionen der Übergabe zu. Für geteilte,
   gebündelte oder direkt im Fakturasystem angelegte Rechnungen nutzen Sie
   **Weitere Rechnung zuordnen**.
3. Weichen die Positionen beim Anbieter ab, bestätigen Sie die Abweichung
   auf der Rechnungsseite mit einer Begründung. Ohne Bestätigung sperrt der
   Versand.
4. Erzeugen oder laden Sie Nachweise in der Karte **Rechnungsbegründende
   Unterlagen** hoch und klicken Sie auf **Zustellen**.

WorkDiary ergänzt das Original in seinem Format: ein PDF geht unverändert
mit den Nachweisen als Beilagen, eine XRechnung trägt sie eingebettet, ein
ZUGFeRD-PDF wird mit ergänzter XML neu geschrieben. Signierte Originale
werden nie verändert. Die **Versandakte** hält jede Fassung mit Hashes,
Nachweisen und Zustellversuchen. **Wiederholen** sendet dieselben Dateien;
bleibt ein Versuch ohne Rückmeldung, klären Sie mit dem Empfänger, ob er
ankam. Später erstellte Nachweise gehen über **Nachweise nachsenden**.

Voraussetzung ist der Versandweg „WorkDiary stellt zu“ im Kundenstamm oder
in den Organisationseinstellungen. Schalten Sie dann den automatischen
Versand im Fakturasystem ab, sonst erhält der Kunde die Rechnung zweimal.
