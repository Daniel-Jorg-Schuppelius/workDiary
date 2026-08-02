---
title: "Offene Zeiten"
topic: finance.open-times
version: 1
audience: []
related:
    - finance.transfers
    - roles.buchhaltung
    - glossary.core
---

Die Arbeitsliste **Offene Zeiten** zeigt alle Zeiteinträge der
Organisation, die noch **nicht abgerechnet** sind — unabhängig davon,
wer sie erfasst hat. Sie ist das Kontrollinstrument der Buchhaltung,
damit vor einem Rechnungslauf keine Zeiten durch die Lappen gehen.

Was gilt als „offen"? Ein Eintrag, der noch von keinem
Abrechnungsweg verbraucht wurde — weder von einer lokalen Rechnung
noch vom Kundenkonto-Abschluss oder einer Faktura-Übergabe.

Funktionen:

1. **Kennzahlen** oben: Anzahl offener Einträge, offene Zeit
   (Uhren- und Dezimalformat), erwarteter Netto-Erlös. Die Warn-Kacheln
   „Nachzügler" und „Älter als 45 Tage" zählen immer über den gesamten
   Bestand — unabhängig vom gewählten Zeitraum.
2. **Zeitraum**: Ohne eigenen Zeitraum-Filter folgt die Liste der
   globalen Zeitauswahl im Seitenkopf. Ein explizit gesetzter
   Von-/Bis-Filter übersteuert sie.
3. **Filter**: Kunde, Projekt, Mitarbeiter/in, Zeitraum sowie der
   Abrechenbar-Schalter. Mit „Nur nicht abrechenbare" lassen sich
   bewusst oder versehentlich nicht abrechenbar markierte Zeiten
   prüfen.
4. **Summen je Kunde & Projekt** als aufklappbarer Block über der
   Einzelliste.
5. **CSV-Export** mit Dauer in beiden Formaten (H:MM und dezimal).
6. **Als abgerechnet markieren**: schließt für die Programmeinführung
   alle offenen Zeiten bis zu einem Stichtag ab, die bereits außerhalb
   des Systems abgerechnet wurden — wahlweise nur für einen Kunden und
   auf Wunsch inklusive nicht abrechenbarer Einträge. Die Aktion steht
   Administration und Buchhaltung zur Verfügung und lässt sich nicht
   per Klick rückgängig machen.

Sichtbar ist die Seite für Rollen mit der Berechtigung
„Zeiteinträge aller anzeigen" (standardmäßig Buchhaltung,
Geschäftsführung und Administration).
