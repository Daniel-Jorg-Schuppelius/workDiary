---
title: "Reklamation & Gewährleistung"
topic: claims.overview
version: 4
keywords:
    - Mängelrüge
    - Kundenreklamation
    - Kundenbeschwerde
    - Garantiefall
    - Kulanz
    - Retoure
    - Rücksendung
    - RMA
    - Gewährleistungsfall
    - Lieferantenregress
    - Reklamationsquote
    - Mangel melden
audience: []
modules:
    - module.claims
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

**Betroffene Objekte verknüpfen:** Im Dialog „Neue Reklamation“ und über
„Bearbeiten“ an der Akte wählen Sie Rechnung, Auftrag, Projekt,
Service-Ticket, Asset, Protokoll und Lieferant, mit Lager-Modul auch
Artikel und Bestellung. Ist ein Kunde gewählt, zeigen die Listen nur
dessen Objekte. Eine im Lager bekannte Seriennummer wird der Akte
automatisch zugeordnet. An Rechnung, Auftrag, Asset und Service-Ticket
öffnet „Reklamation eröffnen“ den Dialog mit vorbelegtem Objekt und
Kunden.

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

**Lagerbezug des Rückläufers:** Ankündigung und Wareneingang nehmen
Artikelvariante, Menge, Lager, Seriennummer und Charge auf, vorbelegt aus
Artikel und Seriennummer der Akte. Mit Variante, Lager und Menge bucht der
Wareneingang den Bestand in die Quarantäne; die Verwendungsentscheidung
bucht ihn um oder aus. Ohne Variante oder ohne Lager-Modul wird der
Rückläufer nur dokumentiert, die Meldung lautet dann „ohne Bestandsbuchung
erfasst“. Rücksendungen aus dem Kundenportal bringen Variante und
Seriennummer mit; das Lager ergänzen Sie beim Wareneingang.

**Maßnahmen und Folgeauftrag:** Nacharbeit, Reparatur, Ersatz oder
Serviceeinsatz legen Sie unter „Maßnahmen“ mit Frist und verantwortlicher
Person an. Jede Maßnahme kann ihren Folgeauftrag nennen — einen Auftrag beim
Kunden der Akte, den Sie öffnen dürfen. Über ihn läuft der Einsatz in
Disposition und Tourplanung; die Akte verlinkt ihn, und an jeder Maßnahme lässt
er sich mit dem Status ändern oder lösen.

**Kaufmännische Folgen:** Minderung, Gutschrift, Storno, Korrektur,
Ersatzrechnung oder Rückerstattung werden vorgeschlagen, per
Vier-Augen-Prinzip freigegeben und erst dann ausgeführt. Belege entstehen
im Faktura-Modul (Gutschrift/Storno als Entwurf) mit strukturiertem
Grund-Kennzeichen — es gibt keinen eigenen Belegtyp.

**Rechnung zur Folge:** Im Dialog „Folge vorschlagen“ wählen Sie die
Rechnung, vorbelegt mit der Rechnung der Akte. Führen Sie die Rechnungen
selbst, braucht jede Folge mit Beleg diese Rechnung: Storno setzt eine
ausgestellte, unbezahlte Rechnung voraus, Gutschrift eine bezahlte. Führt
ein externes System die Rechnungen, entsteht der Korrekturbeleg dort und
Sie tragen die Belegnummer nach.

**Lieferantenregress:** Eigener Anspruch gegenüber dem Vorlieferanten mit
Bestell-/Eingangsrechnungsbezug, Antwortfrist und Kostenrückfluss.

Der Dialog „Regress anlegen“ nimmt Lieferant, Bestellung,
Eingangsrechnung, Artikel, Seriennummer, Garantiebedingungen, Forderung
und eine eigene Antwortfrist in Tagen auf. Ohne Angabe gilt die
Standardfrist; sie läuft ab dem Status „eingereicht“.

**Auswertung:** Der Qualitätsbericht zeigt Quote, Ursachen, betroffene
Artikel, Lieferanten, Kosten, Bearbeitungsdauer und Wiederholfehler;
Berichtsstände lassen sich als Nachweis einfrieren.

**Kundenportal:** Kunden sehen den Status ihrer eigenen Fälle und können
Nachreichungen übermitteln — interne Bewertungen und Beträge bleiben
unsichtbar.

**Charge an der Akte:** Bei chargengeführten Artikeln wählen Sie im Reklamationsdialog die betroffene Charge. Ohne gewählten Artikel übernimmt die Akte den Artikel der Charge; die Mustererkennung „gleiche Charge“ wertet das Feld aus.

## Vorschlag ablehnen

Wer kaufmännische Folgen freigibt, kann einen Vorschlag auch **ablehnen**. Er endet ohne Beleg und lässt sich weder freigeben noch ausführen; ein neuer Vorschlag bleibt möglich.
