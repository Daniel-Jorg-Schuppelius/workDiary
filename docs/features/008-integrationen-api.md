# Integrationen und offene API

## Status

In Progress — Webhook-System (ausgehende, signierte Event-Benachrichtigungen)
implementiert. REST-API für Kernobjekte besteht. Das Feature ist zugleich das
verbindliche Architekturprinzip für WorkDiary als Bindeglied zwischen
Fachsystemen. Offen: zentrale Datenführerschaft-/Capability-Konfiguration,
Microsoft-365- und Google-Kalender-Anbindung (OAuth) als separater Pilot sowie
API-Token-Scopes.

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

WorkDiary soll die operative Arbeit zwischen bestehenden Fachsystemen
verbinden: Auftrag, Ausführung, Zeit, Material, Prozedur, Nachweis und Freigabe
werden in WorkDiary zusammengeführt und kontrolliert an Buchhaltung,
Fakturierung, Warenwirtschaft, Kalender, Kommunikation und eigene Tools
übergeben.

WorkDiary ersetzt diese Fachsysteme nicht. Es stellt sicher, dass aus real
ausgeführter Arbeit konsistente, nachvollziehbare Daten für das jeweils
zuständige Zielsystem entstehen.

## Warum

Integrationen senken Wechselhürden. Für viele Betriebe ist entscheidend, ob ein
System mit DATEV, Lexware, Lexoffice, Microsoft 365, Google Calendar oder
internen Schnittstellen zusammenarbeitet. Ohne klare Datenführerschaft drohen
jedoch doppelte Rechnungen, widersprüchliche Kunden- oder Artikelstammdaten und
abweichende Lagerbestände.

## Produktprinzip: WorkDiary als Integrationsdrehscheibe

```text
Mitarbeitende und Arbeitsabläufe
                ↓
            WorkDiary
 Auftrag · Zeit · Material · Nachweis
    ↙          ↓          ↓          ↘
Lexoffice   JTL-Wawi    DATEV      Kalender/Tools
Faktura     Warenlager  Fibu/Lohn  Planung/Kommunikation
```

Der WorkDiary-Kern bleibt zuständig für:

- operative Aufträge und deren Ausführung
- Zeit-, Material- und Dienstmittelnachweise
- Prozeduren, Prüfungen, Abweichungen und Freigaben
- Audit, Übergabenachweise und Fehlerstatus

Plugins und Adapter verbinden diese Daten mit Fachsystemen. Externe Systeme
bleiben dort führend, wo sie fachlich zuständig sind.

## Führendes System je Datenbereich

Pro Organisation wird die Datenführerschaft getrennt nach Datenbereich
festgelegt:

| Datenbereich | Mögliche Führerschaft |
| --- | --- |
| Auftrag und operative Ausführung | WorkDiary |
| Arbeitszeit und Nachweise | WorkDiary |
| Kunden/Lieferanten | WorkDiary, Lexoffice oder anderes CRM/ERP |
| Artikel, Leistungen und Preise | WorkDiary, Lexoffice oder Warenwirtschaft |
| Lagerbestand und Reservierungen | WorkDiary oder Warenwirtschaft |
| Rechnungen und Gutschriften | WorkDiary, Lexoffice oder DATEV Faktura |
| Finanzbuchhaltung | DATEV oder anderes Buchhaltungssystem |
| Lohnabrechnung | DATEV LODAS/Lohn und Gehalt oder anderes Lohnsystem |
| Termine und Kalender | WorkDiary oder externer Kalender |

Je Datenbereich darf genau ein System schreibend führen. Andere Systeme
erhalten lokale Spiegel, Übergabedaten oder read-only Ansichten. Ein
bidirektionaler Abgleich ist nur zulässig, wenn Konfliktregeln, Feldhoheit und
Idempotenz ausdrücklich definiert sind.

Beispiel:

- Lexoffice führt Artikel, Kunden und Rechnungen.
- WorkDiary führt Arbeitsaufträge, Zeiten, Nachweise und den lokalen
  Lagerbestand.
- DATEV erhält Buchungs- oder Lohndaten.

Lexoffice ist dabei kein Lagerprovider. Wird keine Warenwirtschaft eingesetzt,
übernimmt der lokale Lagerkern aus
[Lagerwirtschaft und Bestandsintegration](./048-lagerwirtschaft-bestandsintegration.md)
die Bestandsführung.

Unabhängig von der Stammdatenführerschaft besitzt WorkDiary einen kanonischen
internen Artikelbezug. Externe Artikel aus Lexoffice, JTL-Wawi oder anderen
Systemen werden über stabile Zuordnungen angebunden; sie erzeugen keine
parallelen fachlichen Artikelstämme.

SKU- und GTIN-Hoheit werden je Organisation getrennt konfiguriert. Änderungen
oder Löschungen externer Varianten überschreiben keine lokal bereits
referenzierten Artikel; sie erzeugen einen sichtbaren Konflikt. Lieferung,
Bestandsbuchung und Fakturaübergabe bleiben getrennte, idempotente Vorgänge.

## Integrationsvertrag

Jede Integration deklariert:

- unterstützte Datenbereiche und Fähigkeiten
- Lese-, Schreib- oder read-only-Zugriff
- führendes System und Feldhoheit
- externe IDs und interne Zuordnungen
- Idempotenz- und Dublettenschutz
- Konfliktstrategie
- Webhook-, Polling- oder Datei-Transport
- Retry-, Fehler- und Wiederanlaufverhalten
- Audit- und Übergabenachweis

Die Oberfläche und die Service-Schicht dürfen nur Fähigkeiten anbieten, die
der aktive Adapter tatsächlich unterstützt.

## MVP

- Dokumentierte REST-API für Kernobjekte: Zeiten, Projekte, Kunden, Schichten,
  Abwesenheiten, Spesen und Rechnungen.
- Webhooks für wichtige Ereignisse.
- API-Token mit scopes.
- Microsoft-365- und Google-Kalender-Anbindung als Ergänzung zu ICS.
- Erweiterung des Plugin-Systems für Buchhaltungs- und Exportadapter.
- Organisationsbezogene Datenführerschaft je Datenbereich.
- Capability-Verträge für Faktura-, Artikel-, Kalender- und Lagerprovider.
- Zentrale Sync-/Konfliktübersicht mit Wiederholungsmöglichkeit.

## Akzeptanzkriterien

- API-Zugriffe sind rollen- und organisationssicher.
- Webhooks sind signiert und wiederholbar.
- Exporte laufen nachvollziehbar und mit Fehlerprotokoll.
- Integrationen sind modular, nicht fest in Controller eingebaut.
- Je Datenbereich ist genau ein schreibend führendes System konfiguriert.
- Wiederholte Übertragungen erzeugen keine doppelten Fachvorgänge.
- Nicht unterstützte Adapter-Fähigkeiten werden technisch blockiert.

## Abhängigkeiten

- API-Token
- Plugin-System
- Kalenderfeeds
- Lexoffice-Plugin
- Lagerwirtschaft und Bestandsintegration
- Rollen/Rechte
- Audit

## GitHub Issues

- TBD
