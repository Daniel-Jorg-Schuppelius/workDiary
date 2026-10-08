---
title: "Beschaffung & Bestellungen"
topic: procurement.orders
version: 3
keywords:
    - Einkauf
    - Bestellung anlegen
    - Lieferantenbestellung
    - Wareneingang buchen
    - Teillieferung
    - Lieferavis
    - ASN
    - Bestellvorschläge
    - Meldebestand
    - nachbestellen
    - Mindestbestellmenge
    - Bestellung stornieren
audience: []
modules:
    - module.lager
related:
    - inventory.stock
    - articles.master
    - manufacturing.orders
    - contacts.manage
---

Bestellungen erfassen den Einkauf von Artikeln bei einem Lieferanten
gegen ein Ziellager. Sie finden sie unter **Vertrieb & Abrechnung** →
**Beschaffung & Kataloge** → **Bestellungen**. Mit **Bestellung anlegen**
entsteht zunächst ein Entwurf mit **Lieferant**, **Lager**, optional
**Liefertermin** und **Notiz**. Über **Position hinzufügen** füllen Sie
die Bestellzeilen (**Artikel**, **Menge**, optional **Einzelpreis**) und
lösen anschließend mit **Bestellen** die Bestellung aus. Bestellbar sind
Artikel mit dem Merkmal **Einkaufbar**. Der Status durchläuft „Entwurf“,
„Bestellt“, „Teilweise geliefert“, „Geliefert“ oder „Storniert“.

Der **Wareneingang** wird gegen die einzelne Bestellzeile gebucht und
erhöht den Lagerbestand bewertet; Teil- und Überlieferungen werden
unterstützt, die Spalten **Bestellt** und **Geliefert** zeigen den Stand.
Alternativ können Sie zu einer Bestellung mit **Lieferavis erfassen** die
angekündigten Mengen festhalten und den Wareneingang später daraus mit
**Wareneingang buchen** übernehmen. Der Reiter **Erwartete Eingänge**
öffnet die Ansicht „Erwartete Wareneingänge“; sie listet offene
Bestellzeilen bestellter Bestellungen, sortiert nach Liefertermin.

Der Reiter **Bestellvorschläge** ermittelt nach **Lager wählen** den
**Bedarf** aus Meldebestand und offenen Anforderungen und schlägt Mengen
(**Vorschlag**) unter Berücksichtigung von Mindestbestellmenge und
bevorzugtem Lieferanten vor. **Bestellungen erzeugen** legt daraus je
Lieferant Entwürfe an, die Sie vor dem Bestellen prüfen sollten.
Anlegen, Bestellen und Buchen erfordern das Recht **Lagerbewegungen
buchen**; das **Stornieren** einer Bestellung ist nicht umkehrbar.
