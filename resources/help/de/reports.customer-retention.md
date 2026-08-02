---
title: "Kundenbindung"
topic: reports.customer-retention
version: 2
audience: []
related:
    - reports.customer-value
    - reports.customer-analysis
---

Der Bericht zeigt, **wie gut das Unternehmen Kunden hält** — und woraus
sich der Kundenbestand speist.

## Kohorten-Matrix lesen

Kunden werden nach ihrem **Erstleistungsjahr** gruppiert (org-weit,
unabhängig vom Zeitraumfilter). Jede Zeile ist eine Kohorte, jede Spalte
„+n" das n-te Jahr danach. Beispiel: Zeile **2028 (n=12)**, Spalte **+2**
= 75 % → von den 12 Kunden mit Erstleistung 2028 haben 9 auch im Jahr
2030 Leistungen bezogen. Fällt eine Zeile schnell ab, verliert das
Unternehmen Kunden früh nach dem Einstieg. **Klick auf Zeile oder Zelle**
öffnet die Namensliste der Kohorte.

## Bestandsbrücke — Definitionen

„**Aktiv**" zu einem Stichtag heißt: Leistung innerhalb der eingestellten
Schwelle davor (Standard 365 Tage, Filter „Verloren nach"). Die Brücke
geht exakt auf:

Bestand Start **+ Neukunden** (Erstleistung im Zeitraum)
**+ Zurückgewonnen** (davor inaktiv, jetzt wieder aktiv)
**− Neu, wieder inaktiv** (Erstkunden ohne Folgeleistung)
**− Verloren** (am Start aktiv, am Ende nicht mehr)
= Bestand Ende.

Klick auf einen Brücken-Schritt springt zur Namensliste darunter; jeder
Name führt in den Kunden-&-Projekte-Bericht.

## Kennzahlen

- **Wiederkehrquote**: Anteil der im Vorjahr aktiven Kunden, die auch im
  Berichtsjahr aktiv sind — die ehrlichste Bindungskennzahl.
- **Ø Kundenalter**: Jahre seit Erstleistung, über die am Ende aktiven
  Kunden gemittelt.

## Was tun damit?

- Kohorte bricht im Jahr +1 ein → Onboarding/zweite Beauftragung prüfen.
- Verlorene Kunden häufen sich → Verlustgründe erheben (Preis, Qualität,
  Ansprechpartner), gezielte Rückgewinnung starten.
- Wiederkehrquote unter ~70 % bei Bestandsgeschäft → Bindungsmaßnahmen
  (Wartungsverträge, Check-in-Termine) aufsetzen.
