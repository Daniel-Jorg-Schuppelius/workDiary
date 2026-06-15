# SLA, Verträge und Service-Level

## Status

In Progress — MVP: SLA-Status (im Plan/gefährdet/verletzt) auf Ticketliste und
-detail, SLA-Verletzungsregister (`sla_violations`) mit idempotenter Erkennung
(Scanner `tickets:scan-sla-breaches` + Statusübergänge), SLA-Report
(Einhaltungsquote, Aufschlüsselung je Typ/Priorität/Kunde/Ursache, CSV/PDF),
Eskalation (`sla.atRisk` < 20 % Restzeit / `sla.breached`) über den Fristen-Scanner.
Offen: Auftrags-/DiaryEntry-Verknüpfung des SLA-Kontexts, Wartungsintervalle,
Inklusivzeiten/Kontingente, Geschäftszeiten in der Fristberechnung.

## Ziel

WorkDiary soll Vertrags- und Service-Level-Informationen abbilden, damit
Aufwand und Qualität gegen vereinbarte Erwartungen bewertet werden können:
Reaktionszeiten, Lösungszeiten, Wartungsintervalle, inkludierte Leistungen,
Bereitschaftsfenster, Eskalationsregeln und Abrechnungsgrenzen.

## Warum

Ohne Sollwerte kann WorkDiary Aufwand messen, aber nicht belastbar bewerten.
Ob ein Kunde schwierig ist oder ein Auftrag ineffizient lief, hängt oft vom
Vertrag ab. Ein hoher Aufwand kann normal, abrechenbar, vertragswidrig oder ein
Warnsignal für Preisanpassung sein.

## MVP

- Vertragsprofile pro Kunde oder Projekt.
- SLA-Ziele: Reaktionszeit, Bearbeitungszeit, Lösungszeit, Eskalationsstufen.
- Wartungsintervalle und wiederkehrende Pflichttermine.
- Inklusivzeiten, Kontingente oder Pauschalen.
- SLA-Status am Auftrag: im Ziel, gefährdet, verletzt.
- Auswertung von SLA-Verletzungen und Ursachen.

## Akzeptanzkriterien

- Ein Auftrag kennt seinen Vertrags- und SLA-Kontext.
- Überschreitungen werden zeitnah sichtbar.
- Reports können Aufwand mit Vertragserwartungen vergleichen.
- Eskalationen und Verletzungen sind nachvollziehbar dokumentiert.
- Vertragsdaten beeinflussen Abrechnung und Auswertung, ohne Rohzeiten zu
  verändern.

## Abhängigkeiten

- Aufzeichnung und Zeiterfassung als Kernprodukt
- Auswertungen und Entscheidungsgrundlagen
- Kunden und Projekte
- Dienstplan-Intelligenz
- Rechnungen
- Automationen

## GitHub Issues

- TBD
