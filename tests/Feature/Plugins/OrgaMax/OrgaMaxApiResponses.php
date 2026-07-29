<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxApiResponses.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\OrgaMax;

use GuzzleHttp\Psr7\Response as Psr7Response;
use Tests\Support\FakePluginHttp;

/**
 * Antwort-Envelopes der orgaMAX-API für die Plugin-Tests: Listen kommen als
 * `{"meta": {...}, "data": [...]}`, Einzelobjekte als `{"meta": {}, "data": {...}}`
 * (siehe SDK-Notes). Die Feldnamen entsprechen der OpenAPI-Spec.
 */
trait OrgaMaxApiResponses {
    /** @param  list<array<string, mixed>>  $rows */
    private static function listResponse(array $rows, int $status = 200): Psr7Response {
        return FakePluginHttp::response([
            'meta' => ['count' => count($rows), 'totalCount' => count($rows)],
            'data' => $rows,
        ], $status);
    }

    /** @param  array<string, mixed>  $data */
    private static function itemResponse(array $data, int $status = 200): Psr7Response {
        return FakePluginHttp::response(['meta' => [], 'data' => $data], $status);
    }
}
