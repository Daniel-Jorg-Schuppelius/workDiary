<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DecidesSuggestions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Suggestions\Concerns;

use App\Models\Ai\AiTextSuggestion;
use App\Models\{AuditLog, User};
use App\Services\Ai\Dto\AiInvocationResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Gemeinsame Persistenz-/Entscheidungslogik der Vorschlags-Services
 * (Feature 084/143): offenen Vorschlag je Subjekt UND Capability ablegen,
 * verwerfen (idempotent) und Entscheidungen ohne Klartext auditieren.
 */
trait DecidesSuggestions {
    public function reject(AiTextSuggestion $suggestion, ?User $user): void {
        if (! $suggestion->isOpen()) {
            return; // idempotent
        }

        $suggestion->forceFill([
            'status' => AiTextSuggestion::STATUS_REJECTED,
            'decided_by_user_id' => $user?->getKey(),
            'decided_at' => Carbon::now(),
        ])->save();

        $this->auditDecision($suggestion, 'rejected', $user);
    }

    /**
     * Offenen Vorschlag persistieren — ersetzt einen bestehenden offenen
     * Vorschlag DERSELBEN Capability am selben Subjekt (Text- und
     * Klassifikationsvorschlag dürfen nebeneinander stehen).
     */
    protected function storeProposal(
        int $organizationId,
        Model $subject,
        string $capability,
        string $original,
        string $text,
        AiInvocationResult $result,
        ?User $user,
    ): AiTextSuggestion {
        AiTextSuggestion::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', (int) $subject->getKey())
            ->where('capability', $capability)
            ->where('status', AiTextSuggestion::STATUS_PROPOSED)
            ->delete();

        return AiTextSuggestion::query()->create([
            'organization_id' => $organizationId,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (int) $subject->getKey(),
            'capability' => $result->capability,
            'original' => $original,
            'suggestion' => $text,
            'status' => AiTextSuggestion::STATUS_PROPOSED,
            'connection_id' => $result->connectionId,
            'provider' => $result->provider->value,
            'fallback_used' => $result->fallbackUsed,
            'from_cache' => $result->fromCache,
            'created_by_user_id' => $user?->getKey(),
        ]);
    }

    protected function markDecided(AiTextSuggestion $suggestion, string $status, ?User $user): void {
        $suggestion->forceFill([
            'status' => $status,
            'decided_by_user_id' => $user?->getKey(),
            'decided_at' => Carbon::now(),
        ])->save();
    }

    /** @param array<string, scalar|null> $extra */
    protected function auditDecision(AiTextSuggestion $suggestion, string $decision, ?User $user, array $extra = []): void {
        AuditLog::create([
            'organization_id' => $suggestion->organization_id,
            'user_id' => $user?->getKey(),
            'event' => 'ai.suggestion_decided',
            'auditable_type' => $suggestion->getMorphClass(),
            'auditable_id' => $suggestion->getKey(),
            'changes' => array_merge([
                'decision' => $decision,
                'capability' => $suggestion->capability,
                'provider' => $suggestion->provider,
                'subject_type' => $suggestion->subject_type,
                'subject_id' => $suggestion->subject_id,
            ], $extra),
        ]);
    }
}
