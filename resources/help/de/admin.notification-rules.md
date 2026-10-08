---
title: "Benachrichtigungsregeln"
topic: admin.notification-rules
version: 3
keywords:
    - Eskalation
    - Benachrichtigung einstellen
    - E-Mail-Benachrichtigung
    - Push-Benachrichtigung
    - Empfänger festlegen
    - Erinnerung
    - Fristenüberwachung
    - überfällig
    - Alarmierung
    - Benachrichtigungskanäle
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
related:
    - admin.handbook
    - communication.notes
    - glossary.core
---

Benachrichtigungsregeln legen pro Ereignistyp fest, **wer** auf
**welchen Kanälen** informiert wird – und wann eskaliert wird. Die
Liste zeigt je **Ereignis** die Spalten **Aktiv**, **Kanäle**,
**Empfänger** und **Eskalation**; Ereignisse ohne eigene Regel tragen
den Hinweis **Standard (noch nicht angepasst)**.

Typischer Ablauf:

1. Beim Ereignis **Bearbeiten** wählen (z. B. offener Punkt
   zugewiesen/bald fällig/überfällig, Folgeaktion fällig, Dokument
   läuft ab, Korrekturantrag, Monatsfreigabe eingereicht,
   ISMS-Zertifikat läuft ab, Korrekturmaßnahme überfällig,
   Risiko-Review fällig). Es öffnet sich **Benachrichtigungsregel
   bearbeiten**.
2. Unter **Aktiv** den Schalter **Benachrichtigungen für dieses
   Ereignis aktiv** setzen und die **Kanäle** wählen: **In-App**,
   **E-Mail**, **Push**, **Microsoft Teams**, **Mattermost** oder
   **Kalender**. Bei kritischen Ereignissen (z. B. **Krisenalarm**,
   **Notdienst zugewiesen**, **Kritisches Sicherheitsereignis**) steht
   zusätzlich **SMS** bereit.
3. **Empfänger** festlegen: **Betroffene Person benachrichtigen** (z. B.
   zugewiesene oder antragstellende Person), **Empfänger-Rollen** (z. B.
   Teamleitung) und **Zusätzliche feste Empfänger**.
4. Bei Überfälligkeits-Ereignissen optional **Eskalation**: **Eskalation
   aktiv** einschalten; nach **Eskalieren nach (Stunden)** (1–720) wird
   zusätzlich die **Eskalationsrolle** benachrichtigt. **Eskalationsstufe
   2** und **Eskalationsstufe 3** benachrichtigen jeweils nach weiteren
   Stunden eigene Rollen und feste Empfänger.

Wichtig zu wissen:

- Ohne eigene Regel greift der angezeigte Standard des Ereignisses
  (Kanäle, Betroffenen-Flag, Rollen) – Sie müssen nur abweichende Fälle
  konfigurieren.
- **Microsoft Teams** und **Mattermost** senden an den hinterlegten
  Chat-Kanal der Organisation; **Kalender** trägt terminbezogene
  Ereignisse in die verbundenen Kalender der Organisation ein
  (CalDAV/Microsoft 365/Google). **SMS** erreicht nur Personen mit
  bestätigter Mobilnummer und kostet je Nachricht.
- Eskalation gibt es nur für Überfälligkeits-/Ablauf-Ereignisse.
- Einige Ereignisse werden sofort ausgelöst (z. B. Zuweisung), andere
  vom Fristen-Scanner gefunden (z. B. „bald fällig“).

Berechtigung: Die Liste sehen Personen mit dem Recht
**Benachrichtigungsregeln sehen**; ändern dürfen nur Personen mit
**Benachrichtigungsregeln bearbeiten**.
