<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeReadingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages;

use App\Enums\Knowledge\{KnowledgeAudienceKind, KnowledgePageStatus};
use App\Models\Knowledge\{KnowledgePage, KnowledgePageAssignment, KnowledgePageRead, KnowledgePageVersion};
use App\Models\Platform\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\{Builder, Collection};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Pflichtlektüre mit Lesebestätigung je Fassung (Feature 191, MVP-1666).
 *
 * Bestätigt wird immer die freigegebene Fassung, und zwar nur, solange die
 * Seite gilt. Eine neu freigegebene Fassung verlangt eine neue Bestätigung —
 * wie die Kenntnisnahme in der Personalakte. Wer eine Seite lesen muss, ergibt
 * sich aus den Zuordnungen (alle, Rolle, Team, Person) und dem Leserecht des
 * Bereichs; ehemalige und Portal-Konten fallen heraus.
 */
final class KnowledgeReadingService {
    public function __construct(private readonly KnowledgePageAccess $access) {}

    public function assign(KnowledgePage $page, User $actor, KnowledgeAudienceKind $kind, ?string $roleName, ?int $teamId, ?int $userId, bool $onboarding): KnowledgePageAssignment {
        $attributes = [
            'knowledge_page_id' => $page->id,
            'kind' => $kind->value,
            'role_name' => $kind === KnowledgeAudienceKind::Role ? $roleName : null,
            'team_id' => $kind === KnowledgeAudienceKind::Team ? $teamId : null,
            'user_id' => $kind === KnowledgeAudienceKind::User ? $userId : null,
        ];
        $existing = KnowledgePageAssignment::query()->where($attributes)->first();
        if ($existing instanceof KnowledgePageAssignment) {
            $existing->forceFill(['is_onboarding' => $onboarding || $existing->is_onboarding])->save();

            return $existing;
        }
        $assignment = KnowledgePageAssignment::query()->create([
            ...$attributes,
            'organization_id' => $page->organization_id,
            'is_onboarding' => $onboarding,
            'created_by' => $actor->id,
        ]);
        $page->audit('knowledge_page.assigned', ['kind' => $kind->value, 'role' => $attributes['role_name'], 'team_id' => $attributes['team_id'], 'user_id' => $attributes['user_id'], 'onboarding' => $onboarding]);

        return $assignment;
    }

    public function unassign(KnowledgePageAssignment $assignment): void {
        $page = $assignment->page;
        $assignment->delete();
        $page?->audit('knowledge_page.unassigned', ['kind' => $assignment->kind->value, 'role' => $assignment->role_name, 'team_id' => $assignment->team_id, 'user_id' => $assignment->user_id]);
    }

    /**
     * Wer die Seite lesen muss — nur interne, aktive Mitglieder mit Leserecht
     * auf den Bereich.
     *
     * @return Collection<int, User>
     */
    public function requiredReaders(KnowledgePage $page): Collection {
        $assignments = $page->relationLoaded('assignments') ? $page->assignments : $page->assignments()->get();
        if ($assignments->isEmpty()) {
            return new Collection();
        }
        $organizationId = (int) $page->organization_id;
        $query = $this->members($organizationId);
        if (! $assignments->contains(static fn (KnowledgePageAssignment $a): bool => $a->kind === KnowledgeAudienceKind::All)) {
            $roles = array_values(array_map('strval', $assignments->where('kind', KnowledgeAudienceKind::Role)->pluck('role_name')->filter()->all()));
            $teams = $assignments->where('kind', KnowledgeAudienceKind::Team)->pluck('team_id')->filter()->values()->all();
            $users = $assignments->where('kind', KnowledgeAudienceKind::User)->pluck('user_id')->filter()->values()->all();
            $query->where(function (Builder $match) use ($roles, $teams, $users, $organizationId): void {
                $match->whereIn('users.id', $users === [] ? [0] : $users);
                if ($teams !== []) {
                    $match->orWhereHas('teams', static fn (Builder $team) => $team->whereIn('teams.id', $teams));
                }
                if ($roles !== []) {
                    $match->orWhereIn('users.id', $this->userIdsWithRoles($roles, $organizationId));
                }
            });
        }

        return $query->orderBy('name')->get()
            ->filter(fn (User $user): bool => $this->access->canReadSpace($user, (int) $page->knowledge_space_id))
            ->values();
    }

    /** Gilt die Seite für diese Person als Pflichtlektüre? */
    public function isRequiredFor(KnowledgePage $page, User $user): bool {
        return ! $user->isCustomer()
            && array_key_exists((int) $page->id, $this->assignedPages($user))
            && $this->access->canReadSpace($user, (int) $page->knowledge_space_id);
    }

    public function hasConfirmedCurrent(KnowledgePage $page, User $user): bool {
        return $page->approved_version !== null && KnowledgePageRead::query()
            ->where('knowledge_page_id', $page->id)
            ->where('version', $page->approved_version)
            ->where('user_id', $user->id)
            ->exists();
    }

    /** Lesebestätigung der freigegebenen Fassung. */
    public function confirm(KnowledgePage $page, User $user): KnowledgePageRead {
        if ($page->approved_version === null || $page->status !== KnowledgePageStatus::Valid) {
            throw ValidationException::withMessages(['page' => (string) __('knowledge_pages.reading.error.not_valid')]);
        }
        $version = KnowledgePageVersion::query()
            ->where('knowledge_page_id', $page->id)
            ->where('version', $page->approved_version)
            ->firstOrFail();

        return DB::transaction(static function () use ($page, $user, $version): KnowledgePageRead {
            $read = KnowledgePageRead::query()->firstOrCreate(
                ['knowledge_page_id' => $page->id, 'version' => $version->version, 'user_id' => $user->id],
                ['organization_id' => $page->organization_id, 'knowledge_page_version_id' => $version->id, 'confirmed_at' => CarbonImmutable::now()],
            );

            return $read;
        });
    }

    /**
     * Offene Pflichtlektüre einer Person: gültige Seiten, deren freigegebene
     * Fassung sie noch nicht bestätigt hat — Einarbeitung zuerst.
     *
     * @return list<array{page: KnowledgePage, onboarding: bool, confirmed_version: int|null}>
     */
    public function openFor(User $user): array {
        $rows = [];
        foreach ($this->assignedPages($user) as $pageId => $onboarding) {
            $rows[$pageId] = $onboarding;
        }
        if ($rows === []) {
            return [];
        }
        $pages = $this->access->scope(KnowledgePage::query(), $user)
            ->whereKey(array_keys($rows))
            ->where('status', KnowledgePageStatus::Valid->value)
            ->whereNotNull('approved_version')
            ->with(['space:id,title'])
            ->orderBy('title')
            ->get();
        $confirmed = KnowledgePageRead::query()
            ->where('user_id', $user->id)
            ->whereIn('knowledge_page_id', $pages->modelKeys())
            ->get(['knowledge_page_id', 'version'])
            ->groupBy('knowledge_page_id')
            ->map(static fn ($reads): int => (int) $reads->max('version'));

        $open = [];
        foreach ($pages as $page) {
            $last = $confirmed->get($page->id);
            if ($last !== null && $last >= (int) $page->approved_version) {
                continue;
            }
            $open[] = ['page' => $page, 'onboarding' => $rows[$page->id], 'confirmed_version' => $last];
        }
        usort($open, static fn (array $a, array $b): int => [$b['onboarding'], $a['page']->title] <=> [$a['onboarding'], $b['page']->title]);

        return $open;
    }

    /**
     * Einarbeitungsliste der Person: alle Seiten ihrer Pflichtlektüre mit
     * Einarbeitungsvermerk, gelesen oder nicht.
     *
     * @return list<array{page: KnowledgePage, confirmed: bool}>
     */
    public function onboardingFor(User $user): array {
        $ids = array_keys(array_filter($this->assignedPages($user)));
        if ($ids === []) {
            return [];
        }
        $pages = $this->access->scope(KnowledgePage::query(), $user)
            ->whereKey($ids)
            ->whereNotNull('approved_version')
            ->orderBy('title')
            ->get();
        $reads = KnowledgePageRead::query()->where('user_id', $user->id)->whereIn('knowledge_page_id', $pages->modelKeys())
            ->get(['knowledge_page_id', 'version']);

        return array_values($pages->map(static fn (KnowledgePage $page): array => [
            'page' => $page,
            'confirmed' => $reads->contains(static fn (KnowledgePageRead $read): bool => (int) $read->knowledge_page_id === (int) $page->id && $read->version >= (int) $page->approved_version),
        ])->all());
    }

    /**
     * Übersicht für Verantwortliche: wer welche Fassung bestätigt hat.
     *
     * @return list<array{user: User, version: int|null, confirmed_at: \Illuminate\Support\Carbon|null, current: bool}>
     */
    public function overview(KnowledgePage $page): array {
        $readers = $this->requiredReaders($page);
        $latest = KnowledgePageRead::query()
            ->where('knowledge_page_id', $page->id)
            ->whereIn('user_id', $readers->modelKeys())
            ->orderByDesc('version')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');

        $rows = [];
        foreach ($readers as $reader) {
            $read = $latest->get($reader->id);
            $rows[] = [
                'user' => $reader,
                'version' => $read?->version,
                'confirmed_at' => $read?->confirmed_at,
                'current' => $read !== null && $page->approved_version !== null && $read->version >= $page->approved_version,
            ];
        }

        return $rows;
    }

    /**
     * Zugeordnete Seiten der Person: Seiten-ID → Einarbeitung ja/nein.
     *
     * @return array<int, bool>
     */
    private function assignedPages(User $user): array {
        $roles = $this->access->roleNames($user);
        $teams = $this->access->teamIds($user);
        $assignments = KnowledgePageAssignment::query()
            ->where('organization_id', $user->organization_id)
            ->where(static function (Builder $match) use ($user, $roles, $teams): void {
                $match->where('kind', KnowledgeAudienceKind::All->value)
                    ->orWhere(static fn (Builder $q) => $q->where('kind', KnowledgeAudienceKind::User->value)->where('user_id', $user->id));
                if ($teams !== []) {
                    $match->orWhere(static fn (Builder $q) => $q->where('kind', KnowledgeAudienceKind::Team->value)->whereIn('team_id', $teams));
                }
                if ($roles !== []) {
                    $match->orWhere(static fn (Builder $q) => $q->where('kind', KnowledgeAudienceKind::Role->value)->whereIn('role_name', $roles));
                }
            })
            ->get(['knowledge_page_id', 'is_onboarding']);

        $pages = [];
        foreach ($assignments as $assignment) {
            $id = (int) $assignment->knowledge_page_id;
            $pages[$id] = ($pages[$id] ?? false) || $assignment->is_onboarding;
        }

        return $pages;
    }

    /** @return Builder<User> interne, aktive Mitglieder der Organisation */
    private function members(int $organizationId): Builder {
        return User::query()->forOrganization($organizationId)
            ->whereNull('customer_id')
            ->whereNull('deactivated_at');
    }

    /**
     * @param  list<string>  $roles
     * @return list<int>
     */
    private function userIdsWithRoles(array $roles, int $organizationId): array {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($organizationId);
        try {
            return array_values(array_map('intval', $this->members($organizationId)
                ->whereHas('roles', static fn (Builder $role) => $role->whereIn('name', $roles))
                ->pluck('users.id')
                ->all()));
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
