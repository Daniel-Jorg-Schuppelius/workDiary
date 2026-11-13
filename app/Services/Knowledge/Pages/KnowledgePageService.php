<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages;

use App\Enums\Knowledge\{KnowledgePageKind, KnowledgePageStatus};
use App\Models\Integration\ExternalReference;
use App\Models\Knowledge\{ContentCollectionItem, ContentReference, KnowledgeGuideStep, KnowledgePage, KnowledgePageVersion, KnowledgeSection, KnowledgeSpace};
use App\Models\Platform\User;
use App\Services\Concerns\AssertsValidatedTransition;
use App\Support\CarbonFmt;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lebenszyklus der Wissensseiten (Feature 191, MVP-1664/1665).
 *
 * Jede inhaltliche Änderung des Textes ist eine neue Fassung; die Seite fällt
 * dann auf „Entwurf“, bis die verantwortliche Person freigibt — Leser sehen
 * bis dahin die zuletzt freigegebene Fassung. Gespeichert wird nur gegen die
 * Fassung, die die bearbeitende Person geöffnet hatte: hat jemand inzwischen
 * gespeichert, gibt es einen Hinweis statt eines stillen Überschreibens.
 *
 * Schritte einer Bildanleitung ändern die offene Fassung, solange sie nicht
 * freigegeben ist ({@see guideChanged()}) — sonst entstünde je Mausklick eine
 * Fassung.
 */
final class KnowledgePageService {
    use AssertsValidatedTransition;

    /**
     * @param  array{title: string, body?: string|null, kind?: string|null, tags?: string|null, note?: string|null, owner_user_id?: int|null}  $data
     */
    public function create(KnowledgeSpace $space, ?KnowledgeSection $section, ?KnowledgePage $parent, User $actor, array $data): KnowledgePage {
        if ($section !== null && (int) $section->knowledge_space_id !== (int) $space->id) {
            throw ValidationException::withMessages(['section' => (string) __('knowledge_pages.error.section_space')]);
        }
        if ($parent !== null) {
            $this->assertParent($space, $parent, null);
        }

        return DB::transaction(function () use ($space, $section, $parent, $actor, $data): KnowledgePage {
            $sectionId = $parent !== null ? $parent->knowledge_section_id : $section?->id;
            $page = KnowledgePage::query()->create([
                'organization_id' => $space->organization_id,
                'knowledge_space_id' => $space->id,
                'knowledge_section_id' => $sectionId,
                'parent_id' => $parent?->id,
                'kind' => (KnowledgePageKind::tryFrom((string) ($data['kind'] ?? '')) ?? KnowledgePageKind::Page)->value,
                'position' => $this->nextPosition((int) $space->id, $sectionId, $parent?->id),
                'title' => trim($data['title']),
                'body' => $this->body($data['body'] ?? null),
                'status' => KnowledgePageStatus::Draft->value,
                'version' => 1,
                'owner_user_id' => $data['owner_user_id'] ?? $actor->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            if (($data['tags'] ?? null) !== null) {
                $page->syncTagNames((string) $data['tags']);
            }
            $this->snapshot($page, $actor, $data['note'] ?? null);

            return $page;
        });
    }

    /**
     * Text und Schlagwörter speichern. `$baseVersion` ist die Fassung, die
     * beim Öffnen des Dialogs aktuell war.
     *
     * @param  array{title: string, body?: string|null, tags?: string|null, note?: string|null}  $data
     */
    public function update(KnowledgePage $page, User $actor, array $data, int $baseVersion): KnowledgePage {
        return DB::transaction(function () use ($page, $actor, $data, $baseVersion): KnowledgePage {
            $locked = KnowledgePage::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
            if ($locked->version !== $baseVersion) {
                throw ValidationException::withMessages(['base_version' => (string) __('knowledge_pages.error.conflict', [
                    'version' => $locked->version,
                    'name' => $locked->updater()->value('name') ?? '—',
                    'time' => $locked->updated_at !== null ? CarbonFmt::fdatetime($locked->updated_at) : '—',
                ])]);
            }

            $title = trim($data['title']);
            $body = $this->body($data['body'] ?? null);
            if ($title !== $locked->title || $body !== (string) $locked->body) {
                if ($locked->status !== KnowledgePageStatus::Draft) {
                    $this->assertValidatedTransition($locked->status, KnowledgePageStatus::Draft, 'knowledge_pages.error.transition');
                }
                $locked->forceFill([
                    'title' => $title,
                    'body' => $body,
                    'version' => $locked->version + 1,
                    'status' => KnowledgePageStatus::Draft->value,
                    'updated_by' => $actor->id,
                ])->save();
                $this->snapshot($locked, $actor, $data['note'] ?? null);
            }
            if (array_key_exists('tags', $data)) {
                $locked->syncTagNames((string) $data['tags']);
            }

            return $locked;
        });
    }

    /** Freigabe der aktuellen Fassung durch die verantwortliche Person (MVP-1665). */
    public function approve(KnowledgePage $page, User $actor): KnowledgePage {
        return DB::transaction(function () use ($page, $actor): KnowledgePage {
            $locked = KnowledgePage::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
            if (! $locked->hasPendingVersion()) {
                throw ValidationException::withMessages(['status' => (string) __('knowledge_pages.error.nothing_to_approve')]);
            }
            $this->assertValidatedTransition($locked->status, KnowledgePageStatus::Valid, 'knowledge_pages.error.transition');
            $this->assertStillValid($locked);

            $now = CarbonImmutable::now();
            $locked->forceFill([
                'status' => KnowledgePageStatus::Valid->value,
                'approved_version' => $locked->version,
                'approved_at' => $now,
                'approved_by' => $actor->id,
                // Die Freigabe ist zugleich eine Prüfung.
                'reviewed_at' => $now,
                'reviewed_by' => $actor->id,
                'next_review_on' => $this->nextReview($locked->review_interval_months, $now),
                'review_warned_at' => null,
            ])->save();
            KnowledgePageVersion::query()
                ->where('knowledge_page_id', $locked->id)
                ->where('version', $locked->version)
                ->update(['approved_at' => $now, 'approved_by' => $actor->id]);
            $locked->audit('knowledge_page.approved', ['version' => $locked->version]);

            return $locked;
        });
    }

    /** Von Hand als veraltet markieren (MVP-1665). */
    public function markOutdated(KnowledgePage $page): KnowledgePage {
        return DB::transaction(function () use ($page): KnowledgePage {
            $locked = KnowledgePage::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
            $this->assertValidatedTransition($locked->status, KnowledgePageStatus::Outdated, 'knowledge_pages.error.transition');
            $locked->forceFill(['status' => KnowledgePageStatus::Outdated->value])->save();
            $locked->audit('knowledge_page.outdated', ['version' => $locked->approved_version]);

            return $locked;
        });
    }

    /**
     * „Stimmt, aktuell“ (MVP-1665): die freigegebene Fassung ist geprüft, das
     * nächste Prüfdatum rückt um das Intervall weiter; eine veraltete Seite
     * gilt wieder.
     */
    public function confirmCurrent(KnowledgePage $page, User $actor): KnowledgePage {
        return DB::transaction(function () use ($page, $actor): KnowledgePage {
            $locked = KnowledgePage::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isPublished()) {
                throw ValidationException::withMessages(['status' => (string) __('knowledge_pages.error.not_published')]);
            }
            $this->assertStillValid($locked);
            $now = CarbonImmutable::now();
            $changes = [
                'reviewed_at' => $now,
                'reviewed_by' => $actor->id,
                'next_review_on' => $this->nextReview($locked->review_interval_months, $now),
                'review_warned_at' => null,
            ];
            if ($locked->status === KnowledgePageStatus::Outdated) {
                $this->assertValidatedTransition($locked->status, KnowledgePageStatus::Valid, 'knowledge_pages.error.transition');
                $changes['status'] = KnowledgePageStatus::Valid->value;
            }
            $locked->forceFill($changes)->save();
            $locked->audit('knowledge_page.reviewed', ['version' => $locked->approved_version]);

            return $locked;
        });
    }

    /** Gültigkeit abgelaufen: die Seite wird veraltet (Fristen-Scan, MVP-1665). */
    public function expire(KnowledgePage $page): bool {
        return DB::transaction(function () use ($page): bool {
            $locked = KnowledgePage::query()->withoutGlobalScopes()->whereKey($page->id)->lockForUpdate()->first();
            if ($locked === null || $locked->valid_until === null || ! $locked->valid_until->lt(CarbonImmutable::today())
                || ! $locked->status->canTransitionTo(KnowledgePageStatus::Outdated)) {
                return false;
            }
            $locked->forceFill(['status' => KnowledgePageStatus::Outdated->value])->save();
            $locked->audit('knowledge_page.expired', ['valid_until' => $locked->valid_until?->toDateString()]);

            return true;
        });
    }

    /**
     * Verantwortung, Prüfintervall, Gültigkeit, Notfallhandbuch und Einordnung
     * im Baum (Bereich, Rubrik, übergeordnete Seite).
     *
     * @param  array{owner_user_id: int|null, review_interval_months: int|null, valid_until: string|null, is_emergency: bool, space: KnowledgeSpace, section: KnowledgeSection|null, parent: KnowledgePage|null}  $data
     */
    public function updateResponsibility(KnowledgePage $page, User $actor, array $data): KnowledgePage {
        $space = $data['space'];
        $section = $data['section'];
        $parent = $data['parent'];
        if ($section !== null && (int) $section->knowledge_space_id !== (int) $space->id) {
            throw ValidationException::withMessages(['section' => (string) __('knowledge_pages.error.section_space')]);
        }
        if ($parent !== null) {
            $this->assertParent($space, $parent, $page);
        }

        return DB::transaction(function () use ($page, $actor, $data, $space, $section, $parent): KnowledgePage {
            $locked = KnowledgePage::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
            $interval = $data['review_interval_months'];
            $validUntil = $data['valid_until'] !== null ? CarbonImmutable::parse($data['valid_until'])->toDateString() : null;
            $sectionId = $parent !== null ? $parent->knowledge_section_id : $section?->id;
            $moved = (int) $locked->knowledge_space_id !== (int) $space->id
                || $locked->knowledge_section_id !== $sectionId
                || $locked->parent_id !== $parent?->id;

            $changes = [
                'owner_user_id' => $data['owner_user_id'],
                'review_interval_months' => $interval,
                'valid_until' => $validUntil,
                'is_emergency' => $data['is_emergency'],
                'knowledge_space_id' => $space->id,
                'knowledge_section_id' => $sectionId,
                'parent_id' => $parent?->id,
                'updated_by' => $actor->id,
            ];
            if ($moved) {
                $changes['position'] = $this->nextPosition((int) $space->id, $sectionId, $parent?->id);
            }
            if ($interval !== $locked->review_interval_months) {
                $base = $locked->reviewed_at !== null ? CarbonImmutable::instance($locked->reviewed_at) : CarbonImmutable::now();
                $changes['next_review_on'] = $this->nextReview($interval, $base);
                $changes['review_warned_at'] = null;
            }
            if ($validUntil !== $locked->valid_until?->toDateString()) {
                $changes['valid_warned_at'] = null;
            }
            $locked->forceFill($changes)->save();
            // Unterseiten ziehen mit um.
            if ($moved) {
                $this->moveDescendants($locked);
            }

            return $locked;
        });
    }

    /**
     * Verantwortung einer Seite an eine Nachfolge übertragen (Austritt bzw.
     * endgültige Übergabe, Feature 191/202). Inhalt, Fassung, Freigabe und
     * Prüfintervall bleiben, wie sie sind; nur die verantwortliche Person
     * wechselt, auditiert mit bisheriger Person und Auslöser.
     */
    public function transferOwnership(KnowledgePage $page, User $successor, User $actor): KnowledgePage {
        return DB::transaction(function () use ($page, $successor, $actor): KnowledgePage {
            $locked = KnowledgePage::query()->withoutGlobalScopes()->withTrashed()->whereKey($page->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->owner_user_id;
            if ((int) $previous === (int) $successor->id) {
                return $locked;
            }
            $locked->forceFill(['owner_user_id' => $successor->id])->save();
            $locked->audit('knowledge_page.owner_transferred', [
                'from_user_id' => $previous,
                'to_user_id' => (int) $successor->id,
                'actor_user_id' => (int) $actor->id,
            ]);

            return $locked;
        });
    }

    /**
     * In den Papierkorb. Unterseiten bleiben stehen und hängen danach an der
     * übergeordneten Seite (bzw. an Rubrik und Bereich).
     */
    public function trash(KnowledgePage $page, User $actor): void {
        DB::transaction(function () use ($page, $actor): void {
            $locked = KnowledgePage::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
            KnowledgePage::query()->where('parent_id', $locked->id)->update(['parent_id' => $locked->parent_id]);
            $locked->forceFill(['deleted_by' => $actor->id])->save();
            $locked->delete();
        });
    }

    public function restore(KnowledgePage $page, User $actor): KnowledgePage {
        return DB::transaction(function () use ($page, $actor): KnowledgePage {
            $parentGone = $page->parent_id !== null && ! KnowledgePage::query()->whereKey($page->parent_id)->exists();
            $page->restore();
            $page->forceFill([
                'deleted_by' => null,
                'parent_id' => $parentGone ? null : $page->parent_id,
                'updated_by' => $actor->id,
            ])->save();
            $page->audit('knowledge_page.restored');

            return $page;
        });
    }

    /**
     * Endgültig löschen: Fassungen, Bestätigungen, Pflichtlektüre, Besuche,
     * Schritte (Fremdschlüssel), Anhänge (HasAttachments), dazu Einträge in
     * Sammlungen, Verweise und Herkunftsvermerke.
     */
    public function forceDelete(KnowledgePage $page, ?User $actor): void {
        DB::transaction(function () use ($page, $actor): void {
            $organizationId = (int) $page->organization_id;
            $page->audit('knowledge_page.purged', ['title' => $page->title, 'by' => $actor?->id]);
            ContentCollectionItem::query()->withoutGlobalScopes()
                ->where('collectable_type', $page->getMorphClass())
                ->where('collectable_id', $page->id)
                ->delete();
            ExternalReference::query()->withoutGlobalScopes()->forReferenceable($page)->delete();
            $page->forceDelete();
            ContentReference::pruneOrphans($organizationId);
        });
    }

    /** Papierkorb nach Frist leeren (Befehl `knowledge-pages:empty-trash`). */
    public function purgeTrashedBefore(CarbonImmutable $cutoff): int {
        $purged = 0;
        KnowledgePage::query()->withoutGlobalScopes()->onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->orderBy('id')
            ->each(function (KnowledgePage $page) use (&$purged): void {
                $this->forceDelete($page, null);
                $purged++;
            });

        return $purged;
    }

    /** Ältere Fassung als neue Fassung zurückholen (MVP-1664). */
    public function restoreVersion(KnowledgePage $page, KnowledgePageVersion $version, User $actor): KnowledgePage {
        return DB::transaction(function () use ($page, $version, $actor): KnowledgePage {
            $locked = KnowledgePage::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
            if ((int) $version->knowledge_page_id !== (int) $locked->id) {
                abort(404);
            }
            if ($locked->status !== KnowledgePageStatus::Draft) {
                $this->assertValidatedTransition($locked->status, KnowledgePageStatus::Draft, 'knowledge_pages.error.transition');
            }
            $locked->forceFill([
                'title' => $version->title,
                'body' => $version->body,
                'version' => $locked->version + 1,
                'status' => KnowledgePageStatus::Draft->value,
                'updated_by' => $actor->id,
            ])->save();
            if ($locked->isGuide()) {
                $this->restoreSteps($locked, $version, $actor);
            }
            $this->snapshot($locked, $actor, (string) __('knowledge_pages.version.restored_note', ['version' => $version->version]));
            $locked->audit('knowledge_page.version_restored', ['from' => $version->version, 'version' => $locked->version]);

            return $locked;
        });
    }

    /**
     * Ein Schritt der Anleitung hat sich geändert: die offene Fassung nimmt
     * die Schritte auf; ist die aktuelle Fassung schon freigegeben, beginnt
     * eine neue (Entwurf).
     */
    public function guideChanged(KnowledgePage $page, User $actor): KnowledgePage {
        return DB::transaction(function () use ($page, $actor): KnowledgePage {
            $locked = KnowledgePage::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
            if ($locked->hasPendingVersion()) {
                KnowledgePageVersion::query()
                    ->where('knowledge_page_id', $locked->id)
                    ->where('version', $locked->version)
                    ->update(['steps' => $this->stepsSnapshot($locked)]);
                $locked->forceFill(['updated_by' => $actor->id])->save();

                return $locked;
            }
            $this->assertValidatedTransition($locked->status, KnowledgePageStatus::Draft, 'knowledge_pages.error.transition');
            $locked->forceFill([
                'version' => $locked->version + 1,
                'status' => KnowledgePageStatus::Draft->value,
                'updated_by' => $actor->id,
            ])->save();
            $this->snapshot($locked, $actor, null);

            return $locked;
        });
    }

    /** Fassung so, wie sie gespeichert ist. */
    private function snapshot(KnowledgePage $page, User $actor, ?string $note): KnowledgePageVersion {
        return KnowledgePageVersion::query()->create([
            'organization_id' => $page->organization_id,
            'knowledge_page_id' => $page->id,
            'version' => $page->version,
            'title' => $page->title,
            'body' => $page->body,
            'steps' => $page->isGuide() ? $this->stepsSnapshot($page) : null,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'created_by' => $actor->id,
        ]);
    }

    /** @return list<array{body: string|null, attachment_id: int|null, markers: list<array<string, mixed>>}> */
    private function stepsSnapshot(KnowledgePage $page): array {
        return array_values($page->steps()->get()->map(static fn (KnowledgeGuideStep $step): array => [
            'body' => $step->body,
            'attachment_id' => $step->attachment_id,
            'markers' => $step->markers ?? [],
        ])->all());
    }

    private function restoreSteps(KnowledgePage $page, KnowledgePageVersion $version, User $actor): void {
        $page->steps()->delete();
        $attachmentIds = $page->attachments()->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        foreach ($version->steps ?? [] as $index => $step) {
            $attachmentId = isset($step['attachment_id']) && in_array((int) $step['attachment_id'], $attachmentIds, true) ? (int) $step['attachment_id'] : null;
            KnowledgeGuideStep::query()->create([
                'organization_id' => $page->organization_id,
                'knowledge_page_id' => $page->id,
                'position' => $index + 1,
                'body' => $step['body'] ?? null,
                'attachment_id' => $attachmentId,
                'markers' => $step['markers'],
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        }
    }

    private function assertStillValid(KnowledgePage $page): void {
        if ($page->valid_until !== null && $page->valid_until->lt(CarbonImmutable::today())) {
            throw ValidationException::withMessages(['valid_until' => (string) __('knowledge_pages.error.expired', ['date' => CarbonFmt::fdate($page->valid_until)])]);
        }
    }

    private function nextReview(?int $months, CarbonImmutable $from): ?string {
        return $months !== null && $months > 0 ? $from->addMonthsNoOverflow($months)->toDateString() : null;
    }

    /** Übergeordnete Seite im selben Bereich, nicht die Seite selbst oder eine ihrer Unterseiten, Tiefe begrenzt. */
    private function assertParent(KnowledgeSpace $space, KnowledgePage $parent, ?KnowledgePage $page): void {
        if ((int) $parent->knowledge_space_id !== (int) $space->id) {
            throw ValidationException::withMessages(['parent' => (string) __('knowledge_pages.error.parent_space')]);
        }
        $depth = 1;
        $cursor = $parent;
        $seen = [];
        while ($cursor !== null) {
            if ($page !== null && (int) $cursor->id === (int) $page->id) {
                throw ValidationException::withMessages(['parent' => (string) __('knowledge_pages.error.parent_cycle')]);
            }
            if (isset($seen[$cursor->id])) {
                break;
            }
            $seen[$cursor->id] = true;
            $depth++;
            $cursor = $cursor->parent_id !== null ? KnowledgePage::query()->find($cursor->parent_id) : null;
        }
        $below = $page !== null ? $this->subtreeDepth($page) : 0;
        if ($depth + $below > KnowledgePage::MAX_DEPTH) {
            throw ValidationException::withMessages(['parent' => (string) __('knowledge_pages.error.depth', ['max' => KnowledgePage::MAX_DEPTH])]);
        }
    }

    /** Ebenen unterhalb der Seite (0 = keine Unterseiten). */
    private function subtreeDepth(KnowledgePage $page): int {
        $depth = 0;
        $level = [(int) $page->id];
        while ($level !== [] && $depth < KnowledgePage::MAX_DEPTH) {
            $level = KnowledgePage::query()->whereIn('parent_id', $level)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            if ($level !== []) {
                $depth++;
            }
        }

        return $depth;
    }

    private function moveDescendants(KnowledgePage $page): void {
        $level = [(int) $page->id];
        while ($level !== []) {
            KnowledgePage::query()->whereIn('parent_id', $level)->update([
                'knowledge_space_id' => $page->knowledge_space_id,
                'knowledge_section_id' => $page->knowledge_section_id,
            ]);
            $level = KnowledgePage::query()->whereIn('parent_id', $level)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        }
    }

    private function nextPosition(int $spaceId, ?int $sectionId, ?int $parentId): int {
        return (int) KnowledgePage::query()
            ->where('knowledge_space_id', $spaceId)
            ->where('knowledge_section_id', $sectionId)
            ->where('parent_id', $parentId)
            ->max('position') + 1;
    }

    private function body(?string $body): string {
        return rtrim(StringHelper::normalizeLineEndings($body, "\n"));
    }
}
