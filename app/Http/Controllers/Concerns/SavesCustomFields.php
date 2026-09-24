<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SavesCustomFields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Services\Fields\CustomFieldService;
use Illuminate\Http\Request;

/**
 * Benutzerdefinierte Felder eines Trägers aus dem Formular übernehmen
 * (MVP-868): `custom[<key>]` gegen das Schema der Organisation validieren,
 * nach dem Speichern des Trägers mit `syncCustomFields()` schreiben.
 */
trait SavesCustomFields {
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $subjectClass
     * @return array<string, mixed>
     */
    protected function validatedCustomFields(Request $request, string $subjectClass): array {
        $organizationId = (int) ($request->user()?->getAttribute('organization_id') ?? 0);
        $schema = app(CustomFieldService::class)->schemaFor($subjectClass, $organizationId);
        if ($schema->isEmpty()) {
            return [];
        }
        $validated = $request->validate(app(CustomFieldService::class)->rules($schema), [], $schema->attributeNames('custom'));

        return (array) ($validated['custom'] ?? []);
    }
}
