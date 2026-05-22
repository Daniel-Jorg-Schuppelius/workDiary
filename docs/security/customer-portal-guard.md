# Customer-Portal-Guard

Status: Aktiv • Issue: #56 • Relevante Feature-Doku: [019 — Rollen, Rechte, Produktprofile](../features/019-rollen-rechte-produktprofile.md)

Das Customer-Portal stellt Endkunden einer Organisation eine read-only
Selbstbedienungs-Oberfläche unter `/customer-portal` zur Verfügung
(Diary-Einträge, Zeitbuchungen, Rechnungen). Authentifizierung und
Autorisierung sind strikt vom internen `web`-Stack getrennt, damit ein
kompromittierter Portal-Account niemals Zugriff auf interne Routen oder
fremde Mandanten erhalten kann (OWASP A01 — Broken Access Control).

## Architektur-Überblick

```text
                  ┌────────────────────────────┐
  HTTP-Request -> │ routes/customer.php (web)  │
                  │   prefix /customer-portal  │
                  │   middleware auth:customer │
                  └─────────────┬──────────────┘
                                │
                  ┌─────────────▼──────────────┐
                  │ Guard `customer`            │
                  │  driver: session            │
                  │  provider: customers        │
                  └─────────────┬──────────────┘
                                │
                  ┌─────────────▼──────────────┐
                  │ CustomerUserProvider        │
                  │  filter: customer_id != null│
                  └─────────────┬──────────────┘
                                │
                  ┌─────────────▼──────────────┐
                  │ Controller (CustomerPortal) │
                  │  scope WHERE customer_id    │
                  │       = $user->customer_id  │
                  └────────────────────────────┘
```

Interner Stack:

```text
HTTP -> routes/web|legacy.php
     -> Guard `web`
     -> LegacyUserProvider (filter: customer_id IS NULL)
     -> Controller mit Policies (admin/teamleitung/…)
```

## Beidseitige Trennung der User-Provider

Beide Guards verwenden dasselbe Eloquent-Modell `App\Models\User`. Die
strikte Trennung wird auf Provider-Ebene erzwungen:

| Provider                                                             | Guard      | Filter                    | Konsequenz                                                               |
| -------------------------------------------------------------------- | ---------- | ------------------------- | ------------------------------------------------------------------------ |
| [`LegacyUserProvider`](../../app/Legacy/Auth/LegacyUserProvider.php) | `web`      | `customer_id IS NULL`     | Portal-Accounts können sich am internen Login nicht authentifizieren.    |
| [`CustomerUserProvider`](../../app/Auth/CustomerUserProvider.php)    | `customer` | `customer_id IS NOT NULL` | Interne Mitarbeiter-Accounts können sich am Portal-Login nicht anmelden. |

Beide Provider implementieren `retrieveById`, `retrieveByToken`,
`retrieveByCredentials` und `validateCredentials` mit dem jeweils inversen
Filter. `LegacyUserProvider::retrieveByCredentials` verwirft zusätzlich
einen Fallback-User, der nach der initialen Suche dennoch eine
`customer_id` trägt (defense in depth gegenüber Race-Conditions).

## Konfiguration & Registrierung

- [`config/auth.php`](../../config/auth.php) — Guard `customer` (session) +
  Provider `customers` (Driver `customer-eloquent`, Modell `User::class`).
- [`AppServiceProvider`](../../app/Providers/AppServiceProvider.php) —
  registriert den Driver `customer-eloquent` per `Auth::provider(...)`.
- [`bootstrap/app.php`](../../bootstrap/app.php) —
    - lädt `routes/customer.php` im `web`-Middleware-Stack **vor**
      `routes/legacy.php`, damit Custom-Route-Names eindeutig auflösen;
    - leitet Gäste auf Portal-Routen via `redirectGuestsTo`-Callback auf
      `route('customer.login')` statt auf den internen `/login`.

## Rollen & Permissions

Die Rolle `kunde` ist in
[`UserRole`](../../app/Enums/User/UserRole.php) definiert und wird beim
Seeden jeder Organisation (`PermissionsSeeder::seedOrganization()`) mit
den vier neuen Permissions belegt:

- `customerPortal.access`
- `customerPortal.diary.view`
- `customerPortal.timeEntry.view`
- `customerPortal.invoice.view`

Alle vier Permissions sind auf dem `web`-Guard registriert und in
[`PermissionGroup::CustomerPortal`](../../app/Enums/User/PermissionGroup.php)
gebündelt (Icon `support_agent`). Die `kunde`-Rolle wird Portal-Accounts
per [`UserFactory::kunde()`](../../database/factories/UserFactory.php) im
Test-Setup und produktiv über den Benutzer-Anlage-Workflow zugeordnet.

> **Hinweis zur Autorisierung im Controller:** Spatie wertet Rollen/
> Permissions team-scoped aus (`team_id` = Organisation). Die `auth:customer`-
> Middleware authentifiziert vor dem Setzen des Organisation-Kontexts; die
> Liste-Endpunkte verzichten daher auf eine zusätzliche `hasPermissionTo`-
> Prüfung und stützen sich auf:
>
> 1. Provider-Filter (`customer_id IS NOT NULL`),
> 2. Route-Middleware `auth:customer`,
> 3. expliziten `WHERE customer_id = $user->customer_id` in jedem Controller.

## Auth-Flow (Login)

1. GET `/customer-portal/login` → `LoginController::showLoginForm`,
   eigenes Layout (`resources/views/customer/layout.blade.php`).
2. POST `/customer-portal/login` → Validierung E-Mail/Passwort, dann
   `Auth::guard('customer')->attempt(...)`.
3. Nach erfolgreichem `attempt`: `isCustomer()`-Doppelprüfung
   (Defense in Depth); andernfalls Logout + Validation-Error.
4. Session-Regenerate, Redirect auf `route('customer.dashboard')`.

POST `/customer-portal/logout` invalidiert Session inkl. CSRF-Token und
leitet zurück auf den Portal-Login.

## Daten-Scoping

Jeder Listen-Controller im Portal scoped die Query explizit auf den
eigenen Kunden:

| Controller                                                                                 | Quelle                                                               |
| ------------------------------------------------------------------------------------------ | -------------------------------------------------------------------- |
| [`DashboardController`](../../app/Http/Controllers/CustomerPortal/DashboardController.php) | counts via `customer_id` bzw. `whereHas('project', customer_id)`     |
| [`DiaryController`](../../app/Http/Controllers/CustomerPortal/DiaryController.php)         | `DiaryEntry::where('customer_id', $user->customer_id)`               |
| [`TimeEntryController`](../../app/Http/Controllers/CustomerPortal/TimeEntryController.php) | `TimeEntry::whereHas('project', fn($q) => $q->where('customer_id'))` |
| [`InvoiceController`](../../app/Http/Controllers/CustomerPortal/InvoiceController.php)     | `Invoice::where('customer_id', $user->customer_id)`                  |

Zusätzlich greift die globale `OrganizationScope`-Erzwingung über
`BelongsToOrganization`-Modelle (Mandantentrennung auf DB-Ebene).

## Sicherheits-Argumente

- **OWASP A01 (Broken Access Control):** Beidseitige Provider-Filter +
  expliziter `customer_id`-Scope in jedem Endpoint verhindern, dass ein
  Portal-Account Daten anderer Kunden oder interne Routen erreicht.
- **OWASP A07 (Identification & Authentication):** Eigenständiger Guard,
  eigene Session, eigener Login-Flow; keine geteilten Cookies/Routes mit
  dem internen Stack.
- **Defense in Depth:** Doppelprüfung `isCustomer()` im Login, Provider-
  Filter sowohl bei `retrieveByCredentials` als auch bei `retrieveById`
  (verhindert Session-Hijacking durch nachträgliches Setzen der
  `customer_id`), und CSRF-Token-Regeneration bei Login/Logout.
- **Audit-Trail:** Login/Logout-Events laufen über das Standard-Laravel-
  Event-System und werden vom bestehenden `AuditLog`-Listener erfasst.

## Tests

[`tests/Feature/CustomerPortal/CustomerPortalAccessTest.php`](../../tests/Feature/CustomerPortal/CustomerPortalAccessTest.php)
deckt folgende Szenarien ab:

- Login eines Portal-Accounts über `customer`-Guard (positiv),
- Login eines Portal-Accounts über internen Guard (negativ),
- Login eines internen Accounts über Portal-Guard (negativ),
- Gast-Redirect auf `customer.login` für geschützte Portal-Routen,
- Dashboard rendert nur eigene Statistiken,
- Diary-Liste nur eigene Einträge,
- Portal-User darf keine interne Route erreichen,
- Logout invalidiert die Session,
- Rechnungsliste nur eigene Rechnungen.
