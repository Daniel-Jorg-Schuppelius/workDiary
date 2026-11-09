---
title: "Notfall- & Krisenmanagement"
topic: crisis.overview
version: 2
keywords:
    - Krisenstab
    - Notfallmanagement
    - Notfallplan
    - Alarmierung
    - Krisenalarm
    - Business Continuity
    - BCM
    - Lagebild
    - Meldepflicht
    - NIS2
    - Datenschutzvorfall
    - Wiederanlauf
    - Notfallübung
    - KRITIS
audience: []
modules:
    - module.crisis_management
related:
    - documents.manage
---

Die Krisenakte führt einen koordinierten Führungszustand über verknüpften
Fachvorgängen (Service-Tickets, Security-/Datenschutzvorfälle,
Arbeitsschutzereignisse, Playbook-Läufe) — die Fachmodule bleiben führend.

**Krisenstab & Alarmierung:** Rollen mit Person, Stellvertretung und
Erreichbarkeit. Die Alarmierung überstimmt Ruhezeiten bewusst
(Krisenalarm); jedes Mitglied quittiert seinen Alarm, unquittierte Alarme
werden manuell an die Stellvertretung eskaliert. Die Benennung in den
Stab ist zugleich der auditierte Notfallzugriff auf die Akte.

**Lagebild & Entscheidungen:** Lageberichte sind versioniert und
append-only; das Entscheidungsprotokoll hält Zeitpunkt, Entscheidung und
Begründung fest.

**Meldefristen:** Fristen-Templates je Kategorie (DSGVO 72 h, NIS2
24 h/72 h/1 Monat, KRITIS unverzüglich) sind Katalogdaten — die
Organisation kann sie überschreiben; die konkreten Termine laufen ab der
Aktivierung.

**Fehlmeldung verwerfen:** Stellt sich eine gemeldete Krise als Fehlalarm
heraus, bevor jemand die Lage bewertet hat, verwirft „Fehlmeldung
verwerfen“ die Akte mit Pflichtbegründung. Die Begründung steht im
Auditverlauf, die Akte bleibt nur lesend erhalten. Nach der Bewertung wird
nicht mehr verworfen, sondern entwarnt.

**Kommunikation:** Entwurf, Freigabe (nie durch den Verfasser) und
dokumentierte Aussendung bleiben getrennt nachvollziehbar.

**Wiederanlauf & Nachbereitung:** kritische Prozesse mit RTO/RPO,
Workarounds und Ersatzprozessen; nach der Entwarnung folgt die
Nachbereitung (Pflicht vor dem Schließen). Übungen laufen getrennt und
verfälschen keine echten Akten.
