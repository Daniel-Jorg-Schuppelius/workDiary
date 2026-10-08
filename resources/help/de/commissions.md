---
title: "Provisionen"
topic: commissions
version: 1
keywords:
    - Provisionsabrechnung
    - Vertriebsprovision
    - Verkaufsprovision
    - Provisionslauf
    - Provisionsregel
    - Provisionsstaffel
    - Vermittlerprovision
    - Tippgeberprovision
    - Provisionsrückforderung
    - Stornohaftung
    - Provision auszahlen
    - Jahresdeckel
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.vertrieb
related:
    - invoices.manage
    - finance.reconciliation
---

Provisionen entstehen aus **bezahlten** Rechnungen. Die Seiten zeigen drei
Dinge: die **Regeln** (wer bekommt wofür wie viel), die **offenen Zeilen**
und die **Läufe**, mit denen abgerechnet wird.

## Der eine Zeitpunkt, an dem eine Provision entsteht

Genau dann, wenn eine Rechnung auf **bezahlt** wechselt — egal auf welchem
Weg das passiert (Bankabgleich, Kassenbuch, Retainer-Abgleich, manuelle
Aktion). **Ausgestellt-aber-offen erzeugt nie eine Provision.**

Das ist kein Detail: Wer auf den Rechnungsausgang provisioniert, zahlt für
Umsätze, die vielleicht nie eingehen — und muss sie später zurückholen.

## Storno und Gutschrift: Rückrechnung statt Korrektur

Eine stornierte oder gutgeschriebene Rechnung **ändert die ursprüngliche
Provisionszeile nicht**. Stattdessen entsteht eine zweite Zeile mit
negativen Beträgen. Zwei Fälle:

- Die Ursprungszeile ist **noch nicht abgerechnet**: beide Zeilen gehen auf
  „zurückgerechnet" und landen in keinem Lauf — es wurde ja nie etwas
  gemeldet. Der Vorgang bleibt als Papierspur stehen.
- Die Ursprungszeile steckt in einem **geschlossenen Lauf**: sie bleibt
  unverändert, denn der Lauf ist der Beleg gegenüber der Lohnabrechnung.
  Die negative Zeile fällt in den nächsten Lauf.

Der Grund für diese Umständlichkeit: ein geschlossener Lauf wurde bereits
gemeldet und womöglich ausgezahlt. Ihn nachträglich zu verändern hieße,
einen Beleg zu fälschen, den jemand anders schon verarbeitet hat.

## Läufe

Ein Lauf bündelt die offenen Zeilen eines Zeitraums. Nach dem Schließen ist
er der Beleg — Korrekturen laufen über den nächsten Lauf, nie durch
Nachbearbeiten des alten.

## Staffeln, Deckel, Haftungsfrist, Teilzahlungen und Vermittler

Eine Regel kann eine **Staffel** tragen: Erreicht der Umsatz einer Person im
Monat, Quartal oder Jahr eine Schwelle, gilt für den neuen Betrag der Satz der
höchsten erreichten Stufe. Bereits entstandene Zeilen werden nicht umgerechnet.
Ein **Jahresdeckel** begrenzt die Provision je Kalenderjahr; was darüber liegt,
verfällt mit einem Hinweis an der Zeile.

Mit einer **Haftungsfrist** wird eine Provision erst nach Ablauf der Tage
auszahlbar und fällt in den Lauf dieser Periode — wird die Rechnung vorher
storniert, verschwindet sie, bevor sie gemeldet war. Ist **„Schon auf
Teilzahlungen“** gesetzt, entsteht die Provision anteilig mit jedem
Zahlungseingang statt erst bei vollständiger Zahlung.

Wird eine Zahlung im Bankabgleich zurückgenommen, rechnet WorkDiary die
Provision auf den dann bezahlten Anteil zurück (ohne „Schon auf Teilzahlungen“
ganz) — als eigene Minuszeile, die bisherige Zeile bleibt. Geht die Zahlung
erneut ein, entsteht die Provision wieder.

Provisionen können auch an **externe Vermittler** ohne Benutzerkonto gehen.
Sie werden unter „Vermittler“ gepflegt, an der Rechnung zugeordnet und
erscheinen im Lauf und im Export neben den Beschäftigten.
