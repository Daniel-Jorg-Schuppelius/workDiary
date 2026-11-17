---
title: "Inventur"
topic: inventory.counts
version: 5
keywords:
    - Bestandsaufnahme
    - Lagerinventur
    - Stichtagsinventur
    - zyklische Inventur
    - permanente Inventur
    - Jahresinventur
    - Zählliste
    - Bestand zählen
    - Inventurdifferenz
    - Differenzen buchen
    - ABC-Klasse
audience: []
modules:
    - module.lager
related:
    - inventory.stock
    - warehouses.manage
    - articles.master
---

Auf dieser Seite führen Sie Inventuren je Lagerort durch. Die Liste zeigt
die Zählungen im gewählten Zeitraum. Sie können eine vollständige Inventur
eröffnen oder eine zyklische Teilzählung über eine ABC-Klasse starten,
die nur die fälligen Varianten umfasst.

In der Detailansicht erfassen Sie die gezählten Mengen je Zeile, wahlweise
auch per Scan. Solange eine Inventur offen ist, lassen sich
Zählergebnisse weiter ergänzen und ändern.

Das Buchen der Differenzen erzeugt Korrekturbuchungen im Bestand und
schließt die Inventur ab. Diese Aktion erfordert ein gesondertes
Freigaberecht und ist nicht rückgängig zu machen; prüfen Sie die gezählten
Werte daher, bevor Sie die Differenzen anwenden.

## Zählen in Einheiten und Freigabe

Hat ein Artikel weitere Einheiten, lässt sich die Zählmenge in der Zählliste je Zeile, beim Scan und in der mobilen Zählung in einer dieser Einheiten erfassen; gespeichert wird in der Basiseinheit. Zählen und Freigeben sind getrennte Rechte. Ist die Vier-Augen-Freigabe eingeschaltet, gibt Differenzen ab dem Grenzwert nicht frei, wer sie gezählt hat.

**Offline zählen mit Einheit:** Auch ohne Netz zählt die mobile Ansicht in der gewählten Einheit (etwa Karton). Beim Senden rechnet der Server sie in die Basiseinheit um, genau wie online.

## Zählung abschließen, wieder öffnen, verwerfen

**Zählung abschließen** friert die gezählten Mengen für die Prüfung ein; danach lässt sich nichts mehr erfassen. Wer Differenzen freigibt, kann die Zählung zur Korrektur **wieder öffnen**, die Differenzen buchen oder die **Inventur verwerfen** – eine verworfene Inventur bucht nichts. Der Prüfschritt ist freiwillig; freigeben lässt sich auch direkt aus der Zählung.

**Bewertete Differenzen:** Differenzen im freien Eigenbestand sind bewertet. Mehrbestand geht wie ein manueller Eingang ein (Einkaufspreis der Variante, sonst Durchschnitt des Lagers), Minderbestand nach dem Bewertungsverfahren ab — bei FIFO und FEFO aus den ältesten bzw. zuerst verfallenden Schichten, beim gleitenden Durchschnitt zum Durchschnittspreis. Differenzen in reserviertem oder gesperrtem Bestand und in Fremdbestand ändern den Bestandswert nicht. Die Vier-Augen-Freigabe misst den so gebuchten Wert der Differenz; greift sie, wird nichts gebucht.
