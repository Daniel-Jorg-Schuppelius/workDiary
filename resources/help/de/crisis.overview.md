---
title: "Notfall- & Krisenmanagement"
topic: crisis.overview
version: 4
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

Eigene Meldefristen pflegen Sie im Reiter **Meldefristen**: Vorgaben einer
Kategorie als eigene Fristen übernehmen, Fristen anlegen, ändern und
deaktivieren. Sobald eine Kategorie eine aktive eigene Frist hat, gelten für
sie nur noch die eigenen Fristen; ohne aktive eigene Frist gelten wieder die
Vorgaben.

**Maßnahmen mit Vorgänger:** Beim Erfassen einer Maßnahme wählen Sie auf
Wunsch einen Vorgänger aus derselben Akte. Die Maßnahme lässt sich erst
erledigen, wenn der Vorgänger erledigt oder abgebrochen ist; in Arbeit nehmen
können Sie sie vorher.

**Ersatzprozess und Reststatus:** Zum kritischen Prozess erfassen Sie neben dem
Workaround den Ersatzprozess. Beim Wiederanlaufstatus halten Sie die
verbleibende Einschränkung fest.

**Krise aus einem Vorfall eröffnen:** Sicherheitsvorfall, Datenschutzvorfall,
Arbeitsschutzereignis und Major-Incident-Ticket tragen den Knopf „Krise
eröffnen“. Der Dialog belegt Titel, Kategorie und Auslöser vor und verknüpft
den Vorfall mit der neuen Akte. Weitere Vorgänge verknüpfen Sie in der Akte
über „Vorgang verknüpfen“ aus einer Auswahl; angeboten wird, was Sie lesen
dürfen.

**Notfall-Anwesenheitsliste:** Eine laufende Krisenakte führt mit dem Recht für
die Notfall-Anwesenheitsliste direkt dorthin.

**Vorgänger ändern:** Den Vorgänger einer Maßnahme ändern oder lösen Sie direkt in der Maßnahmenliste. Zur Wahl stehen nur Maßnahmen derselben Akte; eine Wahl, die einen Kreis ergäbe (A wartet auf B, B auf A), weist WorkDiary ab.
