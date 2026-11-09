---
title: "Sonderkonditionen & Abrechnungskonto"
topic: customers.billing
version: 5
keywords:
    - Sonderpreise
    - Kundenstundensatz
    - Stundensatz Kunde
    - Wochenendzuschlag
    - Feiertagszuschlag
    - Anfahrtspauschale
    - Fahrtkostenpauschale
    - Kundenkonto
    - Saldo
    - Monatspauschale
    - Lexoffice
    - sevDesk
    - easybill
    - Monatsabschluss
    - Abschlag
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
**Pauschale** über das Buchhaltungsprogramm (Lexoffice, sevDesk oder easybill).

Zu den Konditionen gehört auch eine **Anfahrtspauschale**: Jeder
abrechenbare Zeiteintrag bringt dann zusätzlich x Minuten mit, bewertet
mit dem Satz des Eintrags — wahlweise nur für ausgewählte Tätigkeiten.
Die erfasste Arbeitszeit bleibt unangetastet, Arbeitszeitkonto und
Gleitzeit ändern sich also nicht; Nachweis und PDF weisen die Anfahrt in
einer eigenen Spalte aus, solange **Anfahrt gesondert ausweisen** gesetzt ist; ohne den Haken steckt die Anfahrt im Portal und im Nachweis in der Dauer, der Betrag bleibt gleich. Im Zeiteintrag lässt sich der Wert für den
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

Im **Pauschal-Modus** führt das Buchhaltungsprogramm aus der
Rechnungshoheit des Kunden Beleg und Zahlung: Lexoffice, sevDesk oder
easybill. Die Monatspauschale wird netto hinterlegt („Erwarteter
Monatsabschlag"); der lokale Saldo stellt Stunden × Satz gegen die gezahlte
Pauschale. Für den Beleg gibt es zwei Wege:

- **Pauschale senden** erzeugt die Rechnung im Programm (monatlich auch
  automatisch für den Vormonat). sevDesk vergibt die Rechnungsnummer und
  führt die Rechnung danach als versendet und offen; easybill stellt sie
  fertig und nummeriert sie.
- **Beleg verknüpfen** hängt eine Rechnung an den Monat, die Sie bereits
  im Programm erstellt haben. Passt genau eine Rechnung des Kunden nach
  Monat und Nettobetrag, geschieht das beim Belege-Sync automatisch.

Sobald ein Beleg am Monat hängt, entfällt „Pauschale senden" — sonst
entstünde im Programm ein zweiter Beleg. Der Zahlstatus fließt beim
Belege-Sync zurück und wird **netto** verbucht (die Programme führen den
offenen Betrag brutto); Teilzahlungen zählen anteilig. Bei sevDesk und
easybill kommt der Zahlstand aus dem Belegspiegel, also aus dem
regelmäßigen Belegabruf; ein stornierter oder im Programm gelöschter Beleg
nimmt die gebuchte Zahlung zurück. Bricht das Senden an sevDesk oder easybill ab,
bleibt die Rechnung als Entwurf stehen; ein erneutes „Pauschale senden" setzt sie fort,
statt im Programm einen zweiten Beleg anzulegen. Zahlungen aus dem Programm
lassen sich am Panel nicht von Hand stornieren — das geschieht über den
Beleg im Programm.

Wurden Sonderkonditionen erst nachträglich angelegt, stehen ältere Zeiten
zunächst mit 0,00 € in „Gesamt". **Neu berechnen** bewertet sie mit den
hinterlegten Sätzen nach; manuell übersteuerte Sätze bleiben unberührt.

Der Kunde sieht Anwesenheiten und Saldo im Kundenportal unter
„Abrechnung" und kann dort den Anwesenheitsnachweis als PDF laden.
