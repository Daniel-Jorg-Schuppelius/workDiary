# Offline-Sync und Konfliktlösung — Architekturkonzept

Status: Konzept (2026-06-10) • Quelle:
[Feature 035 — Offline-Sync und Konfliktlösung](features/035-offline-sync-konfliktloesung.md).
• Verbunden mit:
[Feature 004 — Mobiler Field-Workflow](features/004-mobiler-field-workflow.md).

## 1. Warum nur Konzept, noch keine Umsetzung

Offline-Sync ist kein weiteres CRUD-Modul, sondern ein Architektur-Schnitt
durch JS-Stack, API und Datenmodell. Zwei bewusste Abhängigkeiten blockieren
den Start:

1. **CSP-/Alpine-Umbau ist zurückgestellt** (siehe Memory/Projekthistorie:
   Alpine ist CSP-konform refactored, `app.js` nutzt aber noch den
   Standard-Build, weil Dialoge mit dem CSP-Build zerfließen). Die
   Offline-Queue braucht zusätzliches Client-JS (IndexedDB, Sync-Logik) —
   das sollte einmal sauber auf dem Ziel-Build aufsetzen, nicht zweimal
   gebaut werden.
2. **Idempotente Schreib-API**: Die heutigen Web-Formulare (POST + Redirect
   + Session-Flash) sind nicht wiederholbar. Offline-Sync braucht
   idempotente Endpunkte mit Client-UUIDs — sinnvollerweise auf Basis der
   bestehenden Sanctum-API (Feature 008), nicht der Blade-Routen.

## 2. Ist-Stand (bereits vorhanden)

- PWA-Hülle: `public/manifest.webmanifest`, Service Worker `public/sw.js`
  (Push-Notifications, **kein** Fetch-Handler), `public/offline.html`,
  Install-Prompt in `resources/js/pwa.js`.
- Push-Infrastruktur (`PushSubscription`, `WebPushService`) — seit
  Feature 018 auch als Benachrichtigungskanal.
- Sanctum-API für Kernressourcen (Diary, TimeEntries, Attachments …).

## 3. Zielarchitektur (MVP-Schnitt)

### 3.1 Outbox-Queue im Client

- **IndexedDB-Outbox** (`outbox`-Store): jede offline ausgeführte Aktion
  wird als Befehl gespeichert: `{client_uuid, endpoint, method, payload,
  base_version, created_at, status}`.
- Scope MVP bewusst klein: **Zeiterfassung (TimeEntry/Attendance-Stempel),
  Kommentare, Formular-Submissions (Feature 032)** — also append-artige
  Daten mit geringem Konfliktpotenzial. KEINE Offline-Bearbeitung von
  Stammdaten, Protokoll-Signaturen oder Statusmaschinen.
- Service Worker bekommt einen Fetch-Handler nur für GET-Navigation
  (App-Shell-Cache + `offline.html`-Fallback); Schreibzugriffe laufen
  nicht durch den SW, sondern explizit durch die Outbox (bessere
  Testbarkeit, kein „magisches" Request-Replay).

### 3.2 Idempotenter Sync-Endpunkt

- `POST /api/sync/commands` (Sanctum): nimmt ein Batch von
  Outbox-Befehlen entgegen. Pro Befehl:
  - `client_uuid` ist Idempotenzschlüssel — serverseitige Tabelle
    `sync_commands` (organization_id, user_id, client_uuid unique,
    result_status, result_ref) verhindert Doppelausführung bei
    Wiederholung nach Verbindungsabbruch.
  - Ausführung über die **bestehenden Services** (TimeEntry-,
    Comment-, FormService) — keine zweite Geschäftslogik.
- Antwort je Befehl: `applied | duplicate | conflict | rejected`
  (+ Validierungsfehler), Client räumt die Outbox entsprechend.

### 3.3 Konflikterkennung

- Jeder offline veränderbare Datensatz trägt eine `lock_version`
  (Integer, optimistic locking) ODER es wird `updated_at` als
  Versionsstempel mitgesendet (`base_version` im Befehl). MVP:
  `updated_at`-Vergleich, da kein Migrationsbedarf auf Bestandstabellen.
- Konfliktregel MVP: **Server gewinnt nie stillschweigend** — bei
  `base_version`-Mismatch wird der Befehl als `conflict` zurückgegeben
  und im Client in einen `conflicts`-Store verschoben.
- Konflikt-UI: Liste „Nicht übernommene Änderungen" (eigene Seite oder
  Karte im Profil): lokale Fassung vs. Server-Fassung, Aktionen
  „Server-Stand übernehmen" / „Meine Fassung erneut anwenden" (erzeugt
  neuen Befehl auf aktueller Basis). Kein automatischer Merge im MVP.
- Append-only-Daten (neue Zeitbuchung, neuer Kommentar, neue
  Formular-Submission) sind konfliktfrei per Definition — deshalb der
  MVP-Scope aus §3.1.

### 3.4 Schutzregeln

- Abgenommene/signierte Protokolle, Monatsabschlüsse und audit-relevante
  Objekte sind offline **read-only** (Server lehnt Befehle dagegen mit
  `rejected` ab; Client blendet Aktionen aus, wenn `navigator.onLine`
  false und Objekt schutzklassifiziert).
- Sync-Versuche und Konfliktauflösungen werden auditiert
  (`sync.applied`, `sync.conflict.resolved` in der bestehenden
  Audit-Kette).
- Outbox-Inhalte liegen unverschlüsselt in IndexedDB des Geräts —
  Gerätebindung/Logout muss die Outbox leeren (`storage.clear` beim
  Token-Invalidieren); vertrauliche Notizen (confidential) sind von der
  Offline-Erfassung ausgeschlossen.

### 3.5 Sync-Status im UI

- Statusindikator in der App-Leiste: „online / offline / n Änderungen
  ausstehend / Konflikte". Pro Datensatz reicht im MVP der
  Outbox-Status (ausstehend-Badge in der jeweiligen Liste).

## 4. Umsetzungsphasen

| Phase | Inhalt | Voraussetzung |
| ----- | ------ | ------------- |
| 0 | CSP-/Alpine-Build-Entscheidung abschließen | offen (zurückgestellt) |
| 1 | `sync_commands`-Tabelle + idempotenter Batch-Endpunkt + Tests | Sanctum-API |
| 2 | IndexedDB-Outbox + Sync-Manager (Zeitstempel, Kommentare) | Phase 1 |
| 3 | Formular-Submissions offline + Konflikt-UI | Phase 2, Feature 032 |
| 4 | App-Shell-Caching (SW-Fetch-Handler) + Statusindikator | Phase 2 |

## 5. Bewusst out of scope

- Offline-Bearbeitung von Stammdaten und Statusmaschinen.
- Automatischer Feld-Merge (CRDT o. ä.).
- Hintergrund-Sync über `Background Sync API` (Browser-Support fragmentiert;
  MVP synct beim App-Fokus/Online-Event).
- Offline-Fotoupload großer Mengen (Speicher-Quota; Folgeausbau mit
  Chunk-Upload).
