---
title: "Lokale Buchhaltung"
topic: accounting.overview
version: 6
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
- **Steuerkennzeichen:** Die Seite listet die Steuerkennzeichen mit Satz,
  Steuerkonto, Gültigkeit und ihren Kennziffern der Umsatzsteuer-Voranmeldung.
  **Steuerkennzeichen anlegen** erfasst **Kürzel**, **Bezeichnung**,
  **Richtung**, **Steuersatz (%)**, **Steuerkonto**, **Gültig ab** und die
  Kennziffern für **Bemessungsgrundlage** und **Steuerbetrag** – eine
  Abgleichhilfe, kein Vordruck. Über **Bearbeiten** ändern Sie Bezeichnung und
  Kennziffern jederzeit. Satz, Richtung und Steuerkonto eines Kennzeichens,
  mit dem schon gebucht wurde, ändern sich nur zu einem neuen Stichtag: Die
  bisherige Fassung endet am Vortag, Altbuchungen bleiben unverändert,
  Buchungsregeln und Konten-Vorgaben folgen der neuen Fassung. **Stilllegen**
  nimmt ein Kennzeichen aus der Auswahl; bestehende Buchungen behalten es.
  Buchungen aus der Buchungs-Inbox tragen Kennzeichen und Steuerbetrag an der
  Erlös- bzw. Aufwandszeile. Für Umsätze ohne Steuer wählt die Inbox das
  Kennzeichen über die Steuerkategorie des Belegs: innergemeinschaftliche
  Lieferung (Kennziffer 41), sonstige Leistung mit Steuerschuld des Empfängers
  (21), Ausfuhr (43), nicht steuerbar (45); „steuerfrei“ ohne Kennziffer
  erscheint als Klärungsfall. Kleinunternehmer erhalten kein Kennzeichen.
- **Steuerschuld des Empfängers:** Für die Richtung **Umsatz- und Vorsteuer
  (Steuerschuld des Empfängers)** erfasst das Steuerkennzeichen zusätzlich
  **Vorsteuerkonto**, **Steuerfall im Rechnungseingang** und **Kennziffer der
  Vorsteuer**. Die Vorlagen bringen § 13b Abs. 1 und 2 (19 %) und den
  innergemeinschaftlichen Erwerb (19 % und 7 %) samt Konten mit, dazu das
  Kennzeichen der Kennziffer 60 für Umsätze, für die der Kunde im Inland die
  Steuer schuldet. Bestehende Kontenpläne aus der Vorlage erhalten sie mit dem
  Update.
- **DATEV:** **DATEV-BU-Schlüssel** am Kennzeichen nennt den Steuerschlüssel im
  DATEV-Buchungsstapel (etwa 9 für Vorsteuer 19 %, 94 für § 13b).
  **DATEV-Automatik** am Konto nennt das Kennzeichen, dessen Steuer DATEV auf
  diesem Konto selbst errechnet (Automatikkonto wie 8400 im SKR03); dort setzt
  der Stapel keinen Schlüssel.

**Berechtigung:** Ansehen mit **Buchhaltung einsehen**; Vorlage, Import und
alle Änderungen an Konten und Steuerkennzeichen mit **Buchhaltung einrichten**.
