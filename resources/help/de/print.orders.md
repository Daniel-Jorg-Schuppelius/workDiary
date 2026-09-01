---
title: "Druckaufträge (Druck & Kopiershop)"
topic: print.orders
version: 1
audience: []
modules:
    - module.lager
related:
    - claims.overview
    - documents.manage
---

Das Branchenprofil Druck/Kopiershop führt jeden Druckauftrag als Fachakte am
Fertigungsauftrag: Datenannahme, Dateiprüfung (Preflight), Druckfreigabe,
Produktion, Qualitätskontrolle und Ausgabe gehören reproduzierbar zusammen.

**Datei & Preflight:** Die Produktionsdatei liegt im Dokumentenspeicher und
wird mit ihrer SHA-256-Prüfsumme an den Auftrag gebunden. Der Preflight
unterscheidet Fehler (blockieren die Freigabe) und Warnungen; ein manueller
Override ist nur mit Begründung möglich und wird auditiert. Eine neue
Dateiversion setzt den Auftrag automatisch zurück auf „Datenprüfung".

**Freigabe:** Die Druckfreigabe friert Format, Material, Menge, Farbigkeit,
Termin und Weiterverarbeitung zusammen mit dem Datei-Hash als
unveränderlichen Produktions-Snapshot ein.

**Produktion & QK:** Maschinen mit Sperre oder überfälliger
Prüfung/Kalibrierung können nicht regulär starten. Gutmenge und Makulatur
laufen über den Fertigungsauftrag in Lager und Nachkalkulation. Die
Qualitätskontrolle vergleicht gegen den Freigabestand und dokumentiert
Freigabe, Sperre oder Nacharbeit.

**Ausgabe & Löschfristen:** Abholung verlangt einen Übergabenachweis, Versand
läuft über die vorhandene Sendungslogik, der Tresenverkauf bleibt
datensparsam. Nach Ablauf der Löschfrist wird nur die Kundendatei entfernt —
Auftrag, Snapshot und Prüfsumme bleiben als kaufmännischer Nachweis.
