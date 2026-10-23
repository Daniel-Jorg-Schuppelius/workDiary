<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Survey\Cockpit;

use App\Enums\Survey\SurveyInvitationStatus;
use App\Enums\User\Permission;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Survey\{SurveyAnswer, SurveyInvitation};
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Survey\SurveyService;
use App\Support\CarbonFmt;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Umfragen beim Kunden (Feature 090): Einladungen, Rücklauf und NPS über die
 * Antworten seiner Einladungen — dieselbe Formel wie die Umfrage-Auswertung.
 */
final class SurveyCockpitPanel implements CockpitPanel {
    private const LIMIT = 5;

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function order(): int {
        return 50;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Customer;
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! $this->features->routeEnabled('surveys.show') || ! Gate::forUser($viewer)->allows(Permission::CustomerViewAny->value)) {
            return null;
        }
        $invitations = SurveyInvitation::query()->where('customer_id', $subject->getKey());
        $total = (clone $invitations)->count();
        if ($total === 0) {
            return null;
        }
        $responded = (clone $invitations)->where('status', SurveyInvitationStatus::Responded)->count();
        $scores = SurveyAnswer::query()
            ->whereHas('question', static fn ($q) => $q->where('type', 'nps'))
            ->whereIn('survey_response_id', static fn ($q) => $q->select('id')->from('survey_responses')
                ->whereIn('survey_invitation_id', (clone $invitations)->select('id')))
            ->whereBetween('value_int', [0, 10])
            ->pluck('value_int')
            ->map(static fn ($v): int => (int) $v)
            ->all();
        $nps = SurveyService::nps($scores);

        return view('cockpit._list', [
            'id' => 'cockpit-surveys',
            'title' => __('cockpit.surveys.title'),
            'icon' => 'reviews',
            'count' => $total,
            'summary' => $nps !== null
                ? __('cockpit.surveys.summary_nps', ['nps' => $nps, 'answers' => count($scores), 'responded' => $responded, 'total' => $total])
                : __('cockpit.surveys.summary', ['responded' => $responded, 'total' => $total]),
            'rows' => array_map(static fn (SurveyInvitation $i): array => [
                'title' => (string) $i->survey?->title,
                'url' => $i->survey !== null ? route('surveys.show', $i->survey) : null,
                'subtitle' => null,
                'badge' => $i->status->label(),
                'tone' => $i->status === SurveyInvitationStatus::Responded ? 'success' : 'ghost',
                'date' => ($i->responded_at ?? $i->sent_at) !== null ? CarbonFmt::fdate($i->responded_at ?? $i->sent_at) : null,
            ], $invitations->with('survey:id,title')->orderByDesc('id')->limit(self::LIMIT)->get()->all()),
        ]);
    }
}
