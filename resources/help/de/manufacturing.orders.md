---
title: "Fertigungsaufträge"
topic: manufacturing.orders
version: 2
keywords:
    - Produktionsauftrag
    - Stückliste
    - Rezeptur
    - Materialbedarf
    - MRP
    - Material reservieren
    - Rückmeldung
    - Ausschuss
    - Fremdfertigung
    - Lohnfertigung
    - Zollpapiere
    - Proformarechnung
    - Handelsrechnung
    - Lieferschein
    - Versandlabel
    - Sendungsstatus
    - Versand stornieren
audience: []
modules:
    - module.lager
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
Rückmelden und Ausliefern erfordern das Recht **Lagerbewegungen buchen**.

## Versand an der Auslieferung

Mit einer aktiven Versandanbindung (siehe „Versandanbindungen DHL, UPS und
FedEx“) erstellen Sie an einer Auslieferung mit Kunde über **Versand** einen
Versandauftrag samt Label. Die Auslieferung zeigt danach den Status, etwa
**Versand: Label erstellt**, mit Paketdienst und Sendungsnummer. Fahren Sie mit
der Maus über den Status, sehen Sie, wann er zuletzt beim Paketdienst
abgeglichen wurde. Neben dem Status stehen:

- **Label herunterladen**: lädt das Versandlabel erneut herunter.
- **Sendungsstatus abrufen**: fragt den aktuellen Stand sofort beim
  Paketdienst ab – nicht mehr bei **Zugestellt** oder **Storniert**. Offene
  Sendungen gleicht WorkDiary außerdem regelmäßig von selbst ab.
- **Versand stornieren**: nur im Status **Entwurf** oder **Label erstellt** und
  nach einer Rückfrage. Das Label wird ungültig. Danach können Sie einen neuen
  Versandauftrag erstellen, und die Packstücke der Auslieferung lassen sich
  wieder bearbeiten.

Versandauftrag erstellen, Sendungsstatus abrufen und Versand stornieren setzen
das Recht **Lagerbewegungen buchen** voraus.

## Zollpapiere für Sendungen außerhalb der EU

An jeder Auslieferung mit Empfänger erstellt „Zollpapiere“ eine
Handelsrechnung (bei Verkauf) oder eine Proformarechnung (Geschenk, Muster,
Rücksendung, Reparatur und andere Gründe) als PDF. Der Dialog zeigt, ob das
Ziel außerhalb der EU liegt, und speichert den gewählten Versandgrund an der
Auslieferung. Das Dokument führt Warenbeschreibung, Zolltarifnummer,
Ursprungsland, Menge, Nettogewicht und Wert; Bruttogewicht und Anzahl der
Packstücke kommen aus den erfassten Packstücken. Zolltarifnummer,
Ursprungsland und Nettogewicht pflegen Sie am Artikel, die EORI-Nummer des
Absenders in den Einstellungen der Organisation. Fehlt eine Angabe, nennt der
Dialog sie und erstellt kein Dokument. Die Zollpapiere ersetzen keine
elektronische Ausfuhranmeldung.

Die Seite **Lieferscheine** listet alle Auslieferungen im gewählten Zeitraum —
mit Lieferschein-PDF, Versand per E-Mail, Zollpapieren und Versandstand, ohne
Umweg über den einzelnen Fertigungsauftrag. Filter „Ohne Versand“ zeigt, was
noch auf ein Label wartet.
