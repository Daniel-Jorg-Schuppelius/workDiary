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

use App\Enums\Survey\SurveyInvitationStatus;
use App\Mail\SurveyInvitationMail;
use App\Models\Customer\Customer;
use App\Models\Survey\{Survey, SurveyAnswer, SurveyInvitation, SurveyResponse};
use App\Services\Fields\{FieldSchema, FieldValidator};
use CommonToolkit\Helper\Data\{CryptoHelper, EmailHelper};
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\{DB, Log, Mail, Validator};
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
    public function __construct(private readonly FieldValidator $fieldValidator) {}

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
            'email' => EmailHelper::normalize($email),
            'context_kind' => $contextKind,
            'token_hash' => CryptoHelper::hash($token),
            'expires_at' => Carbon::now()->addDays(30),
            'sent_at' => Carbon::now(),
            'status' => SurveyInvitationStatus::Sent,
        ]);

        $survey->audit('survey.invited', ['context' => $contextKind]);

        return ['invitation' => $invitation, 'token' => $token];
    }

    /** Greift der Ermüdungs-Deckel für diese Adresse noch nicht? */
    public function fatigueAllows(Survey $survey, string $email): bool {
        return ! SurveyInvitation::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $survey->organization_id)
            ->where('email', EmailHelper::normalize($email))
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

            // Form und Bereich je Fragetyp aus dem Feldschema-Baustein (MVP-867).
            $definition = $question->fieldDefinition();
            $schema = new FieldSchema([$definition]);
            if (Validator::make([$definition->key => $value], $this->fieldValidator->rules($schema, ''))->fails()) {
                throw new RuntimeException((string) __('Ungültige Antwort auf „:label".', ['label' => $question->label]));
            }
            $isText = $question->questionType()->bounds() === null;
            $int = $isText ? null : (int) $value;

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
                ->where('status', '!=', SurveyInvitationStatus::Responded)
                ->update([
                    'status' => SurveyInvitationStatus::Responded,
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

    /** Auslöser-Spalten am Fragebogen (Ticket, Kurs, Auftrag). */
    public const TRIGGERS = ['trigger_on_ticket_close', 'trigger_on_course_completion', 'trigger_on_order_completion'];

    /**
     * Lädt zu allen aktiven Fragebögen mit diesem Auslöser ein (MVP-1293).
     * Opt-out und Ermüdungsschutz lehnen still ab — der Deckel ist kein Fehler.
     *
     * @param  (\Closure(SurveyInvitation): void)|null  $beforeMail  ergänzt die Einladung vor dem Versand
     * @return int versandte Einladungen
     */
    public function inviteByTrigger(int $organizationId, string $trigger, string $email, ?Customer $customer, string $context, ?\Closure $beforeMail = null): int {
        if (! in_array($trigger, self::TRIGGERS, true) || trim($email) === '') {
            return 0;
        }

        $sent = 0;
        $surveys = Survey::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->where($trigger, true)
            ->get();
        foreach ($surveys as $survey) {
            $sent += $this->inviteAndMail($survey, trim($email), $customer, $context, $beforeMail) ? 1 : 0;
        }

        return $sent;
    }

    /**
     * Fragebögen im festen Abstand (MVP-1293): Kunden mit abgeschlossenem
     * Auftrag oder gelöstem Ticket in diesem Abstand, die den Fragebogen im
     * Abstand noch nicht bekommen haben.
     *
     * @return int versandte Einladungen
     */
    public function sendScheduled(): int {
        $sent = 0;
        $surveys = Survey::query()->withoutGlobalScopes()
            ->where('active', true)
            ->where('trigger_schedule_days', '>', 0)
            ->get();
        foreach ($surveys as $survey) {
            $since = Carbon::now()->subDays((int) $survey->trigger_schedule_days);
            $customers = Customer::query()->withoutGlobalScopes()
                ->where('organization_id', $survey->organization_id)
                ->whereNotNull('email')->where('email', '!=', '')
                ->where('survey_opt_out', false)
                ->where(fn ($q) => $q
                    ->whereIn('id', DB::table('diary_entries')->where('organization_id', $survey->organization_id)->where('completed_at', '>=', $since)->whereNotNull('customer_id')->select('customer_id'))
                    ->orWhereIn('id', DB::table('service_tickets')->where('organization_id', $survey->organization_id)->where('resolved_at', '>=', $since)->whereNotNull('customer_id')->select('customer_id')))
                ->whereNotIn('id', SurveyInvitation::query()->withoutGlobalScopes()->where('survey_id', $survey->id)->where('sent_at', '>=', $since)->whereNotNull('customer_id')->select('customer_id'))
                ->get();
            foreach ($customers as $customer) {
                $sent += $this->inviteAndMail($survey, (string) $customer->email, $customer, 'schedule') ? 1 : 0;
            }
        }

        return $sent;
    }

    /** @param  (\Closure(SurveyInvitation): void)|null  $beforeMail */
    private function inviteAndMail(Survey $survey, string $email, ?Customer $customer, string $context, ?\Closure $beforeMail = null): bool {
        try {
            $issued = $this->invite($survey, $email, $customer, $context);
            if ($beforeMail !== null) {
                $beforeMail($issued['invitation']);
            }
            Mail::to($email)->send(new SurveyInvitationMail($survey, $issued['token']));

            return true;
        } catch (RuntimeException) {
            return false;
        } catch (\Throwable $e) {
            Log::warning('Umfrage-Einladung fehlgeschlagen.', ['survey_id' => $survey->id, 'context' => $context, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * NPS je Monat (MVP-1294) über die erste NPS-Frage, gezählt nach Eingang der
     * Antwort; Monate ohne Antwort tragen `score` null.
     *
     * @return list<array{month: string, score: int|null, count: int}>
     */
    public function npsByMonth(Survey $survey, int $months = 12): array {
        $question = $survey->questions()->where('type', 'nps')->first();
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);
        $values = $question === null ? collect() : SurveyAnswer::query()
            ->where('survey_question_id', $question->id)
            ->whereNotNull('value_int')
            ->whereBetween('value_int', [0, 10])
            ->where('created_at', '>=', $start)
            ->get(['value_int', 'created_at'])
            ->groupBy(fn (SurveyAnswer $answer): string => $answer->created_at?->format('Y-m') ?? '');

        $rows = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i)->format('Y-m');
            $scores = $values->get($month, collect())->pluck('value_int');
            $count = $scores->count();
            $rows[] = [
                'month' => $month,
                'score' => $count > 0 ? (int) round(($scores->filter(fn ($v): bool => $v >= 9)->count() - $scores->filter(fn ($v): bool => $v <= 6)->count()) / $count * 100) : null,
                'count' => $count,
            ];
        }

        return $rows;
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

        return self::nps(SurveyAnswer::query()
            ->where('survey_question_id', $question->id)
            ->whereNotNull('value_int')
            ->whereBetween('value_int', [0, 10])
            ->pluck('value_int')
            ->map(static fn ($v): int => (int) $v)
            ->all());
    }

    /**
     * %Promotoren (9–10) − %Detraktoren (0–6) über NPS-Antworten 0–10;
     * `null` ohne Antworten.
     *
     * @param  array<int>  $values
     */
    public static function nps(array $values): ?int {
        if ($values === []) {
            return null;
        }
        $promoters = count(array_filter($values, static fn (int $v): bool => $v >= 9));
        $detractors = count(array_filter($values, static fn (int $v): bool => $v <= 6));

        return (int) round(($promoters - $detractors) / count($values) * 100);
    }
}
