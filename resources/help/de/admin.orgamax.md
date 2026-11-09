---
title: "orgaMAX Buchhaltung"
topic: admin.orgamax
version: 2
keywords:
    - orgaMAX Schnittstelle
    - orgaMAX API
    - Buchhaltung anbinden
    - Buchhaltungssoftware
    - Faktura
    - Rechnungen übertragen
    - Kunden synchronisieren
    - Zahlungsstatus
    - Stammdatenabgleich
    - Auftrag übergeben
    - Synchronisation
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

Belegspiegel: Ist Faktura oder Zahlungen aktiv, spiegelt der Abgleich die
orgaMAX-Rechnungen samt Positionen, mit der Capability Ausgaben auch die
Ausgaben als Eingangsbelege. Sie erscheinen im Belegfluss, in der Akte von
Kunde und Lieferant, in Umsatz, offenen Posten und Provisionen sowie im Einkauf
von Abos und Lizenzen; Wiederholungsvorlagen stehen im Abgleich der Abos und
Lizenzen (Rhythmus und nächste Ausführung führt die orgaMAX-API nicht). Mit der
Capability Artikel stehen die orgaMAX-Artikel im Artikelkatalog. Gelesen wird
nur — WorkDiary schreibt die Ausgaben nicht zurück.

Lieferanten überträgt WorkDiary nur, wenn die Capability Lieferanten aktiv ist
und „WorkDiary führt“ gilt; orgaMAX vergibt dann die Lieferantennummer.
