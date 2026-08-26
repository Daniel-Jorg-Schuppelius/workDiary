<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenApi.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/*
 * Die Konstante L5_SWAGGER_APP_VERSION definiert l5-swagger beim Generieren
 * aus config('l5-swagger.defaults.constants') (= APP_VERSION, MVP-717);
 * für die statische Analyse liefert stubs/l5-swagger.php das Symbol.
 */
#[OA\Info(
    version: L5_SWAGGER_APP_VERSION,
    title: 'workDiary REST API',
    description: 'REST-API für workDiary. Authentifizierung via Sanctum Bearer Token. '
        . 'Kanonische Basis-URL ist /api/v1; die unversionierten Pfade unter /api bleiben als Kompatibilitäts-Alias '
        . 'erreichbar und tragen die Header Deprecation: true und Sunset (Abschaltdatum). '
        . 'IDs sind Sqids (kurze alphanumerische Kennungen), Token-Scopes stehen je Operation im security-Block.',
    contact: new OA\Contact(name: 'workDiary Support', email: 'support@example.org'),
    license: new OA\License(name: 'AGPL-3.0-or-later', url: 'https://www.gnu.org/licenses/agpl-3.0.html'),
)]
#[OA\Server(url: '/api/v1', description: 'Aktuelle Instanz (kanonisch, versioniert)')]
#[OA\Server(url: '/api', description: 'Unversionierter Kompatibilitäts-Alias (Deprecation/Sunset-Header)')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'sanctum',
    description: 'Sanctum Personal Access Token. Erstellung unter /profile/api-tokens.',
)]
#[OA\Tag(name: 'Me', description: 'Eigenes Benutzerprofil')]
#[OA\Tag(name: 'Customers', description: 'Kundenverwaltung')]
#[OA\Tag(name: 'Projects', description: 'Projektverwaltung')]
#[OA\Tag(name: 'Tasks', description: 'Tasks (Activities) je Projekt')]
#[OA\Tag(name: 'Diary', description: 'Aufträge/Einsätze (Diary)')]
#[OA\Tag(name: 'Comments', description: 'Kommentare an Aufträgen')]
#[OA\Tag(name: 'Attachments', description: 'Anhänge (Upload/Download)')]
#[OA\Tag(name: 'Tags', description: 'Tags')]
#[OA\Tag(name: 'Shifts', description: 'Bereitschaften')]
#[OA\Tag(name: 'Assignments', description: 'Notfall-Einsätze')]
#[OA\Tag(name: 'Dashboard', description: 'Dashboard-Kennzahlen')]
#[OA\Tag(name: 'Assets', description: 'Assets (Timeline, Status-Sichtbarkeit)')]
#[OA\Tag(name: 'Push', description: 'Web-Push-Abonnements')]
#[OA\Tag(name: 'Timesheets', description: 'Stundenzettel und Zeiteinträge')]
#[OA\Tag(name: 'Materials', description: 'Materialkatalog und Materialverbrauch')]
#[OA\Tag(name: 'Stopwatch', description: 'Stoppuhr')]
#[OA\Tag(name: 'Attendance', description: 'Anwesenheit (Stempeln)')]
#[OA\Tag(name: 'Flex', description: 'Arbeitszeitkonto')]
#[OA\Tag(name: 'Location', description: 'Standort-Stempel und -Ingest')]
#[OA\Tag(name: 'Tickets', description: 'Ticketeingang')]
#[OA\Tag(name: 'Absences', description: 'Abwesenheiten (Urlaub, Krankmeldung)')]
#[OA\Tag(name: 'Expenses', description: 'Spesen')]
#[OA\Tag(name: 'Invoices', description: 'Rechnungen (read-only, PDF)')]
#[OA\Tag(name: 'Hooks', description: 'REST-Hooks für n8n/Make/Zapier')]
#[OA\Tag(name: 'Articles', description: 'Artikel und Varianten (read-only, MVP-718)')]
#[OA\Tag(name: 'Inventory', description: 'Bestände je Lager/Variante/Lagerplatz (read-only, MVP-718)')]
#[OA\Tag(name: 'Purchase Orders', description: 'Bestellungen (read-only, MVP-718)')]
#[OA\Tag(name: 'Suppliers', description: 'Lieferanten (read-only, MVP-718)')]
#[OA\Tag(name: 'Protocols', description: 'Protokolle (read-only, MVP-718)')]
#[OA\Tag(name: 'Vehicles', description: 'Fahrzeuge (read-only, MVP-718)')]
#[OA\Tag(name: 'Ingest', description: 'Geräte-Ingest (Token im Pfad, ohne Sanctum, unversioniert)')]
class OpenApi {}
