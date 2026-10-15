<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourProposalFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Diary;

use App\Enums\Tour\TourProposalStatus;
use App\Models\Diary\TourProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourProposal>
 */
class TourProposalFactory extends Factory {
    protected $model = TourProposal::class;

    public function definition(): array {
        return [
            'organization_id' => null,
            'tour_id' => null,
            'proposal_date' => now()->toDateString(),
            'solver' => 'heuristic',
            'is_estimate' => true,
            'status' => TourProposalStatus::Computing,
        ];
    }
}
