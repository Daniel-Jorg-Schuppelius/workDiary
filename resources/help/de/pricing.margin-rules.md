---
title: "Preis- & Margenregeln"
topic: pricing.margin-rules
version: 1
audience:
    - admin
related:
    - supplier-catalogs.overview
    - articles.master
---

Margenregeln leiten Verkaufspreisvorschläge aus Einkaufspreisen ab. Sie
sorgen dafür, dass Preise aus Lieferantenkatalogen nicht von Hand
kalkuliert werden müssen — und dass keine Übernahme an der Kalkulation
vorbeiläuft.

**Regelinhalt:** Eine Regel rechnet entweder mit einem Aufschlag in
Prozent auf den Einkaufspreis oder mit einer Zielmarge in Prozent vom
Verkaufspreis; sind beide gesetzt, hat die Zielmarge Vorrang. Optional
kommen dazu: eine Mindestmarge (der Vorschlag wird gekennzeichnet, wenn
sie unterschritten würde), ein Mindestverkaufspreis und ein
Rundungsschema für kaufmännisch glatte Endpreise. Regeln lassen sich
deaktivieren, ohne sie zu entfernen.

**Geltungsbereich & Anwendungsreihenfolge:** Eine Regel gilt global, für
einen Lieferanten, für eine Warengruppe oder für die Kombination aus
beidem. Bei mehreren passenden aktiven Regeln gewinnt die spezifischste:
Lieferant plus Warengruppe vor nur einem der beiden Kriterien vor global.
Bei Gleichstand entscheidet die Priorität der Regel, danach die jüngste.
So können Sie einen unternehmensweiten Standardaufschlag pflegen und ihn
gezielt für einzelne Lieferanten oder Warengruppen übersteuern.

**Wirkung auf Katalog-Übernahmen:** Die Vorschläge erscheinen direkt an
den verknüpften Katalogartikeln der Lieferantenkataloge. In den
Verkaufspreis des Artikels gelangen sie nie automatisch: Im Direktmodus
übernimmt sie der Bearbeiter ausdrücklich, im Vier-Augen-Modus entsteht
stattdessen ein Freigabe-Antrag. Genehmigen darf einen Antrag nur eine
andere Person als der Antragsteller; Ablehnungen können begründet werden.
Der Freigabemodus (direkt oder Vier-Augen) wird auf dieser Seite je
Organisation umgestellt, offene und entschiedene Anträge sind dort
einsehbar.

Bereits abgeschlossene Vorgänge und historische Preise bleiben von
Regeländerungen unberührt — eine geänderte Regel wirkt erst auf die
nächste Preisübernahme. Lesen erfordert Lager-Leserechte, das Verwalten
der Regeln und Anträge Lager-Konfigurationsrechte.
