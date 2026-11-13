---
title: "Kundenfristen"
topic: customers.deadlines
version: 1
keywords:
    - Kundenfristen
    - Fristen
    - Mandantenfristen
    - Umsatzsteuer-Voranmeldung
    - Dauerfristverlängerung
    - Lohnsteuer-Anmeldung
    - Zusammenfassende Meldung
    - Jahreserklärung
    - Beitragsnachweis
    - Beratungsbesuch
    - Leistungsumfang
audience: []
related:
    - contacts.manage
    - work-queue.overview
    - delegations.manage
    - accounting.filings
---

Manche Fristen hängen nicht an einem Vertrag, sondern an Eigenschaften des
Kunden und am Gesetz: die Umsatzsteuer-Voranmeldung eines Kanzleikunden, der
Beitragsnachweis eines Lohnkunden, der Beratungsbesuch eines Pflegekunden.
workDiary rechnet diese **Kundenfristen** aus den Fristmerkmalen und dem
Leistungsumfang des Kunden, erinnert rechtzeitig und hakt sie ab, sobald der
passende Auftrag erledigt ist. Die Oberfläche sagt „Kunde“ – in einer Kanzlei
ist das der Mandant (in workDiary meint „Mandant“ die Organisation).

## Regelsätze

Ein **Regelsatz** enthält die gesetzlichen Regeln einer Branche. Er ist
eingeschaltet, wenn das Branchenprofil ihn mitbringt oder Sie ihn unter
**Kundenfristen › Einstellungen** einschalten.

- **Steuerberatung:** Umsatzsteuer-Voranmeldung (10. Tag nach dem Zeitraum,
  mit Dauerfristverlängerung einen Monat später), Sondervorauszahlung (10.02.
  bei monatlicher Abgabe mit Verlängerung), Lohnsteuer-Anmeldung (10. Tag,
  nie verlängert), Zusammenfassende Meldung (25. Tag, nie verlängert),
  Jahreserklärungen je Steuerart aus dem Leistungsumfang (beraten bis Ende
  Februar des übernächsten Jahres, sonst 31.07.; Übergangsfristen bis 2024)
  und – bei der Leistung **Lohn** – Beitragsnachweis und Fälligkeit der
  Sozialversicherung (drittletzter Bankarbeitstag, Nachweis zwei
  Bankarbeitstage davor). Fällt eine Steuerfrist auf ein Wochenende oder einen
  Feiertag **am Ort des Finanzamts**, gilt der nächste Werktag; bei mehreren
  Krankenkassen gilt der früheste Termin aller Bundesländer.
- **Ambulante Pflege:** Beratungsbesuch nach § 37 Abs. 3 SGB XI – Pflicht nur
  bei Pflegegeld und Pflegegrad 2 bis 5, einmal je Halbjahr; bei Pflegegrad 4
  und 5 auf Wunsch je Quartal. Den Pflegegrad liest workDiary aus der
  Klassifikation des Kunden.

## Fristmerkmale pflegen

Im **Cockpit des Kunden** zeigt die Karte **Kundenfristen** die Merkmale und
die nächsten offenen Fristen. Über **Fristmerkmale** öffnet sich der Dialog:
Er ist vorbelegt wie die geltende Fassung („wie Vorjahr“). Eine Änderung gilt
ab dem gewählten Tag und schließt die bisherige Fassung ab – vergangene
Fristen behalten ihre Grundlage. workDiary rechnet danach nur **offene**
Fristen neu; erledigte, verlängerte und vorab angeforderte bleiben, wie sie
sind. Was vor dem Stichtag der ersten Fassung fällig war, entsteht nicht.

Fehlt das **Bundesland des Finanzamts** oder der Einzugsstellen, rechnet
workDiary mit der Feiertagsregion der Organisation und markiert die Frist.

Unter **Leistungsumfang** stehen die Leistungen des Kunden (Eintragsart und
Produktgruppe, Pflicht oder Wahl, Periodenart, gültig ab/bis). Der Regelsatz
Steuerberatung liest daraus die Jahreserklärungen und die Lohnleistung; der
Leistungsplan zeigt sie je Periode.

## Fristen bearbeiten

Ein Klick auf eine Frist öffnet sie mit Verlauf. Sie lässt sich als
**erledigt** vermerken, **verlängern** (neuer Termin und Grund), als **vorab
angefordert** kennzeichnen (das Finanzamt verlangt die Erklärung früher, § 149
Abs. 4 AO), **entfallen** lassen oder **wieder öffnen**. Ein abgeschlossener
Auftrag mit Periode erledigt die passende Frist von selbst – etwa ein Auftrag
**Beratungsbesuch** 2026-H1 den Besuch des ersten Halbjahrs oder ein Auftrag
**Lohn** März die Lohnsteuer-Anmeldung und die Beitragstermine März.

## Übersicht, Erinnerung und Vertretung

- **Kundenfristen** listet alle Fristen mit Ampel (überfällig, in der
  Vorwarnzeit, im Plan), Filtern nach Regelsatz, Fristart, Zuständigkeit,
  Monat und Status sowie Export als CSV oder Excel.
- Die **zuständige Person** erhält eine gebündelte Erinnerung, sobald Fristen
  in die Vorwarnzeit rücken (Vorgabe 7 Tage, je Regelsatz einstellbar), und
  eine weitere bei Überfälligkeit. Fristen bis zum Wochenende und überfällige
  stehen in ihrem **Arbeitsvorrat** und im **Kalender**.
- Während einer **Vertretung** sieht die Vertretung diese Fristen und
  Erinnerungen mit dem Hinweis „In Vertretung für …“. Beim Austritt oder der
  endgültigen Übergabe geht die Zuständigkeit an die Nachfolge.

Sehen dürfen Kundenfristen Personen mit dem Recht **Kundenfristen ansehen**;
Merkmale, Leistungsumfang und alle Fristen pflegt, wer **Kundenfristen und
Fristmerkmale pflegen** hat. Die zuständige Person bearbeitet ihre eigenen
Fristen auch mit dem Leserecht.

## Was workDiary nicht tut

Es übermittelt nichts an ELSTER, DATEV oder Pflegekassen und führt keine
Buchhaltung für Kunden. Vereinbarte Fristen bleiben Vertragspflichten, Fristen
an Geräten bleiben Prüfpläne.
