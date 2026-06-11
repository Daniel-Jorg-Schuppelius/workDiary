# Benachrichtigungen und Eskalationen

## Status

Done — MVP umgesetzt (2026-06-10): Notification-Center (In-App), Regelwerk je Organisation und Ereignistyp, Kanäle In-App/Mail/Push, Fristen-Scanner (notifications:scan-deadlines) mit einstufiger Eskalation, Benutzer-Präferenzen mit Ruhezeit.

## Ziel

WorkDiary soll relevante Ereignisse aktiv melden: vergessene Stempelung,
gefährdete SLA, offene Abnahmen, unvollständige Protokolle, Schichttausch,
Wartung fällig, Lizenz läuft ab, Backup fehlgeschlagen, Freigabe wartet.

## Warum

Ein Nachweissystem darf nicht nur rückblickend dokumentieren. Es muss rechtzeitig
auf offene Risiken, Fristen und fehlende Daten hinweisen, damit Arbeit sauber
abgeschlossen und abgesichert werden kann.

## MVP

- Benachrichtigungsregeln pro Ereignistyp.
- Kanäle: In-App, E-Mail, Push, Kalender, Webhook.
- Eskalationsstufen und Empfängergruppen.
- Ruhezeiten und persönliche Einstellungen.
- Auditierbare Zustellung für kritische Ereignisse.

## Akzeptanzkriterien

- Kritische Fristen und fehlende Nachweise werden rechtzeitig gemeldet.
- Nutzer werden nicht mit irrelevanten Meldungen überlastet.
- Admins können sehen, ob eine kritische Benachrichtigung erzeugt wurde.
- SaaS- und On-Premise-Installationen können Kanäle unterschiedlich
  konfigurieren.

## Abhängigkeiten

- Automationen
- PWA und Push
- SLA, Verträge und Service-Level
- Dokumentation und Abnahmeprotokolle
- Backup und Restore

## GitHub Issues

- TBD
