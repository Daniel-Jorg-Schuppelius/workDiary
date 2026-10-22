---
title: "Automatisierungen"
topic: admin.automations
version: 4
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
  eingereicht“ oder „Rechnungseingang eingegangen“), **Aktion** (z. B. „Spesen freigeben“), **Priorität**
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

Aktionen mit Ziel zeigen im Dialog zusätzlich das Feld **Ziel**: **In Kurs einschreiben** (Auslöser **Mitarbeitende Person angelegt** oder **Gerät ausgegeben**) fragt nach dem Kurs. Bedingungen sehen bei neuen Mitarbeitenden `role`, `roles` und `personnel_number`, bei der Geräteausgabe unter anderem `category_code`, `asset_class` und `manufacturer`. Die Rolle steht erst fest, wenn die Anlage gespeichert ist — deshalb prüft die Regel nach dem Speichern.

## Posteingangsregeln (Rechnungseingang)

Der Auslöser „Rechnungseingang eingegangen“ prüft jeden neuen Eingang des
Rechnungspostfachs. Für Bedingungen stehen neben den Spalten des Eingangs
diese Felder bereit: `seller_name_key`, `buyer_name_key`, `party_name`, `party_name_key`, `sender_domain`, `mail_subject`, `attachment_names`, `file_name`, `country`, `kind_hint`. Namensschlüssel sind kleingeschrieben und ohne
Rechtsform, damit „Muster GmbH“ und „MUSTER“ gleich zählen; `kind_hint` nennt
die erkannte Belegart (etwa `reminder` für eine Mahnung). Operatoren: `=`,
`!=`, `<`, `<=`, `>`, `>=`, `in`, `not_in`, `contains`, `not_contains`,
`starts_with`, `ends_with`, `matches`. Beispiel:
`{"all": [{"field": "sender_domain", "op": "=", "value": "beispiel.de"}]}`.
Mögliche Aktionen: „Rechnungseingang als keine Rechnung ablegen“,
„Rechnungseingang zuordnen“ und „Rechnungseingang verwerfen“. Die Regelseite
des Rechnungseingangs zeigt diese Regeln für die Buchhaltung; dort lassen sie
sich schalten und löschen, beides steht im Audit-Log.
