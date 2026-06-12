---
title: "Organisationen & Mandanten"
topic: admin.tenants
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.license
    - admin.roles
---

Hier verwaltest du Organisationen (Mandanten). Jede Organisation ist
eine abgeschottete Einheit – sämtliche Daten gehören genau einem
Mandanten.

Typische Aktionen:

- **Anlegen/Bearbeiten**: Stammdaten und Plan der Organisation.
- **Deaktivieren/Reaktivieren**: reversibel – die Organisation wird
  gesperrt, Daten bleiben erhalten.
- **Exportieren**: Datenexport im Sinne der Datenübertragbarkeit
  (Art. 20 DSGVO).
- **Endgültig löschen (Purge)**: Löschung nach Art. 17 DSGVO.
- **Wechseln**: globale Admins können in den Kontext einer anderen
  Organisation wechseln (Org-Switcher).

Plan und Module: Jede Organisation hat einen Plan (free/pro/
enterprise) bzw. eine organisationsgebundene Lizenz; daraus ergeben
sich die freigeschalteten Module (z. B. Finance, ISMS, Datenschutz) –
Details im Kapitel **Lizenz**.

Risiken und unumkehrbare Aktionen:

- **Purge ist irreversibel** – alle Daten der Organisation werden
  endgültig gelöscht (Audit-gepflegt). Vorher immer Export anbieten
  und Aufbewahrungspflichten prüfen.
- Deaktivieren ist die sichere Alternative, wenn nur der Zugang
  beendet werden soll.
