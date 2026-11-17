<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeRecordsSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\Knowledge\{KnowledgePage, KnowledgePageRead, KnowledgePageVisit};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Wissensdatenbank in der Betroffenenauskunft (Feature 191 × Feature 129):
 * Lesebestätigungen der Pflichtlektüre (welche Fassung wann bestätigt) und
 * Seitenbesuche (welche Fassung zuletzt gesehen) einer Person. Die Liste
 * nennt den Seitentitel — auch für Seiten im Papierkorb; Seiteninhalte
 * gehören nicht in die Auskunft.
 */
class KnowledgeRecordsSection extends AbstractSubjectSection {
    public function key(): string {
        return 'knowledge';
    }

    public function title(): string {
        return __('knowledge_pages.privacy.title');
    }

    public function portable(): bool {
        return false;
    }

    public function build(Model $subject): array {
        $this->expect($subject, User::class);
        /** @var User $u */
        $u = $subject;
        $orgId = (int) $u->organization_id;

        $reads = KnowledgePageRead::query()->withoutGlobalScopes()
            ->where('organization_id', $orgId)->where('user_id', $u->id);
        $visits = KnowledgePageVisit::query()->withoutGlobalScopes()
            ->where('organization_id', $orgId)->where('user_id', $u->id);

        $readRows = (clone $reads)->orderBy('confirmed_at')->orderBy('id')->get();
        $visitRows = (clone $visits)->orderBy('visited_at')->orderBy('id')->get();
        // Titel auch für Seiten im Papierkorb (withoutGlobalScopes hebt SoftDeletes mit auf).
        $titles = KnowledgePage::query()->withoutGlobalScopes()
            ->whereIn('id', $readRows->pluck('knowledge_page_id')->merge($visitRows->pluck('knowledge_page_id'))->unique()->all())
            ->pluck('title', 'id');

        $readList = [];
        foreach ($readRows as $read) {
            $readList[] = [
                'page' => $this->str($titles->get($read->knowledge_page_id)),
                'version' => (string) $read->version,
                'confirmed_at' => $this->str($read->confirmed_at),
            ];
        }
        $visitList = [];
        foreach ($visitRows as $visit) {
            $visitList[] = [
                'page' => $this->str($titles->get($visit->knowledge_page_id)),
                'seen_version' => (string) $visit->seen_version,
                'visited_at' => $this->str($visit->visited_at),
            ];
        }

        return [
            'lists' => [
                __('knowledge_pages.privacy.reads') => $readList,
                __('knowledge_pages.privacy.visits') => $visitList,
            ],
            'families' => [
                $this->family('knowledge_page_reads', __('knowledge_pages.privacy.reads'), $reads, 'confirmed_at'),
                $this->family('knowledge_page_visits', __('knowledge_pages.privacy.visits'), $visits, 'visited_at'),
            ],
        ];
    }
}
