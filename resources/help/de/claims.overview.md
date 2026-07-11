---
title: "Reklamation & Gewährleistung"
topic: claims.overview
version: 1
audience: []
related:
    - documents.manage
---

Das Modul führt Reklamationen, Gewährleistungsfälle, Kulanzentscheidungen
und Rückläufer als nachvollziehbare Fallakten — vom Eingang über Bewertung
und Entscheidung bis zu Lager-, Service- und Faktura-Folgen.

**Fallakte:** Jede Reklamation erhält eine eigene Nummer (REK-…), Fristen,
Verantwortliche und Verknüpfungen zu Auftrag, Projekt, Asset, Artikel,
Seriennummer, Rechnung und Lieferant. Die Fachmodule bleiben führend —
die Akte verknüpft, sie überschreibt nichts.

**Bewertung & Entscheidung:** Anspruchsart (Garantie, gesetzliche oder
vertragliche Gewährleistung, Kulanz, Transportschaden, Fehlbedienung,
Lieferantenfehler) mit Pflichtbegründung. Die Faktenlage (Seriennummern-
prüfung, Fristen, Rügedatum nach § 377 HGB im B2B-Fall) wird als Snapshot
eingefroren. Entscheidungen brauchen eine aktive Bewertung und sind
auditierbar; eine automatische Anspruchsentscheidung gibt es bewusst nicht.

**Rückläufer (RMA):** Rücksendungen erhalten eine RMA-Nummer, der
Wareneingang landet in Quarantäne (Sperr-/Prüfbestand), die Prüfung
dokumentiert Befund und Seriennummernabgleich. Die Verwendungsentscheidung
(Wiedereinlagerung, Reparatur, Rücksendung an Lieferant, Verschrottung,
Entsorgung) bucht idempotent über das Lagerjournal.

**Kaufmännische Folgen:** Minderung, Gutschrift, Storno, Korrektur,
Ersatzrechnung oder Rückerstattung werden vorgeschlagen, per
Vier-Augen-Prinzip freigegeben und erst dann ausgeführt. Belege entstehen
im Faktura-Modul (Gutschrift/Storno als Entwurf) mit strukturiertem
Grund-Kennzeichen — es gibt keinen eigenen Belegtyp.

**Lieferantenregress:** Eigener Anspruch gegenüber dem Vorlieferanten mit
Bestell-/Eingangsrechnungsbezug, Antwortfrist und Kostenrückfluss.

**Auswertung:** Der Qualitätsbericht zeigt Quote, Ursachen, betroffene
Artikel, Lieferanten, Kosten, Bearbeitungsdauer und Wiederholfehler;
Berichtsstände lassen sich als Nachweis einfrieren.

**Kundenportal:** Kunden sehen den Status ihrer eigenen Fälle und können
Nachreichungen übermitteln — interne Bewertungen und Beträge bleiben
unsichtbar.
