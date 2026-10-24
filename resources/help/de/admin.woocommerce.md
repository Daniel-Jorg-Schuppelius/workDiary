---
title: "WooCommerce-Bestellimport"
topic: admin.woocommerce
version: 1
keywords:
    - WooCommerce
    - WordPress
    - Shop
    - Bestellungen importieren
    - Onlineshop
    - Consumer Key
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

Die Seite **WooCommerce** liest die Bestellungen Ihres WooCommerce-Shops und
zeigt sie in WorkDiary: Nummer, Datum, Käufer, Status und Betrag. Käufer werden
vorhandenen Kunden zugeordnet, sodass deren Bestellungen auch am Kunden
erscheinen. In den Shop schreibt WorkDiary nichts. Sie finden die Seite im
Systemmenü (Zahnrad **System**) unter **Plugins** → **WooCommerce**, sobald das
Plugin aktiv ist.

## Zugang einrichten

1. Legen Sie in WooCommerce unter **Einstellungen** → **Erweitert** →
   **REST-API** einen Schlüssel mit der Berechtigung **Lesen** an.
2. Öffnen Sie auf der Seite im Menü ⋯ **Zugangsdaten** und tragen Sie die
   **Shop-Adresse** (mit https, ohne /wp-json), den **Consumer Key** und das
   **Consumer Secret** ein. WooCommerce nimmt die Schlüssel nur über https an.
   Läuft der Shop im eigenen Netz, setzen Sie zusätzlich **Private Adressen
   erlauben** – das wirkt nur, wenn der Betreiber es zulässt.
3. Klicken Sie auf **Verbindung prüfen**. WorkDiary liest eine Bestellung zur
   Probe und zeigt den Namen des Shops.

Solange Bestellungen gespiegelt sind, lässt sich kein anderer Shop eintragen.

## Vorschau und Import bestätigen

**Vorschau prüfen** zeigt die Bestellungen im Zeitfenster des ersten Abgleichs
(Vorgabe 30 Tage, einstellbar unter **Zugangsdaten**) und ob ihr Käufer schon
einem Kunden zugeordnet ist. Die Vorschau speichert nichts. **Import bestätigen**
gibt den Abgleich frei.

Neue stornierte, fehlgeschlagene oder erstattete Bestellungen übernimmt der
Import nur, wenn **Stornierte und erstattete Bestellungen übernehmen** gesetzt
ist. Bereits übernommene Bestellungen führt er immer nach.

## Was übernommen wird

- Bestellnummer, Datum, **Status** aus dem festen WooCommerce-Status (offen,
  in Bearbeitung, abgeschlossen, storniert, erstattet) und Betrag mit Währung.
- Teilerstattungen ändern in WooCommerce weder Status noch Bestellsumme;
  WorkDiary zeigt die Bestellsumme.
- Käufer mit Name, E-Mail und Firma, die Positionen als Text.
- Die Bestellnummer führt in die Bestellverwaltung des Shops.

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

- Die Prüfung meldet `https_required`: Die Shop-Adresse beginnt nicht mit https.
- Die Prüfung scheitert mit 401: Schlüssel falsch oder ohne Leserecht.
- Die Prüfung meldet eine interne Adresse: Die Shop-Adresse zeigt ins eigene Netz,
  aber **Private Adressen erlauben** ist aus oder vom Betreiber nicht zugelassen.
- Die Prüfung scheitert mit 401, obwohl die Schlüssel stimmen: WordPress erkennt
  hinter einem Proxy die https-Verbindung nicht, oder der Server reicht den
  Authorization-Header nicht weiter.
- Die Prüfung meldet 404: Unter **Einstellungen** → **Permalinks** ist
  „Einfach“ gewählt; die REST-API braucht sprechende Permalinks.
