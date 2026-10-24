---
title: "Shopware-Bestellimport"
topic: admin.shopware
version: 1
keywords:
    - Shopware
    - Shopware 6
    - Shop
    - Bestellungen importieren
    - Onlineshop
    - Integration
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

Die Seite **Shopware** liest die Bestellungen Ihres Shopware-6-Shops und
zeigt sie in WorkDiary: Nummer, Datum, Käufer, Status und Betrag. Käufer werden
vorhandenen Kunden zugeordnet, sodass deren Bestellungen auch am Kunden
erscheinen. In den Shop schreibt WorkDiary nichts. Sie finden die Seite im
Systemmenü (Zahnrad **System**) unter **Plugins** → **Shopware**, sobald das
Plugin aktiv ist.

## Zugang einrichten

1. Legen Sie in Shopware unter **Einstellungen** → **System** → **Integrationen**
   eine Integration an. Sie braucht Leserecht auf
   Bestellungen, Bestellkunden, Kunden, Positionen, Zahlungen samt Captures und Erstattungen, Währungen und Status – oder die Option **Administrator**.
2. Öffnen Sie auf der Seite im Menü ⋯ **Zugangsdaten** und tragen Sie die
   **Shop-Adresse** (ohne /api), die **Zugangs-ID** und den
   **Sicherheitsschlüssel** der Integration ein. Läuft der Shop im eigenen Netz,
   setzen Sie zusätzlich **Private Adressen erlauben** – das wirkt nur, wenn der
   Betreiber es zulässt.
3. Klicken Sie auf **Verbindung prüfen**. WorkDiary meldet sich an, liest eine
   Bestellung zur Probe und zeigt Adresse und Shopware-Version.

Solange Bestellungen gespiegelt sind, lässt sich kein anderer Shop eintragen.

## Vorschau und Import bestätigen

**Vorschau prüfen** zeigt die Bestellungen im Zeitfenster des ersten Abgleichs
(Vorgabe 30 Tage, einstellbar unter **Zugangsdaten**) und ob ihr Käufer schon
einem Kunden zugeordnet ist. Die Vorschau speichert nichts. **Import bestätigen**
gibt den Abgleich frei.

Neue stornierte Bestellungen übernimmt der
Import nur, wenn **Stornierte und erstattete Bestellungen übernehmen** gesetzt
ist. Bereits übernommene Bestellungen führt er immer nach.

## Was übernommen wird

- Bestellnummer, Datum, **Status** aus dem technischen Bestellstatus von
  Shopware (offen, in Bearbeitung, abgeschlossen, storniert) und Betrag mit
  Währung. Erstattungen führt Shopware am Zahlungsstatus: Steht die
  jüngste Zahlung auf erstattet, gilt die Bestellung als **erstattet**; eine
  Teilerstattung ändert den Status nicht.
- Den erstatteten Betrag nennt WorkDiary unter dem Betrag, soweit Shopware
  abgeschlossene Erstattungen führt. Ein von Hand gesetztes „erstattet“ ohne
  Erstattung gilt als volle Erstattung, ein „teilweise erstattet“ ohne
  Erstattung bleibt ohne Betrag.
- Einmal täglich prüft der Abgleich, ob die übernommenen Bestellungen im Shop
  noch vorhanden sind. Gelöschte stehen dann auf **Im Shop gelöscht** und
  bleiben in WorkDiary erhalten; taucht eine wieder auf, entfällt die Markierung.
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

- Die Prüfung scheitert mit 401: Zugangs-ID oder Sicherheitsschlüssel sind falsch.
- Die Prüfung scheitert mit 403: Der Integration fehlt das Leserecht auf
  Bestellungen.
- Die Prüfung meldet eine interne Adresse: Die Shop-Adresse zeigt ins eigene Netz,
  aber **Private Adressen erlauben** ist aus oder vom Betreiber nicht zugelassen.
- Die Prüfung oder ein Lauf scheitert mit 403, obwohl Bestellungen lesbar sind:
  Der Integration fehlt eines der oben genannten Leserechte, etwa auf Zahlungen.
