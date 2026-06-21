---
title: "Demodaten"
topic: admin.demo-data
version: 1
audience:
    - admin
related:
    - admin.tenants
    - admin.handbook
    - admin.data-transfer
---

Demodaten dienen zum Befüllen einer Organisation mit Beispieldaten für
Test und Schulung. Die Inhalte richten sich nach einer wählbaren
Branche (Industry).

Aktionen:

- **Erzeugen (Seed)**: legt Beispieldaten (z. B. Kunden,
  Tagebuch-Einträge) für die gewählte Branche an. Die Übersicht zeigt
  an, ob die Organisation aktuell leer ist.
- **Zurücksetzen (Reset)**: setzt einen Demo-Mandanten zurück.

Risiken und Einschränkungen:

- **Reset ist nur für ausgewiesene Demo-Mandanten erlaubt**
  (`is_demo`). Für reguläre Organisationen wird er abgelehnt, um
  echte Daten zu schützen. Auf einem Demo-Mandanten überschreibt bzw.
  entfernt der Reset jedoch die vorhandenen Demo-Daten.
- Das Erzeugen fügt zusätzliche Datensätze hinzu; prüfe vorab, ob die
  Organisation wirklich leer sein soll.

Beide Aktionen erfordern eigene Berechtigungen (Seed bzw. plattformweit
Reset) und werden im Audit-Log protokolliert.
