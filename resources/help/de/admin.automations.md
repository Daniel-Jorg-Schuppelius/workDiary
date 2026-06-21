---
title: "Automatisierungen"
topic: admin.automations
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.notification-rules
    - admin.webhooks
---

Automatisierungen sind regelbasierte Abläufe nach dem Muster
**Ereignis → Bedingung → Aktion**. Tritt ein definiertes
Auslöse-Ereignis (Trigger) ein und passen die hinterlegten
Bedingungen, werden die zugeordneten Aktionen ausgeführt. Die Regeln
gelten je Organisation und sind streng auf den eigenen Mandanten
beschränkt.

In der Übersicht siehst du alle Regeln, sortiert nach Priorität und
Name. Pro Regel stehen folgende Aktionen bereit:

- **Anlegen**: Name, Auslöse-Ereignis, Bedingungen und Aktionen.
  Bedingungen und Aktionen werden im aktuellen MVP-Stand als
  JSON erfasst; ein visueller Regel-Editor ist als spätere
  Erweiterung vorgesehen.
- **Aktiv/Inaktiv schalten (Toggle)**: deaktivierte Regeln bleiben
  erhalten, lösen aber keine Aktionen mehr aus.
- **Detailansicht**: zeigt die jüngsten Ausführungen (Runs) einer
  Regel zur Nachvollziehbarkeit.
- **Löschen**: entfernt die Regel dauerhaft.

Die **Priorität** steuert die Reihenfolge bei mehreren passenden
Regeln (niedrigerer Wert zuerst). Ungültiges JSON in Bedingungen oder
Aktionen wird abgewiesen.

Hinweis: Für reine Benachrichtigungen sind die
**Benachrichtigungsregeln** oft die einfachere Wahl; für externe
Systeme siehe **Webhooks**.
