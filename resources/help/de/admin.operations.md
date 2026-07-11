---
title: "Betriebsaufgaben & Wartungsfenster"
topic: admin.operations
version: 1
audience:
    - admin
related:
    - admin.backups
    - admin.diagnostics
---

WorkDiary unterstützt den laufenden Betrieb mit zwei Werkzeugen: dem
**Aufgabencenter** für wiederkehrende Betriebsprüfungen und der
Planung von **Wartungsfenstern**.

**Betriebsprüfungen als Scan:** Ein wiederkehrender Scan (standardmäßig
stündlich) prüft betriebsrelevante Punkte – etwa auslaufende
Zertifikate, ausbleibende Backups sowie Ablaufdaten von Lizenzen und
Zugangsdaten. Erkannte Punkte erscheinen als priorisierte Aufgaben im
Aufgabencenter, sortiert nach Schweregrad (kritisch vor Warnung vor
Hinweis) und filterbar nach Status, Typ und Schweregrad.

**Aufgaben bearbeiten:** Jede Aufgabe kannst du **erledigen**,
**zurückstellen** (Snooze für eine einstellbare Anzahl Tage),
**delegieren** (an eine Person deiner Organisation) oder **ignorieren**
(mit Pflicht-Begründung). Erledigte Aufgaben lassen sich wieder
öffnen. Alle Statuswechsel werden revisionssicher protokolliert.
Die Sichtbarkeit ist organisationsgebunden; installationsweite
Aufgaben liegen in der Betreiber-Organisation und sind entsprechend
gekennzeichnet.

**Wartungsfenster planen:** Wartungsfenster kündigst du mit Beginn,
Ende und optionaler Vorlaufzeit an – ab dem Ankündigungszeitpunkt
sehen Nutzer eine Meldung mit deinem Hinweistext. Ein Fenster gilt
wahlweise **systemweit** oder nur für die aktuelle **Organisation**.

**Ablauf eines Fensters:** Nach dem Planen durchläuft ein Fenster die
Schritte Ankündigen, Starten, bei Bedarf Verlängern und Beenden.
Während des laufenden Fensters kannst du optional den
**Nur-Lese-Modus** aktivieren (Nutzer sehen Daten, ändern aber nichts)
und den **Dateneingang blockieren** (externe Anlieferungen werden
angehalten). Läuft etwas schief, dokumentiert ein **Rollback** den
Abbruch samt Notizen; geplante Fenster lassen sich auch ersatzlos
absagen. Jede Aktion wird auditiert.

**Empfehlung:** Plane Wartungsfenster mit ausreichend Vorlauf, damit
die Ankündigung ihre Wirkung entfaltet, und arbeite das Aufgabencenter
regelmäßig ab – kritische Aufgaben zuerst. Zurückstellen ist für
bewusste Verschiebungen gedacht, nicht als Dauerzustand.
