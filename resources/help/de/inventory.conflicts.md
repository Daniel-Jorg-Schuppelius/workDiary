---
title: "Bestands-Konflikte (externe Übertragung)"
topic: inventory.conflicts
version: 1
audience:
    - admin
related:
    - inventory.stock
    - warehouses.manage
---

Führt ein externes System die Bestandshoheit (etwa eine Warenwirtschaft),
spiegelt WorkDiary jede lokal gebuchte Lagerbewegung dorthin. Diese Seite
zeigt die Fälle, in denen die Spiegelung endgültig gescheitert ist — sie
sind der Ort für die fachliche Nacharbeit.

**Übertragung mit Idempotenz:** Jede Bewegung erzeugt höchstens einen
Zustellauftrag in einer persistenten Warteschlange. Wird derselbe Vorgang
mehrfach angestoßen, entsteht trotzdem nur eine Übertragung — Doppelbuchungen
im externen System sind damit ausgeschlossen. Vorübergehende Fehler werden
automatisch erneut versucht.

**Wann ein Konflikt entsteht:** Schlägt die Zustellung einer Bewegung
endgültig fehl — etwa weil das externe System sie ablehnt —, entsteht ein
Konflikt. Die lokale Buchung bleibt bestehen, aber der externe Bestand
weicht ab. Jeder Konflikt erscheint hier mit Bezug zur zugrunde liegenden
Bewegung und wartet auf eine bewusste Entscheidung.

**Auflösen:** Pro Konflikt gibt es zwei Wege. *Lokal beibehalten* akzeptiert
die Abweichung ausdrücklich und schließt den Konflikt ohne weitere Buchung —
sinnvoll, wenn der lokale Stand fachlich korrekt ist. *Kompensieren* gleicht
die lokale Bewegung durch eine betragsgleiche Gegenbuchung im selben Bestand
aus. Es wird niemals nachträglich gelöscht oder technisch zurückgerollt; das
Lagerjournal bleibt lückenlos und jede Entscheidung wird mit Person und
Zeitpunkt festgehalten.

**Rechte & Filter:** Zum Ansehen genügt das Bestands-Leserecht; zum Auflösen
ist zusätzlich das Buchungsrecht erforderlich, weil die Kompensation eine
echte Lagerbuchung ist. Die Liste lässt sich nach offenen bzw. allen
Konflikten filtern.

Offene Konflikte sollten zeitnah geprüft werden: Solange sie bestehen,
weichen lokaler und externer Bestand voneinander ab — mit Folgen für
Verfügbarkeiten, Bestellvorschläge und Bewertung.
