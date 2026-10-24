---
title: "PrestaShop-Bestellimport"
topic: admin.prestashop
version: 1
keywords:
    - PrestaShop
    - Shop
    - Bestellungen importieren
    - Onlineshop
    - Webservice
    - Käufer zuordnen
    - Klärfall
audience:
    - admin
related:
    - admin.plugins
    - admin.integration-inbox
    - admin.billbee
    - privacy.overview
    - admin.scheduler
---

Die Seite **PrestaShop** liest die Bestellungen Ihres PrestaShop-Shops und
zeigt sie in WorkDiary: Nummer, Datum, Käufer, Status und Betrag. Käufer werden
vorhandenen Kunden zugeordnet, sodass deren Bestellungen auch am Kunden
erscheinen. In den Shop schreibt WorkDiary nichts. Sie finden die Seite im
Systemmenü (Zahnrad **System**) unter **Plugins** → **PrestaShop**, sobald das
Plugin aktiv ist.

## Zugang einrichten

1. Aktivieren Sie in PrestaShop unter **Erweiterte Parameter** → **Webservice**
   den Webservice und legen Sie einen Schlüssel an. Er braucht Leserecht (GET)
   auf orders, customers, currencies und order_states.
2. Öffnen Sie auf der Seite im Menü ⋯ **Zugangsdaten** und tragen Sie die
   **Shop-Adresse** (ohne /api) und den **Webservice-Schlüssel** ein. PrestaShop
   liefert Zeiten ohne Zeitzone: Läuft der Shop nicht in der Zeitzone Ihrer
   Organisation, tragen Sie die **Zeitzone des Shops** ein (z. B. Europe/Vienna).
   Läuft der Shop im eigenen Netz, setzen Sie zusätzlich **Private Adressen
   erlauben** – das wirkt nur, wenn der Betreiber es zulässt.
3. Klicken Sie auf **Verbindung prüfen**. WorkDiary liest eine Bestellung zur
   Probe und zeigt die Adresse.

Solange Bestellungen gespiegelt sind, lässt sich kein anderer Shop eintragen.

## Vorschau und Import bestätigen

**Vorschau prüfen** zeigt die Bestellungen im Zeitfenster des ersten Abgleichs
(Vorgabe 30 Tage, einstellbar unter **Zugangsdaten**) und ob ihr Käufer schon
einem Kunden zugeordnet ist. Die Vorschau speichert nichts. **Import bestätigen**
gibt den Abgleich frei.

Neue stornierte oder erstattete Bestellungen übernimmt der
Import nur, wenn **Stornierte und erstattete Bestellungen übernehmen** gesetzt
ist. Bereits übernommene Bestellungen führt er immer nach.

## Was übernommen wird

- Referenz, Datum, **Status** und Betrag mit Währung. Der Status folgt den
  festen Merkmalen des PrestaShop-Bestellstatus, nicht seinem Namen: storniert
  und Zahlungsfehler → storniert, Erstattung → erstattet, versendet (auch zugestellt) → abgeschlossen, bezahlt → in Bearbeitung, sonst offen.
- Der Lieferschein allein (Standardstatus „In Vorbereitung“) gilt nicht als
  versendet. Bei mehreren Shops liest WorkDiary die Bestellungen aller Shops und
  die Währungen anderer Shops einzeln nach.
- Käufer mit Name, E-Mail und Firma, die Positionen als Text.
- Einen Link in die Shop-Verwaltung gibt es nicht: Ihr Ordner ist je Shop
  geheim, und ihre Links gelten nur mit einer Anmeldung.

## Kunden zuordnen und Klärfälle

Ist der Käufer schon einem Kunden zugeordnet oder eindeutig erkennbar, verknüpft
WorkDiary die Bestellung mit ihm. Sonst entsteht ein **Klärfall** in der
Integrations-Inbox: Dort ordnen Sie den Käufer einem Kunden zu, und der nächste
Abgleich verknüpft auch seine weiteren Bestellungen. Neue Kunden legt der Import
nie an. Gastbestellungen werden über die E-Mail-Adresse erkannt.

## Wann abgeglichen wird

Alle 15 Minuten holt WorkDiary die seit dem letzten Lauf neuen oder geänderten
Bestellungen, einmal täglich das ganze Zeitfenster. Den Takt ändern Sie unter
**Geplante Jobs**; **Jetzt abgleichen** startet sofort. Ein abgebrochener Lauf
gilt als **unvollständig**, der nächste setzt am letzten vollständigen Stand an.

## Trennen und Aufbewahrung

**Trennen** beendet den Abgleich; Bestellungen und Kundenzuordnungen bleiben.
Name, E-Mail und Firma der Käufer entfernt die Aufbewahrungsregel
**Käuferdaten von Marktplatz-Bestellungen** nach der handelsrechtlichen Frist;
Beträge und Kundenbezug bleiben.

## Typische Fehler

- Die Prüfung scheitert mit 401: Der Schlüssel ist falsch oder der Webservice
  ist ausgeschaltet.
- Die Prüfung scheitert mit 403 oder ein Lauf bleibt **unvollständig**: Dem
  Schlüssel fehlt das Leserecht auf eine der vier Ressourcen.
- Uhrzeiten liegen um Stunden daneben: Die **Zeitzone des Shops** fehlt oder ist
  falsch.
- Die Prüfung scheitert mit 401, obwohl der Schlüssel stimmt: Der Webserver
  reicht den Authorization-Header nicht weiter (Apache: CGIPassAuth), oder die
  URL-Umschreibung für /api ist aus.
