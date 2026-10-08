---
title: "Lokale Buchhaltung"
topic: accounting.overview
version: 3
keywords:
    - Hauptbuch
    - Finanzbuchhaltung
    - FiBu
    - Buchführung
    - doppelte Buchführung
    - EÜR
    - Einnahmenüberschussrechnung
    - Buchhaltung einrichten
    - Buchungsbeginn
    - Buchhaltungssoftware ersetzen
    - Buchungshoheit
    - integrierte Buchhaltung
    - Kontenplan
    - SKR03
    - SKR04
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.finance
schema: process
related:
    - accounting.posting
    - accounting.closing
    - finance.datev-bookings
---

## Zweck und Hintergrund

Die lokale Buchhaltung führt ein eigenes Hauptbuch in WorkDiary — für
Organisationen ohne separate Buchhaltungssoftware. Sie ersetzt weder
die Buchhaltungs-Plugins noch deren Datenführerschaft. Drei
Führungsfragen bleiben strikt getrennt: **Fakturahoheit** (wer stellt
Rechnungen aus?), **Stammdatenhoheit** (wer führt Kunden und
Lieferanten?) und **Buchungshoheit** (wer führt das Hauptbuch?) — je
Zeitraum führt entweder WorkDiary oder genau ein externes System.

## Voraussetzungen

- Rolle **Buchhaltung** oder Administration.
- Entscheidung für ein Profil: Einnahmenüberschussrechnung oder
  doppelte Buchführung.
- Basiswährung, Geschäftsjahr und Buchungsbeginn (Stichtag).
- Kein externes System mit Buchungshoheit im selben Zeitraum.

## Empfohlener Ablauf

1. **Vertrieb & Abrechnung** → **Buchhaltung** → **Einrichtung** öffnen und
   das Profil wählen.
2. Basiswährung, Geschäftsjahr und Buchungsbeginn festlegen.
3. Den **Preflight** durcharbeiten: Er prüft, ob die Organisation ab
   dem Stichtag lückenlos selbst buchen kann.
4. Erst wenn kein Punkt mehr rot ist, die lokale Buchhaltung
   **aktivieren**.
5. Danach laufen Buchungen über das Journal (siehe „Buchen"), der
   Abschluss über die Abschluss-Seite.

![Einrichtung der lokalen Buchhaltung mit Profilwahl und Preflight](media/buchhaltung/buchhaltung-einrichtung.png)
*Die Einrichtung: Buchhaltungsprofil links, rechts der Preflight — aktiviert wird erst ohne rote Punkte.*

## Beispiel aus der Praxis

Ein kleiner Handwerksbetrieb kündigt seine Buchhaltungssoftware zum
Jahreswechsel: Im Dezember wird das EÜR-Profil eingerichtet, der
Preflight abgearbeitet und der Buchungsbeginn auf den 1. Januar
gelegt. Die Dezember-Belege bleiben im Altsystem — ab Januar bucht
WorkDiary.

## Typische Fehler

- **Rückwirkend buchen wollen:** Belege vor dem Stichtag bleiben
  Historie und werden nicht nachgebucht.
- **Doppelte Buchungshoheit:** Parallel im Altsystem und in WorkDiary
  buchen erzeugt zwei Wahrheiten — der Preflight verhindert das
  bewusst.
- **Aktivieren trotz roter Preflight-Punkte erzwingen wollen** — die
  Lücken holen einen beim ersten Abschluss ein.

## Auswirkungen und nächste Schritte

Mit der Aktivierung wird WorkDiary zum führenden Hauptbuch ab dem
Stichtag: Journal, offene Posten und Abschluss bauen darauf auf. Als
Nächstes: Buchungslogik und Belegeingang kennenlernen („Buchen") und
den ersten Monatsabschluss planen.

## Kontenplan

Die Konten der lokalen Buchhaltung pflegen Sie unter **Vertrieb & Abrechnung**
→ **Buchhaltung** → **Kontenplan**. Der Menüpunkt erscheint, sobald Ihre
Organisation die lokale Buchhaltung führt oder geführt hat.

- **Kontenplan aus Vorlage:** Wählen Sie unter **Vorlage** einen Auszug aus
  SKR03 oder SKR04 und klicken Sie auf **Vorlage anwenden**. Angelegt werden
  Konten, Steuerkennzeichen und passende Buchungsregeln, damit die
  Buchungs-Inbox sofort arbeiten kann; vorhandene Konten und Regeln bleiben
  unverändert. Die Vorlage ist ein Einstieg für Deutschland – Kontenwahl und
  Steuerzuordnung gehören vor dem ersten Buchen fachlich geprüft.
- **Konto anlegen** und **Konto bearbeiten:** **Konto** (die Kontonummer, je
  Organisation eindeutig), **Bezeichnung**, **Kontoart**, **Saldenrichtung**
  (aus der Kontoart vorbelegt), **DATEV-Konto** (nur für den Export), die
  Merkmale **Offene Posten**, **Bank**, **Kasse**, **Klärung** und
  **Kostenstelle Pflicht**, für die Einnahmenüberschussrechnung **EÜR-Zeile**
  und **Abziehbarer Anteil (%)** sowie eine **Beschreibung**. Buchungen auf
  Konten mit dem Merkmal **Offene Posten** erscheinen in der Liste der offenen
  Posten.
- **Stilllegen** statt löschen: Ein stillgelegtes Konto behält seine
  Buchungen, steht aber für neue Buchungen nicht mehr zur Wahl. Die Liste zeigt
  standardmäßig **nur aktive** Konten; die Suche (Nummer, Bezeichnung) und der
  Filter nach Kontoart grenzen weiter ein.
- **Kontenplan importieren:** eine CSV-Datei mit Kopfzeile und den Spalten
  `number`, `name` und `type`, optional `normal_balance`, `is_open_item`,
  `datev_account`, `euer_category` und `deductible_percent`. Bestehende
  Kontonummern werden aktualisiert, neue Konten angelegt, gelöscht wird nichts;
  fehlerhafte Zeilen werden übersprungen und gezählt.
- **Steuerkennzeichen:** Sind Steuerkennzeichen angelegt, listet die Seite sie
  mit ihren Kennziffern der Umsatzsteuer-Voranmeldung. Über **Bearbeiten**
  ordnen Sie **Bemessungsgrundlage** und **Steuerbetrag** je eine Kennziffer zu
  – eine Abgleichhilfe, kein Vordruck.

**Berechtigung:** Ansehen mit **Buchhaltung einsehen**; Vorlage, Import und
alle Änderungen an Konten und Steuerkennzeichen mit **Buchhaltung einrichten**.
