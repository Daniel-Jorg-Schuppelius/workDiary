---
title: "Kostenstellenregeln"
topic: admin.cost-center-rules
version: 1
audience:
    - admin
related:
    - exports.payroll
    - org.teams
---

Kostenstellenregeln ordnen Zeiteinträgen beim Zeitexport (z. B. für
das Lohnbüro) automatisch eine **Kostenstelle** zu – ohne dass jemand
je Eintrag manuell nacharbeiten muss.

**Aufbau einer Regel:** Jede Regel besteht aus genau **einer Quelle**
– entweder ein Benutzer **oder** ein Team; bleiben beide leer, wirkt
die Regel als **Organisations-Standard**. Dazu kommen der
Kostenstellen-Code und eine Priorität. Gepflegt werden die Regeln von
Administratoren sowie von Buchhaltung/Lohnbüro mit entsprechender
Berechtigung.

**Auflösungs-Reihenfolge:** Beim Export wird je Person von der
spezifischsten zur allgemeinsten Regel aufgelöst:

- **Benutzer-Regel** – gewinnt immer, wenn vorhanden.
- **Team-Regel** – greift, wenn die Person Mitglied des Teams ist.
- **Organisations-Standard** – die Regel ohne Benutzer und Team.
- Passt keine Regel, bleibt die Kostenstelle im Export **leer**.

**Priorität als Stichentscheid:** Kommen auf derselben Stufe mehrere
Regeln infrage (z. B. weil eine Person in mehreren Teams mit eigener
Regel ist), gewinnt die Regel mit der **höchsten Priorität**; bei
gleicher Priorität die zuerst angelegte. Vergib daher sprechende
Prioritätsabstände (z. B. 100er-Schritte), damit du später Regeln
dazwischenschieben kannst.

**Zusammenspiel mit den Stammdaten:** Kostenstellen führst du als
Stammdaten mit Code und Bezeichnung je Organisation. Die Regeln
speichern derzeit den Code als Text – achte deshalb darauf, dass die
Codes in den Regeln mit den Stammdaten übereinstimmen und passe die
Regeln an, wenn du Kostenstellen umbenennst oder deaktivierst.

**Empfehlung:** Starte mit einem Organisations-Standard, ergänze
Team-Regeln für Abteilungen mit eigener Kostenstelle und nutze
Benutzer-Regeln nur für echte Ausnahmen. Prüfe nach Änderungen einen
Probe-Export, bevor die Daten ans Lohnbüro gehen.
