<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\OpenIssue\OpenIssueSeverity;
use App\Enums\OpenIssue\OpenIssueSource;
use App\Enums\OpenIssue\OpenIssueStatus;
use App\Enums\OpenIssue\OpenIssueVisibility;
use App\Models\DiaryEntry;
use App\Models\OpenIssue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpenIssue>
 */
class OpenIssueFactory extends Factory {
    protected $model = OpenIssue::class;

    public function definition(): array {
        return [
            'subject_type' => DiaryEntry::class,
            'subject_id' => DiaryEntry::factory(),
            'source_type' => OpenIssueSource::Manual->value,
            'source_ref_id' => null,
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'category' => null,
            'severity' => OpenIssueSeverity::Low->value,
            'status' => OpenIssueStatus::Open->value,
            'assignee_user_id' => null,
            'due_at' => null,
            'visibility' => OpenIssueVisibility::Internal->value,
            'closed_at' => null,
            'closed_by_user_id' => null,
            'closed_reason' => null,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function critical(): self {
        return $this->state(fn () => [
            'severity' => OpenIssueSeverity::Critical->value,
            'due_at' => now()->addDays(7),
            'assignee_user_id' => User::factory(),
        ]);
    }

    public function inProgress(): self {
        return $this->state(fn () => [
            'status' => OpenIssueStatus::InProgress->value,
            'assignee_user_id' => User::factory(),
        ]);
    }

    public function done(): self {
        return $this->state(fn () => [
            'status' => OpenIssueStatus::Done->value,
            'closed_at' => now(),
        ]);
    }

    public function customerVisible(): self {
        return $this->state(fn () => [
            'visibility' => OpenIssueVisibility::Customer->value,
        ]);
    }
}
