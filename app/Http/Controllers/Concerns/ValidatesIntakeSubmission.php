<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValidatesIntakeSubmission.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Enums\Customer\IntakeKind;
use App\Services\Customer\Intake\IntakeTemplates;
use App\Services\Fields\{FieldDocument, FieldSchema, FieldValidator, FieldValues};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};

/**
 * Einreichung eines Kundeneingangs (MVP-1074): gemeinsame Felder plus feste
 * Vorlage der Leistungsart, Pflicht nur für sichtbare Felder. Antwortet
 * dem Upload-Skript (XHR) mit JSON, sonst mit Redirect.
 */
trait ValidatesIntakeSubmission {
    /**
     * @return array{0: array{kind: IntakeKind, subject: string, description: ?string, desired_date: ?string, submission_key: string}, 1: FieldDocument}
     */
    private function validatedIntake(Request $request, IntakeKind $kind): array {
        $schema = app(IntakeTemplates::class)->schema($kind);
        $visible = $schema->visibleFor((array) $request->input('values', []));

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'desired_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'submission_key' => ['required', 'uuid'],
            ...app(FieldValidator::class)->rules($visible),
        ], [], $visible->attributeNames());

        return [[
            'kind' => $kind,
            'subject' => (string) $validated['subject'],
            'description' => $validated['description'] ?? null,
            'desired_date' => $validated['desired_date'] ?? null,
            'submission_key' => (string) $validated['submission_key'],
        ], new FieldDocument($schema, FieldValues::normalize($visible, (array) ($validated['values'] ?? [])))];
    }

    /** Antworten einer Katalogvorlage (`catalog[...]`) gegen ihre aktuell sichtbaren Felder. */
    private function validatedCatalogValues(Request $request, FieldSchema $schema): FieldValues {
        $visible = $schema->visibleFor((array) $request->input('catalog', []));
        $validated = $request->validate(app(FieldValidator::class)->rules($visible, 'catalog'), [], $visible->attributeNames('catalog'));

        return FieldValues::normalize($visible, (array) ($validated['catalog'] ?? []));
    }

    /** XHR (Upload-Skript) bekommt das Ziel als JSON, das Formular ohne JS einen Redirect. */
    private function intakeResponse(Request $request, string $url, string $status): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            session()->flash('status', $status);

            return response()->json(['redirect' => $url]);
        }

        return redirect()->to($url)->with('status', $status);
    }
}
