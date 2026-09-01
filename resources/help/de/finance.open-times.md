---
title: "Offene Zeiten"
topic: finance.open-times
version: 2
audience: []
modules:
    - module.finance
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

**Nicht** in der Liste stehen Kunden mit laufendem Leistungssaldo
(Sonderkonditionen im Modus „Kundenkonto" oder „Pauschale"): deren
Zeiten werden nicht fakturiert, sondern über den Monatsblock der
Kundenakte abgerechnet — sie wären hier Dauergäste. Ein Hinweis über
der Liste nennt die Zahl der so ausgeblendeten Einträge. Kunden im
Modus „monatliche Rechnung" bleiben sichtbar, sie laufen über die
normale Fakturierung.

Funktionen:

1. **Kennzahlen** oben: Anzahl offener Einträge, offene Zeit
   (Uhren- und Dezimalformat), erwarteter Netto-Erlös. Die Warn-Kacheln
   „Nachzügler" und „Älter als 45 Tage" zählen immer über den gesamten
   Bestand — unabhängig vom gewählten Zeitraum.
2. **Zeitraum**: Die Liste folgt der globalen Zeitauswahl im
   Seitenkopf. Von-/Bis-Parameter in der Adresszeile (Lesezeichen)
   übersteuern sie.
3. **Filter**: Kunde, Projekt, Mitarbeiter/in sowie der
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
