<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierQuestionnaireService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Supplier;

use App\Enums\Fields\FieldType;
use App\Enums\Supplier\SupplierQuestionnaireStatus;
use App\Mail\SupplierQuestionnaireMail;
use App\Models\Platform\{Organization, User};
use App\Models\Supplier\{Supplier, SupplierQuestionnaire, SupplierQuestionnaireRequest};
use App\Services\Concerns\AssertsValidatedTransition;
use App\Services\Fields\{FieldExtensionRegistry, FieldSchema, FieldValidator, FieldValues};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Facades\{DB, Mail, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Lieferanten-Selbstauskunft (MVP-937): Fragebogen über das Feldschema,
 * Anfrage mit Einmal-Link (nur als Hash gespeichert), Antworten als
 * Feldwerte, Prüfung mit Gültigkeit. Anhänge und Unterschriften sind im
 * Fragebogen nicht vorgesehen.
 */
final class SupplierQuestionnaireService {
    use AssertsValidatedTransition;

    public const LINK_DAYS = 30;

    public function __construct(
        private readonly FieldValidator $validator,
        private readonly FieldExtensionRegistry $extensions,
    ) {}

    /** @param array{name: string, description?: ?string, validity_months: int, is_active?: bool, fields: array<int|string, mixed>} $data */
    public function save(Organization $organization, ?SupplierQuestionnaire $questionnaire, array $data, User $actor): SupplierQuestionnaire {
        $schema = FieldSchema::fromRows($data['fields']);
        foreach ($schema as $field) {
            if ($field->type->storesAttachment()) {
                throw ValidationException::withMessages(['fields' => __('fields.custom.validation.type_not_allowed', ['label' => $field->label])]);
            }
        }
        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'validity_months' => $data['validity_months'],
            'is_active' => $data['is_active'] ?? true,
            'schema' => $schema,
        ];
        if ($questionnaire === null) {
            return SupplierQuestionnaire::query()->create($attributes + ['organization_id' => $organization->id, 'created_by' => $actor->id]);
        }
        $questionnaire->update($attributes);

        return $questionnaire;
    }

    public function send(SupplierQuestionnaire $questionnaire, Supplier $supplier, string $email, User $actor): SupplierQuestionnaireRequest {
        $token = Str::lower(Str::random(40));

        return DB::transaction(function () use ($questionnaire, $supplier, $email, $actor, $token): SupplierQuestionnaireRequest {
            SupplierQuestionnaireRequest::query()
                ->where('supplier_id', $supplier->id)
                ->where('supplier_questionnaire_id', $questionnaire->id)
                ->where('status', SupplierQuestionnaireStatus::Sent->value)
                ->update(['status' => SupplierQuestionnaireStatus::Withdrawn->value]);
            $request = SupplierQuestionnaireRequest::query()->create([
                'organization_id' => $questionnaire->organization_id,
                'supplier_questionnaire_id' => $questionnaire->id,
                'supplier_id' => $supplier->id,
                'status' => SupplierQuestionnaireStatus::Sent,
                'token_hash' => CryptoHelper::hash($token),
                'recipient_email' => $email,
                'schema_snapshot' => $questionnaire->schema,
                'sent_at' => now(),
                'expires_at' => now()->addDays(self::LINK_DAYS),
                'created_by' => $actor->id,
            ]);
            Mail::to($email)->queue(new SupplierQuestionnaireMail((int) $request->id, $token));

            return $request;
        });
    }

    /** Offene oder abgelehnte (zur Nachbesserung offene) Anfrage zum Link; sonst null. */
    public function resolve(string $token): ?SupplierQuestionnaireRequest {
        if ($token === '') {
            return null;
        }
        $request = SupplierQuestionnaireRequest::query()->withoutGlobalScopes()->where('token_hash', CryptoHelper::hash($token))->first();

        return $request !== null
            && in_array($request->status, [SupplierQuestionnaireStatus::Sent, SupplierQuestionnaireStatus::Rejected], true)
            && $request->expires_at->isFuture() ? $request : null;
    }

    /** @param array<string, mixed> $input */
    public function submit(SupplierQuestionnaireRequest $request, array $input): SupplierQuestionnaireRequest {
        $schema = $request->schema_snapshot->visibleFor($input);
        $validated = Validator::make(['values' => $input], $this->validator->rules($schema), [], $schema->attributeNames())->validate();
        $this->assertValidatedTransition($request->status, SupplierQuestionnaireStatus::Submitted, 'supplier_questionnaire.error.transition');
        $request->forceFill([
            'answers' => FieldValues::normalize($schema, (array) ($validated['values'] ?? []), $this->extensions),
            'status' => SupplierQuestionnaireStatus::Submitted,
            'submitted_at' => now(),
        ])->save();

        return $request;
    }

    public function review(SupplierQuestionnaireRequest $request, bool $accept, ?string $note, User $actor): SupplierQuestionnaireRequest {
        $target = $accept ? SupplierQuestionnaireStatus::Accepted : SupplierQuestionnaireStatus::Rejected;
        $this->assertValidatedTransition($request->status, $target, 'supplier_questionnaire.error.transition');
        $request->forceFill([
            'status' => $target,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'note' => $note,
            'valid_until' => $accept ? now()->addMonths((int) ($request->questionnaire->validity_months ?? 12))->toDateString() : null,
        ])->save();

        return $request;
    }

    /**
     * Anzeige einer Antwort je Frage (ohne Abschnitte).
     *
     * @return list<array{label: string, value: string}>
     */
    public function answerLines(SupplierQuestionnaireRequest $request): array {
        $lines = [];
        foreach ($request->schema_snapshot as $field) {
            if ($field->type !== FieldType::Section) {
                $lines[] = ['label' => $field->label, 'value' => $request->answers->display($field, $this->extensions)];
            }
        }

        return $lines;
    }
}
