---
title: "Kunden & Lieferanten"
topic: contacts.manage
version: 4
keywords:
    - Kundenstamm
    - Kundendaten
    - Stammdaten
    - Lieferantenstamm
    - Kunde anlegen
    - Lieferant anlegen
    - Debitor
    - Kreditor
    - Debitorennummer
    - Dubletten zusammenführen
    - Kunden importieren
    - Adressbuch
    - Geschäftspartner
    - CRM
    - Kundenportal
    - Portalzugang
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

1. **Vor der Neuanlage suchen:** Prüfen Sie, ob der Geschäftspartner schon
   existiert — so entstehen keine Dubletten. Vorhandene Dubletten
   lassen sich zusammenführen, dabei wandert die Historie mit.
2. Legen Sie den Kontakt mit Name, Anschrift und Ansprechpartnern an.
3. Ergänzen Sie Zahlungs- und Abrechnungsdaten sowie externe Kennungen
   vollständig — sie steuern Faktura und Buchhaltungsübergabe.
4. Verknüpfen Sie Projekte, Standorte und Vereinbarungen, sobald sie
   entstehen.

![Kundenliste mit Nummern, Kontaktdaten, Stundensätzen und Projektzahl](media/kunden/kundenliste.png)
*Die Kundenliste: Stammdaten, Stundensatz und verknüpfte Projekte je Geschäftspartner.*

**Kommunikation:** Anrufe, E-Mails und Zusagen halten Sie als
Kommunikationsnotiz am Kunden oder Lieferanten fest. Die Notizen stehen auf
der Detailseite und in der zentralen Notizliste; eine Datenschutzauskunft zum
Lieferanten führt sie mit Anzahl und Zeitraum auf.

**Portalzugänge:** Im Abschnitt **Portalzugänge** der Kundenakte laden Sie
Ansprechpartner mit **Zugang einladen** ins Kundenportal ein; der Kontakt legt
sein Passwort über den Link in der Einladung selbst fest. Solange die
Einladung offen oder abgelaufen ist, steht **Einladung erneut senden** bereit.
Bei aktiven Zugängen setzt **Zugang zurücksetzen** den Zugang nach einer
Rückfrage zurück: Das bisherige Passwort gilt sofort nicht mehr, alle
Sitzungen werden beendet, und der Kontakt erhält eine neue Einladung;
eingerichtete Zwei-Faktor-Methoden bleiben bestehen. Hat der Kontakt nur sein
Passwort vergessen, ist das nicht nötig – er setzt es auf der Anmeldeseite des
Portals über **Passwort vergessen?** selbst zurück. **Deaktivieren** meldet
den Zugang sofort ab und sperrt die Anmeldung, **Reaktivieren** hebt das
wieder auf. Welche Bereiche ein Zugang sieht, legt die Portal-Konfiguration
des Kunden fest.

Hat ein Kontakt alle Zwei-Faktor-Methoden und Recovery-Codes verloren, entfernt **Zweiten Faktor zurücksetzen** nach einer Passwortbestätigung und einer Rückfrage alle Methoden und beendet alle Sitzungen. Der Kontakt erhält dazu eine E-Mail und meldet sich danach mit seinem Passwort an; verlangt Ihre Organisation Zwei-Faktor-Authentifizierung, richtet er sie dabei neu ein. Prüfen Sie vorher seine Identität, etwa durch einen Rückruf.

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
