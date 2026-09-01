---
title: "Anlagenregister und Abschreibung"
topic: accounting.fixed-assets
version: 1
audience:
    - admin
    - buchhaltung
modules:
    - module.finance
related:
    - accounting.closing
    - accounting.posting
    - accounting.overview
---

Das **Anlagenregister** ist die buchhalterische Sicht auf langlebige
Wirtschaftsgüter: Anschaffungs-/Herstellungskosten, Nutzungsdauer,
Restwert und die beteiligten Konten. Es beantwortet die Frage „was ist
diese Maschine zum Stichtag noch wert" — nicht „wo steht sie und wann
war die letzte Wartung". Das steht in der Geräteakte.

**Gerät und Anlage sind zwei Dinge.** Die Verknüpfung zu einem Gerät ist
möglich, aber nicht nötig: eine Betriebsvorrichtung kann ohne Geräteakte
bilanziert werden, und ein Gerät kann geringwertig und damit sofort
abgeschrieben sein. Wer beides gleichsetzt, bekommt entweder Anlagen ohne
Buchwert oder Geräte, die es buchhalterisch nicht gibt.

## Was hier eingetragen wird

1. **Anschaffung**: Datum, Kosten, Währung. Die laufende Nummer vergibt das
   System.
2. **Nutzungsdauer in Monaten** und das **Abschreibungsverfahren**. Beides
   bestimmt, wie sich der Wert über die Jahre verteilt.
3. **Restwert**, wenn am Ende der Nutzungsdauer ein Erinnerungswert oder ein
   erwarteter Verkaufserlös stehen bleibt.
4. **Konten** für Anlagevermögen und Abschreibung — sie steuern, wohin die
   AfA-Buchung läuft.

## Wie die Abschreibung entsteht

Die AfA-Zeilen werden **berechnet, nicht getippt**. Der Abschluss schlägt
sie je Anlage und Geschäftsjahr vor; gebucht wird ausschließlich über die
Buchungs-Inbox.

**Das Register bucht nichts von selbst.** Das ist Absicht: eine Abschreibung
ist eine Entscheidung im Jahresabschluss, kein Nebeneffekt einer
Stammdatenpflege. Wer eine Anlage anlegt, verändert damit noch keinen
Saldo.

## Abgang

Ein Abgang (Verkauf, Verschrottung, Diebstahl) wird mit Datum vermerkt. Die
Anlage verschwindet **nicht** aus dem Register — der Verlauf bleibt lesbar,
sonst wäre ein späterer Abgleich mit der Bilanz nicht möglich.
