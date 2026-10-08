---
title: "Zeitkonten"
topic: time-accounts.overview
version: 2
keywords:
    - Zusatzkonto
    - Freizeitkonto
    - Freizeitausgleich
    - Nachtdienste zählen
    - Zulagenstunden
    - Kontostand
    - Ampel
    - Buchungsjournal
    - Stornobuchung
    - Zeitkonto exportieren
    - Mehrarbeit
    - Periodenvergleich
audience: []
related:
    - time-accounts.flex
---

Zusatz-Zeitkonten führen ausgewählte Zeitgrößen als eigene Konten — zum
Beispiel einen Zähler geleisteter Nachtdienste, ein Freizeitkonto für
Mehrarbeit oder gesammelte Zulagenstunden. Gleitzeit und Urlaub finden Sie
weiterhin im Arbeitszeitkonto.

Die Übersicht zeigt je Konto den aktuellen Stand mit Ampel (Schwellen legt
die Organisation fest), den durchschnittlichen Monatsumsatz und einen
einfachen Trend. Über „Journal ansehen" sehen Sie jede einzelne Buchung
mit Datum, Menge, Quelle und Anmerkung — Korrekturen erscheinen als
Storno-Gegenbuchung, nichts wird überschrieben.

Die Auswertung (für Leitungsrollen) stellt Anfangsstand, Umsatz und
Endstand je Mitarbeiter für einen Zeitraum gegenüber und lässt sich als
CSV oder PDF exportieren.

## Periodenvergleich

Der Periodenvergleich stellt die Buchungen eines Zeitkontos je
Kalenderwoche oder je Monat nebeneinander. Sie finden ihn unter
**Auswertungen** → **Team** → **Periodenvergleich**.

- In der Filterleiste wählen Sie das **Konto** (alle aktiven Zeitkonten) und
  das **Raster**: **Kalenderwoche** (Vorgabe) oder **Monat**. Die Auswahl
  wirkt sofort.
- Der Zeitraum folgt dem Datumsfilter in der Kopfzeile. Es werden höchstens
  53 Spalten angezeigt, also ein Jahr in Wochen.
- Je Mitarbeiter zeigt die Tabelle den **Anfangsstand** (Summe aller
  Buchungen vor dem Zeitraum), die Summe je Woche oder Monat, den **Umsatz**
  im Zeitraum und den **Endstand**. Der Endstand trägt die Ampelfarbe des
  Kontos. Alle Werte erscheinen in der Einheit des Kontos.
- Personen, deren Anfangsstand und Umsatz beide null sind, erscheinen nicht.
  Gibt es im Zeitraum gar keine Werte, meldet die Seite „Keine Buchungen im
  gewählten Zeitraum.“

**Export:** **PDF** sowie unter **Export** die Formate **CSV** und **Excel**.
PDF und CSV enthalten das gewählte Konto. Excel liefert alle aktiven Konten
als je ein Arbeitsblatt derselben Arbeitsmappe.

**Sichtbarkeit:** Die Rolle **Administrator** sieht alle Mitarbeiter der
Organisation, alle anderen sehen nur ihre eigene Zeile. Sind keine aktiven
Zeitkonten eingerichtet, zeigt die Seite den Hinweis „Keine Zeitkonten
eingerichtet“.
