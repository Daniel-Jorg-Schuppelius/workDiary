<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Survey\Demo;

use App\Models\Platform\Organization;
use App\Models\Survey\{Survey, SurveyAnswer, SurveyQuestion};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Services\Survey\SurveyService;
use Illuminate\Support\Carbon;

/**
 * Kundenzufriedenheit zur Vorführung (MVP-1288): ein NPS-Fragebogen mit
 * Antworten aus den letzten sechs Monaten, damit Auswertung und Verlauf etwas
 * zeigen.
 */
final class SurveyDemoBlock implements DemoBlock {
    public function __construct(private readonly SurveyService $surveys) {}

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $organization = $context->organization;

        $survey = Survey::query()->create([
            'organization_id' => $organization->id,
            'title' => (string) __('Kundenzufriedenheit nach Einsatz (Demo)'),
            'purpose' => (string) __('Kurze Rückmeldung nach abgeschlossenen Aufträgen und Tickets.'),
            'active' => true,
            'anonymous' => false,
            'trigger_on_ticket_close' => true,
            'created_by' => $context->actor->id,
        ]);
        /** @var array{nps: SurveyQuestion, scale: SurveyQuestion, text: SurveyQuestion} $questions */
        $questions = [];
        foreach ([
            ['nps', (string) __('Wie wahrscheinlich ist es, dass Sie uns weiterempfehlen?'), true],
            ['scale', (string) __('Wie zufrieden waren Sie mit der Erreichbarkeit?'), true],
            ['text', (string) __('Was können wir besser machen?'), false],
        ] as $position => [$type, $label, $required]) {
            $questions[$type] = SurveyQuestion::query()->create([
                'organization_id' => $organization->id,
                'survey_id' => $survey->id,
                'type' => $type,
                'label' => $label,
                'required' => $required,
                'position' => $position + 1,
            ]);
        }

        // Je Monat ein bis zwei Antworten — der NPS steigt über das Halbjahr.
        $answers = [[5, 6, 3], [4, 7, 3], [4, 8, 4], [3, 9, 4], [2, 10, 5], [2, 9, 5], [1, 10, 5], [1, 6, 3]];
        foreach ($answers as $i => [$monthsAgo, $nps, $scale]) {
            $issued = $this->surveys->invite($survey, 'umfrage' . ($i + 1) . '.demo@example.com', null, 'manual');
            $response = $this->surveys->submit($issued['invitation'], [
                $questions['nps']->id => $nps,
                $questions['scale']->id => $scale,
                $questions['text']->id => $nps <= 6 ? (string) __('Rückruf kam erst am nächsten Tag.') : null,
            ]);
            $at = Carbon::now()->subMonths($monthsAgo)->startOfMonth()->addDays(10 + $i);
            $response->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
            SurveyAnswer::query()->where('survey_response_id', $response->id)->update(['created_at' => $at, 'updated_at' => $at]);
            $issued['invitation']->forceFill(['sent_at' => $at->copy()->subDay(), 'responded_at' => $at])->save();
        }

        return ['surveys' => 1, 'survey_responses' => count($answers)];
    }

    public function purge(Organization $organization): void {
        Survey::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
    }
}
