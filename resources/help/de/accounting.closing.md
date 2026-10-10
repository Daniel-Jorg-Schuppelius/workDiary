---
title: "Abschluss und Auswertungen"
topic: accounting.closing
version: 2
keywords:
    - Monatsabschluss
    - Jahresabschluss
    - Periode sperren
    - Periode wiedereröffnen
    - Festschreibung
    - BWA
    - EÜR
    - USt-Vorschau
    - Liquiditätsplanung
    - Plan-Ist-Vergleich
    - Z3-Export
    - Betriebsprüfung
audience:
    - admin
    - geschaeftsfuehrung
    - buchhaltung
related:
    - accounting.overview
    - accounting.posting
---

**Perioden** werden in zwei Stufen geschlossen: *vorläufig* ist ein Signal —
inhaltlich fertig, Korrektur noch möglich; *endgültig* ist eine Sperre — die
Periode nimmt keine Buchung mehr an. Vor dem endgültigen Abschluss prüft ein
Preflight offene Entwürfe und unausgeglichene Buchungen.

**Wiedereröffnen** braucht ein eigenes Recht, eine Begründung und landet in der
Nachweiskette. Ohne diese drei wäre der Abschluss nur eine Sichtbarkeit.

**Auswertungen** (Finanzberichte) lesen ausschließlich festgeschriebene
Buchungen — ein Entwurf ist eine Absicht, keine Zahl. Umsatzsteuer- und
EÜR-Auswertung sind prüfbare **Vorschauen**: Der MVP übermittelt nichts an
ELSTER.

**Export der Berichte:** PDF, CSV und Excel der Finanzberichte, der BWA und
des Budgets setzen neben dem Leserecht das Recht **Auswertungen exportieren**
voraus; ohne das Recht fehlen die Exportknöpfe. Administratoren dürfen immer
exportieren.

**Übergabe**: Der Z3-Prüfungsexport enthält Kontenplan, Journal,
Buchungszeilen, offene Posten und Perioden; die DATEV-Übergabe entsteht aus den
Festbuchungen, nicht erneut aus den Belegen.

**BWA, Budget und Liquidität:** Aus denselben festgeschriebenen Buchungen
entstehen die **betriebswirtschaftliche Auswertung** (Erlöse, Kosten und
Ergebnis nach Gruppen), der **Budget-Abgleich** je Konto und Kostenstelle — die
Vorjahreswerte lassen sich als Ausgangspunkt übernehmen — sowie die
**Liquiditätsvorschau**. Alle drei sind Auswertungen, keine zweite
Datenhaltung: Was Sie dort sehen, steht so im Journal. Korrekturen erfolgen
deshalb immer an der Buchung, nie am Bericht.

**Umlagen, Budgetfreigabe und Planpositionen:** Umlageschlüssel verteilen die
Aufwendungen einer Vorkostenstelle anteilig auf andere Kostenstellen; die BWA
zeigt das wahlweise „nach Umlage“, die Buchungen bleiben unverändert. Ein
freigegebenes Budget ist gegen Änderungen gesperrt, bis ein Nachtrag mit
Begründung es wieder öffnet; überschreitet eine Buchung das Monatsbudget, weist
die Buchung darauf hin, gebucht wird trotzdem. In der Liquiditätsvorschau
ergänzen Sie **Planpositionen** (etwa Steuervorauszahlungen oder Kreditraten);
jeden Montag hält die App den Wochenstand fest, und „Plan/Ist“ vergleicht ihn
später mit den tatsächlichen Kontobewegungen.
