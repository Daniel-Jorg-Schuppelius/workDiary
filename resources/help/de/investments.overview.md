---
title: "Investitionsplanung"
topic: investments.overview
version: 3
keywords:
    - Investitionsantrag
    - Budgetantrag
    - Investition beantragen
    - Investitionsfreigabe
    - CapEx
    - Anschaffung planen
    - Angebotsvergleich
    - Variantenvergleich
    - Budgetüberschreitung
    - Soll-Ist-Vergleich
    - Wirtschaftlichkeitsbetrachtung
    - Lessons Learned
audience: []
modules:
    - module.investments
related:
    - documents.manage
---

Die Investitionsakte führt eine Investition von der Idee bis zur
Nachbewertung: Anlass, Ziel/Nutzen, Risiko, Variantenvergleich mit
Angeboten (Einmal- und Folgekosten, Lieferzeit, Qualität, Empfehlung),
versionierter Budgetantrag und Freigabekette.

**Freigaben:** Unterhalb des Schwellenwerts genügt eine kaufmännische
Freigabe; ab dem Schwellenwert (konfigurierbar, Standard 10.000 €) gilt
das Vier-Augen-Prinzip mit zusätzlicher Management-Stufe. Der
Antragsteller kann nie selbst freigeben. Genehmigte Budgets werden als
Snapshot eingefroren — eine Erhöhung läuft immer über eine genehmigte
Budget-Abweichung und einen Nachtrag (neuer Antrag, alter Stand bleibt
als „ersetzt" erhalten).

**Genehmigungs-Eingang:** Offene Freigabestufen eines Budgetantrags
erscheinen unter „Genehmigungen“ und im Arbeitsvorrat bei der Rolle, die
Ihre Organisation der Stufenart zuordnet (Vorgabe: kaufmännisch →
Buchhaltung, Geschäftsleitung → Geschäftsführung). Beim Einreichen und
beim Wechsel zur nächsten Stufe werden die Zuständigen benachrichtigt.
Entschieden wird dort wie an der Akte; Freigeben darf nur, wer
Investitionen freigeben darf.

**Entscheiden an der Akte:** Es gilt dieselbe Zuständigkeit wie im
Eingang. „Freigeben“ und „Ablehnen“ sieht nur, wer für die offene Stufe
zuständig ist und sie entscheiden darf — nicht der Antragsteller und nicht,
wer eine frühere Stufe derselben Runde entschieden hat. Alle anderen sehen,
worauf der Antrag wartet, etwa „Wartet auf Stufe 2: Geschäftsleitung (Rolle
Geschäftsführung).“ Auch Admins entscheiden nur mit der Rolle der Stufe;
welche Rolle das ist, legen Sie in den Organisationseinstellungen unter
„Genehmigungen“ fest.

**Zurückstellen & Ablehnung:** In der Planung lässt sich die Akte mit
„Zurückstellen" parken; „Wieder aufnehmen" führt sie genau in die Phase
zurück, in der sie stand. Die Ablehnung eines Budgetantrags ist
endgültig: Eine abgelehnte Akte bleibt abgelehnt und lässt sich weder
zurückstellen noch erneut beantragen — ein neuer Anlauf beginnt mit einer
neuen Akte.

**Umsetzung & Ist-Werte:** Nach der Freigabe werden Projekt, Bestellung,
Asset, Eingangsrechnung oder Dokument verknüpft. Die Soll-Ist-Ansicht
zeigt genehmigtes Budget, gebundene Mittel (Bestellungen) und Ist-Kosten
(verknüpfte Eingangsrechnungen, Asset-Anschaffungskosten, manuelle
Werte) als Projektion — die führenden Module bleiben führend, WorkDiary
ersetzt keine Buchhaltung.

**Nachbewertung:** Nach Abschluss oder Abbruch werden tatsächlicher
Nutzen, Wirtschaftlichkeit, Lessons Learned und Folgemaßnahmen
dokumentiert.
