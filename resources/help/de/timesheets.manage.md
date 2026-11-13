---
title: "Stundenzettel verwalten"
topic: timesheets.manage
version: 4
keywords:
    - Stundennachweis
    - Leistungsnachweis
    - Arbeitsnachweis
    - Regiebericht
    - Stundenbericht
    - Rapportzettel
    - Stundenliste
    - Unterschrift einholen
    - Material erfassen
    - Leistungen abrechnen
    - Stundenzettel PDF
audience: []
related:
    - time-entries.start
    - protocols.sign
    - finance.transfers
---

Stundenzettel bündeln Leistungen eines Projekts oder Zeitraums für
Prüfung, Unterschrift und weitere Abrechnung. Positionen können Zeiten,
Tätigkeiten und Material enthalten.

Typischer Ablauf: Projekt wählen, Positionen erfassen, Summen prüfen,
Stundenzettel zur Unterschrift bereitstellen und nach der Bestätigung
abschließen. Bereits unterschriebene Inhalte dürfen nicht still
verändert werden; Korrekturen erfolgen nachvollziehbar über einen neuen
Stand.

Vor der Freigabe müssen Zeitraum, Kunde, Projekt, Leistungsbeschreibung
und Mengen fachlich stimmen. Ein PDF ist eine Ausgabe des gespeicherten
Stands, nicht die führende Datenquelle.

**Material von „Heute“:** Über „Material“ auf „Heute“ erfassen Sie Verbrauchsmaterial mit Projekt, Menge und Einheit — auch ohne Netz. Es landet auf Ihrem offenen Stundenzettel des Projekts für diesen Tag; gibt es keinen (oder ist er schon unterschrieben), wird ein neuer angelegt.

**Lagerabgang:** Ist das Material mit einem lagergeführten Artikel verknüpft (Artikel mit dem Merkmal „Lagerfähig“) und das Lagermodul aktiv, bucht der Verbrauch den Abgang aus dem Standardlager der Organisation — in der erfassten Einheit und bewertet nach dem Bewertungsverfahren des Lagers. Ändern Sie die Menge, wird nur die Differenz gebucht; ein anderes Material bucht den alten Artikel zurück, und das Löschen der Zeile oder des ganzen Stundenzettels bucht den Abgang zurück. Unter der Bezeichnung zeigt die Zeile, ob und aus welchem Lager gebucht wurde. Reicht der Bestand nicht, wird die Erfassung abgewiesen; mit dem Recht „Negative Bestände freigeben“ wählen Sie „Negativen Bestand zulassen“. Verlangt die Organisation dafür vier Augen, wartet der Abgang auf die Freigabe im Genehmigungs-Eingang („Negativbestand beantragt“). Seriennummernpflichtige Artikel entnehmen Sie über das Lager; chargenpflichtige werden aus Chargen entnommen, das früheste Mindesthaltbarkeitsdatum zuerst. Ohne Standardlager steht „Nicht gebucht – kein Standardlager“ an der Zeile; wird der Bestand in einem anderen System nur lesend geführt, bucht WorkDiary nicht. Führt ein anderes System den Bestand, geht jede Bewegung zusätzlich dorthin. Offline gilt dasselbe: Verhindert der Bestand die Buchung, landet der Eintrag als Konflikt unter „Offline-Änderungen“.

**Summengrenze:** Material, das Sie auf „Heute“ erfassen, landet auf dem offenen Stundenzettel des Tages. Würde Menge × Einzelpreis zusammen mit den übrigen Zeilen die speicherbare Summe des Zettels überschreiten, wird die Erfassung abgewiesen — online wie offline.
