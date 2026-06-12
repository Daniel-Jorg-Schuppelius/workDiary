---
title: "Benachrichtigungsregeln"
topic: admin.notification-rules
version: 1
audience:
    - admin
    - teamleitung
related:
    - admin.handbook
    - communication.notes
    - glossary.core
---

Benachrichtigungsregeln legen pro Ereignistyp fest, **wer** auf
**welchen Kanälen** informiert wird – und wann eskaliert wird.

Typischer Ablauf:

1. Ereignis in der Liste öffnen (z. B. offener Punkt zugewiesen/bald
   fällig/überfällig, Folgeaktion fällig, Dokument läuft ab,
   Korrekturantrag, Monatsfreigabe eingereicht, ISMS-Zertifikat läuft
   ab, Korrekturmaßnahme überfällig, Risiko-Review fällig).
2. **Kanäle** wählen: „In-App", „E-Mail", „Push".
3. **Empfänger** festlegen: betroffene Person ja/nein, Empfänger-Rollen
   (z. B. Teamleitung) und feste Zusatz-Empfänger.
4. Bei Überfälligkeits-Ereignissen optional **Eskalation**: nach 1–720
   Stunden wird zusätzlich die Eskalationsrolle benachrichtigt.

Wichtig zu wissen:

- Ohne eigene Regel greifen die **Code-Defaults** des Ereignisses
  (Kanäle, Betroffenen-Flag, Rollen) – du musst nur abweichende Fälle
  konfigurieren.
- Eskalation gibt es nur für Überfälligkeits-/Ablauf-Ereignisse.
- Einige Ereignisse werden sofort ausgelöst (z. B. Zuweisung), andere
  vom Fristen-Scanner gefunden (z. B. „bald fällig").

Voraussetzungen: Bearbeitung ist Admins der Organisation vorbehalten.
