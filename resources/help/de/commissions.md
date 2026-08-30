---
title: "Provisionen"
topic: commissions
version: 1
audience:
    - admin
    - buchhaltung
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
