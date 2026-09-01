---
title: "Bekanntmachungs-Radar"
topic: tenders.radar
version: 1
audience: []
modules:
    - module.applications
related:
    - applications.overview
---

Der Radar durchsucht die **öffentlichen Bekanntmachungen des Bundes** nach
Ausschreibungen, die zum eigenen Betrieb passen. Quelle ist der
Bekanntmachungsservice (oeffentlichevergabe.de), der alle Pflicht­
bekanntmachungen als OpenData unter CC0 veröffentlicht — registrierungsfrei
und ohne Portal-Zugangsdaten.

**Suchprofile** sagen, wonach gesucht wird. Zwei Codesysteme tragen die
Suche: **CPV** benennt, *was* beschafft wird, **NUTS**, *wo*. Beide sind
hierarchisch, deshalb genügen Präfixe — `45` trifft alle Bauleistungen, `DEA`
ganz Nordrhein-Westfalen. Stichwörter suchen zusätzlich in Titel,
Beschreibung und Vergabestelle; **Ausschlusswörter wiegen schwerer**: Ein
Treffer dort verwirft die Bekanntmachung, auch wenn alles andere passt.
Bekanntmachungen ohne Wertangabe werden von den Wertgrenzen nicht
ausgeschlossen — sonst entginge, was seinen Wert nicht nennt.

**Der Abruf läuft täglich und holt den Vortag.** Ein Veröffentlichungstag ist
erst am Folgetag vollständig; heute abzurufen brächte Lücken. Berichtigte
Bekanntmachungen kommen als neue Fassung an, die alte bleibt erhalten.

**Die Treffer-Inbox schlägt vor, sie entscheidet nicht.** Was nicht passt,
wird ausgeblendet und bleibt als Beleg erhalten; was passt, wird per
Übernahme zu einem Vergabevorgang mit vorbelegtem Titel, Vergabestelle, CPV,
Region, Frist und Quelle. **Verfahrensart und Schwellenwert sind danach zu
prüfen** — die offene Datenquelle nennt das Verfahren nur grob, und daraus
ließe sich weder die deutsche Verfahrensart noch die Schwellenwertlage sicher
ableiten.
