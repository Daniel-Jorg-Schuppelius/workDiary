---
title: "Auftragstyp-Analyse"
topic: reports.entry-type-analysis
version: 5
keywords:
    - Soll-Ist-Vergleich
    - Plan-Ist-Abweichung
    - Bearbeitungsdauer
    - Durchlaufzeit
    - Nacharbeitsquote
    - Mehraufwand
    - Auftragsarten vergleichen
    - Eintragstyp
    - Ursachenanalyse
    - Aufwandsschätzung
    - Nachkalkulation
audience: []
modules:
    - module.auswertungen_team
related:
    - reports.customer-analysis
    - reports.drilldown
---

Die Auftragstyp-Analyse vergleicht Plan- und Ist-Dauern je Eintragstyp
(z. B. Wartung, Störung, Migration) und zeigt:

- **Durchschnittliche Bearbeitungsdauer** und Streuung
- **Plan-/Ist-Abweichung** in Prozent
- **Nacharbeit-Quote**
- **Top-Ursachen** (sofern als Klassifikation gepflegt)

So erkennen Sie Auftragstypen, in denen die Plan-Annahmen unrealistisch
sind oder das Team systematisch Mehraufwand hat – beides Hinweise auf
Schulungs- oder Pricing-Bedarf.

Die Plan-Dauer eines Auftrags ist seine **Geplante Dauer (HH:MM)**; ist sie
leer, zählt die Servicedauer eines disponierten Auftrags, sonst die Länge des Zeitfensters bzw. die Dauer des Termins.

Die Spalte **Top-Ursachen** nennt je Auftragstyp die drei häufigsten Klassifikationen aus dem Nacharbeitsgrund der gebuchten Zeiten und der Grundursache der Reklamationen zum Auftrag. Gezählt wird je Auftrag einmal; ein Klick zeigt die betroffenen Zeiten und Reklamationen. Der CSV-Export enthält die Spalte ebenfalls.

## Durchlaufzeit je Auftragstyp

Der Abschnitt **Durchlaufzeit je Auftragstyp** zeigt für die im Zeitraum
abgeschlossenen Aufträge den Median der Tage vom Anlegen bis zum Abschluss,
dazu die Zahl der Abschlüsse. Stornierte Aufträge zählen nicht. Der
Personenfilter wirkt hier bewusst nicht: Gemessen wird am Auftrag, nie je
Person. Die Leitungssicht zeigt dieselbe Zahl für die häufigsten
Auftragstypen der letzten zwölf Monate und verlinkt hierher.
