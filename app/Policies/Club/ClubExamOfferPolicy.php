<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubExamOfferPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Club;

use App\Enums\User\Permission as P;
use App\Models\Club\ClubExamOffer;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Prüfungsangebote (MVP-847): Register, Gruppenleitung, Graduierungspflege
 * und Prüfer sehen; Pflege und Zulassung mit club.grading.manage; Ergebnisse
 * erfassen Prüfer des Angebots oder Inhaber von club.exams.examine.
 */
class ClubExamOfferPolicy {
    use ClubAccess;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $this->canReadRegister($user) || $this->isGroupLead($user) || $this->canGrade($user) || $user->can(P::ClubExaminer->value);
    }

    public function view(User $user, ClubExamOffer $offer): bool {
        return $this->viewAny($user) || $offer->isExaminer($user);
    }

    public function create(User $user): bool {
        return $this->canGrade($user);
    }

    public function update(User $user, ClubExamOffer $offer): bool {
        unset($offer);

        return $this->canGrade($user);
    }

    /** Kandidaten anlegen, zulassen, ablehnen, freigeben, Überprüfung abschließen. */
    public function manageCandidates(User $user, ClubExamOffer $offer): bool {
        unset($offer);

        return $this->canGrade($user);
    }

    /** Ausnahmezulassung — eigenes Recht laut Fachkonzept: hier die Graduierungspflege. */
    public function admitByException(User $user, ClubExamOffer $offer): bool {
        unset($offer);

        return $this->canGrade($user);
    }

    public function recordResult(User $user, ClubExamOffer $offer): bool {
        return $offer->isExaminer($user) || $user->can(P::ClubExaminer->value) || $this->canGrade($user);
    }

    private function canGrade(User $user): bool {
        return $user->can(P::ClubGradingManage->value);
    }
}
