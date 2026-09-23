<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubPortalContext.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Models\Club\{ClubGuardian, ClubMember};
use App\Models\Platform\User;
use App\Support\Query\DateRange;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\{Carbon, Collection};

/**
 * Wer darf in „Mein Verein“ für wen handeln (MVP-845): das eigene, verknüpfte
 * Mitglied zuerst, dann jedes Mitglied mit aktiver, nicht widerrufener
 * Vertretung. Die Auswahl liegt in der Session; ein Widerruf beendet den
 * Zugriff beim nächsten Aufruf, weil die Liste jedes Mal neu berechnet wird.
 */
class ClubPortalContext {
    public const SESSION_KEY = 'club.my.member_id';

    public function __construct(
        private readonly Session $session,
    ) {}

    /** @return Collection<int, ClubPortalSubject> Schlüssel = Mitglieds-ID */
    public function subjectsFor(User $user): Collection {
        $today = Carbon::today();
        $subjects = collect();

        /** @var ClubMember|null $own */
        $own = ClubMember::query()->where('user_id', $user->id)->current($today)->first();
        if ($own !== null) {
            $subjects->put($own->id, new ClubPortalSubject($own, null));
        }

        $guardianships = ClubGuardian::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where(fn($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<', DateRange::dayAfter($today)))
            ->where(fn($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>=', DateRange::day($today)))
            ->whereHas('member', fn($member) => $member->current($today))
            ->with('member')
            ->get();
        foreach ($guardianships as $guardian) {
            $member = $guardian->member;
            if ($member !== null && ! $subjects->has($member->id)) {
                $subjects->put($member->id, new ClubPortalSubject($member, $guardian));
            }
        }

        return $subjects;
    }

    public function hasSubjects(User $user): bool {
        return $this->subjectsFor($user)->isNotEmpty();
    }

    /** Ausgewähltes Mitglied (Session), sonst das erste — Vertretungen wählen ausdrücklich. */
    public function current(User $user): ?ClubPortalSubject {
        $subjects = $this->subjectsFor($user);
        $selected = (int) $this->session->get(self::SESSION_KEY, 0);
        if ($selected > 0 && $subjects->has($selected)) {
            return $subjects->get($selected);
        }

        return $subjects->first();
    }

    public function select(User $user, int $memberId): ?ClubPortalSubject {
        $subject = $this->subjectsFor($user)->get($memberId);
        if ($subject !== null) {
            $this->session->put(self::SESSION_KEY, $memberId);
        }

        return $subject;
    }
}
