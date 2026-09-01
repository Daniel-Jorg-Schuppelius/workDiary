---
title: "Beschaffung & Bestellungen"
topic: procurement.orders
version: 1
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
gegen ein Ziellager. Eine Bestellung wird zunächst als Entwurf angelegt,
mit Bestellzeilen (Artikel, Menge, optional Einkaufspreis) gefüllt und
anschließend bestellt. Bestellbar sind als einkaufsfähig markierte
Artikel. Der Status durchläuft Entwurf, bestellt, teilweise geliefert,
geliefert oder storniert.

Der Wareneingang wird gegen die einzelne Bestellzeile gebucht und
erhöht den Lagerbestand bewertet; Teil- und Überlieferungen werden
unterstützt. Alternativ kann zu einer Bestellung ein Lieferavis (ASN)
mit avisierten Mengen erfasst und der Wareneingang später daraus gebucht
werden. Die Ansicht „Erwartete Wareneingänge" listet offene
Bestellzeiten bestellter Aufträge, sortiert nach Liefertermin.

Automatische Bestellvorschläge ermitteln je Lager den Bedarf aus
Meldebestand und offenen Anforderungen und schlagen Mengen unter
Berücksichtigung von Mindestbestellmenge und bevorzugtem Lieferanten
vor. Übernommene Vorschläge erzeugen Entwürfe, die vor dem Bestellen
geprüft werden sollten. Anlegen, Bestellen und Buchen erfordern die
Bestandsbuchungs-Berechtigung; das Stornieren einer Bestellung ist nicht
umkehrbar.
