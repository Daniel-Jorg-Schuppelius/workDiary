---
title: "Rechnungen & Belege"
topic: invoices.manage
version: 6
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - contacts.manage
    - projects.manage
    - finance.datev-bookings
    - finance.transfers
    - travel-expenses.manage
---

## Zweck und Hintergrund

Die Rechnungsübersicht verwaltet lokale Rechnungen und angebundene
Belege. Welcher Weg führend ist, hängt von der Organisation und der
eingesetzten Faktura-Integration ab: Je Zeitraum stellt entweder
WorkDiary die Rechnungen oder genau ein externes System — nie beide
gleichzeitig.

## Voraussetzungen

- Geprüfte Stammdaten: Kunde, Empfängeradresse, Steuerangaben.
- **Leistungszeitraum und Projektbezug** der abzurechnenden Positionen.
- Das Recht, Rechnungen zu erstellen; für Mahnläufe die entsprechende
  Finanzrolle.

## Empfohlener Ablauf

1. Kunde und Zeitraum wählen — der Erstell-Dialog zeigt eine
   **Vorschau** der entstehenden Positionen (Anzahl, Dauer in Uhren-
   und Dezimalformat, Betrag, Nachzügler-Warnung).
2. Einzelne Zeiteinträge bei Bedarf per Häkchen **ausschließen** — sie
   bleiben offen und erscheinen im nächsten Lauf.
3. Entwurf prüfen und ergänzen; je Position sind die
   **Quell-Zeiteinträge** aufklappbar (1,50 h = 1:30 h). Bei einem
   Artikel mit Kupfergewicht fügt das Häkchen **Kupferzuschlag** im
   Positionsdialog den Zuschlag zum aktuellen DEL-Tagespreis als eigene
   Position an.
4. Stellen bzw. versenden — PDF, Versand und externe Synchronisation
   sind Ausgaben desselben dokumentierten Stands.
5. Bei Zahlungsverzug den **Mahnlauf** nutzen: Stufe 1 erzeugt eine
   Zahlungserinnerung als eigenes Mahnschreiben-PDF mit
   Forderungsübersicht, optionaler Mahngebühr und Zahlungsziel; die
   E-Mail enthält Mahnschreiben und Original-Rechnung. Ein neuer Beleg
   entsteht dabei nicht.

**E-Rechnung.** Die XRechnung entsteht in der UBL-Syntax; verlangt ein
Empfänger CII, wählen Sie beim Kunden oder beim Versand das Zustellformat
„XRechnung (XML, CII-Syntax)“. Über Peppol geht immer UBL. Ohne USt-IdNr. —
etwa als Kleinunternehmer nach § 19 UStG — genügt die Steuernummer in den
E-Rechnungs-Stammdaten: Sie wird zusätzlich als Verkäuferkennung eingetragen,
die die Prüfung beim Empfänger verlangt.

## Beispiel aus der Praxis

Zum Monatsende wählt die Buchhaltung „Müller GmbH" und den Vormonat:
Die Vorschau zeigt 14 Positionen und warnt vor zwei Nachzügler-Zeiten.
Ein strittiger Eintrag wird ausgeschlossen und wandert automatisch in
den nächsten Lauf — die Rechnung geht ohne Diskussion raus.

## Typische Fehler

- **Versandte oder übergebene Belege still ändern:** Gestellte,
  gebuchte oder extern übergebene Belege sind unveränderlich — für
  Fehler gibt es den Storno- bzw. Korrekturprozess.
- **Belegnummern oder Beträge nachträglich überschreiben** statt zu
  korrigieren — das zerstört die Nachvollziehbarkeit.
- **Doppelte Rechnungshoheit:** Führt ein externes System die Faktura,
  entstehen lokale Rechnungen bewusst nicht parallel.

## Auswirkungen und nächste Schritte

Gestellte Rechnungen fließen in offene Posten, Mahnwesen und die
Buchhaltungsübergabe. Als Nächstes: Zahllauf und Zahlungszuordnung
prüfen und den DATEV-Buchungsstapel für die Kanzlei erzeugen.

## Freie Rechnung ohne Zeiten

Im Erstell-Dialog steht **„Positionen selbst zusammenstellen“** gleichberechtigt
neben der Übernahme aus Zeiten oder Materialverbrauch. Der Entwurf braucht nur
den Kunden (optional Projekt, Endkunde und Zahlungsziel) und startet leer: Er
lässt sich speichern und später ergänzen, aber weder stellen noch versenden,
solange er keine Position hat — das gilt für Ausstellung, E-Mail, Peppol und
Lexoffice-Übergabe gleichermaßen. Ein Doppelklick auf „Entwurf erstellen“
erzeugt keine zweite Rechnung.

**Artikel, Material und Leistungen.** Eine Position ist ein Artikel (mit
optionaler Variante), Material oder Freitext — etwa „Montage pauschal“ oder
eine Sonderanfertigung ohne Fertigungsauftrag. Beschreibung, Artikelnummer,
Einheit und Preis werden als Belegwerte eingefroren; spätere Änderungen im
Artikelstamm ändern den Beleg nicht. Ein fehlender Preis muss bewusst
eingegeben werden (0,00 ist als Gratisposition erlaubt); ein Artikelpreis in
fremder Währung wird nicht still umgerechnet. Artikel- und Freitextpositionen
buchen **keinen Lagerbestand**; Lieferungen laufen über Lager/Auslieferung.

**Fertigungsauslieferungen abrechnen.** Mit aktivem Lagermodul übernimmt
„Auslieferung übernehmen“ erfolgte Auslieferungen des Kunden mit lokalem
Fakturaziel — jede vollständig als eine Position mit quellengebundener Menge
und dem Verkaufspreis der Auslieferung (nicht den Herstellkosten). Eine
Auslieferung kann nur in einem Entwurf zugleich stehen; die Ausstellung
kennzeichnet sie als abgerechnet, Entfernen der Position, Verwerfen des
Entwurfs oder ein Vollstorno geben sie wieder frei, die Herkunft bleibt am
Beleg sichtbar. Teilgutschriften geben nichts frei; der Lagerbestand bleibt bei
allen Rechnungsvorgängen unverändert.
