---
title: "orgaMAX Buchhaltung"
topic: admin.orgamax
version: 1
audience:
    - admin
modules:
    - module.finance
related:
    - admin.integrations
    - admin.plugins
---

orgaMAX Buchhaltung wird als organisationsbezogenes Plugin über die
offizielle OpenAPI angebunden (nicht orgaMAX ERP). orgaMAX bleibt für
aktivierte Capabilities das führende Fachsystem.

Verbindung:

1. **Verbindungsabsicht starten** (privater Pilotmodus mit API-Key/Secret
   oder veröffentlichte Erweiterung mit Betreibergeheimnis). WorkDiary
   erzeugt eine Callback-URL mit State-Token.
2. Die URL in orgaMAX als Erweiterungs-URL hinterlegen und öffnen — orgaMAX
   hängt die `iid` an. Ein fremdes `iid` ohne gültige Absicht wird nie
   gebunden.
3. Das erkannte Konto **ausdrücklich bestätigen**; der Scope-Preflight
   blockiert bei fehlenden Freigaben statt teilweise zu aktivieren.

Datenführerschaft je Capability (Kunden, Lieferanten, Artikel, Faktura,
Zahlungen, Ausgaben, Dokumente): genau ein System führt; sicherer Standard
ist manuelle Prüfung. Stammdaten werden über die Integrations-Inbox
zugeordnet — keine Schattenstammdaten.

Faktura: Freigegebene Übergaben (Finanzen → Übergaben, Ziel orgaMAX)
erzeugen höchstens EINEN orgaMAX-Auftrag (Quellmarker + Reconciliation
statt blinder Wiederholung). Umwandeln in eine Rechnung, irreversibles
Sperren, Versand und Zahlung melden sind getrennte, eigens berechtigte und
auditierte Aktionen. Rechnungsnummer, Status, Zahlung und PDF stammen
sichtbar aus orgaMAX.

Polling läuft budgetiert mit Checkpoints (stündlich, konfigurierbar);
„Jetzt synchronisieren" respektiert dieselben Limits. Die Ausgaben-/Beleg-
Übergabe bleibt bis zum bestätigten Receipt-Pilot blockiert.
