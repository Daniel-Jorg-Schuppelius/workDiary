<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : glossary.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Begriffs-Glossar (Feature 039): Kurzdefinitionen für die x-term-Tooltips.
// Keys sind locale-übergreifend identisch (TranslationParityTest).
return [
    'flexzeit' => "Gleitzeitkonto: laufende Differenz zwischen Soll- und Ist-Arbeitszeit.",
    'zuschlag' => "Lohnzuschlag für Nacht-, Sonn- und Feiertagsarbeit; nach § 3b EStG teils steuerfrei.",
    'kostenstelle' => "Buchhalterische Zuordnungseinheit für Zeit- und Lohnkosten (z. B. im Lohn-Export).",
    'sla' => "Service Level Agreement: vertraglich zugesagte Reaktions- und Lösungszeiten.",
    'gobd' => "Grundsätze ordnungsmäßiger Buchführung: unveränderbare, revisionssichere Aufzeichnungen.",
    'aufmass' => "Vor Ort gemessene Ist-Menge einer Leistungsposition (Grundlage der Abrechnung).",
    'nacharbeit' => "Arbeitszeit zur Behebung eigener Fehler — nicht berechenbar, mindert die Marge.",
    'kulanz' => "Bewusst nicht berechnete Leistung aus Kundenbindungsgründen.",
    'vvt' => "Verzeichnis von Verarbeitungstätigkeiten (Art. 30 DSGVO).",
    'avv' => "Auftragsverarbeitungsvertrag mit externen Dienstleistern (Art. 28 DSGVO).",
    'tom' => "Technische und organisatorische Maßnahmen zum Schutz personenbezogener Daten.",
    'dsfa' => "Datenschutz-Folgenabschätzung für Verarbeitungen mit hohem Risiko (Art. 35 DSGVO).",
    'soa' => "Statement of Applicability: Erklärung, welche ISO-27001-Controls anwendbar sind.",
    'meldebestand' => "Bestandsschwelle, deren Unterschreitung automatische Bestellvorschläge auslöst.",
    'vier_augen' => "Freigabe durch eine zweite, unabhängige Person vor Ausführung des Schritts.",
    'backlog' => "Aufträge ohne festen Termin — werden bei der Planung nachgezogen.",
    'story_points' => "Relatives Aufwandsmaß eines Arbeitselements — Team-intern vergleichbar, nie in Stunden oder Geld umgerechnet.",
    'wip' => "Work in Progress: Obergrenze gleichzeitiger Elemente je Spalte; Überschreitung nur begründet übersteuerbar.",
    'velocity' => "Erledigte Story Points je abgeschlossenem Sprint (Median + Spannweite) — Planungsgröße, kein Leistungsmaß.",
    'abnahme' => "Formelle Bestätigung des Kunden, dass die Leistung vertragsgemäß erbracht wurde — dokumentiert per signiertem Protokoll; startet Gewährleistung und Abrechnung.",
    'prozedur' => "Geführte Schritt-für-Schritt-Arbeitsanweisung aus einer versionierten Vorlage; jeder Durchlauf wird nachvollziehbar protokolliert.",
    'zeitkonto' => "Arbeitszeitkonto: dokumentiert Mehr- und Minderstunden gegenüber der vertraglichen Soll-Arbeitszeit — Grundlage für Freizeitausgleich oder Auszahlung.",
    'rfm_recency' => "Recency-Score 1–5: Wie kurz liegt die letzte Leistung zurück? 5 = oberstes Fünftel (zuletzt aktiv), 1 = am längsten inaktiv.",
    'rfm_frequency' => "Frequency-Score 1–5: Anzahl der Aktivitätstage im Zeitraum, als Quintil über alle aktiven Kunden. 5 = häufigste Kunden.",
    'rfm_monetary' => "Monetary-Score 1–5: Erlös im Zeitraum (abrechenbare Zeit-Snapshots), als Quintil. 5 = umsatzstärkste Kunden.",
    'hhi' => "Herfindahl-Hirschman-Index: Summe der quadrierten Umsatzanteile (in %). Unter 1500 unkritisch, über 2500 hohe Konzentration (Klumpenrisiko).",
    'dso' => "Days Sales Outstanding: offene Forderungen ÷ Umsatz der letzten 90 Tage × 90 — durchschnittliche Kapitalbindung in Tagen.",
    'auslastung' => "Erfasste Arbeitszeit ÷ Soll-Zeit aus dem Arbeitszeitmodell, in Prozent.",
    'abrechenbare_quote' => "Abrechenbare ÷ erfasste Arbeitszeit, in Prozent — wie viel Zeit in bezahlbare Arbeit fließt.",
    'realisierung' => "Fakturierte ÷ abrechenbare Arbeitszeit, in Prozent — wie viel abrechenbare Arbeit tatsächlich auf Rechnungen landet.",
    'kohorte' => "Gruppe von Kunden mit Erstleistung im selben Jahr — ihre Aktivität wird über die Folgejahre verfolgt.",
];
