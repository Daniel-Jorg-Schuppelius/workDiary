---
title: "Automatisierungen"
topic: admin.automations
version: 3
keywords:
    - Workflow
    - Regeln
    - Wenn-Dann-Regel
    - Trigger
    - Auslöser
    - Automatisierungsregel
    - automatische Aktion
    - Workflow-Automatisierung
    - Regel anlegen
    - Prozessautomatisierung
    - Ablauf automatisieren
audience:
    - admin
related:
    - admin.handbook
    - admin.notification-rules
    - admin.webhooks
---

Automatisierungen sind regelbasierte Abläufe nach dem Muster
**Ereignis → Bedingung → Aktion**. Tritt ein definiertes
Auslöse-Ereignis ein und passen die hinterlegten Bedingungen, wird die
zugeordnete Aktion ausgeführt. Die Regeln gelten je Organisation und
sind streng auf den eigenen Mandanten beschränkt. Jede Auswertung wird
im Audit-Log protokolliert.

Die Übersicht zeigt alle Regeln mit **Prio**, **Name**, **Trigger**,
**Aktion(en)** und **Aktiv**, standardmäßig nach Priorität sortiert.
Folgende Aktionen stehen bereit:

- **Neue Regel anlegen (JSON)**: öffnet den Dialog **Neue
  Automationsregel** mit **Name**, **Auslöser** (z. B. „Spesenabrechnung
  eingereicht“), **Aktion** (z. B. „Spesen freigeben“), **Priorität**
  und **Bedingungen (JSON)**. Die Aktion muss zum Auslöser passen; eine
  leere Bedingung gilt immer. **Regel anlegen** speichert die Regel.
- **Deaktivieren**/**Aktivieren**: deaktivierte Regeln bleiben
  erhalten, lösen aber keine Aktionen mehr aus.
- Detailansicht (Klick auf den Namen): zeigt Auslöser, Bedingungen und
  Aktionen sowie das **Audit-Log (letzte 50)** mit **Zeitpunkt**,
  **Subjekt**, **Entscheidung** und **Log**.
- **Löschen**: entfernt die Regel dauerhaft.

Die **Priorität** steuert die Reihenfolge, wenn mehrere Regeln am
selben Auslöser hängen (niedrigerer Wert zuerst, Vorgabe 100). Nur die
erste passende Regel wird ausgeführt; eine Regel greift je Datensatz
höchstens einmal. Ungültiges JSON in den Bedingungen wird abgewiesen.

Berechtigung: Automatisierungen verwalten die Administratoren der
Organisation.

Hinweis: Für reine Benachrichtigungen sind die
**Benachrichtigungsregeln** oft die einfachere Wahl; für externe
Systeme siehe **Webhooks**.
