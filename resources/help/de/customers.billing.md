---
title: "Sonderkonditionen & Abrechnungskonto"
topic: customers.billing
version: 2
audience: []
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
