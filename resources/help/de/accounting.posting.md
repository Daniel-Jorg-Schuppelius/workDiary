---
title: "Buchen und Inbox"
topic: accounting.posting
version: 1
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
modules:
    - module.finance
related:
    - accounting.overview
    - accounting.closing
---

Die **Buchungs-Inbox** ist der Einstieg: Sie zeigt Belege, Auslagen, Kassen-
und Zahlungsvorgänge des Zeitraums mit ihrem Buchungsstatus — ungebucht,
blockiert, bereit, gebucht. Blockiertes steht oben; das ist die Arbeit, die
jemand anfassen muss.

**Vorschlag vor Festbuchung.** Auch ein eindeutiger Vorschlag wird erst zum
geprüften Entwurf, nie direkt zur Festbuchung. Ist das Vier-Augen-Prinzip
aktiv, schreibt die vorbereitende Person nicht selbst fest.

**Blockiert statt geraten.** Fehlt eine Buchungsregel, nennt der Vorschlag
Rolle und Merkmale („Keine Buchungsregel für Erlös (tax_rate=19.00)"). Ein
geratenes Standardkonto fiele erst bei der Auswertung auf — dann ist die
Buchung festgeschrieben.

**Korrektur nur über Gegenbuchung.** Eine festgeschriebene Buchung ist
unveränderlich. Der Storno erzeugt eine gespiegelte Gegenbuchung mit
Pflichtbegründung; das Original bleibt stehen.

**Belege in Fremdwährung.** Ausgangs- und Eingangsrechnungen sowie Auslagen in
fremder Währung werden zum Monatskurs ihres Belegmonats umgerechnet
(§ 16 Abs. 6 UStG). Die Kurse pflegen Sie unter „Umrechnungskurse“ (bei den
Buchungsregeln) einzeln oder zeilenweise, etwa aus der BMF-Veröffentlichung.
Fehlt der Kurs, bleibt der Beleg mit Hinweis in der Inbox. Kurs und
Originalbetrag stehen im Buchungsnachweis. Zahlungen, Kasse und Anlagen in
Fremdwährung bucht die Buchhaltung weiterhin nicht; Kursdifferenzen beim
Ausgleich buchen Sie von Hand.
