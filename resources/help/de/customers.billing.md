---
title: "Sonderkonditionen & Abrechnungskonto"
topic: customers.billing
version: 3
audience: []
modules:
    - module.vertrieb
related:
    - contacts.manage
    - invoices.manage
    - customer-portal.billing
---

An der Kundenakte lassen sich **Sonderkonditionen** hinterlegen: eigene
Stundensätze je Tätigkeit und Tagtyp (Werktag/Wochenende, Definition über
„Arbeitstage pro Woche") sowie der Abrechnungsweg — rechnungsloses
**Kundenkonto** mit laufendem Saldo, **monatliche Rechnung** oder
**Pauschale (Lexoffice)**.

Zu den Konditionen gehört auch eine **Anfahrtspauschale**: Jeder
abrechenbare Zeiteintrag bringt dann zusätzlich x Minuten mit, bewertet
mit dem Satz des Eintrags — wahlweise nur für ausgewählte Tätigkeiten.
Die erfasste Arbeitszeit bleibt unangetastet, Arbeitszeitkonto und
Gleitzeit ändern sich also nicht; Nachweis und PDF weisen die Anfahrt in
einer eigenen Spalte aus. Im Zeiteintrag lässt sich der Wert für den
Einzelfall übersteuern (auch auf 0). Fahrt- und Bereitschaftszeiten sowie
Festpreis-Einträge bekommen keine Anfahrt.

Ob ein Tag als Wochenende zählt, bestimmt „Arbeitstage pro Woche" (6 =
nur Sonntag). Optional zählen auch **Feiertage** wie Wochenende; Quelle
ist der Feiertagskalender der Organisation. Maßgeblich ist der
Kalendertag des Beginns — ein Eintrag über Mitternacht wird komplett dem
Starttag zugerechnet.

Im Konto-Modus entsteht je Monat ein Abrechnungsblock: Gesamt (Stunden ×
Satz), Abgerechnet (Zahlungen), Vormonat (Übertrag) und Offen (Saldo).
Der Saldo wandert automatisch in den Folgemonat. Monate werden
chronologisch **abgeschlossen** (Sperre + Snapshot, Zeiten gelten als
abgerechnet) und bei Bedarf rückwärts wiedereröffnet.

Zahlungen buchen Sie manuell am Panel oder über die Zahlungszuordnung der
Bankumsätze (Kundenkonto als Zuordnungsziel). Nachträge in abgeschlossenen
Monaten werden als Warnung angezeigt — Monat wiedereröffnen oder den
Eintrag umdatieren.

Im **Pauschal-Modus** führt Lexoffice Beleg und Zahlung. Die
Monatspauschale wird netto hinterlegt („Erwarteter Monatsabschlag"); der
lokale Saldo stellt Stunden × Satz gegen die gezahlte Pauschale. Für den
Beleg gibt es zwei Wege:

- **Pauschale senden** erzeugt die Rechnung in Lexoffice (monatlich auch
  automatisch für den Vormonat).
- **Beleg verknüpfen** hängt eine Rechnung an den Monat, die Sie bereits
  in Lexoffice erstellt haben. Passt genau eine Rechnung des Kunden nach
  Monat und Nettobetrag, geschieht das beim Belege-Sync automatisch.

Sobald ein Beleg am Monat hängt, entfällt „Pauschale senden" — sonst
entstünde in Lexoffice ein zweiter Beleg. Der Zahlstatus fließt beim
Belege-Sync zurück und wird **netto** verbucht (Lexoffice führt brutto).

Wurden Sonderkonditionen erst nachträglich angelegt, stehen ältere Zeiten
zunächst mit 0,00 € in „Gesamt". **Neu berechnen** bewertet sie mit den
hinterlegten Sätzen nach; manuell übersteuerte Sätze bleiben unberührt.

Der Kunde sieht Anwesenheiten und Saldo im Kundenportal unter
„Abrechnung" und kann dort den Anwesenheitsnachweis als PDF laden.
