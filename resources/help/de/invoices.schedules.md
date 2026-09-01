---
title: "Abrechnungspläne"
topic: invoices.schedules
version: 1
audience:
    - admin
modules:
    - module.vertrieb
related:
    - invoices.manage
---

**Abrechnungspläne** erzeugen wiederkehrende Rechnungs-**Entwürfe**
(MVP-415) — Ausstellung und Versand bleiben immer manuelle Schritte.

- **Rhythmus**: Woche/Monat/Quartal/Jahr × Anzahl; Abrechnungszeitraum ist der
  abgelaufene oder der laufende Zeitraum (Vorauszahlung).
- **Positionsvorlage**: Platzhalter `{zeitraum_von}` und `{zeitraum_bis}`
  werden je Lauf ersetzt; Rabatte werden übernommen.
- **Vertrag**: optional verknüpft — endet der Vertrag, endet der Plan.
- **Idempotent**: je Plan und Periode entsteht höchstens ein Entwurf, auch
  wenn der Lauf mehrfach startet; verpasste Läufe werden nachgeholt.
- **Rechnungshoheit**: führt ein externes Programm die Faktura des Kunden,
  bleibt der Plan sichtbar blockiert und erzeugt nichts.
