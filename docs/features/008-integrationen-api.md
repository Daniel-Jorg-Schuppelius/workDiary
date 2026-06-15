# Integrationen und offene API

## Status

In Progress — Webhook-System (ausgehende, signierte Event-Benachrichtigungen)
implementiert. REST-API für Kernobjekte besteht; offen: Microsoft-365- und
Google-Kalender-Anbindung (OAuth) als separater Pilot, API-Token-Scopes.

## Webhooks (umgesetzt)

WorkDiary kann fachliche Domänen-Ereignisse als ausgehende Webhooks an externe
Systeme zustellen. Kernpunkte:

- **Ereignisquelle**: keine parallele Event-Liste — die kuratierte Enum
  `App\Enums\Integration\WebhookEvent` (8 stabile Ereignisse) bindet je Case
  über `source()` genau einen real verdrahteten `NotificationEvent`. Die
  Auslösung hängt additiv im zentralen `NotificationDispatcher::notify()` und
  damit an denselben Stellen (Service-Trigger + Fristen-Scanner), die heute
  schon Benachrichtigungen feuern — ohne Umbau der Geschäftslogik.
- **Datenmodell**: `webhook_endpoints` (Secret = HMAC-Signing-Key,
  verschlüsselt at-rest, `$hidden`; abonnierte Events als JSON; Auto-Disable
  nach N Fehlern) und `webhook_deliveries` (Zustellprotokoll je Versuch).
- **Versand**: `WebhookDispatchService` + `WebhookDeliveryJob` (Queue) mit
  HMAC-SHA256-Signatur über `<timestamp>.<body>` (Replay-Schutz), kurzem
  Timeout, Retry mit Backoff und automatischer Endpunkt-Deaktivierung.
- **Verwaltung**: Admin-UI unter `admin/webhooks` (CRUD als Modal,
  Secret-Einmal-Anzeige/-Rotation, Zustellprotokoll, Test-Event). Permissions
  `webhook.viewAny`/`webhook.manage`, Policy mit Org-Bindung.
- **Sicherheit**: Secret nie im Klartext in Responses/Logs/JSON; nur einmal
  bei Anlage/Rotation angezeigt.

## Ziel

WorkDiary soll sich in bestehende Betriebsabläufe einfügen: Buchhaltung,
Kalender, Kommunikation, Lohnabrechnung, Projektabrechnung und eigene Tools.

## Warum

Integrationen senken Wechselhürden. Für viele Betriebe ist entscheidend, ob ein
System mit DATEV, Lexware, Lexoffice, Microsoft 365, Google Calendar oder
internen Schnittstellen zusammenarbeitet.

## MVP

- Dokumentierte REST-API für Kernobjekte: Zeiten, Projekte, Kunden, Schichten,
  Abwesenheiten, Spesen und Rechnungen.
- Webhooks für wichtige Ereignisse.
- API-Token mit scopes.
- Microsoft-365- und Google-Kalender-Anbindung als Ergänzung zu ICS.
- Erweiterung des Plugin-Systems für Buchhaltungs- und Exportadapter.

## Akzeptanzkriterien

- API-Zugriffe sind rollen- und organisationssicher.
- Webhooks sind signiert und wiederholbar.
- Exporte laufen nachvollziehbar und mit Fehlerprotokoll.
- Integrationen sind modular, nicht fest in Controller eingebaut.

## Abhängigkeiten

- API-Token
- Plugin-System
- Kalenderfeeds
- Lexoffice-Plugin
- Rollen/Rechte
- Audit

## GitHub Issues

- TBD
