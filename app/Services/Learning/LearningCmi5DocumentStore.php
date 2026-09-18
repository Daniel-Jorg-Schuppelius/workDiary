<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCmi5DocumentStore.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Learning\LearningXapiDocument;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use ELearningToolkit\Cmi5\{Cmi5, LearnerPreferences};
use Illuminate\Database\Eloquent\Builder;

/**
 * State- und Agent-Profil-Dokumente des cmi5-LRS (xAPI 1.0.3, Document APIs).
 *
 * Ein Dokument gehört genau einer Kombination aus Organisation, Art, Aktivität,
 * Agent, Registrierung und Dokument-ID; der Abdruck darüber ist eindeutig.
 */
final class LearningCmi5DocumentStore {
    public function find(int $organizationId, string $kind, ?string $activityId, string $agentHash, ?string $registration, string $documentId): ?LearningXapiDocument {
        return LearningXapiDocument::query()
            ->where('organization_id', $organizationId)
            ->where('lookup_hash', $this->key($organizationId, $kind, $activityId, $agentHash, $registration, $documentId))
            ->first();
    }

    /** @return list<string> */
    public function ids(int $organizationId, string $kind, ?string $activityId, string $agentHash, ?string $registration, ?CarbonImmutable $since = null): array {
        $query = $this->scope($organizationId, $kind, $activityId, $agentHash, $registration)->orderBy('document_id');

        if ($since !== null) {
            $query->where('updated_at', '>', $since);
        }

        $ids = [];

        foreach ($query->get(['document_id']) as $document) {
            $ids[] = $document->document_id;
        }

        return $ids;
    }

    public function put(int $organizationId, string $kind, ?string $activityId, string $agentHash, ?string $registration, string $documentId, string $content, string $contentType): LearningXapiDocument {
        $document = LearningXapiDocument::query()->firstOrNew([
            'organization_id' => $organizationId,
            'lookup_hash' => $this->key($organizationId, $kind, $activityId, $agentHash, $registration, $documentId),
        ]);

        $document->fill([
            'kind' => $kind,
            'activity_id' => $activityId,
            'agent_hash' => $agentHash,
            'registration' => $registration,
            'document_id' => $documentId,
            'content' => $content,
            'content_type' => mb_substr($contentType, 0, 100),
            'etag' => CryptoHelper::hash($content, HashAlgorithm::SHA1),
        ])->save();

        return $document;
    }

    /**
     * POST: Zwei JSON-Objekte werden zusammengeführt, sonst gilt es wie PUT.
     *
     * @throws Cmi5LrsRejection wenn eine der beiden Seiten kein JSON-Objekt ist
     */
    public function merge(int $organizationId, string $kind, ?string $activityId, string $agentHash, ?string $registration, string $documentId, string $content, string $contentType): LearningXapiDocument {
        $existing = $this->find($organizationId, $kind, $activityId, $agentHash, $registration, $documentId);

        if ($existing === null) {
            return $this->put($organizationId, $kind, $activityId, $agentHash, $registration, $documentId, $content, $contentType);
        }

        $old = self::jsonObject($existing->content_type, $existing->content);
        $new = self::jsonObject($contentType, $content);

        if ($old === null || $new === null) {
            throw new Cmi5LrsRejection(400, 'merge_requires_json');
        }

        $merged = JsonHelper::encode(array_merge($old, $new), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->put($organizationId, $kind, $activityId, $agentHash, $registration, $documentId, $merged, 'application/json');
    }

    public function delete(int $organizationId, string $kind, ?string $activityId, string $agentHash, ?string $registration, string $documentId): void {
        LearningXapiDocument::query()
            ->where('organization_id', $organizationId)
            ->where('lookup_hash', $this->key($organizationId, $kind, $activityId, $agentHash, $registration, $documentId))
            ->delete();
    }

    /** @param  list<string>  $except */
    public function deleteAll(int $organizationId, string $kind, ?string $activityId, string $agentHash, ?string $registration, array $except = []): void {
        $this->scope($organizationId, $kind, $activityId, $agentHash, $registration)
            ->whereNotIn('document_id', $except)
            ->delete();
    }

    /** Lernpräferenzen anlegen, solange es keine gibt (cmi5 11.0). */
    public function ensureLearnerPreferences(int $organizationId, string $agentHash, string $language): void {
        $kind = LearningXapiDocument::KIND_AGENT_PROFILE;

        if ($this->find($organizationId, $kind, null, $agentHash, null, Cmi5::PROFILE_LEARNER_PREFERENCES) !== null) {
            return;
        }

        $document = JsonHelper::encode(LearnerPreferences::document([$language], null), JSON_UNESCAPED_SLASHES);

        $this->put($organizationId, $kind, null, $agentHash, null, Cmi5::PROFILE_LEARNER_PREFERENCES, $document, 'application/json');
    }

    /** @return Builder<LearningXapiDocument> */
    private function scope(int $organizationId, string $kind, ?string $activityId, string $agentHash, ?string $registration): Builder {
        $query = LearningXapiDocument::query()
            ->where('organization_id', $organizationId)
            ->where('kind', $kind)
            ->where('agent_hash', $agentHash);

        if ($activityId === null) {
            $query->whereNull('activity_id');
        } else {
            $query->where('activity_id', $activityId);
        }

        if ($registration === null) {
            $query->whereNull('registration');
        } else {
            $query->where('registration', $registration);
        }

        return $query;
    }

    private function key(int $organizationId, string $kind, ?string $activityId, string $agentHash, ?string $registration, string $documentId): string {
        return CryptoHelper::hash(implode("\n", [(string) $organizationId, $kind, $activityId ?? '', $agentHash, $registration ?? '', $documentId]));
    }

    /** @return array<mixed>|null */
    private static function jsonObject(string $contentType, string $content): ?array {
        if (! str_starts_with(strtolower(trim($contentType)), 'application/json')) {
            return null;
        }

        $data = json_decode($content, true);

        return is_array($data) && ($data === [] || ! array_is_list($data)) ? $data : null;
    }
}
