---
title: "GoBD-Export (Datenträgerüberlassung)"
topic: finance.gobd
version: 1
audience:
    - admin
related:
    - invoices.manage
    - audit.log
---

Für Betriebsprüfungen erzeugt WorkDiary die Datenträgerüberlassung nach
Zugriffsart Z3: ein Prüfungspaket im GDPdU-Beschreibungsstandard, das der
Prüfer direkt in seine Auswertesoftware einlesen kann.

**Paketinhalt:** Das Paket ist ein ZIP-Archiv mit einer index.xml, die
Tabellen, Felder und Formate maschinenlesbar beschreibt, sowie
Semikolon-getrennten CSV-Datendateien. Die Datenbereiche sind einzeln
wählbar: Ausgangsrechnungen, Rechnungspositionen, Debitorenstammdaten und
Zeitnachweise des Prüfungszeitraums.

**Zeitraum & Vorprüfung:** Standardmäßig ist das Vorjahr als
Prüfungszeitraum vorbelegt; Von/Bis sind frei wählbar. Vor dem Export
zeigt eine Vorprüfung die Datensatzzahlen je Bereich und warnt bei
Auffälligkeiten — etwa wenn im Zeitraum noch Entwurfsrechnungen liegen
oder gar keine Rechnungen gefunden werden.

**Zeichensatz:** Die CSV-Dateien werden wahlweise in CP1252 („ANSI",
Standard und prüferseitig der sicherste Weg), ISO-8859-15 oder UTF-8
erzeugt; die Beschreibungsdatei weist den gewählten Zeichensatz aus.

**Reproduzierbarer Hash:** Alle Daten werden deterministisch sortiert und
formatiert. Der Paket-Hash wird über die Dateiinhalte gebildet (nicht
über die ZIP-Binärdatei, die Zeitstempel enthält) — derselbe Zeitraum mit
denselben Bereichen und demselben Zeichensatz ergibt daher reproduzierbar
denselben Hash. Zusätzlich wird je Datei ein eigener Hash dokumentiert.
Damit lässt sich später zweifelsfrei belegen, dass ein übergebenes Paket
unverändert ist.

**Export-Nachweisliste:** Jeder Export legt automatisch einen
revisionssicheren Nachweis an: wer wann welchen Zeitraum mit welchen
Bereichen exportiert hat, inklusive Paket- und Datei-Hashes sowie der
Satzanzahl. Die letzten Exporte sind direkt auf der Seite sichtbar; die
vollständige Historie bleibt dauerhaft erhalten und ergänzt das
Audit-Log.

Der Export liest ausschließlich vorhandene Daten — er verändert weder
Belege noch Stammdaten und kann beliebig oft wiederholt werden.
