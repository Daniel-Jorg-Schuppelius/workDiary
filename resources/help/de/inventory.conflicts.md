---
title: "Konflikte mit Fremdsystemen (Bestand und Artikel)"
topic: inventory.conflicts
version: 3
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
modules:
    - module.lager
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

**Artikelkonflikte:** Dieselbe Liste zeigt Artikel, die lokal geändert
wurden und deren Stand im angebundenen Fremdsystem (etwa Lexware Office)
abweicht — bei Konflikt-Strategie „Manuelle Prüfung“ des Plugins. Je
Konflikt stehen der Artikel, die abweichenden Felder und beide Werte
nebeneinander. Drei Wege: *Lokal belassen* schließt den Konflikt, der
lokale Stand bleibt und geht beim nächsten Abgleich an das Fremdsystem.
*Stand des Fremdsystems übernehmen* (etwa „Lexoffice-Stand übernehmen“)
holt den Artikel frisch aus dem Fremdsystem und überschreibt die lokale
Änderung. *Verwerfen* schließt den Konflikt ohne Abgleich — beide Stände
bleiben, wie sie sind; weicht der Artikel beim nächsten Abgleich weiter
ab, entsteht ein neuer Konflikt.

**Rechte & Filter:** Der Reiter „Konflikte“ in der Lager-Reiterleiste
zeigt die Zahl der offenen Konflikte. Zum Ansehen genügt das
Bestands-Leserecht oder das Artikel-Leserecht; ohne Bestandsrecht sehen
Sie nur Artikelkonflikte, ohne Artikelrecht nur Bestandskonflikte.
Aufgelöst wird je Art: Bestandskonflikte mit dem Buchungsrecht, weil die
Kompensation eine echte Lagerbuchung ist; Artikelkonflikte mit dem Recht
zur Artikelpflege. Die Liste lässt sich nach offenen bzw. allen Konflikten
und nach der Art (Bestand, Artikel) filtern.

Offene Konflikte sollten zeitnah geprüft werden: Solange sie bestehen,
weichen lokaler und externer Bestand voneinander ab — mit Folgen für
Verfügbarkeiten, Bestellvorschläge und Bewertung.
