<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningGamificationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\LearningEnrollmentStatus;
use App\Models\Learning\LearningEnrollment;
use App\Models\{Organization, User};

/**
 * Punkte, Abzeichen und Bestenliste (Feature 149, MVP-747).
 *
 * **Doppelt abgesichert und im Auslieferungszustand aus:** eine Rangliste
 * über Beschäftigte ist mitbestimmungspflichtig (§ 87 Abs. 1 Nr. 6 BetrVG)
 * und ohne Regelung eine Zumutung. Deshalb
 *
 *  1. muss die Organisation Gamification **und** die Bestenliste
 *     ausdrücklich einschalten (Vorgabe: beides aus), und
 *  2. erscheint eine Person in der Bestenliste nur mit **eigenem Opt-in**.
 *
 * Für Kundenkurse ist das unkritisch — dort darf die Organisation die
 * Liste vorbelegen; die persönliche Zustimmung bleibt trotzdem nötig.
 */
class LearningGamificationService {
    /** Abzeichen: Schwelle → Schlüssel. Bewusst wenige, sonst wird es Kitsch. */
    private const BADGES = [
        1 => 'first_course',
        5 => 'five_courses',
        10 => 'ten_courses',
    ];

    public function isEnabled(?Organization $organization): bool {
        return (bool) ($organization->settings['learning']['gamification']['enabled'] ?? false);
    }

    public function isLeaderboardEnabled(?Organization $organization): bool {
        return $this->isEnabled($organization)
            && (bool) ($organization->settings['learning']['gamification']['leaderboard'] ?? false);
    }

    /** Persönliches Opt-in — ohne das erscheint niemand in der Liste. */
    public function hasOptedIn(User $user): bool {
        return (bool) ($user->preferences['learning']['leaderboard_opt_in'] ?? false);
    }

    public function pointsFor(User $user): int {
        return (int) LearningEnrollment::query()
            ->where('user_id', $user->id)
            ->where('status', LearningEnrollmentStatus::Completed->value)
            ->sum('points_earned');
    }

    public function completedCoursesFor(User $user): int {
        return LearningEnrollment::query()
            ->where('user_id', $user->id)
            ->where('status', LearningEnrollmentStatus::Completed->value)
            ->count();
    }

    /**
     * Erreichte Abzeichen einer Person.
     *
     * @return list<string>
     */
    public function badgesFor(User $user): array {
        $completed = $this->completedCoursesFor($user);
        $badges = [];

        foreach (self::BADGES as $threshold => $key) {
            if ($completed >= $threshold) {
                $badges[] = $key;
            }
        }

        return $badges;
    }

    /**
     * Bestenliste — leer, solange die Organisation sie nicht eingeschaltet
     * hat. Aufgeführt werden ausschließlich Personen mit eigenem Opt-in.
     *
     * @return list<array{user: User, points: int}>
     */
    public function leaderboard(?Organization $organization, int $limit = 10): array {
        if (! $this->isLeaderboardEnabled($organization)) {
            return [];
        }

        $rows = LearningEnrollment::query()
            ->selectRaw('user_id, SUM(points_earned) as total')
            ->where('organization_id', $organization?->id)
            ->where('status', LearningEnrollmentStatus::Completed->value)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('total')
            // Großzügig laden: das Opt-in filtert erst danach.
            ->limit(max(1, $limit) * 5)
            ->get();

        $users = User::query()
            ->whereIn('id', $rows->pluck('user_id'))
            ->get()
            ->keyBy('id');

        $board = [];
        foreach ($rows as $row) {
            $user = $users->get($row->user_id);

            if ($user === null || ! $this->hasOptedIn($user)) {
                continue;
            }

            // `total` stammt aus dem SELECT-Aggregat, nicht aus dem Modell.
            $board[] = ['user' => $user, 'points' => (int) $row->getAttribute('total')];

            if (count($board) >= $limit) {
                break;
            }
        }

        return $board;
    }
}
