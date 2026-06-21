---
title: "Kohortenvergleich (vor/nach Fortbildung)"
topic: reports.cohort-comparison
version: 1
audience: []
related:
    - reports.economics
---

Der Kohortenvergleich zeigt, ob sich eine Kennzahl bei Mitarbeitenden nach
dem Erwerb einer Fortbildung verbessert hat.

So funktioniert es:

- Wähle eine **Fortbildung/Qualifikation** und eine **Kennzahl**
  (abrechenbare Quote oder Nacharbeitsanteil) sowie ein **Vergleichsfenster**
  in Tagen (Standard 90).
- Für jeden Mitarbeitenden, der diese Qualifikation besitzt, wird die Kennzahl
  im Fenster **vor** und **nach** dem Erwerbsdatum berechnet und die Differenz
  (Delta) ausgewiesen.
- Zusätzlich wird der **Kohorten-Mittelwert** über alle Mitarbeitenden mit
  Erwerbsdatum gebildet.

Datengrundlage:

- Das **Erwerbsdatum** stammt aus dem Feld "gültig ab" der
  Qualifikationszuordnung (user_qualifications.valid_from). Mitarbeitende
  **ohne hinterlegtes Erwerbsdatum** können nicht in den Vor/Nach-Schnitt
  einfließen und werden transparent gesondert ausgewiesen.
- Die Kennzahlen werden aus **denselben Zeitbuchungsfeldern**
  (abrechenbar/nicht abrechenbar) gebildet wie die Wirtschaftlichkeitssicht.

Hinweis: Fehlen in einem der beiden Fenster Zeitbuchungen, kann der Vergleich
für die betroffene Person nicht gebildet werden ("–"). Der Vergleich ist ein
Indikator, kein kausaler Nachweis.
