<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageOffboardingHandover.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages\Offboarding;

use App\Models\Knowledge\KnowledgePage;
use App\Models\Platform\User;
use App\Models\Scopes\OrganizationScope;
use App\Services\Knowledge\Pages\KnowledgePageService;
use App\Services\Org\Contracts\OffboardingHandover;
use Illuminate\Database\Eloquent\Builder;

/**
 * Verantwortung für Wissensseiten an eine Nachfolge (Feature 191/202) — beim
 * Austritt und bei der endgültigen Übergabe ohne Austritt. Übertragen werden
 * auch Seiten im Papierkorb, damit eine wiederhergestellte Seite nicht bei
 * der ausgetretenen Person bleibt; Prüfintervall und Freigabe bleiben, wie
 * sie sind.
 */
final class KnowledgePageOffboardingHandover implements OffboardingHandover {
    public function __construct(private readonly KnowledgePageService $pages) {}

    public function key(): string {
        return 'knowledge_pages';
    }

    public function label(int $count): string {
        return trans_choice('knowledge_pages.handover.label', $count, ['count' => $count]);
    }

    public function count(User $member): int {
        return $this->owned($member)->count();
    }

    public function handOver(User $member, User $successor, User $actor): int {
        $moved = 0;
        foreach ($this->owned($member)->orderBy('id')->get() as $page) {
            $this->pages->transferOwnership($page, $successor, $actor);
            $moved++;
        }

        return $moved;
    }

    /** @return Builder<KnowledgePage> */
    private function owned(User $member): Builder {
        return KnowledgePage::query()->withoutGlobalScope(OrganizationScope::class)->withTrashed()
            ->where('organization_id', $member->organization_id)
            ->where('owner_user_id', $member->id);
    }
}
