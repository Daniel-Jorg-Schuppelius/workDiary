---
title: "E-Rechnungs-Eingang"
topic: finance.incoming-invoices
version: 1
audience: []
modules:
    - module.vertrieb
related:
    - invoices.manage
    - finance.datev-bookings
---

Der Bereich nimmt eingehende E-Rechnungen entgegen, prüft sie und führt
sie durch einen dokumentierten Freigabeprozess — ohne die Rechnungshoheit
des führenden Buchhaltungs- bzw. Faktura-Programms anzutasten.

**Eingang:** E-Rechnungen kommen per Datei-Upload oder über den
E-Mail-Eingang herein — als XRechnung (XML) oder ZUGFeRD/Factur-X (PDF
mit eingebettetem XML). Alle Kanäle durchlaufen exakt dieselbe
Verarbeitung. Der Beleg wird als Dokument vom Typ Rechnung im DMS
abgelegt; das unveränderte Original bleibt die einzige Quelle, die
Detailseite liest es bei jedem Aufruf neu. Eine lokale Rechnung entsteht
dabei nicht.

**Dubletten:** Identischer Dateiinhalt wird je Organisation nur einmal
erfasst — auch kanalübergreifend (ein Upload nach vorherigem Mail-Eingang
bleibt eine Dublette).

**Validierung & Konsistenz:** Jeder Eingang wird gegen das XML-Schema
und, sofern eingerichtet, gegen die KoSIT-Prüfregeln (EN 16931)
validiert; ob die Prüfungen verfügbar waren, wird transparent
ausgewiesen. Zusätzlich warnt die Abweichungsprüfung sichtbar — nie
stillschweigend — bei bereits erfasster Rechnungsnummer desselben
Ausstellers, bei widersprüchlichen Summen (Netto + Steuer ≠ Brutto) und
bei Steuerausweis ohne Steuerkennung des Ausstellers.

**Vorschläge:** Zur Zuordnung schlägt das System Lieferanten (über
USt-IdNr. oder Namensähnlichkeit), Bestellungen (über die
Bestellreferenz) und Projekte (über Projekt-/Käuferreferenz) vor — als
begründete Kandidaten. Die Übernahme bleibt beim Prüfer; Stammdaten
werden nie automatisch angelegt oder geändert.

**Prüfworkflow:** Ein Eingang wird freigegeben, mit Rückfrage versehen
oder abgelehnt (Ablehnung nur mit Begründung). Erst nach fachlicher
Freigabe ist die Zahlungsfreigabe möglich. Jede Entscheidung wird mit
Person und Zeitpunkt auditiert.

**Übergabe an die Buchhaltung:** Nur freigegebene bzw. zahlungsfreigegebene
Eingänge werden übergeben. Die Übergabe ist idempotent — ein zweiter
Aufruf ändert nichts und erzeugt keinen doppelten Nachweis.

**XML-Download:** Das Rechnungs-XML lässt sich jederzeit deterministisch
aus dem Original extrahieren (bei ZUGFeRD aus dem PDF-Anhang). Jeder
Abruf wird mit Prüfsumme als Nachweis protokolliert.
