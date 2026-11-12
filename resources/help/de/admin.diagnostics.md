---
title: "Diagnose"
topic: admin.diagnostics
version: 2
keywords:
    - Systemstatus
    - Systemzustand
    - Health Check
    - Systemprüfung
    - Fehlersuche
    - Testmail
    - Mailversand prüfen
    - Warteschlange
    - Queue
    - Scheduler
    - Cronjob
    - Ampel
audience:
    - admin
related:
    - admin.security
    - admin.metrics
    - admin.handbook
---

Die Diagnose liefert einen System-Health-Bericht mit Ampel-Status je
Prüfbereich. Sie hilft, Konfigurations- und Betriebsprobleme früh zu
erkennen.

Geprüfte Bereiche sind unter anderem:

- **Version** der Anwendung
- **Lizenz**
- **Queue** (Warteschlange/Hintergrundjobs)
- **Scheduler** (geplante Aufgaben)
- **Mail** (Versandkonfiguration)
- **Storage** (Speicher)
- **Backup**

Jeder Bereich erhält einen Status (OK, Warnung, kritisch oder
unbekannt). Der Bericht steht zusätzlich als JSON zur
maschinellen Auswertung bereit.

Zusätzlich lässt sich eine **Test-Mail** an die eigene
E-Mail-Adresse auslösen, um die Mail-Konfiguration zu prüfen. Das
Ergebnis wird zurückgemeldet.

Aufrufe der Diagnose und ausgelöste Tests werden im Audit-Log
protokolliert. Das Anzeigen erfordert das Diagnose-Recht, das Auslösen
von Prüfungen ein eigenes Recht. Betriebskennzahlen finden Sie unter
**Metriken**.

**Überwachungszugang:** Für externe Überwachung legen Sie unter „Überwachungszugang“ einen Zugang an. Die Überwachung ruft den Bericht ohne Anmeldung unter /api/monitoring/diagnostics ab und sendet den Token im Kopf „Authorization: Bearer …“. Der Token erscheint nur einmal und wird nur als Abdruck gespeichert; Widerrufen sperrt ihn sofort. Der Bericht enthält keine Namen und Adressen, etwa Lizenznehmer oder Absenderadresse. Abrufe werden höchstens einmal je Stunde und Zugang protokolliert.
