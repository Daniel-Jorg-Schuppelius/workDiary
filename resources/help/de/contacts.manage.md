---
title: "Kunden & Lieferanten"
topic: contacts.manage
version: 2
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - projects.manage
    - invoices.manage
    - admin.import
    - communication.notes
---

## Zweck und Hintergrund

Kunden und Lieferanten sind die zentralen Stammdaten von WorkDiary:
Projekte, Aufträge, Rechnungen, Kommunikation, Reisen und Auswertungen
hängen an ihnen. Saubere Stammdaten entscheiden darüber, ob spätere
Vorgänge — von der Zeitbuchung bis zur DATEV-Übergabe — ohne
Nacharbeit funktionieren.

## Voraussetzungen

- Das Recht, Kunden bzw. Lieferanten zu verwalten (in der Regel
  Verwaltung oder Vertrieb).
- Für Import statt Einzelanlage: der CSV-Import-Wizard der Verwaltung.
- Externe Kennungen (z. B. Debitorennummer, Kennungen aus
  Faktura-Integrationen), wenn Belege übergeben werden sollen.

## Empfohlener Ablauf

1. **Vor der Neuanlage suchen:** Prüfe, ob der Geschäftspartner schon
   existiert — so entstehen keine Dubletten. Vorhandene Dubletten
   lassen sich zusammenführen, dabei wandert die Historie mit.
2. Lege den Kontakt mit Name, Anschrift und Ansprechpartnern an.
3. Ergänze Zahlungs- und Abrechnungsdaten sowie externe Kennungen
   vollständig — sie steuern Faktura und Buchhaltungsübergabe.
4. Verknüpfe Projekte, Standorte und Vereinbarungen, sobald sie
   entstehen.

![Kundenliste mit Nummern, Kontaktdaten, Stundensätzen und Projektzahl](media/kunden/kundenliste.png)
*Die Kundenliste: Stammdaten, Stundensatz und verknüpfte Projekte je Geschäftspartner.*

## Beispiel aus der Praxis

Ein IT-Dienstleister legt die „Müller GmbH" an, hinterlegt
Rechnungsanschrift, Zahlungsziel und die Debitorennummer aus der
Kanzlei. Als später der erste DATEV-Stapel erzeugt wird, ist kein
einziger Beleg wegen fehlender Stammdaten blockiert.

## Typische Fehler

- **Dubletten anlegen**, weil vor der Neuanlage nicht gesucht wurde —
  Auswertungen und Historie zersplittern.
- **Historische Beziehungen löschen:** Nicht mehr verwendete Kontakte
  besser deaktivieren oder archivieren; Belege und Zeiten bleiben so
  nachvollziehbar.
- **Abrechnungsdaten „nebenbei" ändern:** Änderungen wirken auf
  zukünftige Vorgänge; bereits erzeugte Belege behalten bewusst ihren
  dokumentierten Stand.

## Auswirkungen und nächste Schritte

Stammdatenänderungen wirken nur nach vorn — abgeschlossene Übergaben
bleiben unverändert. Als Nächstes: Projekte am Kunden anlegen, die
Abrechnungsdaten für Rechnungen prüfen und bei größeren Beständen den
CSV-Import statt der Einzelanlage nutzen.
