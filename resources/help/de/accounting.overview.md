---
title: "Lokale Buchhaltung"
topic: accounting.overview
version: 2
audience:
    - admin
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

1. **Finanzen → Buchhaltung einrichten** öffnen und das Profil wählen.
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
