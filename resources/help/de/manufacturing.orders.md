---
title: "Fertigungsaufträge"
topic: manufacturing.orders
version: 1
audience: []
related:
    - manufacturing.work-centers
    - procurement.orders
    - articles.master
    - inventory.stock
---

Fertigungsaufträge bilden die Herstellung eines Erzeugnisses auf Basis
seiner Stückliste oder Rezeptur ab. Wählbar sind nur als fertigbar
markierte Artikel; aus Zielmenge, Variante und Stückliste leitet das
System den Materialbedarf ab. Mit der Freigabe wird ein Snapshot der
Stückliste festgehalten, sodass spätere Änderungen den laufenden Auftrag
nicht mehr verändern.

Der Ablauf folgt einer Statusmaschine: Entwurf, freigegeben, in Arbeit,
wartend, gesperrt, abgeschlossen oder storniert. Material wird über
„Reservieren" gegen den Bestand gesperrt, der Start protokolliert die
Ausführung, Teilrückmeldungen erfassen produzierte, gute, Ausschuss- und
Nacharbeitsmengen. Fertigerzeugnisse werden über „Ausliefern" als
Bestand eingebucht; dafür müssen Variante und Lager gesetzt sein.

Über die Detailseite lässt sich der Auftrag einem Arbeitsplatz mit
geplanter Belegungsdauer zuordnen oder als Fremdfertigung an einen
Lieferanten vergeben (erzeugt eine Bestellung). Die Planungssicht zeigt
für ein Erzeugnis die mehrstufige Materialbedarfsauflösung (MRP) sowie
Qualitätskennzahlen je Artikel. Stornieren ist nicht umkehrbar; Anlegen,
Rückmelden und Ausliefern erfordern die Bestandsbuchungs-Berechtigung.
