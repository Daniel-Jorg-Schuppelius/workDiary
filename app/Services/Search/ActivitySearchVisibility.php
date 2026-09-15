<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivitySearchVisibility.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search;

use App\Enums\Search\SearchSourceType;
use App\Enums\User\Permission;
use App\Models\{CommunicationNote, KnowledgeArticle, SearchDocument, User};
use App\Plugins\PluginManager;
use App\Plugins\RemoteSupport\RemoteSupportPlugin;
use App\Services\Licensing\FeatureFlagResolver;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Leserechte im Tätigkeitsindex (Feature 153, MVP-771) — ein Spiegel der
 * Policies der Detailseiten, nicht eine zweite Rechtelogik:
 *
 * - Zeiten: eigene; alle mit `timeEntry.viewAny` bzw. Buchhaltung.
 * - Aufträge: eigene/zugewiesene; alle mit `diary.viewAny`.
 * - Stundenzettel: eigene (Modul Planung).
 * - Tickets: `serviceTicket.view`; vertrauliche nur Bearbeitung, Watcher, Queue-Team (Modul Helpdesk).
 * - Protokolle: eigene; alle mit `protocol.viewAny`.
 * - Offene Punkte: Ersteller/Zuständige; alle mit `openIssue.viewAny`.
 * - Notizen: `communication.viewAny`; vertrauliche nur Erfasser oder `communication.confidential.manage`.
 * - Wissen: Veröffentlichtes + eigene Entwürfe; alles mit `knowledge.publish` (Modul Wissen).
 * - Offene Fernwartung: nur Admins bei aktivem Plugin.
 * - Lernkurse: eigene Einschreibung; alle mit `learning.viewAny` (Modul Lernplattform).
 *
 * Admins sehen alles ihrer Organisation; die Organisation filtert der Aufrufer.
 */
final class ActivitySearchVisibility {
    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly PluginManager $plugins,
    ) {}

    /**
     * Quellen, die der Nutzer überhaupt sehen darf (Filterauswahl).
     *
     * @return list<SearchSourceType>
     */
    public function types(User $user): array {
        return array_values(array_filter(SearchSourceType::cases(), fn(SearchSourceType $type): bool => $this->condition($type, $user) !== false));
    }

    /** @param  Builder<SearchDocument>  $query */
    public function apply(Builder $query, User $user): void {
        $conditions = [];
        foreach (SearchSourceType::cases() as $type) {
            $condition = $this->condition($type, $user);
            if ($condition !== false) {
                $conditions[$type->value] = $condition;
            }
        }

        $query->where(static function (Builder $outer) use ($conditions): void {
            if ($conditions === []) {
                $outer->whereRaw('1 = 0');

                return;
            }
            foreach ($conditions as $type => $condition) {
                $outer->orWhere(static function (Builder $inner) use ($type, $condition): void {
                    $inner->where('search_documents.source_type', $type);
                    if ($condition instanceof Closure) {
                        $inner->where($condition);
                    }
                });
            }
        });
    }

    /** false = unsichtbar, true = alle der Organisation, Closure = Einschränkung. */
    private function condition(SearchSourceType $type, User $user): bool|Closure {
        $admin = $user->isAdmin();
        $me = (int) $user->id;

        return match ($type) {
            SearchSourceType::TimeEntry => $admin || $user->canManageBilling() || $user->hasEffectivePermission(Permission::TimeEntryViewAny->value)
                ? true
                : self::own($me, 'user_id'),
            SearchSourceType::DiaryEntry => $admin || $user->can(Permission::DiaryViewAny->value)
                ? true
                : self::own($me, 'user_id', 'assigned_user_id'),
            SearchSourceType::Timesheet => ! $this->features->isEnabled('module.planung')
                ? false
                : ($admin ? true : self::own($me, 'user_id')),
            SearchSourceType::ServiceTicket => $this->ticketCondition($user),
            SearchSourceType::Protocol => $admin || $user->can(Permission::ProtocolViewAny->value)
                ? true
                : self::own($me, 'user_id'),
            SearchSourceType::OpenIssue => $admin || $user->can(Permission::OpenIssueViewAny->value)
                ? true
                : self::own($me, 'user_id', 'assigned_user_id'),
            SearchSourceType::CommunicationNote => ! Gate::forUser($user)->allows('viewAny', CommunicationNote::class)
                ? false
                : ($admin || $user->can(Permission::CommunicationConfidentialManage->value) ? true : self::unrestrictedOrOwn($me)),
            SearchSourceType::KnowledgeArticle => ! ($this->features->isEnabled('module.knowledge') && Gate::forUser($user)->allows('viewAny', KnowledgeArticle::class))
                ? false
                : ($admin || $user->can(Permission::KnowledgePublish->value) ? true : self::unrestrictedOrOwn($me)),
            SearchSourceType::RemoteSession => $admin && $this->plugins->enabled()->has(RemoteSupportPlugin::ID),
            SearchSourceType::LearningCourse => ! $this->features->isEnabled('module.lms')
                ? false
                : ($admin || $user->can(Permission::LearningViewAny->value) ? true : self::enrolled($me)),
        };
    }

    private function ticketCondition(User $user): bool|Closure {
        if (! $this->features->isEnabled('module.helpdesk')) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }
        if (! $user->can(Permission::ServiceTicketView->value)) {
            return false;
        }

        $me = (int) $user->id;

        return static function (Builder $query) use ($me): void {
            $query->where('search_documents.restricted', false)
                ->orWhere('search_documents.assigned_user_id', $me)
                ->orWhereIn('search_documents.source_id', static fn($watchers) => $watchers
                    ->select('service_ticket_id')
                    ->from('service_ticket_watchers')
                    ->where('user_id', $me))
                ->orWhereIn('search_documents.source_id', static fn($queues) => $queues
                    ->select('service_tickets.id')
                    ->from('service_tickets')
                    ->join('service_queues', 'service_queues.id', '=', 'service_tickets.queue_id')
                    ->join('team_user', 'team_user.team_id', '=', 'service_queues.team_id')
                    ->where('team_user.user_id', $me));
        };
    }

    /** Kurse, in die die Person selbst eingeschrieben ist. */
    private static function enrolled(int $me): Closure {
        return static function (Builder $query) use ($me): void {
            $query->whereIn('search_documents.source_id', static fn($enrollments) => $enrollments
                ->select('learning_course_id')
                ->from('learning_enrollments')
                ->where('user_id', $me));
        };
    }

    private static function own(int $userId, string ...$columns): Closure {
        return static function (Builder $query) use ($userId, $columns): void {
            foreach ($columns as $column) {
                $query->orWhere('search_documents.' . $column, $userId);
            }
        };
    }

    private static function unrestrictedOrOwn(int $userId): Closure {
        return static function (Builder $query) use ($userId): void {
            $query->where('search_documents.restricted', false)
                ->orWhere('search_documents.user_id', $userId);
        };
    }
}
