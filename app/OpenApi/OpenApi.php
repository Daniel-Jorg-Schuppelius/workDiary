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

#[OA\Info(
    version: '1.0.0',
    title: 'workDiary REST API',
    description: 'REST-API für workDiary. Authentifizierung via Sanctum Bearer Token.',
    contact: new OA\Contact(name: 'workDiary Support', email: 'support@example.org'),
    license: new OA\License(name: 'AGPL-3.0-or-later', url: 'https://www.gnu.org/licenses/agpl-3.0.html'),
)]
#[OA\Server(url: '/api', description: 'Aktuelle Instanz')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'sanctum',
    description: 'Sanctum Personal Access Token. Erstellung unter /profile/api-tokens.',
)]
#[OA\Tag(name: 'Customers', description: 'Kundenverwaltung')]
#[OA\Tag(name: 'Projects', description: 'Projektverwaltung')]
#[OA\Tag(name: 'Tasks', description: 'Tasks (Activities) je Projekt')]
class OpenApi {
}
