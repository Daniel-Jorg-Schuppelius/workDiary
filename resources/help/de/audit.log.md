---
title: "Audit-Log"
topic: audit.log
version: 1
audience:
    - admin
related:
    - admin.security
    - admin.handbook
    - privacy.overview
---

Das Audit-Log (`/audit`) ist das revisionssichere Prüfprotokoll über
Änderungen und Aktionen im System. Die Einträge sind **append-only**
und über eine **SHA-256-Hash-Kette** miteinander verkettet (GoBD);
sie werden nie roh geschrieben und lassen sich nicht nachträglich
ändern oder löschen.

**Filter**: Die Liste lässt sich nach

- **Aktion** (z. B. angelegt, geändert, gelöscht, archiviert,
  wiederhergestellt sowie Import-Ereignisse),
- **Typ** des betroffenen Objekts (u. a. Tagebucheintrag, Kommentar,
  Kunde, Lieferant, Importlauf, Nummernkreis),
- **Benutzer** und
- **Zeitraum** (über den globalen Datumsfilter)

einschränken. Pro Eintrag siehst du Zeitpunkt, auslösenden Benutzer,
Aktion, Objekt, die konkreten Änderungen und die IP-Adresse.

**Integrität prüfen**: Die Hash-Kette wird über den Konsolenbefehl
`php artisan audit:verify` geprüft. Er validiert die Verkettung und
endet bei einem Bruch mit Exit-Code 1 – ideal für Cron/CI. Halte den
Befehl dauerhaft grün; ein Bruch deutet auf Manipulation oder einen
Datenfehler hin. Mit `--chain` lässt sich gezielt eine einzelne Kette
prüfen (`audit_logs` bzw. `organization_audit_logs`).

Hinweis: Das Audit-Log ist ein reines Lese-Werkzeug. Es zeigt
Vorgänge an, verändert aber selbst keine Daten.
