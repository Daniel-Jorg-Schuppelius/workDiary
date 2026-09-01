---
title: "Billbee anbinden"
topic: admin.billbee
version: 1
audience:
    - admin
modules:
    - module.lager
related:
    - admin.integrations
    - admin.plugins
---

WorkDiary bindet **Billbee** als Multichannel-Aggregator an: Bestellungen
aus Amazon, eBay, Otto, Kaufland, Shopify u. a. laufen bei Billbee zusammen
und werden hier als **Bestellspiegel mit Kanalherkunft** importiert.

**Inbox-First:** Käufer werden nie blind als Kunden angelegt. Eindeutige
Treffer oder bereits zugeordnete Wiederkäufer werden verlinkt; alles andere
erscheint als Vorschlag in der Integrations-Inbox und wird dort entschieden.

**Zugangsdaten:** API-Key (Freischaltung über den Billbee-Support),
Billbee-Benutzername und das separate API-Passwort — verschlüsselt je
Organisation, gepflegt über die Plugin-Karte (Verwaltung → Plugins).

**Bestandsrückkanal:** Führt die Organisation den Bestand mit
Bestandsführung „extern" über Billbee, werden lokale Bewegungen als
**absolute Bestandsmeldungen** je SKU an Billbee übertragen (kein Drift bei
Wiederholungen). Voraussetzung ist ein gepflegtes SKU-Mapping — Produkte
ohne lokale Entsprechung bleiben als offene Zuordnung sichtbar.

**Drossel:** Billbee erlaubt 2 Anfragen pro Sekunde; der Abgleich hält
dieses Limit automatisch ein.
