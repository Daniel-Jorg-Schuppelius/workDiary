<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageVisits.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages;

use App\Models\Knowledge\{KnowledgePage, KnowledgePageVisit};
use App\Models\Platform\User;
use Carbon\CarbonImmutable;

/**
 * „Geändert seit Ihrem letzten Besuch“ (Feature 191, MVP-1667): je Person und
 * Seite die zuletzt gesehene Fassung. Gezeigt wird der Unterschied zwischen
 * dieser und der Fassung, die die Person jetzt sieht.
 */
final class KnowledgePageVisits {
    public function __construct(private readonly KnowledgePageAccess $access) {}

    /** Besuch vermerken; liefert die bis dahin gesehene Fassung (null = erster Besuch). */
    public function record(KnowledgePage $page, User $user): ?int {
        $shown = $this->access->displayedVersion($page, $user);
        if ($shown === null) {
            return null;
        }
        $visit = KnowledgePageVisit::query()
            ->where('knowledge_page_id', $page->id)
            ->where('user_id', $user->id)
            ->first();
        $previous = $visit?->seen_version;
        if ($visit === null) {
            KnowledgePageVisit::query()->create([
                'organization_id' => $page->organization_id,
                'knowledge_page_id' => $page->id,
                'user_id' => $user->id,
                'seen_version' => $shown,
                'visited_at' => CarbonImmutable::now(),
            ]);
        } else {
            $visit->forceFill(['seen_version' => $shown, 'visited_at' => CarbonImmutable::now()])->save();
        }

        return $previous;
    }

    /**
     * Seiten der Liste, die sich seit dem letzten Besuch geändert haben.
     *
     * @param  iterable<KnowledgePage>  $pages
     * @return array<int, true> Seiten-ID → geändert
     */
    public function changedSinceLastVisit(iterable $pages, User $user): array {
        $byId = [];
        foreach ($pages as $page) {
            $byId[(int) $page->id] = $page;
        }
        if ($byId === []) {
            return [];
        }
        $changed = [];
        $visits = KnowledgePageVisit::query()
            ->where('user_id', $user->id)
            ->whereIn('knowledge_page_id', array_keys($byId))
            ->get(['knowledge_page_id', 'seen_version']);
        foreach ($visits as $visit) {
            $page = $byId[(int) $visit->knowledge_page_id];
            $shown = $this->access->displayedVersion($page, $user);
            if ($shown !== null && $visit->seen_version < $shown) {
                $changed[(int) $page->id] = true;
            }
        }

        return $changed;
    }
}
