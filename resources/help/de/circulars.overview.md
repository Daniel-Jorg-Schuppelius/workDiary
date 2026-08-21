---
title: "Kundenrundschreiben"
topic: circulars.overview
version: 1
audience: []
related:
    - contacts.manage
    - invoices.manage
---

Rundschreiben sind Geschäftsmitteilungen an einen gefilterten Kundenkreis —
Preisanpassung, Wartungsfenster, geänderte Notdienstzeiten. Kein Newsletter:
kein Zählpixel, keine umgeschriebenen Links.

**Empfängerkreis:** Der Kreis wird über die vorhandenen Kundenfilter
bestimmt (Suche, Ort, Postleitzahl-Anfang, nur Kunden mit aktivem Projekt).
Vor dem Versand steht die Empfängerzahl mit der vollständigen Liste — eine
Mail an alle Kunden soll nicht versehentlich ausgelöst werden können.

**Werbe-Opt-out:** Kunden mit gesetztem Schalter *Keine Sammelmails* werden
übersprungen. Als *Pflichtmitteilung* markierte Rundschreiben erreichen sie
trotzdem; das ist rechtlich gebotenen Informationen vorbehalten.

**Nachweis:** Je Empfänger entsteht eine Zeile — auch für Übersprungene, mit
Grund (etwa fehlende E-Mail-Adresse). Zusätzlich wird die Mitteilung als
Kommunikationsnotiz in der Kundenakte abgelegt; auf Wunsch erscheint sie
auch im Kundenportal.

**Platzhalter:** `:firma`, `:kunde` und `:ansprechpartner` werden je
Empfänger ersetzt. Fehlt ein Wert, bleibt die Stelle leer — es wird nichts
erfunden.
