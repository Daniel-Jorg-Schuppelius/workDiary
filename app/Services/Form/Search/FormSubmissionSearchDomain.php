<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormSubmissionSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Form\Search;

use App\Enums\User\Permission;
use App\Models\Form\FormSubmission;
use App\Models\Platform\User;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use App\Support\CarbonFmt;
use Illuminate\Support\Facades\Gate;

/** Formulare (Feature 032): die Vorlagen-Sicht sieht alle Einreichungen, alle anderen nur die eigenen. Kein Kundenbezug. */
final class FormSubmissionSearchDomain implements SearchDomain {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'forms';
    }

    public function label(): string {
        return (string) __('form.title.submissions');
    }

    public function icon(): string {
        return 'edit_note';
    }

    public function order(): int {
        return 80;
    }

    public function available(User $user): bool {
        return $this->features->isEnabled('module.forms') && Gate::forUser($user)->allows('viewAny', FormSubmission::class);
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->customer !== null) {
            return [];
        }
        $query = FormSubmission::query()
            ->where('organization_id', $organizationId)
            ->with(['template', 'submitter'])
            ->whereHas('template', fn ($q) => $q->whereLikeEscaped('name', $term))
            ->when($filters->person !== null, fn ($q) => $q->where('submitted_by_user_id', $filters->person));
        if (! ($user->isAdmin() || $user->can(Permission::FormTemplateViewAny->value))) {
            $query->where('submitted_by_user_id', $user->id);
        }
        $filters->range($query, 'submitted_at');

        return array_values($query->orderByDesc('submitted_at')->limit($limit)->get()
            ->map(fn (FormSubmission $s) => [
                'id' => $s->id,
                'title' => $s->template->name ?? __('form.title.submissions') . ' #' . $s->id,
                'subtitle' => CarbonFmt::fdate(CarbonFmt::orgTz($s->submitted_at))
                    . ($s->submitter ? ' · ' . $s->submitter->name : ''),
                'url' => route('form-submissions.show', $s),
            ])
            ->all());
    }
}
