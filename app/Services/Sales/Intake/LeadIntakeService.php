<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake;

use App\Enums\Sales\{LeadIntakeDecision, LeadIntakeStage, LeadStatus};
use App\Models\Attachments\Attachment;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Sales\{Lead, LeadIntake};
use App\Services\Attachments\FileAttacher;
use App\Services\Fields\{FieldDocument, FieldExtensionRegistry, FieldSchema, FieldValidator, FieldValues};
use App\Services\Sales\Intake\Contracts\{ActsOnIntakeAcceptance, ChecksIntakeBeforeAcceptance};
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Aufnahmeakte am Lead (Feature 200, MVP-1641–1643) — einzige Schreibstelle
 * für Öffnen, Stufe, Entscheidung, zweiten Blick, Unterlagen, Aktualisierung
 * und das Ende der Geschäftsbeziehung. Feldwerte schreibt der gemeinsame
 * Endpunkt „Speichern je Feld“ (Feature 203) über den Vertrag des Modells.
 *
 * Entscheidungen: Die Annahme prüft Pflichtfelder (sichtbare, nach den
 * Bedingungen des Bogens) und die Paketprüfungen; verlangt die Organisation
 * einen zweiten Blick, bestätigt eine zweite Person mit Entscheidungsrecht.
 * Danach steht die Akte in „Einrichtung“, bis der Lead umgewandelt ist; eine
 * Aktualisierung nach der Umwandlung endet direkt in „eingerichtet“.
 */
final class LeadIntakeService {
    use \App\Services\Concerns\AssertsStatusTransition;

    public const int REASON_MAX = 2000;

    public function __construct(
        private readonly IntakePackages $packages,
        private readonly IntakeFormBuilder $builder,
        private readonly LeadIntakeRetention $retention,
        private readonly LeadIntakeVisibility $visibility,
        private readonly FieldValidator $validator,
        private readonly FieldExtensionRegistry $extensions,
        private readonly FileAttacher $files,
    ) {}

    /** Neueste Fassung der Akte eines Leads. */
    public function current(Lead $lead): ?LeadIntake {
        return LeadIntake::query()->withoutGlobalScopes()
            ->where('organization_id', $lead->organization_id)
            ->where('lead_id', $lead->id)
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->first();
    }

    /** @return Collection<int, LeadIntake> alle Fassungen, neueste zuerst */
    public function versions(Lead $lead): Collection {
        return LeadIntake::query()->withoutGlobalScopes()
            ->where('organization_id', $lead->organization_id)
            ->where('lead_id', $lead->id)
            ->with(['decider:id,name', 'secondReviewer:id,name'])
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Akte öffnen — mit den eingeschalteten Prüfpaketen der Organisation.
     * Gibt es schon eine, bleibt es bei ihr (Wiederholung über {@see refresh()}).
     */
    public function open(Lead $lead, User $actor): LeadIntake {
        if ($lead->anonymized_at !== null) {
            throw new RuntimeException((string) __('lead_intake.error.anonymized'));
        }
        $existing = $this->current($lead);
        if ($existing !== null) {
            return $existing;
        }
        $organization = Organization::query()->findOrFail($lead->organization_id);
        ['schema' => $schema, 'packages' => $snapshots] = $this->builder->build($organization, $this->packages->enabled($organization));

        $intake = new LeadIntake([
            'organization_id' => $lead->organization_id,
            'lead_id' => $lead->id,
            'customer_id' => $lead->customer_id,
            'stage' => LeadIntakeStage::Inquiry,
            'packages' => array_map(static fn ($snapshot): array => $snapshot->toArray(), $snapshots),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $intake->form = new FieldDocument($schema, new FieldValues);
        $interest = trim((string) $lead->interest);
        $intake->fillValues(new FieldValues($interest !== '' && $schema->has('request') ? ['request' => $interest] : []));
        $intake->save();

        return $intake;
    }

    /**
     * Ganzen Bogen speichern („Speichern“, auch ohne JavaScript): nur Felder,
     * die die Person sieht, ohne Pflicht (die prüft die Annahme); verborgene
     * Angaben bleiben unverändert.
     *
     * @param  array<array-key, mixed>  $input  Eingaben unter `values`
     * @param  array<array-key, mixed>  $notes  Ergänzungen unter `notes`
     *
     * @throws ValidationException
     */
    public function saveValues(LeadIntake $intake, array $input, array $notes, User $actor): LeadIntake {
        $schema = $this->visibility->schemaFor($intake, $actor);
        $editable = new FieldSchema(array_values(array_map(
            static fn ($field) => $field->optional(),
            array_filter($schema->all(), static fn ($field): bool => ! $field->type->storesAttachment()),
        )));
        Validator::make(['values' => $input], $this->validator->rules($editable, 'values'), [], $editable->attributeNames('values'))->validate();
        $normalized = FieldValues::normalize($editable, $input, $this->extensions);

        return DB::transaction(function () use ($intake, $editable, $normalized, $notes, $actor): LeadIntake {
            $locked = LeadIntake::query()->withoutGlobalScopes()->whereKey($intake->id)->lockForUpdate()->firstOrFail();
            if (! $locked->acceptsFieldAutosave()) {
                throw new RuntimeException((string) __('lead_intake.error.closed'));
            }
            $locked->fillValues($locked->fieldDocumentValues()->with($normalized->toArray())->withNotesFrom($editable, $notes));
            $locked->forceFill(['version' => $locked->version + 1, 'updated_by' => $actor->id])->saveQuietly();

            return $locked;
        });
    }

    /** Stufe vor der Entscheidung wechseln (Anfrage, Gespräch, Unterlagen, Entscheidung). */
    public function moveTo(LeadIntake $intake, LeadIntakeStage $to, User $actor): LeadIntake {
        if (! $to->isEditable()) {
            throw new RuntimeException((string) __('lead_intake.error.stage_by_decision'));
        }

        return DB::transaction(function () use ($intake, $to, $actor): LeadIntake {
            $locked = LeadIntake::query()->withoutGlobalScopes()->whereKey($intake->id)->lockForUpdate()->firstOrFail();
            if (! $locked->acceptsFieldAutosave()) {
                throw new RuntimeException((string) __('lead_intake.error.closed'));
            }
            $from = $locked->stage;
            $this->assertStatusTransition($from, $to);
            $locked->forceFill(['stage' => $to, 'updated_by' => $actor->id])->save();
            $locked->audit('lead_intake.stage_changed', ['from' => $from->value, 'to' => $to->value]);

            return $locked;
        });
    }

    /**
     * Annehmen oder ablehnen, immer mit Begründung. Die Annahme verlangt alle
     * Pflichtangaben und die Paketprüfungen (ValidationException je Feld).
     *
     * @throws ValidationException
     */
    public function decide(LeadIntake $intake, LeadIntakeDecision $decision, string $reason, User $actor): LeadIntake {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > self::REASON_MAX) {
            throw ValidationException::withMessages(['reason' => (string) __('lead_intake.error.reason_required')]);
        }

        return DB::transaction(function () use ($intake, $decision, $reason, $actor): LeadIntake {
            $locked = LeadIntake::query()->withoutGlobalScopes()->whereKey($intake->id)->lockForUpdate()->firstOrFail();
            if (! $locked->acceptsFieldAutosave()) {
                throw new RuntimeException((string) __('lead_intake.error.closed'));
            }
            if ($locked->stage !== LeadIntakeStage::Decision) {
                $this->assertStatusTransition($locked->stage, LeadIntakeStage::Decision);
            }
            $organization = Organization::query()->findOrFail($locked->organization_id);

            if ($decision === LeadIntakeDecision::Accepted) {
                $errors = $this->acceptanceErrors($locked, $organization);
                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }
            } else {
                $this->assertStatusTransition(LeadIntakeStage::Decision, LeadIntakeStage::Rejected);
            }

            $locked->forceFill([
                'decision' => $decision,
                'decision_reason' => $reason,
                'decider_user_id' => $actor->id,
                'decided_at' => Carbon::now(),
                'stage' => $decision === LeadIntakeDecision::Rejected ? LeadIntakeStage::Rejected : LeadIntakeStage::Decision,
                'updated_by' => $actor->id,
            ])->save();
            $locked->audit('lead_intake.decided', ['decision' => $decision->value, 'reason' => $reason]);

            if ($decision === LeadIntakeDecision::Accepted && ! $this->packages->secondReview($organization)) {
                $this->finishAcceptanceLocked($locked, $organization, $actor);
            }
            $this->retention->refresh($locked);

            return $locked;
        });
    }

    /** Zweiter Blick: eine andere Person mit Entscheidungsrecht bestätigt die Annahme. */
    public function confirmSecondReview(LeadIntake $intake, User $actor): LeadIntake {
        return DB::transaction(function () use ($intake, $actor): LeadIntake {
            $locked = LeadIntake::query()->withoutGlobalScopes()->whereKey($intake->id)->lockForUpdate()->firstOrFail();
            if (! $locked->awaitsSecondReview() || ! $locked->isLatest()) {
                throw new RuntimeException((string) __('lead_intake.error.no_second_review'));
            }
            if ((int) $locked->decider_user_id === (int) $actor->id) {
                throw new RuntimeException((string) __('lead_intake.error.same_person'));
            }
            $locked->forceFill([
                'second_reviewer_user_id' => $actor->id,
                'second_reviewed_at' => Carbon::now(),
                'updated_by' => $actor->id,
            ])->save();
            $locked->audit('lead_intake.second_review', ['decider_user_id' => $locked->decider_user_id]);
            $this->finishAcceptanceLocked($locked, Organization::query()->findOrFail($locked->organization_id), $actor);
            $this->retention->refresh($locked);

            return $locked;
        });
    }

    /** Zweiter Blick gibt zurück: die Annahme entfällt, der Bogen ist wieder offen. */
    public function returnFromSecondReview(LeadIntake $intake, string $reason, User $actor): LeadIntake {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > self::REASON_MAX) {
            throw ValidationException::withMessages(['reason' => (string) __('lead_intake.error.reason_required')]);
        }

        return DB::transaction(function () use ($intake, $reason, $actor): LeadIntake {
            $locked = LeadIntake::query()->withoutGlobalScopes()->whereKey($intake->id)->lockForUpdate()->firstOrFail();
            if (! $locked->awaitsSecondReview() || ! $locked->isLatest()) {
                throw new RuntimeException((string) __('lead_intake.error.no_second_review'));
            }
            if ((int) $locked->decider_user_id === (int) $actor->id) {
                throw new RuntimeException((string) __('lead_intake.error.same_person'));
            }
            $locked->forceFill([
                'decision' => null,
                'decision_reason' => null,
                'decider_user_id' => null,
                'decided_at' => null,
                'updated_by' => $actor->id,
            ])->save();
            $locked->audit('lead_intake.second_review_returned', ['reason' => $reason]);
            $this->retention->refresh($locked);

            return $locked;
        });
    }

    /**
     * Fehlende Pflichtangaben und Einwände der Pakete, je Feldschlüssel.
     *
     * @return array<string, string>
     */
    public function acceptanceErrors(LeadIntake $intake, ?Organization $organization = null): array {
        $organization ??= Organization::query()->findOrFail($intake->organization_id);
        $values = $intake->fieldDocumentValues();
        $data = $values->toArray();
        unset($data[FieldValues::NOTES_KEY]);
        $schema = $intake->fullSchema()->visibleFor($data);
        $validator = Validator::make($data, $this->validator->rules($schema, ''), [], $schema->attributeNames(''));
        $errors = [];
        foreach ($validator->errors()->messages() as $key => $messages) {
            $base = explode('.', (string) $key, 2)[0];
            $errors[$base] ??= (string) ($messages[0] ?? '');
        }
        foreach ($intake->packageSnapshots() as $snapshot) {
            $package = $snapshot->isClass ? $this->packages->get($organization, $snapshot->key) : null;
            if (! $package instanceof ChecksIntakeBeforeAcceptance) {
                continue;
            }
            foreach ($package->checkBeforeAcceptance(IntakeKeys::scoped($values, $snapshot->key), $intake) as $field => $message) {
                $errors[IntakeKeys::package($snapshot->key, $field)] ??= $message;
            }
        }

        return $errors;
    }

    /**
     * Neue Fassung nach der Annahme (Aktualisierung, MVP-1643): Bogen aus den
     * Paketen der alten Fassung und den heute eingeschalteten, Werte
     * übernommen (was nicht mehr passt, steht als Ergänzung an der Frage).
     */
    public function refresh(LeadIntake $intake, User $actor): LeadIntake {
        return DB::transaction(function () use ($intake, $actor): LeadIntake {
            $locked = LeadIntake::query()->withoutGlobalScopes()->whereKey($intake->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isLatest() || ! $locked->isAccepted()) {
                throw new RuntimeException((string) __('lead_intake.error.refresh_needs_acceptance'));
            }
            $organization = Organization::query()->findOrFail($locked->organization_id);
            $available = $this->packages->available($organization);
            $keys = array_unique([...array_map(static fn ($snapshot): string => $snapshot->key, $locked->packageSnapshots()), ...$this->packages->enabledKeys($organization)]);
            $packages = array_intersect_key($available, array_flip($keys));
            ksort($packages);
            ['schema' => $schema, 'packages' => $snapshots] = $this->builder->build($organization, $packages);

            $new = new LeadIntake([
                'organization_id' => $locked->organization_id,
                'lead_id' => $locked->lead_id,
                'customer_id' => $locked->customer_id,
                'revision' => $locked->revision + 1,
                'supersedes_id' => $locked->id,
                'stage' => LeadIntakeStage::Interview,
                'packages' => array_map(static fn ($snapshot): array => $snapshot->toArray(), $snapshots),
                'relationship_ended_on' => $locked->relationship_ended_on?->toDateString(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $new->form = new FieldDocument($schema, new FieldValues);
            $new->fillValues($locked->fieldDocumentValues()->rebase($locked->fullSchema(), $schema, $this->extensions));
            $new->save();
            $new->audit('lead_intake.refreshed', ['from_revision' => $locked->revision]);

            $locked->forceFill(['superseded_at' => Carbon::now(), 'updated_by' => $actor->id])->save();

            return $new;
        });
    }

    /**
     * Datei zu einer Unterlage ablegen; der Haken der Unterlage wird gesetzt.
     *
     * @throws AuthorizationException ohne Recht für eine eng sichtbare Unterlage
     * @throws ValidationException unbekannte Unterlage oder unzulässige Datei
     */
    public function addDocument(LeadIntake $intake, string $fieldKey, UploadedFile $file, User $actor): Attachment {
        if (! IntakeKeys::isDocument($fieldKey) || ! $intake->fullSchema()->has($fieldKey)) {
            throw ValidationException::withMessages(['document' => (string) __('lead_intake.error.unknown_document')]);
        }
        if (! $this->visibility->canSeeKey($intake, $actor, $fieldKey)) {
            throw new AuthorizationException((string) __('lead_intake.error.restricted'));
        }
        if ($intake->attachmentsLocked()) {
            throw new RuntimeException((string) __('lead_intake.error.closed'));
        }
        $problem = FileAttacher::rejection($file);
        if ($problem !== null) {
            throw ValidationException::withMessages(['file' => $problem]);
        }

        return DB::transaction(function () use ($intake, $fieldKey, $file, $actor): Attachment {
            $attachment = $this->files->store($intake, $file, (int) $actor->id, [
                'organization_id' => $intake->organization_id,
                'meta_type' => IntakeKeys::ATTACHMENT_META . $fieldKey,
            ]);
            $intake->fillValues($intake->fieldDocumentValues()->with([$fieldKey => true]));
            $intake->forceFill(['version' => $intake->version + 1, 'updated_by' => $actor->id])->saveQuietly();
            $intake->attachmentAdded($attachment, $actor);

            return $attachment;
        });
    }

    /**
     * Ende der Geschäftsbeziehung am Kunden setzen oder zurücknehmen — Anker
     * der Aufbewahrung aller Akten des Kunden (§ 8 Abs. 4 Satz 2 GwG).
     *
     * @return int Anzahl der Akten mit neu berechneter Frist
     */
    public function endRelationship(Customer $customer, ?CarbonImmutable $endedOn, User $actor): int {
        return DB::transaction(function () use ($customer, $endedOn, $actor): int {
            $customer->forceFill(['relationship_ended_on' => $endedOn?->toDateString()])->save();
            $leadIds = Lead::query()->withoutGlobalScopes()->where('organization_id', $customer->organization_id)->where('customer_id', $customer->id)->pluck('id');
            $intakes = LeadIntake::query()->withoutGlobalScopes()
                ->where('organization_id', $customer->organization_id)
                ->where(static fn ($q) => $q->where('customer_id', $customer->id)->orWhereIn('lead_id', $leadIds))
                ->get();
            foreach ($intakes as $intake) {
                $intake->forceFill(['relationship_ended_on' => $endedOn?->toDateString(), 'updated_by' => $actor->id])->saveQuietly();
                $this->retention->refresh($intake);
            }
            $customer->audit('lead_intake.relationship_ended', ['ended_on' => $endedOn?->toDateString(), 'intakes' => $intakes->count()]);

            return $intakes->count();
        });
    }

    /**
     * Annahme abschließen (unter der Sperre des Aufrufers): Einrichtung, bzw.
     * eingerichtet, wenn der Lead schon Kunde ist; Aktualisierungsfrist; Folgen
     * der Pakete (GwG-Vermerk).
     */
    private function finishAcceptanceLocked(LeadIntake $locked, Organization $organization, User $actor): void {
        $this->assertStatusTransition($locked->stage, LeadIntakeStage::Setup);
        $lead = Lead::query()->withoutGlobalScopes()->find($locked->lead_id);
        $months = $this->refreshMonths($locked, $organization);
        $decidedAt = CarbonImmutable::instance($locked->decided_at ?? Carbon::now());
        $locked->forceFill([
            'stage' => LeadIntakeStage::Setup,
            'refresh_due_on' => $months !== null ? $decidedAt->addMonths($months)->toDateString() : null,
            'refresh_warned_at' => null,
        ])->save();
        if ($lead instanceof Lead && $lead->status === LeadStatus::Converted && $lead->customer_id !== null) {
            $this->assertStatusTransition(LeadIntakeStage::Setup, LeadIntakeStage::Completed);
            $locked->forceFill(['stage' => LeadIntakeStage::Completed, 'customer_id' => $lead->customer_id, 'completed_at' => Carbon::now()])->save();
        }
        foreach ($locked->packageSnapshots() as $snapshot) {
            $package = $snapshot->isClass ? $this->packages->get($organization, $snapshot->key) : null;
            if ($package instanceof ActsOnIntakeAcceptance) {
                $package->accepted($locked, $actor);
            }
        }
    }

    /** Kürzeste Aktualisierung der Pakete in Monaten, null = keine. */
    private function refreshMonths(LeadIntake $intake, Organization $organization): ?int {
        $values = $intake->fieldDocumentValues();
        $months = null;
        foreach ($intake->packageSnapshots() as $snapshot) {
            $interval = $snapshot->isClass
                ? $this->packages->get($organization, $snapshot->key)?->refreshInterval(IntakeKeys::scoped($values, $snapshot->key), $organization)
                : $snapshot->refreshMonths;
            if ($interval !== null && $interval > 0) {
                $months = $months === null ? $interval : min($months, $interval);
            }
        }

        return $months;
    }
}
