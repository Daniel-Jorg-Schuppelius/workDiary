---
title: "Systemeinstellungen"
topic: admin.settings
version: 1
audience:
    - admin
related:
    - admin.handbook
---

Diese Seite verwaltet alle registrierten Einstellungen der Plattform
an einer Stelle – von Seitengrößen über Upload-Grenzen bis zu
Betriebs- und Integrations-Schwellen.

**Zentrale Registry:** Jede Einstellung ist mit Typ, erlaubten
Geltungsbereichen und Validierungsregeln registriert. Geschrieben
wird ausschließlich über diesen validierten Weg – ungültige Werte
(z. B. außerhalb der Min-/Max-Grenzen) werden mit einer klaren
Fehlermeldung abgelehnt, bevor sie wirken können.

**Zwei Geltungsbereiche:** Einstellungen gelten je nach Eintrag
**systemweit**, **je Organisation** oder beides. Über den
Bereichs-Umschalter wechseln Sie die Sicht; die Suche filtert nach
Schlüsseln, die Liste ist nach Gruppen sortiert.

**Vorrang-Logik:** Für jeden Wert gilt eine feste Reihenfolge – die
**Organisations-Einstellung** geht vor der **System-Einstellung**,
und diese vor dem eingebauten **Standardwert** der Installation. Die
Übersicht zeigt zu jedem Eintrag den effektiven Wert samt Herkunft,
sodass Sie sofort erkennen, ob ein Wert Standard ist oder übersteuert
wurde.

**Zurücksetzen und Verlauf:** Jede Übersteuerung lässt sich einzeln
auf den Standard zurücksetzen. Für System-Einstellungen können Sie
zusätzlich den Änderungsverlauf einsehen: wer wann welchen Wert
gesetzt hat – nachvollziehbar über das Audit-Protokoll.

**Sensible Werte:** Einträge, die als sensibel markiert sind (z. B.
Webhook-Adressen mit Geheimnissen), werden in der Oberfläche
maskiert angezeigt. Sie lassen sich neu setzen, aber nicht auslesen.

**Wirkung auf Jobs:** Manche Einstellungen beeinflussen geplante
Hintergrund-Jobs (etwa Aufbewahrungsfristen oder Ausführungszeiten).
Solche Zusammenhänge sind am Eintrag vermerkt; die Änderung greift
beim nächsten Lauf.

**Empfehlung:** Übersteuern Sie so wenig wie möglich. Jeder Org-Override
macht das Verhalten schwerer vorhersagbar – setzen Sie ihn nur, wenn die
Organisation wirklich abweichen muss, und dokumentieren Sie den Grund.
Prüfen Sie nach Änderungen den angezeigten Effektivwert, statt sich auf
die Eingabe zu verlassen.
