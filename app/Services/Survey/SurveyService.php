<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Survey;

use App\Models\Customer;
use App\Models\Survey\{Survey, SurveyAnswer, SurveyInvitation, SurveyResponse};
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Umfrage-Engine (Feature 090, MVP-660–662): Einladung, Teilnahme,
 * NPS-Auswertung.
 *
 * Zwei Pflichtbestandteile, keine Optionen:
 *
 * 1. **Ermüdungsschutz** — je E-Mail höchstens eine Einladung im
 *    Deckel-Fenster (Default 90 Tage, `surveys.fatigue_days`), über ALLE
 *    Fragebögen der Organisation hinweg. Beim Trigger wird still
 *    übersprungen, beim manuellen Versand mit Fehlermeldung abgelehnt.
 * 2. **Anonymität als Speicher-Eigenschaft** — anonyme Antworten tragen
 *    keinen Einladungsbezug und die Einladung keinen Antwortzeitpunkt.
 *    Und weil das rückwirkend nicht reparierbar wäre: Nach der ersten
 *    Einladung ist `anonymous` eingefroren.
 */
class SurveyService {
    /**
     * @return array{invitation: SurveyInvitation, token: string}
     */
    public function invite(Survey $survey, string $email, ?Customer $customer = null, string $contextKind = 'manual'): array {
        if (! $survey->active) {
            throw new RuntimeException((string) __('Dieser Fragebogen ist nicht aktiv.'));
        }
        if ($survey->questions()->count() === 0) {
            throw new RuntimeException((string) __('Ohne Fragen keine Einladung.'));
        }
        if ($customer !== null && $customer->survey_opt_out) {
            throw new RuntimeException((string) __('Dieser Kunde hat Umfragen widersprochen (Opt-out).'));
        }
        if (! $this->fatigueAllows($survey, $email)) {
            throw new RuntimeException((string) __('Ermüdungsschutz: Diese Adresse wurde innerhalb der letzten :days Tage bereits eingeladen.', [
                'days' => $this->fatigueDays(),
            ]));
        }

        $token = Str::random(48);
        $invitation = SurveyInvitation::query()->create([
            'organization_id' => $survey->organization_id,
            'survey_id' => $survey->id,
            'customer_id' => $customer?->id,
            'email' => mb_strtolower(trim($email)),
            'context_kind' => $contextKind,
            'token_hash' => SurveyInvitation::hashToken($token),
            'expires_at' => Carbon::now()->addDays(30),
            'sent_at' => Carbon::now(),
            'status' => SurveyInvitation::STATUS_SENT,
        ]);

        $survey->audit('survey.invited', ['context' => $contextKind]);

        return ['invitation' => $invitation, 'token' => $token];
    }

    /** Greift der Ermüdungs-Deckel für diese Adresse noch nicht? */
    public function fatigueAllows(Survey $survey, string $email): bool {
        return ! SurveyInvitation::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $survey->organization_id)
            ->where('email', mb_strtolower(trim($email)))
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', Carbon::now()->subDays($this->fatigueDays()))
            ->exists();
    }

    public function fatigueDays(): int {
        return max(1, (int) config('surveys.fatigue_days', 90));
    }

    /**
     * Nimmt die Teilnahme entgegen.
     *
     * @param array<int, int|string|array<array-key, mixed>|null> $answers Frage-ID → Wert (Arrays wehrt die Prüfung ab)
     */
    public function submit(SurveyInvitation $invitation, array $answers): SurveyResponse {
        if (! $invitation->isUsable()) {
            throw new RuntimeException((string) __('Dieser Umfrage-Link ist abgelaufen oder bereits verwendet.'));
        }

        /** @var Survey $survey */
        $survey = $invitation->survey()->withoutGlobalScopes()->firstOrFail();

        // Erst prüfen, dann schreiben (Vollscan 2026-08-23, E1): eine
        // Pflichtverletzung darf keine Antwort-Leiche hinterlassen, und
        // Bereichsverletzungen (NPS 999) dürfen den Score nicht verfälschen.
        $rows = [];
        foreach ($survey->questions()->withoutGlobalScopes()->get() as $question) {
            $value = $answers[$question->id] ?? null;
            if (is_array($value)) {
                throw new RuntimeException((string) __('Ungültige Antwort auf „:label".', ['label' => $question->label]));
            }
            if ($value === null || $value === '') {
                if ($question->required) {
                    throw new RuntimeException((string) __('Die Frage „:label" ist eine Pflichtfrage.', ['label' => $question->label]));
                }

                continue;
            }

            $isText = in_array($question->type, ['text', 'choice'], true);
            $int = $isText ? null : (int) $value;
            if (($question->type === 'nps' && ($int < 0 || $int > 10))
                || ($question->type === 'scale' && ($int < 1 || $int > 5))
                || ($question->type === 'choice' && ! in_array((string) $value, $question->options ?? [], true))) {
                throw new RuntimeException((string) __('Ungültige Antwort auf „:label".', ['label' => $question->label]));
            }

            $rows[] = [
                'survey_question_id' => $question->id,
                'value_int' => $int,
                'value_text' => $isText ? (string) $value : null,
            ];
        }

        return DB::transaction(function () use ($invitation, $survey, $rows): SurveyResponse {
            // Atomarer Claim: nur EIN Submit gewinnt das Statuswechsel-Update;
            // der zweite sieht 0 Zeilen und legt keine Doppel-Antwort an.
            $claimed = SurveyInvitation::query()
                ->withoutGlobalScopes()
                ->whereKey($invitation->id)
                ->where('status', '!=', SurveyInvitation::STATUS_RESPONDED)
                ->update([
                    'status' => SurveyInvitation::STATUS_RESPONDED,
                    // Anonym: kein Antwortzeitpunkt - kein Join-Feld zur Antwort.
                    'responded_at' => $survey->anonymous ? null : Carbon::now(),
                ]);
            if ($claimed === 0) {
                throw new RuntimeException((string) __('Dieser Umfrage-Link ist abgelaufen oder bereits verwendet.'));
            }

            $response = SurveyResponse::query()->create([
                'organization_id' => $invitation->organization_id,
                'survey_id' => $survey->id,
                // Anonym: KEIN Einladungsbezug an der Antwort.
                'survey_invitation_id' => $survey->anonymous ? null : $invitation->id,
                'context_kind' => $invitation->context_kind,
            ]);

            foreach ($rows as $row) {
                SurveyAnswer::query()->create($row + [
                    'organization_id' => $invitation->organization_id,
                    'survey_response_id' => $response->id,
                ]);
            }

            return $response;
        });
    }

    /**
     * NPS-Score einer Umfrage: %Promotoren (9–10) − %Detraktoren (0–6),
     * über die erste NPS-Frage. `null`, wenn es nichts zu rechnen gibt —
     * eine Null wäre ein neutraler Score, kein fehlender.
     */
    public function npsScore(Survey $survey): ?int {
        $question = $survey->questions()->where('type', 'nps')->first();
        if ($question === null) {
            return null;
        }

        $values = SurveyAnswer::query()
            ->where('survey_question_id', $question->id)
            ->whereNotNull('value_int')
            ->whereBetween('value_int', [0, 10])
            ->pluck('value_int');
        if ($values->isEmpty()) {
            return null;
        }

        $total = $values->count();
        $promoters = $values->filter(fn (int $v): bool => $v >= 9)->count();
        $detractors = $values->filter(fn (int $v): bool => $v <= 6)->count();

        return (int) round(($promoters - $detractors) / $total * 100);
    }
}
